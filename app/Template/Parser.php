<?php

namespace Axer\Template;

class Parser
{
    protected array $tokens;
    protected int $cursor = 0;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public function parse(): array
    {
        return $this->parseNodes([]);
    }

    /**
     * Parse until one of $stopAt block names is reached (not consumed).
     */
    protected function parseNodes(array $stopAt): array
    {
        $nodes = [];

        while (!$this->isEof()) {
            $token = $this->current();

            if ($token['type'] === Lexer::TOKEN_BLOCK_START && $stopAt !== []) {
                $lookahead = $this->tokens[$this->cursor + 1] ?? null;

                if ($lookahead && in_array(strtolower((string) $lookahead['value']), $stopAt, true)) {
                    break;
                }
            }

            if ($token['type'] === Lexer::TOKEN_TEXT) {
                $nodes[] = ['type' => 'Text', 'value' => $token['value']];
                $this->next();
            } elseif ($token['type'] === Lexer::TOKEN_VAR_START) {
                $this->next();
                $nodes[] = $this->parseVariable();
            } elseif ($token['type'] === Lexer::TOKEN_BLOCK_START) {
                $this->next();
                $node = $this->parseBlock();

                if ($node !== null) {
                    $nodes[] = $node;
                }
            } else {
                $this->next();
            }
        }

        return $nodes;
    }

    protected function parseVariable(): array
    {
        $expr = $this->parseExpression();
        $filters = $this->parseFilterChain();

        if ($this->current()['type'] === Lexer::TOKEN_VAR_END) {
            $this->next();
        }

        return [
            'type' => 'Print',
            'expr' => $expr,
            'filters' => $filters,
        ];
    }

    /**
     * `| name`, `| name(arg, arg)` and the legacy `| name: arg` form.
     *
     * Only the colon form used to be understood, so every theme that wrote
     * `{{ value | default('#fff') }}` silently lost its default and
     * rendered an empty string.
     */
    protected function parseFilterChain(): array
    {
        $filters = [];

        while ($this->current()['value'] === '|' && !$this->isEof()) {
            $this->next(); // consume |

            if ($this->current()['type'] !== Lexer::TOKEN_NAME) {
                break;
            }

            $name = $this->current()['value'];
            $this->next();

            $args = [];

            if ($this->current()['value'] === '(') {
                $this->next(); // consume (

                while (!$this->isEof() && $this->current()['value'] !== ')') {
                    $args[] = $this->parseExpression();

                    if ($this->current()['value'] === ',') {
                        $this->next();
                        continue;
                    }

                    break;
                }

                if ($this->current()['value'] === ')') {
                    $this->next();
                }
            } elseif ($this->current()['value'] === ':') {
                $this->next(); // consume :

                while (!$this->isEof()
                    && $this->current()['type'] !== Lexer::TOKEN_VAR_END
                    && $this->current()['type'] !== Lexer::TOKEN_BLOCK_END
                    && $this->current()['value'] !== '|') {
                    $args[] = $this->parseExpression();

                    if ($this->current()['value'] === ',') {
                        $this->next();
                        continue;
                    }

                    break;
                }
            }

            $filters[] = ['name' => $name, 'args' => $args];
        }

        return $filters;
    }

    protected function parseBlock(): ?array
    {
        $name = strtolower((string) $this->current()['value']);
        $this->next();

        if ($name === 'if') {
            return $this->parseIf();
        }

        if ($name === 'for') {
            return $this->parseFor();
        }

        if ($name === 'section') {
            $expr = $this->parseExpression();
            $this->consumeBlockEnd();

            return ['type' => 'Tag', 'name' => 'section', 'expr' => $expr];
        }

        // Unknown tag: consume it so it does not derail the rest of the file.
        while (!$this->isEof() && $this->current()['type'] !== Lexer::TOKEN_BLOCK_END) {
            $this->next();
        }

        $this->consumeBlockEnd();

        return null;
    }

    /**
     * {% if %} / {% elseif %} / {% else %} / {% endif %}
     *
     * elseif and else were not implemented at all. parseUntil() stopped
     * only at `endif`, so an {% else %} was parsed as an unknown tag that
     * compiled to nothing — and its body was emitted as part of the
     * "true" branch. Both arms of every conditional rendered.
     */
    protected function parseIf(): array
    {
        $expr = $this->parseExpression();
        $this->consumeBlockEnd();

        $branches = [];
        $else = [];

        $body = $this->parseNodes(['elseif', 'elif', 'else', 'endif']);
        $branches[] = ['expr' => $expr, 'body' => $body];

        while (!$this->isEof()) {
            $keyword = strtolower((string) ($this->tokens[$this->cursor + 1]['value'] ?? ''));

            if ($this->current()['type'] !== Lexer::TOKEN_BLOCK_START) {
                break;
            }

            if ($keyword === 'elseif' || $keyword === 'elif') {
                $this->next(); // {%
                $this->next(); // elseif

                $branchExpr = $this->parseExpression();
                $this->consumeBlockEnd();

                $branches[] = [
                    'expr' => $branchExpr,
                    'body' => $this->parseNodes(['elseif', 'elif', 'else', 'endif']),
                ];

                continue;
            }

            if ($keyword === 'else') {
                $this->next(); // {%
                $this->next(); // else
                $this->consumeBlockEnd();

                $else = $this->parseNodes(['endif']);
                continue;
            }

            break;
        }

        $this->consumeClosingTag('endif');

        return [
            'type' => 'Tag',
            'name' => 'if',
            'branches' => $branches,
            'else' => $else,
        ];
    }

