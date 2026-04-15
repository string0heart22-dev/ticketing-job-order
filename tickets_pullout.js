// Pull Out Tickets JavaScript

// Global variable to track current pullout ticket ID
let currentPulloutId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Create Pull Out Ticket Modal
    const createPulloutBtn = document.getElementById('create-pullout-btn');
    const createPulloutModal = document.getElementById('create-pullout-modal');
    const closeCreatePullout = document.getElementById('close-create-pullout');
    const cancelCreatePullout = document.getElementById('cancel-create-pullout');

    if (createPulloutBtn) {
        createPulloutBtn.addEventListener('click', function() {
            createPulloutModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    }

    // Create pullout form — handled exclusively by tickets_address_save_patch.js
    // Check if coming from service areas and populate address
    const serviceAreaParams = new URLSearchParams(window.location.search);
    if (serviceAreaParams.get('from_service_area') === '1') {
        const addressData = sessionStorage.getItem('pullout_address_data');
        if (addressData) {
            try {
                const data = JSON.parse(addressData);
                document.getElementById('pullout_province_input').value = data.province || '';
                document.getElementById('pullout_city_input').value = data.city || '';
                document.getElementById('pullout_barangay_input').value = data.barangay || '';
                document.getElementById('pullout_purok_zone_input').value = data.purok_zone || '';
                document.getElementById('pullout_zip_code').value = data.zip_code || '';
                const fullAddress = [data.purok_zone, data.barangay, data.city, data.province, data.zip_code].filter(v => v).join(', ');
                document.getElementById('pullout_address').value = fullAddress;
                createPulloutModal.style.display = 'block';
                sessionStorage.removeItem('pullout_address_data');
            } catch (e) {
                console.error('Error parsing address data:', e);
            }
        }
    }

    if (closeCreatePullout) {
        closeCreatePullout.addEventListener('click', function() {
            createPulloutModal.style.display = 'none';
            document.body.style.overflow = '';
        });
    }

    if (cancelCreatePullout) {
        cancelCreatePullout.addEventListener('click', function() {
            createPulloutModal.style.display = 'none';
            document.body.style.overflow = '';
        });
    }

    // Assign Pull Out Modal — submit via AJAX
    const assignPulloutModal = document.getElementById('assign-pullout-modal');
    const assignPulloutForm = document.getElementById('assign-pullout-form');
    if (assignPulloutForm) {
        assignPulloutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('assign_pullout.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        Dialog.error('Assignment failed: ' + (data.message || 'Unknown error'));
                        return;
                    }
                    document.getElementById('assign-pullout-modal').style.display = 'none';
                    if (typeof triggerPulloutRefresh === 'function') triggerPulloutRefresh();
                })
                .catch(err => console.error('Assign error:', err));
        });
    }
    const closeAssignPullout = document.getElementById('close-assign-pullout');
    const cancelAssignPullout = document.getElementById('cancel-assign-pullout');

    if (closeAssignPullout) {
        closeAssignPullout.addEventListener('click', function() {
            assignPulloutModal.style.display = 'none';
        });
    }

    if (cancelAssignPullout) {
        cancelAssignPullout.addEventListener('click', function() {
            assignPulloutModal.style.display = 'none';
        });
    }

    // View Pull Out Modal
    const viewPulloutModal = document.getElementById('view-pullout-modal');
    const closeViewPullout = document.getElementById('close-view-pullout');

    if (closeViewPullout) {
        closeViewPullout.addEventListener('click', function() {
            viewPulloutModal.style.display = 'none';
            if (pulloutNocClearedFlag) {
                pulloutNocClearedFlag = false;
                if (typeof triggerPulloutRefresh === 'function') triggerPulloutRefresh();
            }
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === createPulloutModal) {
            createPulloutModal.style.display = 'none';
        }
        if (event.target === assignPulloutModal) {
            assignPulloutModal.style.display = 'none';
        }
        if (event.target === viewPulloutModal) {
            viewPulloutModal.style.display = 'none';
            if (pulloutNocClearedFlag) {
                pulloutNocClearedFlag = false;
                if (typeof triggerPulloutRefresh === 'function') triggerPulloutRefresh();
            }
        }
    });

    // Status tabs functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tableRows = document.querySelectorAll('.tickets-table tbody tr');

    // Check URL parameters for tab and set initial active tab
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    let activeStatus = tabParam || 'Pending';

    tabButtons.forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-status') === activeStatus);
    });

    // Tab button click handlers — SPA: just switch active tab and re-filter
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            tabButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const tbody = document.querySelector('.tickets-table tbody');
            if (tbody) {
                const activeStatus = this.getAttribute('data-status');
                const searchVal = (document.getElementById('search-input') || {}).value || '';
                const activeCity = (document.querySelector('.city-btn.active') || {}).getAttribute('data-city') || '';
                tbody.querySelectorAll('tr[data-status]').forEach(row => {
                    const matchStatus = activeStatus === 'all' || row.getAttribute('data-status') === activeStatus;
                    const matchCity = !activeCity || (row.querySelector('td:nth-child(5)') || {}).textContent?.toLowerCase().includes(activeCity.toLowerCase());
                    const matchSearch = !searchVal.trim() || row.textContent.toLowerCase().includes(searchVal.toLowerCase());
                    row.style.display = (matchStatus && matchCity && matchSearch) ? '' : 'none';
                });
                if (typeof updateTabCounts === 'function') updateTabCounts();
            }
        });
    });

    // Select all checkboxes
    const selectAllPullout = document.getElementById('select-all-pullout');
    if (selectAllPullout) {
        selectAllPullout.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActionsBar();
        });
    }

    // Update bulk actions bar
    document.querySelectorAll('.row-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActionsBar);
    });

    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.tickets-table tbody tr');
            
            rows.forEach(row => {
                if (row.querySelector('.no-data')) return;
                
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});

