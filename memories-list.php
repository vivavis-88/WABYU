<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/api.php';

wabyu_api_guard('GET', false);
try { wabyu_retry_pending(); } catch (Throwable $e) { wabyu_log('retry_pending: ' . $e->getMessage()); }
try {
    wabyu_json_response(['ok' => true, 'memories' => wabyu_list_memories()]);
} catch (WabyuStorageException $e) {
    wabyu_log('List failed: ' . $e->getMessage());
    wabyu_json_response(['ok' => false, 'error' => 'The album data could not be read. Your files were left untouched; see the README recovery steps.'], 500);
}
