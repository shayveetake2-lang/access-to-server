<?php
// api/system/test.php — System Environment Health Check (Admin Only)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../auth/require_admin.php';
requireAdmin();

echo json_encode([
    'status' => 'success',
    'session_active' => !empty(session_id()),
    'storage_writable' => is_writable(session_save_path()),
    'timestamp' => time()
]);
