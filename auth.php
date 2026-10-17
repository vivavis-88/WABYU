<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/storage.php';

function wabyu_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    $base = wabyu_base_url();
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('WABYUSESS');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => ($base === '' ? '/' : $base . '/'),
        'secure' => wabyu_is_https(), 'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

function wabyu_is_authenticated(): bool
{
    if (empty($_SESSION['wabyu_auth'])) { return false; }
    if ((int)($_SESSION['wabyu_seen'] ?? 0) < time() - WABYU_SESSION_IDLE) { return false; }
    $_SESSION['wabyu_seen'] = time();
    return true;
}

function wabyu_login_success(): string
{
    session_regenerate_id(true);
    $_SESSION['wabyu_auth'] = true;
    $_SESSION['wabyu_seen'] = time();
    return wabyu_csrf_rotate();
}

/** Ends the authenticated session and returns a fresh CSRF token for the login screen. */
function wabyu_logout(): string
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
    }
    session_destroy();
    wabyu_session_start();
    session_regenerate_id(true);
    return wabyu_csrf_rotate();
}

/* ---- Rate limiting (file based, per IP + bucket) ---- */
function wabyu_rate_load(): array
{
    $f = WABYU_DATA_DIR . '/ratelimit.json';
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    return is_array($d) ? $d : [];
}

function wabyu_rate_save(array $d): void
{
    $now = time();
    foreach ($d as $k => $times) {
        $d[$k] = array_values(array_filter((array)$times, static fn($t) => $t > $now - WABYU_RATE_WINDOW));
        if (!$d[$k]) { unset($d[$k]); }
    }
    wabyu_atomic_write(WABYU_DATA_DIR . '/ratelimit.json', json_encode($d));
}

/** Seconds the caller must wait, or 0 when allowed. */
function wabyu_rate_blocked(string $bucket): int
{
    return (int)wabyu_with_lock(static function () use ($bucket): int {
        $times = array_filter((array)(wabyu_rate_load()[$bucket . '|' . wabyu_client_ip()] ?? []), static fn($t) => $t > time() - WABYU_RATE_WINDOW);
        if (count($times) < WABYU_RATE_MAX) { return 0; }
        return max(1, (int)(min($times) + WABYU_RATE_WINDOW - time()));
    });
}

function wabyu_rate_fail(string $bucket): void
{
    wabyu_with_lock(static function () use ($bucket): void {
        $d = wabyu_rate_load();
        $d[$bucket . '|' . wabyu_client_ip()][] = time();
        wabyu_rate_save($d);
    });
}

function wabyu_rate_clear(string $bucket): void
{
    wabyu_with_lock(static function () use ($bucket): void {
        $d = wabyu_rate_load();
        unset($d[$bucket . '|' . wabyu_client_ip()]);
        wabyu_rate_save($d);
    });
}

function wabyu_wait_text(int $s): string
{
    $m = (int)ceil($s / 60);
    return 'Too many attempts. Please wait ' . $m . ' minute' . ($m === 1 ? '' : 's') . ' and try again.';
}
