// Add CSS styles for the PO modal
document.addEventListener('DOMContentLoaded', function() {
    // Add custom CSS for the PO modal
    const style = document.createElement('style');
    style.textContent = `
        .po-modal-container {
            z-index: 1060 !important;
        }
        
        .po-modal-popup {
            max-width: 90% !important;
            margin: 1.75rem auto;
        }
        
        .po-modal-content {
            max-height: 80vh;
            overflow-y: auto;
        }
        
        @media (min-width: 992px) {
            .po-modal-popup {
                max-width: 800px !important;
            }
        }
        
        .po-modal-content table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .po-modal-content th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .po-modal-content th, 
        .po-modal-content td {
            padding: 0.5rem;
            border: 1px solid #dee2e6;
        }
        
        .po-modal-content tr:hover {
            background-color: #f5f5f5;
        }
    `;
    document.head.appendChild(style);
});

document.addEventListener('DOMContentLoaded', function() {
    const savePoBtn = document.getElementById('savePoBtn');
    if (savePoBtn) {
        savePoBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Disable button to prevent multiple submissions
            this.disabled = true;
            const originalBtnText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

            // Get the form
            const poForm = document.getElementById('poForm');
            if (!poForm) {
                console.error('PO Form not found');
                this.disabled = false;
                this.innerHTML = originalBtnText;
                return;
            }

            // Get all items from the table
            const itemRows = document.querySelectorAll('#poItemsTable tbody tr:not(.d-none)');
            const items = [];
            
            itemRows.forEach(row => {
                const itemName = row.querySelector('.item-name')?.value;
                const unit = row.querySelector('.item-unit')?.value;
                const description = row.querySelector('.item-description')?.value;
                const qty = parseFloat(row.querySelector('.qty')?.value) || 0;
                const unitCost = parseFloat(row.querySelector('.unit-cost')?.value) || 0;
                const amount = parseFloat(row.querySelector('.amount')?.value) || 0;

                if (itemName && qty > 0 && unitCost > 0) {
                    items.push({
                        item_description: itemName,
                        unit: unit,
                        description: description,
                        quantity: qty,
                        unit_cost: unitCost,
                        amount: amount
                    });
                }
            });

            // Collect form data
            const poData = {
                po_no: document.getElementById('poNo')?.value,
                ref_no: document.getElementById('refNo')?.value,
                supplier_name: document.getElementById('supplier')?.value,
                supplier_address: document.getElementById('supplierAddress')?.value,
                email: document.getElementById('emailAddress')?.value,
                tel: document.getElementById('telephoneNo')?.value,
                po_date: document.getElementById('poDate')?.value,
                mode_of_procurement: document.getElementById('modeOfProcurement')?.value,
                pr_no: document.getElementById('prNo')?.value,
                pr_date: document.getElementById('prDate')?.value,
                place_of_delivery: document.getElementById('placeOfDelivery')?.value,
                delivery_date: document.getElementById('deliveryDate')?.value,
                payment_term: document.getElementById('paymentTerm')?.value,
                delivery_term: document.getElementById('deliveryTerm')?.value,
                obligation_request_no: document.getElementById('obligationRequestNo')?.value,
                obligation_amount: parseFloat(document.getElementById('obligationAmount')?.value) || 0,
                total_amount: parseFloat(document.getElementById('totalAmount')?.value.replace(/[^0-9.-]+/g, '')) || 0,
                items: items
            };

            // Validate required fields
            if (!poData.po_no || !poData.supplier_name || !poData.po_date || items.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Required Fields Missing',
                    text: 'Please fill in required fields (PO No, Supplier, Date) and add at least one item'
                });
                this.disabled = false;
                this.innerHTML = originalBtnText;
                return;
            }

            // Check if we're in edit mode
            const isEdit = this.dataset.editMode === 'true';
            const poId = this.dataset.poId;

            // Add PO ID to data if in edit mode
            if (isEdit && poId) {
                poData.id = poId;
                poData.po_id = poId;
            }

            // Determine which endpoint to use
            const url = isEdit ? 'update_po.php' : 'add_po.php';

            // Send the data to the server
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(poData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Close the modal
                    const modalElement = document.getElementById('addPOModal');
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }

                    // Reset form
                    poForm.reset();
                    document.querySelector('#poItemsTable tbody').innerHTML = '';

                    // Reset button state
                    this.textContent = 'Save PO';
                    this.dataset.editMode = 'false';
                    delete this.dataset.poId;

                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `PO has been ${isEdit ? 'updated' : 'saved'} successfully.`,
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // Reload PO data if the function exists
                    if (typeof loadPOData === 'function') {
                        loadPOData();
                    } else {
                        // Fallback - reload the page
                        setTimeout(() => window.location.reload(), 500);
                    }
                } else {
                    throw new Error(data.message || 'Failed to save PO');
                }
            })
            .catch(error => {
                console.error('Error saving PO:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to save PO: ' + error.message
                });
            })
            .finally(() => {
                // Re-enable button
                this.disabled = false;
                this.innerHTML = originalBtnText;
            });
        });
    }

// ... existing code ...


    // Helper functions for calculating totals
    window.calculatePOTotal = function () {
        const rows = document.querySelectorAll('#poItemsTable tbody tr:not(.d-none)');
        let total = 0;

        rows.forEach(row => {
            const amountElement = row.querySelector('.amount');
            if (amountElement && amountElement.value) {
                total += parseFloat(amountElement.value.replace(/[^\d.-]/g, '')) || 0;
            }
        });

        // Update total field
        const totalField = document.getElementById('totalAmount');
        if (totalField) {
            totalField.value = total.toFixed(2);
        }

        return total;
    };

    window.calculatePARTotal = function () {
        const rows = document.querySelectorAll('#parItemsTable tbody tr:not(.d-none)');
        let total = 0;

        rows.forEach(row => {
            const amountElement = row.querySelector('.amount');
            if (amountElement && amountElement.value) {
                total += parseFloat(amountElement.value.replace(/[^\d.-]/g, '')) || 0;
            }
        });

        // Update total field
        const totalField = document.getElementById('parTotalAmount');
        if (totalField) {
            totalField.value = total.toFixed(2);
        }

        return total;
    };

    // Calculate amount in a row
    window.updatePORowAmount = function (row) {
        const quantity = parseFloat(row.querySelector('.quantity')?.value) || 0;
        const unitCost = parseFloat(row.querySelector('.unit-cost')?.value) || 0;
        const amountField = row.querySelector('.amount');

        if (amountField) {
            const amount = quantity * unitCost;
            amountField.value = amount.toFixed(2);
        }

        // Update total
        window.calculatePOTotal();
    };

    window.updatePARRowAmount = function (row) {
        const quantity = parseFloat(row.querySelector('.quantity')?.value) || 0;
        const unitCost = parseFloat(row.querySelector('.unit-cost')?.value) || 0;
        const amountField = row.querySelector('.amount');

        if (amountField) {
            const amount = quantity * unitCost;
            amountField.value = amount.toFixed(2);
        }

        // Update total
        window.calculatePARTotal();
    };

    // Add event listeners to quantity and unit cost inputs
    document.addEventListener('input', function (e) {
        if (e.target.matches('.quantity') || e.target.matches('.unit-cost')) {
            const row = e.target.closest('tr');
            if (row) {
                if (row.closest('#poItemsTable')) {
                    window.updatePORowAmount(row);
                } else if (row.closest('#parItemsTable')) {
                    window.updatePARRowAmount(row);
                }
            }
        }
    });

    // Add Row button handlers for PO and PAR tables
    document.addEventListener('click', function (e) {
        // Add PO Item Row
        if (e.target.matches('#addPoItemBtn')) {
            const tbody = document.querySelector('#poItemsTable tbody');
            if (tbody) {
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                        <td><input type="text" class="form-control item-name" required></td>
                        <td><input type="text" class="form-control unit"></td>
                        <td><input type="number" class="form-control quantity" min="1" value="1"></td>
                        <td><input type="number" class="form-control unit-cost" min="0" step="0.01" value="0.00"></td>
                        <td><input type="number" class="form-control amount" readonly value="0.00"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                    `;
                tbody.appendChild(newRow);

                // Calculate the amount for the new row
                window.updatePORowAmount(newRow);
            }
        }

        // Add PAR Item Row
        if (e.target.matches('#addParItemBtn')) {
            const tbody = document.querySelector('#parItemsTable tbody');
            if (tbody) {
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                        <td><input type="text" class="form-control item-description" required></td>
                        <td><input type="text" class="form-control unit"></td>
                        <td><input type="number" class="form-control quantity" min="1" value="1"></td>
                        <td><input type="text" class="form-control property-number"></td>
                        <td><input type="date" class="form-control date-acquired"></td>
                        <td><input type="number" class="form-control unit-cost" min="0" step="0.01" value="0.00"></td>
                        <td><input type="number" class="form-control amount" readonly value="0.00"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                    `;
                tbody.appendChild(newRow);

                // Calculate the amount for the new row
                window.updatePARRowAmount(newRow);
            }
        }

        // Remove Row button
        if (e.target.matches('.remove-row')) {
            const row = e.target.closest('tr');
            if (row) {
                row.remove();

                // Update totals
                if (row.closest('#poItemsTable')) {
                    window.calculatePOTotal();
                } else if (row.closest('#parItemsTable')) {
                    window.calculatePARTotal();
                }
            }
        }
    });
});
document.addEventListener('DOMContentLoaded', function () {
    const myButton = document.getElementById("myButton");
    if (myButton) {
        myButton.addEventListener("click", function () {
            alert("Clicked!");
        });
    } else {
        console.log("myButton element not found");
    }

    // Log that the script was loaded successfully
    console.log("style.js loaded and executed successfully at " + new Date().toISOString());
});

function showError(message) {
    console.log("Primary showError function called with message:", message);
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
        const alert = bootstrap.Alert.getOrCreateInstance(errorContainer);
        if (alert) {
            alert.close();
        }
    }, 5000);
}

