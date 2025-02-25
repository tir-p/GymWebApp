<?php
// dashboard.php (this handles the backend logic)

// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch users for the User Management section
$userQuery = $conn->query("SELECT ClientID, Name, Email, Role FROM client");
$users = [];
while ($row = $userQuery->fetch_assoc()) {
    $users[] = $row;
}

// Fetch membership plans for the Membership Management section
$planQuery = $conn->query("SELECT PlanTypeID, PlanName, Price FROM plantype");
$plans = [];
while ($row = $planQuery->fetch_assoc()) {
    $plans[] = $row;
}

// Fetch class schedules for the Class Schedule Management section
$classQuery = $conn->query("SELECT c.ClassID, c.ClassName, t.Name AS TrainerName, c.StartTime FROM class c JOIN trainer t ON c.TrainerID = t.TrainerID");
$classes = [];
while ($row = $classQuery->fetch_assoc()) {
    $classes[] = $row;
}

// Fetch trainers for the Trainer Management section
$trainerQuery = $conn->query("SELECT TrainerID, Name, Expertise FROM trainer");
$trainers = [];
while ($row = $trainerQuery->fetch_assoc()) {
    $trainers[] = $row;
}

// Package all data into an array
$data = [
    'users' => $users,
    'plans' => $plans,
    'classes' => $classes,
    'trainers' => $trainers,
];

// Return the data in JSON format
header('Content-Type: application/json');
echo json_encode($data);

// Close connection
$conn->close();

