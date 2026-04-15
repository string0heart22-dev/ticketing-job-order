// Unified Tickets Management JavaScript

// Note: currentMaintenanceId is declared in tickets_maintenance.js
// Note: currentPulloutId is declared in tickets_pullout.js

document.addEventListener('DOMContentLoaded', function() {
    // Initialize address dropdowns for all modals (installation, maintenance, pullout)
    if (typeof initializeAllAddressForms === 'function') {
        initializeAllAddressForms();
    } else {
        // If not loaded yet, try again after a short delay
        setTimeout(function() {
            if (typeof initializeAllAddressForms === 'function') {
                initializeAllAddressForms();
            }
        }, 500);
    }
    // Ticket Type Tab Switching
    const ticketTypeTabs = document.querySelectorAll('.ticket-type-tab');
    const ticketSections = document.querySelectorAll('.ticket-section');
    
    // Check URL parameter for initial ticket type
    const urlParams = new URLSearchParams(window.location.search);
    let initialType = urlParams.get('type');
    
    // If no type parameter or empty, default to 'all' and update URL
    if (!initialType || initialType === '') {
        initialType = 'all';
        // Update URL to include type=all without reload
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('type', 'all');
        window.history.replaceState({}, '', newUrl);
    }
    
    // Set initial active tab and section
    setActiveTicketType(initialType);
    
    ticketTypeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const ticketType = this.getAttribute('data-ticket-type');
            setActiveTicketType(ticketType);
            
            // Update URL without reload
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('type', ticketType);
            window.history.pushState({}, '', newUrl);
        });
    });
    
    function setActiveTicketType(type) {
        // Update tabs
        ticketTypeTabs.forEach(tab => {
            if (tab.getAttribute('data-ticket-type') === type) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        // Update sections
        ticketSections.forEach(section => {
            if (section.id === type + '-section') {
                section.classList.add('active');
            } else {
                section.classList.remove('active');
            }
        });
        
        // Show all rows in the active section
        showAllRowsInSection(type);
    }
    
    function showAllRowsInSection(type) {
        const section = document.getElementById(type + '-section');
        if (!section) return;
        
        // Show all rows
        const tableRows = section.querySelectorAll('.tickets-table tbody tr');
        tableRows.forEach(row => {
            row.style.display = '';
        });
        
        // Clear search input
        const searchInput = section.querySelector('.search-input');
        if (searchInput) {
            searchInput.value = '';
        }
        
        // Reset city filter
        const cityButtons = section.querySelectorAll('.city-btn');
        cityButtons.forEach(btn => {
            if (btn.getAttribute('data-city') === '') {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }
    
    // City Filter Buttons
    document.querySelectorAll('.city-btn').forEach(button => {
        button.addEventListener('click', function() {
            const section = this.closest('.ticket-section');
            if (!section) return;
            
            // Update active button
            section.querySelectorAll('.city-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Apply filter
            const selectedCity = this.getAttribute('data-city');
            filterTicketsByCity(section, selectedCity);
        });
    });
    
    function filterTicketsByCity(section, city) {
        const rows = section.querySelectorAll('.tickets-table tbody tr');
        
        rows.forEach(row => {
            // Skip "no data" rows
            if (row.querySelector('.no-data')) return;
            
            // Get address cell (contains city)
            const addressCell = row.querySelector('td:nth-child(4)'); // Adjust based on table structure
            if (!addressCell) return;
            
            const address = addressCell.textContent.toLowerCase();
            
            if (city === '' || address.includes(city.toLowerCase())) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Search Functionality for each section
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const section = this.closest('.ticket-section');
            if (!section) return;
            
            const rows = section.querySelectorAll('.tickets-table tbody tr');
            rows.forEach(row => {
                // Skip "no data" rows
                if (row.querySelector('.no-data')) return;
                
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
    
    // Select All Checkboxes
    const selectAllCheckboxes = document.querySelectorAll('[id^="select-all-"]');
    selectAllCheckboxes.forEach(selectAll => {
        selectAll.addEventListener('change', function() {
            const section = this.closest('.ticket-section');
            if (!section) return;
            
            const checkboxes = section.querySelectorAll('.row-checkbox');
            const visibleCheckboxes = Array.from(checkboxes).filter(cb => {
                const row = cb.closest('tr');
                return row && row.style.display !== 'none';
            });
            
            visibleCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActionsForSection(section);
        });
    });
    
    // Individual Checkbox Change
    const allCheckboxes = document.querySelectorAll('.row-checkbox');
    allCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const section = this.closest('.ticket-section');
            if (section) {
                updateBulkActionsForSection(section);
            }
        });
    });
    
    function updateBulkActionsForSection(section) {
        const checkedBoxes = section.querySelectorAll('.row-checkbox:checked');
        const deleteBtn = section.querySelector('.btn-delete-selected');
        
        if (deleteBtn) {
            if (checkedBoxes.length > 0) {
                deleteBtn.style.display = 'inline-block';
            } else {
                deleteBtn.style.display = 'none';
            }
        }
    }
    
    // Create Ticket Buttons - open modals instead of redirect
    document.getElementById('open-create-installation')?.addEventListener('click', function() {
        const modal = document.getElementById('create-ticket-modal');
        if (modal) { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; }
    });

    document.getElementById('open-create-maintenance')?.addEventListener('click', function() {
        const modal = document.getElementById('create-maintenance-modal');
        if (modal) { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; }
    });

    document.getElementById('open-create-pullout')?.addEventListener('click', function() {
        const modal = document.getElementById('create-pullout-modal');
        if (modal) { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; }
    });

    // Cancel buttons for create modals
    const cancelMap = {
        'cancel-create-ticket': 'create-ticket-modal',
        'close-create-ticket': 'create-ticket-modal',
        'cancel-create-maintenance': 'create-maintenance-modal',
        'close-create-maintenance': 'create-maintenance-modal',
        'cancel-create-pullout': 'create-pullout-modal',
        'close-create-pullout': 'create-pullout-modal'
    };
    Object.entries(cancelMap).forEach(([btnId, modalId]) => {
        document.getElementById(btnId)?.addEventListener('click', function() {
            const modal = document.getElementById(modalId);
            if (modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
        });
    });
});

// Unified Maintenance Modal Function (now using viewMaintenanceDetails directly)
// Unified Pullout Modal Function (now using viewPulloutDetails directly)
function openUnifiedMaintenanceModal(ticketId) {
    fetch('get_maintenance_data.php?id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateMaintenanceModal(data.ticket, data.images);
            } else {
                Dialog.error('Error loading maintenance data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Dialog.error('An error occurred while loading maintenance data.');
        });
}

function populateMaintenanceModal(ticket, images) {
    try {
        // Populate ticket information with maintenance-specific IDs
        document.getElementById('view-maintenance-ticket-number').textContent = ticket.ticket_number;
        
        // Status badge
        const statusSpan = document.getElementById('view-maintenance-status');
        statusSpan.className = 'status-badge status-' + ticket.status.toLowerCase().replace(' ', '-');
        statusSpan.textContent = ticket.status;
        
        // Priority badge
        const prioritySpan = document.getElementById('view-maintenance-priority');
        let priorityClass = '';
        if (ticket.priority === 'Urgent') priorityClass = 'priority-urgent';
        else if (ticket.priority === 'Normal') priorityClass = 'priority-normal';
        else priorityClass = 'priority-low';
        prioritySpan.className = 'priority-badge ' + priorityClass;
        prioritySpan.textContent = ticket.priority;
        
        document.getElementById('view-maintenance-issue-type').textContent = ticket.issue_type;
        
        // Format date
        const createdDate = new Date(ticket.created_at);
        document.getElementById('view-maintenance-created').textContent = createdDate.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        
        document.getElementById('view-maintenance-assigned').textContent = ticket.assigned_name || 'Unassigned';
        
        // Client information
        document.getElementById('view-maintenance-client-name').textContent = ticket.client_name;
        document.getElementById('view-maintenance-contact').textContent = ticket.contact_number;
        document.getElementById('view-maintenance-email').textContent = ticket.email || 'N/A';
        document.getElementById('view-maintenance-account').textContent = ticket.account_number || 'N/A';
        document.getElementById('view-maintenance-address').textContent = ticket.address;
        
        // Description
        document.getElementById('view-maintenance-description').innerHTML = ticket.description.replace(/\n/g, '<br>');
        
        // Set maintenance ID for forms
        document.getElementById('modal-tech-maintenance-id').value = ticket.id;
        document.getElementById('modal-maintenance-id').value = ticket.id;
        
        // Display images if function exists
        if (typeof displayModalImages === 'function') {
            displayModalImages(images);
        } else {
            console.log('displayModalImages function not found, skipping images');
        }

        // Sync NOC clearance state
        if (typeof updateModalNOCClearance === 'function') {
            updateModalNOCClearance(ticket.noc_cleared_at, ticket.noc_cleared_by_name);
        }

        // Clear any previous preview images and reset file input
        const previewContainer = document.getElementById('modal-preview-container');
        if (previewContainer) previewContainer.innerHTML = '';
        const fileInput = document.getElementById('modal-image-upload');
        if (fileInput) fileInput.value = '';
        const uploadBtn = document.getElementById('modal-upload-btn');
        if (uploadBtn) uploadBtn.style.display = 'none';
        // Reset selected files array
        if (typeof selectedMaintFiles !== 'undefined') selectedMaintFiles = [];

        // Set current maintenance ID for edit functionality (variable declared in tickets_maintenance.js)
        if (typeof currentMaintenanceId !== 'undefined') {
            currentMaintenanceId = ticket.id;
        }

        // Show modal
        document.getElementById('view-maintenance-modal').style.display = 'block';
        document.body.style.overflow = 'hidden';
        console.log('✅ Maintenance modal displayed successfully');
        
    } catch (error) {
        console.error('Error in populateMaintenanceModal:', error);
        Dialog.error('Error populating maintenance modal: ' + error.message);
    }
}

// Unified Pullout Modal Function
function openUnifiedPulloutModal(ticketId) {
    fetch('get_pullout_data.php?id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populatePulloutModal(data.ticket, data.images);
            } else {
                Dialog.error('Error loading pullout data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Dialog.error('An error occurred while loading pullout data.');
        });
}

function populatePulloutModal(ticket, images) {
    try {
        // Populate ticket information with pullout-specific IDs
        document.getElementById('view-pullout-ticket-number').textContent = ticket.ticket_number;
        
        // Status badge
        const statusSpan = document.getElementById('view-pullout-status');
        statusSpan.className = 'status-badge status-' + ticket.status.toLowerCase().replace(' ', '-');
        statusSpan.textContent = ticket.status;
        
        // Priority badge
        const prioritySpan = document.getElementById('view-pullout-priority');
        let priorityClass = '';
        if (ticket.priority === 'Urgent') priorityClass = 'priority-urgent';
        else if (ticket.priority === 'Normal') priorityClass = 'priority-normal';
        else priorityClass = 'priority-low';
        prioritySpan.className = 'priority-badge ' + priorityClass;
        prioritySpan.textContent = ticket.priority;
        
        // Format date
        const createdDate = new Date(ticket.created_at);
        document.getElementById('view-pullout-created').textContent = createdDate.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        
        document.getElementById('view-pullout-assigned').textContent = ticket.assigned_name || 'Unassigned';
        
        // Client information
        document.getElementById('view-pullout-client-name').textContent = ticket.client_name;
        document.getElementById('view-pullout-contact').textContent = ticket.contact_number;
        document.getElementById('view-pullout-address').textContent = ticket.address;
        
        // Reason
        document.getElementById('view-pullout-reason').innerHTML = ticket.reason.replace(/\n/g, '<br>');
        
        // Set pullout ID for forms
        document.getElementById('modal-pullout-id').value = ticket.id;
        document.getElementById('modal-pullout-upload-id').value = ticket.id;
        
        // Set current status in dropdown
        const statusSelect = document.getElementById('modal-pullout-status');
        if (statusSelect) {
            statusSelect.value = ticket.status;
        }
        
        // NOC clearance display
        updatePulloutModalNOCClearance(ticket.noc_cleared_at, ticket.noc_cleared_by_name);

        // Clear any previous preview images and reset file input
        const pulloutPreviewContainer = document.getElementById('modal-pullout-preview-container');
        if (pulloutPreviewContainer) pulloutPreviewContainer.innerHTML = '';
        const pulloutFileInput = document.getElementById('modal-pullout-image-upload');
        if (pulloutFileInput) pulloutFileInput.value = '';
        const pulloutUploadBtn = document.getElementById('modal-pullout-upload-btn');
        if (pulloutUploadBtn) pulloutUploadBtn.style.display = 'none';
        // Reset selected files array
        if (typeof selectedPulloutFiles !== 'undefined') selectedPulloutFiles = [];

        // Set current pullout ID for edit functionality (variable declared in tickets_pullout.js)
        if (typeof currentPulloutId !== 'undefined') {
            currentPulloutId = ticket.id;
        }

        // Display images in pullout gallery
        displayPulloutImages(images);
        
        // Show modal
        document.getElementById('view-pullout-modal').style.display = 'block';
        document.body.style.overflow = 'hidden';
        console.log('✅ Pullout modal displayed successfully');
        
    } catch (error) {
        console.error('Error in populatePulloutModal:', error);
        Dialog.error('Error populating pullout modal: ' + error.message);
    }
}

// Simple image display function for pullout
function displayPulloutImages(images) {
    const gallery = document.getElementById('modal-pullout-images-gallery');
    if (!gallery) {
        console.log('Pullout images gallery not found');
        return;
    }
    
    if (!images || images.length === 0) {
        gallery.innerHTML = '<p class="no-images">No images uploaded yet.</p>';
        return;
    }
    
    let html = '';
    images.forEach(image => {
        let src = image.image_path;
        if (src && src.startsWith('/IMAGES/')) {
            src = (window.BASE_URL || '') + src;
        }
        html += `
            <div class="gallery-item">
                <img src="${src}" alt="Pullout Image" onclick="openImageModal('${src}')">
                <div class="image-info">
                    <span class="image-date">${new Date(image.uploaded_at).toLocaleDateString()}</span>
                </div>
            </div>
        `;
    });
    
    gallery.innerHTML = html;
}

// Image modal functions
function openImageModal(imageSrc) {
    const modal = document.getElementById('image-modal');
    const modalImg = document.getElementById('modal-image');
    if (modal && modalImg) {
        modalImg.src = imageSrc;
        modal.style.display = 'block';
        if (typeof initImageZoom === 'function') initImageZoom('modal-image', 'image-modal');
    }
}

function closeImageModal() {
    const modal = document.getElementById('image-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Reset Ticket Filters
function resetTicketFilters() {
    // Get active section
    const activeSection = document.querySelector('.ticket-section.active');
    if (!activeSection) return;
    
    // Clear search
    const searchInput = activeSection.querySelector('.search-input');
    if (searchInput) searchInput.value = '';
    
    // Show all rows
    const rows = activeSection.querySelectorAll('.tickets-table tbody tr');
    rows.forEach(row => {
        row.style.display = '';
    });
}


// Modal close button handlers
document.addEventListener('DOMContentLoaded', function() {
    // Close buttons for all modals
    const closeButtons = [
        { id: 'close-view-installation', modalId: 'view-installation-modal' },
        { id: 'close-view-maintenance', modalId: 'view-maintenance-modal' },
        { id: 'close-view-pullout', modalId: 'view-pullout-modal' }
    ];
    
    closeButtons.forEach(btn => {
        const closeBtn = document.getElementById(btn.id);
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                const modal = document.getElementById(btn.modalId);
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
});

// NOC Clear for Pull Out (missing function)
// Note: pulloutNocClearedFlag is declared in tickets_pullout.js

function updatePulloutModalNOCClearance(clearedAt, clearedBy) {
    const infoDiv = document.getElementById('pullout-noc-clear-info');
    const button = document.getElementById('btn-pullout-noc-clear');
    if (!infoDiv || !button) return;

    if (clearedAt) {
        const date = new Date(clearedAt);
        infoDiv.innerHTML = `
            <div class="cleared-status">
                <span class="cleared-icon">✓</span>
                <div>
                    <div class="cleared-label">Cleared</div>
                    <div class="cleared-time">${date.toLocaleString()}</div>
                    <div class="cleared-by">By: ${clearedBy || 'Unknown'}</div>
                </div>
            </div>
        `;
        button.disabled = true;
        button.classList.add('disabled');
    } else {
        infoDiv.innerHTML = '<div class="not-cleared">Not cleared yet</div>';
        button.disabled = false;
        button.classList.remove('disabled');
    }
}

function clearModalPulloutNOC() {
    const pulloutId = document.getElementById('modal-pullout-id').value;
    
    if (!pulloutId) {
        Dialog.warning('No ticket selected');
        return;
    }
    
    Dialog.confirm('Are you sure you want to mark this as NOC CLEARED?', { type: 'confirm', okText: 'Yes, Clear' }).then(ok => {
        if (!ok) return;
    
    const formData = new FormData();
    formData.append('pullout_id', pulloutId);
    
    fetch('clear_pullout_hostinger_fix.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check if response is ok first
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response as text first to check for HTML errors
        return response.text();
    })
    .then(text => {
        console.log('Raw pullout response:', text); // Debug log
        
        // Try to parse as JSON
        try {
            const data = JSON.parse(text);
            if (data.success) {
                // Update the NOC clearance display in place without reloading
                const now = new Date().toISOString();
                const userName = document.querySelector('.user-name')?.textContent || 'You';
                updatePulloutModalNOCClearance(now, userName);
                // Update status badge to Closed
                const statusSpan = document.getElementById('view-pullout-status');
                if (statusSpan) {
                    statusSpan.className = 'status-badge status-closed';
                    statusSpan.textContent = 'Closed';
                }
                const statusSelect = document.getElementById('modal-status');
                if (statusSelect) statusSelect.value = 'Closed';
                // Force-refresh the unified tickets.php table immediately (bypasses modal guard)
                if (typeof triggerUnifiedRefresh === 'function') triggerUnifiedRefresh(true);
            } else {
                Dialog.error('Error: ' + data.message);
            }
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', text);
            Dialog.error('Server response error. Check console for details.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Dialog.error('An error occurred while clearing NOC: ' + error.message);
    });
    }); // end Dialog.confirm
}

// View Pull Out Details (missing function)
function viewPulloutDetails(ticketId) {
    // Fetch pullout data and open modal
    fetch('get_pullout_data.php?id=' + ticketId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text(); // Get as text first to check for errors
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    // Attach images to ticket object
                    data.ticket.images = data.images || [];
                    openViewPulloutModal(data.ticket);
                } else {
                    Dialog.error('Error loading pull out data: ' + data.message);
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw response:', text);
                Dialog.error('Invalid response from server. Check console for details.');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            Dialog.error('An error occurred while loading pull out data: ' + error.message);
        });
}

// Open View Pull Out Modal (missing function)
function openViewPulloutModal(ticket) {
    try {
        // Use the unified modal function instead
        openUnifiedPulloutModal(ticket.id);
    } catch (error) {
        console.error('Error opening pullout modal:', error);
        Dialog.error('Error opening pullout details');
    }
}

// --- Bulk Delete for All Tickets ---
document.addEventListener('DOMContentLoaded', function() {
    const selectAllAll = document.getElementById('select-all-all');
    const checkboxesAll = document.querySelectorAll('.row-checkbox-all');
    const bulkDeleteBtn = document.getElementById('bulk-delete-all-btn');
    const table = document.querySelector('#all-section .tickets-table');
    if (selectAllAll && table) {
        selectAllAll.addEventListener('change', function() {
            const checked = this.checked;
            table.querySelectorAll('.row-checkbox-all').forEach(cb => { cb.checked = checked; });
            updateBulkDeleteAllBtn();
        });
        table.addEventListener('change', function(e) {
            if (e.target.classList.contains('row-checkbox-all')) {
                updateBulkDeleteAllBtn();
                // If any unchecked, uncheck select-all
                if (!e.target.checked) selectAllAll.checked = false;
                // If all checked, check select-all
                else if ([...table.querySelectorAll('.row-checkbox-all')].every(cb => cb.checked)) selectAllAll.checked = true;
            }
        });
    }
    function updateBulkDeleteAllBtn() {
        const checkedCount = table.querySelectorAll('.row-checkbox-all:checked').length;
        if (bulkDeleteBtn) bulkDeleteBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
    }
});

// Initialize modal functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Tickets unified JS loaded - using individual page functions directly');
});


// ── SPA Auto-Refresh (every 10 seconds) ──────────────────────────────────────
(function () {
    const INTERVAL = 3000;
    let lastHash = null; // detect actual changes before re-rendering

    function priorityClass(p) {
        if (p === 'Urgent') return 'priority-urgent';
        if (p === 'Normal') return 'priority-normal';
        return 'priority-low';
    }

    function typeLabel(type) {
        if (type === 'installation') return '<span style="background:linear-gradient(135deg, #065f5f, #0d9488);color:#fff;font-size:10px;padding:2px 8px;border-radius:12px;font-weight:700;box-shadow:0 2px 4px rgba(6,95,95,0.4);">INST</span>';
        if (type === 'maintenance')  return '<span style="background:linear-gradient(135deg, #064e4e, #065f5f);color:#fff;font-size:10px;padding:2px 8px;border-radius:12px;font-weight:700;box-shadow:0 2px 4px rgba(6,78,78,0.4);">MAINT</span>';
        return '<span style="background:linear-gradient(135deg, #043f3f, #064e4e);color:#fff;font-size:10px;padding:2px 8px;border-radius:12px;font-weight:700;box-shadow:0 2px 4px rgba(4,63,63,0.4);">PULL</span>';
    }

    function viewBtn(t) {
        let btns = '';
        // Assign button
        if (t.ticket_type === 'installation') btns += `<button class="btn-icon btn-assign" onclick="openAssignModal(${t.id}, '${(t.client_name||'').replace(/'/g,"\\'")}')" title="Assign"></button>`;
        else if (t.ticket_type === 'maintenance') btns += `<button class="btn-icon btn-assign" onclick="openAssignMaintenanceModal(${t.id}, '${(t.client_name||'').replace(/'/g,"\\'")}')" title="Assign"></button>`;
        else btns += `<button class="btn-icon btn-assign" onclick="openAssignPulloutModal(${t.id}, '${(t.client_name||'').replace(/'/g,"\\'")}')" title="Assign"></button>`;
        // View button
        if (t.ticket_type === 'installation') btns += `<button class="btn-icon btn-view" onclick="viewInstallation(${t.id})" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`;
        else if (t.ticket_type === 'maintenance') btns += `<button class="btn-icon btn-view" onclick="viewMaintenanceDetails(${t.id})" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`;
        else btns += `<button class="btn-icon btn-view" onclick="viewPulloutDetails(${t.id})" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`;
        // Delete button
        if (t.ticket_type === 'installation') btns += `<button class="btn-icon btn-delete" onclick="deleteTicket(${t.id})" title="Delete">🗑️</button>`;
        else if (t.ticket_type === 'maintenance') btns += `<button class="btn-icon btn-delete" onclick="deleteMaintenanceTicket(${t.id})" title="Delete">🗑️</button>`;
        else btns += `<button class="btn-icon btn-delete" onclick="deletePulloutTicket(${t.id})" title="Delete">🗑️</button>`;
        return btns;
    }

    function buildRow(t) {
        return `<tr class="clickable-row" data-status="${t.status}" data-priority="${t.priority}" data-type="${t.ticket_type}" data-id="${t.id}">
            <td onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox-all" value="${t.ticket_type}:${t.id}"></td>
            <td class="td-actions" onclick="event.stopPropagation()"><div class="action-buttons">${viewBtn(t)}</div></td>
            <td><div class="ticket-number">${t.ticket_number || ''} ${typeLabel(t.ticket_type)}</div></td>
            <td>${t.client_name || ''}</td>
            <td>${t.address || ''}</td>
            <td>${t.contact_number || ''}</td>
            <td>${t.info || ''}</td>
            <td>${t.assigned_name || 'Unassigned'}</td>
            <td>${t.created_at || ''}</td>
            <td class="details-column"><span class="priority-badge ${priorityClass(t.priority)}">${t.priority}</span>
                <span class="status-badge status-${(t.status||'').toLowerCase().replace(' ','-')}">${t.status}</span></td>
        </tr>`;
    }

    // Build row for installation-specific table
    function buildInstallationRow(t) {
        return `<tr class="clickable-row" data-status="${t.status}" data-priority="${t.priority}" data-id="${t.id}">
            <td onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox-installation" value="${t.id}"></td>
            <td class="td-actions" onclick="event.stopPropagation()"><div class="action-buttons">${viewBtn(t)}</div></td>
            <td><div class="ticket-number">${t.ticket_number || ''}</div></td>
            <td>${t.client_name || ''}</td>
            <td>${t.address || ''}</td>
            <td>${t.contact_number || ''}</td>
            <td>${t.plan || ''}</td>
            <td>${t.service_type || ''} / ${t.connection_type || ''}</td>
            <td>${t.created_at || ''}</td>
            <td><span class="priority-badge ${priorityClass(t.priority)}">${t.priority}</span>
                <span class="status-badge status-${(t.status||'').toLowerCase().replace(' ','-')}">${t.status}</span></td>
        </tr>`;
    }

    // Build row for maintenance-specific table
    function buildMaintenanceRow(t) {
        return `<tr class="clickable-row" data-status="${t.status}" data-priority="${t.priority}" data-id="${t.id}">
            <td onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox-maintenance" value="${t.id}"></td>
            <td class="td-actions" onclick="event.stopPropagation()"><div class="action-buttons">${viewBtn(t)}</div></td>
            <td><div class="ticket-number">${t.ticket_number || ''}</div></td>
            <td>${t.issue_type || ''}</td>
            <td>${t.client_name || ''}</td>
            <td>${t.address || ''}</td>
            <td>${t.contact_number || ''}</td>
            <td>${t.description ? t.description.substring(0,30) + (t.description.length > 30 ? '...' : '') : ''}</td>
            <td>${t.created_at || ''}</td>
            <td><span class="priority-badge ${priorityClass(t.priority)}">${t.priority}</span>
                <span class="status-badge status-${(t.status||'').toLowerCase().replace(' ','-')}">${t.status}</span></td>
        </tr>`;
    }

    // Build row for pullout-specific table
    function buildPulloutRow(t) {
        return `<tr class="clickable-row" data-status="${t.status}" data-priority="${t.priority}" data-id="${t.id}">
            <td onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox-pullout" value="${t.id}"></td>
            <td class="td-actions" onclick="event.stopPropagation()"><div class="action-buttons">${viewBtn(t)}</div></td>
            <td><div class="ticket-number">${t.ticket_number || ''}</div></td>
            <td>${t.client_name || ''}</td>
            <td>${t.address || ''}</td>
            <td>${t.contact_number || ''}</td>
            <td>${t.reason ? t.reason.substring(0,40) + (t.reason.length > 40 ? '...' : '') : ''}</td>
            <td>${t.created_at || ''}</td>
            <td><span class="priority-badge ${priorityClass(t.priority)}">${t.priority}</span>
                <span class="status-badge status-${(t.status||'').toLowerCase().replace(' ','-')}">${t.status}</span></td>
        </tr>`;
    }

    function updateTabBadges(count, byType) {
        // Update tab badge counts
        document.querySelectorAll('.ticket-type-tab').forEach(tab => {
            const type = tab.getAttribute('data-ticket-type');
            const badge = tab.querySelector('.ticket-type-badge');
            if (!badge) return;
            if (type === 'all') badge.textContent = count;
            else if (byType[type] !== undefined) badge.textContent = byType[type];
        });
    }

    function refresh(force) {
        // Don't refresh if a modal is open (unless forced)
        if (!force && document.querySelector('.modal[style*="block"]')) return;

        fetch('get_all_tickets.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                // Change detection - include all fields that might be edited
                const hash = data.tickets.map(t => 
                    t.ticket_type + '|' + t.id + '|' + t.status + '|' + t.priority + '|' + 
                    (t.client_name||'') + '|' + (t.address||'') + '|' + (t.contact_number||'') + '|' +
                    (t.plan||'') + '|' + (t.issue_type||'') + '|' + (t.description||'') + '|' + (t.reason||'') + '|' +
                    (t.assigned_name||'')
                ).join('||');
                
                // Only skip if not forced and hash matches
                if (!force && hash === lastHash) return; // nothing changed
                lastHash = hash;

                // Count by type
                const byType = { installation: 0, maintenance: 0, pullout: 0 };
                data.tickets.forEach(t => { if (byType[t.ticket_type] !== undefined) byType[t.ticket_type]++; });

                // Re-render all-section tbody
                const allTbody = document.querySelector('#all-section .tickets-table tbody');
                if (allTbody) {
                    const searchVal = (document.getElementById('search-all') || {}).value || '';
                    if (data.tickets.length === 0) {
                        allTbody.innerHTML = '<tr><td colspan="10">No tickets found.</td></tr>';
                    } else {
                        allTbody.innerHTML = data.tickets.map(buildRow).join('');
                    }
                    // Re-apply search filter if active
                    if (searchVal.trim()) {
                        const term = searchVal.toLowerCase();
                        allTbody.querySelectorAll('tr').forEach(row => {
                            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                        });
                    }
                    // Re-attach bulk delete checkbox listeners
                    const allTable = document.querySelector('#all-section .tickets-table');
                    const bulkBtn = document.getElementById('bulk-delete-all-btn');
                    if (allTable && bulkBtn) {
                        allTable.querySelectorAll('.row-checkbox-all').forEach(cb => {
                            cb.addEventListener('change', () => {
                                const checked = allTable.querySelectorAll('.row-checkbox-all:checked').length;
                                bulkBtn.style.display = checked > 0 ? 'inline-block' : 'none';
                                const countSpan = document.getElementById('bulk-delete-all-count');
                                if (countSpan) countSpan.textContent = checked;
                            });
                        });
                    }
                }

                // Re-render installation-section tbody
                const instTbody = document.querySelector('#installation-section .tickets-table tbody');
                if (instTbody) {
                    const instTickets = data.tickets.filter(t => t.ticket_type === 'installation');
                    const instSearchVal = (document.getElementById('search-installation') || {}).value || '';
                    if (instTickets.length === 0) {
                        instTbody.innerHTML = '<tr><td colspan="10">No installation tickets found.</td></tr>';
                    } else {
                        instTbody.innerHTML = instTickets.map(buildInstallationRow).join('');
                    }
                    if (instSearchVal.trim()) {
                        const term = instSearchVal.toLowerCase();
                        instTbody.querySelectorAll('tr').forEach(row => {
                            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                        });
                    }
                }

                // Re-render maintenance-section tbody
                const maintTbody = document.querySelector('#maintenance-section .tickets-table tbody');
                if (maintTbody) {
                    const maintTickets = data.tickets.filter(t => t.ticket_type === 'maintenance');
                    const maintSearchVal = (document.getElementById('search-maintenance') || {}).value || '';
                    if (maintTickets.length === 0) {
                        maintTbody.innerHTML = '<tr><td colspan="10">No maintenance tickets found.</td></tr>';
                    } else {
                        maintTbody.innerHTML = maintTickets.map(buildMaintenanceRow).join('');
                    }
                    if (maintSearchVal.trim()) {
                        const term = maintSearchVal.toLowerCase();
                        maintTbody.querySelectorAll('tr').forEach(row => {
                            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                        });
                    }
                }

                // Re-render pullout-section tbody
                const pulloutTbody = document.querySelector('#pullout-section .tickets-table tbody');
                if (pulloutTbody) {
                    const pulloutTickets = data.tickets.filter(t => t.ticket_type === 'pullout');
                    const pulloutSearchVal = (document.getElementById('search-pullout') || {}).value || '';
                    if (pulloutTickets.length === 0) {
                        pulloutTbody.innerHTML = '<tr><td colspan="9">No pullout tickets found.</td></tr>';
                    } else {
                        pulloutTbody.innerHTML = pulloutTickets.map(buildPulloutRow).join('');
                    }
                    if (pulloutSearchVal.trim()) {
                        const term = pulloutSearchVal.toLowerCase();
                        pulloutTbody.querySelectorAll('tr').forEach(row => {
                            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                        });
                    }
                }

                // Update tab badges
                updateTabBadges(data.count, byType);
            })
            .catch(() => {}); // silent fail — don't disrupt the user
    }

    // Expose manual trigger for other scripts
    window.triggerUnifiedRefresh = function (force) { refresh(force); };

    // Start polling after DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        refresh(); // immediate first run
        setInterval(refresh, INTERVAL);
    });
})();