// Open Assign Pull Out Modal
function openAssignPulloutModal(ticketId, clientName) {
    document.getElementById('assign_pullout_id').value = ticketId;
    document.getElementById('assign_pullout_name').textContent = 'Assigning pull out ticket for: ' + clientName;
    document.getElementById('assign-pullout-modal').style.display = 'block';
}

// Update Pull Out Status
function updatePulloutStatus(ticketId, status) {
    Dialog.confirm('Are you sure you want to change the status to "' + status + '"?', { type: 'confirm' }).then(ok => {
        if (!ok) {
            // User cancelled — reset dropdown via SPA refresh
            const selectElement = document.querySelector(`select[data-id="${ticketId}"]`);
            if (selectElement) {
                if (typeof triggerPulloutRefresh === 'function') triggerPulloutRefresh();
            }
            return;
        }
        const formData = new FormData();
        formData.append('ticket_id', ticketId);
        formData.append('status', status);

        fetch('update_pullout_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Check if we're in a modal view
                const modal = document.getElementById('view-pullout-modal');
                if (modal && modal.style.display === 'block') {
                    viewPulloutDetails(ticketId);
                } else {
                    if (typeof triggerPulloutRefresh === 'function') triggerPulloutRefresh();
                }
            } else {
                Dialog.error('Error updating status: ' + data.message);
            }
        })
        .catch(error => {
            Dialog.error('An error occurred. Please try again.');
            console.error('Error:', error);
        });
    });
}

// View Pull Out Details
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