    /**
     * {% for item in items %} ... {% else %} ... {% endfor %}
     *
     * The else arm renders when the collection is empty, which is what
     * every product grid in the themes needs for its empty state.
     */
    protected function parseFor(): array
    {
        $var = (string) $this->current()['value'];
        $this->next();

        $keyVar = null;

        // {% for key, value in map %}
        if ($this->current()['value'] === ',') {
            $this->next();
            $keyVar = $var;
            $var = (string) $this->current()['value'];
            $this->next();
        }

        if (strtolower((string) $this->current()['value']) === 'in') {
            $this->next();
        }

        $expr = $this->parseExpression();
        $this->consumeBlockEnd();

        $body = $this->parseNodes(['else', 'endfor']);
        $else = [];

        if ($this->current()['type'] === Lexer::TOKEN_BLOCK_START
            && strtolower((string) ($this->tokens[$this->cursor + 1]['value'] ?? '')) === 'else') {
            $this->next();
            $this->next();
            $this->consumeBlockEnd();

            $else = $this->parseNodes(['endfor']);
        }

        $this->consumeClosingTag('endfor');

        return [
            'type' => 'Tag',
            'name' => 'for',
            'var' => $var,
            'key' => $keyVar,
            'expr' => $expr,
            'body' => $body,
            'else' => $else,
        ];
    }

    protected function consumeBlockEnd(): void
    {
        if ($this->current()['type'] === Lexer::TOKEN_BLOCK_END) {
            $this->next();
        }
    }

    protected function consumeClosingTag(string $name): void
    {
        if ($this->current()['type'] === Lexer::TOKEN_BLOCK_START) {
            $this->next();
        }

        if (strtolower((string) $this->current()['value']) === $name) {
            $this->next();
        }

        $this->consumeBlockEnd();
    }

    /**
     * Expression parsing with precedence:
     *   ||  <  &&  <  comparison  <  unary  <  primary
     *
     * Previously this read a single token, so `{% if position == 'left' %}`
     * evaluated only `position` and the `== 'left'` was discarded — the
     * condition was true whenever the variable was merely non-empty.
     */
    protected function parseExpression(): array
    {
        return $this->parseOr();
    }

    protected function parseOr(): array
    {
        $left = $this->parseAnd();

        while ($this->current()['value'] === '||' && $this->current()['type'] === Lexer::TOKEN_OPERATOR) {
            $this->next();
            $left = ['type' => 'Binary', 'operator' => '||', 'left' => $left, 'right' => $this->parseAnd()];
        }

        return $left;
    }

    protected function parseAnd(): array
    {
        $left = $this->parseComparison();

        while ($this->current()['value'] === '&&' && $this->current()['type'] === Lexer::TOKEN_OPERATOR) {
            $this->next();
            $left = ['type' => 'Binary', 'operator' => '&&', 'left' => $left, 'right' => $this->parseComparison()];
        }

        return $left;
    }

    protected function parseComparison(): array
    {
        $left = $this->parseUnary();

        $comparisons = ['==', '===', '!=', '!==', '<>', '>', '<', '>=', '<='];

        while ($this->current()['type'] === Lexer::TOKEN_OPERATOR
            && in_array($this->current()['value'], $comparisons, true)) {
            $operator = $this->current()['value'];
            $this->next();

            $left = [
                'type' => 'Binary',
                'operator' => $operator === '<>' ? '!=' : $operator,
                'left' => $left,
                'right' => $this->parseUnary(),
            ];
        }

        return $left;
    }

    protected function parseUnary(): array
    {
        if ($this->current()['type'] === Lexer::TOKEN_OPERATOR && $this->current()['value'] === '!') {
            $this->next();

            return ['type' => 'Unary', 'operator' => '!', 'expr' => $this->parseUnary()];
        }

        return $this->parsePrimary();
    }

    protected function parsePrimary(): array
    {
        $token = $this->current();

        if ($token['value'] === '(' && $token['type'] === Lexer::TOKEN_PUNCTUATION) {
            $this->next();
            $expr = $this->parseExpression();

            if ($this->current()['value'] === ')') {
                $this->next();
            }

            return $expr;
        }

        if ($token['type'] === Lexer::TOKEN_NAME) {
            $this->next();

            $lower = strtolower((string) $token['value']);

            if ($lower === 'true' || $lower === 'false') {
                return ['type' => 'Bool', 'value' => $lower === 'true'];
            }

            if ($lower === 'null' || $lower === 'nil') {
                return ['type' => 'Null'];
            }

            return ['type' => 'Var', 'value' => $token['value']];
        }

        if ($token['type'] === Lexer::TOKEN_STRING) {
            $this->next();

            return ['type' => 'String', 'value' => $token['value']];
        }

        if ($token['type'] === Lexer::TOKEN_NUMBER) {
            $this->next();

            return ['type' => 'Number', 'value' => $token['value']];
        }

        $this->next();

        return ['type' => 'Null'];
    }

    protected function current(): array
    {
        return $this->tokens[$this->cursor] ?? ['type' => Lexer::TOKEN_EOF, 'value' => '', 'line' => 0];
    }

    protected function next(): void
    {
        $this->cursor++;
    }

    protected function isEof(): bool
    {
        return $this->current()['type'] === Lexer::TOKEN_EOF;
    }
}
