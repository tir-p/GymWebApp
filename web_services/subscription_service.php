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

    public function __construct($db) {
        $this->db = $db;
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
