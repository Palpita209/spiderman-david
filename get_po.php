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

try {
    // Use the existing connection from db.php
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection not available");
    }
    
    // Query to get all purchase orders
    $sql = "SELECT * FROM purchase_orders ORDER BY po_id DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Error executing query: " . $conn->error);
    }
    
    $purchaseOrders = array();
    
    // Fetch all purchase orders
    while ($row = $result->fetch_assoc()) {
        // Format dates for consistency
        if (isset($row['po_date']) && $row['po_date']) {
            $row['po_date'] = date('Y-m-d', strtotime($row['po_date']));
        }
        if (isset($row['pr_date']) && $row['pr_date']) {
            $row['pr_date'] = date('Y-m-d', strtotime($row['pr_date']));
        }
        if (isset($row['delivery_date']) && $row['delivery_date']) {
            $row['delivery_date'] = date('Y-m-d', strtotime($row['delivery_date']));
        }
        
        // Get items for this PO
        $po_id = $row['po_id'];
        $itemsSql = "SELECT * FROM po_items WHERE po_id = ?";
        $stmtItems = $conn->prepare($itemsSql);
        $stmtItems->bind_param("i", $po_id);
        $stmtItems->execute();
        $itemsResult = $stmtItems->get_result();
        
        $items = array();
        while ($itemRow = $itemsResult->fetch_assoc()) {
            $items[] = $itemRow;
        }
        
        // Add items to the PO
        $row['items'] = $items;
        
        // Add the PO to the array
        $purchaseOrders[] = $row;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $purchaseOrders
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Do not close the connection here as it might be used by other code
    // It will be closed when the script finishes
}

// Ensure no trailing output
exit;
?>