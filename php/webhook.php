<?php
/**
 * Manor Cares — inbound webhook receiver
 *
 * Generic, signature-verified webhook endpoint for third-party integrations
 * (payment provider, scheduling partner, etc). Every request must include
 * an `X-Manor-Signature` header containing an HMAC-SHA256 signature of the
 * raw request body, keyed with WEBHOOK_SECRET. Invalid signatures are
 * rejected before the payload is ever parsed or logged as trusted.
 *
 * Set WEBHOOK_SECRET as an environment variable (never commit it).
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/security-headers.php';
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function mc_webhook_respond(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mc_webhook_respond(false, 'Invalid request method.', 405);
}

$secret = getenv('WEBHOOK_SECRET') ?: '';
if ($secret === '') {
    error_log('Manor Cares webhook rejected: WEBHOOK_SECRET is not configured.');
    mc_webhook_respond(false, 'Webhooks are not configured.', 503);
}

$rawBody = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_MANOR_SIGNATURE'] ?? '';

$expected = hash_hmac('sha256', $rawBody, $secret);
$signatureValid = $signature !== '' && hash_equals($expected, $signature);

$payload = json_decode($rawBody, true);
$eventType = is_array($payload) ? (string) ($payload['event'] ?? 'unknown') : 'unknown';

try {
    $pdo = mc_db();
    $stmt = $pdo->prepare('INSERT INTO webhooks_log (event_type, payload, signature_valid) VALUES (:type, :payload, :valid)');
    $stmt->execute([
        'type'    => $eventType,
        'payload' => is_array($payload) ? json_encode($payload) : json_encode(['raw' => substr($rawBody, 0, 2000)]),
        'valid'   => $signatureValid ? 't' : 'f',
    ]);
} catch (Throwable $e) {
    error_log('Manor Cares webhook log error: ' . $e->getMessage());
}

if (!$signatureValid) {
    mc_webhook_respond(false, 'Invalid signature.', 401);
}

// Handle known, trusted event types here as the integration grows, e.g.:
// if ($eventType === 'booking.status_updated') { ... }

mc_webhook_respond(true, 'Received.');
