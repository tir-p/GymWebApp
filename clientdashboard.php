<?php
// This file will handle client dashboard logic
// Currently, it is empty. Please implement the necessary logic here.

// Start the session to access session variables
session_start();

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header("Location: login.html");
    exit();
}

// Store client ID for database queries
$clientID = $_SESSION['client_id'];

// Check if it's an API request for JSON data
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    // Database connection parameters
    $host = 'localhost';
    $dbname = 'gymwebapp';
    $username = 'root';
    $password = '';

    // Create connection
    $conn = new mysqli($host, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Connection failed: " . $conn->connect_error,
            'data' => null
        ]);
        exit();
    }

    // Fetch memberships for the client using prepared statements
    $membershipQuery = $conn->prepare("SELECT p.PlanName, s.StartDate, s.EndDate, s.Status 
                                       FROM subscription s 
                                       JOIN plantype p ON s.PlanTypeID = p.PlanTypeID 
                                       WHERE s.ClientID = ?");
    $membershipQuery->bind_param("i", $clientID);
    $membershipQuery->execute();
    $membershipResult = $membershipQuery->get_result();

    $memberships = [];
    while ($row = $membershipResult->fetch_assoc()) {
        $memberships[] = [
            'plan_name' => $row['PlanName'],
            'start_date' => $row['StartDate'],
            'end_date' => $row['EndDate'],
            'status' => $row['Status']
        ];
    }

    // Fetch bookings for the client using prepared statements
    $bookingQuery = $conn->prepare("SELECT b.BookingID, b.BookingDate, c.ClassName, t.Name AS TrainerName 
                                   FROM booking b 
                                   JOIN class c ON b.ClassID = c.ClassID 
                                   JOIN trainer t ON b.TrainerID = t.TrainerID 
                                   WHERE b.ClientID = ?");
    $bookingQuery->bind_param("i", $clientID);
    $bookingQuery->execute();
    $bookingResult = $bookingQuery->get_result();

    $bookings = [];
    while ($row = $bookingResult->fetch_assoc()) {
        $bookings[] = [
            'booking_id' => $row['BookingID'],
            'booking_date' => $row['BookingDate'],
            'class_name' => $row['ClassName'],
            'trainer_name' => $row['TrainerName']
        ];
    }

    // Package all data into an array
    $response = [
        'success' => true,
        'message' => 'Data retrieved successfully',
        'data' => [
            'memberships' => $memberships,
            'bookings' => $bookings,
        ]
    ];

    // Return the data in JSON format
    header('Content-Type: application/json');
    echo json_encode($response);

    // Close connections
    $membershipQuery->close();
    $bookingQuery->close();
    $conn->close();
    exit();
}

// Regular HTML output for the dashboard
$clientName = $_SESSION['name'] ?? 'Client';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - GymFit</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <header>
        <div class="container">
            <h1>GymFit</h1>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.html">Home</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="membership.html">Membership</a></li>
                    <li><a href="booking.html">Book a Class</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($clientName); ?>!</h2>
        <p>This is your client dashboard. Here you can view your bookings and manage your account.</p>
        
        <div class="dashboard-section">
            <h3>Your Upcoming Bookings</h3>
            <div id="bookings-container">
                <p>Loading bookings...</p>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h3>Your Membership</h3>
            <div id="membership-container">
                <p>Loading membership details...</p>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h3>Quick Actions</h3>
            <div class="action-buttons">
                <a href="booking.html" class="btn">Book a New Class</a>
                <a href="account.html" class="btn">Manage Account</a>
                <a href="submit_review.php" class="btn">Leave a Review</a>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 GymFit - All Rights Reserved</p>
    </footer>

    <script>
        $(document).ready(function() {
            // Load client data via AJAX
            $.ajax({
                url: 'clientdashboard.php?format=json',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        displayBookings(data.bookings);
                        displayMembership(data.memberships);
                    } else {
                        showError(response.message);
                    }
                },
                error: function(xhr, status, error) {
                    showError('Failed to load data. Please try again later.');
                    console.error(xhr.responseText);
                }
            });
            
            // Function to display bookings
            function displayBookings(bookings) {
                if (!bookings || bookings.length === 0) {
                    $('#bookings-container').html('<p>You have no upcoming bookings. <a href="booking.html">Book a class now</a>!</p>');
                    return;
                }
                
                let html = '<table class="bookings-table">';
                html += '<thead><tr><th>Class</th><th>Date</th><th>Trainer</th><th>Actions</th></tr></thead>';
                html += '<tbody>';
                
                bookings.forEach(function(booking) {
                    html += '<tr>';
                    html += `<td>${booking.class_name}</td>`;
                    html += `<td>${booking.booking_date}</td>`;
                    html += `<td>${booking.trainer_name}</td>`;
                    html += `<td><button class="btn-cancel" data-id="${booking.booking_id}">Cancel</button></td>`;
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('#bookings-container').html(html);
                
                // Add event listener for cancel buttons
                $('.btn-cancel').click(function() {
                    const bookingId = $(this).data('id');
                    cancelBooking(bookingId);
                });
            }
            
            // Function to display membership
            function displayMembership(memberships) {
                if (!memberships || memberships.length === 0) {
                    $('#membership-container').html('<p>You have no active memberships. <a href="membership.html">Get a membership now</a>!</p>');
                    return;
                }
                
                let html = '<div class="membership-details">';
                
                memberships.forEach(function(membership) {
                    html += `<div class="membership-card ${membership.status.toLowerCase()}">`;
                    html += `<h4>${membership.plan_name}</h4>`;
                    html += `<p>Start Date: ${membership.start_date}</p>`;
                    html += `<p>End Date: ${membership.end_date}</p>`;
                    html += `<p>Status: <span class="status">${membership.status}</span></p>`;
                    html += '</div>';
                });
                
                html += '</div>';
                $('#membership-container').html(html);
            }
            
            // Function to cancel a booking
            function cancelBooking(bookingId) {
                if (confirm('Are you sure you want to cancel this booking?')) {
                    $.ajax({
                        url: 'cancel_booking.php',
                        type: 'POST',
                        data: JSON.stringify({ booking_id: bookingId }),
                        contentType: 'application/json',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert('Booking cancelled successfully!');
                                location.reload();
                            } else {
                                alert('Failed to cancel booking: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('An error occurred. Please try again later.');
                            console.error(xhr.responseText);
                        }
                    });
                }
            }
            
            // Function to show error
            function showError(message) {
                $('#bookings-container').html(`<p class="error">${message}</p>`);
                $('#membership-container').html(`<p class="error">${message}</p>`);
            }
        });
    </script>
</body>
</html>
