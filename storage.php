<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

final class WabyuStorageException extends RuntimeException {}

const WABYU_JSON_FILE    = WABYU_DATA_DIR . '/memories.json';
const WABYU_PENDING_FILE = WABYU_DATA_DIR . '/pending-deletes.json';
const WABYU_LOCK_FILE    = WABYU_DATA_DIR . '/.lock';

/** Creates folders / protection files / an empty JSON file. Never resets existing data. */
function wabyu_init_storage(): void
{
    foreach ([WABYU_DATA_DIR, WABYU_UPLOAD_DIR, WABYU_BACKUP_DIR] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new WabyuStorageException('Cannot create directory.');
        }
    }
    $deny = "Require all denied\n";
    foreach ([WABYU_DATA_DIR, WABYU_UPLOAD_DIR, WABYU_BACKUP_DIR, WABYU_ROOT . '/config', WABYU_ROOT . '/includes'] as $dir) {
        if (is_dir($dir) && !is_file($dir . '/.htaccess')) { @file_put_contents($dir . '/.htaccess', $deny); }
    }
    if (!is_file(WABYU_JSON_FILE)) {
        wabyu_with_lock(static function (): void {
            if (!is_file(WABYU_JSON_FILE)) { wabyu_write_all([]); }
        });
    }
}

