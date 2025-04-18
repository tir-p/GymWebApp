<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class TrainerService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getTrainers() {
        try {
            $sql = "SELECT t.TrainerID, t.Name, t.Specialization, t.Experience
                   FROM trainer t
                   WHERE t.Status = 'active'
                   ORDER BY t.Name";
            
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
            error_log("Error in getTrainers: " . $e->getMessage());
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
try {
    // Create a connection using mysqli
    $conn = new mysqli('localhost', 'root', '', 'gymwebapp');
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $service = new TrainerService($conn);

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            echo $service->getTrainers();
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
    error_log("Error in trainer service: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
}
?> 