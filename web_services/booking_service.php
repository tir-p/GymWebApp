<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

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
            ]);
        }
    }
}

// Handle the request
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
