<?php
// dashboard.php (this handles the backend logic)

// Include the database connection file
include 'connect.php';

// Fetch users for the User Management section
$userQuery = $pdo->query("SELECT ClientID, Name, Email, Role FROM client");
$users = $userQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch membership plans for the Membership Management section
$planQuery = $pdo->query("SELECT PlanTypeID, PlanName, Price FROM plantype");
$plans = $planQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch class schedules for the Class Schedule Management section
$classQuery = $pdo->query("SELECT c.ClassID, c.ClassName, t.Name AS TrainerName, c.StartTime FROM class c JOIN trainer t ON c.TrainerID = t.TrainerID");
$classes = $classQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch trainers for the Trainer Management section
$trainerQuery = $pdo->query("SELECT TrainerID, Name, Expertise FROM trainer");
$trainers = $trainerQuery->fetchAll(PDO::FETCH_ASSOC);

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
