<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

class ReviewService {
    private $db;
    private $schema;

    public function __construct($db) {
        $this->db = $db;
        $this->schema = json_decode(file_get_contents(__DIR__ . '/../schemas/review_schema.json'), true);
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

    public function createReview($data) {
        $validation = $this->validateSchema($data);
        if (!$validation['valid']) {
            http_response_code(400);
            return json_encode([
                'status' => 'error',
                'message' => 'Invalid review data',
                'errors' => $validation['errors']
            ]);
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO reviews (user_id, rating, comment, trainer_id, class_id, date_posted) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['user_id'],
                $data['rating'],
                $data['comment'],
                $data['trainer_id'] ?? null,
                $data['class_id'] ?? null,
                $data['date_posted'] ?? date('Y-m-d H:i:s')
            ]);

            return json_encode([
                'status' => 'success',
                'message' => 'Review created successfully',
                'review_id' => $this->db->lastInsertId()
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

    public function getReviews($filters = []) {
        try {
            $query = "SELECT r.*, u.username as user_name 
                     FROM reviews r 
                     JOIN users u ON r.user_id = u.id";
            $params = [];
            
            if (!empty($filters)) {
                $conditions = [];
                foreach ($filters as $key => $value) {
                    if (in_array($key, ['trainer_id', 'class_id'])) {
                        $conditions[] = "r.$key = ?";
                        $params[] = $value;
                    }
                }
                if (!empty($conditions)) {
                    $query .= " WHERE " . implode(" AND ", $conditions);
                }
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_encode([
                'status' => 'success',
                'reviews' => $reviews
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
$db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
$service = new ReviewService($db);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        echo $service->createReview($data);
        break;
    case 'GET':
        $filters = [];
        if (isset($_GET['trainer_id'])) $filters['trainer_id'] = $_GET['trainer_id'];
        if (isset($_GET['class_id'])) $filters['class_id'] = $_GET['class_id'];
        echo $service->getReviews($filters);
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed'
        ]);
} 