<?php
declare(strict_types=1);
namespace App\Store;
use App\Core\Database;
final class FlagsStore
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function activeFlags(): array
    {
        return $this->db->query("SELECT * FROM moderation_flags WHERE active = 1 ORDER BY created_at DESC")->fetchAll();
    }

    public function activeFlagsByRecipe(string $recipeId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM moderation_flags WHERE recipe_id = ? AND active = 1');
        $stmt->execute([$recipeId]);
        return $stmt->fetchAll();
    }

    public function activeFlagCount(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM moderation_flags WHERE active = 1")->fetchColumn();
    }

    public function userAlreadyFlagged(string $recipeId, string $userId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM moderation_flags WHERE recipe_id = ? AND user_id = ? AND active = 1');
        $stmt->execute([$recipeId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function add(string $recipeId, string $userId, string $reason, string $details): array
    {
        $flag = ['id' => bin2hex(random_bytes(8)), 'recipe_id' => $recipeId, 'user_id' => $userId, 'reason' => $reason, 'details' => $details, 'active' => true, 'created_at' => current_timestamp()];
        $this->db->prepare(
            'INSERT INTO moderation_flags (id, recipe_id, user_id, reason, details, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)'
        )->execute([$flag['id'], $recipeId, $userId, $reason, $details, $flag['created_at']]);
        return $flag;
    }

    public function resolve(string $flagId, string $resolution): void
    {
        $this->db->prepare(
            'UPDATE moderation_flags SET active = 0, resolved_at = ?, resolution = ? WHERE id = ?'
        )->execute([current_timestamp(), $resolution, $flagId]);
    }

    public function activeCountByRecipe(string $recipeId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM moderation_flags WHERE recipe_id = ? AND active = 1');
        $stmt->execute([$recipeId]);
        return (int) $stmt->fetchColumn();
    }
}
