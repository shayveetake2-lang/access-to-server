<?php
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if ($input['action'] === 'register') {
    // Admin credentials for backend registration
    $admin_u = 'admin';
    $admin_p = '18e499b984c75ad09e233f6d8fe0228d';
    
    $newUser = urlencode($input['username']);
    $newPass = urlencode($input['password']);
    $email = urlencode($input['username'] . "@local.host"); // dummy email
    
    $url = "http://127.0.0.1:8888/ampache/public/rest/index.php?action=createUser&username={$newUser}&password={$newPass}&email={$email}&u={$admin_u}&p={$admin_p}&v=1.16.1&c=test&f=json";
    
    $response = file_get_contents($url);
    echo $response;
}
