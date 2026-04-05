// Admin Assignment Management
document.addEventListener('DOMContentLoaded', function() {
    // Initialize assignment functionality
    initAdminAssignments();
});

function initAdminAssignments() {
    // Load available assignments and job seekers
    loadAssignmentData();
    
    // Set up event listeners
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('assign-assignment-btn')) {
            const userId = e.target.getAttribute('data-user-id');
            openAssignmentModal(userId);
        }
    });
}

function loadAssignmentData() {
    fetch('../api/admin/assign_assignment.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Store data for later use
                window.availableAssignments = data.assignments;
                window.jobSeekers = data.job_seekers;
            } else {
                showToast('Error loading assignment data', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading assignment data:', error);
            showToast('Error loading assignment data', 'error');
        });
}

function openAssignmentModal(userId) {
    // Get user info
    const user = window.jobSeekers.find(js => js.id == userId) || {};
    
    // Create modal content
    const modalContent = `
        <div class="assignment-modal">
            <h3>Assign Task to ${user.name || 'User'}</h3>
            <p>Select an assignment to assign to this user:</p>
            <div class="form-group">
                <label for="assignment-select">Available Assignments:</label>
                <select id="assignment-select" class="form-control">
                    <option value="">-- Select an Assignment --</option>
                    ${window.availableAssignments.map(assignment => 
                        `<option value="${assignment.id}">${assignment.title} (${assignment.job_title} - ${assignment.company_name})</option>`
                    ).join('')}
                </select>
            </div>
            <div class="form-group mt-3">
                <button id="confirm-assignment-btn" class="btn btn-primary" data-user-id="${userId}">
                    Assign Task
                </button>
                <button id="cancel-assignment-btn" class="btn btn-secondary">
                    Cancel
                </button>
            </div>
        </div>
    `;
    
    // Show modal
    const modal = document.createElement('div');
    modal.className = 'custom-modal';
    modal.innerHTML = modalContent;
    document.body.appendChild(modal);
    
    // Add event listeners
    document.getElementById('confirm-assignment-btn').addEventListener('click', function() {
        const assignmentId = document.getElementById('assignment-select').value;
        if (!assignmentId) {
            showToast('Please select an assignment', 'warning');
            return;
        }
        
        assignTaskToUser(userId, assignmentId);
        closeModal();
    });
    
    document.getElementById('cancel-assignment-btn').addEventListener('click', closeModal);
}

function closeModal() {
    const modal = document.querySelector('.custom-modal');
    if (modal) {
        document.body.removeChild(modal);
    }
}

function assignTaskToUser(userId, assignmentId) {
    fetch('../api/admin/assign_assignment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            assignment_id: assignmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Assignment successfully assigned to user', 'success');
            // Refresh data
            loadAssignmentData();
        } else {
            showToast(data.message || 'Error assigning task', 'error');
        }
    })
    .catch(error => {
        console.error('Error assigning task:', error);
        showToast('Error assigning task', 'error');
    });
}

// Using utility functions from utility.js

function showToast(message, type = 'info') {
    // Use the utility function
    JobPortalUtils.showNotification(message, type);
}