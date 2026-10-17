<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/api.php';

// Photos are private: they are only served to an authenticated session, never by direct URL.
wabyu_api_guard('GET', false);

$f = $_GET['f'] ?? '';
if (!is_string($f) || preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $f, $m) !== 1) { http_response_code(404); exit; }
$path = WABYU_UPLOAD_DIR . '/' . $f;
if (!is_file($path)) { http_response_code(404); exit; }

$etag = '"' . substr(sha1($f), 0, 16) . '"';
header_remove('Content-Type');
header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$m[1]]);
header('Cache-Control: private, max-age=31536000, immutable');
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
header('Content-Length: ' . filesize($path));
readfile($path);
