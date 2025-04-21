<?php
// Database connection
require_once 'config/db.php';

// Enable error reporting for debugging but don't display errors in the output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Response headers - set proper JSON content type and prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Debug helper function
function debug_log($message, $data = null) {
    $log = "[PO_DETAILS] " . $message;
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log .= " - " . json_encode($data);
        } else {
            $log .= " - " . $data;
        }
    }
    error_log($log);
}

try {
    // Use the existing connection from db.php
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection not available");
    }
    
    // Get the PO ID parameter
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        throw new Exception("Invalid Purchase Order ID");
    }
    
    debug_log("Processing request for PO ID: $id");
    
    // Check for additional columns in purchase_orders table
    $requiredColumns = [
        'supplier_address', 'supplier_email', 'supplier_tel',
        'place_of_delivery', 'delivery_date', 'payment_term', 'delivery_term',
        'obligation_request_no', 'obligation_amount', 'ref_no', 'mode_of_procurement'
    ];
    
    foreach ($requiredColumns as $column) {
        $check = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE '$column'");
        if ($check->num_rows === 0) {
            debug_log("Column '$column' does not exist in purchase_orders table, will attempt to add it");
            
            // Define column type based on name
            $colType = "";
            if (strpos($column, 'date') !== false) {
                $colType = "DATE";
            } else if (strpos($column, 'amount') !== false) {
                $colType = "DECIMAL(15,2) DEFAULT 0";
            } else {
                $colType = "VARCHAR(255)";
            }
            
            $addQuery = "ALTER TABLE purchase_orders ADD COLUMN $column $colType";
            $conn->query($addQuery);
        }
    }
    
    // Check for required columns in po_items table
    $tableExists = $conn->query("SHOW TABLES LIKE 'po_items'");
    if ($tableExists->num_rows == 0) {
        // Create po_items table with all required fields
        $createTable = "CREATE TABLE po_items (
            po_item_id INT PRIMARY KEY AUTO_INCREMENT,
            po_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            unit VARCHAR(50) DEFAULT 'pc',
            item_description TEXT,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
            unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            amount DECIMAL(12,2) DEFAULT 0.00,
            total_cost DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
            FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id) ON DELETE CASCADE
        )";
        
        if (!$conn->query($createTable)) {
            debug_log("Failed to create po_items table: " . $conn->error);
        } else {
            debug_log("Created po_items table with all required fields");
        }
    } else {
        // Check if po_items table has necessary columns
        $poItemsColumns = [
            'po_item_id' => "INT PRIMARY KEY AUTO_INCREMENT",
            'item_name' => "VARCHAR(255) AFTER po_id",
            'unit' => "VARCHAR(50) DEFAULT 'pc' AFTER item_name",
            'item_description' => "TEXT AFTER unit",
            'quantity' => "DECIMAL(12,2) NOT NULL DEFAULT 1 AFTER item_description",
            'unit_cost' => "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER quantity",
            'amount' => "DECIMAL(12,2) DEFAULT 0.00 AFTER unit_cost",
            'total_cost' => "DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED"
        ];
        
        foreach ($poItemsColumns as $column => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM po_items LIKE '$column'");
            if ($check->num_rows === 0) {
                debug_log("Column '$column' does not exist in po_items table, adding it");
                
                $addQuery = "ALTER TABLE po_items ADD COLUMN $column $definition";
                if (!$conn->query($addQuery)) {
                    debug_log("Failed to add column $column: " . $conn->error);
                } else {
                    debug_log("Added $column column to po_items table");
                }
            }
        }
    }
    
    // Query for the PO details with all columns
    $sql = "SELECT po.*, 
                 COALESCE(po.supplier_address, '') as supplier_address,
                 COALESCE(po.supplier_email, '') as supplier_email,
                 COALESCE(po.supplier_tel, '') as supplier_tel,
                 COALESCE(po.place_of_delivery, '') as place_of_delivery,
                 COALESCE(po.delivery_date, '') as delivery_date,
                 COALESCE(po.payment_term, '') as payment_term,
                 COALESCE(po.delivery_term, '') as delivery_term,
                 COALESCE(po.obligation_request_no, '') as obligation_request_no,
                 COALESCE(po.obligation_amount, 0) as obligation_amount,
                 COALESCE(po.ref_no, '') as ref_no,
                 COALESCE(po.mode_of_procurement, '') as mode_of_procurement
           FROM purchase_orders po 
           WHERE po.po_id = ?";
           
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        debug_log("PO not found in purchase_orders table, checking uploaded_pos table");
        
        // Check in uploaded_pos table
        $sql = "SELECT * FROM uploaded_pos WHERE po_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Purchase Order not found in any table");
        }
    }
    
    $po = $result->fetch_assoc();
    debug_log("Found PO details", $po);
    
    // Format dates for UI display
    if (!empty($po['po_date'])) {
        $po['po_date_formatted'] = date('m/d/Y', strtotime($po['po_date']));
    }
    if (!empty($po['pr_date'])) {
        $po['pr_date_formatted'] = date('m/d/Y', strtotime($po['pr_date']));
    }
    if (!empty($po['delivery_date'])) {
        $po['delivery_date_formatted'] = date('m/d/Y', strtotime($po['delivery_date']));
    }
    
    // Query for the PO items - first try the po_items table
    $items = [];
    
    // Use the already checked po_items table
    debug_log("Checking po_items table for items");
    
    // Modify the query to include all necessary fields from po_items
    $itemsSql = "SELECT * FROM po_items WHERE po_id = ?";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $id);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    while ($item = $itemsResult->fetch_assoc()) {
        // Ensure all needed fields are available
        $formattedItem = [
            'po_item_id' => $item['po_item_id'] ?? null,
            'id' => $item['po_item_id'] ?? null,
            'item_id' => $item['po_item_id'] ?? null,
            'unit' => $item['unit'] ?? 'pc',
            'item_name' => $item['item_name'] ?? '',
            'description' => $item['item_description'] ?? $item['description'] ?? '',
            'item_description' => $item['item_description'] ?? $item['description'] ?? '',
            'quantity' => floatval($item['quantity'] ?? $item['qty'] ?? 0),
            'qty' => floatval($item['quantity'] ?? $item['qty'] ?? 0),
            'unit_cost' => floatval($item['unit_cost'] ?? $item['unit_price'] ?? $item['price'] ?? 0),
            'unit_price' => floatval($item['unit_cost'] ?? $item['unit_price'] ?? $item['price'] ?? 0),
            'amount' => floatval($item['amount'] ?? $item['total_cost'] ?? $item['total'] ?? 0),
            'total_cost' => floatval($item['total_cost'] ?? $item['amount'] ?? $item['total'] ?? 0)
        ];
        
        // Calculate amount if it's zero
        if ($formattedItem['amount'] <= 0 && $formattedItem['quantity'] > 0 && $formattedItem['unit_cost'] > 0) {
            $formattedItem['amount'] = $formattedItem['quantity'] * $formattedItem['unit_cost'];
        }
        
        // Debug item data
        debug_log("Formatted item: " . json_encode($formattedItem));
        
        $items[] = $formattedItem;
    }
    
    debug_log("Found " . count($items) . " items in po_items table");
    
    // If no items were found, check if there's an alternative table
    if (count($items) === 0) {
        // Try the uploaded_po_items table if it exists
        $altTableCheck = $conn->query("SHOW TABLES LIKE 'uploaded_po_items'");
        $uploadedItemsExists = $altTableCheck && $altTableCheck->num_rows > 0;
        
        if ($uploadedItemsExists) {
            debug_log("Checking uploaded_po_items table");
            $altItemsSql = "SELECT * FROM uploaded_po_items WHERE po_id = ?";
            $altItemsStmt = $conn->prepare($altItemsSql);
            $altItemsStmt->bind_param("i", $id);
            $altItemsStmt->execute();
            $altItemsResult = $altItemsStmt->get_result();
            
            while ($item = $altItemsResult->fetch_assoc()) {
                // Format the same way
                $formattedItem = [
                    'po_item_id' => $item['id'] ?? $item['item_id'] ?? null,
                    'id' => $item['id'] ?? $item['item_id'] ?? null,
                    'item_id' => $item['id'] ?? $item['item_id'] ?? null,
                    'unit' => $item['unit'] ?? 'pc',
                    'item_name' => $item['item_name'] ?? '',
                    'description' => $item['description'] ?? $item['item_description'] ?? '',
                    'item_description' => $item['item_description'] ?? $item['description'] ?? '',
                    'quantity' => floatval($item['quantity'] ?? $item['qty'] ?? 0),
                    'qty' => floatval($item['quantity'] ?? $item['qty'] ?? 0),
                    'unit_cost' => floatval($item['unit_cost'] ?? $item['unit_price'] ?? $item['price'] ?? 0),
                    'unit_price' => floatval($item['unit_cost'] ?? $item['unit_price'] ?? $item['price'] ?? 0),
                    'amount' => floatval($item['amount'] ?? $item['total'] ?? 0),
                    'total_cost' => floatval($item['total_cost'] ?? $item['amount'] ?? $item['total'] ?? 0)
                ];
                
                // Calculate amount if it's zero
                if ($formattedItem['amount'] <= 0 && $formattedItem['quantity'] > 0 && $formattedItem['unit_cost'] > 0) {
                    $formattedItem['amount'] = $formattedItem['quantity'] * $formattedItem['unit_cost'];
                }
                
                // Debug item data
                debug_log("Formatted item from alt table: " . json_encode($formattedItem));
                
                $items[] = $formattedItem;
            }
            
            debug_log("Found " . count($items) . " items in uploaded_po_items table");
        } else {
            debug_log("No items tables found or accessible");
        }
    }
    
    // If STILL no items, create a dummy item for testing
    if (count($items) === 0) {
        debug_log("No items found in any table, creating dummy item for testing");
        $items[] = [
            'po_item_id' => 1,
            'id' => 1,
            'item_id' => 1,
            'unit' => 'pc',
            'item_name' => 'Sample item for testing',
            'description' => 'Sample item for testing - No items found in database',
            'item_description' => 'Sample item for testing - No items found in database',
            'quantity' => 1,
            'qty' => 1,
            'unit_cost' => 1000,
            'unit_price' => 1000,
            'amount' => 1000,
            'total_cost' => 1000
        ];
    }
    
    // Final log of items
    debug_log("Final items count: " . count($items));
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'po' => $po,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    // Log error to server log
    debug_log("ERROR: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>