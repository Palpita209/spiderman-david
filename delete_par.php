<?php
// Include database connection
require_once 'config/db.php';

// Set proper headers to avoid any output before JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error handling - catch and convert PHP errors to JSON
function handleError($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "Error: $errstr in $errfile on line $errline"
    ]);
    exit;
}
set_error_handler('handleError');

// Get PAR ID from request (support both GET and POST)
$parId = null;

try {
    // Debug log
    error_log("Delete PAR request received: Method=" . $_SERVER['REQUEST_METHOD']);

    // Check GET parameters first
    if (isset($_GET['id'])) {
        $parId = $_GET['id'];
    }
    // Then check POST data
    else if (isset($_POST['par_id'])) {
        $parId = $_POST['par_id'];
    } 
    // Finally check JSON input
    else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if ($data !== null) {
            $parId = isset($data['par_id']) ? $data['par_id'] : (isset($data['parId']) ? $data['parId'] : null);
        }
    }

    error_log("Parsed PAR ID for deletion: " . $parId);

    if (!$parId) {
        echo json_encode(['success' => false, 'message' => 'PAR ID is required']);
        exit;
    }

    // Get a database connection
    $conn = getConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Start transaction
    $conn->begin_transaction();
    
    // Debug log
    error_log("Starting PAR deletion transaction for PAR_ID: " . $parId);
    
    // Delete PAR items first (due to foreign key constraints)
    $deleteItemsSql = "DELETE FROM par_items WHERE par_id = ?";
    $deleteItemsStmt = $conn->prepare($deleteItemsSql);
    if (!$deleteItemsStmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $deleteItemsStmt->bind_param('i', $parId);
    $deleteItemsStmt->execute();
    
    error_log("Deleted PAR items, affected rows: " . $deleteItemsStmt->affected_rows);
    
    // Delete PAR record
    $deleteParSql = "DELETE FROM property_acknowledgement_receipts WHERE par_id = ?";
    $deleteParStmt = $conn->prepare($deleteParSql);
    if (!$deleteParStmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $deleteParStmt->bind_param('i', $parId);
    $deleteParStmt->execute();
    
    error_log("Deleted PAR record, affected rows: " . $deleteParStmt->affected_rows);
    
    if ($deleteParStmt->affected_rows > 0) {
        // Commit transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'PAR deleted successfully']);
    } else {
        // Rollback transaction
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'PAR not found or could not be deleted']);
    }
    
} catch (Exception $e) {
    // Rollback transaction on error if connection established
    if (isset($conn) && !$conn->connect_error) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackEx) {
            error_log("Error in rollback: " . $rollbackEx->getMessage());
        }
    }
    
    error_log("Error in PAR deletion: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    // Close connection if established
    if (isset($conn) && !$conn->connect_error) {
        $conn->close();
    }
}
?>