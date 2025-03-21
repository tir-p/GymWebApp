<?php
session_start();

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

// Get form data
$login_type = $_POST['login_type'] ?? ''; // 'admin', 'client', or 'trainer'
$username_email = $_POST['username_email'] ?? ''; // Username for admin or Email for client
$pass = $_POST['password'] ?? ''; // Password input

// Validate input
if (empty($login_type) || empty($username_email) || empty($pass)) {
    echo "<p>All fields are required. <a href='login.html'>Try again</a>.</p>";
    exit();
}

if ($login_type === 'admin') {
    // Prepare and bind for admin login
    $stmt = $conn->prepare("SELECT AdminID, Password FROM admin WHERE Username = ?");
    $stmt->bind_param("s", $username_email);
} elseif ($login_type === 'trainer') {
    // Prepare and bind for trainer login
    $stmt = $conn->prepare("SELECT TrainerID, Password FROM trainer WHERE Username = ?");
    $stmt->bind_param("s", $username_email);
} else {
    // Prepare and bind for client login
    $stmt = $conn->prepare("SELECT ClientID, Password, Name FROM client WHERE Email = ?");
    $stmt->bind_param("s", $username_email);
}

// Execute the statement
$stmt->execute();
$result = $stmt->get_result();

// Check if user exists
if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $stored_password = $row['Password'];
    
    // Get the user ID based on login type
    if ($login_type === 'admin') {
        $user_id = $row['AdminID'];
    } elseif ($login_type === 'trainer') {
        $user_id = $row['TrainerID'];
    } else {
        $user_id = $row['ClientID'];
        $user_name = $row['Name']; // Get the client's name
    }
    
    // Check if the password is correct
    if (password_verify($pass, $stored_password)) {
        // Password is correct, set session variables
        if ($login_type === 'admin') {
            $_SESSION['admin_id'] = $user_id;
            $_SESSION['username'] = $username_email;
            header("Location: admin_dashboard.html");
        } elseif ($login_type === 'trainer') {
            $_SESSION['trainer_id'] = $user_id;
            $_SESSION['username'] = $username_email;
            header("Location: trainer_dashboard.html");
        } else {
            $_SESSION['client_id'] = $user_id;
            $_SESSION['email'] = $username_email;
            $_SESSION['name'] = $user_name;
            header("Location: client_dashboard.html");
        }
        exit();
    } else {
        // Invalid password
        echo "<div style='background-color: #ffcccc; padding: 10px; margin: 10px; border: 1px solid #ff0000;'>";
        echo "<p>Invalid password. <a href='login.html'>Try again</a>.</p>";
        echo "</div>";
    }
} else {
    // User does not exist
    echo "<div style='background-color: #ffcccc; padding: 10px; margin: 10px; border: 1px solid #ff0000;'>";
    echo "<p>Invalid username/email. <a href='login.html'>Try again</a>.</p>";
    echo "</div>";
}

// Close the statement and connection
$stmt->close();
$conn->close();
?>