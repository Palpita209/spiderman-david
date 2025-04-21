<?php
/**
 * Test script for PAR APIs
 * Tests database connection and API endpoints
 */

// Include database connection
require_once 'config/db.php';

// Set headers
header('Content-Type: text/html');

echo "<h1>PAR System API Test</h1>";

// Test database connection
echo "<h2>Database Connection</h2>";
if (isset($conn)) {
    echo "<p style='color:green'>Database connection OK</p>";
} else {
    echo "<p style='color:red'>Database connection ERROR: No connection variable found</p>";
    exit;
}

// Test PAR tables
echo "<h2>PAR Database Tables</h2>";
$tables = ["property_acknowledgement_receipts", "par_items", "users"];
foreach ($tables as $table) {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();
        if ($exists) {
            echo "<p style='color:green'>Table '$table' exists</p>";

            // Show table structure
            $structure = $conn->query("DESCRIBE $table");
            $columns = $structure->fetchAll(PDO::FETCH_ASSOC);
            if ($columns) {
                echo "<table border='1' cellpadding='3' style='border-collapse:collapse'>";
                echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
                foreach ($columns as $col) {
                    echo "<tr>";
                    echo "<td>{$col['Field']}</td>";
                    echo "<td>{$col['Type']}</td>";
                    echo "<td>{$col['Null']}</td>";
                    echo "<td>{$col['Key']}</td>";
                    echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<p style='color:red'>Table '$table' NOT found</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>Error checking table '$table': {$e->getMessage()}</p>";
    }
}

// Test PAR API endpoints
echo "<h2>API Endpoints Test</h2>";

// Test get_par.php
echo "<h3>get_par.php</h3>";
$get_par_url = "get_par.php";
$get_par_contents = @file_get_contents($get_par_url);
$get_par_data = json_decode($get_par_contents, true);

if ($get_par_data && isset($get_par_data['success'])) {
    echo "<p style='color:green'>get_par.php is returning valid JSON</p>";
    echo "<pre>" . htmlspecialchars(json_encode($get_par_data, JSON_PRETTY_PRINT)) . "</pre>";
} else {
    echo "<p style='color:red'>get_par.php is not returning valid JSON</p>";
    echo "<pre>" . htmlspecialchars($get_par_contents ?: "No response or error fetching file") . "</pre>";
}
?>