<?php
/**
 * Manor Cares — Supabase project configuration
 *
 * SUPABASE_URL and SUPABASE_ANON_KEY come from Project Settings → API in
 * your Supabase dashboard. They're used here purely to talk to Supabase
 * Auth's OAuth endpoints (Google/GitHub sign-in) — the anon key is safe to
 * expose to the browser by design, but we keep it server-side here since
 * the OAuth flow is handled entirely by PHP.
 *
 * SUPABASE_SERVICE_ROLE_KEY is optional and only needed if you extend the
 * app to call privileged Supabase REST/Storage endpoints server-side.
 * Never send the service role key to the browser.
 */

declare(strict_types=1);

if (!function_exists('mc_env')) {
    function mc_env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value !== false && $value !== '' ? $value : $default;
    }
}

return [
    'url'              => rtrim(mc_env('SUPABASE_URL', ''), '/'),
    'anon_key'         => mc_env('SUPABASE_ANON_KEY', ''),
    'service_role_key' => mc_env('SUPABASE_SERVICE_ROLE_KEY', ''),
];
