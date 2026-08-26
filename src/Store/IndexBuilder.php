<?php
declare(strict_types=1);
namespace App\Store;
use App\Core\Database;
/**
 * IndexBuilder is now a thin wrapper around direct MySQL queries.
 * The rebuild() method is a no-op because MySQL itself is always the source of truth —
 * no denormalized JSON index files are needed.
 */
final class IndexBuilder
{
    private \PDO $db;
    public function __construct(private RecipeStore $recipeStore) { $this->db = Database::getInstance(); }

    /** No-op: MySQL eliminates the need for pre-built JSON indexes. */
    public function rebuild(): void {}
    /** @deprecated */
    public function rebuildSearch(): void {}
    /** @deprecated */
    public function rebuildTags(): void {}

    public function getSearchIndex(): array
    {
        $stmt = $this->db->query(
            "SELECT id, title, slug, description, tags, categories, ingredients, rating_avg, rating_count, author_id, created_at
             FROM recipes WHERE status = 'published' AND deleted_at IS NULL"
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $ingredients = json_decode((string)$row['ingredients'], true) ?? [];
            $rows[] = [
                'id'               => $row['id'],
                'title'            => $row['title'],
                'slug'             => $row['slug'],
                'description'      => $row['description'],
                'tags'             => json_decode((string)$row['tags'], true) ?? [],
                'categories'       => json_decode((string)$row['categories'], true) ?? [],
                'ingredient_names' => array_values(array_map(static fn(array $i): string => (string)($i['name'] ?? ''), $ingredients)),
                'rating_avg'       => (float)$row['rating_avg'],
                'rating_count'     => (int)$row['rating_count'],
                'author_id'        => $row['author_id'],
                'created_at'       => (int)$row['created_at'],
            ];
        }
        return $rows;
    }

    public function getTagsIndex(): array
    {
        $stmt = $this->db->query("SELECT id, tags FROM recipes WHERE status = 'published' AND deleted_at IS NULL");
        $index = [];
        foreach ($stmt->fetchAll() as $row) {
            foreach (json_decode((string)$row['tags'], true) ?? [] as $tag) {
                $index[$tag][] = $row['id'];
            }
        }
        ksort($index);
        return $index;
    }

    public function getCategoryIndex(): array
    {
        $stmt = $this->db->query("SELECT id, categories FROM recipes WHERE status = 'published' AND deleted_at IS NULL");
        $index = [];
        foreach ($stmt->fetchAll() as $row) {
            foreach (json_decode((string)$row['categories'], true) ?? [] as $cat) {
                $index[$cat][] = $row['id'];
            }
        }
        ksort($index);
        return $index;
    }
}


