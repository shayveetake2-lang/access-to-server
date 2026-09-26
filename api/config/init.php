<?php
// /api/config/init.php
// Strict Enterprise Security Headers, CORS, and Session Configuration

if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Global Security Response Headers
header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Dynamic Restricted CORS Policy Whitelist
$allowedOriginsList = [
    'serverflow.icu',
    'www.serverflow.icu',
    'localhost',
    '127.0.0.1'
];

$envOrigins = getenv('CORS_ALLOWED_ORIGINS');
if (!empty($envOrigins)) {
    $extraOrigins = array_map('trim', explode(',', $envOrigins));
    $allowedOriginsList = array_merge($allowedOriginsList, $extraOrigins);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';

if (!empty($origin)) {
    $parsedOriginHost = parse_url($origin, PHP_URL_HOST);
    $isAllowed = false;
    
    foreach ($allowedOriginsList as $allowed) {
        if (strcasecmp($parsedOriginHost, $allowed) === 0 || (!empty($httpHost) && strpos($httpHost, $allowed) !== false)) {
            $isAllowed = true;
            break;
        }
    }

    // Allow ZeroTier and private local mesh subnets dynamically
    if (!$isAllowed && $parsedOriginHost) {
        if (strncmp($parsedOriginHost, '10.', 3) === 0 ||
            strncmp($parsedOriginHost, '192.168.', 8) === 0 ||
            strncmp($parsedOriginHost, '172.', 4) === 0) {
            $isAllowed = true;
        }
    }

    if ($isAllowed) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Credentials: true");
    }
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle CORS Preflight (OPTIONS request)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Global exception handler to output sanitized JSON instead of HTML stack traces
set_exception_handler(function($exception) {
    error_log("Unhandled Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "An internal server error occurred."
    ]);
    exit;
});

// Global error handler to convert errors to exceptions
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
