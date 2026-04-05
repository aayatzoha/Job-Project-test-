<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = getUserType();

// Get job ID if provided
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;

// Page title
$page_title = 'Assignments';

// Get job details if job_id is provided
$job = null;
if ($job_id) {
    $job = $db->fetch("SELECT * FROM jobs WHERE id = ?", [$job_id]);
    if ($job) {
        $page_title = 'Assignments for ' . $job['title'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AI Job System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2196F3">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="content-header">
                <h1><?php echo $page_title; ?></h1>
                <?php if ($user_type === 'company' || $user_type === 'admin'): ?>
                <a href="create_assignment.php<?php echo $job_id ? "?job_id=$job_id" : ''; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Assignment
                </a>
                <?php endif; ?>
            </div>
            
            <div id="message-container">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
            </div>
            
            <?php if ($job): ?>
            <div class="job-info-box">
                <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                <p><?php echo htmlspecialchars(substr($job['description'], 0, 150)) . '...'; ?></p>
            </div>
            <?php endif; ?>
            
            <div id="assignments-container" class="assignments-container">
                <div class="loading">Loading assignments...</div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/assignments.js?v=<?php echo time(); ?>"></script>
    <script>
    window.logout = function () {
        if (confirm('Are you sure you want to logout?')) {
            fetch('api/auth/logout.php', { credentials: 'same-origin' })
                .then(() => { window.location.href = 'index.php'; })
                .catch(() => { window.location.href = 'index.php'; });
        }
    };
    </script>
</body>
</html>