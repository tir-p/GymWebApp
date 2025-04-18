<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class ReviewService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getReviews() {
        try {
            $sql = "SELECT f.FeedbackID, f.Rating, f.Comments, f.ClientID,
                   t.Name AS TrainerName, t.TrainerID,
                   c.Name AS ClientName
                   FROM feedback f
                   JOIN trainer t ON f.TrainerID = t.TrainerID
                   JOIN client c ON f.ClientID = c.ClientID
                   ORDER BY f.FeedbackID DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $reviews = [];
            
            while ($row = $result->fetch_assoc()) {
                $reviews[] = $row;
            }
            
            $stmt->close();

            return json_encode([
                'status' => 'success',
                'reviews' => $reviews
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Database error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getTrainers() {
        try {
            $sql = "SELECT TrainerID, Name FROM trainer ORDER BY Name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $trainers = [];
            
            while ($row = $result->fetch_assoc()) {
                $trainers[] = $row;
            }
            
            $stmt->close();

            return json_encode([
                'status' => 'success',
                'trainers' => $trainers
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Database error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function createReview($data) {
        try {
            // Validate required fields
            if (!isset($data['trainer_id']) || !isset($data['rating']) || !isset($data['review'])) {
                throw new Exception('Missing required fields');
            }

            // Validate rating
            $rating = (int)$data['rating'];
            if ($rating < 1 || $rating > 5) {
                throw new Exception('Invalid rating value');
            }

            // Get client ID from session
            if (!isset($_SESSION['client_id'])) {
                throw new Exception('User not logged in');
            }
            $clientId = $_SESSION['client_id'];

            // Insert the review
            $sql = "INSERT INTO feedback (ClientID, TrainerID, Rating, Comments)
                   VALUES (?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iiis", 
                $clientId,
                $data['trainer_id'],
                $rating,
                $data['review']
            );
            
            if ($stmt->execute()) {
                $feedbackId = $this->conn->insert_id;
                $stmt->close();
                
                return json_encode([
                    'status' => 'success',
                    'message' => 'Review submitted successfully',
                    'feedback_id' => $feedbackId
                ]);
            } else {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception("Database error: " . $error);
            }
        } catch (Exception $e) {
            error_log("Error in createReview: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Error submitting review: ' . $e->getMessage(),
                'error' => $e->getMessage()
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
    
    $service = new ReviewService($conn);

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] === 'trainers') {
                echo $service->getTrainers();
            } else {
                echo $service->getReviews();
            }
            break;
        case 'POST':
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            if (!$data) {
                throw new Exception('Invalid JSON data received');
            }
            
            echo $service->createReview($data);
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
    error_log("Error in review service: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
} 