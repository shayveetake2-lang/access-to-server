<?php
// /api/config/db.php
require_once 'init.php';

class Database {
    // We use localhost here since the PHP script executes directly on the Late 2011 MacBook Pro MAMP server.
    private $host = "localhost"; 
    private $port;
    private $db_name = "access_db";
    private $username = "root";
    private $password = "root";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        $this->port = '8889';
        $s8889 = @fsockopen('127.0.0.1', 8889, $errno, $errstr, 0.5);
        if ($s8889) {
            fclose($s8889);
            $this->port = '8889';
        } else {
            $s3307 = @fsockopen('127.0.0.1', 3307, $errno, $errstr, 0.5);
            if ($s3307) {
                fclose($s3307);
                $this->port = '3307';
            }
        }

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

