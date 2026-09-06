<?php
// usb_manager.php — USB Drive Management Backend
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$usbMountPath = '/Volumes/USBDrive';

/**
 * Format bytes to human-readable format
 */
function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Retrieve storage capacity and status of the USB drive
 */
function getUsbStorageStatus($mountPath) {
    // Check if the USB drive directory exists and is accessible
    if (!is_dir($mountPath)) {
        return [
            'status'         => 'warning',
            'connected'      => false,
            'is_full'        => false,
            'message'        => 'USB Drive is currently unplugged or unmounted at ' . $mountPath,
            'mount_path'     => $mountPath,
            'total_bytes'    => 0,
            'free_bytes'     => 0,
            'used_bytes'     => 0,
            'percent_used'   => 0,
            'total_formatted'=> '0 B',
            'free_formatted' => '0 B',
            'used_formatted' => '0 B',
        ];
    }

    $freeBytes  = @disk_free_space($mountPath);
    $totalBytes = @disk_total_space($mountPath);

    if ($freeBytes === false || $totalBytes === false || $totalBytes <= 0) {
        return [
            'status'         => 'error',
            'connected'      => true,
            'is_full'        => false,
            'message'        => 'Unable to read disk capacity from ' . $mountPath,
            'mount_path'     => $mountPath,
            'total_bytes'    => 0,
            'free_bytes'     => 0,
            'used_bytes'     => 0,
            'percent_used'   => 0,
            'total_formatted'=> 'Unknown',
            'free_formatted' => 'Unknown',
            'used_formatted' => 'Unknown',
        ];
    }

    $usedBytes   = $totalBytes - $freeBytes;
    $percentUsed = round(($usedBytes / $totalBytes) * 100, 1);
    // Consider drive full if less than 5MB free
    $isFull      = ($freeBytes < (5 * 1024 * 1024));

    return [
        'status'         => $isFull ? 'warning' : 'success',
        'connected'      => true,
        'is_full'        => $isFull,
        'message'        => $isFull ? 'Warning: USB Drive is full!' : 'USB Drive connected and ready.',
        'mount_path'     => $mountPath,
        'total_bytes'    => $totalBytes,
        'free_bytes'     => $freeBytes,
        'used_bytes'     => $usedBytes,
        'percent_used'   => $percentUsed,
        'total_formatted'=> formatBytes($totalBytes),
        'free_formatted' => formatBytes($freeBytes),
        'used_formatted' => formatBytes($usedBytes),
    ];
}

$requestMethod = $_SERVER['REQUEST_METHOD'];

// 1. GET: Return USB Storage Capacity and Connection Status
if ($requestMethod === 'GET') {
    $status = getUsbStorageStatus($usbMountPath);
    echo json_encode($status);
    exit;
}

// 2. POST: Handle File Upload to USB Drive
if ($requestMethod === 'POST') {
    $storage = getUsbStorageStatus($usbMountPath);

    // Verify drive is connected
    if (!$storage['connected']) {
        http_response_code(400);
        echo json_encode([
            'status'    => 'error',
            'connected' => false,
            'message'   => 'Upload failed: USB Drive is currently unplugged or not mounted.',
            'storage'   => $storage
        ]);
        exit;
    }

    // Verify drive is not full
    if ($storage['is_full']) {
        http_response_code(400);
        echo json_encode([
            'status'    => 'error',
            'connected' => true,
            'message'   => 'Upload failed: USB Drive is completely full. Free up space before uploading.',
            'storage'   => $storage
        ]);
        exit;
    }

    // Check if file was provided
    $fileKey = isset($_FILES['usb_file']) ? 'usb_file' : (isset($_FILES['file']) ? 'file' : null);
    if (!$fileKey || !isset($_FILES[$fileKey])) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => 'No file received in upload request.',
            'storage' => $storage
        ]);
        exit;
    }

    $file = $_FILES[$fileKey];

    // Check upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'File upload error.';
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'The uploaded file exceeds the server maximum upload limit.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'The file was only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = 'No file was selected for upload.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = 'Missing temporary folder on server.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = 'Failed to write file to disk.';
                break;
            default:
                $errorMessage = 'Upload error code: ' . $file['error'];
        }
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => $errorMessage,
            'storage' => $storage
        ]);
        exit;
    }

    // Check if remaining disk space is sufficient for the uploaded file
    if ($file['size'] > $storage['free_bytes']) {
        http_response_code(400);
        echo json_encode([
            'status'    => 'error',
            'connected' => true,
            'message'   => 'Upload failed: File size (' . formatBytes($file['size']) . ') exceeds remaining USB drive capacity (' . $storage['free_formatted'] . ').',
            'storage'   => $storage
        ]);
        exit;
    }

    // Securely sanitize filename
    $rawName = basename($file['name']);
    // Strip control chars and dangerous traversal elements
    $cleanName = preg_replace('/[^\w\s\d\.\-_]/i', '', $rawName);
    $cleanName = trim($cleanName);
    if (empty($cleanName) || $cleanName === '.') {
        $cleanName = 'upload_' . date('Ymd_His');
    }

    // Ensure target path is inside /Volumes/USBDrive/
    $targetFilePath = rtrim($usbMountPath, '/') . '/' . $cleanName;

    // Handle collision by appending timestamp if file already exists
    if (file_exists($targetFilePath)) {
        $nameOnly  = pathinfo($cleanName, PATHINFO_FILENAME);
        $extension = pathinfo($cleanName, PATHINFO_EXTENSION);
        $extSuffix = $extension ? ('.' . $extension) : '';
        $cleanName = $nameOnly . '_' . time() . $extSuffix;
        $targetFilePath = rtrim($usbMountPath, '/') . '/' . $cleanName;
    }

    // Move uploaded file to USB destination
    if (@move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        @chmod($targetFilePath, 0666);
        $updatedStorage = getUsbStorageStatus($usbMountPath);
        echo json_encode([
            'status'       => 'success',
            'message'      => "File '{$cleanName}' uploaded successfully to USB Drive!",
            'filename'     => $cleanName,
            'size'         => formatBytes($file['size']),
            'storage'      => $updatedStorage
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Failed to save file to USB drive. Check write permissions on /Volumes/USBDrive/.',
            'storage' => $storage
        ]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use GET or POST.']);
