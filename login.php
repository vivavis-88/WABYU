<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/api.php';

wabyu_install_error_handlers(true);
wabyu_security_headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); wabyu_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405); }
$secrets = wabyu_secrets();
if (!$secrets) { wabyu_json_response(['ok' => false, 'error' => 'WABYU has not been set up yet. Run setup first.'], 503); }
wabyu_session_start();
if (!wabyu_csrf_verify()) { wabyu_json_response(['ok' => false, 'error' => 'Your session expired. Please refresh the page.'], 419); }
wabyu_init_storage();

$wait = wabyu_rate_blocked('login');
if ($wait > 0) { wabyu_json_response(['ok' => false, 'error' => wabyu_wait_text($wait)], 429); }

$body = wabyu_json_body();
$password = is_string($body['password'] ?? null) ? substr($body['password'], 0, 256) : '';

if ($password !== '' && password_verify($password, $secrets['login_hash'])) {
    wabyu_rate_clear('login');
    $token = wabyu_login_success();
    wabyu_json_response(['ok' => true, 'csrf' => $token]);
}
wabyu_rate_fail('login');
usleep(300000);
wabyu_json_response(['ok' => false, 'error' => 'That password is not correct.'], 401);
