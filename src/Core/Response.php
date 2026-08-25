<?php
declare(strict_types=1);
namespace App\Core;
final class Response
{
    public function __construct(private string $body = '', private int $status = 200, private array $headers = ['Content-Type' => 'text/html; charset=UTF-8']) {}
    public static function json(array $data, int $status = 200): self { return new self(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}', $status, ['Content-Type' => 'application/json; charset=UTF-8']); }
    public static function redirect(string $path, int $status = 302): self { return new self('', $status, ['Location' => $path]); }
    public function send(): void { http_response_code($this->status); foreach ($this->headers as $name => $value) header($name . ': ' . $value); echo $this->body; }
}
