<?php
session_start();

// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Response array for JSON output
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $response['message'] = "Connection failed: " . $conn->connect_error;
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get form data
$login_type = $_POST['login_type'] ?? '';
$username_email = $_POST['username_email'] ?? '';
$pass = $_POST['password'] ?? '';

// Validate input
if (empty($login_type) || empty($username_email) || empty($pass)) {
    $response['message'] = "All fields are required.";
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

try {
    if ($login_type === 'admin') {
        $stmt = $conn->prepare("SELECT AdminID, Password FROM admin WHERE Username = ?");
        $stmt->bind_param("s", $username_email);
    } elseif ($login_type === 'trainer') {
        $stmt = $conn->prepare("SELECT TrainerID, Password FROM trainer WHERE Username = ?");
        $stmt->bind_param("s", $username_email);
    } else {
        $stmt = $conn->prepare("SELECT ClientID, Password, Name FROM client WHERE Email = ?");
        $stmt->bind_param("s", $username_email);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $stored_password = $row['Password'];
        if ($login_type === 'admin') {
            $user_id = $row['AdminID'];
        } elseif ($login_type === 'trainer') {
            $user_id = $row['TrainerID'];
        } else {
            $user_id = $row['ClientID'];
            $user_name = $row['Name'];
        }
        $password_matches = ($pass === $stored_password);
        if (!$password_matches && strlen($stored_password) > 20) {
            $password_matches = password_verify($pass, $stored_password);
        }
        if ($password_matches) {
            if ($login_type === 'admin') {
                $_SESSION['admin_id'] = $user_id;
                $_SESSION['username'] = $username_email;
                $response['success'] = true;
                $response['message'] = "Login successful";
                $response['data'] = [
                    'redirect' => 'admin_dashboard.html',
                    'user_type' => 'admin',
                    'username' => $username_email
                ];
            } elseif ($login_type === 'trainer') {
                $_SESSION['trainer_id'] = $user_id;
                $_SESSION['username'] = $username_email;
                $response['success'] = true;
                $response['message'] = "Login successful";
                $response['data'] = [
                    'redirect' => 'trainer_dashboard.html',
                    'user_type' => 'trainer',
                    'username' => $username_email
                ];
            } else {
                $_SESSION['client_id'] = $user_id;
                $_SESSION['email'] = $username_email;
                $_SESSION['name'] = $user_name;
                $response['success'] = true;
                $response['message'] = "Login successful";
                $response['data'] = [
                    'redirect' => 'client_dashboard.html',
                    'user_type' => 'client',
                    'email' => $username_email,
                    'name' => $user_name
                ];
            }
        } else {
            $response['message'] = "Invalid password.";
        }
    } else {
        $response['message'] = "Invalid username/email.";
    }
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = "An error occurred. Please try again.";
}
$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
?>
