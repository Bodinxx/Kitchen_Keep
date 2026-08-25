<?php
declare(strict_types=1);
namespace App\Store;
final class RatingsStore
{
    private string $dir;
    public function __construct(private RecipeStore $recipeStore) { $this->dir = DATA_PATH . '/ratings'; }
    public function rate(string $recipeId, string $userId, int $score): void { $score = max(1, min(5, $score)); $path = $this->dir . '/' . $recipeId . '.json'; $ratings = read_json_file($path, []); $ratings[$userId] = ['score' => $score, 'rated_at' => current_timestamp()]; write_json_file($path, $ratings); $stats = $this->getStats($recipeId); if ($this->recipeStore->findById($recipeId) !== null) $this->recipeStore->update($recipeId, ['rating_avg' => $stats['avg'], 'rating_count' => $stats['count']]); }
    public function getUserRating(string $recipeId, string $userId): ?int { $ratings = read_json_file($this->dir . '/' . $recipeId . '.json', []); return isset($ratings[$userId]['score']) ? (int) $ratings[$userId]['score'] : null; }
    public function getStats(string $recipeId): array { $ratings = read_json_file($this->dir . '/' . $recipeId . '.json', []); if ($ratings === []) return ['avg' => 0.0, 'count' => 0]; $scores = array_map(static fn(array $item): int => (int) $item['score'], $ratings); return ['avg' => round(array_sum($scores) / count($scores), 2), 'count' => count($scores)]; }
    public function totalRatings(): int { $total = 0; foreach (glob($this->dir . '/*.json') ?: [] as $file) $total += count(read_json_file($file, [])); return $total; }
}
