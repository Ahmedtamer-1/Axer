<?php

namespace Lume\Template;

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
        $ast = [];
        
        while (!$this->isEof()) {
            $token = $this->current();
            
            if ($token['type'] === Lexer::TOKEN_TEXT) {
                $ast[] = [
                    'type' => 'Text',
                    'value' => $token['value']
                ];
                $this->next();
            } elseif ($token['type'] === Lexer::TOKEN_VAR_START) {
                $this->next();
                $ast[] = $this->parseVariable();
            } elseif ($token['type'] === Lexer::TOKEN_BLOCK_START) {
                $this->next();
                $node = $this->parseBlock();
                if ($node) {
                    $ast[] = $node;
                }
            } else {
                $this->next();
            }
        }
        
        return $ast;
    }

    protected function parseVariable(): array
    {
        $expr = $this->parseExpression();
        
        $filters = [];
        while ($this->current()['value'] === '|') {
            $this->next(); // skip |
            $filters[] = $this->parseFilter();
        }
        
        if ($this->current()['type'] === Lexer::TOKEN_VAR_END) {
            $this->next();
        }
        
        return [
            'type' => 'Print',
            'expr' => $expr,
            'filters' => $filters
        ];
    }

    protected function parseFilter(): array
    {
        $name = $this->current()['value'];
        $this->next();
        
        $args = [];
        if ($this->current()['value'] === ':') {
            $this->next(); // skip :
            
            while ($this->current()['type'] !== Lexer::TOKEN_VAR_END && $this->current()['value'] !== '|') {
                $args[] = $this->parseExpression();
                if ($this->current()['value'] === ',') {
                    $this->next(); // skip ,
                } else {
                    break;
                }
            }
        }
        
        return [
            'name' => $name,
            'args' => $args
        ];
    }

    protected function parseBlock(): ?array
    {
        $name = $this->current()['value'];
        $this->next();
        
        $node = [
            'type' => 'Tag',
            'name' => $name,
            'body' => []
        ];
        
        // Very basic parsing for Phase 1/3 demo purposes
        // Real parser would handle if/for/sections individually
        
        if ($name === 'if') {
            $node['expr'] = $this->parseExpression();
            if ($this->current()['type'] === Lexer::TOKEN_BLOCK_END) $this->next();
            $node['body'] = $this->parseUntil(['endif']);
            if ($this->current()['value'] === 'endif') {
                $this->next(); // skip endif
                if ($this->current()['type'] === Lexer::TOKEN_BLOCK_END) $this->next();
            }
        } elseif ($name === 'for') {
            $node['var'] = $this->current()['value'];
            $this->next(); // var
            if ($this->current()['value'] === 'in') $this->next();
            $node['expr'] = $this->parseExpression();
            if ($this->current()['type'] === Lexer::TOKEN_BLOCK_END) $this->next();
            $node['body'] = $this->parseUntil(['endfor']);
            if ($this->current()['value'] === 'endfor') {
                $this->next(); // skip endfor
                if ($this->current()['type'] === Lexer::TOKEN_BLOCK_END) $this->next();
            }
        } elseif ($name === 'section') {
            $node['expr'] = $this->parseExpression();
            if ($this->current()['type'] === Lexer::TOKEN_BLOCK_END) $this->next();
        } else {
            // consume until end of tag
            while ($this->current()['type'] !== Lexer::TOKEN_BLOCK_END && !$this->isEof()) {
                $this->next();
            }
            if ($this->current()['type'] === Lexer::TOKEN_BLOCK_END) $this->next();
        }
        
        return $node;
    }

    protected function parseUntil(array $tags): array
    {
        $body = [];
        while (!$this->isEof()) {
            if ($this->current()['type'] === Lexer::TOKEN_BLOCK_START) {
                $lookahead = $this->tokens[$this->cursor + 1] ?? null;
                if ($lookahead && in_array($lookahead['value'], $tags)) {
                    break;
                }
            }
            
            $token = $this->current();
            if ($token['type'] === Lexer::TOKEN_TEXT) {
                $body[] = ['type' => 'Text', 'value' => $token['value']];
                $this->next();
            } elseif ($token['type'] === Lexer::TOKEN_VAR_START) {
                $this->next();
                $body[] = $this->parseVariable();
            } elseif ($token['type'] === Lexer::TOKEN_BLOCK_START) {
                $this->next();
                $body[] = $this->parseBlock();
            } else {
                $this->next();
            }
        }
        return array_filter($body);
    }

    protected function parseExpression(): array
    {
        // Simplistic expression parsing
        $token = $this->current();
        if ($token['type'] === Lexer::TOKEN_NAME) {
            $this->next();
            return ['type' => 'Var', 'value' => $token['value']];
        } elseif ($token['type'] === Lexer::TOKEN_STRING) {
            $this->next();
            return ['type' => 'String', 'value' => $token['value']];
        } elseif ($token['type'] === Lexer::TOKEN_NUMBER) {
            $this->next();
            return ['type' => 'Number', 'value' => $token['value']];
        }
        
        $this->next();
        return ['type' => 'Unknown', 'value' => $token['value'] ?? ''];
    }

    protected function current(): array
    {
        return $this->tokens[$this->cursor] ?? ['type' => Lexer::TOKEN_EOF, 'value' => ''];
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
