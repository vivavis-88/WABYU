<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

function wabyu_log(string $message): void
{
    @file_put_contents(WABYU_LOG_FILE, '[' . gmdate('c') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
}

function wabyu_install_error_handlers(bool $json): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
        if (!(error_reporting() & $no)) { return false; }
        wabyu_log("PHP[$no] $str @ " . basename($file) . ":$line");
        return true;
    });
    set_exception_handler(static function (Throwable $e) use ($json): void {
        wabyu_log(get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        if (!headers_sent()) { http_response_code(500); }
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Something went wrong on the server. Please try again.']);
        } else {
            echo 'Something went wrong on the server.';
        }
    });
}

/** @return array{login_hash:string,delete_hash:string}|null */
function wabyu_secrets(): ?array
{
    static $cache = false;
    if ($cache === false) {
        $file = WABYU_ROOT . '/config/secrets.php';
        $data = is_file($file) ? require $file : null;
        $cache = (is_array($data) && !empty($data['login_hash']) && !empty($data['delete_hash'])) ? $data : null;
    }
    return $cache;
}

function wabyu_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** URL path of the project folder, e.g. "/wabyu" (empty when installed at the web root). */
function wabyu_base_url(): string
{
    $doc  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $root = realpath(WABYU_ROOT) ?: WABYU_ROOT;
    $d = str_replace('\\', '/', $doc);
    $r = str_replace('\\', '/', $root);
    if ($d !== '' && strncasecmp($r, $d, strlen($d)) === 0) {
        return rtrim(substr($r, strlen($d)), '/');
    }
    return rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
}

function wabyu_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function wabyu_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
        . "style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
        . "img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

function wabyu_json_response(array $data, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function wabyu_json_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 65536);
    $data = json_decode($raw === false ? '' : $raw, true);
    return is_array($data) ? $data : [];
}

function wabyu_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function wabyu_asset(string $path): string
{
    $file = WABYU_ROOT . '/' . $path;
    return wabyu_h($path . (is_file($file) ? '?v=' . filemtime($file) : ''));
}
