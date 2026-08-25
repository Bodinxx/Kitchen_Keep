<?php
declare(strict_types=1);
namespace App\Core;
final class Router
{
    private array $routes = ['GET' => [], 'POST' => []];
    private $notFoundHandler = null;
    public function __construct(private Auth $auth) {}
    public function get(string $path, callable $handler, array $middleware = []): void { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, callable $handler, array $middleware = []): void { $this->add('POST', $path, $handler, $middleware); }
    public function add(string $method, string $path, callable $handler, array $middleware = []): void { $this->routes[$method][] = compact('path', 'handler', 'middleware'); }
    public function setNotFoundHandler(callable $handler): void { $this->notFoundHandler = $handler; }
    public function dispatch(Request $request): Response
    {
        foreach ($this->routes[$request->method()] ?? [] as $route) {
            $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static fn(array $m): string => '(?P<' . $m[1] . '>[^/]+)', $route['path']);
            if (!preg_match('#^' . $pattern . '$#', $request->path(), $matches)) continue;
            $params = array_filter($matches, static fn($key): bool => !is_int($key), ARRAY_FILTER_USE_KEY);
            $response = $this->runMiddleware($route['middleware'], $request);
            if ($response instanceof Response) return $response;
            $result = call_user_func_array($route['handler'], array_merge([$request], array_values($params)));
            return $result instanceof Response ? $result : new Response((string) $result);
        }
        if (is_callable($this->notFoundHandler)) {
            $result = call_user_func($this->notFoundHandler, $request);
            return $result instanceof Response ? $result : new Response((string) $result, 404);
        }
        return new Response('Not found', 404);
    }
    private function runMiddleware(array $middleware, Request $request): ?Response
    {
        foreach ($middleware as $item) {
            if ($item === 'auth' && !$this->auth->isLoggedIn()) {
                flash('error', 'Please log in to continue.');
                return $request->expectsJson() ? Response::json(['error' => 'Authentication required'], 401) : Response::redirect('/login');
            }
            if (str_starts_with($item, 'role:') && !$this->auth->hasRole(substr($item, 5))) {
                flash('error', 'You do not have permission to access that page.');
                return $request->expectsJson() ? Response::json(['error' => 'Forbidden'], 403) : Response::redirect('/');
            }
        }
        return null;
    }
}