// Implement the inventory search functionality
document.addEventListener('DOMContentLoaded', function () {
    // Add search functionality
    const inventorySearchInput = document.getElementById('inventorySearchInput');
    if (inventorySearchInput) {
        console.log('Inventory search input found, attaching event listener');

        // Use debounce for search to avoid too many requests
        let searchTimeout;
        inventorySearchInput.addEventListener('input', function (e) {
            // Clear any existing timeout
            clearTimeout(searchTimeout);

            // Set loading state
            const searchIcon = this.parentElement.querySelector('.bi-search');
            if (searchIcon) {
                searchIcon.className = 'bi bi-hourglass-split position-absolute top-50 start-0 translate-middle-y ms-2';
            }

            // Debounce the search
            searchTimeout = setTimeout(() => {
                const searchTerm = e.target.value.trim();
                console.log('Searching for:', searchTerm);

                if (searchTerm === '') {
                    console.log('Empty search term, loading all inventory data');
                    loadInventoryData();
                } else {
                    // Show loading indicator
                    showLoading();

                    // Safe encode the search term to handle JavaScript code
                    const encodedSearchTerm = encodeURIComponent(searchTerm);

                    // Perform the search
                    fetch(`get_inventory.php?search=${encodedSearchTerm}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(response => {
                            let data = Array.isArray(response) ? response : (response.data || []);

                            console.log('Search results received:', data.length, 'items');

                            window.filteredData = data;

                            updateInventoryTable(data);
                            checkWarrantyStatus();
                            updateConditionStatus();

                            // Reset search icon
                            if (searchIcon) {
                                searchIcon.className = 'bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2';
                            }

                            // Hide loading indicator
                            hideLoading();
                        })
                        .catch(error => {
                            console.error('Error searching inventory:', error);

                            // Show error message
                            Swal.fire({
                                icon: 'error',
                                title: 'Search Error',
                                text: 'Failed to search inventory. ' + error.message,
                                timer: 3000
                            });

                            // Reset search icon
                            if (searchIcon) {
                                searchIcon.className = 'bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2';
                            }

                            // Hide loading indicator
                            hideLoading();
                        });
                }
            }, 300); // 300ms delay for debounce
        });
    } else {
        console.warn('Inventory search input not found');
    }
});

// Add filter functionality
const conditionFilter = document.getElementById('conditionFilter');
const locationFilter = document.getElementById('locationFilter');
const purchaseDateFilter = document.getElementById('purchaseDateFilter');

function applyFilters() {
    // Get filter values
    const condition = conditionFilter ? conditionFilter.value : '';
    const location = locationFilter ? locationFilter.value : '';
    const dateFilter = purchaseDateFilter ? purchaseDateFilter.value : '';

    // Get search term
    const searchTerm = inventorySearchInput ? inventorySearchInput.value.trim() : '';

    // Show loading indicator
    showLoading();

    // Build query parameters
    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (condition) params.append('condition', condition);
    if (location) params.append('location', location);
    if (dateFilter) params.append('date_filter', dateFilter);

    console.log('Applying filters:', {condition, location, dateFilter, searchTerm});

    // Fetch filtered data with correct path
    fetch(`get_inventory.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(response => {
            // Handle both data formats (array or {success, data})
            let data = Array.isArray(response) ? response : (response.data || []);

            console.log('Filter results received:', data.length, 'items');

            // Store the data globally so it can be used by other functions
            window.filteredData = data;

            // Update table with the filtered data
            updateInventoryTable(data);

            // Update related components
            checkWarrantyStatus();
            updateConditionStatus();

            // Hide loading indicator
            hideLoading();
        })
        .catch(error => {
            console.error('Error applying filters:', error);
            // Show error message
            showError('Failed to apply filters: ' + error.message);
            // Hide loading indicator
            hideLoading();
        });
}

// Attach change event listeners to filters
if (conditionFilter) conditionFilter.addEventListener('change', applyFilters);
if (locationFilter) locationFilter.addEventListener('change', applyFilters);
if (purchaseDateFilter) purchaseDateFilter.addEventListener('change', applyFilters);

// Add export and print functionality
document.getElementById('exportBtn')?.addEventListener('click', function () {
    window.location.href = 'export_inventory.php';
});

document.getElementById('printBtn')?.addEventListener('click', function () {
    const printHeader = document.querySelector('.print-header');
    printHeader.classList.remove('d-none');
    document.getElementById('printDate').textContent = new Date().toLocaleDateString();

    // Print the document
    window.print();

    // Hide the header again after printing
    setTimeout(() => {
        printHeader.classList.add('d-none');
    }, 100);
});

// Attach click event to the warranty notification button
document.getElementById('warrantyNotificationBtn')?.addEventListener('click', function () {
    Swal.fire({
        title: 'Warranty Notifications',
        html: document.getElementById('warrantyBills').innerHTML.trim() || '<p>No notifications</p>',
        icon: 'info'
    });
});

// Replace the existing checkWarrantyStatus function with this improved version
function checkWarrantyStatus() {
    console.log('Checking warranty status for inventory items');

    // Get all inventory items from both table and modal
    const inventoryItems = document.querySelectorAll('.inventory-item, .item-row, #inventoryModal .modal-body tr');

    if (!inventoryItems || inventoryItems.length === 0) {
        console.log('No inventory items found to check warranty');
        return;
    }

    console.log(`Found ${inventoryItems.length} inventory items to check warranty status`);

    // Process each item
    inventoryItems.forEach(item => {
        try {
            // Get all possible status elements (both in table and modal)
            const statusElements = [
                item.querySelector('.warranty-status'),
                item.querySelector('.badge-warranty'),
                item.querySelector('td.warranty-column .badge'),
                item.querySelector('[data-warranty-status]')
            ].filter(el => el); // Remove null elements

            if (statusElements.length === 0) {
                console.log('No warranty status element found for item');
                return;
            }

            // Update each status element found
            statusElements.forEach(statusElement => {
                // Remove all existing badge classes
                statusElement.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'bg-secondary');

                // Get the current status text
                const currentStatus = statusElement.textContent.trim().toUpperCase();
                
                // If current status is EXPIRED, keep it EXPIRED, otherwise show as ACTIVE
                if (currentStatus === 'EXPIRED') {
                    statusElement.textContent = 'EXPIRED';
                    statusElement.classList.add('badge', 'bg-danger');
                } else {
                    statusElement.textContent = 'ACTIVE';
                    statusElement.classList.add('badge', 'bg-success');
                }

                // Keep consistent styling
                statusElement.style.fontWeight = 'bold';
                statusElement.style.letterSpacing = '0.5px';
                statusElement.style.padding = '5px 8px';
                statusElement.style.fontSize = '0.85rem';
            });

            // Remove any days-left display since we're not using dates anymore
            const daysLeftElement = item.querySelector('.days-left');
            if (daysLeftElement) {
                daysLeftElement.remove();
            }
        } catch (error) {
            console.error('Error checking warranty for item:', error);
        }
    });
}

// Debug function to help troubleshoot PO system
function debugPOSystem() {
    console.group('🔍 PO System Debug Information');

    // Check if PO form exists
    const poForm = document.getElementById('poForm');
    console.log('PO Form exists:', !!poForm);

    // Check PO form elements
    const criticalFormFields = [
        'poNo', 'poDate', 'supplier', 'supplierAddress', 'email', 'telephone',
        'procurementMode', 'prNo', 'prDate', 'deliveryPlace', 'paymentTerm', 'deliveryTerm'
    ];

    console.group('Form Fields Existence Check:');
    criticalFormFields.forEach(field => {
        const element = document.getElementById(field);
        console.log(`${field}: ${!!element ? '✅ Found' : '❌ Missing'}`);
    });
    console.groupEnd();

    // Check table existence
    const poTable = document.querySelector('#poItemsTable, #po-items-table, .po-items-table');
    console.log('PO Items Table exists:', !!poTable);

    if (poTable) {
        const rows = poTable.querySelectorAll('tbody tr');
        console.log(`Found ${rows.length} items in PO table`);
    }

    // Check modal structure
    const poModal = document.getElementById('addPOModal');
    console.log('PO Modal exists:', !!poModal);

    if (poModal) {
        const modalForm = poModal.querySelector('form');
        console.log('Form inside modal:', !!modalForm);

        if (modalForm) {
            console.log('Form ID:', modalForm.id);
            console.log('Form Action:', modalForm.action);
            console.log('Form Method:', modalForm.method);
        }
    }

    // Check save button
    const saveBtn = document.getElementById('savePoBtn');
    console.log('Save PO Button exists:', !!saveBtn);

    if (saveBtn) {
        console.log('Save button attributes:', {
            'data-edit-mode': saveBtn.dataset.editMode,
            'data-po-id': saveBtn.dataset.poId,
            'disabled': saveBtn.disabled
        });
    }

    // Check if functions are defined
    console.group('Required Functions Check:');
    ['loadPOData', 'savePoData', 'updatePOTableBody', 'showLoading', 'hideLoading'].forEach(func => {
        console.log(`${func}: ${typeof window[func] === 'function' ? '✅ Defined' : '❌ Missing'}`);
    });
    console.groupEnd();

    // Check server endpoints
    console.group('Server Endpoints:');
    ['get_po.php', 'save_po.php', 'update_po.php'].forEach(endpoint => {
        fetch(endpoint, { method: 'HEAD' })
            .then(response => {
                console.log(`${endpoint}: ${response.ok ? '✅ Accessible' : '❌ Not Found'} (Status: ${response.status})`);
            })
            .catch(error => {
                console.log(`${endpoint}: ❌ Error (${error.message})`);
            });
    });
    console.groupEnd();

    console.groupEnd();

    // Run this debug function on page load
    return 'Debug information has been logged to the console.';
}

// Run debug on page load - but wait for everything to be loaded first
document.addEventListener('DOMContentLoaded', function () {
    // Wait a bit to ensure all scripts are loaded
    setTimeout(debugPOSystem, 1000);
});

// Call checkWarrantyStatus initially and set up interval
document.addEventListener('DOMContentLoaded', function () {
    checkWarrantyStatus();
    setInterval(checkWarrantyStatus, 60000); // Check every minute
});

// Note: The viewPO function is defined earlier in this file (around line 725)
// Do not add another viewPO function here to avoid duplicates

// Function to print the PO
function printPO() {
    // Hide elements not needed for printing
    const elementsToHide = document.querySelectorAll('.no-print, .sidebar, .header');
    elementsToHide.forEach(el => {
        el.dataset.originalDisplay = el.style.display;
        el.style.display = 'none';
    });

    // Add a print-specific class to the body
    document.body.classList.add('printing');

    // Print the document
    window.print();

    // Restore the elements after printing
    setTimeout(() => {
        elementsToHide.forEach(el => {
            el.style.display = el.dataset.originalDisplay || '';
        });
        document.body.classList.remove('printing');
    }, 500);
}

// Function to enhance tables with 3D effects and responsive data attributes
function enhance3DTables() {
    // Apply data-label attributes to cells for responsive design
    function applyDataLabels(tableSelector, headerSelector = 'th') {
        const tables = document.querySelectorAll(tableSelector);

        tables.forEach(table => {
            const headers = Array.from(table.querySelectorAll(headerSelector))
                .map(header => header.textContent.trim());

            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (index < headers.length) {
                        cell.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    }

    // Add subtle animation to the table rows for 3D effect - Updated to apply to all tables
    function addHoverAnimation() {
        const style = document.createElement('style');
        style.textContent = `
            /* Apply consistent hover effects to all tables */
            .table tbody tr {
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            
            .table tbody tr:hover {
                transform: translateY(-2px) scale(1.005);
                box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
                z-index: 1;
                position: relative;
            }
            
            /* Disable transform on mobile */
            @media (max-width: 992px) {
                .table tbody tr:hover,
                .table tbody tr {
                    transform: none !important;
                    box-shadow: none !important;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Apply status badges to condition cells
    function applyStatusBadges() {
        // For inventory condition column
        document.querySelectorAll('.custom-table tbody tr, .table tbody tr').forEach(row => {
            const conditionCell = row.querySelector('td:nth-child(10)'); // Adjust based on your table structure
            if (conditionCell) {
                const condition = conditionCell.textContent.trim();
                const badgeClass = condition.toLowerCase();

                if (condition && ['New', 'Good', 'Fair', 'Poor'].includes(condition)) {
                    conditionCell.innerHTML = `<span class="status-badge status-${badgeClass.toLowerCase()}">${condition}</span>`;
                }
            }
        });
    }

    // Add depth to table header - Updated for all tables
    function addTableHeaderDepth() {
        const style = document.createElement('style');
        style.textContent = `
            .table thead th {
                position: relative;
                overflow: hidden;
            }
            
            .table thead th:after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 2px;
                background: linear-gradient(to right, rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 1), rgba(255, 255, 255, 0.8));
            }
            
            .table {
                border-collapse: separate;
                border-spacing: 0;
            }
        `;
        document.head.appendChild(style);
    }

    // Style serial numbers for better visibility
    function styleSerialNumbers() {
        document.querySelectorAll('.table td:nth-child(5)').forEach(cell => {
            const serialNumber = cell.textContent.trim();
            if (serialNumber && serialNumber !== 'N/A' && serialNumber !== '-') {
                cell.innerHTML = `<code class="serial-number">${serialNumber}</code>`;
            }
        });
    }

    // Apply the functions
    applyDataLabels('.table');
    applyDataLabels('#poTable');
    applyDataLabels('#parTable');
    applyDataLabels('#poItemsTable');
    applyDataLabels('#parItemsTable');

    // Add hover animations and other enhancements
    addHoverAnimation();
    addTableHeaderDepth();
    applyStatusBadges();
    styleSerialNumbers();

    console.log('Enhanced tables with 3D effects');
}

// Run the table enhancement function when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Run immediately and also after data is loaded
    enhance3DTables();

    // Run again after inventory data is loaded
    const originalUpdateInventoryTable = window.updateInventoryTable || function () {
        window.updateInventoryTable = function (data) {
            originalUpdateInventoryTable(data);
            setTimeout(enhance3DTables, 100); // Slight delay to ensure DOM is updated
        };
    }
    // Run again after PO data is loaded
    const originalUpdatePOTable = window.updatePOTable || function () { };
    window.updatePOTable = function (data) {
        originalUpdatePOTable(data);
        setTimeout(enhance3DTables, 100);
    };

    // Run again after PAR data is loaded
    const originalDisplayPARData = window.displayPARData || function () { };
    window.displayPARData = function (data) {
        originalDisplayPARData(data);
        setTimeout(enhance3DTables, 100);
    };
});

function checkForPHPErrors() {
    // This function can be called after fetch requests to check for PHP error outputs
    const errorElements = document.querySelectorAll('.php-error');

    if (errorElements.length > 0) {
        console.error('PHP errors detected on the page:');
        errorElements.forEach(element => {
            console.error(element.textContent);
        });

        // Show a notification to the user
        showError('Server-side errors detected. Please check the console for details.');
        return true;
    }

    return false;
}


document.addEventListener('DOMContentLoaded', function () {
    // Show loading indicator
    function showLoading() {
        console.log('Showing loading indicator...');
        // Check if loading indicator already exists
        if (document.getElementById('loadingIndicator')) {
            console.log('Loading indicator already exists, skipping');
            return;
        }

        const loading = document.createElement('div');
        loading.id = 'loadingIndicator';
        loading.innerHTML = `
    <div class="position-fixed w-100 h-100 d-flex flex-column justify-content-center align-items-center" 
    style="background: rgba(255,255,255,0.9); top: 0; left: 0; z-index: 9999;">
    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
    <span class="visually-hidden">Loading...</span>
    </div>
    <div class="mt-3 text-primary">Loading data...</div>
    </div>`;
        document.body.appendChild(loading);

        // Set a timeout to auto-hide loading if it takes too long
        window.loadingTimeout = setTimeout(() => {
            console.warn('Loading indicator timeout after 30 seconds');
            hideLoading();
            Swal.fire('Timeout', 'The operation took too long. Please try again.', 'warning');
        }, 30000);
    }

    // Hide loading indicator
    function hideLoading() {
        console.log('Hiding loading indicator...');
        // Clear the loading timeout if it exists
        if (window.loadingTimeout) {
            clearTimeout(window.loadingTimeout);
            window.loadingTimeout = null;
        }

        const loading = document.getElementById('loadingIndicator');
        if (loading) {
            loading.remove();
            console.log('Loading indicator removed');
        } else {
            console.log('No loading indicator found to hide');
        }
    }
});
// Set active section with data loading
function setActiveSection(section, skipSave = false) {
    showLoading();

    // Log the requested section for debugging
    console.log(`Setting active section to: ${section}`);

    setTimeout(() => {
        try {
            // Hide all sections first
            const allSections = document.querySelectorAll('.dashboard-section, .inventory-section, .po-section, .par-section');
            if (allSections.length === 0) {
                console.error('No section elements found in the DOM');
                showError('Page sections not found. Please refresh the page.');
                hideLoading();
                return;
            }

            allSections.forEach(el => el.classList.add('d-none'));

            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));

            const sectionMap = {
                'dashboard': {
                    section: '.dashboard-section',
                    link: '#dashboard-link',
                    icon: '<i class="bi bi-grid"></i> Dashboard'
                },
                'inventory': {
                    section: '.inventory-section',
                    link: '#inventory-link',
                    icon: '<i class="bi bi-box-seam"></i> Inventory'
                },
                'po': {
                    section: '.po-section',
                    link: '#po-link',
                    icon: '<i class="bi bi-cart3"></i> Purchase Orders'
                },
                'par': {
                    section: '.par-section',
                    link: '#par-link',
                    icon: '<i class="bi bi-receipt"></i> Property Acknowledgement Receipts'
                }
            };

            if (sectionMap[section]) {
                // Get the section element
                const sectionElement = document.querySelector(sectionMap[section].section);
                if (!sectionElement) {
                    console.error(`Section element not found: ${sectionMap[section].section}`);
                    showError(`Section "${section}" not found. Please refresh the page.`);
                    hideLoading();
                    return;
                }

                // Show the active section
                sectionElement.classList.remove('d-none');
                sectionElement.style.display = 'block'; // Force display block

                // Activate the nav link
                const navLink = document.querySelector(sectionMap[section].link);
                if (navLink) {
                    navLink.classList.add('active');
                }

                // Update header if it exists
                const headerEl = document.querySelector('.header h4');
                if (headerEl) {
                    headerEl.innerHTML = sectionMap[section].icon;
                }

                // Save active section to localStorage if not skipping
                if (!skipSave) {
                    localStorage.setItem('activeSection', section);
                    localStorage.setItem('activeSectionLink', sectionMap[section].link);
                }
                
                // Load data for the active section
                console.log(`Loading data for section: ${section}`);
                try {
                    // Track loading state to prevent recursive calls
                    if (!window.sectionDataLoading) {
                        window.sectionDataLoading = true;

                        if (section === 'inventory') {
                            loadInventoryData();
                        }
                        else if (section === 'po') {
                            loadPOData();
                        }
                        else if (section === 'par') {
                            loadPARData();
                        }
                        else if (section === 'dashboard') {
                            // Prevent dashboard loading loops
                            if (!window.dashboardLoadAttempts) {
                                window.dashboardLoadAttempts = 0;
                            }

                            window.dashboardLoadAttempts++;

                            // If we've tried to load dashboard too many times, switch to inventory
                            if (window.dashboardLoadAttempts > 3) {
                                console.error('Too many dashboard load attempts, switching to inventory');
                                localStorage.setItem('activeSection', 'inventory');
                                setActiveSection('inventory', true);
                                return;
                            }

                            if (typeof loadDashboardStats === 'function') {
                                loadDashboardStats();
                            } else {
                                console.warn('loadDashboardStats function not available');
                            }
                        }

                        // Reset loading flag after a delay
                        setTimeout(() => {
                            window.sectionDataLoading = false;
                        }, 1000);
                    } else {
                        console.warn('Section data already loading, skipping duplicate load');
                    }
                } catch (dataError) {
                    console.error(`Error loading data for section ${section}:`, dataError);
                    showError(`Failed to load data for ${section}: ${dataError.message}`);
                    window.sectionDataLoading = false;
                }
            } else {
                console.error(`Unknown section: ${section}`);
                showError(`Unknown section: ${section}`);
            }
        } catch (error) {
            console.error('Error setting active section:', error);
            showError(`Failed to change section: ${error.message}`);
        } finally {
            hideLoading();
        }
    }, 100); // Reduced timeout for faster section switching
}
// Navigation event listeners
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', function (e) {
        e.preventDefault();
        const section = this.id.replace('-link', '');
        setActiveSection(section);
    });
});
// Initialize PO Modal when opened
const addPOModal2 = document.getElementById('addPOModal');
if (addPOModal2) {
    // Add a preload event to ensure data is loaded before showing the modal
    document.querySelector('[data-bs-target="#addPOModal"]')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        // Show loading indicator
        Swal.fire({
            title: 'Preparing Purchase Order Form',
            html: 'Loading...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Simulate loading time or fetch necessary data
        setTimeout(() => {
            // Reset the form fields
            const poForm = document.getElementById('poForm');
            if (poForm) poForm.reset();

            // Clear items table except first row
            const poItemsTable = document.getElementById('poItemsTable')?.querySelector('tbody');
            if (poItemsTable) {
                const rows = poItemsTable.querySelectorAll('tr');
                if (rows.length > 1) {
                    // Keep only the first row and clear its values
                    for (let i = rows.length - 1; i > 0; i--) {
                        poItemsTable.removeChild(rows[i]);
                    }

                    // Clear the first row's input values
                    const firstRow = rows[0];
                    firstRow.querySelectorAll('input, textarea').forEach(input => {
                        input.value = '';
                    });
                }
            }

            // Setup default date values
            const poDateInput = document.getElementById('poDate');
            if (poDateInput && !poDateInput.value) {
                const today = new Date().toISOString().split('T')[0];
                poDateInput.value = today;
            }

            // Clear any previous total
            const totalAmountInput = document.getElementById('totalAmount');
            if (totalAmountInput) {
                totalAmountInput.value = '₱0.00';
            }

            // Close loading dialog and show the modal
            Swal.close();
            const modal = new bootstrap.Modal(addPOModal2);
            modal.show();
        }, 500);
    });

    addPOModal2.addEventListener('shown.bs.modal', function () {
        // Initialize event listeners for existing rows
        const poItemsTable2 = document.getElementById('poItemsTable');
        if (poItemsTable2) {
            const rows = poItemsTable2.querySelectorAll('tbody tr');
            rows.forEach(row => {
                addPORowEventListeners(row);
            });
            // Calculate initial total
            calculatePOTotal();
        }
    });
}
window.addEventListener('load', function () {
    // Prevent loading loops - only set section if not already set
    if (!window.initialSectionLoaded) {
        window.initialSectionLoaded = true;

        // Safety check to avoid potential dashboard loop
        let savedSection = localStorage.getItem('activeSection') || 'inventory';

        // If last section was dashboard (which might be causing issues), default to inventory
        if (savedSection === 'dashboard' && window.location.search.includes('resetDashboard=true')) {
            console.log('Resetting from dashboard to inventory section due to URL parameter');
            savedSection = 'inventory';
            localStorage.setItem('activeSection', 'inventory');
        }

        console.log('Initial section load:', savedSection);
        setActiveSection(savedSection, true);
    }
});
// PO Items table functionality
const addRowBtn2 = document.getElementById('addRow');
const poItemsTable2 = document.getElementById('poItemsTable')?.getElementsByTagName('tbody')[0];
if (addRowBtn2 && poItemsTable2) {
    addRowBtn2.addEventListener('click', function () {
        const newRow = poItemsTable2.insertRow();
        newRow.innerHTML = `
        <td><input type="text" class="form-control form-control-sm item-name" placeholder="Item"></td>
        <td><input type="text" class="form-control form-control-sm item-unit" placeholder="Unit"></td>
        <td><textarea class="form-control form-control-sm item-description" placeholder="Description" rows="2" style="min-height: 60px;"></textarea></td>
        <td><input type="number" class="form-control form-control-sm qty" placeholder="1" min="1" value="1" step="1"></td>
        <td><input type="number" class="form-control form-control-sm unit-cost" placeholder="0.00" min="0" value="0.00" step="0.01"></td>
        <td><input type="text" class="form-control form-control-sm amount" placeholder="0.00" value="0.00" readonly></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm remove-row">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;

        // Add event listeners to the new row for amount calculations
        addPORowEventListeners(newRow);

        // Focus on the first input of the new row
        newRow.querySelector('input').focus();

        // Update totals
        calculatePOTotal();
    });
}
// PAR Items table functionality
const addParRowBtn2 = document.getElementById('addParRow');
const parItemsTable2 = document.getElementById('parItemsTable')?.getElementsByTagName('tbody')[0];
if (addParRowBtn2 && parItemsTable2) {
    addParRowBtn2.addEventListener('click', function () {
        const newRow = parItemsTable2.insertRow();
        const removeBtn = newRow.querySelector('.remove-row');
        removeBtn.addEventListener('click', function () {
            parItemsTable2.removeChild(newRow);
            calculateParTotal();
        });
        newRow.querySelector('.par-amount').addEventListener('input', calculateParTotal);
    });
}
// Calculation functions
function addRowEventListeners(row) {
    const qtyInput = row.querySelector('.qty');
    const unitCostInput = row.querySelector('.unit-cost');
    const amountInput = row.querySelector('.amount');
    const removeBtn = row.querySelector('.remove-row');

    function calculateAmount() {
        const qty = parseFloat(qtyInput.value) || 0;
        const unitCost = parseFloat(unitCostInput.value) || 0;
        const amount = qty * unitCost;
        amountInput.value = amount.toFixed(2);
        calculateTotal();
    }

    qtyInput?.addEventListener('input', calculateAmount);
    unitCostInput?.addEventListener('input', calculateAmount);
    removeBtn?.addEventListener('click', function () {
        poItemsTable2.removeChild(row);
        calculateTotal();
    });
}

// Utility function to format currency amounts consistently
function formatCurrency(amount) {
    // Convert to number first
    const numericAmount = typeof amount === 'string' ?
        parseFloat(amount.replace(/[^\d.-]/g, '')) :
        parseFloat(amount) || 0;

    // Format with no space after peso sign
    return '₱' + numericAmount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Utility function to parse currency values consistently
function parseCurrency(currencyString) {
    if (!currencyString) return 0;
    return parseFloat(currencyString.replace(/[^\d.-]/g, '')) || 0;
}

// Function to update amount in a row
function updateAmount(row) {
    // Try both class-based and position-based selectors to ensure compatibility
    const qtyInput = row.querySelector('.qty') || row.querySelector('input:nth-child(4)');
    const unitCostInput = row.querySelector('.unit-cost') || row.querySelector('input:nth-child(5)');
    const amountInput = row.querySelector('.amount') || row.querySelector('input:nth-child(6) input');

    if (!qtyInput || !unitCostInput || !amountInput) {
        console.error('Required inputs not found in row:', row);
        return;
    }

    // Parse the quantity and unit cost values
    const qty = parseInt(qtyInput.value) || 0;
    // Clean the unit cost value by removing any currency symbols
    const unitCostValue = unitCostInput.value.replace(/[^\d.-]/g, '');
    const unitCost = parseFloat(unitCostValue) || 0;

    // Calculate the amount
    const amount = qty * unitCost;

    // Store the raw amount in a data attribute for calculations
    amountInput.dataset.rawAmount = amount.toFixed(2);

    // Format the amount for display - use peso sign "₱"
    amountInput.value = '₱' + amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    // Call calculatePOTotal to update the grand total
    if (document.getElementById('totalAmount')) {
        calculatePOTotal();
    }
}

function calculateTotal() {
    const totalAmountField = document.getElementById('totalAmount');
    const obligationAmount = document.getElementById('obligationAmount');

    if (totalAmountField) {
        let total = 0;

        // Get all amount fields and sum their values
        document.querySelectorAll('table tbody tr .amount').forEach(field => {
            // Use the raw amount if available
            if (field.dataset.rawAmount) {
                total += parseFloat(field.dataset.rawAmount) || 0;
            } else {
                // Otherwise, parse the formatted value
                const value = field.value.replace(/[^0-9.-]+/g, '');
                total += parseFloat(value) || 0;
            }
        });

        // Store the raw total value
        totalAmountField.dataset.rawAmount = total.toFixed(2);

        // Format for display - no space after peso sign to match image
        totalAmountField.value = '₱' + total.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Update obligation amount if it exists
        if (obligationAmount) {
            obligationAmount.dataset.rawAmount = total.toFixed(2);
            obligationAmount.value = '₱' + total.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    }
}
// Global calculateAmount function for use with event delegation
function calculateAmount(row) {
    if (!row) return;

    const qtyInput = row.querySelector('.qty');
    const unitCostInput = row.querySelector('.unit-cost');
    const amountInput = row.querySelector('.amount');

    if (!qtyInput || !unitCostInput || !amountInput) return;

    const qty = parseFloat(qtyInput.value) || 0;
    const unitCost = parseFloat(unitCostInput.value) || 0;
    const amount = qty * unitCost;

    // Update the amount field - format with commas but no spaces
    amountInput.value = amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");

    // Call calculate total to update the grand total
    calculateTotal();
}

function calculateParTotal() {
    let total = 0;
    document.querySelectorAll('#parItemsTable tbody tr').forEach(row => {
        const amount = parseFloat(row.querySelector('.amount')?.value.replace(/[^\d.-]/g, '')) || 0;
        total += amount;
    });
    return total;
}

document.querySelectorAll('#poItemsTable tbody tr').forEach(addRowEventListeners);
calculateTotal();
calculateParTotal();
// Enhanced delete item function
function deleteItem(itemId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();

            // Try the direct path first
            fetch('delete_item.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_id: itemId
                })
            })
                .then(response => {
                    if (!response.ok) {
                        // If direct path fails, try the Learning/ path
                        return fetch('Learning/delete_item.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                item_id: itemId
                            })
                        });
                    }
                    return response;
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        Swal.fire('Deleted!', 'The item has been deleted.', 'success');
                        loadInventoryData();
                    } else {
                        throw new Error(data.message || 'Failed to delete item');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    Swal.fire('Error!', 'Failed to delete item: ' + error.message, 'error');
                });
        }
    });
}
// Function to handle item editing
function editItem(itemId) {
    showLoading();
    fetch(`get_item.php?id=${itemId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load item details');
            }
            
            const item = data;
            
            // Populate modal with item data
            const modal = new bootstrap.Modal(document.getElementById('addInventoryModal'));
            const modalTitleElement = document.getElementById('addInventoryModalLabel');
            if (modalTitleElement) {
                modalTitleElement.innerHTML = '<i class="bi bi-pencil"></i> Edit Inventory Item';
            }

            // Populate form fields
            document.getElementById('itemID').value = item.item_id;
            document.getElementById('itemName').value = item.item_name || '';

            // Handle the Brand/Model field which has a slash in the ID
            const brandModelField = document.getElementById('Brand/model') || document.getElementById('brandModel');
            if (brandModelField) {
                brandModelField.value = item.brand_model || '';
            }

            document.getElementById('serialNumber').value = item.serial_number || '';
            document.getElementById('purchaseDate').value = item.purchase_date || '';
            document.getElementById('warrantyDate').value = item.warranty_expiration || '';
            document.getElementById('assignedTo').value = item.assigned_to || '';

            // Handle location field - might be ID or name
            const locationField = document.getElementById('location');
            if (locationField) {
                locationField.value = item.location || '';
            }

            // Handle condition dropdown
            const conditionField = document.getElementById('condition');
            if (conditionField) {
                conditionField.value = item.condition || 'Good';
            }

            document.getElementById('notes').value = item.notes || '';

            // Change save button to update
            const saveBtn = document.getElementById('saveItemBtn');
            if (saveBtn) {
                saveBtn.textContent = 'Update Item';
                saveBtn.dataset.editMode = 'true';
                saveBtn.dataset.itemId = itemId;
            }

            modal.show();
        })
        .catch(error => {
            console.error('Error loading item for editing:', error);
            Swal.fire('Error!', 'Error loading item details: ' + error.message, 'error');
        })
        .finally(() => {
            hideLoading();
        });
}

document.getElementById('savePoBtn')?.addEventListener('click', function () {
    const form = document.querySelector('#addPOModal form');

    // Validate the form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Collect items from table
    const items = [];
    document.querySelectorAll('#poItemsTable tbody tr').forEach(row => {
        // Check if the row has data
        const inputs = row.querySelectorAll('input, textarea');
        if (inputs.length >= 6) {
            const itemName = inputs[0].value.trim();
            const unit = inputs[1].value.trim();
            const description = inputs[2].value.trim();
            const qty = parseFloat(inputs[3].value) || 0;
            const unitCost = parseFloat(inputs[4].value) || 0;
            const amount = parseFloat(inputs[5].value) || 0;

            if (itemName && qty > 0 && unitCost > 0) {
                items.push({
                    item_description: itemName,
                    unit: unit,
                    description: description,
                    quantity: qty,
                    unit_cost: unitCost,
                    amount: amount
                });
            }
        }
    });

    // Check if we have items
    if (items.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'No Items Added',
            text: 'Please add at least one item to the purchase order.'
        });
        return;
    }

    // Collect form data
    const poData = {
        po_no: document.getElementById('poNo').value,
        ref_no: document.getElementById('refNo').value,
        supplier_name: document.getElementById('supplier').value,
        supplier_address: document.getElementById('supplierAddress').value,
        email: document.getElementById('emailAddress').value,
        tel: document.getElementById('telephoneNo').value,
        po_date: document.getElementById('poDate').value,
        mode_of_procurement: document.getElementById('modeOfProcurement').value,
        pr_no: document.getElementById('prNo').value,
        pr_date: document.getElementById('prDate').value,
        place_of_delivery: document.getElementById('placeOfDelivery').value,
        delivery_date: document.getElementById('deliveryDate').value,
        payment_term: document.getElementById('paymentTerm').value,
        delivery_term: document.getElementById('deliveryTerm')?.value || '60 days from receipt of Purchase Order',
        obligation_request_no: document.getElementById('obligationRequestNo')?.value || 'N/A',
        obligation_amount: parseFloat(document.getElementById('obligationAmount')?.value) || 0,
        total_amount: parseFloat(document.getElementById('totalAmount')?.value.replace(/[^0-9.-]+/g, '')) || 0,
        items: items
    };

    // Validate required fields
    if (!poData.po_no || !poData.supplier_name) {
        Swal.fire({
            icon: 'error',
            title: 'Required Fields Missing',
            text: 'Please fill in required fields (PO No, Supplier) and add at least one item'
        });
        return;
    }

    // Show loading indicator
    const saveBtn = document.getElementById('savePoBtn');
    const originalBtnText = saveBtn.textContent;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

    // Check if it's an edit operation
    const isEdit = saveBtn.dataset.editMode === 'true';
    const poId = saveBtn.dataset.poId;

    if (isEdit && poId) {
        poData.id = poId;
        poData.po_id = poId;
    }

    // Determine which endpoint to use
    const url = isEdit ? 'update_po.php' : 'add_po.php';

    console.log('Saving PO data:', poData);

    // Send the data to the server
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(poData)
    })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    throw new Error(`Server error: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                console.log('PO saved successfully, server response:', data);

                // Close the modal
                const modalElement = document.getElementById('addPOModal');
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                } else {
                    modalElement.classList.remove('show');
                    modalElement.style.display = 'none';
                    modalElement.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }

                // Reset form and clean up
                form.reset();
                document.querySelector('#poItemsTable tbody').innerHTML = '';

                // Reset button state
                saveBtn.textContent = originalBtnText;
                saveBtn.dataset.editMode = 'false';
                delete saveBtn.dataset.poId;

                // Reload PO data immediately after successful save
                loadPOData();

                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: isEdit ? 'Purchase Order Updated' : 'Purchase Order Created',
                    text: `The purchase order was ${isEdit ? 'updated' : 'created'} successfully!`,
                    timer: 2000,
                    showConfirmButton: false
                });

                // Add a delayed reload to ensure server data is refreshed
                setTimeout(() => {
                    loadPOData();
                }, 500);
            } else {
                throw new Error(data.message || `Failed to ${isEdit ? 'update' : 'create'} PO`);
            }
        })
        .catch(error => {
            console.error('Error saving PO:', error);
            Swal.fire('Error!', error.message, 'error');
        })
        .finally(() => {
            // Re-enable button
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;
        });
});
// Add automatic amount calculation for PO items
document.querySelector('#poItemsTable')?.addEventListener('input', function (e) {
    if (e.target.classList.contains('qty') || e.target.classList.contains('unit-cost')) {
        const row = e.target.closest('tr');
        if (!row) return;

        const qtyInput = row.querySelector('.qty');
        const unitCostInput = row.querySelector('.unit-cost');
        const amountInput = row.querySelector('.amount');

        if (!qtyInput || !unitCostInput || !amountInput) return;

        // Clean and validate qty input
        if (e.target.classList.contains('qty')) {
            // Remove any non-numeric characters
            qtyInput.value = qtyInput.value.replace(/[^\d]/g, '');

            // Ensure minimum value is 1
            if (qtyInput.value === '' || parseInt(qtyInput.value) < 1) {
                qtyInput.value = '1';
            }
        }

        // Clean and validate unit cost input
        if (e.target.classList.contains('unit-cost')) {
            // Remove any non-numeric or decimal characters
            unitCostInput.value = unitCostInput.value.replace(/[^\d.]/g, '');

            // Enforce proper decimal format
            const parts = unitCostInput.value.split('.');
            if (parts.length > 2) {
                // Keep only the first decimal point
                unitCostInput.value = parts[0] + '.' + parts.slice(1).join('');
            }
        }

        // Parse values with appropriate defaults
        const qty = parseInt(qtyInput.value) || 1;
        const unitCost = parseFloat(unitCostInput.value) || 0;

        // Calculate and update amount
        const amount = qty * unitCost;
        amountInput.value = amount.toFixed(2);

        // Update the total
        calculatePOTotal();
    }
});

