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
    die("Connection failed: " . $conn->connect_error);
}

// SQL query to fetch feedback with limit for homepage display
$sql = "SELECT c.Name, f.Comments 
        FROM feedback f 
        JOIN client c ON f.ClientID = c.ClientID
        LIMIT 6";

// Execute the query
$result = $conn->query($sql);

// Display the fetched feedback details
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div class='review-c'>";
        echo "<h3>" . $row['Name'] . "</h3>";
        echo "<p>" . $row['Comments'] . "</p>";
        echo "</div>";
    }
} else {
    echo '<p>No feedback available.</p>';
}

// Close connection
$conn->close();

// Ensure all queries are correctly fetching data
?>
