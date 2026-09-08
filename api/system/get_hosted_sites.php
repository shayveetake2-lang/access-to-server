<?php
// api/system/get_hosted_sites.php — Public endpoint to list live hosted websites
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sitesDir = realpath(__DIR__ . '/../../sites');
if (!$sitesDir && is_dir(__DIR__ . '/../../sites')) {
    $sitesDir = __DIR__ . '/../../sites';
}

$sites = [];

if ($sitesDir && is_dir($sitesDir)) {
    $items = scandir($sitesDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || strpos($item, '.') === 0) continue;

        $fullPath = $sitesDir . '/' . $item;
        if (!is_dir($fullPath)) continue;

        $hasIndex = file_exists($fullPath . '/index.html')
                 || file_exists($fullPath . '/index.php')
                 || file_exists($fullPath . '/index.htm');

        $title = null;
        foreach (['index.html', 'index.htm', 'index.php'] as $idx) {
            $idxPath = $fullPath . '/' . $idx;
            if (file_exists($idxPath)) {
                $content = @file_get_contents($idxPath, false, null, 0, 2000);
                if ($content && preg_match('/<title[^>]*>(.+?)<\/title>/si', $content, $m)) {
                    $title = trim(strip_tags($m[1]));
                }
                break;
            }
        }

        $sites[] = [
            'name'      => $item,
            'title'     => $title ?: 'Web Application',
            'has_index' => $hasIndex,
            'modified'  => filemtime($fullPath),
            'url'       => 'sites/' . rawurlencode($item) . '/',
            'date_str'  => date('M j, Y H:i', filemtime($fullPath))
        ];
    }
    usort($sites, function($a, $b) { return $b['modified'] - $a['modified']; });
}

echo json_encode([
    'status' => 'success',
    'count'  => count($sites),
    'sites'  => $sites
]);
