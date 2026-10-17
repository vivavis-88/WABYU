<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';

/** Common gate for every protected endpoint: method, secrets, session, CSRF. */
function wabyu_api_guard(string $method, bool $needsCsrf = true): void
{
    wabyu_install_error_handlers(true);
    wabyu_security_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        wabyu_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
    if (!wabyu_secrets()) { wabyu_json_response(['ok' => false, 'error' => 'WABYU has not been set up yet.'], 503); }
    wabyu_session_start();
    if (!wabyu_is_authenticated()) { wabyu_json_response(['ok' => false, 'auth' => false, 'error' => 'Please log in again.'], 401); }
    if ($needsCsrf && !wabyu_csrf_verify()) { wabyu_json_response(['ok' => false, 'error' => 'Your session expired. Please refresh the page.'], 419); }
    session_write_close();   // do not block parallel requests
    wabyu_init_storage();
}

/** Detects a POST body larger than php.ini's post_max_size (PHP then empties $_POST and $_FILES). */
function wabyu_check_post_size(): void
{
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        wabyu_json_response(['ok' => false, 'error' => 'That photograph is larger than this server allows. Try a smaller file or raise post_max_size in php.ini.'], 413);
    }
}
