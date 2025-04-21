<?php
include 'config/db.php';

// Ensure we're sending JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off display errors to prevent HTML in output
ini_set('log_errors', 1);
ini_set('error_log', 'delete_item_errors.log');

try {
    // Check database connection first
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn ? $conn->connect_error : 'No connection'));
    }

    // Check if we received a request
    $input = file_get_contents('php://input');
    if (empty($input)) {
        error_log('No input data received');
        throw new Exception('No input data received');
    }
    
    // Decode the JSON data
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Invalid JSON: ' . json_last_error_msg());
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    // Check if item_id is provided
    if (!isset($data['item_id']) || empty($data['item_id'])) {
        error_log('Item ID is required');
        throw new Exception('Item ID is required');
    }
    
    $item_id = $data['item_id'];
    
    // Prepare and execute the delete query
    $query = "DELETE FROM inventory_items WHERE item_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log('Prepare statement failed: ' . $conn->error);
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $item_id);
    
    if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error);
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    // Check if any rows were affected
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No item found with ID: ' . $item_id]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    // Log the error
    error_log('Delete item error: ' . $e->getMessage());
    
    // Return a proper JSON error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete item: ' . $e->getMessage()
    ]);
}

// Close the database connection
if (isset($conn) && !$conn->connect_error) {
    $conn->close();
}
?>