<?php

namespace Axer\Template;

class Lexer
{
    const TOKEN_TEXT = 'text';
    const TOKEN_VAR_START = 'var_start';
    const TOKEN_VAR_END = 'var_end';
    const TOKEN_BLOCK_START = 'block_start';
    const TOKEN_BLOCK_END = 'block_end';
    const TOKEN_NAME = 'name';
    const TOKEN_NUMBER = 'number';
    const TOKEN_STRING = 'string';
    const TOKEN_OPERATOR = 'operator';
    const TOKEN_PUNCTUATION = 'punctuation';
    const TOKEN_EOF = 'eof';

    /**
     * Multi-character operators, longest first so that ">=" is not read as
     * ">" followed by "=".
     */
    private const OPERATORS = ['===', '!==', '==', '!=', '<>', '>=', '<=', '&&', '||', '>', '<', '!'];

    public function tokenize(string $source): array
    {
        $tokens = [];
        $cursor = 0;
        $length = strlen($source);
        $line = 1;

        while ($cursor < $length) {
            $varPos = strpos($source, '{{', $cursor);
            $blockPos = strpos($source, '{%', $cursor);
            $commentPos = strpos($source, '{#', $cursor);

            $nextTag = min(
                $varPos === false ? $length : $varPos,
                $blockPos === false ? $length : $blockPos,
                $commentPos === false ? $length : $commentPos
            );

            if ($nextTag > $cursor) {
                $text = substr($source, $cursor, $nextTag - $cursor);
                $tokens[] = ['type' => self::TOKEN_TEXT, 'value' => $text, 'line' => $line];
                $line += substr_count($text, "\n");
                $cursor = $nextTag;
            }

            if ($cursor >= $length) {
                break;
            }

            if ($commentPos === $cursor) {
                $end = strpos($source, '#}', $cursor);
                $skipped = substr($source, $cursor, ($end === false ? $length : $end + 2) - $cursor);
                $line += substr_count($skipped, "\n");
                $cursor = $end !== false ? $end + 2 : $length;
                continue;
            }

            $isVar = $varPos === $cursor;
            $tokens[] = [
                'type' => $isVar ? self::TOKEN_VAR_START : self::TOKEN_BLOCK_START,
                'value' => $isVar ? '{{' : '{%',
                'line' => $line,
            ];
            $cursor += 2;

            $cursor = $this->tokenizeInside($source, $cursor, $length, $isVar ? '}}' : '%}', $tokens, $line);
        }

        $tokens[] = ['type' => self::TOKEN_EOF, 'value' => '', 'line' => $line];

        return $tokens;
    }

    /**
     * Consume the inside of a {{ }} or {% %} tag.
     */
    protected function tokenizeInside(
        string $source,
        int $cursor,
        int $length,
        string $endToken,
        array &$tokens,
        int &$line
    ): int {
        while ($cursor < $length) {
            $char = $source[$cursor];

            if (substr($source, $cursor, 2) === $endToken) {
                $tokens[] = [
                    'type' => $endToken === '}}' ? self::TOKEN_VAR_END : self::TOKEN_BLOCK_END,
                    'value' => $endToken,
                    'line' => $line,
                ];

                return $cursor + 2;
            }

            if ($char === "\n") {
                $line++;
                $cursor++;
                continue;
            }

            if (ctype_space($char)) {
                $cursor++;
                continue;
            }

            // Identifiers and dotted paths (product.image.url).
            if (ctype_alpha($char) || $char === '_') {
                $start = $cursor;

                while ($cursor < $length
                    && (ctype_alnum($source[$cursor]) || $source[$cursor] === '_' || $source[$cursor] === '.')) {
                    $cursor++;
                }

                $word = substr($source, $start, $cursor - $start);

                // "and"/"or"/"not" read better than &&/||/! in templates.
                $keywordOperators = ['and' => '&&', 'or' => '||', 'not' => '!'];
                $lower = strtolower($word);

                if (isset($keywordOperators[$lower])) {
                    $tokens[] = ['type' => self::TOKEN_OPERATOR, 'value' => $keywordOperators[$lower], 'line' => $line];
                } else {
                    $tokens[] = ['type' => self::TOKEN_NAME, 'value' => $word, 'line' => $line];
                }

                continue;
            }

            if (ctype_digit($char)) {
                $start = $cursor;

                while ($cursor < $length && (ctype_digit($source[$cursor]) || $source[$cursor] === '.')) {
                    $cursor++;
                }

                $tokens[] = [
                    'type' => self::TOKEN_NUMBER,
                    'value' => substr($source, $start, $cursor - $start),
                    'line' => $line,
                ];

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $cursor++;
                $buffer = '';

                while ($cursor < $length && $source[$cursor] !== $quote) {
                    if ($source[$cursor] === '\\' && $cursor + 1 < $length) {
                        // Keep the escaped character, drop the backslash, so
                        // "it\'s" yields "it's" rather than "it\'s".
                        $cursor++;
                        $buffer .= $source[$cursor];
                    } else {
                        $buffer .= $source[$cursor];
                    }

                    $cursor++;
                }

                $tokens[] = ['type' => self::TOKEN_STRING, 'value' => $buffer, 'line' => $line];
                $cursor++;
                continue;
            }

            // Operators, longest match first.
            $matched = null;

            foreach (self::OPERATORS as $operator) {
                if (substr($source, $cursor, strlen($operator)) === $operator) {
                    $matched = $operator;
                    break;
                }
            }

            if ($matched !== null) {
                $tokens[] = ['type' => self::TOKEN_OPERATOR, 'value' => $matched, 'line' => $line];
                $cursor += strlen($matched);
                continue;
            }

            if (str_contains('(),[]|:', $char)) {
                $tokens[] = ['type' => self::TOKEN_PUNCTUATION, 'value' => $char, 'line' => $line];
                $cursor++;
                continue;
            }

            // Unknown character — skip it rather than looping forever.
            $cursor++;
        }

        return $cursor;
    }
}
