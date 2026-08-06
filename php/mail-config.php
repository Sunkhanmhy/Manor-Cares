<?php
/**
 * Manor Cares — Mail configuration
 *
 * Two send paths are supported (see php/mailer.php):
 *
 *   RESEND_API_KEY   set  -> emails are sent via the Resend HTTPS API.
 *                            Get a key at https://resend.com/api-keys and
 *                            verify a sending domain there first.
 *   RESEND_API_KEY  unset -> falls back to SMTP via PHPMailer, configured
 *                            with the MAIL_SMTP_* variables below.
 *
 *   RESEND_API_KEY          re_xxxxxxxxxxxx
 *   RESEND_FROM_EMAIL       no-reply@yourdomain.com  (must be on a domain verified in Resend)
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
    'resend_api_key' => mc_env('RESEND_API_KEY', ''),
    'resend_from'    => mc_env('RESEND_FROM_EMAIL', mc_env('MAIL_FROM_ADDRESS', 'no-reply@manor-cares.com')),
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
