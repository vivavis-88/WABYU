<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/api.php';

wabyu_api_guard('POST');
wabyu_check_post_size();

[$clean, $errors] = wabyu_validate_memory_input($_POST);
if ($errors) { wabyu_json_response(['ok' => false, 'error' => reset($errors), 'fields' => $errors], 422); }
if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    wabyu_json_response(['ok' => false, 'error' => 'Please choose a photograph.', 'fields' => ['image' => 'Please choose a photograph.']], 422);
}
try {
    $memory = wabyu_create_memory($clean, $_FILES['image']);
    wabyu_json_response(['ok' => true, 'memory' => $memory], 201);
} catch (WabyuValidationException $e) {
    wabyu_json_response(['ok' => false, 'error' => $e->getMessage(), 'fields' => ['image' => $e->getMessage()]], 422);
} catch (WabyuStorageException $e) {
    wabyu_log('Create failed: ' . $e->getMessage());
    wabyu_json_response(['ok' => false, 'error' => 'The memory could not be saved, and nothing was changed. Please try again.'], 500);
}
