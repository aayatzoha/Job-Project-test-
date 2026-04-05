<?php
require_once '../config/database.php';
session_start();

// Enforce company role, redirect to role-specific login preserving return URL
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'company') {
    $redirect = urlencode('create-assignment.php');
    header('Location: assignment-login-company.php?redirect=' . $redirect);
    exit();
}

// Get available jobs for dropdown
$db = new Database();
$company_id = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$_SESSION['user_id']])['id'] ?? 0;
$jobs = $db->fetchAll("SELECT id, title FROM jobs WHERE company_id = ? ORDER BY created_at DESC", [$company_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .container { max-width: 900px; margin: 32px auto; padding: 16px; }
        .header { display:flex; align-items:center; justify-content:space-between; }
        .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .full { grid-column: 1 / -1; }
        label { font-weight: 600; margin-bottom: 6px; display:block; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; }
        .actions { margin-top: 20px; display:flex; gap:12px; }
        .btn-primary { background:#1f6feb; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; }
        .btn-secondary { background:#6b7280; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; }
    </style>
    <script>
        async function submitAssignment(e) {
            e.preventDefault();
            const form = document.getElementById('assignmentForm');
            const formData = new FormData(form);

            // Basic client-side validation
            const title = form.title.value.trim();
            const jobId = form.job_id.value;
            const description = form.description.value.trim();
            const dueDate = form.due_date.value.trim();
            if (!title || !jobId) {
                alert('Please provide Assignment Title and select a Job.');
                return;
            }
            if (!description) {
                alert('Please provide Assignment Instructions/Description.');
                return;
            }
            if (!dueDate) {
                alert('Please provide a Due Date.');
                return;
            }

            try {
                const res = await fetch('/job/api/jobs/assignment.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });

                // Detect browser following a redirect (often to login), which yields HTML
                if (res.redirected) {
                    console.error('Request was redirected to', res.url);
                    alert('Your session may have expired. Please log in again.');
                    return;
                }

                const contentType = res.headers.get('content-type') || '';
                const bodyText = await res.text();
                let parsed = null;
                if (contentType.includes('application/json')) {
                    try { parsed = JSON.parse(bodyText); } catch {}
                }

                // Handle non-2xx status. If 404, route to diagnostic page
                if (!res.ok) {
                    console.error('Server error creating assignment', res.status, bodyText);
                    if (res.status === 404) {
                        window.location.href = '/job/pages/unexpected-response.html';
                        return;
                    }
                    const msg = (parsed && parsed.message) ? parsed.message : bodyText.slice(0, 300);
                    alert(`Server error (${res.status}): ${msg}`);
                    return;
                }

                // If server returned HTML (e.g., login page), route to diagnostic page
                if (!parsed) {
                    console.error('Unexpected non-JSON response:', bodyText.slice(0, 300));
                    window.location.href = '/job/pages/unexpected-response.html';
                    return;
                }

                if (parsed.success) {
                    alert('Assignment created successfully');
                    if (parsed.assignment_id) {
                        window.location.href = `/job/pages/assignment-questions.php?id=${parsed.assignment_id}`;
                    } else {
                        window.location.href = '../dashboard/company.php';
                    }
                } else {
                    alert(parsed.message || 'Failed to create assignment');
                }
            } catch (err) {
                // Network or other fetch-level error
                console.error('Network or fetch error creating assignment:', err);
                if (err instanceof TypeError) {
                    alert('Network error: could not reach the server. Is Apache/PHP running?');
                } else {
                    alert('An unexpected error occurred while creating the assignment. See console for details.');
                }
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Create New Assignment</h2>
            <a class="btn-secondary" href="../dashboard/company.php">Back to Company Dashboard</a>
        </div>
        <form id="assignmentForm" onsubmit="submitAssignment(event)">
            <div class="form-grid">
                <div>
                    <label>Assignment Title</label>
                    <input type="text" name="title" required placeholder="e.g., Technical Screening">
                </div>
                <div>
                    <label>Select Job</label>
                    <select name="job_id" required>
                        <option value="">Choose a job...</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?php echo $job['id']; ?>"><?php echo htmlspecialchars($job['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($jobs)): ?>
                        <p style="color: #dc2626; font-size: 14px; margin-top: 5px;">
                            No jobs found. <a href="../dashboard/company.php" style="color: #1f6feb;">Create a job first</a>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <label>Requires Camera Recording</label>
                    <select name="requires_camera">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div>
                    <label>Due Date</label>
                    <input type="date" name="due_date" required>
                </div>
                <div class="full">
                    <label>Instructions / Description</label>
                    <textarea name="description" rows="5" required placeholder="Provide candidates with instructions for this assignment"></textarea>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="btn-primary">Create Assignment</button>
                <button type="reset" class="btn-secondary">Reset</button>
            </div>
        </form>
    </div>
</body>
</html>
