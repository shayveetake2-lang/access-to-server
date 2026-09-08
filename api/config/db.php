<?php
// /api/config/db.php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../../config/config.php';

class Database {
    public $conn;

    public function getConnection() {
        try {
            $this->conn = getDBConnection();
        } catch(PDOException $exception) {
            http_response_code(503); // Service Unavailable
            echo json_encode(["status" => "error", "message" => "Database Connection Failed: " . $exception->getMessage()]);
            exit;
        } catch(\Exception $exception) {
            http_response_code(503);
            echo json_encode(["status" => "error", "message" => "Database Connection Failed: " . $exception->getMessage()]);
            exit;
        }
        return $this->conn;
    }
}
