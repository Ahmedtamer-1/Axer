<?php

namespace Lume\Core;

class Router
{
    protected array $routes = [];
    protected array $groupStack = [];
    protected array $namedRoutes = [];
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(string $uri, $action): self
    {
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
            'name' => null
        ];

        return $this;
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        call_user_func($callback, $this);
        array_pop($this->groupStack);
    }

    public function name(string $name): self
    {
        $lastMethod = array_key_last($this->routes);
        $lastIndex = array_key_last($this->routes[$lastMethod]);
        
        $this->routes[$lastMethod][$lastIndex]['name'] = $name;
        $this->namedRoutes[$name] = &$this->routes[$lastMethod][$lastIndex];
        
        return $this;
    }

    public function where(string $param, string $regex): self
    {
        $lastMethod = array_key_last($this->routes);
        $lastIndex = array_key_last($this->routes[$lastMethod]);
        
        $this->routes[$lastMethod][$lastIndex]['wheres'][$param] = $regex;
        
        return $this;
    }

    public function middleware($middleware): self
    {
        $lastMethod = array_key_last($this->routes);
        $lastIndex = array_key_last($this->routes[$lastMethod]);
        
        $current = $this->routes[$lastMethod][$lastIndex]['middleware'];
        $this->routes[$lastMethod][$lastIndex]['middleware'] = array_merge($current, (array) $middleware);
        
        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        // Handle CORS preflight automatically
        if ($method === 'OPTIONS') {
            return new Response('', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            ]);
        }

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $route) {
            $pattern = $this->buildRegex($route['uri'], $route['wheres']);
            
            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                return $this->runRoute($route, $request, $params);
            }
        }

        return new Response('404 Not Found', 404);
    }

    protected function buildRegex(string $uri, array $wheres): string
    {
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use ($wheres) {
            $name = $matches[1];
            $constraint = $wheres[$name] ?? '[a-zA-Z0-9_\-]+';
            return "(?P<{$name}>{$constraint})";
        }, $uri);

        return '#^' . $pattern . '$#';
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
        if (is_callable($action)) {
            $response = call_user_func_array($action, array_merge([$request], $params));
        } elseif (is_array($action)) {
            [$class, $method] = $action;
            $controller = $this->container->get($class);
            $response = call_user_func_array([$controller, $method], array_merge([$request], $params));
        } else {
            throw new \Exception("Invalid route action.");
        }

        if (!$response instanceof Response) {
            if (is_array($response) || is_object($response)) {
                $response = Response::json($response);
            } else {
                $response = new Response((string) $response);
            }
        }

        return $response;
    }
}
