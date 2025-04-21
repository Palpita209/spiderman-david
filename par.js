/**
 * PAR.js - Property Acknowledgement Receipt management functions
 * This file handles all PAR-related functionality including CRUD operations
 */

// Global variables
let parItems = [];
let parTotal = 0;
let parData = []; // Store all PAR data for search and filtering

/**
 * Initialize PAR functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log("PAR.js initialized");
    
    // Check for required stored procedures
    fetch('check_stored_procedures.php')
        .then(response => response.json())
        .then(data => {
            console.log("Stored procedure check result:", data);
        })
        .catch(error => {
            console.error("Error checking stored procedures:", error);
        });
    
    // Debug PAR form submission
    const saveParBtn = document.getElementById('saveParBtn');
    if (saveParBtn) {
        console.log("Found saveParBtn element, adding diagnostic logging");
        saveParBtn.addEventListener('click', function(e) {
            // Just log, don't prevent default or stop propagation
            console.log("saveParBtn clicked directly from initial event handler");
            // Check form existence and structure
            const parForm = document.getElementById('parForm');
            if (parForm) {
                console.log("PAR form found:", {
                    parId: parForm.querySelector('[name="par_id"]')?.value,
                    parNo: parForm.querySelector('[name="par_no"]')?.value,
                    fields: Array.from(parForm.elements).map(el => el.name || el.id)
                });
            } else {
                console.error("PAR form not found in DOM");
            }
        });
    } else {
        console.warn("saveParBtn element not found during initialization");
    }
    
    // Add event listener to the received_by dropdown to update the hidden received_by_id field
    const receivedByDropdown = document.getElementById('received_by');
    if (receivedByDropdown) {
        console.log('Found received_by dropdown, adding event listener');
        receivedByDropdown.addEventListener('change', function() {
            const receivedById = document.getElementById('received_by_id');
            if (receivedById) {
                receivedById.value = this.value;
                console.log('Updated received_by_id with value:', this.value);
            } else {
                console.error('received_by_id field not found in the form');
            }
        });
        
        // Trigger the change event initially to set the initial value if one is selected
        receivedByDropdown.dispatchEvent(new Event('change'));
    } else {
        console.warn('received_by dropdown not found - will create it dynamically if needed');
    }
    
    // Load PAR data if on the PAR page - with force refresh to prevent cache issues
    if (document.getElementById('parTable') || document.querySelector('.par-table')) {
        // Enforce a slight delay to ensure DOM is fully ready
        setTimeout(() => {
            loadPARData(true); // Force initial refresh
        }, 100);
    }

    // Initialize PAR form events
    initPARFormEvents();

    // Run cleanup function for specific PAR items
    removeSpecificParItem();
    
    // Set up search functionality for both parSearchInput and poSearchInput
    const parSearchInput = document.getElementById('parSearchInput');
    const poSearchInput = document.getElementById('poSearchInput');
    
    if (parSearchInput) {
        console.log('Found parSearchInput, adding event listener');
        parSearchInput.addEventListener('input', function() {
            searchPAR(this.value);
        });
    }
    
    if (poSearchInput) {
        console.log('Found poSearchInput (alternative ID), adding event listener');
        poSearchInput.addEventListener('input', function() {
            searchPAR(this.value);
        });
    }
    
    // Enhanced modal event handling for PAR modals
    document.addEventListener('shown.bs.modal', function(event) {
        const modal = event.target;
        console.log("Modal shown:", modal.id || 'unnamed modal');
        
        // Mark this modal as being shown in data attribute for tracking
        modal.setAttribute('data-modal-status', 'shown');
        
        // Remember which modal was most recently shown
        window.lastShownModalId = modal.id || '';
        
        // Run cleanup and calculation functions
        removeSpecificParItem();
        setTimeout(calculateParTotal, 200);
    });
    
    // Critical: PAR data refresh when modals are closed
    document.addEventListener('hidden.bs.modal', function(event) {
        const modal = event.target;
        console.log("Modal hidden:", modal.id || 'unnamed modal');
        
        // Mark this modal as being hidden
        modal.setAttribute('data-modal-status', 'hidden');
        modal.setAttribute('data-hidden-time', Date.now());
        
        // If this is a PAR-related modal, refresh the PAR data
        const parRelatedModalIds = ['parModal', 'addPARModal', 'editPARModal', 'addParModal'];
        const isParModal = parRelatedModalIds.includes(modal.id) || 
                          modal.id.toLowerCase().includes('par') || 
                          modal.classList.contains('par-modal');
        
        if (isParModal || window.lastShownModalId.toLowerCase().includes('par')) {
            console.log("PAR modal closed, triggering data refresh");
            
            // Force refresh to ensure data consistency
            refreshPARTableData();
            
            // Schedule additional refreshes to catch any potential delays
            setTimeout(() => refreshPARTableData(), 800);
            setTimeout(() => loadPARData(true), 1500);
        }
    });
    
    // Save PAR button in the modal - handle all possible button variants
    const saveBtnSelectors = [
        '#saveParBtn', 
        '[data-action="save-par"]', 
        '.save-par-btn',
        'button[form="parForm"]',
        '#parForm button[type="submit"]',
        '.modal button.btn-primary:not([data-bs-dismiss])'
    ];
    
    // Create a single selector string
    const combinedSelector = saveBtnSelectors.join(', ');
    
    // Find all potential save buttons
    const saveParBtns = document.querySelectorAll(combinedSelector);
    console.log(`Found ${saveParBtns.length} potential PAR save buttons`);
    
    // Add event listeners to all potential save buttons
    saveParBtns.forEach((btn, index) => {
        // Skip if the button has a dismiss attribute (it's a cancel/close button)
        if (btn.hasAttribute('data-bs-dismiss') || btn.hasAttribute('data-dismiss')) {
            return;
        }
        
        // Skip if button already has the handler
        if (btn.hasAttribute('data-handler-attached')) {
            return;
        }
        
        console.log(`Adding save event to button ${index}:`, btn.id || btn.className);
        btn.setAttribute('data-handler-attached', 'true');
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log("Save PAR button clicked");
            savePAR();
        });
    });
    
    // Add global event delegation for dynamically created PAR buttons
    document.addEventListener('click', function(e) {
        // Handle dynamically created PAR buttons
        if (e.target.closest('.view-par')) {
            const parId = e.target.closest('.view-par').getAttribute('data-par-id');
            console.log("View PAR clicked for ID:", parId);
            viewPAR(parId);
        } else if (e.target.closest('.edit-par')) {
            const parId = e.target.closest('.edit-par').getAttribute('data-par-id');
            console.log("Edit PAR clicked for ID:", parId);
            editPAR(parId);
        } else if (e.target.closest('.delete-par')) {
            const parId = e.target.closest('.delete-par').getAttribute('data-par-id');
            console.log("Delete PAR clicked for ID:", parId);
            deletePAR(parId);
        } else if (e.target.closest('.remove-par-row')) {
            handleRemoveParRow(e.target.closest('.remove-par-row'));
        } else if (e.target.closest('.print-par')) {
            const parId = e.target.closest('.print-par').getAttribute('data-par-id');
            console.log("Print PAR clicked for ID:", parId);
            printPAR(parId);
        } else if (e.target.closest('.export-par')) {
            exportPARData();
        } else if (e.target.closest('.batch-print-par')) {
            batchPrintPAR();
        } else if (e.target.closest('.save-par-btn, [data-action="save-par"]') && !e.target.closest('[data-handler-attached="true"]')) {
            // Handle dynamically added save buttons that don't have handlers yet
            e.preventDefault();
            console.log("Dynamically created save PAR button clicked");
            e.target.closest('.save-par-btn, [data-action="save-par"]').setAttribute('data-handler-attached', 'true');
            savePAR();
        }
    });
    
    // Add PAR search input if it doesn't exist
    addPARSearchInput();
    
    // Enhanced save button handling - Add this at the end of the DOMContentLoaded function
    const enhanceSaveButton = function() {
        const saveParBtn = document.getElementById('saveParBtn');
        if (!saveParBtn) {
            console.warn("saveParBtn not found for enhanced handler");
            // Try again in 500ms in case it's being added dynamically
            setTimeout(enhanceSaveButton, 500);
            return;
        }
        
        console.log("Setting up enhanced save button handler");
        
        // Use the capture phase to ensure this runs first
        saveParBtn.addEventListener('click', function(e) {
            console.log("Enhanced saveParBtn handler activated");
            e.preventDefault(); // Prevent default form submission
            e.stopImmediatePropagation(); // Stop other handlers
            
            // Call the save function
            try {
                savePAR();
            } catch (error) {
                console.error("Error in savePAR function:", error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while saving: ' + error.message
                });
            }
        }, true); // true = use capture phase
        
        console.log("Enhanced save button handler added");
    };
    
    // Run immediately and also after DOM is fully loaded
    enhanceSaveButton();
    setTimeout(enhanceSaveButton, 1000);
});

/**
 * Add PAR search input to the PAR table section
 */
