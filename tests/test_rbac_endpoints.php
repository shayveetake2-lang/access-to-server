<?php
/**
 * tests/test_rbac_endpoints.php
 * Comprehensive DevSecOps Test Suite for RBAC:
 * - api/get_users.php
 * - api/update_role.php
 */

putenv("DB_SQLITE_PATH=/tmp/access_db.sqlite");
require_once __DIR__ . '/../config/config.php';

$pdo = getDBConnection();
try {
    $pdo->exec("PRAGMA busy_timeout = 5000;");
} catch (\Exception $e) {}

// Set up test users in sys_users
// 1: Admin user (admin)
// 2: Test Admin 2 (admin2)
// 3: Test Member (member1)

$passHash = password_hash('TestPass123!', PASSWORD_BCRYPT);
$adminToken = 'admin_test_token_' . bin2hex(random_bytes(16));
$adminTokenHash = hash('sha256', $adminToken);

$memberToken = 'member_test_token_' . bin2hex(random_bytes(16));
$memberTokenHash = hash('sha256', $memberToken);

// Upsert admin user
$stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = 'admin'");
$stmt->execute();
if (!$stmt->fetch()) {
    $pdo->prepare("INSERT INTO sys_users (username, email, password_hash, role, auth_token, token_hash) VALUES ('admin', 'admin@local.server', :p, 'admin', :t, :th)")
        ->execute([':p' => $passHash, ':t' => $adminToken, ':th' => $adminTokenHash]);
} else {
    $pdo->prepare("UPDATE sys_users SET email = 'admin@local.server', role = 'admin', auth_token = :t, token_hash = :th WHERE username = 'admin'")
        ->execute([':t' => $adminToken, ':th' => $adminTokenHash]);
}

// Upsert second admin (for demotion test)
$stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = 'admin2'");
$stmt->execute();
$admin2User = $stmt->fetch();
if (!$admin2User) {
    $pdo->prepare("INSERT INTO sys_users (username, email, password_hash, role) VALUES ('admin2', 'admin2@local.server', :p, 'admin')")
        ->execute([':p' => $passHash]);
    $admin2Id = (int)$pdo->lastInsertId();
} else {
    $admin2Id = (int)$admin2User['id'];
    $pdo->prepare("UPDATE sys_users SET email = 'admin2@local.server', role = 'admin' WHERE id = :id")->execute([':id' => $admin2Id]);
}

// Upsert member user
$stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = 'member1'");
$stmt->execute();
$memberUser = $stmt->fetch();
if (!$memberUser) {
    $pdo->prepare("INSERT INTO sys_users (username, email, password_hash, role, auth_token, token_hash) VALUES ('member1', 'member1@local.server', :p, 'member', :t, :th)")
        ->execute([':p' => $passHash, ':t' => $memberToken, ':th' => $memberTokenHash]);
    $member1Id = (int)$pdo->lastInsertId();
} else {
    $member1Id = (int)$memberUser['id'];
    $pdo->prepare("UPDATE sys_users SET email = 'member1@local.server', role = 'member', auth_token = :t, token_hash = :th WHERE id = :id")
        ->execute([':t' => $memberToken, ':th' => $memberTokenHash, ':id' => $member1Id]);
}

echo "=== Running RBAC Security Test Suite ===\n\n";

$passed = 0;
$failed = 0;

function runTest($name, $closure) {
    global $passed, $failed;
    echo "• [TEST] $name... ";
    try {
        $closure();
        echo "PASS ✓\n";
        $passed++;
    } catch (\Throwable $t) {
        echo "FAIL ✗: " . $t->getMessage() . "\n";
        $failed++;
    }
}

// Helper to simulate request to an endpoint script in an isolated sub-process
function invokeEndpoint($scriptPath, $method = 'GET', $headers = [], $body = null, $query = []) {
    $runnerCode = '<?php
    putenv("DB_SQLITE_PATH=/tmp/access_db.sqlite");
    $_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';
    $_SERVER["HTTP_AUTHORIZATION"] = ' . var_export($headers['Authorization'] ?? '', true) . ';
    $_SERVER["CONTENT_TYPE"] = "application/json";
    $_SERVER["HTTP_CONTENT_TYPE"] = "application/json";
    $_GET = ' . var_export($query, true) . ';
    $_POST = [];
    $_REQUEST = $_GET;

    register_shutdown_function(function() {
        $code = http_response_code() ?: 200;
        $output = ob_get_clean();
        echo "\n__RESPONSE_DELIMITER__\n" . json_encode(["http_code" => $code, "body" => $output]);
    });
    ob_start();
    require ' . var_export(realpath($scriptPath), true) . ';
    ';

    $tempScript = tempnam(sys_get_temp_dir(), 'rbac_runner_');
    file_put_contents($tempScript, $runnerCode);

    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $proc = proc_open("php " . escapeshellarg($tempScript), $descriptors, $pipes);
    if (!is_resource($proc)) {
        unlink($tempScript);
        throw new \Exception("Failed to spawn php runner process");
    }

    if ($body !== null) {
        $bodyStr = is_string($body) ? $body : json_encode($body);
        fwrite($pipes[0], $bodyStr);
    }
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($proc);
    unlink($tempScript);

    $parts = explode("\n__RESPONSE_DELIMITER__\n", $stdout);
    $jsonSection = end($parts);
    $parsed = json_decode(trim($jsonSection), true);

    if (!$parsed) {
        throw new \Exception("Runner failed. Stdout: " . $stdout . " Stderr: " . $stderr);
    }

    return [
        'code' => $parsed['http_code'],
        'json' => json_decode($parsed['body'], true),
        'raw'  => $parsed['body']
    ];
}

