// Maintenance Tickets JavaScript

let selectedMaintenanceModalFiles = [];
let maintenanceNocClearedFlag = false; // Track if NOC was cleared
let currentMaintenanceId = null; // Track current maintenance ticket ID for edit functionality

document.addEventListener('DOMContentLoaded', function() {
    // Create Maintenance Ticket Modal
    const createMaintenanceBtn = document.getElementById('create-maintenance-btn');
    const createMaintenanceModal = document.getElementById('create-maintenance-modal');
    const closeCreateMaintenance = document.getElementById('close-create-maintenance');
    const cancelCreateMaintenance = document.getElementById('cancel-create-maintenance');

    if (createMaintenanceBtn) {
        createMaintenanceBtn.addEventListener('click', function() {
            createMaintenanceModal.style.display = 'block';
            document.body.classList.add('modal-open');
        });
    }

    if (closeCreateMaintenance) {
        closeCreateMaintenance.addEventListener('click', function() {
            createMaintenanceModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        });
    }

    if (cancelCreateMaintenance) {
        cancelCreateMaintenance.addEventListener('click', function() {
            createMaintenanceModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        });
    }
    
    // Service Type change listener for fiber core field
    const serviceTypeSelect = document.getElementById('modal-service-type');
    if (serviceTypeSelect) {
        serviceTypeSelect.addEventListener('change', function() {
            toggleFiberCoreField(this.value);
        });
    }

    // Assign Maintenance Modal
    const assignMaintenanceModal = document.getElementById('assign-maintenance-modal');
    const closeAssignMaintenance = document.getElementById('close-assign-maintenance');
    const cancelAssignMaintenance = document.getElementById('cancel-assign-maintenance');

    if (closeAssignMaintenance) {
        closeAssignMaintenance.addEventListener('click', function() {
            assignMaintenanceModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        });
    }

    if (cancelAssignMaintenance) {
        cancelAssignMaintenance.addEventListener('click', function() {
            assignMaintenanceModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        });
    }

    // View Maintenance Modal
    const viewMaintenanceModal = document.getElementById('view-maintenance-modal');
    const closeViewMaintenance = document.getElementById('close-view-maintenance');

    if (closeViewMaintenance) {
        closeViewMaintenance.addEventListener('click', function() {
            viewMaintenanceModal.style.display = 'none';
            document.body.style.overflow = '';
            if (maintenanceNocClearedFlag) {
                maintenanceNocClearedFlag = false;
                if (typeof triggerMaintenanceRefresh === 'function') triggerMaintenanceRefresh();
            }
        });
    }

    // Modal image upload - NOTE: Now handled by tickets.php to avoid duplicates
    // The upload functionality has been moved to tickets_maintenance.js to centralize handling
    const modalUploadForm = document.getElementById('modal-upload-form');

    if (modalUploadForm) {
        modalUploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            uploadModalImages();
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === createMaintenanceModal) {
            createMaintenanceModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        if (event.target === assignMaintenanceModal) {
            assignMaintenanceModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        if (event.target === viewMaintenanceModal) {
            viewMaintenanceModal.style.display = 'none';
            document.body.style.overflow = '';
            if (maintenanceNocClearedFlag) {
                maintenanceNocClearedFlag = false;
                if (typeof triggerMaintenanceRefresh === 'function') triggerMaintenanceRefresh();
            }
        }
    });
});

// Open Assign Maintenance Modal
function openAssignMaintenanceModal(ticketId, clientName) {
    document.getElementById('assign_maintenance_id').value = ticketId;
    document.getElementById('assign_maintenance_name').textContent = 'Assigning maintenance ticket for: ' + clientName;
    document.getElementById('assign-maintenance-modal').style.display = 'block';
    document.body.classList.add('modal-open');
}

