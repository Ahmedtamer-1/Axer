<?php

namespace Lume\Auth\Middleware;

use Lume\Core\Middleware;
use Lume\Core\Request;
use Lume\Core\Response;

class CorsMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        return $response;
    }
}
