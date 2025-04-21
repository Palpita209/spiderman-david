<?php
require_once 'config/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Pragma: no-cache');
header('Expires: 0');

// Check if this is a force refresh request and add response header
$forceRefresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] == '1';
if ($forceRefresh) {
    // Add extra headers to prevent any caching
    error_log('Force refresh requested for PAR data');
    header('X-Force-Refresh: true');
}

try {
    $request_method = $_SERVER["REQUEST_METHOD"];
        if ($request_method === 'POST') {
        error_log('get_par.php - POST request received, redirecting to add_par.php');
        $json_data = file_get_contents('php://input');
                $ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/add_par.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Error forwarding request to add_par.php: ' . curl_error($ch));
        }
        
        curl_close($ch);
        http_response_code($http_code);
        echo $result;
        exit;
    }
    
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $parId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if ($parId) {
        // Fetch specific PAR with items
        $sql = "SELECT p.*, 
                CASE 
                    WHEN p.date_acquired IS NULL OR p.date_acquired = '0000-00-00' THEN CURDATE()
                    ELSE DATE_FORMAT(p.date_acquired, '%Y-%m-%d')
                END as date_acquired, 
                u.full_name as received_by_name, 
                u.position, 
                u.department
                FROM property_acknowledgement_receipts p
                LEFT JOIN users u ON p.received_by = u.user_id
                WHERE p.par_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database query preparation failed"]);
            exit;
        }
        
        $stmt->bind_param("i", $parId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            error_log("Database error while fetching PAR: " . $conn->error);
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to fetch PAR"]);
            exit;
        }
        
        if ($result->num_rows === 0) {
            throw new Exception('PAR not found');
        }
        
        $par = $result->fetch_assoc();
        
        // Fetch PAR items
        $itemStmt = $conn->prepare("
            SELECT pi.*,
            CASE 
                WHEN pi.date_acquired IS NULL OR pi.date_acquired = '0000-00-00' THEN DATE_FORMAT(p.date_acquired, '%Y-%m-%d')
                ELSE DATE_FORMAT(pi.date_acquired, '%Y-%m-%d')
            END as date_acquired 
            FROM par_items pi
            JOIN property_acknowledgement_receipts p ON pi.par_id = p.par_id
            WHERE pi.par_id = ?
            ORDER BY pi.par_item_id
        ");
        $itemStmt->bind_param("i", $parId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        $items = [];
        
        if (!$itemResult) {
            error_log("Database error while fetching PAR items: " . $conn->error);
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to fetch PAR items"]);
            exit;
        }
        
        // Check column names for items table
        $itemColumnsResult = $conn->query("SHOW COLUMNS FROM par_items");
        $itemColumns = [];
        while ($column = $itemColumnsResult->fetch_assoc()) {
            $itemColumns[] = $column['Field'];
        }
        $hasItemDescription = in_array('item_description', $itemColumns);
        $hasDescription = in_array('description', $itemColumns);
        $hasUnitPrice = in_array('unit_price', $itemColumns);
        $hasAmount = in_array('amount', $itemColumns);
        
        while ($item = $itemResult->fetch_assoc()) {
            $transformedItem = [];
            
            // Map fields with proper names
            $transformedItem['quantity'] = $item['quantity'] ?? 1;
            $transformedItem['unit'] = $item['unit'] ?? '';
            
            // Handle different description field names
            if ($hasItemDescription && isset($item['item_description'])) {
                $transformedItem['description'] = $item['item_description'];
            } else if ($hasDescription && isset($item['description'])) {
                $transformedItem['description'] = $item['description'];
            } else {
                // Fallback to a default or empty value
                $transformedItem['description'] = $item['item_description'] ?? $item['description'] ?? '';
            }
            
            $transformedItem['property_number'] = $item['property_number'] ?? '';
            $transformedItem['date_acquired'] = $item['date_acquired'] ?? date('Y-m-d');
            
            // Handle different price field names
            if ($hasUnitPrice && isset($item['unit_price'])) {
                $transformedItem['amount'] = floatval($item['unit_price']);
            } else if ($hasAmount && isset($item['amount'])) {
                $transformedItem['amount'] = floatval($item['amount']);
            } else {
                // Fallback to a default or empty value
                $transformedItem['amount'] = floatval($item['unit_price'] ?? $item['amount'] ?? 0);
            }
            
            $items[] = $transformedItem;
        }
        
        $par["items"] = $items;
        
        // Calculate total if not set
        if (empty($par['total_amount']) || floatval($par['total_amount']) == 0) {
            $total = 0;
            foreach ($items as $item) {
                $total += floatval($item['quantity']) * floatval($item['amount']);
            }
            if ($total > 0) {
                $par['total_amount'] = $total;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $par
        ]);
    } else {
        // Fetch all PARs with basic info
        $stmt = $conn->prepare("
            SELECT p.par_id, 
                  p.par_no, 
                  p.entity_name, 
                  CASE 
                      WHEN p.date_acquired IS NULL OR p.date_acquired = '0000-00-00' THEN CURDATE()
                      ELSE DATE_FORMAT(p.date_acquired, '%Y-%m-%d')
                  END as date_acquired, 
                  u.full_name as received_by_name, 
                  p.position, 
                  p.department, 
                  p.total_amount,
                  (SELECT property_number FROM par_items WHERE par_id = p.par_id LIMIT 1) as property_number
            FROM property_acknowledgement_receipts p
            LEFT JOIN users u ON p.received_by = u.user_id
            ORDER BY p.date_acquired DESC
        ");
        
        if (!$stmt) {
            error_log("Prepare failed for PARs list: " . $conn->error);
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database query preparation failed"]);
            exit;
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            error_log("Database error while fetching PARs list: " . $conn->error);
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Failed to fetch PAR list"]);
            exit;
        }
        
        $pars = [];
        while ($row = $result->fetch_assoc()) {
            // Ensure date is formatted correctly
            if (isset($row['date_acquired'])) {
                // Already formatted by CASE in SQL, but make sure it's not null or empty
                if (empty($row['date_acquired'])) {
                    $row['date_acquired'] = date('Y-m-d'); // Fallback to today if empty
                }
            }
            
            $pars[] = $row;
        }
        
        // Debug - log the data being returned
        error_log('Returning PAR data: ' . count($pars) . ' records found');
        
        echo json_encode([
            'success' => true,
            'data' => $pars,
            'count' => count($pars)
        ]);
    }
    
} catch (Exception $e) {
    error_log('Error in get_par.php: ' . $e->getMessage());
    http_response_code(500); // Set proper HTTP status code
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ]);
}

// Close database connection
if (isset($conn) && !$conn->connect_error) {
    $conn->close();
}
?> 