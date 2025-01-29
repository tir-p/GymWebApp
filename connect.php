<?php
// Database connection
$host = 'localhost';  // or 'localhost'
$dbname = 'gymwebapp';  // Your database name
$username = 'root';  // Your database username
$password = '';  // Your database password

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Ensure connection is correctly established
?>



