<?php
// Include database connection
require_once 'config/db.php';

// Set response headers
header('Content-Type: application/json');
$response = ['success' => true, 'messages' => []];

// Check if connected
if ($conn->connect_error) {
    $response['success'] = false;
    $response['messages'][] = "Connection failed: " . $conn->connect_error;
    echo json_encode($response);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Add missing columns to property_acknowledgement_receipts table
    // First check if columns exist
    $result = $conn->query("SHOW COLUMNS FROM property_acknowledgement_receipts LIKE 'entity_name'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE property_acknowledgement_receipts ADD COLUMN entity_name VARCHAR(255) AFTER par_no");
        $response['messages'][] = "Added entity_name column";
    }
    
    $result = $conn->query("SHOW COLUMNS FROM property_acknowledgement_receipts LIKE 'position'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE property_acknowledgement_receipts ADD COLUMN position VARCHAR(100) AFTER received_by");
        $response['messages'][] = "Added position column";
    }
    
    $result = $conn->query("SHOW COLUMNS FROM property_acknowledgement_receipts LIKE 'department'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE property_acknowledgement_receipts ADD COLUMN department VARCHAR(100) AFTER position");
        $response['messages'][] = "Added department column";
    }
    
    $result = $conn->query("SHOW COLUMNS FROM property_acknowledgement_receipts LIKE 'remarks'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE property_acknowledgement_receipts ADD COLUMN remarks TEXT AFTER department");
        $response['messages'][] = "Added remarks column";
    }
    
    // Also make sure par_items table has all needed columns
    $result = $conn->query("SHOW COLUMNS FROM par_items LIKE 'property_number'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE par_items ADD COLUMN property_number VARCHAR(100) AFTER description");
        $response['messages'][] = "Added property_number column to par_items";
    }
    
    // Commit changes
    $conn->commit();
    $response['messages'][] = "Database structure updated successfully";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $response['success'] = false;
    $response['messages'][] = "Error updating database structure: " . $e->getMessage();
}

// Close connection
$conn->close();

// Return response
echo json_encode($response);
?> 