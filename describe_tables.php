<?php
require_once 'config/db.php';

// Set the proper content type
header('Content-Type: text/html; charset=utf-8');

try {
    // Check connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection not available");
    }
    
    echo "<h1>ICTD Inventory System Database Schema</h1>";
    
    // Get all tables
    $tables_result = $conn->query("SHOW TABLES");
    if (!$tables_result) {
        throw new Exception("Failed to retrieve tables: " . $conn->error);
    }
    
    $tables = [];
    while ($table_row = $tables_result->fetch_row()) {
        $tables[] = $table_row[0];
    }
    
    echo "<h2>Database: $dbname</h2>";
    echo "<p>Total Tables: " . count($tables) . "</p>";
    
    // Display each table structure
    foreach ($tables as $table) {
        echo "<div style='margin-bottom: 30px; border: 1px solid #ccc; padding: 10px;'>";
        echo "<h3>Table: $table</h3>";
        
        // Get columns
        $columns_result = $conn->query("DESCRIBE `$table`");
        if (!$columns_result) {
            echo "<p style='color: red;'>Error retrieving structure: " . $conn->error . "</p>";
            continue;
        }
        
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($column = $columns_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . (is_null($column['Default']) ? 'NULL' : htmlspecialchars($column['Default'])) . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Get foreign keys
        $fk_result = $conn->query("
            SELECT 
                COLUMN_NAME, 
                REFERENCED_TABLE_NAME, 
                REFERENCED_COLUMN_NAME 
            FROM 
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE 
                TABLE_SCHEMA = '$dbname' AND 
                TABLE_NAME = '$table' AND 
                REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        if ($fk_result && $fk_result->num_rows > 0) {
            echo "<h4>Foreign Keys:</h4>";
            echo "<ul>";
            
            while ($fk = $fk_result->fetch_assoc()) {
                echo "<li><strong>" . htmlspecialchars($fk['COLUMN_NAME']) . "</strong> references <strong>" . 
                     htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "." . 
                     htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</strong></li>";
            }
            
            echo "</ul>";
        }
        
        // Get row count
        $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
        $count = $count_result->fetch_assoc()['count'];
        echo "<p>Total Records: $count</p>";
        
        echo "</div>";
    }
    
    // Check for triggers
    $trigger_result = $conn->query("SHOW TRIGGERS FROM `$dbname`");
    if ($trigger_result && $trigger_result->num_rows > 0) {
        echo "<h2>Database Triggers</h2>";
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>Trigger</th><th>Event</th><th>Table</th><th>Statement</th><th>Timing</th></tr>";
        
        while ($trigger = $trigger_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($trigger['Trigger']) . "</td>";
            echo "<td>" . htmlspecialchars($trigger['Event']) . "</td>";
            echo "<td>" . htmlspecialchars($trigger['Table']) . "</td>";
            echo "<td>" . htmlspecialchars($trigger['Statement']) . "</td>";
            echo "<td>" . htmlspecialchars($trigger['Timing']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "<h2>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

// Close connection
if (isset($conn) && !$conn->connect_error) {
    $conn->close();
}
?> 