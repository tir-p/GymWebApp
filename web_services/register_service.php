<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

class RegisterService {
    private $db;
    private $schema;

    public function __construct($db) {
        $this->db = $db;
        $this->schema = json_decode(file_get_contents(__DIR__ . '/../schemas/user_schema.json'), true);
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

    public function register($data) {
        $validation = $this->validateSchema($data);
        if (!$validation['valid']) {
            http_response_code(400);
            return json_encode([
                'status' => 'error',
                'message' => 'Invalid input data',
                'errors' => $validation['errors']
            ]);
        }

        try {
            // Hash password
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert into database
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password, full_name, phone, role) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['username'],
                $data['email'],
                $data['password'],
                $data['full_name'],
                $data['phone'],
                $data['role'] ?? 'client'
            ]);

            return json_encode([
                'status' => 'success',
                'message' => 'User registered successfully',
                'user_id' => $this->db->lastInsertId()
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
    $service = new RegisterService($db);
    echo $service->register($data);
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
} 