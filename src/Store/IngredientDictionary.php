<?php
declare(strict_types=1);
namespace App\Store;
use App\Core\Database;
use App\Util\Uuid;
final class IngredientDictionary
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ingredients WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->decode($row) : null;
    }

    public function findByName(string $name): ?array
    {
        $needle = normalize_term($name);
        // Exact name match first
        $stmt = $this->db->prepare('SELECT * FROM ingredients WHERE LOWER(name) = ?');
        $stmt->execute([$needle]);
        $row = $stmt->fetch();
        if ($row) return $this->decode($row);
        // Check aliases in PHP (aliases stored as JSON)
        foreach ($this->all() as $item) {
            foreach ($item['aliases'] ?? [] as $alias) {
                if (normalize_term((string) $alias) === $needle) return $item;
            }
        }
        return null;
    }

    public function search(string $query): array
    {
        $needle = normalize_term($query);
        if ($needle === '') {
            $stmt = $this->db->query('SELECT * FROM ingredients LIMIT 10');
            return array_map([$this, 'decode'], $stmt->fetchAll());
        }
        $results = [];
        foreach ($this->all() as $item) {
            foreach (array_merge([$item['name']], $item['aliases'] ?? []) as $haystack) {
                if (str_contains(normalize_term((string) $haystack), $needle)) {
                    $results[$item['id']] = $item;
                    break;
                }
            }
            if (count($results) >= 12) break;
        }
        return array_slice(array_values($results), 0, 12);
    }

    public function add(array $data): array
    {
        $item = array_merge([
            'id' => Uuid::generateV4(), 'name' => '', 'aliases' => [], 'category' => 'general',
            'density_g_per_ml' => null, 'created_by' => null, 'created_at' => current_timestamp(),
        ], $data);
        $this->db->prepare(
            'INSERT INTO ingredients (id,name,aliases,category,density_g_per_ml,created_by,created_at) VALUES (?,?,?,?,?,?,?)'
        )->execute([$item['id'], $item['name'], json_encode($item['aliases']), $item['category'], $item['density_g_per_ml'], $item['created_by'], $item['created_at']]);
        return $item;
    }

    public function update(string $id, array $data): array
    {
        $item = $this->findById($id);
        if ($item === null) throw new \RuntimeException('Ingredient not found.');
        $item = array_merge($item, $data);
        $this->db->prepare(
            'UPDATE ingredients SET name=?,aliases=?,category=?,density_g_per_ml=? WHERE id=?'
        )->execute([$item['name'], json_encode($item['aliases']), $item['category'], $item['density_g_per_ml'], $id]);
        return $item;
    }

    public function all(): array
    {
        return array_map([$this, 'decode'], $this->db->query('SELECT * FROM ingredients ORDER BY name ASC')->fetchAll());
    }

    /** @deprecated kept for compatibility */
    public function save(): void {}

    private function decode(array $row): array
    {
        $row['aliases'] = is_string($row['aliases']) ? (json_decode($row['aliases'], true) ?? []) : ($row['aliases'] ?? []);
        return $row;
    }
}

