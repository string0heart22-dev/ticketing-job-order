// Service Plans Management JavaScript

// Open Add Plan Modal
function openAddPlanModal() {
    document.getElementById('modalTitle').textContent = 'Add New Service Plan';
    document.getElementById('planForm').reset();
    document.getElementById('plan_id').value = '';
    document.getElementById('planModal').style.display = 'block';
}

// Close Plan Modal
function closePlanModal() {
    document.getElementById('planModal').style.display = 'none';
}

// Edit Plan
function editPlan(planId) {
    document.getElementById('modalTitle').textContent = 'Edit Service Plan';
    
    // Fetch plan data
    fetch('get_service_plan.php?id=' + planId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('plan_id').value = data.plan.id;
                document.getElementById('plan_name').value = data.plan.plan_name;
                document.getElementById('speed').value = data.plan.speed;
                document.getElementById('monthly_fee').value = data.plan.monthly_fee;
                document.getElementById('contract_duration').value = data.plan.contract_duration;
                document.getElementById('description').value = data.plan.description || '';
                document.getElementById('status').value = data.plan.status;
                
                document.getElementById('planModal').style.display = 'block';
            } else {
                Dialog.toast('Error loading plan data: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Dialog.toast('Error loading plan data.', 'error');
        });
}

// Delete Plan
function deletePlan(planId) {
    Dialog.confirm('Are you sure you want to delete this service plan?', {
        okText: 'Delete',
        cancelText: 'Cancel',
        type: 'danger'
    }).then(function(confirmed) {
        if (!confirmed) return;
        fetch('delete_service_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + planId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Dialog.toast('Service plan deleted successfully.', 'success');
                setTimeout(() => {
                    if (window.SPARouter && typeof window.SPARouter.navigateTo === 'function') {
                        window.SPARouter.navigateTo('service_plans.php');
                    } else {
                        location.reload();
                    }
                }, 1000);
            } else {
                Dialog.toast('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Dialog.toast('Error deleting plan.', 'error');
        });
    });
}

// Handle Form Submission — use delegation so it works after SPA re-renders
document.addEventListener('submit', function(e) {
    if (!e.target || e.target.id !== 'planForm') return;
    e.preventDefault();

    const formData = new FormData(e.target);

    fetch('process_service_plan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Dialog.toast(data.message || 'Plan saved successfully.', 'success');
            closePlanModal();
            setTimeout(() => {
                if (window.SPARouter && typeof window.SPARouter.navigateTo === 'function') {
                    window.SPARouter.navigateTo('service_plans.php');
                } else {
                    location.reload();
                }
            }, 1000);
        } else {
            Dialog.toast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Dialog.toast('Error saving plan.', 'error');
    });
});

// Bulk delete helpers
function toggleAllPlanCheckboxes(source) {
    document.querySelectorAll('.plan-checkbox').forEach(cb => cb.checked = source.checked);
    updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
    const checked = document.querySelectorAll('.plan-checkbox:checked').length;
    const btn = document.getElementById('bulkDeleteBtn');
    if (btn) btn.style.display = checked > 0 ? 'inline-block' : 'none';
    const selectAll = document.getElementById('selectAllPlans');
    if (selectAll) {
        const total = document.querySelectorAll('.plan-checkbox').length;
        selectAll.checked = checked === total && total > 0;
        selectAll.indeterminate = checked > 0 && checked < total;
    }
}

function bulkDeletePlans() {
    const ids = [...document.querySelectorAll('.plan-checkbox:checked')].map(cb => cb.value);
    if (!ids.length) return;

    Dialog.confirm(`Delete ${ids.length} selected plan(s)?`, { okText: 'Delete', type: 'danger' })
        .then(confirmed => {
            if (!confirmed) return;
            fetch('bulk_delete_service_plans.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ids=' + encodeURIComponent(JSON.stringify(ids))
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Dialog.toast(data.message, 'success');
                    setTimeout(() => {
                        if (window.SPARouter && typeof window.SPARouter.navigateTo === 'function') {
                            window.SPARouter.navigateTo('service_plans.php');
                        } else {
                            location.reload();
                        }
                    }, 1000);
                } else {
                    Dialog.toast('Error: ' + data.message, 'error');
                }
            })
            .catch(() => Dialog.toast('Error deleting plans.', 'error'));
        });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('planModal');
    if (event.target == modal) {
        closePlanModal();
    }
}
