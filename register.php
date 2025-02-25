<?php
// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Initialize error array
$errors = [];

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form inputs
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validate name (no numbers allowed)
    if (preg_match('/\d/', $name)) {
        $errors[] = "Name should not contain numbers.";
    }
    
    // Validate email with stricter pattern
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Validate phone (8 digits)
    if (!preg_match('/^\d{8}$/', $phone)) {
        $errors[] = "Please enter a valid 8-digit phone number.";
    }
    
    // Validate password (minimum 6 characters)
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // If there are validation errors, display them and stop processing
    if (!empty($errors)) {
        echo "<div style='background-color: #ffcccc; padding: 10px; margin: 10px; border: 1px solid #ff0000;'>";
        echo "<h3>Please correct the following errors:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
        echo "<a href='javascript:history.back()'>Go back</a>";
        echo "</div>";
        exit();
    }
    
    // Sanitize inputs for database
    $name = $conn->real_escape_string($name);
    $email = $conn->real_escape_string($email);
    $phone = $conn->real_escape_string($phone);
    $address = $conn->real_escape_string($address);
    
    // Check if the email already exists using prepared statement
    $email_check_sql = "SELECT * FROM client WHERE Email = ? LIMIT 1";
    $check_stmt = $conn->prepare($email_check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo "<div style='background-color: #ffcccc; padding: 10px; margin: 10px; border: 1px solid #ff0000;'>";
        echo "Email is already registered. <a href='javascript:history.back()'>Go back</a>";
        echo "</div>";
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();
    
    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert the user into the database
    $sql = "INSERT INTO client (Name, Email, Password, Phone, Address) VALUES (?, ?, ?, ?, ?)";
    
    // Create a prepared statement
    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    $stmt->bind_param("sssss", $name, $email, $hashed_password, $phone, $address);
    
    // Execute the statement
    if ($stmt->execute()) {
        // Registration successful, output message and redirect to login page
        echo "<div style='background-color: #ccffcc; padding: 10px; margin: 10px; border: 1px solid #00cc00;'>";
        echo "Registration successful. Redirecting to login page...";
        echo "</div>";
        
        // Redirect to login page after 3 seconds
        header("refresh:3;url=login.html");
        exit();
    } else {
        echo "<div style='background-color: #ffcccc; padding: 10px; margin: 10px; border: 1px solid #ff0000;'>";
        echo "Error: " . $stmt->error;
        echo "</div>";
    }
    
    // Close statement
    $stmt->close();
}

// Close connection
$conn->close();
?>