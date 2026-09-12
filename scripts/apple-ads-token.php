<?php
/**
 * Apple Ads Platform API — client secret + access token.
 *
 * Apple wants the OAuth client secret to be a JWT signed with the ECDSA key
 * pair whose public half you uploaded in the Apple Ads UI. This mints that JWT
 * (ES256), exchanges it for an access token, and then proves the token works.
 *
 *   php scripts/apple-ads-token.php
 *
 * Needs in .env: APPLE_ADS_CLIENT_ID, APPLE_ADS_TEAM_ID, APPLE_ADS_KEY_ID
 * and the private key at storage/app/apple-ads-private.pem
 */

const APPLE_TOKEN_URL = 'https://appleid.apple.com/auth/oauth2/token';
const APPLE_API = 'https://api.ads.apple.com/v1';

$root = dirname(__DIR__);
$keyFile = $root.'/storage/app/apple-ads-private.pem';

function env(string $k): ?string {
    foreach (file(dirname(__DIR__).'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        if (trim($key) === $k) return trim($val, " \t\"'") ?: null;
    }
    return null;
}

function b64url(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }

/** JWT ES256 wants the raw R||S pair, openssl gives DER. */
function derToJose(string $der): string {
    $offset = 0;
    if (ord($der[0]) !== 0x30) return $der;
    $offset = 2;
    if (ord($der[1]) > 0x80) $offset = 3;
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $offset++; // skip 0x02
        $len = ord($der[$offset++]);
        $part = substr($der, $offset, $len);
        $offset += $len;
        $part = ltrim($part, "\x00");
        $out .= str_pad($part, 32, "\x00", STR_PAD_LEFT);
    }
    return $out;
}

$clientId = env('APPLE_ADS_CLIENT_ID');
$teamId = env('APPLE_ADS_TEAM_ID');
$keyId = env('APPLE_ADS_KEY_ID');

if (! $clientId || ! $teamId || ! $keyId) {
    fwrite(STDERR, "Missing APPLE_ADS_CLIENT_ID / APPLE_ADS_TEAM_ID / APPLE_ADS_KEY_ID in .env\n");
    exit(1);
}
if (! file_exists($keyFile)) {
    fwrite(STDERR, "Missing private key at storage/app/apple-ads-private.pem\n");
    exit(1);
}

$header = b64url(json_encode(['alg' => 'ES256', 'kid' => $keyId]));
$claims = b64url(json_encode([
    'sub' => $clientId,
    'iss' => $teamId,
    'aud' => 'https://appleid.apple.com',
    'iat' => time(),
    'exp' => time() + 60 * 60 * 24 * 30, // Apple allows up to 180 days
]));
$signingInput = $header.'.'.$claims;

$key = openssl_pkey_get_private(file_get_contents($keyFile));
openssl_sign($signingInput, $derSig, $key, OPENSSL_ALGO_SHA256);
$clientSecret = $signingInput.'.'.b64url(derToJose($derSig));
echo "client secret JWT minted (".strlen($clientSecret)." chars)\n";

$ch = curl_init(APPLE_TOKEN_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => 'searchadsorg',
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$token = json_decode((string) $body, true);

if ($status !== 200 || empty($token['access_token'])) {
    fwrite(STDERR, "token exchange failed (HTTP $status): ".json_encode($token)."\n");
    exit(1);
}
echo "access token acquired, expires in {$token['expires_in']}s\n\n";

// Prove it: which ad accounts can this token see?
$ch = curl_init(APPLE_API.'/acls');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$token['access_token'], 'Accept: application/json'],
    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
]);
$acls = curl_exec($ch);
$aclStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "GET /v1/acls -> HTTP $aclStatus\n";
echo substr((string) $acls, 0, 800)."\n";
