<?php
// Include database configuration
include 'config/db.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Check if the proper data was sent
if (!isset($_POST['po_id']) || empty($_POST['po_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'PO ID is required'
    ]);
    exit;
}

$poId = intval($_POST['po_id']);
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$uploadDate = date('Y-m-d H:i:s');

// Check if a file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'No file was uploaded or an error occurred'
    ]);
    exit;
}

$file = $_FILES['file'];
$fileName = $file['name'];
$fileType = $file['type'];
$fileSize = $file['size'];
$fileTmpPath = $file['tmp_name'];

// Create uploads directory if it doesn't exist
$uploadsDir = 'uploads/po_documents/';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Generate a unique filename
$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
$uniqueFileName = 'PO_' . $poId . '_' . date('Ymd_His') . '.' . $fileExtension;
$uploadPath = $uploadsDir . $uniqueFileName;

// Move the uploaded file
if (!move_uploaded_file($fileTmpPath, $uploadPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload file'
    ]);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Update PO record to mark as uploaded
    $stmt = $conn->prepare("UPDATE purchase_orders SET upload_status = 'Uploaded', last_modified = ? WHERE po_id = ?");
    $stmt->bind_param("si", $uploadDate, $poId);
    $stmt->execute();
    
    // Check if any rows were affected
    if ($stmt->affected_rows <= 0) {
        // PO not found, roll back and return error
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Purchase order not found'
        ]);
        exit;
    }
    
    // Insert into uploads table
    $stmt = $conn->prepare("INSERT INTO po_uploads (po_id, file_path, file_name, file_type, file_size, upload_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $poId, $uploadPath, $fileName, $fileType, $fileSize, $uploadDate, $notes);
    $stmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Purchase order uploaded successfully',
        'data' => [
            'po_id' => $poId,
            'file_path' => $uploadPath,
            'file_name' => $fileName,
            'upload_date' => $uploadDate
        ]
    ]);
} catch (Exception $e) {
    // Roll back the transaction on error
    $conn->rollback();
    
    // Log the error
    error_log('Error uploading PO: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during upload: ' . $e->getMessage()
    ]);
}

// Close database connection
$conn->close(); 