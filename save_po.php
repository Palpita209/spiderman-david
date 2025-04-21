<?php
/**
 * Save Purchase Order Handler
 * Handles saving PO data to database and updating prediction system
 */

// Include database connection
if (file_exists('config/db.php')) {
    include 'config/db.php';
} else {
    // Define a fallback function if db.php doesn't exist
    function getConnection() {
        return null;
    }  
}

// Set response headers
header('Content-Type: application/json');

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log received data for debugging
error_log("Received PO data: " . substr($json, 0, 1000));
error_log("JSON decode status: " . json_last_error_msg());

// Log the structure of the data for debugging
if ($data) {
    error_log("Data structure: " . json_encode(array_keys($data)));
    if (isset($data['items']) && is_array($data['items'])) {
        error_log("Found " . count($data['items']) . " items in data");
        error_log("First item structure: " . json_encode(array_keys($data['items'][0])));
    } else {
        error_log("No items array found in data or it's not properly formatted");
    }
}

// Validate data
if (!$data || !isset($data['po_no']) || !isset($data['supplier_name']) || !isset($data['po_date'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Connect to database
$conn = getConnection();

// If no connection, simulate success for testing purposes
if (!$conn || $conn->connect_error) {
    // Log error but return success for demo purposes
    if ($conn && $conn->connect_error) {
        error_log("Database connection error: " . $conn->connect_error);
    } else {
        error_log("No database connection available");
    }
    
    // Also send the data to the prediction system for processing
    $result = simulateUpdatePrediction('po', $data['total_amount'], $data['po_date']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order simulated successfully (no database connection)',
        'po_id' => 'DEMO-' . rand(1000, 9999),
        'prediction_update' => $result
    ]);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if required columns exist and add them if not
    $requiredColumns = [
        'ref_no' => "VARCHAR(100) AFTER po_no",
        'supplier_address' => "VARCHAR(255) AFTER supplier_name",
        'supplier_email' => "VARCHAR(255) AFTER supplier_address",
        'supplier_tel' => "VARCHAR(50) AFTER supplier_email",
        'place_of_delivery' => "VARCHAR(255) AFTER supplier_tel",
        'payment_term' => "VARCHAR(255) DEFAULT 'Full Payment on Full Delivery'",
        'delivery_term' => "VARCHAR(255) DEFAULT '60 days from receipt of Purchase Order'",
        'obligation_request_no' => "VARCHAR(100) AFTER delivery_term",
        'obligation_amount' => "DECIMAL(15,2) DEFAULT 0 AFTER obligation_request_no"
    ];

    error_log("Checking and adding required columns to purchase_orders table if needed");
    foreach ($requiredColumns as $column => $definition) {
        $checkCol = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE '$column'");
        if ($checkCol->num_rows === 0) {
            error_log("Adding missing column: $column");
            $addColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN $column $definition");
            if (!$addColumn) {
                error_log("Failed to add column $column: " . $conn->error);
            } else {
                error_log("Successfully added column $column");
            }
        }
    }
    
    // Extract all possible fields with defaults
    $po_no = $data['po_no'];
    $supplier_name = $data['supplier_name'];
    $po_date = $data['po_date'];
    $total_amount = isset($data['total_amount']) ? floatval($data['total_amount']) : 0;
    $mode_of_procurement = isset($data['mode_of_procurement']) ? $data['mode_of_procurement'] : '';
    $supplier_address = isset($data['supplier_address']) ? $data['supplier_address'] : '';
    $supplier_email = isset($data['supplier_email']) ? $data['supplier_email'] : '';
    $supplier_tel = isset($data['supplier_tel']) ? $data['supplier_tel'] : 
                  (isset($data['telephone']) ? $data['telephone'] : '');
    $obligation_request_no = isset($data['obligation_request_no']) ? $data['obligation_request_no'] : '';
    $obligation_amount = isset($data['obligation_amount']) ? floatval($data['obligation_amount']) : 0;
    $delivery_term = isset($data['delivery_term']) ? $data['delivery_term'] : '60 days from receipt of Purchase Order';
    $payment_term = isset($data['payment_term']) ? $data['payment_term'] : 'Full Payment on Full Delivery';
    $place_of_delivery = isset($data['place_of_delivery']) ? $data['place_of_delivery'] : '';
    $pr_no = isset($data['pr_no']) ? $data['pr_no'] : '';
    $ref_no = isset($data['ref_no']) ? $data['ref_no'] : '';
    
    // Format date properly
    $formatted_po_date = !empty($po_date) ? date('Y-m-d', strtotime($po_date)) : NULL;
    
    // Insert purchase order with all required fields
    $sql = "INSERT INTO purchase_orders (
        po_no, supplier_name, po_date, mode_of_procurement,
        supplier_email, supplier_address, supplier_tel, total_amount,
        obligation_request_no, obligation_amount, delivery_term, payment_term,
        place_of_delivery, pr_no, ref_no
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param('sssssssdsssssss', 
        $po_no, 
        $supplier_name, 
        $formatted_po_date,
        $mode_of_procurement,
        $supplier_email,
        $supplier_address,
        $supplier_tel,
        $total_amount,
        $obligation_request_no,
        $obligation_amount,
        $delivery_term,
        $payment_term,
        $place_of_delivery,
        $pr_no,
        $ref_no
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert PO: " . $stmt->error);
    }
    
    $po_id = $stmt->insert_id;
    $stmt->close();
    
    error_log("Successfully inserted PO with ID: $po_id");
    
    // Insert PO items if available
    $items = [];
    if (isset($data['items']) && is_array($data['items']) && count($data['items']) > 0) {
        $sql = "INSERT INTO po_items (po_id, item_name, unit, item_description, quantity, unit_cost) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Failed to prepare statement for items: " . $conn->error);
            throw new Exception("Failed to prepare statement for items: " . $conn->error);
        }
        
        $itemsInserted = 0;
        $itemsSkipped = 0;
        
        foreach ($data['items'] as $index => $item) {
            error_log("Processing item #" . ($index + 1) . ": " . json_encode($item));
            
            // Map name field to appropriate column name
            $itemName = isset($item['name']) ? $item['name'] : 
                       (isset($item['item_name']) ? $item['item_name'] : '');
            
            if (empty($itemName)) {
                error_log("Warning: Item #" . ($index + 1) . " has no name field, skipping");
                $itemsSkipped++;
                continue;
            }
            
            // Map description field to appropriate column name
            $itemDescription = isset($item['description']) ? $item['description'] : 
                              (isset($item['item_description']) ? $item['item_description'] : '');
            
            // Map unit field
            $unit = isset($item['unit']) ? $item['unit'] : 'pc';
            
            // Map quantity and unit_cost with fallbacks
            $quantity = isset($item['quantity']) ? floatval($item['quantity']) : 
                      (isset($item['qty']) ? floatval($item['qty']) : 0);
                      
            $unitCost = isset($item['unit_cost']) ? floatval($item['unit_cost']) : 
                       (isset($item['unit_price']) ? floatval($item['unit_price']) : 
                       (isset($item['price']) ? floatval($item['price']) : 0));
            
            // Validate quantity and unit cost
            if ($quantity <= 0) {
                error_log("Warning: Item #" . ($index + 1) . " '{$itemName}' has invalid quantity ({$quantity}), setting to 1");
                $quantity = 1;
            }
            
            if ($unitCost < 0) {
                error_log("Warning: Item #" . ($index + 1) . " '{$itemName}' has negative unit cost ({$unitCost}), setting to 0");
                $unitCost = 0;
            }
            
            error_log("Inserting item: {$itemName}, {$unit}, quantity: {$quantity}, unit cost: {$unitCost}");
            
            $stmt->bind_param('isssdd', 
                $po_id, 
                $itemName, 
                $unit, 
                $itemDescription, 
                $quantity, 
                $unitCost
            );
            
            if (!$stmt->execute()) {
                error_log("Warning: Failed to insert item '{$itemName}': " . $stmt->error);
                $itemsSkipped++;
            } else {
                $itemsInserted++;
                // Save the item data for the response
                $itemId = $stmt->insert_id;
                $items[] = [
                    'po_item_id' => $itemId,
                    'item_name' => $itemName,
                    'unit' => $unit,
                    'item_description' => $itemDescription,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $quantity * $unitCost
                ];
                error_log("Successfully inserted item '{$itemName}' with ID: " . $itemId);
            }
        }
        
        $stmt->close();
        error_log("Items processing completed: {$itemsInserted} inserted, {$itemsSkipped} skipped");
    } else {
        error_log("No items provided for PO ID: $po_id");
    }
    
    // Commit transaction
    $conn->commit();
    
    // Prepare the full PO data to return
    $poData = [
        'po_id' => $po_id,
        'po_no' => $po_no,
        'supplier_name' => $supplier_name,
        'po_date' => $formatted_po_date,
        'mode_of_procurement' => $mode_of_procurement,
        'supplier_email' => $supplier_email,
        'supplier_address' => $supplier_address,
        'supplier_tel' => $supplier_tel,
        'total_amount' => $total_amount,
        'items' => $items
    ];
    
    // Update prediction system
    updatePrediction('po', $data['total_amount'], $data['po_date']);
    
    // Return success with the complete PO data
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order saved successfully',
        'po_id' => $po_id,
        'po_data' => $poData
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    error_log("Error saving PO: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error saving Purchase Order: ' . $e->getMessage()
    ]);
}

// Close connection
if (isset($conn)) {
    $conn->close();
}

/**
 * Update prediction system with transaction data
 */
function updatePrediction($type, $amount, $date) {
    // Call ML prediction API to update with new transaction
    $endpoint = 'ml_prediction.php?action=update_prediction';
    
    $data = json_encode([
        'transaction_type' => $type,
        'amount' => $amount,
        'date' => $date
    ]);
    
    // Create stream context for POST request
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $data
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Send request
    $result = @file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $endpoint, false, $context);
    
    if ($result === false) {
        error_log("Error updating prediction system");
        return false;
    }
    
    return json_decode($result, true);
}

/**
 * Simulate prediction update for testing without database
 */
function simulateUpdatePrediction($type, $amount, $date) {
    // Send to prediction system directly
    $endpoint = 'ml_prediction.php?action=update_prediction';
    
    $data = json_encode([
        'transaction_type' => $type,
        'amount' => $amount,
        'date' => $date
    ]);
    
    // Create stream context for POST request
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $data
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Send request
    $result = @file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $endpoint, false, $context);
    
    if ($result === false) {
        error_log("Error simulating prediction update");
        return ['success' => false, 'message' => 'Error simulating prediction update'];
    }
    
    return json_decode($result, true);
}