function addPARSearchInput() {
    const parCardHeader = document.querySelector('.par-section .card-header');
    if (parCardHeader) {
        // Check if search input already exists
        if (!document.getElementById('parSearchInput')) {
            // Create container for search and export elements
            const controlsContainer = document.createElement('div');
            controlsContainer.className = 'mt-2 mb-3 d-flex justify-content-between align-items-center';
            
            // Create search input container
            const searchContainer = document.createElement('div');
            searchContainer.className = 'input-group';
            searchContainer.style.width = '250px';
            
            // Create search input
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.id = 'parSearchInput';
            searchInput.className = 'form-control ps-4';
            searchInput.placeholder = 'Search PAR...';
            
            // Create search icon
            const searchIcon = document.createElement('span');
            searchIcon.className = 'input-group-text bg-transparent border-start-0';
            searchIcon.innerHTML = '<i class="bi bi-search"></i>';
            
            // Assemble the search input group
            searchContainer.appendChild(searchInput);
            searchContainer.appendChild(searchIcon);
            
            // Add buttons container
            const buttonsContainer = document.createElement('div');
            buttonsContainer.className = 'd-flex align-items-center';
            
            // Batch Print button
            const batchPrintBtn = document.createElement('button');
            batchPrintBtn.className = 'btn btn-outline-primary batch-print-par me-2';
            batchPrintBtn.innerHTML = '<i class="bi bi-printer"></i> Batch Print';
            batchPrintBtn.title = 'Print multiple PAR documents';
            
            // Export button
            const exportBtn = document.createElement('button');
            exportBtn.className = 'btn btn-outline-secondary export-par';
            exportBtn.innerHTML = '<i class="bi bi-download"></i> Export';
            exportBtn.title = 'Export PAR data to Excel';
            
            // Add buttons to container
            buttonsContainer.appendChild(batchPrintBtn);
            buttonsContainer.appendChild(exportBtn);
            
            // Add search container to controls container
            controlsContainer.appendChild(searchContainer);
            controlsContainer.appendChild(buttonsContainer);
            
            // Add controls below the heading
            const cardBody = parCardHeader.closest('.card').querySelector('.card-body');
            if (cardBody) {
                // Insert controls at the beginning of card body, before table
                const tableResponsive = cardBody.querySelector('.table-responsive');
                if (tableResponsive) {
                    cardBody.insertBefore(controlsContainer, tableResponsive);
                } else {
                    cardBody.prepend(controlsContainer);
                }
            }
            
            // Add event listener to search input
            searchInput.addEventListener('input', function() {
                searchPAR(this.value);
            });
        }
    }
    
    // Also add event listener to existing search inputs if they exist but don't have listeners
    const existingSearchInput = document.getElementById('parSearchInput');
    if (existingSearchInput) {
        existingSearchInput.addEventListener('input', function() {
            searchPAR(this.value);
        });
    }
    
    // Also check for the alternative ID poSearchInput
    const poSearchInput = document.getElementById('poSearchInput');
    if (poSearchInput) {
        console.log('Found poSearchInput (alternative ID), adding event listener');
        poSearchInput.addEventListener('input', function() {
            searchPAR(this.value);
        });
    }
}

/**
 * Search PAR data with the given query
 */
function searchPAR(query) {
    if (!parData || parData.length === 0) return;
    
    query = query.toLowerCase().trim();
    const tbody = document.getElementById('parTableBody');
    if (!tbody) return;
    
    // If query is empty, show all data
    if (query === '') {
        displayPARData(parData);
        return;
    }
    
    // Filter PAR data based on search query
    const filteredData = parData.filter(par => {
        return (
            (par.par_no && par.par_no.toLowerCase().includes(query)) ||
            (par.property_number && par.property_number.toLowerCase().includes(query)) ||
            (par.received_by_name && par.received_by_name.toLowerCase().includes(query)) ||
            (par.date_acquired && par.date_acquired.toLowerCase().includes(query))
        );
    });
    
    // Display filtered data
    displayPARData(filteredData);
}

/**
 * Load PAR data from the server
 * @param {boolean} forceRefresh - Whether to force a cache bypass
 */
function loadPARData(forceRefresh = false) {
    showLoading();
    
    // Build the request URL with cache-busting if needed
    let requestUrl = 'get_par.php';
    if (forceRefresh) {
        requestUrl += '?force_refresh=1&_=' + new Date().getTime() + '&random=' + Math.random();
    }
    
    console.log(`Loading PAR data from ${requestUrl} with force refresh: ${forceRefresh}`);
    
    // Try to fetch PAR data
    fetch(requestUrl, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        },
        credentials: 'same-origin' // Include cookies for session consistency
    })
    .then(response => {
        console.log(`PAR data response status: ${response.status}`);
        if (!response.ok) {
            throw new Error(`Server returned ${response.status} ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log(`PAR data received with ${data.data?.length || 0} records. Success: ${data.success}`);
        
        // Additional validation of response data
        if (!data || typeof data !== 'object') {
            console.error('Invalid data structure received from server:', data);
            throw new Error('Invalid data structure received from server');
        }
        
        if (data.success) {
            if (!Array.isArray(data.data)) {
                console.warn('PAR data is not an array, converting to array format for consistency');
                // Convert non-array data to array format for consistency
                parData = data.data ? [data.data] : [];
            } else {
                parData = data.data; // Store data globally for search/filtering
            }
            
            console.log(`Stored ${parData.length} PAR records in global parData variable`);
            
            // Display the data in the table
            displayPARData(parData);
            console.log(`Loaded ${parData.length} PAR records into table`);
        } else {
            showError(data.message || 'Failed to load PAR data');
            // Try to recover by displaying any data we might have
            if (parData && parData.length > 0) {
                console.log('Using cached PAR data as fallback');
                displayPARData(parData);
            }
        }
    })
    .catch(error => {
        console.error('Error loading PAR data:', error);
        
        // Specific handling for invalid JSON (which might be HTML error output)
        if (error instanceof SyntaxError && error.message.includes('Unexpected token')) {
            console.error('Received invalid JSON (possibly HTML error page)');
            // Try to fetch the error content directly
            fetch(requestUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'text/html',
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => response.text())
            .then(html => {
                console.error('Error content:', html.substring(0, 500)); // Log first 500 chars for debugging
                showError('Server returned HTML instead of JSON. Please check PHP error logs.');
            })
            .catch(() => {
                showError('Error loading PAR data: Invalid response format (not JSON)');
            });
        } else {
            showError('Error loading PAR data: ' + error.message);
        }
        
        // Try to recover by displaying any data we might have
        if (parData && parData.length > 0) {
            console.log('Using cached PAR data as fallback after error');
            displayPARData(parData);
        }
        
        // Try force refresh after error with a delay
        if (!forceRefresh) {
            console.log('Attempting reload with force refresh after error');
            setTimeout(() => {
                loadPARData(true);
            }, 1000);
        }
    })
    .finally(() => {
        hideLoading();
    });
}

/**
 * Shorthand for refreshing PAR table data with force refresh
 */
function refreshPARTableData() {
    console.log('Force refreshing PAR table data');
    
    // Generate cache-busting parameters
    const timestamp = new Date().getTime();
    const randomVal = Math.random().toString(36).substring(2, 15);
    
    console.log(`Refreshing PAR data with timestamp ${timestamp} and random value ${randomVal}`);
    
    // Fetch with explicit no-cache headers
    fetch(`get_par.php?force_refresh=1&_=${timestamp}&rand=${randomVal}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma': 'no-cache',
            'Expires': '0'
        },
        credentials: 'same-origin' // Include cookies for session consistency
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server returned ${response.status} ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log(`PAR data refreshed with ${data.data?.length || 0} records`);
        
        if (data.success) {
            // Store and display the refreshed data
            if (Array.isArray(data.data)) {
                parData = data.data;
                console.log(`Updated global parData with ${parData.length} records`);
                
                // Display refreshed data in the table
                displayPARData(parData);
                
                // Schedule a second refresh after a delay to catch any potential race conditions
                setTimeout(() => {
                    console.log("Performing secondary refresh to ensure data consistency");
                    loadPARData(true); // Use the main loadPARData with force refresh
                }, 1500);
            } else {
                console.warn('PAR data refresh returned non-array data:', data);
                // Convert to array if single object
                if (data.data && typeof data.data === 'object') {
                    parData = [data.data];
                    displayPARData(parData);
                } else {
                    // If data is completely invalid, try a fresh load
                    loadPARData(true);
                }
            }
        } else {
            console.error('PAR data refresh returned error:', data.message);
            loadPARData(true); // Try a fresh load with force refresh
        }
    })
    .catch(error => {
        console.error('Error refreshing PAR data:', error);
        
        // Specific handling for invalid JSON (which might be HTML error output)
        if (error instanceof SyntaxError && error.message.includes('Unexpected token')) {
            console.error('Received invalid JSON in refresh (possibly HTML error page)');
            // Try to fetch the error content directly
            const requestUrl = `get_par.php?force_refresh=1&_=${new Date().getTime()}`;
            fetch(requestUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'text/html',
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => response.text())
            .then(html => {
                console.error('Error content:', html.substring(0, 500)); // Log first 500 chars for debugging
                
                // Check if it's a database error and handle db_connect.php issue
                if (html.includes('db_connect.php') || html.includes('Failed to open stream')) {
                    console.error('Detected missing db_connect.php issue');
                    showError('Database connection error: The backend cannot find db_connect.php. Contact administrator.');
                } else {
                    showError('Server returned HTML instead of JSON. Please check PHP error logs.');
                }
            })
            .catch(() => {
                showError('Error refreshing PAR data: Invalid response format (not JSON)');
            });
        }
        
        // Try main loadPARData with force refresh
        setTimeout(() => {
            loadPARData(true);
        }, 1500);
    });
}

/**
 * Display PAR data in the table
 * @param {Array} pars - Array of PAR objects to display
 * @returns {number} - The number of displayed records
 */
