<?php

namespace Lume\Template;

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

    public function tokenize(string $source): array
    {
        $tokens = [];
        $cursor = 0;
        $length = strlen($source);
        $state = 'text';

        while ($cursor < $length) {
            if ($state === 'text') {
                $varPos = strpos($source, '{{', $cursor);
                $blockPos = strpos($source, '{%', $cursor);
                $commentPos = strpos($source, '{#', $cursor);

                $nextTag = min(
                    $varPos === false ? $length : $varPos,
                    $blockPos === false ? $length : $blockPos,
                    $commentPos === false ? $length : $commentPos
                );

                if ($nextTag > $cursor) {
                    $tokens[] = ['type' => self::TOKEN_TEXT, 'value' => substr($source, $cursor, $nextTag - $cursor)];
                    $cursor = $nextTag;
                }

                if ($cursor >= $length) {
                    break;
                }

                if ($commentPos === $cursor) {
                    $endComment = strpos($source, '#}', $cursor);
                    $cursor = $endComment !== false ? $endComment + 2 : $length;
                } elseif ($varPos === $cursor) {
                    $tokens[] = ['type' => self::TOKEN_VAR_START, 'value' => '{{'];
                    $cursor += 2;
                    $state = 'var';
                } elseif ($blockPos === $cursor) {
                    $tokens[] = ['type' => self::TOKEN_BLOCK_START, 'value' => '{%'];
                    $cursor += 2;
                    $state = 'block';
                }
            } else {
                // Parse inside tags
                $endToken = $state === 'var' ? '}}' : '%}';
                
                while ($cursor < $length) {
                    $char = $source[$cursor];
                    
                    if (substr($source, $cursor, 2) === $endToken) {
                        $tokens[] = ['type' => $state === 'var' ? self::TOKEN_VAR_END : self::TOKEN_BLOCK_END, 'value' => $endToken];
                        $cursor += 2;
                        $state = 'text';
                        break;
                    }
                    
                    if (ctype_space($char)) {
                        $cursor++;
                        continue;
                    }
                    
                    if (ctype_alpha($char) || $char === '_') {
                        $start = $cursor;
                        while ($cursor < $length && (ctype_alnum($source[$cursor]) || $source[$cursor] === '_' || $source[$cursor] === '.')) {
                            $cursor++;
                        }
                        $tokens[] = ['type' => self::TOKEN_NAME, 'value' => substr($source, $start, $cursor - $start)];
                        continue;
                    }
                    
                    if (ctype_digit($char)) {
                        $start = $cursor;
                        while ($cursor < $length && (ctype_digit($source[$cursor]) || $source[$cursor] === '.')) {
                            $cursor++;
                        }
                        $tokens[] = ['type' => self::TOKEN_NUMBER, 'value' => substr($source, $start, $cursor - $start)];
                        continue;
                    }
                    
                    if ($char === '"' || $char === "'") {
                        $quote = $char;
                        $cursor++;
                        $start = $cursor;
                        while ($cursor < $length && $source[$cursor] !== $quote) {
                            if ($source[$cursor] === '\\') $cursor++;
                            $cursor++;
                        }
                        $tokens[] = ['type' => self::TOKEN_STRING, 'value' => substr($source, $start, $cursor - $start)];
                        $cursor++;
                        continue;
                    }
                    
                    if (strpos('!=<>&|+-*/%:', $char) !== false) {
                        if ($cursor + 1 < $length && strpos('!=<>&|', $char . $source[$cursor + 1]) !== false) {
                            $tokens[] = ['type' => self::TOKEN_OPERATOR, 'value' => $char . $source[$cursor + 1]];
                            $cursor += 2;
                        } else {
                            $tokens[] = ['type' => self::TOKEN_OPERATOR, 'value' => $char];
                            $cursor++;
                        }
                        continue;
                    }
                    
                    if (strpos('(),[]|', $char) !== false) {
                        $tokens[] = ['type' => self::TOKEN_PUNCTUATION, 'value' => $char];
                        $cursor++;
                        continue;
                    }
                    
                    // Fallback
                    $cursor++;
                }
            }
        }

        $tokens[] = ['type' => self::TOKEN_EOF, 'value' => ''];
        return $tokens;
    }
}
