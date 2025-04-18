<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class ClassService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getClasses() {
        try {
            $sql = "SELECT c.ClassID, c.ClassName, c.ClassType, c.Schedule, 
                   c.StartTime, c.EndTime, c.Capacity, c.CurrentEnrollment,
                   t.Name AS TrainerName, t.TrainerID, t.Specialization
                   FROM class c 
                   JOIN trainer t ON c.TrainerID = t.TrainerID
                   WHERE c.Status = 'active'
                   ORDER BY c.ClassName";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $classes = [];
            
            while ($row = $result->fetch_assoc()) {
                // Convert schedule string to array of days
                $row['Schedule'] = explode(',', $row['Schedule']);
                $classes[] = $row;
            }
            
            $stmt->close();

            return json_encode([
                'status' => 'success',
                'classes' => $classes
            ]);
        } catch (Exception $e) {
            error_log("Error in getClasses: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Database error',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getClassSchedule($classId) {
        try {
            $sql = "SELECT c.ClassID, c.ClassName, c.Schedule, 
                   c.StartTime, c.EndTime, c.Capacity, c.CurrentEnrollment
                   FROM class c
                   WHERE c.ClassID = ? AND c.Status = 'active'";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $classId);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $class = $result->fetch_assoc();
            
            if ($class) {
                // Convert schedule string to array of days
                $class['Schedule'] = explode(',', $class['Schedule']);
            }
            
            $stmt->close();

            return json_encode([
                'status' => 'success',
                'class' => $class
            ]);
        } catch (Exception $e) {
            error_log("Error in getClassSchedule: " . $e->getMessage());
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
    
    $service = new ClassService($conn);

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['class_id'])) {
                echo $service->getClassSchedule($_GET['class_id']);
            } else {
                echo $service->getClasses();
            }
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
    error_log("Error in class service: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
}
?> 