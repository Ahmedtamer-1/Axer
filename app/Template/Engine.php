<?php

namespace Axer\Template;

use Axer\Core\Logger;

class Engine
{
    protected array $paths = [];
    protected string $cachePath;
    protected array $globals = [];
    protected Sandbox $sandbox;

    /** Recompile on every request when true (development). */
    protected bool $debug;

    /** template name => compiled file, for repeated renders in one request. */
    protected array $resolved = [];

    public function __construct(array $paths, string $cachePath, ?bool $debug = null)
    {
        $this->paths = $paths;
        $this->cachePath = rtrim($cachePath, '/');
        $this->sandbox = new Sandbox();
        $this->debug = $debug ?? (defined('APP_DEBUG') && APP_DEBUG);

        if (!is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0755, true);
        }
    }

    public function addGlobal(string $name, $value): void
    {
        $this->globals[$name] = $value;
    }

    public function addGlobals(array $values): void
    {
        $this->globals = array_merge($this->globals, $values);
    }

    public function getSandbox(): Sandbox
    {
        return $this->sandbox;
    }

    public function render(string $template, array $data = []): string
    {
        $context = array_merge($this->globals, $data);

        return $this->evaluate($this->compile($template), $context);
    }

    /**
     * Execute a compiled template in an isolated scope.
     *
     * This used to be `extract($context)` in the body of render(), sharing
     * a scope with $template, $data, $context and $compiledFile. A theme
     * that passed a variable named "compiledFile" (or "context", or
     * "template") overwrote the local the very next line depended on.
     * The compiled file is required inside a closure whose only locals are
     * deliberately name-mangled.
     */
    protected function evaluate(string $__axer_compiled, array $__axer_context): string
    {
        $render = function () use ($__axer_compiled, $__axer_context) {
            // EXTR_SKIP so context data can never shadow the two locals above.
            extract($__axer_context, EXTR_SKIP);

            ob_start();

            try {
                require $__axer_compiled;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return ob_get_clean();
        };

        return $render();
    }

    protected function compile(string $template): string
    {
        if (isset($this->resolved[$template])) {
            return $this->resolved[$template];
        }

        $sourceFile = $this->resolveTemplate($template);
        $mtime = filemtime($sourceFile);

        // Cache key covers the source path only; freshness is decided by
        // comparing mtimes below. Keying the *filename* on mtime (as it was)
        // orphaned a new file on every edit and the cache directory grew
        // without limit.
        $cacheFile = $this->cachePath . '/' . md5($sourceFile) . '.php';

        if (!$this->debug && is_file($cacheFile) && filemtime($cacheFile) >= $mtime) {
            return $this->resolved[$template] = $cacheFile;
        }

        $compiled = $this->compileSource(file_get_contents($sourceFile) ?: '', $sourceFile);

        $this->writeAtomically($cacheFile, $compiled);

        return $this->resolved[$template] = $cacheFile;
    }

    public function compileSource(string $source, string $origin = 'inline'): string
    {
        try {
            $tokens = (new Lexer())->tokenize($source);
            $ast = (new Parser($tokens))->parse();

            return (new Compiler($this->sandbox))->compile($ast);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to compile template {$origin}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Write via a temp file + rename so a concurrent request can never
     * require a half-written PHP file (which is a fatal parse error).
     */
    protected function writeAtomically(string $target, string $contents): void
    {
        $temp = $target . '.' . getmypid() . '.tmp';

        if (@file_put_contents($temp, $contents, LOCK_EX) === false) {
            Logger::warning("Could not write template cache: {$target}");
            return;
        }

        if (!@rename($temp, $target)) {
            @unlink($temp);
            Logger::warning("Could not move template cache into place: {$target}");
            return;
        }

        // Make sure the freshly written file is the one that gets executed.
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($target, true);
        }
    }

    /**
     * Remove every compiled template. Called when a theme is switched or
     * its settings change, since those are baked into the output.
     */
    public function clearCache(): int
    {
        $removed = 0;

        foreach (glob($this->cachePath . '/*.php') ?: [] as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        $this->resolved = [];

        return $removed;
    }

    protected function resolveTemplate(string $template): string
    {
        // Reject traversal before touching the filesystem: template names
        // reach here from page builder data.
        if (str_contains($template, '..') || str_contains($template, "\0")) {
            throw new \InvalidArgumentException("Invalid template name: {$template}");
        }

        if (!str_ends_with($template, '.lume') && !str_ends_with($template, '.php')) {
            $template .= '.lume';
        }

        foreach ($this->paths as $path) {
            $file = rtrim($path, '/') . '/' . ltrim($template, '/');

            if (is_file($file)) {
                return $file;
            }
        }

        throw new \RuntimeException("Template not found: {$template}");
    }
}
