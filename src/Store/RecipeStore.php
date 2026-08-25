<?php
declare(strict_types=1);
namespace App\Store;
use App\Util\Uuid;
final class RecipeStore
{
    private string $dir;
    public function __construct() { $this->dir = DATA_PATH . '/recipes'; }
    public function create(array $data): array
    {
        $timestamp = current_timestamp();
        $recipe = array_merge(['id' => Uuid::generateV4(), 'author_id' => '', 'title' => '', 'slug' => 'recipe', 'description' => '', 'servings' => 4, 'yield_amount' => null, 'yield_unit' => null, 'ingredients' => [], 'steps' => [], 'tags' => [], 'categories' => [], 'images' => [], 'status' => 'draft', 'deleted_at' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp, 'rating_avg' => 0.0, 'rating_count' => 0], $data);
        $recipe['slug'] = $this->ensureUniqueSlug($recipe['slug'] ?: slugify($recipe['title']), $recipe['id']); $this->save($recipe); return $recipe;
    }
    public function save(array $recipe): void { write_json_file($this->pathFor($recipe['id']), $recipe); }
    public function findById(string $id): ?array { if (!is_valid_uuid($id)) return null; $path = $this->pathFor($id); return is_file($path) ? read_json_file($path) : null; }
    public function findBySlug(string $slug): ?array { foreach ($this->listAll() as $recipe) if (($recipe['slug'] ?? '') === $slug) return $recipe; return null; }
    public function update(string $id, array $data): array { $recipe = $this->findById($id); if ($recipe === null) throw new \RuntimeException('Recipe not found.'); $recipe = array_merge($recipe, $data, ['updated_at' => current_timestamp()]); $recipe['slug'] = $this->ensureUniqueSlug($recipe['slug'] ?: slugify($recipe['title']), $id); $this->save($recipe); return $recipe; }
    public function softDelete(string $id): void { $this->update($id, ['deleted_at' => current_timestamp(), 'status' => 'draft']); }
    public function restore(string $id): void { $this->update($id, ['deleted_at' => null, 'status' => 'published']); }
    public function hardDelete(string $id): void { $path = $this->pathFor($id); if (is_file($path)) unlink($path); }
    public function listPublished(int $page, int $perPage, string $sort): array
    {
        $recipes = array_values(array_filter($this->listAll(), static fn(array $recipe): bool => ($recipe['status'] ?? '') === 'published' && empty($recipe['deleted_at'])));
        usort($recipes, static function (array $a, array $b) use ($sort): int { return match ($sort) { 'rating' => ($b['rating_avg'] <=> $a['rating_avg']) ?: (($b['rating_count'] ?? 0) <=> ($a['rating_count'] ?? 0)), 'title' => strcasecmp($a['title'] ?? '', $b['title'] ?? ''), default => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0), }; });
        $offset = max(0, ($page - 1) * $perPage); return ['items' => array_slice($recipes, $offset, $perPage), 'total' => count($recipes), 'page' => $page, 'per_page' => $perPage];
    }
    public function listByAuthor(string $authorId): array { return array_values(array_filter($this->listAll(), static fn(array $recipe): bool => ($recipe['author_id'] ?? '') === $authorId && empty($recipe['deleted_at']))); }
    public function listAll(): array { $recipes = []; foreach (glob($this->dir . '/*.json') ?: [] as $file) { $recipe = read_json_file($file); if ($recipe !== []) $recipes[] = $recipe; } return $recipes; }
    public function countAll(): int { return count($this->listAll()); }
    private function ensureUniqueSlug(string $slug, string $id): string { $base = slugify($slug); $candidate = $base; $suffix = 2; while (($existing = $this->findBySlug($candidate)) !== null && ($existing['id'] ?? '') !== $id) { $candidate = $base . '-' . $suffix; $suffix++; } return $candidate; }
    private function pathFor(string $id): string { return $this->dir . '/' . $id . '.json'; }
}
