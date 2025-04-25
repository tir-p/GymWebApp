<?php
session_start();
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$clientId = $_SESSION['client_id'];
$stmt = $conn->prepare('SELECT Name, Email, Phone FROM client WHERE ClientID=?');
$stmt->bind_param('i', $clientId);
$stmt->execute();
$stmt->bind_result($name, $email, $phone);
if ($stmt->fetch()) {
    echo json_encode(['success' => true, 'name' => $name, 'email' => $email, 'phone' => $phone]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
$stmt->close();
$conn->close(); 