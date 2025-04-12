<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

class BookingService {
    private $db;
    private $schema;

    public function __construct($db) {
        $this->db = $db;
        $this->schema = json_decode(file_get_contents(__DIR__ . '/../schemas/booking_schema.json'), true);
    }

    private function validateSchema($data) {
        $validator = new JsonSchema\Validator();
        $validator->validate($data, $this->schema);
        
        if (!$validator->isValid()) {
            $errors = array_map(function($error) {
                return $error['message'];
            }, $validator->getErrors());
            return ['valid' => false, 'errors' => $errors];
        }
        return ['valid' => true];
    }

    public function createBooking($data) {
        $validation = $this->validateSchema($data);
        if (!$validation['valid']) {
            http_response_code(400);
            return json_encode([
                'status' => 'error',
                'message' => 'Invalid booking data',
                'errors' => $validation['errors']
            ]);
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO bookings (user_id, class_id, booking_date, time_slot, status, notes) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['user_id'],
                $data['class_id'],
                $data['booking_date'],
                $data['time_slot'],
                $data['status'] ?? 'pending',
                $data['notes'] ?? null
            ]);

            return json_encode([
                'status' => 'success',
                'message' => 'Booking created successfully',
                'booking_id' => $this->db->lastInsertId()
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Database error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getBookings($userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM bookings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_encode([
                'status' => 'success',
                'bookings' => $bookings
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Database error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function cancelBooking($bookingId, $userId) {
        try {
            $stmt = $this->db->prepare("UPDATE bookings SET status = 'cancelled' 
                                      WHERE id = ? AND user_id = ?");
            $stmt->execute([$bookingId, $userId]);

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
                ]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Database error',
                'error' => $e->getMessage()
            ]);
        }
    }
}

// Handle the request
$db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
$service = new BookingService($db);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        echo $service->createBooking($data);
        break;
    case 'GET':
        $userId = $_GET['user_id'] ?? null;
        if ($userId) {
            echo $service->getBookings($userId);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'User ID is required'
            ]);
        }
        break;
    case 'DELETE':
        $bookingId = $_GET['booking_id'] ?? null;
        $userId = $_GET['user_id'] ?? null;
        if ($bookingId && $userId) {
            echo $service->cancelBooking($bookingId, $userId);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Booking ID and User ID are required'
            ]);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed'
        ]);
} 