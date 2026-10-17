<?php
declare(strict_types=1);

function wabyu_csrf_token(): string
{
    if (empty($_SESSION['wabyu_csrf'])) {
        $_SESSION['wabyu_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['wabyu_csrf'];
}

function wabyu_csrf_rotate(): string
{
    $_SESSION['wabyu_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['wabyu_csrf'];
}

function wabyu_csrf_verify(): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && $sent !== '' && !empty($_SESSION['wabyu_csrf']) && hash_equals($_SESSION['wabyu_csrf'], $sent);
}
