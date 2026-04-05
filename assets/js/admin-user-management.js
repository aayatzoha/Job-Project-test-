// Admin User Management
document.addEventListener('DOMContentLoaded', function() {
    // Set up event listeners for user management actions
    setupUserManagementListeners();
});

function setupUserManagementListeners() {
    // Assign task button
    document.querySelectorAll('.assign-assignment-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            showAssignmentDialog(userId);
        });
    });
    
    // Other user management buttons
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            // View user profile
            console.log('View user:', userId);
        });
    });
    
    document.querySelectorAll('.suspend-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            // Suspend user
            console.log('Suspend user:', userId);
        });
    });
    
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            // Delete user
            console.log('Delete user:', userId);
        });
    });
}

function showAssignmentDialog(userId) {
    // Get user name from the table
    const userRow = document.querySelector(`button[data-user-id="${userId}"]`).closest('tr');
    const userName = userRow.querySelector('td:nth-child(2)').textContent.trim();
    
    // Create assignment dialog
    const dialog = document.createElement('div');
    dialog.className = 'modal fade show';
    dialog.style.display = 'block';
    dialog.style.backgroundColor = 'rgba(0,0,0,0.5)';
    dialog.setAttribute('tabindex', '-1');
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-hidden', 'false');
    
    // Fetch available assignments
    fetch('../api/jobs/assignment.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load assignments');
            }
            
            const assignments = data.assignments || [];
            
            dialog.innerHTML = `
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Assign Task to ${userName}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="this.closest('.modal').remove()">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form id="assign-task-form">
                                <div class="form-group">
                                    <label for="assignment-select">Select Assignment:</label>
                                    <select class="form-control" id="assignment-select" required>
                                        <option value="">-- Select an Assignment --</option>
                                        ${assignments.map(assignment => 
                                            `<option value="${assignment.id}">${assignment.title}</option>`
                                        ).join('')}
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="this.closest('.modal').remove()">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirm-assignment">Assign Task</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(dialog);
            
            // Set up confirm button
            document.getElementById('confirm-assignment').addEventListener('click', function() {
                const assignmentId = document.getElementById('assignment-select').value;
                if (!assignmentId) {
                    alert('Please select an assignment');
                    return;
                }
                
                assignTaskToUser(userId, assignmentId, dialog);
            });
        })
        .catch(error => {
            console.error('Error loading assignments:', error);
            dialog.innerHTML = `
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Error</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="this.closest('.modal').remove()">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p class="text-danger">Failed to load assignments. Please try again later.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="this.closest('.modal').remove()">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(dialog);
        });
}

function assignTaskToUser(userId, assignmentId, dialog) {
    fetch('../api/jobs/assignment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'assign',
            user_id: userId,
            assignment_id: assignmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close dialog
            dialog.remove();
            
            // Show success message
            showNotification('Assignment successfully assigned to user', 'success');
        } else {
            throw new Error(data.message || 'Failed to assign task');
        }
    })
    .catch(error => {
        console.error('Error assigning task:', error);
        showNotification(error.message || 'Error assigning task', 'danger');
    });
}

// Using utility functions from utility.js

function showNotification(message, type = 'info') {
    // Use the imported utility function
    JobPortalUtils.showNotification(message, type);
}