<?php
$conn = new mysqli('127.0.0.1', 'ampache_user', 'password', 'ampache', 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "MySQL is alive! Ping successful.";
$conn->close();
?>
