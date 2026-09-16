<?php
$conn = new mysqli('10.247.192.231', 'ampache_user', 'password', 'ampache', 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "MySQL is alive! Ping successful.";
$conn->close();
?>
