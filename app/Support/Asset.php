<?php

namespace Axer\Support;

/**
 * Cache-busted asset URLs.
 *
 * The .htaccess rules serve CSS/JS as immutable for a year, which is only
 * safe if the URL changes when the file does. Appending the file mtime
 * gives that for free without a build step.
 */
class Asset
{
    protected static array $versions = [];

    public static function url(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        if (!isset(self::$versions[$path])) {
            $file = BASE_PATH . $path;
            self::$versions[$path] = is_file($file) ? (string) filemtime($file) : '0';
        }

        return $path . '?v=' . self::$versions[$path];
    }
}
