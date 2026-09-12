<?php
/**
 * One-time Google Ads OAuth flow, run locally so no Playground or redirect-URI
 * registration is needed. Desktop clients may use loopback redirects.
 *
 *   php scripts/google-ads-oauth.php url      # prints the consent URL
 *   php -S 127.0.0.1:8080 scripts/google-ads-oauth.php   # catches the callback
 *
 * On success it writes GOOGLE_ADS_REFRESH_TOKEN into .env and lists the Ads
 * accounts the authorised user can reach.
 */

const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
const TOKEN_URL = 'https://oauth2.googleapis.com/token';
const SCOPE = 'https://www.googleapis.com/auth/adwords';
const REDIRECT = 'http://localhost:8080/callback';
const ADS_API = 'https://googleads.googleapis.com/v25';

$root = dirname(__DIR__);
$pendingFile = $root.'/storage/app/google-oauth-pending.json';

function env_values(string $path): array
{
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_contains($line, '=') && ! str_starts_with(trim($line), '#')) {
            [$k, $v] = explode('=', $line, 2);
            $out[trim($k)] = trim($v, " \t\"'");
        }
    }

    return $out;
}

function set_env(string $path, string $key, string $value): void
{
    $text = file_get_contents($path);
    $line = $key.'='.$value;
    if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $text)) {
        $text = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $text);
    } else {
        $text = rtrim($text, "\n")."\n".$line."\n";
    }
    file_put_contents($path, $text);
}

function post_form(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return [$status, json_decode((string) $body, true) ?? ['raw' => $body]];
}

// ---------- CLI: print the consent URL ----------
if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'url') {
    $env = env_values($root.'/.env');
    if (empty($env['GOOGLE_ADS_CLIENT_ID']) || empty($env['GOOGLE_ADS_CLIENT_SECRET'])) {
        fwrite(STDERR, "GOOGLE_ADS_CLIENT_ID / GOOGLE_ADS_CLIENT_SECRET are missing from .env\n");
        exit(1);
    }
    $state = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    file_put_contents($pendingFile, json_encode(['state' => $state, 'at' => time()]));

    echo AUTH_URL.'?'.http_build_query([
        'client_id' => $env['GOOGLE_ADS_CLIENT_ID'],
        'redirect_uri' => REDIRECT,
        'response_type' => 'code',
        'scope' => SCOPE,
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state,
    ])."\n";
    exit(0);
}

// ---------- Server mode: handle the callback ----------
$env = env_values($root.'/.env');
$query = $_GET;

if (isset($query['error'])) {
    echo "<h2>Google refused the request</h2><p>".htmlspecialchars($query['error']).": ".htmlspecialchars($query['error_description'] ?? '')."</p>";
    echo "<p>Most likely your Google account is not listed under Test users on the consent screen.</p>";
    return true;
}

if (! isset($query['code'])) {
    echo '<h2>Waiting for the callback…</h2><p>This page is the redirect target. Start the flow with the URL from <code>php scripts/google-ads-oauth.php url</code>.</p>';
    return true;
}

$pending = file_exists($pendingFile) ? json_decode(file_get_contents($pendingFile), true) : [];
if ($pending && ($query['state'] ?? '') !== $pending['state']) {
    echo '<h2>State mismatch</h2><p>Restart the flow from the beginning.</p>';
    return true;
}

[$status, $tok] = post_form(TOKEN_URL, [
    'code' => $query['code'],
    'client_id' => $env['GOOGLE_ADS_CLIENT_ID'],
    'client_secret' => $env['GOOGLE_ADS_CLIENT_SECRET'],
    'redirect_uri' => REDIRECT,
    'grant_type' => 'authorization_code',
]);

if ($status !== 200 || empty($tok['refresh_token'])) {
    echo '<h2>Token exchange failed</h2><pre>'.htmlspecialchars(json_encode($tok, JSON_PRETTY_PRINT)).'</pre>';
    file_put_contents('/tmp/google-ads-oauth.log', "FAILED $status ".json_encode($tok)."\n", FILE_APPEND);
    return true;
}

set_env($root.'/.env', 'GOOGLE_ADS_REFRESH_TOKEN', $tok['refresh_token']);
@unlink($pendingFile);
$note = 'refresh token written to .env ('.strlen($tok['refresh_token']).' chars, '.substr($tok['refresh_token'], 0, 3).'…)';
echo '<h2>✅ Authorised</h2><p>'.htmlspecialchars($note).'</p>';

// Prove the token works and find the Ads accounts.
$ch = curl_init(ADS_API.'/customers:listAccessibleCustomers');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer '.$tok['access_token'],
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$decoded = json_decode((string) $body, true);

echo '<h3>Ads accounts reachable with this token (HTTP '.$code.')</h3>';
if ($code === 200) {
    echo '<pre>'.htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)).'</pre>';
    echo '<p>Copy the numeric id into GOOGLE_ADS_CUSTOMER_ID.</p>';
} else {
    echo '<pre>'.htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)).'</pre>';
    echo '<p>CUSTOMER_NOT_ENABLED means the Ads account itself is not active — that is an account-state problem, not a code one. '
        .'CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION would instead mean the Cloud project needs its access level raised (apply for Explorer on the Google Ads API Overview page).</p>';
}
file_put_contents('/tmp/google-ads-oauth.log', "OK token written; listAccessibleCustomers HTTP $code ".json_encode($decoded)."\n");

return true;
