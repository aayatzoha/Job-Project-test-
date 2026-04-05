<?php
session_start();

// Simple gate: require company login
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'company') {
    http_response_code(302);
    header('Location: /job/pages/assignment-login-company.php');
    exit;
}

$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($assignment_id <= 0) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><body><p>Missing or invalid assignment id.</p></body></html>';
    exit;
}

// Load assignment details
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = new Database();
$user_id = $_SESSION['user_id'];

// Get assignment details
$assignment = $db->fetch(
    "SELECT a.*, j.title as job_title, j.company_id, c.company_name,
            u.name as creator_name
     FROM assignments a
     JOIN jobs j ON a.job_id = j.id
     JOIN companies c ON j.company_id = c.id
     LEFT JOIN users u ON a.created_by = u.id
     WHERE a.id = ?",
    [$assignment_id]
);

if (!$assignment) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><p>Assignment not found.</p></body></html>';
    exit;
}

// Verify ownership
$company = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id]);
if (!$company || (int)$company['id'] !== (int)$assignment['company_id']) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body><p>You do not have permission to view this assignment.</p></body></html>';
    exit;
}

// Format dates
$created_at = !empty($assignment['created_at']) ? date('M d, Y', strtotime($assignment['created_at'])) : 'N/A';
$due_date = !empty($assignment['due_date']) ? date('M d, Y', strtotime($assignment['due_date'])) : 'No due date';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Assignment Questions</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/assignments.css" />
    <style>
        .container { max-width: 900px; margin: 24px auto; padding: 16px; }
        .header { display: flex; justify-content: space-between; align-items: center; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-top: 16px; }
        .question-item { border-bottom: 1px solid #eee; padding: 12px 0; }
        .question-item:last-child { border-bottom: none; }
        .options { margin-top: 8px; }
        .options li { margin-left: 18px; }
        .btn { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid .full { grid-column: 1 / -1; }
        .error { color: #b91c1c; font-size: 0.9em; }
        .success { color: #166534; font-size: 0.9em; }
        .assignment-info { background: #f8f9fa; border-left: 4px solid #2563eb; padding: 16px; margin-bottom: 16px; border-radius: 4px; }
        .assignment-info h3 { margin: 0 0 12px 0; color: #1f2937; }
        .assignment-info p { margin: 6px 0; color: #4b5563; }
        .assignment-info .meta { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 12px; }
        .assignment-info .meta-item { display: flex; align-items: center; gap: 6px; }
        .assignment-info .meta-item strong { color: #374151; }
        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        .file-info { margin-top: 12px; padding: 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 4px; }
        .file-info a { color: #2563eb; text-decoration: none; }
        .file-info a:hover { text-decoration: underline; }
    </style>
    <script>
        const assignmentId = <?php echo json_encode($assignment_id, JSON_UNESCAPED_SLASHES); ?>;

        async function loadQuestions() {
            const container = document.getElementById('questions-list');
            container.innerHTML = '<p>Loading questions...</p>';
            try {
                const res = await fetch(`/job/api/jobs/assignment_questions.php?assignment_id=${assignmentId}`, { credentials: 'same-origin' });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Failed to load questions');
                const questions = data.questions || [];
                if (questions.length === 0) {
                    container.innerHTML = '<p>No questions yet. Add your first question below.</p>';
                    return;
                }
                let html = '';
                for (const q of questions) {
                    html += `<div class="question-item">
                        <div><strong>#${q.order_index ?? 0}</strong> ${escapeHtml(q.question_text)} <small>(${q.question_type})</small></div>
                        ${q.options && q.options.length ? `<div class="options"><strong>Options:</strong><ul>${q.options.map(o => `<li>${escapeHtml(o.option_text)}</li>`).join('')}</ul></div>` : ''}
                    </div>`;
                }
                container.innerHTML = html;
            } catch (err) {
                console.error('Error loading questions', err);
                container.innerHTML = `<p class="error">Error loading questions: ${err.message}</p>`;
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function onTypeChange(sel) {
            const optionsBox = document.getElementById('options-box');
            optionsBox.style.display = sel.value === 'multiple_choice' ? 'block' : 'none';
        }

        async function submitQuestion(e) {
            e.preventDefault();
            const msg = document.getElementById('submit-msg');
            msg.textContent = '';
            try {
                const fd = new FormData(document.getElementById('question-form'));
                fd.append('assignment_id', String(assignmentId));
                const res = await fetch('/job/api/jobs/assignment_questions.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json();
                if (res.ok && data.success) {
                    msg.className = 'success';
                    msg.textContent = 'Question added';
                    (document.getElementById('question_text')).value = '';
                    (document.getElementById('order_index')).value = '';
                    (document.getElementById('question_type')).value = 'text';
                    (document.getElementById('options')).value = '';
                    onTypeChange(document.getElementById('question_type'));
                    loadQuestions();
                } else {
                    msg.className = 'error';
                    msg.textContent = data.message || `Failed (${res.status})`;
                }
            } catch (err) {
                console.error('Create question error', err);
                msg.className = 'error';
                msg.textContent = err.message;
            }
        }

        function copyShareLink() {
            const input = document.getElementById('share-link-display');
            if (input) {
                input.select();
                input.setSelectionRange(0, 99999);
                try {
                    document.execCommand('copy');
                    alert('Share link copied to clipboard!');
                } catch (err) {
                    navigator.clipboard.writeText(input.value).then(() => {
                        alert('Share link copied to clipboard!');
                    }).catch(() => {
                        alert('Please manually copy the link: ' + input.value);
                    });
                }
            }
        }

        async function generateAssignment() {
            if (!confirm('This will generate assignment questions based on the job requirements. Continue?')) {
                return;
            }
            
            try {
                const res = await fetch(`/job/api/jobs/generate_assignment.php?assignment_id=${assignmentId}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                const data = await res.json();
                
                if (data.success) {
                    alert(`Successfully generated ${data.questions_generated || 0} questions!`);
                    loadQuestions();
                } else {
                    alert('Error: ' + (data.message || 'Failed to generate assignment'));
                }
            } catch (err) {
                console.error('Generate assignment error', err);
                alert('Error generating assignment. Please try again.');
            }
        }

        async function postAssignmentToJob() {
            if (!confirm('Post this assignment to the job posting? This will make it visible to job seekers who apply for this job.')) {
                return;
            }
            
            try {
                const response = await fetch('/job/api/jobs/post_assignment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ assignment_id: assignmentId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Assignment posted to job successfully!');
                    // Reload page to show updated status
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to post assignment'));
                }
            } catch (error) {
                console.error('Error posting assignment:', error);
                alert('Error posting assignment. Please try again.');
            }
        }
        
        async function saveAssignmentToJobRole() {
            if (!confirm('Save this assignment template to the job role for future use?')) {
                return;
            }
            
            try {
                const res = await fetch(`/job/api/jobs/save_assignment_to_role.php`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ assignment_id: assignmentId })
                });
                
                const responseText = await res.text();
                console.log('Response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseErr) {
                    console.error('JSON parse error:', parseErr);
                    alert('Server error: ' + responseText.substring(0, 200));
                    return;
                }
                
                if (data.success) {
                    alert('Assignment saved to job role successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to save assignment'));
                }
            } catch (err) {
                console.error('Save assignment error', err);
                alert('Error saving assignment: ' + err.message);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadQuestions();
            onTypeChange(document.getElementById('question_type'));
        });
    </script>
    </head>
<body>
    <div class="container">
        <div class="header">
            <h2>Assignment #<?php echo htmlspecialchars((string)$assignment_id); ?> — Manage Questions</h2>
            <a class="btn btn-secondary" href="../dashboard/company.php">Back to Dashboard</a>
        </div>

        <!-- Assignment Details -->
        <div class="card assignment-info">
            <h3><?php echo htmlspecialchars($assignment['title'] ?? 'Untitled Assignment'); ?></h3>
            <?php if (!empty($assignment['description'])): ?>
                <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
            <?php endif; ?>
            <div class="meta">
                <div class="meta-item">
                    <strong>Job:</strong>
                    <span><?php echo htmlspecialchars($assignment['job_title'] ?? 'N/A'); ?></span>
                </div>
                <div class="meta-item">
                    <strong>Company:</strong>
                    <span><?php echo htmlspecialchars($assignment['company_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="meta-item">
                    <strong>Status:</strong>
                    <span class="status-badge status-<?php echo htmlspecialchars($assignment['status'] ?? 'pending'); ?>">
                        <?php echo ucfirst(htmlspecialchars($assignment['status'] ?? 'pending')); ?>
                    </span>
                </div>
                <div class="meta-item">
                    <strong>Created:</strong>
                    <span><?php echo $created_at; ?></span>
                </div>
                <div class="meta-item">
                    <strong>Due Date:</strong>
                    <span><?php echo $due_date; ?></span>
                </div>
                <?php if (!empty($assignment['requires_camera'])): ?>
                <div class="meta-item">
                    <strong>📹 Camera:</strong>
                    <span>Required</span>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($assignment['quiz_file'])): ?>
            <div class="file-info">
                <strong>📎 Uploaded Quiz File:</strong>
                <a href="../<?php echo htmlspecialchars($assignment['quiz_file']); ?>" target="_blank" download>
                    <?php echo htmlspecialchars(basename($assignment['quiz_file'])); ?>
                </a>
            </div>
            <?php endif; ?>
            <?php if (!empty($assignment['is_public']) && !empty($assignment['share_token'])): ?>
            <div class="file-info" style="background: #eff6ff; border-color: #3b82f6;">
                <strong>🌐 Public Quiz:</strong>
                <p style="margin: 8px 0 4px 0;">This assignment is publicly accessible.</p>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="text" id="share-link-display" 
                           value="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/quiz.php?token=' . $assignment['share_token']); ?>" 
                           readonly style="flex: 1; padding: 6px; border: 1px solid #3b82f6; border-radius: 4px; background: #fff;">
                    <button onclick="copyShareLink()" class="btn btn-primary" style="padding: 6px 12px;">Copy Link</button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Existing Questions</h3>
            <div id="questions-list"></div>
        </div>

        <!-- Generate Assignment Button -->
        <div class="card" style="background: #eff6ff; border-color: #3b82f6;">
            <h3 style="margin-top: 0;">⚡ Quick Actions</h3>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <button onclick="generateAssignment()" class="btn btn-primary" style="padding: 10px 20px; font-size: 1em;">
                    🤖 Generate Assignment for Job Role
                </button>
                <button onclick="saveAssignmentToJobRole()" class="btn btn-primary" style="padding: 10px 20px; font-size: 1em; background: #10b981;">
                    💾 Save Assignment to Job Role
                </button>
                <button onclick="postAssignmentToJob()" class="btn btn-primary" style="padding: 10px 20px; font-size: 1em; background: #2563eb;">
                    📢 Post Assignment to Job Posting
                </button>
            </div>
            <p style="margin-top: 12px; color: #4b5563; font-size: 0.9em;">
                <strong>Generate:</strong> Auto-create assignment questions based on job requirements.<br>
                <strong>Save to Job Role:</strong> Link this assignment to the job role for future use.<br>
                <strong>Post to Job Posting:</strong> Make this assignment visible to job seekers who apply for this job.
            </p>
        </div>

        <div class="card">
            <h3>Add a Question</h3>
            <form id="question-form" onsubmit="submitQuestion(event)">
                <div class="form-grid">
                    <div class="full">
                        <label>Question Text *</label>
                        <textarea id="question_text" name="question_text" class="form-control" rows="3" required placeholder="Enter your question..."></textarea>
                    </div>
                    <div>
                        <label>Question Type *</label>
                        <select id="question_type" name="question_type" class="form-control" onchange="onTypeChange(this)">
                            <option value="text">Text Answer</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="file_upload">File Upload</option>
                        </select>
                    </div>
                    <div>
                        <label>Order Index</label>
                        <input type="number" id="order_index" name="order_index" class="form-control" placeholder="0" />
                    </div>
                </div>
                <div id="options-box" class="full" style="display:none; margin-top:8px;">
                    <label>Options (one per line)</label>
                    <textarea id="options" name="options" class="form-control" rows="4" placeholder="Option A\nOption B\nOption C"></textarea>
                </div>
                <div style="margin-top:12px; display:flex; gap:8px;">
                    <button class="btn btn-primary" type="submit">Add Question</button>
                    <span id="submit-msg"></span>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

