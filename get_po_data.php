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
    
    // Get pagination parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $perPage = 10; // Number of results per page
    $offset = ($page - 1) * $perPage;
    
    // Get filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $supplier = isset($_GET['supplier']) ? trim($_GET['supplier']) : '';
    $dateRange = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';
    
    // Build the WHERE clause based on filters
    $whereClause = [];
    $params = [];
    $paramTypes = '';
    
    if (!empty($search)) {
        $whereClause[] = "(po_no LIKE ? OR supplier_name LIKE ? OR ref_no LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $paramTypes .= 'sss';
    }
    
    if (!empty($supplier)) {
        $whereClause[] = "supplier_name = ?";
        $params[] = $supplier;
        $paramTypes .= 's';
    }
    
    // Handle date range filter - ensure it works for the "Last 30 Days" option
    if (!empty($dateRange)) {
        // Check if it's numeric (direct number of days)
        if (is_numeric($dateRange)) {
            $whereClause[] = "po_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $params[] = intval($dateRange);
            $paramTypes .= 'i';
        } 
        // Could add more date range options here if needed
    }
    
    // Combine WHERE clauses
    $whereString = '';
    if (!empty($whereClause)) {
        $whereString = "WHERE " . implode(" AND ", $whereClause);
    }
    
    // Build and execute the query with pagination
    $countSql = "SELECT COUNT(*) as total FROM purchase_orders $whereString";
    $dataSql = "SELECT 
        po_id, 
        po_no, 
        ref_no, 
        supplier_name, 
        supplier_address, 
        supplier_email, 
        supplier_tel, 
        total_amount, 
        po_date, 
        mode_of_procurement, 
        place_of_delivery, 
        delivery_term, 
        payment_term, 
        obligation_request_no,
        obligation_amount
    FROM purchase_orders $whereString ORDER BY po_date DESC LIMIT ?, ?";
    
    // Get total count
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRows = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRows / $perPage);
    
    // Get paginated data
    $dataStmt = $conn->prepare($dataSql);
    $dataParams = $params;
    $dataParams[] = $offset;
    $dataParams[] = $perPage;
    $dataParamTypes = $paramTypes . 'ii';
    $dataStmt->bind_param($dataParamTypes, ...$dataParams);
    $dataStmt->execute();
    $dataResult = $dataStmt->get_result();
    
    // Process results
    $pos = [];
    while ($row = $dataResult->fetch_assoc()) {
        $pos[] = $row;
    }
    
    // Get list of unique suppliers for filter
    $supplierSql = "SELECT DISTINCT supplier_name FROM purchase_orders WHERE supplier_name IS NOT NULL AND supplier_name != '' ORDER BY supplier_name";
    $supplierStmt = $conn->prepare($supplierSql);
    $supplierStmt->execute();
    $supplierResult = $supplierStmt->get_result();
    $suppliers = [];
    while ($row = $supplierResult->fetch_assoc()) {
        $suppliers[] = $row['supplier_name'];
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'pos' => $pos,
        'suppliers' => $suppliers,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'total' => $totalRows
    ]);
    
} catch (Exception $e) {
    // Log error to server log
    error_log("Error in get_po_data.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 