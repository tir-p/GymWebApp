<?php
// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Connection failed: " . $conn->connect_error,
        'data' => null
    ]);
    exit();
}

// Query to fetch classes
$sql = "SELECT ClassID as id, ClassName as name, Description as description FROM class";
$result = $conn->query($sql);

// Process results
$classes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Prepare response
$response = [
    'success' => true,
    'message' => 'Classes retrieved successfully',
    'data' => $classes
];

// Close connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 