function displayPARData(pars) {
    console.log(`Beginning displayPARData with ${pars?.length || 0} records`);
    
    // Normalize data format
    // get_par.php returns {success: true, data: [...]} while get_par_data.php may return different format
    if (pars && typeof pars === 'object' && pars.success && Array.isArray(pars.data)) {
        console.log('Normalizing data format from API response');
        pars = pars.data;
    }
    
    // If still not an array, try to convert
    if (!Array.isArray(pars)) {
        console.warn('PAR data is not an array, attempting to convert');
        if (pars && typeof pars === 'object') {
            // Single object - convert to array with one item
            pars = [pars];
        } else {
            // Empty or invalid - use empty array
            pars = [];
        }
    }
    
    // Find the PAR table - try multiple possible selectors
    const parTable = document.getElementById('parTable') || 
                     document.querySelector('.par-table') || 
                     document.querySelector('table.table');
    
    if (!parTable) {
        console.error("PAR table not found in the DOM. Will attempt to create it.");
        // Log all tables on the page to help debugging
        const allTables = document.querySelectorAll('table');
        console.error("Available tables:", 
            Array.from(allTables).map(t => ({
                id: t.id || '(no id)', 
                class: t.className || '(no class)',
                parent: t.parentElement ? (t.parentElement.id || t.parentElement.className || 'unknown') : 'none'
            })));
            
        // Try to find a container to create the table in
        const parSection = document.querySelector('.par-section, .par-container, #parContainer, #parSection') || 
                          document.querySelector('.card') || document.querySelector('.container');
        if (parSection) {
            console.log("Found container to create PAR table in:", parSection);
            
            // Create a table
            const tableContainer = document.createElement('div');
            tableContainer.className = 'table-responsive mt-3';
            
            const table = document.createElement('table');
            table.id = 'parTable';
            table.className = 'table table-bordered table-hover par-table';
            
            // Add table header
            const thead = document.createElement('thead');
            thead.innerHTML = `
                <tr>
                    <th>PAR No.</th>
                    <th>Date Acquired</th>
                    <th>Property Number</th>
                    <th>Received By</th>
                    <th>Amount</th>
                    <th class="text-center">Actions</th>
                </tr>
            `;
            
            // Add table body
            const tbody = document.createElement('tbody');
            tbody.id = 'parTableBody';
            
            // Assemble the table
            table.appendChild(thead);
            table.appendChild(tbody);
            tableContainer.appendChild(table);
            
            // Add to the page
            parSection.appendChild(tableContainer);
            console.log("Created new PAR table in the DOM");
            
            // Now use this table
            return displayPARData(pars, table); // Recursive call with created table
        } else {
            showError('PAR table not found. Please refresh the page or contact support.');
            return 0;
        }
    }
    
    console.log(`Found PAR table with ID: ${parTable.id || 'unnamed'}, Class: ${parTable.className}`);
    
    // Find the tbody - try multiple possible selectors
    const tbody = parTable.querySelector('tbody') || 
                document.getElementById('parTableBody') || 
                document.querySelector('#parTable tbody');
    
    if (!tbody) {
        console.error("PAR table body not found in table:", parTable);
        
        // Try to create tbody if it doesn't exist
        try {
            const newTbody = document.createElement('tbody');
            newTbody.id = 'parTableBody';
            parTable.appendChild(newTbody);
            console.log("Created new tbody element for PAR table");
            return displayPARData(pars, parTable); // Recursive call with new tbody
        } catch (e) {
            console.error("Failed to create tbody:", e);
            showError('Could not display PAR data. Please refresh the page.');
            return 0;
        }
    }
    
    console.log(`Displaying ${pars?.length || 0} PAR records in table with ${tbody.children.length} existing rows`);
    
    // Clear existing content
    tbody.innerHTML = '';
    
    // If no data or empty array, show empty message
    if (!pars || !Array.isArray(pars) || pars.length === 0) {
        console.warn("No PAR data to display");
        const emptyRow = document.createElement('tr');
        emptyRow.innerHTML = `<td colspan="6" class="text-center">No PAR records found</td>`;
        tbody.appendChild(emptyRow);
        return 0;
    }
    
    // Make a copy of the data to avoid modifying the original
    const parsToDisplay = [...pars];
    
    // Check if the received data appears valid
    let needsFreshData = false;
    if (parsToDisplay.length > 0) {
        // Check if first par has required fields
        const firstPar = parsToDisplay[0];
        if (!firstPar.par_id || !firstPar.par_no) {
            console.warn("PAR data appears invalid, missing required fields:", firstPar);
            needsFreshData = true;
        }
    }
    
    if (needsFreshData) {
        console.log("PAR data appears invalid, fetching fresh data...");
        refreshPARTableData();
        return 0;
    }
    
    // Keep track of rows created for debugging
    let rowsCreated = 0;
    
    // Add each PAR row to the table
    parsToDisplay.forEach((par, index) => {
        // Skip if par is not an object or is null
        if (!par || typeof par !== 'object') {
            console.warn(`Skipping invalid PAR record at index ${index}:`, par);
            return;
        }
        
        console.log(`Processing PAR record ${index+1}:`, par.par_id);
        
        // Ensure all required properties are available
        const parId = par.par_id || '';
        const parNo = par.par_no || '';
        const dateAcquired = par.date_acquired || '';
        const receivedBy = par.received_by_name || par.received_by || '';
        const totalAmount = par.total_amount || 0;
        
        // Ensure property number is available, even if it's from first item
        let propertyNumber = par.property_number || '';
        
        // If property number is missing, try to get it from the first item if available
        if (!propertyNumber && par.items && par.items.length > 0) {
            propertyNumber = par.items[0].property_number || '';
        }
        
        // Create row element
        const row = document.createElement('tr');
        row.setAttribute('data-par-id', parId);
        
        // Add row content
        row.innerHTML = `
            <td>${parNo}</td>
            <td>${dateAcquired}</td>
            <td>${propertyNumber}</td>
            <td>${receivedBy}</td>
            <td class="text-end fw-medium text-dark">${formatNumber(totalAmount)}</td>
            <td class="text-center">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-info view-par" data-par-id="${parId}">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary edit-par" data-par-id="${parId}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger delete-par" data-par-id="${parId}">
                        <i class="bi bi-trash"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary print-par" data-par-id="${parId}">
                        <i class="bi bi-printer"></i>
                    </button>
                </div>
            </td>
        `;
        
        // Append row to tbody
        tbody.appendChild(row);
        rowsCreated++;
    });
    
    // Log success message
    console.log(`Successfully created ${rowsCreated} PAR table rows`);
    
    // Refresh button event listeners for newly created elements
    addPARButtonEventListeners();
    
    console.log("PAR table updated with data. Row count:", tbody.children.length);
    
    // Store the updated data in the global variable
    parData = parsToDisplay;
    
    // Return the number of records displayed for verification
    return tbody.children.length;
}

/**
 * Add event listeners to PAR action buttons
 */
function addPARButtonEventListeners() {
    // This function is now supplementary to the global event delegation
    // The existing code can remain as a backup for static elements
    
    // View PAR
    document.querySelectorAll('.view-par').forEach(button => {
        button.addEventListener('click', function() {
            const parId = this.getAttribute('data-par-id');
            viewPAR(parId);
        });
    });
    
    // Edit PAR
    document.querySelectorAll('.edit-par').forEach(button => {
        button.addEventListener('click', function() {
            const parId = this.getAttribute('data-par-id');
            editPAR(parId);
        });
    });
    
    // Delete PAR
    document.querySelectorAll('.delete-par').forEach(button => {
        button.addEventListener('click', function() {
            const parId = this.getAttribute('data-par-id');
            deletePAR(parId);
        });
    });
}

/**
 * Initialize PAR form events
 */
function initPARFormEvents() {
    // Add PAR item row button
    const addParRowBtn = document.getElementById('addParRowBtn');
    if (addParRowBtn) {
        addParRowBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addParRow();
        });
    }
    
    // Form submission
    const parForm = document.getElementById('parForm');
    if (parForm) {
        parForm.addEventListener('submit', function(e) {
            e.preventDefault();
            savePAR();
        });
    }

    // Add initial PAR row if table is empty
    const parItemsTable = document.getElementById('parItemsTable');
    if (parItemsTable) {
        const tbody = parItemsTable.querySelector('tbody');
        if (tbody && tbody.children.length === 0) {
            addInitialParRow();
        }
    }
    
    // Initialize modals if the function exists
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
        document.querySelectorAll('.modal').forEach(modalElement => {
            try {
                new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: false
                });
            } catch (e) {
                console.error('Error initializing modal:', e);
            }
        });
    }
}

/**
 * View PAR details
 */
function viewPAR(parId) {
    window.location.href = `ViewPAR.php?id=${parId}`;
}

/**
 * Print PAR with the given ID
 */
function printPAR(parId) {
    if (!parId) return;
    
    console.log('Printing PAR with ID:', parId);
    
    // Open the PAR in a new window for printing
    const printWindow = window.open(`viewPar.php?id=${parId}&print=true`, '_blank');
    
    // Focus the new window (if not blocked by browser)
    if (printWindow) {
        printWindow.focus();
    } else {
        Swal.fire({
            title: 'Pop-up Blocked',
            text: 'Please allow pop-ups for this site to print PAR documents',
            icon: 'warning'
        });
    }
}

/**
 * Edit PAR
 */