// Open View Pull Out Modal
function openViewPulloutModal(ticket) {
    try {
        // Populate ticket information with safety checks
        const ticketNumberEl = document.getElementById('view-pullout-ticket-number');
        if (ticketNumberEl) ticketNumberEl.textContent = ticket.ticket_number || 'N/A';
        
        // Status badge
        const statusSpan = document.getElementById('view-pullout-status');
        if (statusSpan && ticket.status) {
            statusSpan.className = 'status-badge status-' + ticket.status.toLowerCase().replace(' ', '-');
            statusSpan.textContent = ticket.status;
        }
        
        // Priority badge
        const prioritySpan = document.getElementById('view-pullout-priority');
        if (prioritySpan && ticket.priority) {
            let priorityClass = '';
            if (ticket.priority === 'Urgent') priorityClass = 'priority-urgent';
            else if (ticket.priority === 'Normal') priorityClass = 'priority-normal';
            else priorityClass = 'priority-low';
            prioritySpan.className = 'priority-badge ' + priorityClass;
            prioritySpan.textContent = ticket.priority;
        }
        
        // Format date with error handling
        const createdEl = document.getElementById('view-pullout-created');
        if (createdEl) {
            try {
                if (ticket.created_at) {
                    const createdDate = new Date(ticket.created_at);
                    if (!isNaN(createdDate.getTime())) {
                        createdEl.textContent = createdDate.toLocaleString('en-US', {
                            timeZone: 'Asia/Manila',
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                    } else {
                        createdEl.textContent = 'Invalid date';
                    }
                } else {
                    createdEl.textContent = 'N/A';
                }
            } catch (error) {
                console.error('Error formatting date:', error);
                createdEl.textContent = 'Date error';
            }
        }
        
        const assignedEl = document.getElementById('view-pullout-assigned');
        if (assignedEl) assignedEl.textContent = ticket.assigned_name || 'Unassigned';
        
        // Client information with safety checks
        const clientNameEl = document.getElementById('view-pullout-client-name');
        if (clientNameEl) clientNameEl.textContent = ticket.client_name || 'N/A';
        
        const contactEl = document.getElementById('view-pullout-contact');
        if (contactEl) contactEl.textContent = ticket.contact_number || 'N/A';
        
        const addressEl = document.getElementById('view-pullout-address');
        if (addressEl) addressEl.textContent = ticket.address || 'N/A';
        
        // Reason with safety check
        const reasonEl = document.getElementById('view-pullout-reason');
        if (reasonEl) {
            reasonEl.innerHTML = (ticket.reason || 'No reason provided').replace(/\n/g, '<br>');
        }
        
        // Set pullout ID for status update and image upload with safety checks
        const modalPulloutId = document.getElementById('modal-pullout-id');
        if (modalPulloutId) modalPulloutId.value = ticket.id || '';
        
        const modalUploadId = document.getElementById('modal-pullout-upload-id');
        if (modalUploadId) modalUploadId.value = ticket.id || '';
        
        // Set current pullout ID for edit functionality
        currentPulloutId = ticket.id || null;
        
        const modalStatus = document.getElementById('modal-status');
        if (modalStatus) modalStatus.value = ticket.status || '';
        
        // Update NOC clearance display
        if (typeof updatePulloutModalNOCClearance === 'function') {
            updatePulloutModalNOCClearance(ticket.noc_cleared_at, ticket.noc_cleared_by_name);
        }
        
        // Load images with safety check
        const imagesGallery = document.getElementById('modal-pullout-images-gallery');
        if (imagesGallery) {
            if (ticket.images && ticket.images.length > 0) {
                if (typeof displayPulloutModalImages === 'function') {
                    displayPulloutModalImages(ticket.images);
                }
            } else {
                imagesGallery.innerHTML = '<p class="no-images">No images uploaded yet</p>';
            }
        }
        
        // Show modal with safety check
        const modal = document.getElementById('view-pullout-modal');
        if (modal) {
            modal.style.display = 'block';
        } else {
            console.error('Pullout modal element not found');
            Dialog.error('Modal element not found. Please refresh the page.');
        }
        
    } catch (error) {
        console.error('Error in openViewPulloutModal:', error);
        Dialog.error('An error occurred while opening the pullout modal. Please try again.');
    }
}

// Delete Pull Out Ticket
function deletePulloutTicket(id) {
    Dialog.confirm('Are you sure you want to delete this pull out ticket?', { type: 'danger', okText: 'Delete' }).then(ok => {
        if (!ok) return;
        fetch('delete_pullout_ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Dialog.toast('Pull out ticket deleted successfully!', 'success');
                if (typeof triggerPulloutRefresh === 'function') triggerPulloutRefresh();
            } else {
                Dialog.error('Error deleting ticket: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Dialog.error('Error deleting ticket');
        });
    });
}

// Bulk delete pull out tickets
function bulkDeletePullout() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkboxes.length === 0) {
        Dialog.warning('Please select tickets to delete');
        return;
    }

    Dialog.confirm(`Are you sure you want to delete ${checkboxes.length} pull out ticket(s)?`, { type: 'danger', okText: 'Delete' }).then(ok => {
        if (!ok) return;
        const ids = Array.from(checkboxes).map(cb => cb.dataset.id);
        
        fetch('bulk_delete_pullout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Dialog.toast(`${data.deleted} pull out ticket(s) deleted successfully!`, 'success');
                if (typeof triggerPulloutRefresh === 'function') triggerPulloutRefresh();
            } else {
                Dialog.error('Error deleting tickets: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Dialog.error('Error deleting tickets');
        });
    });
}

