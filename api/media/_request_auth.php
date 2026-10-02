<?php
// api/media/_request_auth.php — Shared dual-auth for the media request pipeline.
// Accepts either a ServerFlow sys_users Bearer token, or Ampache Subsonic
// credentials (u/t/s) verified via Ampache's own REST API loopback, so both
// ServerFlow's media portal and the Aether SPA can hit the same endpoints.

function getBearerToken(): string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        return trim($m[1]);
    }
    return '';
}

// Verifies Ampache Subsonic credentials via server-side loopback and returns
// the user's profile (including adminRole), or null if invalid.
function verifyAmpacheUser(string $username, string $token, string $salt): ?array {
    if ($username === '' || $token === '' || $salt === '') return null;

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    $base   = "{$scheme}://{$host}/access-to-server/ampache/public/rest/index.php";

    $qs = http_build_query([
        'action'   => 'getUser',
        'username' => $username,
        'u'        => $username,
        't'        => $token,
        's'        => $salt,
        'v'        => '1.16.1',
        'c'        => 'AetherMediaRequests',
        'f'        => 'json',
    ]);

    $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
    $raw = @file_get_contents("{$base}?{$qs}", false, $ctx);
    if (!$raw) {
        $fallbackBase = "{$scheme}://{$host}/ampache/public/rest/index.php";
        $raw = @file_get_contents("{$fallbackBase}?{$qs}", false, $ctx);
    }
    if (!$raw) return null;

    $data = json_decode($raw, true);
    $resp = $data['subsonic-response'] ?? null;
    if (!$resp || ($resp['status'] ?? '') !== 'ok') return null;

    $u = $resp['user'] ?? null;
    if (!$u || strtolower($u['username'] ?? '') !== strtolower($username)) return null;

    return $u;
}

// Resolves the requesting identity for any authenticated user (not necessarily admin).
// Returns ['id' => sys_users.id|0, 'username' => string] or null if unauthenticated.
function resolveMediaRequester(PDO $pdo, array $body = []): ?array {
    // 1. ServerFlow sys_users Bearer token or body token (JWT or DB hash)
    $token = getBearerToken();
    if ($token === '' && !empty($body['token'])) {
        $token = trim((string)$body['token']);
    }
    if ($token === '' && !empty($_POST['token'])) {
        $token = trim((string)$_POST['token']);
    }

    if ($token !== '') {
        require_once __DIR__ . '/../auth/jwt_utils.php';
        if (substr_count($token, '.') === 2) {
            $payload = verifyAndDecodeJwt($token);
            if ($payload !== null) {
                return [
                    'id' => (int)($payload['user_id'] ?? $payload['sub'] ?? 0),
                    'username' => (string)($payload['username'] ?? 'user')
                ];
            }
        }

        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id, username FROM sys_users WHERE (token_hash = :h1 OR auth_token = :h2 OR auth_token = :t) LIMIT 1");
        $stmt->execute([':h1' => $tokenHash, ':h2' => $tokenHash, ':t' => $token]);
        if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['id' => (int)$u['id'], 'username' => $u['username']];
        }
    }

    // 2. Aether / Ampache Subsonic credentials (u/t/s), any valid user
    $u = trim($body['u'] ?? $_POST['u'] ?? $_GET['u'] ?? '');
    $t = trim($body['t'] ?? $_POST['t'] ?? $_GET['t'] ?? '');
    $s = trim($body['s'] ?? $_POST['s'] ?? $_GET['s'] ?? '');
    $ampacheUser = verifyAmpacheUser($u, $t, $s);
    if ($ampacheUser) {
        return ['id' => 0, 'username' => $ampacheUser['username']];
    }

    return null;
}

// Resolves the requesting identity ONLY if they are an admin (ServerFlow role=admin
// or Ampache adminRole=true). Returns the same shape as resolveMediaRequester().
function resolveMediaAdmin(PDO $pdo, array $body = []): ?array {
    $token = getBearerToken();
    if ($token === '' && !empty($body['token'])) {
        $token = trim((string)$body['token']);
    }
    if ($token === '' && !empty($_POST['token'])) {
        $token = trim((string)$_POST['token']);
    }

    if ($token !== '') {
        require_once __DIR__ . '/../auth/jwt_utils.php';
        if (substr_count($token, '.') === 2) {
            $payload = verifyAndDecodeJwt($token);
            if ($payload !== null && !empty($payload['role']) && $payload['role'] === 'admin') {
                return [
                    'id' => (int)($payload['user_id'] ?? $payload['sub'] ?? 0),
                    'username' => (string)($payload['username'] ?? 'admin')
                ];
            }
        }

        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id, username, role FROM sys_users WHERE (token_hash = :h1 OR auth_token = :h2 OR auth_token = :t) LIMIT 1");
        $stmt->execute([':h1' => $tokenHash, ':h2' => $tokenHash, ':t' => $token]);
        if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($u['role'] === 'admin') {
                return ['id' => (int)$u['id'], 'username' => $u['username']];
            }
            return null;
        }
    }

    $u = trim($body['u'] ?? $_POST['u'] ?? $_GET['u'] ?? '');
    $t = trim($body['t'] ?? $_POST['t'] ?? $_GET['t'] ?? '');
    $s = trim($body['s'] ?? $_POST['s'] ?? $_GET['s'] ?? '');
    $ampacheUser = verifyAmpacheUser($u, $t, $s);
    if ($ampacheUser && !empty($ampacheUser['adminRole'])) {
        return ['id' => 0, 'username' => $ampacheUser['username']];
    }

    return null;
}
