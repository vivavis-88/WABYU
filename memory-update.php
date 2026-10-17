<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/api.php';

wabyu_api_guard('POST');
wabyu_check_post_size();

$id = $_POST['id'] ?? null;
if (!wabyu_valid_id($id)) { wabyu_json_response(['ok' => false, 'error' => 'Unknown memory.'], 404); }
$fields = $_POST;
unset($fields['id']);
[$clean, $errors] = wabyu_validate_memory_input($fields, true);
if ($errors) { wabyu_json_response(['ok' => false, 'error' => reset($errors), 'fields' => $errors], 422); }

$file = (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) ? $_FILES['image'] : null;
try {
    $memory = wabyu_update_memory($id, $clean, $file);
    if ($memory === null) { wabyu_json_response(['ok' => false, 'error' => 'That memory no longer exists.'], 404); }
    wabyu_json_response(['ok' => true, 'memory' => $memory]);
} catch (WabyuValidationException $e) {
    wabyu_json_response(['ok' => false, 'error' => $e->getMessage(), 'fields' => ['image' => $e->getMessage()]], 422);
} catch (WabyuStorageException $e) {
    wabyu_log('Update failed: ' . $e->getMessage());
    wabyu_json_response(['ok' => false, 'error' => 'The changes could not be saved. The previous version was kept.'], 500);
}