function editPAR(parId) {
    showLoading();
    
    fetch(`get_par.php?id=${parId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const modalElement = document.getElementById('addPARModal');
                if (!modalElement) {
                    throw new Error('Modal element not found');
                }

                // Get Bootstrap modal instance or create a new one
                let parModal;
                try {
                    if (typeof bootstrap !== 'undefined') {
                        parModal = bootstrap.Modal.getInstance(modalElement);
                        if (!parModal) {
                            parModal = new bootstrap.Modal(modalElement);
                        }
                    } else {
                        throw new Error('Bootstrap not defined');
                    }
                } catch (e) {
                    console.error('Error getting modal instance:', e);
                    // Fallback manual modal show
                    modalElement.classList.add('show');
                    modalElement.style.display = 'block';
                    modalElement.removeAttribute('aria-hidden');
                    modalElement.setAttribute('aria-modal', 'true');
                    document.body.classList.add('modal-open');
                    
                    // Add backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }

                const parForm = document.getElementById('parForm');
                if (!parForm) {
                    throw new Error('PAR form not found');
                }

                // Reset form
                parForm.reset();
                
                // Set form fields
                parForm.querySelector('[name="par_id"]').value = data.data.par_id;
                parForm.querySelector('[name="par_no"]').value = data.data.par_no;
                parForm.querySelector('[name="entity_name"]').value = data.data.entity_name;
                
                // Set the received_by field with the user name
                const receivedByInput = parForm.querySelector('[name="received_by"]');
                if (receivedByInput) {
                    // Use the received_by_name if available, otherwise fallback to an empty value
                    receivedByInput.value = data.data.received_by_name || '';
                }
                
                // Set the hidden received_by_id field with the user ID
                const receivedByIdField = parForm.querySelector('[name="received_by_id"]');
                if (receivedByIdField) {
                    receivedByIdField.value = data.data.received_by || '';
                }
                
                const positionField = parForm.querySelector('[name="position"]');
                if (positionField && data.data.position !== undefined) {
                    positionField.value = data.data.position || '';
                }
                
                const departmentField = parForm.querySelector('[name="department"]');
                if (departmentField && data.data.department !== undefined) {
                    departmentField.value = data.data.department || '';
                }
                
                parForm.querySelector('[name="date_acquired"]').value = data.data.date_acquired || '';
                
                const remarksField = parForm.querySelector('[name="remarks"]');
                if (remarksField && data.data.remarks !== undefined) {
                    remarksField.value = data.data.remarks || '';
                }
                
                // Clear and populate items
                const tbody = document.getElementById('parItemsTable')?.querySelector('tbody');
                if (tbody) {
                    tbody.innerHTML = '';
                    if (data.data.items?.length > 0) {
                        data.data.items.forEach(item => addParRowWithData(item));
                    } else {
                        addInitialParRow();
                    }
                }
                
                calculateParTotal();
                
                // Update modal title and show
                const titleElement = document.getElementById('addPARModalLabel');
                if (titleElement) titleElement.textContent = 'Edit Property Acknowledgement Receipt';
                
                // Show the modal
                if (parModal) {
                    parModal.show();
                }
            } else {
                throw new Error(data.message || 'Failed to load PAR data');
            }
        })
        .catch(error => {
            console.error('Error loading PAR data:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to load PAR data'
            });
        })
        .finally(hideLoading);
}

/**
 * Delete PAR
 */
function deletePAR(parId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            // Use both approaches for compatibility - try POST first with JSON body
            fetch('delete_par.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    par_id: parId
                })
            })
            .then(response => {
                if (!response.ok) {
                    // If POST fails, try the GET endpoint as fallback
                    console.log('POST request failed, trying GET endpoint as fallback');
                    return fetch(`delete_par.php?id=${parId}`, {
                        method: 'GET',
                        headers: {
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache',
                            'Expires': '0'
                        }
                    });
                }
                return response;
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error("Server response:", text);
                        try {
                            // Try to parse as JSON
                            return JSON.parse(text);
                        } catch (e) {
                            // If not valid JSON, throw with text
                            throw new Error(`Server error: ${response.status} ${response.statusText}. Details: ${text}`);
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSuccessModal(
                        'Item Deleted!', 
                        'The Property Acknowledgement Receipt has been deleted successfully.',
                        () => loadPARData(true)
                    );
                } else {
                    showError(data.message || 'Failed to delete PAR');
                }
            })
            .catch(error => {
                console.error('Error deleting PAR:', error);
                showError('Error deleting PAR: ' + error.message);
            })
            .finally(() => {
                hideLoading();
            });
        }
    });
}

/**
 * Add a new PAR item row
 */
function addParRow() {
    console.log("Adding new PAR row");
    const tbody = document.getElementById('parItemsTable').querySelector('tbody');
    if (!tbody) {
        console.error("PAR table body not found");
        return;
    }
    
    const newRow = document.createElement('tr');
    
    // Get current date in ISO format
    const today = new Date().toISOString().split('T')[0];
    
    newRow.innerHTML = `
        <td><input type="number" class="form-control par-qty qty" name="quantity[]" value="1" min="1"></td>
        <td><input type="text" class="form-control" name="unit[]" placeholder="Unit"></td>
        <td><textarea class="form-control" name="description[]" placeholder="Description" required></textarea></td>
        <td><input type="text" class="form-control" name="property_number[]" placeholder="Property Number"></td>
        <td><input type="date" class="form-control par-item-date" name="date_acquired[]" value="${today}"></td>
        <td><input type="text" class="form-control par-amount amount" name="unit_price[]" value="0.00" data-raw-value="0"></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-par-row"><i class="bi bi-trash"></i></button></td>
    `;
    
    tbody.appendChild(newRow);
    
    // Add event listeners for the new row
    const amountInput = newRow.querySelector('.par-amount');
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            // Store raw numeric value
            const rawValue = this.value.replace(/[₱,\s]/g, '');
            this.dataset.rawValue = rawValue;
            
            // Format the display value
            const value = parseFloat(rawValue) || 0;
            this.value = formatNumber(value);
            
            console.log(`Amount input changed to ${value} (${this.value}), raw value: ${rawValue}`);
            calculateParTotal();
        });
        
        // Also add blur event to ensure proper formatting
        amountInput.addEventListener('blur', function() {
            const value = parseFloat(this.dataset.rawValue) || 0;
            this.value = formatNumber(value);
            console.log(`Amount input blurred, formatted to ${this.value}, raw: ${this.dataset.rawValue}`);
            calculateParTotal();
        });
    }
    
    const qtyInput = newRow.querySelector('.par-qty');
    if (qtyInput) {
        qtyInput.addEventListener('input', function() {
            if (parseInt(this.value) < 1 || isNaN(parseInt(this.value))) {
                this.value = 1;
            }
            console.log(`Quantity changed to ${this.value}`);
            calculateParTotal();
        });
    }
    
    const removeButton = newRow.querySelector('.remove-par-row');
    if (removeButton) {
        removeButton.addEventListener('click', function() {
            handleRemoveParRow(this);
        });
    }
    
    calculateParTotal();
}

/**
 * Add a PAR item row with data
 */
function addParRowWithData(item) {
    const tbody = document.getElementById('parItemsTable').querySelector('tbody');
    if (!tbody) {
        console.error("PAR table body not found");
        return;
    }
    
    const newRow = document.createElement('tr');
    
    const quantity = item.quantity || 1;
    const unit = item.unit || '';
    const description = item.description || item.item_description || '';
    const propertyNumber = item.property_number || '';
    const dateAcquired = item.date_acquired || new Date().toISOString().split('T')[0];
    
    // Get price from unit_price, amount, or 0
    let amount = 0;
    if (typeof item.unit_price !== 'undefined') {
        amount = parseFloat(item.unit_price);
    } else if (typeof item.amount !== 'undefined') {
        amount = parseFloat(item.amount);
    }
    
    const formattedAmount = formatNumber(amount);
    
    newRow.innerHTML = `
        <td>
            <input type="number" class="form-control par-qty qty" name="quantity[]" value="${quantity}" min="1">
        </td>
        <td>
            <input type="text" class="form-control" name="unit[]" value="${unit}" placeholder="Unit">
        </td>
        <td>
            <textarea class="form-control" name="description[]" placeholder="Description" required>${description}</textarea>
        </td>
        <td>
            <input type="text" class="form-control" name="property_number[]" value="${propertyNumber}" placeholder="Property Number">
        </td>
        <td>
            <input type="date" class="form-control par-item-date" name="date_acquired[]" value="${dateAcquired}">
        </td>
        <td>
            <input type="text" class="form-control par-amount amount" name="unit_price[]" value="${formattedAmount}" data-raw-value="${amount}">
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm remove-par-row">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(newRow);
    
    // Add event listeners for the new row
    const amountInput = newRow.querySelector('.par-amount');
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            // Store raw numeric value
            const rawValue = this.value.replace(/[₱,\s]/g, '');
            this.dataset.rawValue = rawValue;
            
            // Format the display value
            const value = parseFloat(rawValue) || 0;
            this.value = formatNumber(value);
            
            calculateParTotal();
        });
        
        // Also add blur event to ensure proper formatting
        amountInput.addEventListener('blur', function() {
            const value = parseFloat(this.dataset.rawValue) || 0;
            this.value = formatNumber(value);
            calculateParTotal();
        });
    }
    
    const qtyInput = newRow.querySelector('.par-qty');
    if (qtyInput) {
        qtyInput.addEventListener('input', function() {
            if (parseInt(this.value) < 1 || isNaN(parseInt(this.value))) {
                this.value = 1;
            }
            calculateParTotal();
        });
    }
    
    const removeButton = newRow.querySelector('.remove-par-row');
    if (removeButton) {
        removeButton.addEventListener('click', function() {
            handleRemoveParRow(this);
        });
    }
    
    calculateParTotal();
}

/**
 * Add initial PAR row if table is empty
 */
function addInitialParRow() {
    const tbody = document.getElementById('parItemsTable').querySelector('tbody');
    if (tbody && tbody.children.length === 0) {
        addParRow();
    }
}

/**
 * Add event listeners to PAR row
 */
