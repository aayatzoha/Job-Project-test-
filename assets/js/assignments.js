/**
 * Assignments Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize assignments functionality
    initAssignments();
});

/**
 * Initialize assignments functionality
 */
function initAssignments() {
    // Load assignments if on assignments page
    if (document.getElementById('assignments-container')) {
        // Get job_id from URL if present
        const urlParams = new URLSearchParams(window.location.search);
        const jobId = urlParams.get('job_id');
        loadAssignments(jobId ? parseInt(jobId) : null);
    }
    
    // Initialize assignment creation form
    const createForm = document.getElementById('create-assignment-form');
    if (createForm) {
        createForm.addEventListener('submit', createAssignment);
    }
    
    // Initialize assignment submission form
    const submitForm = document.getElementById('submit-assignment-form');
    if (submitForm) {
        submitForm.addEventListener('submit', submitAssignment);
        
        const startBtn = document.getElementById('start-assignment-btn');
        if (startBtn) {
            const requiresCamera = startBtn.dataset.requiresCamera === '1';
            startBtn.addEventListener('click', () => {
                if (requiresCamera) {
                    requestCameraAccess();
                } else {
                    startAssignmentWithoutCamera();
                }
            });
        }
    }
    
    // Initialize assignment review form
    const reviewForm = document.getElementById('review-assignment-form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', reviewAssignment);
    }
}

/**
 * Load assignments
 */
