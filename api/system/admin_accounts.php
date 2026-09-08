<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/db_connect.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    $stmt = $pdo->query("SELECT id, username, created_at FROM admin_users ORDER BY id ASC");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'admins' => $admins]);
    exit;
}

if ($action === 'add') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: [];
    
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)");
        $stmt->execute([':u' => $username, ':p' => $hash]);
        echo json_encode(['status' => 'success', 'message' => 'Admin added successfully.']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Username already exists.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
    }
    exit;
}

if ($action === 'delete') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: [];
    
    $id = intval($data['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }

    // Prevent deleting self
    $stmt = $pdo->prepare("SELECT username FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetchColumn();

    if ($user === $_SESSION['admin_user']) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete your own account.']);
        exit;
    }

    // Prevent deleting the main 'admin' if desired, but let's allow it if there are others. 
    // Actually, maybe prevent deleting the very first admin just in case.
    if ($id === 1 || $user === 'admin') {
        // Just make sure at least one admin remains.
        $count = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
        if ($count <= 1) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete the only admin.']);
            exit;
        }
    }

    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    echo json_encode(['status' => 'success', 'message' => 'Admin deleted successfully.']);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
