<?php
// This file will handle fetching booking data
// Currently, it is empty. Please implement the necessary logic here.

// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Assuming the client is logged in and we have their ClientID
$clientID = 1; // Replace with actual logged-in client ID

// Fetch bookings for the client
$bookingQuery = $conn->query("SELECT b.BookingID, b.BookingDate, c.ClassName, t.Name AS TrainerName 
                               FROM booking b 
                               JOIN class c ON b.ClassID = c.ClassID 
                               JOIN trainer t ON b.TrainerID = t.TrainerID 
                               WHERE b.ClientID = $clientID");

$bookings = [];
while ($row = $bookingQuery->fetch_assoc()) {
    $bookings[] = $row;
}

// Return the bookings in JSON format
header('Content-Type: application/json');
echo json_encode($bookings);

// Close connection
$conn->close();
?>