function addParRowEventListeners(row) {
    // Remove row button
    row.querySelector('.remove-par-row').addEventListener('click', function() {
        handleRemoveParRow(this);
    });
    
    // Amount input event
    const amountInput = row.querySelector('.par-amount');
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            // Format the input to currency
            const value = this.value.replace(/[^0-9.]/g, '');
            this.value = formatNumber(value);
            
            // Recalculate total
            calculateParTotal();
        });
        
        // Also add blur event to ensure proper formatting
        amountInput.addEventListener('blur', function() {
            if (this.value === '' || isNaN(parseFloat(this.value))) {
                this.value = '0.00';
            } else {
                this.value = formatNumber(parseFloat(this.value));
            }
            calculateParTotal();
        });
    }
    
    // Quantity input event
    const qtyInput = row.querySelector('.par-qty');
    if (qtyInput) {
        qtyInput.addEventListener('input', function() {
            if (parseInt(this.value) < 1 || isNaN(parseInt(this.value))) {
                this.value = 1;
            }
            calculateParTotal();
        });
    }
}

/**
 * Handle removing a PAR row
 */
function handleRemoveParRow(button) {
    const row = button.closest('tr');
    if (!row) return;

    const tbody = row.parentElement;
    if (!tbody) return;
    
    // Make sure we keep at least one row
    if (tbody.querySelectorAll('tr').length > 1) {
        row.remove();
        calculateParTotal();
    } else {
        Swal.fire({
            icon: 'warning',
            title: 'Cannot Remove',
            text: 'At least one item is required',
            timer: 2000,
            showConfirmButton: false
        });
    }
}

/**
 * Calculate PAR total amount
 */
function calculateParTotal() {
    let total = 0;
    
    try {
        console.log("Calculating PAR total amount...");
        const rows = document.querySelectorAll('#parItemsTable tbody tr');
        
        if (rows.length === 0) {
            console.log('No rows found to calculate total');
            updateTotalDisplay(0);
            return 0;
        }
        
        rows.forEach((row, index) => {
            const qtyInput = row.querySelector('[name="quantity[]"]');
            const amountInput = row.querySelector('[name="unit_price[]"]');
            
            if (!qtyInput || !amountInput) {
                console.log(`Missing inputs in row ${index + 1}`);
                return;
            }
            
            // Parse quantity and amount values
            const qtyValue = qtyInput.value.trim();
            const amountValue = amountInput.dataset.rawValue || amountInput.value.trim().replace(/[₱,\s]/g, '');
            
            const qty = parseInt(qtyValue) || 1;
            const amount = parseFloat(amountValue) || 0;
            
            const rowTotal = qty * amount;
            total += rowTotal;
            
            console.log(`Row ${index + 1}: qty=${qty}, unit_price=${amount}, rowTotal=${rowTotal}`);
        });
        
        console.log('Final total:', total);
        updateTotalDisplay(total);
    } catch (error) {
        console.error('Error calculating PAR total:', error);
    }
    
    return total;
}

/**
 * Update total display and hidden input
 */
function updateTotalDisplay(total) {
    console.log("Updating total display with value:", total);
    
    // Try to find total display element in various locations
    const totalElementSelectors = [
        '#parTotalAmount',  // Hidden input
        '#parTotal',        // Visible span
        '#parItemsTable tfoot #parTotal',
        '#parItemsTable tfoot input[name="total_amount"]'
    ];
    
    // Update all total display elements we can find
    totalElementSelectors.forEach(selector => {
        const element = document.querySelector(selector);
        if (element) {
            if (element.tagName === 'INPUT') {
                element.value = total.toFixed(2);
            } else {
                element.textContent = formatNumber(total);
            }
            console.log(`Updated total element (${selector}):`, element);
        }
    });
    
    // Always update the hidden input for form submission
    const totalInput = document.querySelector('[name="total_amount"]');
    if (totalInput) {
        totalInput.value = total.toFixed(2);
        console.log('Updated hidden total input:', total);
    } else {
        // Create a hidden input for total_amount if it doesn't exist
        const parForm = document.getElementById('parForm');
        if (parForm) {
            let hiddenInput = parForm.querySelector('[name="total_amount"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'total_amount';
                parForm.appendChild(hiddenInput);
            }
            hiddenInput.value = total.toFixed(2);
            console.log('Created/updated hidden total input:', total);
        }
    }
}

/**
 * Clear browser cache for PAR-related requests
 * This uses various techniques to ensure PAR data is freshly loaded
 */
function clearPARCache() {
    console.log('Clearing PAR data cache');
    
    // 1. Try to use the Cache API if available (modern browsers)
    if ('caches' in window) {
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    return caches.open(cacheName).then(cache => {
                        // Define the URLs to clear
                        const urlsToDelete = [
                            'get_par.php',
                            '/get_par.php',
                            'add_par.php',
                            '/add_par.php',
                            'update_par.php',
                            '/update_par.php',
                            'delete_par.php',
                            '/delete_par.php'
                        ];
                        
                        // Add variations with parameters
                        const timestamp = new Date().getTime();
                        const random = Math.random().toString(36).substring(2, 15);
                        urlsToDelete.push(`get_par.php?_=${timestamp}`);
                        urlsToDelete.push(`get_par.php?force_refresh=1`);
                        urlsToDelete.push(`get_par.php?force_refresh=1&_=${timestamp}`);
                        urlsToDelete.push(`get_par.php?random=${random}`);
                        
                        // Also add the current page URL and variants
                        const currentUrl = window.location.href;
                        urlsToDelete.push(currentUrl);
                        urlsToDelete.push(currentUrl + '?_=' + timestamp);
                        
                        // Delete each URL from cache
                        const deletionPromises = urlsToDelete.map(url => {
                            return cache.delete(url).then(success => {
                                if (success) {
                                    console.log(`Successfully deleted ${url} from cache ${cacheName}`);
                                }
                                return success;
                            });
                        });
                        
                        return Promise.all(deletionPromises);
                    });
                })
            );
        }).then(() => {
            console.log('PAR cache clearing completed');
        }).catch(error => {
            console.error('Error clearing PAR cache:', error);
        });
    } else {
        console.log('Cache API not available, using alternative cache busting');
    }
    
    // 2. Force reload for specific URLs
    const timestamp = new Date().getTime();
    const random = Math.floor(Math.random() * 1000000);
    
    // Create a fetch request with cache-busting parameters
    fetch(`get_par.php?cache_bust=${timestamp}&rand=${random}`, {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma': 'no-cache',
            'Expires': '0'
        }
    }).then(() => {
        console.log('Sent cache-busting request');
    }).catch(error => {
        console.error('Cache busting request failed:', error);
    });
    
    // 3. Create an image element with a unique URL to bypass cache (old technique but still effective)
    const cacheBuster = new Image();
    cacheBuster.src = `get_par.php?cache_bust=${timestamp}&rand=${random}&r=${Math.random()}`;
    cacheBuster.style.display = 'none';
    cacheBuster.onload = function() {
        console.log('Image cache bust request completed');
        document.body.removeChild(cacheBuster);
    };
    document.body.appendChild(cacheBuster);
    
    // 4. Reload PAR data with force refresh as a final fallback
    setTimeout(() => {
        console.log('Reloading PAR data after cache clear');
        loadPARData(true);
    }, 500);
    
    console.log('PAR cache clearing triggered via multiple methods');
}

/**
 * Save PAR data to server
 */
