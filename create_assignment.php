<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = getUserType();

// Only companies and admins can create assignments
if ($user_type !== 'company' && $user_type !== 'admin') {
    $_SESSION['error'] = 'You do not have permission to create assignments';
    header('Location: dashboard/index.php');
    exit();
}

// Check if editing an existing assignment
$edit_mode = false;
$assignment = null;
$assignment_questions = [];

if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $assignment_id = intval($_GET['edit']);
    $assignment = $db->fetch("SELECT * FROM assignments WHERE id = ?", [$assignment_id]);
    
    if ($assignment) {
        $edit_mode = true;
        $job_id = $assignment['job_id'];
        
        // Check if company has access to this assignment
        if ($user_type === 'company') {
            $company_id = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id])['id'] ?? 0;
            $job = $db->fetch("SELECT company_id FROM jobs WHERE id = ?", [$job_id]);
            
            if ($job['company_id'] != $company_id) {
                $_SESSION['error'] = 'You do not have permission to edit this assignment';
                header('Location: dashboard/index.php');
                exit();
            }
        }
        
        // Get assignment questions
        $assignment_questions = $db->fetchAll(
            "SELECT * FROM assignment_questions WHERE assignment_id = ? ORDER BY id",
            [$assignment_id]
        );
    }
} else {
    // Get job ID if provided
    $job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;

    // If job_id is provided, verify access
    if ($job_id) {
        $job = $db->fetch("SELECT * FROM jobs WHERE id = ?", [$job_id]);
        
        if (!$job) {
            $_SESSION['error'] = 'Job not found';
            header('Location: dashboard/index.php');
            exit();
        }
        
        // Check if company has access to this job
        if ($user_type === 'company') {
            $company_id = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id])['id'] ?? 0;
            if ($job['company_id'] != $company_id) {
                $_SESSION['error'] = 'You do not have permission to create assignments for this job';
                header('Location: dashboard/index.php');
                exit();
            }
        }
    }
}

// Get available jobs for dropdown
if ($user_type === 'company') {
    $company_id = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id])['id'] ?? 0;
    $jobs = $db->fetchAll("SELECT id, title FROM jobs WHERE company_id = ? ORDER BY created_at DESC", [$company_id]);
} else {
    // Admin can see all jobs
    $jobs = $db->fetchAll("SELECT j.id, j.title, c.company_name FROM jobs j JOIN companies c ON j.company_id = c.id ORDER BY j.created_at DESC LIMIT 100");
}