// Update bulk actions bar
function updateBulkActionsBar() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    const deleteBtn = document.getElementById('delete-selected-btn');
    
    if (checkedBoxes.length > 0) {
        deleteBtn.style.display = 'inline-block';
        deleteBtn.onclick = bulkDeletePullout;
    } else {
        deleteBtn.style.display = 'none';
    }
}

// Reset ticket filters
function resetTicketFilters() {
    // Clear search
    const searchInput = document.getElementById('search-input');
    if (searchInput) searchInput.value = '';
    
    // Show all rows
    const rows = document.querySelectorAll('.tickets-table tbody tr');
    rows.forEach(row => {
        if (!row.querySelector('.no-data')) {
            row.style.display = '';
        }
    });
}

// Close image modal
function closePulloutImageModal() {
    document.getElementById('image-modal').style.display = 'none';
}


// NOC Clear for Pull Out
let pulloutNocClearedFlag = false; // Track if NOC was cleared

function clearModalPulloutNOC(btn) {
    const pulloutId = document.getElementById('modal-pullout-id').value;
    
    if (!pulloutId) {
        Dialog.warning('No ticket selected');
        return;
    }

    const originalText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Clearing...'; }
    
    const formData = new FormData();
    formData.append('pullout_id', pulloutId);
    
    fetch('clear_pullout_hostinger_fix.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                pulloutNocClearedFlag = true;
                if (btn) {
                    btn.textContent = '✓ Cleared';
                    btn.classList.add('btn-save-tech-success');
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.classList.remove('btn-save-tech-success');
                        btn.disabled = false;
                    }, 2000);
                }
                viewPulloutDetails(pulloutId);
            } else {
                if (btn) {
                    btn.textContent = '✗ Error';
                    btn.classList.add('btn-save-tech-error');
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.classList.remove('btn-save-tech-error');
                        btn.disabled = false;
                    }, 2000);
                }
            }
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            if (btn) { btn.textContent = originalText; btn.disabled = false; }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (btn) { btn.textContent = originalText; btn.disabled = false; }
    });
}