function wabyu_with_lock(callable $fn): mixed
{
    $h = fopen(WABYU_LOCK_FILE, 'c');
    if ($h === false) { throw new WabyuStorageException('Cannot open lock file.'); }
    try {
        if (!flock($h, LOCK_EX)) { throw new WabyuStorageException('Cannot lock storage.'); }
        return $fn();
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

/** @return array<int,array<string,mixed>>  Throws (and leaves the file untouched) if JSON is unreadable. */
function wabyu_read_all(): array
{
    $raw = @file_get_contents(WABYU_JSON_FILE);
    if ($raw === false) { throw new WabyuStorageException('memories.json unreadable.'); }
    $data = json_decode($raw, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE || !isset($data['memories']) || !is_array($data['memories'])) {
        wabyu_log('memories.json is invalid; left untouched for manual recovery (see memories.json.bak).');
        throw new WabyuStorageException('memories.json invalid.');
    }
    foreach ($data['memories'] as $m) {
        if (!is_array($m) || !isset($m['id'], $m['image'])) { throw new WabyuStorageException('Malformed record.'); }
    }
    return array_values($data['memories']);
}

/** Atomic write. Call only while holding the lock. */
function wabyu_write_all(array $memories): void
{
    $json = json_encode(['memories' => array_values($memories)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    wabyu_atomic_write(WABYU_JSON_FILE, $json, true);
}

/** Temp file -> flush -> keep .bak of previous -> rename over the target. */
function wabyu_atomic_write(string $target, string $content, bool $keepBackup = false): void
{
    $tmp = $target . '.tmp' . bin2hex(random_bytes(4));
    $h = fopen($tmp, 'wb');
    if ($h === false) { throw new WabyuStorageException('Cannot create temp file.'); }
    $ok = fwrite($h, $content) === strlen($content) && fflush($h);
    fclose($h);
    if (!$ok) { @unlink($tmp); throw new WabyuStorageException('Write failed.'); }
    if ($keepBackup && is_file($target)) { @copy($target, $target . '.bak'); }
    if (!rename($tmp, $target)) { @unlink($tmp); throw new WabyuStorageException('Cannot replace data file.'); }
}

function wabyu_valid_id(mixed $id): bool
{
    return is_string($id) && preg_match('/^[a-f0-9]{16}$/', $id) === 1;
}

/** Only server-generated names inside the managed upload folder are ever touched. */
function wabyu_image_abs(string $rel): ?string
{
    if (preg_match('#^' . preg_quote(WABYU_UPLOAD_REL, '#') . '/[a-f0-9]{32}\.(jpg|png|webp)$#', $rel) !== 1) { return null; }
    return WABYU_ROOT . '/' . $rel;
}

function wabyu_store_image(array $file): string
{
    $ext = wabyu_validate_upload($file);   // validation.php (throws WabyuValidationException)
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = WABYU_UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { throw new WabyuStorageException('Cannot store image.'); }
    if (filesize($dest) !== (int)$file['size']) { @unlink($dest); throw new WabyuStorageException('Stored image size mismatch.'); }
    @chmod($dest, 0644);
    return WABYU_UPLOAD_REL . '/' . $name;
}

/** Removes an image; if that fails it is queued and retried later. Returns true when the file is gone. */
function wabyu_remove_image(string $rel): bool
{
    $abs = wabyu_image_abs($rel);
    if ($abs === null) { return true; }
    if (!file_exists($abs) || @unlink($abs)) { return true; }
    wabyu_queue_pending($rel);
    return false;
}

function wabyu_queue_pending(string $rel): void
{
    try {
        wabyu_with_lock(static function () use ($rel): void {
            $list = wabyu_read_pending();
            if (!in_array($rel, $list, true)) { $list[] = $rel; }
            wabyu_atomic_write(WABYU_PENDING_FILE, json_encode(array_values($list)));
        });
    } catch (Throwable $e) { wabyu_log('Could not queue pending delete: ' . $e->getMessage()); }
}

function wabyu_read_pending(): array
{
    $d = is_file(WABYU_PENDING_FILE) ? json_decode((string)file_get_contents(WABYU_PENDING_FILE), true) : [];
    return is_array($d) ? array_values(array_filter($d, 'is_string')) : [];
}

function wabyu_retry_pending(): void
{
    if (!is_file(WABYU_PENDING_FILE)) { return; }
    wabyu_with_lock(static function (): void {
        $left = [];
        foreach (wabyu_read_pending() as $rel) {
            $abs = wabyu_image_abs($rel);
            if ($abs !== null && file_exists($abs) && !@unlink($abs)) { $left[] = $rel; }
        }
        if ($left) { wabyu_atomic_write(WABYU_PENDING_FILE, json_encode($left)); } else { @unlink(WABYU_PENDING_FILE); }
    });
}

function wabyu_public_view(array $m): array
{
    return [
        'id'          => $m['id'],
        'title'       => (string)($m['title'] ?? ''),
        'comment'     => (string)($m['comment'] ?? ''),
        'category'    => (string)($m['category'] ?? ''),
        'favorite'    => (bool)($m['favorite'] ?? false),
        'memory_date' => (string)($m['memory_date'] ?? ''),
        'created_at'  => (string)($m['created_at'] ?? ''),
        'updated_at'  => (string)($m['updated_at'] ?? ''),
        'image_url'   => 'api/image.php?f=' . rawurlencode(basename((string)$m['image'])),
    ];
}

function wabyu_list_memories(): array
{
    $all = wabyu_read_all();
    usort($all, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_map('wabyu_public_view', $all);
}

function wabyu_create_memory(array $clean, array $file): array
{
    $rel = wabyu_store_image($file);
    try {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $record = [
            'id' => bin2hex(random_bytes(8)), 'title' => $clean['title'], 'comment' => $clean['comment'],
            'image' => $rel, 'category' => $clean['category'], 'favorite' => $clean['favorite'],
            'memory_date' => $clean['memory_date'], 'created_at' => $now, 'updated_at' => $now,
        ];
        wabyu_with_lock(static function () use ($record): void {
            $all = wabyu_read_all();
            $all[] = $record;
            wabyu_write_all($all);
        });
        return wabyu_public_view($record);
    } catch (Throwable $e) {
        wabyu_remove_image($rel);   // no orphan left behind; existing records untouched
        throw $e;
    }
}

/** @param array<string,mixed> $clean only the fields to change */
function wabyu_update_memory(string $id, array $clean, ?array $file): ?array
{
    $newRel = $file ? wabyu_store_image($file) : null;
    $oldRel = null;
    try {
        $result = wabyu_with_lock(static function () use ($id, $clean, $newRel, &$oldRel): ?array {
            $all = wabyu_read_all();
            foreach ($all as $i => $m) {
                if ($m['id'] !== $id) { continue; }
                foreach ($clean as $k => $v) { $m[$k] = $v; }
                if ($newRel !== null) { $oldRel = (string)$m['image']; $m['image'] = $newRel; }
                $m['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
                $all[$i] = $m;
                wabyu_write_all($all);
                return $m;
            }
            return null;
        });
    } catch (Throwable $e) {
        if ($newRel !== null) { wabyu_remove_image($newRel); }
        throw $e;
    }
    if ($result === null) {
        if ($newRel !== null) { wabyu_remove_image($newRel); }
        return null;
    }
    if ($oldRel !== null) { wabyu_remove_image($oldRel); }   // only after the new record is committed
    return wabyu_public_view($result);
}

/** @return array{found:bool,image_removed:bool} */
function wabyu_delete_memory(string $id): array
{
    $rel = null;
    $found = wabyu_with_lock(static function () use ($id, &$rel): bool {
        $all = wabyu_read_all();
        foreach ($all as $i => $m) {
            if ($m['id'] === $id) {
                $rel = (string)$m['image'];
                unset($all[$i]);
                wabyu_write_all($all);   // record removed first; if this fails nothing was deleted
                return true;
            }
        }
        return false;
    });
    if (!$found) { return ['found' => false, 'image_removed' => true]; }
    return ['found' => true, 'image_removed' => $rel === null ? true : wabyu_remove_image($rel)];
}
