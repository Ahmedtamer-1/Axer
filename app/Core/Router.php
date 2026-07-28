<?php

namespace Axer\Core;

class Router
{
    protected array $routes = [];
    protected array $groupStack = [];
    protected array $namedRoutes = [];
    protected Container $container;

    /**
     * Method + URI of the most recently registered route.
     *
     * name(), where() and middleware() used to locate "the last route" with
     * array_key_last($this->routes), which returns the last *HTTP method*
     * ever inserted — not the last route added. Registering GET /a then
     * POST /b then GET /c and calling ->middleware('auth') attached the
     * middleware to POST /b. That silently put auth on the wrong endpoint.
     */
    protected ?array $lastRoute = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(string $uri, $action): self
    {
        // A GET route also answers HEAD, per RFC 9110.
        $this->addRoute('HEAD', $uri, $action);

        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, $action): self
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, $action): self
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, $action): self
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, $action): self
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function any(string $uri, $action): self
    {
        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->addRoute($method, $uri, $action);
        }

        return $this;
    }

    protected function addRoute(string $method, string $uri, $action): self
    {
        $prefix = '';
        $middleware = [];

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] ?? '';
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }

        $uri = rtrim($prefix . '/' . trim($uri, '/'), '/') ?: '/';

        $this->routes[$method][] = [
            'uri' => $uri,
            'action' => $action,
            'middleware' => $middleware,
            'wheres' => [],
            'name' => null,
            'static' => !str_contains($uri, '{'),
        ];

        $this->lastRoute = [$method, array_key_last($this->routes[$method])];

        return $this;
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        call_user_func($callback, $this);
        array_pop($this->groupStack);
    }

    /**
     * Apply a mutation to the route registered last.
     *
     * GET registrations also create a HEAD twin, so the modifier has to be
     * applied to both or a named/guarded GET route would leave its HEAD
     * counterpart unprotected.
     */
    protected function modifyLastRoute(callable $mutator): self
    {
        if ($this->lastRoute === null) {
            return $this;
        }

        [$method, $index] = $this->lastRoute;
        $mutator($this->routes[$method][$index]);

        if ($method === 'GET' && isset($this->routes['HEAD'])) {
            $twin = array_key_last($this->routes['HEAD']);

            if ($twin !== null && $this->routes['HEAD'][$twin]['uri'] === $this->routes[$method][$index]['uri']) {
                $mutator($this->routes['HEAD'][$twin]);
            }
        }

        return $this;
    }

    public function name(string $name): self
    {
        return $this->modifyLastRoute(function (array &$route) use ($name) {
            $route['name'] = $name;
            $this->namedRoutes[$name] = $route['uri'];
        });
    }

    public function where(string $param, string $regex): self
    {
        return $this->modifyLastRoute(static function (array &$route) use ($param, $regex) {
            $route['wheres'][$param] = $regex;
        });
    }

    public function middleware($middleware): self
    {
        return $this->modifyLastRoute(static function (array &$route) use ($middleware) {
            $route['middleware'] = array_merge($route['middleware'], (array) $middleware);
        });
    }

    /**
     * Build a URL for a named route, substituting {param} placeholders.
     */
    public function route(string $name, array $params = []): string
    {
        $uri = $this->namedRoutes[$name] ?? '/';

        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', rawurlencode((string) $value), $uri);
        }

        return $uri;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        // Handle CORS preflight automatically
        if ($method === 'OPTIONS') {
            return new Response('', 204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-CSRF-TOKEN, X-Requested-With',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        $routes = $this->routes[$method] ?? [];

        // Fast path: exact match on a route with no placeholders. Most
        // requests hit one of these, and it avoids compiling a regex per
        // registered route on every single request.
        foreach ($routes as $route) {
            if ($route['static'] && $route['uri'] === $uri) {
                return $this->runRoute($route, $request, []);
            }
        }

        foreach ($routes as $route) {
            if ($route['static']) {
                continue;
            }

            if (preg_match($this->buildRegex($route['uri'], $route['wheres']), $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                return $this->runRoute($route, $request, $params);
            }
        }

        // Distinguish "wrong method" from "no such URL" so clients get a
        // usable answer instead of a blanket 404.
        $allowed = $this->methodsFor($uri);

        if ($allowed !== []) {
            $response = $request->expectsJson()
                ? Response::json(['success' => false, 'message' => 'Method not allowed.'], 405)
                : new Response('405 Method Not Allowed', 405);

            return $response->setHeader('Allow', implode(', ', $allowed));
        }

        if ($request->expectsJson()) {
            return Response::json(['success' => false, 'message' => 'Not found.'], 404);
        }

        return Response::notFound();
    }

    /**
     * @return string[] HTTP methods that would match this URI
     */
    protected function methodsFor(string $uri): array
    {
        $allowed = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $matched = $route['static']
                    ? $route['uri'] === $uri
                    : preg_match($this->buildRegex($route['uri'], $route['wheres']), $uri) === 1;

                if ($matched) {
                    $allowed[] = $method;
                    break;
                }
            }
        }

        return $allowed;
    }

    protected function buildRegex(string $uri, array $wheres): string
    {
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function ($matches) use ($wheres) {
            $name = $matches[1];
            // Default allows unicode slugs (Arabic product names produce
            // them) but still stops at a path separator.
            $constraint = $wheres[$name] ?? '[^/]+';

            return "(?P<{$name}>{$constraint})";
        }, $uri);

        return '#^' . $pattern . '$#u';
    }

    protected function runRoute(array $route, Request $request, array $params): Response
    {
        $action = $route['action'];
        $middlewareStack = $route['middleware'];

        // Execute middleware stack
        $pipeline = array_reduce(
            array_reverse($middlewareStack),
            function ($next, $middleware) {
                return function ($request) use ($next, $middleware) {
                    if (is_string($middleware)) {
                        $middleware = $this->container->get($middleware);
                    }

                    return $middleware->handle($request, $next);
                };
            },
            function ($request) use ($action, $params) {
                return $this->executeAction($action, $request, $params);
            }
        );

        return $pipeline($request);
    }

    protected function executeAction($action, Request $request, array $params): Response
    {
        if (is_array($action)) {
            [$class, $method] = $action;
            $controller = $this->container->get($class);

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Controller method {$class}::{$method}() does not exist.");
            }

            $response = call_user_func_array([$controller, $method], array_merge([$request], array_values($params)));
        } elseif (is_callable($action)) {
            $response = call_user_func_array($action, array_merge([$request], array_values($params)));
        } else {
            throw new \RuntimeException('Invalid route action.');
        }

        if ($response instanceof Response) {
            return $response;
        }

        if (is_array($response) || is_object($response)) {
            return Response::json($response);
        }

        return new Response((string) $response);
    }
}