// Initialize event listeners when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {
    // Setup PO items table calculations
    const poItemsTable = document.getElementById('poItemsTable');
    if (poItemsTable) {
        // Initialize existing rows
        const rows = poItemsTable.querySelectorAll('tbody tr');
        rows.forEach(row => {
            addPORowEventListeners(row);
        });

        // Calculate initial total
        calculatePOTotal();
    }
});
// Function to view a PO

// Function to view a PO
function viewPO(poId) {
    if (!poId) {
        Swal.fire('Error', 'PO ID is required', 'error');
        return;
    }

    console.log('Viewing PO with ID:', poId);
    
    // Show loading indicator
    showLoading();
    
    // Get the PO data first
    getPOData(poId, function(poData) {
        hideLoading();
        
        // Format the items array to ensure it has all required fields
        const formattedItems = (poData.items || []).map(item => {
            return {
                id: item.id || item.item_id || null,
                unit: item.unit || 'pc',
                description: item.description || item.item_description || '',
                item_description: item.item_description || item.description || '',
                item_name: item.item_name || '',
                quantity: parseFloat(item.quantity || item.qty || 0),
                unit_cost: parseFloat(item.unit_cost || item.unit_price || item.price || 0),
                amount: parseFloat(item.amount || item.total_cost || item.total || 0),
                // Add textarea field explicitly for viewPO.php compatibility
                textarea: item.description || item.item_description || '',
                // Include data field for extra compatibility
                data: JSON.stringify({
                    description: item.description || item.item_description || '',
                    unit: item.unit || 'pc',
                    quantity: parseFloat(item.quantity || item.qty || 0),
                    unit_cost: parseFloat(item.unit_cost || item.unit_price || item.price || 0)
                })
            };
        });
        
        // Create a properly structured data object with all required fields
        const completePoData = {
            po_id: poData.po_id || poData.id,
            po_no: poData.po_no || poData.po_number || '',
            po_date: poData.po_date || poData.date || new Date().toISOString().split('T')[0],
            pr_no: poData.pr_no || poData.pr_number || '',
            pr_date: poData.pr_date || '',
            supplier_name: poData.supplier_name || poData.supplier || '',
            supplier_address: poData.supplier_address || poData.address || '',
            supplier_email: poData.supplier_email || poData.email || '',
            supplier_tel: poData.supplier_tel || poData.tel || poData.telephone || '',
            place_of_delivery: poData.place_of_delivery || poData.delivery_place || '',
            delivery_date: poData.delivery_date || '',
            delivery_term: poData.delivery_term || poData.delivery_terms || '60 days from receipt of Purchase Order',
            payment_term: poData.payment_term || poData.payment_terms || 'Full Payment on Full Delivery',
            mode_of_procurement: poData.mode_of_procurement || poData.procurement_mode || 'Public Bidding',
            total_amount: parseFloat(poData.total_amount || poData.total || 0),
            obligation_request_no: poData.obligation_request_no || '',
            obligation_amount: parseFloat(poData.obligation_amount || poData.total_amount || 0),
            ref_no: poData.ref_no || '',
            items: formattedItems
        };
        
        // Create a properly structured data object for the modal
        const modalData = {
            po_details: completePoData,
            items: formattedItems
        };
        
        // Encode the data as JSON, then URL encode it
        const jsonString = JSON.stringify(modalData);
        const encodedData = encodeURIComponent(jsonString);
        
        // Log data size for debugging
        console.log('Modal data size (bytes):', encodedData.length);
        
        // Open in a new window with the data
        window.open(`viewPO.php?id=${encodeURIComponent(poId)}&modal_data=${encodedData}`, '_blank');
    });
}
// Helper function to format and display PO data
function displayPOData(poData, poId) {
    // Check if we have valid data
    if (!poData || !poId) {
        Swal.fire('Error', 'Invalid PO data', 'error');
        return;
    }

    // Format the items array to ensure it has all required fields
    const formattedItems = (poData.items || []).map(item => {
        return {
            id: item.id || item.item_id || null,
            unit: item.unit || 'pc',
            description: item.description || item.item_description || '',
            item_description: item.item_description || item.description || '',
            quantity: parseFloat(item.quantity || item.qty || 0),
            unit_cost: parseFloat(item.unit_cost || item.unit_price || item.price || 0),
            amount: parseFloat(item.amount || item.total_cost || item.total || 0)
        };
    });
    
    // Create a properly structured data object for the modal
    const modalData = {
        po_details: poData,
        items: formattedItems
    };
    
    try {
        // Encode the data as JSON, then URL encode it
        const jsonString = JSON.stringify(modalData);
        const encodedData = encodeURIComponent(jsonString);
        
        // Log data size for debugging
        console.log('Modal data size (bytes):', encodedData.length);
        
        // Check if data is too large for URL (practical limit around 2000 chars)
        if (encodedData.length > 1800) {
            console.warn('PO data exceeds URL size limit, using sessionStorage instead');
            // Store data in sessionStorage with a special key for this PO
            const storageKey = `po_view_data_${poId}`;
            sessionStorage.setItem(storageKey, jsonString);
            // Open in a new window with a reference to sessionStorage
            window.open(`viewPO.php?id=${encodeURIComponent(poId)}&use_storage=true&storage_key=${storageKey}`, '_blank');
        } else {
            // Open in a new window with the data in URL
            window.open(`viewPO.php?id=${encodeURIComponent(poId)}&modal_data=${encodedData}`, '_blank');
        }
    } catch (error) {
        console.error('Error preparing PO data for view:', error);
        Swal.fire('Error', 'Failed to prepare PO data for viewing: ' + error.message, 'error');
    }
}

// Function to print a PO
function printPO() {
    // Hide elements not needed for printings
    const elementsToHide = document.querySelectorAll('.no-print, .sidebar, .header');
    elementsToHide.forEach(el => {
        el.dataset.originalDisplay = el.style.display;
        el.style.display = 'none';
    });

    // Add a print-specific class to the body
    document.body.classList.add('printing');

    // Print the document
    window.print();

    // Restore the elements after printing
    setTimeout(() => {
        elementsToHide.forEach(el => {
            el.style.display = el.dataset.originalDisplay || '';
        });
        document.body.classList.remove('printing');
    }, 500);
}

// Document Ready Function to Initialize Everything
document.addEventListener('DOMContentLoaded', function () {
    // Initialize the inventory system
    initInventorySystem();

    // Add event listeners for search and filters
    document.getElementById('inventorySearchInput')?.addEventListener('input', function (e) {
        loadInventoryData(e.target.value);
    });

    document.getElementById('conditionFilter')?.addEventListener('change', applyFilters);
    document.getElementById('locationFilter')?.addEventListener('change', applyFilters);

    // Add event listeners for pagination
    document.getElementById('inventoryPrevBtn')?.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            updateInventoryTable(window.filteredData || []);
        }
    });

    document.getElementById('inventoryNextBtn')?.addEventListener('click', function () {
        const maxPages = Math.ceil((window.filteredData || []).length / itemsPerPage);
        if (currentPage < maxPages) {
            currentPage++;
            updateInventoryTable(window.filteredData || []);
        }
    });

    // Initialize action buttons
    addActionButtonListeners();

    // Load initial inventory data
    loadInventoryData();

    // Enhance table appearance
    enhance3DTables();
});

// Fix the loadInventoryData function to handle server failures better
function loadInventoryData(searchQuery = '') {
    console.log(`Loading inventory data with search query: "${searchQuery}"`);

    // Ensure we're using a consistent endpoint path
    const url = searchQuery
        ? `get_inventory.php?search=${encodeURIComponent(searchQuery)}`
        : 'get_inventory.php';

    showLoading();

    console.log('Fetching inventory data from:', url);

    fetch(url)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text.substring(0, 200)}...`);
                });
            }
            return response.json();
        })
        .then(response => {
            console.log('Data received:', response);

            // Handle different response formats
            let data = response;

            // Check for error format
            if (response && response.success === false) {
                throw new Error(response.message || 'Unknown error occurred');
            }
            // If response has a data property, use that
            else if (response && response.data) {
                data = response.data;
            }
            // If response is empty array, that's valid
            else if (Array.isArray(response) && response.length === 0) {
                data = [];
            }
            // Check if data is not valid
            else if (!response || (typeof response !== 'object' && !Array.isArray(response))) {
                throw new Error('Invalid data format received from server');
            }

            // Store data globally for filtering operations
            window.filteredData = data;

            // Update the table with the data
            updateInventoryTable(data);

            // Run additional functions if they exist
            if (typeof checkWarrantyStatus === 'function') {
                checkWarrantyStatus();
            }

            if (typeof updateConditionStatus === 'function') {
                updateConditionStatus();
            }

            hideLoading();
        })
        .catch(error => {
            console.error('Failed to load inventory data:', error);
            showError('Failed to load inventory data: ' + error.message);

            // Make sure loading indicator is hidden even on error
            hideLoading();

            // Set empty table with error message
            const tableBody = document.getElementById('inventoryTableBody');
            if (tableBody) {
                tableBody.innerHTML = `<tr><td colspan="11" class="text-center text-danger">
                        Error loading data. Please try again.
                    </td></tr>`;
            }
        });
}

// Initialize the inventory system
function initInventorySystem() {
    console.log('Initializing inventory system...');

    // Load initial data
    loadInventoryData();

    // Add search functionality if search input exists
    const searchInput = document.getElementById('inventorySearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function () {
            loadInventoryData(this.value);
        }, 500));
    }

    // Add refresh button functionality if it exists
    const refreshButton = document.getElementById('refreshInventory');
    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            loadInventoryData();
        });
    }

    console.log('Inventory system initialized');
}

// Document ready function
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM fully loaded');

    // Initialize the inventory system when the page loads
    if (document.getElementById('inventoryTableBody')) {
        initInventorySystem();
    }
});

// Add a function to check for common PHP errors
function checkForPHPErrors() {
    // This function can be called after fetch requests to check for PHP error outputs
    const errorElements = document.querySelectorAll('.php-error');

    if (errorElements.length > 0) {
        console.error('PHP errors detected on the page:');
        errorElements.forEach(element => {
            console.error(element.textContent);
        });

        // Show a notification to the user
        showError('Server-side errors detected. Please check the console for details.');
        return true;
    }

    return false;
}

// Fix saveInventoryItem to match your form and backend
function saveInventoryItem() {
    console.log('saveInventoryItem function called');

    // Get form data
    const form = document.querySelector('#addInventoryModal form');
    if (!form) {
        console.error('Form not found in modal');
        return;
    }

    // Basic validation - check required fields
    const itemName = document.getElementById('itemName').value.trim();

    // Required field validation
    let hasError = false;
    if (!itemName) {
        document.getElementById('itemName').classList.add('is-invalid');
        hasError = true;
    } else {
        document.getElementById('itemName').classList.remove('is-invalid');
    }

    // Check if serial is valid (if it has a value and has the is-invalid class)
    const serialNumber = document.getElementById('serialNumber').value.trim();
    if (serialNumber && document.getElementById('serialNumber').classList.contains('is-invalid')) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Serial Number',
            text: 'The serial number is already in use. Please enter a unique serial number.'
        });
        return;
    }

    if (hasError) {
        Swal.fire({
            icon: 'error',
            title: 'Missing Information',
            text: 'Please fill in all required fields before saving.'
        });
        return;
    }

    // Get all form values directly from input elements to ensure proper values
    const itemData = {
        item_id: document.getElementById('itemID')?.value || '',
        item_name: itemName,
        brand_model: document.getElementById('Brand/model')?.value || document.getElementById('brandModel')?.value || '',
        serial_number: serialNumber,
        purchase_date: document.getElementById('purchaseDate')?.value || '',
        warranty_expiration: document.getElementById('warrantyDate')?.value || '',
        assigned_to: document.getElementById('assignedTo')?.value || '',
        location: document.getElementById('location')?.value || '',
        condition: document.getElementById('condition')?.value || 'Good',
        notes: document.getElementById('notes')?.value || ''
    };

    console.log('Item data to be saved:', itemData);

    // Check if we're editing an existing item
    const saveBtn = document.getElementById('saveItemBtn');
    const isEdit = saveBtn && saveBtn.dataset.editMode === 'true';
    const itemId = saveBtn ? saveBtn.dataset.itemId : null;

    if (isEdit && itemId) {
        itemData.item_id = itemId;
    }

    // Determine which endpoint to use
    const url = isEdit ? 'update_item.php' : 'add_item.php';

    console.log('Saving item data:', itemData);

    // Show loading state on button
    if (saveBtn) {
        const originalText = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    }

    // Send request to server
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(itemData)
    })
        .then(response => {
            // First check if response is ok
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    throw new Error(`HTTP error! Status: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Response received:', data);

            if (data.success) {
                // Close modal
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('addInventoryModal'));
                if (modalInstance) {
                    modalInstance.hide();
                } else {
                    // Try alternate method to close modal
                    $('#addInventoryModal').modal('hide');
                }

                // Reset form
                form.reset();

                // Reset validation states
                form.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
                    el.classList.remove('is-invalid', 'is-valid');
                });

                // Reset button state if it exists
                if (saveBtn) {
                    saveBtn.textContent = 'Save Item';
                    saveBtn.dataset.editMode = 'false';
                    delete saveBtn.dataset.itemId;
                    saveBtn.disabled = false;
                }

                // Reload inventory data
                loadInventoryData();

                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: isEdit ? 'Item Updated!' : 'Item Added!',
                    text: isEdit ? 'The inventory item has been updated successfully.' : 'New inventory item has been added successfully.'
                });
            } else {
                throw new Error(data.message || 'Failed to save item');
            }
        })
        .catch(error => {
            console.error('Error saving item:', error);

            // Reset button state
            if (saveBtn) {
                saveBtn.textContent = 'Save Item';
                saveBtn.disabled = false;
            }

            // Show error message
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to save item: ' + error.message
            });
        });
}

// Improved showError function
function showError(message) {
    const errorContainer = document.getElementById('error-container');
    if (errorContainer) {
        errorContainer.textContent = message;
        errorContainer.style.display = 'block';

        // Optional: Auto-hide error after a few seconds
        setTimeout(() => {
            errorContainer.style.display = 'none';
        }, 5000);
    } else {
        console.error(message);
        alert(message);
    }
}

// Make sure event listeners are properly set up when the DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM fully loaded');

    // Set up event listener for the save inventory button
    const saveInventoryBtn = document.getElementById('saveInventoryBtn');
    if (saveInventoryBtn) {
        console.log('Found save inventory button, attaching event listener');
        saveInventoryBtn.addEventListener('click', saveInventoryItem);
    } else {
        console.error('Save inventory button not found in the DOM');
    }

    // Add a listener for form submission to prevent default behavior
    const inventoryForm = document.getElementById('inventoryForm');
    if (inventoryForm) {
        inventoryForm.addEventListener('submit', function (e) {
            e.preventDefault();
            console.log('Form submission intercepted');
            saveInventoryItem();
        });
    }

    // Load inventory data when the page loads
    console.log('Initial loading of inventory data');
    loadInventoryData();
});

// Fix the addPORowEventListeners function which is called but not defined
function addPORowEventListeners(row) {
    const qtyInput = row.querySelector('.qty');
    const unitCostInput = row.querySelector('.unit-cost');
    const amountInput = row.querySelector('.amount');
    const removeBtn = row.querySelector('.remove-row');

    // Update amount when quantity or unit cost changes
    if (qtyInput) {
        // Ensure qty is always at least 1 and a whole number
        qtyInput.addEventListener('input', function () {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^\d]/g, '');

            // Ensure minimum value is 1
            if (this.value === '' || parseInt(this.value) < 1) {
                this.value = '1';
            }

            updateAmount(row);
        });

        // Additional validation on blur
        qtyInput.addEventListener('blur', function () {
            if (this.value === '' || parseInt(this.value) < 1) {
                this.value = '1';
                updateAmount(row);
            }
        });
    }

    if (unitCostInput) {
        unitCostInput.addEventListener('input', function () {
            // Allow only numbers and decimal point
            this.value = this.value.replace(/[^\d.]/g, '');

            // Only allow one decimal point
            const decimalPoints = this.value.match(/\./g);
            if (decimalPoints && decimalPoints.length > 1) {
                this.value = this.value.slice(0, this.value.lastIndexOf('.'));
            }

            updateAmount(row);
        });

        // Format to 2 decimal places on blur
        unitCostInput.addEventListener('blur', function () {
            if (this.value !== '') {
                const value = parseFloat(this.value) || 0;
                this.value = value.toFixed(2);
                updateAmount(row);
            }
        });
    }

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            const tbody = row.parentNode;
            if (tbody) {
                tbody.removeChild(row);
                calculatePOTotal();
            }
        });
    }
}

// Function to update amount in a row
function updateAmount(row) {
    if (!row) {
        console.error('Row is undefined in updateAmount');
        return;
    }

    // Get the input elements
    const qtyInput = row.querySelector('.qty');
    const unitCostInput = row.querySelector('.unit-cost');
    const amountInput = row.querySelector('.amount');

    if (!qtyInput || !unitCostInput || !amountInput) {
        console.error('Required inputs not found in row:', row);
        return;
    }

    // Ensure qty is valid
    let qty = parseInt(qtyInput.value.replace(/[^\d]/g, '')) || 0;
    if (qty < 1) qty = 1;
    qtyInput.value = qty;

    // Clean and parse unit cost value
    let unitCost = parseFloat(unitCostInput.value.replace(/[^\d.]/g, '')) || 0;

    // Calculate the amount
    const amount = qty * unitCost;

    // Store raw amount for calculations
    amountInput.dataset.rawAmount = amount.toFixed(2);

    // Format the amount for display
    amountInput.value = '₱' + amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    // Update the total
    calculatePOTotal();
}

// Function to calculate total for PO
function calculatePOTotal() {
    const totalAmountField = document.getElementById('totalAmount');
    if (!totalAmountField) return;

    let total = 0;

    // Get all amount fields and sum their values
    document.querySelectorAll('#poItemsTable tbody tr .amount').forEach(field => {
        // Use the raw amount if available
        if (field.dataset.rawAmount) {
            total += parseFloat(field.dataset.rawAmount) || 0;
        } else {
            // Otherwise, parse the formatted value
            const value = field.value.replace(/[^0-9.-]+/g, '');
            total += parseFloat(value) || 0;
        }
    });

    // Store the raw total
    totalAmountField.dataset.rawAmount = total.toFixed(2);

    // Format for display
    totalAmountField.value = '₱' + total.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Add missing showError function
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        timer: 3000
    });
}

// Add missing displayInventoryData function
function displayInventoryData(data) {
    if (!data) {
        showError('No inventory data received');
        return;
    }

    // Update the inventory table with the received data
    updateInventoryTable({ data: Array.isArray(data) ? data : [data] });

    // Add event listeners to the newly created elements
    document.querySelectorAll('.edit-item').forEach(button => {
        button.addEventListener('click', function () {
            const itemId = this.getAttribute('data-id');
            editItem(itemId);
        });
    });

    document.querySelectorAll('.delete-item').forEach(button => {
        button.addEventListener('click', function () {
            const itemId = this.getAttribute('data-id');
            deleteItem(itemId);
        });
    });
}

// Add missing getConditionBadgeClass function used in trackLocation
function getConditionBadgeClass(condition) {
    switch (condition) {
        case 'New': return 'bg-success';
        case 'Good': return 'bg-primary';
        case 'Fair': return 'bg-warning';
        case 'Poor': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

// Add missing loadPOData and loadPARData functions
function loadPOData() {
    showLoading();
    console.log('Loading PO data from server...');

    // Create a single, reliable endpoint path
    const endpoint = 'get_po.php';
    const cacheBuster = `?cache=${new Date().getTime()}`;
    const url = endpoint + cacheBuster;

    console.log('Fetching PO data from:', url);

    fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        },
        cache: 'no-store'
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                console.error(`Server error (${response.status}):`, text.substring(0, 200));
                throw new Error(`Server returned ${response.status}: ${text.substring(0, 100)}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log(`Successfully loaded data:`, data);
        processPoData(data);
    })
    .catch(error => {
        console.error('Error loading PO data:', error);
        
        // Display user-friendly error message in the PO table
        const poTableBody = document.getElementById('poTableBody') ||
            document.querySelector('.po-section table tbody') ||
            document.querySelector('#po-section table tbody');
            
        if (poTableBody) {
            poTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">
                Error: Failed to load PO data. Please try again. (${error.message})
            </td></tr>`;
        }
        
        // Show error message to user
        if (typeof showError === 'function') {
            showError('Failed to load PO data: ' + error.message);
        } else {
            Swal.fire('Error', 'Failed to load PO data: ' + error.message, 'error');
        }
    })
    .finally(() => {
        hideLoading();
    });
}

