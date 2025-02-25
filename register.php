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

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form inputs
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        echo "Passwords do not match.";
        exit();
    }
    
    // Check if the email already exists using prepared statement
    $email_check_sql = "SELECT * FROM client WHERE Email = ? LIMIT 1";
    $check_stmt = $conn->prepare($email_check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo "Email is already registered.";
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();
    
    // Insert the user into the database
    // Note: In a production environment, you should use password_hash() for password security
    $sql = "INSERT INTO client (Name, Email, Password, Phone, Address) VALUES (?, ?, ?, ?, ?)";
    
    // Create a prepared statement
    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    $stmt->bind_param("sssss", $name, $email, $password, $phone, $address);
    
    // Execute the statement
    if ($stmt->execute()) {
        // Registration successful, output message and redirect to login page
        echo "Registration successful. Redirecting to login page...";
        
        // Redirect to login page after 3 seconds
        header("refresh:3;url=login.html");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    
    // Close statement
    $stmt->close();
}

// Close connection
$conn->close();
?>