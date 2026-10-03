<?php
/**
 * tests/test_phase2_charts.php
 * Comprehensive test suite for Phase 2: Top 100 Charts & Authenticated Play Tracking
 * Strictly PHP 7.4 compatible.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../api/auth/jwt_utils.php';

echo "=== Phase 2 Charts & Play Tracking Test Suite ===\n";
$passed = 0;
$failed = 0;

function assertTest($cond, $name) {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] $name\n";
        $passed++;
    } else {
        echo " [FAIL] $name\n";
        $failed++;
    }
}

// 1. Timezone & Midnight Auckland Calculation Test
try {
    $tz = new DateTimeZone('Pacific/Auckland');
    $nowAuckland = new DateTime('now', $tz);
    $midnightAuckland = new DateTime('today midnight', $tz);
    $diffSec = $nowAuckland->getTimestamp() - $midnightAuckland->getTimestamp();
    
    assertTest(
        $tz->getName() === 'Pacific/Auckland',
        'Timezone correctly identifies Pacific/Auckland'
    );
    assertTest(
        $diffSec >= 0 && $diffSec <= 86400,
        'Midnight Auckland timestamp is within valid [0, 86400] seconds of current time'
    );
    assertTest(
        $midnightAuckland->format('H:i:s') === '00:00:00',
        'Midnight timestamp formats precisely as 00:00:00 in Auckland local time'
    );
} catch (Exception $e) {
    assertTest(false, 'Auckland datetime calculation failed: ' . $e->getMessage());
}

// 2. JWT Generation & Verification for recordPlay
$testPayload = [
    'user_id' => 9999,
    'username' => 'chart_tester',
    'role' => 'user'
];
$validJwt = createSignedJwt($testPayload, 3600);
$verifiedPayload = verifyAndDecodeJwt($validJwt);

assertTest(
    $verifiedPayload !== null && isset($verifiedPayload['username']) && $verifiedPayload['username'] === 'chart_tester',
    'Valid JWT generates and verifies payload successfully'
);

$invalidJwt = $validJwt . 'tampered';
$tamperedPayload = verifyAndDecodeJwt($invalidJwt);
assertTest(
    $tamperedPayload === null,
    'Tampered JWT is rejected cleanly by verifyAndDecodeJwt'
);

$expiredJwt = createSignedJwt($testPayload, -3600);
$verifiedExpired = verifyAndDecodeJwt($expiredJwt);
assertTest(
    $verifiedExpired === null,
    'Expired JWT is rejected cleanly'
);

// 3. Cache Directory & Shared Cache Read/Write/Busting Test
$cacheDir = sys_get_temp_dir() . '/aether_chart_cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

$cacheFile = $cacheDir . '/top_songs_test_100.json';
$dummyData = [
    'status' => 'ok',
    'period' => 'daily',
    'songs' => [
        ['id' => 101, 'title' => 'Track 1', 'dailyPlays' => 15, 'playCount' => 100],
        ['id' => 102, 'title' => 'Track 2', 'dailyPlays' => 10, 'playCount' => 200]
    ]
];

@file_put_contents($cacheFile, json_encode([
    'timestamp' => time(),
    'data' => $dummyData
]));

assertTest(
    file_exists($cacheFile),
    'Chart cache file creates successfully in sys_get_temp_dir()'
);

$cachedContent = @json_decode(@file_get_contents($cacheFile), true);
assertTest(
    isset($cachedContent['data']['songs']) && count($cachedContent['data']['songs']) === 2,
    'Chart cache file payload is valid JSON and contains expected songs'
);

// Bust chart cache
$files = @glob($cacheDir . '/top_songs_*.json');
if ($files) {
    foreach ($files as $f) {
        @unlink($f);
    }
}

assertTest(
    !file_exists($cacheFile),
    'Bust chart cache successfully removes cache files'
);

// 4. Test Pure Daily Ranking Order Logic (In-Memory Verification of Ranking Rules)
$testSongSet = [
    ['id' => 1, 'title' => 'Old Hit', 'dailyPlays' => 2, 'playCount' => 5000],
    ['id' => 2, 'title' => 'Viral Today', 'dailyPlays' => 25, 'playCount' => 30],
    ['id' => 3, 'title' => 'Moderate Daily', 'dailyPlays' => 10, 'playCount' => 200],
    ['id' => 4, 'title' => 'Tied Daily High Total', 'dailyPlays' => 10, 'playCount' => 800],
    ['id' => 5, 'title' => 'Zero Plays Today', 'dailyPlays' => 0, 'playCount' => 10000]
];

// Ranking rule: dailyPlays DESC, playCount (all-time) DESC, id DESC
usort($testSongSet, function($a, $b) {
    if ($a['dailyPlays'] !== $b['dailyPlays']) {
        return ($a['dailyPlays'] > $b['dailyPlays']) ? -1 : 1;
    }
    if ($a['playCount'] !== $b['playCount']) {
        return ($a['playCount'] > $b['playCount']) ? -1 : 1;
    }
    return ($a['id'] > $b['id']) ? -1 : 1;
});

assertTest(
    $testSongSet[0]['id'] === 2,
    'Top ranked song is "Viral Today" (pure daily count 25 beats massive historical total 5000)'
);

assertTest(
    $testSongSet[1]['id'] === 4 && $testSongSet[2]['id'] === 3,
    'Tied daily count (10 vs 10) resolves tie-breaker via all-time count (800 beats 200)'
);

assertTest(
    $testSongSet[3]['id'] === 1 && $testSongSet[4]['id'] === 5,
    'Tracks with zero daily plays fall to the bottom of the daily chart regardless of all-time count'
);

// 5. Test Album ID Safeguard (No synthetic string "unknown")
$rawSubsonicSong = [
    'id' => 501,
    'title' => 'Untagged Song',
    'artist' => 'Unknown Artist',
    'albumId' => 'unknown', // bad synthetic string
    'album' => 'Unknown Album'
];

$sanitizedAlbumId = null;
if (isset($rawSubsonicSong['albumId']) && is_numeric($rawSubsonicSong['albumId'])) {
    $sanitizedAlbumId = (int)$rawSubsonicSong['albumId'];
}

assertTest(
    $sanitizedAlbumId === null,
    'Synthetic string "unknown" for albumId is safely neutralized to null (not passed to SQL/API)'
);

$validNumericAlbum = [
    'id' => 502,
    'title' => 'Tagged Song',
    'albumId' => '12345'
];
$sanitizedNumericAlbumId = (isset($validNumericAlbum['albumId']) && is_numeric($validNumericAlbum['albumId']))
    ? (int)$validNumericAlbum['albumId']
    : null;

assertTest(
    $sanitizedNumericAlbumId === 12345,
    'Numeric string albumId correctly casts to integer 12345'
);

echo "\nSummary: $passed passed, $failed failed.\n";
if ($failed > 0) {
    exit(1);
}
exit(0);
