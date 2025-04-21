<?php
header('Content-Type: application/json');

// Include database connection
include 'config/db.php';

// Get JSON input
$json = file_get_contents('php://input');

// Validate JSON
$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Log received data for debugging
error_log("Received data: " . json_encode($data));

try {
    // Basic validation
    if (empty($data['item_name'])) {
        throw new Exception("Item name is required");
    }
    
    // Validate condition_status is one of the allowed values
    $allowed_conditions = ['New', 'Good', 'Fair', 'Poor'];
    $condition_status = !empty($data['condition']) ? $data['condition'] : 'New';
    
    if (!in_array($condition_status, $allowed_conditions)) {
        $condition_status = 'New'; // Default to 'New' if not valid
    }
    
    // Format dates correctly for MySQL
    $date_acquired = !empty($data['purchase_date']) ? date('Y-m-d', strtotime($data['purchase_date'])) : null;
    $warranty_expiry = !empty($data['warranty_expiration']) ? date('Y-m-d', strtotime($data['warranty_expiration'])) : null;
    
    // Prepare the statement with correct field names matching the database schema
    $stmt = $conn->prepare("INSERT INTO inventory_items(
        item_name,
        brand_model,
        serial_number,
        date_acquired,
        warranty_expiry,
        assigned_to,
        condition_status,
        location,
        notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }

    // Set parameters with correct names
    $item_name = $data['item_name'];
    $brand_model = !empty($data['brand_model']) ? $data['brand_model'] : null;
    $serial_number = !empty($data['serial_number']) ? $data['serial_number'] : null;
    $assigned_to = !empty($data['assigned_to']) ? $data['assigned_to'] : null;
    $location = !empty($data['location']) ? $data['location'] : null;
    $notes = !empty($data['notes']) ? $data['notes'] : null;

    // Bind parameters (s = string)
    $stmt->bind_param("sssssssss",
        $item_name,
        $brand_model,
        $serial_number,
        $date_acquired,
        $warranty_expiry,
        $assigned_to,
        $condition_status,
        $location,
        $notes
    );

    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    
    $new_item_id = $stmt->insert_id;
    
    echo json_encode([
        'success' => true, 
        'item_id' => $new_item_id,
        'message' => 'Item added successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in add_item.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>