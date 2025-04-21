<?php
// Include database connection
require_once 'config/db.php';

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Get PAR ID from request
$parId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$parId) {
    echo json_encode(['success' => false, 'message' => 'PAR ID is required']);
    exit;
}

try {
    // Get PAR details
    $sql = "SELECT par.par_id, par.par_no, 
                 par.entity_name,
                 CASE 
                     WHEN par.date_acquired IS NULL OR par.date_acquired = '0000-00-00' THEN CURDATE()
                     ELSE DATE_FORMAT(par.date_acquired, '%Y-%m-%d')
                 END as date_acquired, 
                 par.received_by,
                 u.full_name as received_by_name,
                 par.position,
                 par.department,
                 par.total_amount,
                 par.remarks
           FROM property_acknowledgement_receipts par
           LEFT JOIN users u ON par.received_by = u.user_id
           WHERE par.par_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $parId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'PAR not found']);
        exit;
    }
    
    $par = $result->fetch_assoc();
    
    // Get PAR items
    $itemsSql = "SELECT par_item_id as item_id,
                      quantity,
                      unit,
                      description,
                      property_number,
                      CASE 
                          WHEN date_acquired IS NULL OR date_acquired = '0000-00-00' THEN CURDATE()
                          ELSE DATE_FORMAT(date_acquired, '%Y-%m-%d')
                      END as date_acquired,
                      amount
                FROM par_items
                WHERE par_id = ?
                ORDER BY par_item_id";
    
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param('i', $parId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    $items = [];
    $totalAmount = 0;
    
    while ($item = $itemsResult->fetch_assoc()) {
        // Calculate row total
        $item['row_total'] = floatval($item['quantity']) * floatval($item['amount']);
        $totalAmount += $item['row_total'];
        $items[] = $item;
    }
    
    // Update the total amount if it's different from calculated total
    if ($totalAmount != $par['total_amount'] && $totalAmount > 0) {
        $updateSql = "UPDATE property_acknowledgement_receipts SET total_amount = ? WHERE par_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('di', $totalAmount, $parId);
        $updateStmt->execute();
        
        // Update the value in our response
        $par['total_amount'] = $totalAmount;
    }
    
    // Create response data
    $response = [
        'success' => true,
        'data' => [
            'par' => $par,
            'items' => $items,
            'calculated_total' => $totalAmount
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?> 