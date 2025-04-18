<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class SubscriptionService {
    private $conn;
    private $schema;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->schema = json_decode(file_get_contents(__DIR__ . '/../schemas/subscription_schema.json'), true);
        // Debug schema loading
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error loading schema: " . json_last_error_msg());
        }
    }

    private function validateSchema($data) {
        // Debug input data
        error_log("Validating data: " . print_r($data, true));
        
        // Validate required fields directly for clearer errors
        $required = ['client_id', 'plan_type_id', 'start_date'];
        $errors = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        // Type validation
        if (isset($data['client_id']) && !is_int($data['client_id'])) {
            $errors[] = "client_id must be an integer, got " . gettype($data['client_id']);
        }
        
        if (isset($data['plan_type_id']) && !is_int($data['plan_type_id'])) {
            $errors[] = "plan_type_id must be an integer, got " . gettype($data['plan_type_id']);
        }
        
        // Value validation
        if (isset($data['client_id']) && $data['client_id'] < 1) {
            $errors[] = "client_id must be at least 1";
        }
        
        if (isset($data['plan_type_id']) && $data['plan_type_id'] < 1) {
            $errors[] = "plan_type_id must be at least 1";
        }
        
        // Date format validation
        if (isset($data['start_date'])) {
            $datePattern = '/^\d{4}-\d{2}-\d{2}$/'; // YYYY-MM-DD
            if (!preg_match($datePattern, $data['start_date'])) {
                $errors[] = "start_date must be in YYYY-MM-DD format";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        return ['valid' => true];
    }

    public function getSubscriptions($clientId) {
        try {
            $sql = "SELECT s.SubscriptionID, c.Name AS ClientName, 
                   pt.PlanName AS PlanTypeName, s.StartDate, s.EndDate, s.Status
                   FROM subscription s 
                   JOIN client c ON s.ClientID = c.ClientID 
                   JOIN plantype pt ON s.PlanTypeID = pt.PlanTypeID 
                   WHERE s.ClientID = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $clientId);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $subscriptions = [];
            
            while ($row = $result->fetch_assoc()) {
                $subscriptions[] = $row;
            }
            
            $stmt->close();

            return json_encode([
                'status' => 'success',
                'subscriptions' => $subscriptions
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

    public function createSubscription($data) {
        try {
            // Debug input data with more detail
            error_log("Received subscription data (raw): " . file_get_contents('php://input'));
            error_log("Received subscription data (parsed): " . print_r($data, true));

            // Ensure IDs are integers
            if (isset($data['client_id'])) {
                $data['client_id'] = (int)$data['client_id'];
            } else {
                throw new Exception('client_id is missing');
            }
            
            if (isset($data['plan_type_id'])) {
                $data['plan_type_id'] = (int)$data['plan_type_id'];
            } else {
                throw new Exception('plan_type_id is missing');
            }
            
            if (!isset($data['start_date'])) {
                throw new Exception('start_date is missing');
            }

            $validation = $this->validateSchema($data);
            if (!$validation['valid']) {
                http_response_code(400);
                return json_encode([
                    'status' => 'error',
                    'message' => 'Invalid subscription data',
                    'errors' => $validation['errors'],
                    'received_data' => $data
                ]);
            }

            // Calculate end date (30 days from start date)
            $endDate = date('Y-m-d', strtotime($data['start_date'] . ' + 30 days'));
            
            // Check for existing active subscription
            $checkSql = "SELECT SubscriptionID FROM subscription WHERE ClientID = ? AND Status = 'active'";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("i", $data['client_id']);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing subscription
                $row = $result->fetch_assoc();
                $subscriptionId = $row['SubscriptionID'];
                
                $updateSql = "UPDATE subscription SET 
                            PlanTypeID = ?, 
                            StartDate = ?, 
                            EndDate = ?,
                            Status = 'active'
                            WHERE SubscriptionID = ?";
                
                $updateStmt = $this->conn->prepare($updateSql);
                $updateStmt->bind_param("issi", 
                    $data['plan_type_id'],
                    $data['start_date'],
                    $endDate,
                    $subscriptionId
                );
                
                if ($updateStmt->execute()) {
                    $updateStmt->close();
                    return json_encode([
                        'status' => 'success',
                        'message' => 'Subscription updated successfully',
                        'subscription_id' => $subscriptionId
                    ]);
                } else {
                    $error = $updateStmt->error;
                    $updateStmt->close();
                    throw new Exception("Database error: " . $error);
                }
            } else {
                // Create new subscription
                $insertSql = "INSERT INTO subscription (ClientID, PlanTypeID, StartDate, EndDate, Status) 
                            VALUES (?, ?, ?, ?, 'active')";
                
                $insertStmt = $this->conn->prepare($insertSql);
                $insertStmt->bind_param("iiss", 
                    $data['client_id'],
                    $data['plan_type_id'],
                    $data['start_date'],
                    $endDate
                );
                
                if ($insertStmt->execute()) {
                    $subscriptionId = $this->conn->insert_id;
                    $insertStmt->close();
                    
                    return json_encode([
                        'status' => 'success',
                        'message' => 'Subscription created successfully',
                        'subscription_id' => $subscriptionId
                    ]);
                } else {
                    $error = $insertStmt->error;
                    $insertStmt->close();
                    throw new Exception("Database error: " . $error);
                }
            }
        } catch (Exception $e) {
            error_log("Error in createSubscription: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Error creating subscription: ' . $e->getMessage(),
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
    
    $service = new SubscriptionService($conn);

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (!isset($_SESSION['client_id'])) {
                throw new Exception('User not logged in');
            }
            echo $service->getSubscriptions($_SESSION['client_id']);
            break;
        case 'POST':
            $rawInput = file_get_contents('php://input');
            error_log("Raw POST input: " . $rawInput);
            
            $data = json_decode($rawInput, true);
            if (!$data) {
                $jsonError = json_last_error_msg();
                error_log("JSON decode error: " . $jsonError);
                throw new Exception('Invalid JSON data received: ' . $jsonError);
            }
            
            error_log("Decoded JSON data: " . print_r($data, true));
            
            // Prioritize session client_id for security, but allow directly provided client_id for testing
            if (isset($_SESSION['client_id'])) {
                $data['client_id'] = (int)$_SESSION['client_id'];
                error_log("Using client_id from session: " . $_SESSION['client_id']);
            } else if (!isset($data['client_id'])) {
                // For testing only - in production, always require session login
                error_log("No client_id in session or request data");
                throw new Exception('User not logged in and no client_id provided');
            } else {
                error_log("Using client_id from request: " . $data['client_id']);
                // Make sure it's an integer
                $data['client_id'] = (int)$data['client_id'];
            }
            
            echo $service->createSubscription($data);
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
    error_log("Error in subscription service: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
} 