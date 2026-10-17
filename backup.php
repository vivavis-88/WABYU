<?php
declare(strict_types=1);
/**
 * Command-line backup: copies data/memories.json and uploads/memories into
 * database-backups/<timestamp>/. Run:  php backup.php   (safe while Apache is running)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/includes/storage.php';

wabyu_init_storage();
$dest = WABYU_BACKUP_DIR . '/' . gmdate('Ymd-His');
$json = wabyu_with_lock(static fn() => (string)file_get_contents(WABYU_JSON_FILE));   // consistent snapshot
if (!mkdir($dest . '/uploads/memories', 0775, true) && !is_dir($dest)) { fwrite(STDERR, "Cannot create backup folder.\n"); exit(1); }
file_put_contents($dest . '/memories.json', $json);

$missing = 0; $copied = 0;
foreach ((json_decode($json, true)['memories'] ?? []) as $m) {
    $abs = wabyu_image_abs((string)($m['image'] ?? ''));
    if ($abs !== null && is_file($abs)) {
        if (copy($abs, $dest . '/uploads/memories/' . basename($abs))) { $copied++; } else { $missing++; }
    } else { $missing++; }
}
file_put_contents($dest . '/RESTORE.txt', "Restore: stop Apache, copy memories.json to data/memories.json and uploads/memories/* into the project's uploads/memories, then start Apache.\n");

$all = array_values(array_filter(glob(WABYU_BACKUP_DIR . '/*', GLOB_ONLYDIR) ?: [], static fn($d) => preg_match('/\d{8}-\d{6}$/', $d)));
sort($all);
foreach (array_slice($all, 0, max(0, count($all) - WABYU_BACKUP_KEEP)) as $old) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($old, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($old);
}
echo "Backup saved to $dest ($copied photos" . ($missing ? ", $missing missing/failed" : '') . ").\nCopy this folder somewhere off this computer too.\n";
exit($missing ? 2 : 0);
