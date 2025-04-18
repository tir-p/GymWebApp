<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

class RegisterService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function register($data) {
        // Validate required fields
        $required = ['email', 'password', 'full_name', 'phone', 'address'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                return json_encode([
                    'status' => 'error',
                    'message' => "Missing required field: $field"
                ]);
            }
        }

        // Check for duplicate email
        $stmt = $this->db->prepare("SELECT 1 FROM client WHERE Email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            return json_encode([
                'status' => 'error',
                'message' => 'Email already exists'
            ]);
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Insert into client table
        $stmt = $this->db->prepare(
            "INSERT INTO client (Name, Email, Password, Phone, Address, Role) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['full_name'],
            $data['email'],
            $hashedPassword,
            $data['phone'],
            $data['address'],
            'Member' // Default role
        ]);

        return json_encode([
            'status' => 'success',
            'message' => 'Registration successful',
            'client_id' => $this->db->lastInsertId()
        ]);
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $service = new RegisterService($db);
    echo $service->register($data);
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
