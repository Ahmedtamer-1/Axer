<?php

namespace Axer\Core;

/**
 * File-backed sliding-window rate limiter.
 *
 * The previous global limiter kept its counter in $_SESSION, so any client
 * that simply declined to send a session cookie got a fresh allowance on
 * every request — it limited nobody. Counters now live on disk keyed by
 * client IP, so they survive across sessions.
 *
 * APCu is used when available (shared hosting usually has it) and the
 * filesystem is the fallback.
 */
class RateLimiter
{
    protected string $storePath;
    protected bool $useApcu;

    public function __construct(?string $storePath = null)
    {
        $this->storePath = $storePath ?? BASE_PATH . '/storage/cache/ratelimit';
        $this->useApcu = function_exists('apcu_fetch') && ini_get('apc.enabled');

        if (!$this->useApcu && !is_dir($this->storePath)) {
            @mkdir($this->storePath, 0755, true);
        }
    }

    /**
     * @return bool true when the caller is over budget
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        return $this->hit($key, $decaySeconds) > $maxAttempts;
    }

    /**
     * Record one hit and return the running count for the current window.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $now = time();
        $bucket = $this->read($key);

        if ($bucket === null || ($now - $bucket['start']) >= $decaySeconds) {
            $bucket = ['start' => $now, 'count' => 0];
        }

        $bucket['count']++;
        $this->write($key, $bucket, $decaySeconds);

        return $bucket['count'];
    }

    /**
     * Current count without recording a hit.
     */
    public function attempts(string $key, int $decaySeconds = 60): int
    {
        $bucket = $this->read($key);

        if ($bucket === null || (time() - $bucket['start']) >= $decaySeconds) {
            return 0;
        }

        return $bucket['count'];
    }

    /**
     * Seconds until the current window rolls over — used for Retry-After.
     */
    public function availableIn(string $key, int $decaySeconds = 60): int
    {
        $bucket = $this->read($key);

        if ($bucket === null) {
            return 0;
        }

        return max(0, $decaySeconds - (time() - $bucket['start']));
    }

    public function clear(string $key): void
    {
        if ($this->useApcu) {
            apcu_delete($this->cacheKey($key));
            return;
        }

        @unlink($this->file($key));
    }

    protected function read(string $key): ?array
    {
        if ($this->useApcu) {
            $value = apcu_fetch($this->cacheKey($key), $ok);
            return ($ok && is_array($value)) ? $value : null;
        }

        $file = $this->file($key);

        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return (is_array($data) && isset($data['start'], $data['count'])) ? $data : null;
    }

    protected function write(string $key, array $bucket, int $decaySeconds): void
    {
        if ($this->useApcu) {
            apcu_store($this->cacheKey($key), $bucket, $decaySeconds);
            return;
        }

        @file_put_contents($this->file($key), json_encode($bucket), LOCK_EX);

        // Opportunistic sweep so the directory does not grow without bound.
        if (random_int(1, 100) === 1) {
            $this->prune($decaySeconds);
        }
    }

    protected function prune(int $decaySeconds): void
    {
        $cutoff = time() - max($decaySeconds * 2, 3600);

        foreach (glob($this->storePath . '/*.json') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    protected function cacheKey(string $key): string
    {
        return 'axer_rl_' . sha1($key);
    }

    protected function file(string $key): string
    {
        return $this->storePath . '/' . sha1($key) . '.json';
    }

    /**
     * Best-effort real client IP. Proxy headers are only trusted when the
     * request actually arrived from a configured trusted proxy, otherwise
     * anyone could spoof X-Forwarded-For to dodge the limiter.
     */
    public static function clientIp(array $trustedProxies = []): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($trustedProxies && in_array($remote, $trustedProxies, true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            $first = trim(explode(',', $forwarded)[0] ?? '');

            if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return $remote;
    }
}
