<?php
require_once 'config/db.php';

// Set response headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Debug log
error_log('PAR Save Request Received');

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception('Invalid input data: ' . json_last_error_msg());
    }

    // Validate required fields
    $required = ['par_no', 'entity_name', 'date_acquired', 'received_by', 'items'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // Database connection - use the one from config/db.php
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // First, check if user exists, if not create one
        $userId = null;
        
        if (is_numeric($data['received_by'])) {
            // If it's already a numeric ID, use it directly
            $userId = intval($data['received_by']);
            
            // Verify this user ID exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // If user ID doesn't exist, treat as a name
                $userId = null;
            }
        }
        
        // If userId is still null, look for user by name or create new 
        if ($userId === null) {
            // Check if this user exists by name
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE full_name = ?");
            $stmt->bind_param("s", $data['received_by']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $userId = $row['user_id'];
            } else {
                // Create new user
                $stmt = $conn->prepare("INSERT INTO users (full_name, position, department) VALUES (?, ?, ?)");
                $position = isset($data['position']) ? $data['position'] : '';
                $department = isset($data['department']) ? $data['department'] : '';
                
                $stmt->bind_param("sss", 
                    $data['received_by'], 
                    $position, 
                    $department
                );
                $stmt->execute();
                $userId = $conn->insert_id;
            }
        }

        // Insert PAR header - use property_acknowledgement_receipts table
        $sql = "INSERT INTO property_acknowledgement_receipts (
            par_no, entity_name, date_acquired, received_by, 
            position, department, remarks, total_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $position = isset($data['position']) ? $data['position'] : '';
        $department = isset($data['department']) ? $data['department'] : '';
        $remarks = isset($data['remarks']) ? $data['remarks'] : '';
        $totalAmount = 0;
        
        // Calculate total amount from items
        foreach ($data['items'] as $item) {
            $quantity = intval($item['quantity'] ?? 1);
            $amount = floatval(preg_replace('/[^0-9.]/', '', $item['amount'] ?? 0));
            $totalAmount += $quantity * $amount;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssisssd',
            $data['par_no'],
            $data['entity_name'],
            $data['date_acquired'],
            $userId,
            $position,
            $department,
            $remarks,
            $totalAmount
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save PAR header: ' . $conn->error);
        }
        
        $par_id = $conn->insert_id;

        // Insert PAR items - use par_items table
        $sql = "INSERT INTO par_items (
            par_id, quantity, unit, item_description, 
            property_number, date_acquired, unit_price
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        foreach ($data['items'] as $item) {
            // Skip empty descriptions
            if (empty($item['description'])) {
                continue;
            }
            
            $quantity = intval($item['quantity'] ?? 1);
            $unit = $item['unit'] ?? '';
            $description = $item['description'] ?? '';
            $propertyNumber = $item['property_number'] ?? '';
            $itemDate = !empty($item['date_acquired']) ? $item['date_acquired'] : $data['date_acquired'];
            $unitPrice = floatval(preg_replace('/[^0-9.]/', '', $item['amount'] ?? 0));
            
            $stmt->bind_param('iissssd',
                $par_id,
                $quantity,
                $unit,
                $description,
                $propertyNumber,
                $itemDate,
                $unitPrice
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to save PAR item: ' . $conn->error . ' SQL: ' . $sql);
            }
        }

        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'PAR saved successfully',
            'par_id' => $par_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log('Error in save_par.php: ' . $e->getMessage());
    http_response_code(500); // Set proper HTTP status code
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close database connection
if (isset($conn) && !$conn->connect_error) {
    $conn->close();
}
?>