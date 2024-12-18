<?php
// Include the database connection file
include 'connect.php'; // This file contains your PDO connection setup

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Prepare and execute the SQL statement using prepared statements
    $stmt = $pdo->prepare("INSERT INTO contact (name, email, message) VALUES (:name, :email, :message)");

    // Bind parameters to prevent SQL injection
    $stmt->bindParam(':name', $_POST['name']);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':message', $_POST['message']);

    // Try to execute the query and handle success or failure
    try {
        if ($stmt->execute()) {
            echo "Thank you for reaching out! We will get back to you shortly.";
        } else {
            echo "There was an error submitting your message. Please try again later.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