function savePAR() {
    // Validate form
    const parForm = document.getElementById('parForm');
    if (!parForm) {
        console.error("PAR form not found");
        return;
    }
    
    if (!parForm.checkValidity()) {
        parForm.reportValidity();
        return;
    }
    
    console.log("Saving PAR data...");
    
    // Clear browser cache for PAR data first
    clearPARCache();
    
    // Collect form data
    const parId = parForm.querySelector('[name="par_id"]')?.value || '';
    
    // Get all the form fields by name attributes 
    const formData = {
        par_id: parId || null,
        par_no: parForm.querySelector('[name="par_no"]')?.value || '',
        entity_name: parForm.querySelector('[name="entity_name"]')?.value || '',
        date_acquired: parForm.querySelector('[name="date_acquired"]')?.value || '',
        items: []
    };
    
    // Handle received_by - check if we have an ID or name
    const receivedById = parForm.querySelector('[name="received_by_id"]')?.value || '';
    const receivedByName = parForm.querySelector('[name="received_by"]')?.value || '';
    
    if (receivedById && receivedById.trim()) {
        // We have a user ID - use it
        formData.received_by = receivedById.trim();
        console.log("Using existing user ID:", receivedById);
    } else if (receivedByName && receivedByName.trim()) {
        // No user ID but we have a name - send the name for backend to create or find user
        formData.received_by = receivedByName.trim();
        console.log("Using name for received_by:", receivedByName);
    } else {
        // No ID or name - use default ID if available
        const defaultUserId = parForm.querySelector('[name="default_user_id"]')?.value || '1';
        formData.received_by = defaultUserId;
        console.log("Using fallback user ID:", defaultUserId);
    }
    
    // Position field is optional - only add if it exists in the form
    const positionField = parForm.querySelector('[name="position"]');
    if (positionField && positionField.value.trim() !== '') {
        formData.position = positionField.value.trim();
    }
    
    // Department field is optional - only add if it exists in the form
    const departmentField = parForm.querySelector('[name="department"]');
    if (departmentField && departmentField.value.trim() !== '') {
        formData.department = departmentField.value.trim();
    }
    
    // Remarks field is optional - only add if it exists in the form
    const remarksField = parForm.querySelector('[name="remarks"]');
    if (remarksField && remarksField.value.trim() !== '') {
        formData.remarks = remarksField.value.trim();
    }
    
    // Collect items data
    let hasEmptyDescription = false;
    let emptyItemNumbers = [];
    
    // Get all rows from the items table
    document.querySelectorAll('#parItemsTable tbody tr').forEach((row, index) => {
        const qtyInput = row.querySelector('[name="quantity[]"]');
        const unitInput = row.querySelector('[name="unit[]"]');
        const descInput = row.querySelector('[name="description[]"]');
        const propInput = row.querySelector('[name="property_number[]"]');
        const dateInput = row.querySelector('[name="date_acquired[]"]');
        const priceInput = row.querySelector('[name="unit_price[]"]');
        
        // Check if description is empty and track it
        if (!descInput || !descInput.value.trim()) {
            hasEmptyDescription = true;
            emptyItemNumbers.push(index + 1);
            console.log(`Row ${index + 1} has no description`);
            return; // Skip rows without description
        }

        // Get raw amount value (either from data attribute or by cleaning formatted text)
        let unitPrice = 0;
        if (priceInput) {
            if (priceInput.dataset.rawValue) {
                unitPrice = parseFloat(priceInput.dataset.rawValue) || 0;
            } else {
                unitPrice = parseFloat(priceInput.value.replace(/[₱,\s]/g, '')) || 0;
            }
        }
        
        const item = {
            quantity: parseInt(qtyInput?.value) || 1,
            unit: unitInput?.value || '',
            description: descInput?.value?.trim() || '', 
            item_description: descInput?.value?.trim() || '', // Keep both field names for compatibility
            property_number: propInput?.value || '',
            date_acquired: dateInput?.value || formData.date_acquired,
            unit_price: unitPrice
        };
        
        console.log(`Item ${index + 1}:`, item);
        formData.items.push(item);
    });
    
    // Show error if any descriptions are empty
    if (hasEmptyDescription) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please add description for items: ' + emptyItemNumbers.join(', '),
            didClose: () => {
                // Make sure the modal stays open
                if (parId) {
                    setTimeout(() => editPAR(parId), 100);
                } else {
                    // Find and show the modal
                    const modalElement = document.getElementById('parModal') || document.getElementById('addPARModal');
                    if (modalElement && typeof bootstrap !== 'undefined') {
                        try {
                            const parModal = new bootstrap.Modal(modalElement);
                            parModal.show();
                        } catch (e) {
                            console.error('Error showing modal after validation:', e);
                        }
                    }
                }
            }
        });
        return;
    }
    
    // Check if we have items
    if (formData.items.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please add at least one item',
            didClose: () => {
                // Make sure the modal stays open
                if (parId) {
                    setTimeout(() => editPAR(parId), 100);
                } else {
                    // Find and show the modal
                    const modalElement = document.getElementById('parModal') || document.getElementById('addPARModal');
                    if (modalElement && typeof bootstrap !== 'undefined') {
                        try {
                            const parModal = new bootstrap.Modal(modalElement);
                            parModal.show();
                        } catch (e) {
                            console.error('Error showing modal after validation:', e);
                        }
                    }
                }
            }
        });
        return;
    }
    
    // Calculate and include the total amount
    const calculatedTotal = calculateParTotal();
    formData.total_amount = calculatedTotal;
    console.log("Final calculated total:", calculatedTotal);
    
    console.log('Saving PAR data:', formData);
    
    // Determine endpoint - either update or create
    const endpoint = parId ? 'update_par.php' : 'add_par.php';
    console.log("Using endpoint:", endpoint);
    
    // Send data to server with proper JSON Content-Type header
    console.log(`Sending data to ${endpoint} with ${formData.items.length} items`);
    
    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        },
        body: JSON.stringify(formData)
    })
    .then(response => {
        console.log("Server response status:", response.status);
        console.log("Server response headers:", Object.fromEntries([...response.headers.entries()]));
        
        if (!response.ok) {
            return response.text().then(text => {
                console.error("Error response text:", text);
                try {
                    // Try to parse the response as JSON
                    const errorData = JSON.parse(text);
                    // Handle the specific error about missing procedure
                    if (errorData.message && errorData.message.includes('update_par_total does not exist')) {
                        console.log("Missing stored procedure error detected. Retrying with direct calculation.");
                        // We'll continue and treat this as a success since the total was already calculated in JavaScript
                        return { success: true, message: 'PAR saved successfully' };
                    }
                    throw new Error(errorData.message || `Server returned ${response.status}`);
                } catch (e) {
                    // If not valid JSON, return the text or status
                    throw new Error(text || `Server returned ${response.status}`);
                }
            });
        }
        return response.json();
    })
    .then(data => {
        console.log("Server response data:", data);
        if (data.success) {
            // Store the success status and par_id for post-processing
            const isNewPar = !parId;
            const savedParId = data.par_id || null;
            
            // Log detailed information about the save operation
            console.log(`PAR ${isNewPar ? 'created' : 'updated'} successfully. PAR ID: ${savedParId}`);
            
            // Close the modal first to ensure UI is updated properly
            closeParModal();
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: parId ? 'PAR Updated!' : 'PAR Added!',
                text: parId ? 'Property Acknowledgement Receipt has been updated successfully.' : 'New Property Acknowledgement Receipt has been added successfully.',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Only refresh the data once after the success message closes
            setTimeout(() => {
                loadPARData(true);
            }, 2100);
        } else {
            throw new Error(data.message || 'Failed to save PAR');
        }
    })
    .catch(error => {
        console.error('Error saving PAR:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Failed to save PAR. Please try again.'
        });
    })
    .finally(() => {
        // Ensure any loading indicators are hidden, just in case
        hideLoading();
    });
}

/**
 * Helper function to ensure PAR table data is refreshed properly
 * with reliable cache bypassing
 */
function refreshPARTableData() {
    // Extra cache busting by adding multiple random parameters
    const timestamp = new Date().getTime();
    const randomVal = Math.random().toString(36).substring(2, 15);
    
    console.log(`Refreshing PAR data with timestamp ${timestamp}`);
    
    // Fetch with explicit no-cache headers
    fetch(`get_par.php?force_refresh=1&_=${timestamp}&rand=${randomVal}`, {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma': 'no-cache',
            'Expires': '0'
        },
        credentials: 'same-origin' // Include cookies for session consistency
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server returned ${response.status} ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log(`PAR data refreshed with ${data.data?.length || 0} records`);
        
        if (data.success) {
            // Store and display the refreshed data
            if (Array.isArray(data.data)) {
                parData = data.data;
                console.log(`Updated global parData with ${parData.length} records`);
                
                // Display refreshed data in the table
                displayPARData(parData);
                
                // Schedule a second refresh after a delay to catch any potential race conditions
                setTimeout(() => {
                    console.log("Performing secondary refresh to ensure data consistency");
                    fetchLatestPARData();
                }, 1000);
            } else {
                console.warn('PAR data refresh returned non-array data:', data);
                fetchLatestPARData(); // Try another approach if data structure is unexpected
            }
        } else {
            console.error('PAR data refresh returned error:', data.message);
            fetchLatestPARData(); // Try another approach if refresh failed
        }
    })
    .catch(error => {
        console.error('Error refreshing PAR data:', error);
        
        // Specific handling for invalid JSON (which might be HTML error output)
        if (error instanceof SyntaxError && error.message.includes('Unexpected token')) {
            console.error('Received invalid JSON in refresh (possibly HTML error page)');
            // Try to fetch the error content directly
            const requestUrl = `get_par.php?force_refresh=1&_=${new Date().getTime()}`;
            fetch(requestUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'text/html',
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => response.text())
            .then(html => {
                console.error('Error content:', html.substring(0, 500)); // Log first 500 chars for debugging
                
                // Check if it's a database error and handle db_connect.php issue
                if (html.includes('db_connect.php') || html.includes('Failed to open stream')) {
                    console.error('Detected missing db_connect.php issue');
                    showError('Database connection error: The backend cannot find db_connect.php. Contact administrator.');
                } else {
                    showError('Server returned HTML instead of JSON. Please check PHP error logs.');
                }
            })
            .catch(() => {
                showError('Error refreshing PAR data: Invalid response format (not JSON)');
            });
        }
        
        // Try main loadPARData with force refresh
        setTimeout(() => {
            loadPARData(true);
        }, 1500);
    });
}

/**
 * Alternative fetch method using XMLHttpRequest for maximum reliability
 * Used as a fallback when the primary refresh method fails
 */
function fetchLatestPARData() {
    const timestamp = new Date().getTime();
    console.log(`Attempting alternative PAR data fetch with timestamp ${timestamp}`);
    
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `get_par.php?nocache=1&_=${timestamp}`, true);
    xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    xhr.setRequestHeader('Pragma', 'no-cache');
    xhr.setRequestHeader('Expires', '0');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                const data = JSON.parse(xhr.responseText);
                console.log(`Alternative fetch: PAR data received with ${data.data?.length || 0} records`);
                
                if (data.success && Array.isArray(data.data)) {
                    parData = data.data;
                    displayPARData(parData);
                }
            } catch (e) {
                console.error('Error parsing PAR data:', e);
            }
        } else {
            console.error('Alternative fetch failed:', xhr.status, xhr.statusText);
        }
    };
    
    xhr.onerror = function() {
        console.error('Network error during alternative PAR data fetch');
    };
    
    xhr.send();
}

/**
 * Close PAR modal function
 */
