<?php
declare(strict_types=1);
namespace App\Core;

/**
 * PDO singleton for the application's MySQL connection.
 * Connection is established lazily on first call and reused throughout the request.
 */
final class Database
{
    private static ?\PDO $instance = null;

    public static function getInstance(): \PDO
    {
        if (self::$instance === null) {
            $cfg = require CONFIG_PATH . '/db.php';
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $cfg['host'],
                $cfg['dbname']
            );
            self::$instance = new \PDO($dsn, $cfg['user'], $cfg['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    /** Prevent instantiation. */
    private function __construct() {}
}
