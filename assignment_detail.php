<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
requireLogin();

// Get assignment ID
$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($assignment_id <= 0) {
    header('Location: dashboard/index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = getUserType();

// Get assignment details
try {
    $assignment = $db->fetch(
        "SELECT a.*, j.title as job_title, j.company_id, 
                c.company_name, c.logo as company_logo,
                u1.name as creator_name, u2.name as assignee_name
         FROM assignments a
         JOIN jobs j ON a.job_id = j.id
         JOIN companies c ON j.company_id = c.id
         LEFT JOIN users u1 ON a.created_by = u1.id
         LEFT JOIN users u2 ON a.assigned_to = u2.id
         WHERE a.id = ?",
        [$assignment_id]
    );

    if (!$assignment) {
        throw new Exception('Assignment not found');
    }

    // Check permissions
    $has_access = false;
    
    if ($user_type === 'admin') {
        $has_access = true;
    } elseif ($user_type === 'company') {
        $company_id = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id])['id'] ?? 0;
        if ($company_id == $assignment['company_id']) {
            $has_access = true;
        }
    } elseif ($user_type === 'job_seeker') {
        // Job seekers can view assignments assigned to them OR all unassigned assignments for active jobs
        if ($assignment['assigned_to'] == $user_id) {
            $has_access = true;
        } elseif (is_null($assignment['assigned_to'])) {
            // Check if job is active
            $job = $db->fetch("SELECT status FROM jobs WHERE id = ?", [$assignment['job_id']]);
            if ($job && $job['status'] === 'active') {
                $has_access = true;
            }
        }
    }
    
    if (!$has_access) {
        throw new Exception('You do not have permission to view this assignment');
    }
    
    // Check if user can submit
    // Job seekers can submit if: assigned to them OR unassigned assignment for active job, and status is pending
    $can_submit = false;
    if ($user_type === 'job_seeker' && $assignment['status'] === 'pending') {
        if ($assignment['assigned_to'] == $user_id) {
            $can_submit = true;
        } elseif (is_null($assignment['assigned_to'])) {
            $job = $db->fetch("SELECT status FROM jobs WHERE id = ?", [$assignment['job_id']]);
            if ($job && $job['status'] === 'active') {
                $can_submit = true;
            }
        }
    }
                  
    // Check if user can review
    $can_review = false;
    if ($assignment['status'] === 'submitted' || $assignment['status'] === 'reviewed') {
        if ($user_type === 'admin') {
            $can_review = true;
        } elseif ($user_type === 'company') {
            $company_id = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id])['id'] ?? 0;
            if ($company_id == $assignment['company_id']) {
                $can_review = true;
            }
        } elseif ($assignment['created_by'] == $user_id) {
            $can_review = true;
        }
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: dashboard/index.php');
    exit();
}

// Format dates
$created_at = new DateTime($assignment['created_at']);
$due_date = !empty($assignment['due_date']) ? new DateTime($assignment['due_date']) : null;
$submission_date = null;
$reviewed_at = null;

// Get submission details if exists
$submission = $db->fetch(
    "SELECT * FROM assignment_submissions WHERE assignment_id = ? AND user_id = ?",
    [$assignment_id, $user_id]
);
if ($submission) {
    $submission_date = new DateTime($submission['submission_date']);
}

// Get status class
$status_class = '';
switch ($assignment['status']) {
    case 'pending':
        $status_class = 'warning';
        break;
    case 'submitted':
        $status_class = 'info';
        break;
    case 'reviewed':
        $status_class = 'success';
        break;
    case 'completed':
        $status_class = 'success';
        break;
    default:
        $status_class = 'secondary';
}

