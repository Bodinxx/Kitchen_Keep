<?php
declare(strict_types=1);
namespace App\Store;
use App\Core\Database;
use App\Util\Uuid;
final class RecipeStore
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function create(array $data): array
    {
        $timestamp = current_timestamp();
        $recipe = array_merge([
            'id' => Uuid::generateV4(), 'author_id' => '', 'title' => '', 'slug' => 'recipe',
            'description' => '', 'servings' => 4, 'yield_amount' => null, 'yield_unit' => null,
            'ingredients' => [], 'steps' => [], 'tags' => [], 'categories' => [], 'images' => [],
            'status' => 'draft', 'deleted_at' => null, 'created_at' => $timestamp,
            'updated_at' => $timestamp, 'rating_avg' => 0.0, 'rating_count' => 0,
        ], $data);
        $recipe['slug'] = $this->ensureUniqueSlug($recipe['slug'] ?: slugify($recipe['title']), $recipe['id']);
        $this->insert($recipe);
        return $recipe;
    }

    public function findById(string $id): ?array
    {
        if (!is_valid_uuid($id)) return null;
        $stmt = $this->db->prepare('SELECT * FROM recipes WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM recipes WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function update(string $id, array $data): array
    {
        $recipe = $this->findById($id);
        if ($recipe === null) throw new \RuntimeException('Recipe not found.');
        $recipe = array_merge($recipe, $data, ['updated_at' => current_timestamp()]);
        if (isset($data['title']) || isset($data['slug'])) {
            $recipe['slug'] = $this->ensureUniqueSlug($recipe['slug'] ?: slugify($recipe['title']), $id);
        }
        $this->upsert($recipe);
        return $recipe;
    }

    public function softDelete(string $id): void { $this->update($id, ['deleted_at' => current_timestamp(), 'status' => 'draft']); }
    public function restore(string $id): void { $this->update($id, ['deleted_at' => null, 'status' => 'published']); }

    public function hardDelete(string $id): void
    {
        $this->db->prepare('DELETE FROM recipes WHERE id = ?')->execute([$id]);
    }

    public function listPublished(int $page, int $perPage, string $sort): array
    {
        $orderBy = match ($sort) {
            'rating' => 'rating_avg DESC, rating_count DESC',
            'title'  => 'title ASC',
            default  => 'created_at DESC',
        };
        $total  = (int) $this->db->query("SELECT COUNT(*) FROM recipes WHERE status = 'published' AND deleted_at IS NULL")->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $stmt   = $this->db->prepare("SELECT * FROM recipes WHERE status = 'published' AND deleted_at IS NULL ORDER BY $orderBy LIMIT ? OFFSET ?");
        $stmt->execute([$perPage, $offset]);
        return ['items' => array_map([$this, 'decode'], $stmt->fetchAll()), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function listByAuthor(string $authorId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM recipes WHERE author_id = ? AND deleted_at IS NULL ORDER BY created_at DESC');
        $stmt->execute([$authorId]);
        return array_map([$this, 'decode'], $stmt->fetchAll());
    }

    public function listAll(): array
    {
        return array_map([$this, 'decode'], $this->db->query('SELECT * FROM recipes')->fetchAll());
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM recipes')->fetchColumn();
    }

    // ------------------------------------------------------------------
    private function ensureUniqueSlug(string $slug, string $id): string
    {
        $base = slugify($slug); $candidate = $base; $suffix = 2;
        while (($existing = $this->findBySlug($candidate)) !== null && $existing['id'] !== $id) {
            $candidate = $base . '-' . $suffix++;
        }
        return $candidate;
    }

    private function insert(array $r): void
    {
        $this->db->prepare(
            'INSERT INTO recipes (id,author_id,title,slug,description,servings,yield_amount,yield_unit,ingredients,steps,tags,categories,images,status,deleted_at,created_at,updated_at,rating_avg,rating_count)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $r['id'], $r['author_id'], $r['title'], $r['slug'], $r['description'],
            $r['servings'], $r['yield_amount'], $r['yield_unit'],
            json_encode($r['ingredients']), json_encode($r['steps']),
            json_encode($r['tags']), json_encode($r['categories']), json_encode($r['images']),
            $r['status'], $r['deleted_at'], $r['created_at'], $r['updated_at'],
            $r['rating_avg'], $r['rating_count'],
        ]);
    }

    private function upsert(array $r): void
    {
        $this->db->prepare(
            'UPDATE recipes SET author_id=?,title=?,slug=?,description=?,servings=?,yield_amount=?,yield_unit=?,ingredients=?,steps=?,tags=?,categories=?,images=?,status=?,deleted_at=?,updated_at=?,rating_avg=?,rating_count=? WHERE id=?'
        )->execute([
            $r['author_id'], $r['title'], $r['slug'], $r['description'],
            $r['servings'], $r['yield_amount'], $r['yield_unit'],
            json_encode($r['ingredients']), json_encode($r['steps']),
            json_encode($r['tags']), json_encode($r['categories']), json_encode($r['images']),
            $r['status'], $r['deleted_at'], $r['updated_at'],
            $r['rating_avg'], $r['rating_count'], $r['id'],
        ]);
    }

    private function decode(array $row): array
    {
        foreach (['ingredients', 'steps', 'tags', 'categories', 'images'] as $col) {
            $row[$col] = is_string($row[$col]) ? (json_decode($row[$col], true) ?? []) : ($row[$col] ?? []);
        }
        $row['rating_avg']   = (float) $row['rating_avg'];
        $row['rating_count'] = (int)   $row['rating_count'];
        $row['servings']     = (int)   $row['servings'];
        return $row;
    }
}

