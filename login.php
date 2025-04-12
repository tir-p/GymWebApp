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
$login_type = $_POST['login_type'] ?? ''; // 'admin', 'client', or 'trainer'
$username_email = $_POST['username_email'] ?? ''; // Username for admin or Email for client
$pass = $_POST['password'] ?? ''; // Password input

// Validate input
if (empty($login_type) || empty($username_email) || empty($pass)) {
    $response['message'] = "All fields are required.";
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

try {
    if ($login_type === 'admin') {
        // Prepare and bind for admin login
        $stmt = $conn->prepare("SELECT AdminID, Password FROM admin WHERE Username = ?");
        $stmt->bind_param("s", $username_email);
    } elseif ($login_type === 'trainer') {
        // Prepare and bind for trainer login
        $stmt = $conn->prepare("SELECT TrainerID, Password FROM trainer WHERE Username = ?");
        $stmt->bind_param("s", $username_email);
    } else {
        // Prepare and bind for client login
        $stmt = $conn->prepare("SELECT ClientID, Password, Name FROM client WHERE Email = ?");
        $stmt->bind_param("s", $username_email);
    }

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $stored_password = $row['Password'];
        
        // Get the user ID based on login type
        if ($login_type === 'admin') {
            $user_id = $row['AdminID'];
        } elseif ($login_type === 'trainer') {
            $user_id = $row['TrainerID'];
        } else {
            $user_id = $row['ClientID'];
            $user_name = $row['Name']; // Get the client's name
        }
        
        // Check if the password is correct
        // Try direct comparison first for plain text passwords
        $password_matches = ($pass === $stored_password);
        
        // If direct comparison fails, try password_verify for hashed passwords
        if (!$password_matches && strlen($stored_password) > 20) {
            $password_matches = password_verify($pass, $stored_password);
        }
        
        if ($password_matches) {
            // Password is correct, set session variables
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
            // Invalid password
            $response['message'] = "Invalid password.";
        }
    } else {
        // User does not exist
        $response['message'] = "Invalid username/email.";
    }

    // Close the statement
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = "An error occurred. Please try again.";
}

// Close connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>