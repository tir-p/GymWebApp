<?php
session_start();

// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Only allow clients
if (!isset($_SESSION['client_id'])) {
    echo '<div style="color: red;">Only clients can update account information.</div>';
    exit();
}
$userId = $_SESSION['client_id'];

// Validate POST data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$errors = [];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}
if (empty($phone)) {
    $errors[] = 'Phone number is required.';
}

if (!empty($errors)) {
    echo '<div style="color: red;"><ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul><a href="javascript:history.back()">Go back</a></div>';
    exit();
}

// Update the client table
$stmt = $conn->prepare('UPDATE client SET Email=?, Phone=? WHERE ClientID=?');
$stmt->bind_param('ssi', $email, $phone, $userId);

if ($stmt->execute()) {
    echo '<div style="color: green;">Account information updated successfully. <a href="account.html">Return to Account</a></div>';
} else {
    echo '<div style="color: red;">Failed to update account: ' . htmlspecialchars($stmt->error) . '</div>';
}
$stmt->close();
$conn->close(); 