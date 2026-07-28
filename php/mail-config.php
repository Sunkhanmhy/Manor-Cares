<?php
/**
 * Manor Cares — Mail configuration
 *
 * All values are read from environment variables so real credentials are
 * never hard-coded or committed to version control. Set these variables
 * on your server (e.g. via Apache/Nginx vhost, a `.env` loaded by your
 * process manager, or your hosting control panel):
 *
 *   MAIL_USE_SMTP        true|false   (default: true)
 *   MAIL_SMTP_HOST        smtp.yourprovider.com
 *   MAIL_SMTP_USER        smtp-username
 *   MAIL_SMTP_PASS        smtp-password
 *   MAIL_SMTP_PORT        587
 *   MAIL_SMTP_ENCRYPTION  tls|ssl
 *   MAIL_FROM_ADDRESS     no-reply@manor-cares.com
 *   MAIL_FROM_NAME        Manor Cares Website
 *   MAIL_TO_ADDRESS       support@manor-cares.com
 *   MAIL_TO_NAME          Manor Cares Support
 *   ALLOWED_ORIGIN        https://www.manor-cares.com
 *
 * For local testing you can export these in your shell before starting
 * the PHP dev server, e.g.:
 *   MAIL_SMTP_HOST=smtp.mailtrap.io MAIL_SMTP_USER=xxx MAIL_SMTP_PASS=yyy php -S localhost:8000
 */

if (!function_exists('mc_env')) {
    function mc_env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value !== false && $value !== '' ? $value : $default;
    }
}

return [
    'use_smtp'   => filter_var(mc_env('MAIL_USE_SMTP', 'true'), FILTER_VALIDATE_BOOLEAN),
    'smtp_host'  => mc_env('MAIL_SMTP_HOST', 'smtp.example.com'),
    'smtp_user'  => mc_env('MAIL_SMTP_USER', ''),
    'smtp_pass'  => mc_env('MAIL_SMTP_PASS', ''),
    'smtp_port'  => (int) mc_env('MAIL_SMTP_PORT', '587'),
    'smtp_secure'=> mc_env('MAIL_SMTP_ENCRYPTION', 'tls'),
    'from_email' => mc_env('MAIL_FROM_ADDRESS', 'no-reply@manor-cares.com'),
    'from_name'  => mc_env('MAIL_FROM_NAME', 'Manor Cares Website'),
    'to_email'   => mc_env('MAIL_TO_ADDRESS', 'support@manor-cares.com'),
    'to_name'    => mc_env('MAIL_TO_NAME', 'Manor Cares Support'),
    'allowed_origin' => mc_env('ALLOWED_ORIGIN', ''),
];
