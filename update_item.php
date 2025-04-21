<?php
// Include database connection
require_once 'config/db.php';

// Set headers
header('Content-Type: application/json');

// Get JSON input
$json = file_get_contents('php://input');
$input = json_decode($json, true);

// Log received data for debugging
error_log("Update item received data: " . json_encode($input));

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Get item ID - support item_id for compatibility
$item_id = !empty($input['item_id']) ? $input['item_id'] : null;

// Validate required fields
if (!$item_id || empty($input['item_name'])) {
    echo json_encode(['success' => false, 'message' => 'Item ID and name are required']);
    exit;
}

try {
    // Get current item data for comparison
    $stmt = $conn->prepare("SELECT * FROM inventory_items WHERE item_id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare error (select): " . $conn->error);
    }
    
    $stmt->bind_param("i", $item_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error (select): " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $currentItem = $result->fetch_assoc();
    
    if (!$currentItem) {
        throw new Exception("Item not found with ID: " . $item_id);
    }
    
    $previousLocation = $currentItem['location'];
    $stmt->close();

    // Check for duplicate serial number if serial number is being changed
    if (!empty($input['serial_number']) && $input['serial_number'] !== $currentItem['serial_number']) {
        $check_sql = "SELECT COUNT(*) as count FROM inventory_items 
                      WHERE serial_number = ? AND item_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Database prepare error (duplicate check): " . $conn->error);
        }
        
        $check_stmt->bind_param("si", $input['serial_number'], $item_id);
        
        if (!$check_stmt->execute()) {
            throw new Exception("Database execute error (duplicate check): " . $check_stmt->error);
        }
        
        $result = $check_stmt->get_result();
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'Serial Number already exists']);  
            exit;
        }
        $check_stmt->close();
    }

    // Validate condition_status is one of the allowed values
    $allowed_conditions = ['New', 'Good', 'Fair', 'Poor'];
    $condition_status = !empty($input['condition']) ? $input['condition'] : 'New';
    
    if (!in_array($condition_status, $allowed_conditions)) {
        $condition_status = 'New'; // Default to 'New' if not valid
    }
    
    // Format dates correctly for MySQL
    $date_acquired = !empty($input['purchase_date']) ? date('Y-m-d', strtotime($input['purchase_date'])) : null;
    $warranty_expiry = !empty($input['warranty_expiration']) ? date('Y-m-d', strtotime($input['warranty_expiration'])) : null;

    // Prepare statement for updating inventory with correct field names
    $stmt = $conn->prepare("UPDATE inventory_items
                      SET item_name = ?, 
                          brand_model = ?, 
                          serial_number = ?,
                          date_acquired = ?, 
                          warranty_expiry = ?,
                          condition_status = ?, 
                          location = ?, 
                          notes = ?,
                          assigned_to = ?
                      WHERE item_id = ?");
    
    if (!$stmt) {
        throw new Exception("Database prepare error (update): " . $conn->error);
    }

    // Set parameters with correct field mappings
    $item_name = $input['item_name'];
    $brand_model = !empty($input['brand_model']) ? $input['brand_model'] : null;
    $serial_number = !empty($input['serial_number']) ? $input['serial_number'] : null;
    $location = !empty($input['location']) ? $input['location'] : null;
    $notes = !empty($input['notes']) ? $input['notes'] : null;
    $assigned_to = !empty($input['assigned_to']) ? $input['assigned_to'] : null;

    // Bind parameters (s = string, i = integer)
    $stmt->bind_param(
        "sssssssssi",
        $item_name,
        $brand_model,
        $serial_number,
        $date_acquired,
        $warranty_expiry,
        $condition_status,
        $location,
        $notes,
        $assigned_to,
        $item_id
    );

    // Execute update
    if (!$stmt->execute()) {
        throw new Exception("Database execute error (update): " . $stmt->error);
    }

    // If location changed, record in history (if you have that table)
    if ($previousLocation !== $location && $conn->query("SHOW TABLES LIKE 'location_history'")->num_rows > 0) {
        $historyStmt = $conn->prepare("INSERT INTO location_history (item_id, previous_location, new_location, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
        if (!$historyStmt) {
            throw new Exception("Database prepare error (history): " . $conn->error);
        }
        
        $changedBy = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'System';
        $historyNotes = "Location changed from " . ($previousLocation ?: 'None') . " to " . ($location ?: 'None');
        
        $historyStmt->bind_param("sssss", 
            $item_id,
            $previousLocation,
            $location,
            $changedBy,
            $historyNotes
        );
        
        $historyStmt->execute();
        $historyStmt->close();
    }

    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Item updated successfully',
        'item_id' => $item_id
    ]);
    
} catch (Exception $e) {
    error_log("Error in update_item.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Close connection
$conn->close();
?>