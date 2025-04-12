<?php
// Include the JSON validator
require_once 'json_validator.php';

/**
 * Example of PHP consuming JSON data
 * This could be from an external API or a JSON file
 */

// Function to consume JSON from an external URL
function fetchJsonFromUrl($url) {
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Execute the cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'message' => "cURL Error: {$error}", 'data' => null];
    }
    
    // Get HTTP status code
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // If not successful status code, return error
    if ($httpStatus != 200) {
        return ['success' => false, 'message' => "HTTP Error: {$httpStatus}", 'data' => null];
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    // Check if JSON is valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => "JSON Error: " . json_last_error_msg(), 'data' => null];
    }
    
    return ['success' => true, 'message' => "Data fetched successfully", 'data' => $data];
}

// Function to consume JSON from a POST request
function getJsonFromRequest() {
    // Get the raw POST data
    $rawPostData = file_get_contents('php://input');
    
    // Parse the JSON data
    $data = json_decode($rawPostData, true);
    
    // Check if JSON is valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => "JSON Error: " . json_last_error_msg(), 'data' => null];
    }
    
    return ['success' => true, 'message' => "Data received successfully", 'data' => $data];
}

// Example of consuming JSON from a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data from request
    $result = getJsonFromRequest();
    
    if ($result['success']) {
        // Define the expected schema
        $bookingSchema = [
            'type' => 'object',
            'properties' => [
                'client_id' => ['type' => 'integer'],
                'class_id' => ['type' => 'integer'],
                'trainer_id' => ['type' => 'integer'],
                'booking_date' => ['type' => 'string'],
                'notes' => ['type' => 'string']
            ],
            'required' => ['client_id', 'class_id', 'trainer_id', 'booking_date']
        ];
        
        // Validate the JSON against the schema
        $validationResult = JSONValidator::validate($result['data'], $bookingSchema);
        
        if ($validationResult['valid']) {
            // Process the validated data
            // This is where you would typically save to database
            
            // Generate a success response
            $response = [
                'success' => true,
                'message' => 'Booking data processed successfully',
                'data' => [
                    'booking_id' => rand(1000, 9999),
                    'processed_at' => date('Y-m-d H:i:s')
                ]
            ];
        } else {
            // Invalid JSON schema
            $response = [
                'success' => false,
                'message' => 'Invalid booking data format',
                'errors' => $validationResult['errors']
            ];
        }
    } else {
        // Error parsing JSON data
        $response = $result;
    }
    
    // Output the response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Example of URL consumption - this would typically be an API endpoint
$apiUrl = 'https://jsonplaceholder.typicode.com/users/1';

// If this is a GET request, fetch and display data from the API
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = fetchJsonFromUrl($apiUrl);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}
?> 