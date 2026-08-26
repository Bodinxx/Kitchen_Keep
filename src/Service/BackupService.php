<?php
declare(strict_types=1);
namespace App\Service;
use App\Core\Database;
final class BackupService
{
    public function createBackup(): string
    {
        if (!class_exists('ZipArchive')) throw new \RuntimeException('ZipArchive is required to create backups.');
        $cfg      = require CONFIG_PATH . '/db.php';
        $filename = 'backup-' . date('Ymd-His') . '.zip';
        $zipPath  = DATA_PATH . '/backups/' . $filename;
        $sqlPath  = DATA_PATH . '/backups/dump-' . date('Ymd-His') . '.sql';

        // Generate a SQL dump via mysqldump if available, otherwise fall back to PDO export.
        $host = escapeshellarg($cfg['host']);
        $user = escapeshellarg($cfg['user']);
        $pass = $cfg['password'];
        $db   = escapeshellarg($cfg['dbname']);
        $cmd  = "mysqldump -h {$host} -u {$user} " . ($pass !== '' ? "-p" . escapeshellarg($pass) . " " : '') . "{$db} > " . escapeshellarg($sqlPath) . " 2>&1";
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            // Fall back: export via PDO SELECT INTO OUTFILE is not portable; use PHP export.
            $sqlPath = $this->pdoDump($sqlPath, $cfg);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create backup archive.');
        }
        $zip->addFile($sqlPath, 'database.sql');
        // Also bundle uploaded media if present.
        if (is_dir(PUBLIC_PATH . '/uploads')) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(PUBLIC_PATH . '/uploads', \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $local = 'uploads/' . substr($file->getPathname(), strlen(PUBLIC_PATH . '/uploads') + 1);
                $zip->addFile($file->getPathname(), $local);
            }
        }
        $zip->close();
        if (is_file($sqlPath)) unlink($sqlPath);
        return $zipPath;
    }

    public function listBackups(): array
    {
        $backups = [];
        foreach (glob(DATA_PATH . '/backups/*.zip') ?: [] as $file) {
            $backups[] = ['name' => basename($file), 'path' => $file, 'size' => filesize($file) ?: 0, 'created_at' => filemtime($file) ?: 0];
        }
        usort($backups, static fn(array $a, array $b): int => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
        return $backups;
    }

    private function pdoDump(string $path, array $cfg): string
    {
        $db   = Database::getInstance();
        $tables = $db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        $sql  = "-- Kitchen Keep MySQL dump (PDO fallback)\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET NAMES utf8mb4;\n\n";
        foreach ($tables as $table) {
            $create = $db->query("SHOW CREATE TABLE `$table`")->fetch();
            $sql   .= ($create['Create Table'] ?? '') . ";\n\n";
            $rows   = $db->query("SELECT * FROM `$table`")->fetchAll();
            foreach ($rows as $row) {
                $vals  = array_map(static fn($v) => $v === null ? 'NULL' : $db->quote((string)$v), $row);
                $cols  = '`' . implode('`, `', array_keys($row)) . '`';
                $sql  .= "INSERT INTO `$table` ($cols) VALUES (" . implode(', ', $vals) . ");\n";
            }
            $sql .= "\n";
        }
        file_put_contents($path, $sql);
        return $path;
    }
}

