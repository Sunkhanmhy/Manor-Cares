<?php
/**
 * Manor Cares — outbound mail abstraction
 *
 * Two backends are supported, chosen automatically at runtime:
 *
 *   1. Resend (https://resend.com) — used whenever RESEND_API_KEY is set.
 *      A simple HTTPS POST to the Resend API, no extra Composer dependency.
 *   2. PHPMailer + SMTP (already vendored) — used as the fallback when no
 *      Resend key is configured, so the app keeps working with any SMTP
 *      provider (Mailtrap, Gmail, SES SMTP, Postmark, etc.).
 *
 * Callers don't need to know which backend is active — just call
 * mc_send_mail() and handle the boolean result / thrown exception.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/http-client.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * @param array{email:string,name?:string} $to
 * @param array{email:string,name?:string}|null $replyTo
 * @throws RuntimeException on failure (message is safe to show generically, log details separately)
 */
function mc_send_mail(array $to, string $subject, string $html, string $text, ?array $replyTo = null): bool
{
    $config = require __DIR__ . '/mail-config.php';

    if ($config['resend_api_key'] !== '') {
        return mc_send_mail_via_resend($config, $to, $subject, $html, $text, $replyTo);
    }

    return mc_send_mail_via_smtp($config, $to, $subject, $html, $text, $replyTo);
}

function mc_send_mail_via_resend(array $config, array $to, string $subject, string $html, string $text, ?array $replyTo): bool
{
    $payload = [
        'from'    => $config['from_name'] . ' <' . $config['resend_from'] . '>',
        'to'      => [$to['name'] ?? '' ? "{$to['name']} <{$to['email']}>" : $to['email']],
        'subject' => $subject,
        'html'    => $html,
        'text'    => $text,
    ];
    if ($replyTo !== null && $replyTo['email'] !== '') {
        $payload['reply_to'] = $replyTo['email'];
    }

    $response = mc_http_post_json('https://api.resend.com/emails', $payload, [
        'Authorization' => 'Bearer ' . $config['resend_api_key'],
    ]);

    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException('Resend API error (HTTP ' . $response['status'] . '): ' . $response['body']);
    }

    return true;
}

function mc_send_mail_via_smtp(array $config, array $to, string $subject, string $html, string $text, ?array $replyTo): bool
{
    $mail = new PHPMailer(true);

    try {
        if ($config['use_smtp']) {
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['smtp_user'];
            $mail->Password   = $config['smtp_pass'];
            $mail->SMTPSecure = $config['smtp_secure'] === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $config['smtp_port'];
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to['email'], $to['name'] ?? '');
        if ($replyTo !== null && $replyTo['email'] !== '') {
            $mail->addReplyTo($replyTo['email'], $replyTo['name'] ?? '');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody  = $text;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        throw new RuntimeException('SMTP send failed: ' . $mail->ErrorInfo);
    }
}
