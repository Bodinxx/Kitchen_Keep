<?php
declare(strict_types=1);
namespace App\Store;
use App\Core\Database;
final class RatingsStore
{
    private \PDO $db;
    public function __construct(private RecipeStore $recipeStore) { $this->db = Database::getInstance(); }

    public function rate(string $recipeId, string $userId, int $score): void
    {
        $score = max(1, min(5, $score));
        $this->db->prepare(
            'INSERT INTO ratings (recipe_id, user_id, score, rated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score), rated_at = VALUES(rated_at)'
        )->execute([$recipeId, $userId, $score, current_timestamp()]);
        $stats = $this->getStats($recipeId);
        if ($this->recipeStore->findById($recipeId) !== null) {
            $this->recipeStore->update($recipeId, ['rating_avg' => $stats['avg'], 'rating_count' => $stats['count']]);
        }
    }

    public function getUserRating(string $recipeId, string $userId): ?int
    {
        $stmt = $this->db->prepare('SELECT score FROM ratings WHERE recipe_id = ? AND user_id = ?');
        $stmt->execute([$recipeId, $userId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['score'] : null;
    }

    public function getStats(string $recipeId): array
    {
        $stmt = $this->db->prepare('SELECT AVG(score) as avg, COUNT(*) as cnt FROM ratings WHERE recipe_id = ?');
        $stmt->execute([$recipeId]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['cnt'] === 0) return ['avg' => 0.0, 'count' => 0];
        return ['avg' => round((float) $row['avg'], 2), 'count' => (int) $row['cnt']];
    }

    public function totalRatings(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM ratings')->fetchColumn();
    }
}

