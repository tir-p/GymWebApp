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
    die("<div class='bg-red-100 text-red-700 p-4 rounded mt-4'>Connection failed: " . $conn->connect_error . "</div>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    if (preg_match('/\d/', $name)) $errors[] = "Name should not contain numbers.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) $errors[] = "Please enter a valid email address.";
    if (!preg_match('/^\d{8}$/', $phone)) $errors[] = "Please enter a valid 8-digit phone number.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (!empty($errors)) {
        echo "<div class='bg-red-100 text-red-700 p-4 rounded mt-4 max-w-md mx-auto mt-10'>";
        echo "<h3 class='font-bold mb-2'>Please correct the following errors:</h3><ul class='list-disc pl-5'>";
        foreach ($errors as $error) echo "<li>$error</li>";
        echo "</ul><a href='javascript:history.back()' class='text-blue-600 underline mt-2 inline-block'>Go back</a></div>";
        exit();
    }

    $name = $conn->real_escape_string($name);
    $email = $conn->real_escape_string($email);
    $phone = $conn->real_escape_string($phone);
    $address = $conn->real_escape_string($address);

    $email_check_sql = "SELECT * FROM client WHERE Email = ? LIMIT 1";
    $check_stmt = $conn->prepare($email_check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo "<div class='bg-red-100 text-red-700 p-4 rounded mt-4 max-w-md mx-auto mt-10'>";
        echo "Email is already registered. <a href='javascript:history.back()' class='text-blue-600 underline'>Go back</a>";
        echo "</div>";
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO client (Name, Email, Password, Phone, Address) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $name, $email, $hashed_password, $phone, $address);

    if ($stmt->execute()) {
        $clientID = $conn->insert_id;
        $planTypeID = 1;
        $startDate = date("Y-m-d");
        $endDate = date("Y-m-d", strtotime("+30 days"));
        $status = 'active';

        $subSql = "INSERT INTO subscription (ClientID, PlanTypeID, StartDate, EndDate, Status)
                   VALUES (?, ?, ?, ?, ?)";
        $subStmt = $conn->prepare($subSql);
        $subStmt->bind_param("iisss", $clientID, $planTypeID, $startDate, $endDate, $status);

        if ($subStmt->execute()) {
            echo "<div class='bg-green-100 text-green-700 p-4 rounded mt-4 max-w-md mx-auto mt-10'>";
            echo "Registration and default subscription successful. Redirecting to login page...</div>";
            header("refresh:3;url=login.html");
            exit();
        } else {
            echo "<div class='bg-red-100 text-red-700 p-4 rounded mt-4 max-w-md mx-auto mt-10'>";
            echo "User created, but failed to create subscription: " . $subStmt->error . "</div>";
        }
        $subStmt->close();
    } else {
        echo "<div class='bg-red-100 text-red-700 p-4 rounded mt-4 max-w-md mx-auto mt-10'>";
        echo "Failed to register user: " . $stmt->error . "</div>";
    }
    $stmt->close();
}
$conn->close();
?>
