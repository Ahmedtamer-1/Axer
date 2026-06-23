<?php

namespace Axer\Auth\Middleware;

use Axer\Core\Middleware;
use Axer\Core\Request;
use Axer\Core\Response;

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
