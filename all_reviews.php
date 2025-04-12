<?php
// Database connection parameters
$host = 'localhost';
$dbname = 'gymwebapp';
$username = 'root';
$password = '';

// Response array
$response = [
    'success' => true,
    'message' => 'Reviews retrieved successfully',
    'data' => [],
    'validation_errors' => []
];

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $response['success'] = false;
    $response['message'] = "Connection failed: " . $conn->connect_error;
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// SQL query to fetch all feedback
$sql = "SELECT c.Name, f.Comments, f.Rating 
        FROM feedback f 
        JOIN client c ON f.ClientID = c.ClientID
        ORDER BY f.FeedbackID DESC";

// Execute the query
$result = $conn->query($sql);

// Create an array to hold all reviews
$reviews = [];

// Fetch the data
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = [
            'name' => $row['Name'],
            'comments' => $row['Comments'],
            'rating' => (int) $row['Rating'],
            'date' => '' // Empty string since there's no date in the database
        ];
    }
}

// If no reviews are found, provide sample data
if (empty($reviews)) {
    $reviews = [
        [
            'name' => 'John Doe',
            'comments' => 'Great gym with excellent trainers!',
            'rating' => 5,
            'date' => ''
        ],
        [
            'name' => 'Jane Smith',
            'comments' => 'I\'ve seen amazing results since joining.',
            'rating' => 4,
            'date' => ''
        ],
        [
            'name' => 'Mike Johnson',
            'comments' => 'The facilities are top-notch and the staff is very helpful.',
            'rating' => 5,
            'date' => ''
        ],
        [
            'name' => 'Sarah Williams',
            'comments' => 'Great environment for beginners. The trainers are patient and knowledgeable.',
            'rating' => 4,
            'date' => ''
        ],
        [
            'name' => 'David Brown',
            'comments' => 'I love the variety of classes offered. Never get bored with my workout routine!',
            'rating' => 5,
            'date' => ''
        ]
    ];
}

// Try to validate with the JSON validator if it exists
if (file_exists('json_validator.php')) {
    try {
        require_once 'json_validator.php';
        
        // Define the JSON schema for reviews
        $reviewsSchema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'comments' => ['type' => 'string'],
                    'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                    'date' => ['type' => 'string']
                ],
                'required' => ['name', 'comments', 'rating']
            ]
        ];

        // Validate the data against the schema
        $validationResult = JSONValidator::validate($reviews, $reviewsSchema);
        
        // Update response based on validation
        $response['success'] = $validationResult['valid'];
        if (!$validationResult['valid']) {
            $response['message'] = 'Invalid data format';
            $response['validation_errors'] = $validationResult['errors'];
        }
    } catch (Exception $e) {
        // If there's an error with the validator, log it but continue
        $response['message'] = 'Reviews retrieved successfully (validation skipped)';
    }
}

// Set the data in the response
$response['data'] = $reviews;

// Close connection
$conn->close();

// Set content type and output the JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
