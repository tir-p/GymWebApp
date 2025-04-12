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

// Only accept POST requests with JSON content type
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
$booking = json_decode($rawData, true);

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

// Define the booking schema
$bookingSchema = [
    'type' => 'object',
    'properties' => [
        'class_id' => ['type' => 'integer'],
        'trainer_id' => ['type' => 'integer'],
        'booking_date' => ['type' => 'string'],
        'notes' => ['type' => 'string']
    ],
    'required' => ['class_id', 'trainer_id', 'booking_date']
];

// Validate the JSON against the schema
$validationResult = JSONValidator::validate($booking, $bookingSchema);

if (!$validationResult['valid']) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking data format',
        'errors' => $validationResult['errors']
    ]);
    exit();
}

// If validation passed, insert the booking into database
try {
    // Prepare statement
    $stmt = $conn->prepare("INSERT INTO booking (ClientID, ClassID, TrainerID, BookingDate, Notes) VALUES (?, ?, ?, ?, ?)");
    
    // Get client ID from session
    $clientID = $_SESSION['client_id'];
    
    // Bind parameters
    $stmt->bind_param("iiiss", 
        $clientID, 
        $booking['class_id'], 
        $booking['trainer_id'], 
        $booking['booking_date'], 
        $booking['notes'] ?? '');
    
    // Execute the statement
    $stmt->execute();
    
    // Check if successful
    if ($stmt->affected_rows > 0) {
        $bookingId = $stmt->insert_id;
        
        // Prepare success response
        $response = [
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => [
                'booking_id' => $bookingId,
                'client_id' => $clientID,
                'class_id' => $booking['class_id'],
                'trainer_id' => $booking['trainer_id'],
                'booking_date' => $booking['booking_date']
            ]
        ];
    } else {
        // Insertion failed
        $response = [
            'success' => false,
            'message' => 'Failed to create booking',
            'data' => null
        ];
    }
    
    // Close statement
    $stmt->close();
} catch (Exception $e) {
    // Handle any exceptions
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => null
    ];
}

// Close connection
$conn->close();

// Output the response
header('Content-Type: application/json');
echo json_encode($response);
?> 