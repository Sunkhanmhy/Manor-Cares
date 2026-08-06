<?php
/**
 * Manor Cares — inbound webhook receiver
 *
 * Supports two signature schemes so you can point either a generic
 * integration or a real payment provider at this single endpoint:
 *
 *   1. Stripe — sent automatically if the request has a `Stripe-Signature`
 *      header. Verified per Stripe's documented scheme using
 *      STRIPE_WEBHOOK_SECRET (from the Stripe Dashboard → Developers →
 *      Webhooks → your endpoint → "Signing secret"), with a 5-minute replay
 *      tolerance. See README.md → "Payment webhooks" for setup steps.
 *   2. Generic HMAC — any other integration (Paystack, a custom partner,
 *      etc.) that sends an `X-Manor-Signature` header containing an
 *      HMAC-SHA256 signature of the raw request body, keyed with
 *      WEBHOOK_SECRET.
 *
 * Either way, invalid signatures are rejected before the payload is ever
 * parsed or logged as trusted.
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

$rawBody = file_get_contents('php://input') ?: '';
$stripeSignatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($stripeSignatureHeader !== '') {
    $signatureValid = mc_webhook_verify_stripe($rawBody, $stripeSignatureHeader);
} else {
    $secret = getenv('WEBHOOK_SECRET') ?: '';
    if ($secret === '') {
        error_log('Manor Cares webhook rejected: WEBHOOK_SECRET is not configured.');
        mc_webhook_respond(false, 'Webhooks are not configured.', 503);
    }
    $signature = $_SERVER['HTTP_X_MANOR_SIGNATURE'] ?? '';
    $expected = hash_hmac('sha256', $rawBody, $secret);
    $signatureValid = $signature !== '' && hash_equals($expected, $signature);
}

/**
 * Verifies Stripe's `t=...,v1=...` signature scheme:
 * https://docs.stripe.com/webhooks#verify-manually
 */
function mc_webhook_verify_stripe(string $rawBody, string $signatureHeader, int $toleranceSeconds = 300): bool
{
    $secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
    if ($secret === '') {
        error_log('Manor Cares Stripe webhook rejected: STRIPE_WEBHOOK_SECRET is not configured.');
        return false;
    }

    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $signatureHeader) as $part) {
        [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1') {
            $signatures[] = $value;
        }
    }

    if ($timestamp === null || $signatures === [] || abs(time() - (int) $timestamp) > $toleranceSeconds) {
        return false;
    }

    $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);
    foreach ($signatures as $candidate) {
        if (hash_equals($expected, $candidate)) {
            return true;
        }
    }
    return false;
}

$payload = json_decode($rawBody, true);
$eventType = is_array($payload) ? (string) ($payload['type'] ?? $payload['event'] ?? 'unknown') : 'unknown';

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
// if ($eventType === 'checkout.session.completed') { ... } // Stripe
// if ($eventType === 'charge.success') { ... }              // Paystack

mc_webhook_respond(true, 'Received.');
