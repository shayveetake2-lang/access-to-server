<?php
// /api/config/init.php
// Enforce strict JSON output for all API endpoints to prevent iOS JSON parsing crashes
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle CORS Preflight (OPTIONS request)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Global exception handler to output JSON instead of HTML stack traces
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server Exception: " . $exception->getMessage()
    ]);
    exit;
});

// Global error handler to convert errors to exceptions for JSON output
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
?>

