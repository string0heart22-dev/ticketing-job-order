// View Maintenance JavaScript

let selectedFiles = [];

document.addEventListener('DOMContentLoaded', function() {
    const imageUpload = document.getElementById('image-upload');
    const uploadForm = document.getElementById('upload-form');
    const previewContainer = document.getElementById('preview-container');
    const uploadBtn = document.getElementById('upload-btn');

    // Handle file selection
    if (imageUpload) {
        imageUpload.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            selectedFiles = files;
            displayPreviews(files);
            
            if (files.length > 0) {
                uploadBtn.style.display = 'block';
            }
        });
    }

    // Handle form submission
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            uploadImages();
        });
    }
});

// Display image previews
function displayPreviews(files) {
    const previewContainer = document.getElementById('preview-container');
    previewContainer.innerHTML = '';

    files.forEach((file, index) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="preview-remove" onclick="removePreview(${index})">×</button>
            `;
            previewContainer.appendChild(previewItem);
        };
        
        reader.readAsDataURL(file);
    });
}

// Remove preview
function removePreview(index) {
    selectedFiles.splice(index, 1);
    
    // Update file input
    const dataTransfer = new DataTransfer();
    selectedFiles.forEach(file => dataTransfer.items.add(file));
    document.getElementById('image-upload').files = dataTransfer.files;
    
    displayPreviews(selectedFiles);
    
    if (selectedFiles.length === 0) {
        document.getElementById('upload-btn').style.display = 'none';
    }
}

// Upload images
function uploadImages() {
    const formData = new FormData(document.getElementById('upload-form'));
    const uploadBtn = document.getElementById('upload-btn');
    
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
            location.reload();
        } else {
            Dialog.error('Error uploading images: ' + data.message);
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Upload Images';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Dialog.error('An error occurred while uploading images.');
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload Images';
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

// Close modal when clicking outside image
window.onclick = function(event) {
    const modal = document.getElementById('image-modal');
    if (event.target === modal) {
        closeImageModal();
    }
}

// Delete image
function deleteImage(imageId) {
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
            location.reload();
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


// Handle maintenance technical form submission
document.addEventListener('DOMContentLoaded', function() {
    const maintTechForm = document.getElementById('maint-technical-form');
    if (maintTechForm) {
        maintTechForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('update_maintenance_technical.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Dialog.toast(data.message, 'success');
                    location.reload();
                } else {
                    Dialog.error('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Dialog.error('An error occurred while saving technical details.');
            });
        });
    }
});

// Clear Maintenance NOC
function clearMaintenanceNOC(maintenanceId) {
    Dialog.confirm('Are you sure you want to mark this as NOC CLEARED?', { type: 'confirm', okText: 'Yes, Clear' }).then(ok => {
        if (!ok) return;
    
    const formData = new FormData();
    formData.append('maintenance_id', maintenanceId);
    
    fetch('clear_maintenance_noc_minimal.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Dialog.toast(data.message, 'success');
            location.reload();
        } else {
            Dialog.error('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Dialog.error('An error occurred while clearing NOC.');
    });
    });
}


// Scroll to Technical Details Section
function scrollToTechnicalDetails() {
    const techSection = document.getElementById('technical-details-section');
    if (techSection) {
        techSection.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start',
            inline: 'nearest'
        });
        
        // Add a highlight effect
        techSection.style.transition = 'background-color 0.5s ease';
        techSection.style.backgroundColor = '#fef3c7';
        
        setTimeout(() => {
            techSection.style.backgroundColor = '';
        }, 1500);
    }
}