// Process the PO data from the server response
function processPoData(response) {
    console.log('Processing PO data:', response);
    
    try {
        // Check if response is an error message from the server
        if (response && response.success === false) {
            console.error('Server returned error:', response.message || 'Unknown server error');
            Swal.fire({
                title: 'Server Error',
                text: response.message || 'Failed to load PO data from server',
                icon: 'error',
                confirmButtonText: 'Try Again'
            }).then((result) => {
                if (result.isConfirmed) {
                    loadPOData();
                }
            });
            return;
        }

        // Extract data based on response format
        let data;
        if (response && response.success === true && Array.isArray(response.data)) {
            data = response.data;
            console.log('Using data array from success response');
        } else if (Array.isArray(response)) {
            data = response;
            console.log('Using direct array response');
        } else if (response && response.success === true && typeof response.data === 'object') {
            data = Object.values(response.data);
            console.log('Converting data object to array');
        } else if (typeof response === 'object' && response !== null) {
            // Try to convert object to array if needed
            const possibleData = Object.values(response);
            if (possibleData.length > 0) {
                data = possibleData;
                console.log('Converted response object to array with ' + possibleData.length + ' items');
            } else {
                console.warn('Empty object values in response');
                data = [];
            }
        } else {
            console.error('Invalid response format:', response);
            Swal.fire('Data Error', 'Received invalid data format from server', 'error');
            data = [];
        }

        // Validate data is an array
        if (!Array.isArray(data)) {
            console.error('Data is not an array after processing:', data);
            data = [];
        }

        console.log('Processed data:', data);

        // Find the table body
        const poTableBody = document.getElementById('poTableBody') ||
            document.querySelector('.po-section table tbody') ||
            document.querySelector('#po-section table tbody');

        if (!poTableBody) {
            console.error('PO table body not found in the DOM');
            return;
        }

        // Clear the table first
        poTableBody.innerHTML = '';

        // Check if data is empty
        if (data.length === 0) {
            poTableBody.innerHTML = '<tr><td colspan="5" class="text-center">No purchase orders found</td></tr>';
            return;
        }

        // Populate the table with data
        data.forEach(po => {
            const row = document.createElement('tr');
            
            // Make sure we have valid data by using optional chaining or fallbacks
            const poNo = po?.po_no || 'N/A';
            const supplierName = po?.supplier_name || 'N/A';
            const poDate = po?.po_date || 'N/A';
            const totalAmount = po?.total_amount || 0;
            const poId = po?.id || po?.po_id || 'N/A';
            
            row.innerHTML = `
                <td>${poNo}</td>
                <td>${supplierName}</td>
                <td>${poDate}</td>
                <td>${formatCurrency(totalAmount) || '₱0.00'}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-sm btn-info view-po" data-id="${poId}">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-primary edit-po" data-id="${poId}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-po" data-id="${poId}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            poTableBody.appendChild(row);
        });
        
        // Add event listeners to buttons
        addPOButtonEventListeners();
        
    } catch (error) {
        console.error('Error processing PO data:', error);
        Swal.fire('Processing Error', 'Failed to process PO data: ' + error.message, 'error');
        
        // Find table body and show error
        const poTableBody = document.getElementById('poTableBody') ||
            document.querySelector('.po-section table tbody') ||
            document.querySelector('#po-section table tbody');
            
        if (poTableBody) {
            poTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">
                Error processing data. Please try reloading the page.
            </td></tr>`;
        }
    }
}

// ... existing code ...

// Fix for inventory modal save button - place directly in the DOMContentLoaded event
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM loaded, setting up inventory modal button handlers');

    // Initialize serial number validation
    validateSerialNumber();

    // Use direct event handler attachment with JavaScript for reliability
    const saveItemBtn = document.getElementById('saveItemBtn');
    if (saveItemBtn) {
        console.log('Save button found, attaching click handler');
        saveItemBtn.addEventListener('click', function (event) {
            event.preventDefault();
            console.log('Save button clicked');

            // Get form data
            const form = document.querySelector('#addInventoryModal form');
            if (!form) {
                console.error('Form not found in modal');
                return;
            }

            // Basic validation - check required fields
            const itemName = document.getElementById('item_name').value.trim();
            const serialNumber = document.getElementById('serial_number').value.trim();

            // Required field validation
            let hasError = false;
            if (!itemName) {
                document.getElementById('item_name').classList.add('is-invalid');
                hasError = true;
            } else {
                document.getElementById('item_name').classList.remove('is-invalid');
            }

            // Check if serial is valid (if it has a value and has the is-invalid class)
            if (serialNumber && document.getElementById('serial_number').classList.contains('is-invalid')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Serial Number',
                    text: 'The serial number is already in use. Please enter a unique serial number.'
                });
                return;
            }

            if (hasError) {
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields before saving.'
                });
                return;
            }

            // Get all form values
            const formData = new FormData(form);

            // Convert FormData to JSON object
            const itemData = {};
            formData.forEach((value, key) => {
                itemData[key] = value;
            });

            // Normalize field names for backend compatibility
            const normalizedData = {
                item_id: itemData.item_id || itemData.item_code || '',
                item_name: itemData.item_name || '',
                brand_model: itemData.brand_model || itemData['Brand/model'] || '',
                serial_number: itemData.serial_number || '',
                purchase_date: itemData.purchase_date || '',
                warranty_expiration: itemData.warranty_expiration || itemData.warrantyDate || '',
                assigned_to: itemData.assigned_to || '',
                location: itemData.location || itemData.location_id || '',
                condition: itemData.condition || 'Good',
                notes: itemData.notes || ''
            };

            // Check if we're editing an existing item
            const isEdit = this.dataset.editMode === 'true';
            const itemId = this.dataset.itemId;

            if (isEdit && itemId) {
                normalizedData.item_id = itemId;
            }

            // Determine which endpoint to use
            const url = isEdit ? 'update_item.php' : 'add_item.php';

            console.log('Saving item data:', normalizedData);

            // Show loading state on button
            const saveBtn = this;
            const originalText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

            // Send request to server using fetch API
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(normalizedData)
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Response received:', data);

                    if (data.success) {
                        // Close modal
                        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('addInventoryModal'));
                        modalInstance.hide();

                        // Reset form
                        form.reset();

                        // Reset validation states
                        form.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
                            el.classList.remove('is-invalid', 'is-valid');
                        });

                        // Reset button state
                        saveBtn.textContent = 'Save Item';
                        saveBtn.dataset.editMode = 'false';
                        delete saveBtn.dataset.itemId;

                        // Reload inventory data
                        loadInventoryData();

                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: isEdit ? 'Item Updated' : 'Item Added',
                            text: isEdit ? 'The item has been updated successfully.' : 'The item has been added successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        throw new Error(data.message || 'Failed to save item');
                    }
                })
                .catch(error => {
                    console.error('Error saving item:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Failed to save item. Please try again.'
                    });
                })
                .finally(() => {
                    // Reset button state
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                });
        });
    } else {
        console.error('Save button not found when setting up event handler');
    }

    // Also ensure the button in the modal works when the modal is shown
    const addInventoryModal = document.getElementById('addInventoryModal');
    if (addInventoryModal) {
        addInventoryModal.addEventListener('shown.bs.modal', function () {
            console.log('Inventory modal shown, checking button');
            const saveBtn = document.getElementById('saveItemBtn');
            if (saveBtn) {
                console.log('Save button found in modal');
                // Reset form and validation states
                const form = document.querySelector('#addInventoryModal form');
                if (form) {
                    form.reset();
                    form.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
                        el.classList.remove('is-invalid', 'is-valid');
                    });
                }

                // Reset any feedback messages
                const serialFeedback = document.querySelector('.serial-feedback');
                if (serialFeedback) {
                    serialFeedback.textContent = '';
                }

                // Reinitialize serial number validation
                validateSerialNumber();
            } else {
                console.error('Save button not found in modal');
            }
        });
    }

    // Rest of your initialization code
});

// Add serial number tracking functionality
const serialNumberInput = document.getElementById('serial_number');

if (serialNumberInput) {
    console.log('Serial number input found, adding validation and tracking');

    // Add input event listener for validation
    serialNumberInput.addEventListener('input', function () {
        // Remove any existing validation messages
        this.classList.remove('is-invalid', 'is-valid');
        const feedbackElement = this.nextElementSibling;
        if (feedbackElement && feedbackElement.classList.contains('invalid-feedback')) {
            feedbackElement.remove();
        }

        const serialValue = this.value.trim();

        // Skip validation if empty
        if (!serialValue) return;

        // Check format - allow letters, numbers, and hyphens only
        const validFormat = /^[A-Za-z0-9\-]+$/.test(serialValue);
        if (!validFormat) {
            this.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.innerText = 'Serial number should only contain letters, numbers, and hyphens.';
            this.parentNode.appendChild(feedback);
            return;
        }

        // Check minimum length
        if (serialValue.length < 3) {
            this.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.innerText = 'Serial number should be at least 3 characters.';
            this.parentNode.appendChild(feedback);
            return;
        }

        // Check for duplicate serial number
        checkDuplicateSerialNumber(serialValue, this);
    });

    // Add blur event for final validation
    serialNumberInput.addEventListener('blur', function () {
        // Remove any existing validation messages
        const feedbackElement = this.nextElementSibling;
        if (feedbackElement && feedbackElement.classList.contains('invalid-feedback')) {
            feedbackElement.remove();
        }

        const serialValue = this.value.trim();

        // Skip validation if empty
        if (!serialValue) return;

        // Normalize serial number format (uppercase)
        this.value = serialValue.toUpperCase();

        // Check for duplicate serial number
        checkDuplicateSerialNumber(serialValue, this);
    });
}

// Function to check for duplicate serial numbers
function checkDuplicateSerialNumber(serialNumber, inputElement) {
    if (!serialNumber) return;

    // Don't check for duplicates in edit mode if it's the same item
    const saveItemBtn = document.getElementById('saveItemBtn');
    const isEdit = saveItemBtn && saveItemBtn.dataset.editMode === 'true';
    const originalItemId = saveItemBtn ? saveItemBtn.dataset.itemId : null;

    // Make API call to check if serial number exists
    fetch(`check_serial.php?serial=${encodeURIComponent(serialNumber)}&item_id=${encodeURIComponent(originalItemId || '')}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Remove any existing validation messages
            inputElement.classList.remove('is-invalid', 'is-valid');
            const feedbackElement = inputElement.nextElementSibling;
            if (feedbackElement && feedbackElement.classList.contains('invalid-feedback')) {
                feedbackElement.remove();
            }

            if (data.exists) {
                // Serial number exists
                inputElement.classList.add('is-invalid');
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.innerText = 'This serial number is already in use.';
                inputElement.parentNode.appendChild(feedback);
            } else {
                // Serial number is unique
                inputElement.classList.add('is-valid');
            }
        })
        .catch(error => {
            console.error('Error checking serial number:', error);
        });
}

// Add serial number search functionality
const serialSearchBtn = document.getElementById('serialSearchBtn');
if (serialSearchBtn) {
    serialSearchBtn.addEventListener('click', function () {
        // Show a prompt to input a serial number
        Swal.fire({
            title: 'Scan Serial Number',
            text: 'Enter or scan the serial number to search for',
            input: 'text',
            inputAttributes: {
                autocapitalize: 'off',
                autocorrect: 'off',
                autocomplete: 'off',
                spellcheck: 'false',
                autofocus: 'true'
            },
            showCancelButton: true,
            confirmButtonText: 'Search',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const serialNumber = result.value.trim();
                if (serialNumber) {
                    // Show loading state
                    showLoading();

                    // Get the notification area
                    const scanResultNotification = document.getElementById('scanResultNotification');
                    const scanResultStatus = scanResultNotification.querySelector('.scan-result-status');
                    const scanResultText = scanResultNotification.querySelector('.scan-result-text');

                    // Search for the serial number in the inventory data
                    fetch(`search_serial.php?serial=${encodeURIComponent(serialNumber)}`)
                        .then(response => response.json())
                        .then(data => {
                            hideLoading();

                            if (data.success && data.item) {
                                // Show the notification
                                scanResultNotification.classList.remove('d-none', 'error', 'info');
                                scanResultNotification.classList.add('success');
                                scanResultStatus.innerHTML = '<i class="bi bi-check-circle"></i>';
                                scanResultText.textContent = `Found: ${data.item.item_name} (${data.item.serial_number})`;

                                // Scroll to the item in the table and highlight it
                                const tableRows = document.querySelectorAll('#inventoryTableBody tr');
                                let foundRow = null;

                                tableRows.forEach(row => {
                                    // Remove any previous highlight
                                    row.classList.remove('found-item-highlight');

                                    // Check if this row contains the serial number we're looking for
                                    const serialCell = row.querySelector('td:nth-child(5)'); // Adjust column index as needed
                                    if (serialCell && serialCell.textContent.trim() === serialNumber) {
                                        foundRow = row;
                                    }
                                });

                                if (foundRow) {
                                    // Add highlight and scroll to the row
                                    foundRow.classList.add('found-item-highlight');
                                    foundRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                } else {
                                    // Item found in database but not visible in current table view
                                    scanResultNotification.classList.remove('success', 'error', 'd-none');
                                    scanResultNotification.classList.add('info');
                                    scanResultStatus.innerHTML = '<i class="bi bi-info-circle"></i>';
                                    scanResultText.textContent = 'Item found but not visible in current view. Applying filter...';

                                    // Apply filter to show the item
                                    document.getElementById('inventorySearchInput').value = serialNumber;
                                    loadInventoryData(serialNumber);
                                }
                            } else {
                                // No item found with that serial number
                                scanResultNotification.classList.remove('d-none', 'success', 'info');
                                scanResultNotification.classList.add('error');
                                scanResultStatus.innerHTML = '<i class="bi bi-x-circle"></i>';
                                scanResultText.textContent = 'No item found with that serial number';

                                // Hide the notification after 5 seconds
                                setTimeout(() => {
                                    scanResultNotification.classList.add('d-none');
                                }, 5000);
                            }
                        })
                        .catch(error => {
                            hideLoading();
                            console.error('Error searching for serial number:', error);

                            // Show error notification
                            scanResultNotification.classList.remove('d-none', 'success', 'info');
                            scanResultNotification.classList.add('error');
                            scanResultStatus.innerHTML = '<i class="bi bi-exclamation-triangle"></i>';
                            scanResultText.textContent = 'Error searching for serial number';

                            // Hide the notification after 5 seconds
                            setTimeout(() => {
                                scanResultNotification.classList.add('d-none');
                            }, 5000);
                        });
                }
            }
        });
    });
}
// Track when serial numbers are scanned (for barcode scanners)
document.addEventListener('keydown', function (e) {
    // Check if we're in an input field already
    if (document.activeElement.tagName === 'INPUT' ||
        document.activeElement.tagName === 'TEXTAREA' ||
        document.activeElement.tagName === 'SELECT') {
        return;
    }

    // If it's a rapid sequence of characters (like from a barcode scanner)
    const now = new Date().getTime();
    if (!window.lastKeypress) window.lastKeypress = now;

    const timeSinceLastKeypress = now - window.lastKeypress;
    window.lastKeypress = now;

    // If keys are pressed within 50ms of each other, it might be a scanner
    if (timeSinceLastKeypress < 50) {
        if (!window.scanBuffer) window.scanBuffer = '';

        // Only add alphanumeric characters or hyphens
        if (/^[a-zA-Z0-9\-]$/.test(e.key)) {
            window.scanBuffer += e.key;
        }

        // If Enter is pressed, process the buffer as a potential serial number
        if (e.key === 'Enter' && window.scanBuffer.length > 3) {
            e.preventDefault();
            console.log('Scanned serial number:', window.scanBuffer);

            // Check if this serial exists in inventory
            fetch(`get_inventory.php?serial=${encodeURIComponent(window.scanBuffer)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.length > 0) {
                        // Found item with this serial number
                        Swal.fire({
                            title: 'Item Found',
                            html: `
                                    <div class="scanned-item-details">
                                        <p><strong>Item:</strong> ${data[0].item_name}</p>
                                        <p><strong>Serial:</strong> ${data[0].serial_number}</p>
                                        <p><strong>Assigned to:</strong> ${data[0].assigned_to || 'N/A'}</p>
                                        <p><strong>Location:</strong> ${data[0].location || 'N/A'}</p>
                                    </div>
                                `,
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonText: 'View Details',
                            cancelButtonText: 'Close'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Switch to inventory section and filter to this item
                                setActiveSection('inventory');

                                // Set search to this serial number
                                const searchInput = document.getElementById('inventorySearchInput');
                                if (searchInput) {
                                    searchInput.value = window.scanBuffer;
                                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            }
                        });
                    } else {
                        // Not found - ask if user wants to add it
                        Swal.fire({
                            title: 'Item Not Found',
                            text: `No item found with serial number: ${window.scanBuffer}. Would you like to add it?`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Add Item',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Open add inventory modal and pre-fill serial number
                                const modal = new bootstrap.Modal(document.getElementById('addInventoryModal'));
                                modal.show();

                                // Set serial number field
                                const serialInput = document.getElementById('serialNumber');
                                if (serialInput) {
                                    serialInput.value = window.scanBuffer;
                                    // Trigger validation
                                    serialInput.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error checking scanned serial number:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to process scanned barcode.',
                        icon: 'error'
                    });
                });

            // Clear scan buffer
            window.scanBuffer = '';
        }
    } else if (timeSinceLastKeypress > 500) {
        // Reset scan buffer if there's a long pause
        window.scanBuffer = '';
        if (/^[a-zA-Z0-9\-]$/.test(e.key)) {
            window.scanBuffer += e.key;
        }
    }
});
// Add this serial number validation function, keep it outside of the DOM loaded event
function validateSerialNumber() {
    console.log('Setting up serial number validation');

    // Get the serial number input field
    const serialInput = document.getElementById('serial_number');
    if (!serialInput) {
        console.warn('Serial number input field not found');
        return;
    }

    // Add event listeners for input changes and blur events
    serialInput.addEventListener('input', debounce(checkSerialNumber, 500));
    serialInput.addEventListener('blur', function () {
        if (serialInput.value.trim()) {
            checkSerialNumber();
        }
    });

    function checkSerialNumber() {
        const serialNumber = serialInput.value.trim();
        if (!serialNumber) {
            resetValidation();
            return;
        }

        console.log('Checking serial number:', serialNumber);

        // Get the current item ID if we're in edit mode
        let itemId = '';
        const saveBtn = document.getElementById('saveItemBtn');
        if (saveBtn && saveBtn.dataset.editMode === 'true' && saveBtn.dataset.itemId) {
            itemId = saveBtn.dataset.itemId;
        }

        // Show checking indicator
        serialInput.classList.remove('is-valid', 'is-invalid');
        serialInput.classList.add('is-validating');

        // Add a small spinner next to the input if not already present
        let spinnerElement = serialInput.nextElementSibling;
        if (!spinnerElement || !spinnerElement.classList.contains('serial-validation-spinner')) {
            spinnerElement = document.createElement('div');
            spinnerElement.className = 'serial-validation-spinner ms-2 spinner-border spinner-border-sm text-primary';
            spinnerElement.setAttribute('role', 'status');
            serialInput.parentNode.appendChild(spinnerElement);
        } else {
            spinnerElement.style.display = 'inline-block';
        }

        // Make an AJAX request to check the serial number
        fetch(`check_serial.php?serial=${encodeURIComponent(serialNumber)}&item_id=${encodeURIComponent(itemId)}`)
            .then(response => response.json())
            .then(data => {
                console.log('Serial check response:', data);

                // Remove the checking indicator
                serialInput.classList.remove('is-validating');
                if (spinnerElement) {
                    spinnerElement.style.display = 'none';
                }

                if (data.success) {
                    if (data.exists) {
                        // Serial exists - show invalid feedback
                        serialInput.classList.add('is-invalid');
                        serialInput.classList.remove('is-valid');

                        // Create or update feedback message
                        let feedbackElement = document.querySelector('.serial-feedback');
                        if (!feedbackElement) {
                            feedbackElement = document.createElement('div');
                            feedbackElement.className = 'invalid-feedback serial-feedback';
                            serialInput.parentNode.appendChild(feedbackElement);
                        }
                        feedbackElement.textContent = 'Serial number already exists in inventory';
                    } else {
                        // Serial is available - show valid feedback
                        serialInput.classList.add('is-valid');
                        serialInput.classList.remove('is-invalid');

                        // Create or update feedback message
                        let feedbackElement = document.querySelector('.serial-feedback');
                        if (!feedbackElement) {
                            feedbackElement = document.createElement('div');
                            feedbackElement.className = 'valid-feedback serial-feedback';
                            serialInput.parentNode.appendChild(feedbackElement);
                        } else {
                            feedbackElement.className = 'valid-feedback serial-feedback';
                        }
                        feedbackElement.textContent = 'Serial number is available';
                    }
                } else {
                    // Error checking serial
                    console.error('Error checking serial number:', data.message);
                    resetValidation();
                }
            })
            .catch(error => {
                console.error('Error checking serial number:', error);
                resetValidation();
                if (spinnerElement) {
                    spinnerElement.style.display = 'none';
                }
            });
    }

    function resetValidation() {
        serialInput.classList.remove('is-valid', 'is-invalid', 'is-validating');
        const feedbackElement = document.querySelector('.serial-feedback');
        if (feedbackElement) {
            feedbackElement.textContent = '';
        }
    }
}
// Helper function for debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Add this to the DOMContentLoaded event to initialize the validation
document.addEventListener('DOMContentLoaded', function () {
    // ... existing code ...

    // Initialize serial number validation
    validateSerialNumber();

    // Reinitialize validation when the modal is shown
    $('#addInventoryModal').on('shown.bs.modal', function () {
        validateSerialNumber();
    });

    // ... existing code ...
});

// ... existing code ...

document.addEventListener('DOMContentLoaded', function () {
    // Show loading indicator
    function showLoading() {
        console.log('Showing loading indicator...');
        // Check if loading indicator already exists
        if (document.getElementById('loadingIndicator')) {
            console.log('Loading indicator already exists, skipping');
            return;
        }

        const loading = document.createElement('div');
        loading.id = 'loadingIndicator';
        loading.innerHTML = `
        <div class="position-fixed w-100 h-100 d-flex flex-column justify-content-center align-items-center" 
        style="background: rgba(255,255,255,0.9); top: 0; left: 0; z-index: 9999;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-3 text-primary">Loading data...</div>
        </div>`;
        document.body.appendChild(loading);

        // Set a timeout to auto-hide loading if it takes too long
        window.loadingTimeout = setTimeout(() => {
            console.warn('Loading indicator timeout after 30 seconds');
            hideLoading();
            if (typeof Swal !== 'undefined') {
                Swal.fire('Timeout', 'The operation took too long. Please try again.', 'warning');
            } else {
                alert('The operation took too long. Please try again.');
            }
        }, 30000);
    }

    // Hide loading indicator
    function hideLoading() {
        console.log('Hiding loading indicator...');
        // Clear the loading timeout if it exists
        if (window.loadingTimeout) {
            clearTimeout(window.loadingTimeout);
            window.loadingTimeout = null;
        }

        const loading = document.getElementById('loadingIndicator');
        if (loading) {
            loading.remove();
            console.log('Loading indicator removed');
        } else {
            console.log('No loading indicator found to hide');
        }
    }
});

