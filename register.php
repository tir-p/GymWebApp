<?php include 'connect.php'; ?>
<?php
// Database connection details
$servername = "localhost";  // Use your server's details
$username = "root";         // Your MySQL username
$password = "";             // Your MySQL password
$dbname = "gymwebapp";      // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

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
    
    // Check if passwords match
    if ($password != $confirm_password) {
        echo "Passwords do not match.";
        exit();
    }
    
    // Check if the email already exists
    $email_check_query = "SELECT * FROM client WHERE Email='$email' LIMIT 1";
    $result = $conn->query($email_check_query);
    
    if ($result->num_rows > 0) {
        echo "Email is already registered.";
        exit();
    }
    
    // Insert the user into the database (no password hashing)
    $sql = "INSERT INTO client (Name, Email, Password) VALUES ('$name', '$email', '$password')";
    
    if ($conn->query($sql) === TRUE) {
        // Registration successful, output message and redirect to login page
        echo "Registration successful. Redirecting to login page...";
        
        // Redirect to login page after 3 seconds
        header("refresh:3;url=login.html");
        exit();
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Close connection
$conn->close();
?>