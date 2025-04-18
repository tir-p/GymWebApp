<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class BookingService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getBookings($clientId) {
        try {
            $sql = "SELECT b.BookingID, b.BookingDate, b.StartTime, b.EndTime,
                   c.ClassName, c.ClassType, t.Name AS TrainerName
                   FROM booking b
                   JOIN class c ON b.ClassID = c.ClassID
                   JOIN trainer t ON c.TrainerID = t.TrainerID
                   WHERE b.ClientID = ?
                   ORDER BY b.BookingDate DESC, b.StartTime";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $clientId);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $bookings = [];
            
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
            
            $stmt->close();

            return json_encode([
                'status' => 'success',
                'bookings' => $bookings
            ]);
        } catch (Exception $e) {
            error_log("Error in getBookings: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Database error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function createBooking($data) {
        try {
            // Validate required fields
            $requiredFields = ['client_id', 'class_id', 'booking_date'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            // Get class schedule
            $classSql = "SELECT Schedule, StartTime, EndTime, Capacity, CurrentEnrollment 
                        FROM class WHERE ClassID = ? AND Status = 'active'";
            $classStmt = $this->conn->prepare($classSql);
            $classStmt->bind_param("i", $data['class_id']);
            $classStmt->execute();
            $classResult = $classStmt->get_result();
            $class = $classResult->fetch_assoc();
            
            if (!$class) {
                throw new Exception("Class not found or inactive");
            }

            // Check if class is full
            if ($class['CurrentEnrollment'] >= $class['Capacity']) {
                throw new Exception("Class is full");
            }

            // Check if booking date is valid for class schedule
            $bookingDay = date('l', strtotime($data['booking_date']));
            $scheduleDays = explode(',', $class['Schedule']);
            if (!in_array($bookingDay, $scheduleDays)) {
                throw new Exception("Invalid booking date - class not scheduled for this day");
            }

            // Check for existing booking on same date
            $checkSql = "SELECT COUNT(*) as count FROM booking 
                        WHERE ClientID = ? AND ClassID = ? AND BookingDate = ? AND Status = 'active'";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("iis", $data['client_id'], $data['class_id'], $data['booking_date']);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $check = $checkResult->fetch_assoc();
            
            if ($check['count'] > 0) {
                throw new Exception("You already have a booking for this class on this date");
            }

            // Create booking
            $insertSql = "INSERT INTO booking (ClientID, ClassID, BookingDate, StartTime, EndTime, Status) 
                         VALUES (?, ?, ?, ?, ?, 'active')";
            $insertStmt = $this->conn->prepare($insertSql);
            $insertStmt->bind_param("iisss", 
                $data['client_id'], 
                $data['class_id'], 
                $data['booking_date'],
                $class['StartTime'],
                $class['EndTime']
            );
            
            if (!$insertStmt->execute()) {
                throw new Exception("Failed to create booking");
            }

            // Update class enrollment
            $updateSql = "UPDATE class SET CurrentEnrollment = CurrentEnrollment + 1 
                         WHERE ClassID = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bind_param("i", $data['class_id']);
            $updateStmt->execute();

            return json_encode([
                'status' => 'success',
                'message' => 'Booking created successfully',
                'booking_id' => $insertStmt->insert_id
            ]);
        } catch (Exception $e) {
            error_log("Error in createBooking: " . $e->getMessage());
            http_response_code(400);
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function cancelBooking($bookingId, $clientId) {
        try {
            // First verify the booking exists and belongs to the client
            $checkSql = "SELECT BookingID, ClientID FROM booking WHERE BookingID = ?";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("i", $bookingId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows === 0) {
                error_log("Booking not found for ID: " . $bookingId);
                throw new Exception("Booking not found");
            }
            
            $booking = $result->fetch_assoc();
            if ($booking['ClientID'] != $clientId) {
                error_log("Booking " . $bookingId . " does not belong to client " . $clientId);
                throw new Exception("You do not have permission to cancel this booking");
            }

            // Delete the booking
            $deleteSql = "DELETE FROM booking WHERE BookingID = ? AND ClientID = ?";
            $deleteStmt = $this->conn->prepare($deleteSql);
            $deleteStmt->bind_param("ii", $bookingId, $clientId);
            
            if (!$deleteStmt->execute()) {
                error_log("Failed to delete booking: " . $deleteStmt->error);
                throw new Exception("Failed to cancel booking");
            }

            return json_encode([
                'status' => 'success',
                'message' => 'Booking cancelled successfully'
            ]);
        } catch (Exception $e) {
            error_log("Error in cancelBooking: " . $e->getMessage());
            http_response_code(400);
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}

// Handle the request
try {
    // Create a connection using mysqli
    $conn = new mysqli('localhost', 'root', '', 'gymwebapp');
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $service = new BookingService($conn);

    // Get client ID from session
    if (!isset($_SESSION['client_id'])) {
        throw new Exception("User not authenticated");
    }
    $clientId = $_SESSION['client_id'];

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            echo $service->getBookings($clientId);
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $data['client_id'] = $clientId;
            echo $service->createBooking($data);
            break;
        case 'DELETE':
            if (!isset($_GET['booking_id'])) {
                throw new Exception("Booking ID is required");
            }
            echo $service->cancelBooking($_GET['booking_id'], $clientId);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
    }
    
    // Close the connection
    $conn->close();
} catch (Exception $e) {
    error_log("Error in booking service: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
}
?> 