// Set active section with data loading
function setActiveSection(section, skipSave = false) {
    showLoading();

    // Log the requested section for debugging
    console.log(`Setting active section to: ${section}`);

    setTimeout(() => {
        try {
            // Hide all sections first
            const allSections = document.querySelectorAll('.dashboard-section, .inventory-section, .po-section, .par-section');
            if (allSections.length === 0) {
                console.error('No section elements found in the DOM');
                showError('Page sections not found. Please refresh the page.');
                hideLoading();
                return;
            }

            allSections.forEach(el => el.classList.add('d-none'));

            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));

            const sectionMap = {
                'dashboard': {
                    section: '.dashboard-section',
                    link: '#dashboard-link',
                    icon: '<i class="bi bi-grid"></i> Dashboard'
                },
                'inventory': {
                    section: '.inventory-section',
                    link: '#inventory-link',
                    icon: '<i class="bi bi-box-seam"></i> Inventory'
                },
                'po': {
                    section: '.po-section',
                    link: '#po-link',
                    icon: '<i class="bi bi-cart3"></i> Purchase Orders'
                },
                'par': {
                    section: '.par-section',
                    link: '#par-link',
                    icon: '<i class="bi bi-receipt"></i> Property Acknowledgement Receipts'
                }
            };

            if (sectionMap[section]) {
                // Get the section element
                const sectionElement = document.querySelector(sectionMap[section].section);
                if (!sectionElement) {
                    console.error(`Section element not found: ${sectionMap[section].section}`);
                    showError(`Section "${section}" not found. Please refresh the page.`);
                    hideLoading();
                    return;
                }

                // Show the active section
                sectionElement.classList.remove('d-none');
                sectionElement.style.display = 'block'; // Force display block

                // Activate the nav link
                const navLink = document.querySelector(sectionMap[section].link);
                if (navLink) {
                    navLink.classList.add('active');
                }

                // Update header if it exists
                const headerEl = document.querySelector('.header h4');
                if (headerEl) {
                    headerEl.innerHTML = sectionMap[section].icon;
                }

                // Save active section to localStorage if not skipping
                if (!skipSave) {
                    localStorage.setItem('activeSection', section);
                    localStorage.setItem('activeSectionLink', sectionMap[section].link);
                }
                
                // Load data for the active section
                console.log(`Loading data for section: ${section}`);
                try {
                    // Track loading state to prevent recursive calls
                    if (!window.sectionDataLoading) {
                        window.sectionDataLoading = true;

                        if (section === 'inventory') {
                            loadInventoryData();
                        }
                        else if (section === 'po') {
                            loadPOData();
                        }
                        else if (section === 'par') {
                            loadPARData();
                        }
                        else if (section === 'dashboard') {
                            // Prevent dashboard loading loops
                            if (!window.dashboardLoadAttempts) {
                                window.dashboardLoadAttempts = 0;
                            }

                            window.dashboardLoadAttempts++;

                            // If we've tried to load dashboard too many times, switch to inventory
                            if (window.dashboardLoadAttempts > 3) {
                                console.error('Too many dashboard load attempts, switching to inventory');
                                localStorage.setItem('activeSection', 'inventory');
                                setActiveSection('inventory', true);
                                return;
                            }

                            if (typeof loadDashboardStats === 'function') {
                                loadDashboardStats();
                            } else {
                                console.warn('loadDashboardStats function not available');
                            }
                        }

                        // Reset loading flag after a delay
                        setTimeout(() => {
                            window.sectionDataLoading = false;
                        }, 1000);
                    } else {
                        console.warn('Section data already loading, skipping duplicate load');
                    }
                } catch (dataError) {
                    console.error(`Error loading data for section ${section}:`, dataError);
                    showError(`Failed to load data for ${section}: ${dataError.message}`);
                    window.sectionDataLoading = false;
                }
            } else {
                console.error(`Unknown section: ${section}`);
                showError(`Unknown section: ${section}`);
            }
        } catch (error) {
            console.error('Error setting active section:', error);
            showError(`Failed to change section: ${error.message}`);
        } finally {
            hideLoading();
        }
    }, 100); // Reduced timeout for faster section switching
}
// Navigation event listeners
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', function (e) {
        e.preventDefault();
        const section = this.id.replace('-link', '');
        setActiveSection(section);
    });
});

// Handle page load
window.addEventListener('load', function () {
    // Prevent loading loops - only set section if not already set
    if (!window.initialSectionLoaded) {
        window.initialSectionLoaded = true;

        // Safety check to avoid potential dashboard loop
        let savedSection = localStorage.getItem('activeSection') || 'inventory';

        // If last section was dashboard (which might be causing issues), default to inventory
        if (savedSection === 'dashboard' && window.location.search.includes('resetDashboard=true')) {
            console.log('Resetting from dashboard to inventory section due to URL parameter');
            savedSection = 'inventory';
            localStorage.setItem('activeSection', 'inventory');
        }

        console.log('Initial section load:', savedSection);
        setActiveSection(savedSection, true);
    }

// PO Items table functionality
const addRowBtn2 = document.getElementById('addRow');
const poItemsTable2 = document.getElementById('poItemsTable')?.getElementsByTagName('tbody')[0];
if (addRowBtn2 && poItemsTable2) {
    addRowBtn2.addEventListener('click', function () {
        const newRow = poItemsTable2.insertRow();
        newRow.innerHTML = `
        <td><input type="text" class="form-control form-control-sm item-name" placeholder="Item"></td>
        <td><input type="text" class="form-control form-control-sm item-unit" placeholder="Unit"></td>
        <td><textarea class="form-control form-control-sm item-description" placeholder="Description" rows="2" style="min-height: 60px;"></textarea></td>
        <td><input type="number" class="form-control form-control-sm qty" placeholder="1" min="1" value="1" step="1"></td>
        <td><input type="number" class="form-control form-control-sm unit-cost" placeholder="0.00" min="0" value="0.00" step="0.01"></td>
        <td><input type="text" class="form-control form-control-sm amount" placeholder="0.00" value="0.00" readonly></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm remove-row">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
        });
    }
        // Add event listeners to the new row for amount calculations
        addPORowEventListeners(newRow);

    // Add event listener to remove button
    const removeBtn = newRow.querySelector('.remove-row');
    removeBtn.addEventListener('click', function () {
        poItemsTable2.removeChild(newRow);
        calculatePOTotal();
    });

        // Focus on the first input of the new row
    const qtyInput = newRow.querySelector('.qty');
    qtyInput.addEventListener('input', function () {
        updateAmount(newRow);
        calculateTotal();
    });

    const unitCostInput = newRow.querySelector('.unit-cost');
    unitCostInput.addEventListener('input', function () {
        updateAmount(newRow);
        calculateTotal();
    });

    // Focus on the first input of the new row
    newRow.querySelector('input').focus();
// PAR Items table functionality
const addParRowBtn2 = document.getElementById('addParRow');
const parItemsTable2 = document.getElementById('parItemsTable')?.getElementsByTagName('tbody')[0];
if (addParRowBtn2 && parItemsTable2) {
    addParRowBtn2.addEventListener('click', function () {
        const newRow = parItemsTable2.insertRow();
        const removeBtn = newRow.querySelector('.remove-row');
        removeBtn.addEventListener('click', function () {
            parItemsTable2.removeChild(newRow);
            calculateParTotal();
        });
        newRow.querySelector('.par-amount').addEventListener('input', calculateParTotal);
    });
}
// Calculation functions
function addRowEventListeners(row) {
    const qtyInput = row.querySelector('.qty');
    const unitCostInput = row.querySelector('.unit-cost');
    const amountInput = row.querySelector('.amount');
    const removeBtn = row.querySelector('.remove-row');

    function calculateAmount() {
        const qty = parseFloat(qtyInput.value) || 0;
        const unitCost = parseFloat(unitCostInput.value) || 0;
        const amount = qty * unitCost;
        amountInput.value = amount.toFixed(2);
        calculateTotal();
    }

    qtyInput?.addEventListener('input', calculateAmount);
    unitCostInput?.addEventListener('input', calculateAmount);
    removeBtn?.addEventListener('click', function () {
        poItemsTable2.removeChild(row);
        calculateTotal();
    });
}
// Utility function to format currency amounts consistently
function formatCurrency(amount) {
    // Convert to number first
    const numericAmount = typeof amount === 'string' ?
        parseFloat(amount.replace(/[^\d.-]/g, '')) :
        parseFloat(amount) || 0;

    // Format with no space after peso sign
    return '₱' + numericAmount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}
// Utility function to parse currency values consistently
function parseCurrency(currencyString) {
    if (!currencyString) return 0;
    return parseFloat(currencyString.replace(/[^\d.-]/g, '')) || 0;
}

// Function to update amount in a row
function updateAmount(row) {
    // Try both class-based and position-based selectors to ensure compatibility
    const qtyInput = row.querySelector('.qty') || row.querySelector('input:nth-child(4)');
    const unitCostInput = row.querySelector('.unit-cost') || row.querySelector('input:nth-child(5)');
    const amountInput = row.querySelector('.amount') || row.querySelector('input:nth-child(6) input');

    if (!qtyInput || !unitCostInput || !amountInput) {
        console.error('Required inputs not found in row:', row);
        return;
    }

    // Parse the quantity and unit cost values
    const qty = parseInt(qtyInput.value) || 0;
    // Clean the unit cost value by removing any currency symbols
    const unitCostValue = unitCostInput.value.replace(/[^\d.-]/g, '');
    const unitCost = parseFloat(unitCostValue) || 0;

    // Calculate the amount
    const amount = qty * unitCost;

    // Store the raw amount in a data attribute for calculations
    amountInput.dataset.rawAmount = amount.toFixed(2);

    // Format the amount for display - use peso sign "₱"
    amountInput.value = '₱' + amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    // Call calculatePOTotal to update the grand total
    if (document.getElementById('totalAmount')) {
        calculatePOTotal();
    }
}

function calculateTotal() {
    const totalAmountField = document.getElementById('totalAmount');
    const obligationAmount = document.getElementById('obligationAmount');

    if (totalAmountField) {
        let total = 0;

        // Get all amount fields and sum their values
        document.querySelectorAll('table tbody tr .amount').forEach(field => {
            // Use the raw amount if available
            if (field.dataset.rawAmount) {
                total += parseFloat(field.dataset.rawAmount) || 0;
            } else {
                // Otherwise, parse the formatted value
                const value = field.value.replace(/[^0-9.-]+/g, '');
                total += parseFloat(value) || 0;
            }
        });

        // Store the raw total value
        totalAmountField.dataset.rawAmount = total.toFixed(2);

        // Format for display - no space after peso sign to match image
        totalAmountField.value = '₱' + total.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Update obligation amount if it exists
        if (obligationAmount) {
            obligationAmount.dataset.rawAmount = total.toFixed(2);
            obligationAmount.value = '₱' + total.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    }
}

// Global calculateAmount function for use with event delegation
function calculateAmount(row) {
    if (!row) return;

    const qtyInput = row.querySelector('.qty');
    const unitCostInput = row.querySelector('.unit-cost');
    const amountInput = row.querySelector('.amount');

    if (!qtyInput || !unitCostInput || !amountInput) return;

    const qty = parseFloat(qtyInput.value) || 0;
    const unitCost = parseFloat(unitCostInput.value) || 0;
    const amount = qty * unitCost;

    // Update the amount field - format with commas but no spaces
    amountInput.value = amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");

    // Call calculate total to update the grand total
    calculateTotal();
}

function calculateParTotal() {
    let total = 0;
    document.querySelectorAll('#parItemsTable tbody tr').forEach(row => {
        const amount = parseFloat(row.querySelector('.amount')?.value.replace(/[^\d.-]/g, '')) || 0;
        total += amount;
    });
    return total;
}

document.querySelectorAll('#poItemsTable tbody tr').forEach(addRowEventListeners);
calculateTotal();
calculateParTotal();



// Enhanced delete item function
function deleteItem(itemId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();

            // Try the direct path first
            fetch('delete_item.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_id: itemId
                })
            })
                .then(response => {
                    if (!response.ok) {
                        // If direct path fails, try the Learning/ path
                        return fetch('Learning/delete_item.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                item_id: itemId
                            })
                        });
                    }
                    return response;
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        Swal.fire('Deleted!', 'The item has been deleted.', 'success');
                        loadInventoryData();
                    } else {
                        throw new Error(data.message || 'Failed to delete item');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    Swal.fire('Error!', 'Failed to delete item: ' + error.message, 'error');
                });
        }
    });
}

// Function to handle item editing
function editItem(itemId) {
    showLoading();
    fetch(`get_item.php?id=${itemId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load item details');
            }
            
            const item = data;
            
            // Populate modal with item data
            const modal = new bootstrap.Modal(document.getElementById('addInventoryModal'));
            const modalTitleElement = document.getElementById('addInventoryModalLabel');
            if (modalTitleElement) {
                modalTitleElement.innerHTML = '<i class="bi bi-pencil"></i> Edit Inventory Item';
            }

            // Populate form fields
            document.getElementById('itemID').value = item.item_id;
            document.getElementById('itemName').value = item.item_name || '';

            // Handle the Brand/Model field which has a slash in the ID
            const brandModelField = document.getElementById('Brand/model') || document.getElementById('brandModel');
            if (brandModelField) {
                brandModelField.value = item.brand_model || '';
            }

            document.getElementById('serialNumber').value = item.serial_number || '';
            document.getElementById('purchaseDate').value = item.purchase_date || '';
            document.getElementById('warrantyDate').value = item.warranty_expiration || '';
            document.getElementById('assignedTo').value = item.assigned_to || '';

            // Handle location field - might be ID or name
            const locationField = document.getElementById('location');
            if (locationField) {
                locationField.value = item.location || '';
            }

            // Handle condition dropdown
            const conditionField = document.getElementById('condition');
            if (conditionField) {
                conditionField.value = item.condition || 'Good';
            }

            document.getElementById('notes').value = item.notes || '';

            // Change save button to update
            const saveBtn = document.getElementById('saveItemBtn');
            if (saveBtn) {
                saveBtn.textContent = 'Update Item';
                saveBtn.dataset.editMode = 'true';
                saveBtn.dataset.itemId = itemId;
            }

            modal.show();
        })
        .catch(error => {
            console.error('Error loading item for editing:', error);
            Swal.fire('Error!', 'Error loading item details: ' + error.message, 'error');
        })
        .finally(() => {
            hideLoading();
        });
}
// Function to view location history
function viewLocationHistory(itemId) {
    fetch(`get_location_history.php?item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const history = data.history;
                if (history.length === 0) {
                    Swal.fire({
                        title: 'No Location History',
                        text: 'This item has no location change history.',
                        icon: 'info',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Create history HTML
                const historyHtml = history.map(entry => `
            <div class="location-history-entry">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${entry.new_location}</strong>
                        <br>
                        <small class="text-muted">
                            From: ${entry.previous_location || 'None'}
                        </small>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">
                            ${new Date(entry.change_date).toLocaleString()}
                        </small>
                        <br>
                        <small class="text-muted">
                            By: ${entry.changed_by}
                        </small>
                    </div>
                </div>
                ${entry.notes ? `<small class="text-muted mt-2 d-block">${entry.notes}</small>` : ''}
            </div>
        `).join('<hr>');

                // Show modal with history
                Swal.fire({
                    title: 'Location History',
                    html: `
            <div class="location-history-container">
                ${historyHtml}
            </div>
        `,
                    width: '600px',
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        container: 'location-history-modal'
                    }
                });
            } else {
                throw new Error(data.message || 'Failed to fetch location history');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error!', error.message, 'error');
        });
}

// Modify the existing trackLocation function to include history
function trackLocation(location) {
    // Get all items assigned to this location
    const items = filteredData.filter(item => item.assigned_to === location);

    if (items.length === 0) {
        Swal.fire({
            title: 'No Items Found',
            text: `No items are currently assigned to ${location}`,
            icon: 'info',
            confirmButtonText: 'OK'
        });
        return;
    }
}
const itemsList = items.map(item => `
    <div class="location-item">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>${item.item_name}</strong>
                <br>
                <small class="text-muted">Serial: ${item.serial_number}</small>
                <br>
                <span class="badge ${getConditionBadgeClass(item.condition)}">${item.condition}</span>
            </div>
            <button class="btn btn-sm btn-outline-primary" onclick="viewLocationHistory('${item.item_id}')">
                <i class="bi bi-clock-history"></i> History
            </button>
        </div>
    </div>
    `).join('<hr>');

// Show modal with items at this location
Swal.fire({
    title: `Items at ${location}`,
    html: `
        <div class="location-items">
            ${itemsList}
        </div>
    `,
    width: '600px',
    showCloseButton: true,
    showConfirmButton: false,
    customClass: {
        container: 'location-modal'
    }
});
// Restore deleted PO data section
document.getElementById('savePoBtn')?.addEventListener('click', function () {
    const form = document.querySelector('#addPOModal form');

    // Validate the form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Collect items from table
    const items = [];
    document.querySelectorAll('#poItemsTable tbody tr').forEach(row => {
        // Check if the row has data
        const inputs = row.querySelectorAll('input, textarea');
        if (inputs.length >= 6) {
            const itemName = inputs[0].value.trim();
            const unit = inputs[1].value.trim();
            const description = inputs[2].value.trim();
            const qty = parseFloat(inputs[3].value) || 0;
            const unitCost = parseFloat(inputs[4].value) || 0;
            const amount = parseFloat(inputs[5].value) || 0;

            if (itemName && qty > 0 && unitCost > 0) {
                items.push({
                    item_description: itemName,
                    unit: unit,
                    description: description,
                    quantity: qty,
                    unit_cost: unitCost,
                    amount: amount
                });
            }
        }
    });

    // Check if we have items
    if (items.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'No Items Added',
            text: 'Please add at least one item to the purchase order.'
        });
        return;
    }

    // Collect form data
    const poData = {
        po_no: document.getElementById('poNo').value,
        ref_no: document.getElementById('refNo').value,
        supplier_name: document.getElementById('supplier').value,
        supplier_address: document.getElementById('supplierAddress').value,
        email: document.getElementById('emailAddress').value,
        tel: document.getElementById('telephoneNo').value,
        po_date: document.getElementById('poDate').value,
        mode_of_procurement: document.getElementById('modeOfProcurement').value,
        pr_no: document.getElementById('prNo').value,
        pr_date: document.getElementById('prDate').value,
        place_of_delivery: document.getElementById('placeOfDelivery').value,
        delivery_date: document.getElementById('deliveryDate').value,
        payment_term: document.getElementById('paymentTerm').value,
        delivery_term: document.getElementById('deliveryTerm').value,
        obligation_request_no: document.getElementById('obligationRequestNo').value,
        obligation_amount: parseFloat(document.getElementById('obligationAmount').value) || 0,
        total_amount: parseCurrency(document.getElementById('totalAmount').value),
        items: items
    };

    // Validate required fields
    if (!poData.po_no || !poData.supplier_name) {
        Swal.fire({
            icon: 'error',
            title: 'Required Fields Missing',
            text: 'Please fill in required fields (PO No, Supplier) and add at least one item'
        });
        return;
    }

    // Show loading indicator
    const saveBtn = document.getElementById('savePoBtn');
    const originalBtnText = saveBtn.textContent;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

    // Check if it's an edit operation
    const isEdit = saveBtn.dataset.editMode === 'true';
    const poId = saveBtn.dataset.poId;

    if (isEdit && poId) {
        poData.id = poId;
        poData.po_id = poId;
    }

    // Determine which endpoint to use
    const url = isEdit ? 'update_po.php' : 'add_po.php';

    console.log('Saving PO data:', poData);

    // Send the data to the server
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(poData)
    })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    throw new Error('Server Error: ' + (text || 'Unknown error'));
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('PO saved successfully:', data);
            
            // Hide loading indicator
            if (typeof hideLoading === 'function') {
                hideLoading();
            }
            
            Swal.fire({
                title: 'Success!',
                text: 'Purchase Order saved successfully',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                // Reset form
                const form = document.querySelector('#addPOModal form');
                if (form) form.reset();
                
                // Clear table
                document.querySelectorAll('#poItemsTable tbody tr:not(.d-none)').forEach(row => {
                    if (!row.classList.contains('template-row')) {
                        row.remove();
                    }
                });
                
                // Close modal
                const poModal = document.getElementById('addPOModal');
                if (poModal && typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getInstance(poModal);
                    if (modal) modal.hide();
                }
                
                // Reload PO data
                if (typeof loadPOData === 'function') {
                    loadPOData();
                } else {
                    // Don't reload the page as it might cause issues
                    console.warn('loadPOData function not found');
                }
            });
        })
        .catch(error => {
            console.error('Error saving PO:', error);
            
            // Hide loading indicator
            if (typeof hideLoading === 'function') {
                hideLoading();
            }
            
            Swal.fire({
                title: 'Error!',
                text: 'Failed to save Purchase Order: ' + error.message,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        })
        .finally(() => {
            // Always re-enable button
            this.disabled = false;
            this.innerHTML = originalBtnText;
        });
});
// Add automatic amount calculation for PO items
document.querySelector('#poItemsTable')?.addEventListener('input', function (e) {
    if (e.target.classList.contains('qty') || e.target.classList.contains('unit-cost')) {
        const row = e.target.closest('tr');
        if (!row) return;

        const qtyInput = row.querySelector('.qty');
        const unitCostInput = row.querySelector('.unit-cost');
        const amountInput = row.querySelector('.amount');

        if (!qtyInput || !unitCostInput || !amountInput) return;

        // Clean and validate qty input
        if (e.target.classList.contains('qty')) {
            // Remove any non-numeric characters
            qtyInput.value = qtyInput.value.replace(/[^\d]/g, '');

            // Ensure minimum value is 1
            if (qtyInput.value === '' || parseInt(qtyInput.value) < 1) {
                qtyInput.value = '1';
            }
        }

        // Clean and validate unit cost input
        if (e.target.classList.contains('unit-cost')) {
            // Remove any non-numeric or decimal characters
            unitCostInput.value = unitCostInput.value.replace(/[^\d.]/g, '');

            // Enforce proper decimal format
            const parts = unitCostInput.value.split('.');
            if (parts.length > 2) {
                // Keep only the first decimal point
                unitCostInput.value = parts[0] + '.' + parts.slice(1).join('');
            }
        }

        // Parse values with appropriate defaults
        const qty = parseInt(qtyInput.value) || 1;
        const unitCost = parseFloat(unitCostInput.value) || 0;

        // Calculate and update amount
        const amount = qty * unitCost;
        amountInput.value = amount.toFixed(2);

        // Update the total
        calculatePOTotal();
    }
});

// Initialize event listeners when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {
    // Setup PO items table calculations
    const poItemsTable = document.getElementById('poItemsTable');
    if (poItemsTable) {
        // Initialize existing rows
        const rows = poItemsTable.querySelectorAll('tbody tr');
        rows.forEach(row => {
            addPORowEventListeners(row);
        });

        // Calculate initial total
        calculatePOTotal();

            // Set up listeners for input events at the table level
            poItemsTable.addEventListener('focus', function (e) {
                if (e.target.classList.contains('qty') || e.target.classList.contains('unit-cost')) {
                    // Store original value for comparison
                    e.target.dataset.originalValue = e.target.value;
                }
            }, true);

            poItemsTable.addEventListener('blur', function (e) {
                if (e.target.classList.contains('qty')) {
                    // Ensure minimum value
                    if (e.target.value === '' || parseInt(e.target.value) < 1) {
                        e.target.value = '1';
                        const row = e.target.closest('tr');
                        if (row) updatePORowAmount(row);
                    }
                }

                if (e.target.classList.contains('unit-cost')) {
                    // Format to 2 decimal places on blur
                    if (e.target.value !== '') {
                        const value = parseFloat(e.target.value);
                        e.target.value = value.toFixed(2);
                        const row = e.target.closest('tr');
                        if (row) updatePORowAmount(row);
                    }
                }
            }, true);
        }
    });

// Function to view a PO
function viewPO(poId) {
    if (!poId) {
        Swal.fire('Error', 'PO ID is required', 'error');
        return;
    }

    console.log('Viewing PO with ID:', poId);
    
    // Get the PO data first
    getPOData(poId, function(poData) {
        // Format the items array to ensure it has all required fields
        const formattedItems = (poData.items || []).map(item => {
            return {
                id: item.id || item.item_id || null,
                unit: item.unit || 'pc',
                description: item.description || item.item_description || '',
                item_description: item.item_description || item.description || '',
                quantity: parseFloat(item.quantity || item.qty || 0),
                unit_cost: parseFloat(item.unit_cost || item.unit_price || item.price || 0),
                amount: parseFloat(item.amount || item.total_cost || item.total || 0)
            };
        });
        
        // Create a properly structured data object with all required fields
        const completePoData = {
            po_id: poData.po_id || poData.id,
            po_no: poData.po_no || poData.po_number || '',
            po_date: poData.po_date || poData.date || new Date().toISOString().split('T')[0],
            pr_no: poData.pr_no || poData.pr_number || '',
            pr_date: poData.pr_date || '',
            supplier_name: poData.supplier_name || poData.supplier || '',
            supplier_address: poData.supplier_address || poData.address || '',
            supplier_email: poData.supplier_email || poData.email || '',
            supplier_tel: poData.supplier_tel || poData.tel || poData.telephone || '',
            place_of_delivery: poData.place_of_delivery || poData.delivery_place || '',
            delivery_date: poData.delivery_date || '',
            delivery_term: poData.delivery_term || poData.delivery_terms || '60 days from receipt of Purchase Order',
            payment_term: poData.payment_term || poData.payment_terms || 'Full Payment on Full Delivery',
            mode_of_procurement: poData.mode_of_procurement || poData.procurement_mode || 'Public Bidding',
            total_amount: parseFloat(poData.total_amount || poData.total || 0),
            obligation_request_no: poData.obligation_request_no || '',
            obligation_amount: parseFloat(poData.obligation_amount || poData.total_amount || 0),
            ref_no: poData.ref_no || '',
            items: formattedItems
        };
        
        // Create a properly structured data object for the modal
        const modalData = {
            po_details: completePoData,
            items: formattedItems
        };
        
        // Encode the data as JSON, then URL encode it
        const jsonString = JSON.stringify(modalData);
        const encodedData = encodeURIComponent(jsonString);
        
        // Log data size for debugging
        console.log('Modal data size (bytes):', encodedData.length);
        
        // Open in a new window with the data
        window.open(`viewPO.php?id=${encodeURIComponent(poId)}&modal_data=${encodedData}`, '_blank');
    });
}

// Function to print a PO
function printPO() {
    // Hide elements not needed for printings
    const elementsToHide = document.querySelectorAll('.no-print, .sidebar, .header');
    elementsToHide.forEach(el => {
        el.dataset.originalDisplay = el.style.display;
        el.style.display = 'none';
    });

    // Add a print-specific class to the body
    document.body.classList.add('printing');

    // Print the document
    window.print();

    // Restore the elements after printing
    setTimeout(() => {
        elementsToHide.forEach(el => {
            el.style.display = el.dataset.originalDisplay || '';
        });
        document.body.classList.remove('printing');
    }, 500);
}

// Fix the loadInventoryData function to handle server failures better
function loadInventoryData(searchQuery = '') {
    console.log(`Loading inventory data with search query: "${searchQuery}"`);

    // Ensure we're using a consistent endpoint path
    const url = searchQuery
        ? `get_inventory.php?search=${encodeURIComponent(searchQuery)}`
        : 'get_inventory.php';

    showLoading();

    console.log('Fetching inventory data from:', url);

    fetch(url)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text.substring(0, 200)}...`);
                });
            }
            return response.json();
        })
        .then(response => {
            console.log('Data received:', response);

            // Handle different response formats
            let data = response;
            if (response && response.success === false) {
                throw new Error(response.message || 'Unknown error occurred');
            } else if (response && response.data) {
                data = response.data;
            }

            // Store data globally
            window.filteredData = data;

            // Update the table
            updateInventoryTable(data);

            // Run additional functions
            checkWarrantyStatus();
            updateConditionStatus();

            hideLoading();
        })
        .catch(error => {
            console.error('Failed to load inventory data:', error);
            showError('Failed to load inventory data. Please try again.');
            hideLoading();
        });
}

