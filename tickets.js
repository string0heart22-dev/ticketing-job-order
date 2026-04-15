// Tickets Page JavaScript

// Wait until service area data is loaded before running callback
function waitForServiceAreas(callback, attempts) {
    attempts = attempts || 0;
    if (window.ticket_serviceAreas && window.ticket_serviceAreas.length > 0) {
        callback();
    } else if (attempts < 40) {
        setTimeout(function() { waitForServiceAreas(callback, attempts + 1); }, 100);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Create Ticket Modal
    const createTicketBtn = document.getElementById('create-ticket-btn');
    const createTicketModal = document.getElementById('create-ticket-modal');
    const closeCreateTicket = document.getElementById('close-create-ticket');
    const cancelCreateTicket = document.getElementById('cancel-create-ticket');

    if (createTicketBtn) {
        createTicketBtn.addEventListener('click', function() {
            createTicketModal.style.display = 'block';
        });
    }

    if (closeCreateTicket) {
        closeCreateTicket.addEventListener('click', function() {
            createTicketModal.style.display = 'none';
        });
    }

    if (cancelCreateTicket) {
        cancelCreateTicket.addEventListener('click', function() {
            createTicketModal.style.display = 'none';
        });
    }

    // Assign Modal
    const assignModal = document.getElementById('assign-modal');
    const closeAssign = document.getElementById('close-assign');
    const cancelAssign = document.getElementById('cancel-assign');

    if (closeAssign) {
        closeAssign.addEventListener('click', function() {
            assignModal.style.display = 'none';
        });
    }

    if (cancelAssign) {
        cancelAssign.addEventListener('click', function() {
            assignModal.style.display = 'none';
        });
    }

    // Status Comment Modal
    const statusCommentModal = document.getElementById('status-comment-modal');
    const closeStatusComment = document.getElementById('close-status-comment');
    const cancelStatusComment = document.getElementById('cancel-status-comment');
    const statusCommentForm = document.getElementById('status-comment-form');

    if (closeStatusComment) {
        closeStatusComment.addEventListener('click', function() {
            statusCommentModal.style.display = 'none';
            // Reset dropdown to original status
            const originalStatus = statusCommentModal.dataset.originalStatus;
            const ticketId = document.getElementById('comment_ticket_id').value;
            const selectElement = document.querySelector(`select[data-id="${ticketId}"]`);
            if (selectElement && originalStatus) {
                selectElement.value = originalStatus;
            }
        });
    }

    if (cancelStatusComment) {
        cancelStatusComment.addEventListener('click', function() {
            statusCommentModal.style.display = 'none';
            // Reset dropdown to original status
            const originalStatus = statusCommentModal.dataset.originalStatus;
            const ticketId = document.getElementById('comment_ticket_id').value;
            const selectElement = document.querySelector(`select[data-id="${ticketId}"]`);
            if (selectElement && originalStatus) {
                selectElement.value = originalStatus;
            }
        });
    }

    if (statusCommentForm) {
        statusCommentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const ticketId = document.getElementById('comment_ticket_id').value;
            const status = document.getElementById('comment_status').value;
            const comment = document.getElementById('status_comment').value.trim();
            
            if (!comment) {
                Dialog.error('Please enter a comment');
                return;
            }
            
            statusCommentModal.style.display = 'none';
            submitStatusUpdate(ticketId, status, comment);
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === createTicketModal) {
            createTicketModal.style.display = 'none';
        }
        if (event.target === assignModal) {
            assignModal.style.display = 'none';
        }
        if (event.target === statusCommentModal) {
            statusCommentModal.style.display = 'none';
            // Reset dropdown to original status
            const originalStatus = statusCommentModal.dataset.originalStatus;
            const ticketId = document.getElementById('comment_ticket_id').value;
            const selectElement = document.querySelector(`select[data-id="${ticketId}"]`);
            if (selectElement && originalStatus) {
                selectElement.value = originalStatus;
            }
        }
    });
});

// Open Assign Modal
function openAssignModal(ticketId, clientName) {
    document.getElementById('assign_ticket_id').value = ticketId;
    document.getElementById('assign_ticket_name').textContent = 'Assigning ticket for: ' + clientName;
    document.getElementById('assign-modal').style.display = 'block';
}

