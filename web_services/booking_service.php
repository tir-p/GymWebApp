<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

<<<<<<< Updated upstream
if (!isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

class BookingService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createBooking($data, $clientId) {
        // Validate required fields
        $required = ['class_id', 'trainer_id', 'booking_date', 'start_time', 'end_time'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                return json_encode([
                    'status' => 'error',
                    'message' => "Missing required field: $field"
                ]);
            }
        }

        // Prevent double booking (same client, class, date, time)
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM booking WHERE ClientID = ? AND ClassID = ? AND BookingDate = ? AND StartTime = ?");
        $stmt->execute([$clientId, $data['class_id'], $data['booking_date'], $data['start_time']]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            return json_encode([
                'status' => 'error',
                'message' => 'You have already booked this class at this time.'
            ]);
        }

        // Insert booking
        $stmt = $this->db->prepare("INSERT INTO booking (BookingDate, StartTime, EndTime, ClientID, ClassID, TrainerID) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['booking_date'],
            $data['start_time'],
            $data['end_time'],
            $clientId,
            $data['class_id'],
            $data['trainer_id']
        ]);

        return json_encode([
            'status' => 'success',
            'message' => 'Booking created successfully',
            'booking_id' => $this->db->lastInsertId()
        ]);
    }

    public function getBookings($clientId) {
        $stmt = $this->db->prepare("SELECT * FROM booking WHERE ClientID = ?");
        $stmt->execute([$clientId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode([
            'status' => 'success',
            'bookings' => $bookings
        ]);
    }

    public function cancelBooking($bookingId, $clientId) {
        $stmt = $this->db->prepare("DELETE FROM booking WHERE BookingID = ? AND ClientID = ?");
        $stmt->execute([$bookingId, $clientId]);
        if ($stmt->rowCount() > 0) {
            return json_encode([
                'status' => 'success',
                'message' => 'Booking cancelled successfully'
            ]);
        } else {
            http_response_code(404);
            return json_encode([
                'status' => 'error',
                'message' => 'Booking not found or unauthorized'
=======
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class BookingService {
    private $conn;
    private $schema;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->schema = json_decode(file_get_contents(__DIR__ . '/../schemas/booking_schema.json'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error loading schema: " . json_last_error_msg());
        }
    }

    private function validateSchema($data) {
        error_log("Validating data: " . print_r($data, true));
        
        $required = ['client_id', 'class_id', 'booking_date', 'start_time', 'end_time'];
        $errors = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        // Type validation
        if (isset($data['client_id']) && !is_int($data['client_id'])) {
            $errors[] = "client_id must be an integer";
        }
        
        if (isset($data['class_id']) && !is_int($data['class_id'])) {
            $errors[] = "class_id must be an integer";
        }
        
        // Date format validation
        if (isset($data['booking_date'])) {
            $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
            if (!preg_match($datePattern, $data['booking_date'])) {
                $errors[] = "booking_date must be in YYYY-MM-DD format";
            }
        }
        
        // Time format validation
        if (isset($data['start_time']) && isset($data['end_time'])) {
            $timePattern = '/^\d{2}:\d{2}:\d{2}$/';
            if (!preg_match($timePattern, $data['start_time']) || !preg_match($timePattern, $data['end_time'])) {
                $errors[] = "Time must be in HH:MM:SS format";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        return ['valid' => true];
    }

    public function getBookings($clientId) {
        try {
            $sql = "SELECT b.BookingID, b.BookingDate, b.StartTime, b.EndTime, 
                   c.ClassName, c.ClassType, t.Name AS TrainerName
                   FROM booking b 
                   JOIN class c ON b.ClassID = c.ClassID 
                   JOIN trainer t ON c.TrainerID = t.TrainerID
                   WHERE b.ClientID = ? 
                   ORDER BY b.BookingDate DESC, b.StartTime DESC";
            
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
            error_log("Received booking data: " . print_r($data, true));

            $validation = $this->validateSchema($data);
            if (!$validation['valid']) {
                http_response_code(400);
                return json_encode([
                    'status' => 'error',
                    'message' => 'Invalid booking data',
                    'errors' => $validation['errors']
                ]);
            }

            // Check for time slot availability
            $checkSql = "SELECT COUNT(*) as count FROM booking 
                        WHERE ClassID = ? AND BookingDate = ? 
                        AND ((StartTime <= ? AND EndTime > ?) 
                        OR (StartTime < ? AND EndTime >= ?))";
            
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("isssss", 
                $data['class_id'],
                $data['booking_date'],
                $data['start_time'],
                $data['start_time'],
                $data['end_time'],
                $data['end_time']
            );
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $checkStmt->close();
                return json_encode([
                    'status' => 'error',
                    'message' => 'Time slot already booked'
                ]);
            }
            $checkStmt->close();

            // Create new booking
            $insertSql = "INSERT INTO booking (ClientID, ClassID, BookingDate, StartTime, EndTime) 
                         VALUES (?, ?, ?, ?, ?)";
            
            $insertStmt = $this->conn->prepare($insertSql);
            $insertStmt->bind_param("iisss", 
                $data['client_id'],
                $data['class_id'],
                $data['booking_date'],
                $data['start_time'],
                $data['end_time']
            );
            
            if ($insertStmt->execute()) {
                $bookingId = $this->conn->insert_id;
                $insertStmt->close();
                
                return json_encode([
                    'status' => 'success',
                    'message' => 'Booking created successfully',
                    'booking_id' => $bookingId
                ]);
            } else {
                $error = $insertStmt->error;
                $insertStmt->close();
                throw new Exception("Database error: " . $error);
            }
        } catch (Exception $e) {
            error_log("Error in createBooking: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Error creating booking',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function cancelBooking($bookingId, $clientId) {
        try {
            $sql = "DELETE FROM booking WHERE BookingID = ? AND ClientID = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $bookingId, $clientId);
            
            if ($stmt->execute()) {
                $stmt->close();
                return json_encode([
                    'status' => 'success',
                    'message' => 'Booking cancelled successfully'
                ]);
            } else {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception("Database error: " . $error);
            }
        } catch (Exception $e) {
            error_log("Error in cancelBooking: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Error cancelling booking',
                'error' => $e->getMessage()
>>>>>>> Stashed changes
            ]);
        }
    }
}

// Handle the request
<<<<<<< Updated upstream
$db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$service = new BookingService($db);
$clientId = $_SESSION['client_id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        echo $service->createBooking($data, $clientId);
        break;
    case 'GET':
        echo $service->getBookings($clientId);
        break;
    case 'DELETE':
        $bookingId = $_GET['booking_id'] ?? null;
        if ($bookingId) {
            echo $service->cancelBooking($bookingId, $clientId);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Booking ID is required']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
=======
try {
    $conn = new mysqli('localhost', 'root', '', 'gymwebapp');
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $service = new BookingService($conn);

    // Check if user is logged in
    if (!isset($_SESSION['client_id'])) {
        throw new Exception('User not logged in');
    }

    $clientId = $_SESSION['client_id'];

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            echo $service->getBookings($clientId);
            break;
        case 'POST':
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            if (!$data) {
                throw new Exception('Invalid JSON data received');
            }
            
            // Add client_id from session to the data
            $data['client_id'] = $clientId;
            
            echo $service->createBooking($data);
            break;
        case 'DELETE':
            $bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($bookingId <= 0) {
                throw new Exception('Invalid booking ID');
            }
            
            echo $service->cancelBooking($bookingId, $clientId);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
    }
    
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
>>>>>>> Stashed changes
