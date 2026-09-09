<?php
require_once __DIR__ . '/../../config/db_connect.php';

class DiagnosticHelper {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getDiagnostics() {
        return [
            'storage' => $this->getStorageStatus(),
            'usb' => $this->getUsbStatus(),
            'databases' => $this->getDatabaseSummary(),
            'hosted_sites' => $this->getHostedSitesSummary(),
            'activity' => $this->getActivitySummary()
        ];
    }

    private function getStorageStatus() {
        $path = __DIR__ . '/../../';
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if ($total && $total > 0) {
            $used = $total - $free;
            return [
                'total_gb' => round($total / 1024 / 1024 / 1024, 2),
                'used_gb' => round($used / 1024 / 1024 / 1024, 2),
                'free_gb' => round($free / 1024 / 1024 / 1024, 2),
                'percent_used' => round(($used / $total) * 100, 1)
            ];
        }
        return ['status' => 'unknown'];
    }

    private function getUsbStatus() {
        $usbPath = __DIR__ . '/../../usb';
        if (is_dir($usbPath)) {
            $files = array_diff(scandir($usbPath), array('.', '..', '.DS_Store'));
            $size = 0;
            foreach($files as $f) {
                $size += filesize($usbPath . '/' . $f);
            }
            return [
                'status' => 'connected',
                'file_count' => count($files),
                'total_size_mb' => round($size / 1024 / 1024, 2)
            ];
        }
        return ['status' => 'offline'];
    }

    private function getDatabaseSummary() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM sys_users");
            $users = $stmt->fetchColumn();
            
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM admin_users");
            $admins = $stmt->fetchColumn();
            
            return [
                'status' => 'online',
                'total_users' => $users,
                'total_admins' => $admins
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'DB inaccessible'];
        }
    }

    private function getHostedSitesSummary() {
        $sitesDir = __DIR__ . '/../../sites';
        $activeCount = 0;
        if (is_dir($sitesDir)) {
            $dirs = array_filter(glob($sitesDir . '/*'), 'is_dir');
            $activeCount = count($dirs);
        }
        return [
            'active_sites' => $activeCount
        ];
    }

    private function getActivitySummary() {
        try {
            $stmt = $this->pdo->query("SELECT project_name, deploy_status, executed_at FROM sys_deploy_logs ORDER BY executed_at DESC LIMIT 5");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
