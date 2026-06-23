<?php

namespace Axer\Auth\Middleware;

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
            return Response::json([
                'success' => false,
                'message' => 'Unauthorized. Token not provided.'
            ], 401);
        }

        $apiToken = QueryBuilder::table('api_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->orWhere('expires_at', 'IS', null)
            ->first();

        if (!$apiToken) {
            return Response::json([
                'success' => false,
                'message' => 'Unauthorized. Invalid or expired token.'
            ], 401);
        }
        
        // Update last used
        QueryBuilder::table('api_tokens')
            ->where('id', $apiToken['id'])
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);

        // Proceed to next middleware/controller
        // You could also set the authenticated user into the request or container here
        return $next($request);
    }
}