// Initialize the inventory system
function initInventorySystem() {
    console.log('Initializing inventory system...');

    // Load initial data
    loadInventoryData();

    // Add search functionality if search input exists
    const searchInput = document.getElementById('inventorySearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function () {
            loadInventoryData(this.value);
        }, 500));
    }

    // Add refresh button functionality if it exists
    const refreshButton = document.getElementById('refreshInventory');
    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            loadInventoryData();
        });
    }

    console.log('Inventory system initialized');
}

// Document ready function
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM fully loaded');

    // Initialize the inventory system when the page loads
    if (document.getElementById('inventoryTableBody')) {
    initInventorySystem();
    }
    });
});

// Add missing utility functions
function showLoading() {
    console.log('Showing loading indicator...');
    // Check if loading indicator already exists
    if (document.getElementById('loadingIndicator')) {
        console.log('Loading indicator already exists, skipping');
        return;
    }

    const loading = document.createElement('div');
    loading.id = 'loadingIndicator';
    loading.innerHTML = `
    <div class="position-fixed w-100 h-100 d-flex flex-column justify-content-center align-items-center" 
    style="background: rgba(255,255,255,0.9); top: 0; left: 0; z-index: 9999;">
    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
    <span class="visually-hidden">Loading...</span>
    </div>
    <div class="mt-3 text-primary">Loading data...</div>
    </div>`;
    document.body.appendChild(loading);

    // Set a timeout to auto-hide loading if it takes too long
    window.loadingTimeout = setTimeout(() => {
        console.warn('Loading indicator timeout after 30 seconds');
        hideLoading();
        if (typeof Swal !== 'undefined') {
            Swal.fire('Timeout', 'The operation took too long. Please try again.', 'warning');
        } else {
            alert('The operation took too long. Please try again.');
        }
    }, 30000);
}

function hideLoading() {
    console.log('Hiding loading indicator...');
    // Clear the loading timeout if it exists
    if (window.loadingTimeout) {
        clearTimeout(window.loadingTimeout);
        window.loadingTimeout = null;
    }

    const loading = document.getElementById('loadingIndicator');
    if (loading) {
        loading.remove();
        console.log('Loading indicator removed');
    } else {
        console.log('No loading indicator found to hide');
    }
}

function showError(message) {
    console.error('Error:', message);
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message,
            timer: 3000
        });
    } else {
        const errorContainer = document.getElementById('error-container');
        if (errorContainer) {
            errorContainer.textContent = message;
            errorContainer.style.display = 'block';

            // Auto-hide error after a few seconds
            setTimeout(() => {
                errorContainer.style.display = 'none';
            }, 5000);
        } else {
            alert(message);
        }
    }
}

// Define the updateInventoryTable function if it doesn't exist
function updateInventoryTable(data) {
    console.log('Updating inventory table with data:', data);

    // Get the table body element
    const tableBody = document.getElementById('inventoryTableBody');
    if (!tableBody) {
        console.error('Inventory table body not found');
        return;
    }

    // Clear existing rows
    tableBody.innerHTML = '';

    // Check if data is valid
    if (!data || (!Array.isArray(data) && !data.data)) {
        console.error('Invalid inventory data format:', data);
        tableBody.innerHTML = `<tr><td colspan="11" class="text-center">No inventory data available</td></tr>`;
        return;
    }

    // Extract the data array
    const items = Array.isArray(data) ? data : (data.data || []);

    if (items.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="11" class="text-center">No inventory items found</td></tr>`;
        return;
    }

    // Add rows for each inventory item
    items.forEach(item => {
        const row = document.createElement('tr');

        // Format dates if available
        const purchaseDate = item.purchase_date ? new Date(item.purchase_date).toLocaleDateString() : 'N/A';
        const warrantyDate = item.warranty_expiration ? new Date(item.warranty_expiration).toLocaleDateString() : 'N/A';

        // Calculate warranty status
        let warrantyStatus = 'N/A';
        let warrantyBadgeClass = 'bg-secondary';
        
        if (item.warranty_expiration) {
            const now = new Date();
            const warrantyDate = new Date(item.warranty_expiration);
            
            const daysRemaining = Math.floor((warrantyDate - now) / (1000 * 60 * 60 * 24));
            
            if (daysRemaining > 90) {
                warrantyStatus = 'ACTIVE';
                warrantyBadgeClass = 'bg-success';
            } else if (daysRemaining > 0) {
                warrantyStatus = `${daysRemaining} days left`;
                warrantyBadgeClass = 'bg-warning text-dark';
            } else {
                warrantyStatus = 'EXPIRED';
                warrantyBadgeClass = 'bg-danger';
            }
        }

        // Get condition badge class
        const conditionClass = getConditionBadgeClass(item.condition || 'Unknown');

        row.innerHTML = `
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-sm btn-primary edit-item" data-id="${item.item_id}">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-item" data-id="${item.item_id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
            <td>${item.item_id || ''}</td>
            <td>${item.item_name || ''}</td>
            <td>${item.brand_model || ''}</td>
            <td>${item.serial_number || ''}</td>
            <td>${purchaseDate}</td>
            <td class="warranty-column">
                <span class="badge ${warrantyBadgeClass}">${warrantyStatus}</span>
            </td>
            <td>${item.assigned_to || ''}</td>
            <td>${item.location || ''}</td>
            <td><span class="badge ${conditionClass}">${item.condition || 'Unknown'}</span></td>
            <td>${item.notes || ''}</td>
        `;

        tableBody.appendChild(row);
    });

    // Add event listeners for action buttons
    document.querySelectorAll('.edit-item').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            editItem(itemId);
        });
    });

    document.querySelectorAll('.delete-item').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            deleteItem(itemId);
        });
    });
}

// Update the getConditionBadgeClass function to handle the condition values correctly
function getConditionBadgeClass(condition) {
    switch(condition) {
        case 'New':
            return 'bg-success';
        case 'Good':
            return 'bg-info';
        case 'Fair':
            return 'bg-warning text-dark';
        case 'Poor':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Function to add event listeners to action buttons
function addActionButtonListeners() {
    // Add event listeners to edit buttons
    document.querySelectorAll('.edit-item').forEach(button => {
        button.addEventListener('click', function () {
            const itemId = this.getAttribute('data-id');
            if (typeof editItem === 'function') {
                editItem(itemId);
    } else {
                console.error('editItem function not defined');
            }
        });
    });

    // Add event listeners to delete buttons
    document.querySelectorAll('.delete-item').forEach(button => {
        button.addEventListener('click', function () {
            const itemId = this.getAttribute('data-id');
            if (typeof deleteItem === 'function') {
                deleteItem(itemId);
            } else {
                console.error('deleteItem function not defined');
            }
        });
    });
}

// Define placeholder functions if they don't exist
function checkWarrantyStatus() {
    console.log('Checking warranty status for inventory items');

    // Get all inventory items from both table and modal
    const inventoryItems = document.querySelectorAll('.inventory-item, .item-row, #inventoryModal .modal-body tr');

    if (!inventoryItems || inventoryItems.length === 0) {
        console.log('No inventory items found to check warranty');
        return;
    }

    console.log(`Found ${inventoryItems.length} inventory items to check warranty status`);

    // Process each item
    inventoryItems.forEach(item => {
        try {
            // Get all possible status elements (both in table and modal)
            const statusElements = [
                item.querySelector('.warranty-status'),
                item.querySelector('.badge-warranty'),
                item.querySelector('td.warranty-column .badge'),
                item.querySelector('[data-warranty-status]')
            ].filter(el => el); // Remove null elements

            if (statusElements.length === 0) {
                console.log('No warranty status element found for item');
        return;
    }

            // Update each status element found
            statusElements.forEach(statusElement => {
                // Remove all existing badge classes
                statusElement.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'bg-secondary');

                // Get the current status text
                const currentStatus = statusElement.textContent.trim().toUpperCase();
                
                // If current status is EXPIRED, keep it EXPIRED, otherwise show as ACTIVE
                if (currentStatus === 'EXPIRED') {
                    statusElement.textContent = 'EXPIRED';
                    statusElement.classList.add('badge', 'bg-danger');
    } else {
                    statusElement.textContent = 'ACTIVE';
                    statusElement.classList.add('badge', 'bg-success');
                }

                // Keep consistent styling
                statusElement.style.fontWeight = 'bold';
                statusElement.style.letterSpacing = '0.5px';
                statusElement.style.padding = '5px 8px';
                statusElement.style.fontSize = '0.85rem';
            });

            // Remove any days-left display since we're not using dates anymore
            const daysLeftElement = item.querySelector('.days-left');
            if (daysLeftElement) {
                daysLeftElement.remove();
            }
        } catch (error) {
            console.error('Error checking warranty for item:', error);
        }
    });
}

function updateConditionStatus() {
    console.log('Updating condition status for inventory items');

    // Get all inventory items with condition information
    const inventoryItems = document.querySelectorAll('.inventory-item, .item-row');

    if (!inventoryItems || inventoryItems.length === 0) {
        console.log('No inventory items found to update conditions');
        return;
    }

    console.log(`Found ${inventoryItems.length} inventory items to update condition status`);

    // Process each item
    inventoryItems.forEach(item => {
        try {
            // Get condition elements
            const conditionElements = [
                item.querySelector('.condition-badge'),
                item.querySelector('td.condition-column .badge'),
                item.querySelector('[data-condition]')
            ].filter(el => el); // Remove null elements

            if (conditionElements.length === 0) {
                return; // No condition elements found
            }

            // Update each condition element
            conditionElements.forEach(conditionElement => {
                // Get the current condition text
                const condition = conditionElement.textContent.trim();
                
                // Remove all existing badge classes
                conditionElement.classList.remove('bg-success', 'bg-info', 'bg-warning', 'bg-danger', 'bg-secondary', 'text-dark');
                
                // Apply the appropriate badge class based on condition
                const badgeClass = getConditionBadgeClass(condition);
                badgeClass.split(' ').forEach(cls => {
                    conditionElement.classList.add(cls);
                });
                
                // Ensure styling is consistent
                conditionElement.classList.add('badge');
                conditionElement.style.fontWeight = 'bold';
                conditionElement.style.letterSpacing = '0.5px';
                conditionElement.style.padding = '5px 8px';
                conditionElement.style.fontSize = '0.85rem';
            });
        } catch (error) {
            console.error('Error updating condition for item:', error);
        }
    });
}

// Modify loadInventoryData to make sure it's properly implemented
function loadInventoryData(searchQuery = '') {
    console.log(`Loading inventory data with search query: "${searchQuery}"`);

    // Ensure we're using a consistent endpoint path
    const url = searchQuery
        ? `get_inventory.php?search=${encodeURIComponent(searchQuery)}`
        : 'get_inventory.php';

    showLoading();

    console.log('Loading inventory data from:', url);

    fetch(url)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text.substring(0, 200)}...`);
                });
            }
            return response.json();
        })
        .then(response => {
            console.log('Data received:', response);

            // Handle different response formats
            let data = response;
            if (response && response.success === false) {
                throw new Error(response.message || 'Unknown error occurred');
            } else if (response && response.data) {
                data = response.data;
            }

            // Store data globally
            window.filteredData = data;

            // Update the table
            updateInventoryTable(data);

            // Run additional functions
            checkWarrantyStatus();
            updateConditionStatus();

            hideLoading();
        })
        .catch(error => {
            console.error('Failed to load inventory data:', error);
            showError('Failed to load inventory data. Please try again.');
            hideLoading();
        });
}



// ... existing code ...

// Wrap inventory-related functions in document ready event
document.addEventListener('DOMContentLoaded', function () {
    // Initialize inventory system
    initInventorySystem();

    // Add event listeners for inventory filters
    const conditionFilter = document.getElementById('conditionFilter');
    const locationFilter = document.getElementById('locationFilter');
    const purchaseDateFilter = document.getElementById('purchaseDateFilter');

    if (conditionFilter) conditionFilter.addEventListener('change', applyFilters);
    if (locationFilter) locationFilter.addEventListener('change', applyFilters);
    if (purchaseDateFilter) purchaseDateFilter.addEventListener('change', applyFilters);

    // Initialize inventory data load
                loadInventoryData();

    // Add action button listeners
    addActionButtonListeners();

    // Enhance table appearance
    enhance3DTables();

    // Initialize any tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// ... existing code ...
// ... existing code ...

// Inventory-specific document ready event handler
document.addEventListener('DOMContentLoaded', function () {
    // Initialize inventory functionality
    if (document.getElementById('inventoryTable')) {
        console.log('Initializing inventory system...');

        // Initialize inventory data
    loadInventoryData();

        // Add event listeners for inventory actions
        addActionButtonListeners();

        // Initialize inventory filters
        const conditionFilter = document.getElementById('conditionFilter');
        const locationFilter = document.getElementById('locationFilter');
        const purchaseDateFilter = document.getElementById('purchaseDateFilter');

        if (conditionFilter) conditionFilter.addEventListener('change', applyFilters);
        if (locationFilter) locationFilter.addEventListener('change', applyFilters);
        if (purchaseDateFilter) purchaseDateFilter.addEventListener('change', applyFilters);

        // Initialize search functionality
        const searchInput = document.getElementById('inventorySearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function (e) {
                loadInventoryData(e.target.value.trim());
            }, 300));
        }

        // Check warranty status periodically
        checkWarrantyStatus();
        setInterval(checkWarrantyStatus, 60000); // Check every minute

    // Enhance table appearance
    enhance3DTables();
    }
});
// ... existing code ...
// Function to print a PO
function printPO() {
    // Hide elements not needed for printing
    const elementsToHide = document.querySelectorAll('.no-print, .sidebar, .header');
    elementsToHide.forEach(el => {
        el.dataset.originalDisplay = el.style.display;
        el.style.display = 'none';
    });

    // Add a print-specific class to the body
    document.body.classList.add('printing');

    // Print the document
    window.print();

    // Restore the elements after printing
        setTimeout(() => {
        elementsToHide.forEach(el => {
            el.style.display = el.dataset.originalDisplay || '';
        });
        document.body.classList.remove('printing');
    }, 500);
}

