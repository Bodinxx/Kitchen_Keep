<?php
declare(strict_types=1);
namespace App\Store;
use App\Core\Database;
use App\Util\Uuid;
final class CookbookStore
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function create(string $userId, array $data): array
    {
        $timestamp = current_timestamp();
        $cookbook = array_merge([
            'id' => Uuid::generateV4(), 'owner_id' => $userId, 'title' => '',
            'description' => '', 'share_token' => null, 'sections' => [],
            'created_at' => $timestamp, 'updated_at' => $timestamp,
        ], $data);
        $this->db->prepare(
            'INSERT INTO cookbooks (id,owner_id,title,description,share_token,sections,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$cookbook['id'], $cookbook['owner_id'], $cookbook['title'], $cookbook['description'], $cookbook['share_token'], json_encode($cookbook['sections']), $cookbook['created_at'], $cookbook['updated_at']]);
        return $cookbook;
    }

    public function findById(string $userId, string $cookbookId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cookbooks WHERE id = ? AND owner_id = ?');
        $stmt->execute([$cookbookId, $userId]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function findByShareToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cookbooks WHERE share_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function list(string $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM cookbooks WHERE owner_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$userId]);
        return array_map([$this, 'decode'], $stmt->fetchAll());
    }

    public function update(string $userId, string $cookbookId, array $data): array
    {
        $cookbook = $this->findById($userId, $cookbookId);
        if ($cookbook === null) throw new \RuntimeException('Cookbook not found.');
        $cookbook = array_merge($cookbook, $data, ['updated_at' => current_timestamp()]);
        $this->db->prepare(
            'UPDATE cookbooks SET title=?,description=?,share_token=?,sections=?,updated_at=? WHERE id=? AND owner_id=?'
        )->execute([$cookbook['title'], $cookbook['description'], $cookbook['share_token'], json_encode($cookbook['sections']), $cookbook['updated_at'], $cookbookId, $userId]);
        return $cookbook;
    }

    public function delete(string $userId, string $cookbookId): void
    {
        $this->db->prepare('DELETE FROM cookbooks WHERE id = ? AND owner_id = ?')->execute([$cookbookId, $userId]);
    }

    private function decode(array $row): array
    {
        $row['sections'] = is_string($row['sections']) ? (json_decode($row['sections'], true) ?? []) : ($row['sections'] ?? []);
        return $row;
    }
}