// Get assignment questions
$questions = $db->fetchAll(
    "SELECT * FROM assignment_questions WHERE assignment_id = ? ORDER BY order_index",
    [$assignment_id]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Details - AI Job System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/assignments.css?v=<?php echo time(); ?>">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2196F3">
    
    <!-- Camera access script -->
    <script src="assets/js/camera-access.js?v=<?php echo time(); ?>" defer></script>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Assignment Details</h1>
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
            
            <div class="assignment-detail-container">
                <div class="assignment-header">
                    <div class="assignment-title">
                        <h2><?php echo htmlspecialchars($assignment['title']); ?></h2>
                        <span class="badge badge-<?php echo $status_class; ?>">
                            <?php echo ucfirst(htmlspecialchars($assignment['status'])); ?>
                        </span>
                    </div>
                    <div class="assignment-meta">
                        <div class="meta-item">
                            <span class="meta-label">Job:</span>
                            <span class="meta-value"><?php echo htmlspecialchars($assignment['job_title']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Company:</span>
                            <span class="meta-value"><?php echo htmlspecialchars($assignment['company_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Created by:</span>
                            <span class="meta-value"><?php echo htmlspecialchars($assignment['creator_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Created on:</span>
                            <span class="meta-value"><?php echo $created_at->format('M d, Y'); ?></span>
                        </div>
                        <?php if ($due_date): ?>
                        <div class="meta-item">
                            <span class="meta-label">Due date:</span>
                            <span class="meta-value"><?php echo $due_date->format('M d, Y'); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($assignment['requires_camera']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Camera:</span>
                            <span class="meta-value badge badge-warning">📹 Required</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($assignment['assignee_name']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Assigned to:</span>
                            <span class="meta-value"><?php echo htmlspecialchars($assignment['assignee_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="assignment-content">
                    <h3>Description</h3>
                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                    </div>
                    
                    <?php if ($submission): ?>
                    <h3>My Submission</h3>
                    <div class="submission-box">
                        <div class="submission-meta">
                            <span>Submitted on: <?php echo $submission_date->format('M d, Y h:i A'); ?></span>
                            <span class="badge badge-<?php echo $submission['status'] === 'reviewed' ? 'success' : 'info'; ?>">
                                <?php echo ucfirst($submission['status']); ?>
                            </span>
                        </div>
                        <?php if (!empty($submission['feedback'])): ?>
                        <div class="feedback-content" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                            <strong>Feedback:</strong>
                            <p><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                            <?php if ($submission['score']): ?>
                            <p><strong>Score:</strong> <?php echo $submission['score']; ?>%</p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($questions)): ?>
                    <h3>Assignment Questions</h3>
                    <div class="questions-box">
                        <?php foreach ($questions as $index => $question): ?>
                        <div class="question-item" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                            <h4>Question <?php echo $index + 1; ?></h4>
                            <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($can_submit || ($user_type === 'job_seeker' && !$submission)): ?>
                    <div class="submission-form-container">
                        <h3>Submit Assignment</h3>
                        <?php if ($assignment['requires_camera']): ?>
                        <div class="alert alert-info">
                            <strong>📹 Camera Required:</strong> This assignment requires camera access for proctoring. Please ensure your camera is working before starting.
                        </div>
                        <?php endif; ?>
                        <button id="start-assignment-btn" class="btn btn-primary start-assignment-btn" data-requires-camera="<?php echo $assignment['requires_camera'] ? '1' : '0'; ?>">Start Assignment</button>
                        <div id="assignment-content" class="hidden">
                            <form id="submit-assignment-form" data-assignment-id="<?php echo $assignment_id; ?>" enctype="multipart/form-data">
                                <?php if (!empty($questions)): ?>
                                    <?php foreach ($questions as $index => $question): ?>
                                    <div class="form-group">
                                        <label for="question_<?php echo $question['id']; ?>">
                                            Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($question['question_text']); ?>
                                        </label>
                                        <?php if ($question['question_type'] === 'text'): ?>
                                            <textarea id="question_<?php echo $question['id']; ?>" name="answers[<?php echo $question['id']; ?>]" class="form-control" rows="4" required></textarea>
                                        <?php elseif ($question['question_type'] === 'file_upload'): ?>
                                            <input type="file" id="question_<?php echo $question['id']; ?>" name="files[<?php echo $question['id']; ?>]" class="form-control" required>
                                        <?php elseif ($question['question_type'] === 'multiple_choice'): ?>
                                            <?php 
                                            $options = json_decode($question['options'], true);
                                            if (is_array($options)):
                                            ?>
                                            <select id="question_<?php echo $question['id']; ?>" name="answers[<?php echo $question['id']; ?>]" class="form-control" required>
                                                <option value="">Select an option</option>
                                                <?php foreach ($options as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <div class="form-group">
                                    <label for="submission">Your Submission</label>
                                    <textarea id="submission" name="submission" class="form-control" rows="6" required></textarea>
                                </div>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary">Submit Assignment</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($can_review): ?>
                    <div class="review-form-container">
                        <h3>Review Submission</h3>
                        <form id="review-assignment-form" data-assignment-id="<?php echo $assignment_id; ?>">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="">Select status</option>
                                    <option value="approved">Approve</option>
                                    <option value="rejected">Reject</option>
                                    <option value="pending">Request Changes</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="feedback">Feedback</label>
                                <textarea id="feedback" name="feedback" class="form-control" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/camera-modal.php'; ?>
    
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/assignments.js?v=<?php echo time(); ?>"></script>
</body>
</html>