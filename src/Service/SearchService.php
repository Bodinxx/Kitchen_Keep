<?php
declare(strict_types=1);
namespace App\Service;
use App\Store\IndexBuilder;
use App\Store\RecipeStore;
final class SearchService
{
    public function __construct(private IndexBuilder $indexBuilder, private RecipeStore $recipeStore) {}
    public function search(string $query, array $filters = []): array
    {
        $index = $this->indexBuilder->getSearchIndex(); $results = []; $query = trim($query);
        foreach ($index as $entry) {
            $score = $query === '' ? 1.0 : $this->fullTextScore($entry, $query); if ($score <= 0 && $query !== '') continue;
            if (!empty($filters['tags'])) { $requiredTags = array_filter(array_map('trim', (array) $filters['tags'])); if (array_diff($requiredTags, $entry['tags'] ?? [])) continue; }
            if (!empty($filters['categories'])) { $required = array_filter(array_map('trim', (array) $filters['categories'])); if (array_diff($required, $entry['categories'] ?? [])) continue; }
            $ingredients = array_map('normalize_term', $entry['ingredient_names'] ?? []); $include = array_map('normalize_term', array_filter((array) ($filters['include_ingredients'] ?? []))); $exclude = array_map('normalize_term', array_filter((array) ($filters['exclude_ingredients'] ?? [])));
            if ($include && array_diff($include, $ingredients)) continue; if ($exclude && array_intersect($exclude, $ingredients)) continue;
            $recipe = $this->recipeStore->findById((string) $entry['id']); if ($recipe === null) continue; $recipe['_score'] = $score; $results[] = $recipe;
        }
        $sort = $filters['sort'] ?? 'relevance';
        usort($results, static function (array $a, array $b) use ($sort): int { return match ($sort) { 'latest' => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0), 'rating' => (($b['rating_avg'] ?? 0) <=> ($a['rating_avg'] ?? 0)) ?: (($b['rating_count'] ?? 0) <=> ($a['rating_count'] ?? 0)), 'title' => strcasecmp($a['title'] ?? '', $b['title'] ?? ''), default => (($b['_score'] ?? 0) <=> ($a['_score'] ?? 0)) ?: (($b['rating_avg'] ?? 0) <=> ($a['rating_avg'] ?? 0)), }; });
        return $results;
    }
    public function fullTextScore(array $recipe, string $query): float
    {
        $tokens = array_values(array_filter(explode(' ', normalize_term($query)))); if ($tokens === []) return 1.0;
        $haystack = normalize_term(implode(' ', [$recipe['title'] ?? '', $recipe['description'] ?? '', implode(' ', $recipe['tags'] ?? []), implode(' ', $recipe['categories'] ?? []), implode(' ', $recipe['ingredient_names'] ?? [])]));
        $score = 0.0; foreach ($tokens as $token) if (str_contains($haystack, $token)) $score += 1; return $score / count($tokens);
    }
}
