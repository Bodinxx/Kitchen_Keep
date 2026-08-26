<?php
declare(strict_types=1);
namespace App\Store;
use App\Core\Database;
use App\Util\Token;
use App\Util\Uuid;
final class UserStore
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function create(array $data): array
    {
        $timestamp = current_timestamp();
        $user = array_merge([
            'id' => Uuid::generateV4(), 'username' => '', 'real_name' => '', 'email' => '',
            'password_hash' => '', 'role' => 'user', 'verified' => false,
            'verify_token' => Token::generate(), 'reset_token' => null, 'reset_expires' => null,
            'display_name' => $data['username'] ?? '', 'bio' => '', 'avatar' => null,
            'theme' => site_config('default_theme', 'light'),
            'created_at' => $timestamp, 'updated_at' => $timestamp, 'deleted_at' => null,
        ], $data);
        $this->insert($user);
        return $user;
    }

    public function all(): array
    {
        $rows = $this->db->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
        return array_map([$this, 'decode'], $rows);
    }

    public function findById(string $id): ?array
    {
        if (!is_valid_uuid($id)) return null;
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([strtolower($email)]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function findByVerifyToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE verify_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function findByResetToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_expires >= ?');
        $stmt->execute([$token, time()]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function update(string $id, array $data): array
    {
        $user = $this->findById($id);
        if ($user === null) throw new \RuntimeException('User not found.');
        $user = array_merge($user, $data, ['updated_at' => current_timestamp()]);
        $this->upsert($user);
        return $user;
    }

    public function softDelete(string $id): void { $this->update($id, ['deleted_at' => current_timestamp()]); }

    public function hardDelete(string $id): void
    {
        $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public function list(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total  = (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $stmt   = $this->db->prepare('SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->execute([$perPage, $offset]);
        return ['items' => array_map([$this, 'decode'], $stmt->fetchAll()), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function hasAdmin(): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND deleted_at IS NULL");
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @deprecated kept for compatibility; email index is now a DB index */
    public function rebuildEmailIndex(): void {}

    // ------------------------------------------------------------------
    private function insert(array $user): void
    {
        $this->db->prepare(
            'INSERT INTO users (id,username,real_name,email,password_hash,role,verified,verify_token,reset_token,reset_expires,display_name,bio,avatar,theme,created_at,updated_at,deleted_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $user['id'], $user['username'], $user['real_name'], strtolower((string)$user['email']),
            $user['password_hash'], $user['role'], (int)$user['verified'],
            $user['verify_token'], $user['reset_token'], $user['reset_expires'],
            $user['display_name'], $user['bio'], $user['avatar'], $user['theme'],
            $user['created_at'], $user['updated_at'], $user['deleted_at'],
        ]);
    }

    private function upsert(array $user): void
    {
        $this->db->prepare(
            'UPDATE users SET username=?,real_name=?,email=?,password_hash=?,role=?,verified=?,verify_token=?,reset_token=?,reset_expires=?,display_name=?,bio=?,avatar=?,theme=?,updated_at=?,deleted_at=? WHERE id=?'
        )->execute([
            $user['username'], $user['real_name'], strtolower((string)$user['email']),
            $user['password_hash'], $user['role'], (int)$user['verified'],
            $user['verify_token'], $user['reset_token'], $user['reset_expires'],
            $user['display_name'], $user['bio'], $user['avatar'], $user['theme'],
            $user['updated_at'], $user['deleted_at'], $user['id'],
        ]);
    }

    private function decode(array $row): array
    {
        $row['verified'] = (bool) $row['verified'];
        return $row;
    }
}

