/**
 * SPA View Modal for Installation Tickets
 * Handles URL state management, browser history, and keyboard navigation
 */

(function() {
    'use strict';

    // SPA State Management
    const SPA = {
        currentModal: null,
        currentId: null,
        isOpen: false,
        
        // Real-time sync properties
        lastModified: null,
        pollInterval: null,
        pollDelay: 3000, // Poll every 3 seconds
        isPolling: false,
        knownImageIds: [], // Track IDs of images already in gallery
        ownUpdateTimestamp: null, // Track when current user made an update
        
        // Open modal and update URL
        openViewModal: function(id) {
            if (!id || isNaN(parseInt(id))) return;
            
            this.currentId = parseInt(id);
            this.isOpen = true;
            this.currentModal = 'view-installation';
            
            // Update URL with hash for SPA routing
            const newHash = `#view=${this.currentId}`;
            if (window.location.hash !== newHash) {
                window.history.pushState(
                    { modal: 'view-installation', id: this.currentId }, 
                    '', 
                    newHash
                );
            }
            
            // Load and display the installation
            this.loadInstallationData(this.currentId);
            
            // Start real-time polling
            this.startPolling();
        },
        
        // Close modal and clean up URL
        closeViewModal: function() {
            // Stop polling when modal closes
            this.stopPolling();
            
            const modal = document.getElementById('view-installation-modal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
            
            this.isOpen = false;
            this.currentModal = null;
            this.currentId = null;
            this.lastModified = null;
            this.knownImageIds = []; // Reset known images
            this.ownUpdateTimestamp = null; // Reset own update tracking
            
            // Remove hash from URL if it's a view hash
            if (window.location.hash.startsWith('#view=')) {
                window.history.pushState({}, '', window.location.pathname + window.location.search);
            }
        },
        
        // Load installation data via AJAX
        loadInstallationData: function(id) {
            fetch('get_installation_data.php?id=' + id)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            // Store last modified timestamp for real-time sync
                            if (data.installation && data.installation.updated_at) {
                                this.lastModified = data.installation.updated_at;
                            } else if (data.installation && data.installation.last_modified) {
                                this.lastModified = data.installation.last_modified;
                            }
                            
                            // Track all image IDs from the server
                            if (data.images && data.images.length > 0) {
                                this.knownImageIds = data.images.map(img => img.id);
                                console.log('📸 Tracked', this.knownImageIds.length, 'existing image IDs');
                            }
                            
                            // Call the existing modal display function
                            if (typeof openViewInstallationModal === 'function') {
                                openViewInstallationModal(data.installation, data.images);
                            }
                            
                            // Show the modal
                            const modal = document.getElementById('view-installation-modal');
                            if (modal) {
                                modal.style.display = 'block';
                                document.body.style.overflow = 'hidden';
                            }
                        } else {
                            if (typeof Dialog !== 'undefined') {
                                Dialog.error('Error loading installation data: ' + data.message);
                            } else {
                                alert('Error loading installation data: ' + data.message);
                            }
                            this.closeViewModal();
                        }
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        if (typeof Dialog !== 'undefined') {
                            Dialog.error('Invalid response from server. Check console for details.');
                        }
                        this.closeViewModal();
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    if (typeof Dialog !== 'undefined') {
                        Dialog.error('An error occurred while loading installation data: ' + error.message);
                    } else {
                        alert('An error occurred while loading installation data: ' + error.message);
                    }
                    this.closeViewModal();
                });
        },
        
        // Start real-time polling for updates
        startPolling: function() {
            if (this.isPolling) return;
            
            this.isPolling = true;
            console.log('🔄 Started real-time polling for installation updates');
            
            // Set up the polling interval
            this.pollInterval = setInterval(() => {
                this.checkForUpdates();
            }, this.pollDelay);
        },
        
        // Stop real-time polling
        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
            this.isPolling = false;
            console.log('⏹️ Stopped real-time polling');
        },
        
        // Check for updates from server
        checkForUpdates: function() {
            if (!this.currentId || !this.isOpen) return;
            
            // Build URL with known image IDs
            const knownImagesParam = this.knownImageIds.length > 0 ? `&known_images=${this.knownImageIds.join(',')}` : '';
            const url = `get_installation_data.php?id=${this.currentId}&check_update=1&last_modified=${this.lastModified || ''}&check_images=1${knownImagesParam}`;
            
            console.log('🔍 Polling for updates... lastModified:', this.lastModified);
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('📊 Poll response - has_update:', data.has_update, 'last_modified:', data.last_modified);
                        
                        // If data has been modified
                        if (data.has_update) {
                            // Check if this is our own update (within 5 seconds of our last action)
                            const isOwnUpdate = this.ownUpdateTimestamp && 
                                (Date.now() - this.ownUpdateTimestamp < 5000);
                            
                            if (isOwnUpdate) {
                                console.log('📡 Update detected but it was our own - skipping notification');
                            } else {
                                console.log('📡 Update detected on other device, refreshing...');
                                // Show notification
                                this.showUpdateNotification();
                            }
                            
                            // Reload the installation data (always refresh)
                            console.log('🔄 Calling loadInstallationData...');
                            this.loadInstallationData(this.currentId);
                            
                            // Update last modified timestamp
                            this.lastModified = data.last_modified;
                            console.log('✅ Updated lastModified to:', this.lastModified);
                        }
                        
                        // Check if there are new images
                        if (data.new_images && data.new_images.length > 0) {
                            // Check if images were uploaded by us
                            const isOwnImageUpload = this.ownUpdateTimestamp && 
                                (Date.now() - this.ownUpdateTimestamp < 5000);
                            
                            if (isOwnImageUpload) {
                                console.log(`📸 ${data.new_images.length} image(s) detected but they are ours - skipping notification`);
                            } else {
                                console.log(`📸 ${data.new_images.length} new image(s) detected from other device`);
                                // Show notification
                                this.showImageNotification(data.new_images.length);
                            }
                            
                            // Add new images to gallery (always add)
                            if (typeof appendImagesToGallery === 'function') {
                                appendImagesToGallery(data.new_images);
                            }
                            
                            // Update known image IDs to include the new ones
                            const newIds = data.new_images.map(img => img.id);
                            this.knownImageIds = [...this.knownImageIds, ...newIds];
                        }
                        
                        // Check if there are deleted images
                        if (data.deleted_image_ids && data.deleted_image_ids.length > 0) {
                            console.log(`🗑️ ${data.deleted_image_ids.length} image(s) deleted on other device`);
                            
                            // Remove deleted images from gallery
                            data.deleted_image_ids.forEach(id => {
                                const imageItem = document.querySelector(`#inst-images-gallery .image-item[data-image-id="${id}"]`);
                                if (imageItem) {
                                    imageItem.style.animation = 'fadeOut 0.3s ease';
                                    setTimeout(() => imageItem.remove(), 300);
                                    console.log(`🗑️ Removed deleted image ${id} from gallery`);
                                }
                            });
                            
                            // Update known image IDs to remove deleted ones
                            this.knownImageIds = this.knownImageIds.filter(id => !data.deleted_image_ids.includes(id));
                            
                            // Check if gallery is now empty
                            const gallery = document.getElementById('inst-images-gallery');
                            if (gallery && gallery.children.length === 0) {
                                gallery.innerHTML = '<p class="no-images">No images uploaded yet</p>';
                            }
                            
                            // Show notification for deletions from other device
                            const isOwnDelete = this.ownUpdateTimestamp && 
                                (Date.now() - this.ownUpdateTimestamp < 5000);
                            if (!isOwnDelete) {
                                this.showDeleteNotification(data.deleted_image_ids.length);
                            }
                        }
                    }
                })
                .catch(error => {
                    // Silently handle errors during polling
                    console.log('Polling error (non-critical):', error);
                });
        },
        
        // Show notification when new images are detected
        showImageNotification: function(count) {
            let notification = document.getElementById('spa-update-notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'spa-update-notification';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #10b981;
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    font-family: system-ui, -apple-system, sans-serif;
                    font-size: 14px;
                    z-index: 10000;
                    display: none;
                    animation: slideIn 0.3s ease;
                `;
                document.body.appendChild(notification);
            }
            
            notification.textContent = `📸 ${count} new photo${count > 1 ? 's' : ''} from another device`;
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        },
        
        // Show notification when images are deleted remotely
        showDeleteNotification: function(count) {
            let notification = document.getElementById('spa-update-notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'spa-update-notification';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #ef4444;
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    font-family: system-ui, -apple-system, sans-serif;
                    font-size: 14px;
                    z-index: 10000;
                    display: none;
                    animation: slideIn 0.3s ease;
                `;
                document.body.appendChild(notification);
            }
            
            notification.textContent = `🗑️ ${count} photo${count > 1 ? 's' : ''} deleted on another device`;
            notification.style.background = '#ef4444';
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        },
        
        // Show notification when data is updated remotely
        showUpdateNotification: function() {
            // Create notification element if it doesn't exist
            let notification = document.getElementById('spa-update-notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'spa-update-notification';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #10b981;
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    font-family: system-ui, -apple-system, sans-serif;
                    font-size: 14px;
                    z-index: 10000;
                    display: none;
                    animation: slideIn 0.3s ease;
                `;
                document.body.appendChild(notification);
            }
            
            notification.textContent = '🔄 Updated from another device';
            notification.style.display = 'block';
            
            // Hide after 3 seconds
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        },
        
        // Mark that current user made an update (to skip self-notifications)
        markOwnUpdate: function() {
            this.ownUpdateTimestamp = Date.now();
            console.log('📌 Marked own update at:', new Date(this.ownUpdateTimestamp).toLocaleTimeString());
        },
        
        // Handle browser back/forward buttons
        handlePopState: function(event) {
            const hash = window.location.hash;
            
            if (hash.startsWith('#view=')) {
                const id = parseInt(hash.replace('#view=', ''));
                if (!isNaN(id)) {
                    this.openViewModal(id);
                }
            } else if (hash.startsWith('#edit=')) {
                const id = parseInt(hash.replace('#edit=', ''));
                if (!isNaN(id)) {
                    // Close view modal if open
                    this.isOpen = false;
                    this.currentModal = 'edit-ticket';
                    this.currentId = id;
                    const modal = document.getElementById('view-installation-modal');
                    if (modal) {
                        modal.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                    // Open edit modal
                    if (typeof editTicket === 'function') {
                        editTicket(id);
                    }
                }
            } else {
                // Close modal if no view/edit hash
                if (this.isOpen) {
                    const modal = document.getElementById('view-installation-modal');
                    if (modal) {
                        modal.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                    this.isOpen = false;
                    this.currentModal = null;
                    this.currentId = null;
                }
            }
        },
        
        // Initialize SPA functionality
        init: function() {
            // Handle browser back/forward buttons
            window.addEventListener('popstate', (e) => this.handlePopState(e));
            
            // Check URL on page load for existing hash
            if (window.location.hash.startsWith('#view=')) {
                const id = parseInt(window.location.hash.replace('#view=', ''));
                if (!isNaN(id)) {
                    setTimeout(() => this.openViewModal(id), 100);
                }
            } else if (window.location.hash.startsWith('#edit=')) {
                const id = parseInt(window.location.hash.replace('#edit=', ''));
                if (!isNaN(id)) {
                    setTimeout(() => {
                        if (typeof editTicket === 'function') {
                            editTicket(id);
                        }
                    }, 100);
                }
            }
            
            // Override the global viewInstallation function
            window.viewInstallation = (id) => this.openViewModal(id);
            
            console.log('✅ SPA View Modal initialized');
        }
    };

    // Make SPA object globally available
    window.InstallationSPA = SPA;
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => SPA.init());
    } else {
        SPA.init();
    }
})();
