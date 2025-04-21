<?php
// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Set all ScheduleDates to today
$updateSql = "UPDATE class SET ScheduleDate = CURDATE()";
$conn->query($updateSql);


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
$sql = "SELECT 
    c.ClassID as id, 
    c.ClassName as name, 
    c.ClassType as class_type,
    DATE_FORMAT(c.ScheduleDate, '%Y-%m-%d') as schedule_date,
    TIME_FORMAT(c.StartTime, '%H:%i') as start_time,
    TIME_FORMAT(c.EndTime, '%H:%i') as end_time,
    c.TrainerID as trainer_id,
    t.Name as trainer_name
FROM class c
LEFT JOIN trainer t ON c.TrainerID = t.TrainerID
ORDER BY c.ScheduleDate, c.StartTime";

$result = $conn->query($sql);

if (!$result) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Query failed: " . $conn->error,
        'data' => null
    ]);
    exit();
}

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