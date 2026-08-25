<?php
declare(strict_types=1);
namespace App\Core;
final class Session
{
    public function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public function set(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public function forget(string $key): void { unset($_SESSION[$key]); }
    public function regenerate(): void { session_regenerate_id(true); }
    public function flash(string $type, string $message): void { $_SESSION['flash'][] = ['type' => $type, 'message' => $message]; }
    public function pullFlashes(): array { $flashes = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $flashes; }
}
