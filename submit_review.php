<?php
// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize input data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $trainer_name = isset($_POST['trainer_name']) ? trim($_POST['trainer_name']) : '';
    $review = isset($_POST['review']) ? trim($_POST['review']) : '';

    // Validate input data
    if (empty($name) || empty($email) || empty($trainer_name) || empty($review)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }

    // Database connection
    $conn = new mysqli('localhost', 'root', '', 'gymwebapp');

    // Check connection
    if ($conn->connect_error) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }

    // Get ClientID based on the name and email
    $clientID = null;
    $clientQuery = $conn->prepare("SELECT ClientID FROM client WHERE Name = ? AND Email = ?");
    $clientQuery->bind_param("ss", $name, $email);
    $clientQuery->execute();
    $clientQuery->bind_result($clientID);
    $clientQuery->fetch();
    $clientQuery->close();

    // Get TrainerID based on the trainer name
    $trainerID = null;
    $trainerQuery = $conn->prepare("SELECT TrainerID FROM trainer WHERE Name = ?");
    $trainerQuery->bind_param("s", $trainer_name);
    $trainerQuery->execute();
    $trainerQuery->bind_result($trainerID);
    $trainerQuery->fetch();
    $trainerQuery->close();

    // Check if ClientID and TrainerID were found
    if (is_null($clientID) || is_null($trainerID)) {
        echo json_encode(['status' => 'error', 'message' => 'Client or Trainer not found.']);
        exit;
    }

    // Prepare and bind for inserting feedback
    $stmt = $conn->prepare("INSERT INTO feedback (ClientID, TrainerID, Comments) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $clientID, $trainerID, $review);

    // Execute the statement
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Review submitted successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit review: ' . $stmt->error]);
    }

    // Close connections
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
