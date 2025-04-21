<?php
include 'config/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set proper headers for HTML output
header('Content-Type: text/html; charset=UTF-8');

// URL to test
$api_url = 'Learning/get_po.php';

echo "<html><head><title>PO API Test</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #333; }
    .success { color: green; }
    .error { color: red; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .container { max-width: 1200px; margin: 0 auto; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>Purchase Order API Test</h1>";
echo "<p>Testing API endpoint: <code>$api_url</code></p>";

try {
    // Make the API request with error handling
    echo "<h2>API Response</h2>";
    $response = @file_get_contents($api_url);
    
    if ($response === false) {
        // Get the error message
        $error = error_get_last();
        throw new Exception("Failed to get response: " . $error['message']);
    }
    
    // Try to decode the JSON response
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p class='error'>Error parsing JSON response: " . json_last_error_msg() . "</p>";
        echo "<h3>Raw Response:</h3>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        // Display the response
        echo "<h3>Response Status</h3>";
        
        if (isset($data['success'])) {
            $success = $data['success'] ? 'Success' : 'Failed';
            $class = $data['success'] ? 'success' : 'error';
            echo "<p class='$class'><strong>Status:</strong> $success</p>";
        } else {
            echo "<p class='error'><strong>Status:</strong> Unknown (success flag not found)</p>";
        }
        
        if (isset($data['message'])) {
            echo "<p><strong>Message:</strong> " . htmlspecialchars($data['message']) . "</p>";
        }
        
        // Display purchase orders data if available
        if (isset($data['data']) && is_array($data['data'])) {
            $pos = $data['data'];
            echo "<h3>Found " . count($pos) . " Purchase Orders</h3>";
            
            if (count($pos) > 0) {
                echo "<table>";
                echo "<tr>";
                // Get headers from the first PO
                $headers = array_keys($pos[0]);
                foreach ($headers as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr>";
                
                // Display each PO
                foreach ($pos as $po) {
                    echo "<tr>";
                    foreach ($po as $key => $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'N/A') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No purchase orders found in the response data array.</p>";
            }
        } elseif (is_array($data) && !isset($data['data']) && count($data) > 0 && isset($data[0])) {
            // Handle case where data is direct array of POs without the data wrapper
            $pos = $data;
            echo "<h3>Found " . count($pos) . " Purchase Orders (direct array)</h3>";
            
            if (count($pos) > 0) {
                echo "<table>";
                echo "<tr>";
                // Get headers from the first PO
                $headers = array_keys($pos[0]);
                foreach ($headers as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr>";
                
                // Display each PO
                foreach ($pos as $po) {
                    echo "<tr>";
                    foreach ($po as $key => $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'N/A') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No purchase orders found in the direct response array.</p>";
            }
        } else {
            echo "<p class='error'>No purchase orders data found in the response.</p>";
        }
        
        echo "<h3>Full Response (JSON)</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div></body></html>";
?> 