// Update NOC clearance display in modal
function updatePulloutModalNOCClearance(clearedAt, clearedBy) {
    const infoDiv = document.getElementById('modal-noc-clear-info');
    const button = document.getElementById('btn-modal-noc-clear');
    
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

// Image Upload for Pull Out
let selectedPulloutModalFiles = [];

// Helper: get the right upload button ID based on which input fired
function _getPulloutUploadBtnId(inputId) {
    return inputId === 'modal-pullout-image-upload' ? 'modal-pullout-upload-btn' : 'modal-upload-btn';
}
function _getPulloutPreviewContainerId(inputId) {
    return inputId === 'modal-pullout-image-upload' ? 'modal-pullout-preview-container' : 'modal-preview-container';
}

document.addEventListener('DOMContentLoaded', function() {
    // Handle pullout uploads only - maintenance is handled by tickets.php
    let lastPulloutProcessTime = 0;
    document.addEventListener('change', function(e) {
        if (!e.target) return;
        const id = e.target.id;
        // Only handle pullout image upload, not maintenance (modal-image-upload)
        if (id !== 'modal-pullout-image-upload') return;
        
        // Prevent duplicate processing
        const now = Date.now();
        if (now - lastPulloutProcessTime < 1000) {
            console.log('Pullout upload blocked - too soon');
            return;
        }
        lastPulloutProcessTime = now;

        const files = Array.from(e.target.files);
        
        // Deduplicate files by name + size
        const seen = new Set();
        const uniqueFiles = [];
        files.forEach(file => {
            const key = file.name + '|' + file.size;
            if (!seen.has(key)) {
                seen.add(key);
                uniqueFiles.push(file);
            }
        });
        
        const validFiles = [];
        const invalidFiles = [];
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/jfif'];
        const allowedExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.jfif'];

        uniqueFiles.forEach(file => {
            const fileExt = '.' + file.name.split('.').pop().toLowerCase();
            if ((allowedTypes.includes(file.type) || allowedExtensions.includes(fileExt)) && file.size <= 5 * 1024 * 1024) {
                validFiles.push(file);
            } else if (file.size > 5 * 1024 * 1024) {
                invalidFiles.push(file.name + ' (too large, max 5MB)');
            } else {
                invalidFiles.push(file.name + ' (invalid file type)');
            }
        });

        if (invalidFiles.length > 0) {
            Dialog.warning('Files rejected:\n' + invalidFiles.join('\n'));
        }

        selectedPulloutModalFiles = validFiles;
        displayPulloutModalPreviews(validFiles, _getPulloutPreviewContainerId(id));

        const uploadBtn = document.getElementById(_getPulloutUploadBtnId(id));
        if (uploadBtn) uploadBtn.style.display = validFiles.length > 0 ? 'block' : 'none';
    });

    // Handle form submit for both pages
    document.addEventListener('submit', function(e) {
        const formId = e.target.id;
        // Only handle pullout upload forms - check for pullout-specific field
        if (formId === 'modal-pullout-upload-form' || 
            (formId === 'modal-upload-form' && document.getElementById('modal-pullout-upload-id'))) {
            e.preventDefault();
            uploadPulloutModalImages();
        }
    });
});

function displayPulloutModalPreviews(files, containerId) {
    const cid = containerId || (document.getElementById('modal-pullout-preview-container') ? 'modal-pullout-preview-container' : 'modal-preview-container');
    const container = document.getElementById(cid);
    container.innerHTML = '';
    
    const generation = Date.now() + Math.random();
    container.dataset.generation = generation;

    files.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (container.dataset.generation != generation) return;
            const preview = document.createElement('div');
            preview.className = 'preview-item';
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="remove-preview" onclick="removePulloutModalPreview(${index})">×</button>
            `;
            container.appendChild(preview);
        };
        reader.readAsDataURL(file);
    });
}

function removePulloutModalPreview(index) {
    selectedPulloutModalFiles.splice(index, 1);
    displayPulloutModalPreviews(selectedPulloutModalFiles);
    const btn = document.getElementById('modal-pullout-upload-btn') || document.getElementById('modal-upload-btn');
    if (btn && selectedPulloutModalFiles.length === 0) btn.style.display = 'none';
}

function uploadPulloutModalImages() {
    const pulloutId = document.getElementById('modal-pullout-upload-id').value;
    const uploadBtn = document.getElementById('modal-pullout-upload-btn') || document.getElementById('modal-upload-btn');

    if (!pulloutId) {
        Dialog.warning('No ticket selected');
        return;
    }

    if (selectedPulloutModalFiles.length === 0) {
        Dialog.warning('Please select images to upload');
        return;
    }

    const formData = new FormData();
    formData.append('pullout_id', pulloutId);

    selectedPulloutModalFiles.forEach((file, index) => {
        formData.append('images[]', file);
    });
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';

    fetch('upload_pullout_images.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                Dialog.toast(data.message, 'success');
                selectedPulloutModalFiles = [];
                const previewContainer = document.getElementById('modal-pullout-preview-container') || document.getElementById('modal-preview-container');
                if (previewContainer) previewContainer.innerHTML = '';
                uploadBtn.style.display = 'none';
                const fileInput = document.getElementById('modal-pullout-image-upload') || document.getElementById('modal-image-upload');
                if (fileInput) fileInput.value = '';
                loadPulloutModalImages(pulloutId);
            } else {
                Dialog.error('Upload failed: ' + data.message);
            }
        } catch (e) {
            console.error('JSON parse error:', e);
            Dialog.error('Server error: Invalid response format. Check console for details.');
        }
    })
    .catch(error => {
        Dialog.error('An error occurred while uploading images: ' + error.message);
    })
    .finally(() => {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload Images';
    });
}

function loadPulloutModalImages(pulloutId) {
    fetch(`get_pullout_data.php?id=${pulloutId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.images) {
                displayPulloutModalImages(data.images);
            }
        })
        .catch(error => console.error('Error loading images:', error));
}

