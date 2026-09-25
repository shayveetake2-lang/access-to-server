<?php
// Prevent script timeout for long operations
set_time_limit(0);
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ini_set('implicit_flush', true);
ob_implicit_flush(true);

// Helper to stream output live to the browser
function outputLog($message, $type = 'normal') {
    $colorMap = [
        'normal'  => '#c5c5c5',
        'success' => '#4caf50',
        'warning' => '#ff9800',
        'error'   => '#f44336',
        'info'    => '#2196f3'
    ];
    $color = $colorMap[$type] ?? $colorMap['normal'];
    
    echo "<div style='color: {$color};'>[" . date('H:i:s') . "] " . htmlspecialchars($message) . "</div>\n";
    
    // Flush buffers to ensure immediate delivery
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

/**
 * Lightweight approach to read basic ID3 tags.
 * Checks for ID3v1 (last 128 bytes) first for MP3s, 
 * then falls back to parsing the filename (e.g. "Artist - Album - Title").
 */
function getMetadata($filepath) {
    $artist = '';
    $album = '';
    
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    
    // Try reading ID3v1 tag from MP3
    if ($ext === 'mp3' && is_readable($filepath) && filesize($filepath) > 128) {
        $f = fopen($filepath, 'r');
        if ($f) {
            fseek($f, -128, SEEK_END);
            $tag = fread($f, 128);
            fclose($f);
            
            if (substr($tag, 0, 3) === 'TAG') {
                $artistTag = trim(substr($tag, 33, 30));
                $albumTag  = trim(substr($tag, 63, 30));
                
                // Only use if not completely empty/null bytes
                if (!empty(trim($artistTag, "\x00..\x1F"))) {
                    $artist = $artistTag;
                }
                if (!empty(trim($albumTag, "\x00..\x1F"))) {
                    $album = $albumTag;
                }
            }
        }
    }
    
    // Fallback: Parse filename (Format assumption: Artist - Album - Title.mp3)
    if (empty($artist) || empty($album)) {
        $filename = pathinfo($filepath, PATHINFO_FILENAME);
        // Split by standard space-dash-space
        $parts = explode(' - ', $filename);
        
        if (count($parts) >= 2) {
            if (empty($artist)) $artist = trim($parts[0]);
            if (empty($album) && count($parts) >= 3) {
                 $album = trim($parts[1]); 
            }
        }
    }
    
    // Final default fallback
    if (empty($artist)) $artist = 'Unknown Artist';
    if (empty($album)) $album = 'Unknown Album';
    
    return ['artist' => $artist, 'album' => $album];
}

// Clean folder names for safe filesystem usage
function sanitizeDirName($name) {
    // Remove characters not suitable for typical filesystems, allowing alphanumeric, space, dash, and underscore
    return trim(preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $name));
}

$isApi = isset($_GET['api']) && $_GET['api'] === 'true';

if (!$isApi) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ampache Auto Organizer</title>
    <style>
        body {
            background-color: #121212;
            color: #c5c5c5;
            font-family: 'Courier New', Courier, monospace;
            padding: 20px;
            line-height: 1.6;
            margin: 0;
        }
        h2 {
            color: #ffffff;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        #log-container {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 8px;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
            min-height: 300px;
        }
    </style>
</head>
<body>
    <h2>🎵 Music Auto Organizer</h2>
    <div id="log-container">
<?php
}

// Initial flush so the header shows immediately
if (ob_get_level() > 0) ob_flush();
flush();

// Setup paths
$stagingDir = '/Volumes/Music/Staging/';
$destDirBase = '/Volumes/Music/';

// Mount Verification Failsafe
if (!is_dir($destDirBase)) {
    outputLog("CRITICAL ERROR: The base Music folder is missing. Ensure the Windows SMB network drive is mounted at {$destDirBase} on the 2011 Mac.", 'error');
    echo "    </div>\n</body>\n</html>";
    exit;
}

// Auto-Heal: Create Staging directory if missing
if (!is_dir($stagingDir)) {
    if (mkdir($stagingDir, 0777, true)) {
        outputLog("Created missing Staging directory at {$stagingDir}", 'success');
    } else {
        outputLog("Error: Failed to create Staging directory at {$stagingDir}. Check permissions.", 'error');
        echo "    </div>\n</body>\n</html>";
        exit;
    }
}

outputLog("Started scanning {$stagingDir}...", 'info');

$files = scandir($stagingDir);
$audioExtensions = ['mp3', 'flac', 'm4a'];
$movedCount = 0;

foreach ($files as $file) {
    // Skip dots
    if ($file === '.' || $file === '..') continue;
    
    $filePath = $stagingDir . $file;
    if (!is_file($filePath)) continue;
    
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $filenameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
    
    // Is it an audio file we care about?
    if (in_array($ext, $audioExtensions)) {
        outputLog("Processing: {$file}");
        
        $meta = getMetadata($filePath);
        $cleanArtist = sanitizeDirName($meta['artist']);
        $cleanAlbum = sanitizeDirName($meta['album']);
        
        // Ensure not totally empty after sanitation
        if (empty($cleanArtist)) $cleanArtist = 'Unknown Artist';
        if (empty($cleanAlbum)) $cleanAlbum = 'Unknown Album';
        
        $destFolder = $destDirBase . $cleanArtist . '/' . $cleanAlbum . '/';
        
        // Create destination directory if it doesn't exist
        if (!is_dir($destFolder)) {
            if (!mkdir($destFolder, 0777, true)) {
                outputLog("Failed to create directory: {$destFolder}", 'error');
                continue;
            }
        }
        
        $destFilePath = $destFolder . $file;
        
        // Move the audio file
        if (rename($filePath, $destFilePath)) {
            outputLog("Moved: {$file} -> {$cleanArtist}/{$cleanAlbum}/", 'success');
            $movedCount++;
            
            // Look for matching image files (e.g. same_name.jpg)
            $imageExtensions = ['jpg', 'jpeg'];
            foreach ($imageExtensions as $imgExt) {
                $imgFile = $stagingDir . $filenameWithoutExt . '.' . $imgExt;
                if (file_exists($imgFile)) {
                    $destImgPath = $destFolder . 'cover.jpg'; // Always rename to cover.jpg in destination
                    if (rename($imgFile, $destImgPath)) {
                        outputLog("Moved matching image -> cover.jpg", 'success');
                    } else {
                        outputLog("Failed to move image: " . basename($imgFile), 'error');
                    }
                    break; // Only move one matching image, then break
                }
            }
        } else {
            outputLog("Failed to move: {$file}", 'error');
        }
    }
}

outputLog("Finished organizing files. Total moved: {$movedCount}", 'info');
outputLog("Triggering Ampache catalog update...", 'info');

// Run the Ampache CLI update script and pipe output back to browser
$command = "php /Applications/MAMP/htdocs/ampache/bin/cli.inc -c update 2>&1";
$handle = popen($command, 'r');

if ($handle) {
    while (!feof($handle)) {
        $line = fgets($handle);
        if ($line !== false) {
             outputLog(trim($line));
        }
    }
    pclose($handle);
    outputLog("Ampache catalog update complete.", 'success');
} else {
    outputLog("Failed to execute Ampache CLI update command.", 'error');
}

if (!$isApi) {
?>
    </div>
    <script>
        // Auto-scroll to the bottom of the log as new output streams in
        const container = document.getElementById('log-container');
        const observer = new MutationObserver(() => {
            window.scrollTo(0, document.body.scrollHeight);
        });
        observer.observe(container, { childList: true, subtree: true });
    </script>
</body>
</html>
<?php
}

