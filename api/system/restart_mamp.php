<?php
header('Content-Type: application/json');

$output = [];
exec("/Applications/MAMP/bin/stopApache.sh 2>&1", $output);
sleep(2);
exec("/Applications/MAMP/bin/startApache.sh 2>&1", $output);

echo json_encode([
    'status' => 'success',
    'reloaded' => true,
    'message' => 'Apache HARD restarted.',
    'output' => $output
]);
?>
