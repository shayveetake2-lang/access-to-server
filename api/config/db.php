<?php
// /api/config/db.php
require_once 'init.php';

class Database {
    // We use localhost here since the PHP script executes directly on the Late 2011 MacBook Pro MAMP server.
    private $host = "localhost"; 
    private $port = "8889";
    private $db_name = "access_db";
    private $username = "root";
    private $password = "root";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Critical for iOS APIs: Ensure PDO returns associative arrays and throws exceptions natively
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
        } catch(PDOException $exception) {
            http_response_code(503); // Service Unavailable
            echo json_encode(["status" => "error", "message" => "Database Connection Failed: " . $exception->getMessage()]);
            exit;
        }
        return $this->conn;
    }
}
?>

