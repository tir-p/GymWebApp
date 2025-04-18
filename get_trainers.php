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

// Query to fetch trainers
$sql = "SELECT 
    t.TrainerID as id, 
    t.Name as name, 
    t.Expertise as expertise,
    t.AvailableSlots as available_slots
FROM trainer t
WHERE t.AvailableSlots > 0
ORDER BY t.Name";
$result = $conn->query($sql);

// Process results
$trainers = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $trainers[] = $row;
    }
}

// Prepare response
$response = [
    'success' => true,
    'message' => 'Trainers retrieved successfully',
    'data' => $trainers
];

// Close connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 