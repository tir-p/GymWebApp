<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['client_id'])) {
    echo '<p>Please log in to view your memberships.</p>';
    exit();
}

// Get the logged-in user's ClientID from the session
$clientID = $_SESSION['client_id'];

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

// SQL query to fetch all active subscription details for the logged-in client
$sql = "SELECT s.SubscriptionID, c.Name AS ClientName, pt.PlanName AS PlanTypeName, 
        s.StartDate, s.EndDate 
        FROM subscription s 
        JOIN client c ON s.ClientID = c.ClientID 
        JOIN plantype pt ON s.PlanTypeID = pt.PlanTypeID 
        WHERE s.ClientID = ?";

// Prepare statement
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $clientID);
$stmt->execute();
$result = $stmt->get_result();

// Display the fetched subscription details
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div class='membership-info'>";
        echo "<strong>Subscription ID:</strong> " . $row['SubscriptionID'] . "<br>";
        echo "<strong>Client Name:</strong> " . $row['ClientName'] . "<br>";
        echo "<strong>Plan Type:</strong> " . $row['PlanTypeName'] . "<br>";
        echo "<strong>Start Date:</strong> " . $row['StartDate'] . "<br>";
        echo "<strong>End Date:</strong> " . $row['EndDate'] . "<br>";
        echo "</div><br>";
    }
} else {
    echo '<p>No subscriptions found.</p>';
}

// Close the statement and connection
$stmt->close();
$conn->close();

// Ensure all queries are correctly fetching data
?>
