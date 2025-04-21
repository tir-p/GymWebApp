<?php
// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("<div class='bg-red-100 text-red-700 p-4 rounded mt-4 max-w-md mx-auto mt-10'>Connection failed: " . $conn->connect_error . "</div>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $message = $conn->real_escape_string($_POST['message']);

    $sql = "INSERT INTO contact (name, email, message) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $name, $email, $message);

    if ($stmt->execute()) {
        echo "<div class='bg-green-100 text-green-700 p-4 rounded mt-4 max-w-md mx-auto mt-10'>Thank you for reaching out! We will get back to you shortly.</div>";
    } else {
        echo "<div class='bg-red-100 text-red-700 p-4 rounded mt-4 max-w-md mx-auto mt-10'>There was an error submitting your message. Please try again later.</div>";
    }
    $stmt->close();
}
$conn->close();
?>
