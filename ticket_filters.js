// Ticket Filters - City Filter Buttons and Search functionality

let selectedCity = '';

document.addEventListener('DOMContentLoaded', function() {
    initializeFilters();
});

function initializeFilters() {
    const searchInput = document.getElementById('search-input');
    
    if (!searchInput) return;
    
    // Populate city buttons from service_areas database
    populateCityButtonsFromDatabase();
    
    // Add event listener for search
    searchInput.addEventListener('input', applyFilters);
}

async function populateCityButtonsFromDatabase() {
    const container = document.getElementById('city-buttons-container');
    if (!container) return;
    
    // Add click listener to the "All Cities" button
    const allCitiesBtn = container.querySelector('.city-btn[data-city=""]');
    if (allCitiesBtn) {
        allCitiesBtn.addEventListener('click', function() {
            selectCity('');
        });
    }
    
    try {
        const response = await fetch('get_cities_list.php');
        const data = await response.json();
        
        if (data.success && data.cities && data.cities.length > 0) {
            // Keep the "All Cities" button, add city buttons
            data.cities.forEach(city => {
                const button = document.createElement('button');
                button.className = 'city-btn';
                button.textContent = city;
                button.dataset.city = city;
                button.addEventListener('click', function() {
                    selectCity(city);
                });
                container.appendChild(button);
            });
        } else {
            // Fallback: extract from table if database query fails
            populateCityButtonsFromTable();
        }
    } catch (error) {
        console.error('Error loading cities:', error);
        // Fallback: extract from table
        populateCityButtonsFromTable();
    }
}

function populateCityButtonsFromTable() {
    const container = document.getElementById('city-buttons-container');
    if (!container) return;
    
    // Add click listener to the "All Cities" button
    const allCitiesBtn = container.querySelector('.city-btn[data-city=""]');
    if (allCitiesBtn) {
        allCitiesBtn.addEventListener('click', function() {
            selectCity('');
        });
    }
    
    const table = document.querySelector('.tickets-table tbody');
    if (!table) return;
    
    const cities = new Set();
    
    // Extract cities from address column
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        
        // Find address cell
        cells.forEach(cell => {
            const text = cell.textContent.trim();
            // Address typically has format: "Purok/Zone, Barangay, City, Province"
            if (text.includes(',') && text.split(',').length >= 3) {
                const parts = text.split(',');
                const city = parts[2].trim();
                if (city) cities.add(city);
            }
        });
    });
    
    // Sort cities alphabetically
    const sortedCities = Array.from(cities).sort();
    
    // Add city buttons
    sortedCities.forEach(city => {
        const button = document.createElement('button');
        button.className = 'city-btn';
        button.textContent = city;
        button.dataset.city = city;
        button.addEventListener('click', function() {
            selectCity(city);
        });
        container.appendChild(button);
    });
}

function selectCity(city) {
    selectedCity = city;
    
    // Update button active states
    const buttons = document.querySelectorAll('.city-btn');
    buttons.forEach(btn => {
        if (btn.dataset.city === city) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Apply filters
    applyFilters();
}

function applyFilters() {
    const searchInput = document.getElementById('search-input');
    const table = document.querySelector('.tickets-table tbody');
    
    if (!table) return;
    
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const cityFilter = selectedCity.toLowerCase();

    // Get active tab status
    const activeTabBtn = document.querySelector('.tab-btn.active');
    const activeStatus = activeTabBtn ? activeTabBtn.getAttribute('data-status') : 'all';
    
    const rows = table.querySelectorAll('tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        // Skip "no data" rows
        if (row.querySelector('.no-data')) {
            row.style.display = 'none';
            return;
        }
        
        const cells = row.querySelectorAll('td');
        if (cells.length === 0) return;

        // Tab status filter — use data-status attribute if present, else skip
        if (activeStatus && activeStatus !== 'all') {
            const rowStatus = row.getAttribute('data-status');
            if (rowStatus && rowStatus !== activeStatus) {
                row.style.display = 'none';
                return;
            }
        }
        
        // Get all cell text for searching
        let rowText = '';
        let address = '';
        
        cells.forEach(cell => {
            const text = cell.textContent.trim();
            rowText += text.toLowerCase() + ' ';
            
            // Identify address cell (contains commas)
            if (text.includes(',') && text.split(',').length >= 3) {
                address = text.toLowerCase();
            }
        });
        
        // Extract city from address (check both position 1 and 2 for flexibility)
        let city = '';
        if (address) {
            const addressParts = address.split(',').map(p => p.trim().toLowerCase());
            // Try position 2 first (City, Province format), then position 1 (Barangay, City format)
            if (addressParts.length >= 3) {
                city = addressParts[2]; // Province position
            }
            if (addressParts.length >= 2 && !city) {
                city = addressParts[1]; // City position
            }
            // Also check if city filter matches any part of the address
            if (cityFilter && !city.includes(cityFilter)) {
                // Check all parts of address for city match
                city = addressParts.find(part => part.includes(cityFilter)) || city;
            }
        }
        
        // Check city filter - match if city filter is in any part of the address
        const cityMatch = !cityFilter || address.includes(cityFilter);
        
        // Check search term (search across all row text)
        const searchMatch = !searchTerm || rowText.includes(searchTerm);
        
        // Show/hide row based on filters
        if (cityMatch && searchMatch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show "no results" message if no rows visible
    showNoResultsMessage(table, visibleCount);
}

function showNoResultsMessage(table, visibleCount) {
    // Remove existing "no results" row
    const existingNoResults = table.querySelector('.no-results-row');
    if (existingNoResults) {
        existingNoResults.remove();
    }
    
    // Add "no results" row if needed
    if (visibleCount === 0) {
        const noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-row';
        
        // Count columns for colspan
        const headerCells = document.querySelectorAll('.tickets-table thead th');
        const colspan = headerCells.length || 12;
        
        noResultsRow.innerHTML = `<td colspan="${colspan}" class="no-data">No tickets match your filters</td>`;
        table.appendChild(noResultsRow);
    }
}

// Reset filters function (can be called from outside)
function resetFilters() {
    const searchInput = document.getElementById('search-input');
    
    // Reset selected city
    selectedCity = '';
    
    // Reset button states
    const buttons = document.querySelectorAll('.city-btn');
    buttons.forEach(btn => {
        if (btn.dataset.city === '') {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Reset search input
    if (searchInput) searchInput.value = '';
    
    applyFilters();
}

// Export for use in other scripts
window.resetTicketFilters = resetFilters;
window.applyTicketFilters = applyFilters;
