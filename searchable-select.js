/**
 * Searchable Select - Makes all select dropdowns searchable/typable
 * Automatically enhances all select elements on the page
 */

class SearchableSelect {
    constructor(selectElement) {
        this.select = selectElement;
        this.options = Array.from(selectElement.options);
        this.selectedIndex = selectElement.selectedIndex;
        this.isOpen = false;
        this.searchTerm = '';
        this.searchTimeout = null;
        
        this.init();
    }

    init() {
        // Skip if already initialized
        if (this.select.classList.contains('searchable-select-initialized')) {
            return;
        }
        
        this.select.classList.add('searchable-select-initialized');
        this.createWrapper();
        this.attachEvents();
    }

    createWrapper() {
        // Create wrapper
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'searchable-select-wrapper';
        
        // Create display button
        this.displayBtn = document.createElement('button');
        this.displayBtn.type = 'button';
        this.displayBtn.className = 'searchable-select-display';
        this.updateDisplayText();
        
        // Create dropdown container
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'searchable-select-dropdown';
        
        // Create search input
        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = 'searchable-select-search';
        this.searchInput.placeholder = 'Type to search...';
        
        // Create options list
        this.optionsList = document.createElement('div');
        this.optionsList.className = 'searchable-select-options';
        this.renderOptions();
        
        // Assemble dropdown
        this.dropdown.appendChild(this.searchInput);
        this.dropdown.appendChild(this.optionsList);
        
        // Assemble wrapper
        this.wrapper.appendChild(this.displayBtn);
        this.wrapper.appendChild(this.dropdown);
        
        // Replace select with wrapper
        this.select.parentNode.insertBefore(this.wrapper, this.select);
        this.select.style.display = 'none';
    }

    renderOptions(filter = '') {
        this.optionsList.innerHTML = '';
        
        const filteredOptions = this.options.filter(option => {
            if (!filter) return true;
            const text = option.textContent.toLowerCase();
            const value = option.value.toLowerCase();
            const searchLower = filter.toLowerCase();
            return text.includes(searchLower) || value.includes(searchLower);
        });

        if (filteredOptions.length === 0) {
            const noResults = document.createElement('div');
            noResults.className = 'searchable-select-no-results';
            noResults.textContent = 'No results found';
            this.optionsList.appendChild(noResults);
            return;
        }

        filteredOptions.forEach((option, index) => {
            const optionDiv = document.createElement('div');
            optionDiv.className = 'searchable-select-option';
            optionDiv.textContent = option.textContent;
            optionDiv.dataset.value = option.value;
            optionDiv.dataset.index = this.options.indexOf(option);
            
            if (option.selected) {
                optionDiv.classList.add('selected');
            }
            
            optionDiv.addEventListener('click', () => this.selectOption(option));
            
            this.optionsList.appendChild(optionDiv);
        });
    }

    attachEvents() {
        // Toggle dropdown
        this.displayBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggle();
        });

        // Search input
        this.searchInput.addEventListener('input', (e) => {
            this.searchTerm = e.target.value;
            this.renderOptions(this.searchTerm);
        });

        // Prevent dropdown close when clicking inside
        this.dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!this.wrapper.contains(e.target)) {
                this.close();
            }
        });

        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.close();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const firstVisible = this.optionsList.querySelector('.searchable-select-option');
                if (firstVisible) {
                    const index = parseInt(firstVisible.dataset.index);
                    this.selectOption(this.options[index]);
                }
            }
        });
    }

    updateDisplayText() {
        const selectedOption = this.select.options[this.select.selectedIndex];
        this.displayBtn.textContent = selectedOption ? selectedOption.textContent : 'Select...';
        
        // Add arrow icon
        this.displayBtn.innerHTML = `
            <span class="searchable-select-text">${this.displayBtn.textContent}</span>
            <span class="searchable-select-arrow">▼</span>
        `;
    }

    selectOption(option) {
        // Update original select
        this.select.value = option.value;
        this.select.selectedIndex = this.options.indexOf(option);
        
        // Trigger change event on original select - make sure it bubbles
        const event = new Event('change', { bubbles: true, cancelable: true });
        this.select.dispatchEvent(event);
        
        console.log('Dispatched change event for:', option.value);
        
        // Update display
        this.updateDisplayText();
        
        // Update selected class
        this.optionsList.querySelectorAll('.searchable-select-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        const selectedDiv = this.optionsList.querySelector(`[data-value="${option.value}"]`);
        if (selectedDiv) {
            selectedDiv.classList.add('selected');
        }
        
        this.close();
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.isOpen = true;
        this.wrapper.classList.add('open');
        this.searchInput.value = '';
        this.searchTerm = '';
        this.renderOptions();
        this.searchInput.focus();
    }

    close() {
        this.isOpen = false;
        this.wrapper.classList.remove('open');
        this.searchInput.value = '';
    }

    destroy() {
        this.wrapper.parentNode.insertBefore(this.select, this.wrapper);
        this.wrapper.remove();
        this.select.style.display = '';
        this.select.classList.remove('searchable-select-initialized');
    }
}

// Auto-initialize all select elements
function initSearchableSelects() {
    // Wait for service areas to load first
    if (window.serviceAreaAddress && !window.serviceAreaAddress.initialized) {
        console.log('Waiting for service areas to load before initializing searchable selects...');
        setTimeout(initSearchableSelects, 100);
        return;
    }
    
    // Don't apply searchable-select to address fields - they have cascading behavior
    const selects = document.querySelectorAll('select:not(.searchable-select-initialized):not(.no-search):not([name="province"]):not([name="city"]):not([name="barangay"]):not([name="purok_zone"])');
    selects.forEach(select => {
        new SearchableSelect(select);
    });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSearchableSelects);
} else {
    initSearchableSelects();
}

// Re-initialize when new content is added (for dynamic content)
const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1) { // Element node
                if (node.tagName === 'SELECT' && !node.classList.contains('searchable-select-initialized')) {
                    new SearchableSelect(node);
                }
                // Check children
                const selects = node.querySelectorAll ? node.querySelectorAll('select:not(.searchable-select-initialized):not(.no-search)') : [];
                selects.forEach(select => new SearchableSelect(select));
            }
        });
    });
});

observer.observe(document.body, {
    childList: true,
    subtree: true
});

// Export for manual initialization if needed
window.SearchableSelect = SearchableSelect;
window.initSearchableSelects = initSearchableSelects;
