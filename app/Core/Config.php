<?php

namespace Axer\Core;

class Config
{
    protected array $settings = [];

    public function __construct(string $envPath)
    {
        $this->loadEnv($envPath);
    }

    protected function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }

                // Convert booleans
                if (strtolower($value) === 'true') {
                    $value = true;
                } elseif (strtolower($value) === 'false') {
                    $value = false;
                } elseif (strtolower($value) === 'null') {
                    $value = null;
                }

                $this->settings[$name] = $value;
                
                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                }
                if (!array_key_exists($name, $_SERVER)) {
                    $_SERVER[$name] = $value;
                }
            }
        }
    }

    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->settings[$key] = $value;
    }
}
