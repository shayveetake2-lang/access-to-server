<?php
// api/system/admin_accounts.php — Complete User & Access Management API for Admins
require_once __DIR__ . "/../auth/require_admin.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

try {
    require_once __DIR__ . '/../../config/db_connect.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Read JSON input or fallback to $_POST / $_REQUEST
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = [];
}

$action = $data['action'] ?? $_REQUEST['action'] ?? 'list';
$currentAdminUser = $_SESSION['username'] ?? $_SESSION['admin_user'] ?? 'admin';

// ─── 1. LIST ALL USERS ───
if ($action === 'list') {
    try {
        $stmt = $pdo->query("SELECT id, username, role, COALESCE(storage_limit_mb, 100) AS storage_limit_mb, COALESCE(storage_used_mb, 0.0) AS storage_used_mb, created_at FROM sys_users ORDER BY id ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'status' => 'success',
            'users'  => $users,
            'admins' => array_values(array_filter($users, function($u) { return $u['role'] === 'admin'; }))
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch users: ' . $e->getMessage()]);
    }
    exit;
}

// ─── 2. UPDATE USER ROLE (Promote to Admin / Demote to User) ───
if ($action === 'update_role') {
    $userId = intval($data['id'] ?? $_POST['id'] ?? 0);
    $newRole = strtolower(trim($data['role'] ?? $_POST['role'] ?? ''));

    if ($userId <= 0 || !in_array($newRole, ['admin', 'user'], true)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID or role specified. Role must be "admin" or "user".']);
        exit;
    }

    // Fetch target user
    $stmt = $pdo->prepare("SELECT id, username, role, password_hash FROM sys_users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        exit;
    }

    // Safety: Prevent demoting the only admin
    if ($target['role'] === 'admin' && $newRole === 'user') {
        $adminCount = $pdo->query("SELECT COUNT(*) FROM sys_users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Cannot demote the only remaining administrator account.']);
            exit;
        }
    }

    // Update role in sys_users
    $upd = $pdo->prepare("UPDATE sys_users SET role = :role WHERE id = :id");
    $upd->execute([':role' => $newRole, ':id' => $userId]);

    // Sync with admin_users table for legacy compatibility
    if ($newRole === 'admin') {
        $chk = $pdo->prepare("SELECT id FROM admin_users WHERE username = :u LIMIT 1");
        $chk->execute([':u' => $target['username']]);
        if (!$chk->fetch()) {
            $ins = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)");
            $ins->execute([':u' => $target['username'], ':p' => $target['password_hash']]);
        }
    } else {
        $del = $pdo->prepare("DELETE FROM admin_users WHERE username = :u");
        $del->execute([':u' => $target['username']]);
    }

    echo json_encode([
        'status'  => 'success',
        'message' => "Successfully updated '{$target['username']}' role to " . ucfirst($newRole) . "."
    ]);
    exit;
}

// ─── 3. CHANGE USER PASSWORD ───
if ($action === 'change_password') {
    $userId = intval($data['id'] ?? $_POST['id'] ?? 0);
    $newPassword = trim($data['password'] ?? $_POST['password'] ?? '');

    if ($userId <= 0 || strlen($newPassword) < 4) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 4 characters long.']);
        exit;
    }

    // Fetch target user
    $stmt = $pdo->prepare("SELECT id, username FROM sys_users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);

    // Update in sys_users
    $upd = $pdo->prepare("UPDATE sys_users SET password_hash = :p WHERE id = :id");
    $upd->execute([':p' => $hash, ':id' => $userId]);

    // Update in admin_users if present
    $updAdmin = $pdo->prepare("UPDATE admin_users SET password_hash = :p WHERE username = :u");
    $updAdmin->execute([':p' => $hash, ':u' => $target['username']]);

    echo json_encode([
        'status'  => 'success',
        'message' => "Password for '{$target['username']}' updated successfully."
    ]);
    exit;
}

// ─── 4. ALLOCATE MORE STORAGE (Storage Quota) ───
if ($action === 'update_storage') {
    $userId = intval($data['id'] ?? $_POST['id'] ?? 0);
    $storageLimitMb = intval($data['storage_limit_mb'] ?? $_POST['storage_limit_mb'] ?? 0);

    if ($userId <= 0 || $storageLimitMb < 10) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Storage limit must be at least 10 MB.']);
        exit;
    }

    // Fetch target user
    $stmt = $pdo->prepare("SELECT id, username FROM sys_users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        exit;
    }

    $upd = $pdo->prepare("UPDATE sys_users SET storage_limit_mb = :s WHERE id = :id");
    $upd->execute([':s' => $storageLimitMb, ':id' => $userId]);

    echo json_encode([
        'status'  => 'success',
        'message' => "Storage allocated for '{$target['username']}' updated to {$storageLimitMb} MB."
    ]);
    exit;
}

// ─── 5. ADD NEW USER ───
if ($action === 'add') {
    $username = trim($data['username'] ?? $_POST['username'] ?? '');
    $password = trim($data['password'] ?? $_POST['password'] ?? '');
    $role = strtolower(trim($data['role'] ?? $_POST['role'] ?? 'user'));
    $storageLimitMb = intval($data['storage_limit_mb'] ?? $_POST['storage_limit_mb'] ?? 100);

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
        exit;
    }
    if (!in_array($role, ['admin', 'user'], true)) {
        $role = 'user';
    }
    if ($storageLimitMb < 10) {
        $storageLimitMb = 100;
    }

    // Check if exists
    $chk = $pdo->prepare("SELECT id FROM sys_users WHERE username = :u LIMIT 1");
    $chk->execute([':u' => $username]);
    if ($chk->fetch()) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Username '{$username}' already exists."]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $token = bin2hex(random_bytes(24));

    $ins = $pdo->prepare("INSERT INTO sys_users (username, password_hash, role, storage_limit_mb, auth_token) VALUES (:u, :p, :r, :s, :t)");
    $ins->execute([':u' => $username, ':p' => $hash, ':r' => $role, ':s' => $storageLimitMb, ':t' => $token]);

    if ($role === 'admin') {
        $insAdmin = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)");
        $insAdmin->execute([':u' => $username, ':p' => $hash]);
    }

    echo json_encode([
        'status'  => 'success',
        'message' => "User '{$username}' created successfully with {$storageLimitMb} MB quota."
    ]);
    exit;
}

// ─── 6. DELETE USER ───
if ($action === 'delete') {
    $userId = intval($data['id'] ?? $_POST['id'] ?? 0);

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, role FROM sys_users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        exit;
    }

    // Safety checks: Cannot delete self or primary admin 'admin'
    if (strcasecmp($target['username'], $currentAdminUser) === 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete your own active administrator account.']);
        exit;
    }
    if ($target['username'] === 'admin') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete the primary root admin account.']);
        exit;
    }

    // If deleting an admin, ensure at least one remains
    if ($target['role'] === 'admin') {
        $adminCount = $pdo->query("SELECT COUNT(*) FROM sys_users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete the only remaining administrator account.']);
            exit;
        }
    }

    $del = $pdo->prepare("DELETE FROM sys_users WHERE id = :id");
    $del->execute([':id' => $userId]);

    $delAdmin = $pdo->prepare("DELETE FROM admin_users WHERE username = :u");
    $delAdmin->execute([':u' => $target['username']]);

    echo json_encode([
        'status'  => 'success',
        'message' => "User '{$target['username']}' deleted successfully."
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