// Function to render comment overlays on rows
function renderCommentOverlays() {
    // Find all rows with comment overlay class
    document.querySelectorAll('.ticket-row-comment-overlay').forEach(row => {
        // Check if overlay already exists
        let overlay = row.querySelector('.ticket-comment-overlay');
        
        // Get comment from first data-comment cell
        const firstCell = row.querySelector('td[data-comment]');
        const comment = firstCell && firstCell.dataset.comment ? firstCell.dataset.comment : '';
        
        if (!comment) {
            // No comment, remove overlay if exists
            if (overlay) overlay.remove();
            return;
        }
        
        // Create or update overlay
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'ticket-comment-overlay';
            row.style.position = 'relative';
            row.appendChild(overlay);
        }
        
        overlay.textContent = '⚠️ ' + comment;
    });
}

// Render overlays on page load
(function initCommentOverlays() {
    function render() {
        console.log('Rendering comment overlays...');
        renderCommentOverlays();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render);
    } else {
        // DOM already loaded, render immediately
        render();
    }
    
    // Re-render periodically to ensure persistence
    setInterval(renderCommentOverlays, 1000);
})();

// Also hook into SPA refresh
document.addEventListener('DOMContentLoaded', function() {
    const originalTriggerInstallationRefresh = window.triggerInstallationRefresh;
    if (originalTriggerInstallationRefresh) {
        window.triggerInstallationRefresh = function() {
            originalTriggerInstallationRefresh();
            setTimeout(renderCommentOverlays, 100);
            setTimeout(renderCommentOverlays, 500);
        };
    }
    
    // Watch for DOM changes
    const observer = new MutationObserver(function(mutations) {
        let shouldRender = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0) {
                shouldRender = true;
            }
        });
        if (shouldRender) {
            setTimeout(renderCommentOverlays, 50);
        }
    });
    
    const tableBody = document.querySelector('.tickets-table tbody');
    if (tableBody) {
        observer.observe(tableBody, { childList: true, subtree: true });
    }
});

// Update Status
function updateStatus(ticketId, status) {
    // Check if status requires comment (Cancelled or Negative)
    const requiresComment = status === 'Cancelled' || status === 'Negative';
    
    if (requiresComment) {
        // Show comment modal instead of confirmation
        showStatusCommentModal(ticketId, status);
        return;
    }
    
    // For other statuses, use normal confirmation
    Dialog.confirm('Are you sure you want to change the status to "' + status + '"?', { type: 'confirm' }).then(ok => {
        if (!ok) {
            // User cancelled — reset the dropdown value via re-fetch
            const selectElement = document.querySelector(`select[data-id="${ticketId}"]`);
            if (selectElement) {
                fetch('get_installation_tickets.php')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const t = data.tickets.find(t => t.id == ticketId);
                            if (t) selectElement.value = t.status;
                        }
                    }).catch(() => {});
            }
            return;
        }
        
        submitStatusUpdate(ticketId, status, null);
    });
}

// Show Status Comment Modal
function showStatusCommentModal(ticketId, status) {
    const modal = document.getElementById('status-comment-modal');
    const ticketIdInput = document.getElementById('comment_ticket_id');
    const statusInput = document.getElementById('comment_status');
    const commentTextarea = document.getElementById('status_comment');
    const selectElement = document.querySelector(`select[data-id="${ticketId}"]`);
    
    if (!modal) {
        // Modal not found, fall back to regular update
        submitStatusUpdate(ticketId, status, null);
        return;
    }
    
    // Set values
    ticketIdInput.value = ticketId;
    statusInput.value = status;
    commentTextarea.value = '';
    
    // Store original status for cancel
    modal.dataset.originalStatus = selectElement ? selectElement.value : '';
    
    // Show modal
    modal.style.display = 'block';
}

// Submit status update with optional comment
function submitStatusUpdate(ticketId, status, comment) {
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('status', status);
    if (comment) {
        formData.append('comment', comment);
    }

    fetch('update_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const modal = document.getElementById('view-installation-modal');
            if (modal && modal.style.display === 'block') {
                viewInstallation(ticketId);
            } else {
                if (typeof triggerInstallationRefresh === 'function') triggerInstallationRefresh();
            }
        } else {
            Dialog.error('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        Dialog.error('An error occurred. Please try again.');
        console.error('Error:', error);
    });
}

