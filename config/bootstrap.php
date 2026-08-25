<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
    define('CONFIG_PATH', BASE_PATH . '/config');
    define('DATA_PATH', BASE_PATH . '/data');
    define('PUBLIC_PATH', BASE_PATH . '/public');
    define('SRC_PATH', BASE_PATH . '/src');
    define('TEMPLATES_PATH', BASE_PATH . '/templates');
    define('SITE_CONFIG_PATH', DATA_PATH . '/config/site.json');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', DATA_PATH . '/logs/php-error.log');

foreach ([DATA_PATH, DATA_PATH . '/recipes', DATA_PATH . '/users', DATA_PATH . '/ratings', DATA_PATH . '/cookbooks', DATA_PATH . '/ingredients', DATA_PATH . '/indexes', DATA_PATH . '/moderation', DATA_PATH . '/config', DATA_PATH . '/media', DATA_PATH . '/backups', DATA_PATH . '/logs', PUBLIC_PATH . '/uploads'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = SRC_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_name('kitchen_keep');
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);
}

function read_json_file(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }
    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return $default;
    }
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $default;
}

function write_json_file(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open file: ' . $path);
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock file: ' . $path);
        }
        ftruncate($handle, 0);
        rewind($handle);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode JSON: ' . $path);
        }
        fwrite($handle, $json . PHP_EOL);
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function append_json_record(string $path, array $record): void
{
    $data = read_json_file($path, []);
    $data[] = $record;
    write_json_file($path, $data);
}

$GLOBALS['site_config'] = read_json_file(SITE_CONFIG_PATH, ['site_name' => 'Kitchen Keep', 'tagline' => 'Discover, create, and share recipes', 'default_theme' => 'light', 'items_per_page' => 12, 'from_email' => 'noreply@kitchenkeep.local', 'from_name' => 'Kitchen Keep']);
define('APP_SECRET', hash('sha256', json_encode($GLOBALS['site_config']) . BASE_PATH));

function site_config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['site_config'] ?? [];
    return $key === null ? $config : ($config[$key] ?? $default);
}
function save_site_config(array $config): void
{
    $GLOBALS['site_config'] = $config;
    write_json_file(SITE_CONFIG_PATH, $config);
}
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function current_timestamp(): int
{
    return time();
}
function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'recipe';
}
function normalize_term(string $value): string
{
    $value = strtolower(trim($value));
    return preg_replace('/\s+/', ' ', $value) ?? $value;
}
function format_datetime(?int $timestamp): string
{
    return $timestamp ? date('M j, Y', $timestamp) : '';
}
function format_quantity(float|int|string|null $value): string
{
    if ($value === null || $value === '') return '';
    $number = (float) $value;
    $whole = floor($number);
    $fraction = round(($number - $whole) * 8) / 8;
    foreach ([0.125 => '⅛', 0.25 => '¼', 0.333 => '⅓', 0.375 => '⅜', 0.5 => '½', 0.625 => '⅝', 0.667 => '⅔', 0.75 => '¾', 0.875 => '⅞'] as $decimal => $label) {
        if (abs($fraction - $decimal) < 0.03) {
            return trim(($whole > 0 ? (string) $whole . ' ' : '') . $label);
        }
    }
    if (abs($number - round($number)) < 0.001) return (string) (int) round($number);
    return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
}
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}
function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}
function put_old(array $data): void
{
    $_SESSION['_old'] = $data;
}
function clear_old(): void
{
    unset($_SESSION['_old']);
}
function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function url(string $path, array $query = []): string
{
    $uri = $path === '' ? '/' : $path;
    if ($query !== []) $uri .= '?' . http_build_query($query);
    return $uri;
}
function is_valid_uuid(string $value): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
}
function render(string $template, array $data = [], string $layout = 'layout/base'): string
{
    $templatePath = TEMPLATES_PATH . '/' . trim($template, '/') . '.php';
    $layoutPath = TEMPLATES_PATH . '/' . trim($layout, '/') . '.php';
    extract($data, EXTR_SKIP);
    $auth = $data['auth'] ?? null;
    $currentUser = $data['currentUser'] ?? ($auth?->getCurrentUser());
    $site = site_config();
    $theme = $data['theme'] ?? ($currentUser['theme'] ?? site_config('default_theme', 'light'));
    ob_start(); include $templatePath; $content = ob_get_clean();
    ob_start(); include $layoutPath; return (string) ob_get_clean();
}
function append_audit(string $action, array $context = []): void
{
    append_json_record(DATA_PATH . '/moderation/audit.json', ['id' => bin2hex(random_bytes(8)), 'action' => $action, 'context' => $context, 'created_at' => current_timestamp()]);
}
function default_categories(): array
{
    return ['Breakfast', 'Lunch', 'Dinner', 'Appetizer', 'Soup', 'Salad', 'Side Dish', 'Bread', 'Dessert', 'Beverage'];
}
