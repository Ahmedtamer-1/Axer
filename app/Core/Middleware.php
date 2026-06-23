<?php

namespace Axer\Core;

interface Middleware
{
    /**
     * Handle the incoming request.
     * 
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}
