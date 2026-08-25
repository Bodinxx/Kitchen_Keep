<?php
declare(strict_types=1);
namespace App\Store;
use App\Util\Token;
use App\Util\Uuid;
final class UserStore
{
    private string $dir; private string $emailIndexPath;
    public function __construct() { $this->dir = DATA_PATH . '/users'; $this->emailIndexPath = $this->dir . '/by-email.json'; }
    public function create(array $data): array
    {
        $timestamp = current_timestamp();
        $user = array_merge(['id' => Uuid::generateV4(), 'username' => '', 'real_name' => '', 'email' => '', 'password_hash' => '', 'role' => 'user', 'verified' => false, 'verify_token' => Token::generate(), 'reset_token' => null, 'reset_expires' => null, 'display_name' => $data['username'] ?? '', 'bio' => '', 'avatar' => null, 'theme' => site_config('default_theme', 'light'), 'created_at' => $timestamp, 'updated_at' => $timestamp, 'deleted_at' => null], $data);
        $this->save($user); $this->rebuildEmailIndex(); return $user;
    }
    public function all(): array
    {
        $users = [];
        foreach (glob($this->dir . '/*.json') ?: [] as $file) { if (basename($file) === 'by-email.json') continue; $user = read_json_file($file); if ($user !== []) $users[] = $user; }
        usort($users, static fn(array $a, array $b): int => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
        return $users;
    }
    public function findById(string $id): ?array { if (!is_valid_uuid($id)) return null; $path = $this->pathFor($id); return is_file($path) ? read_json_file($path) : null; }
    public function findByEmail(string $email): ?array { $index = read_json_file($this->emailIndexPath, []); $id = $index[strtolower($email)] ?? null; return is_string($id) ? $this->findById($id) : null; }
    public function findByUsername(string $username): ?array { foreach ($this->all() as $user) if (strcasecmp($user['username'] ?? '', $username) === 0) return $user; return null; }
    public function update(string $id, array $data): array { $user = $this->findById($id); if ($user === null) throw new \RuntimeException('User not found.'); $user = array_merge($user, $data, ['updated_at' => current_timestamp()]); $this->save($user); $this->rebuildEmailIndex(); return $user; }
    public function softDelete(string $id): void { $this->update($id, ['deleted_at' => current_timestamp()]); }
    public function hardDelete(string $id): void { $path = $this->pathFor($id); if (is_file($path)) unlink($path); $this->rebuildEmailIndex(); }
    public function list(int $page, int $perPage): array { $all = $this->all(); $offset = max(0, ($page - 1) * $perPage); return ['items' => array_slice($all, $offset, $perPage), 'total' => count($all), 'page' => $page, 'per_page' => $perPage]; }
    public function rebuildEmailIndex(): void { $index = []; foreach ($this->all() as $user) if (!empty($user['email'])) $index[strtolower((string) $user['email'])] = $user['id']; write_json_file($this->emailIndexPath, $index); }
    public function save(array $user): void { write_json_file($this->pathFor($user['id']), $user); }
    public function findByVerifyToken(string $token): ?array { foreach ($this->all() as $user) if (($user['verify_token'] ?? null) === $token) return $user; return null; }
    public function findByResetToken(string $token): ?array { foreach ($this->all() as $user) if (($user['reset_token'] ?? null) === $token && ($user['reset_expires'] ?? 0) >= time()) return $user; return null; }
    public function hasAdmin(): bool { foreach ($this->all() as $user) if (($user['role'] ?? '') === 'admin' && empty($user['deleted_at'])) return true; return false; }
    private function pathFor(string $id): string { return $this->dir . '/' . $id . '.json'; }
}
