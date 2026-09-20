<?php
/**
 * Standalone Security Regression Test Runner
 * Runs natively on PHP 7.x/8.x (MacBook Pro 2011 / macOS High Sierra)
 * without requiring global Composer or PHPUnit installation.
 */

namespace PHPUnit\Framework {
    if (!class_exists('PHPUnit\Framework\TestCase')) {
        class TestCase {
            protected function setUp(): void {}
            protected function tearDown(): void {}

            public function assertDirectoryExists(string $dir, string $msg = ''): void {
                if (!is_dir($dir)) {
                    throw new \Exception($msg ?: "Directory does not exist: $dir");
                }
            }

            public function assertFileExists(string $file, string $msg = ''): void {
                if (!file_exists($file)) {
                    throw new \Exception($msg ?: "File does not exist: $file");
                }
            }

            public function assertFileDoesNotExist(string $file, string $msg = ''): void {
                if (file_exists($file)) {
                    throw new \Exception($msg ?: "File should not exist: $file");
                }
            }

            public function assertMatchesRegularExpression(string $pattern, string $string, string $msg = ''): void {
                if (!preg_match($pattern, $string)) {
                    throw new \Exception($msg ?: "Failed asserting that string matches pattern: $pattern");
                }
            }

            public function assertDoesNotMatchRegularExpression(string $pattern, string $string, string $msg = ''): void {
                if (preg_match($pattern, $string)) {
                    throw new \Exception($msg ?: "Failed asserting that string does not match pattern: $pattern");
                }
            }

            public function assertTrue($val, string $msg = ''): void {
                if ($val !== true) {
                    throw new \Exception($msg ?: "Failed asserting that value is true");
                }
            }

            public function assertFalse($val, string $msg = ''): void {
                if ($val !== false) {
                    throw new \Exception($msg ?: "Failed asserting that value is false");
                }
            }

            public function assertEquals($expected, $actual, string $msg = ''): void {
                if ($expected != $actual) {
                    throw new \Exception($msg ?: "Failed asserting that actual equals expected");
                }
            }

            public function assertStringContainsString(string $needle, string $haystack, string $msg = ''): void {
                if (strpos($haystack, $needle) === false) {
                    throw new \Exception($msg ?: "Did not find '{$needle}' in string");
                }
            }

            public function assertNotEmpty($actual, string $msg = ''): void {
                if (empty($actual)) {
                    throw new \Exception($msg ?: "Failed asserting that value is not empty");
                }
            }
        }
    }
}

namespace {
    require_once __DIR__ . '/security/SecurityRegressionTest.php';

    echo "===============================================================\n";
    echo " SERVERFLOW ENTERPRISE SECURITY REGRESSION TEST SUITE\n";
    echo " Target: macOS High Sierra (MacBook Pro 2011 Native Stack)\n";
    echo "===============================================================\n\n";

    $testClass = 'Tests\Security\SecurityRegressionTest';
    $ref = new ReflectionClass($testClass);
    $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);

    $passed = 0;
    $failed = 0;
    $total = 0;

    foreach ($methods as $method) {
        $name = $method->getName();
        if (!str_starts_with($name, 'test')) {
            continue;
        }

        $total++;
        $instance = new $testClass();
        
        $setUp = new ReflectionMethod($testClass, 'setUp');
        $setUp->invoke($instance);

        $start = microtime(true);
        try {
            $method->invoke($instance);
            $duration = round((microtime(true) - $start) * 1000, 2);
            echo " [PASS] {$name} ({$duration}ms)\n";
            $passed++;
        } catch (\Throwable $t) {
            $duration = round((microtime(true) - $start) * 1000, 2);
            echo " [FAIL] {$name} ({$duration}ms)\n";
            echo "        Error: " . $t->getMessage() . "\n";
            $failed++;
        }
    }

    echo "\n---------------------------------------------------------------\n";
    echo "Results: {$passed}/{$total} PASSED";
    if ($failed > 0) {
        echo ", {$failed} FAILED\n";
        echo "STATUS: REGRESSION DETECTED\n";
        exit(1);
    } else {
        echo " (100% Success)\n";
        echo "STATUS: ALL SECURITY DEFENSES VERIFIED & SOUND\n";
        echo "---------------------------------------------------------------\n";
        exit(0);
    }
}