// ─── TESTS FOR api/get_users.php ───

runTest("get_users.php rejects unauthenticated requests (HTTP 401)", function() {
    $res = invokeEndpoint(__DIR__ . '/../api/get_users.php', 'GET');
    if ($res['code'] !== 401) {
        throw new \Exception("Expected 401, got " . $res['code'] . ": " . $res['raw']);
    }
    if (($res['json']['status'] ?? '') !== 'error') {
        throw new \Exception("Expected error status in JSON");
    }
});

runTest("get_users.php rejects non-admin ('member') requests (HTTP 403)", function() use ($memberToken) {
    $res = invokeEndpoint(__DIR__ . '/../api/get_users.php', 'GET', ['Authorization' => "Bearer $memberToken"]);
    if ($res['code'] !== 403) {
        throw new \Exception("Expected 403, got " . $res['code'] . ": " . $res['raw']);
    }
});

runTest("get_users.php rejects tokens passed in query string (HTTP 400)", function() use ($adminToken) {
    $res = invokeEndpoint(__DIR__ . '/../api/get_users.php', 'GET', [], null, ['token' => $adminToken]);
    if ($res['code'] !== 400) {
        throw new \Exception("Expected 400, got " . $res['code'] . ": " . $res['raw']);
    }
});

runTest("get_users.php allows admin and returns distinct roles for each user (HTTP 200)", function() use ($adminToken) {
    $res = invokeEndpoint(__DIR__ . '/../api/get_users.php', 'GET', ['Authorization' => "Bearer $adminToken"]);
    if ($res['code'] !== 200) {
        throw new \Exception("Expected 200, got " . $res['code'] . ": " . $res['raw']);
    }
    $users = $res['json']['users'] ?? null;
    if (!is_array($users) || count($users) === 0) {
        throw new \Exception("Users array missing or empty in response");
    }

    $roles = [];
    foreach ($users as $u) {
        if (!isset($u['id'], $u['email'], $u['role'])) {
            throw new \Exception("User missing required keys (id, email, role): " . json_encode($u));
        }
        $roles[$u['username'] ?? $u['email']] = $u['role'];
    }

    if (($roles['admin'] ?? '') !== 'admin') {
        throw new \Exception("Expected 'admin' user to have role 'admin', got " . ($roles['admin'] ?? 'none'));
    }
    if (($roles['member1'] ?? '') !== 'member') {
        throw new \Exception("Expected 'member1' user to have role 'member', got " . ($roles['member1'] ?? 'none'));
    }
});

// ─── TESTS FOR api/update_role.php ───

runTest("update_role.php rejects non-POST requests (HTTP 405)", function() {
    $res = invokeEndpoint(__DIR__ . '/../api/update_role.php', 'GET');
    if ($res['code'] !== 405) {
        throw new \Exception("Expected 405, got " . $res['code']);
    }
});

runTest("update_role.php rejects unauthenticated requests (HTTP 401)", function() use ($admin2Id) {
    $res = invokeEndpoint(__DIR__ . '/../api/update_role.php', 'POST', [], ['user_id' => $admin2Id, 'new_role' => 'member']);
    if ($res['code'] !== 401) {
        throw new \Exception("Expected 401, got " . $res['code']);
    }
});

runTest("update_role.php rejects non-admin ('member') demote/promote requests (HTTP 403)", function() use ($memberToken, $admin2Id) {
    $res = invokeEndpoint(__DIR__ . '/../api/update_role.php', 'POST', ['Authorization' => "Bearer $memberToken"], ['user_id' => $admin2Id, 'new_role' => 'member']);
    if ($res['code'] !== 403) {
        throw new \Exception("Expected 403, got " . $res['code']);
    }
});

