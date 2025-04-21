<?php
// Include database configuration
include 'config/db.php';

// Get PAR ID from query string
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get the print parameter
$autoPrint = isset($_GET['print']) && $_GET['print'] === 'true';

if ($id <= 0) {
    echo "Invalid PAR ID";
    exit;
}

// Function to convert numbers to words
function numberToWords($num)
{
    $ones = array(
        0 => "ZERO",
        1 => "ONE",
        2 => "TWO",
        3 => "THREE",
        4 => "FOUR",
        5 => "FIVE",
        6 => "SIX",
        7 => "SEVEN",
        8 => "EIGHT",
        9 => "NINE",
        10 => "TEN",
        11 => "ELEVEN",
        12 => "TWELVE",
        13 => "THIRTEEN",
        14 => "FOURTEEN",
        15 => "FIFTEEN",
        16 => "SIXTEEN",
        17 => "SEVENTEEN",
        18 => "EIGHTEEN",
        19 => "NINETEEN"
    );
    
    $tens = array(
        1 => "TEN",
        2 => "TWENTY",
        3 => "THIRTY",
        4 => "FORTY",
        5 => "FIFTY",
        6 => "SIXTY",
        7 => "SEVENTY",
        8 => "EIGHTY",
        9 => "NINETY"
    );
    
    $hundreds = array(
        "HUNDRED",
        "THOUSAND",
        "MILLION",
        "BILLION",
        "TRILLION",
        "QUADRILLION"
    );

    if ($num == 0) {
        return $ones[0];
    }

    $num = number_format($num, 2, '.', ',');
    $num_arr = explode(".", $num);
    $whole = $num_arr[0];
    $fraction = $num_arr[1];

    $whole_arr = array_reverse(explode(",", $whole));
    $result = "";

    foreach ($whole_arr as $key => $value) {
        $value = (int)$value;
        if ($value) {
            $key_name = $key > 0 ? $hundreds[$key] . " " : "";
            if ($value < 20) {
                $result = $ones[$value] . " " . $key_name . $result;
            } elseif ($value < 100) {
                $result = $tens[floor($value/10)] . " " . $ones[$value%10] . " " . $key_name . $result;
            } else {
                $result = $ones[floor($value/100)] . " HUNDRED " . 
                           $tens[floor(($value%100)/10)] . " " . 
                           $ones[($value%100)%10] . " " . $key_name . $result;
            }
        }
    }

    if ($fraction > 0) {
        $result .= " AND {$fraction}/100";
    }

    return rtrim($result);
}

