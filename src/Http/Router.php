<?php

declare(strict_types=1);

namespace VitaGap\Http;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    /** @var array<string, array<string, array{pattern: string, handler: callable, params: string[]}>> */
    private array $patternRoutes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        if (str_contains($path, '{')) {
            $paramNames = [];
            $regex = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                return '([^/]+)';
            }, $path);

            $regex = '#^' . $regex . '$#';
            $this->patternRoutes[$method][] = [
                'pattern' => $regex,
                'handler' => $handler,
                'params' => $paramNames,
            ];
        } else {
            $this->routes[$method][$path] = $handler;
        }
    }

    public function dispatch(string $method, string $uri, float $startTime = 0.0): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Direct static match
        if (isset($this->routes[$method][$path])) {
            $handler = $this->routes[$method][$path];
            $handler([], $startTime);
            return;
        }

        // Pattern dynamic match
        if (isset($this->patternRoutes[$method])) {
            foreach ($this->patternRoutes[$method] as $route) {
                if (preg_match($route['pattern'], $path, $matches)) {
                    array_shift($matches);
                    $params = [];
                    foreach ($route['params'] as $idx => $paramName) {
                        $params[$paramName] = $matches[$idx] ?? '';
                    }
                    ($route['handler'])($params, $startTime);
                    return;
                }
            }
        }

        // 404 Not Found
        JsonResponse::error(
            JsonResponse::CODE_NOT_FOUND,
            sprintf('Route %s %s not found', $method, $path),
            404,
            [],
            [],
            $startTime
        );
    }
}
