<?php
session_start();
header('Content-Type: application/json');

// Debug session
error_log("Session data: " . print_r($_SESSION, true));

// Only allow admins
if (!isset($_SESSION['admin_id'])) {
    error_log("Unauthorized access attempt - no admin_id in session");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Please log in as admin']);
    exit;
}

try {
    $db = new PDO('mysql:host=localhost;dbname=gymwebapp', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// --- GET: Return all dashboard data ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
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
            'status' => 'success',
            'users' => $users,
            'plans' => $plans,
            'classes' => $classes,
            'trainers' => $trainers
        ]);
    } catch (PDOException $e) {
        error_log("Error fetching dashboard data: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error fetching dashboard data']);
    }
    exit;
}

// --- POST: Handle admin actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    error_log("Admin action requested: " . $action);

    try {
        // --- USER MANAGEMENT ---
        if ($action === 'verify_user') {
            $clientId = $_POST['client_id'] ?? null;
            if (!$clientId) { 
                throw new Exception('Client ID is required');
            }
            $stmt = $db->prepare("UPDATE client SET Role = 'Verified' WHERE ClientID = ?");
            $stmt->execute([$clientId]);
            echo json_encode(['status' => 'success', 'message' => 'User verified']);
            exit;
        }

        if ($action === 'reset_password') {
            $clientId = $_POST['client_id'] ?? null;
            if (!$clientId) { 
                throw new Exception('Client ID is required');
            }
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
            
            if (!$clientId || !$name || !$email) { 
                throw new Exception('Client ID, name, and email are required');
            }
            
            $stmt = $db->prepare("UPDATE client SET Name = ?, Email = ?, Phone = ? WHERE ClientID = ?");
            $stmt->execute([$name, $email, $phone, $clientId]);
            echo json_encode(['status' => 'success', 'message' => 'User updated successfully']);
            exit;
        }

        if ($action === 'delete_user') {
            $clientId = $_POST['client_id'] ?? null;
            if (!$clientId) { 
                throw new Exception('Client ID is required');
            }
            
            // First delete related records
            $db->beginTransaction();
            
            // Delete from subscription
            $stmt = $db->prepare("DELETE FROM subscription WHERE ClientID = ?");
            $stmt->execute([$clientId]);
            
            // Delete from booking
            $stmt = $db->prepare("DELETE FROM booking WHERE ClientID = ?");
            $stmt->execute([$clientId]);
            
            // Delete from feedback
            $stmt = $db->prepare("DELETE FROM feedback WHERE ClientID = ?");
            $stmt->execute([$clientId]);
            
            // Finally delete the user
            $stmt = $db->prepare("DELETE FROM client WHERE ClientID = ?");
            $stmt->execute([$clientId]);
            
            $db->commit();
            echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
            exit;
        }

        // --- PLAN MANAGEMENT ---
        if ($action === 'edit_plan') {
            $planId = $_POST['plan_type_id'] ?? null;
            $name = $_POST['plan_name'] ?? null;
            $price = $_POST['price'] ?? null;
            
            if (!$planId || !$name || !$price) { 
                throw new Exception('Plan ID, name, and price are required');
            }
            
            $stmt = $db->prepare("UPDATE plantype SET PlanName = ?, Price = ? WHERE PlanTypeID = ?");
            $stmt->execute([$name, $price, $planId]);
            echo json_encode(['status' => 'success', 'message' => 'Plan updated successfully']);
            exit;
        }

        if ($action === 'delete_plan') {
            $planId = $_POST['plan_type_id'] ?? null;
            if (!$planId) { 
                throw new Exception('Plan ID is required');
            }
            
            // First check if the plan is being used
            $stmt = $db->prepare("SELECT COUNT(*) FROM subscription WHERE PlanTypeID = ?");
            $stmt->execute([$planId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete plan that is currently in use');
            }
            
            $stmt = $db->prepare("DELETE FROM plantype WHERE PlanTypeID = ?");
            $stmt->execute([$planId]);
            echo json_encode(['status' => 'success', 'message' => 'Plan deleted successfully']);
            exit;
        }

        // --- CLASS MANAGEMENT ---
        if ($action === 'edit_class') {
            $classId = $_POST['class_id'] ?? null;
            $name = $_POST['class_name'] ?? null;
            $trainerId = $_POST['trainer_id'] ?? null;
            $startTime = $_POST['start_time'] ?? null;
            
            if (!$classId || !$name || !$trainerId || !$startTime) { 
                throw new Exception('Class ID, name, trainer ID, and start time are required');
            }
            
            $stmt = $db->prepare("UPDATE class SET ClassName = ?, TrainerID = ?, StartTime = ? WHERE ClassID = ?");
            $stmt->execute([$name, $trainerId, $startTime, $classId]);
            echo json_encode(['status' => 'success', 'message' => 'Class updated successfully']);
            exit;
        }

        // --- TRAINER MANAGEMENT ---
        if ($action === 'edit_trainer') {
            $trainerId = $_POST['trainer_id'] ?? null;
            $name = $_POST['name'] ?? null;
            $expertise = $_POST['expertise'] ?? null;
            
            if (!$trainerId || !$name || !$expertise) { 
                throw new Exception('Trainer ID, name, and expertise are required');
            }
            
            $stmt = $db->prepare("UPDATE trainer SET Name = ?, Expertise = ? WHERE TrainerID = ?");
            $stmt->execute([$name, $expertise, $trainerId]);
            echo json_encode(['status' => 'success', 'message' => 'Trainer updated successfully']);
            exit;
        }

        if ($action === 'view_feedback') {
            $trainerId = $_POST['trainer_id'] ?? null;
            if (!$trainerId) { 
                throw new Exception('Trainer ID is required');
            }
            
            $stmt = $db->prepare("
                SELECT f.*, c.Name as ClientName 
                FROM feedback f 
                JOIN client c ON f.ClientID = c.ClientID 
                WHERE f.TrainerID = ?
            ");
            $stmt->execute([$trainerId]);
            $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => 'success', 'feedback' => $feedback]);
            exit;
        }

        // Unknown action
        throw new Exception('Unknown action requested');
    } catch (Exception $e) {
        error_log("Error in admin action: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// If not a GET or POST with action, return error
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']); 