function displayPulloutModalImages(images) {
    const gallery = document.getElementById('modal-pullout-images-gallery');
    
    if (images.length === 0) {
        gallery.innerHTML = '<p class="no-images">No images uploaded yet</p>';
        return;
    }
    
    gallery.innerHTML = '';
    images.forEach(image => {
        const imgDiv = document.createElement('div');
        imgDiv.className = 'gallery-item';
        
        let imageSrc = image.image_url || image.image_path;

        // Remove /ADMIN/ prefix if present
        if (imageSrc.startsWith('/ADMIN/')) {
            imageSrc = imageSrc.substring(7);
        }

        // Prepend base URL for absolute /IMAGES/ paths
        if (imageSrc.startsWith('/IMAGES/') || imageSrc.startsWith('IMAGES/')) {
            imageSrc = (window.BASE_URL || '') + '/' + imageSrc.replace(/^\//, '');
        } else if (imageSrc.startsWith('/') && !imageSrc.startsWith('//')) {
            imageSrc = (window.BASE_URL || '') + imageSrc;
        }
        
        const imgEl = document.createElement('img');
        imgEl.src = imageSrc;
        imgEl.alt = 'Pullout Image';
        imgEl.style.cursor = 'pointer';
        imgEl.addEventListener('click', (function(path) {
            return function() { openPulloutImageModal(path); };
        })(imageSrc));

        const delBtn = document.createElement('button');
        delBtn.className = 'delete-image';
        delBtn.textContent = '×';
        delBtn.addEventListener('click', (function(id) {
            return function() { deletePulloutModalImage(id); };
        })(image.id));

        const dateSpan = document.createElement('span');
        dateSpan.className = 'image-date';
        dateSpan.textContent = new Date(image.uploaded_at).toLocaleString();

        imgDiv.appendChild(imgEl);
        imgDiv.appendChild(delBtn);
        imgDiv.appendChild(dateSpan);
        gallery.appendChild(imgDiv);
    });
}

function deletePulloutModalImage(imageId) {
    Dialog.confirm('Are you sure you want to delete this image?', { type: 'danger', okText: 'Delete' }).then(ok => {
        if (!ok) return;
    
    const formData = new FormData();
    formData.append('image_id', imageId);
    
    fetch('delete_pullout_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const pulloutId = document.getElementById('modal-pullout-upload-id').value;
            loadPulloutModalImages(pulloutId);
        } else {
            Dialog.error('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Dialog.error('An error occurred while deleting the image');
    });
    });
}

function openPulloutImageModal(imagePath) {
    document.getElementById('modal-image').src = imagePath;
    document.getElementById('image-modal').style.display = 'block';
}

function closePulloutImageModal() {
    document.getElementById('image-modal').style.display = 'none';
}

// Aliases used by tickets_pullout.php HTML onclick handlers
function closeImageModal() { closePulloutImageModal(); }
function openImageModal(src) { openPulloutImageModal(src); }

// Edit pullout from view modal
function editPulloutFromView() {
    // Keep view modal open in background, just open edit modal on top
    // This allows user to return to view modal after canceling edit
    
    // Open edit modal using the unified editTicket function if available
    if (currentPulloutId) {
        // Check if editTicket is defined in the page (tickets_pullout.php)
        if (typeof editTicket === 'function') {
            editTicket(currentPulloutId, 'pullout');
        } else {
            console.error('editTicket function not found');
        }
    } else {
        console.error('No current pullout ID set');
    }
}

// Scroll to a section inside the pullout modal's scrollable container
function scrollToPulloutSection(sectionId) {
    const container = document.getElementById('view-pullout-content');
    const section = document.getElementById(sectionId);
    if (!container || !section) return;
    const containerRect = container.getBoundingClientRect();
    const sectionRect = section.getBoundingClientRect();
    container.scrollTo({ top: container.scrollTop + (sectionRect.top - containerRect.top) - 10, behavior: 'smooth' });
    section.style.transition = 'background-color 0.5s ease';
    section.style.backgroundColor = '#fef3c7';
    setTimeout(() => { section.style.backgroundColor = ''; }, 1500);
}

document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    var lightboxIds = ['pullout-image-modal', 'image-modal'];
    for (var i = 0; i < lightboxIds.length; i++) {
        var lb = document.getElementById(lightboxIds[i]);
        if (lb && lb.style.display !== 'none') { lb.style.display = 'none'; return; }
    }
    var modals = Array.from(document.querySelectorAll('.modal')).reverse();
    for (var j = 0; j < modals.length; j++) {
        var m = modals[j];
        if (m.style.display !== 'none' && m.style.display !== '') { m.style.display = 'none'; return; }
    }
});

