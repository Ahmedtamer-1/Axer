<?php

namespace Axer\Core;

/**
 * Minimal file logger.
 *
 * Errors previously vanished unless APP_DEBUG was on, which made production
 * failures invisible. Everything now lands in storage/logs/app-Y-m-d.log.
 */
class Logger
{
    protected static ?string $path = null;

    protected static function path(): string
    {
        if (self::$path === null) {
            self::$path = BASE_PATH . '/storage/logs';
            if (!is_dir(self::$path)) {
                @mkdir(self::$path, 0755, true);
            }
        }

        return self::$path;
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        @file_put_contents(self::path() . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function exception(\Throwable $e): void
    {
        self::error($e->getMessage(), [
            'type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'uri'  => $_SERVER['REQUEST_URI'] ?? 'cli',
            'trace' => explode("\n", $e->getTraceAsString()),
        ]);
    }
}
