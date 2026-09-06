<?php
header('Content-Type: application/json');

// Ensure error reporting is off for clean JSON output
error_reporting(0);

$projectName = isset($_POST['project_name']) ? preg_replace('/[^a-zA-Z0-9-_]/', '', $_POST['project_name']) : '';
$deployMethod = isset($_POST['deploy_method']) ? $_POST['deploy_method'] : '';

if (empty($projectName)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid project name.']);
    exit;
}

// Define the target directory — /sites/ is inside the document root and visible to Hosted Sites page
$targetBaseDir = realpath(__DIR__ . '/../') . '/sites';

// Create the sites directory if it doesn't exist
if (!is_dir($targetBaseDir)) {
    if (!mkdir($targetBaseDir, 0755, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create sites base directory. Check permissions.']);
        exit;
    }
}

$targetDir = $targetBaseDir . '/' . $projectName;

if (is_dir($targetDir)) {
    echo json_encode(['status' => 'error', 'message' => 'Project with that name already exists. Please choose a different name.']);
    exit;
}

if ($deployMethod === 'github') {
    $githubUrl = isset($_POST['github_url']) ? trim($_POST['github_url']) : '';
    
    // Validate basic github URL
    if (empty($githubUrl) || !filter_var($githubUrl, FILTER_VALIDATE_URL) || strpos($githubUrl, 'github.com') === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid GitHub URL.']);
        exit;
    }

    // Escape shell arguments
    $escapedUrl = escapeshellarg($githubUrl);
    $escapedTarget = escapeshellarg($targetDir);
    
    // Execute git clone
    $output = shell_exec("git clone $escapedUrl $escapedTarget 2>&1");
    
    if (is_dir($targetDir)) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Repository cloned successfully!',
            'url' => 'sites/' . $projectName . '/'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to clone repository. Make sure it is public. Git output: ' . $output]);
    }
    exit;

} else if ($deployMethod === 'zip') {
    
    if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = isset($_FILES['zip_file']['error']) ? $_FILES['zip_file']['error'] : 'Unknown error';
        echo json_encode(['status' => 'error', 'message' => 'File upload failed. Error code: ' . $uploadError . '. Check php.ini upload_max_filesize if the file is large.']);
        exit;
    }
    
    $fileTmpPath = $_FILES['zip_file']['tmp_name'];
    $fileExtension = strtolower(pathinfo($_FILES['zip_file']['name'], PATHINFO_EXTENSION));
    
    if ($fileExtension !== 'zip') {
        echo json_encode(['status' => 'error', 'message' => 'Only .zip files are allowed.']);
        exit;
    }
    
    if (!mkdir($targetDir, 0755, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create project directory.']);
        exit;
    }
    
    $zip = new ZipArchive;
    if ($zip->open($fileTmpPath) === TRUE) {
        $zip->extractTo($targetDir);
        $zip->close();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Files extracted successfully!',
            'url' => 'sites/' . $projectName . '/'
        ]);
    } else {
        // Clean up the created directory on failure
        rmdir($targetDir);
        echo json_encode(['status' => 'error', 'message' => 'Failed to unzip the file.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid deployment method.']);
