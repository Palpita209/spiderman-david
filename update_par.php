<?php
header('Content-Type: application/json');
require_once 'config/db.php';

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

// Debug received data
error_log("UPDATE PAR Data received: " . json_encode($data));

// Check if data is valid
if (!$data || !isset($data['par_id']) || !isset($data['items']) || empty($data['items'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Update PAR header
    $stmt = $conn->prepare("
        UPDATE property_acknowledgement_receipts 
        SET par_no = ?, 
            entity_name = ?, 
            date_acquired = ?,
            received_by = ?,
            position = ?,
            department = ?,
            notes = ?
        WHERE par_id = ?
    ");
    
    // Set default values if not provided
    $par_no = isset($data['par_no']) ? $data['par_no'] : '';
    $entity_name = isset($data['entity_name']) ? $data['entity_name'] : '';
    $date_acquired = !empty($data['date_acquired']) ? $data['date_acquired'] : date('Y-m-d');
    $position = isset($data['position']) ? $data['position'] : null;
    $department = isset($data['department']) ? $data['department'] : null;
    $notes = isset($data['remarks']) ? $data['remarks'] : null;
    
    // For received_by, accept either name string or ID
    $received_by = null;
    error_log("Processing update received_by value: " . print_r($data['received_by'] ?? 'NULL', true));
    
    if (isset($data['received_by'])) {
        if (is_numeric($data['received_by'])) {
            $received_by = intval($data['received_by']);
            error_log("Update: Received_by is numeric: $received_by");
            
            // Verify this user_id exists
            $checkUserStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $checkUserStmt->bind_param("i", $received_by);
            $checkUserStmt->execute();
            $checkResult = $checkUserStmt->get_result();
            if ($checkResult->num_rows === 0) {
                error_log("WARNING: User ID $received_by does not exist in database");
            } else {
                error_log("Confirmed user ID $received_by exists in database");
            }
            $checkUserStmt->close();
        } else if (!empty($data['received_by'])) {
            // If not numeric, check if user exists
            error_log("Update: Received_by is a name: " . $data['received_by']);
            $userStmt = $conn->prepare("SELECT user_id FROM users WHERE full_name = ? LIMIT 1");
            $userStmt->bind_param("s", $data['received_by']);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult->num_rows > 0) {
                $user = $userResult->fetch_assoc();
                $received_by = $user['user_id'];
                error_log("Update: Found existing user with ID: $received_by");
            } else {
                // Create a new user record if name doesn't exist
                error_log("Update: User not found, creating new user with name: " . $data['received_by']);
                $username = strtolower(str_replace(' ', '.', trim($data['received_by'])));
                $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
                
                // Check if users table has the expected columns
                $columnsResult = $conn->query("SHOW COLUMNS FROM users");
                $columns = [];
                while ($column = $columnsResult->fetch_assoc()) {
                    $columns[] = $column['Field'];
                }
                error_log("Users table columns: " . implode(", ", $columns));
                
                $newUserStmt = $conn->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, 'user')");
                if (!$newUserStmt) {
                    error_log("Error preparing new user statement: " . $conn->error);
                } else {
                    $newUserStmt->bind_param("sss", $data['received_by'], $username, $defaultPassword);
                    
                    if ($newUserStmt->execute()) {
                        $received_by = $conn->insert_id;
                        error_log("Update: Created new user with ID: $received_by");
                    } else {
                        error_log("Update: Failed to create new user: " . $newUserStmt->error);
                    }
                    
                    $newUserStmt->close();
                }
            }
            
            $userStmt->close();
        }
    }
    
    error_log("Update: Final received_by value for database insert: " . ($received_by !== null ? $received_by : "NULL"));
    
    // If received_by is still null at this point and is required, use a default user
    if ($received_by === null) {
        error_log("Update: No valid received_by found. Checking if default user (ID 1) exists...");
        // Check if user ID 1 exists
        $checkDefaultUserStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = 1 LIMIT 1");
        $checkDefaultUserStmt->execute();
        $defaultUserResult = $checkDefaultUserStmt->get_result();
        
        if ($defaultUserResult->num_rows > 0) {
            error_log("Update: Using default user ID 1");
            $received_by = 1; // Default to first user if available
        } else {
            // Create a default admin user if none exists
            error_log("Update: Default user not found, creating a default admin user");
            $createDefaultUser = $conn->prepare("INSERT INTO users (user_id, full_name, username, password, role) VALUES (1, 'System Admin', 'admin', ?, 'admin')");
            $defaultAdminPass = password_hash('admin123', PASSWORD_DEFAULT);
            $createDefaultUser->bind_param("s", $defaultAdminPass);
            
            if ($createDefaultUser->execute()) {
                error_log("Update: Created default admin user with ID 1");
                $received_by = 1;
            } else {
                error_log("Update: Failed to create default user: " . $createDefaultUser->error);
                // Last resort - try to find any user
                $findAnyUserStmt = $conn->prepare("SELECT user_id FROM users LIMIT 1");
                $findAnyUserStmt->execute();
                $anyUserResult = $findAnyUserStmt->get_result();
                if ($anyUserResult->num_rows > 0) {
                    $anyUser = $anyUserResult->fetch_assoc();
                    $received_by = $anyUser['user_id'];
                    error_log("Update: Using any available user ID: " . $received_by);
                } else {
                    error_log("Update: CRITICAL: No users found in the database at all!");
                }
                $findAnyUserStmt->close();
            }
            $createDefaultUser->close();
        }
        $checkDefaultUserStmt->close();
    }
    
    // Final check - if still null, we can't proceed
    if ($received_by === null) {
        throw new Exception("Could not find or create a valid user for the 'received_by' field. Check your users table.");
    }
    
    // Bind parameters
    $stmt->bind_param(
        "sssisssi", 
        $par_no, 
        $entity_name, 
        $date_acquired,
        $received_by,
        $position, 
        $department,
        $notes,
        $data['par_id']
    );
    
    // Execute update
    if (!$stmt->execute()) {
        throw new Exception("Error updating PAR: " . $conn->error);
    }
    
    // Delete existing PAR items
    $deleteStmt = $conn->prepare("DELETE FROM par_items WHERE par_id = ?");
    $deleteStmt->bind_param("i", $data['par_id']);
    
    if (!$deleteStmt->execute()) {
        throw new Exception("Error deleting existing PAR items: " . $conn->error);
    }
    
    // Insert updated PAR items
    $itemStmt = $conn->prepare("
        INSERT INTO par_items 
        (par_id, property_number, item_description, quantity, unit, unit_price, date_acquired, remarks) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($data['items'] as $item) {
        // Set default values for items
        $itemDate = !empty($item['date_acquired']) ? $item['date_acquired'] : $date_acquired;
        $itemQty = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $itemRemarks = isset($item['remarks']) ? $item['remarks'] : null;
        $propertyNumber = isset($item['property_number']) ? $item['property_number'] : '';
        $unit = isset($item['unit']) ? $item['unit'] : '';
        
        // Get description from either 'description' or 'item_description' field
        $description = isset($item['description']) ? $item['description'] : 
                      (isset($item['item_description']) ? $item['item_description'] : '');
        
        // Skip items with empty description
        if (empty($description)) {
            continue;
        }
        
        // Get price from either 'unit_price' or 'amount' field
        $price = isset($item['unit_price']) ? floatval($item['unit_price']) : 
                (isset($item['amount']) ? floatval($item['amount']) : 0);
        
        // Bind parameters
        $itemStmt->bind_param(
            "issssdss", 
            $data['par_id'],
            $propertyNumber,
            $description,
            $itemQty,
            $unit,
            $price,
            $itemDate,
            $itemRemarks
        );
        
        // Execute insert
        if (!$itemStmt->execute()) {
            throw new Exception("Error inserting PAR item: " . $conn->error);
        }
    }
    
    // Update total amount using stored procedure if it exists
    try {
        $updateTotalStmt = $conn->prepare("CALL update_par_total(?)");
        $updateTotalStmt->bind_param("i", $data['par_id']);
        $updateTotalStmt->execute();
        $updateTotalStmt->close();
    } catch (Exception $e) {
        // If procedure doesn't exist, manually update total
        $manualUpdateStmt = $conn->prepare("
            UPDATE property_acknowledgement_receipts 
            SET total_amount = (
                SELECT COALESCE(SUM(quantity * unit_price), 0) 
                FROM par_items 
                WHERE par_id = ?
            )
            WHERE par_id = ?
        ");
        $manualUpdateStmt->bind_param("ii", $data['par_id'], $data['par_id']);
        $manualUpdateStmt->execute();
        $manualUpdateStmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'PAR updated successfully',
        'par_id' => $data['par_id']
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
