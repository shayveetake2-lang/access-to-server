<?php
// api/auth/jwt_utils.php — Centralized HMAC-SHA256 JWT Utility

if (!function_exists('getEnvValue')) {
    function getEnvValue($key, $default = null) {
        $val = getenv($key);
        if ($val !== false && $val !== null && $val !== '') {
            return $val;
        }
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    if ($k === $key) {
                        return $v;
                    }
                }
            }
        }
        return $default;
    }
}

function getJwtSecret(): string {
    $secret = getEnvValue('JWT_SECRET');
    if (!empty($secret)) {
        return $secret;
    }
    return '7db70f9af8d51965a40ba687133dde19bbcc3ac553000207f49f96e9071b953e';
}

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

function createSignedJwt(array $payload, int $ttlSeconds = 604800): string {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $now = time();
    $payload['iat'] = $payload['iat'] ?? $now;
    $payload['exp'] = $payload['exp'] ?? ($now + $ttlSeconds);

    $base64Header  = base64UrlEncode($header);
    $base64Payload = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", getJwtSecret(), true);
    $base64Signature = base64UrlEncode($signature);

    return "{$base64Header}.{$base64Payload}.{$base64Signature}";
}

function verifyAndDecodeJwt(string $jwt): ?array {
    $parts = explode('.', trim($jwt));
    if (count($parts) !== 3) {
        return null;
    }

    list($base64Header, $base64Payload, $base64Signature) = $parts;

    $expectedSignature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", getJwtSecret(), true);
    $providedSignature = base64UrlDecode($base64Signature);

    if (!hash_equals($expectedSignature, $providedSignature)) {
        return null;
    }

    $payloadJson = base64UrlDecode($base64Payload);
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        return null;
    }

    // Verify token expiration
    if (isset($payload['exp']) && (int)$payload['exp'] < time()) {
        return null;
    }

    return $payload;
}
