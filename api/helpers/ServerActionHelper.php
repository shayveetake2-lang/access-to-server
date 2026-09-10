<?php
require_once __DIR__ . '/DiagnosticHelper.php';
require_once __DIR__ . '/../../config/db_connect.php';

class ServerActionHelper {
    public static function executeAction($actionId, $params = []) {
        $allowedActions = ['restart_plex', 'restart_database', 'restart_web_service', 'recheck_diagnostics', 'inspect_hosted_sites', 'inspect_filtered_logs'];
        
        if (!in_array($actionId, $allowedActions)) {
            return ['status' => 'error', 'message' => 'Action not allowed.'];
        }

        try {
            switch ($actionId) {
                case 'restart_plex':
                    if (file_exists(__DIR__ . '/../../restart_plex.sh')) {
                        exec('bash ' . __DIR__ . '/../../restart_plex.sh', $output, $return_var);
                        return ['status' => 'success', 'message' => 'Plex restarted.', 'output' => $output];
                    }
                    return ['status' => 'error', 'message' => 'Script restart_plex.sh not found.'];
                case 'restart_database':
                    if (file_exists(__DIR__ . '/../../restart_db.sh')) {
                        exec('bash ' . __DIR__ . '/../../restart_db.sh', $output, $return_var);
                        return ['status' => 'success', 'message' => 'Database restarted.', 'output' => $output];
                    }
                    return ['status' => 'error', 'message' => 'Script restart_db.sh not found.'];
                case 'restart_web_service':
                    if (file_exists(__DIR__ . '/../../restart_web.sh')) {
                        exec('bash ' . __DIR__ . '/../../restart_web.sh', $output, $return_var);
                        return ['status' => 'success', 'message' => 'Web service restarted.', 'output' => $output];
                    }
                    return ['status' => 'error', 'message' => 'Script restart_web.sh not found.'];
                case 'recheck_diagnostics':
                    global $pdo;
                    if (!$pdo) {
                        $pdo = getDBConnection();
                    }
                    $helper = new DiagnosticHelper($pdo);
                    return ['status' => 'success', 'data' => $helper->getDiagnostics()];
                case 'inspect_hosted_sites':
                    $sitesDir = __DIR__ . '/../../sites';
                    $active = [];
                    if (is_dir($sitesDir)) {
                        foreach (glob($sitesDir . '/*') as $dir) {
                            if (is_dir($dir)) {
                                $active[] = basename($dir);
                            }
                        }
                    }
                    return ['status' => 'success', 'sites' => $active];
                case 'inspect_filtered_logs':
                    $logFile = __DIR__ . '/../../error_log';
                    if (file_exists($logFile)) {
                        $lines = array_slice(file($logFile), -50);
                        // Filter logs
                        $filtered = array_map(function($line) {
                            $line = preg_replace('/(password|token|key|secret)=[^&\s]+/', '$1=***', $line);
                            return substr($line, 0, 300); // truncate
                        }, $lines);
                        
                        return ['status' => 'success', 'message' => "Here are the recent logs:
" . implode("
", $filtered)];
                    }
                    return ['status' => 'success', 'message' => 'No logs found.'];

            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Failed to execute action.'];
        }
        
        return ['status' => 'error', 'message' => 'Unknown action mapping.'];
    }
}
