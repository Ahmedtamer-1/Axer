<?php

namespace Axer\Auth\Middleware;

use Axer\Core\Logger;
use Axer\Core\Middleware;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return $this->deny('Unauthorized. Token not provided.');
        }

        $apiToken = QueryBuilder::table('api_tokens')
            ->where('token_hash', hash('sha256', $token))
            // A token either never expires or expires in the future.
            //
            // This used to read:
            //   ->where('expires_at', '>', $now)->orWhere('expires_at', 'IS', null)
            // which produced `token_hash = ? AND expires_at > ? OR expires_at = 'IS'`.
            // AND binds tighter than OR, and the null argument collapsed the
            // operator into the value, so non-expiring tokens (expires_at
            // NULL) could never authenticate at all.
            ->whereGroup(static function (QueryBuilder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->first();

        if (!$apiToken) {
            return $this->deny('Unauthorized. Invalid or expired token.');
        }

        // Record usage, but never let a write failure block a valid request.
        try {
            QueryBuilder::table('api_tokens')
                ->where('id', $apiToken['id'])
                ->update(['last_used_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            Logger::warning('Could not update api_tokens.last_used_at: ' . $e->getMessage());
        }

        // Make the authenticated principal available to controllers, which
        // previously had no way to tell who was calling.
        $request->setAuth([
            'token_id' => $apiToken['id'],
            'user_id' => $apiToken['user_id'] ?? null,
            'permissions' => isset($apiToken['permissions'])
                ? (json_decode((string) $apiToken['permissions'], true) ?? [])
                : [],
        ]);

        return $next($request);
    }

    protected function deny(string $message): Response
    {
        return Response::json([
            'success' => false,
            'message' => $message,
        ], 401)->setHeader('WWW-Authenticate', 'Bearer');
    }
}
