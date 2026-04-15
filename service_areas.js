// Service Areas JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Add Service Area Modal
    const addAreaBtn = document.getElementById('add-area-btn');
    const addAreaModal = document.getElementById('add-area-modal');
    const closeAddArea = document.getElementById('close-add-area');
    const cancelAddArea = document.getElementById('cancel-add-area');

    if (addAreaBtn) {
        addAreaBtn.addEventListener('click', function() {
            addAreaModal.style.display = 'block';
        });
    }

    if (closeAddArea) {
        closeAddArea.addEventListener('click', function() {
            addAreaModal.style.display = 'none';
        });
    }

    if (cancelAddArea) {
        cancelAddArea.addEventListener('click', function() {
            addAreaModal.style.display = 'none';
        });
    }

    // Edit Service Area Modal
    const editAreaModal = document.getElementById('edit-area-modal');
    const closeEditArea = document.getElementById('close-edit-area');
    const cancelEditArea = document.getElementById('cancel-edit-area');

    if (closeEditArea) {
        closeEditArea.addEventListener('click', function() {
            editAreaModal.style.display = 'none';
        });
    }

    if (cancelEditArea) {
        cancelEditArea.addEventListener('click', function() {
            editAreaModal.style.display = 'none';
        });
    }

    // Import Excel Modal
    const importExcelBtn = document.getElementById('import-excel-btn');
    const importExcelModal = document.getElementById('import-excel-modal');
    const closeImportExcel = document.getElementById('close-import-excel');
    const cancelImportExcel = document.getElementById('cancel-import-excel');

    if (importExcelBtn) {
        importExcelBtn.addEventListener('click', function() {
            importExcelModal.style.display = 'block';
        });
    }

    if (closeImportExcel) {
        closeImportExcel.addEventListener('click', function() {
            importExcelModal.style.display = 'none';
        });
    }

    if (cancelImportExcel) {
        cancelImportExcel.addEventListener('click', function() {
            importExcelModal.style.display = 'none';
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === addAreaModal) {
            addAreaModal.style.display = 'none';
        }
        if (event.target === editAreaModal) {
            editAreaModal.style.display = 'none';
        }
        if (event.target === importExcelModal) {
            importExcelModal.style.display = 'none';
        }
    });
});

// Edit Service Area
function editArea(areaId) {
    fetch('get_service_area.php?id=' + areaId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_area_id').value = data.area.id;
                document.getElementById('edit_purok_zone').value = data.area.purok_zone;
                document.getElementById('edit_barangay').value = data.area.barangay;
                document.getElementById('edit_city').value = data.area.city;
                document.getElementById('edit_province').value = data.area.province;
                document.getElementById('edit_zip_code').value = data.area.zip_code;
                
                document.getElementById('edit-area-modal').style.display = 'block';
            } else {
                Dialog.toast('Error loading service area: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Dialog.toast('An error occurred while loading service area data.', 'error');
        });
}

// Delete Service Area
function deleteArea(areaId) {
    Dialog.confirm('Are you sure you want to delete this service area? This action cannot be undone.', {
        confirmText: 'Delete',
        cancelText: 'Cancel',
        type: 'danger'
    }).then(function(confirmed) {
        if (!confirmed) return;
        const formData = new FormData();
        formData.append('area_id', areaId);

        fetch('delete_service_area.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Dialog.toast('Service area deleted successfully.', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                Dialog.toast('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            Dialog.toast('An error occurred. Please try again.', 'error');
            console.error('Error:', error);
        });
    });
}
