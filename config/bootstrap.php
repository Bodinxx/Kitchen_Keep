<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
    define('CONFIG_PATH', BASE_PATH . '/config');
    define('DATA_PATH', BASE_PATH . '/data');
    define('PUBLIC_PATH', BASE_PATH . '/public');
    define('SRC_PATH', BASE_PATH . '/src');
    define('TEMPLATES_PATH', BASE_PATH . '/templates');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', DATA_PATH . '/logs/php-error.log');

foreach ([DATA_PATH, DATA_PATH . '/media', DATA_PATH . '/backups', DATA_PATH . '/logs', PUBLIC_PATH . '/uploads'] as $dir) {
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
    $sessionPath = DATA_PATH . '/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_name('kitchen_keep');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/',
        'cookie_secure' => $https,
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ]);
}

// ---------------------------------------------------------------------------
// Database-backed site configuration
// ---------------------------------------------------------------------------

function _db(): \PDO
{
    return \App\Core\Database::getInstance();
}

/** Load all site_config rows into the in-memory cache. */
function _load_site_config(): array
{
    $defaults = [
        'site_name'     => 'Kitchen Keep',
        'tagline'       => 'Discover, create, and share recipes',
        'default_theme' => 'light',
        'items_per_page'=> 12,
        'from_email'    => 'noreply@kitchenkeep.local',
        'from_name'     => 'Kitchen Keep',
    ];
    try {
        $rows = _db()->query('SELECT config_key, config_value FROM site_config')->fetchAll();
        $config = $defaults;
        foreach ($rows as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }
        return $config;
    } catch (\Throwable) {
        return $defaults;
    }
}

$GLOBALS['site_config'] = _load_site_config();
define('APP_SECRET', hash('sha256', BASE_PATH . ($_SERVER['SERVER_NAME'] ?? 'localhost')));

function site_config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['site_config'] ?? [];
    return $key === null ? $config : ($config[$key] ?? $default);
}

function save_site_config(array $config): void
{
    $GLOBALS['site_config'] = $config;
    $db = _db();
    $stmt = $db->prepare('INSERT INTO site_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)');
    foreach ($config as $key => $value) {
        $stmt->execute([$key, (string) $value]);
    }
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
    try {
        _db()->prepare('INSERT INTO audit_log (id, action, context, created_at) VALUES (?, ?, ?, ?)')
            ->execute([bin2hex(random_bytes(8)), $action, json_encode($context), current_timestamp()]);
    } catch (\Throwable) {
        // Audit failures must never break the main request.
    }
}
function default_categories(): array
{
    return ['Breakfast', 'Lunch', 'Dinner', 'Appetizer', 'Soup', 'Salad', 'Side Dish', 'Bread', 'Dessert', 'Beverage'];
}
