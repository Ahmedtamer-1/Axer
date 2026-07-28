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
        $code .= "/* Compiled by Axer LumeScript. Do not edit — regenerated automatically. */\n";

        foreach ($ast as $node) {
            $code .= $this->compileNode($node);
        }

        return $code;
    }

    protected function compileNode(array $node): string
    {
        return match ($node['type'] ?? '') {
            'Text' => 'echo ' . var_export($node['value'], true) . ";\n",
            'Print' => $this->compilePrint($node),
            'Tag' => $this->compileTag($node),
            default => '',
        };
    }

    protected function compilePrint(array $node): string
    {
        $expr = $this->compileExpression($node['expr']);
        $hasRaw = false;

        foreach ($node['filters'] as $filter) {
            if ($filter['name'] === 'raw') {
                $hasRaw = true;
                continue;
            }

            $args = array_map([$this, 'compileExpression'], $filter['args']);
            $argsStr = $args === [] ? '' : ', ' . implode(', ', $args);
            $expr = "\$this->sandbox->callFilter(" . var_export($filter['name'], true) . ", {$expr}{$argsStr})";
        }

        if ($hasRaw) {
            return "echo \$this->sandbox->toText({$expr});\n";
        }

        return "echo htmlspecialchars(\$this->sandbox->toText({$expr}), ENT_QUOTES, 'UTF-8');\n";
    }

    protected function compileTag(array $node): string
    {
        if ($node['name'] === 'if') {
            return $this->compileIf($node);
        }

        if ($node['name'] === 'for') {
            return $this->compileFor($node);
        }

        if ($node['name'] === 'section') {
            $expr = $this->compileExpression($node['expr']);

            return "echo \$this->sandbox->toText(\$this->sandbox->callTag('section', [{$expr}]));\n";
        }

        return '';
    }

    protected function compileIf(array $node): string
    {
        $code = '';

        foreach ($node['branches'] as $index => $branch) {
            $keyword = $index === 0 ? 'if' : '} elseif';
            $expr = $this->compileExpression($branch['expr']);
            $body = $this->compileBody($branch['body']);

            $code .= "{$keyword} ({$expr}) {\n{$body}";
        }

        if (!empty($node['else'])) {
            $code .= "} else {\n" . $this->compileBody($node['else']);
        }

        return $code . "}\n";
    }

    /**
     * Iteration is wrapped in ensureIterable(). A `{% for %}` over a
     * variable that was never supplied used to emit `foreach (null as ...)`,
     * which is a fatal in PHP 8 — one missing context key took the whole
     * page down.
     */
    protected function compileFor(array $node): string
    {
        $var = $this->safeVarName($node['var']);
        $key = $node['key'] !== null ? $this->safeVarName($node['key']) : null;
        $expr = $this->compileExpression($node['expr']);
        $body = $this->compileBody($node['body']);

        $collection = '$__axer_items' . substr(md5($var . serialize($node['expr'])), 0, 8);
        $flag = $collection . '_any';

        $code = "{$collection} = \$this->sandbox->ensureIterable({$expr});\n";

        if (!empty($node['else'])) {
            $code .= "{$flag} = false;\n";
        }

        $binding = $key !== null ? "\${$key} => \${$var}" : "\${$var}";
        $code .= "foreach ({$collection} as {$binding}) {\n";

        if (!empty($node['else'])) {
            $code .= "{$flag} = true;\n";
        }

        $code .= $body . "}\n";

        if (!empty($node['else'])) {
            $code .= "if (!{$flag}) {\n" . $this->compileBody($node['else']) . "}\n";
        }

        return $code;
    }

    protected function compileBody(array $nodes): string
    {
        $body = '';

        foreach ($nodes as $child) {
            $body .= $this->compileNode($child);
        }

        return $body;
    }

    protected function compileExpression(array $expr): string
    {
        switch ($expr['type'] ?? '') {
            case 'String':
                return var_export($expr['value'], true);

            case 'Number':
                return is_numeric($expr['value']) ? (string) (0 + $expr['value']) : '0';

            case 'Bool':
                return $expr['value'] ? 'true' : 'false';

            case 'Null':
                return 'null';

            case 'Unary':
                return '(!' . $this->compileExpression($expr['expr']) . ')';

            case 'Binary':
                $left = $this->compileExpression($expr['left']);
                $right = $this->compileExpression($expr['right']);
                $operator = $this->safeOperator($expr['operator']);

                return "({$left} {$operator} {$right})";

            case 'Var':
                return $this->compileVar($expr['value']);
        }

        return "''";
    }

    /**
     * Compile a dotted path into a null-safe lookup.
     *
     * `product.image.url` becomes a chain of `?? null` accesses rather
     * than direct array indexing, so a missing key yields null instead of
     * an "Undefined array key" warning on every render.
     */
    protected function compileVar(string $path): string
    {
        $parts = explode('.', $path);
        $base = $this->safeVarName(array_shift($parts));

        $compiled = "\${$base} ?? null";

        foreach ($parts as $part) {
            $compiled = "\$this->sandbox->getProperty({$compiled}, " . var_export($part, true) . ")";
        }

        return "\$this->sandbox->resolve({$compiled})";
    }

    /**
     * Only allow operators the parser is supposed to produce, so nothing
     * unexpected can be spliced into generated PHP.
     */
    protected function safeOperator(string $operator): string
    {
        static $allowed = ['==', '===', '!=', '!==', '>', '<', '>=', '<=', '&&', '||'];

        if (!in_array($operator, $allowed, true)) {
            throw new \RuntimeException("Unsupported template operator: {$operator}");
        }

        return $operator;
    }

    /**
     * A loop variable name ends up as a PHP variable in generated code, so
     * it must be a plain identifier.
     */
    protected function safeVarName(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \RuntimeException("Invalid template variable name: {$name}");
        }

        return $name;
    }
}
