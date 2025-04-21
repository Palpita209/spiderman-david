<?php
include 'config/db.php';
include 'iot_blockchain_integration.php';

header('Content-Type: application/json');

try {
    // Check if connection exists from db.php
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn ? $conn->connect_error : 'No connection object'));
    }

    // Initialize IoT Blockchain Integration
    $integration = new IoTBlockchainIntegration();

    // Get filter parameters
    $itemId = isset($_GET['item_id']) ? $_GET['item_id'] : null;
    $trackHistory = isset($_GET['track_history']) && $_GET['track_history'] === 'true';
    
    // Check if required tables exist
    $tablesExist = true;
    
    // Check inventory_items table
    $checkInventoryTable = $conn->query("SHOW TABLES LIKE 'inventory_items'");
    if ($checkInventoryTable->num_rows == 0) {
        $tablesExist = false;
        error_log("inventory_items table does not exist");
    }
    
    // Check item_conditions table
    $checkConditionsTable = $conn->query("SHOW TABLES LIKE 'item_conditions'");
    if ($checkConditionsTable->num_rows == 0) {
        // We can proceed without this table, but should log it
        error_log("item_conditions table does not exist - will use basic condition data only");
    }
    
    if (!$tablesExist) {
        // Return empty array with message if tables don't exist
        echo json_encode([
            [
                'error' => true,
                'message' => 'Required database tables do not exist. Please use the "Setup Condition Monitoring" button to create them.'
            ]
        ]);
        exit;
    }
    
    // Build the query to get inventory items with condition data
    $query = "SELECT i.*, 
                     i.last_updated AS basic_last_updated";
    
    // Check if item_conditions table exists before joining it
    if ($checkConditionsTable->num_rows > 0) {
        $query .= ", COALESCE(c.last_updated, i.last_updated) AS condition_updated,
                     COALESCE(c.condition_details, '') AS condition_details";
    } else {
        $query .= ", i.last_updated AS condition_updated,
                     '' AS condition_details";
    }
    
    $query .= " FROM inventory_items i";
    
    // Join with item_conditions if it exists
    if ($checkConditionsTable->num_rows > 0) {
        $query .= " LEFT JOIN item_conditions c ON i.item_id = c.item_id";
    }
    
    // Add filters if needed
    if ($itemId) {
        $query .= " WHERE i.item_id = ?";
        $params = [$itemId];
        $types = "i";
    } else {
        $params = [];
        $types = "";
    }
    
    // Order by last updated, using the appropriate column
    if ($checkConditionsTable->num_rows > 0) {
        $query .= " ORDER BY COALESCE(c.last_updated, i.last_updated) DESC";
    } else {
        $query .= " ORDER BY i.last_updated DESC";
    }
    
    // Log the query for debugging
    error_log("Inventory conditions query: " . $query);
    
    // Execute the query
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepared statement error: " . $conn->error);
        }
        
        // Bind parameters if present
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }

    if (!$result) {
        throw new Exception("Error retrieving inventory conditions: " . $conn->error);
    }

    $items = [];
    while ($row = $result->fetch_assoc()) {
        // Process each item's condition through the blockchain integration
        $item = [
            'item_id' => $row['item_id'],
            'item_name' => $row['item_name'] ?? 'Unknown Item',
            'serial' => $row['serial_number'] ?? 'N/A',
            'location' => $row['location'] ?? 'Unknown',
            'condition' => isset($row['condition']) ? $row['condition'] : 'Good', // Default to Good if not set
            'condition_details' => $row['condition_details'] ?? '',
            'last_updated' => $row['condition_updated'] ?? date('Y-m-d H:i:s')
        ];
        
        // Get condition status from blockchain integration
        $status = $integration->trackInventoryCondition($item);
        
        // Add status information to the item
        $item['status'] = $status['text'] ?? 'Unknown';
        $item['status_class'] = $status['class'] ?? 'bg-secondary';
        $item['blockchain_hash'] = $status['hash'] ?? '';
        
        // If tracking history is requested and this is for a specific item
        // and the blockchain_transactions table exists
        $checkBlockchainTable = $conn->query("SHOW TABLES LIKE 'blockchain_transactions'");
        if ($trackHistory && $itemId && $checkBlockchainTable->num_rows > 0) {
            // Get condition history from blockchain
            $item['history'] = getItemConditionHistory($conn, $itemId);
        }
        
        $items[] = $item;
    }

    // Return the inventory conditions
    echo json_encode($items);

} catch (Exception $e) {
    // Log the error for server-side debugging
    error_log("Inventory Condition Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading inventory condition data: ' . $e->getMessage()
    ]);
}

/**
 * Get condition history for an item from the blockchain
 * @param mysqli $conn Database connection
 * @param int $itemId Item ID to get history for
 * @return array Condition history
 */
function getItemConditionHistory($conn, $itemId) {
    $history = [];
    
    // Check if blockchain_transactions table exists
    $checkBlockchainTable = $conn->query("SHOW TABLES LIKE 'blockchain_transactions'");
    if ($checkBlockchainTable->num_rows == 0) {
        return $history; // Return empty history if table doesn't exist
    }
    
    // Query blockchain transactions table for condition history
    $query = "SELECT hash, data_json, timestamp 
              FROM blockchain_transactions 
              WHERE data_type = 'condition_warning' 
              AND JSON_EXTRACT(data_json, '$.item_id') = ?
              ORDER BY timestamp DESC 
              LIMIT 10";
    
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepared statement error: " . $conn->error);
        }
        
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $data = json_decode($row['data_json'], true);
            $history[] = [
                'hash' => $row['hash'],
                'condition' => $data['condition'] ?? 'Unknown',
                'status' => $data['status'] ?? 'Unknown',
                'timestamp' => $row['timestamp']
            ];
        }
    } catch (Exception $e) {
        error_log("Error retrieving condition history: " . $e->getMessage());
    }
    
    return $history;
}

if (isset($conn)) {
    $conn->close();
}
?> 