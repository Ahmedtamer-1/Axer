<?php

namespace Axer\Template;

class Compiler
{
    protected Sandbox $sandbox;

    public function __construct(Sandbox $sandbox)
    {
        $this->sandbox = $sandbox;
    }

    public function compile(array $ast): string
    {
        $code = "<?php\n";
        foreach ($ast as $node) {
            $code .= $this->compileNode($node);
        }
        return $code;
    }

    protected function compileNode(array $node): string
    {
        if ($node['type'] === 'Text') {
            return "echo " . var_export($node['value'], true) . ";\n";
        } elseif ($node['type'] === 'Print') {
            $expr = $this->compileExpression($node['expr']);
            $hasRaw = false;
            foreach ($node['filters'] as $filter) {
                if ($filter['name'] === 'raw') {
                    $hasRaw = true;
                    continue; // Skip calling raw filter as it's handled intrinsically
                }
                $args = array_map([$this, 'compileExpression'], $filter['args']);
                $argsStr = empty($args) ? '' : ', ' . implode(', ', $args);
                $expr = "\$this->sandbox->callFilter('{$filter['name']}', {$expr}{$argsStr})";
            }
            if ($hasRaw) {
                return "echo (string) ({$expr});\n";
            }
            return "echo htmlspecialchars((string) ({$expr}), ENT_QUOTES, 'UTF-8');\n";
        } elseif ($node['type'] === 'Tag') {
            return $this->compileTag($node);
        }
        return "";
    }

    protected function compileTag(array $node): string
    {
        if ($node['name'] === 'if') {
            $expr = $this->compileExpression($node['expr']);
            $body = "";
            foreach ($node['body'] as $child) {
                $body .= $this->compileNode($child);
            }
            return "if ({$expr}) {\n{$body}}\n";
        } elseif ($node['name'] === 'for') {
            $var = $node['var'];
            $expr = $this->compileExpression($node['expr']);
            $body = "";
            foreach ($node['body'] as $child) {
                $body .= $this->compileNode($child);
            }
            return "foreach ({$expr} as \${$var}) {\n{$body}}\n";
        } elseif ($node['name'] === 'section') {
            $expr = $this->compileExpression($node['expr']);
            return "echo \$this->sandbox->callTag('section', [{$expr}]);\n";
        }
        return "";
    }

    protected function compileExpression(array $expr): string
    {
        if ($expr['type'] === 'String') {
            return var_export($expr['value'], true);
        } elseif ($expr['type'] === 'Number') {
            return $expr['value'];
        } elseif ($expr['type'] === 'Var') {
            $parts = explode('.', $expr['value']);
            $base = array_shift($parts);
            $compiled = "\${$base}";
            foreach ($parts as $part) {
                $compiled .= "['{$part}']";
            }
            return "\$this->sandbox->resolve({$compiled} ?? null, '{$expr['value']}')";
        }
        return "''";
    }
}
