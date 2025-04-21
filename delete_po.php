<?php
// Force JSON content type for all responses
header('Content-Type: application/json');

// Error reporting for debugging - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in the output

try {
    // Get database connection
    require_once 'config/db.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        throw new Exception('PO ID is required');
    }
    
    $poId = intval($data['id']);
    
    // Log deletion attempt for debugging
    error_log("Attempting to delete PO ID: $poId");
    
    // Start transaction
    $conn->begin_transaction();
    
    // Check if there's a foreign key constraint
    $checkConstraint = $conn->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                                     WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' 
                                     AND TABLE_NAME = 'po_items'
                                     AND CONSTRAINT_SCHEMA = 'ictd_inventory'");
    $hasConstraint = false;                                 
    if ($checkConstraint && $row = $checkConstraint->fetch_assoc()) {
        $hasConstraint = $row['count'] > 0;
    }
    
    // If no constraint exists to auto-delete items, delete them manually
    if (!$hasConstraint) {
        // Delete PO items first
        $stmt = $conn->prepare("DELETE FROM po_items WHERE po_id = ?");
        $stmt->bind_param("i", $poId);
        if (!$stmt->execute()) {
            throw new Exception("Error deleting PO items: " . $stmt->error);
        }
    }
    
    // Then delete the PO
    $stmt = $conn->prepare("DELETE FROM purchase_orders WHERE po_id = ?");
    $stmt->bind_param("i", $poId);
    if (!$stmt->execute()) {
        throw new Exception("Error deleting PO: " . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    error_log("Successfully deleted PO ID: $poId");
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order deleted successfully'
    ]);
} catch (Exception $e) {
    // Rollback transaction if it was started
    if (isset($conn) && $conn->connect_error === false) {
        $conn->rollback();
    }
    
    error_log("Error deleting PO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close connection
if (isset($conn)) {
    $conn->close();
}
exit;
?>