// Scroll/pinch zoom + drag for image modal
(function() {
    var scale = 1, panX = 0, panY = 0;
    var dragging = false, dragStartX = 0, dragStartY = 0, panStartX = 0, panStartY = 0;
    var startDist = 0;

    function applyTransform(img) {
        img.style.transform = 'translate(calc(-50% + ' + panX + 'px), calc(-50% + ' + panY + 'px)) scale(' + scale + ')';
    }
    function resetZoom(img) {
        scale = 1; panX = 0; panY = 0;
        img.style.transform = 'translate(-50%, -50%) scale(1)';
        img.style.cursor = 'zoom-in';
    }

    function attachZoom(modalId, imgId) {
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById(modalId);
            if (!modal) return;

            modal.addEventListener('wheel', function(e) {
                var img = document.getElementById(imgId);
                if (!img) return;
                e.preventDefault();
                scale += e.deltaY < 0 ? 0.2 : -0.2;
                scale = Math.min(Math.max(scale, 1), 8);
                if (scale === 1) { panX = 0; panY = 0; }
                applyTransform(img);
                img.style.cursor = scale > 1 ? 'grab' : 'zoom-in';
            }, { passive: false });

            modal.addEventListener('mousedown', function(e) {
                var img = document.getElementById(imgId);
                if (!img || scale <= 1 || e.target !== img) return;
                dragging = true;
                dragStartX = e.clientX; dragStartY = e.clientY;
                panStartX = panX; panStartY = panY;
                img.style.cursor = 'grabbing';
                e.preventDefault();
            });
            document.addEventListener('mousemove', function(e) {
                if (!dragging) return;
                var img = document.getElementById(imgId);
                if (!img) return;
                panX = panStartX + (e.clientX - dragStartX);
                panY = panStartY + (e.clientY - dragStartY);
                applyTransform(img);
            });
            document.addEventListener('mouseup', function() {
                if (!dragging) return;
                dragging = false;
                var img = document.getElementById(imgId);
                if (img) img.style.cursor = scale > 1 ? 'grab' : 'zoom-in';
            });

            modal.addEventListener('touchstart', function(e) {
                if (e.touches.length === 2)
                    startDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
            });
            modal.addEventListener('touchmove', function(e) {
                var img = document.getElementById(imgId);
                if (!img || e.touches.length !== 2) return;
                e.preventDefault();
                var dist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
                scale *= dist / startDist;
                scale = Math.min(Math.max(scale, 1), 8);
                startDist = dist;
                applyTransform(img);
            }, { passive: false });
        });
    }

    attachZoom('image-modal', 'modal-image');

    var origOpen = window.openPulloutImageModal;
    window.openPulloutImageModal = function(src) {
        origOpen(src);
        var img = document.getElementById('modal-image');
        if (img) resetZoom(img);
    };
    var origClose = window.closePulloutImageModal;
    window.closePulloutImageModal = function() {
        var img = document.getElementById('modal-image');
        if (img) resetZoom(img);
        origClose();
    };
})();

