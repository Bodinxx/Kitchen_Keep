<?php
declare(strict_types=1);
namespace App\Service;
final class BackupService
{
    public function createBackup(): string
    {
        if (!class_exists('ZipArchive')) throw new \RuntimeException('ZipArchive is required to create backups.');
        $filename = 'backup-' . date('Ymd-His') . '.zip'; $path = DATA_PATH . '/backups/' . $filename; $zip = new \ZipArchive(); if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Unable to create backup archive.');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(DATA_PATH, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) { if (str_contains($file->getPathname(), '/backups/')) continue; $localName = substr($file->getPathname(), strlen(DATA_PATH) + 1); $zip->addFile($file->getPathname(), $localName); }
        $zip->close(); return $path;
    }
    public function listBackups(): array { $backups = []; foreach (glob(DATA_PATH . '/backups/*.zip') ?: [] as $file) $backups[] = ['name' => basename($file), 'path' => $file, 'size' => filesize($file) ?: 0, 'created_at' => filemtime($file) ?: 0]; usort($backups, static fn(array $a, array $b): int => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0)); return $backups; }
}
