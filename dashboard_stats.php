<?php
/**
 * Dashboard Statistics API
 * Provides statistics for the inventory management dashboard
 */

// Include database connection
require_once 'config/db.php';

// Include metrics functions
require_once 'metrics.php';

// Set content type to JSON
header('Content-Type: application/json');

// Response array
$response = [
    'success' => false,
    'message' => '',
    'total_items' => 0,
    'inventory_count' => 0,
    'total_pos' => 0,
    'total_pars' => 0,
    'chart_data' => [],
    'stock_status' => []
];

try {
    // Get system metrics
    $metrics = getSystemMetrics();
    
    // Update response with metrics data
    $response['total_items'] = $metrics['total_items'];
    $response['inventory_count'] = $metrics['inventory_count'];
    $response['total_pos'] = $metrics['po_count'];
    $response['total_pars'] = $metrics['par_count'];
    
    // Get chart data from the prediction data
    $response['chart_data'] = $metrics['prediction_data'];
    
    // Get stock status data
    $response['stock_status'] = getStockStatusData();
    
    // Mark as successful
    $response['success'] = true;
    $response['message'] = 'Data loaded successfully';
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return JSON response
echo json_encode($response);

/**
 * Get stock status data
 * 
 * @return array Stock status data
 */
function getStockStatusData() {
    global $conn;
    
    $status = [
        'in_stock' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0
    ];
    
    // If no connection, return default values
    if (!isset($conn) || $conn->connect_error) {
        return $status;
    }
    
    try {
        // Count items in stock (quantity > 0)
        $sql = "SELECT COUNT(*) as count FROM inventory_items WHERE quantity > 5";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $status['in_stock'] = intval($row['count']);
        }
        
        // Count low stock items (quantity between 1 and 5)
        $sql = "SELECT COUNT(*) as count FROM inventory_items WHERE quantity BETWEEN 1 AND 5";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $status['low_stock'] = intval($row['count']);
        }
        
        // Count out of stock items (quantity = 0)
        $sql = "SELECT COUNT(*) as count FROM inventory_items WHERE quantity = 0";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $status['out_of_stock'] = intval($row['count']);
        }
    } catch (Exception $e) {
        error_log("Error getting stock status: " . $e->getMessage());
    }
    
    return $status;
} 