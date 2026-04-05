<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserType() !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserInfo($user_id);

// Get system statistics
$stats = $db->fetch(
    "SELECT 
        (SELECT COUNT(*) FROM users WHERE user_type = 'job_seeker') as total_job_seekers,
        (SELECT COUNT(*) FROM users WHERE user_type = 'company') as total_companies,
        (SELECT COUNT(*) FROM jobs WHERE status = 'active') as active_jobs,
        (SELECT COUNT(*) FROM job_applications) as total_applications,
        (SELECT COUNT(*) FROM user_assessments WHERE status = 'completed') as completed_assessments,
        (SELECT COUNT(*) FROM ai_recommendations) as total_recommendations
    "
);

// Get recent activity
$recent_activity = $db->fetchAll(
    "SELECT al.*, u.name as user_name, u.user_type
     FROM activity_logs al
     JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC
     LIMIT 10"
);

// Get system health metrics
$system_health = $db->fetch(
    "SELECT 
        (SELECT COUNT(*) FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 7 DAY)) as active_users_week,
        (SELECT COUNT(*) FROM jobs WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_jobs_week,
        (SELECT COUNT(*) FROM job_applications WHERE application_date > DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_applications_week,
        (SELECT AVG(percentage_score) FROM user_assessments WHERE status = 'completed' AND completed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as avg_assessment_score
    "
);

// Get top performing jobs
$top_jobs = $db->fetchAll(
    "SELECT j.title, c.company_name, COUNT(ja.id) as application_count
     FROM jobs j
     JOIN companies c ON j.company_id = c.id
     LEFT JOIN job_applications ja ON j.id = ja.job_id
     GROUP BY j.id
     ORDER BY application_count DESC
     LIMIT 5"
);

// Get user registrations by month
$user_registrations = $db->fetchAll(
    "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count,
        user_type
     FROM users 
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m'), user_type
     ORDER BY month DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AI Job System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#2196F3">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>AI Job System</h2>
                <p>Admin Dashboard</p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="active">
                        <a href="#dashboard" onclick="showSection('dashboard')">
                            <span class="icon">📊</span>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="#users" onclick="showSection('users')">
                            <span class="icon">👥</span>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="#system" onclick="showSection('system')">
                            <span class="icon">⚙️</span>
                            System
                        </a>
                    </li>
                    <li>
                        <a href="#account" onclick="showSection('account')">
                            <span class="icon">🔑</span>
                            Account
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                        <p>System Administrator</p>
                    </div>
                </div>
                <button class="btn btn-secondary" onclick="logout()">Logout</button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Admin Dashboard</h1>
                    <p>Monitor and manage the AI Job Recommendation System</p>
                </div>
                <div class="header-right">
                    <div class="system-status">
                        <span class="status-indicator online"></span>
                        <span>System Online</span>
                    </div>
                </div>
            </header>

            <!-- Dashboard Section -->
            <section id="dashboard-section" class="dashboard-section active">
                <!-- Real-time Stats Grid -->
                <div class="stats-grid" id="real-time-stats">
                    <div class="stat-card trending-up" data-metric="job_seekers">
                        <div class="stat-header">
                            <div class="stat-icon">👤</div>
                            <div class="stat-trend" id="job-seekers-trend">+0%</div>
                        </div>
                        <div class="stat-content">
                            <h3 id="job-seekers-count"><?php echo $stats['total_job_seekers'] ?? 0; ?></h3>
                            <p>Job Seekers</p>
                            <small class="stat-subtitle">+<span id="new-job-seekers">0</span> this week</small>
                        </div>
                    </div>
                    
                    <div class="stat-card trending-up" data-metric="companies">
                        <div class="stat-header">
                            <div class="stat-icon">🏢</div>
                            <div class="stat-trend" id="companies-trend">+0%</div>
                        </div>
                        <div class="stat-content">
                            <h3 id="companies-count"><?php echo $stats['total_companies'] ?? 0; ?></h3>
                            <p>Companies</p>
                            <small class="stat-subtitle">+<span id="new-companies">0</span> this week</small>
                        </div>
                    </div>
                    
                    <div class="stat-card trending-up" data-metric="jobs">
                        <div class="stat-header">
                            <div class="stat-icon">💼</div>
                            <div class="stat-trend" id="jobs-trend">+0%</div>
                        </div>
                        <div class="stat-content">
                            <h3 id="active-jobs-count"><?php echo $stats['active_jobs'] ?? 0; ?></h3>
                            <p>Active Jobs</p>
                            <small class="stat-subtitle">+<span id="new-jobs">0</span> this week</small>
                        </div>
                    </div>
                    
                    <div class="stat-card trending-up" data-metric="applications">
                        <div class="stat-header">
                            <div class="stat-icon">📄</div>
                            <div class="stat-trend" id="applications-trend">+0%</div>
                        </div>
                        <div class="stat-content">
                            <h3 id="applications-count"><?php echo $stats['total_applications'] ?? 0; ?></h3>
                            <p>Applications</p>
                            <small class="stat-subtitle">+<span id="new-applications">0</span> today</small>
                        </div>
                    </div>
                    
                    <div class="stat-card" data-metric="assessments">
                        <div class="stat-header">
                            <div class="stat-icon">📋</div>
                            <div class="stat-trend" id="assessments-trend">0%</div>
                        </div>
                        <div class="stat-content">
                            <h3 id="assessments-count"><?php echo $stats['completed_assessments'] ?? 0; ?></h3>
                            <p>Assessments</p>
                            <small class="stat-subtitle"><span id="assessment-completion-rate">0</span>% completion rate</small>
                        </div>
                    </div>
                    
                    <div class="stat-card" data-metric="recommendations">
                        <div class="stat-header">
                            <div class="stat-icon">🎯</div>
                            <div class="stat-trend" id="recommendations-trend">0%</div>
                        </div>
                        <div class="stat-content">
                            <h3 id="recommendations-count"><?php echo $stats['total_recommendations'] ?? 0; ?></h3>
                            <p>AI Recommendations</p>
                            <small class="stat-subtitle"><span id="recommendation-success-rate">0</span>% success rate</small>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Analytics Row -->
                <div class="analytics-row">
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>📊 User Growth Trend</h3>
                            <div class="card-actions">
                                <select id="growth-period">
                                    <option value="7">Last 7 days</option>
                                    <option value="30" selected>Last 30 days</option>
                                    <option value="90">Last 90 days</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-content">
                            <canvas id="userGrowthChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>🎯 Job Match Success</h3>
                            <div class="match-stats">
                                <span class="match-rate" id="overall-match-rate">0%</span>
                            </div>
                        </div>
                        <div class="card-content">
                            <canvas id="matchSuccessChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- System Health -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>🏥 System Health</h3>
                        </div>
                        <div class="card-content">
                            <div class="health-metrics">
                                <div class="metric">
                                    <span class="metric-label">Active Users (7 days)</span>
                                    <span class="metric-value"><?php echo $system_health['active_users_week'] ?? 0; ?></span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">New Jobs (7 days)</span>
                                    <span class="metric-value"><?php echo $system_health['new_jobs_week'] ?? 0; ?></span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">New Applications (7 days)</span>
                                    <span class="metric-value"><?php echo $system_health['new_applications_week'] ?? 0; ?></span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Avg Assessment Score (30 days)</span>
                                    <span class="metric-value"><?php echo $system_health['avg_assessment_score'] ? number_format($system_health['avg_assessment_score'], 1) : 'N/A'; ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>📝 Recent Activity</h3>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recent_activity)): ?>
                                <div class="empty-state">
                                    <p>No recent activity</p>
                                </div>
                            <?php else: ?>
                                <div class="activity-list">
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">
                                                <?php
                                                switch ($activity['action']) {
                                                    case 'user_registered':
                                                        echo '👤';
                                                        break;
                                                    case 'login_successful':
                                                        echo '🔑';
                                                        break;
                                                    case 'job_created':
                                                        echo '💼';
                                                        break;
                                                    case 'application_submitted':
                                                        echo '📄';
                                                        break;
                                                    default:
                                                        echo '📝';
                                                }
                                                ?>
                                            </div>
                                            <div class="activity-content">
                                                <p class="activity-text">
                                                    <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                                    <?php echo htmlspecialchars($activity['action']); ?>
                                                </p>
                                                <span class="activity-time"><?php echo timeAgo($activity['created_at']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Performing Jobs -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>🏆 Top Performing Jobs</h3>
                        </div>
                        <div class="card-content">
                            <?php if (empty($top_jobs)): ?>
                                <div class="empty-state">
                                    <p>No job data available</p>
                                </div>
                            <?php else: ?>
                                <div class="top-jobs-list">
                                    <?php foreach ($top_jobs as $job): ?>
                                        <div class="job-item">
                                            <div class="job-info">
                                                <h4><?php echo htmlspecialchars($job['title']); ?></h4>
                                                <p class="company"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                            </div>
                                            <div class="job-metric">
                                                <span class="application-count"><?php echo $job['application_count']; ?> applications</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>⚡ Quick Actions</h3>
                        </div>
                        <div class="card-content">
                            <div class="quick-actions">
                                <button class="action-btn" onclick="showSection('users')">
                                    <span class="icon">👥</span>
                                    <span>Manage Users</span>
                                </button>
                                <button class="action-btn" onclick="showSection('jobs')">
                                    <span class="icon">💼</span>
                                    <span>Review Jobs</span>
                                </button>
                                <button class="action-btn" onclick="showSection('assessments')">
                                    <span class="icon">📋</span>
                                    <span>Manage Assessments</span>
                                </button>
                                <button class="action-btn" onclick="showSection('analytics')">
                                    <span class="icon">📈</span>
                                    <span>View Analytics</span>
                                </button>
                                <button class="action-btn" onclick="showSection('system')">
                                    <span class="icon">⚙️</span>
                                    <span>System Settings</span>
                                </button>
                                <button class="action-btn" onclick="exportData()">
                                    <span class="icon">📊</span>
                                    <span>Export Data</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Other sections (trimmed for minimal admin) -->
            <section id="users-section" class="dashboard-section">
                <div class="section-header">
                    <h2>👥 User Management</h2>
                    <p>Manage job seekers, companies, and admin users</p>
                </div>
                <div id="users-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="system-section" class="dashboard-section">
                <div class="section-header">
                    <h2>⚙️ System Management</h2>
                    <p>System configuration and maintenance</p>
                </div>
                <div id="system-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>


            <section id="account-section" class="dashboard-section">
                <div class="section-header">
                    <h2>🔑 Account</h2>
                    <p>Update your admin password</p>
                </div>
                <div id="account-content">
                    <form id="admin-password-form" onsubmit="return updateAdminPassword(event)">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" id="current-password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" id="new-password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" id="confirm-password" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@next/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/admin-dashboard.js"></script>
    <script src="../assets/js/admin-charts.js"></script>
    <script src="../assets/js/admin-assignments.js"></script>
    <script src="../assets/js/admin-user-management.js"></script>
    <script>
        // Initialize the admin dashboard manager
        document.addEventListener('DOMContentLoaded', function() {
            try {
                window.dashboardManager = new AdminDashboardManager();
                window.dashboardManager.init();
                console.log('Admin Dashboard Manager initialized successfully');
            } catch (error) {
                console.error('Failed to initialize Admin Dashboard Manager:', error);
                alert('Failed to initialize dashboard. Please refresh the page.');
            }
        });
        
        function showSection(sectionId) {
            if (!window.dashboardManager) {
                console.error('Dashboard manager not initialized');
                return;
            }
            window.dashboardManager.showSection(sectionId);
        }
    </script>
    <style>
        .custom-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .assignment-modal {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 500px;
            max-width: 90%;
        }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 4px;
            color: white;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 1001;
        }
        
        .toast.show {
            opacity: 1;
        }
        
        .toast-success {
            background-color: #28a745;
        }
        
        .toast-error {
            background-color: #dc3545;
        }
        
        .toast-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .toast-info {
            background-color: #17a2b8;
        }
    </style>
</body>
</html>
