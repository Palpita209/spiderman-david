<?php
require_once 'Learning/config/db.php';

// Set the proper content type for plain text output
header('Content-Type: text/plain');

try {
    echo "Connected to database: $dbname\n\n";
    
    // List all tables
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "TABLES IN DATABASE:\n";
        echo "===================\n";
        $tableCount = 0;
        while ($row = $result->fetch_array()) {
            $tableName = $row[0];
            echo "- $tableName\n";
            $tableCount++;
        }
        echo "\nTotal tables: $tableCount\n";
        echo "===================\n\n";
    }
    
    // Focus on the purchase_orders and po_items tables
    $tables = ['purchase_orders', 'po_items'];
    
    foreach ($tables as $table) {
        echo "TABLE STRUCTURE: $table\n";
        echo "===================\n";
        $result = $conn->query("DESCRIBE $table");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                echo "Field: {$row['Field']}\n";
                echo "  Type: {$row['Type']}\n";
                echo "  Null: {$row['Null']}\n";
                echo "  Key: {$row['Key']}\n";
                echo "  Default: {$row['Default']}\n";
                echo "  Extra: {$row['Extra']}\n\n";
            }
        } else {
            echo "Error describing table $table: " . $conn->error . "\n";
        }
        
        echo "===================\n\n";
    }

    // Show sample data from purchase_orders and po_items
    echo "SAMPLE DATA FROM TABLES:\n";
    echo "===================\n";
    
    foreach ($tables as $table) {
        echo "SAMPLE DATA FROM $table (up to 5 rows):\n";
        $result = $conn->query("SELECT * FROM $table LIMIT 5");
        
        if ($result && $result->num_rows > 0) {
            echo "Found " . $result->num_rows . " rows\n";
            
            // Get column names
            $columns = [];
            $fieldInfo = $result->fetch_fields();
            
            foreach ($fieldInfo as $field) {
                $columns[] = $field->name;
            }
            
            // Output column names
            echo implode("\t", $columns) . "\n";
            echo str_repeat("-", 80) . "\n";
            
            // Output data
            while ($row = $result->fetch_assoc()) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = isset($row[$column]) ? substr($row[$column], 0, 20) : 'NULL';
                }
                echo implode("\t", $values) . "\n";
            }
        } else {
            echo "No data found in $table or error: " . $conn->error . "\n";
        }
        echo "\n===================\n\n";
    }
    
    // Close connection
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 