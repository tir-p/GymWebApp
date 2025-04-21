<?php
session_start();
header('Content-Type: application/json');

// Check if trainer is logged in
if (!isset($_SESSION['trainer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$trainerID = $_SESSION['trainer_id'];

// DB connection
$conn = new mysqli('localhost', 'root', '', 'gymwebapp');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

// Edit profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_profile') {
    $name = trim($_POST['name']);
    $expertise = trim($_POST['expertise']);
    if ($name && $expertise) {
        $stmt = $conn->prepare("UPDATE trainer SET Name=?, Expertise=? WHERE TrainerID=?");
        $stmt->bind_param("ssi", $name, $expertise, $trainerID);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Profile updated!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
    }
    $conn->close();
    exit;
}

// Get trainer info
$trainer = $conn->query("SELECT * FROM trainer WHERE TrainerID = $trainerID")->fetch_assoc();

// Get assigned clients (clients who have bookings with this trainer)
$clients = [];
$res = $conn->query("
    SELECT DISTINCT c.ClientID, c.Name, c.Email, c.Phone
    FROM client c
    JOIN booking b ON c.ClientID = b.ClientID
    WHERE b.TrainerID = $trainerID
");
while ($row = $res->fetch_assoc()) $clients[] = $row;

// Get schedule (classes assigned to this trainer)
$schedule = [];
$res = $conn->query("
    SELECT cl.ClassName, cl.ScheduleDate, cl.StartTime, cl.EndTime
    FROM class cl
    WHERE cl.TrainerID = $trainerID
    ORDER BY cl.ScheduleDate, cl.StartTime
");
while ($row = $res->fetch_assoc()) $schedule[] = $row;

// Get attendance for this trainer's classes
$attendance = [];
$res = $conn->query("
    SELECT a.AttendanceDate, a.AttendanceStatus, c.Name AS ClientName, cl.ClassName
    FROM attendance a
    JOIN client c ON a.ClientID = c.ClientID
    JOIN class cl ON a.ClassID = cl.ClassID
    WHERE cl.TrainerID = $trainerID
    ORDER BY a.AttendanceDate DESC
");
while ($row = $res->fetch_assoc()) $attendance[] = $row;

// You can add more features here (e.g., workout plans)

echo json_encode([
    'success' => true,
    'trainer' => $trainer,
    'clients' => $clients,
    'schedule' => $schedule,
    'attendance' => $attendance
]);
$conn->close();
?>
