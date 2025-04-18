<?php
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
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Connection failed: " . $conn->connect_error
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
        'message' => 'Invalid JSON: ' . json_last_error_msg()
    ]);
    exit();
}

// Validate required fields
if (!isset($booking['class_id']) || !isset($booking['trainer_id']) || 
    !isset($booking['booking_date']) || !isset($booking['start_time']) || 
    !isset($booking['end_time'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if the class exists and is available
    $classCheck = $conn->prepare("SELECT ClassID FROM class WHERE ClassID = ?");
    $classCheck->bind_param("i", $booking['class_id']);
    $classCheck->execute();
    $classResult = $classCheck->get_result();
    
    if ($classResult->num_rows === 0) {
        throw new Exception("Class not found");
    }
    
    // Check if the trainer exists
    $trainerCheck = $conn->prepare("SELECT TrainerID FROM trainer WHERE TrainerID = ?");
    $trainerCheck->bind_param("i", $booking['trainer_id']);
    $trainerCheck->execute();
    $trainerResult = $trainerCheck->get_result();
    
    if ($trainerResult->num_rows === 0) {
        throw new Exception("Trainer not found");
    }
    
    // Check for existing bookings at the same time
    $timeCheck = $conn->prepare("
        SELECT BookingID FROM booking 
        WHERE BookingDate = ? 
        AND ((StartTime <= ? AND EndTime > ?) OR (StartTime < ? AND EndTime >= ?))
    ");
    $timeCheck->bind_param("sssss", 
        $booking['booking_date'],
        $booking['end_time'],
        $booking['start_time'],
        $booking['end_time'],
        $booking['start_time']
    );
    $timeCheck->execute();
    $timeResult = $timeCheck->get_result();
    
    if ($timeResult->num_rows > 0) {
        throw new Exception("Time slot already booked");
    }
    
    // Insert the booking
    $stmt = $conn->prepare("
        INSERT INTO booking (
            ClientID, 
            ClassID, 
            TrainerID, 
            BookingDate, 
            StartTime, 
            EndTime
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    // Get client ID from session or use a default for testing
    $clientID = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : 1;
    
    // Bind parameters
    $stmt->bind_param("iiisss", 
        $clientID,
        $booking['class_id'],
        $booking['trainer_id'],
        $booking['booking_date'],
        $booking['start_time'],
        $booking['end_time']
    );
    
    // Execute the statement
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Failed to create booking");
    }
    
    // Update trainer's available slots
    $updateSlots = $conn->prepare("
        UPDATE trainer 
        SET AvailableSlots = AvailableSlots - 1 
        WHERE TrainerID = ?
    ");
    $updateSlots->bind_param("i", $booking['trainer_id']);
    $updateSlots->execute();
    
    // Commit transaction
    $conn->commit();
    
    $response = [
        'success' => true,
        'message' => 'Booking created successfully'
    ];
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Close connections
$conn->close();

// Output the response
header('Content-Type: application/json');
echo json_encode($response);
?> 