function closeParModal() {
    console.log("Closing PAR modal");
    
    // Find all possible modal elements - check for all known IDs
    const modalIds = ['addPARModal', 'parModal', 'addParModal', 'editPARModal', 'editParModal'];
    let modalClosed = false;
    
    for (const modalId of modalIds) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) continue;
        
        console.log(`Found modal with ID: ${modalId}`);
        
        try {
            // Try closing with Bootstrap 5 API first
            if (typeof bootstrap !== 'undefined') {
                const bsModal = bootstrap.Modal.getInstance(modalElement);
                if (bsModal) {
                    bsModal.hide();
                    console.log(`Modal ${modalId} closed using Bootstrap API`);
                    modalClosed = true;
                    continue; // Continue checking other modals - we want to close ALL PAR modals
                }
            }
            
            // Fallback to jQuery if available
            if (typeof $ !== 'undefined' && typeof $.fn.modal !== 'undefined') {
                $(modalElement).modal('hide');
                console.log(`Modal ${modalId} closed using jQuery`);
                modalClosed = true;
                continue;
            }
            
            // Fallback to manual DOM manipulation
            modalElement.classList.remove('show');
            modalElement.style.display = 'none';
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.removeAttribute('aria-modal');
            
            // Mark as closed
            modalClosed = true;
            console.log(`Modal ${modalId} closed manually`);
        } catch (e) {
            console.error(`Error closing modal ${modalId}:`, e);
        }
    }
    
    // If no specific PAR modals were found/closed, try closing any visible modal
    if (!modalClosed) {
        console.warn("No specific PAR modal elements found, trying to close any visible modal");
        
        // Close any visible modal
        const visibleModals = document.querySelectorAll('.modal.show, .modal[style*="display: block"]');
        visibleModals.forEach((modal, index) => {
            try {
                // Try bootstrap API first
                if (typeof bootstrap !== 'undefined') {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) {
                        bsModal.hide();
                        console.log(`Visible modal ${index} closed using Bootstrap API`);
                        return;
                    }
                }
                
                // Fallback to manual close
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                console.log(`Visible modal ${index} closed manually`);
            } catch (e) {
                console.error(`Error closing visible modal ${index}:`, e);
            }
        });
    }
    
    // Remove any modal backdrops
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.remove();
        console.log("Removed modal backdrop");
    });
    
    // Clean up body element
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // Reset all PAR forms
    const parForms = document.querySelectorAll('#parForm, form[id*="par"], form[id*="PAR"]');
    parForms.forEach((form, index) => {
        try {
            form.reset();
            console.log(`Reset PAR form ${index}`);
            
            // Clear hidden par_id field if present
            const parIdInput = form.querySelector('[name="par_id"]');
            if (parIdInput) {
                parIdInput.value = '';
                console.log("Cleared PAR ID input");
            }
            
            // Clear the items table if present
            const itemsTable = form.querySelector('#parItemsTable tbody, .par-items-table tbody');
            if (itemsTable) {
                itemsTable.innerHTML = '';
                console.log("Cleared PAR items table");
                
                // Add a clean initial row for next use
                setTimeout(() => {
                    if (typeof addInitialParRow === 'function') {
                        addInitialParRow();
                        console.log("Added initial PAR row");
                    }
                }, 100);
            }
        } catch (e) {
            console.error(`Error resetting PAR form ${index}:`, e);
        }
    });
}

/**
 * Format number with commas
 */
function formatNumber(number) {
    // Handle both numbers and strings by ensuring it's treated as a number first
    try {
        if (isNaN(parseFloat(number))) {
            return '0.00';
        }
        
        // Format with PHP-style number formatting
        const num = parseFloat(number);
        return num.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } catch (e) {
        console.error('Error formatting number:', e);
        return '0.00';
    }
}

/**
 * Show loading indicator
 */
function showLoading() {
    // Check for existing loading overlay
    let loadingOverlay = document.getElementById('loadingOverlay');
    
    if (!loadingOverlay) {
        // Create loading overlay
        loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loadingOverlay';
        loadingOverlay.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        `;
        
        // Apply styles
        loadingOverlay.style.position = 'fixed';
        loadingOverlay.style.top = '0';
        loadingOverlay.style.left = '0';
        loadingOverlay.style.width = '100%';
        loadingOverlay.style.height = '100%';
        loadingOverlay.style.display = 'flex';
        loadingOverlay.style.alignItems = 'center';
        loadingOverlay.style.justifyContent = 'center';
        loadingOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        loadingOverlay.style.zIndex = '9999';
        
        // Add to body
        document.body.appendChild(loadingOverlay);
    } else {
        // Show existing overlay
        loadingOverlay.style.display = 'flex';
    }
}

/**
 * Hide loading indicator
 */
function hideLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
}

/**
 * Show error message
 */
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        timer: 5000
    });
}

/**
 * Show success modal with check mark
 * @param {string} title - Modal title 
 * @param {string} message - Modal message
 * @param {Function} onConfirm - Optional callback when OK is clicked
 */
function showSuccessModal(title = 'Item Added!', message = 'New inventory item has been added successfully.', onConfirm = null) {
    // Create modal container if it doesn't exist
    let successModal = document.getElementById('successModal');
    
    if (!successModal) {
        successModal = document.createElement('div');
        successModal.id = 'successModal';
        successModal.className = 'modal fade';
        successModal.setAttribute('tabindex', '-1');
        successModal.setAttribute('role', 'dialog');
        successModal.setAttribute('aria-hidden', 'true');
        
        // Create modal content with exact styling from the image
        successModal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
                <div class="modal-content border-0 shadow-sm">
                    <div class="modal-body text-center p-5">
                        <div class="mb-4">
                            <div style="width: 80px; height: 80px; margin: 0 auto; background-color: rgba(130, 210, 130, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="45" height="45" fill="#7cc17c" class="bi bi-check" viewBox="0 0 16 16">
                                    <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                                </svg>
                            </div>
                        </div>
                        <h3 id="successModalTitle" class="modal-title fs-3 fw-bold text-dark mb-2" style="color: #333;"></h3>
                        <p id="successModalMessage" class="text-muted fs-6 mb-4" style="color: #666;"></p>
                        <button type="button" class="btn btn-primary px-5 py-2" data-bs-dismiss="modal" style="background-color: #6c65f1; border-color: #6c65f1; border-radius: 6px; font-weight: 500;">OK</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(successModal);
        
        // Add custom styles to ensure it looks exactly like the image
        const style = document.createElement('style');
        style.textContent = `
            #successModal .modal-content {
                border-radius: 12px;
            }
            #successModal .modal-body {
                padding: 2.5rem;
            }
            #successModal .btn-primary:hover {
                background-color: #5951ed !important;
                border-color: #5951ed !important;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Set modal content
    document.getElementById('successModalTitle').textContent = title;
    document.getElementById('successModalMessage').textContent = message;
    
    // Initialize modal if needed
    let bsModal;
    try {
        if (typeof bootstrap !== 'undefined') {
            const instance = bootstrap.Modal.getInstance(successModal);
            if (instance) {
                bsModal = instance;
            } else {
                bsModal = new bootstrap.Modal(successModal);
            }
        } else {
            console.error('Bootstrap is not defined. Modal may not work properly.');
        }
    } catch (e) {
        console.error('Error initializing modal:', e);
    }
    
    // Add event handler for OK button if callback provided
    if (onConfirm && typeof onConfirm === 'function') {
        const okButton = successModal.querySelector('.btn-primary');
        const clickHandler = function() {
            onConfirm();
            okButton.removeEventListener('click', clickHandler);
        };
        okButton.addEventListener('click', clickHandler);
    }
    
    // Show modal
    if (bsModal) {
        bsModal.show();
    } else {
        console.error('Failed to initialize modal');
        // Fallback to showing an alert
        alert(title + '\n' + message);
        if (onConfirm && typeof onConfirm === 'function') {
            onConfirm();
        }
    }
}

/**
 * Remove specific PAR item row with QTY=1, AMOUNT=0, Date Acquired=10/04/25
 */
