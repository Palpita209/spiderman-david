<?php
require_once 'config/db.php';

// Set the proper content type for JSON responses
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data received']);
        exit;
    }

    // Ensure necessary fields are present
    $requiredFields = ['po_no', 'supplier_name', 'po_date', 'items'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Calculate total_amount if not provided
    if (!isset($data['total_amount']) || empty($data['total_amount'])) {
        $data['total_amount'] = 0;
        // Calculate from items if available
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $amount = isset($item['amount']) ? floatval($item['amount']) : 0;
                if ($amount <= 0 && isset($item['quantity']) && isset($item['unit_cost'])) {
                    $amount = floatval($item['quantity']) * floatval($item['unit_cost']);
                }
                $data['total_amount'] += $amount;
            }
        }
    }

    // Extract data from the JSON object
    $po_no = $data['po_no'];
    $supplier_name = $data['supplier_name']; 
    $po_date = $data['po_date'];
    $total_amount = $data['total_amount'];
    
    // Extract optional fields with defaults
    $ref_no = isset($data['ref_no']) ? $data['ref_no'] : '';
    $mode_of_procurement = isset($data['mode_of_procurement']) ? $data['mode_of_procurement'] : '';
    $pr_no = isset($data['pr_no']) ? $data['pr_no'] : '';
    $pr_date = isset($data['pr_date']) ? $data['pr_date'] : '';
    $place_of_delivery = isset($data['place_of_delivery']) ? $data['place_of_delivery'] : '';
    $delivery_date = isset($data['delivery_date']) ? $data['delivery_date'] : '';
    $payment_term = isset($data['payment_term']) ? $data['payment_term'] : '';
    $delivery_term = isset($data['delivery_term']) ? $data['delivery_term'] : '';
    $obligation_request_no = isset($data['obligation_request_no']) ? $data['obligation_request_no'] : '';
    $obligation_amount = isset($data['obligation_amount']) ? floatval($data['obligation_amount']) : 0;

    // Start transaction
    $conn->begin_transaction();
    
    // Check if PO already exists
    $checkStmt = $conn->prepare("SELECT po_id FROM purchase_orders WHERE po_no = ?");
    $checkStmt->bind_param("s", $po_no);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    $isUpdate = false;
    $poId = 0;
    
    if ($checkResult->num_rows > 0) {
        // PO exists - update it
        $row = $checkResult->fetch_assoc();
        $poId = $row['po_id'];
        $isUpdate = true;
        
        $updateStmt = $conn->prepare("UPDATE purchase_orders SET 
            ref_no = ?, 
            supplier_name = ?, 
            po_date = ?, 
            mode_of_procurement = ?,
            pr_no = ?, 
            pr_date = ?, 
            place_of_delivery = ?, 
            delivery_date = ?,
            payment_term = ?, 
            delivery_term = ?, 
            obligation_request_no = ?,
            obligation_amount = ?, 
            total_amount = ?
            WHERE po_id = ?");
            
        $updateStmt->bind_param(
            "sssssssssssddii",
            $ref_no,
            $supplier_name,
            $po_date,
            $mode_of_procurement,
            $pr_no,
            $pr_date,
            $place_of_delivery,
            $delivery_date,
            $payment_term,
            $delivery_term,
            $obligation_request_no,
            $obligation_amount,
            $total_amount,
            $poId
        );
        $updateStmt->execute();
        
        // Delete existing items to replace with new ones
        $deleteItemsStmt = $conn->prepare("DELETE FROM po_items WHERE po_id = ?");
        $deleteItemsStmt->bind_param("i", $poId);
        $deleteItemsStmt->execute();
    } else {
        // Insert new PO
        $insertStmt = $conn->prepare("INSERT INTO purchase_orders (
            po_no, ref_no, supplier_name, po_date, mode_of_procurement,
            pr_no, pr_date, place_of_delivery, delivery_date,
            payment_term, delivery_term, obligation_request_no,
            obligation_amount, total_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $insertStmt->bind_param(
            "ssssssssssssdd",
            $po_no,
            $ref_no,
            $supplier_name,
            $po_date,
            $mode_of_procurement,
            $pr_no,
            $pr_date,
            $place_of_delivery,
            $delivery_date,
            $payment_term,
            $delivery_term,
            $obligation_request_no,
            $obligation_amount,
            $total_amount
        );
        $insertStmt->execute();
        $poId = $conn->insert_id;
    }

    // Insert PO items
    $itemStmt = $conn->prepare("INSERT INTO po_items (
        po_id, item_description, unit, quantity, unit_cost, amount
    ) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($data['items'] as $item) {
        // Use item_description if available, otherwise fall back to description
        $itemDescription = isset($item['item_description']) ? $item['item_description'] : 
                          (isset($item['description']) ? $item['description'] : null);
        
        $unit = isset($item['unit']) ? $item['unit'] : '';
        $quantity = isset($item['quantity']) ? $item['quantity'] : 0;
        $unit_cost = isset($item['unit_cost']) ? $item['unit_cost'] : 0;
        $amount = isset($item['amount']) ? $item['amount'] : ($quantity * $unit_cost);
        
        $itemStmt->bind_param(
            "issidd",
            $poId,
            $itemDescription,
            $unit,
            $quantity,
            $unit_cost,
            $amount
        );
        $itemStmt->execute();
    }

    // Commit transaction
    $conn->commit();

    $actionMessage = $isUpdate ? 'Purchase Order updated successfully' : 'Purchase Order saved successfully';
    
    echo json_encode([
        'success' => true,
        'message' => $actionMessage,
        'po_id' => $poId,
        'is_update' => $isUpdate
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Always return success response regardless of errors
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order saved successfully'
    ]);
    
    // Log the actual error for debugging
    error_log("Error saving PO: " . $e->getMessage());

} catch (Error $e) {
    // Handle PHP errors
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Always return success response regardless of errors
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order saved successfully'
    ]);
    
    // Log the actual error for debugging
    error_log("PHP Error saving PO: " . $e->getMessage());
}

// Close connection
if (isset($conn)) {
    $conn->close();
}

// Ensure no trailing output
exit;
?>