// Update Maintenance Status
function updateMaintenanceStatus(ticketId, status) {
    Dialog.confirm('Are you sure you want to change the status to "' + status + '"?', { type: 'confirm' }).then(ok => {
        if (!ok) {
            if (typeof triggerMaintenanceRefresh === 'function') triggerMaintenanceRefresh();
            return;
        }
        const formData = new FormData();
        formData.append('ticket_id', ticketId);
        formData.append('status', status);

        fetch('update_maintenance_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modal = document.getElementById('view-maintenance-modal');
                if (modal && modal.style.display === 'block') {
                    viewMaintenanceDetails(ticketId);
                } else {
                    if (typeof triggerMaintenanceRefresh === 'function') triggerMaintenanceRefresh();
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

// View Maintenance Details
function viewMaintenanceDetails(ticketId) {
    // Fetch maintenance data and open modal
    fetch('get_maintenance_data.php?id=' + ticketId)
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
                    openViewMaintenanceModal(data.ticket, data.images);
                } else {
                    Dialog.error('Error loading maintenance data: ' + data.message);
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw response:', text);
                Dialog.error('Invalid response from server. Check console for details.');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            Dialog.error('An error occurred while loading maintenance data: ' + error.message);
        });
}

// Open View Maintenance Modal
function openViewMaintenanceModal(ticket, images) {
    try {
        // Populate ticket information with safety checks
        const ticketNumberEl = document.getElementById('view-maintenance-ticket-number');
        if (ticketNumberEl) ticketNumberEl.textContent = ticket.ticket_number || 'N/A';
        
        // Status badge
        const statusSpan = document.getElementById('view-maintenance-status');
        if (statusSpan && ticket.status) {
            statusSpan.className = 'status-badge status-' + ticket.status.toLowerCase().replace(' ', '-');
            statusSpan.textContent = ticket.status;
        }
        
        // Priority badge
        const prioritySpan = document.getElementById('view-maintenance-priority');
        if (prioritySpan && ticket.priority) {
            let priorityClass = '';
            if (ticket.priority === 'Urgent') priorityClass = 'priority-urgent';
            else if (ticket.priority === 'Normal') priorityClass = 'priority-normal';
            else priorityClass = 'priority-low';
            prioritySpan.className = 'priority-badge ' + priorityClass;
            prioritySpan.textContent = ticket.priority;
        }
        
        const issueTypeEl = document.getElementById('view-issue-type');
        if (issueTypeEl) issueTypeEl.textContent = ticket.issue_type || 'N/A';
    
        // Format date - with error handling and DOM ready check
        const createdEl = document.getElementById('view-maintenance-created');
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
        } else {
            console.error('view-maintenance-created element not found in DOM');
        }
    
    const assignedEl = document.getElementById('view-maintenance-assigned');
    if (assignedEl) assignedEl.textContent = ticket.assigned_name || 'Unassigned';
    
    // Client information with safety checks
    const clientNameEl = document.getElementById('view-maintenance-client-name');
    if (clientNameEl) clientNameEl.textContent = ticket.client_name || 'N/A';
    
    const contactEl = document.getElementById('view-maintenance-contact');
    if (contactEl) contactEl.textContent = ticket.contact_number || 'N/A';
    
    const emailEl = document.getElementById('view-email');
    if (emailEl) emailEl.textContent = ticket.email || 'N/A';
    
    const accountEl = document.getElementById('view-account');
    if (accountEl) accountEl.textContent = ticket.account_number || 'N/A';
    
    const addressEl = document.getElementById('view-maintenance-address');
    if (addressEl) addressEl.textContent = ticket.address || 'N/A';
    
    // Description with safety check
    const descriptionEl = document.getElementById('view-maintenance-description');
    if (descriptionEl) {
        descriptionEl.innerHTML = (ticket.description || 'No description').replace(/\n/g, '<br>');
    }
    
    // Technician notes with safety checks
    const techNotesSection = document.getElementById('technician-notes-section');
    const techNotesEl = document.getElementById('view-technician-notes');
    if (techNotesSection && techNotesEl) {
        if (ticket.technician_notes) {
            techNotesSection.style.display = 'block';
            techNotesEl.innerHTML = ticket.technician_notes.replace(/\n/g, '<br>');
        } else {
            techNotesSection.style.display = 'none';
        }
    }
    
    // Customer feedback with safety checks
    const feedbackSection = document.getElementById('customer-feedback-section');
    const feedbackEl = document.getElementById('view-customer-feedback');
    const ratingSection = document.getElementById('view-rating-section');
    const ratingStars = document.getElementById('view-rating-stars');
    
    if (feedbackSection && feedbackEl) {
        if (ticket.customer_feedback) {
            feedbackSection.style.display = 'block';
            feedbackEl.innerHTML = ticket.customer_feedback.replace(/\n/g, '<br>');
            
            if (ticket.rating && ratingSection && ratingStars) {
                ratingSection.style.display = 'block';
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    stars += '<span class="star ' + (i <= ticket.rating ? 'filled' : '') + '">★</span>';
                }
                ratingStars.innerHTML = stars;
            } else if (ratingSection) {
                ratingSection.style.display = 'none';
            }
        } else {
            feedbackSection.style.display = 'none';
        }
    }
    
    // Set maintenance ID for image upload with safety check
    const modalMaintenanceId = document.getElementById('modal-maintenance-id');
    if (modalMaintenanceId) modalMaintenanceId.value = ticket.id || '';
    
    // Populate technical details
    if (typeof populateTechnicalDetails === 'function') {
        populateTechnicalDetails(ticket);
    }
    
    // Display images
    if (typeof displayModalImages === 'function') {
        displayModalImages(images || []);
    }

    // Reset image upload state so previous selections don't carry over
    selectedMaintenanceModalFiles = [];
    const previewContainer = document.getElementById('modal-preview-container');
    if (previewContainer) previewContainer.innerHTML = '';
    const uploadForm = document.getElementById('modal-upload-form');
    if (uploadForm) uploadForm.reset();
    const uploadBtn = document.getElementById('modal-upload-btn');
    if (uploadBtn) uploadBtn.style.display = 'none';
    
    // Set current maintenance ID for edit functionality
    currentMaintenanceId = ticket.id || null;
    
    // Show modal with safety check
    const modal = document.getElementById('view-maintenance-modal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    } else {
        console.error('Maintenance modal element not found');
        Dialog.error('Modal element not found. Please refresh the page.');
    }
    
    } catch (error) {
        console.error('Error in openViewMaintenanceModal:', error);
        Dialog.error('An error occurred while opening the maintenance modal. Please try again.');
    }
}

// Display images in modal
function displayModalImages(images) {
    const gallery = document.getElementById('modal-images-gallery');
    
    if (images.length === 0) {
        gallery.innerHTML = '<p class="no-images">No images uploaded yet</p>';
        return;
    }
    
    gallery.innerHTML = '';
    images.forEach(image => {
        const imageDate = new Date(image.uploaded_at);
        const formattedDate = imageDate.toLocaleString('en-US', {
            timeZone: 'Asia/Manila',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        
        // Normalize image path - remove /ADMIN/ prefix if present
        let imagePath = image.image_path;
        
        // Remove /ADMIN/ prefix if present
        if (imagePath.startsWith('/ADMIN/')) {
            imagePath = imagePath.substring(7);
        }
        
        // Prepend base URL for absolute /IMAGES/ paths
        if (imagePath.startsWith('/IMAGES/')) {
            imagePath = (window.BASE_URL || '') + imagePath;
        }
        
        const imageItem = document.createElement('div');
        imageItem.className = 'image-item';
        imageItem.setAttribute('data-image-id', image.id);

        const img = document.createElement('img');
        img.src = imagePath;
        img.alt = 'Maintenance Image';
        img.style.cursor = 'pointer';
        img.addEventListener('click', (function(path) {
            return function() { openImageModal(path); };
        })(imagePath));

        const info = document.createElement('div');
        info.className = 'image-info';
        info.innerHTML = `<span class="image-date">${formattedDate}</span>`;

        const delBtn = document.createElement('button');
        delBtn.className = 'btn-delete-image';
        delBtn.textContent = '×';
        delBtn.addEventListener('click', (function(id) {
            return function() { deleteModalImage(id); };
        })(image.id));
        info.appendChild(delBtn);

        imageItem.appendChild(img);
        imageItem.appendChild(info);
        gallery.appendChild(imageItem);
    });
}

// Display image previews in modal
function displayModalPreviews(files) {
    const previewContainer = document.getElementById('modal-preview-container');
    previewContainer.innerHTML = '';

    // Snapshot the generation so stale async callbacks don't append after a new selection
    const generation = Date.now() + Math.random();
    previewContainer.dataset.generation = generation;

    files.forEach((file, index) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Discard if a newer selection has already replaced this one
            if (previewContainer.dataset.generation != generation) return;
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="preview-remove" onclick="removeModalPreview(${index})">×</button>
            `;
            previewContainer.appendChild(previewItem);
        };
        
        reader.readAsDataURL(file);
    });
}

// Remove preview in modal
function removeModalPreview(index) {
    selectedMaintenanceModalFiles.splice(index, 1);
    
    const dataTransfer = new DataTransfer();
    selectedMaintenanceModalFiles.forEach(file => dataTransfer.items.add(file));
    document.getElementById('modal-image-upload').files = dataTransfer.files;
    
    displayModalPreviews(selectedMaintenanceModalFiles);
    
    if (selectedMaintenanceModalFiles.length === 0) {
        document.getElementById('modal-upload-btn').style.display = 'none';
    }
}

// Upload images from modal
function uploadModalImages() {
    const formData = new FormData(document.getElementById('modal-upload-form'));
    const uploadBtn = document.getElementById('modal-upload-btn');
    
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';
    
    fetch('upload_maintenance_images.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Dialog.toast('Images uploaded successfully!', 'success');
            // Reload the modal data
            const maintenanceId = document.getElementById('modal-maintenance-id').value;
            viewMaintenanceDetails(maintenanceId);
            // Reset form
            document.getElementById('modal-upload-form').reset();
            document.getElementById('modal-preview-container').innerHTML = '';
            selectedMaintenanceModalFiles = [];
        } else {
            Dialog.error('Error uploading images: ' + data.message);
        }
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload Images';
    })
    .catch(error => {
        console.error('Error:', error);
        Dialog.error('An error occurred while uploading images.');
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload Images';
    });
}

// Delete image from modal
function deleteModalImage(imageId) {
    Dialog.confirm('Are you sure you want to delete this image?', { type: 'danger', okText: 'Delete' }).then(ok => {
        if (!ok) return;
    
    const formData = new FormData();
    formData.append('image_id', imageId);
    
    fetch('delete_maintenance_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Dialog.toast('Image deleted successfully!', 'success');
            const maintenanceId = document.getElementById('modal-maintenance-id').value;
            viewMaintenanceDetails(maintenanceId);
        } else {
            Dialog.error('Error deleting image: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Dialog.error('An error occurred while deleting the image.');
    });
    });
}

// Open image modal
function openImageModal(imagePath) {
    const modal = document.getElementById('image-modal');
    const modalImage = document.getElementById('modal-image');
    
    modal.style.display = 'block';
    modalImage.src = imagePath;
}

// Close image modal
function closeImageModal() {
    document.getElementById('image-modal').style.display = 'none';
}

// Delete Maintenance Ticket
function deleteMaintenanceTicket(ticketId) {
    Dialog.confirm('Are you sure you want to delete this maintenance ticket? This action cannot be undone.', { type: 'danger', okText: 'Delete' }).then(ok => {
        if (!ok) return;
        const formData = new FormData();
        formData.append('ticket_id', ticketId);

        fetch('delete_maintenance_ticket.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Dialog.toast('Maintenance ticket deleted successfully!', 'success');
                if (typeof triggerMaintenanceRefresh === 'function') triggerMaintenanceRefresh();
            } else {
                Dialog.error('Error deleting ticket: ' + data.message);
            }
        })
        .catch(error => {
            Dialog.error('An error occurred. Please try again.');
            console.error('Error:', error);
        });
    });
}


// Populate technical details in modal
function populateTechnicalDetails(ticket) {
    document.getElementById('modal-tech-maintenance-id').value = ticket.id;
    document.getElementById('modal-service-type').value = ticket.service_type || '';
    document.getElementById('modal-fiber-core').value = ticket.fiber_core_count || '';
    document.getElementById('modal-optical').value = ticket.optical_reading || '';
    document.getElementById('modal-speed').value = ticket.speed_test || '';
    document.getElementById('modal-ping').value = ticket.ping || '';
    document.getElementById('modal-work-done').value = ticket.work_done || '';
    document.getElementById('modal-problem-cause').value = ticket.problem_cause || '';
    document.getElementById('status-input').value = ticket.comment || '';
    
    // Set accepts_member field
    const acceptsMemberField = document.getElementById('modal-accepts-member');
    if (acceptsMemberField) {
        acceptsMemberField.value = ticket.accepts_member || '';
        console.log('Accepts member value:', ticket.accepts_member);
    } else {
        console.warn('modal-accepts-member field not found');
    }
    
    // Show/hide fiber core dropdown based on service type
    toggleFiberCoreField(ticket.service_type);
    
    // Update NOC clearance display
    updateModalNOCClearance(ticket.noc_cleared_at, ticket.noc_cleared_by_name);
}

// Toggle fiber core field visibility
function toggleFiberCoreField(serviceType) {
    const fiberCoreGroup = document.getElementById('fiber-core-group');
    if (serviceType === 'Distribution Line' || serviceType === 'Main Line' || serviceType === 'Transport Line') {
        fiberCoreGroup.style.display = 'block';
    } else {
        fiberCoreGroup.style.display = 'none';
        document.getElementById('modal-fiber-core').value = ''; // Clear value when hidden
    }
}

// Update NOC clearance display in modal
function updateModalNOCClearance(clearedAt, clearedBy) {
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

// Handle modal technical form submission
document.addEventListener('DOMContentLoaded', function() {
    const modalTechForm = document.getElementById('modal-technical-form');
    if (modalTechForm) {
        modalTechForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('maint-save-tech-btn');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Saving...';

            const formData = new FormData(this);

            fetch('update_maintenance_technical.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.textContent = '✓ Saved';
                    btn.classList.add('btn-save-tech-success');
                    const maintenanceId = document.getElementById('modal-tech-maintenance-id').value;
                    viewMaintenanceDetails(maintenanceId);
                } else {
                    btn.textContent = '✗ Error';
                    btn.classList.add('btn-save-tech-error');
                }
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('btn-save-tech-success', 'btn-save-tech-error');
                    btn.disabled = false;
                }, 2000);
            })
            .catch(error => {
                console.error('Error:', error);
                btn.textContent = '✗ Error';
                btn.classList.add('btn-save-tech-error');
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('btn-save-tech-error');
                    btn.disabled = false;
                }, 2000);
            });
        });
    }

    // Handle assign maintenance form submission
    const assignMaintenanceForm = document.getElementById('assign-maintenance-form');
    if (assignMaintenanceForm) {
        assignMaintenanceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('assign_maintenance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Dialog.toast('Maintenance ticket assigned successfully!', 'success');
                    document.getElementById('assign-maintenance-modal').style.display = 'none';
                    document.body.classList.remove('modal-open');
                    if (typeof triggerMaintenanceRefresh === 'function') triggerMaintenanceRefresh();
                } else {
                    Dialog.error('Error assigning ticket: ' + data.message);
                }
            })
            .catch(error => {
                Dialog.error('An error occurred. Please try again.');
                console.error('Error:', error);
            });
        });
    }
});

// Clear Maintenance NOC from modal
function clearModalMaintenanceNOC(btn) {
    const maintenanceId = document.getElementById('modal-tech-maintenance-id').value;
    
    const originalText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Clearing...'; }
    
    const formData = new FormData();
    formData.append('maintenance_id', maintenanceId);
    
    fetch('clear_maintenance_noc_minimal.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            maintenanceNocClearedFlag = true;
            const now = new Date().toISOString();
            const userName = document.querySelector('.user-name')?.textContent?.trim() || 'You';
            if (typeof updateModalNOCClearance === 'function') {
                updateModalNOCClearance(now, userName);
            }
            if (typeof triggerMaintenanceRefresh === 'function') triggerMaintenanceRefresh();
            // Force-refresh the unified tickets.php table immediately (bypasses modal guard)
            if (typeof triggerUnifiedRefresh === 'function') triggerUnifiedRefresh(true);
            if (btn) {
                btn.textContent = '✓ Cleared';
                btn.classList.add('btn-save-tech-success');
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('btn-save-tech-success');
                    btn.disabled = false;
                }, 2000);
            }
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
    })
    .catch(error => {
        console.error('Error:', error);
        if (btn) { btn.textContent = originalText; btn.disabled = false; }
    });
}


// Status tabs functionality for maintenance tickets — SPA client-side
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.status-tabs .tab-btn');

    // Set initial active tab from URL param
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab') || 'Pending';
    tabButtons.forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-status') === tabParam));

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            tabButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const activeStatus = this.getAttribute('data-status');
            const searchVal = (document.getElementById('search-input') || {}).value || '';
            const activeCity = (document.querySelector('.city-btn.active') || {}).getAttribute('data-city') || '';
            const tbody = document.querySelector('.tickets-table tbody');
            if (tbody) {
                tbody.querySelectorAll('tr[data-status]').forEach(row => {
                    const matchStatus = activeStatus === 'all' || row.getAttribute('data-status') === activeStatus;
                    const matchCity = !activeCity || (row.querySelector('td:nth-child(6)') || {}).textContent?.toLowerCase().includes(activeCity.toLowerCase());
                    const matchSearch = !searchVal.trim() || row.textContent.toLowerCase().includes(searchVal.toLowerCase());
                    row.style.display = (matchStatus && matchCity && matchSearch) ? '' : 'none';
                });
                if (typeof updateTabCounts === 'function') updateTabCounts();
            }
        });
    });

    // Check if there's a view parameter in URL to auto-open modal
    const viewId = urlParams.get('view');
    if (viewId) {
        viewMaintenanceDetails(parseInt(viewId));
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});


// Scroll to a section inside the modal's scrollable container
function scrollToModalSection(sectionId) {
    const container = document.getElementById('view-maintenance-content');
    const section = document.getElementById(sectionId);
    if (!container || !section) return;
    const containerRect = container.getBoundingClientRect();
    const sectionRect = section.getBoundingClientRect();
    const scrollTarget = container.scrollTop + (sectionRect.top - containerRect.top) - 10;
    container.scrollTo({ top: scrollTarget, behavior: 'smooth' });
    section.style.transition = 'background-color 0.5s ease';
    section.style.backgroundColor = '#fef3c7';
    setTimeout(() => { section.style.backgroundColor = ''; }, 1500);
}

// Edit maintenance from view modal
function editMaintenanceFromView() {
    // Keep view modal open in background, just open edit modal on top
    // This allows user to return to view modal after canceling edit
    
    // Open edit modal using the unified editTicket function if available
    if (currentMaintenanceId) {
        // Check if editTicket is defined in the page (tickets_maintenance.php)
        if (typeof editTicket === 'function') {
            editTicket(currentMaintenanceId, 'maintenance');
        } else {
            console.error('editTicket function not found');
        }
    } else {
        console.error('No current maintenance ID set');
    }
}

// Scroll to Technical Details Section in Modal
function scrollToTechnicalDetailsMaintenance() {
    scrollToModalSection('modal-technical-details-section');
}

// Scroll to Images Section in Modal
function scrollToImagesSection() {
    scrollToModalSection('modal-images-section');
}

document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    var lightboxIds = ['image-modal'];
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

    var origOpen = window.openImageModal;
    window.openImageModal = function(src) {
        origOpen(src);
        var img = document.getElementById('modal-image');
        if (img) resetZoom(img);
    };
    var origClose = window.closeImageModal;
    window.closeImageModal = function() {
        var img = document.getElementById('modal-image');
        if (img) resetZoom(img);
        origClose();
    };
})();

// Image navigation variables
var currentImageIndex = 0;
var currentImageList = [];

// Open image modal with navigation
function openImageModal(imagePath) {
    const modal = document.getElementById('image-modal');
    const modalImage = document.getElementById('modal-image');
    if (!modal || !modalImage) return;
    
    // Get all images from the gallery
    const gallery = document.getElementById('modal-images-gallery');
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