try {
    // Get database connection
    $conn = getConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Check if remarks column exists
    $columnsExistQuery = "SHOW COLUMNS FROM property_acknowledgement_receipts";
    $columnsResult = $conn->query($columnsExistQuery);
    $existingColumns = [];
    
    while ($column = $columnsResult->fetch_assoc()) {
        $existingColumns[] = $column['Field'];
    }
    
    $hasRemarksColumn = in_array('remarks', $existingColumns);
    $hasNotesColumn = in_array('notes', $existingColumns);
    
    // Build the query dynamically based on available columns
    $selectFields = "par.par_id, par.par_no, 
                    par.entity_name, 
                    CASE 
                        WHEN par.date_acquired IS NULL OR par.date_acquired = '0000-00-00' THEN CURDATE()
                        ELSE DATE_FORMAT(par.date_acquired, '%Y-%m-%d')
                    END as date_acquired, 
                    u.full_name as received_by_name, 
                    par.position, 
                    par.department, 
                    par.total_amount";
    
    // Add remarks or notes column if it exists
    if ($hasRemarksColumn) {
        $selectFields .= ", par.remarks";
    } else if ($hasNotesColumn) {
        $selectFields .= ", par.notes as remarks";
    } else {
        $selectFields .= ", '' as remarks";
    }
    
    $selectFields .= ", 'Province of Negros Occidental' as fund";
    
    // Get PAR details
    $stmt = $conn->prepare("SELECT $selectFields
                            FROM property_acknowledgement_receipts par 
                            LEFT JOIN users u ON par.received_by = u.user_id 
                            WHERE par.par_id = ?");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $par = $result->fetch_assoc();

    if (!$par) {
        echo "<div class='alert alert-danger'>Property Acknowledgement Receipt not found</div>";
        exit;
    }

    // Check item fields structure
    $itemColumnsQuery = "SHOW COLUMNS FROM par_items";
    $itemColumnsResult = $conn->query($itemColumnsQuery);
    $itemColumns = [];
    
    while ($column = $itemColumnsResult->fetch_assoc()) {
        $itemColumns[] = $column['Field'];
    }
    
    // Determine correct field names based on table structure
    $descriptionField = in_array('item_description', $itemColumns) ? 'item_description' : (in_array('description', $itemColumns) ? 'description' : 'item_description');
    $priceField = in_array('unit_price', $itemColumns) ? 'unit_price' : 'amount';
    
    // Get PAR items with dynamic field names
    $itemsStmt = $conn->prepare("SELECT quantity, 
                                unit, 
                                $descriptionField as description, 
                                property_number, 
                                CASE 
                                    WHEN date_acquired IS NULL OR date_acquired = '0000-00-00' THEN CURDATE()
                                    ELSE DATE_FORMAT(date_acquired, '%Y-%m-%d')
                                END as date_acquired, 
                                $priceField as amount 
                                FROM par_items 
                                WHERE par_id = ?");
    
    if (!$itemsStmt) {
        throw new Exception("Failed to prepare items statement: " . $conn->error . ". SQL error: " . $conn->error);
    }
    
    $itemsStmt->bind_param("i", $id);
    if (!$itemsStmt->execute()) {
        throw new Exception("Failed to execute items query: " . $itemsStmt->error);
    }
    
    $itemsResult = $itemsStmt->get_result();
    $items = [];
    $totalAmount = 0;
    while ($item = $itemsResult->fetch_object()) {
        $items[] = $item;
        $totalAmount += floatval($item->quantity) * floatval($item->amount);
    }

    // If total_amount is zero or null, update it with the calculated total
    if ((empty($par['total_amount']) || floatval($par['total_amount']) === 0) && $totalAmount > 0) {
        $updateStmt = $conn->prepare("UPDATE property_acknowledgement_receipts SET total_amount = ? WHERE par_id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param("di", $totalAmount, $id);
            $updateStmt->execute();
            $par['total_amount'] = $totalAmount;
        }
    }

    // Format date
    $dateFormatted = $par['date_acquired'] ?? date('m-d-Y');
    
} catch (Exception $e) {
    error_log("Error in viewPAR.php: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    exit;
}

// Auto print if requested
$printScript = '';
if ($autoPrint) {
    $printScript = '<script>window.onload = function() { window.print(); }</script>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAR<?php echo htmlspecialchars($par['par_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="image.jpg">
    <?php echo $printScript; ?>
    <style>
       body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            background-color: #f5f5f5;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        .appendix {
            position: absolute;
            top: 0;
            right: 0;
            font-size: 12px;
            color: #000;
        }
        .title {
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
            margin: 10px 0;
        }
        .par-details {
            margin-bottom: 20px;
        }
        .par-number {
            font-weight: bold;
            font-size: 13px;
        }
        .table-container {
            width: 100%;
            margin-bottom: 15px;
            overflow: visible;
        }
        .page {
            width: 8.5in;
            margin: 0 auto;
            background: #fff;
            padding: 0.5in;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            table-layout: fixed;
            border: 1px solid #000;
        }

        .table-items th, .table-items td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 12px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .table-items th {
            background-color: #fff;
            font-weight: bold;
            text-align: center;
        }

        .table-items td.quantity,
        .table-items td.unit,
        .table-items td.property-number,
        .table-items td.amount-cell {
            text-align: center;
            vertical-align: middle;
        }

        .table-items td.description-cell {
            text-align: left;
            padding-left: 8px;
            vertical-align: top;
        }

        /* Column widths */
        .table-items th:nth-child(1), .table-items td:nth-child(1) {
            width: 8%;
        }
        .table-items th:nth-child(2), .table-items td:nth-child(2) {
            width: 8%;
        }
        .table-items th:nth-child(3), .table-items td:nth-child(3) {
            width: 54%;
        }
        .table-items th:nth-child(4), .table-items td:nth-child(4) {
            width: 15%;
        }
        .table-items th:nth-child(5), .table-items td:nth-child(5) {
            width: 15%;
        }
        .description-column {
            overflow: visible;
            text-align: left;
            vertical-align: top;
        }
        .description-cell {
            font-size: 12px;
            line-height: 1.3;
            padding-left: 5px;
            word-break: normal;
            min-height: 20px;
            display: block;
            width: 100%;
        }
        .property-number-box {
            border: 1px solid #000;
            padding: 10px;
            font-size: 10px;
            min-height: 120px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .property-number-label {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
        }
        .par-footer {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signature-block {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            font-weight: bold;
            width: 200px;
            margin-left: auto;
            margin-right: auto;
        }
        .memory-specs {
            font-size: 10px;
            margin-top: 5px;
            margin-bottom: 15px;
        }
        .serial-numbers {
            font-size: 10px;
            word-break: break-all;
            line-height: 1.2;
        }
        .noted-by {
            font-size: 11px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 5px;
        }
        .admin-title {
            font-size: 10px;
            font-style: italic;
        }
        .supplier-info {
            font-size: 10px;
            margin-top: 10px;
        }
        .position-office {
            font-size: 10px;
            font-weight: normal;
            border-top: 1px solid #000;
            width: 150px;
            margin: 0 auto;
            padding-top: 3px;
        }
        .instructions-box {
            border: 1px solid #000;
            padding: 8px;
            font-size: 11px;
            margin-top: 20px;
            margin-bottom: 20px;
            float: right;
            width: 350px;
        }
        .quantity {
            text-align: center;
            vertical-align: top;
            padding: 6px;
            white-space: pre-line;
        }
        .unit {
            text-align: center;
            vertical-align: middle;
            padding: 6px;
        }
        .date-cell {
            text-align: center;
            white-space: nowrap;
        }
        .property-number {
            font-size: 12px;
            vertical-align: middle;
            text-align: center;
            padding: 6px;
        }
        .signature-name {
            font-weight: bold;
            margin-bottom: 0;
            text-transform: uppercase;
            font-size: 11px;
        }
        .amount-cell {
            text-align: right;
            vertical-align: middle;
            font-family: monospace;
            padding: 6px;
        }
        @media print {
            body {
                background: none;
                margin: 0;
                padding: 0;
            }
            .page {
                box-shadow: none;
                margin: 0;
                padding: 0.2in;
                overflow: visible;  
                width: 100%;
                min-height: auto;
            }
            .control-buttons, .no-print {
                display: none !important;
            }
            .table-container, .table-items, .table-items th, .table-items td {
                overflow: visible !important;
            }
            @page {
                size: letter portrait;
                margin: 0.5cm;
            }
        }
        
        /* Modal Table Styles for PAR items */
        .modal .table-items {
            width: 100%;
            border-collapse: collapse !important;
            border: 1px solid #000 !important;
            margin-bottom: 0;
        }

        .modal .table-items th, 
        .modal .table-items td {
            border: 1px solid #000 !important;
            padding: 6px !important;
            font-size: 12px;
            vertical-align: middle;
            word-wrap: break-word;
            background-color: #fff !important;
        }

        .modal .table-items th {
            font-weight: bold;
            text-align: center;
        }

        /* Remove Bootstrap table borders and styling */
        .modal .table-items.table {
            margin-bottom: 0;
        }

        .modal .table-items tbody tr {
            border-top: none !important;
            background: transparent !important;
        }

        .modal .table-items tbody {
            border-top: none !important;
        }

        /* Remove excess borders */
        .modal .table-items thead tr th {
            border-bottom-width: 1px !important;
        }

        /* Column alignment */
        .modal .table-items td.quantity,
        .modal .table-items td.unit,
        .modal .table-items td.property-number,
        .modal .table-items td.date-cell,
        .modal .table-items td.amount-cell {
            text-align: center;
            vertical-align: middle;
        }

        .modal .table-items td.description-cell {
            text-align: left;
            padding-left: 8px !important;
            vertical-align: top;
        }

        /* Remove any bootstrap outlines or highlights */
        .modal .table-items tr:hover {
            background-color: transparent !important;
        }
        
        .modal .table-items tr:focus, 
        .modal .table-items td:focus {
            outline: none !important;
            box-shadow: none !important;
        }
        
        /* Fixed column widths for modal table */
        .modal .table-items th:nth-child(1), 
        .modal .table-items td:nth-child(1) {
            width: 8%;
        }
        .modal .table-items th:nth-child(2), 
        .modal .table-items td:nth-child(2) {
            width: 8%;
        }
        .modal .table-items th:nth-child(3), 
        .modal .table-items td:nth-child(3) {
            width: 34%;
        }
        .modal .table-items th:nth-child(4), 
        .modal .table-items td:nth-child(4) {
            width: 15%;
        }
        .modal .table-items th:nth-child(5), 
        .modal .table-items td:nth-child(5) {
            width: 15%;
        }
        .modal .table-items th:nth-child(6), 
        .modal .table-items td:nth-child(6) {
            width: 15%;
        }

        /* Form controls inside modal table */
        .modal .table-items input.form-control,
        .modal .table-items textarea.form-control {
            border: none;
            box-shadow: none;
            padding: 2px;
            font-size: 12px;
            width: 100%;
            min-height: 30px;
        }

        .modal .table-items textarea.form-control {
            min-height: 60px;
        }

        /* Remove default bootstrap form styling */
        .modal .table-items .form-control:focus {
            border-color: transparent;
            box-shadow: none;
        }
        
    </style>
</head>
<body>
    <div class="no-print container mb-3 mt-3">
        <div class="d-flex justify-content-between align-items-center">
            <button class="btn btn-secondary" onclick="window.close()">
                <i class="bi bi-arrow-left"></i> Back
            </button>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print PAR
            </button>
        </div>
    </div>

    <div class="page">
        <div class="header">
            <p class="appendix mb-0">Appendix 51</p>
            <h5 class="title">PROPERTY ACKNOWLEDGEMENT RECEIPT</h5>
        </div>

        <div class="par-details">
            <div class="row mb-2">
                <div class="col-8">
                    <strong>LGU:</strong> <?php echo $par['fund'] ?? 'Province of Negros Occidental'; ?>
                </div>
                <div class="col-4">
                    <strong>PAR No:</strong> <span class="par-number"><?php echo $par['par_no'] ?? ''; ?></span>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-8">
                    <strong>Fund:</strong> ___________
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="table-items">
                <thead>
                    <tr>
                        <th width="8%">QTY</th>
                        <th width="8%">Unit</th>
                        <th width="54%">Description</th>
                        <th width="15%">Property Number</th>
                        <th width="15%">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $displayTotal = 0;
                    if (!empty($items)): 
                        foreach ($items as $key => $item): 
                            $itemAmount = floatval($item->amount);
                            $itemQty = floatval($item->quantity);
                            $rowTotal = $itemQty * $itemAmount;
                            $displayTotal += $rowTotal;
                    ?>
                    <tr>
                        <td class="quantity"><?php echo str_replace(array("\r\n", "\n", "\r"), '<br>', $item->quantity); ?></td>
                        <td class="unit"><?php echo $item->unit; ?></td>
                        <td class="description-column">
                            <div class="description-cell"><?php echo $item->description; ?></div>
                        </td>
                        <td class="property-number"><?php echo $item->property_number; ?></td>
                        <td class="amount-cell">₱<?php echo number_format($itemAmount, 2); ?></td>
                    </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                    <tr>
                        <td class="quantity">112<br>321</td>
                        <td class="unit">31</td>
                        <td class="description-column">
                            <div class="description-cell">12</div>
                        </td>
                        <td class="property-number">1231</td>
                        <td class="amount-cell">₱123.00</td>
                    </tr>
                    <tr>
                        <td class="quantity">112<br>3</td>
                        <td class="unit">123</td>
                        <td class="description-column">
                            <div class="description-cell">123</div>
                        </td>
                        <td class="property-number">13</td>
                        <td class="amount-cell">₱123.00</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="row">
            <div class="col-12 text-end">
                <div class="instructions-box">
                    <p class="mb-0">Pls. assign property number corresponding to the item descriptions (serial, tag colors, et al.) as reflected (left to right/top to bottom respectively).</p>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-6">
                <p><strong>ISSUED BY:</strong></p>
                <div class="text-center mt-4">
                    <div class="signature-line mx-auto"></div>
                    <p class="signature-name mb-0">ARNEL D. ARGUSAR, MPA</p>
                    <p class="position-office">Provincial General Services Officer</p>
                </div>
            </div>
            <div class="col-6">
                <p><strong>RECEIVED BY:</strong></p>
                <div class="text-center mt-4">
                    <div class="signature-line mx-auto"></div>
                    <p class="signature-name mb-0">JOSE LARRY L. SAÑOR</p>
                    <p class="position-office">OIC-ICTD</p>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-6">
                <p><strong>DATE</strong></p>
            </div>
            <div class="col-6">
                <p><strong>DATE</strong></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Automatically trigger print dialog when print parameter is set
            <?php if ($autoPrint): ?>
            setTimeout(function() { 
                window.print();
            }, 500); // Small delay to ensure page is fully loaded
            <?php endif; ?>
        });
    </script>
</body>
</html> 
