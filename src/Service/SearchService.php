<?php
declare(strict_types=1);
namespace App\Service;
use App\Core\Database;
use App\Store\IndexBuilder;
use App\Store\RecipeStore;
final class SearchService
{
    private \PDO $db;
    public function __construct(private IndexBuilder $indexBuilder, private RecipeStore $recipeStore)
    {
        $this->db = Database::getInstance();
    }

    public function search(string $query, array $filters = []): array
    {
        $query = trim($query);
        $params = [];
        $where  = ["status = 'published'", 'deleted_at IS NULL'];

        if ($query !== '') {
            $where[]  = '(title LIKE ? OR description LIKE ?)';
            $like     = '%' . $query . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql  = 'SELECT * FROM recipes WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $recipe      = $this->recipeStore->findById($row['id']);
            if ($recipe === null) continue;

            if (!empty($filters['tags'])) {
                $required = array_filter(array_map('trim', (array) $filters['tags']));
                if (array_diff($required, $recipe['tags'] ?? [])) continue;
            }
            if (!empty($filters['categories'])) {
                $required = array_filter(array_map('trim', (array) $filters['categories']));
                if (array_diff($required, $recipe['categories'] ?? [])) continue;
            }

            $ingredients = array_map('normalize_term', array_map(static fn(array $i): string => (string)($i['name'] ?? ''), $recipe['ingredients'] ?? []));
            $include = array_map('normalize_term', array_filter((array)($filters['include_ingredients'] ?? [])));
            $exclude = array_map('normalize_term', array_filter((array)($filters['exclude_ingredients'] ?? [])));
            if ($include && array_diff($include, $ingredients)) continue;
            if ($exclude && array_intersect($exclude, $ingredients)) continue;

            $recipe['_score'] = $query !== '' ? $this->fullTextScore($recipe, $query) : 1.0;
            $results[] = $recipe;
        }

        $sort = $filters['sort'] ?? 'relevance';
        usort($results, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'latest'  => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0),
                'rating'  => (($b['rating_avg'] ?? 0) <=> ($a['rating_avg'] ?? 0)) ?: (($b['rating_count'] ?? 0) <=> ($a['rating_count'] ?? 0)),
                'title'   => strcasecmp($a['title'] ?? '', $b['title'] ?? ''),
                default   => (($b['_score'] ?? 0) <=> ($a['_score'] ?? 0)) ?: (($b['rating_avg'] ?? 0) <=> ($a['rating_avg'] ?? 0)),
            };
        });

        return $results;
    }

    public function fullTextScore(array $recipe, string $query): float
    {
        $tokens = array_values(array_filter(explode(' ', normalize_term($query))));
        if ($tokens === []) return 1.0;
        $haystack = normalize_term(implode(' ', [
            $recipe['title'] ?? '', $recipe['description'] ?? '',
            implode(' ', $recipe['tags'] ?? []),
            implode(' ', $recipe['categories'] ?? []),
            implode(' ', array_map(static fn(array $i): string => (string)($i['name'] ?? ''), $recipe['ingredients'] ?? [])),
        ]));
        $score = 0.0;
        foreach ($tokens as $token) if (str_contains($haystack, $token)) $score += 1;
        return $score / count($tokens);
    }
}

