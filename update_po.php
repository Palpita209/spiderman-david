<?php
header('Content-Type: application/json');
require_once 'config/db.php';

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if required columns exist in purchase_orders table and add them if needed
    $requiredColumns = [
        'ref_no' => "VARCHAR(100) AFTER po_no",
        'supplier_address' => "VARCHAR(255) AFTER supplier_name",
        'supplier_email' => "VARCHAR(100) AFTER supplier_address",
        'supplier_tel' => "VARCHAR(50) AFTER supplier_email",
        'pr_no' => "VARCHAR(50) AFTER mode_of_procurement",
        'pr_date' => "DATE AFTER pr_no",
        'place_of_delivery' => "TEXT AFTER pr_date",
        'delivery_date' => "DATE AFTER place_of_delivery",
        'payment_term' => "VARCHAR(200) DEFAULT 'Full Payment on Full Delivery' AFTER delivery_date",
        'delivery_term' => "VARCHAR(200) DEFAULT '60 days from receipt of Purchase Order' AFTER payment_term",
        'obligation_request_no' => "VARCHAR(50) AFTER delivery_term",
        'obligation_amount' => "DECIMAL(12,2) AFTER obligation_request_no"
    ];
    
    foreach ($requiredColumns as $column => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE '$column'");
        if ($check->num_rows === 0) {
            $addQuery = "ALTER TABLE purchase_orders ADD COLUMN $column $definition";
            if (!$conn->query($addQuery)) {
                error_log("Failed to add column $column: " . $conn->error);
            } else {
                error_log("Added column $column to purchase_orders table");
            }
        }
    }

    // Check if email and tel columns exist - if not, rename supplier_email/supplier_tel
    $emailCheck = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'email'");
    if ($emailCheck->num_rows === 0) {
        $check = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'supplier_email'");
        if ($check->num_rows > 0) {
            $conn->query("ALTER TABLE purchase_orders CHANGE COLUMN supplier_email email VARCHAR(100)");
        } else {
            $conn->query("ALTER TABLE purchase_orders ADD COLUMN email VARCHAR(100) AFTER supplier_address");
        }
    }
    
    $telCheck = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'tel'");
    if ($telCheck->num_rows === 0) {
        $check = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'supplier_tel'");
        if ($check->num_rows > 0) {
            $conn->query("ALTER TABLE purchase_orders CHANGE COLUMN supplier_tel tel VARCHAR(50)");
        } else {
            $conn->query("ALTER TABLE purchase_orders ADD COLUMN tel VARCHAR(50) AFTER email");
        }
    }

    // Check if po_no column exists, add if it doesn't
    $check_po_no = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'po_no'");
    if ($check_po_no->num_rows == 0) {
        $add_column = $conn->query("ALTER TABLE purchase_orders ADD COLUMN po_no VARCHAR(100) AFTER id");
        if (!$add_column) {
            throw new Exception('Error adding po_no column: ' . $conn->error);
        }
    }

    // Check if supplier_address column exists, add if it doesn't
    $check = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'supplier_address'");
    if ($check->num_rows == 0) {
        $addColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN supplier_address VARCHAR(255) AFTER supplier_name");
        if (!$addColumn) {
            throw new Exception('Error adding supplier_address column: ' . $conn->error);
        }
    }   
    
    // Check email column exists, add if it doesn't
    $checkEmail = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'email'");
    if ($checkEmail->num_rows == 0) {
        $addEmailColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN email VARCHAR(255) AFTER supplier_address");
        if (!$addEmailColumn) {
            throw new Exception('Error adding email column: ' . $conn->error);
        }
    }
    
    // Check if tel column exists, add if it doesn't
    $checkTel = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'tel'");
    if ($checkTel->num_rows == 0) {
        $addTelColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN tel VARCHAR(50) AFTER email");
        if (!$addTelColumn) {
            throw new Exception('Error adding tel column: ' . $conn->error);
        }
    }
    
    // Check if place_of_delivery column exists, add if it doesn't
    $checkPlaceOfDelivery = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'place_of_delivery'");
    if ($checkPlaceOfDelivery->num_rows == 0) {
        $addPlaceOfDeliveryColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN place_of_delivery VARCHAR(255) AFTER pr_date");
        if (!$addPlaceOfDeliveryColumn) {
            throw new Exception('Error adding place_of_delivery column: ' . $conn->error);
        }
    }
    
    // Check if delivery_date column exists, add if it doesn't
    $checkDeliveryDate = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'delivery_date'");
    if ($checkDeliveryDate->num_rows == 0) {
        $addDeliveryDateColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN delivery_date DATE AFTER place_of_delivery");
        if (!$addDeliveryDateColumn) {
            throw new Exception('Error adding delivery_date column: ' . $conn->error);
        }
    }
    
    // Check if payment_term column exists, add if it doesn't
    $checkPaymentTerm = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'payment_term'");
    if ($checkPaymentTerm->num_rows == 0) {
        $addPaymentTermColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN payment_term VARCHAR(255) AFTER delivery_date");
        if (!$addPaymentTermColumn) {
            throw new Exception('Error adding payment_term column: ' . $conn->error);
        }
    }
    
    // Check if delivery_term column exists, add if it doesn't
    $checkDeliveryTerm = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'delivery_term'");
    if ($checkDeliveryTerm->num_rows == 0) {
        $addDeliveryTermColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN delivery_term VARCHAR(255) AFTER payment_term");
        if (!$addDeliveryTermColumn) {
            throw new Exception('Error adding delivery_term column: ' . $conn->error);
        }
    }
    
    // Check if obligation_request_no column exists, add if it doesn't
    $checkObligation = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'obligation_request_no'");
    if ($checkObligation->num_rows == 0) {
        $addObligationColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN obligation_request_no VARCHAR(255) AFTER delivery_term");
        if (!$addObligationColumn) {
            throw new Exception('Error adding obligation_request_no column: ' . $conn->error);
        }
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id']) && !isset($data['po_id'])) {
        throw new Exception('PO ID is required');
    }
    
    // Use whichever ID is provided
    $poId = isset($data['id']) ? $data['id'] : $data['po_id'];

    // Check if 'id' column exists
    $checkColumnStmt = $conn->prepare("SHOW COLUMNS FROM purchase_orders LIKE 'id'");
    $checkColumnStmt->execute();
    $idColumnExists = $checkColumnStmt->get_result()->num_rows > 0;
    
    // Check if 'po_id' column exists
    $checkPoIdColumnStmt = $conn->prepare("SHOW COLUMNS FROM purchase_orders LIKE 'po_id'");
    $checkPoIdColumnStmt->execute();
    $poIdColumnExists = $checkPoIdColumnStmt->get_result()->num_rows > 0;
    
    // Construct SQL based on which column exists
    if ($idColumnExists) {
        $whereClause = "WHERE id = ?";
    } elseif ($poIdColumnExists) {
        $whereClause = "WHERE po_id = ?";
    } else {
        throw new Exception("Could not find ID column in purchase_orders table");
    }
    
    // Normalize field names to match database structure
    $placeOfDelivery = isset($data['place_of_delivery']) ? $data['place_of_delivery'] : 
                      (isset($data['delivery_place']) ? $data['delivery_place'] : '');
                      
    // Handle optional fields with default values
    $ref_no = isset($data['ref_no']) ? $data['ref_no'] : '';
    $supplier_address = isset($data['supplier_address']) ? $data['supplier_address'] : '';
    $email = isset($data['email']) ? $data['email'] : '';
    $tel = isset($data['tel']) ? $data['tel'] : '';
    $mode_of_procurement = isset($data['mode_of_procurement']) ? $data['mode_of_procurement'] : '';
    $pr_no = isset($data['pr_no']) ? $data['pr_no'] : '';
    $pr_date = isset($data['pr_date']) && !empty($data['pr_date']) ? $data['pr_date'] : NULL;
    $delivery_date = isset($data['delivery_date']) && !empty($data['delivery_date']) ? $data['delivery_date'] : NULL;
    $payment_term = isset($data['payment_term']) ? $data['payment_term'] : '';
    $delivery_term = isset($data['delivery_term']) ? $data['delivery_term'] : '';
    $obligation_request_no = isset($data['obligation_request_no']) ? $data['obligation_request_no'] : '';
    $obligation_amount = isset($data['obligation_amount']) ? floatval($data['obligation_amount']) : 0;
    $total_amount = isset($data['total_amount']) ? floatval($data['total_amount']) : 0;

    // Update PO header
    $sql = "UPDATE purchase_orders SET 
            po_no = ?, ref_no = ?, supplier_name = ?, supplier_address = ?, 
            email = ?, tel = ?, po_date = ?, mode_of_procurement = ?, 
            pr_no = ?, pr_date = ?, place_of_delivery = ?, delivery_date = ?, 
            payment_term = ?, delivery_term = ?, 
            obligation_request_no = ?, total_amount = ? 
            $whereClause";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssssssdi", 
        $data['po_no'],
        $ref_no,
        $data['supplier_name'],
        $supplier_address,
        $email,
        $tel,
        $data['po_date'],
        $mode_of_procurement,
        $pr_no,
        $pr_date,
        $placeOfDelivery,
        $delivery_date,
        $payment_term,
        $delivery_term,
        $obligation_request_no,
        $total_amount,
        $poId
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating PO: " . $stmt->error);
    }

    // Delete existing items
    $sql = "DELETE FROM po_items WHERE po_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $poId);
    
    if (!$stmt->execute()) {
        throw new Exception("Error deleting existing PO items: " . $stmt->error);
    }

    // Insert updated items
    $sql = "INSERT INTO po_items (po_id, item_description, unit, quantity, unit_cost, amount) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($data['items'] as $item) {
        // Use the correct field names (item_description, not description)
        $itemDesc = isset($item['item_description']) ? $item['item_description'] : 
                  (isset($item['description']) ? $item['description'] : '');
                  
        $unit = $item['unit'] ?? '';
        $quantity = isset($item['quantity']) ? $item['quantity'] : 
                  (isset($item['qty']) ? $item['qty'] : 0);
        $unitCost = $item['unit_cost'] ?? 0;
        $amount = $item['amount'] ?? 0;
        
        $stmt->bind_param("issidd", 
            $poId,
            $itemDesc,
            $unit,
            $quantity,
            $unitCost,
            $amount
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error inserting PO item: " . $stmt->error);
        }
    }

    // Commit the transaction
    if (!$conn->commit()) {
        throw new Exception("Error committing transaction: " . $conn->error);
    }
    
    // Get the updated PO data to return to the client
    $po = [
        'po_id' => $poId,
        'po_no' => $data['po_no'],
        'supplier_name' => $data['supplier_name'],
        'po_date' => $data['po_date'],
        'total_amount' => $data['total_amount'],
        'ref_no' => $ref_no,
        'status' => $status ?? 'Pending'
    ];
    
    // Return success response with the updated PO
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order updated successfully',
        'po' => $po
    ]);

} catch (Exception $e) {
    if ($conn->connect_error === false) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>