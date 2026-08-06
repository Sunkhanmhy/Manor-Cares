<?php
/**
 * Manor Cares — minimal HTTP client for outbound API calls (Resend, Supabase Auth)
 *
 * Uses PHP's built-in `http`/`https` stream wrapper (allow_url_fopen) instead
 * of the `curl` extension, since curl isn't guaranteed to be installed on
 * every host (it wasn't on this dev machine) — keeps the app dependency-free
 * for outbound HTTPS calls.
 */

declare(strict_types=1);

/**
 * @param array<string,string> $headers
 * @return array{status:int, body:string}
 */
function mc_http_request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 15): array
{
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = "{$name}: {$value}";
    }

    $context = stream_context_create([
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headerLines),
            'content'       => $body ?? '',
            'timeout'       => $timeoutSeconds,
            'ignore_errors' => true, // so 4xx/5xx responses are still readable, not just false
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    if ($responseBody === false) {
        throw new RuntimeException("HTTP request to {$url} failed (no response).");
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
        }
    }

    return ['status' => $status, 'body' => $responseBody];
}

/**
 * @param array<string,mixed> $payload
 * @param array<string,string> $headers
 * @return array{status:int, body:string}
 */
function mc_http_post_json(string $url, array $payload, array $headers = []): array
{
    $headers['Content-Type'] = 'application/json';
    return mc_http_request('POST', $url, $headers, json_encode($payload, JSON_UNESCAPED_SLASHES));
}
