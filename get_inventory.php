<?php
// Database connection settings
require_once 'config/db.php';

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Use existing connection from db.php
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection not available");
    }
    
    // Extract all filter parameters
    $searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
    $conditionFilter = isset($_GET['condition']) ? $_GET['condition'] : '';
    $locationFilter = isset($_GET['location']) ? $_GET['location'] : '';
    $dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
    
    // Build the WHERE clause
    $whereConditions = [];
    $params = [];
    $types = '';
    
    // Add search condition if provided
    if (!empty($searchQuery)) {
        $whereConditions[] = "(item_name LIKE ? OR brand_model LIKE ? OR serial_number LIKE ? OR notes LIKE ?)";
        $searchTerm = "%" . $searchQuery . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }
    
    // Add condition filter if provided
    if (!empty($conditionFilter)) {
        $whereConditions[] = "condition_status = ?";
        $params[] = $conditionFilter;
        $types .= 's';
    }
    
    // Add location filter if provided
    if (!empty($locationFilter)) {
        $whereConditions[] = "location = ?";
        $params[] = $locationFilter;
        $types .= 's';
    }
    
    // Add date filter if provided
    if (!empty($dateFilter)) {
        // Convert dateFilter to proper SQL condition for filter
        if ($dateFilter == '30') {
            $whereConditions[] = "date_acquired >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($dateFilter == '90') {
            $whereConditions[] = "date_acquired >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
        } elseif ($dateFilter == '180') {
            $whereConditions[] = "date_acquired >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)";
        } elseif ($dateFilter == '365') {
            $whereConditions[] = "date_acquired >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
        }
    }
    
    // Construct the full SQL query
    $sql = "SELECT * FROM inventory_items";
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY item_id DESC";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process results
    $items = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Map database field names to frontend field names
            $item = [
                'item_id' => $row['item_id'],
                'item_name' => $row['item_name'],
                'brand_model' => $row['brand_model'],
                'serial_number' => $row['serial_number'],
                'purchase_date' => $row['date_acquired'],           // Map date_acquired to purchase_date
                'warranty_expiration' => $row['warranty_expiry'],   // Map warranty_expiry to warranty_expiration
                'condition' => $row['condition_status'],            // Map condition_status to condition
                'location' => $row['location'],
                'assigned_to' => $row['assigned_to'],               // Include assigned_to field
                'notes' => $row['notes'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
            
            // Format date fields for consistency
            if (!empty($item['purchase_date'])) {
                $item['purchase_date'] = date('Y-m-d', strtotime($item['purchase_date']));
            }
            if (!empty($item['warranty_expiration'])) {
                $item['warranty_expiration'] = date('Y-m-d', strtotime($item['warranty_expiration']));
            }
            
            $items[] = $item;
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $items
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