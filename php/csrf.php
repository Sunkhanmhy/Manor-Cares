<?php
/**
 * Manor Cares — CSRF protection
 *
 * Double-submit-cookie style CSRF token, independent from the httpOnly JWT
 * auth cookie so a stolen JWT alone can't be used to forge state-changing
 * requests from a third-party page. The token is stored in a short-lived
 * PHP session and must be echoed back by the client in the
 * `X-CSRF-Token` header for every POST/PUT/PATCH/DELETE call.
 */

declare(strict_types=1);

function mc_csrf_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_name('manor_csrf');
        session_start();
    }
}

function mc_csrf_token(): string
{
    mc_csrf_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function mc_csrf_verify(): bool
{
    mc_csrf_start_session();
    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
}

function mc_csrf_require(): void
{
    if (!mc_csrf_verify()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Invalid or missing security token. Please refresh and try again.']);
        exit;
    }
}