function loadAssignments(jobId = null) {
    const container = document.getElementById('assignments-container');
    if (!container) return;
    
    container.innerHTML = '<div class="loading">Loading assignments...</div>';
    
    // Determine the correct API path based on current location
    let basePath = '';
    if (window.location.pathname.includes('/dashboard/') || window.location.pathname.includes('/pages/')) {
        basePath = '../';
    }
    
    let url = basePath + 'api/jobs/assignment.php';
    if (jobId) {
        url += `?job_id=${jobId}`;
    }
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("JSON Parse Error:", e);
                    console.error("Raw response:", text);
                    throw new Error("Invalid JSON response from server");
                }
            });
        })
        .then(data => {
            if (data.success) {
                displayAssignments(data.assignments || []);
            } else {
                container.innerHTML = `<div class="error">${data.message || 'Failed to load assignments'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error loading assignments:', error);
            container.innerHTML = `<div class="error">Error loading assignments. Please try again later.</div>`;
        });
}

/**
 * Display assignments in container
 */
function displayAssignments(assignments) {
    const container = document.getElementById('assignments-container');
    if (!container) return;
    
    if (!assignments || assignments.length === 0) {
        container.innerHTML = '<div class="no-data">No assignments found.</div>';
        return;
    }
    
    let html = '<div class="assignments-list">';
    
    assignments.forEach(assignment => {
        const dueDate = new Date(assignment.due_date).toLocaleDateString();
        const status = getStatusBadge(assignment.status);
        
        html += `
            <div class="assignment-card" data-id="${assignment.id}">
                <div class="assignment-header">
                    <h3>${assignment.title}</h3>
                    ${status}
                </div>
                <div class="assignment-details">
                    <p>${assignment.description}</p>
                    <div class="assignment-meta">
                        <span><i class="fas fa-calendar"></i> Due: ${dueDate}</span>
                        ${assignment.job_title ? `<span><i class="fas fa-briefcase"></i> ${assignment.job_title}</span>` : ''}
                        ${assignment.assignee_name ? `<span><i class="fas fa-user"></i> Assigned to: ${assignment.assignee_name}</span>` : ''}
                    </div>
                </div>
                <div class="assignment-actions">
                    <button class="btn btn-primary btn-sm view-assignment" data-id="${assignment.id}">View Details</button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    
    // Add event listeners to view buttons
    document.querySelectorAll('.view-assignment').forEach(button => {
        button.addEventListener('click', () => viewAssignment(button.dataset.id));
    });
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    let badgeClass = '';
    let text = status.charAt(0).toUpperCase() + status.slice(1);
    
    switch (status) {
        case 'pending':
            badgeClass = 'badge-warning';
            break;
        case 'submitted':
            badgeClass = 'badge-info';
            break;
        case 'approved':
            badgeClass = 'badge-success';
            break;
        case 'rejected':
            badgeClass = 'badge-danger';
            break;
        default:
            badgeClass = 'badge-secondary';
    }
    
    return `<span class="badge ${badgeClass}">${text}</span>`;
}

/**
 * View assignment details
 */
function viewAssignment(assignmentId) {
    // Redirect to assignment detail page or show modal
    window.location.href = `assignment_detail.php?id=${assignmentId}`;
}

/**
 * Create or update assignment
 */
function createAssignment(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';
    
    const formData = new FormData(form);
    const messageContainer = document.getElementById('message-container');
    
    // Check if this is an update
    const isUpdate = formData.get('edit_mode') === '1';
    const assignmentId = formData.get('assignment_id');
    
    fetch('../api/jobs/assignment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        
        if (data.success) {
            showMessage(isUpdate ? 'Assignment updated successfully!' : 'Assignment saved successfully!', 'success');
            form.reset();
            
            // Reload assignments or redirect
            if (document.getElementById('assignments-container')) {
                loadAssignments(formData.get('job_id'));
            } else if (!isUpdate) {
                setTimeout(() => {
                    window.location.href = `assignments.php?job_id=${formData.get('job_id')}`;
                }, 1500);
            }
        } else {
            showMessage(data.message || 'An error occurred while saving the assignment', 'error');
        }
    })
    .catch(error => {
        console.error('Error saving assignment:', error);
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        showMessage('An error occurred while saving the assignment. Please try again.', 'error');
    });
}

/**
 * Add a new question to the form
 */
function addQuestion() {
    const container = document.getElementById('questions-container');
    const questionCount = container.querySelectorAll('.question-item').length;
    
    const questionItem = document.createElement('div');
    questionItem.className = 'question-item';
    
    questionItem.innerHTML = `
        <div class="question-header">
            <label>Question ${questionCount + 1}</label>
            <button type="button" class="btn-remove-question" onclick="removeQuestion(this)">Remove</button>
        </div>
        <div class="form-group">
            <textarea name="questions[]" class="form-control" rows="3" required placeholder="Enter your question here..."></textarea>
        </div>
        <div class="form-group">
            <label>Question Type</label>
            <select name="question_types[]" class="form-control question-type" onchange="toggleOptions(this)">
                <option value="text">Text Answer</option>
                <option value="multiple_choice">Multiple Choice</option>
                <option value="file_upload">File Upload</option>
                <option value="code_editor">Code Editor</option>
            </select>
        </div>
        <div class="options-container" style="display: none;">
            <label>Options (one per line)</label>
            <textarea name="options[]" class="form-control" rows="4" placeholder="Enter each option on a new line"></textarea>
        </div>
    `;
    
    container.appendChild(questionItem);
    
    // Scroll to the new question
    questionItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Remove a question from the form
 */
function removeQuestion(button) {
    const questionItem = button.closest('.question-item');
    const container = document.getElementById('questions-container');
    
    // Don't remove if it's the only question
    if (container.querySelectorAll('.question-item').length <= 1) {
        alert('You must have at least one question.');
        return;
    }
    
    // Confirm removal
    if (confirm('Are you sure you want to remove this question?')) {
        questionItem.remove();
        
        // Renumber remaining questions
        const questions = container.querySelectorAll('.question-item');
        questions.forEach((item, index) => {
            const label = item.querySelector('.question-header label');
            label.textContent = `Question ${index + 1}`;
        });
    }
}

/**
 * Toggle options visibility based on question type
 */
function toggleOptions(select) {
    const questionItem = select.closest('.question-item');
    const optionsContainer = questionItem.querySelector('.options-container');
    
    if (select.value === 'multiple_choice') {
        optionsContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
    }
}

/**
 * Request camera access for proctoring
 */
function requestCameraAccess() {
    let videoContainer = document.getElementById('video-container');
    if (!videoContainer) {
        videoContainer = document.createElement('div');
        videoContainer.id = 'video-container';
        document.querySelector('.assignment-content').prepend(videoContainer);
    }
    
    // Show camera access modal
    Swal.fire({
        title: 'Camera Access Required',
        text: 'This assignment requires camera access for proctoring. Please allow access to continue.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Allow Camera Access',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Request camera access
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(stream => {
                    // Create video element
                    const video = document.createElement('video');
                    video.srcObject = stream;
                    video.autoplay = true;
                    video.classList.add('proctor-video');
                    
                    // Add video to container
                    videoContainer.innerHTML = '';
                    videoContainer.appendChild(video);
                    
                    // Show assignment content
                    document.getElementById('assignment-content').classList.remove('hidden');
                    document.getElementById('start-assignment-btn').classList.add('hidden');
                    
                    // Start proctoring
                    startProctoring(stream);
                })
                .catch(error => {
                    console.error('Error accessing camera:', error);
                    Swal.fire({
                        title: 'Camera Access Denied',
                        text: 'You must allow camera access to take this assignment. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        }
    });
}

/**
 * Start assignment when camera isn't required
 */
function startAssignmentWithoutCamera() {
    const startBtn = document.getElementById('start-assignment-btn');
    const assignmentContent = document.getElementById('assignment-content');
    if (!assignmentContent || !startBtn) return;
    
    assignmentContent.classList.remove('hidden');
    startBtn.classList.add('hidden');
}

/**
 * Start proctoring for the assignment
 */
function startProctoring(stream) {
    // Log proctoring start
    fetch('../api/assessments/log_activity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            activity_type: 'proctoring_started',
            assignment_id: document.querySelector('[data-assignment-id]').dataset.assignmentId
        })
    });
    
    // Set up periodic screenshots for proctoring
    window.proctoringInterval = setInterval(() => {
        // Take screenshot from video stream
        const video = document.querySelector('.proctor-video');
        if (!video) return;
        
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Convert to base64 and send to server
        const screenshot = canvas.toDataURL('image/jpeg', 0.5);
        
        // Log proctoring activity
        fetch('../api/assessments/log_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                activity_type: 'proctoring_screenshot',
                assignment_id: document.querySelector('[data-assignment-id]').dataset.assignmentId,
                screenshot: screenshot
            })
        });
    }, 30000); // Every 30 seconds
    
    // Set up event listener for tab visibility changes
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            // Log tab switch
            fetch('../api/assessments/log_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    activity_type: 'tab_switch',
                    assignment_id: document.querySelector('[data-assignment-id]').dataset.assignmentId
                })
            });
        }
    });
}

