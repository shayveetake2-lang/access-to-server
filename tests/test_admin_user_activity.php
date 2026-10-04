<?php
/**
 * tests/test_admin_user_activity.php
 * DevSecOps Test Suite for Admin User Activity & Favorites Endpoints:
 * - action=adminGetUserHistory
 * - action=adminGetUserFavorites
 *
 * Runs api_proxy.php in an isolated CLI sub-process against a throwaway SQLite
 * database (via the CLI-only TEST_PROXY_SQLITE override).
 */

putenv("DB_SQLITE_PATH=/tmp/access_db.sqlite");
$testSqlitePath = '/tmp/test_aether_activity.sqlite';
@unlink($testSqlitePath);
putenv("TEST_PROXY_SQLITE=" . $testSqlitePath);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/auth/jwt_utils.php';

// Initialize SQLite schema mimicking Ampache & sys_users tables
$testPdo = new PDO('sqlite:' . $testSqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$testPdo->exec("
    CREATE TABLE IF NOT EXISTS sys_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        role TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS user (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        access INTEGER NOT NULL DEFAULT 25,
        email TEXT
    );
    CREATE TABLE IF NOT EXISTS artist (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS album (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        year INTEGER DEFAULT 2026,
        song_count INTEGER DEFAULT 1,
        album_artist INTEGER
    );
    CREATE TABLE IF NOT EXISTS song (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        time INTEGER DEFAULT 180,
        track INTEGER DEFAULT 1,
        size INTEGER DEFAULT 5000000,
        bitrate INTEGER DEFAULT 320000,
        total_count INTEGER DEFAULT 0,
        artist INTEGER,
        album INTEGER,
        enabled INTEGER DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS object_count (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        object_type TEXT NOT NULL,
        object_id INTEGER NOT NULL,
        date INTEGER NOT NULL,
        user INTEGER NOT NULL,
        agent TEXT DEFAULT 'Aether',
        count_type TEXT DEFAULT 'stream'
    );
    CREATE TABLE IF NOT EXISTS user_flag (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user INTEGER NOT NULL,
        object_id INTEGER NOT NULL,
        object_type TEXT NOT NULL,
        date INTEGER NOT NULL
    );
");

// Seed test data
$testPdo->exec("
    INSERT INTO sys_users (id, username, role) VALUES (1, 'admin', 'admin');
    INSERT INTO sys_users (id, username, role) VALUES (2, 'member1', 'member');
    INSERT INTO sys_users (id, username, role) VALUES (3, 'testlistener', 'member');

    INSERT INTO user (id, username, access, email) VALUES (1, 'admin', 100, 'admin@example.com');
    INSERT INTO user (id, username, access, email) VALUES (2, 'member1', 25, 'member1@example.com');
    INSERT INTO user (id, username, access, email) VALUES (3, 'testlistener', 25, 'listener@example.com');

    INSERT INTO artist (id, name) VALUES (10, 'Daft Punk');
    INSERT INTO artist (id, name) VALUES (11, 'Tame Impala');

    INSERT INTO album (id, name, year, song_count, album_artist) VALUES (20, 'Discovery', 2001, 14, 10);
    INSERT INTO album (id, name, year, song_count, album_artist) VALUES (21, 'Currents', 2015, 13, 11);

    INSERT INTO song (id, title, time, track, size, bitrate, total_count, artist, album, enabled)
    VALUES (101, 'One More Time', 320, 1, 8000000, 320000, 42, 10, 20, 1);
    INSERT INTO song (id, title, time, track, size, bitrate, total_count, artist, album, enabled)
    VALUES (102, 'Harder, Better, Faster, Stronger', 224, 2, 6000000, 320000, 15, 10, 20, 1);
    INSERT INTO song (id, title, time, track, size, bitrate, total_count, artist, album, enabled)
    VALUES (103, 'The Less I Know The Better', 216, 1, 5500000, 320000, 30, 11, 21, 1);
    -- Disabled song: must NOT appear anywhere
    INSERT INTO song (id, title, time, track, size, bitrate, total_count, artist, album, enabled)
    VALUES (104, 'Deleted Song', 150, 4, 3000000, 320000, 5, 10, 20, 0);

    -- Stream events for testlistener (user 3). 105 has no song row at all (orphan).
    INSERT INTO object_count (object_type, object_id, date, user, agent, count_type) VALUES ('song', 101, 1775280000, 3, 'Aether', 'stream');
    INSERT INTO object_count (object_type, object_id, date, user, agent, count_type) VALUES ('song', 101, 1775283600, 3, 'Aether', 'stream');
    INSERT INTO object_count (object_type, object_id, date, user, agent, count_type) VALUES ('song', 102, 1775287200, 3, 'Aether', 'stream');
    INSERT INTO object_count (object_type, object_id, date, user, agent, count_type) VALUES ('song', 104, 1775289000, 3, 'Aether', 'stream');
    INSERT INTO object_count (object_type, object_id, date, user, agent, count_type) VALUES ('song', 105, 1775290000, 3, 'Aether', 'stream');
    -- Another user's play must never leak into testlistener's data
    INSERT INTO object_count (object_type, object_id, date, user, agent, count_type) VALUES ('song', 103, 1775291000, 2, 'Aether', 'stream');

    -- Favorites for testlistener (user 3)
    INSERT INTO user_flag (user, object_id, object_type, date) VALUES (3, 101, 'song', 1775280000);
    INSERT INTO user_flag (user, object_id, object_type, date) VALUES (3, 20, 'album', 1775281000);
    INSERT INTO user_flag (user, object_id, object_type, date) VALUES (3, 10, 'artist', 1775282000);
    -- Another user's favorite must not leak
    INSERT INTO user_flag (user, object_id, object_type, date) VALUES (2, 103, 'song', 1775283000);
");

$adminJwt = createSignedJwt(['sub' => 1, 'user_id' => 1, 'username' => 'admin', 'role' => 'admin'], 3600);
$memberJwt = createSignedJwt(['sub' => 2, 'user_id' => 2, 'username' => 'member1', 'role' => 'member'], 3600);

echo "=== Running Admin User Activity Test Suite ===\n\n";

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

// Simulate a request to modern-music-app/api_proxy.php in an isolated sub-process
function invokeProxyEndpoint($action, $method = 'GET', $headers = [], $query = [], $body = null) {
    global $testSqlitePath;
    $proxyPath = realpath(__DIR__ . '/../modern-music-app/api_proxy.php');
    $query['action'] = $action;

    $runnerCode = '<?php
    putenv("DB_SQLITE_PATH=/tmp/access_db.sqlite");
    putenv("TEST_PROXY_SQLITE=' . $testSqlitePath . '");
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
    require ' . var_export($proxyPath, true) . ';
    ';

    $tempScript = tempnam(sys_get_temp_dir(), 'proxy_runner_');
    file_put_contents($tempScript, $runnerCode);

    $descriptors = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
    $proc = proc_open("php -d log_errors=1 -d error_log=/dev/stderr " . escapeshellarg($tempScript), $descriptors, $pipes);
    if (!is_resource($proc)) {
        unlink($tempScript);
        throw new \Exception("Failed to spawn php runner process");
    }

    if ($body !== null) {
        fwrite($pipes[0], is_string($body) ? $body : json_encode($body));
    }
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($proc);
    unlink($tempScript);

    $parts = explode("\n__RESPONSE_DELIMITER__\n", $stdout);
    $parsed = json_decode(trim(end($parts)), true);
    if (!$parsed) {
        throw new \Exception("Runner failed. Stdout: " . $stdout . " Stderr: " . $stderr);
    }

    return [
        'code' => $parsed['http_code'],
        'json' => json_decode($parsed['body'], true),
        'raw'  => $parsed['body'],
        'stderr' => $stderr
    ];
}

function authHeader($jwt) {
    return ['Authorization' => "Bearer $jwt"];
}

// ─── 1. AUTHENTICATION & RBAC ───

foreach (['adminGetUserHistory', 'adminGetUserFavorites'] as $act) {
    runTest("$act rejects unauthenticated requests (HTTP 401)", function() use ($act) {
        $res = invokeProxyEndpoint($act, 'GET', [], ['username' => 'testlistener']);
        if ($res['code'] !== 401) throw new \Exception("Expected 401, got " . $res['code'] . ": " . $res['raw']);
    });
    runTest("$act rejects a tampered JWT (HTTP 401)", function() use ($act, $adminJwt) {
        $res = invokeProxyEndpoint($act, 'GET', authHeader($adminJwt . 'x'), ['username' => 'testlistener']);
        if ($res['code'] !== 401) throw new \Exception("Expected 401, got " . $res['code'] . ": " . $res['raw']);
    });
    runTest("$act rejects member role (HTTP 403)", function() use ($act, $memberJwt) {
        $res = invokeProxyEndpoint($act, 'GET', authHeader($memberJwt), ['username' => 'testlistener']);
        if ($res['code'] !== 403) throw new \Exception("Expected 403, got " . $res['code'] . ": " . $res['raw']);
    });
    runTest("$act requires username parameter (HTTP 400)", function() use ($act, $adminJwt) {
        $res = invokeProxyEndpoint($act, 'GET', authHeader($adminJwt), ['username' => '']);
        if ($res['code'] !== 400) throw new \Exception("Expected 400, got " . $res['code'] . ": " . $res['raw']);
    });
    runTest("$act treats a SQL-injection username as an unknown user (HTTP 200, ampacheLinked false)", function() use ($act, $adminJwt) {
        $res = invokeProxyEndpoint($act, 'GET', authHeader($adminJwt), ['username' => "x' OR '1'='1"]);
        if ($res['code'] !== 200 || ($res['json']['user']['ampacheLinked'] ?? null) !== false) {
            throw new \Exception("Injection not inert: " . $res['raw']);
        }
    });
    runTest("$act handles non-existent user gracefully (HTTP 200, ampacheLinked false)", function() use ($act, $adminJwt) {
        $res = invokeProxyEndpoint($act, 'GET', authHeader($adminJwt), ['username' => 'nonexistent_user_9999']);
        if ($res['code'] !== 200 || ($res['json']['status'] ?? '') !== 'ok') throw new \Exception("Expected 200/ok: " . $res['raw']);
        if (($res['json']['user']['ampacheLinked'] ?? null) !== false) throw new \Exception("Expected ampacheLinked: false");
    });
}

// ─── 2. DATA CORRECTNESS ───

runTest("History: counts exclude disabled and orphaned songs, and other users' plays", function() use ($adminJwt) {
    $res = invokeProxyEndpoint('adminGetUserHistory', 'GET', authHeader($adminJwt), ['username' => 'testlistener', 'limit' => 10]);
    if ($res['code'] !== 200) throw new \Exception("Expected 200, got " . $res['code'] . ": " . $res['raw']);
    $j = $res['json'];
    if ($j['summary']['totalPlays'] !== 3) throw new \Exception("Expected totalPlays 3, got " . $j['summary']['totalPlays']);
    if ($j['summary']['uniqueSongs'] !== 2) throw new \Exception("Expected uniqueSongs 2, got " . $j['summary']['uniqueSongs']);
    if ($j['summary']['lastPlayedAt'] !== 1775287200) throw new \Exception("Expected lastPlayedAt 1775287200, got " . var_export($j['summary']['lastPlayedAt'], true));
    if (count($j['history']) !== 3) throw new \Exception("Expected 3 history rows, got " . count($j['history']));
    foreach ($j['history'] as $row) {
        if ($row['title'] === null || $row['title'] === '' || $row['title'] === 'Deleted Song') throw new \Exception("Unexpected row: " . json_encode($row));
    }
    if ($j['history'][0]['title'] !== 'Harder, Better, Faster, Stronger') throw new \Exception("History must be newest-first");
    if ($j['user']['role'] !== 'member') throw new \Exception("Expected role member, got " . $j['user']['role']);
});

runTest("History: Subsonic IDs are numeric strings with the expected offsets", function() use ($adminJwt) {
    $res = invokeProxyEndpoint('adminGetUserHistory', 'GET', authHeader($adminJwt), ['username' => 'testlistener']);
    $row = $res['json']['history'][2]; // oldest = song 101
    if ($row['id'] !== '300000101' || $row['albumId'] !== '200000020' || $row['artistId'] !== '100000010') {
        throw new \Exception("Bad ID mapping: " . json_encode($row));
    }
    foreach (['id', 'albumId', 'artistId'] as $k) {
        if (!ctype_digit($row[$k])) throw new \Exception("$k is not numeric: " . $row[$k]);
    }
});

runTest("History: top songs ranked by plays and carry play counts", function() use ($adminJwt) {
    $res = invokeProxyEndpoint('adminGetUserHistory', 'GET', authHeader($adminJwt), ['username' => 'testlistener']);
    $top = $res['json']['topSongs'];
    if (count($top) !== 2) throw new \Exception("Expected 2 top songs, got " . count($top));
    if ($top[0]['title'] !== 'One More Time' || $top[0]['plays'] !== 2) throw new \Exception("Wrong top song: " . json_encode($top[0]));
    if ($top[1]['plays'] !== 1) throw new \Exception("Wrong second song: " . json_encode($top[1]));
});

runTest("History: pagination (limit/offset/hasMore) and topSongs only on first page", function() use ($adminJwt) {
    $p1 = invokeProxyEndpoint('adminGetUserHistory', 'GET', authHeader($adminJwt), ['username' => 'testlistener', 'limit' => 2, 'offset' => 0])['json'];
    if (count($p1['history']) !== 2 || $p1['hasMore'] !== true) throw new \Exception("Page 1 wrong");
    $p2 = invokeProxyEndpoint('adminGetUserHistory', 'GET', authHeader($adminJwt), ['username' => 'testlistener', 'limit' => 2, 'offset' => 2])['json'];
    if (count($p2['history']) !== 1 || $p2['hasMore'] !== false) throw new \Exception("Page 2 wrong");
    if (count($p2['topSongs']) !== 0) throw new \Exception("topSongs should be empty on later pages");
});

runTest("History: limit is clamped to 200", function() use ($adminJwt) {
    $res = invokeProxyEndpoint('adminGetUserHistory', 'GET', authHeader($adminJwt), ['username' => 'testlistener', 'limit' => 99999]);
    if (($res['json']['limit'] ?? null) !== 200) throw new \Exception("Expected limit 200, got " . var_export($res['json']['limit'] ?? null, true));
});

runTest("Favorites: returns only the target user's songs, albums and artists", function() use ($adminJwt) {
    $res = invokeProxyEndpoint('adminGetUserFavorites', 'GET', authHeader($adminJwt), ['username' => 'testlistener']);
    $j = $res['json'];
    if ($res['code'] !== 200 || ($j['status'] ?? '') !== 'ok') throw new \Exception("Expected 200/ok: " . $res['raw']);
    if (count($j['songs']) !== 1 || $j['songs'][0]['title'] !== 'One More Time') throw new \Exception("Wrong starred songs");
    if (count($j['albums']) !== 1 || $j['albums'][0]['title'] !== 'Discovery') throw new \Exception("Wrong starred albums");
    if (count($j['artists']) !== 1 || $j['artists'][0]['name'] !== 'Daft Punk') throw new \Exception("Wrong starred artists");
    if ($j['counts'] !== ['songs' => 1, 'albums' => 1, 'artists' => 1]) throw new \Exception("Wrong counts: " . json_encode($j['counts']));
});

runTest("Favorites: admin view equals the user's own getStarred2 view", function() use ($adminJwt) {
    $listenerJwt = createSignedJwt(['sub' => 3, 'user_id' => 3, 'username' => 'testlistener', 'role' => 'member'], 3600);
    $own = invokeProxyEndpoint('getStarred2', 'GET', authHeader($listenerJwt), [])['json'];
    $adm = invokeProxyEndpoint('adminGetUserFavorites', 'GET', authHeader($adminJwt), ['username' => 'testlistener'])['json'];
    foreach (['songs', 'albums', 'artists'] as $k) {
        if (json_encode($own[$k]) !== json_encode($adm[$k])) throw new \Exception("$k differ between own view and admin view");
    }
});

runTest("Admin target is reported with role 'admin' (peer badge)", function() use ($adminJwt) {
    $res = invokeProxyEndpoint('adminGetUserHistory', 'GET', authHeader($adminJwt), ['username' => 'admin']);
    if (($res['json']['user']['role'] ?? '') !== 'admin') throw new \Exception("Expected role admin: " . $res['raw']);
});

// ─── 3. AUDIT TRAIL ───

runTest("Audit log entry is written for admin access", function() use ($adminJwt) {
    $res = invokeProxyEndpoint('adminGetUserHistory', 'GET', authHeader($adminJwt), ['username' => 'testlistener']);
    if (strpos($res['stderr'], "[Aether Audit] Admin 'admin' viewed adminGetUserHistory for user 'testlistener'") === false) {
        throw new \Exception("Audit line missing. stderr: " . $res['stderr']);
    }
});

runTest("No audit entry or data is produced for rejected (member) requests", function() use ($memberJwt) {
    $res = invokeProxyEndpoint('adminGetUserHistory', 'GET', authHeader($memberJwt), ['username' => 'testlistener']);
    if (strpos($res['stderr'], '[Aether Audit]') !== false) throw new \Exception("Audit written for a rejected request");
    if (isset($res['json']['history'])) throw new \Exception("Data leaked to a member");
});

echo "\n======================================\n";
echo "Test Results: $passed Passed, $failed Failed.\n";
echo "======================================\n";

@unlink($testSqlitePath);

exit($failed > 0 ? 1 : 0);
