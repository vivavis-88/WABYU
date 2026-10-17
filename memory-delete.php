<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/api.php';

wabyu_api_guard('POST');   // POST only + authenticated session + CSRF token

$body = wabyu_json_body();
$id = $body['id'] ?? null;
$password = is_string($body['password'] ?? null) ? substr($body['password'], 0, 256) : '';
if (!wabyu_valid_id($id)) { wabyu_json_response(['ok' => false, 'error' => 'Unknown memory.'], 404); }

$wait = wabyu_rate_blocked('delete');
if ($wait > 0) { wabyu_json_response(['ok' => false, 'error' => wabyu_wait_text($wait)], 429); }

// Case-sensitive, verified only here on the server against a stored hash.
if ($password === '' || !password_verify($password, wabyu_secrets()['delete_hash'])) {
    wabyu_rate_fail('delete');
    usleep(300000);
    wabyu_json_response(['ok' => false, 'error' => 'That confirmation password is not correct.', 'code' => 'bad_password'], 403);
}
wabyu_rate_clear('delete');

try {
    $r = wabyu_delete_memory($id);
} catch (WabyuStorageException $e) {
    wabyu_log('Delete failed: ' . $e->getMessage());
    wabyu_json_response(['ok' => false, 'error' => 'The memory could not be deleted and was left unchanged. Please try again.'], 500);
}
if (!$r['found']) { wabyu_json_response(['ok' => false, 'error' => 'That memory no longer exists.', 'code' => 'not_found'], 404); }
wabyu_json_response([
    'ok' => true, 'id' => $id, 'image_removed' => $r['image_removed'],
    'note' => $r['image_removed'] ? null : 'The memory was deleted, but its image file could not be removed yet. WABYU will retry automatically.',
]);
