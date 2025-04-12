<?php
session_start();
// Include the JSON validator
require_once 'json_validator.php';

// Verify that client is logged in
if (!isset($_SESSION['client_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required',
        'data' => null
    ]);
    exit();
}

// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Connection failed: " . $conn->connect_error,
        'data' => null
    ]);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method is allowed',
        'data' => null
    ]);
    exit();
}

// Get raw request body
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON: ' . json_last_error_msg(),
        'data' => null
    ]);
    exit();
}

// Define the schema for cancellation request
$cancelSchema = [
    'type' => 'object',
    'properties' => [
        'booking_id' => ['type' => 'integer']
    ],
    'required' => ['booking_id']
];

// Validate the JSON against the schema
$validationResult = JSONValidator::validate($data, $cancelSchema);

if (!$validationResult['valid']) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request format',
        'errors' => $validationResult['errors']
    ]);
    exit();
}

// Get values from the request
$bookingId = $data['booking_id'];
$clientId = $_SESSION['client_id'];

// First verify that this booking belongs to the logged-in client
$verifyQuery = $conn->prepare("SELECT BookingID FROM booking WHERE BookingID = ? AND ClientID = ?");
$verifyQuery->bind_param("ii", $bookingId, $clientId);
$verifyQuery->execute();
$verifyResult = $verifyQuery->get_result();

if ($verifyResult->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found or does not belong to this client',
        'data' => null
    ]);
    $verifyQuery->close();
    $conn->close();
    exit();
}

// If verification passed, delete the booking
$deleteQuery = $conn->prepare("DELETE FROM booking WHERE BookingID = ?");
$deleteQuery->bind_param("i", $bookingId);
$deleteQuery->execute();

// Check if deletion was successful
if ($deleteQuery->affected_rows > 0) {
    $response = [
        'success' => true,
        'message' => 'Booking cancelled successfully',
        'data' => [
            'booking_id' => $bookingId
        ]
    ];
} else {
    $response = [
        'success' => false,
        'message' => 'Failed to cancel booking',
        'data' => null
    ];
}

// Close statements and connection
$verifyQuery->close();
$deleteQuery->close();
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 