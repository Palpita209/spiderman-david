<?php
include 'config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');


if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Item ID is required']);
    exit;
}

$item_id = $_GET['id'];

if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

$query = "SELECT * FROM inventory_items WHERE item_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $item = $result->fetch_assoc();
    echo json_encode([
        'success' => true, 
        'item_id' => $item['item_id'],
        'item_name' => $item['item_name'],
        'brand_model' => $item['brand_model'],
        'serial_number' => $item['serial_number'],
        'purchase_date' => $item['date_acquired'],
        'warranty_expiration' => $item['warranty_expiry'],
        'assigned_to' => $item['assigned_to'],
        'location' => $item['location'],
        'condition' => $item['condition_status'],
        'notes' => $item['notes']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
}

$stmt->close();
$conn->close();
?>