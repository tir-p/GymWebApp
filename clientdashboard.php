<?php
// This file will handle client dashboard logic
// Currently, it is empty. Please implement the necessary logic here.

// Start the session to access session variables
session_start();

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

// Assuming the client is logged in and we have their ClientID stored in session
if (!isset($_SESSION['ClientID'])) {
    die(json_encode(['error' => 'Client not logged in.']));
}

$clientID = $_SESSION['ClientID']; // Get the actual logged-in client ID from session

// Fetch memberships for the client using prepared statements
$membershipQuery = $conn->prepare("SELECT p.PlanName, s.StartDate, s.EndDate, s.Status 
                                     FROM subscription s 
                                     JOIN plantype p ON s.PlanTypeID = p.PlanTypeID 
                                     WHERE s.ClientID = ?");
$membershipQuery->bind_param("i", $clientID); // Bind the client ID as an integer
$membershipQuery->execute();
$membershipResult = $membershipQuery->get_result();

$memberships = [];
while ($row = $membershipResult->fetch_assoc()) {
    $memberships[] = $row;
}

// Fetch bookings for the client using prepared statements
$bookingQuery = $conn->prepare("SELECT b.BookingID, b.BookingDate, c.ClassName, t.Name AS TrainerName 
                                 FROM booking b 
                                 JOIN class c ON b.ClassID = c.ClassID 
                                 JOIN trainer t ON b.TrainerID = t.TrainerID 
                                 WHERE b.ClientID = ?");
$bookingQuery->bind_param("i", $clientID); // Bind the client ID as an integer
$bookingQuery->execute();
$bookingResult = $bookingQuery->get_result();

$bookings = [];
while ($row = $bookingResult->fetch_assoc()) {
    $bookings[] = $row;
}

// Package all data into an array
$data = [
    'memberships' => $memberships,
    'bookings' => $bookings,
];

// Return the data in JSON format
header('Content-Type: application/json');
echo json_encode($data);

// Close connections
$membershipQuery->close();
$bookingQuery->close();
$conn->close();
?>
