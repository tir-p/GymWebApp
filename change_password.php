<?php
// change_password.php
// Accepts: user_type (admin/client/trainer), identifier (email or username), current_password, new_password
// Returns: JSON { success: bool, message: string }

header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

$response = [
    'success' => false,
    'message' => ''
];

// Get POST data
$user_type = $_POST['user_type'] ?? '';
$identifier = $_POST['identifier'] ?? '';
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($user_type) || empty($identifier) || empty($current_password) || empty($new_password)) {
    $response['message'] = 'All fields are required.';
    echo json_encode($response);
    exit();
}

// Validate new password length
if (strlen($new_password) < 6) {
    $response['message'] = 'New password must be at least 6 characters long.';
    echo json_encode($response);
    exit();
}

// Connect to DB
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    $response['message'] = 'Connection failed: ' . $conn->connect_error;
    echo json_encode($response);
    exit();
}

// Determine table and fields
if ($user_type === 'admin') {
    $table = 'admin';
    $id_field = 'Username';
    $pw_field = 'Password';
    $id_param = $identifier;
} elseif ($user_type === 'trainer') {
    $table = 'trainer';
    $id_field = 'Username';
    $pw_field = 'Password';
    $id_param = $identifier;
} else {
    $table = 'client';
    $id_field = 'Email';
    $pw_field = 'Password';
    $id_param = $identifier;
}

// Fetch current password hash
$stmt = $conn->prepare("SELECT $pw_field FROM $table WHERE $id_field = ? LIMIT 1");
$stmt->bind_param('s', $id_param);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    $response['message'] = 'User not found.';
    echo json_encode($response);
    $stmt->close();
    $conn->close();
    exit();
}
$row = $result->fetch_assoc();
$stored_password = $row[$pw_field];

// Check current password (plain or hashed)
$password_matches = ($current_password === $stored_password);
if (!$password_matches && strlen($stored_password) > 20) {
    $password_matches = password_verify($current_password, $stored_password);
}
if (!$password_matches) {
    $response['message'] = 'Current password is incorrect.';
    echo json_encode($response);
    $stmt->close();
    $conn->close();
    exit();
}

// Hash new password
$new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update password
$update_stmt = $conn->prepare("UPDATE $table SET $pw_field = ? WHERE $id_field = ?");
$update_stmt->bind_param('ss', $new_hashed_password, $id_param);
if ($update_stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Password changed successfully.';
} else {
    $response['message'] = 'Failed to update password.';
}
$update_stmt->close();
$stmt->close();
$conn->close();
echo json_encode($response); 