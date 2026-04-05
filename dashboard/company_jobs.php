<?php
// Include database connection
require_once '../config/database.php';
require_once '../includes/functions.php';
session_start();

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'company') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get company information
$company = $db->fetch("SELECT name FROM companies WHERE user_id = ?", [$user_id]);
$company_name = $company ? $company['name'] : 'Company';

// Get company jobs
$jobs = $db->fetchAll("SELECT id, title, description, location, salary, job_type, 
                      created_at, status, 
                      (SELECT COUNT(*) FROM job_applications WHERE job_id = jobs.id) AS application_count
                      FROM jobs WHERE company_id = ? ORDER BY created_at DESC", [$user_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs | Company Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="dashboard-content">
            <header class="dashboard-header">
                <h1>Manage Jobs</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($company_name); ?></span>
                </div>
            </header>

            <div class="dashboard-section active">
                <div class="section-header">
                    <h2>Your Posted Jobs</h2>
                    <button id="createJobBtn" class="btn btn-primary">Create New Job</button>
                </div>
                
                <div class="table-responsive">
                    <table class="dashboard-table" id="jobsTable">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Location</th>
                                <th>Job Type</th>
                                <th>Posted Date</th>
                                <th>Applications</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($jobs->num_rows > 0): ?>
                                <?php while ($job = $jobs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                                        <td><?php echo htmlspecialchars($job['job_type']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                        <td><?php echo $job['application_count']; ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($job['status']); ?>"><?php echo ucfirst($job['status']); ?></span></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-icon view-job" data-id="<?php echo $job['id']; ?>"><i class="fas fa-eye"></i></button>
                                                <button class="btn-icon edit-job" data-id="<?php echo $job['id']; ?>"><i class="fas fa-edit"></i></button>
                                                <button class="btn-icon delete-job" data-id="<?php echo $job['id']; ?>"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No jobs posted yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Modal -->
    <div id="jobModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 id="jobModalTitle">Create New Job</h2>
            <form id="jobForm">
                <input type="hidden" id="jobId">
                <div class="form-group">
                    <label for="jobTitle">Job Title</label>
                    <input type="text" id="jobTitle" name="title" required>
                </div>
                <div class="form-group">
                    <label for="jobLocation">Location</label>
                    <input type="text" id="jobLocation" name="location" required>
                </div>
                <div class="form-group">
                    <label for="jobType">Job Type</label>
                    <select id="jobType" name="job_type" required>
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                        <option value="Internship">Internship</option>
                        <option value="Remote">Remote</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="jobSalary">Salary (optional)</label>
                    <input type="text" id="jobSalary" name="salary" placeholder="e.g. $50,000 - $70,000">
                </div>
                <div class="form-group">
                    <label for="jobDescription">Job Description</label>
                    <textarea id="jobDescription" name="description" rows="6" required></textarea>
                </div>
                <div class="form-group">
                    <label for="jobRequirements">Requirements</label>
                    <textarea id="jobRequirements" name="requirements" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="jobStatus">Status</label>
                    <select id="jobStatus" name="status">
                        <option value="Active">Active</option>
                        <option value="Draft">Draft</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" id="cancelJobBtn" class="btn btn-secondary">Cancel</button>
                    <button type="submit" id="saveJobBtn" class="btn btn-primary">Save Job</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/company-dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const modal = document.getElementById('jobModal');
            const createJobBtn = document.getElementById('createJobBtn');
            const closeModal = document.querySelector('.close-modal');
            const cancelJobBtn = document.getElementById('cancelJobBtn');
            
            createJobBtn.addEventListener('click', function() {
                document.getElementById('jobModalTitle').textContent = 'Create New Job';
                document.getElementById('jobForm').reset();
                document.getElementById('jobId').value = '';
                modal.style.display = 'block';
            });
            
            closeModal.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            cancelJobBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Edit job functionality
            document.querySelectorAll('.edit-job').forEach(button => {
                button.addEventListener('click', function() {
                    const jobId = this.getAttribute('data-id');
                    // Fetch job details and populate form
                    fetch(`../api/company/jobs.php?action=get&id=${jobId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const job = data.job;
                                document.getElementById('jobModalTitle').textContent = 'Edit Job';
                                document.getElementById('jobId').value = job.id;
                                document.getElementById('jobTitle').value = job.title;
                                document.getElementById('jobLocation').value = job.location;
                                document.getElementById('jobType').value = job.job_type;
                                document.getElementById('jobSalary').value = job.salary;
                                document.getElementById('jobDescription').value = job.description;
                                document.getElementById('jobRequirements').value = job.requirements;
                                document.getElementById('jobStatus').value = job.status;
                                modal.style.display = 'block';
                            }
                        });
                });
            });
            
            // Form submission
            document.getElementById('jobForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const jobId = document.getElementById('jobId').value;
                const formData = new FormData(this);
                
                // Add action based on whether we're creating or updating
                formData.append('action', jobId ? 'update' : 'create');
                if (jobId) {
                    formData.append('id', jobId);
                }
                
                fetch('../api/company/jobs.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modal.style.display = 'none';
                        // Reload page to show updated job list
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
            
            // Delete job functionality
            document.querySelectorAll('.delete-job').forEach(button => {
                button.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this job?')) {
                        const jobId = this.getAttribute('data-id');
                        
                        fetch('../api/company/jobs.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=delete&id=${jobId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove row from table
                                this.closest('tr').remove();
                                // If no more jobs, show "No jobs" message
                                if (document.querySelectorAll('#jobsTable tbody tr').length === 0) {
                                    const tbody = document.querySelector('#jobsTable tbody');
                                    const tr = document.createElement('tr');
                                    tr.innerHTML = '<td colspan="7" class="text-center">No jobs posted yet</td>';
                                    tbody.appendChild(tr);
                                }
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                    }
                });
            });
            
            // View job details
            document.querySelectorAll('.view-job').forEach(button => {
                button.addEventListener('click', function() {
                    const jobId = this.getAttribute('data-id');
                    window.location.href = `../job_detail.php?id=${jobId}`;
                });
            });
        });
    </script>
</body>
</html>