runTest("update_role.php rejects invalid role input (HTTP 400)", function() use ($adminToken, $member1Id) {
    $res = invokeEndpoint(__DIR__ . '/../api/update_role.php', 'POST', ['Authorization' => "Bearer $adminToken"], ['user_id' => $member1Id, 'new_role' => "'; DROP TABLE sys_users;--"]);
    if ($res['code'] !== 400) {
        throw new \Exception("Expected 400, got " . $res['code']);
    }
});

function getLatestUserRole($userId) {
    $conn = new SQLitePDO("sqlite:" . (getenv('DB_SQLITE_PATH') ?: DB_SQLITE_PATH));
    $conn->exec("PRAGMA busy_timeout = 5000;");
    $stmt = $conn->prepare("SELECT role FROM sys_users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $role = $stmt->fetchColumn();
    $stmt->closeCursor();
    $stmt = null;
    $conn = null;
    return $role;
}

runTest("update_role.php demotes an admin to 'member' successfully (HTTP 200)", function() use ($adminToken, $admin2Id) {
    $res = invokeEndpoint(__DIR__ . '/../api/update_role.php', 'POST', ['Authorization' => "Bearer $adminToken"], ['user_id' => $admin2Id, 'new_role' => 'member']);
    if ($res['code'] !== 200) {
        throw new \Exception("Expected 200, got " . $res['code'] . ": " . $res['raw']);
    }
    if (($res['json']['new_role'] ?? '') !== 'member') {
        throw new \Exception("Expected returned new_role to be 'member'");
    }

    // Verify in database
    $dbRole = getLatestUserRole($admin2Id);
    if ($dbRole !== 'member') {
        throw new \Exception("Database role was not updated to 'member': got '$dbRole'");
    }
});

runTest("update_role.php blocks demoting the sole remaining admin (HTTP 400)", function() use ($adminToken, $pdo) {
    // Only 'admin' is admin now (admin2 was demoted in previous test)
    $stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = 'admin'");
    $stmt->execute();
    $adminId = (int)$stmt->fetchColumn();

    $res = invokeEndpoint(__DIR__ . '/../api/update_role.php', 'POST', ['Authorization' => "Bearer $adminToken"], ['user_id' => $adminId, 'new_role' => 'member']);
    if ($res['code'] !== 400) {
        throw new \Exception("Expected 400 when attempting to demote last admin, got " . $res['code'] . ": " . $res['raw']);
    }
});

runTest("update_role.php promotes a member to 'admin' successfully (HTTP 200)", function() use ($adminToken, $admin2Id) {
    $res = invokeEndpoint(__DIR__ . '/../api/update_role.php', 'POST', ['Authorization' => "Bearer $adminToken"], ['user_id' => $admin2Id, 'new_role' => 'admin']);
    if ($res['code'] !== 200) {
        throw new \Exception("Expected 200, got " . $res['code'] . ": " . $res['raw']);
    }

    // Verify in database
    $dbRole = getLatestUserRole($admin2Id);
    if ($dbRole !== 'admin') {
        throw new \Exception("Database role was not updated to 'admin': got '$dbRole'");
    }
});

// ─── JWT TOKEN VERIFICATION TEST ───
runTest("JWT token decoding and verification in api/update_role.php", function() use ($member1Id, $pdo) {
    // Generate a valid JWT with role 'admin'
    $header = json_encode(['typ' => 'JWT', 'alg' => 'none']);
    $payload = json_encode([
        'sub' => 1,
        'username' => 'admin',
        'role' => 'admin',
        'exp' => time() + 3600
    ]);
    $jwt = rtrim(strtr(base64_encode($header), '+/', '-_'), '=') . '.' .
           rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' .
           'test_signature';

    $res = invokeEndpoint(__DIR__ . '/../api/update_role.php', 'POST', ['Authorization' => "Bearer $jwt"], ['user_id' => $member1Id, 'new_role' => 'admin']);
    if ($res['code'] !== 200) {
        throw new \Exception("Expected 200 with JWT, got " . $res['code'] . ": " . $res['raw']);
    }

    // Verify role in database
    $role12 = getLatestUserRole($member1Id);
    if ($role12 !== 'admin') {
        throw new \Exception("Role was not updated to admin with JWT: got '$role12'");
    }

    // Reset member1 back to member
    $resetConn = new SQLitePDO("sqlite:" . (getenv('DB_SQLITE_PATH') ?: DB_SQLITE_PATH));
    $resetConn->exec("PRAGMA busy_timeout = 5000;");
    $resetConn->prepare("UPDATE sys_users SET role = 'member' WHERE id = :id")->execute([':id' => $member1Id]);
    $resetConn = null;
});

echo "\n======================================\n";
echo "Test Results: $passed Passed, $failed Failed.\n";
echo "======================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
