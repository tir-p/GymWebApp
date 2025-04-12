<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class SubscriptionService {
    private $db;
    private $schema;

    public function __construct($db) {
        $this->db = $db;
        $this->schema = json_decode(file_get_contents(__DIR__ . '/../schemas/subscription_schema.json'), true);
        // Debug schema loading
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error loading schema: " . json_last_error_msg());
        }
    }

    private function validateSchema($data) {
        // Debug input data
        error_log("Validating data: " . print_r($data, true));
        error_log("Using schema: " . print_r($this->schema, true));

        $validator = new JsonSchema\Validator();
        $validator->validate($data, $this->schema);
        
        if (!$validator->isValid()) {
            $errors = array_map(function($error) {
                return $error['message'] . " at " . $error['property'];
            }, $validator->getErrors());
            error_log("Validation errors: " . print_r($errors, true));
            return ['valid' => false, 'errors' => $errors];
        }
        return ['valid' => true];
    }

    public function getSubscriptions($clientId) {
        try {
            $stmt = $this->db->prepare("SELECT s.SubscriptionID, c.Name AS ClientName, 
                                      pt.PlanName AS PlanTypeName, s.StartDate, s.EndDate, s.Status
                                      FROM subscription s 
                                      JOIN client c ON s.ClientID = c.ClientID 
                                      JOIN plantype pt ON s.PlanTypeID = pt.PlanTypeID 
                                      WHERE s.ClientID = ?");
            $stmt->execute([$clientId]);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_encode([
                'status' => 'success',
                'subscriptions' => $subscriptions
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

    public function createSubscription($data) {
        try {
            // Debug input data
            error_log("Received subscription data: " . print_r($data, true));

            // Ensure IDs are integers
            if (isset($data['client_id'])) {
                $data['client_id'] = (int)$data['client_id'];
            }
            if (isset($data['plan_type_id'])) {
                $data['plan_type_id'] = (int)$data['plan_type_id'];
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
            
            // Debug SQL query
            $sql = "INSERT INTO subscription (ClientID, PlanTypeID, StartDate, EndDate, Status) 
                   VALUES (?, ?, ?, ?, ?)";
            error_log("Executing SQL: " . $sql);
            error_log("With parameters: " . print_r([
                $data['client_id'],
                $data['plan_type_id'],
                $data['start_date'],
                $endDate,
                'active'
            ], true));
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['client_id'],
                $data['plan_type_id'],
                $data['start_date'],
                $endDate,
                'active'
            ]);

            if ($result) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Subscription created successfully',
                    'subscription_id' => $this->db->lastInsertId()
                ]);
            } else {
                $error = $stmt->errorInfo();
                error_log("Database error: " . print_r($error, true));
                throw new Exception("Database error: " . $error[2]);
            }
        } catch (Exception $e) {
            error_log("Error in createSubscription: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'message' => 'Error creating subscription',
                'error' => $e->getMessage()
            ]);
        }
    }
}

// Handle the request
try {
    $db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $service = new SubscriptionService($db);

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (!isset($_SESSION['client_id'])) {
                throw new Exception('User not logged in');
            }
            echo $service->getSubscriptions($_SESSION['client_id']);
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Invalid JSON data received');
            }
            
            // Always use client_id from session for security
            if (!isset($_SESSION['client_id'])) {
                throw new Exception('User not logged in');
            }
            $data['client_id'] = $_SESSION['client_id'];
            
            echo $service->createSubscription($data);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
    }
} catch (Exception $e) {
    error_log("Error in subscription service: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
} 