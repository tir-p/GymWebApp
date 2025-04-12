<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

class SubscriptionService {
    private $db;
    private $schema;

    public function __construct($db) {
        $this->db = $db;
        $this->schema = json_decode(file_get_contents(__DIR__ . '/../schemas/subscription_schema.json'), true);
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
        $validation = $this->validateSchema($data);
        if (!$validation['valid']) {
            http_response_code(400);
            return json_encode([
                'status' => 'error',
                'message' => 'Invalid subscription data',
                'errors' => $validation['errors']
            ]);
        }

        try {
            // Calculate end date (30 days from start date)
            $endDate = date('Y-m-d', strtotime($data['start_date'] . ' + 30 days'));
            
            $stmt = $this->db->prepare("INSERT INTO subscription (ClientID, PlanTypeID, StartDate, EndDate, Status) 
                                      VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([
                $data['client_id'],
                $data['plan_type_id'],
                $data['start_date'],
                $endDate
            ]);

            return json_encode([
                'status' => 'success',
                'message' => 'Subscription created successfully',
                'subscription_id' => $this->db->lastInsertId()
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
}

// Handle the request
if (!isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized - Please log in'
    ]);
    exit();
}

$db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
$service = new SubscriptionService($db);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo $service->getSubscriptions($_SESSION['client_id']);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
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