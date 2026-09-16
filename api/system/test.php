<?php
session_start();
$_SESSION['test'] = 'working';
echo json_encode([
    'session_id' => session_id(),
    'save_path' => session_save_path(),
    'is_writable' => is_writable(session_save_path()),
    'ampache_config' => parse_ini_file(__DIR__ . '/../../../ampache/config/ampache.cfg.php')
]);
