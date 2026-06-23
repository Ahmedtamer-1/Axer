<?php

namespace Axer\Template;

class Engine
{
    protected array $paths = [];
    protected string $cachePath;
    protected array $globals = [];
    protected Sandbox $sandbox;

    public function __construct(array $paths, string $cachePath)
    {
        $this->paths = $paths;
        $this->cachePath = rtrim($cachePath, '/');
        $this->sandbox = new Sandbox();
        
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function addGlobal(string $name, $value): void
    {
        $this->globals[$name] = $value;
    }

    public function render(string $template, array $data = []): string
    {
        $context = array_merge($this->globals, $data);
        $compiledFile = $this->compile($template);
        
        // Use output buffering to capture the rendered output
        ob_start();
        extract($context);
        
        try {
            require $compiledFile;
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
        
        return ob_get_clean();
    }

    protected function compile(string $template): string
    {
        $sourceFile = $this->resolveTemplate($template);
        
        // Cache file name based on modification time to detect changes
        $hash = md5($sourceFile . filemtime($sourceFile));
        $cacheFile = $this->cachePath . '/' . $hash . '.php';
        
        if (file_exists($cacheFile)) {
            return $cacheFile;
        }
        
        $content = file_get_contents($sourceFile);
        
        $lexer = new Lexer();
        $tokens = $lexer->tokenize($content);
        
        $parser = new Parser($tokens);
        $ast = $parser->parse();
        
        $compiler = new Compiler($this->sandbox);
        $compiledCode = $compiler->compile($ast);
        
        file_put_contents($cacheFile, $compiledCode);
        
        return $cacheFile;
    }

    protected function resolveTemplate(string $template): string
    {
        // Add .lume extension if missing
        if (!str_ends_with($template, '.lume') && !str_ends_with($template, '.php')) {
            $template .= '.lume';
        }

        foreach ($this->paths as $path) {
            $file = rtrim($path, '/') . '/' . ltrim($template, '/');
            if (file_exists($file)) {
                return $file;
            }
        }
        
        throw new \Exception("Template not found: {$template}");
    }
}
