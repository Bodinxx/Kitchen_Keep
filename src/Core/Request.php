<?php
declare(strict_types=1);
namespace App\Core;
final class Request
{
    public function __construct(private string $method, private string $path, private array $query, private array $post, private array $files, private array $server, private array $cookies) {}
    public static function fromGlobals(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($uri, '/') === '' ? '/' : rtrim($uri, '/'), $_GET, $_POST, $_FILES, $_SERVER, $_COOKIE);
    }
    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function query(string $key, mixed $default = null): mixed { return $this->query[$key] ?? $default; }
    public function post(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $default; }
    public function input(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $this->query[$key] ?? $default; }
    public function all(): array { return array_merge($this->query, $this->post); }
    public function postData(): array { return $this->post; }
    public function queryData(): array { return $this->query; }
    public function file(string $key): mixed { return $this->files[$key] ?? null; }
    public function files(): array { return $this->files; }
    public function header(string $key, mixed $default = null): mixed { $name = 'HTTP_' . strtoupper(str_replace('-', '_', $key)); return $this->server[$name] ?? $default; }
    public function expectsJson(): bool { return str_contains((string) $this->header('Accept', ''), 'application/json') || str_contains((string) $this->header('X-Requested-With', ''), 'XMLHttpRequest'); }
}
