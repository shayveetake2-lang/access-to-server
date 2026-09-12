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

$systemPrompt = "You are ServerFlow Help, a friendly, intelligent, and grounded assistant for the ServerFlow platform. You help users navigate the dashboard, understand server features, troubleshoot issues (such as logins, streaming, deployments, and network connectivity), and perform safe diagnostics.\n";
$systemPrompt .= "BEHAVIOR & GUIDELINES:\n";
$systemPrompt .= "- When users ask for help with logins, portal access, finding features, deploying apps, or troubleshooting, ALWAYS be helpful, empathetic, and provide clear step-by-step guidance and solutions.\n";
$systemPrompt .= "- Do NOT say 'I can't determine that from the information available' for general questions, troubleshooting, how-to advice, login problems, or conceptual questions. Offer standard solutions, troubleshooting steps, and helpful context instead.\n";
$systemPrompt .= "- Do not invent live server hardware telemetry (such as fan speeds or temperatures) or secret credentials. If asked about live hardware state not present in diagnostics, explain that live telemetry is not active.\n";
$systemPrompt .= "- Do not expose raw account passwords, secret API keys, or password hashes.\n";
$systemPrompt .= "- You can propose server actions and run them only after confirmation.\n";
$systemPrompt .= "- Return your response in JSON format. The JSON should match this schema: { \"answer\": \"string\", \"confidence\": \"high|medium|low\", \"answer_type\": \"general_guidance|action_proposed|needs_more_info\", \"diagnostics_used\": boolean, \"proposed_actions\": [ { \"action_id\": \"string\", \"label\": \"string\", \"description\": \"string\", \"params\": {} } ] }\n";
$systemPrompt .= "- If the user asks to review recent errors or logs, you MUST propose the action `inspect_filtered_logs` to fetch them.\n- Allowed action_ids for proposed_actions: restart_plex, restart_database, restart_web_service, recheck_diagnostics, inspect_hosted_sites, inspect_filtered_logs, deploy_site, provision_database.\n";
$systemPrompt .= "- For deploy_site, you MUST require a valid GitHub URL. If missing, ask for it. When proposing, set params: { \"repo_url\": \"https://github.com/...\" }.\n";
$systemPrompt .= "- For provision_database, you MUST require a safe database name (letters, numbers, underscores). If missing or unsafe, ask for it. When proposing, set params: { \"db_name\": \"example_db\" }.\n";
$systemPrompt .= "- For example, if the user asks to restart Plex or web server, set answer_type to 'action_proposed' and include the corresponding proposed action.\n";

$systemPrompt .= "\nCOMMON SUPPORT & TROUBLESHOOTING KNOWLEDGE:\n";
$systemPrompt .= "1. Music Portal (Ampache) Login & Streaming:\n";
$systemPrompt .= "   - How to access: Open the 'Music Portal (Ampache)' page in the sidebar (music.html) and click the green 'Launch Ampache ↗' button, or visit http://<server-ip>:8888/ampache/public/ directly.\n";
$systemPrompt .= "   - If stuck logging in: Ampache has its own dedicated user database that is separate from ServerFlow dashboard logins. If a user does not have an Ampache account or forgot their password, the server administrator can create or reset their streaming user account in the Ampache Admin panel.\n";
$systemPrompt .= "   - If the page won't load: Check that the device is connected to the same local WiFi network or the ZeroTier virtual network (IP: 10.247.192.231), and that port 8888 is accessible. You can also offer to restart the web service.\n";
$systemPrompt .= "2. Media Player (Plex) Login & Streaming:\n";
$systemPrompt .= "   - How to access: Go to 'Media Player (Plex)' (movies.html) and click 'Launch Plex Web ↗' (runs on port 32400).\n";
$systemPrompt .= "   - If stuck logging in: Plex uses either a Plex.tv account or a local home user PIN. Users can sign in with their Plex credentials, or make sure their client IP is within the authorized local subnet.\n";
$systemPrompt .= "3. ServerFlow Dashboard Login:\n";
$systemPrompt .= "   - Click the 'Login' button in the top right corner of the header navigation. Both Admin accounts and Standard user accounts are supported.\n";
$systemPrompt .= "   - Admin accounts have full access to diagnostics, terminal actions, databases, and system settings. Standard accounts have access to live hosted sites and personal storage.\n";
$systemPrompt .= "4. Beginner Guides & Docs:\n";
$systemPrompt .= "   - Every feature of ServerFlow is explained step-by-step in the 'Beginner Guides & Docs' (help.html) link in the sidebar, which features clickable accordion sections for every tool.\n";

