<?php
header('Content-Type: application/json');
require_once 'config/db.php';

// Add cache control headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Set default filters
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    
    // Base query
    $query = "
        SELECT 
            p.par_id, p.par_no, p.entity_name, p.date_acquired, 
            CONCAT(u.full_name) as received_by_name, p.position, p.department, 
            p.total_amount, p.created_at, p.updated_at
        FROM property_acknowledgement_receipts p 
        LEFT JOIN users u ON p.received_by = u.user_id
        WHERE 1=1
    ";
    
    $countQuery = "SELECT COUNT(*) as total FROM property_acknowledgement_receipts p WHERE 1=1";
    $params = [];
    $types = "";
    
    // Add search condition if provided
    if (!empty($searchTerm)) {
        $searchTermLike = "%$searchTerm%";
        $query .= " AND (p.par_no LIKE ? OR p.entity_name LIKE ? OR CONCAT(u.full_name) LIKE ?)";
        $countQuery .= " AND (p.par_no LIKE ? OR p.entity_name LIKE ? OR EXISTS (SELECT 1 FROM users u WHERE u.user_id = p.received_by AND u.full_name LIKE ?))";
        $params[] = $searchTermLike;
        $params[] = $searchTermLike;
        $params[] = $searchTermLike;
        $types .= "sss";
    }
    
    // Add date range if provided
    if (!empty($startDate)) {
        $query .= " AND p.date_acquired >= ?";
        $countQuery .= " AND p.date_acquired >= ?";
        $params[] = $startDate;
        $types .= "s";
    }
    
    if (!empty($endDate)) {
        $query .= " AND p.date_acquired <= ?";
        $countQuery .= " AND p.date_acquired <= ?";
        $params[] = $endDate;
        $types .= "s";
    }
    
    // Add ordering
    $query .= " ORDER BY p.created_at DESC";
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    // Prepare and execute count query
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        // Only bind params for count query if there are search/date filters
        $countBindTypes = substr($types, 0, -2); // Remove the limit and offset types
        $countBindParams = array_slice($params, 0, -2); // Remove limit and offset values
        
        if (!empty($countBindTypes)) {
            $countBindParamsRef = [];
            $countBindParamsRef[] = &$countBindTypes;
            
            foreach ($countBindParams as $key => $value) {
                $countBindParamsRef[] = &$countBindParams[$key];
            }
            
            call_user_func_array([$countStmt, 'bind_param'], $countBindParamsRef);
        }
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];
    
    // Prepare and execute main query
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $bindParamsRef = [];
        $bindParamsRef[] = &$types;
        
        foreach ($params as $key => $value) {
            $bindParamsRef[] = &$params[$key];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $bindParamsRef);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pars = [];
    while ($row = $result->fetch_assoc()) {
        // For each PAR, get a property number from its first item if available
        $itemStmt = $conn->prepare("
            SELECT property_number 
            FROM par_items 
            WHERE par_id = ? 
            LIMIT 1
        ");
        $itemStmt->bind_param("i", $row['par_id']);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        
        if ($itemResult->num_rows > 0) {
            $itemRow = $itemResult->fetch_assoc();
            $row['property_number'] = $itemRow['property_number'];
        } else {
            $row['property_number'] = '';
        }
        
        $pars[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $pars,
        'total' => $totalCount,
        'limit' => $limit,
        'offset' => $offset,
        'filters' => [
            'search' => $searchTerm,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);
    
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
