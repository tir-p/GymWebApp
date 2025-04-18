<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

if (!isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

class SubscriptionService {
    private $db;

<<<<<<< Updated upstream
    public function __construct($db) {
        $this->db = $db;
=======
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
>>>>>>> Stashed changes
    }

    public function getSubscriptions($clientId) {
        $stmt = $this->db->prepare("SELECT s.SubscriptionID, c.Name AS ClientName, pt.PlanName AS PlanTypeName, s.StartDate, s.EndDate, s.Status
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
    }

    public function createSubscription($data, $clientId) {
        // Validate required fields
        $required = ['plan_type_id', 'start_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                return json_encode([
                    'status' => 'error',
                    'message' => "Missing required field: $field"
                ]);
            }
<<<<<<< Updated upstream
=======

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
>>>>>>> Stashed changes
        }

        // Cancel existing active subscriptions
        $stmt = $this->db->prepare("UPDATE subscription SET Status = 'inactive' WHERE ClientID = ? AND Status = 'active'");
        $stmt->execute([$clientId]);

        // Calculate end date (30 days from start)
        $endDate = date('Y-m-d', strtotime($data['start_date'] . ' + 30 days'));
        $status = 'active';

        // Insert new subscription
        $stmt = $this->db->prepare("INSERT INTO subscription (ClientID, PlanTypeID, StartDate, EndDate, Status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $clientId,
            $data['plan_type_id'],
            $data['start_date'],
            $endDate,
            $status
        ]);

        return json_encode([
            'status' => 'success',
            'message' => 'Subscription created successfully',
            'subscription_id' => $this->db->lastInsertId()
        ]);
    }
}

// Handle the request
$db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$service = new SubscriptionService($db);
$clientId = $_SESSION['client_id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo $service->getSubscriptions($clientId);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        echo $service->createSubscription($data, $clientId);
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
