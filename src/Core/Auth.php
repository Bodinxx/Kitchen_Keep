<?php
declare(strict_types=1);
namespace App\Core;
use App\Store\UserStore;
final class Auth
{
    private ?array $currentUser = null;
    public function __construct(private Session $session, private UserStore $userStore) { $this->bootstrapRememberedUser(); }
    public function getCurrentUser(): ?array
    {
        if ($this->currentUser !== null) return $this->currentUser;
        $userId = $this->session->get('user_id');
        if (!is_string($userId) || !is_valid_uuid($userId)) return null;
        $user = $this->userStore->findById($userId);
        if ($user === null || !empty($user['deleted_at'])) { $this->session->forget('user_id'); return null; }
        return $this->currentUser = $user;
    }
    public function isLoggedIn(): bool { return $this->getCurrentUser() !== null; }
    public function hasRole(string $role): bool
    {
        $hierarchy = ['guest' => 0, 'user' => 1, 'editor' => 2, 'admin' => 3];
        return ($hierarchy[$this->getCurrentUser()['role'] ?? 'guest'] ?? 0) >= ($hierarchy[$role] ?? PHP_INT_MAX);
    }
    public function login(array $user, bool $remember = false): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', $user['id']);
        $this->currentUser = $user;
        if ($remember) {
            $expires = time() + 60 * 60 * 24 * 30;
            $payload = $user['id'] . '|' . $expires;
            $signature = hash_hmac('sha256', $payload . '|' . $user['password_hash'], APP_SECRET);
            setcookie('kk_remember', $payload . '|' . $signature, ['expires' => $expires, 'path' => '/', 'httponly' => true, 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'samesite' => 'Lax']);
        }
    }
    public function logout(): void
    {
        $this->session->forget('user_id');
        $this->currentUser = null;
        setcookie('kk_remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'samesite' => 'Lax']);
    }
    private function bootstrapRememberedUser(): void
    {
        if ($this->session->get('user_id')) return;
        $cookie = $_COOKIE['kk_remember'] ?? null;
        if (!is_string($cookie)) return;
        $parts = explode('|', $cookie);
        if (count($parts) !== 3) return;
        [$userId, $expires, $signature] = $parts;
        if (!is_valid_uuid($userId) || (int) $expires < time()) return;
        $user = $this->userStore->findById($userId);
        if ($user === null) return;
        $expected = hash_hmac('sha256', $userId . '|' . $expires . '|' . $user['password_hash'], APP_SECRET);
        if (!hash_equals($expected, $signature)) return;
        $this->session->set('user_id', $userId);
        $this->currentUser = $user;
    }
}
