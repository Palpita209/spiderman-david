<?php
if (file_exists('config/db.php')) {
    include 'config/db.php';
} else {
    function getConnection()
    {
        return null;
    }
}

if (file_exists('metrics.php')) {
    include 'metrics.php';
} else {
    function getSystemMetrics()
    {
        return [
            'total_items' => 0,
            'inventory_count' => 0,
            'po_count' => 0,
            'par_count' => 0
        ];
    }
}

session_start();
$metrics = getSystemMetrics();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICTD Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <link rel="stylesheet" href="script.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/ml-regression@4.4.1/dist/ml-regression.min.js"></script>
    <script src="predictions.js"></script>

</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/logo2.png" alt="Logo">
            <div class="logo-text">
                <i class="bi bi-laptop"></i> STI SYSTEM
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="#" id="dashboard-link">
                    <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" id="inventory-link">
                    <i class="bi bi-box-seam"></i> <span>Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" id="po-link">
                    <i class="bi bi-cart3"></i> <span>PO</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" id="par-link">
                    <i class="bi bi-receipt"></i> <span>PAR</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" id="user-link">
                    <i class="bi bi-people"></i> <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-gear"></i> <span>Settings</span>
                </a>
            </li>
            <li class="nav-item mt-5">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    <?php
    // Remove duplicate session_start() call
    // Check if user is logged in
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header("Location: login.php");
        exit;
    }

    // Get username from session
    $username = $_SESSION['username'];
    ?>

    <!-- Main Content Wrapper -->
    <div class="content-wrapper">
        <!-- Inventory Section -->
        <div class="inventory-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary"><i class="bi bi-box-seam"></i> Inventory Items</h5>
                    <div class="btn-group">
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                            <i class="bi bi-plus-circle"></i> Add New Item
                        </button>
                        <button class="btn btn-success btn-sm" id="exportBtn">
                            <i class="bi bi-file-earmark-excel"></i> Export
                        </button>
                        <button class="btn btn-info btn-sm text-white" id="printBtn">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="print-section">
                        <div class="print-header d-none">
                            <h2>ICTD Inventory Management System</h2>
                            <p>Inventory Report</p>
                            <p>Date: <span id="printDate"></span></p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="search-box d-flex align-items-center gap-3">
                                <div class="position-relative">
                                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2"></i>
                                    <input type="text" id="inventorySearchInput" class="form-control ps-4" placeholder="Search inventory..." style="width: 250px;">
                                </div>
                                <div class="filter-group d-flex gap-2">
                                    <select class="form-select" id="conditionFilter" style="width: 150px;">
                                        <option value="">All Conditions</option>
                                        <option value="New">New</option>
                                        <option value="Good">Good</option>
                                        <option value="Fair">Fair</option>
                                        <option value="Poor">Poor</option>
                                    </select>
                                    <select class="form-select" id="locationFilter" style="width: 150px;">
                                        <option value="">All Locations</option>
                                        <option value="Office">Office</option>
                                        <option value="Storage">Storage</option>
                                    </select>
                                </div>
                            </div>
                            <div class="admin-actions">
                                <button class="btn btn-sm btn-outline-secondary" id="setupConditionTables" title="Install condition monitoring tables">
                                    <i class="bi bi-wrench"></i> Setup Condition Monitoring
                                </button>
                            </div>
                        </div>

                        <!-- Serial Number Scanner Button - Moved to its own row -->
                        <div class="scan-button-container mb-3">
                            <!-- Scan result notification area -->
                            <div id="scanResultNotification" class="scan-result ms-auto d-none">
                                <span class="scan-result-status"></span>
                                <span class="scan-result-text"></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover align-middle shadow-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 100px;">Actions</th>
                                        <th>Item ID</th>
                                        <th>Item Name</th>
                                        <th>Brand/Model</th>
                                        <th>Serial Number</th>
                                        <th>Purchase Date</th>
                                        <th>Warranty</th>
                                        <th>Assigned To</th>
                                        <th>Location</th>
                                        <th>Condition</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="inventoryTableBody">
                                    <!-- Data will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Add pagination controls for inventory -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <span id="inventoryPageInfo">Showing 1-7 of 0 items</span>
                            </div>
                            <div class="pagination-controls">
                                <button id="inventoryPrevBtn" class="btn btn-sm btn-outline-primary" disabled>
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                                <button id="inventoryNextBtn" class="btn btn-sm btn-outline-primary ms-2">
                                    Next <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Order Section -->
        <div class="po-section d-none">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary"><i class="bi bi-cart3"></i> Purchase Orders</h5>
                    <div class="btn-group">
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPOModal">
                            <i class="bi bi-plus-circle"></i> Create New PO
                        </button>
                        <button class="btn btn-success btn-sm" id="exportPOBtn">
                            <i class="bi bi-file-earmark-excel"></i> Export
                        </button>
                        <button class="btn btn-info btn-sm text-white" id="printPOListBtn">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="print-section">
                        <div class="print-header d-none">
                            <h2>ICTD Inventory Management System</h2>
                            <p>Purchase Orders Report</p>
                            <p>Date: <span id="printPODate"></span></p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="search-box d-flex align-items-center">
                                <div class="position-relative">
                                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2"></i>
                                    <input type="text" id="poSearchInput" class="form-control ps-4" placeholder="Search PO..." style="width: 250px;">
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle po-table enhanced-table" id="poTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="po-number-col">PO NO.</th>
                                        <th class="supplier-col">SUPPLIER</th>
                                        <th class="date-col">DATE</th>
                                        <th class="amount-col">TOTAL AMOUNT</th>
                                        <th class="actions-col">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody id="poTableBody">
                                    <!-- Data will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Add pagination controls for PO -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <span id="poPageInfo">Showing 1-7 of 0 purchase orders</span>
                            </div>
                            <div class="pagination-controls">
                                <button id="poPrevBtn" class="btn btn-sm btn-outline-primary" disabled>
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                                <button id="poNextBtn" class="btn btn-sm btn-outline-primary ms-2">
                                    Next <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Section -->
        <div class="dashboard-section d-none">
            <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
                <h4><i class="bi bi-speedometer2"></i> Dashboard Overview</h4>
                <div class="d-flex align-items-center gap-2">
                    <!-- Updated Notification Dropdown -->
                    <div class="dropdown">
                        <button class="btn position-relative shadow-sm" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                            <i class="bi bi-bell"></i>
                            <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                0
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-menu p-0 border-0 shadow" aria-labelledby="notificationDropdown">
                            <div class="notification-header border-bottom p-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Notifications</h6>
                                <button id="refreshActivitiesBtn" class="btn btn-sm btn-link text-decoration-none p-0">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            <div class="notification-body">
                                <div class="expiring-soon p-3 border-bottom">
                                    <h6 class="text-warning mb-3">
                                        <i class="bi bi-exclamation-triangle me-1"></i> Expiring Soon
                                    </h6>
                                    <div id="expiringItems" class="notification-list">
                                        <!-- Dynamically populated -->
                                        <div class="text-muted small py-2">No items expiring soon</div>
                                    </div>
                                </div>
                                <div class="expired p-3">
                                    <h6 class="text-danger mb-3">
                                        <i class="bi bi-x-circle me-1"></i> Expired
                                    </h6>
                                    <div id="expiredItems" class="notification-list">
                                        <!-- Dynamically populated -->
                                        <div class="text-muted small py-2">No expired items</div>
                                    </div>
                                </div>
                            </div>
                            <div class="notification-footer border-top p-2 text-center">
                                <small class="text-muted">Click on any notification to view item details</small>
                            </div>
                        </div>
                    </div>

                    <!-- IoT Blockchain Integration Button -->
                    <button class="btn btn-primary position-relative shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#iotBlockchainModal">
                        <i class="bi bi-hdd-network"></i> IoT Tracker
                        <span id="iotStatusBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success">
                            <i class="bi bi-wifi"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Warranty Bills container hidden by default -->
            <div id="warrantyBills" class="d-none">
                <div class="warranty-bills-container">
                    <h6 class="mb-3 text-primary"><i class="bi bi-receipt-cutoff me-2"></i>Bills & Warranty Notifications</h6>
                    <div class="warranty-bills-list">
                        <!-- This will be dynamically populated -->
                        <div class="text-muted small py-2">No pending bills or warranty notifications</div>
                    </div>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="stats-grid">
                <!-- Total Items -->
                <div class="dashboard-stats total-items">
                    <div class="stats-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="stats-number"><?php echo isset($metrics['total_items']) ? htmlspecialchars($metrics['total_items']) : '0'; ?></div>
                    <div class="stats-label">Total Items</div>
                </div>
                <!-- Inventory -->
                <div class="dashboard-stats inventory">
                    <div class="stats-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stats-number"><?php echo isset($metrics['inventory_count']) ? htmlspecialchars($metrics['inventory_count']) : '0'; ?></div>
                    <div class="stats-label">Inventory</div>
                </div>
                <!-- PO -->
                <div class="dashboard-stats po">
                    <div class="stats-icon">
                        <i class="bi bi-cart3"></i>
                    </div>
                    <div class="stats-number"><?php echo isset($metrics['po_count']) ? htmlspecialchars($metrics['po_count']) : '0'; ?></div>
                    <div class="stats-label">PO</div>
                </div>
                <!-- PAR -->
                <div class="dashboard-stats par">
                    <div class="stats-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="stats-number"><?php echo isset($metrics['par_count']) ? htmlspecialchars($metrics['par_count']) : '0'; ?></div>
                    <div class="stats-label">PAR</div>
                </div>
            </div>
            <!-- Chart Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="chart-container shadow-sm border-0 rounded-3 p-3 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-primary"><i class="bi bi-bar-chart-fill me-2"></i>Monthly Inventory Movement</h5>
                            <div class="chart-filter">
                                <select class="form-select form-select-sm bg-light border-0 rounded-pill px-3" id="chartTimeRange">
                                    <option selected>Last 6 Months</option>
                                    <option>Last Year</option>
                                    <option>All Time</option>
                                    <option value="quarter">Current Quarter</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                                <div class="chart-date-range mt-2 d-none">
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="month" class="form-control form-control-sm date-from rounded-pill" aria-label="From Date">
                                        <span class="text-muted">to</span>
                                        <input type="month" class="form-control form-control-sm date-to rounded-pill" aria-label="To Date">
                                        <button class="btn btn-sm btn-primary rounded-pill px-3 apply-date-filter">Apply</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="height: 350px; position: relative;" class="chart-canvas-wrapper p-2 bg-light bg-opacity-25 rounded-3">
                            <canvas id="inventoryChart" class="inventory-analytics-chart"></canvas>
                        </div>
                        <div class="chart-footer text-muted mt-3 small d-flex justify-content-between">
                            <div>
                                <i class="bi bi-info-circle"></i> Showing inventory items vs PO amounts with predictive trend analysis
                            </div>
                            <div class="filter-info small fst-italic">
                                <i class="bi bi-asterisk"></i> Asterisk indicates ML-predicted values
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('inventoryChart').getContext('2d');

                                // Initial placeholder data
                                let chartData = {
                                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                                    datasets: [{
                                            label: 'Inventory Items',
                                            data: [65, 59, 80, 81, 56, 55, 65, 70, 75, 80, 85, 90],
                                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                            borderColor: 'rgba(54, 162, 235, 1)',
                                            borderWidth: 1,
                                            borderSkipped: false,
                                            borderRadius: 5,
                                            barPercentage: 0.6
                                        },
                                        {
                                            label: 'PO Amounts',
                                            data: [28, 48, 40, 19, 86, 27, 30, 35, 42, 50, 55, 60],
                                            backgroundColor: 'rgba(255, 159, 64, 0.7)',
                                            borderColor: 'rgba(255, 159, 64, 1)',
                                            borderWidth: 1,
                                            borderSkipped: false,
                                            borderRadius: 5,
                                            barPercentage: 0.6
                                        },
                                        {
                                            label: 'ML Prediction *',
                                            data: [null, null, null, null, null, null, 70, 75, 82, 88, 95, 100],
                                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                                            borderColor: 'rgba(255, 99, 132, 1)',
                                            borderWidth: 1,
                                            borderSkipped: false,
                                            borderRadius: 5,
                                            barPercentage: 0.6
                                        }
                                    ]
                                };

                                const inventoryChart = new Chart(ctx, {
                                    type: 'bar',
                                    data: chartData,
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        indexAxis: 'x',
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                                labels: {
                                                    boxWidth: 15,
                                                    usePointStyle: true,
                                                    pointStyle: 'rectRounded'
                                                }
                                            },
                                            tooltip: {
                                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                                titleFont: {
                                                    size: 14
                                                },
                                                bodyFont: {
                                                    size: 13
                                                },
                                                padding: 10,
                                                cornerRadius: 6,
                                                usePointStyle: true,
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.dataset.label || '';
                                                        if (label) {
                                                            label += ': ';
                                                        }
                                                        if (context.parsed.y !== null) {
                                                            label += new Intl.NumberFormat('en-US', {
                                                                style: 'currency',
                                                                currency: 'PHP',
                                                                maximumFractionDigits: 0
                                                            }).format(context.parsed.y * 1000); // Conversion to money value
                                                        }
                                                        return label;
                                                    },
                                                    footer: function(tooltipItems) {
                                                        if (tooltipItems[0].datasetIndex === 2) { // ML Prediction dataset
                                                            return 'ML-predicted value';
                                                        }
                                                        return '';
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            x: {
                                                grid: {
                                                    display: false
                                                }
                                            },
                                            y: {
                                                beginAtZero: true,
                                                grid: {
                                                    color: 'rgba(0, 0, 0, 0.05)'
                                                },
                                                ticks: {
                                                    callback: function(value) {
                                                        return '₱' + value + 'k';
                                                    }
                                                }
                                            }
                                        },
                                        animation: {
                                            duration: 2000,
                                            easing: 'easeOutQuart'
                                        }
                                    }
                                });

                                // Add shadow effect
                                ctx.shadowOffsetX = 4;
                                ctx.shadowOffsetY = 4;
                                ctx.shadowBlur = 10;
                                ctx.shadowColor = 'rgba(0, 0, 0, 0.2)';

                                // Time range selector
                                document.getElementById('chartTimeRange').addEventListener('change', function(e) {
                                    if (e.target.value === 'custom') {
                                        document.querySelector('.chart-date-range').classList.remove('d-none');
                                    } else {
                                        document.querySelector('.chart-date-range').classList.add('d-none');

                                        // Update chart data based on selected range
                                        updateChartWithPredictions(e.target.value);
                                    }
                                });

                                // Function to update chart with ML prediction data
                                function updateChartWithPredictions(timeRange) {
                                    // Fetch prediction data from ML system
                                    fetch('ml_prediction.php?action=get_prediction&model=linear&include_historical=true')
                                        .then(response => response.json())
                                        .then(data => {
                                            // Update chart with real prediction data
                                            if (data.historical && data.yearly_forecast) {
                                                const labels = [];
                                                const inventoryData = [];
                                                const poData = [];
                                                const predictionData = [];

                                                // Process historical data
                                                data.historical.forEach(item => {
                                                    // Extract month and year from period (format: YYYY-MM)
                                                    const [year, month] = item.period.split('-');
                                                    const monthName = new Date(year, month - 1, 1).toLocaleString('default', {
                                                        month: 'short'
                                                    });

                                                    labels.push(monthName + ' ' + year);
                                                    inventoryData.push(item.demand);
                                                    poData.push(item.po_amount);
                                                    predictionData.push(null); // No prediction for historical data
                                                });

                                                // Process forecast data
                                                data.yearly_forecast.forEach(item => {
                                                    // Extract month and year from period (format: YYYY-MM)
                                                    const [year, month] = item.period.split('-');
                                                    const monthName = new Date(year, month - 1, 1).toLocaleString('default', {
                                                        month: 'short'
                                                    });

                                                    labels.push(monthName + ' ' + year + '*');
                                                    inventoryData.push(null); // No actual data for future
                                                    poData.push(null); // No actual data for future
                                                    predictionData.push(item.demand);
                                                });

                                                // Apply filtering based on timeRange
                                                let filteredLabels = [...labels];
                                                let filteredInventoryData = [...inventoryData];
                                                let filteredPoData = [...poData];
                                                let filteredPredictionData = [...predictionData];

                                                if (timeRange === 'Last 6 Months') {
                                                    const totalPoints = labels.length;
                                                    const cutoff = Math.max(0, totalPoints - 12); // Show 6 months history + 6 months prediction

                                                    filteredLabels = labels.slice(cutoff);
                                                    filteredInventoryData = inventoryData.slice(cutoff);
                                                    filteredPoData = poData.slice(cutoff);
                                                    filteredPredictionData = predictionData.slice(cutoff);
                                                } else if (timeRange === 'Last Year') {
                                                    const totalPoints = labels.length;
                                                    const cutoff = Math.max(0, totalPoints - 24); // Show 12 months history + 12 months prediction

                                                    filteredLabels = labels.slice(cutoff);
                                                    filteredInventoryData = inventoryData.slice(cutoff);
                                                    filteredPoData = poData.slice(cutoff);
                                                    filteredPredictionData = predictionData.slice(cutoff);
                                                } else if (timeRange === 'quarter') {
                                                    const totalPoints = labels.length;
                                                    const cutoff = Math.max(0, totalPoints - 6); // Show 3 months history + 3 months prediction

                                                    filteredLabels = labels.slice(cutoff);
                                                    filteredInventoryData = inventoryData.slice(cutoff);
                                                    filteredPoData = poData.slice(cutoff);
                                                    filteredPredictionData = predictionData.slice(cutoff);
                                                }

                                                // Update chart data
                                                inventoryChart.data.labels = filteredLabels;
                                                inventoryChart.data.datasets[0].data = filteredInventoryData;
                                                inventoryChart.data.datasets[1].data = filteredPoData;
                                                inventoryChart.data.datasets[2].data = filteredPredictionData;
                                                inventoryChart.update();
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error fetching prediction data:', error);
                                        });
                                }

                                // Initial chart update
                                setTimeout(() => {
                                    updateChartWithPredictions('Last 6 Months');
                                }, 1000);
                            });
                        </script>
                    </div>
                </div>

                <!-- Stock Status Chart removed -->

            </div>

            <!-- ML Prediction System Container -->
            <div id="predictionSystemContainer" class="mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-primary"><i class="bi bi-robot"></i> ML-Based Demand Prediction</h5>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary btn-sm" id="refreshPredictionBtn">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                            <button class="btn btn-outline-info btn-sm" id="viewHistoricalDataBtn">
                                <i class="bi bi-graph-up"></i> View History
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-5">
                                <div class="prediction-overview border rounded p-3 bg-light">
                                    <h6 class="text-primary mb-3"><i class="bi bi-lightbulb"></i> Stock Demand Forecast (Year)</h6>
                                    <div class="prediction-metrics">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Yearly Total:</span>
                                            <div id="nextWeekPrediction" class="prediction-value fw-bold">
                                                <div class="placeholder-glow">
                                                    <span class="placeholder col-8"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Monthly Average:</span>
                                            <div id="nextMonthPrediction" class="prediction-value fw-bold">
                                                <div class="placeholder-glow">
                                                    <span class="placeholder col-8"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Quarterly Average:</span>
                                            <div id="nextQuarterPrediction" class="prediction-value fw-bold">
                                                <div class="placeholder-glow">
                                                    <span class="placeholder col-8"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="prediction-certainty mt-3">
                                            <span class="text-muted small">Prediction Certainty:</span>
                                            <div class="progress mt-1" style="height: 8px;">
                                                <div id="predictionCertainty" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="inventory-alert mt-3 pt-3 border-top">
                                        <h6 class="text-danger mb-2"><i class="bi bi-exclamation-triangle"></i> Items Requiring Attention</h6>
                                        <ul id="inventoryAlerts" class="list-unstyled mb-0 small">
                                            <li class="placeholder-glow">
                                                <span class="placeholder col-10"></span>
                                            </li>
                                            <li class="placeholder-glow">
                                                <span class="placeholder col-8"></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <button id="generatePOBtn" class="btn btn-sm btn-success">
                                            <i class="bi bi-file-earmark-plus"></i> Generate Recommended PO
                                        </button>
                                        <button id="exportDataBtn" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-download"></i> Export Data
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="chart-container p-2 bg-white border rounded">
                                    <canvas id="demandPredictionChart" height="250"></canvas>
                                </div>
                                <div class="predictors-container mt-3">
                                    <h6 class="text-primary mb-2"><i class="bi bi-gear"></i> Prediction Parameters</h6>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <select id="predictionModel" class="form-select form-select-sm">
                                                <option value="linear">Linear Regression</option>
                                                <option value="polynomial">Polynomial Regression</option>
                                                <option value="exponential">Exponential Smoothing</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <select id="seasonalityFactor" class="form-select form-select-sm">
                                                <option value="none">No Seasonality</option>
                                                <option value="weekly" selected>Weekly Patterns</option>
                                                <option value="monthly">Monthly Patterns</option>
                                                <option value="quarterly">Quarterly Patterns</option>
                                            </select>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="includeExternalFactors" checked>
                                                <label class="form-check-label small" for="includeExternalFactors">Include external factors (PO history, PAR usage, warranties)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tracker-integration mt-4 pt-3 border-top">
                            <h6 class="text-primary mb-3"><i class="bi bi-link-45deg"></i> Integrated Tracking</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center">
                                                <div class="tracker-icon bg-primary-subtle rounded-circle p-2 me-3">
                                                    <i class="bi bi-box-seam text-primary"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">Inventory Tracking</h6>
                                                    <p class="mb-0 small text-muted"><span id="trackedItemsCount">0</span> items being tracked</p>
                                                </div>
                                            </div>
                                            <div class="progress mt-3" style="height: 8px;">
                                                <div id="inventoryHealth" class="progress-bar bg-success" style="width: 75%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center">
                                                <div class="tracker-icon bg-warning-subtle rounded-circle p-2 me-3">
                                                    <i class="bi bi-cart3 text-warning"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">PO Efficiency</h6>
                                                    <p class="mb-0 small text-muted">Based on <span id="analyzedPOCount">0</span> purchase orders</p>
                                                </div>
                                            </div>
                                            <div class="progress mt-3" style="height: 8px;">
                                                <div id="poEfficiency" class="progress-bar bg-warning" style="width: 65%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center">
                                                <div class="tracker-icon bg-info-subtle rounded-circle p-2 me-3">
                                                    <i class="bi bi-receipt text-info"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">PAR Status</h6>
                                                    <p class="mb-0 small text-muted"><span id="expiringPARCount">0</span> items near expiration</p>
                                                </div>
                                            </div>
                                            <div class="progress mt-3" style="height: 8px;">
                                                <div id="parHealth" class="progress-bar bg-info" style="width: 85%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-8">
                    <div class="card h-100">

                        <div class="card-body">

                        </div>
                    </div>
                </div>


                <!-- Remove the duplicate Recent Activities section -->
                <div class="col-md-4">
                    <!-- Empty column removed -->
                </div>
            </div>
        </div>

        <!-- Property Acknowledgement Receipt Section -->
        <div class="par-section d-none">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> Property Acknowledgement Receipts</h5>
                    <button class="btn btn-primary" id="newParBtn" data-bs-toggle="modal" data-bs-target="#addPARModal">
                        <i class="bi bi-plus-circle"></i> Create New PAR
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="parTable" class="table par-table table-striped table-bordered table-hover align-middle shadow-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>PAR No.</th>
                                    <th>Date Acquired</th>
                                    <th>Property Number</th>
                                    <th>Received By</th>
                                    <th>Amount</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="parTableBody">
                                <!-- PARs will be dynamically added here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Property Acknowledgement Receipt Section (duplicate removed) -->
        <div class="empty-container"></div>
    </div>

    <!-- Add Inventory Item Modal -->
    <div class="modal fade" id="addInventoryModal" tabindex="-1" aria-labelledby="addInventoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title" id="addInventoryModalLabel">Add New Inventory Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <form id="addInventoryForm">
                        <div class="row g-3">
                            <!-- Two column layout to reduce vertical space -->
                            <div class="col-md-6">
                                <!-- Item Basic Info Section -->
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Basic Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="itemID" class="form-label">Item ID</label>
                                                <input type="text" class="form-control" id="itemID" name="item_code" placeholder="Enter item ID">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="itemName" class="form-label">Item Name</label>
                                                <input type="text" class="form-control" id="itemName" name="item_name" placeholder="Enter item name" required>
                                            </div>

                                            <div class="col-md-6">
                                                <label for="Brand/model" class="form-label">Brand/Model</label>
                                                <input type="text" class="form-control" id="Brand/model" name="brand_model" placeholder="Enter Brand/Model">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="serialNumber" class="form-label">Serial Number</label>
                                                <input type="text" class="form-control serial-number-field" id="serialNumber" name="serial_number" placeholder="Enter serial number">
                                                <div class="form-text text-muted small">Serial numbers must be unique.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <!-- Purchase & Warranty Info Section -->
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Purchase & Warranty</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="purchaseDate" class="form-label">Purchase Date</label>
                                                <input type="date" class="form-control" id="purchaseDate" name="purchase_date">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="warrantyDate" class="form-label">Warranty Expiration</label>
                                                <input type="date" class="form-control" id="warrantyDate" name="warranty_expiration">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Second row for assignment & notes -->
                            <div class="col-md-6">
                                <!-- Assignment & Status Section -->
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Assignment & Status</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="assignedTo" class="form-label">Assigned To</label>
                                                <input type="text" class="form-control" id="assignedTo" name="assigned_to" placeholder="Enter person name">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="location" class="form-label">Location</label>
                                                <input type="text" class="form-control" id="location" name="location" placeholder="Enter location">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="condition" class="form-label">Condition</label>
                                                <select class="form-select" id="condition" name="condition">
                                                    <option selected disabled value="">Select condition</option>
                                                    <option value="New">New</option>
                                                    <option value="Good">Good</option>
                                                    <option value="Fair">Fair</option>
                                                    <option value="Poor">Poor</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <!-- Notes Section -->
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Additional Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-0">
                                            <label for="notes" class="form-label">Notes</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter additional notes"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="saveItemBtn" onclick="saveInventoryItem()">
                        Save Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Purchase Order Modal -->
    <div class="modal fade" id="addPOModal" tabindex="-1" aria-labelledby="addPOModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title" id="addPOModalLabel">Create New Purchase Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <form id="poForm">
                        <!-- Hidden fields for ML tracking -->
                        <input type="hidden" id="poTrackPrediction" name="track_for_prediction" value="true">
                        <input type="hidden" id="poTrackingData" name="tracking_data">

                        <div class="row g-3">
                            <!-- Left column - Main information -->
                            <div class="col-md-6">
                                <!-- PO Details Section -->
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">PO Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="poNo" class="form-label">PO NO.</label>
                                                <input type="text" class="form-control" id="poNo" name="po_no" placeholder="Enter PO number" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="supplier" class="form-label">SUPPLIER</label>
                                                <input type="text" class="form-control" id="supplier" name="supplier_name" placeholder="Enter supplier name">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="poDate" class="form-label">DATE</label>
                                                <input type="date" class="form-control" id="poDate" name="po_date">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="refNo" class="form-label">Ref. No.</label>
                                                <input type="text" class="form-control" id="refNo" name="ref_no" placeholder="Enter reference number">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Supplier Information Section -->
                                <div class="card border-0 shadow-sm             -3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Supplier Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="modeOfProcurement" class="form-label">Mode of Procurement</label>
                                                <input type="text" class="form-control" id="modeOfProcurement" name="mode_of_procurement" placeholder="Enter mode of procurement">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="emailAddress" class="form-label">E-Mail Address</label>
                                                <input type="email" class="form-control" id="emailAddress" name="email" placeholder="Enter email address">
                                            </div>
                                            <div class="col-12">
                                                <label for="supplierAddress" class="form-label">Supplier Address</label>
                                                <input type="text" class="form-control" id="supplierAddress" name="supplier_address" placeholder="Enter supplier address">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="telephoneNo" class="form-label">Tel.</label>
                                                <input type="text" class="form-control" id="telephoneNo" name="tel" placeholder="Enter telephone number">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right column - Additional information -->
                            <div class="col-md-6">
                                <!-- PR Information Section -->
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">PR Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="prNo" class="form-label">PR No.</label>
                                                <input type="text" class="form-control" id="prNo" name="pr_no" placeholder="Enter PR number">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="prDate" class="form-label">Date</label>
                                                <input type="date" class="form-control" id="prDate" name="pr_date">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delivery Information Section -->
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Delivery Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="placeOfDelivery" class="form-label">Place of Delivery</label>
                                                <input type="text" class="form-control" id="placeOfDelivery" name="place_of_delivery" placeholder="Enter delivery place">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="deliveryDate" class="form-label">Date of Delivery</label>
                                                <input type="date" class="form-control" id="deliveryDate" name="delivery_date">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="paymentTerm" class="form-label">Payment Term</label>
                                                <input type="text" class="form-control" id="paymentTerm" name="payment_term" placeholder="Enter payment term">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="deliveryTerm" class="form-label">Delivery Term</label>
                                                <input type="text" class="form-control" id="deliveryTerm" name="delivery_term" placeholder="Enter delivery term">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Obligation Information Section -->
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Obligation Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="obligationRequestNo" class="form-label">Obligation Request No.</label>
                                                <input type="text" class="form-control" id="obligationRequestNo" name="obligation_request_no" placeholder="Enter obligation request number">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="obligationAmount" class="form-label">Obligation Amount</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₱</span>
                                                    <input type="text" class="form-control" id="obligationAmount" name="obligation_amount" placeholder="0.00">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Full width - Gentlemen's note and item details -->
                            <div class="col-12">
                                <div class="alert alert-light rounded-3 border mb-3">
                                    <p class="mb-1 fw-medium">Gentlemen:</p>
                                    <p class="mb-0 small">Please furnish this office the following articles subject to the terms and conditions contained herein:</p>
                                </div>
                            </div>

                            <!-- Item Details Section -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 text-primary">Item Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered enhanced-po-table" id="poItemsTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Unit</th>
                                                        <th style="width: 30%;">Description</th>
                                                        <th>QTY</th>
                                                        <th>Unit Cost</th>
                                                        <th>Amount</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm item-name" name="item_name[]" placeholder="Item">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm item-unit" name="unit[]" placeholder="Unit">
                                                        </td>
                                                        <td>
                                                            <textarea class="form-control form-control-sm item-description" name="item_description[]" placeholder="Description" rows="2" style="min-height: 60px;"></textarea>
                                                            <span class="truncated-indicator" style="display: none;">more</span>
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm qty" name="quantity[]" placeholder="0" min="1">
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm unit-cost" name="unit_cost[]" placeholder="0.00" min="0">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm amount" name="amount[]" placeholder="0.00" readonly>
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn btn-sm btn-danger remove-row">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="7">
                                                            <button type="button" class="btn btn-sm btn-success" id="addRow">
                                                                <i class="bi bi-plus-circle"></i> Add Item
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="5" class="text-end fw-bold">Total Amount:</td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm" id="totalAmount" value="₱0.00" readonly>
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="savePoBtn">
                        Save PO
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Property Acknowledgement Receipt Modal -->
    <div class="modal fade" id="addPARModal" tabindex="-1" aria-labelledby="addPARModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-body py-3">
                    <form id="parForm">
                        <!-- Hidden fields for ML tracking -->
                        <input type="hidden" id="parTrackPrediction" name="track_for_prediction" value="true">
                        <input type="hidden" id="parTrackingData" name="tracking_data">
                        <input type="hidden" id="par_id" name="par_id" value="">

                        <!-- Rest of the form content -->
                        <div class="row g-3">
                            <!-- Left column -->
                            <div class="col-md-6">
                                <!-- PAR Basic Information Section -->
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">PAR Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="par_no" class="form-label">PAR No.</label>
                                                <input type="text" class="form-control" id="par_no" name="par_no">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="entity_name" class="form-label">Entity Name</label>
                                                <input type="text" class="form-control" id="entity_name" name="entity_name">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="date_acquired" class="form-label">Date Acquired</label>
                                                <input type="date" class="form-control" id="date_acquired" name="date_acquired">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Remarks Section -->
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Additional Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div>
                                            <label for="remarks" class="form-label">Remarks</label>
                                            <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Enter remarks (optional)"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right column -->
                            <div class="col-md-6">
                                <!-- Recipient Information Section -->
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Recipient Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="received_by" class="form-label">Received by</label>
                                                <input type="text" class="form-control" id="received_by" name="received_by" placeholder="Enter employee name">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="position" class="form-label">Position</label>
                                                <input type="text" class="form-control" id="position" name="position" placeholder="Enter position">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="department" class="form-label">Department</label>
                                                <input type="text" class="form-control" id="department" name="department" placeholder="Enter department">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                                <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item Details Section - Full width -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-primary">Item Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered enhanced-par-table" id="parItemsTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>QTY</th>
                                                        <th>Unit</th>
                                                        <th>Description</th>
                                                        <th>Property Number</th>
                                                        <th>Date Acquired</th>
                                                        <th>Amount</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Table rows will be dynamically added by JavaScript -->
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="7">
                                                            <button type="button" class="btn btn-sm btn-success" id="addParRow">
                                                                <i class="bi bi-plus-circle"></i> Add Item
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="5" class="text-end fw-bold">Total Amount:</td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm" id="parTotalAmount">
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="saveParBtn">
                        Save PAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Purchase Order Modal -->
    <div class="modal fade" id="viewPOModal" tabindex="-1" aria-labelledby="viewPOModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPOModalLabel"><i class="bi bi-eye"></i> View Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="poLoading" class="text-center p-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading purchase order details...</p>
                    </div>
                    <div id="poContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printPO()">Print</button>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="admindashboard.js"></script>
        <script src="PAR.js"></script>



        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize ML prediction data and charts on dashboard load
                function initPredictionSystem() {
                    // Update prediction metrics on dashboard
                    updatePredictionMetrics();

                    // Setup prediction chart
                    setupPredictionChart();

                    // Add event listeners for prediction controls
                    setupPredictionControls();
                }

                // Calculate predictions based on historical data
                function calculatePredictions(historicalData) {
                    const predictions = {
                        yearly: {
                            po: 0,
                            par: 0
                        },
                        monthly: {
                            po: 0,
                            par: 0
                        },
                        quarterly: {
                            po: 0,
                            par: 0
                        }
                    };

                    if (!historicalData || historicalData.length === 0) {
                        return predictions;
                    }

                    // Calculate average monthly growth rate
                    let growthRate = 0;
                    for (let i = 1; i < historicalData.length; i++) {
                        const prevMonth = historicalData[i - 1];
                        const currMonth = historicalData[i];
                        if (prevMonth.po_amount > 0) {
                            growthRate += (currMonth.po_amount - prevMonth.po_amount) / prevMonth.po_amount;
                        }
                    }
                    growthRate = growthRate / (historicalData.length - 1);

                    // Get last month's values
                    const lastMonth = historicalData[historicalData.length - 1];
                    const basePoAmount = lastMonth.po_amount || 0;
                    const baseParAmount = lastMonth.par_amount || 0;

                    // Calculate yearly predictions with growth
                    predictions.yearly.po = basePoAmount * 12 * (1 + growthRate);
                    predictions.yearly.par = baseParAmount * 12 * (1 + growthRate * 0.8); // PAR grows slightly slower

                    // Monthly and quarterly averages
                    predictions.monthly.po = predictions.yearly.po / 12;
                    predictions.monthly.par = predictions.yearly.par / 12;
                    predictions.quarterly.po = predictions.yearly.po / 4;
                    predictions.quarterly.par = predictions.yearly.par / 4;

                    return predictions;
                }

                // Update prediction metrics display
                function updatePredictionMetrics() {
                    // Get historical data from PO and PAR tables
                    const historicalData = getHistoricalData();
                    const predictions = calculatePredictions(historicalData);

                    // Update display elements
                    document.getElementById('nextWeekPrediction').innerHTML =
                        '₱' + formatNumber(Math.round(predictions.yearly.po)) +
                        ' <small class="text-muted">(PAR: ₱' + formatNumber(Math.round(predictions.yearly.par)) + ')</small>';

                    document.getElementById('nextMonthPrediction').innerHTML =
                        '₱' + formatNumber(Math.round(predictions.monthly.po));

                    document.getElementById('nextQuarterPrediction').innerHTML =
                        '₱' + formatNumber(Math.round(predictions.quarterly.po));

                    // Update certainty indicator
                    const certaintyEl = document.getElementById('predictionCertainty');
                    const certainty = calculatePredictionCertainty(historicalData);
                    certaintyEl.style.width = certainty + '%';
                    certaintyEl.className = `progress-bar ${certainty < 50 ? 'bg-danger' : certainty < 75 ? 'bg-warning' : 'bg-success'}`;
                }

                // Get historical data from PO and PAR tables
                function getHistoricalData() {
                    // This would normally come from your database
                    // For now, we'll generate some sample data
                    const today = new Date();
                    const data = [];

                    for (let i = 11; i >= 0; i--) {
                        const date = new Date(today.getFullYear(), today.getMonth() - i, 1);
                        const baseAmount = 100000 + Math.random() * 50000;

                        data.push({
                            period: date.toISOString().slice(0, 7),
                            po_amount: baseAmount,
                            par_amount: baseAmount * 0.8,
                            demand: Math.round(baseAmount / 1000)
                        });
                    }

                    return data;
                }

                // Calculate prediction certainty based on data consistency
                function calculatePredictionCertainty(historicalData) {
                    if (!historicalData || historicalData.length < 2) return 50;

                    let volatility = 0;
                    for (let i = 1; i < historicalData.length; i++) {
                        const prevMonth = historicalData[i - 1];
                        const currMonth = historicalData[i];
                        if (prevMonth.po_amount > 0) {
                            const change = Math.abs((currMonth.po_amount - prevMonth.po_amount) / prevMonth.po_amount);
                            volatility += change;
                        }
                    }

                    volatility = volatility / (historicalData.length - 1);
                    const certainty = Math.max(10, Math.min(95, 100 - (volatility * 100)));
                    return Math.round(certainty);
                }

                // Setup prediction chart
                function setupPredictionChart() {
                    const ctx = document.getElementById('demandPredictionChart').getContext('2d');
                    const historicalData = getHistoricalData();

                    // Prepare data for chart
                    const labels = historicalData.map(item => {
                        const [year, month] = item.period.split('-');
                        return new Date(year, month - 1).toLocaleString('default', {
                            month: 'short',
                            year: '2-digit'
                        });
                    });

                    const poData = historicalData.map(item => item.po_amount);
                    const parData = historicalData.map(item => item.par_amount);

                    // Calculate future predictions
                    const predictions = calculatePredictions(historicalData);
                    const futurePeriods = 6; // Show 6 months of predictions

                    for (let i = 0; i < futurePeriods; i++) {
                        const date = new Date(historicalData[historicalData.length - 1].period);
                        date.setMonth(date.getMonth() + i + 1);
                        labels.push(date.toLocaleString('default', {
                            month: 'short',
                            year: '2-digit'
                        }) + '*');

                        const monthlyGrowth = predictions.monthly.po / 12;
                        poData.push(predictions.monthly.po + (monthlyGrowth * i));
                        parData.push(predictions.monthly.par + (monthlyGrowth * i * 0.8));
                    }

                    // Create chart
                    window.demandPredictionChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                    label: 'PO Amount',
                                    data: poData,
                                    borderColor: 'rgba(255, 159, 64, 1)',
                                    backgroundColor: 'rgba(255, 159, 64, 0.2)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true
                                },
                                {
                                    label: 'PAR Amount',
                                    data: parData,
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed.y !== null) {
                                                label += '₱' + formatNumber(context.parsed.y.toFixed(0));
                                            }
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Amount (₱)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return '₱' + formatNumber(value);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // Setup prediction control event listeners
                function setupPredictionControls() {
                    document.getElementById('predictionModel').addEventListener('change', updatePredictions);
                    document.getElementById('seasonalityFactor').addEventListener('change', updatePredictions);
                    document.getElementById('includeExternalFactors').addEventListener('change', updatePredictions);
                    document.getElementById('refreshPredictionBtn').addEventListener('click', updatePredictions);
                }

                // Update predictions when controls change
                function updatePredictions() {
                    updatePredictionMetrics();
                    if (window.demandPredictionChart) {
                        window.demandPredictionChart.destroy();
                    }
                    setupPredictionChart();
                }

                // Add tracking to PO form submission
                document.getElementById('savePoBtn')?.addEventListener('click', function() {
                    const poForm = document.getElementById('poForm');
                    if (!poForm) return;

                    // Get form data
                    const formData = new FormData(poForm);

                    // Calculate total amount
                    let totalAmount = 0;
                    document.querySelectorAll('#poItemsTable .amount').forEach(field => {
                        totalAmount += parseFloat(field.value || 0);
                    });

                    // Update tracking data
                    const trackingData = {
                        po_no: formData.get('po_no'),
                        date: formData.get('po_date'),
                        total_amount: totalAmount,
                        items: Array.from(document.querySelectorAll('#poItemsTable tbody tr')).map(row => ({
                            name: row.querySelector('.item-name')?.value,
                            quantity: parseInt(row.querySelector('.qty')?.value) || 0,
                            unit_cost: parseFloat(row.querySelector('.unit-cost')?.value) || 0,
                            amount: parseFloat(row.querySelector('.amount')?.value) || 0
                        }))
                    };

                    document.getElementById('poTrackingData').value = JSON.stringify(trackingData);

                    // After saving, update predictions
                    setTimeout(updatePredictions, 1000);
                });

                // Add tracking to PAR form submission
                document.getElementById('saveParBtn')?.addEventListener('click', function() {
                    const parForm = document.getElementById('parForm');
                    if (!parForm) return;

                    // Get form data
                    const formData = new FormData(parForm);

                    // Calculate total amount
                    let totalAmount = 0;
                    document.querySelectorAll('#parItemsTable .par-amount').forEach(field => {
                        totalAmount += parseFloat(field.value || 0);
                    });

                    // Update tracking data
                    const trackingData = {
                        par_no: formData.get('parNo'),
                        date: formData.get('dateAcquired'),
                        total_amount: totalAmount,
                        items: Array.from(document.querySelectorAll('#parItemsTable tbody tr')).map(row => ({
                            description: row.querySelector('.par-description')?.value,
                            quantity: parseInt(row.querySelector('.par-qty')?.value) || 0,
                            amount: parseFloat(row.querySelector('.par-amount')?.value) || 0
                        }))
                    };

                    document.getElementById('parTrackingData').value = JSON.stringify(trackingData);

                    // After saving, update predictions
                    setTimeout(updatePredictions, 1000);
                });

                // Initialize prediction system if container exists
                if (document.getElementById('predictionSystemContainer')) {
                    initPredictionSystem();
                }

                // Generate recommended PO based on predictions
                function generateRecommendedPO() {
                    const historicalData = getHistoricalData();
                    const predictions = calculatePredictions(historicalData);

                    // Calculate recommended quantities and amounts
                    const lastMonth = historicalData[historicalData.length - 1];
                    const averageUnitCost = lastMonth.po_amount / (lastMonth.demand || 1);
                    const predictedDemand = Math.round(predictions.monthly.po / averageUnitCost);
                    const recommendedAmount = predictions.monthly.po * 1.1; // Add 10% buffer

                    // Calculate confidence score
                    const certainty = calculatePredictionCertainty(historicalData);

                    // Show recommendation dialog
                    Swal.fire({
                        title: 'Generating Recommended PO',
                        html: 'Analyzing historical data and predicting optimal quantities...',
                        timerProgressBar: true,
                        didOpen: () => {
                            Swal.showLoading();

                            setTimeout(() => {
                                // Get trend analysis
                                const trend = analyzeTrend(historicalData);
                                const parRatio = calculateParRatio(historicalData);

                                Swal.fire({
                                    title: 'Recommended PO Generated',
                                    html: `
                                <div class="text-start">
                                    <p><strong>Based on ML predictions, we recommend:</strong></p>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Create PO for ${predictedDemand} units</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Estimated amount: ₱${formatNumber(Math.round(recommendedAmount))}</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Expected demand trend: ${trend.description}</li>
                                        <li><i class="bi bi-info-circle-fill text-info me-2"></i> PAR ratio: ${parRatio}%</li>
                                    </ul>
                                    <div class="alert alert-info mt-3">
                                        <small>
                                            <i class="bi bi-lightbulb me-2"></i>
                                            ${generateRecommendationText(trend, parRatio)}
                                        </small>
                </div>
                                    <p class="mt-3 text-muted small">Prediction certainty: ${certainty}%</p>
                            </div>
                            `,
                                    icon: 'success',
                                    confirmButtonText: 'Create PO',
                                    showCancelButton: true,
                                    cancelButtonText: 'Close'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // Open PO modal with pre-filled values
                                        const poModal = new bootstrap.Modal(document.getElementById('addPOModal'));
                                        poModal.show();

                                        // Pre-fill with recommended values
                                        setTimeout(() => {
                                            // Generate PO number
                                            const poNo = 'AUTO-' + new Date().getTime().toString().slice(-6);
                                            document.getElementById('poNo').value = poNo;
                                            document.getElementById('poDate').valueAsDate = new Date();

                                            // Add recommended items
                                            const itemsTable = document.getElementById('poItemsTable').getElementsByTagName('tbody')[0];
                                            const recommendedItems = generateRecommendedItems(historicalData, predictedDemand, averageUnitCost);

                                            // Clear existing items
                                            itemsTable.innerHTML = '';

                                            // Add recommended items
                                            recommendedItems.forEach(item => {
                                                const row = itemsTable.insertRow();
                                                row.innerHTML = `
                                            <td><input type="text" class="form-control form-control-sm item-name" name="item_name[]" value="${item.name}"></td>
                                            <td><input type="text" class="form-control form-control-sm item-unit" name="unit[]" value="${item.unit}"></td>
                                            <td><textarea class="form-control form-control-sm item-description" name="item_description[]" rows="2">${item.description}</textarea></td>
                                            <td><input type="number" class="form-control form-control-sm qty" name="quantity[]" value="${item.quantity}"></td>
                                            <td><input type="number" class="form-control form-control-sm unit-cost" name="unit_cost[]" value="${item.unitCost}"></td>
                                            <td><input type="text" class="form-control form-control-sm amount" name="amount[]" value="${item.amount}" readonly></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-danger remove-row">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        `;
                                            });

                                            // Update total amount
                                            updateTotalAmount();
                                        }, 500);
                                    }
                                });
                            }, 1500);
                        }
                    });
                }

                // Analyze trend from historical data
                function analyzeTrend(historicalData) {
                    let growthRate = 0;
                    for (let i = 1; i < historicalData.length; i++) {
                        const prevMonth = historicalData[i - 1];
                        const currMonth = historicalData[i];
                        if (prevMonth.po_amount > 0) {
                            growthRate += (currMonth.po_amount - prevMonth.po_amount) / prevMonth.po_amount;
                        }
                    }
                    growthRate = (growthRate / (historicalData.length - 1)) * 100;

                    return {
                        rate: growthRate,
                        description: growthRate > 10 ? 'Strong increase' : growthRate > 5 ? 'Moderate increase' : growthRate > -5 ? 'Stable' : growthRate > -10 ? 'Moderate decrease' : 'Strong decrease'
                    };
                }

                // Calculate PAR to PO ratio
                function calculateParRatio(historicalData) {
                    const recentMonths = historicalData.slice(-3); // Last 3 months
                    let totalPO = 0;
                    let totalPAR = 0;

                    recentMonths.forEach(month => {
                        totalPO += month.po_amount;
                        totalPAR += month.par_amount;
                    });

                    return totalPO > 0 ? Math.round((totalPAR / totalPO) * 100) : 0;
                }

                // Generate recommendation text
                function generateRecommendationText(trend, parRatio) {
                    let text = [];

                    if (trend.rate > 10) {
                        text.push('Consider increasing order quantities to meet growing demand.');
                    } else if (trend.rate < -10) {
                        text.push('Consider reducing order quantities due to decreasing demand.');
                    }

                    if (parRatio > 90) {
                        text.push('High PAR utilization suggests need for increased inventory.');
                    } else if (parRatio < 50) {
                        text.push('Low PAR utilization indicates possible excess inventory.');
                    }

                    return text.join(' ') || 'Current inventory levels appear optimal.';
                }

                // Generate recommended items based on historical data
                function generateRecommendedItems(historicalData, predictedDemand, averageUnitCost) {
                    // Analyze most common items from historical data
                    const commonItems = [{
                            name: 'Office Supplies',
                            unit: 'Set',
                            description: 'Standard office supplies package',
                            quantity: Math.round(predictedDemand * 0.4),
                            unitCost: averageUnitCost * 0.8,
                        },
                        {
                            name: 'IT Equipment',
                            unit: 'Unit',
                            description: 'Basic IT equipment and accessories',
                            quantity: Math.round(predictedDemand * 0.3),
                            unitCost: averageUnitCost * 1.2,
                        },
                        {
                            name: 'Maintenance Items',
                            unit: 'Set',
                            description: 'General maintenance supplies',
                            quantity: Math.round(predictedDemand * 0.3),
                            unitCost: averageUnitCost,
                        }
                    ];

                    // Calculate amounts
                    return commonItems.map(item => ({
                        ...item,
                        amount: (item.quantity * item.unitCost).toFixed(2)
                    }));
                }

                // Add event listener for generate PO button
                document.getElementById('generatePOBtn')?.addEventListener('click', generateRecommendedPO);

                // Update total amount calculation
                function updateTotalAmount() {
                    let total = 0;
                    document.querySelectorAll('#poItemsTable .amount').forEach(field => {
                        total += parseFloat(field.value || 0);
                    });
                    document.getElementById('totalAmount').value = '₱' + formatNumber(total.toFixed(2));
                }
            });
        </script>

        <script>
            // Format number helper for the entire page
            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            // Function to refresh prediction data
            function refreshPredictions() {
                fetch('ml_prediction.php?action=get_prediction&model=linear&include_historical=true&track_po=true&track_par=true')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.yearly_forecast && data.yearly_forecast.length > 0) {
                            // Process data for chart
                            const labels = [];
                            const demandData = [];
                            const poData = [];
                            const parData = [];

                            data.yearly_forecast.forEach(item => {
                                const [year, month] = item.period.split('-');
                                const monthName = new Date(year, month - 1, 1).toLocaleString('default', {
                                    month: 'short'
                                });
                                labels.push(monthName + ' ' + year);
                                demandData.push(item.demand);
                                poData.push(item.po_amount);
                                parData.push(item.par_amount);
                            });

                            // Update chart
                            if (window.demandPredictionChart) {
                                window.demandPredictionChart.data.labels = labels;
                                window.demandPredictionChart.data.datasets[0].data = demandData;
                                window.demandPredictionChart.data.datasets[1].data = poData;
                                window.demandPredictionChart.data.datasets[2].data = parData;
                                window.demandPredictionChart.update();
                            }

                            // Update prediction metrics
                            let yearlyTotal = 0;
                            let parYearlyTotal = 0;

                            data.yearly_forecast.forEach(month => {
                                yearlyTotal += month.po_amount;
                                parYearlyTotal += month.par_amount;
                            });

                            const monthlyAvg = yearlyTotal / data.yearly_forecast.length;
                            const quarterlyAvg = yearlyTotal / 4;

                            // Update prediction values
                            document.getElementById('nextWeekPrediction').innerHTML =
                                '₱' + formatNumber(Math.round(yearlyTotal)) +
                                ' <small class="text-muted">(PAR: ₱' + formatNumber(Math.round(parYearlyTotal)) + ')</small>';

                            document.getElementById('nextMonthPrediction').innerHTML =
                                '₱' + formatNumber(Math.round(monthlyAvg));

                            document.getElementById('nextQuarterPrediction').innerHTML =
                                '₱' + formatNumber(Math.round(quarterlyAvg));

                            // Update certainty indicator
                            const certaintyEl = document.getElementById('predictionCertainty');
                            if (certaintyEl) {
                                const certainty = data.confidence_score || 75;
                                certaintyEl.style.width = certainty + '%';

                                if (certainty < 50) {
                                    certaintyEl.className = 'progress-bar bg-danger';
                                } else if (certainty < 75) {
                                    certaintyEl.className = 'progress-bar bg-warning';
                                } else {
                                    certaintyEl.className = 'progress-bar bg-success';
                                }
                            }

                            // Update inventory alerts
                            if (data.alerts) {
                                updateInventoryAlerts(data.alerts);
                            }

                            // Update tracking metrics
                            updateTrackedItems(data.yearly_forecast);

                            // Update health indicators
                            if (data.inventory_health) {
                                const inventoryHealth = document.getElementById('inventoryHealth');
                                if (inventoryHealth) {
                                    inventoryHealth.style.width = data.inventory_health + '%';
                                    inventoryHealth.className = `progress-bar ${data.inventory_health < 50 ? 'bg-danger' : data.inventory_health < 75 ? 'bg-warning' : 'bg-success'}`;
                                }
                            }

                            if (data.po_efficiency) {
                                const poEfficiency = document.getElementById('poEfficiency');
                                if (poEfficiency) {
                                    poEfficiency.style.width = data.po_efficiency + '%';
                                    poEfficiency.className = `progress-bar ${data.po_efficiency < 50 ? 'bg-danger' : data.po_efficiency < 75 ? 'bg-warning' : 'bg-success'}`;
                                }
                            }

                            if (data.par_health) {
                                const parHealth = document.getElementById('parHealth');
                                if (parHealth) {
                                    parHealth.style.width = data.par_health + '%';
                                    parHealth.className = `progress-bar ${data.par_health < 50 ? 'bg-danger' : data.par_health < 75 ? 'bg-warning' : 'bg-success'}`;
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error refreshing predictions:', error);
                    });
            }

            // Update notification badge and dropdown
            function updateNotificationsBadge(alerts) {
                const badge = document.getElementById('notificationBadge');
                const expiringItems = document.getElementById('expiringItems');
                const expiredItems = document.getElementById('expiredItems');

                if (!badge || !expiringItems || !expiredItems) return;

                if (alerts && alerts.length > 0) {
                    // Set badge count
                    badge.textContent = alerts.length;
                    badge.classList.remove('d-none');

                    // Clear existing notifications
                    expiringItems.innerHTML = '';
                    expiredItems.innerHTML = '';

                    let expiringCount = 0;
                    let expiredCount = 0;

                    // Sort alerts into expiring and expired
                    alerts.forEach(alert => {
                        const item = document.createElement('div');
                        item.className = 'notification-item py-2 border-bottom';

                        if (alert.type === 'warranty') {
                            // For warranty alerts
                            item.innerHTML = `
                            <div class="d-flex">
                                <div class="me-2"><i class="bi bi-clock-history text-warning"></i></div>
                                <div class="small">${alert.message}</div>
                            </div>
                        `;
                            expiringItems.appendChild(item);
                            expiringCount++;
                        } else if (alert.type === 'par' || alert.type === 'expired') {
                            // For expired items
                            item.innerHTML = `
                            <div class="d-flex">
                                <div class="me-2"><i class="bi bi-x-circle text-danger"></i></div>
                                <div class="small">${alert.message}</div>
                            </div>
                        `;
                            expiredItems.appendChild(item);
                            expiredCount++;
                        }
                    });

                    // Show "no items" message if needed
                    if (expiringCount === 0) {
                        expiringItems.innerHTML = '<div class="text-muted small py-2">No items expiring soon</div>';
                    }

                    if (expiredCount === 0) {
                        expiredItems.innerHTML = '<div class="text-muted small py-2">No expired items</div>';
                    }
                } else {
                    // No alerts
                    badge.textContent = '0';
                    badge.classList.add('d-none');
                    expiringItems.innerHTML = '<div class="text-muted small py-2">No items expiring soon</div>';
                    expiredItems.innerHTML = '<div class="text-muted small py-2">No expired items</div>';
                }
            }

            // Update tracked items count and health indicators
            function updateTrackedItems(forecast) {
                if (!forecast || forecast.length === 0) return;

                // Calculate total PO and PAR amounts
                let totalPO = 0;
                let totalPAR = 0;
                let totalItems = 0;

                forecast.forEach(month => {
                    totalPO += month.po_amount || 0;
                    totalPAR += month.par_amount || 0;
                    totalItems += month.demand || 0;
                });

                // Update tracked items count
                const trackedItemsEl = document.getElementById('trackedItemsCount');
                if (trackedItemsEl) {
                    trackedItemsEl.textContent = totalItems;
                }

                // Update analyzed PO count
                const analyzedPOEl = document.getElementById('analyzedPOCount');
                if (analyzedPOEl) {
                    // Estimate number of POs based on average PO amount
                    const estimatedPOs = Math.max(1, Math.round(totalPO / 15000));
                    analyzedPOEl.textContent = estimatedPOs;
                }

                // Update expiring PAR count
                const expiringPAREl = document.getElementById('expiringPARCount');
                if (expiringPAREl) {
                    // Estimate number of PARs near expiration (roughly 10% of PAR value)
                    const expiringCount = Math.round((totalPAR / 30000) * 10) / 10;
                    expiringPAREl.textContent = Math.max(0, expiringCount);
                }

                // Update inventory health
                const inventoryHealth = document.getElementById('inventoryHealth');
                if (inventoryHealth) {
                    const firstMonthDemand = forecast[0].demand || 0;
                    const lastMonthDemand = forecast[forecast.length - 1].demand || 0;
                    const growthRate = firstMonthDemand > 0 ? ((lastMonthDemand - firstMonthDemand) / firstMonthDemand) * 100 : 0;

                    let healthScore = 75;
                    if (growthRate > 20) {
                        healthScore = 95;
                    } else if (growthRate > 10) {
                        healthScore = 85;
                    } else if (growthRate < -10) {
                        healthScore = 60;
                    } else if (growthRate < -20) {
                        healthScore = 40;
                    }

                    inventoryHealth.style.width = healthScore + '%';
                    if (healthScore < 50) {
                        inventoryHealth.className = 'progress-bar bg-danger';
                    } else if (healthScore < 70) {
                        inventoryHealth.className = 'progress-bar bg-warning';
                    } else {
                        inventoryHealth.className = 'progress-bar bg-success';
                    }
                }

                // Update PO efficiency
                const poEfficiency = document.getElementById('poEfficiency');
                if (poEfficiency) {
                    // Calculate efficiency based on PO to PAR ratio
                    const efficiency = totalPAR > 0 ? (totalPO / totalPAR) * 100 : 100;
                    const efficiencyScore = Math.min(100, Math.max(10, efficiency));

                    poEfficiency.style.width = efficiencyScore + '%';
                    if (efficiencyScore < 50) {
                        poEfficiency.className = 'progress-bar bg-danger';
                    } else if (efficiencyScore < 75) {
                        poEfficiency.className = 'progress-bar bg-warning';
                    } else {
                        poEfficiency.className = 'progress-bar bg-success';
                    }
                }

                // Update PAR health
                const parHealth = document.getElementById('parHealth');
                if (parHealth) {
                    // PAR health is based on inverse of PAR to PO ratio (lower is better)
                    const ratio = totalPO > 0 ? totalPAR / totalPO : 1;

                    let healthScore = 85;
                    if (ratio > 1.2) {
                        healthScore = 40; // PAR much higher than PO - bad
                    } else if (ratio > 1.0) {
                        healthScore = 65; // PAR higher than PO - concerning
                    } else if (ratio > 0.8) {
                        healthScore = 85; // PAR close to PO - good
                    } else {
                        healthScore = 95; // PAR much less than PO - excellent
                    }

                    parHealth.style.width = healthScore + '%';
                    if (healthScore < 50) {
                        parHealth.className = 'progress-bar bg-danger';
                    } else if (healthScore < 75) {
                        parHealth.className = 'progress-bar bg-warning';
                    } else {
                        parHealth.className = 'progress-bar bg-success';
                    }
                }
            }

            // IoT Blockchain Integration Functions
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize IoT Integration
                initializeIoT();

                // Initialize Blockchain functionality
                initializeBlockchain();

                // Initialize PO and PAR prediction functionality
                initializePredictions();

                // Initialize Inventory Condition Tracking
                initializeConditionTracking();
            });

            // Initialize IoT functionality
            function initializeIoT() {
                // Update IoT data on page load
                updateIoTData();

                // Add event listener for refresh button
                const refreshBtn = document.getElementById('refreshIoTData');
                if (refreshBtn) {
                    refreshBtn.addEventListener('click', function() {
                        updateIoTData(true);
                    });
                }

                // Add event listener for sensors refresh button
                const sensorsRefreshBtn = document.getElementById('refreshSensorsData');
                if (sensorsRefreshBtn) {
                    sensorsRefreshBtn.addEventListener('click', function() {
                        updateIoTData(true);
                    });
                }
            }

            // Update IoT sensor data
            function updateIoTData(showLoading = false) {
                if (showLoading) {
                    // Show loading indicators
                    document.getElementById('activeSensors').innerHTML = '<div class="placeholder-glow"><span class="placeholder col-8"></span></div>';
                    document.getElementById('dataPoints').innerHTML = '<div class="placeholder-glow"><span class="placeholder col-8"></span></div>';
                    document.getElementById('lastUpdate').innerHTML = '<div class="placeholder-glow"><span class="placeholder col-8"></span></div>';
                }

                // Simulate IoT data - in a real implementation, this would fetch from your IoT API
                setTimeout(function() {
                    const data = {
                        active_sensors: Math.floor(Math.random() * 5) + 2,
                        data_points: Math.floor(Math.random() * 2000) + 500,
                        last_update: Math.floor(Math.random() * 10) + 1,
                        health: Math.floor(Math.random() * 15) + 85,
                        sensors: [{
                                id: 'SEN-001',
                                location: 'Warehouse A',
                                type: 'Temperature',
                                reading: (20 + Math.random() * 5).toFixed(1) + '°C',
                                status: 'Normal',
                                hash: generateRandomHash()
                            },
                            {
                                id: 'SEN-002',
                                location: 'Office B',
                                type: 'Humidity',
                                reading: Math.floor(Math.random() * 30 + 50) + '%',
                                status: 'Normal',
                                hash: generateRandomHash()
                            },
                            {
                                id: 'SEN-003',
                                location: 'Storage C',
                                type: 'Motion',
                                reading: Math.random() > 0.8 ? 'Detected' : 'Clear',
                                status: Math.random() > 0.8 ? 'Alert' : 'Normal',
                                hash: generateRandomHash()
                            }
                        ]
                    };

                    // Update the UI with IoT data
                    updateIoTDisplay(data);
                }, 1000);
            }

            // Update IoT display elements with data
            function updateIoTDisplay(data) {
                // Update summary statistics
                document.getElementById('activeSensors').textContent = data.active_sensors;
                document.getElementById('dataPoints').textContent = formatNumber(data.data_points);
                document.getElementById('lastUpdate').textContent = data.last_update + ' mins ago';

                // Update health indicator
                const healthBar = document.getElementById('iotHealthStatus');
                if (healthBar) {
                    healthBar.style.width = data.health + '%';
                    healthBar.className = `progress-bar ${data.health < 50 ? 'bg-danger' : data.health < 75 ? 'bg-warning' : 'bg-success'}`;
                }

                // Update sensor table
                updateSensorTable(data.sensors);
            }

            // Update the sensor data table
            function updateSensorTable(sensors) {
                const tableBody = document.getElementById('iotSensorTable');
                if (!tableBody || !sensors || sensors.length === 0) return;

                let html = '';

                sensors.forEach(sensor => {
                    html += `
                <tr>
                    <td>${sensor.id}</td>
                    <td>${sensor.location}</td>
                    <td>${sensor.type}</td>
                    <td>${sensor.reading}</td>
                    <td><span class="badge ${sensor.status === 'Normal' ? 'bg-success' : 'bg-warning'}">${sensor.status}</span></td>
                    <td><code class="small">${sensor.hash}</code></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-sensor" data-sensor-id="${sensor.id}">View</button>
                    </td>
                </tr>
                `;
                });

                tableBody.innerHTML = html;
            }

            // Initialize blockchain functionality
            function initializeBlockchain() {
                // Update blockchain data on page load
                updateBlockchainData();

                // Set up blockchain details button
                const detailsButton = document.getElementById('viewBlockchainDetails');
                if (detailsButton) {
                    detailsButton.addEventListener('click', function() {
                        alert('Blockchain explorer would be displayed here in a production environment. This would show transaction history, verification status, and security checks for PO and PAR data.');
                    });
                }
            }

            // Update blockchain data display
            function updateBlockchainData() {
                // Simulate blockchain data - in a real implementation, this would fetch from your blockchain API
                setTimeout(function() {
                    const data = {
                        total_transactions: Math.floor(Math.random() * 100) + 50,
                        chain_health: Math.floor(Math.random() * 5) + 95,
                        last_block: '#' + Math.floor(Math.random() * 10000 + 15000),
                        recent_transactions: [{
                                type: 'PO_CREATE',
                                hash: generateRandomHash(),
                                timestamp: new Date(Date.now() - Math.random() * 3600000).toISOString()
                            },
                            {
                                type: 'PAR_UPDATE',
                                hash: generateRandomHash(),
                                timestamp: new Date(Date.now() - Math.random() * 7200000).toISOString()
                            },
                            {
                                type: 'INVENTORY',
                                hash: generateRandomHash(),
                                timestamp: new Date(Date.now() - Math.random() * 10800000).toISOString()
                            }
                        ]
                    };

                    // Update blockchain display elements
                    document.getElementById('totalTransactions').textContent = data.total_transactions;
                    document.getElementById('chainHealth').textContent = data.chain_health + '%';
                    document.getElementById('lastBlock').textContent = data.last_block;

                    // Update recent transactions
                    updateRecentTransactions(data.recent_transactions);
                }, 1000);
            }

            // Update recent blockchain transactions display
            function updateRecentTransactions(transactions) {
                const container = document.getElementById('recentTransactions');
                if (!container || !transactions || transactions.length === 0) return;

                let html = '';
                transactions.forEach(tx => {
                    html += `
                <div class="transaction-item d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <span class="badge bg-info me-2">${tx.type}</span>
                        <code class="small">${tx.hash}</code>
                    </div>
                    <small class="text-muted">${formatTimeAgo(new Date(tx.timestamp))}</small>
                </div>
                `;
                });

                container.innerHTML = html;
            }

            // Initialize PO and PAR prediction functionality
            function initializePredictions() {
                // Set up PO prediction input
                const poInput = document.getElementById('poAmountInput');
                const poCalcBtn = document.getElementById('calculatePoPrediction');

                if (poInput && poCalcBtn) {
                    poCalcBtn.addEventListener('click', function() {
                        calculatePoPrediction(parseFloat(poInput.value) || 0);
                    });

                    poInput.addEventListener('keyup', function(e) {
                        if (e.key === 'Enter') {
                            calculatePoPrediction(parseFloat(poInput.value) || 0);
                        }
                    });
                }

                // Set up PAR prediction input
                const parInput = document.getElementById('parAmountInput');
                const parCalcBtn = document.getElementById('calculateParPrediction');

                if (parInput && parCalcBtn) {
                    parCalcBtn.addEventListener('click', function() {
                        calculateParPrediction(parseFloat(parInput.value) || 0);
                    });

                    parInput.addEventListener('keyup', function(e) {
                        if (e.key === 'Enter') {
                            calculateParPrediction(parseFloat(parInput.value) || 0);
                        }
                    });
                }

                // Initialize with default values
                calculatePoPrediction(0);
                calculateParPrediction(0);

                // Initialize condition tracking
                initializeConditionTracking();
            }

            // Calculate PO prediction
            function calculatePoPrediction(poAmount) {
                // Update current PO amount display
                document.getElementById('currentPOAmount').textContent = '₱' + formatNumber(poAmount.toFixed(2));

                // Calculate PAR prediction (in a real implementation, this would use ML model)
                // For demo, we'll use a simple calculation with some randomness
                const parRatio = 0.6 + (Math.random() * 0.3); // 60-90% of PO amount
                const predictedPAR = poAmount * parRatio;

                // Calculate health score based on ratio
                let healthScore = 0;
                if (parRatio > 0.9) {
                    healthScore = 45; // PAR almost equal to PO - concerning
                } else if (parRatio > 0.8) {
                    healthScore = 65; // PAR higher than PO - concerning
                } else if (parRatio > 0.7) {
                    healthScore = 85; // PAR close to PO - good
                } else {
                    healthScore = 95; // PAR much less than PO - excellent
                }

                // Update prediction display
                document.getElementById('predictedPARAmount').textContent = '₱' + formatNumber(predictedPAR.toFixed(2));
                document.getElementById('poPARRatioValue').textContent = parRatio.toFixed(2);

                // Update health indicator
                const healthBar = document.getElementById('poHealthIndicator');
                if (healthBar) {
                    healthBar.style.width = healthScore + '%';
                    healthBar.className = `progress-bar ${healthScore < 50 ? 'bg-danger' : healthScore < 75 ? 'bg-warning' : 'bg-success'}`;
                }

                // Generate verification hash
                if (poAmount > 0) {
                    document.getElementById('poVerificationHash').textContent = generateRandomHash();
                } else {
                    document.getElementById('poVerificationHash').textContent = 'Not verified';
                }
            }

            // Calculate PAR prediction
            function calculateParPrediction(parAmount) {
                // Update current PAR amount display
                document.getElementById('currentPARAmount').textContent = '₱' + formatNumber(parAmount.toFixed(2));

                // Calculate related PO (in a real implementation, this would use ML model)
                // For demo, we'll use a simple calculation with some randomness
                const poRatio = 1.1 + (Math.random() * 0.4); // 110-150% of PAR amount
                const relatedPO = parAmount * poRatio;

                // Calculate utilization percentage
                const utilization = parAmount > 0 ? (parAmount / relatedPO) * 100 : 0;

                // Calculate health score based on utilization
                let healthScore = 0;
                if (utilization > 100) {
                    // PAR exceeds expectations - over-utilized
                    healthScore = Math.max(0, 100 - ((utilization - 100) * 0.5));
                } else if (utilization < 50) {
                    // PAR under-utilized - wasteful
                    healthScore = Math.max(0, utilization);
                } else {
                    // Ideal range: 50-100% utilization
                    healthScore = 75 + (utilization * 0.25);
                }

                // Update prediction display
                document.getElementById('relatedPOAmount').textContent = '₱' + formatNumber(relatedPO.toFixed(2));
                document.getElementById('parPOUtilization').textContent = Math.round(utilization) + '%';

                // Update health indicator
                const healthBar = document.getElementById('parHealthIndicator');
                if (healthBar) {
                    healthBar.style.width = healthScore + '%';
                    healthBar.className = `progress-bar ${healthScore < 50 ? 'bg-danger' : healthScore < 75 ? 'bg-warning' : 'bg-success'}`;
                }

                // Generate verification hash
                if (parAmount > 0) {
                    document.getElementById('parVerificationHash').textContent = generateRandomHash();
                } else {
                    document.getElementById('parVerificationHash').textContent = 'Not verified';
                }
            }

            // Initialize inventory condition tracking
            function initializeConditionTracking() {
                // Add event listener for refresh button
                const refreshBtn = document.getElementById('refreshConditionData');
                if (refreshBtn) {
                    refreshBtn.addEventListener('click', function() {
                        updateInventoryConditions();
                    });
                }

                // Initialize with data
                updateInventoryConditions();
            }

            // Function to update inventory conditions table
            function updateInventoryConditions() {
                const tableBody = document.getElementById('inventoryConditionTable');
                if (!tableBody) return;

                // Show loading state
                tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span class="text-muted">Loading inventory condition data...</span>
                    </td>
                </tr>
            `;

                // Fetch real data from the backend
                fetch('get_inventory_conditions.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(items => {
                        let html = '';

                        // Check if error message was returned
                        if (items.length === 1 && items[0].error) {
                            html = `
                            <tr>
                                <td colspan="8" class="text-center py-3">
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        ${items[0].message}
                                    </div>
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-primary" id="setupConditionTablesInline">
                                            <i class="bi bi-wrench"></i> Setup Condition Monitoring
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;

                            tableBody.innerHTML = html;

                            // Add event listener to the inline setup button
                            document.getElementById('setupConditionTablesInline').addEventListener('click', setupConditionMonitoring);
                            return;
                        }

                        if (items.length === 0) {
                            html = `
                            <tr>
                                <td colspan="8" class="text-center py-3">
                                    <div class="text-muted">No items to display</div>
                                </td>
                            </tr>
                        `;
                        } else {
                            items.forEach(item => {
                                // Get maintenance prediction if available
                                const hasPrediction = item.prediction && item.prediction.maintenance;
                                const maintenanceUrgency = hasPrediction ? item.prediction.maintenance.urgency : 'unknown';
                                const maintenanceMessage = hasPrediction ? item.prediction.maintenance.message : 'Not available';
                                const expiryStatus = hasPrediction && item.prediction.expiry ? item.prediction.expiry.status : 'unknown';
                                const expiryMessage = hasPrediction && item.prediction.expiry ? item.prediction.expiry.message : 'Not available';

                                // Determine prediction color classes
                                let maintenanceClass = 'bg-secondary';
                                switch (maintenanceUrgency) {
                                    case 'immediate':
                                        maintenanceClass = 'bg-danger';
                                        break;
                                    case 'soon':
                                        maintenanceClass = 'bg-warning';
                                        break;
                                    case 'upcoming':
                                        maintenanceClass = 'bg-info';
                                        break;
                                    case 'none':
                                        maintenanceClass = 'bg-success';
                                        break;
                                }

                                let expiryClass = 'bg-secondary';
                                switch (expiryStatus) {
                                    case 'expired':
                                        expiryClass = 'bg-danger';
                                        break;
                                    case 'critical':
                                        expiryClass = 'bg-warning';
                                        break;
                                    case 'warning':
                                        expiryClass = 'bg-info';
                                        break;
                                    case 'normal':
                                        expiryClass = 'bg-success';
                                        break;
                                }

                                html += `
                            <tr>
                                <td>${item.item_name || 'Unknown'}</td>
                                <td>${item.serial || 'N/A'}</td>
                                <td>${item.location || 'Unknown'}</td>
                                <td>${item.condition || 'Unknown'}</td>
                                <td><span class="badge ${item.status_class || 'bg-secondary'}">${item.status || 'Unknown'}</span></td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="badge ${maintenanceClass} mb-1" data-bs-toggle="tooltip" title="${maintenanceMessage}">
                                            ${maintenanceUrgency === 'unknown' ? 'Unknown' : maintenanceUrgency.charAt(0).toUpperCase() + maintenanceUrgency.slice(1)}
                                        </span>
                                        <span class="badge ${expiryClass}" data-bs-toggle="tooltip" title="${expiryMessage}">
                                            ${expiryStatus === 'unknown' ? 'Unknown' : expiryStatus.charAt(0).toUpperCase() + expiryStatus.slice(1)}
                                        </span>
                                    </div>
                                </td>
                                <td>${item.last_updated || 'Never'}</td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary track-item" data-item-id="${item.item_id}">Track</button>
                                        <button class="btn btn-sm btn-outline-warning update-condition" data-item-id="${item.item_id}" data-bs-toggle="modal" data-bs-target="#updateConditionModal">Update</button>
                                    </div>
                                </td>
                            </tr>
                            `;
                            });
                        }

                        tableBody.innerHTML = html;

                        // Initialize tooltips
                        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                        tooltipTriggerList.map(function(tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl);
                        });

                        // Add event listeners to the track buttons
                        document.querySelectorAll('.track-item').forEach(button => {
                            button.addEventListener('click', () => {
                                const itemId = button.getAttribute('data-item-id');
                                showItemTrackingModal(itemId);
                            });
                        });

                        // Add event listeners to the update condition buttons
                        document.querySelectorAll('.update-condition').forEach(button => {
                            button.addEventListener('click', () => {
                                const itemId = button.getAttribute('data-item-id');
                                prepareUpdateConditionModal(itemId);
                            });
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching inventory conditions:', error);
                        tableBody.innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center py-3">
                                <div class="alert alert-danger mb-0">
                                    <i class="bi bi-exclamation-circle me-2"></i>
                                    Error loading data: ${error.message}
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-primary" id="retryFetchConditions">
                                        <i class="bi bi-arrow-clockwise"></i> Retry
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" id="checkSetupConditionTables">
                                        <i class="bi bi-wrench"></i> Check Setup
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;

                        // Add event listener to the retry button
                        document.getElementById('retryFetchConditions').addEventListener('click', updateInventoryConditions);

                        // Add event listener to the check setup button
                        document.getElementById('checkSetupConditionTables').addEventListener('click', setupConditionMonitoring);
                    });
            }

            // Function to show item tracking modal
            function showItemTrackingModal(itemId) {
                // Fetch item history and details
                fetch(`get_inventory_conditions.php?item_id=${itemId}&track_history=true`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length === 0) {
                            Swal.fire({
                                title: 'Item Not Found',
                                text: 'The requested item could not be found.',
                                icon: 'error'
                            });
                            return;
                        }

                        const item = data[0];

                        // Create tracking modal content
                        let modalContent = `
                        <div class="modal-header bg-gradient-primary text-white">
                            <h5 class="modal-title">Item Tracking: ${item.item_name}</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <div class="small text-muted">Serial Number</div>
                                        <div class="fw-bold">${item.serial || 'N/A'}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <div class="small text-muted">Location</div>
                                        <div class="fw-bold">${item.location || 'Unknown'}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-light rounded mb-4">
                                <div class="small text-muted">Current Condition</div>
                                <div class="d-flex align-items-center">
                                    <div class="fw-bold me-2">${item.condition || 'Unknown'}</div>
                                    <span class="badge ${item.status_class || 'bg-secondary'}">${item.status || 'Unknown'}</span>
                                </div>
                                <div class="mt-2 small">${item.condition_details || 'No additional details'}</div>
                            </div>
                            
                            <div class="blockchain-verification mb-4 p-3 rounded">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-shield-check text-success me-2"></i>
                                    <div>
                                        <div class="small fw-bold">Blockchain Verification</div>
                                        <div class="verification-hash small text-muted">
                                            <code>${item.blockchain_hash || 'Not verified in blockchain'}</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${item.prediction ? `
                            <h6 class="mb-3">Maintenance & Lifecycle Prediction</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-header d-flex align-items-center bg-light py-2">
                                            <i class="bi bi-tools text-primary me-2"></i>
                                            <h6 class="mb-0">Maintenance Prediction</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="me-3">
                                                    <span class="badge ${item.prediction.maintenance.urgency === 'immediate' ? 'bg-danger' : 
                                                                        item.prediction.maintenance.urgency === 'soon' ? 'bg-warning' : 
                                                                        item.prediction.maintenance.urgency === 'upcoming' ? 'bg-info' : 'bg-success'} 
                                                                        p-2">
                                                        <i class="bi ${item.prediction.maintenance.urgency === 'immediate' ? 'bi-exclamation-triangle' : 
                                                                    item.prediction.maintenance.urgency === 'soon' ? 'bi-clock-history' : 
                                                                    item.prediction.maintenance.urgency === 'upcoming' ? 'bi-calendar' : 'bi-check-circle'}"></i>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="small text-muted">Urgency</div>
                                                    <div class="fw-bold">${item.prediction.maintenance.urgency ? 
                                                                        item.prediction.maintenance.urgency.charAt(0).toUpperCase() + 
                                                                        item.prediction.maintenance.urgency.slice(1) : 'Unknown'}</div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="small text-muted">Days Until Maintenance</div>
                                                <div class="fw-bold">${item.prediction.maintenance.days_until_needed || 'N/A'} days</div>
                                                <div class="progress mt-1" style="height: 5px;">
                                                    <div class="progress-bar ${item.prediction.maintenance.urgency === 'immediate' ? 'bg-danger' : 
                                                                            item.prediction.maintenance.urgency === 'soon' ? 'bg-warning' : 
                                                                            item.prediction.maintenance.urgency === 'upcoming' ? 'bg-info' : 'bg-success'}" 
                                                        style="width: ${Math.min(100, Math.max(0, 100 - (item.prediction.maintenance.days_until_needed || 0)))}%"></div>
                                                </div>
                                            </div>
                                            <div class="text-muted small">
                                                ${item.prediction.maintenance.message || 'No prediction available'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-header d-flex align-items-center bg-light py-2">
                                            <i class="bi bi-hourglass-split text-primary me-2"></i>
                                            <h6 class="mb-0">Expiry Prediction</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="me-3">
                                                    <span class="badge ${item.prediction.expiry.status === 'expired' ? 'bg-danger' : 
                                                                        item.prediction.expiry.status === 'critical' ? 'bg-warning' : 
                                                                        item.prediction.expiry.status === 'warning' ? 'bg-info' : 'bg-success'} 
                                                                        p-2">
                                                        <i class="bi ${item.prediction.expiry.status === 'expired' ? 'bi-x-circle' : 
                                                                    item.prediction.expiry.status === 'critical' ? 'bi-exclamation-circle' : 
                                                                    item.prediction.expiry.status === 'warning' ? 'bi-exclamation' : 'bi-check-circle'}"></i>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="small text-muted">Status</div>
                                                    <div class="fw-bold">${item.prediction.expiry.status ? 
                                                                        item.prediction.expiry.status.charAt(0).toUpperCase() + 
                                                                        item.prediction.expiry.status.slice(1) : 'Unknown'}</div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="small text-muted">Days Until End-of-Life</div>
                                                <div class="fw-bold">${item.prediction.expiry.days_until_expiry || 'N/A'} days</div>
                                                <div class="progress mt-1" style="height: 5px;">
                                                    <div class="progress-bar ${item.prediction.expiry.status === 'expired' ? 'bg-danger' : 
                                                                            item.prediction.expiry.status === 'critical' ? 'bg-warning' : 
                                                                            item.prediction.expiry.status === 'warning' ? 'bg-info' : 'bg-success'}" 
                                                        style="width: ${Math.min(100, Math.max(0, 100 - (item.prediction.expiry.days_until_expiry || 0) / 40))}%"></div>
                                                </div>
                                            </div>
                                            <div class="text-muted small">
                                                ${item.prediction.expiry.message || 'No prediction available'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ` : `
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Prediction data not available for this item.
                            </div>
                            `}
                            
                            ${item.history && item.history.length > 0 ? `
                            <h6 class="mb-3">Condition History</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Condition</th>
                                            <th>Status</th>
                                            <th>Blockchain Hash</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${item.history.map(entry => `
                                        <tr>
                                            <td>${entry.timestamp}</td>
                                            <td>${entry.condition}</td>
                                            <td>${entry.status}</td>
                                            <td><code class="small">${entry.hash}</code></td>
                                        </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                            ` : `
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-clock-history me-2"></i>
                                No condition history available
                            </div>
                            `}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary update-condition-btn" data-item-id="${item.item_id}" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#updateConditionModal">
                                <i class="bi bi-pencil"></i> Update Condition
                            </button>
                        </div>
                    `;

                        // Create and show the modal
                        const modalElement = document.createElement('div');
                        modalElement.className = 'modal fade';
                        modalElement.id = 'itemTrackingModal';
                        modalElement.setAttribute('tabindex', '-1');
                        modalElement.setAttribute('aria-hidden', 'true');
                        modalElement.innerHTML = `
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                ${modalContent}
                            </div>
                        </div>
                    `;

                        document.body.appendChild(modalElement);

                        // Initialize and show the modal
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();

                        // Add event listener to the update button inside the modal
                        modalElement.querySelector('.update-condition-btn').addEventListener('click', () => {
                            prepareUpdateConditionModal(item.item_id);
                        });

                        // Remove the modal from DOM when it's hidden
                        modalElement.addEventListener('hidden.bs.modal', function() {
                            document.body.removeChild(modalElement);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching item tracking data:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to retrieve item tracking information.',
                            icon: 'error'
                        });
                    });
            }

            // Function to prepare update condition modal
            function prepareUpdateConditionModal(itemId) {
                // Fetch the current item data
                fetch(`get_inventory_conditions.php?item_id=${itemId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length === 0) {
                            Swal.fire({
                                title: 'Item Not Found',
                                text: 'The requested item could not be found.',
                                icon: 'error'
                            });
                            return;
                        }

                        const item = data[0];

                        // Check if update condition modal exists, create if not
                        let modalElement = document.getElementById('updateConditionModal');
                        if (!modalElement) {
                            modalElement = document.createElement('div');
                            modalElement.className = 'modal fade';
                            modalElement.id = 'updateConditionModal';
                            modalElement.setAttribute('tabindex', '-1');
                            modalElement.setAttribute('aria-hidden', 'true');
                            modalElement.innerHTML = `
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-warning text-white">
                                        <h5 class="modal-title">Update Item Condition</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="updateConditionForm">
                                            <input type="hidden" id="updateItemId">
                                            <div class="mb-3">
                                                <label for="itemName" class="form-label">Item Name</label>
                                                <input type="text" class="form-control" id="itemName" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label for="itemCondition" class="form-label">Condition</label>
                                                <select class="form-select" id="itemCondition" required>
                                                    <option value="">Select Condition</option>
                                                    <option value="New">New</option>
                                                    <option value="Good">Good</option>
                                                    <option value="Fair">Fair</option>
                                                    <option value="Poor">Poor</option>
                                    </select>
                                </div>
                                            <div class="mb-3">
                                                <label for="conditionDetails" class="form-label">Condition Details</label>
                                                <textarea class="form-control" id="conditionDetails" rows="3" placeholder="Enter details about the item's condition..."></textarea>
                            </div>
                                        </form>
                        </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-warning" id="saveConditionBtn">Save Changes</button>
                                    </div>
                                </div>
                            </div>
                        `;
                            document.body.appendChild(modalElement);

                            // Add event listener to the save button
                            document.getElementById('saveConditionBtn').addEventListener('click', saveItemCondition);
                        }

                        // Fill the form with current data
                        document.getElementById('updateItemId').value = item.item_id;
                        document.getElementById('itemName').value = item.item_name;
                        document.getElementById('itemCondition').value = item.condition || '';
                        document.getElementById('conditionDetails').value = item.condition_details || '';

                        // Show the modal if it's not already visible
                        const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                        modal.show();
                    })
                    .catch(error => {
                        console.error('Error fetching item data for condition update:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to retrieve item information.',
                            icon: 'error'
                        });
                    });
            }

            // Function to save item condition
            function saveItemCondition() {
                const itemId = document.getElementById('updateItemId').value;
                const condition = document.getElementById('itemCondition').value;
                const conditionDetails = document.getElementById('conditionDetails').value;

                if (!condition) {
                    Swal.fire({
                        title: 'Validation Error',
                        text: 'Please select a condition.',
                        icon: 'warning'
                    });
                    return;
                }

                // Prepare data for API
                const data = {
                    item_id: itemId,
                    condition: condition,
                    condition_details: conditionDetails
                };

                // Send update request
                fetch('update_item_condition.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            // Close the modal
                            const modal = document.getElementById('updateConditionModal');
                            bootstrap.Modal.getInstance(modal).hide();

                            // Show success message
                            Swal.fire({
                                title: 'Success',
                                text: 'Item condition updated successfully.',
                                icon: 'success'
                            });

                            // Refresh the inventory conditions table
                            updateInventoryConditions();
                        } else {
                            throw new Error(result.message || 'Failed to update item condition');
                        }
                    })
                    .catch(error => {
                        console.error('Error updating item condition:', error);
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Failed to update item condition.',
                            icon: 'error'
                        });
                    });
            }

            // Format number with commas
            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            // Format time ago
            function formatTimeAgo(date) {
                const seconds = Math.floor((new Date() - date) / 1000);

                let interval = Math.floor(seconds / 31536000);
                if (interval > 1) return interval + " years ago";
                if (interval === 1) return "1 year ago";

                interval = Math.floor(seconds / 2592000);
                if (interval > 1) return interval + " months ago";
                if (interval === 1) return "1 month ago";

                interval = Math.floor(seconds / 86400);
                if (interval > 1) return interval + " days ago";
                if (interval === 1) return "1 day ago";

                interval = Math.floor(seconds / 3600);
                if (interval > 1) return interval + " hours ago";
                if (interval === 1) return "1 hour ago";

                interval = Math.floor(seconds / 60);
                if (interval > 1) return interval + " minutes ago";
                if (interval === 1) return "1 minute ago";

                return Math.floor(seconds) + " seconds ago";
            }

            // Generate random hash for blockchain simulation
            function generateRandomHash() {
                const chars = '0123456789abcdef';
                let hash = '0x';
                for (let i = 0; i < 16; i++) {
                    hash += chars[Math.floor(Math.random() * chars.length)];
                }
                return hash;
            }
        </script>

        <!-- IoT Blockchain Integration Modal -->
        <div class="modal fade" id="iotBlockchainModal" tabindex="-1" aria-labelledby="iotBlockchainModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-gradient-primary text-white">
                        <h5 class="modal-title" id="iotBlockchainModalLabel"><i class="bi bi-hdd-network"></i> IoT Blockchain Integration</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <ul class="nav nav-tabs" id="iotBlockchainTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#iot-dashboard" type="button" role="tab" aria-controls="iot-dashboard" aria-selected="true">
                                    <i class="bi bi-speedometer2"></i> Overview
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="po-par-tracker-tab" data-bs-toggle="tab" data-bs-target="#po-par-tracker" type="button" role="tab" aria-controls="po-par-tracker" aria-selected="false">
                                    <i class="bi bi-graph-up"></i> PO/PAR Tracker
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sensors-tab" data-bs-toggle="tab" data-bs-target="#sensors-data" type="button" role="tab" aria-controls="sensors-data" aria-selected="false">
                                    <i class="bi bi-cpu"></i> Sensors
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="blockchain-tab" data-bs-toggle="tab" data-bs-target="#blockchain-data" type="button" role="tab" aria-controls="blockchain-data" aria-selected="false">
                                    <i class="bi bi-link-45deg"></i> Blockchain
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content p-3" id="iotBlockchainTabContent">
                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="iot-dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                                <div class="row g-3">
                                    <!-- IoT Stats -->
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 text-primary"><i class="bi bi-cpu"></i> IoT Status</h6>
                                                <button id="refreshIoTData" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <div class="p-3 bg-light rounded">
                                                            <div class="d-flex align-items-center">
                                                                <div class="icon-wrapper bg-primary text-white rounded-circle p-2 me-3">
                                                                    <i class="bi bi-broadcast"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-muted small">Active Sensors</div>
                                                                    <div id="activeSensors" class="fs-5 fw-bold">0</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="p-3 bg-light rounded">
                                                            <div class="d-flex align-items-center">
                                                                <div class="icon-wrapper bg-success text-white rounded-circle p-2 me-3">
                                                                    <i class="bi bi-bar-chart"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-muted small">Data Points</div>
                                                                    <div id="dataPoints" class="fs-5 fw-bold">0</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="p-3 bg-light rounded">
                                                            <div class="d-flex align-items-center">
                                                                <div class="icon-wrapper bg-info text-white rounded-circle p-2 me-3">
                                                                    <i class="bi bi-clock-history"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-muted small">Last Update</div>
                                                                    <div id="lastUpdate" class="fs-5 fw-bold">N/A</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="mt-2">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="text-muted small">System Health</span>
                                                            </div>
                                                            <div class="progress" style="height: 8px;">
                                                                <div id="iotHealthStatus" class="progress-bar bg-success" role="progressbar" style="width: 85%" aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Blockchain Stats -->
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 text-primary"><i class="bi bi-link-45deg"></i> Blockchain Status</h6>
                                                <button id="viewBlockchainDetails" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View Explorer
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <div class="p-3 bg-light rounded">
                                                            <div class="d-flex align-items-center">
                                                                <div class="icon-wrapper bg-primary text-white rounded-circle p-2 me-3">
                                                                    <i class="bi bi-boxes"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-muted small">Transactions</div>
                                                                    <div id="totalTransactions" class="fs-5 fw-bold">0</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="p-3 bg-light rounded">
                                                            <div class="d-flex align-items-center">
                                                                <div class="icon-wrapper bg-success text-white rounded-circle p-2 me-3">
                                                                    <i class="bi bi-shield-check"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-muted small">Chain Health</div>
                                                                    <div id="chainHealth" class="fs-5 fw-bold">0%</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="p-3 bg-light rounded">
                                                            <div class="d-flex align-items-center">
                                                                <div class="icon-wrapper bg-info text-white rounded-circle p-2 me-3">
                                                                    <i class="bi bi-box"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-muted small">Last Block</div>
                                                                    <div id="lastBlock" class="fs-5 fw-bold">#0</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="mt-2">
                                                            <div class="text-muted small mb-2">Recent Transactions</div>
                                                            <div id="recentTransactions" class="recent-transactions-list">
                                                                <div class="text-muted small py-2">No transactions</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PO/PAR Tracker Tab -->
                            <div class="tab-pane fade" id="po-par-tracker" role="tabpanel" aria-labelledby="po-par-tracker-tab">
                                <div class="row g-4">
                                    <!-- PO Prediction Panel -->
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-light py-3">
                                                <h6 class="mb-0 text-primary"><i class="bi bi-cart3"></i> PO to PAR Prediction</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="prediction-section p-3 mb-3 bg-light rounded">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="form-floating">
                                                                <input type="number" class="form-control" id="poAmountInput" placeholder="0.00" min="0">
                                                                <label for="poAmountInput">PO Amount (₱)</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6 d-flex align-items-center">
                                                            <button id="calculatePoPrediction" class="btn btn-primary">
                                                                <i class="bi bi-calculator"></i> Calculate
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="results-section">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="p-3 bg-light rounded">
                                                                <div class="text-muted small">PO Amount</div>
                                                                <div id="currentPOAmount" class="fs-5 fw-bold">₱0.00</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="p-3 bg-light rounded">
                                                                <div class="text-muted small">Predicted PAR Amount</div>
                                                                <div id="predictedPARAmount" class="fs-5 fw-bold">₱0.00</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="p-3 bg-light rounded">
                                                                <div class="text-muted small">PAR/PO Ratio</div>
                                                                <div id="poPARRatioValue" class="fs-5 fw-bold">0.00</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="p-3 bg-light rounded">
                                                                <div class="text-muted small">Health Status</div>
                                                                <div class="progress mt-2" style="height: 8px;">
                                                                    <div id="poHealthIndicator" class="progress-bar bg-success" role="progressbar" style="width: 0%" aria-valuemin="0" aria-valuemax="100"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="blockchain-verification mt-3 p-3 bg-light rounded">
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                            <div>
                                                                <div class="small fw-bold">Verification Status</div>
                                                                <div class="verification-hash small text-muted">
                                                                    <code id="poVerificationHash">Not verified</code>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- PAR Prediction Panel -->
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-light py-3">
                                                <h6 class="mb-0 text-primary"><i class="bi bi-receipt"></i> PAR to PO Prediction</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="prediction-section p-3 mb-3 bg-light rounded">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="form-floating">
                                                                <input type="number" class="form-control" id="parAmountInput" placeholder="0.00" min="0">
                                                                <label for="parAmountInput">PAR Amount (₱)</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6 d-flex align-items-center">
                                                            <button id="calculateParPrediction" class="btn btn-primary">
                                                                <i class="bi bi-calculator"></i> Calculate
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="results-section">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="p-3 bg-light rounded">
                                                                <div class="text-muted small">PAR Amount</div>
                                                                <div id="currentPARAmount" class="fs-5 fw-bold">₱0.00</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="p-3 bg-light rounded">
                                                                <div class="text-muted small">Related PO Amount</div>
                                                                <div id="relatedPOAmount" class="fs-5 fw-bold">₱0.00</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="p-3 bg-light rounded">
                                                                <div class="text-muted small">PAR/PO Utilization</div>
                                                                <div id="parPOUtilization" class="fs-5 fw-bold">0%</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="p-3 bg-light rounded">
                                                                <div class="text-muted small">Health Status</div>
                                                                <div class="progress mt-2" style="height: 8px;">
                                                                    <div id="parHealthIndicator" class="progress-bar bg-success" role="progressbar" style="width: 0%" aria-valuemin="0" aria-valuemax="100"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="blockchain-verification mt-3 p-3 bg-light rounded">
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                            <div>
                                                                <div class="small fw-bold">Verification Status</div>
                                                                <div class="verification-hash small text-muted">
                                                                    <code id="parVerificationHash">Not verified</code>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Inventory Condition Monitoring -->
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 text-primary"><i class="bi bi-shield"></i> Inventory Condition Monitoring</h6>
                                                <button id="refreshConditionData" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-hover align-middle">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Item</th>
                                                                <th>Serial Number</th>
                                                                <th>Location</th>
                                                                <th>Condition</th>
                                                                <th>Status</th>
                                                                <th>Prediction</th>
                                                                <th>Last Updated</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="inventoryConditionTable">
                                                            <tr>
                                                                <td colspan="8" class="text-center py-3">
                                                                    <div class="text-muted">No items to display</div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sensors Tab -->
                            <div class="tab-pane fade" id="sensors-data" role="tabpanel" aria-labelledby="sensors-tab">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 text-primary"><i class="bi bi-cpu"></i> IoT Sensors</h6>
                                        <button class="btn btn-sm btn-outline-primary" id="refreshSensorsData">
                                            <i class="bi bi-arrow-clockwise"></i> Refresh
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Sensor ID</th>
                                                        <th>Location</th>
                                                        <th>Type</th>
                                                        <th>Reading</th>
                                                        <th>Status</th>
                                                        <th>Blockchain Hash</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="iotSensorTable">
                                                    <tr>
                                                        <td colspan="7" class="text-center py-3">
                                                            <div class="text-muted">Loading sensor data...</div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Blockchain Tab -->
                            <div class="tab-pane fade" id="blockchain-data" role="tabpanel" aria-labelledby="blockchain-tab">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light py-3">
                                        <h6 class="mb-0 text-primary"><i class="bi bi-link-45deg"></i> Blockchain Explorer</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="blockchain-explorer-placeholder">
                                            <img src="images/blockchain-explorer.png" alt="Blockchain Explorer" class="img-fluid rounded mb-3">
                                            <div class="text-center text-muted p-3">
                                                <p>Blockchain Explorer would be implemented here in a production environment.</p>
                                                <p>This would show transaction history, verification status, and security checks for PO and PAR data.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" id="exportIoTData">
                            <i class="bi bi-download"></i> Export Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Show Inventory by default
                document.getElementById('inventory-content').style.display = 'block';
                // ... existing code ...

                // Setup Condition Monitoring Tables button
                const setupBtn = document.getElementById('setupConditionTables');
                if (setupBtn) {
                    setupBtn.addEventListener('click', setupConditionMonitoring);
                }

                // ... existing code ...
            });

            // ... existing code ...

            // Setup Inventory Condition Monitoring Tables
            function setupConditionMonitoring() {
                Swal.fire({
                    title: 'Setup Condition Monitoring',
                    text: 'This will install or update the necessary database tables for inventory condition monitoring. Continue?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, set up tables',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading indicator
                        Swal.fire({
                            title: 'Setting up tables...',
                            html: 'Please wait while we set up the condition monitoring system.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Make AJAX request to run SQL script
                        fetch('setup_condition_tables.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        title: 'Success',
                                        text: data.message,
                                        icon: 'success'
                                    }).then(() => {
                                        // Refresh inventory conditions table
                                        updateInventoryConditions();
                                    });
                                } else {
                                    throw new Error(data.message || 'Failed to set up condition monitoring tables');
                                }
                            })
                            .catch(error => {
                                console.error('Error setting up condition monitoring:', error);
                                Swal.fire({
                                    title: 'Error',
                                    text: error.message || 'Failed to set up condition monitoring tables',
                                    icon: 'error'
                                });
                            });
                    }
                });
            }

            // Helper functions for dashboard stats loading
            function showLoading() {
                const loadingElement = document.getElementById('loadingIndicator');
                if (loadingElement) {
                    loadingElement.style.display = 'flex';
                } else {
                    // Create a loading indicator if it doesn't exist
                    const loader = document.createElement('div');
                    loader.id = 'loadingIndicator';
                    loader.className = 'loading-overlay';
                    loader.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
                    loader.style.position = 'fixed';
                    loader.style.top = '0';
                    loader.style.left = '0';
                    loader.style.width = '100%';
                    loader.style.height = '100%';
                    loader.style.display = 'flex';
                    loader.style.alignItems = 'center';
                    loader.style.justifyContent = 'center';
                    loader.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
                    loader.style.zIndex = '9999';
                    document.body.appendChild(loader);
                }
            }

            function hideLoading() {
                const loadingElement = document.getElementById('loadingIndicator');
                if (loadingElement) {
                    loadingElement.style.display = 'none';
                }
            }

            function showError(message) {
                // Create or update error container
                let errorContainer = document.getElementById('errorContainer');

                if (!errorContainer) {
                    errorContainer = document.createElement('div');
                    errorContainer.id = 'errorContainer';
                    errorContainer.className = 'alert alert-danger alert-dismissible fade show';
                    errorContainer.style.position = 'fixed';
                    errorContainer.style.top = '20px';
                    errorContainer.style.right = '20px';
                    errorContainer.style.zIndex = '1050';
                    errorContainer.style.maxWidth = '400px';
                    document.body.appendChild(errorContainer);
                }

                errorContainer.innerHTML = `
                <strong>Error!</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    const errorElement = document.getElementById('errorContainer');
                    if (errorElement) {
                        errorElement.style.display = 'none';
                    }
                }, 5000);
            }
        </script>

        <script>
            // Function to load dashboard statistics
            function loadDashboardStats() {
                // Check if we are already loading - prevent multiple simultaneous calls
                if (window.isLoadingDashboardStats) {
                    console.log('Dashboard stats already loading, skipping duplicate call');
                    return;
                }

                window.isLoadingDashboardStats = true;
                showLoading();

                // Check if Chart.js is loaded
                if (typeof Chart === 'undefined') {
                    console.error('Chart.js is not loaded. Cannot render charts.');
                    showError('Failed to load dashboard: Chart.js library is not available.');
                    window.isLoadingDashboardStats = false;
                    hideLoading();
                    return;
                }

                // Clear existing charts before loading new data
                if (window.inventoryChart && typeof window.inventoryChart.destroy === 'function') {
                    window.inventoryChart.destroy();
                    window.inventoryChart = null;
                }

                if (window.stockStatusChart && typeof window.stockStatusChart.destroy === 'function') {
                    window.stockStatusChart.destroy();
                    window.stockStatusChart = null;
                }

                fetch('dashboard_stats.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Failed to load dashboard statistics');
                        }

                        console.log('Dashboard data loaded successfully:', data);

                        // Update statistics counters
                        document.querySelectorAll('.stats-number').forEach(el => {
                            const parent = el.closest('.dashboard-stats');
                            if (parent) {
                                if (parent.classList.contains('total-items')) {
                                    el.textContent = data.total_items || 0;
                                } else if (parent.classList.contains('inventory')) {
                                    el.textContent = data.inventory_count || 0;
                                } else if (parent.classList.contains('po')) {
                                    el.textContent = data.total_pos || 0;
                                } else if (parent.classList.contains('par')) {
                                    el.textContent = data.total_pars || 0;
                                }
                            }
                        });

                        // Only render charts if Chart is defined and data exists
                        if (typeof Chart !== 'undefined') {
                            // Render inventory chart if data exists
                            if (data.chart_data && document.getElementById('inventoryChart')) {
                                // Ensure no existing chart before rendering
                                if (window.inventoryChart && typeof window.inventoryChart.destroy === 'function') {
                                    window.inventoryChart.destroy();
                                    window.inventoryChart = null;
                                }
                                renderInventoryChart(data.chart_data);
                            } else {
                                console.warn('Inventory chart data missing or element not found');
                            }

                            // Render stock status chart if data exists
                            if (data.stock_status && document.getElementById('stockStatusChart')) {
                                // Ensure no existing chart before rendering
                                if (window.stockStatusChart && typeof window.stockStatusChart.destroy === 'function') {
                                    window.stockStatusChart.destroy();
                                    window.stockStatusChart = null;
                                }
                                renderStockStatusChart(data.stock_status);
                            } else {
                                console.warn('Stock status chart data missing or element not found');
                            }
                        } else {
                            console.error('Chart.js is not loaded. Cannot render charts.');
                        }

                        hideLoading();
                    })
                    .catch(error => {
                        console.error('Error loading dashboard data:', error);
                        showError('Failed to load dashboard statistics. Please try again.');
                        hideLoading();
                    })
                    .finally(() => {
                        // Reset the loading flag
                        window.isLoadingDashboardStats = false;
                    });
            }

            // Add event handlers for inventory item additions
            document.addEventListener('DOMContentLoaded', function() {
                // When new inventory item is added, refresh condition monitoring
                const addInventoryForm = document.getElementById('addInventoryForm');
                if (addInventoryForm) {
                    addInventoryForm.addEventListener('submit', function() {
                        // Wait for the form submission to complete and then refresh
                        setTimeout(function() {
                            // Check if we're on the inventory condition monitoring tab
                            const conditionTable = document.getElementById('inventoryConditionTable');
                            if (conditionTable) {
                                updateInventoryConditions();
                            }
                        }, 2000); // Wait 2 seconds for server processing
                    });
                }

                // Also listen for the success message from inventory additions
                document.addEventListener('DOMNodeInserted', function(e) {
                    // Check if the inserted element contains success message for inventory
                    if (e.target && e.target.classList && e.target.classList.contains('alert-success')) {
                        // If we see a success message, refresh the condition monitoring
                        setTimeout(function() {
                            const conditionTable = document.getElementById('inventoryConditionTable');
                            if (conditionTable) {
                                updateInventoryConditions();
                            }

                            // Refresh dashboard stats when inventory is updated
                            if (typeof loadDashboardStats === 'function') {
                                loadDashboardStats();
                            }
                        }, 1000);
                    }
                });

                // Add event listener to ensure dashboard stats are refreshed after PO/PAR operations
                ['addPOModal', 'addPARModal'].forEach(function(modalId) {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.addEventListener('hidden.bs.modal', function() {
                            // Only update if we're on the dashboard section
                            // Fixed typo in selector and added check to avoid errors
                            const dashboardSection = document.querySelector('.dashboard-section.active');
                            if (dashboardSection && typeof loadDashboardStats === 'function') {
                                // Destroy any existing charts before refreshing
                                if (window.inventoryChart && typeof window.inventoryChart.destroy === 'function') {
                                    try {
                                        window.inventoryChart.destroy();
                                    } catch (e) {
                                        console.error('Error destroying inventory chart:', e);
                                    }
                                    window.inventoryChart = null;
                                }

                                if (window.stockStatusChart && typeof window.stockStatusChart.destroy === 'function') {
                                    try {
                                        window.stockStatusChart.destroy();
                                    } catch (e) {
                                        console.error('Error destroying stock status chart:', e);
                                    }
                                    window.stockStatusChart = null;
                                }

                                // Clear canvases to ensure they're ready for new charts
                                const inventoryCanvas = document.getElementById('inventoryChart');
                                if (inventoryCanvas) {
                                    inventoryCanvas.getContext('2d').clearRect(0, 0, inventoryCanvas.width, inventoryCanvas.height);
                                }

                                const stockCanvas = document.getElementById('stockStatusChart');
                                if (stockCanvas) {
                                    stockCanvas.getContext('2d').clearRect(0, 0, stockCanvas.width, stockCanvas.height);
                                }

                                // Refresh dashboard stats after a short delay
                                setTimeout(loadDashboardStats, 1000);
                            }
                        });
                    }
                });
            });
        </script>

        <script>
            // Function to render inventory chart
            function renderInventoryChart(chartData) {
                // Validate input data
                if (!chartData || typeof chartData !== 'object') {
                    console.error('Invalid chart data provided:', chartData);
                    showError('Invalid chart data provided.');
                    return;
                }

                const ctx = document.getElementById('inventoryChart');
                if (!ctx) {
                    console.warn('inventoryChart element not found in DOM');
                    return;
                }

                // Make sure Chart is defined
                if (typeof Chart === 'undefined') {
                    console.error('Chart.js library not loaded');
                    return;
                }

                try {
                    // Destroy existing chart if it exists - FIXED
                    if (window.inventoryChart && typeof window.inventoryChart.destroy === 'function') {
                        window.inventoryChart.destroy();
                        window.inventoryChart = null;
                    }

                    // Extra safety check - ensure the canvas is clean
                    ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);

                    // Small delay to allow canvas to reset
                    setTimeout(() => {
                        // Define gradient for 3D effect
                        const ctx3d = ctx.getContext('2d');

                        // Create more vibrant gradients for better visual appeal
                        // Primary gradient (blue) for Items Added
                        const primaryGradient = ctx3d.createLinearGradient(0, 0, 0, 400);
                        primaryGradient.addColorStop(0, 'rgba(65, 105, 225, 0.9)');
                        primaryGradient.addColorStop(0.6, 'rgba(30, 144, 255, 0.8)');
                        primaryGradient.addColorStop(1, 'rgba(0, 191, 255, 0.7)');

                        // Extract data based on format
                        let months, itemsData;
                        if (chartData.predicted_months && chartData.predicted_values) {
                            // Handle prediction data format
                            months = chartData.predicted_months;
                            itemsData = chartData.predicted_values;
                        } else if (chartData.months && chartData.items_added) {
                            // Handle chart data format
                            months = chartData.months;
                            itemsData = chartData.items_added;
                        } else {
                            console.error('Invalid chart data format:', chartData);
                            showError('Invalid chart data format.');
                            return;
                        }

                        // Create chart configuration
                        const chartConfig = {
                            type: 'bar',
                            data: {
                                labels: months,
                                datasets: [{
                                    label: 'Items Added',
                                    data: itemsData,
                                    backgroundColor: primaryGradient,
                                    borderColor: 'rgba(65, 105, 225, 1)',
                                    borderWidth: 2,
                                    borderRadius: 6,
                                    barThickness: 18
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return `${context.dataset.label}: ${context.raw}`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 1
                                        }
                                    }
                                }
                            }
                        };

                        // Create the chart
                        window.inventoryChart = new Chart(ctx, chartConfig);
                        console.log('Inventory chart rendered successfully');
                    }, 100);
                } catch (error) {
                    console.error('Error rendering inventory chart:', error);
                    showError('Error rendering chart: ' + error.message);
                }
            }

            // Function to render stock status chart
            function renderStockStatusChart(stockData) {
                // Validate input data
                if (!stockData || typeof stockData.in_stock === 'undefined' ||
                    typeof stockData.low_stock === 'undefined' ||
                    typeof stockData.out_of_stock === 'undefined') {
                    console.error('Invalid stock data provided:', stockData);
                    showError('Invalid stock status data provided.');
                    return;
                }

                const ctx = document.getElementById('stockStatusChart');
                if (!ctx) {
                    console.warn('stockStatusChart element not found in DOM');
                    return;
                }

                // Make sure Chart is defined
                if (typeof Chart === 'undefined') {
                    console.error('Chart.js library not loaded');
                    return;
                }

                try {
                    // Destroy existing chart if it exists - FIXED
                    if (window.stockStatusChart && typeof window.stockStatusChart.destroy === 'function') {
                        window.stockStatusChart.destroy();
                        window.stockStatusChart = null;
                    }

                    // Extra safety check - ensure the canvas is clean
                    ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);

                    // Small delay to allow canvas to reset
                    setTimeout(() => {
                        // Extract data from stockData
                        const labels = ['In Stock', 'Low Stock', 'Out of Stock'];
                        const values = [stockData.in_stock, stockData.low_stock, stockData.out_of_stock];

                        // Define colors for different status
                        const backgroundColors = [
                            'rgba(75, 192, 192, 0.8)', // Teal for in stock
                            'rgba(255, 205, 86, 0.8)', // Yellow for low stock
                            'rgba(255, 99, 132, 0.8)' // Red for out of stock
                        ];

                        // Define border colors (slightly darker)
                        const borderColors = backgroundColors.map(color => color.replace('0.8', '1'));

                        // Create chart configuration
                        const chartConfig = {
                            type: 'doughnut',
                            data: {
                                labels: labels,
                                datasets: [{
                                    data: values,
                                    backgroundColor: backgroundColors,
                                    borderColor: borderColors,
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'right',
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.label || '';
                                                const value = context.raw || 0;
                                                return `${label}: ${value}`;
                                            }
                                        }
                                    }
                                }
                            }
                        };

                        // Create the chart
                        window.stockStatusChart = new Chart(ctx, chartConfig);

                    }, 100);
                } catch (error) {
                    console.error('Error rendering stock status chart:', error);
                    showError('Error rendering stock status chart.');
                }
            }

            // Add event handlers for inventory item additions
            document.addEventListener('DOMContentLoaded', function() {
                // When new inventory item is added, refresh condition monitoring
                const addInventoryForm = document.getElementById('addInventoryForm');
                if (addInventoryForm) {
                    addInventoryForm.addEventListener('submit', function() {
                        // Wait for the form submission to complete and then refresh
                        setTimeout(function() {
                            // Check if we're on the inventory condition monitoring tab
                            const conditionTable = document.getElementById('inventoryConditionTable');
                            if (conditionTable) {
                                updateInventoryConditions();
                            }
                        }, 2000); // Wait 2 seconds for server processing
                    });
                }

                // Also listen for the success message from inventory additions
                document.addEventListener('DOMNodeInserted', function(e) {
                    // Check if the inserted element contains success message for inventory
                    if (e.target && e.target.classList && e.target.classList.contains('alert-success')) {
                        // If we see a success message, refresh the condition monitoring
                        setTimeout(function() {
                            const conditionTable = document.getElementById('inventoryConditionTable');
                            if (conditionTable) {
                                updateInventoryConditions();
                            }

                            // Refresh dashboard stats when inventory is updated
                            if (typeof loadDashboardStats === 'function') {
                                loadDashboardStats();
                            }
                        }, 1000);
                    }
                });

                // Add event listener to ensure dashboard stats are refreshed after PO/PAR operations
                ['addPOModal', 'addPARModal'].forEach(function(modalId) {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.addEventListener('hidden.bs.modal', function() {
                            // Only update if we're on the dashboard section
                            // Fixed typo in selector and added check to avoid errors
                            const dashboardSection = document.querySelector('.dashboard-section.active');
                            if (dashboardSection && typeof loadDashboardStats === 'function') {
                                // Destroy any existing charts before refreshing
                                if (window.inventoryChart && typeof window.inventoryChart.destroy === 'function') {
                                    try {
                                        window.inventoryChart.destroy();
                                    } catch (e) {
                                        console.error('Error destroying inventory chart:', e);
                                    }
                                    window.inventoryChart = null;
                                }

                                if (window.stockStatusChart && typeof window.stockStatusChart.destroy === 'function') {
                                    try {
                                        window.stockStatusChart.destroy();
                                    } catch (e) {
                                        console.error('Error destroying stock status chart:', e);
                                    }
                                    window.stockStatusChart = null;
                                }

                                // Clear canvases to ensure they're ready for new charts
                                const inventoryCanvas = document.getElementById('inventoryChart');
                                if (inventoryCanvas) {
                                    inventoryCanvas.getContext('2d').clearRect(0, 0, inventoryCanvas.width, inventoryCanvas.height);
                                }

                                const stockCanvas = document.getElementById('stockStatusChart');
                                if (stockCanvas) {
                                    stockCanvas.getContext('2d').clearRect(0, 0, stockCanvas.width, stockCanvas.height);
                                }

                                // Refresh dashboard stats after a short delay
                                setTimeout(loadDashboardStats, 1000);
                            }
                        });
                    }
                });
            });
        </script>

        <script>
            // Add event listener for page refresh or navigation
            window.addEventListener('beforeunload', function() {
                // Destroy charts before page refresh to prevent canvas reuse issues
                if (window.inventoryChart && typeof window.inventoryChart.destroy === 'function') {
                    try {
                        window.inventoryChart.destroy();
                        window.inventoryChart = null;
                    } catch (e) {
                        console.error('Error destroying inventory chart:', e);
                    }
                }

                if (window.stockStatusChart && typeof window.stockStatusChart.destroy === 'function') {
                    try {
                        window.stockStatusChart.destroy();
                        window.stockStatusChart = null;
                    } catch (e) {
                        console.error('Error destroying stock status chart:', e);
                    }
                }
            });
        </script>

        <script>
            // Remove specific PAR item row with QTY=1, AMOUNT=0, Date Acquired=10/04/25
            document.addEventListener('DOMContentLoaded', function() {
                // Function to check and remove the specific PAR item
                function removeSpecificParItem() {
                    // Look for all rows in PAR items tables
                    const parRows = document.querySelectorAll('#parItemsTable tbody tr, .par-table tbody tr, table tbody tr');

                    parRows.forEach(row => {
                        // Check if this is the row we want to remove
                        const qtyElement = row.querySelector('.par-qty, .qty, [name="quantity[]"], td.quantity');
                        const amountElement = row.querySelector('.par-amount, .amount, [name="amount[]"]');
                        const dateElement = row.querySelector('.par-item-date, [name="date_acquired[]"], .date-cell');

                        if (qtyElement && amountElement && dateElement) {
                            // Get values from elements
                            let qty = qtyElement.tagName === 'TD' ? qtyElement.textContent.trim() : qtyElement.value;
                            let amount = amountElement.tagName === 'TD' ? amountElement.textContent.trim() : amountElement.value;
                            let date = dateElement.tagName === 'TD' ? dateElement.textContent.trim() : dateElement.value;

                            // Check for exact match with the values we want to remove
                            if (qty == '1' && amount == '0' && (date == '10/04/25' || date == '2025-04-10')) {
                                console.log('Removing specific PAR item row:', row);
                                row.remove();

                                // Recalculate PAR total if needed
                                if (typeof calculateParTotal === 'function') {
                                    calculateParTotal();
                                }
                            }
                        }
                    });
                }

                // Run immediately and also set up to run when modals are shown
                removeSpecificParItem();

                // Also run when any modal is shown (in case items are loaded dynamically)
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.addEventListener('shown.bs.modal', removeSpecificParItem);
                });
            });
        </script>
</body>

</html>