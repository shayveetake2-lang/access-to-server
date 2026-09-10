<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit();
}

function getEnvVar($key, $default = null) {
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                if (trim($name) === $key) return trim($value, " \"'");
            }
        }
    }
    return $default;
}


// Rate limiting
if (!isset($_SESSION['last_chat_request'])) {
    $_SESSION['last_chat_request'] = 0;
}
if (time() - $_SESSION['last_chat_request'] < 2) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait a moment.']);
    exit();
}
$_SESSION['last_chat_request'] = time();

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);
$userMessage = isset($input['message']) ? trim($input['message']) : '';
$history = isset($input['history']) && is_array($input['history']) ? $input['history'] : [];

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty.']);
    exit();
}
if (strlen($userMessage) > 2000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Message is too long. Please keep it under 2000 characters.']);
    exit();
}

$systemPrompt = "You are ServerFlow Help, a grounded server diagnostic assistant. You provide safe server context and diagnose common problems from read-only checks.\n";
$systemPrompt .= "CRITICAL RULES:\n";
$systemPrompt .= "- Separate observed facts from general advice.\n";
$systemPrompt .= "- If you cannot determine something from available information, explicitly say 'I can't determine that from the information available' instead of guessing.\n";
$systemPrompt .= "- Do not invent live server state, CPU temperatures, logs, credentials, or file contents.\n";
$systemPrompt .= "- Do not expose raw account data, API keys, raw DB rows, or hashes.\n";
$systemPrompt .= "- You can propose fixes and run them only after confirmation.\n";
$systemPrompt .= "- Return your response in JSON format. The JSON should match this schema: { \"answer\": \"string\", \"confidence\": \"high|medium|low\", \"answer_type\": \"general_guidance|action_proposed|needs_more_info\", \"diagnostics_used\": boolean, \"proposed_actions\": [ { \"action_id\": \"string\", \"label\": \"string\", \"description\": \"string\", \"params\": {} } ] }\n";
$systemPrompt .= "- If the user asks to review recent errors or logs, you MUST propose the action `inspect_filtered_logs` to fetch them.\n- Allowed action_ids for proposed_actions: restart_plex, restart_database, restart_web_service, recheck_diagnostics, inspect_hosted_sites, inspect_filtered_logs, deploy_site, provision_database.\n";
$systemPrompt .= "- For deploy_site, you MUST require a valid GitHub URL. If missing, ask for it. When proposing, set params: { \"repo_url\": \"https://github.com/...\" }.\n";
$systemPrompt .= "- For provision_database, you MUST require a safe database name (letters, numbers, underscores). If missing or unsafe, ask for it. When proposing, set params: { \"db_name\": \"example_db\" }.\n";
$systemPrompt .= "- For example, if the user asks to restart Plex, set answer_type to 'action_proposed' and include a proposed action with action_id 'restart_plex'.\n";

$diagnosticsUsed = false;
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    require_once __DIR__ . '/../config/db_connect.php';
    require_once __DIR__ . '/helpers/DiagnosticHelper.php';
    try {
        global $pdo;
        if (!$pdo) $pdo = getDBConnection();
        $helper = new DiagnosticHelper($pdo);
        $diag = json_encode($helper->getDiagnostics());
        $systemPrompt .= "\nCURRENT SERVER DIAGNOSTICS (OBSERVED FACTS):\n" . $diag;
        $diagnosticsUsed = true;
    } catch (\Exception $e) {}
} else {
    $systemPrompt .= "\nUSER IS NOT LOGGED IN. No live diagnostics available.";
}

$apiKey = getEnvVar('GROQ_API_KEY');
if (empty($apiKey) || $apiKey === 'YOUR_GROQ_API_KEY_HERE') {
    echo json_encode([
        'status' => 'success',
        'reply' => 'I cannot reach the AI service because the Groq API key is not configured. I am operating in fallback mode.',
        'answer' => 'I cannot reach the AI service because the Groq API key is not configured.',
        'confidence' => 'low',
        'answer_type' => 'service_error'
    ]);
    exit();
}

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($history as $turn) {
    if (isset($turn['role']) && isset($turn['content'])) {
        $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

$payload = json_encode([
    'model' => 'openai/gpt-oss-20b',
    'messages' => $messages,
    'temperature' => 0.1,
    'max_tokens' => 2000,
    'response_format' => ['type' => 'json_object']
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) { error_log('Groq API Error [' . $httpCode . ']: ' . $curlError . ' - ' . substr($response, 0, 300)); }


if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['choices'][0]['message']['content'])) {
        $content = trim($data['choices'][0]['message']['content']);
        $parsed = json_decode($content, true);
        if (is_array($parsed)) {
            $parsed['status'] = 'success';
            $parsed['reply'] = $parsed['answer'] ?? '';
            echo json_encode($parsed);
            exit();
        }
    }
}

echo json_encode([
    'status' => 'error',
    'message' => 'API Error [' . $httpCode . ']: ' . $curlError . ' - ' . substr($response, 0, 200) . ((!isset($parsed) || $parsed === null) ? ' (JSON Parse failed)' : ''),
    'reply' => 'I cannot reach the AI service right now.',
    'answer' => 'I cannot reach the AI service right now.',
    'answer_type' => 'service_error',
    'confidence' => 'low'
]);