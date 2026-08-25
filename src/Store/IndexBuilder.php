<?php
declare(strict_types=1);
namespace App\Store;
final class IndexBuilder
{
    public function __construct(private RecipeStore $recipeStore) {}
    public function rebuild(): void { $this->rebuildSearch(); $this->rebuildTags(); }
    public function rebuildSearch(): void
    {
        $search = []; $browse = [];
        foreach ($this->recipeStore->listAll() as $recipe) {
            if (!empty($recipe['deleted_at']) || ($recipe['status'] ?? '') !== 'published') continue;
            $entry = ['id' => $recipe['id'], 'title' => $recipe['title'], 'slug' => $recipe['slug'], 'description' => $recipe['description'], 'tags' => $recipe['tags'] ?? [], 'categories' => $recipe['categories'] ?? [], 'ingredient_names' => array_values(array_map(static fn(array $ingredient): string => (string) ($ingredient['name'] ?? ''), $recipe['ingredients'] ?? [])), 'rating_avg' => $recipe['rating_avg'] ?? 0, 'rating_count' => $recipe['rating_count'] ?? 0, 'author_id' => $recipe['author_id'] ?? '', 'created_at' => $recipe['created_at'] ?? 0];
            $search[] = $entry; $browse[] = $entry;
        }
        usort($browse, static fn(array $a, array $b): int => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
        write_json_file(DATA_PATH . '/indexes/search.json', $search); write_json_file(DATA_PATH . '/indexes/browse.json', $browse);
    }
    public function rebuildTags(): void
    {
        $tags = []; $categories = [];
        foreach ($this->recipeStore->listAll() as $recipe) {
            if (!empty($recipe['deleted_at']) || ($recipe['status'] ?? '') !== 'published') continue;
            foreach ($recipe['tags'] ?? [] as $tag) $tags[$tag][] = $recipe['id'];
            foreach ($recipe['categories'] ?? [] as $category) $categories[$category][] = $recipe['id'];
        }
        ksort($tags); ksort($categories); write_json_file(DATA_PATH . '/indexes/tags.json', $tags); write_json_file(DATA_PATH . '/indexes/categories.json', $categories);
    }
    public function getSearchIndex(): array { return read_json_file(DATA_PATH . '/indexes/search.json', []); }
    public function getBrowseIndex(): array { return read_json_file(DATA_PATH . '/indexes/browse.json', []); }
}
