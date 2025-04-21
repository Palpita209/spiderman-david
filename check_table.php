<?php
// Include database connection
require_once 'config/db.php';

// Set headers for debugging
header('Content-Type: text/html');

try {
    // Get connection
    $conn = getConnection();
    if (!$conn) {
        die("Database connection failed");
    }
    
    echo "Database connection successful\n";
    
    // Check if the PAR tables exist
    $tables = array(
        'property_acknowledgement_receipts',
        'par_items',
        'users' 
    );
    
    echo "\nChecking tables:\n";
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "$table: EXISTS\n";
            
            // Get table structure
            $structResult = $conn->query("DESCRIBE $table");
            echo "  Columns:\n";
            while ($row = $structResult->fetch_assoc()) {
                echo "    - {$row['Field']} ({$row['Type']})" . 
                     ($row['Key'] == 'PRI' ? " PRIMARY KEY" : "") . 
                     ($row['Null'] == 'NO' ? " NOT NULL" : "") . "\n";
            }
        } else {
            echo "$table: MISSING\n";
        }
    }
    
    echo "\nDatabase name: " . $dbname . "\n";
    
    // Create purchase_orders table
    $purchase_orders_sql = "
    CREATE TABLE IF NOT EXISTS purchase_orders (
        po_id INT PRIMARY KEY AUTO_INCREMENT,
        po_no VARCHAR(50) NOT NULL UNIQUE,
        supplier_name VARCHAR(100),
        po_date DATE,
        mode_of_procurement VARCHAR(100),
        supplier_email VARCHAR(100),
        supplier_address TEXT,
        supplier_tel VARCHAR(50),
        total_amount DECIMAL(12,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    // Create po_items table
    $po_items_sql = "
    CREATE TABLE IF NOT EXISTS po_items (
        po_item_id INT PRIMARY KEY AUTO_INCREMENT,
        po_id INT NOT NULL,
        item_name VARCHAR(100) NOT NULL,
        unit VARCHAR(50),
        item_description TEXT,
        quantity INT NOT NULL DEFAULT 1,
        unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_cost DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
        FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id) ON DELETE CASCADE
    )";

    // Execute the SQL statements
    if ($conn->query($purchase_orders_sql) === TRUE) {
        echo "<p>purchase_orders table created successfully</p>";
    } else {
        echo "<p>Error creating purchase_orders table: " . $conn->error . "</p>";
    }

    if ($conn->query($po_items_sql) === TRUE) {
        echo "<p>po_items table created successfully</p>";
    } else {
        echo "<p>Error creating po_items table: " . $conn->error . "</p>";
    }

    // Check table structures
    echo "<h3>purchase_orders Table Structure:</h3>";
    $result = $conn->query("DESCRIBE purchase_orders");
    if ($result) {
        echo "<pre>";
        while ($row = $result->fetch_assoc()) {
            print_r($row);
        }
        echo "</pre>";
    } else {
        echo "<p>Error getting table structure: " . $conn->error . "</p>";
    }

    echo "<h3>po_items Table Structure:</h3>";
    $result = $conn->query("DESCRIBE po_items");
    if ($result) {
        echo "<pre>";
        while ($row = $result->fetch_assoc()) {
            print_r($row);
        }
        echo "</pre>";
    } else {
        echo "<p>Error getting table structure: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    if (isset($conn) && !$conn->connect_error) {
        $conn->close();
    }
}
?> 