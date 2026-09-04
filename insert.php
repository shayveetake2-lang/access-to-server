<?php
// insert.php

// MAMP Hardcoded Credentials
$host = 'localhost';
$port = '8889';
$dbname = 'access_db';
$username = 'root';
$password = 'root';

try {
    // Data Source Name for PDO
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    // Create a PDO instance
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Check if the form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize and retrieve POST data
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

        // Prepare the SQL statement
        $sql = "INSERT INTO user_inputs (name, email, message) VALUES (:name, :email, :message)";
        $stmt = $pdo->prepare($sql);

        // Execute the statement
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':message' => $message
        ]);

        // Success feedback
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Success</title>
            <link rel='stylesheet' href='style.css'>
            <style>
                body { display: flex; justify-content: center; align-items: center; height: 100vh; }
                .success-msg { text-align: center; background: rgba(31, 40, 51, 0.8); padding: 40px; border-radius: 15px; border: 1px solid #45a29e; animation: floatForm 6s infinite ease-in-out alternate;}
                a { color: #66fcf1; text-decoration: none; border-bottom: 1px solid #66fcf1; display: inline-block; margin-top: 20px;}
                a:hover { color: #fff; border-bottom-color: #fff; }
            </style>
        </head>
        <body>
            <div class='success-msg'>
                <h2>Transmission Received</h2>
                <p>Welcome to the server, " . htmlspecialchars($name) . ".</p>
                <a href='index.html'>Return to Terminal</a>
            </div>
        </body>
        </html>";

    } else {
        header("Location: index.html");
        exit;
    }

} catch (PDOException $e) {
    // Error handling
    die("Database connection failed: " . $e->getMessage());
}
?>

