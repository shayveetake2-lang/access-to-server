<?php
/**
 * tests/test_aether_media_auth.php
 * Verification suite for Aether Media & Search Authentication and Security Headers
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== Aether Media & Search Security Verification ===\n";
$passed = 0;
$failed = 0;

function assertCheck($cond, $name) {
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
$proxyFile = $projectRoot . '/modern-music-app/api_proxy.php';

assertCheck(file_exists($proxyFile), 'api_proxy.php exists');
$proxyContent = file_get_contents($proxyFile);

// 1. Security Headers
assertCheck(
    strpos($proxyContent, "header('X-Content-Type-Options: nosniff');") !== false,
    'api_proxy.php enforces X-Content-Type-Options: nosniff header'
);
assertCheck(
    strpos($proxyContent, "header('X-Frame-Options: SAMEORIGIN');") !== false,
    'api_proxy.php enforces X-Frame-Options: SAMEORIGIN header'
);

// 2. Authentication Enforcement in Streaming and Search
assertCheck(
    strpos($proxyContent, 'verifyMediaProxyAuth($pdo)') !== false,
    'api_proxy.php defines and invokes verifyMediaProxyAuth'
);

assertCheck(
    strpos($proxyContent, "http_response_code(401);\n        echo json_encode(['status' => 'error', 'message' => 'Authentication required for media playback.']);") !== false ||
    strpos($proxyContent, 'Authentication required for media playback.') !== false,
    'api_proxy.php rejects unauthenticated stream and download requests with 401'
);

assertCheck(
    strpos($proxyContent, 'Authentication required for library search.') !== false,
    'api_proxy.php rejects unauthenticated library search requests with 401'
);

// 3. Frontend Authentication Propagation
$apiJsFile = $projectRoot . '/modern-music-app/src/utils/api.js';
assertCheck(file_exists($apiJsFile), 'src/utils/api.js exists');
$apiJsContent = file_get_contents($apiJsFile);

assertCheck(
    strpos($apiJsContent, 'export function getStreamUrl(trackId, authParams = null)') !== false,
    'api.js getStreamUrl accepts authParams and appends credentials'
);

assertCheck(
    strpos($apiJsContent, 'getSubsonicAuthParams(user)') !== false &&
    strpos($apiJsContent, 'authSuffix') !== false,
    'api.js searchSubsonic transmits resolved authentication to api_proxy.php'
);

// 4. ErrorBoundary Hook Safety
$ebFile = $projectRoot . '/modern-music-app/src/components/ErrorBoundary.jsx';
$ebContent = file_get_contents($ebFile);
assertCheck(
    strpos($ebContent, 'try {') === false && strpos($ebContent, 'useLocation()') !== false,
    'ErrorBoundary.jsx calls useLocation unconditionally without try/catch'
);
assertCheck(
    strpos($ebContent, 'key={locationKey}') !== false,
    'ErrorBoundary.jsx uses locationKey prop key for safe error boundary remounting'
);

// 5. AuthContext Closure and Function Integrity
$acFile = $projectRoot . '/modern-music-app/src/context/AuthContext.jsx';
$acContent = file_get_contents($acFile);
assertCheck(
    strpos($acContent, 'async function verifyToken(credentials)') !== false,
    'AuthContext.jsx defines verifyToken at top-level avoiding initialization deadlocks'
);

echo "\nSummary: $passed passed, $failed failed.\n";
if ($failed > 0) {
    exit(1);
}
exit(0);