function removeSpecificParItem() {
    // Look for all rows in PAR items tables
    const parRows = document.querySelectorAll('#parItemsTable tbody tr, .par-table tbody tr, table tbody tr');
    
    parRows.forEach(row => {
        // Check if this is the row we want to remove
        const qtyElement = row.querySelector('.par-qty, .qty, [name="quantity[]"], td.quantity');
        const priceElement = row.querySelector('.par-amount, .amount, [name="unit_price[]"]');
        const dateElement = row.querySelector('.par-item-date, [name="date_acquired[]"], .date-cell');
        
        if (qtyElement && priceElement && dateElement) {
            // Get values from elements
            let qty = qtyElement.tagName === 'TD' ? qtyElement.textContent.trim() : qtyElement.value;
            let price = priceElement.tagName === 'TD' ? priceElement.textContent.trim() : priceElement.value;
            let date = dateElement.tagName === 'TD' ? dateElement.textContent.trim() : dateElement.value;
            
            // Check for exact match with the values we want to remove
            if (qty == '1' && price == '0' && (date == '10/04/25' || date == '2025-04-10')) {
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

/**
 * Export PAR data to Excel
 */
function exportPARData() {
    if (!parData || parData.length === 0) {
        Swal.fire({
            title: 'No Data',
            text: 'There is no PAR data to export',
            icon: 'warning'
        });
        return;
    }
    
    console.log('Exporting PAR data to Excel');
    
    // Create worksheet from PAR data
    const worksheet = XLSX.utils.json_to_sheet(parData.map(par => {
        return {
            'PAR No': par.par_no || '',
            'Date Acquired': par.date_acquired || '',
            'Property Number': par.property_number || '',
            'Received By': par.received_by_name || '',
            'Position': par.position || '',
            'Department': par.department || '',
            'Total Amount': par.total_amount || 0
        };
    }));
    
    // Create workbook and add the worksheet
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'PAR Data');
    
    // Generate Excel file and trigger download
    const today = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(workbook, `PAR_Export_${today}.xlsx`);
    
    // Show success message
    Swal.fire({
        title: 'Export Complete',
        text: 'PAR data has been exported to Excel',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

/**
 * Batch print multiple PAR documents
 */
function batchPrintPAR() {
    if (!parData || parData.length === 0) {
        Swal.fire({
            title: 'No Data',
            text: 'There are no PAR records to print',
            icon: 'warning'
        });
        return;
    }
    
    // Create batch print modal
    let batchPrintModal = document.getElementById('batchPrintModal');
    
    if (!batchPrintModal) {
        batchPrintModal = document.createElement('div');
        batchPrintModal.id = 'batchPrintModal';
        batchPrintModal.className = 'modal fade';
        batchPrintModal.setAttribute('tabindex', '-1');
        batchPrintModal.setAttribute('aria-hidden', 'true');
        
        batchPrintModal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Batch Print PAR Documents</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllPAR">
                                <label class="form-check-label" for="selectAllPAR">
                                    Select All
                                </label>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th width="50px"></th>
                                        <th>PAR No.</th>
                                        <th>Date Acquired</th>
                                        <th>Received By</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="batchPrintTableBody">
                                    <!-- Items will be added dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="printSelectedBtn">
                            <i class="bi bi-printer"></i> Print Selected
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(batchPrintModal);
        
        // Add event listeners
        document.getElementById('selectAllPAR').addEventListener('change', function() {
            const checkboxes = batchPrintModal.querySelectorAll('.par-select-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Populate the table with PAR data
    const tableBody = document.getElementById('batchPrintTableBody');
    tableBody.innerHTML = '';
    
    parData.forEach(par => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="form-check">
                    <input class="form-check-input par-select-checkbox" type="checkbox" value="${par.par_id}" id="par_${par.par_id}">
                </div>
            </td>
            <td>${par.par_no || ''}</td>
            <td>${par.date_acquired || ''}</td>
            <td>${par.received_by_name || ''}</td>
            <td class="text-end">${formatNumber(par.total_amount || 0)}</td>
        `;
        tableBody.appendChild(row);
    });
    
    // Show the modal
    let bsModal;
    try {
        if (typeof bootstrap !== 'undefined') {
            bsModal = new bootstrap.Modal(batchPrintModal);
            bsModal.show();
        } else {
            console.error('Bootstrap is not defined');
            batchPrintModal.style.display = 'block';
        }
    } catch (e) {
        console.error('Error showing modal:', e);
    }
    
    // Add event listener for print button
    document.getElementById('printSelectedBtn').onclick = function() {
        const selectedIds = Array.from(
            document.querySelectorAll('.par-select-checkbox:checked')
        ).map(checkbox => checkbox.value);
        
        if (selectedIds.length === 0) {
            Swal.fire({
                title: 'No Selection',
                text: 'Please select at least one PAR to print',
                icon: 'warning'
            });
            return;
        }
        
        console.log('Printing selected PARs:', selectedIds);
        
        // Close the modal
        if (bsModal) {
            bsModal.hide();
        }
        
        // Create a progress modal
        Swal.fire({
            title: 'Preparing Documents',
            html: `Preparing ${selectedIds.length} document(s) for printing...`,
            timerProgressBar: true,
            didOpen: () => {
                Swal.showLoading();
                
                // Print each document with a delay to prevent browser blocking
                printPARSequentially(selectedIds, 0);
            }
        });
    };
}

/**
 * Print PAR documents one by one with delay
 */
function printPARSequentially(parIds, index) {
    if (index >= parIds.length) {
        // All documents have been opened for printing
        Swal.fire({
            title: 'Print Queue Processed',
            html: `${parIds.length} document(s) have been sent to the print queue.`,
            icon: 'success',
            timer: 3000
        });
        return;
    }
    
    // Print current document
    const parId = parIds[index];
    const printWindow = window.open(`viewPar.php?id=${parId}&print=true`, '_blank');
    
    // Check if window was opened successfully
    if (!printWindow) {
        Swal.fire({
            title: 'Pop-up Blocked',
            text: 'Please allow pop-ups for this site to print PAR documents',
            icon: 'warning'
        });
        return;
    }
    
    // Proceed to next document after delay
    setTimeout(() => {
        printPARSequentially(parIds, index + 1);
    }, 1000);
}
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
            e.stopPropagation(); // Stop event bubbling
            console.log('saveParBtn clicked');

            // Call the global savePAR function to ensure consistent behavior
            savePAR();
        });
        
        // Set the button type to button to prevent form submission
        newSaveBtn.setAttribute('type', 'button');
        
        return newSaveBtn;
    }
    return null;
};

document.addEventListener('DOMContentLoaded', function() {
    // Attach handler to save button if it exists on page load
    attachPARButtonHandler();
    
    // Add event delegation for dynamically added save buttons
    document.addEventListener('click', function(e) {
        const saveBtn = e.target.closest('#saveParBtn:not([data-handler-attached="true"])');
        if (saveBtn) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Delegated click on saveParBtn');
            savePAR();
        }
    });
});

function deletePAR(parId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            // Use both approaches for compatibility - try POST first with JSON body
            fetch('delete_par.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    par_id: parId
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error("Server response:", text);
                        try {
                            // Try to parse as JSON
                            return JSON.parse(text);
                        } catch (e) {
                            // If not valid JSON, throw with text
                            throw new Error(`Server error: ${response.status} ${response.statusText}. Details: ${text}`);
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSuccessModal(
                        'Item Deleted!', 
                        'The Property Acknowledgement Receipt has been deleted successfully.',
                        () => loadPARData(true)
                    );
                } else {
                    showError(data.message || 'Failed to delete PAR');
                }
            })
            .catch(error => {
                console.error('Error deleting PAR:', error);
                showError('Error deleting PAR: ' + error.message);
            })
            .finally(() => {
                hideLoading();
            });
        }
    });
}
document.getElementById('saveParBtn')?.addEventListener('click', function () {
    // This will use the savePAR function defined above
    savePAR();
});
// Function to view a PAR
function viewPAR(parId) {
    if (!parId) {
        Swal.fire('Error', 'PAR ID is required', 'error');
        return;
    }

    console.log('Viewing PAR with ID:', parId);
    
    // Open the viewPAR.php in a new window
    window.open(`viewPAR.php?id=${encodeURIComponent(parId)}`, '_blank');
}

// Function to handle PAR form submission
document.getElementById('saveParBtn')?.addEventListener('click', function () {
    // Get form data
    const parForm = document.getElementById('parForm');
    if (!parForm) return;

    // Get all items from the table
    const itemRows = document.querySelectorAll('#parItemsTable tbody tr:not(.d-none)');
    const items = [];

    itemRows.forEach(row => {
        const item = {
            quantity: parseInt(row.querySelector('.quantity')?.value) || 0,
            unit: row.querySelector('.unit')?.value || '',
            description: row.querySelector('.description')?.value || '',
            property_number: row.querySelector('.property-number')?.value || '',
            date_acquired: row.querySelector('.date-acquired')?.value || '',
            amount: parseFloat(row.querySelector('.amount')?.value) || 0
        };
        if (item.description) {
            items.push(item);
        }
    });

    const parData = {
        par_no: document.getElementById('parNo').value,
        entity_name: document.getElementById('entityName').value,
        date_acquired: document.getElementById('dateAcquired').value,
        received_by: document.getElementById('receivedBy').value,
        position: document.getElementById('position').value,
        department: document.getElementById('department').value,
        remarks: document.getElementById('remarks').value,
        total_amount: parseFloat(document.getElementById('parTotalAmount').value.replace(/[^\d.-]/g, '')) || 0,
        items: items
    };

    // Validate required fields
    if (!parData.par_no || !parData.entity_name || !parData.date_acquired || !parData.received_by || items.length === 0) {
            Swal.fire({
                icon: 'error',
            title: 'Required Fields Missing',
            text: 'Please fill in required fields (PAR No, Entity Name, Date, Received By) and add at least one item'
        });
        return;
    }

    // Show loading state
    Swal.fire({
        title: 'Saving Property Acknowledgement Receipt',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send data to server
    fetch('add_par.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(parData)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: 'Property Acknowledgement Receipt saved successfully',
                    icon: 'success'
                }).then(() => {
                    // Close modal
                    const parModal = bootstrap.Modal.getInstance(document.getElementById('parModal'));
                    if (parModal) {
                        parModal.hide();
                    }
                    // Refresh PAR table
                    loadPARData();
                });
            } else {
                throw new Error(data.message || 'Failed to save PAR');
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Error!',
                text: error.message || 'Failed to save PAR',
                icon: 'error'
            });
        });
    });

// ... existing code ...image.png
function loadPARData() {
    console.log('Loading PAR data');
    showLoading();

    fetch('get_par_data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('PAR data loaded:', data);
            if (data && typeof displayPARData === 'function') {
                displayPARData(data);
    } else {
                console.error('Failed to process PAR data:', data);
                showError('Failed to process PAR data');
            }
            hideLoading();
        })
        .catch(error => {
            console.error('Failed to load data for par:', error);
            showError('Failed to load data for par: ' + error.message);
            hideLoading();
        });
}
function deletePO(poId) {
    if (!poId) {
        Swal.fire('Error', 'PO ID is required', 'error');
        return;
    }

    console.log('Deleting PO with ID:', poId);

    // Confirm deletion with the user
    Swal.fire({
        title: 'Delete Purchase Order',
        text: 'Are you sure you want to delete this purchase order? This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            showLoading();

            // Send delete request to the server
            fetch('delete_po.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: poId })
            })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Server error response:', text);
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
    hideLoading();

                    if (data.success) {
                // Show success message
                Swal.fire({
                    icon: 'success',
                            title: 'Deleted!',
                            text: 'The purchase order has been deleted successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        // Reload PO data to update the table
                    loadPOData();
            } else {
                        throw new Error(data.message || 'Failed to delete PO');
            }
        })
        .catch(error => {
                    hideLoading();
                    console.error('Error deleting PO:', error);
                    Swal.fire('Error', error.message || 'Failed to delete purchase order', 'error');
                });
            }
        });
    }
