<?php

namespace Axer\Auth\Middleware;

use Axer\Core\Middleware;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class RateLimitMiddleware implements Middleware
{
    protected int $maxAttempts;
    protected int $decayMinutes;

    public function __construct(int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    public function handle(Request $request, callable $next): Response
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $endpoint = parse_url($request->uri(), PHP_URL_PATH) ?? '/';

        $attempts = 0;
        $allowed = true;

        try {
            // Try database rate limiting first
            $record = QueryBuilder::table('rate_limits')
                ->where('ip_address', $ip)
                ->where('endpoint', $endpoint)
                ->first();

            $now = time();

            if ($record) {
                $updatedAt = strtotime($record['updated_at'] ?? 'now');
                if ($now - $updatedAt > ($this->decayMinutes * 60)) {
                    // Window expired, reset
                    QueryBuilder::table('rate_limits')
                        ->where('id', $record['id'])
                        ->update(['attempts' => 1]);
                    $attempts = 1;
                } else {
                    $attempts = $record['attempts'] + 1;
                    QueryBuilder::table('rate_limits')
                        ->where('id', $record['id'])
                        ->update(['attempts' => $attempts]);
                }
            } else {
                QueryBuilder::table('rate_limits')->insert([
                    'ip_address' => $ip,
                    'endpoint' => $endpoint,
                    'attempts' => 1
                ]);
                $attempts = 1;
            }
        } catch (\Exception $e) {
            // Fallback to session rate limiting if DB is unreachable
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $key = "rl:" . md5($ip . ':' . $endpoint);
            $data = $_SESSION[$key] ?? ['attempts' => 0, 'time' => time()];
            if (time() - $data['time'] > ($this->decayMinutes * 60)) {
                $data = ['attempts' => 1, 'time' => time()];
            } else {
                $data['attempts']++;
            }
            $_SESSION[$key] = $data;
            $attempts = $data['attempts'];
        }

        if ($attempts > $this->maxAttempts) {
            return Response::json([
                'success' => false,
                'message' => 'Too Many Requests. Please slow down.'
            ], 429);
        }

        $response = $next($request);
        $response->setHeader('X-RateLimit-Limit', (string)$this->maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', (string)max(0, $this->maxAttempts - $attempts));

        return $response;
    }
}