// Get job seekers for assignment
if ($job_id) {
    $job_seekers = $db->fetchAll(
        "SELECT u.id, u.name, u.email 
         FROM users u
         JOIN job_applications ja ON u.id = ja.job_seeker_id
         WHERE ja.job_id = ? AND u.user_type = 'job_seeker'
         ORDER BY u.name",
        [$job_id]
    );
} else {
    $job_seekers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_mode ? 'Edit' : 'Create'; ?> Assignment - AI Job System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/assignments.css?v=<?php echo time(); ?>">

    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2196F3">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="content-header">
                <h1><?php echo $edit_mode ? 'Edit' : 'Create'; ?> Assignment</h1>
                <a href="javascript:history.back()" class="btn btn-outline-primary"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
            
            <div id="message-container">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <form id="create-assignment-form">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" id="edit_mode" name="edit_mode" value="1">
                            <input type="hidden" id="assignment_id" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="job_id">Select Job</label>
                            <select id="job_id" name="job_id" class="form-control" required <?php echo ($job_id || $edit_mode) ? 'disabled' : ''; ?>>
                                <option value="">Select a job</option>
                                <?php foreach ($jobs as $job_item): ?>
                                    <option value="<?php echo $job_item['id']; ?>" <?php echo ($job_id == $job_item['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($job_item['title']); ?>
                                        <?php if ($user_type === 'admin' && isset($job_item['company_name'])): ?>
                                            (<?php echo htmlspecialchars($job_item['company_name']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($job_id): ?>
                                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="title">Assignment Title</label>
                            <input type="text" id="title" name="title" class="form-control" required value="<?php echo $edit_mode ? htmlspecialchars($assignment['title']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Assignment Description</label>
                            <textarea id="description" name="description" class="form-control" rows="6" required><?php echo $edit_mode ? htmlspecialchars($assignment['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="due_date">Due Date</label>
                            <input type="date" id="due_date" name="due_date" class="form-control" required value="<?php echo $edit_mode ? htmlspecialchars($assignment['due_date']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="assigned_to">Assign To (Optional)</label>
                            <select id="assigned_to" name="assigned_to" class="form-control">
                                <option value="">Select a candidate</option>
                                <?php foreach ($job_seekers as $seeker): ?>
                                    <option value="<?php echo $seeker['id']; ?>" <?php echo ($edit_mode && $assignment['assigned_to'] == $seeker['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($seeker['name']); ?> (<?php echo htmlspecialchars($seeker['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="message-container"></div>
                        
                        <div class="form-group">
                            <label>Assignment Questions</label>
                            <div id="questions-container">
                                <?php if ($edit_mode && !empty($assignment_questions)): ?>
                                    <?php foreach ($assignment_questions as $index => $question): ?>
                                        <div class="question-item">
                                            <div class="question-header">
                                                <label>Question <?php echo $index + 1; ?></label>
                                                <button type="button" class="btn-remove-question" onclick="removeQuestion(this)">Remove</button>
                                            </div>
                                            <div class="form-group">
                                                <textarea name="questions[]" class="form-control" rows="3" required placeholder="Enter your question here..."><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label>Question Type</label>
                                                <select name="question_types[]" class="form-control question-type" onchange="toggleOptions(this)">
                                                    <option value="text" <?php echo $question['question_type'] === 'text' ? 'selected' : ''; ?>>Text Answer</option>
                                                    <option value="multiple_choice" <?php echo $question['question_type'] === 'multiple_choice' ? 'selected' : ''; ?>>Multiple Choice</option>
                                                    <option value="file_upload" <?php echo $question['question_type'] === 'file_upload' ? 'selected' : ''; ?>>File Upload</option>
                                                </select>
                                            </div>
                                            <div class="options-container" style="display: <?php echo $question['question_type'] === 'multiple_choice' ? 'block' : 'none'; ?>">
                                                <label>Options (one per line)</label>
                                                <textarea name="options[]" class="form-control" rows="4" placeholder="Enter each option on a new line"><?php 
                                                    if (!empty($question['options'])) {
                                                        if (is_string($question['options'])) {
                                                            $options = json_decode($question['options'], true);
                                                            if (is_array($options)) {
                                                                echo htmlspecialchars(implode("\n", $options));
                                                            }
                                                        } else if (is_array($question['options'])) {
                                                            echo htmlspecialchars(implode("\n", array_column($question['options'], 'option_text')));
                                                        }
                                                    }
                                                ?></textarea>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="question-item">
                                        <div class="question-header">
                                            <label>Question 1</label>
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
                                            </select>
                                        </div>
                                        <div class="options-container" style="display: none;">
                                            <label>Options (one per line)</label>
                                            <textarea name="options[]" class="form-control" rows="4" placeholder="Enter each option on a new line"></textarea>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="add-question" class="btn btn-add-question">
                                <i class="fas fa-plus"></i> Add Question
                            </button>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Update' : 'Create'; ?> Assignment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/assignments.js?v=<?php echo time(); ?>"></script>
    <script>
        // Dynamic job selection to load candidates
        document.getElementById('job_id').addEventListener('change', function() {
            const jobId = this.value;
            if (jobId) {
                window.location.href = 'create_assignment.php?job_id=' + jobId;
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            // Handle question type changes
            document.addEventListener('change', function(e) {
                if (e.target && e.target.classList.contains('question-type')) {
                    const optionsContainer = e.target.closest('.question-item').querySelector('.options-container');
                    if (e.target.value === 'multiple_choice') {
                        optionsContainer.style.display = 'block';
                    } else {
                        optionsContainer.style.display = 'none';
                    }
                }
            });
            
            // Add new question
            document.getElementById('add-question').addEventListener('click', function() {
                const questionsContainer = document.getElementById('questions-container');
                const questionCount = questionsContainer.querySelectorAll('.question-item').length + 1;
                
                const newQuestion = document.createElement('div');
                newQuestion.className = 'question-item';
                newQuestion.innerHTML = `
                    <hr>
                    <div class="form-group">
                        <label>Question ${questionCount}</label>
                        <textarea name="questions[]" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Question Type</label>
                        <select name="question_types[]" class="form-control question-type">
                            <option value="text">Text Answer</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="file_upload">File Upload</option>
                        </select>
                    </div>
                    <div class="options-container" style="display: none;">
                        <div class="form-group">
                            <label>Options (one per line)</label>
                            <textarea name="options[]" class="form-control" rows="4"></textarea>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm remove-question">Remove Question</button>
                `;
                
                questionsContainer.appendChild(newQuestion);
            });
            
            // Remove question
            document.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-question')) {
                    e.target.closest('.question-item').remove();
                }
            });
            
            // Form submission
            document.getElementById('create-assignment-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Convert form data to JSON
                const formData = new FormData(this);
                const jsonData = {};
                
                formData.forEach((value, key) => {
                    // Handle arrays like questions[], question_types[], options[]
                    if (key.endsWith('[]')) {
                        const cleanKey = key.substring(0, key.length - 2);
                        if (!jsonData[cleanKey]) {
                            jsonData[cleanKey] = [];
                        }
                        jsonData[cleanKey].push(value);
                    } else {
                        jsonData[key] = value;
                    }
                });
                
                // Log the data being sent for debugging
                console.log('Sending data:', jsonData);
                
                // Make sure job_id is included and not disabled
                if (document.getElementById('job_id').disabled && !jsonData.job_id) {
                    const jobIdSelect = document.getElementById('job_id');
                    jsonData.job_id = jobIdSelect.options[jobIdSelect.selectedIndex].value;
                }
                
                fetch('api/jobs/assignment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(jsonData)
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        const editMode = document.getElementById('edit_mode');
                        const message = editMode && editMode.value ? 'Assignment updated successfully!' : 'Assignment created successfully!';
                        alert(message);
                        window.location.href = 'dashboard/company.php';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const editMode = document.getElementById('edit_mode');
                    alert('An error occurred while ' + (editMode && editMode.value ? 'updating' : 'creating') + ' the assignment.');
                });
            });
        });
    </script>
</body>
</html>