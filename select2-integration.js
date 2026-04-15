/**
 * Select2 Integration for Address Dropdowns
 * Makes dropdowns searchable like Google Sheets
 * Works with address_cascade.js cascading system
 */

// Wait for page to load and address_cascade.js to initialize
document.addEventListener('DOMContentLoaded', function() {
    // Wait for address_cascade.js to populate dropdowns and set up listeners
    setTimeout(initializeSelect2, 1000);
});

function initializeSelect2() {
    // All possible form prefixes used in the system
    const prefixes = ['', 'ticket_', 'edit_', 'maint_', 'pullout_'];
    
    prefixes.forEach(prefix => {
        initializeSelect2ForPrefix(prefix);
    });
}

function initializeSelect2ForPrefix(prefix) {
    const fields = ['province', 'city', 'barangay', 'purok_zone'];
    
    fields.forEach(field => {
        const elementId = prefix + field;
        const element = document.getElementById(elementId);
        
        if (element && !element.classList.contains('select2-hidden-accessible')) {
            // Find the closest modal or use document body
            const modalContent = element.closest('.modal-content');
            const dropdownParent = modalContent || $(document.body);
            
            // Initialize Select2 with proper modal support
            $(element).select2({
                placeholder: element.options[0]?.text || 'Select...',
                allowClear: true,
                width: '100%',
                theme: 'default',
                dropdownParent: dropdownParent,
                dropdownCssClass: 'select2-dropdown-modal', // Custom class for modal dropdowns
                containerCssClass: 'select2-container-modal',
                // Ensure dropdown appears above modal content
                dropdownAutoWidth: true
            });
            
            // CRITICAL: Make Select2 trigger native change events for cascade system
            $(element).on('select2:select select2:clear', function() {
                // Trigger native change event that address_cascade.js is listening for
                const event = new Event('change', { bubbles: true });
                this.dispatchEvent(event);
            });
            
            // Watch for when cascade system updates options
            observeSelectChanges(elementId);
        }
    });
}

function observeSelectChanges(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    // Create a MutationObserver to watch for changes in the select element
    const observer = new MutationObserver(function(mutations) {
        let optionsChanged = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                optionsChanged = true;
            }
        });
        
        if (optionsChanged) {
            // Options were added or removed by cascade system, refresh Select2
            setTimeout(() => {
                refreshSelect2Field(elementId);
            }, 50);
        }
    });
    
    // Start observing the select element for changes to its children (options)
    observer.observe(element, {
        childList: true,
        subtree: false
    });
}

function refreshSelect2Field(elementId) {
    const element = document.getElementById(elementId);
    
    if (element && element.classList.contains('select2-hidden-accessible')) {
        // Find the closest modal or use document body
        const modalContent = element.closest('.modal-content');
        const dropdownParent = modalContent || $(document.body);
        
        // Destroy and reinitialize to show updated options
        $(element).select2('destroy');
        $(element).select2({
            placeholder: element.options[0]?.text || 'Select...',
            allowClear: true,
            width: '100%',
            theme: 'default',
            dropdownParent: dropdownParent,
            dropdownCssClass: 'select2-dropdown-modal',
            containerCssClass: 'select2-container-modal',
            dropdownAutoWidth: true
        });
        
        // Re-attach the event handler for triggering native change events
        $(element).on('select2:select select2:clear', function() {
            const event = new Event('change', { bubbles: true });
            this.dispatchEvent(event);
        });
    }
}

// Expose function to reinitialize Select2 when modals open
window.reinitializeSelect2 = function(prefix = '') {
    setTimeout(() => {
        initializeSelect2ForPrefix(prefix);
    }, 300);
};

// Add modal event listeners to reinitialize Select2 when modals open
document.addEventListener('DOMContentLoaded', function() {
    // Listen for modal show events
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const target = mutation.target;
                    if (target.style.display === 'block' || target.classList.contains('active')) {
                        // Modal is being shown, reinitialize Select2
                        setTimeout(() => {
                            initializeSelect2();
                        }, 100);
                    }
                }
            });
        });
        
        observer.observe(modal, {
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    });
});
