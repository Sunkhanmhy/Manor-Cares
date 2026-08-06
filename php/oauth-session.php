<?php
/**
 * Manor Cares — short-lived session for the OAuth (PKCE) handshake
 *
 * Stores the PKCE code_verifier + a CSRF state value between the redirect
 * to the provider (via Supabase Auth) and the callback. Must use
 * SameSite=Lax (not Strict) because the callback request arrives as a
 * top-level GET navigation initiated from Supabase's/the provider's
 * domain — a Strict cookie would not be sent back to us in that case.
 */

declare(strict_types=1);

function mc_oauth_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('manor_oauth');
        session_start();
    }
}
