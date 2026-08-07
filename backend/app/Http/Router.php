<?php

namespace App\Http;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, callable $handler, array $middleware): void
    {
        [$regex, $paramNames] = $this->compile($path);

        $this->routes[] = [
            'method' => $method,
            'regex' => $regex,
            'paramNames' => $paramNames,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    private function compile(string $path): array
    {
        $paramNames = [];

        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];
                return '([^/]+)';
            },
            $path
        );

        return ['#^' . $pattern . '$#', $paramNames];
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            array_shift($matches);
            $request->setParams(array_combine($route['paramNames'], $matches));

            return $this->runPipeline($route['middleware'], $route['handler'], $request);
        }

        if ($pathMatched) {
            return Response::error('Method not allowed', 405);
        }

        return Response::error('Not found', 404);
    }

    // Builds a middleware pipeline around the route handler: each middleware
    // class is instantiated and called as handle($request, $next), with the
    // handler as the innermost call. A middleware short-circuits by simply
    // returning a Response without calling $next().
    private function runPipeline(array $middleware, callable $handler, Request $request): Response
    {
        $next = static function (Request $request) use ($handler): Response {
            return $handler($request);
        };

        foreach (array_reverse($middleware) as $middlewareClass) {
            $next = static function (Request $request) use ($middlewareClass, $next): Response {
                $instance = new $middlewareClass();
                return $instance->handle($request, $next);
            };
        }

        return $next($request);
    }
}