// Fix the loadInventoryData function to handle server failures better
function loadInventoryData(searchQuery = '') {
    console.log(`Loading inventory data with search query: ${searchQuery}`);

    // Ensure we're using a consistent endpoint path
    const url = searchQuery
        ? `get_inventory.php?search=${encodeURIComponent(searchQuery)}`
        : 'get_inventory.php';

    showLoading();

    console.log('Loading inventory data from:', url);

    fetch(url)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text.substring(0, 200)}...`);
                });
            }
            return response.json();
        })
        .then(response => {
            console.log('Data received:', response);

            // Handle different response formats
            let data = response;
            if (response && response.success === false) {
                throw new Error(response.message || 'Unknown error occurred');
            } else if (response && response.data) {
                data = response.data;
            }

            // Store data globally
            window.filteredData = data;

            // Update the table
            updateInventoryTable(data);

            // Run additional functions
            checkWarrantyStatus();
            updateConditionStatus();

            hideLoading();
        })
        .catch(error => {
            console.error('Failed to load inventory data:', error);
            showError('Failed to load inventory data. Please try again.');
            hideLoading();
        });
}

// Make sure we're calling the loadInventoryData function when the page loads
document.addEventListener('DOMContentLoaded', function () {
    loadInventoryData();
});

// Add missing loadPARData function

// Make loadPARData available globally
window.loadPARData = loadPARData;

// Make sure we're calling the loadInventoryData function when the page loads
document.addEventListener('DOMContentLoaded', function () {
    loadInventoryData();
    // No need to call loadPARData here as it will be called when the PAR tab is clicked
});
// Remove duplicate event listener

// Function to attach event handler to Save PO button
window.attachPOButtonHandler = function () {
    const savePoBtn = document.getElementById('savePoBtn');
    if (savePoBtn) {
        console.log('Attaching event handler to savePoBtn');

        // Check if handler is already attached
        if (savePoBtn.hasAttribute('data-handler-attached') && savePoBtn.getAttribute('data-handler-attached') === 'true') {
            console.log('Handler already attached to savePoBtn, skipping');
            
            // Make sure button is enabled
            savePoBtn.disabled = false;
            if (savePoBtn.querySelector('.spinner-border')) {
                savePoBtn.innerHTML = 'Save PO';
            }
            return;
        }

        // Remove any existing listeners by cloning the node
        const newSavePoBtn = savePoBtn.cloneNode(true);
        savePoBtn.parentNode.replaceChild(newSavePoBtn, savePoBtn);

        // Mark as handler attached to prevent duplicate calls
        newSavePoBtn.setAttribute('data-handler-attached', 'true');

        newSavePoBtn.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('savePoBtn clicked');

            // Prevent duplicate processing
            if (this.disabled) {
                console.log('Button is already disabled, preventing duplicate processing');
                return;
            }

            // Disable button to prevent multiple submissions
            this.disabled = true;
            const originalBtnText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

            try {
                // Get all items from the table
                const itemRows = document.querySelectorAll('#poItemsTable tbody tr:not(.d-none)');
                const items = [];

                itemRows.forEach(row => {
                    const itemName = row.querySelector('.item-name')?.value || '';
                    const unit = row.querySelector('.unit')?.value || 'pc';
                    const description = row.querySelector('.description')?.value || '';
                    const quantity = parseFloat(row.querySelector('.qty')?.value) || 0;
                    const unitCost = parseFloat(row.querySelector('.unit-cost')?.value) || 0;
                    const amount = parseFloat(row.querySelector('.amount')?.value?.replace(/[^\d.-]/g, '')) || 0;

                    // Only add non-empty items, but log any missing required fields
                    if (itemName || description) {
                        items.push({
                            item_name: itemName,        // Primary field name
                            name: itemName,             // Alternative field name
                            unit: unit,
                            item_description: description, // Primary field name
                            description: description,    // Alternative field name
                            quantity: quantity,
                            qty: quantity,              // Alternative field name
                            unit_cost: unitCost,
                            unit_price: unitCost,       // Alternative field name
                            amount: amount,
                            total_cost: amount          // Alternative field name
                        });
                    } else {
                        console.warn('Skipping empty row:', row);
                    }
                });

                // Check if items exist
                if (items.length === 0) {
                    throw new Error('Please add at least one item to the purchase order');
                }

                // Get form data
                const poNo = document.getElementById('poNumber')?.value || '';
                const supplier = document.getElementById('supplier')?.value || '';
                const poDate = document.getElementById('poDate')?.value || '';
                const totalAmount = document.getElementById('totalAmount')?.value?.replace(/[^\d.-]/g, '') || 0;
                
                // Basic validation
                if (!poNo || !supplier) {
                    throw new Error('Please fill in required fields (PO Number, Supplier)');
                }
                
                // Get additional fields if they exist
                const refNo = document.getElementById('refNo')?.value || '';
                const prNo = document.getElementById('prNo')?.value || '';
                const prDate = document.getElementById('prDate')?.value || '';
                const placeOfDelivery = document.getElementById('placeOfDelivery')?.value || '';
                const deliveryDate = document.getElementById('deliveryDate')?.value || '';
                const paymentTerm = document.getElementById('paymentTerm')?.value || '';
                const deliveryTerm = document.getElementById('deliveryTerm')?.value || '';
                const mode_of_procurement = document.getElementById('modeOfProcurement')?.value || '';
                const supplier_email = document.getElementById('supplierEmail')?.value || '';
                const supplier_address = document.getElementById('supplierAddress')?.value || '';
                const supplier_tel = document.getElementById('supplierTel')?.value || '';
                const obligation_request_no = document.getElementById('obligationRequestNo')?.value || '';
                const obligation_amount = parseFloat(document.getElementById('obligationAmount')?.value?.replace(/[^\d.-]/g, '')) || 0;
                
                // Create properly formatted PO data
                const poData = {
                    po_no: poNo,
                    ref_no: refNo,
                    supplier_name: supplier,
                    po_date: poDate,
                    pr_no: prNo,
                    pr_date: prDate,
                    place_of_delivery: placeOfDelivery,
                    delivery_date: deliveryDate,
                    payment_term: paymentTerm,
                    delivery_term: deliveryTerm,
                    mode_of_procurement: mode_of_procurement,
                    supplier_email: supplier_email,
                    supplier_address: supplier_address,
                    supplier_tel: supplier_tel,
                    obligation_request_no: obligation_request_no,
                    obligation_amount: obligation_amount,
                    total_amount: totalAmount,
                    items: items
                };

                // Add PO details that use different field names for compatibility
                poData.po_number = poNo;              // Alternative field name
                poData.supplier = supplier;           // Alternative field name
                poData.date = poDate;                 // Alternative field name
                poData.address = supplier_address;    // Alternative field name
                poData.email = supplier_email;        // Alternative field name
                poData.telephone = supplier_tel;      // Alternative field name
                poData.tel = supplier_tel;            // Alternative field name

                console.log('PO Data being sent:', poData);

                // Show loading indicator
                if (typeof showLoading === 'function') {
                    showLoading();
                }

                // Send data to server
                fetch('add_po.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(poData)
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Server error response:', text);
                            throw new Error('Server Error: ' + (text || 'Unknown error'));
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('PO saved successfully:', data);
                    
                    // Hide loading indicator
                    if (typeof hideLoading === 'function') {
                        hideLoading();
                    }
                    
                    Swal.fire({
                        title: 'Success!',
                        text: 'Purchase Order saved successfully',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Reset form
                        const form = document.querySelector('#addPOModal form');
                        if (form) form.reset();
                        
                        // Clear table
                        document.querySelectorAll('#poItemsTable tbody tr:not(.d-none)').forEach(row => {
                            if (!row.classList.contains('template-row')) {
                                row.remove();
                            }
                        });
                        
                        // Close modal
                        const poModal = document.getElementById('addPOModal');
                        if (poModal && typeof bootstrap !== 'undefined') {
                            const modal = bootstrap.Modal.getInstance(poModal);
                            if (modal) modal.hide();
                        }
                        
                        // Reload PO data
                        if (typeof loadPOData === 'function') {
                            loadPOData();
                        } else {
                            // Don't reload the page as it might cause issues
                            console.warn('loadPOData function not found');
                        }
                    });
                })
                .catch(error => {
                    console.error('Error saving PO:', error);
                    
                    // Hide loading indicator
                    if (typeof hideLoading === 'function') {
                        hideLoading();
                    }
                    
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to save Purchase Order: ' + error.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                })
                .finally(() => {
                    // Always re-enable button
                    this.disabled = false;
                    this.innerHTML = originalBtnText;
                });
            } catch (error) {
                console.error('Error in savePoBtn click handler:', error);
                
                // Hide loading indicator
                if (typeof hideLoading === 'function') {
                    hideLoading();
                }
                
                // Re-enable button
                this.disabled = false;
                this.innerHTML = originalBtnText;
                
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while processing your request: ' + error.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }
};

// Function to attach event handler to Save PAR button
window.attachPARButtonHandler = function () {
    const saveParBtn = document.getElementById('saveParBtn');
    if (saveParBtn) {
        console.log('Attaching event handler to saveParBtn');

        // Remove any existing listeners by cloning the node
        const newSaveBtn = saveParBtn.cloneNode(true);
        saveParBtn.parentNode.replaceChild(newSaveBtn, saveParBtn);

        // Mark as handler attached to prevent duplicate calls
        newSaveBtn.setAttribute('data-handler-attached', 'true');

        newSaveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('saveParBtn clicked');

            // Disable button to prevent multiple submissions
            this.disabled = true;
            const originalBtnText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

            // Get all items from the table
            const itemRows = document.querySelectorAll('#parItemsTable tbody tr:not(.d-none)');
            const items = [];

            itemRows.forEach(row => {
                const quantity = parseFloat(row.querySelector('.quantity')?.value) || 0;
                const unit = row.querySelector('.unit')?.value || '';
                const propertyNumber = row.querySelector('.property-number')?.value || '';
                const dateAcquired = row.querySelector('.date-acquired')?.value || '';
                const amount = parseFloat(row.querySelector('.amount')?.value.replace(/[^\d.-]/g, '')) || 0;

                // Only add non-empty items
                if (itemName) {
                    items.push({
                        quantity: quantity,
                        unit: unit,
                        property_number: propertyNumber,
                        date_acquired: dateAcquired,
                        amount: amount
                    });
                }
            });

            // Get form data
            const parData = {
                par_number: document.getElementById('parNumber')?.value || '',
                date: document.getElementById('parDate')?.value || '',
                department: document.getElementById('department')?.value || '',
                purpose: document.getElementById('purpose')?.value || '',
                total_amount: document.getElementById('parTotalAmount')?.value?.replace(/[^\d.-]/g, '') || 0,
                items: items
            };

            console.log('PAR Data being sent:', parData);

            // Send data to server
            fetch('add_po.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(parData)
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('PAR saved successfully:', data);
        Swal.fire({
                        title: 'Success!',
                        text: 'PAR saved successfully',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Reload PAR data or close modal
                        const parModal = document.getElementById('addPARModal');
                        if (parModal && typeof bootstrap !== 'undefined') {
                            const modal = bootstrap.Modal.getInstance(parModal);
                            if (modal) modal.hide();
                        }
                        if (typeof loadPARData === 'function') {
                            loadPARData();
                        } else {
                            window.location.reload();
                        }
                    });
                })
                .catch(error => {
                    console.error('Error saving PAR:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to save PAR: ' + error.message,
            icon: 'error',
                        confirmButtonText: 'OK'
                    });
                })
                .finally(() => {
                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = originalBtnText;
                });
        });
    }
};

// Update the existing DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', function () {
    console.log('Document loaded, initializing systems');

    // Load initial data
    if (typeof loadInventoryData === 'function') {
                loadInventoryData();
    }

    // Initialize warranty checking
    checkWarrantyStatus();

    // Setup periodic warranty checks
    setInterval(checkWarrantyStatus, 3600000); // Check warranty status every hour

    // Listen for modal shown events to attach PO button handler
    document.body.addEventListener('shown.bs.modal', function (event) {
        if (event.target.id === 'poModal' || event.target.id === 'addPOModal') {
            console.log('PO modal shown, attaching handlers');
            window.attachPOButtonHandler();
            
            // Add safeguard to ensure modal can be closed
            const modal = event.target;
            const closeButtons = modal.querySelectorAll('.btn-close, [data-bs-dismiss="modal"]');
            closeButtons.forEach(button => {
                // Remove existing listeners by cloning
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);
                
                // Add enhanced close handler
                newButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get modal instance and hide it
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) {
                        modalInstance.hide();
                    } else {
                        // Fallback if modal instance not found
                        modal.style.display = 'none';
                        modal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) backdrop.remove();
                    }
                    
                    // Reset any buttons that might be disabled
                    const saveBtn = document.getElementById('savePoBtn');
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        if (saveBtn.querySelector('.spinner-border')) {
                            saveBtn.innerHTML = 'Save PO';
                        }
                    }
                });
            });
        }
        if (event.target.id === 'parModal' || event.target.id === 'addPARModal') {
            console.log('PAR modal shown, attaching handlers');
            window.attachPARButtonHandler();
        }
    });

    // Also attach handlers directly if modals are already in the DOM
    setTimeout(function () {
        window.attachPOButtonHandler();
        window.attachPARButtonHandler();
    }, 1000);

    // Add direct click event handler for savePoBtn as fallback
    document.addEventListener('click', function (event) {
        if (event.target && (event.target.id === 'savePoBtn' || event.target.closest('#savePoBtn'))) {
            if (!event.target.hasAttribute('data-handler-attached')) {
                console.log('Direct click on savePoBtn detected, triggering handler');
                window.attachPOButtonHandler();
                // Add a small delay and click again to ensure the handler is called
                setTimeout(function () {
                    document.getElementById('savePoBtn')?.click();
                }, 100);
            }
        }
        if (event.target && (event.target.id === 'saveParBtn' || event.target.closest('#saveParBtn'))) {
            if (!event.target.hasAttribute('data-handler-attached')) {
                console.log('Direct click on saveParBtn detected, triggering handler');
                window.attachPARButtonHandler();
                // Add a small delay and click again to ensure the handler is called
                setTimeout(function () {
                    document.getElementById('saveParBtn')?.click();
                }, 100);
            }
        }
    });

    // Add tab change handler for PAR tab
    const parTab = document.querySelector('a[href="#parSection"], [data-bs-target="#parSection"]');
    if (parTab) {
        parTab.addEventListener('shown.bs.tab', function () {
            console.log('PAR tab activated, loading PAR data');
            loadPARData();
        });
    }
});
// Function to view a PO
function viewPO(poId) {
    if (!poId) {
        Swal.fire('Error', 'PO ID is required', 'error');
        return;
    }

    console.log('Viewing PO with ID:', poId);
    
    // Open the viewPO.php in a new window
    window.open(`viewPO.php?id=${encodeURIComponent(poId)}`, '_blank');
}



// ... existing code ...

// Function to handle PO form submission
document.getElementById('savePoBtn')?.addEventListener('click', function (e) {
    // Prevent default form submission to avoid double processing
    e.preventDefault();
    
    // If the button already has the handler attached, don't proceed
    if (this.hasAttribute('data-handler-attached')) {
        console.log('Handler already attached to button, skipping duplicate execution');
        return;
    }
    
    // Disable button to prevent multiple submissions
    this.disabled = true;
    const originalBtnText = this.innerHTML;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    
    try {
        // Get form data
        const poForm = document.getElementById('poForm');
        if (!poForm) {
            throw new Error('PO Form not found');
        }

        // Get all items from the table
        const itemRows = document.querySelectorAll('#poItemsTable tbody tr:not(.d-none)');
        const items = [];

        itemRows.forEach(row => {
            const item = {
                description: row.querySelector('.item-description')?.value || '',
                unit: row.querySelector('.unit')?.value || '',
                quantity: parseInt(row.querySelector('.quantity')?.value) || 0,
                unit_cost: parseFloat(row.querySelector('.unit-cost')?.value) || 0,
                amount: parseFloat(row.querySelector('.amount')?.value) || 0
            };
            if (item.description) {
                items.push(item);
            }
        });

        const poData = {
            po_no: document.getElementById('poNo').value,
            ref_no: document.getElementById('refNo').value,
            supplier_name: document.getElementById('supplier').value,
            po_date: document.getElementById('poDate').value,
            mode_of_procurement: document.getElementById('modeOfProcurement').value,
            pr_no: document.getElementById('prNo').value,
            pr_date: document.getElementById('prDate').value,
            place_of_delivery: document.getElementById('placeOfDelivery').value,
            delivery_date: document.getElementById('deliveryDate').value,
            payment_term: document.getElementById('paymentTerm').value,
            delivery_term: document.getElementById('deliveryTerm').value,
            obligation_request_no: document.getElementById('obligationRequestNo').value,
            obligation_amount: parseFloat(document.getElementById('obligationAmount').value) || 0,
            total_amount: parseFloat(document.getElementById('totalAmount').value.replace(/[^\d.-]/g, '')) || 0,
            items: items
        };
    
    // Show loading state
        Swal.fire({
            title: 'Saving Purchase Order',
            text: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Send data to server
        fetch('add_po.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(poData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Purchase Order saved successfully',
                        icon: 'success'
                    }).then(() => {
                        // Close modal
                        const poModal = bootstrap.Modal.getInstance(document.getElementById('addPOModal'));
                        if (poModal) {
                            poModal.hide();
                        }
                        // Refresh PO table
                        if (typeof loadPOData === 'function') {
                            loadPOData();
                        }
                    });
                } else {
                    throw new Error(data.message || 'Failed to save PO');
                }
            })
            .catch(error => {
                console.error('Error saving PO:', error);
                Swal.fire({
                    title: 'Error!',
                    text: error.message || 'Failed to save PO: Server error',
                    icon: 'error'
                });
            })
            .finally(() => {
                // Always re-enable button and restore text
                this.disabled = false;
                this.innerHTML = originalBtnText;
                
                // Ensure loading indicator is hidden
                if (typeof hideLoading === 'function') {
                    hideLoading();
                }
            });
    } catch (error) {
        console.error('Error in PO form submission:', error);
        
        // Show error message
        Swal.fire({
            title: 'Error!',
            text: 'An error occurred: ' + error.message,
            icon: 'error'
        });
        
        // Re-enable button and restore text
        this.disabled = false;
        this.innerHTML = originalBtnText;
        
        // Ensure loading indicator is hidden
        if (typeof hideLoading === 'function') {
            hideLoading();
        }
    }
});
// ... existing code ...


// Function to print a PO
function printPO() {
    // Get the iframe from the modal
    const iframe = document.querySelector('#viewPOModal iframe');

    if (!iframe) {
        console.error('Print failed: PO iframe not found');
        return;
    }

    try {
        // Get the iframe's window object
        const iframeWindow = iframe.contentWindow;

        // Store current page title
        const originalTitle = document.title;

        // Set the title to the PO number if available
        const poNumberElement = iframeWindow.document.querySelector('.po-number');
        if (poNumberElement) {
            document.title = 'PO ' + poNumberElement.textContent.trim();
        }

        // Print the iframe content
        iframeWindow.focus();
        iframeWindow.print();

        // Restore original title
        setTimeout(() => {
            document.title = originalTitle;
        }, 1000);
    } catch (error) {
        console.error('Error printing PO:', error);
        Swal.fire('Error', 'Failed to print Purchase Order. Please try again.', 'error');
    }
}
function viewPODetails(poId) {
    if (!poId) {
        Swal.fire('Error', 'PO ID is required', 'error');
        return;
    }

    // Use the shared data retrieval function
    getPOData(poId, function(poData) {
        // Display PO details in modal
        displayPODetailsModal(poData);
    });
}

function addPOButtonEventListeners() {
    try {
        console.log('Adding event listeners to PO buttons');

        document.querySelectorAll('.view-po').forEach(btn => {
            btn.addEventListener('click', function () {
                const poId = this.getAttribute('data-id');
                console.log('View PO clicked for ID:', poId);
                viewPO(poId);
            });
        });

        document.querySelectorAll('.edit-po').forEach(btn => {
            btn.addEventListener('click', function () {
                const poId = this.getAttribute('data-id');
                console.log('Edit PO clicked for ID:', poId);
                editPO(poId);
            });
        });

        document.querySelectorAll('.delete-po').forEach(btn => {
            btn.addEventListener('click', function () {
                const poId = this.getAttribute('data-id');
                console.log('Delete PO clicked for ID:', poId);
                deletePO(poId);
            });
        });

        console.log('PO button event listeners added successfully');
    } catch (error) {
        console.error('Error adding event listeners to PO buttons:', error);
    }
}

function viewPO(poId) {
    if (!poId) {
        Swal.fire('Error', 'PO ID is required', 'error');
        return;
    }

    console.log('Viewing PO with ID:', poId);
    
    // Open the viewPO.php in a new window
    window.open(`viewPO.php?id=${encodeURIComponent(poId)}`, '_blank');
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded - initializing event handlers');
    
    // Initialize the PO button events
    if (typeof attachPOButtonHandler === 'function') {
        attachPOButtonHandler();
    }
    
    // Initial data loading if on the PO section
    if (document.getElementById('poTableBody')) {
        if (typeof loadPOData === 'function') {
            console.log('Loading initial PO data');
            loadPOData();
        }
    }
    
    // Add listeners to section tabs/buttons to ensure they load their data
    const poTab = document.querySelector('[data-section="po-section"]');
    if (poTab) {
        poTab.addEventListener('click', function() {
            if (typeof loadPOData === 'function') {
                loadPOData();
            }
        });
    }
    
    // Ensure PO button event listeners are added
    if (typeof addPOButtonEventListeners === 'function') {
        addPOButtonEventListeners();
    }
    
    console.log('Initialization completed');
});
// ... existing code ...

// Function to load Purchase Orders data with filtering
function loadPOData(page = 1) {
    showLoading();
    
    // Get filter values
    const searchTerm = document.getElementById('poSearchInput')?.value || '';
    const supplierFilter = document.getElementById('poSupplierFilter')?.value || '';
    const dateFilter = document.getElementById('poDateFilter')?.value || '';
    
    fetch(`get_po_data.php?page=${page}&search=${encodeURIComponent(searchTerm)}&supplier=${encodeURIComponent(supplierFilter)}&date_range=${encodeURIComponent(dateFilter)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            hideLoading();
            displayPOData(data.pos, data.currentPage, data.totalPages, data.total);
            
            // Populate supplier filter options if they don't exist yet
            if (data.suppliers && data.suppliers.length > 0) {
                populateSupplierFilter(data.suppliers);
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error loading PO data:', error);
            Swal.fire('Error', 'Failed to load purchase orders. Please try again.', 'error');
        });
}

// Function to populate the supplier filter dropdown
function populateSupplierFilter(suppliers) {
    const supplierFilter = document.getElementById('poSupplierFilter');
    if (!supplierFilter || supplierFilter.options.length > 1) return; // Skip if already populated
    
    // Clear existing options except the first one (All Suppliers)
    while (supplierFilter.options.length > 1) {
        supplierFilter.remove(1);
    }
    
    // Add supplier options
    suppliers.forEach(supplier => {
        if (supplier) {
            const option = document.createElement('option');
            option.value = supplier;
            option.textContent = supplier;
            supplierFilter.appendChild(option);
        }
    });
}

// Add event listeners to PO filters
document.addEventListener('DOMContentLoaded', function() {
    // Existing code...
    
    // Add filter event listeners for PO section
    const poSearchInput = document.getElementById('poSearchInput');
    const poSupplierFilter = document.getElementById('poSupplierFilter');
    const poDateFilter = document.getElementById('poDateFilter');
    
    if (poSearchInput) {
        poSearchInput.addEventListener('input', debounce(() => loadPOData(1), 500));
    }
    
    if (poSupplierFilter) {
        poSupplierFilter.addEventListener('change', () => loadPOData(1));
    }
    
    if (poDateFilter) {
        poDateFilter.addEventListener('change', () => loadPOData(1));
    }
});

// Function to display PO data in the table
function displayPOData(poData, currentPage, totalPages, total) {
    const poTableBody = document.getElementById('poTableBody');
    const pageInfo = document.getElementById('poPageInfo');
    const prevBtn = document.getElementById('poPrevBtn');
    const nextBtn = document.getElementById('poNextBtn');
    
    if (!poTableBody) {
        console.error('PO table body not found');
        return;
    }
    
    // Ensure the table has the proper classes for styling
    const poTable = document.getElementById('poTable');
    if (poTable) {
        // Add the enhanced styling classes to match PAR and Inventory tables
        poTable.classList.add('table-hover', 'enhanced-table');
        poTable.classList.remove('table-dark', 'table-striped-dark');
    }
    
    // Clear the table
    poTableBody.innerHTML = '';
    
    if (poData.length === 0) {
        // No data found
        const emptyRow = document.createElement('tr');
        emptyRow.innerHTML = `<td colspan="5" class="text-center py-4">No purchase orders found</td>`;
        poTableBody.appendChild(emptyRow);
    } else {
        // Add data rows
        poData.forEach(po => {
            // Format the date properly
            let formattedDate = 'N/A';
            if (po.po_date) {
                try {
                    // Handle different date formats
                    const date = new Date(po.po_date);
                    if (!isNaN(date.getTime())) {
                        formattedDate = date.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit'
                        });
                    }
                } catch (e) {
                    console.error('Error formatting date:', e);
                }
            }
            
            // Format the amount properly
            let formattedAmount = '₱0.00';
            if (po.total_amount !== undefined && po.total_amount !== null) {
                try {
                    const amount = parseFloat(po.total_amount);
                    if (!isNaN(amount)) {
                        formattedAmount = formatCurrency(amount);
                    }
                } catch (e) {
                    console.error('Error formatting amount:', e);
                }
            }
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${po.po_no || 'N/A'}</td>
                <td>${po.supplier_name || 'N/A'}</td>
                <td>${formattedDate}</td>
                <td class="text-end fw-medium">${formattedAmount}</td>
                <td class="text-center">
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-view" onclick="viewPO(${po.po_id})" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-edit" onclick="editPO(${po.po_id})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-delete" onclick="deletePO(${po.po_id})" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            poTableBody.appendChild(row);
        });
    }
    
    // Update pagination info
    if (pageInfo) {
        const start = (currentPage - 1) * 10 + 1;
        const end = Math.min(start + poData.length - 1, total);
        pageInfo.textContent = `Showing ${start}-${end} of ${total} purchase orders`;
    }
    
    // Update pagination buttons
    if (prevBtn) {
        prevBtn.disabled = currentPage <= 1;
        prevBtn.onclick = () => loadPOData(currentPage - 1);
    }
    
    if (nextBtn) {
        nextBtn.disabled = currentPage >= totalPages;
        nextBtn.onclick = () => loadPOData(currentPage + 1);
    }
}

// ... existing code ...

// Improved section visibility management and sidebar navigation fixing
(function() {
    // Fix for condition badges - apply consistently to all condition elements
    function fixConditionBadges() {
        console.log('Fixing condition badges');
        
        // Update all condition cells in tables
        document.querySelectorAll('td[data-condition], .condition-badge, .badge[data-condition]').forEach(element => {
            if (!element) return;
            
            const condition = element.textContent.trim();
            // Remove all existing badge classes
            element.classList.remove('bg-success', 'bg-info', 'bg-warning', 'bg-danger', 'bg-secondary', 'text-dark');
            
            // Add appropriate badge class
            switch(condition) {
                case 'New':
                    element.classList.add('bg-success');
                    break;
                case 'Good': 
                    element.classList.add('bg-info');
                    break;
                case 'Fair':
                    element.classList.add('bg-warning');
                    element.classList.add('text-dark');
                    break;
                case 'Poor':
                    element.classList.add('bg-danger');
                    break;
                default:
                    element.classList.add('bg-secondary');
            }
            
            // Ensure it has badge class
            if (!element.classList.contains('badge')) {
                element.classList.add('badge');
            }
        });
    }
    
    // Ensure consistent condition badge function across the application
    function getConditionBadgeClass(condition) {
        switch(condition) {
            case 'New': return 'bg-success';
            case 'Good': return 'bg-info';
            case 'Fair': return 'bg-warning text-dark';
            case 'Poor': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }
    
    // Section stabilizer - ensures sections don't disappear
    function stabilizeSections() {
        console.log('Running section stabilizer');
        
        // Get the current active section from localStorage
        const activeSection = localStorage.getItem('activeSection') || 'dashboard';
        
        // Make sure all sections are properly initialized
        const sectionSelectors = ['.dashboard-section', '.inventory-section', '.po-section', '.par-section'];
        const sectionElements = document.querySelectorAll(sectionSelectors.join(', '));
        
        // First ensure all section elements exist and have proper styling
        if (sectionElements.length > 0) {
            sectionElements.forEach(section => {
                // Make sure sections have block display style when visible
                if (!section.classList.contains('d-none')) {
                    section.style.display = 'block';
                }
            });
        } else {
            console.warn('No section elements found to stabilize');
        }
        
        // Force refresh the active section if needed
        const currentVisibleSection = document.querySelector('.dashboard-section:not(.d-none), .inventory-section:not(.d-none), .po-section:not(.d-none), .par-section:not(.d-none)');
        
        if (!currentVisibleSection) {
            console.warn('No visible section detected, restoring active section');
            setActiveSection(activeSection, true);
        }
        
        // Make sure nav links are properly set
        const activeNavLink = document.querySelector('.nav-link.active');
        if (!activeNavLink) {
            const targetLink = document.querySelector(`#${activeSection}-link`);
            if (targetLink) {
                targetLink.classList.add('active');
            }
        }
        
        // Fix condition badges
        fixConditionBadges();
    }
    
    // Monitor for section visibility issues
    function monitorSectionVisibility() {
        console.log('Starting section visibility monitoring');
        
        // Set up a MutationObserver to watch for changes to section visibility
        const observer = new MutationObserver(mutations => {
            let needsCheck = false;
            
            mutations.forEach(mutation => {
                if (mutation.type === 'attributes' && 
                    (mutation.attributeName === 'class' || mutation.attributeName === 'style')) {
                    needsCheck = true;
                }
            });
            
            if (needsCheck) {
                stabilizeSections();
            }
        });
        
        // Watch all section elements
        document.querySelectorAll('.dashboard-section, .inventory-section, .po-section, .par-section').forEach(section => {
            observer.observe(section, { attributes: true });
        });
        
        return observer;
    }
    
    // Initialize section stability features and fixed sidebar navigation
    function initSectionStability() {
        // Initial stabilization
        stabilizeSections();
        
        // Start monitoring for section visibility changes
        const observer = monitorSectionVisibility();
        
        // Periodically check section visibility as a backup
        const stabilityInterval = setInterval(stabilizeSections, 3000);
        
        // Fix sidebar navigation links
        document.querySelectorAll('.nav-link').forEach(link => {
            // Remove any existing click handlers to prevent duplicates
            const clone = link.cloneNode(true);
            link.parentNode.replaceChild(clone, link);
            
            // Add fresh click handler with improved stability
            clone.addEventListener('click', function(e) {
                e.preventDefault();
                
                const section = this.id.replace('-link', '');
                console.log(`Nav link clicked for section: ${section}`);
                
                // Set active section immediately but protect against race conditions
                if (!window.sectionChanging) {
                    window.sectionChanging = true;
                    
                    try {
                        setActiveSection(section);
                    } finally {
                        // Reset the flag after a short delay
                        setTimeout(() => {
                            window.sectionChanging = false;
                        }, 500);
                    }
                }
            });
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            clearInterval(stabilityInterval);
            observer.disconnect();
        });
    }
    
    // Run on DOM content loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSectionStability);
    } else {
        // DOM already loaded, run immediately
        initSectionStability();
    }
    
    // Also run on window load to handle late-loading resources
    window.addEventListener('load', function() {
        // Small delay to allow other load handlers to complete
        setTimeout(stabilizeSections, 200);
    });
    
    // Override getConditionBadgeClass globally
    window.getConditionBadgeClass = getConditionBadgeClass;
})();

