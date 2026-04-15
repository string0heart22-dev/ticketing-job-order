// Status Tab Filtering for Tickets Pages
// Handles filtering tickets by status (Pending, In Progress, Completed, etc.)

document.addEventListener('DOMContentLoaded', function() {
    initializeStatusFiltering();
    updateTabCounts();
    
    // Check URL for tab parameter and set active tab
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab) {
        setActiveTab(activeTab);
    }
});

function initializeStatusFiltering() {
    // Get all status tab buttons
    const tabButtons = document.querySelectorAll('.status-tabs .tab-btn');
    
    // Add click event listeners to tab buttons
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const status = this.getAttribute('data-status');
            
            // Update active tab
            const allTabs = document.querySelectorAll('.status-tabs .tab-btn');
            allTabs.forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');
            
            // Filter tickets by status
            filterTicketsByStatus(status);
            
            // Update date column header based on selected status
            updateDateColumnHeader();
            
            // Update URL without reloading
            const url = new URL(window.location);
            url.searchParams.set('tab', status);
            window.history.pushState({}, '', url);
        });
    });
}

function filterTicketsByStatus(status) {
    const tableRows = document.querySelectorAll('.tickets-table tbody tr[data-status]');
    let visibleCount = 0;
    
    tableRows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        
        if (status === 'all' || rowStatus === status) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide "no data" message
    updateNoDataMessage(visibleCount, status);
    
    // Update tab counts
    updateTabCounts();
}

function updateNoDataMessage(visibleCount, status) {
    const tbody = document.querySelector('.tickets-table tbody');
    if (!tbody) return;
    
    // Remove existing no-data rows
    const existingNoData = tbody.querySelectorAll('.no-data-row');
    existingNoData.forEach(row => row.remove());
    
    // Add no-data message if no tickets visible
    if (visibleCount === 0) {
        const noDataRow = document.createElement('tr');
        noDataRow.className = 'no-data-row';
        
        // Count columns for proper colspan
        const headerCells = document.querySelectorAll('.tickets-table thead th');
        const colspan = headerCells.length || 8;
        
        const statusText = status === 'all' ? 'tickets' : `${status.toLowerCase()} tickets`;
        noDataRow.innerHTML = `<td colspan="${colspan}" class="no-data">No ${statusText} found</td>`;
        tbody.appendChild(noDataRow);
    }
}

function updateTabCounts() {
    const tabButtons = document.querySelectorAll('.status-tabs .tab-btn[data-status]');
    
    tabButtons.forEach(tab => {
        const status = tab.getAttribute('data-status');
        let count = 0;
        
        if (status === 'all') {
            count = document.querySelectorAll('.tickets-table tbody tr[data-status]').length;
        } else {
            count = document.querySelectorAll(`.tickets-table tbody tr[data-status="${status}"]`).length;
        }
        
        // Update or create badge
        let badge = tab.querySelector('.status-badge');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'status-badge';
            tab.appendChild(badge);
        }
        badge.textContent = count;
        
        // Hide badge if count is 0
        if (count === 0) {
            badge.style.display = 'none';
        } else {
            badge.style.display = 'inline';
        }
    });
}

function setActiveTab(status) {
    const tabButtons = document.querySelectorAll('.status-tabs .tab-btn');
    
    tabButtons.forEach(tab => {
        const tabStatus = tab.getAttribute('data-status');
        if (tabStatus === status) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });
    
    // Filter tickets immediately
    filterTicketsByStatus(status);
}

// Function to get current status filter
function getCurrentStatusFilter() {
    const activeTab = document.querySelector('.status-tabs .tab-btn.active');
    return activeTab ? activeTab.getAttribute('data-status') : 'all';
}

// Function to update date column header based on active tab
function updateDateColumnHeader() {
    const header = document.getElementById('date-column-header');
    if (!header) return;
    
    const activeTab = document.querySelector('.tab-btn.active');
    const status = activeTab ? activeTab.getAttribute('data-status') : 'all';
    
    const headerMap = {
        'Pending': 'Created',
        'In Progress': 'Started Time',
        'Installed': 'Installed Time',
        'Completed': 'Completed Time',
        'Closed': 'Closed Time',
        'Cancelled': 'Cancelled Time',
        'Negative': 'Negative Time',
        'On Hold': 'On Hold Time',
        'Reconnected': 'Reconnected Time',
        'all': 'Time Started / Time Finished'
    };
    
    header.textContent = headerMap[status] || 'Created';
}

// Export functions for use in other scripts
window.filterTicketsByStatus = filterTicketsByStatus;
window.updateTabCounts = updateTabCounts;
window.getCurrentStatusFilter = getCurrentStatusFilter;
window.updateDateColumnHeader = updateDateColumnHeader;

// Initialize header on page load
document.addEventListener('DOMContentLoaded', function() {
    updateDateColumnHeader();
});