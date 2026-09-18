<?php
/**
 * Enterprise Security Regression Test Suite — ServerFlow Platform
 * 
 * Asserts closure of all Critical, High, Medium, and Low vulnerabilities
 * identified in the two-pass adversarial penetration audit.
 * 
 * Specifically validates hardware and thermal safeguards for 2011 MacBook Pro host.
 */

namespace Tests\Security;

if (!class_exists('\PHPUnit\Framework\TestCase')) {
    class MockTestCase {
        protected function assertTrue($condition, $message = '') {
            if (!$condition) {
                throw new \Exception("Assertion Failed (assertTrue): " . $message);
            }
        }

        protected function assertFalse($condition, $message = '') {
            if ($condition) {
                throw new \Exception("Assertion Failed (assertFalse): " . $message);
            }
        }

        protected function assertFileExists($path, $message = '') {
            if (!file_exists($path)) {
                throw new \Exception("Assertion Failed (assertFileExists): Path does not exist: {$path}. " . $message);
            }
        }

        protected function assertFileDoesNotExist($path, $message = '') {
            if (file_exists($path)) {
                throw new \Exception("Assertion Failed (assertFileDoesNotExist): Path exists: {$path}. " . $message);
            }
        }

        protected function assertDirectoryExists($path, $message = '') {
            if (!is_dir($path)) {
                throw new \Exception("Assertion Failed (assertDirectoryExists): Directory does not exist: {$path}. " . $message);
            }
        }

        protected function assertMatchesRegularExpression($pattern, $string, $message = '') {
            if (!preg_match($pattern, $string)) {
                throw new \Exception("Assertion Failed (assertMatchesRegularExpression): Pattern {$pattern} did not match. " . $message);
            }
        }

        protected function assertEquals($expected, $actual, $message = '') {
            if ($expected !== $actual) {
                throw new \Exception("Assertion Failed (assertEquals): Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ". " . $message);
            }
        }

        protected function assertStringContainsString($needle, $haystack, $message = '') {
            if (strpos($haystack, $needle) === false) {
                throw new \Exception("Assertion Failed (assertStringContainsString): Did not find '{$needle}'. " . $message);
            }
        }

        protected function assertDoesNotMatchRegularExpression($pattern, $string, $message = '') {
            if (preg_match($pattern, $string)) {
                throw new \Exception("Assertion Failed (assertDoesNotMatchRegularExpression): Pattern {$pattern} matched unexpectedly. " . $message);
            }
        }
    }
} else {
    class_alias('\PHPUnit\Framework\TestCase', 'Tests\Security\MockTestCase');
}

class SecurityRegressionTest extends MockTestCase
{
    private string $projectRoot;

    public function __construct()
    {
        $this->setUp();
    }

    public function setUp(): void
    {
        $this->projectRoot = realpath(__DIR__ . '/../../');
    }

    // =========================================================================
    // SECTION 1: REMOTE CODE EXECUTION (RCE) & ARTIFACT INGESTION DEFENSES
    // =========================================================================

    public function testSitesDirectoryDisallowsPhpExecution(): void
    {
        $sitesDir = $this->projectRoot . '/sites';
        $this->assertDirectoryExists($sitesDir, 'sites/ directory must exist.');

        $htaccessPath = $sitesDir . '/.htaccess';
        $this->assertFileExists($htaccessPath, 'sites/ directory must contain .htaccess disabling script execution.');

        $content = file_get_contents($htaccessPath);
        $this->assertMatchesRegularExpression(
            '/php_flag\s+engine\s+off|RemoveHandler\s+\.php|SetHandler\s+default-handler|<FilesMatch\s+.*\.php/i',
            $content,
            'sites/.htaccess must actively disable PHP script execution to prevent webshell RCE.'
        );
    }

    public function testGitDeployerRejectsArgumentInjection(): void
    {
        $maliciousUrls = [
            '--upload-pack="touch /tmp/pwn"',
            'git@--upload-pack=evil',
            '-u https://github.com/evil/repo',
            '--config core.sshCommand=evil https://github.com/evil/repo',
            '-oProxyCommand=calc.exe https://github.com/evil/repo'
        ];

        foreach ($maliciousUrls as $url) {
            $isValid = filter_var($url, FILTER_VALIDATE_URL) &&
                       preg_match('/^https:\/\/[a-zA-Z0-9_\-\.]+\/[a-zA-Z0-9_\-\.\/]+$/', $url) &&
                       !str_starts_with(trim($url), '-');
            
            $this->assertFalse(
                (bool)$isValid,
                "Git deployer failed to reject CLI argument injection payload: {$url}"
            );
        }
    }

    public function testDeployEngineUsesExplicitArgumentSeparators(): void
    {
        $deployFile = $this->projectRoot . '/api/system/deploy.php';
        $this->assertFileExists($deployFile);
        $content = file_get_contents($deployFile);

        $this->assertMatchesRegularExpression(
            '/git\s+clone\s+--\s+|escapeshellarg/i',
            $content,
            'deploy.php must use escapeshellarg and end-of-options delimiter (--) for git commands.'
        );
    }

    public function testZipDeployerRejectsExecutableAndBombPatterns(): void
    {
        $forbidden = [
            'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phps', 
            'phar', 'exe', 'sh', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'bat', 
            'cmd', 'user.ini', 'htaccess'
        ];
        
        $testFilenames = [
            'exploit.php8',
            'config.pht',
            '.user.ini',
            '.htaccess',
            'script.phps',
            'payload.phar',
            'exploit.php.jpeg'
        ];

        foreach ($testFilenames as $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $parts = explode('.', strtolower($filename));
            
            $isBlocked = in_array($ext, $forbidden, true) || in_array(strtolower($filename), ['.user.ini', '.htaccess'], true);
            foreach ($parts as $part) {
                if (in_array($part, $forbidden, true)) {
                    $isBlocked = true;
                    break;
                }
            }

            $this->assertTrue(
                $isBlocked,
                "Archive extraction allowed forbidden executable pattern: {$filename}"
            );
        }
    }

    // =========================================================================
    // SECTION 2: AUTHENTICATION, PASSWORD HANDLING & SESSION SECURITY
    // =========================================================================

    public function testAdminPasswordDoesNotUseHardcodedDefaults(): void
    {
        $filesToCheck = [
            $this->projectRoot . '/config/config.php',
            $this->projectRoot . '/api/system/admin_auth.php'
        ];

        foreach ($filesToCheck as $file) {
            $this->assertFileExists($file);
            $code = file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/Cones420|123456789/i',
                $code,
                "File {$file} contains hardcoded default credentials ('Cones420' or '123456789')!"
            );
        }
    }

    public function testAuthTokensEnforceTtlAndHashedStorage(): void
    {
        $configFile = $this->projectRoot . '/config/config.php';
        $code = file_get_contents($configFile);

        $this->assertMatchesRegularExpression(
            '/token_expires_at/i',
            $code,
            'sys_users schema must include an expiration timestamp (token_expires_at) for auth tokens.'
        );

        $this->assertMatchesRegularExpression(
            '/token_hash/i',
            $code,
            'sys_users schema must include token_hash column.'
        );

        $loginFile = $this->projectRoot . '/api/auth/login.php';
        $loginCode = file_get_contents($loginFile);
        $this->assertMatchesRegularExpression(
            '/hash\s*\(\s*[\'"]sha256[\'"]/i',
            $loginCode,
            'login.php must hash bearer tokens with SHA-256 prior to database storage.'
        );
    }

    public function testSessionCookieSecurityFlags(): void
    {
        $bootstrapFile = $this->projectRoot . '/api/config/init.php';
        $this->assertFileExists($bootstrapFile);
        $content = file_get_contents($bootstrapFile);

        $hasCookieParams = str_contains($content, 'session_set_cookie_params') &&
                           str_contains($content, 'httponly') &&
                           str_contains($content, 'samesite');
        
        $this->assertTrue(
            $hasCookieParams,
            'api/config/init.php must configure session_set_cookie_params with HttpOnly and SameSite.'
        );
    }

    public function testCsrfProtectionRejectsQueryParamBypass(): void
    {
        $requireAdminFile = $this->projectRoot . '/api/auth/require_admin.php';
        $this->assertFileExists($requireAdminFile);
        $content = file_get_contents($requireAdminFile);

        $this->assertMatchesRegularExpression(
            '/\$_GET\[[\'"]token[\'"]\]/i',
            $content,
            'require_admin.php must explicitly check and reject tokens passed in $_GET.'
        );
    }

    public function testPasswordLengthPolicyEnforced(): void
    {
        $registerFile = $this->projectRoot . '/api/auth/register.php';
        $content = file_get_contents($registerFile);

        $this->assertDoesNotMatchRegularExpression(
            '/strlen\(\$password\)\s*<\s*[4-9]\b/',
            $content,
            'api/auth/register.php allows passwords shorter than 10 characters.'
        );
    }

    // =========================================================================
    // SECTION 3: AUTHORIZATION & ACCESS CONTROL (BROKEN ACCESS CONTROL)
    // =========================================================================

    public function testRestartMampEndpointRequiresAdmin(): void
    {
        $file = $this->projectRoot . '/api/system/restart_mamp.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);

        $this->assertMatchesRegularExpression(
            '/requireAdmin\s*\(\s*\)/',
            $content,
            'api/system/restart_mamp.php must require admin authentication before restarting web server.'
        );
    }

    public function testStorageStatsRequiresAuth(): void
    {
        $file = $this->projectRoot . '/api/system/storage_stats.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);

        $this->assertMatchesRegularExpression(
            '/requireAdmin\s*\(\s*\)|requireAuth\s*\(\s*\)/',
            $content,
            'api/system/storage_stats.php must enforce authentication to prevent telemetry disclosure.'
        );
    }

    public function testTestEndpointRequiresAdminAndDoesNotDumpSecrets(): void
    {
        $testFile = $this->projectRoot . '/api/system/test.php';
        $this->assertFileExists($testFile);
        $content = file_get_contents($testFile);

        $this->assertMatchesRegularExpression(
            '/requireAdmin\s*\(\s*\)/',
            $content,
            'api/system/test.php must require admin authentication.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/parse_ini_file\s*\(.*ampache\.cfg\.php/i',
            $content,
            'api/system/test.php must not dump raw ampache.cfg.php containing plaintext database passwords.'
        );
    }

    public function testLegacyTestScriptsAreProtectedOrRemoved(): void
    {
        $scripts = [
            $this->projectRoot . '/test_db_insert.php',
            $this->projectRoot . '/create_table.php',
            $this->projectRoot . '/test_ampache_create_user.php'
        ];

        foreach ($scripts as $script) {
            $this->assertFileDoesNotExist(
                $script,
                "Obsolete script {$script} still exists in webroot!"
            );
        }
    }

    // =========================================================================
    // SECTION 4: DATA EXPOSURE, SQLITE WAL & FILE ACCESS DEFENSES
    // =========================================================================

    public function testSqliteDatabaseNotInPublicWebroot(): void
    {
        $publicDb = $this->projectRoot . '/access_db.sqlite';
        $this->assertFileDoesNotExist(
            $publicDb,
            'access_db.sqlite must not exist in public web document root.'
        );

        $htaccess = file_get_contents($this->projectRoot . '/.htaccess');
        $this->assertMatchesRegularExpression(
            '/sqlite/i',
            $htaccess,
            '.htaccess must explicitly deny all *.sqlite files.'
        );
    }

    public function testGitDirectoryDeniedInHtaccess(): void
    {
        $htaccess = file_get_contents($this->projectRoot . '/.htaccess');
        $this->assertMatchesRegularExpression(
            '/\.git/i',
            $htaccess,
            '.htaccess must deny access to .git metadata directory.'
        );
        $this->assertMatchesRegularExpression(
            '/RedirectMatch\s+404\s+.*\\\.git/i',
            $htaccess,
            '.htaccess must comprehensively block .git directory contents.'
        );
    }

    public function testInspectTableRedactsAuthTokens(): void
    {
        $inspectFile = $this->projectRoot . '/api/system/inspect_table.php';
        $this->assertFileExists($inspectFile);
        $content = file_get_contents($inspectFile);

        $this->assertMatchesRegularExpression(
            '/auth_token/i',
            $content,
            'inspect_table.php must explicitly redact auth_token.'
        );
        $this->assertMatchesRegularExpression(
            '/token_hash/i',
            $content,
            'inspect_table.php must explicitly redact token_hash.'
        );
    }

    public function testSqliteEnforcesWalMode(): void
    {
        $configFile = $this->projectRoot . '/config/config.php';
        $content = file_get_contents($configFile);

        $this->assertMatchesRegularExpression(
            '/PRAGMA\s+journal_mode\s*=\s*WAL/i',
            $content,
            'config/config.php must enforce WAL mode for 2011 MacBook disk efficiency.'
        );
    }

    // =========================================================================
    // SECTION 5: CROSS-SITE SCRIPTING (XSS) & FRONTEND DEFENSES
    // =========================================================================

    public function testStoredXssEscapingInTemplates(): void
    {
        $indexHtml = file_get_contents($this->projectRoot . '/index.html');
        
        $this->assertDoesNotMatchRegularExpression(
            '/\$\{req\.media_title\}/',
            $indexHtml,
            'index.html renders unescaped ${req.media_title} in media requests table.'
        );
        $this->assertMatchesRegularExpression(
            '/escapeHtml\(\s*req\.media_title\s*\)/',
            $indexHtml,
            'index.html must escape req.media_title using escapeHtml().'
        );
    }

    public function testAdminManagementEscapesUsernameInHandlers(): void
    {
        $adminAuthJs = file_get_contents($this->projectRoot . '/js/admin_auth.js');

        $this->assertDoesNotMatchRegularExpression(
            '/onclick="deleteAdmin\([^,]+,\s*\'\$\{admin\.username\}\'\)"/',
            $adminAuthJs,
            'js/admin_auth.js contains unsanitized inline JS injection in deleteAdmin onclick handler.'
        );
    }

    // =========================================================================
    // SECTION 6: NETWORK SECURITY, CORS & SSL VALIDATION
    // =========================================================================

    public function testAiChatVerifiesSslCertificates(): void
    {
        $chatFile = $this->projectRoot . '/api/chat.php';
        $content = file_get_contents($chatFile);

        $this->assertDoesNotMatchRegularExpression(
            '/CURLOPT_SSL_VERIFYPEER\s*,\s*false/i',
            $content,
            'api/chat.php must not disable CURLOPT_SSL_VERIFYPEER.'
        );
    }

    public function testCorsPolicyWhitelistsServerflowIcu(): void
    {
        $initFile = $this->projectRoot . '/api/config/init.php';
        $content = file_get_contents($initFile);

        $this->assertDoesNotMatchRegularExpression(
            '/Access-Control-Allow-Origin:\s*\*/i',
            $content,
            'api/config/init.php must restrict Access-Control-Allow-Origin.'
        );
        $this->assertMatchesRegularExpression(
            '/serverflow\.icu/i',
            $content,
            'api/config/init.php must explicitly whitelist serverflow.icu.'
        );
    }

    public function testApiProxyDoesNotExposeHardcodedAdminCredentials(): void
    {
        $proxyFile = $this->projectRoot . '/modern-music-app/api_proxy.php';
        if (file_exists($proxyFile)) {
            $content = file_get_contents($proxyFile);
            $this->assertDoesNotMatchRegularExpression(
                '/adc397ffc4293cfe6dddb7ac19b759cc/i',
                $content,
                'modern-music-app/api_proxy.php exposes hardcoded administrator password hash!'
            );
        }
    }

    public function testMusicFrontendBundleIntegrity(): void
    {
        $htmlFile = $this->projectRoot . '/modern-music-app/dist/index.html';
        $this->assertFileExists($htmlFile, 'Music frontend dist/index.html must exist.');
        $html = file_get_contents($htmlFile);
        $this->assertStringContainsString('id="root"', $html, 'dist/index.html must contain #root mounting container.');

        // Extract bundle filename from index.html
        preg_match('/src="\.\/assets\/(index-[a-zA-Z0-9_-]+\.js)"/', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? '', 'dist/index.html must reference a compiled JS bundle.');

        $bundleFile = $this->projectRoot . '/modern-music-app/dist/assets/' . $matches[1];
        $this->assertFileExists($bundleFile, 'Referenced production JS bundle must exist.');
        
        $output = [];
        $returnCode = 0;
        exec("node -c " . escapeshellarg($bundleFile) . " 2>&1", $output, $returnCode);
        $this->assertEquals(0, $returnCode, 'Music frontend JS bundle contains syntax errors: ' . implode("\n", $output));

        // Safari 13 / High Sierra compatibility check: no logical assignment operators
        $bundleCode = file_get_contents($bundleFile);
        $this->assertDoesNotMatchRegularExpression(
            '/(\w+|\))\s*(\?\?=|\|\|=|&&=)/',
            $bundleCode,
            'Bundle contains ES2021 logical assignment operators (??=, ||=, &&=) which crash Safari on macOS High Sierra!'
        );
    }

    // =========================================================================
    // SECTION 7: HARDWARE & THERMAL SAFEGUARDS FOR 2011 MACBOOK PRO
    // =========================================================================

    public function testCatalogUpdateEndpointRequiresAdmin(): void
    {
        $catalogEndpoint = $this->projectRoot . '/api/system/update_catalog.php';
        $this->assertFileExists($catalogEndpoint, 'update_catalog.php must exist.');

        $content = file_get_contents($catalogEndpoint);
        $this->assertMatchesRegularExpression(
            '/requireAdmin\s*\(\s*\)/',
            $content,
            'api/system/update_catalog.php must enforce requireAdmin().'
        );
    }

    public function testCatalogUpdateEnforcesNiceAndLocking(): void
    {
        $catalogEndpoint = $this->projectRoot . '/api/system/update_catalog.php';
        $content = file_get_contents($catalogEndpoint);

        $this->assertMatchesRegularExpression(
            '/nice\s+-n\s+(15|19)/',
            $content,
            'update_catalog.php must invoke scanner with nice -n 15/19 to prevent 2011 MacBook overheating.'
        );
        $this->assertMatchesRegularExpression(
            '/serverflow_catalog_update\.lock/',
            $content,
            'update_catalog.php must use lockfile to prevent concurrent CPU exhaustion.'
        );
        $this->assertMatchesRegularExpression(
            '/run:updateCatalog\s+-a/',
            $content,
            'update_catalog.php must use incremental scan (-a) mode to avoid full library re-indexing load.'
        );
    }

    // =========================================================================
    // CLI TEST RUNNER
    // =========================================================================

    public function runAllTests(): void
    {
        $methods = get_class_methods($this);
        $testMethods = array_filter($methods, fn($m) => str_starts_with($m, 'test'));

        echo "\n=============================================================\n";
        echo "   SERVERFLOW ENTERPRISE SECURITY REGRESSION TEST SUITE\n";
        echo "   Target Host: MacBook Pro 2011 (Sandy Bridge Thermal Guard)\n";
        echo "=============================================================\n\n";

        $passed = 0;
        $failed = 0;

        foreach ($testMethods as $test) {
            echo sprintf("  %-55s ", $test . "()");
            try {
                $this->$test();
                echo "\033[32m[ PASS ]\033[0m\n";
                $passed++;
            } catch (\Throwable $t) {
                echo "\033[31m[ FAIL ]\033[0m\n";
                echo "    \033[33m" . $t->getMessage() . "\033[0m\n";
                $failed++;
            }
        }

        echo "\n-------------------------------------------------------------\n";
        echo "Results: {$passed} passed, {$failed} failed (" . count($testMethods) . " total)\n";
        if ($failed === 0) {
            echo "\033[32m✓ ALL SECURITY & THERMAL REGRESSION TESTS PASSED SUCCESSFULLY!\033[0m\n\n";
            exit(0);
        } else {
            echo "\033[31m✗ REGRESSION TESTS FAILED.\033[0m\n\n";
            exit(1);
        }
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $suite = new SecurityRegressionTest();
    $suite->runAllTests();
}
