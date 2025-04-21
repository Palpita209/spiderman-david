<?php
// Include database connection
require_once 'config/db.php';

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Debug log
    error_log('add_par.php - Request received: ' . file_get_contents('php://input'));
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid data received: ' . json_last_error_msg());
    }
    
    // Get connection
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Validate required fields
    $requiredFields = ['par_no', 'entity_name', 'date_acquired', 'received_by'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception('Please fill in required fields: ' . implode(', ', $missingFields));
    }
    
    // Validate and format the date
    if (isset($data['date_acquired']) && !empty($data['date_acquired'])) {
        // Ensure date is in Y-m-d format
        $originalDate = $data['date_acquired'];
        $dateAcquired = date('Y-m-d', strtotime($data['date_acquired']));
        
        // Check if date is valid
        if ($dateAcquired === '1970-01-01' && $originalDate !== '1970-01-01') {
            // If invalid, use current date
            $dateAcquired = date('Y-m-d');
            error_log("Invalid date format converted to today: $originalDate -> $dateAcquired");
        }
        $data['date_acquired'] = $dateAcquired;
    } else {
        // Default to current date if empty
        $data['date_acquired'] = date('Y-m-d');
        error_log("Empty date defaulted to today: " . $data['date_acquired']);
    }
    
    // Check if items array is empty
    if (empty($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
        throw new Exception('Please add at least one item');
    }
    
    // Validate each item has the required fields
    $invalidItems = [];
    foreach ($data['items'] as $index => $item) {
        if (!isset($item['description']) || empty(trim($item['description']))) {
            $invalidItems[] = $index + 1;
        }
    }

    if (!empty($invalidItems)) {
        throw new Exception('Please add description for items: ' . implode(', ', array_unique($invalidItems)));
    }
    
    // Check if PAR_NO already exists
    $checkStmt = $conn->prepare("SELECT par_id FROM property_acknowledgement_receipts WHERE par_no = ?");
    $checkStmt->bind_param("s", $data['par_no']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('PAR No. "' . $data['par_no'] . '" already exists. Please use a different PAR No.');
    }

    // First, check if user exists or create a new one
    $userId = null;
    
    if (is_numeric($data['received_by'])) {
        // If it's already a numeric ID, use it directly
        $userId = intval($data['received_by']);
        
        // Verify this user ID exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // If user ID doesn't exist, treat as a name
            $userId = null;
        }
    }
    
    // If userId is still null, look for user by name or create new 
    if ($userId === null) {
        // Check if this user exists by name
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE full_name = ?");
        $stmt->bind_param("s", $data['received_by']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $userId = $row['user_id'];
        } else {
            // Only create a new user if the name is not empty
            if (!empty(trim($data['received_by']))) {
                try {
                    // Check if users table has username field
                    $userColumnsQuery = "SHOW COLUMNS FROM users";
                    $userColumnsResult = $conn->query($userColumnsQuery);
                    $userColumns = [];
                    
                    while ($column = $userColumnsResult->fetch_assoc()) {
                        $userColumns[] = $column['Field'];
                    }
                    
                    $hasUsernameField = in_array('username', $userColumns);
                    
                    // Create new user - handle username field if it exists
                    if ($hasUsernameField) {
                        // Generate a unique username based on the full name and timestamp
                        $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $data['received_by']));
                        if (empty($baseUsername)) {
                            $baseUsername = 'user';
                        }
                        $uniqueUsername = $baseUsername . '_' . time();
                        
                        $stmt = $conn->prepare("INSERT INTO users (full_name, username) VALUES (?, ?)");
                        $stmt->bind_param("ss", $data['received_by'], $uniqueUsername);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO users (full_name) VALUES (?)");
                        $stmt->bind_param("s", $data['received_by']);
                    }
                    
                    $stmt->execute();
                    $userId = $conn->insert_id;
                    
                    if (!$userId) {
                        throw new Exception("Failed to create new user: " . $conn->error);
                    }
                } catch (Exception $userEx) {
                    error_log("Error creating user: " . $userEx->getMessage());
                    
                    // Fallback - use a placeholder user ID if available
                    $placeholderUserQuery = "SELECT user_id FROM users WHERE full_name = 'System' OR full_name = 'Admin' LIMIT 1";
                    $placeholderResult = $conn->query($placeholderUserQuery);
                    if ($placeholderResult && $placeholderResult->num_rows > 0) {
                        $placeholderUser = $placeholderResult->fetch_assoc();
                        $userId = $placeholderUser['user_id'];
                    } else {
                        throw new Exception("Could not create or find a valid user. Please specify a valid name.");
                    }
                }
            } else {
                // Handle empty user name - find a placeholder user
                $placeholderUserQuery = "SELECT user_id FROM users WHERE full_name = 'System' OR full_name = 'Admin' LIMIT 1";
                $placeholderResult = $conn->query($placeholderUserQuery);
                if ($placeholderResult && $placeholderResult->num_rows > 0) {
                    $placeholderUser = $placeholderResult->fetch_assoc();
                    $userId = $placeholderUser['user_id'];
                } else {
                    throw new Exception("Cannot create PAR with empty user and no fallback user found. Please specify a valid name.");
                }
            }
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Calculate total amount
    $totalAmount = 0;
    
    // Check if total amount is provided directly in the form data
    if (isset($data['total_amount']) && is_numeric($data['total_amount']) && floatval($data['total_amount']) > 0) {
        $totalAmount = floatval($data['total_amount']);
        error_log("Using provided total amount: " . $totalAmount);
    } else {
        // Calculate from items if not provided
        foreach ($data['items'] as $item) {
            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
            
            // Check for either unit_price or amount field
            $price = 0;
            if (isset($item['unit_price'])) {
                $price = floatval($item['unit_price']);
                error_log("Using unit_price for calculation: " . $price);
            } else if (isset($item['amount'])) {
                $price = floatval($item['amount']);
                error_log("Using amount for calculation: " . $price);
            }
            
            $lineTotal = $quantity * $price;
            $totalAmount += $lineTotal;
            error_log("Item total: $quantity * $price = $lineTotal");
        }
        error_log("Calculated total amount: " . $totalAmount);
    }
    
    // Define optional fields with default values
    $position = isset($data['position']) ? $data['position'] : '';
    $department = isset($data['department']) ? $data['department'] : '';
    $remarks = isset($data['remarks']) ? $data['remarks'] : '';
    
    // Check if position and department columns exist in the table
    $columnsExistQuery = "SHOW COLUMNS FROM property_acknowledgement_receipts";
    $columnsResult = $conn->query($columnsExistQuery);
    $existingColumns = [];
    
    while ($column = $columnsResult->fetch_assoc()) {
        $existingColumns[] = $column['Field'];
    }
    
    $hasPositionColumn = in_array('position', $existingColumns);
    $hasDepartmentColumn = in_array('department', $existingColumns);
    
    // Build dynamic INSERT query based on existing columns
    $fields = ["par_no", "entity_name", "date_acquired", "received_by"];
    $placeholders = ["?", "?", "?", "?"];
    $values = [$data['par_no'], $data['entity_name'], $data['date_acquired'], $userId];
    $types = "sssi";
    
    if ($hasPositionColumn) {
        $fields[] = "position";
        $placeholders[] = "?";
        $values[] = $position;
        $types .= "s";
    }
    
    if ($hasDepartmentColumn) {
        $fields[] = "department";
        $placeholders[] = "?";
        $values[] = $department;
        $types .= "s";
    }
    
    // Check if notes or remarks should be used for the field
    if (in_array('notes', $existingColumns)) {
        $fields[] = "notes";
        $placeholders[] = "?";
        $values[] = $remarks;
        $types .= "s";
    } else if (in_array('remarks', $existingColumns)) {
        $fields[] = "remarks";
        $placeholders[] = "?";
        $values[] = $remarks;
        $types .= "s";
    }
    
    $fields[] = "total_amount";
    $placeholders[] = "?";
    $values[] = $totalAmount;
    $types .= "d";
    
    // Prepare the SQL statement with dynamic fields
    $sql = "INSERT INTO property_acknowledgement_receipts (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        if (strpos($error, 'Duplicate entry') !== false && strpos($error, 'par_no') !== false) {
            throw new Exception('PAR No. "' . $data['par_no'] . '" already exists. Please use a different PAR No.');
        } else {
            throw new Exception('Failed to insert PAR: ' . $error);
        }
    }
    
    $parId = $conn->insert_id;
    
    // Check item fields structure
    $itemColumnsQuery = "SHOW COLUMNS FROM par_items";
    $itemColumnsResult = $conn->query($itemColumnsQuery);
    $itemColumns = [];
    
    while ($column = $itemColumnsResult->fetch_assoc()) {
        $itemColumns[] = $column['Field'];
    }
    
    // Determine correct field names
    $descriptionField = in_array('item_description', $itemColumns) ? 'item_description' : 'description';
    $priceField = in_array('unit_price', $itemColumns) ? 'unit_price' : 'amount';
    
    // Prepare the fields for the INSERT statement
    $itemFields = [
        "par_id",
        "quantity", 
        "unit", 
        $descriptionField, 
        "property_number", 
        "date_acquired", 
        $priceField
    ];
    
    // Insert PAR items
    $itemSql = "INSERT INTO par_items (" . implode(", ", $itemFields) . ") VALUES (?, ?, ?, ?, ?, ?, ?)";
    $itemStmt = $conn->prepare($itemSql);
    
    foreach ($data['items'] as $item) {
        // Handle different field names for item description
        $description = '';
        if (isset($item['description']) && !empty($item['description'])) {
            $description = $item['description'];
        } else if (isset($item['item_description']) && !empty($item['item_description'])) {
            $description = $item['item_description'];
        }
        
        // Only process items with a description
        if (empty($description)) {
            continue;
        }
        
        // Get item values with defaults for missing properties
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $unit = isset($item['unit']) ? $item['unit'] : '';
        $propertyNumber = isset($item['property_number']) ? $item['property_number'] : '';
        
        // Validate and format the item date
        if (!empty($item['date_acquired'])) {
            $originalItemDate = $item['date_acquired'];
            $itemDate = date('Y-m-d', strtotime($item['date_acquired']));
            
            // Check if date is valid
            if ($itemDate === '1970-01-01' && $originalItemDate !== '1970-01-01') {
                // If invalid date, use the PAR date
                $itemDate = $data['date_acquired'];
                error_log("Invalid item date format converted to PAR date: $originalItemDate -> $itemDate");
            }
        } else {
            $itemDate = $data['date_acquired'];
            error_log("Empty item date defaulted to PAR date: $itemDate");
        }
        
        // Handle different field names for item price
        $price = 0;
        if (isset($item['unit_price'])) {
            $price = floatval($item['unit_price']);
            error_log("Using unit_price for item: " . $price);
        } else if (isset($item['amount'])) {
            $price = floatval($item['amount']);
            error_log("Using amount for item: " . $price);
        }
        
        $itemStmt->bind_param(
            "iissssd",
            $parId,
            $quantity,
            $unit,
            $description,
            $propertyNumber,
            $itemDate,
            $price
        );
        
        if (!$itemStmt->execute()) {
            throw new Exception('Failed to insert PAR item: ' . $itemStmt->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log success
    error_log("PAR saved successfully - PAR ID: $parId");
    
    echo json_encode([
        'success' => true,
        'message' => 'PAR saved successfully',
        'par_id' => $parId
    ]);
    
} catch (Exception $e) {
    error_log('Error in add_par.php: ' . $e->getMessage());
    
    if (isset($conn) && $conn->connect_error === false) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackEx) {
            error_log('Error during rollback: ' . $rollbackEx->getMessage());
        }
    }
    
    http_response_code(500); // Set proper HTTP status code
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn) && $conn->connect_error === false) {
    $conn->close();
}
?>