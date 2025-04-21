<?php
// Include database connection
require_once 'config/db.php';

// Set response headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    // Check database connection
    $conn = getConnection();
    $db_connection = $conn && !$conn->connect_error;
    
    $response = [
        'success' => true,
        'db_connection' => $db_connection,
        'par_table_exists' => false,
        'par_items_table_exists' => false,
        'existing_fields' => [],
        'missing_fields' => []
    ];
    
    if (!$db_connection) {
        throw new Exception('Database connection failed');
    }
    
    // Check PAR table structure
    $par_table_check = $conn->query("SHOW TABLES LIKE 'property_acknowledgement_receipts'");
    $response['par_table_exists'] = $par_table_check->num_rows > 0;
    
    if ($response['par_table_exists']) {
        // Check required fields
        $required_fields = ['par_id', 'par_no', 'entity_name', 'date_acquired', 'received_by', 'notes', 'total_amount'];
        
        $columns_result = $conn->query("SHOW COLUMNS FROM property_acknowledgement_receipts");
        $existing_columns = [];
        
        if ($columns_result) {
            while ($column = $columns_result->fetch_assoc()) {
                $existing_columns[] = $column['Field'];
            }
            
            foreach ($required_fields as $field) {
                if (in_array($field, $existing_columns)) {
                    $response['existing_fields'][] = "PAR Table: " . $field;
                } else {
                    $response['missing_fields'][] = "PAR Table: " . $field;
                }
            }
        }
    }
    
    // Check PAR items table structure
    $par_items_table_check = $conn->query("SHOW TABLES LIKE 'par_items'");
    $response['par_items_table_exists'] = $par_items_table_check->num_rows > 0;
    
    if ($response['par_items_table_exists']) {
        // Check required fields
        $required_fields = ['par_item_id', 'par_id', 'quantity', 'unit', 'item_description', 'property_number', 'date_acquired', 'unit_price'];
        
        $columns_result = $conn->query("SHOW COLUMNS FROM par_items");
        $existing_columns = [];
        
        if ($columns_result) {
            while ($column = $columns_result->fetch_assoc()) {
                $existing_columns[] = $column['Field'];
            }
            
            foreach ($required_fields as $field) {
                if (in_array($field, $existing_columns)) {
                    $response['existing_fields'][] = "PAR Items Table: " . $field;
                } else {
                    $response['missing_fields'][] = "PAR Items Table: " . $field;
                }
            }
        }
    }
    
    // Add PHP version and error log information
    $response['php_info'] = [
        'version' => PHP_VERSION,
        'error_reporting' => error_reporting(),
        'display_errors' => ini_get('display_errors'),
        'error_log_path' => ini_get('error_log')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close database connection
if (isset($conn) && !$conn->connect_error) {
    $conn->close();
}
?> 