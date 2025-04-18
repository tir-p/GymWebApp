<?php
session_start();
header('Content-Type: application/json');

// Only allow admins
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- GET: Return all dashboard data ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Users
    $users = $db->query("SELECT * FROM client")->fetchAll(PDO::FETCH_ASSOC);

    // Plans
    $plans = $db->query("SELECT * FROM plantype")->fetchAll(PDO::FETCH_ASSOC);

    // Classes (with trainer name)
    $classes = $db->query(
        "SELECT c.ClassID, c.ClassName, c.TrainerID, t.Name AS TrainerName, c.StartTime
         FROM class c
         LEFT JOIN trainer t ON c.TrainerID = t.TrainerID"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Trainers
    $trainers = $db->query("SELECT * FROM trainer")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'users' => $users,
        'plans' => $plans,
        'classes' => $classes,
        'trainers' => $trainers
    ]);
    exit;
}

// --- POST: Handle admin actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- USER MANAGEMENT ---
    if ($action === 'verify_user') {
        $clientId = $_POST['client_id'] ?? null;
        if (!$clientId) { http_response_code(400); exit; }
        $stmt = $db->prepare("UPDATE client SET Role = 'Verified' WHERE ClientID = ?");
        $stmt->execute([$clientId]);
        echo json_encode(['status' => 'success', 'message' => 'User verified']);
        exit;
    }
    if ($action === 'reset_password') {
        $clientId = $_POST['client_id'] ?? null;
        if (!$clientId) { http_response_code(400); exit; }
        $newPassword = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE client SET Password = ? WHERE ClientID = ?");
        $stmt->execute([$newPassword, $clientId]);
        echo json_encode(['status' => 'success', 'message' => 'Password reset to "password123"']);
        exit;
    }
    if ($action === 'edit_user') {
        $clientId = $_POST['client_id'] ?? null;
        $name = $_POST['name'] ?? null;
        $email = $_POST['email'] ?? null;
        $phone = $_POST['phone'] ?? null;
        if (!$clientId || !$name || !$email) { http_response_code(400); exit; }
        $stmt = $db->prepare("UPDATE client SET Name=?, Email=?, Phone=? WHERE ClientID=?");
        $stmt->execute([$name, $email, $phone, $clientId]);
        echo json_encode(['status' => 'success', 'message' => 'User updated']);
        exit;
    }
    if ($action === 'delete_user') {
        $clientId = $_POST['client_id'] ?? null;
        if (!$clientId) { http_response_code(400); exit; }
        $stmt = $db->prepare("DELETE FROM client WHERE ClientID = ?");
        $stmt->execute([$clientId]);
        echo json_encode(['status' => 'success', 'message' => 'User deleted']);
        exit;
    }

    // --- PLAN MANAGEMENT ---
    if ($action === 'edit_plan') {
        $planId = $_POST['plan_type_id'] ?? null;
        $name = $_POST['plan_name'] ?? null;
        $price = $_POST['price'] ?? null;
        if (!$planId || !$name || !$price) { http_response_code(400); exit; }
        $stmt = $db->prepare("UPDATE plantype SET PlanName=?, Price=? WHERE PlanTypeID=?");
        $stmt->execute([$name, $price, $planId]);
        echo json_encode(['status' => 'success', 'message' => 'Plan updated']);
        exit;
    }
    if ($action === 'delete_plan') {
        $planId = $_POST['plan_type_id'] ?? null;
        if (!$planId) { http_response_code(400); exit; }
        $stmt = $db->prepare("DELETE FROM plantype WHERE PlanTypeID = ?");
        $stmt->execute([$planId]);
        echo json_encode(['status' => 'success', 'message' => 'Plan deleted']);
        exit;
    }

    // --- CLASS MANAGEMENT ---
    if ($action === 'edit_class') {
        $classId = $_POST['class_id'] ?? null;
        $name = $_POST['class_name'] ?? null;
        $trainerId = $_POST['trainer_id'] ?? null;
        $startTime = $_POST['start_time'] ?? null;
        if (!$classId || !$name || !$trainerId || !$startTime) { http_response_code(400); exit; }
        $stmt = $db->prepare("UPDATE class SET ClassName=?, TrainerID=?, StartTime=? WHERE ClassID=?");
        $stmt->execute([$name, $trainerId, $startTime, $classId]);
        echo json_encode(['status' => 'success', 'message' => 'Class updated']);
        exit;
    }

    // --- TRAINER MANAGEMENT ---
    if ($action === 'edit_trainer') {
        $trainerId = $_POST['trainer_id'] ?? null;
        $name = $_POST['name'] ?? null;
        $expertise = $_POST['expertise'] ?? null;
        if (!$trainerId || !$name || !$expertise) { http_response_code(400); exit; }
        $stmt = $db->prepare("UPDATE trainer SET Name=?, Expertise=? WHERE TrainerID=?");
        $stmt->execute([$name, $expertise, $trainerId]);
        echo json_encode(['status' => 'success', 'message' => 'Trainer updated']);
        exit;
    }
    if ($action === 'view_feedback') {
        $trainerId = $_POST['trainer_id'] ?? null;
        if (!$trainerId) { http_response_code(400); exit; }
        $stmt = $db->prepare("SELECT f.*, c.Name as ClientName FROM feedback f JOIN client c ON f.ClientID = c.ClientID WHERE f.TrainerID = ?");
        $stmt->execute([$trainerId]);
        $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'feedback' => $feedback]);
        exit;
    }

    // Unknown action
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit;
}

// If not a GET or POST with action, return error
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