// Edit Ticket
function editTicket(ticketId) {
    console.log('editTicket called with ID:', ticketId);
    
    // Set current installation ID for view modal reopening
    currentInstallationId = ticketId;
    
    // Fetch ticket data and open modal
    fetch('get_ticket_data.php?id=' + ticketId)
        .then(response => {
            console.log('Response received:', response.status);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                openEditTicketModal(data.ticket);
            } else {
                Dialog.error('Error loading ticket data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            Dialog.error('An error occurred while loading ticket data: ' + error.message);
        });
}

// Open Edit Ticket Modal
function openEditTicketModal(ticket) {
    const modal = document.getElementById('edit-ticket-modal');
    if (!modal) {
        Dialog.error('Edit modal not found in page');
        return;
    }
    
    // Set all form values
    document.getElementById('edit_ticket_id').value = ticket.id;
    document.getElementById('edit_client_name').value = ticket.client_name;
    document.getElementById('edit_contact_number').value = ticket.contact_number || '';
    document.getElementById('edit_email').value = ticket.email || '';
    document.getElementById('edit_account_number').value = ticket.account_number || '';
    document.getElementById('edit_installation_date').value = ticket.installation_date || '';
    document.getElementById('edit_nap_assignment').value = ticket.nap_assignment || '';
    document.getElementById('edit_connection_type').value = ticket.connection_type;
    document.getElementById('edit_service_type').value = ticket.service_type;
    document.getElementById('edit_prepaid_amount').value = ticket.prepaid_amount || '';
    document.getElementById('edit_priority').value = ticket.priority || 'Normal';

    // Wait for service area data to be ready, then populate address
    if (ticket.address) {
        waitForServiceAreas(function() {
            parseAndPopulateAddress(ticket.address, 'edit_');
        });
    }
    
    // Set plan and contract duration
    if (ticket.plan_id) {
        setTimeout(() => {
            const planSelect = document.getElementById('edit_plan');
            if (planSelect) {
                planSelect.value = ticket.plan_id;
                planSelect.dispatchEvent(new Event('change'));
                setTimeout(() => {
                    const durationSelect = document.getElementById('edit_contract_duration');
                    if (durationSelect && ticket.contract_duration) {
                        durationSelect.value = ticket.contract_duration;
                    }
                }, 100);
            }
        }, 200);
    }

    // Show modal
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Parse address string and populate cascading dropdowns
function parseAndPopulateAddress(addressString, prefix) {
    if (!addressString) return;

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };

    const sAreas    = window.ticket_serviceAreas || [];
    const sCities   = window.ticket_cities       || {};
    const sBarangay = window.ticket_barangays    || {};

    // Try to match address parts against known service area data
    const parts = addressString.split(',').map(p => p.trim()).filter(Boolean);

    let province = '', city = '', barangay = '', purok = '', zipCode = '';

    // Walk through known provinces/cities/barangays to find a match
    const allProvinces = Object.keys(sCities);
    for (const prov of allProvinces) {
        if (parts.includes(prov)) {
            province = prov;
            const provCities = sCities[prov] || [];
            for (const c of provCities) {
                if (parts.includes(c)) {
                    city = c;
                    const cityKey = prov + '|' + c;
                    const cityBarangays = sBarangay[cityKey] || [];
                    for (const b of cityBarangays) {
                        if (parts.includes(b)) {
                            barangay = b;
                            break;
                        }
                    }
                    break;
                }
            }
            break;
        }
    }

    // Remaining parts that aren't province/city/barangay are purok and possibly zip
    if (province || city || barangay) {
        const known = [province, city, barangay].filter(Boolean);
        const remaining = parts.filter(p => !known.includes(p));
        // A zip code is purely numeric (4-5 digits)
        const zipPart = remaining.find(p => /^\d{4,5}$/.test(p));
        if (zipPart) zipCode = zipPart;
        purok = remaining.filter(p => p !== zipPart).join(', ');
    } else {
        // Fallback: positional — "Purok, Barangay, City, Province, Zip"
        purok     = parts[0] || '';
        barangay  = parts[1] || '';
        city      = parts[2] || '';
        province  = parts[3] || '';
        zipCode   = parts[4] || '';
    }

    // Auto-fill zip from service areas if not in address string
    if (!zipCode && province && city && barangay) {
        const area = sAreas.find(a => a.province === province && a.city === city && a.barangay === barangay);
        if (area) zipCode = area.zip_code || '';
    }

    // Populate city dropdown options for the saved province
    const cityDropdown = document.getElementById(prefix + 'city_dropdown');
    if (cityDropdown && province) {
        cityDropdown.innerHTML = '';
        (sCities[province] || []).forEach(c => {
            const li = document.createElement('li');
            li.textContent = c;
            cityDropdown.appendChild(li);
        });
    }

    // Populate barangay dropdown options for the saved city
    const barangayDropdown = document.getElementById(prefix + 'barangay_dropdown');
    if (barangayDropdown && province && city) {
        barangayDropdown.innerHTML = '';
        const cityKey = province + '|' + city;
        (sBarangay[cityKey] || []).forEach(b => {
            const li = document.createElement('li');
            li.textContent = b;
            barangayDropdown.appendChild(li);
        });
    }

    // Set all input values
    set(prefix + 'province_input',   province);
    set(prefix + 'city_input',       city);
    set(prefix + 'barangay_input',   barangay);
    set(prefix + 'purok_zone_input', purok);
    set(prefix + 'zip_code',         zipCode);

    // Legacy select fallback
    set(prefix + 'province',   province);
    set(prefix + 'city',       city);
    set(prefix + 'barangay',   barangay);
    set(prefix + 'purok_zone', purok);
}

// Delete Ticket
function deleteTicket(ticketId) {
    Dialog.confirm('Are you sure you want to delete this ticket? This action cannot be undone.', { type: 'danger', okText: 'Delete' }).then(ok => {
        if (!ok) return;
        const formData = new FormData();
        formData.append('ticket_id', ticketId);

        fetch('delete_ticket.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Dialog.toast('Ticket deleted successfully!', 'success');
                const row = document.querySelector(`.tickets-table tbody tr[data-id="${ticketId}"]`);
                if (row) {
                    row.remove();
                } else if (typeof triggerInstallationRefresh === 'function') {
                    triggerInstallationRefresh();
                }
                if (typeof updateTabCounts === 'function') updateTabCounts();
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


// Edit Ticket Modal handlers
document.addEventListener('DOMContentLoaded', function() {
    const editTicketModal = document.getElementById('edit-ticket-modal');
    const closeEditTicket = document.getElementById('close-edit-ticket');
    const cancelEditTicket = document.getElementById('cancel-edit-ticket');
    const editTicketForm = document.getElementById('edit-ticket-form');

    if (closeEditTicket) {
        closeEditTicket.addEventListener('click', function() {
            editTicketModal.style.display = 'none';
            document.body.style.overflow = '';
            // Reopen view modal
            if (currentInstallationId && typeof viewInstallation === 'function') {
                viewInstallation(currentInstallationId);
            }
        });
    }

    if (cancelEditTicket) {
        cancelEditTicket.addEventListener('click', function() {
            editTicketModal.style.display = 'none';
            document.body.style.overflow = '';
            // Reopen view modal
            if (currentInstallationId && typeof viewInstallation === 'function') {
                viewInstallation(currentInstallationId);
            }
        });
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === editTicketModal) {
            editTicketModal.style.display = 'none';
            document.body.style.overflow = '';
            // Reopen view modal
            if (currentInstallationId && typeof viewInstallation === 'function') {
                viewInstallation(currentInstallationId);
            }
        }
    });

    // Handle edit ticket form submission
    if (editTicketForm) {
        editTicketForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('update_ticket.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Dialog.toast('Ticket updated successfully!', 'success');
                    editTicketModal.style.display = 'none';
                    document.body.style.overflow = '';
                    if (typeof triggerInstallationRefresh === 'function') triggerInstallationRefresh();
                    // Reopen view modal after successful update
                    if (currentInstallationId && typeof viewInstallation === 'function') {
                        viewInstallation(currentInstallationId);
                    }
                } else {
                    Dialog.error('Error updating ticket: ' + data.message);
                }
            })
            .catch(error => {
                Dialog.error('An error occurred. Please try again.');
                console.error('Error:', error);
            });
        });
    }
});
