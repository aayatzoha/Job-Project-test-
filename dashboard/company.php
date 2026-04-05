<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a company
requireLogin();
if (getUserType() !== 'company') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserInfo($user_id);

// Get company profile
$company = $db->fetch("SELECT * FROM companies WHERE user_id = ?", [$user_id]);

// Get company statistics
$stats = $db->fetch(
    "SELECT 
        COUNT(DISTINCT j.id) as total_jobs,
        COUNT(DISTINCT ja.id) as total_applications,
        COUNT(DISTINCT CASE WHEN j.status = 'active' THEN j.id END) as active_jobs,
        COUNT(DISTINCT CASE WHEN ja.status = 'screening' THEN ja.id END) as screening_applications
     FROM jobs j
     LEFT JOIN job_applications ja ON j.id = ja.job_id
     WHERE j.company_id = ?",
    [$company['id']]
);

// Get recent job postings
$recent_jobs = $db->fetchAll(
    "SELECT j.*, COUNT(ja.id) as application_count
     FROM jobs j
     LEFT JOIN job_applications ja ON j.id = ja.job_id
     WHERE j.company_id = ?
     GROUP BY j.id
     ORDER BY j.created_at DESC
     LIMIT 5",
    [$company['id']]
);

// Get recent applications
$recent_applications = $db->fetchAll(
    "SELECT ja.*, j.title as job_title, js.user_id, u.name as candidate_name, u.email as candidate_email
     FROM job_applications ja
     JOIN jobs j ON ja.job_id = j.id
     JOIN job_seekers js ON ja.job_seeker_id = js.id
     JOIN users u ON js.user_id = u.id
     WHERE j.company_id = ?
     ORDER BY ja.application_date DESC
     LIMIT 10",
    [$company['id']]
);

