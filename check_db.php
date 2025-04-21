<?php
// Include database configuration
include 'config/db.php';

// Set headers for debugging
header('Content-Type: text/html');

try {
    // Get database connection
    $conn = getConnection();
    
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    // Check if we can connect to the database
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Database Connection Successful</h2>";
    echo "<p>Connected to database: " . $dbname . "</p>";
    
    // Check if purchase_orders table exists
    $result = $conn->query("SHOW TABLES LIKE 'purchase_orders'");
    $table_exists = $result->num_rows > 0;
    
    if (!$table_exists) {
        echo "<p>Creating purchase_orders table...</p>";
        
        // Create purchase_orders table
        $sql = "CREATE TABLE purchase_orders (
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
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>purchase_orders table created successfully!</p>";
        } else {
            echo "<p>Error creating purchase_orders table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>purchase_orders table already exists.</p>";
    }
    
    // Check if po_items table exists
    $result = $conn->query("SHOW TABLES LIKE 'po_items'");
    $table_exists = $result->num_rows > 0;
    
    if (!$table_exists) {
        echo "<p>Creating po_items table...</p>";
        
        // Create po_items table
        $sql = "CREATE TABLE po_items (
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
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>po_items table created successfully!</p>";
        } else {
            echo "<p>Error creating po_items table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>po_items table already exists.</p>";
    }
    
    // Display table structures
    echo "<h3>purchase_orders Table Structure:</h3>";
    $result = $conn->query("DESCRIBE purchase_orders");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
    
    echo "<h3>po_items Table Structure:</h3>";
    $result = $conn->query("DESCRIBE po_items");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 