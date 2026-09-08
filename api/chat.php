<?php
// /api/chat.php — Server Assistant Chat Endpoint using Groq API

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Use POST."]);
    exit();
}

function getEnvVar($key, $default = null) {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \"'");
                if ($name === $key) {
                    return $value;
                }
            }
        }
    }
    return $default;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);
$userMessage = isset($input['message']) ? trim($input['message']) : '';

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Message cannot be empty."]);
    exit();
}

$apiKey = getEnvVar('GROQ_API_KEY');
if (empty($apiKey) || $apiKey === 'YOUR_GROQ_API_KEY_HERE') {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Groq API Key is not configured in .env file."]);
    exit();
}

$systemPrompt = "You are 'ServerHelperBot', an assistant for a MacBook Pro home server hosted at local IP 10.247.192.231. The server runs Plex (tunneled securely through ZeroTier), a main web front-end hosting websites, and custom databases for friends. If users ask general questions or need guidance, explain how to access these services concisely and warmly. If a user is completely stuck, let them know they can ask for automated fixes or guidance.";

// Models to try in order of preference
$models = ["openai/gpt-oss-20b", "groq/compound-mini", "openai/gpt-oss-120b", "qwen/qwen3.6-27b"];
$reply = null;
$lastError = null;

foreach ($models as $model) {
    $payload = json_encode([
        "model" => $model,
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $userMessage]
        ],
        "temperature" => 0.7,
        "max_tokens" => 1024
    ]);

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        $lastError = "cURL Error: " . $curlErr;
        continue;
    }

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            $reply = $data['choices'][0]['message']['content'];
            break;
        }
    } else {
        $errData = json_decode($response, true);
        $errMsg = isset($errData['error']['message']) ? $errData['error']['message'] : "HTTP Code $httpCode";
        $lastError = "Groq API Error ($model): " . $errMsg;
    }
}

if ($reply !== null) {
    echo json_encode([
        "status" => "success",
        "reply" => $reply
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $lastError ?: "Failed to receive response from Groq API."
    ]);
}
?>