// Get AI-recommended candidates
$recommended_candidates = $db->fetchAll(
    "SELECT DISTINCT ja.*, j.title as job_title, js.user_id, u.name as candidate_name, 
            u.email as candidate_email, js.experience_years, js.skills,
            AVG(ua.percentage_score) as avg_assessment_score
     FROM job_applications ja
     JOIN jobs j ON ja.job_id = j.id
     JOIN job_seekers js ON ja.job_seeker_id = js.id
     JOIN users u ON js.user_id = u.id
     LEFT JOIN user_assessments ua ON js.user_id = ua.user_id
     WHERE j.company_id = ? AND ja.status IN ('applied', 'screening')
     GROUP BY ja.id
     ORDER BY avg_assessment_score DESC, ja.application_date DESC
     LIMIT 5",
    [$company['id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard - AI Job System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#2196F3">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>AI Job System</h2>
                <p>Company Dashboard</p>
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
                        <a href="#jobs" onclick="showSection('jobs')">
                            <span class="icon">💼</span>
                            Job Postings
                        </a>
                    </li>
                    <li>
                        <a href="#candidates" onclick="showSection('candidates')">
                            <span class="icon">👥</span>
                            Candidates
                        </a>
                    </li>
                    <li>
                        <a href="#applications" onclick="showSection('applications')">
                            <span class="icon">📄</span>
                            Applications
                        </a>
                    </li>
                    <li>
                        <a href="#assessments" onclick="showSection('assessments')">
                            <span class="icon">📋</span>
                            Assessments
                        </a>
                    </li>
                    <li>
                        <a href="#assignments" onclick="showSection('assignments')">
                            <span class="icon">📝</span>
                            Assignments
                        </a>
                    </li>
                    <li>
                        <a href="#analytics" onclick="showSection('analytics')">
                            <span class="icon">📈</span>
                            Analytics
                        </a>
                    </li>
                    <li>
                        <a href="#profile" onclick="showSection('profile')">
                            <span class="icon">🏢</span>
                            Company Profile
                        </a>
                    </li>
                    <li>
                        <a href="#settings" onclick="showSection('settings')">
                            <span class="icon">⚙️</span>
                            Settings
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($company['company_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($company['company_name']); ?></h4>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
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
                    <h1>Welcome, <?php echo htmlspecialchars($company['company_name']); ?>!</h1>
                    <p>Manage your job postings and find the best candidates</p>
                </div>
                <div class="header-right">
                    <button class="btn btn-primary" onclick="showSection('jobs')">
                        <span class="icon">➕</span>
                        Post New Job
                    </button>
                </div>
            </header>

            <!-- Dashboard Section -->
            <section id="dashboard-section" class="dashboard-section active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">💼</div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_jobs'] ?? 0; ?></h3>
                            <p>Total Jobs Posted</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📄</div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_applications'] ?? 0; ?></h3>
                            <p>Total Applications</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-content">
                            <h3><?php echo $stats['active_jobs'] ?? 0; ?></h3>
                            <p>Active Jobs</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🔍</div>
                        <div class="stat-content">
                            <h3><?php echo $stats['screening_applications'] ?? 0; ?></h3>
                            <p>Under Review</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Recent Job Postings -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>💼 Recent Job Postings</h3>
                            <a href="#jobs" onclick="showSection('jobs')" class="view-all">View All</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recent_jobs)): ?>
                                <div class="empty-state">
                                    <p>No job postings yet. Create your first job posting to start attracting candidates!</p>
                                    <button class="btn btn-primary" onclick="showSection('jobs')">Post First Job</button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_jobs as $job): ?>
                                    <div class="job-item">
                                        <div class="job-info">
                                            <h4><?php echo htmlspecialchars($job['title']); ?></h4>
                                            <p class="job-meta">
                                                <span class="location">📍 <?php echo htmlspecialchars($job['location']); ?></span>
                                                <span class="applications">📄 <?php echo $job['application_count']; ?> applications</span>
                                            </p>
                                        </div>
                                        <div class="job-status">
                                            <span class="status-badge status-<?php echo $job['status']; ?>">
                                                <?php echo ucfirst($job['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Applications -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>📄 Recent Applications</h3>
                            <a href="#applications" onclick="showSection('applications')" class="view-all">View All</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recent_applications)): ?>
                                <div class="empty-state">
                                    <p>No applications yet. Applications will appear here once candidates start applying to your jobs.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($recent_applications, 0, 5) as $app): ?>
                                    <div class="application-item">
                                        <div class="candidate-info">
                                            <h4><?php echo htmlspecialchars($app['candidate_name']); ?></h4>
                                            <p class="job-title"><?php echo htmlspecialchars($app['job_title']); ?></p>
                                            <p class="application-date"><?php echo timeAgo($app['application_date']); ?></p>
                                        </div>
                                        <div class="application-status">
                                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                                <?php echo ucfirst($app['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- AI-Recommended Candidates -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>🎯 AI-Recommended Candidates</h3>
                            <a href="#candidates" onclick="showSection('candidates')" class="view-all">View All</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recommended_candidates)): ?>
                                <div class="empty-state">
                                    <p>No recommended candidates yet. AI recommendations will appear based on job requirements and candidate profiles.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recommended_candidates as $candidate): ?>
                                    <div class="candidate-item">
                                        <div class="candidate-info">
                                            <h4><?php echo htmlspecialchars($candidate['candidate_name']); ?></h4>
                                            <p class="job-title"><?php echo htmlspecialchars($candidate['job_title']); ?></p>
                                            <p class="experience"><?php echo $candidate['experience_years']; ?> years experience</p>
                                        </div>
                                        <div class="candidate-score">
                                            <div class="score-circle">
                                                <span><?php echo $candidate['avg_assessment_score'] ? number_format($candidate['avg_assessment_score'], 0) : 'N/A'; ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
                                <button class="action-btn" onclick="showSection('jobs')">
                                    <span class="icon">➕</span>
                                    <span>Post New Job</span>
                                </button>
                                <button class="action-btn" onclick="showSection('candidates')">
                                    <span class="icon">🔍</span>
                                    <span>Search Candidates</span>
                                </button>
                                <button class="action-btn" onclick="showSection('assessments'); setTimeout(() => { if(window.dashboardManager) window.dashboardManager.showAssessmentCreationForm(); }, 300);">
                                    <span class="icon">📋</span>
                                    <span>Create Quiz/Assessment</span>
                                </button>
                                <button class="action-btn" onclick="showSection('analytics')">
                                    <span class="icon">📈</span>
                                    <span>View Analytics</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Other sections will be loaded dynamically -->
            <section id="jobs-section" class="dashboard-section">
                <div class="section-header">
                    <h2>💼 Job Postings</h2>
                    <p>Manage your job postings and attract the best candidates</p>
                </div>
                <div id="jobs-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="candidates-section" class="dashboard-section">
                <div class="section-header">
                    <h2>👥 Candidates</h2>
                    <p>Discover and manage potential candidates</p>
                </div>
                <div id="candidates-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="applications-section" class="dashboard-section">
                <div class="section-header">
                    <h2>📄 Applications</h2>
                    <p>Review and manage job applications</p>
                </div>
                <div id="applications-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="assessments-section" class="dashboard-section">
                <div class="section-header">
                    <h2>📋 Assessments</h2>
                    <p>Create and manage candidate assessments</p>
                </div>
                <div id="assessments-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="assignments-section" class="dashboard-section">
                <div class="section-header">
                    <h2>📝 Assignments</h2>
                    <p>Create and manage job assignments for candidates</p>
                </div>
                <div id="assignments-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="analytics-section" class="dashboard-section">
                <div class="section-header">
                    <h2>📈 Analytics</h2>
                    <p>Track your hiring performance and insights</p>
                </div>
                <div id="analytics-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="profile-section" class="dashboard-section">
                <div class="section-header">
                    <h2>🏢 Company Profile</h2>
                    <p>Manage your company information and branding</p>
                </div>
                <div id="profile-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="settings-section" class="dashboard-section">
                <div class="section-header">
                    <h2>⚙️ Settings</h2>
                    <p>Configure your account and system preferences</p>
                </div>
                <div id="settings-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>
        </main>
    </div>

    <script src="../assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/auth.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/dashboard.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/company-dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>
