<?php
/**
 * Manor Cares — Contact form handler
 *
 * Receives the "Send Us A Message" form submission from contact.html,
 * validates and sanitizes the input, then sends it via PHPMailer to the
 * support inbox. Responds with JSON so the front-end (js/script.js) can
 * show an inline success/error message without a page reload.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak errors/stack traces to clients

require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json; charset=utf-8');

/**
 * Send a JSON response and stop execution.
 */
function mc_respond(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mc_respond(false, 'Invalid request method.', 405);
}

$config = require __DIR__ . '/mail-config.php';

// Lightweight CSRF/abuse mitigation: if ALLOWED_ORIGIN is configured, reject
// requests that don't come from the expected site origin.
if ($config['allowed_origin'] !== '') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if ($origin === '' || stripos($origin, $config['allowed_origin']) !== 0) {
        mc_respond(false, 'Request origin not allowed.', 403);
    }
}

// Honeypot field: real visitors never fill this hidden input. Bots that
// auto-fill every field will trip it; pretend success so they move on.
if (!empty($_POST['website'])) {
    mc_respond(true, 'Message sent.');
}

$name    = trim((string) ($_POST['name'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$phone   = trim((string) ($_POST['phone'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? 'General Inquiry'));
$message = trim((string) ($_POST['message'] ?? ''));

$errors = [];

if ($name === '' || mb_strlen($name) > 120) {
    $errors[] = 'Please provide your full name (up to 120 characters).';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
    $errors[] = 'Please provide a valid email address.';
}

if ($phone !== '' && !preg_match('/^[0-9+\-\s().]{6,20}$/', $phone)) {
    $errors[] = 'Please provide a valid phone number.';
}

if ($message === '' || mb_strlen($message) > 5000) {
    $errors[] = 'Please provide a message (up to 5000 characters).';
}

$allowedSubjects = [
    'General Inquiry',
    'Request A Quote',
    'Existing Subscription Support',
    'Partnership / Careers',
];
if (!in_array($subject, $allowedSubjects, true)) {
    $subject = 'General Inquiry';
}

if (!empty($errors)) {
    mc_respond(false, implode(' ', $errors), 422);
}

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
    $mail->addAddress($config['to_email'], $config['to_name']);
    // Reply-To lets support reply directly to the visitor; PHPMailer validates the address.
    $mail->addReplyTo($email, $name);

    $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safePhone   = $phone !== '' ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : 'Not provided';
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

    $mail->isHTML(true);
    $mail->Subject = 'Manor Cares Contact Form: ' . $subject;
    $mail->Body = <<<HTML
        <h2 style="font-family:sans-serif;">New Contact Form Submission</h2>
        <p style="font-family:sans-serif;"><strong>Name:</strong> {$safeName}</p>
        <p style="font-family:sans-serif;"><strong>Email:</strong> {$safeEmail}</p>
        <p style="font-family:sans-serif;"><strong>Phone:</strong> {$safePhone}</p>
        <p style="font-family:sans-serif;"><strong>Subject:</strong> {$safeSubject}</p>
        <p style="font-family:sans-serif;"><strong>Message:</strong><br>{$safeMessage}</p>
        HTML;
    $mail->AltBody = "New Contact Form Submission\n\n"
        . "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\nSubject: {$subject}\n\nMessage:\n{$message}";

    $mail->send();

    mc_respond(true, "Thank you, {$name}! Your message has been sent — a Manor Cares representative will reach out within 24 hours.");
} catch (PHPMailerException $e) {
    error_log('Manor Cares contact form mail error: ' . $mail->ErrorInfo);
    mc_respond(false, 'Sorry, your message could not be sent right now. Please try again later or email us directly.', 500);
}
