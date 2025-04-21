<?php
// Check and update PO related tables to ensure they have correct structure
require_once 'config/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response headers
header('Content-Type: application/json');

// Function to log messages for debugging
function logMessage($message) {
    error_log("[CHECK_PO_TABLE] " . $message);
    echo "[CHECK_PO_TABLE] " . $message . "\n";
}

try {
    // Make sure connection is available
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection not available");
    }
    
    // Check if purchase_orders table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'purchase_orders'");
    if ($tableExists->num_rows == 0) {
        // Create purchase_orders table with all required columns
        $createTable = "CREATE TABLE purchase_orders (
            po_id INT PRIMARY KEY AUTO_INCREMENT,
            po_no VARCHAR(50) NOT NULL UNIQUE,
            ref_no VARCHAR(50),
            supplier_name VARCHAR(100),
            supplier_address VARCHAR(255),
            supplier_email VARCHAR(100),
            supplier_tel VARCHAR(50),
            po_date DATE,
            mode_of_procurement VARCHAR(100),
            total_amount DECIMAL(12,2) DEFAULT 0.00,
            pr_no VARCHAR(50),
            pr_date DATE,
            place_of_delivery TEXT,
            delivery_date DATE,
            delivery_term VARCHAR(200) DEFAULT '60 days from receipt of Purchase Order',
            payment_term VARCHAR(200) DEFAULT 'Full Payment on Full Delivery',
            obligation_request_no VARCHAR(50),
            obligation_amount DECIMAL(12,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($createTable)) {
            throw new Exception("Failed to create purchase_orders table: " . $conn->error);
        }
        logMessage("Created purchase_orders table with all required fields");
    } else {
        // Check for required columns in purchase_orders
        $requiredColumns = [
            'ref_no' => "VARCHAR(50) AFTER po_no",
            'supplier_address' => "VARCHAR(255) AFTER supplier_name",
            'supplier_email' => "VARCHAR(100) AFTER supplier_address",
            'supplier_tel' => "VARCHAR(50) AFTER supplier_email",
            'place_of_delivery' => "TEXT AFTER pr_date",
            'delivery_date' => "DATE AFTER place_of_delivery",
            'delivery_term' => "VARCHAR(200) DEFAULT '60 days from receipt of Purchase Order' AFTER delivery_date",
            'payment_term' => "VARCHAR(200) DEFAULT 'Full Payment on Full Delivery' AFTER delivery_term",
            'obligation_request_no' => "VARCHAR(50) AFTER payment_term",
            'obligation_amount' => "DECIMAL(12,2) AFTER obligation_request_no"
        ];
        
        $columnsAdded = 0;
        foreach ($requiredColumns as $column => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE '$column'");
            if ($check->num_rows === 0) {
                $addQuery = "ALTER TABLE purchase_orders ADD COLUMN $column $definition";
                if (!$conn->query($addQuery)) {
                    logMessage("Failed to add column $column: " . $conn->error);
                } else {
                    logMessage("Added $column column to purchase_orders table");
                    $columnsAdded++;
                }
            }
        }
        
        if ($columnsAdded > 0) {
            logMessage("Added $columnsAdded missing columns to purchase_orders table");
        } else {
            logMessage("All required columns already exist in purchase_orders table");
        }
    }
    
    // Check po_items table
    $tableExists = $conn->query("SHOW TABLES LIKE 'po_items'");
    if ($tableExists->num_rows == 0) {
        // Create po_items table
        $createTable = "CREATE TABLE po_items (
            po_item_id INT PRIMARY KEY AUTO_INCREMENT,
            po_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            unit VARCHAR(50) DEFAULT 'pc',
            item_description TEXT,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
            unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total_cost DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
            FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id) ON DELETE CASCADE
        )";
        
        if (!$conn->query($createTable)) {
            throw new Exception("Failed to create po_items table: " . $conn->error);
        }
        logMessage("Created po_items table");
    } else {
        // Check for required columns in po_items
        $requiredColumns = [
            'item_name' => "VARCHAR(255) NOT NULL AFTER po_id",
            'item_description' => "TEXT AFTER unit",
            'total_cost' => "DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED"
        ];
        
        foreach ($requiredColumns as $column => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM po_items LIKE '$column'");
            if ($check->num_rows === 0) {
                $addQuery = "ALTER TABLE po_items ADD COLUMN $column $definition";
                if (!$conn->query($addQuery)) {
                    logMessage("Failed to add column $column to po_items: " . $conn->error);
                } else {
                    logMessage("Added $column column to po_items table");
                }
            }
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Database schema checked and updated if needed'
    ]);
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error checking database schema: ' . $e->getMessage()
    ]);
}

// Close connection if it exists
if (isset($conn)) {
    $conn->close();
}
?> 