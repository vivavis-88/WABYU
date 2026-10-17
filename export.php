<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/api.php';

wabyu_api_guard('GET', false);
if (!class_exists('ZipArchive')) {
    wabyu_json_response(['ok' => false, 'error' => 'Zip support is disabled in PHP (enable extension=zip in php.ini).'], 501);
}
@set_time_limit(0);
$tmp = tempnam(sys_get_temp_dir(), 'wabyu');
$zip = new ZipArchive();
if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    wabyu_json_response(['ok' => false, 'error' => 'Could not prepare the backup.'], 500);
}
$json = wabyu_with_lock(static fn() => (string)file_get_contents(WABYU_JSON_FILE));
$zip->addFromString('data/memories.json', $json);
foreach ((json_decode($json, true)['memories'] ?? []) as $m) {
    $abs = wabyu_image_abs((string)($m['image'] ?? ''));
    if ($abs !== null && is_file($abs)) { $zip->addFile($abs, (string)$m['image']); }
}
$zip->addFromString('RESTORE.txt', "WABYU export.\nTo restore: stop Apache, copy data/memories.json into the project's data folder and the uploads/memories files into uploads/memories, then start Apache.\n");
$zip->close();

header_remove('Content-Type');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="wabyu-export-' . gmdate('Ymd-His') . '.zip"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
