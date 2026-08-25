<?php
declare(strict_types=1);
namespace App\Store;
use App\Util\Uuid;
final class IngredientDictionary
{
    private string $path; private array $items;
    public function __construct() { $this->path = DATA_PATH . '/ingredients/dictionary.json'; $this->items = read_json_file($this->path, []); }
    public function findById(string $id): ?array { return $this->items[$id] ?? null; }
    public function findByName(string $name): ?array { $needle = normalize_term($name); foreach ($this->items as $item) { if (normalize_term((string) $item['name']) === $needle) return $item; foreach ($item['aliases'] ?? [] as $alias) if (normalize_term((string) $alias) === $needle) return $item; } return null; }
    public function search(string $query): array { $needle = normalize_term($query); if ($needle === '') return array_slice(array_values($this->items), 0, 10); $results = []; foreach ($this->items as $item) { foreach (array_merge([$item['name']], $item['aliases'] ?? []) as $haystack) { if (str_contains(normalize_term((string) $haystack), $needle)) { $results[$item['id']] = $item; break; } } } return array_slice(array_values($results), 0, 12); }
    public function add(array $data): array { $item = array_merge(['id' => Uuid::generateV4(), 'name' => '', 'aliases' => [], 'category' => 'general', 'density_g_per_ml' => null, 'created_by' => null, 'created_at' => current_timestamp()], $data); $this->items[$item['id']] = $item; $this->save(); return $item; }
    public function update(string $id, array $data): array { if (!isset($this->items[$id])) throw new \RuntimeException('Ingredient not found.'); $this->items[$id] = array_merge($this->items[$id], $data); $this->save(); return $this->items[$id]; }
    public function all(): array { return $this->items; }
    public function save(): void { write_json_file($this->path, $this->items); }
}