/**
 * Submit assignment
 */
function submitAssignment(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    
    const assignmentId = form.dataset.assignmentId;
    const formData = new FormData(form);
    formData.append('assignment_id', assignmentId);
    
    // Basic validation to ensure answers are provided or files uploaded
    const textAnswers = form.querySelectorAll('[name^="answers"]');
    const fileInputs = form.querySelectorAll('input[type="file"][name^="files"]');
    let hasResponse = false;
    
    textAnswers.forEach(field => {
        if (field.value && field.value.trim() !== '') {
            hasResponse = true;
        }
    });
    
    // If no textual answers, check for general submission textarea
    if (!hasResponse) {
        const generalSubmission = form.querySelector('textarea[name="submission"]');
        if (generalSubmission && generalSubmission.value.trim() !== '') {
            hasResponse = true;
        }
    }
    
    // If still no response, check files
    if (!hasResponse) {
        fileInputs.forEach(input => {
            if (input.files && input.files.length > 0) {
                hasResponse = true;
            }
        });
    }
    
    if (!hasResponse) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        showMessage('Please complete the assignment questions before submitting.', 'error');
        return;
    }
    
    let basePath = '';
    const path = window.location.pathname;
    if (path.includes('/dashboard/') || path.includes('/pages/')) {
        basePath = '../';
    }
    
    fetch(basePath + 'api/jobs/assignment_submit.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        
        if (data.success) {
            showMessage(data.message || 'Assignment submitted successfully!', 'success');
            
            // Reload page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showMessage(data.message || 'Failed to submit assignment', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting assignment:', error);
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        showMessage('An error occurred. Please try again.', 'error');
    });
}

/**
 * Review assignment
 */
function reviewAssignment(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Reviewing...';
    
    const assignmentId = form.dataset.assignmentId;
    const status = form.querySelector('select[name="status"]').value;
    const feedback = form.querySelector('textarea[name="feedback"]').value;
    
    fetch('../api/jobs/assignment.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            assignment_id: assignmentId,
            action: 'review',
            status: status,
            feedback: feedback
        })
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        
        if (data.success) {
            showMessage('Assignment reviewed successfully!', 'success');
            
            // Reload page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showMessage(data.message || 'Failed to review assignment', 'error');
        }
    })
    .catch(error => {
        console.error('Error reviewing assignment:', error);
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        showMessage('An error occurred. Please try again.', 'error');
    });
}

// Import utility function
/**
 * Show message to user
 */
function showMessage(message, type = 'info') {
    // Use the utility function
    JobPortalUtils.showNotification(message, type);
}