// ... existing code ...

// Enhanced dashboard display and table visibility management
(function() {
    // Tables to monitor for visibility issues
    const criticalTables = {
        inventory: ['.inventory-table', '#inventory-table', '.inventory-data-table'],
        po: ['.po-table', '#po-table', '.po-data-table', '.purchase-orders-table'],
        par: ['.par-table', '#par-table', '.par-data-table']
    };
    
    // Design elements to ensure visibility
    const designElements = {
        inventory: ['.inventory-cards', '.inventory-stats', '.inventory-filters'],
        po: ['.po-cards', '.po-stats', '.po-status-cards', '.po-filters'],
        dashboard: ['.stats-cards', '.dashboard-charts', '.status-cards']
    };
    
    // Fix table visibility issues
    function fixTableVisibility() {
        console.log('Checking and fixing table visibility');
        
        // Get current active section
        const activeSection = localStorage.getItem('activeSection') || 'dashboard';
        
        // Only proceed if we're in a section with tables
        if (!['inventory', 'po', 'par'].includes(activeSection)) {
            return;
        }
        
        // Check tables in the active section
        if (criticalTables[activeSection]) {
            let tableFound = false;
            
            // Check if any table is visible
            for (const selector of criticalTables[activeSection]) {
                const table = document.querySelector(selector);
                if (table) {
                    // Ensure table is visible
                    if (table.style.display === 'none') {
                        table.style.display = 'table';
                    }
                    
                    // Remove any d-none class
                    if (table.classList.contains('d-none')) {
                        table.classList.remove('d-none');
                    }
                    
                    tableFound = true;
                }
            }
            
            // If no table found, try reloading data
            if (!tableFound) {
                console.warn(`No visible tables found for ${activeSection}, reloading data`);
                
                // Trigger data reload
                if (activeSection === 'inventory' && typeof loadInventoryData === 'function') {
                    loadInventoryData();
                } else if (activeSection === 'po' && typeof loadPOData === 'function') {
                    loadPOData();
                } else if (activeSection === 'par' && typeof loadPARData === 'function') {
                    loadPARData();
                }
            }
        }
        
        // Fix design elements
        if (designElements[activeSection]) {
            for (const selector of designElements[activeSection]) {
                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    if (element) {
                        // Ensure elements are visible
                        if (element.style.display === 'none') {
                            element.style.display = '';
                        }
                        
                        // Remove any d-none class
                        if (element.classList.contains('d-none')) {
                            element.classList.remove('d-none');
                        }
                    }
                });
            }
        }
    }
    
    // Force proper table and card rendering after section change
    function enhanceSetActiveSection() {
        const originalSetActiveSection = window.setActiveSection;
        
        if (typeof originalSetActiveSection !== 'function') {
            console.error('Cannot enhance setActiveSection - function not found');
            return;
        }
        
        // Override the original function
        window.setActiveSection = function(section, skipSave = false) {
            console.log('Enhanced setActiveSection called for: ' + section);
            
            // Call the original function
            originalSetActiveSection(section, skipSave);
            
            // After a short delay, check and fix table visibility
            setTimeout(() => {
                fixTableVisibility();
                
                // Apply enhanced table styling
                if (typeof enhance3DTables === 'function') {
                    enhance3DTables();
                }
                
                // Fix condition badges
                if (typeof fixConditionBadges === 'function') {
                    fixConditionBadges();   
                }
            }, 300);
        };
    }
    
    // Initialize enhancement when DOM is loaded
    function initTableVisibilityFix() {
        console.log('Initializing enhanced dashboard display');
        
        // Enhance setActiveSection
        enhanceSetActiveSection();
        
        // Set up periodic checks for table visibility
        setInterval(fixTableVisibility, 2000);
        
        // Fix tables when clicked on tab
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                // Short delay to allow section change to complete
                setTimeout(fixTableVisibility, 500);
            });
        });
        
        // Initial check
        setTimeout(fixTableVisibility, 1000);   
    }
    
    // Run immediately if DOM is already loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTableVisibilityFix);
    } else {
        initTableVisibilityFix();
    }
});

// ... existing code ...

// Add this function to share data between PO modals and views
function getPOData(poId, callback) {
    // First try to get the data from sessionStorage
    const storedData = sessionStorage.getItem('currentPOData');
    if (storedData) {
        try {
            const poData = JSON.parse(storedData);
            if (poData && poData.po_id == poId) {
                console.log('Using PO data from session storage');
                
                // Normalize the data to ensure all properties are available
                const normalizedData = normalizePoData(poData);
                callback(normalizedData);
                return;
            }
        } catch (e) {
            console.error('Error parsing stored PO data:', e);
            // Clear the invalid data
            sessionStorage.removeItem('currentPOData');
        }
    }

    // If not found in storage or ID doesn't match, fetch it
    console.log('Fetching fresh PO data from API');
    showLoading();
    
    fetch(`get_po_details.php?id=${poId}`)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error(`Server error (${response.status}):`, text.substring(0, 200));
                    throw new Error(`Server returned ${response.status}: ${text.substring(0, 100)}`);
                });
            }
            return response.json();
        })
        .then(data => {
            hideLoading();
            
            // Check response structure and extract PO data
            let poData;
            if (data.success && data.po) {
                // Standard response with po object and items array
                poData = {
                    ...data.po,
                    items: data.items || []
                };
            } else if (data.po_id) {
                // Direct PO object
                poData = data;
            } else {
                throw new Error('Invalid data structure received from server');
            }
            
            // Normalize the data structure before storing and using
            const normalizedData = normalizePoData(poData);
            
            // Store for future use
            sessionStorage.setItem('currentPOData', JSON.stringify(normalizedData));
            callback(normalizedData);
        })
        .catch(error => {
            hideLoading();
            console.error('Error fetching PO details:', error);
            if (typeof showError === 'function') {
                showError('Failed to load Purchase Order details: ' + error.message);
            } else {
                Swal.fire('Error', 'Failed to load Purchase Order details. Please try again.', 'error');
            }
        });
}

/**
 * Normalize PO data structure to ensure consistent format
 * @param {object} poData - The PO data to normalize
 * @returns {object} Normalized PO data
 */
function normalizePoData(poData) {
    console.log('Normalizing PO data:', poData);
    
    if (!poData) {
        return {};
    }
    
    // Extract supplier contact details with multiple fallbacks
    const supplier_address = poData.supplier_address || poData.address || '';
    const supplier_email = poData.supplier_email || poData.email || '';
    const supplier_tel = poData.supplier_tel || poData.tel || poData.telephone || '';
    
    // Create a standardized object with all possible fields
    const normalized = {
        po_id: poData.po_id || poData.id || 0,
        po_no: poData.po_no || poData.po_number || '',
        supplier_name: poData.supplier_name || poData.supplier || '',
        supplier_address: supplier_address,
        address: supplier_address, // Include both field names for compatibility
        supplier_email: supplier_email,
        email: supplier_email, // Include both field names for compatibility
        supplier_tel: supplier_tel,
        tel: supplier_tel, // Include both field names for compatibility
        telephone: supplier_tel, // Include both field names for compatibility
        po_date: poData.po_date || '',
        ref_no: poData.ref_no || '',
        mode_of_procurement: poData.mode_of_procurement || '',
        pr_no: poData.pr_no || '',
        pr_date: poData.pr_date || '',
        place_of_delivery: poData.place_of_delivery || poData.delivery_place || '',
        delivery_date: poData.delivery_date || '',
        delivery_term: poData.delivery_term || '60 days from receipt of Purchase Order',
        payment_term: poData.payment_term || 'Full Payment on Full Delivery',
        obligation_request_no: poData.obligation_request_no || '',
        obligation_amount: parseFloat(poData.obligation_amount || 0),
        total_amount: parseFloat(poData.total_amount || 0),
        items: []
    };
    
    // Handle items array if it exists
    if (Array.isArray(poData.items) && poData.items.length > 0) {
        normalized.items = poData.items.map(item => {
            // Check if we need to parse the item from string
            let processedItem = item;
            if (typeof item === 'string') {
                try {
                    processedItem = JSON.parse(item);
                } catch (e) {
                    console.error('Error parsing item JSON:', e);
                    processedItem = { description: item };
                }
            }
            
            // Create a normalized item with consistent field names
            const normalizedItem = {
                id: processedItem.id || processedItem.item_id || null,
                item_id: processedItem.id || processedItem.item_id || null,
                item_name: processedItem.item_name || '',
                unit: processedItem.unit || 'pc',
                description: processedItem.description || processedItem.item_description || '',
                item_description: processedItem.item_description || processedItem.description || '',
                quantity: parseFloat(processedItem.quantity || processedItem.qty || 0),
                qty: parseFloat(processedItem.quantity || processedItem.qty || 0),
                unit_cost: parseFloat(processedItem.unit_cost || processedItem.unit_price || processedItem.price || 0),
                unit_price: parseFloat(processedItem.unit_cost || processedItem.unit_price || processedItem.price || 0),
                amount: parseFloat(processedItem.amount || processedItem.total_cost || processedItem.total || 0),
                total_cost: parseFloat(processedItem.amount || processedItem.total_cost || processedItem.total || 0)
            };
            
            // Calculate amount if not set but we have quantity and unit_cost
            if (normalizedItem.amount === 0 && normalizedItem.quantity > 0 && normalizedItem.unit_cost > 0) {
                normalizedItem.amount = normalizedItem.quantity * normalizedItem.unit_cost;
                normalizedItem.total_cost = normalizedItem.amount;
            }
            
            // Handle the case where data might be stored in a data property
            if (processedItem.data) {
                let dataContent = processedItem.data;
                if (typeof dataContent === 'string') {
                    try {
                        dataContent = JSON.parse(dataContent);
                    } catch (e) {
                        // If it's not JSON, treat it as a description
                        if (!normalizedItem.description) {
                            normalizedItem.description = dataContent;
                            normalizedItem.item_description = dataContent;
                        }
                    }
                }
                
                if (typeof dataContent === 'object' && dataContent !== null) {
                    // Extract data from the parsed object
                    if (dataContent.description && !normalizedItem.description) {
                        normalizedItem.description = dataContent.description;
                        normalizedItem.item_description = dataContent.description;
                    }
                    if (dataContent.quantity && !normalizedItem.quantity) {
                        normalizedItem.quantity = dataContent.quantity;
                        normalizedItem.qty = dataContent.quantity;
                    }
                    if (dataContent.unit && !normalizedItem.unit) {
                        normalizedItem.unit = dataContent.unit;
                    }
                    if (dataContent.unit_cost && !normalizedItem.unit_cost) {
                        normalizedItem.unit_cost = dataContent.unit_cost;
                        normalizedItem.unit_price = dataContent.unit_cost;
                    }
                    if (dataContent.amount && !normalizedItem.amount) {
                        normalizedItem.amount = dataContent.amount;
                        normalizedItem.total_cost = dataContent.amount;
                    }
                }
            }
            
            // Add the textarea field explicitly for viewPO.php compatibility
            normalizedItem.textarea = normalizedItem.description;
            
            return normalizedItem;
        });
    }
    
    // Calculate total amount from items if total is not set
    if (normalized.total_amount === 0 && normalized.items.length > 0) {
        normalized.total_amount = normalized.items.reduce((total, item) => {
            return total + parseFloat(item.amount || 0);
        }, 0);
    }
    
    return normalized;
}

// Function to print PO from the modal
function printPOFromModal(poNo) {
    try {
        console.log("Printing PO from modal: " + poNo);
        
        // Get the PO data from the page
        const poData = window.currentPOData || {};
        
        if (!poData || !poData.po_id) {
            console.error("No PO data found for printing");
            Swal.fire('Error', 'No PO data available for printing. Please try viewing the PO first.', 'error');
            return;
        }
        
        // Get the items from the modal
        const itemsTable = document.querySelector('#poDetailsModal .modal-body table tbody');
        
        if (!itemsTable) {
            console.error("No items table found in the modal");
            Swal.fire('Error', 'No items found for printing. Please try viewing the PO first.', 'error');
            return;
        }
        
        // Extract items from the table
        const items = [];
        const rows = itemsTable.querySelectorAll('tr');
        
        rows.forEach((row, index) => {
            const cols = row.querySelectorAll('td');
            
            if (cols.length >= 5) {
                const item = {
                    id: index + 1,
                    item_id: index + 1,
                    item_name: cols[0].textContent.trim() || '',
                    unit: cols[1].textContent.trim() || '',
                    item_description: cols[2].textContent.trim() || '',
                    description: cols[2].textContent.trim() || '', // Include both field names for compatibility
                    quantity: parseFloat(cols[3].textContent.trim().replace(/,/g, '')) || 0,
                    qty: parseFloat(cols[3].textContent.trim().replace(/,/g, '')) || 0, // Include both field names
                    unit_cost: parseFloat(cols[4].textContent.trim().replace(/[^0-9.-]+/g, '')) || 0,
                    unit_price: parseFloat(cols[4].textContent.trim().replace(/[^0-9.-]+/g, '')) || 0, // Include both field names
                    amount: parseFloat(cols[5].textContent.trim().replace(/[^0-9.-]+/g, '')) || 0,
                    total_cost: parseFloat(cols[5].textContent.trim().replace(/[^0-9.-]+/g, '')) || 0 // Include both field names
                };
                
                items.push(item);
            }
        });
        
        // Ensure all required PO fields are included
        const enhancedPoData = {
            ...poData,
            po_no: poData.po_no || '',
            supplier_name: poData.supplier_name || '',
            supplier_address: poData.supplier_address || poData.address || '',
            supplier_email: poData.supplier_email || poData.email || '',
            supplier_tel: poData.supplier_tel || poData.tel || '',
            po_date: poData.po_date || '',
            delivery_term: poData.delivery_term || '60 days from receipt of Purchase Order',
            payment_term: poData.payment_term || 'Full Payment on Full Delivery',
            place_of_delivery: poData.place_of_delivery || '',
            mode_of_procurement: poData.mode_of_procurement || '',
            ref_no: poData.ref_no || '',
            total_amount: poData.total_amount || calculateItemsTotal(items)
        };
        
        // Prepare the data to send to viewPO.php
        const modalData = {
            po_data: enhancedPoData,
            po_details: enhancedPoData, // Include both field names for compatibility
            items: items
        };
        
        // Encode for URL
        const jsonString = JSON.stringify(modalData);
        const encodedData = encodeURIComponent(jsonString);
        
        // Open in new window and auto-print
        window.open(`viewpo.php?id=${encodeURIComponent(poData.po_id)}&modal_data=${encodedData}&print=true`, '_blank');
    } catch (e) {
        console.error('Error processing PO data for printing:', e);
        Swal.fire('Error', 'Failed to prepare PO for printing. Please try again.', 'error');
    }
}

// Helper function to calculate total from items
function calculateItemsTotal(items) {
    return items.reduce((total, item) => total + (parseFloat(item.amount) || 0), 0);
}

// Function to open PO in a new window from the modal
function openPOInNewWindow(poId) {
    // Close the current modal
    Swal.close();
    
    // Use the shared getPOData function to ensure consistent data
    getPOData(poId, function(poData) {
        try {
            // Ensure items are properly formatted with consistent field names
            const items = (poData.items || []).map(item => {
                // Standardize field names for compatibility
                return {
                    id: item.id || item.item_id || item.po_item_id || null,
                    item_id: item.id || item.item_id || item.po_item_id || null,
                    po_item_id: item.po_item_id || item.id || item.item_id || null,
                    item_name: item.item_name || '',
                    unit: item.unit || 'pc',
                    description: item.description || item.item_description || '',
                    item_description: item.item_description || item.description || '', 
                    textarea: item.item_description || item.description || '',
                    quantity: parseFloat(item.quantity || item.qty || 0),
                    qty: parseFloat(item.quantity || item.qty || 0),
                    unit_cost: parseFloat(item.unit_cost || item.unit_price || item.price || 0),
                    unit_price: parseFloat(item.unit_cost || item.unit_price || item.price || 0),
                    amount: parseFloat(item.amount || item.total_cost || (parseFloat(item.quantity || item.qty || 0) * parseFloat(item.unit_cost || item.unit_price || item.price || 0)) || 0),
                    total_cost: parseFloat(item.amount || item.total_cost || (parseFloat(item.quantity || item.qty || 0) * parseFloat(item.unit_cost || item.unit_price || item.price || 0)) || 0)
                };
            });
            
            // Extract supplier contact details with proper fallbacks
            const supplierAddress = poData.supplier_address || poData.address || '';
            const supplierEmail = poData.supplier_email || poData.email || '';
            const supplierTel = poData.supplier_tel || poData.telephone || poData.tel || '';
            
            // Create a standardized version of the PO data
            const modalData = {
                po_id: poId,
                po_no: poData.po_no || poData.po_number || '',
                po_date: poData.po_date || poData.date || new Date().toISOString().split('T')[0],
                pr_no: poData.pr_no || poData.pr_number || '',
                pr_date: poData.pr_date || '',
                supplier_name: poData.supplier_name || poData.supplier || '',
                items: items,
                
                // Include all field variations at the root level
                supplier_address: supplierAddress,
                address: supplierAddress,
                supplier_email: supplierEmail,
                email: supplierEmail, 
                supplier_tel: supplierTel,
                telephone: supplierTel,
                tel: supplierTel,
                
                // Include other fields at the root level
                ref_no: poData.ref_no || '',
                mode_of_procurement: poData.mode_of_procurement || poData.procurement_mode || 'Public Bidding',
                place_of_delivery: poData.place_of_delivery || poData.delivery_place || '',
                delivery_date: poData.delivery_date || '',
                delivery_term: poData.delivery_term || poData.delivery_terms || '60 days from receipt of Purchase Order',
                payment_term: poData.payment_term || poData.payment_terms || 'Full Payment on Full Delivery',
                obligation_request_no: poData.obligation_request_no || '',
                obligation_amount: parseFloat(poData.obligation_amount || 0),
                total_amount: parseFloat(poData.total_amount || calculateItemsTotal(items)),
                
                // Include both po_details and po_data for backward compatibility
                po_details: {
                    po_id: poId,
                    po_no: poData.po_no || poData.po_number || '',
                    po_date: poData.po_date || poData.date || new Date().toISOString().split('T')[0],
                    pr_no: poData.pr_no || poData.pr_number || '',
                    pr_date: poData.pr_date || '',
                    ref_no: poData.ref_no || '',
                    supplier_name: poData.supplier_name || poData.supplier || '',
                    
                    // Include all field variations here too
                    supplier_address: supplierAddress,
                    address: supplierAddress,
                    supplier_email: supplierEmail,
                    email: supplierEmail,
                    supplier_tel: supplierTel,
                    telephone: supplierTel,
                    tel: supplierTel,
                    
                    mode_of_procurement: poData.mode_of_procurement || poData.procurement_mode || 'Public Bidding',
                    place_of_delivery: poData.place_of_delivery || poData.delivery_place || '',
                    delivery_date: poData.delivery_date || '',
                    delivery_term: poData.delivery_term || poData.delivery_terms || '60 days from receipt of Purchase Order',
                    payment_term: poData.payment_term || poData.payment_terms || 'Full Payment on Full Delivery',
                    obligation_request_no: poData.obligation_request_no || '',
                    obligation_amount: parseFloat(poData.obligation_amount || 0),
                    total_amount: parseFloat(poData.total_amount || calculateItemsTotal(items))
                },
                po_data: {
                    po_id: poId,
                    po_no: poData.po_no || poData.po_number || '',
                    po_date: poData.po_date || poData.date || new Date().toISOString().split('T')[0],
                    pr_no: poData.pr_no || poData.pr_number || '',
                    pr_date: poData.pr_date || '',
                    ref_no: poData.ref_no || '',
                    supplier_name: poData.supplier_name || poData.supplier || '',
                    supplier_address: supplierAddress,
                    address: supplierAddress,
                    supplier_email: supplierEmail,
                    email: supplierEmail,
                    supplier_tel: supplierTel,
                    telephone: supplierTel,
                    tel: supplierTel,
                    mode_of_procurement: poData.mode_of_procurement || poData.procurement_mode || 'Public Bidding',
                    place_of_delivery: poData.place_of_delivery || poData.delivery_place || '',
                    delivery_date: poData.delivery_date || '',
                    delivery_term: poData.delivery_term || poData.delivery_terms || '60 days from receipt of Purchase Order',
                    payment_term: poData.payment_term || poData.payment_terms || 'Full Payment on Full Delivery',
                    obligation_request_no: poData.obligation_request_no || '',
                    obligation_amount: parseFloat(poData.obligation_amount || 0),
                    total_amount: parseFloat(poData.total_amount || calculateItemsTotal(items)),
                    items: items
                }
            };
            
            // Log the data for debugging
            console.log('Opening PO with processed data:', modalData);
            
            // Encode the data
            const encodedData = encodeURIComponent(JSON.stringify(modalData));
            
            // Open the window with the data
            window.open(`viewpo.php?id=${poId}&modal_data=${encodedData}`, '_blank');
        } catch (error) {
            console.error('Error processing PO data:', error);
            Swal.fire('Error', 'Failed to process PO data. Please try again.', 'error');
        }
    });
}

// ... existing code ...


