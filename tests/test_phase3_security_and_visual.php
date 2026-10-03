<?php
/**
 * tests/test_phase3_security_and_visual.php
 * Verification test suite for Phase 3: Visual Layout & In-Depth Security Audit
 * Strictly PHP 7.4 compatible.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== Phase 3 Security & Visual Audit Test Suite ===\n";
$passed = 0;
$failed = 0;

function assertPhase3($cond, $name) {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] $name\n";
        $passed++;
    } else {
        echo " [FAIL] $name\n";
        $failed++;
    }
}

$projectRoot = dirname(__DIR__);

// 1. Audit CORS in api_proxy.php
$proxyFile = $projectRoot . '/modern-music-app/api_proxy.php';
assertPhase3(file_exists($proxyFile), 'api_proxy.php exists');
$proxyContent = file_get_contents($proxyFile);

assertPhase3(
    !preg_match('/header\([\'"]Access-Control-Allow-Origin:\s*\*[\'"]\);/i', $proxyContent),
    'api_proxy.php does NOT contain wildcard Access-Control-Allow-Origin: *'
);

assertPhase3(
    strpos($proxyContent, 'https://serverflow.icu') !== false &&
    strpos($proxyContent, 'https://www.serverflow.icu') !== false &&
    strpos($proxyContent, 'http://localhost:8888') !== false &&
    strpos($proxyContent, 'http://10.247.192.231:8888') !== false,
    'api_proxy.php CORS allowlist explicitly includes serverflow.icu, localhost, and ZeroTier IP'
);

assertPhase3(
    strpos($proxyContent, 'Vary: Origin') !== false,
    'api_proxy.php sets Vary: Origin header on allowed CORS responses'
);

// 2. Audit Session Lock Guard in api_proxy.php
assertPhase3(
    strpos($proxyContent, "\$_mediaActions = ['getCoverArt', 'coverArt', 'stream', 'download', 'search', 'search2', 'search3'];") !== false,
    'api_proxy.php preserves session lock guard for all media & search actions'
);

// 3. Audit Cover Art Caching in api_proxy.php
assertPhase3(
    strpos($proxyContent, 'Cache-Control: public, max-age=2592000, immutable') !== false,
    'api_proxy.php issues immutable 30-day Cache-Control header for artwork images'
);

// 4. Audit iOS Safari Notch & Safe Area Insets in Frontend
$indexHtml = file_get_contents($projectRoot . '/modern-music-app/index.html');
assertPhase3(
    strpos($indexHtml, 'viewport-fit=cover') !== false,
    'modern-music-app/index.html sets viewport-fit=cover for full edge-to-edge iOS display'
);

$topBarFile = $projectRoot . '/modern-music-app/src/components/TopBar.jsx';
$topBarContent = file_get_contents($topBarFile);
assertPhase3(
    strpos($topBarContent, 'env(safe-area-inset-top)') !== false,
    'TopBar.jsx accounts for iOS notch with env(safe-area-inset-top)'
);

$mobileNavFile = $projectRoot . '/modern-music-app/src/components/MobileNav.jsx';
$mobileNavContent = file_get_contents($mobileNavFile);
assertPhase3(
    strpos($mobileNavContent, 'env(safe-area-inset-bottom') !== false,
    'MobileNav.jsx accounts for iOS home indicator with env(safe-area-inset-bottom)'
);

$stickyPlayerFile = $projectRoot . '/modern-music-app/src/components/StickyPlayer.jsx';
$stickyPlayerContent = file_get_contents($stickyPlayerFile);
assertPhase3(
    strpos($stickyPlayerContent, 'env(safe-area-inset-bottom') !== false,
    'StickyPlayer.jsx accounts for iOS home indicator with env(safe-area-inset-bottom)'
);

// 5. Audit Root .htaccess Protection
$htaccessFile = $projectRoot . '/.htaccess';
$htaccessContent = file_get_contents($htaccessFile);
assertPhase3(
    preg_match('/sqlite/i', $htaccessContent) &&
    preg_match('/env/i', $htaccessContent) &&
    preg_match('/htpasswd/i', $htaccessContent) &&
    preg_match('/\.git/i', $htaccessContent),
    '.htaccess denies direct web access to .sqlite, .env, .htpasswd, and .git files'
);

// 6. Audit Dist Bundle Integrity
$distHtml = $projectRoot . '/modern-music-app/dist/index.html';
assertPhase3(file_exists($distHtml), 'dist/index.html exists');

$distContent = file_get_contents($distHtml);
preg_match('/src="\.\/assets\/(index[\.-][a-zA-Z0-9_-]+\.js)"/', $distContent, $matches);
$bundleFile = isset($matches[1]) ? $projectRoot . '/modern-music-app/dist/assets/' . $matches[1] : '';

assertPhase3(
    !empty($bundleFile) && file_exists($bundleFile),
    'dist/index.html references valid compiled JS bundle (' . ($matches[1] ?? 'none') . ')'
);

if (file_exists($bundleFile)) {
    $bundleCode = file_get_contents($bundleFile);
    assertPhase3(
        !preg_match('/(\w+|\))\s*(\?\?=|\|\|=|&&=)/', $bundleCode),
        'Compiled JS bundle contains NO ES2021 logical assignments (Safari 13 safe)'
    );
}

// 7. Dynamic Plex Routing Audit
$serverflowCoreFile = $projectRoot . '/js/serverflow_core.js';
$serverflowCoreContent = file_get_contents($serverflowCoreFile);
assertPhase3(
    strpos($serverflowCoreContent, 'function handlePlexRouting') !== false &&
    strpos($serverflowCoreContent, 'serverflow.icu') !== false &&
    strpos($serverflowCoreContent, 'app.plex.tv/web') !== false,
    'serverflow_core.js includes dynamic Plex handler redirecting Cloudflare tunnels to app.plex.tv/web'
);

echo "\nSummary: $passed passed, $failed failed.\n";
if ($failed > 0) {
    exit(1);
}
exit(0);
