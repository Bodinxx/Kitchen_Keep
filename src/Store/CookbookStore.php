<?php
declare(strict_types=1);
namespace App\Store;
use App\Util\Uuid;
final class CookbookStore
{
    public function create(string $userId, array $data): array { $timestamp = current_timestamp(); $cookbook = array_merge(['id' => Uuid::generateV4(), 'owner_id' => $userId, 'title' => '', 'description' => '', 'share_token' => null, 'sections' => [], 'created_at' => $timestamp, 'updated_at' => $timestamp], $data); $this->save($userId, $cookbook); return $cookbook; }
    public function findById(string $userId, string $cookbookId): ?array { $path = $this->pathFor($userId, $cookbookId); return is_file($path) ? read_json_file($path) : null; }
    public function findByShareToken(string $token): ?array { foreach (glob(DATA_PATH . '/cookbooks/*/*.json') ?: [] as $file) { $cookbook = read_json_file($file); if (($cookbook['share_token'] ?? null) === $token) return $cookbook; } return null; }
    public function list(string $userId): array { $items = []; foreach (glob(DATA_PATH . '/cookbooks/' . $userId . '/*.json') ?: [] as $file) $items[] = read_json_file($file); usort($items, static fn(array $a, array $b): int => ($b['updated_at'] ?? 0) <=> ($a['updated_at'] ?? 0)); return $items; }
    public function update(string $userId, string $cookbookId, array $data): array { $cookbook = $this->findById($userId, $cookbookId); if ($cookbook === null) throw new \RuntimeException('Cookbook not found.'); $cookbook = array_merge($cookbook, $data, ['updated_at' => current_timestamp()]); $this->save($userId, $cookbook); return $cookbook; }
    public function delete(string $userId, string $cookbookId): void { $path = $this->pathFor($userId, $cookbookId); if (is_file($path)) unlink($path); }
    private function save(string $userId, array $cookbook): void { write_json_file($this->pathFor($userId, $cookbook['id']), $cookbook); }
    private function pathFor(string $userId, string $cookbookId): string { return DATA_PATH . '/cookbooks/' . $userId . '/' . $cookbookId . '.json'; }
}
