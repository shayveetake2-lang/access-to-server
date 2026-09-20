<?php
/**
 * Test Suite: Daily Top 100 Songs Chart & Similar Artist Deduplication
 */

define('UNIT_TESTING', true);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer dummy_token';
$data = ['u' => 'musicadmin'];
require_once __DIR__ . '/../api/merge_metadata.php';

function runTest($name, $callable) {
    try {
        $callable();
        echo "• [TEST] {$name}... PASS ✓\n";
    } catch (\Throwable $e) {
        echo "• [TEST] {$name}... FAILED ✗ ({$e->getMessage()})\n";
        exit(1);
    }
}

echo "=== Testing Daily Top 100 & Similar Artist Engine ===\n\n";

// 1. Test artist normalization & feature extraction
runTest("Normalizes 'The Weeknd' and 'Weeknd' to identical canonical key", function() {
    $k1 = normalizeArtistKeyForFuzzy("The Weeknd");
    $k2 = normalizeArtistKeyForFuzzy("Weeknd");
    if ($k1 !== $k2 || $k1 !== 'weeknd') {
        throw new Exception("Expected 'weeknd', got '{$k1}' and '{$k2}'");
    }
});

runTest("Extracts primary artist from inline 'feat.' and parenthetical '(ft. ...)'", function() {
    $k1 = normalizeArtistKeyForFuzzy("Daft Punk feat. Pharrell Williams");
    $k2 = normalizeArtistKeyForFuzzy("Daft Punk (ft. Julian Casablancas)");
    $k3 = normalizeArtistKeyForFuzzy("Daft Punk");
    if ($k1 !== 'daftpunk' || $k2 !== 'daftpunk' || $k3 !== 'daftpunk') {
        throw new Exception("Expected all to normalize to 'daftpunk'");
    }
});

runTest("Strips diacritics and accents (Beyoncé -> beyonce, Motörhead -> motorhead)", function() {
    $k1 = normalizeArtistKeyForFuzzy("Beyoncé");
    $k2 = normalizeArtistKeyForFuzzy("Beyonce");
    $k3 = normalizeArtistKeyForFuzzy("Motörhead");
    $k4 = normalizeArtistKeyForFuzzy("Motorhead");
    if ($k1 !== $k2 || $k1 !== 'beyonce') {
        throw new Exception("Expected 'beyonce', got '{$k1}' and '{$k2}'");
    }
    if ($k3 !== $k4 || $k3 !== 'motorhead') {
        throw new Exception("Expected 'motorhead', got '{$k3}' and '{$k4}'");
    }
});

runTest("Ignores punctuation and spacing (AC/DC vs ACDC, Jay-Z vs Jay Z)", function() {
    $k1 = normalizeArtistKeyForFuzzy("AC/DC");
    $k2 = normalizeArtistKeyForFuzzy("ACDC");
    $k3 = normalizeArtistKeyForFuzzy("Jay-Z");
    $k4 = normalizeArtistKeyForFuzzy("Jay Z");
    if ($k1 !== $k2 || $k1 !== 'acdc') {
        throw new Exception("Expected 'acdc', got '{$k1}' and '{$k2}'");
    }
    if ($k3 !== $k4 || $k3 !== 'jayz') {
        throw new Exception("Expected 'jayz', got '{$k3}' and '{$k4}'");
    }
});

runTest("Detects correct similarity reasons", function() {
    $r1 = detectSimilarityReason("The Beatles", "Beatles", "beatles", "beatles");
    if (strpos($r1, "Leading article 'The'") === false) {
        throw new Exception("Expected 'The' reason, got: $r1");
    }

    $r2 = detectSimilarityReason("Drake", "Drake ft. Future", "drake", "drake");
    if (strpos($r2, "Featured collaborator") === false) {
        throw new Exception("Expected featured reason, got: $r2");
    }

    $r3 = detectSimilarityReason("Beyonce", "Beyoncé", "beyonce", "beyonce");
    if (strpos($r3, "Diacritics or accent") === false) {
        throw new Exception("Expected diacritics reason, got: $r3");
    }
});

runTest("api_proxy.php syntax and structure check", function() {
    $output = shell_exec("php -l " . escapeshellarg(__DIR__ . '/../modern-music-app/api_proxy.php'));
    if (strpos($output, "No syntax errors detected") === false) {
        throw new Exception("Syntax error in api_proxy.php: $output");
    }
});

runTest("api/merge_metadata.php syntax check", function() {
    $output = shell_exec("php -l " . escapeshellarg(__DIR__ . '/../api/merge_metadata.php'));
    if (strpos($output, "No syntax errors detected") === false) {
        throw new Exception("Syntax error in api/merge_metadata.php: $output");
    }
});

echo "\n======================================\n";
echo "All Engine Tests Passed Successfully!\n";
echo "======================================\n";