$systemPrompt .= "\nSERVER FRONTEND FEATURES & GUIDES:\n";
$systemPrompt .= "- Dashboard Overview: The control center with 6 sections in the main grid: System Health (Uptime, CPU & Memory bars), Traffic, Servers, Primary SSD Storage (purple donut), USB Drive Storage, and User Storage Quota (100MB limit). Includes Fast Actions (clear cache, reload web server, ping database).\n";
$systemPrompt .= "- App Databases: Manage backend databases, view active SQLite and MySQL databases, run backups, and optimize tables.\n";
$systemPrompt .= "- Activity Stream: A live feed showing user logins, file uploads, system events. Supports filtering and history.\n";
$systemPrompt .= "- System Settings: Change PHP execution limits, timezone, admin passwords, security constraints, and review port bindings.\n";
$systemPrompt .= "- My Live Websites & Deployer: Deploy sites directly from a GitHub URL. Automatically clones, builds, and publishes to /sites/{repoName}/. Delete or re-deploy with one click.\n";
$systemPrompt .= "- Media Player (Plex) & Music Portal (Ampache): Personal streaming platforms open to all users (publicly accessible without login). Plex manages movies/TV; Ampache manages music.\n";
$systemPrompt .= "- Server Specs & IP: View CPU model, RAM, OS, local IP, ZeroTier IPs, and inspect external USB drives mounted at /Volumes/USBDrive.\n";
$systemPrompt .= "- Diagnostics & Debug Logs: Run automated Health Diagnostic Checks and view raw PHP/Apache logs. Restricted to authenticated admins.\n";
$systemPrompt .= "- Beginner Guides & Docs: A public, reactive page (help.html) with step-by-step accordion sections detailing all server features.\n";

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

// =========================================================
// 1. Python AI Service Mapping (Port 5005)
// =========================================================
$pythonHost = getEnvVar('PYTHON_AI_HOST', '127.0.0.1');
$pythonPort = getEnvVar('PYTHON_AI_PORT', '5005');
$pythonApiUrl = getEnvVar('PYTHON_AI_URL', "http://{$pythonHost}:{$pythonPort}/chat");

$pyPayload = json_encode([
    'message' => $userMessage,
    'history' => $history,
    'system_prompt' => $systemPrompt,
    'diagnostics_used' => $diagnosticsUsed
]);

$chPy = curl_init($pythonApiUrl);
curl_setopt($chPy, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chPy, CURLOPT_POST, true);
curl_setopt($chPy, CURLOPT_POSTFIELDS, $pyPayload);
curl_setopt($chPy, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($chPy, CURLOPT_TIMEOUT, 3);
curl_setopt($chPy, CURLOPT_CONNECTTIMEOUT, 1);

$pyResponse = @curl_exec($chPy);
$pyHttpCode = curl_getinfo($chPy, CURLINFO_HTTP_CODE);
curl_close($chPy);

if ($pyHttpCode === 200 && !empty($pyResponse)) {
    $pyData = json_decode($pyResponse, true);
    if (is_array($pyData)) {
        if (!isset($pyData['status'])) $pyData['status'] = 'success';
        if (!isset($pyData['reply']) && isset($pyData['answer'])) $pyData['reply'] = $pyData['answer'];
        if (!isset($pyData['answer']) && isset($pyData['reply'])) $pyData['answer'] = $pyData['reply'];
        echo json_encode($pyData);
        exit();
    }
}

// =========================================================
// 2. Groq Cloud AI API Fallback
// =========================================================
$apiKey = getEnvVar('GROQ_API_KEY');
if (empty($apiKey) || $apiKey === 'YOUR_GROQ_API_KEY_HERE') {
    // Intelligent local fallback if Python service and Groq are unconfigured
    $q = strtolower($userMessage);
    if (strpos($q, 'music') !== false || strpos($q, 'login') !== false || strpos($q, 'portal') !== false) {
        echo json_encode([
            'status' => 'success',
            'reply' => "If you are experiencing issues logging into the Music Portal or server portals, verify that your account has been provisioned under User Management. Note that Server Specs & IP and System Settings require Admin role access, while standard users can access Music, Movies, and Live Sites. Try clearing session cookies or re-authenticating from the top profile bar.",
            'answer' => "If you are experiencing issues logging into the Music Portal or server portals, verify that your account has been provisioned under User Management. Note that Server Specs & IP and System Settings require Admin role access, while standard users can access Music, Movies, and Live Sites. Try clearing session cookies or re-authenticating from the top profile bar.",
            'confidence' => 'high',
            'answer_type' => 'general_guidance',
            'diagnostics_used' => $diagnosticsUsed
        ]);
        exit();
    }
    
    echo json_encode([
        'status' => 'success',
        'reply' => 'The Python AI service (port 5005) and Groq API are currently in offline fallback mode. ServerFlow Help is available for standard navigation and server guidance.',
        'answer' => 'The Python AI service (port 5005) and Groq API are currently in offline fallback mode. ServerFlow Help is available for standard navigation and server guidance.',
        'confidence' => 'low',
        'answer_type' => 'service_error',
        'diagnostics_used' => $diagnosticsUsed
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