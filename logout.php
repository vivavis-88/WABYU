<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/api.php';

wabyu_install_error_handlers(true);
wabyu_security_headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); wabyu_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405); }
wabyu_session_start();
if (!wabyu_csrf_verify()) { wabyu_json_response(['ok' => false, 'error' => 'Your session expired. Please refresh the page.'], 419); }
// Logging out only ends the session. It never touches memories or image files.
wabyu_json_response(['ok' => true, 'csrf' => wabyu_logout()]);
