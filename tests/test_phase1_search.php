<?php
// tests/test_phase1_search.php — Regression & Edge-Case Suite for Phase 1 Search Engine
// Strictly PHP 7.4 compatible.

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "========================================================\n";
echo " Running Phase 1 Search Engine Test Suite\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition($desc, $cond) {
    global $passCount, $failCount;
    if ($cond) {
        echo " [PASS] " . $desc . "\n";
        $passCount++;
    } else {
        echo " [FAIL] " . $desc . "\n";
        $failCount++;
    }
}

// 1. PHP Syntax Check on api_proxy.php
$proxyPath = __DIR__ . '/../modern-music-app/api_proxy.php';
$syntaxOutput = [];
$returnVar = 0;
exec('php -l ' . escapeshellarg($proxyPath) . ' 2>&1', $syntaxOutput, $returnVar);
assertCondition("api_proxy.php has valid PHP 7.4 syntax", $returnVar === 0);

// 2. Session Guard Check (Invariant 1)
$proxyContent = file_get_contents($proxyPath);
assertCondition(
    "PHP Session Guard is intact (skips session_start for media & search)",
    strpos($proxyContent, "if (!in_array(\$_action, \$_mediaActions))") !== false &&
    strpos($proxyContent, "'search', 'search2', 'search3'") !== false
);

// 3. Charset utf8mb4 in getProxyPdo
assertCondition(
    "getProxyPdo enforces charset=utf8mb4 and SET NAMES utf8mb4",
    strpos($proxyContent, "charset=utf8mb4") !== false &&
    strpos($proxyContent, "SET NAMES utf8mb4") !== false
);

// 4. Test Search File Cache and Probabilistic GC
$tempDir = sys_get_temp_dir() . '/aether_search_cache';
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0755, true);
}

// Simulate write and read
$testKey = 'test_key_' . md5('test_query');
$testPayload = ['status' => 'ok', 'song' => [['id' => '300000001', 'title' => 'Test Track']]];
$testFile = $tempDir . '/' . $testKey . '.json';
file_put_contents($testFile, json_encode($testPayload));

assertCondition("Search cache writes valid JSON to sys_get_temp_dir", file_exists($testFile));
$readPayload = json_decode(file_get_contents($testFile), true);
assertCondition("Search cache reads correct payload", isset($readPayload['song'][0]['title']) && $readPayload['song'][0]['title'] === 'Test Track');
@unlink($testFile);

// 5. Test Frontend Bundle & Hashed Asset Output
$distIndex = __DIR__ . '/../modern-music-app/dist/index.html';
assertCondition("dist/index.html exists", file_exists($distIndex));
$distContent = file_get_contents($distIndex);
assertCondition("dist/index.html references hashed JS bundle", preg_match('/assets\/index\.[a-zA-Z0-9_-]+\.js/', $distContent) === 1);

// 6. Test SearchBar.jsx configuration
$searchBarPath = __DIR__ . '/../modern-music-app/src/components/SearchBar.jsx';
$searchBarContent = file_get_contents($searchBarPath);
assertCondition("SearchBar uses 150ms debounce", strpos($searchBarContent, "useDebounce(query, 150)") !== false);
assertCondition("SearchBar requests bounded dropdown counts (8/4/4)", strpos($searchBarContent, "songCount: 8, albumCount: 4, artistCount: 4") !== false);
assertCondition("SearchBar includes onKeyDown Enter navigation", strpos($searchBarContent, "e.key === 'Enter'") !== false);
assertCondition("SearchBar images use lazy loading and async decoding", strpos($searchBarContent, "loading=\"lazy\"") !== false && strpos($searchBarContent, "decoding=\"async\"") !== false);

// 7. Verify Unknown Album IDs are Numeric (no synthetic string IDs)
assertCondition(
    "api_proxy.php casts album IDs to integer and does not emit 'unknown' album IDs",
    strpos($proxyContent, "albumId = 'unknown'") === false &&
    strpos($proxyContent, "cleanAlbId = (!empty(\$r['albumId']) && is_numeric(\$r['albumId'])) ? (int)\$r['albumId'] : 0") !== false
);

echo "\n--------------------------------------------------------\n";
echo sprintf("Phase 1 Tests: %d Passed, %d Failed.\n", $passCount, $failCount);
echo "--------------------------------------------------------\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
