<?php
declare(strict_types=1);
namespace App\Util;
final class Csrf
{
    public static function generateToken(): string
    {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }
    public static function validateToken(?string $token): bool
    {
        return is_string($token) && is_string($_SESSION['_csrf'] ?? null) && hash_equals($_SESSION['_csrf'], $token);
    }
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generateToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