// Image navigation variables
var currentImageIndex = 0;
var currentImageList = [];

// Open image modal with navigation support
function openPulloutImageModal(imagePath) {
    const modal = document.getElementById('image-modal');
    const modalImage = document.getElementById('modal-image');
    if (!modal || !modalImage) return;
    
    // Get all images from the gallery
    const gallery = document.getElementById('modal-pullout-images-gallery');
    if (gallery) {
        const images = gallery.querySelectorAll('img');
        currentImageList = Array.from(images).map(img => img.src);
        // Try to find the index - handle both absolute and relative paths
        currentImageIndex = currentImageList.findIndex(src => {
            return src === imagePath || 
                   src.endsWith(imagePath) || 
                   imagePath.endsWith(src) ||
                   src.replace(window.location.origin, '') === imagePath.replace(window.location.origin, '');
        });
        if (currentImageIndex === -1) currentImageIndex = 0;
    }
    
    modal.style.display = 'block';
    modalImage.src = imagePath;
    updateNavButtons();
}

// Show previous image
function showPrevImage() {
    if (currentImageList.length === 0) return;
    // Check if we're at the first image (button should be disabled)
    if (currentImageIndex === 0) return;
    currentImageIndex = (currentImageIndex - 1 + currentImageList.length) % currentImageList.length;
    const modalImage = document.getElementById('modal-image');
    if (modalImage) {
        modalImage.src = currentImageList[currentImageIndex];
    }
    updateNavButtons();
}

// Show next image
function showNextImage() {
    if (currentImageList.length === 0) return;
    // Check if we're at the last image (button should be disabled)
    if (currentImageIndex === currentImageList.length - 1) return;
    currentImageIndex = (currentImageIndex + 1) % currentImageList.length;
    const modalImage = document.getElementById('modal-image');
    if (modalImage) {
        modalImage.src = currentImageList[currentImageIndex];
    }
    updateNavButtons();
}

// Update navigation button visibility and state
function updateNavButtons() {
    const modal = document.getElementById('image-modal');
    if (!modal) return;
    
    const prevBtn = modal.querySelector('.modal-prev-btn');
    const nextBtn = modal.querySelector('.modal-next-btn');
    
    // Show/hide buttons based on image count
    if (prevBtn) prevBtn.style.display = currentImageList.length > 1 ? 'flex' : 'none';
    if (nextBtn) nextBtn.style.display = currentImageList.length > 1 ? 'flex' : 'none';
    
    // Disable/enable based on position
    if (prevBtn) {
        prevBtn.disabled = currentImageIndex === 0;
        prevBtn.style.opacity = currentImageIndex === 0 ? '0.3' : '1';
        prevBtn.style.cursor = currentImageIndex === 0 ? 'not-allowed' : 'pointer';
    }
    if (nextBtn) {
        nextBtn.disabled = currentImageIndex === currentImageList.length - 1;
        nextBtn.style.opacity = currentImageIndex === currentImageList.length - 1 ? '0.3' : '1';
        nextBtn.style.cursor = currentImageIndex === currentImageList.length - 1 ? 'not-allowed' : 'pointer';
    }
}

// Keyboard navigation for image modal
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('image-modal');
    const isOpen = modal && modal.style.display === 'block';
    
    if (!isOpen) return;
    
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        showPrevImage();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        showNextImage();
    } else if (e.key === 'Escape') {
        closeImageModal();
    }
});
