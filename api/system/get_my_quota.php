<?php
// api/system/get_my_quota.php — returns the CURRENTLY authenticated user's own
// storage quota (not the admin-only bulk refresh in refresh_quotas.php).
require_once __DIR__ . '/../auth/require_auth.php';
requireAuth();

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(storage_limit_mb, 100) AS storage_limit_mb, COALESCE(storage_used_mb, 0.0) AS storage_used_mb
         FROM sys_users WHERE id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        exit;
    }

    $limitMb = (float)$row['storage_limit_mb'];
    $usedMb = (float)$row['storage_used_mb'];
    $percent = $limitMb > 0 ? round(min(100, ($usedMb / $limitMb) * 100), 1) : 0;

    echo json_encode([
        'status' => 'success',
        'storage_limit_mb' => $limitMb,
        'storage_used_mb' => $usedMb,
        'storage_free_mb' => max(0, $limitMb - $usedMb),
        'percent_used' => $percent,
        'within_limit' => $usedMb <= $limitMb,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
