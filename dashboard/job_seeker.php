<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a job seeker
requireLogin();
if (getUserType() !== 'job_seeker') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserInfo($user_id);

// Get job seeker profile
$profile = $db->fetch("SELECT * FROM job_seekers WHERE user_id = ?", [$user_id]);

// Get recent job recommendations
$recommendations = $db->fetchAll(
    "SELECT ar.*, j.title, j.description, j.location, j.job_type, j.salary_min, j.salary_max,
            c.company_name, c.industry
     FROM ai_recommendations ar
     JOIN jobs j ON ar.job_id = j.id
     JOIN companies c ON j.company_id = c.id
     WHERE ar.user_id = ? AND ar.status = 'active'
     ORDER BY ar.recommendation_score DESC
     LIMIT 10",
    [$user_id]
);

// Get recent applications
$applications = $db->fetchAll(
    "SELECT ja.*, j.title, j.location, c.company_name, ja.status as application_status
     FROM job_applications ja
     JOIN jobs j ON ja.job_id = j.id
     JOIN companies c ON j.company_id = c.id
     WHERE ja.job_seeker_id = ?
     ORDER BY ja.application_date DESC
     LIMIT 5",
    [$user_id]
);

// Get pending assessments
$assessments = $db->fetchAll(
    "SELECT ua.*, a.title, a.description, a.time_limit
     FROM user_assessments ua
     JOIN assessments a ON ua.assessment_id = a.id
     WHERE ua.user_id = ? AND ua.status IN ('not_started', 'in_progress')
     ORDER BY ua.created_at DESC
     LIMIT 5",
    [$user_id]
);

// Get notifications
$notifications = $db->fetchAll(
    "SELECT * FROM notifications 
     WHERE user_id = ? AND is_read = FALSE
     ORDER BY created_at DESC
     LIMIT 10",
    [$user_id]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Seeker Dashboard - AI Job System</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/responsive.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#2196F3">
    <style>
        /* Emergency text visibility fix */
        * {
            color: #333 !important;
        }
        .main-content h1, .main-content h2, .main-content h3, .main-content h4, .main-content h5, .main-content h6 {
            color: #2c3e50 !important;
        }
        .dashboard-section, .dashboard-section * {
            color: #333 !important;
        }
        #resume-content, #resume-content * {
            color: #333 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .upload-content p {
            color: #333 !important;
        }
        .resume-manager, .resume-upload, .resume-builder {
            color: #333 !important;
        }
        .resume-manager h3, .resume-upload h3, .resume-builder h3 {
            color: #2c3e50 !important;
        }
        .upload-area {
            background: #fafafa !important;
            border: 2px dashed #ccc !important;
            color: #333 !important;
        }
        /* Form input visibility fixes */
        input, textarea, select {
            color: #fff !important;
            background-color: #4a5568 !important;
            border: 1px solid #63738a !important;
            padding: 12px !important;
            border-radius: 6px !important;
        }
        input::placeholder, textarea::placeholder {
            color: #a0aec0 !important;
        }
        /* Fix for specific input types */
        input[type="text"], input[type="email"], input[type="tel"], input[type="number"] {
            color: #fff !important;
            background-color: #4a5568 !important;
        }
        /* Button styling fixes */
        .btn {
            color: #fff !important;
            cursor: pointer !important;
        }
        .btn-primary {
            background-color: #3498db !important;
            border: 1px solid #3498db !important;
        }
        .btn-secondary {
            background-color: #95a5a6 !important;
            border: 1px solid #95a5a6 !important;
        }
        
        /* Additional fixes for profile form */
        #profile-section input,
        #profile-section select,
        #profile-section textarea {
            color: #fff !important;
            background-color: #32383e !important;
            border: 1px solid #4a5568 !important;
        }
        
        /* Fix placeholders in dark inputs */
        #profile-section ::placeholder {
            color: #aaa !important;
            opacity: 0.7 !important;
        }
        
        /* Fix for select dropdowns */
        #profile-section select option {
            background-color: #32383e !important;
            color: #fff !important;
        }
        
        /* Save changes button */
        #profile-section .btn-primary {
            background-color: #3498db !important;
            color: #fff !important;
            font-weight: bold !important;
            padding: 12px 20px !important;
            margin-top: 10px !important;
        }
        
        /* Universal form element fix */
        .dashboard-section input[type="text"],
        .dashboard-section input[type="email"],
        .dashboard-section input[type="tel"],
        .dashboard-section input[type="number"],
        .dashboard-section select,
        .dashboard-section textarea {
            background-color: #32383e !important;
            color: #fff !important;
            border: 1px solid #4a5568 !important;
            padding: 12px !important;
            border-radius: 6px !important;
        }
        
        /* Make sure the text is always visible in ANY form field */
        input:not([type="button"]):not([type="submit"]):not([type="reset"]){
            color: #fff !important;
            background-color: #32383e !important;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>AI Job System</h2>
                <p>Job Seeker Dashboard</p>
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
                        <a href="#recommendations" onclick="showSection('recommendations')">
                            <span class="icon">🎯</span>
                            Job Recommendations
                        </a>
                    </li>
                    <li>
                        <a href="#search" onclick="showSection('search')">
                            <span class="icon">🔍</span>
                            Search Jobs
                        </a>
                    </li>
                    <li>
                        <a href="#resume" onclick="showSection('resume')">
                            <span class="icon">📝</span>
                            My Resume
                        </a>
                    </li>
                    <li>
                        <a href="#assessments" onclick="showSection('assessments')">
                            <span class="icon">📋</span>
                            Assessments
                        </a>
                    </li>
                    <li>
                        <a href="#applications" onclick="showSection('applications')">
                            <span class="icon">📄</span>
                            My Applications
                        </a>
                    </li>
                    <li>
                        <a href="#assignments" onclick="showSection('assignments')">
                            <span class="icon">📘</span>
                            Assignments
                        </a>
                    </li>
                    <li>
                        <a href="#companies" onclick="showSection('companies')">
                            <span class="icon">🏢</span>
                            Companies
                        </a>
                    </li>
                    <li>
                        <a href="#profile" onclick="showSection('profile')">
                            <span class="icon">👤</span>
                            Profile
                        </a>
                    </li>
                    <li>
                        <a href="#chatbot" onclick="showSection('chatbot')">
                            <span class="icon">🤖</span>
                            AI Assistant
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
                    <h1>Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                    <p>Here's what's happening with your job search</p>
                </div>
                <div class="header-right">
                    <div class="notifications">
                        <button class="notification-btn" onclick="toggleNotifications()">
                            <span class="icon">🔔</span>
                            <?php if (count($notifications) > 0): ?>
                                <span class="badge"><?php echo count($notifications); ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Dashboard Section -->
            <section id="dashboard-section" class="dashboard-section active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-content">
                            <h3><?php echo count($recommendations); ?></h3>
                            <p>Job Recommendations</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📄</div>
                        <div class="stat-content">
                            <h3><?php echo count($applications); ?></h3>
                            <p>Applications Sent</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📋</div>
                        <div class="stat-content">
                            <h3><?php echo count($assessments); ?></h3>
                            <p>Pending Assessments</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-content">
                            <h3><?php echo $profile ? $profile['experience_years'] : '0'; ?></h3>
                            <p>Years Experience</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Recent Recommendations -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>🎯 AI Job Recommendations</h3>
                            <a href="#recommendations" onclick="showSection('recommendations')" class="view-all">View All</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recommendations)): ?>
                                <div class="empty-state">
                                    <p>No recommendations yet. Complete your profile and take assessments to get personalized job recommendations!</p>
                                    <button class="btn btn-primary" onclick="showSection('profile')">Complete Profile</button>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($recommendations, 0, 3) as $rec): ?>
                                    <div class="recommendation-item">
                                        <div class="job-info">
                                            <h4><?php echo htmlspecialchars($rec['title']); ?></h4>
                                            <p class="company"><?php echo htmlspecialchars($rec['company_name']); ?></p>
                                            <p class="location">📍 <?php echo htmlspecialchars($rec['location']); ?></p>
                                        </div>
                                        <div class="match-score">
                                            <div class="score-circle">
                                                <span><?php echo number_format($rec['recommendation_score'], 0); ?>%</span>
                                            </div>
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
                            <?php if (empty($applications)): ?>
                                <div class="empty-state">
                                    <p>No applications yet. Start applying to jobs!</p>
                                    <button class="btn btn-primary" onclick="showSection('search')">Search Jobs</button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($applications as $app): ?>
                                    <div class="application-item">
                                        <div class="job-info">
                                            <h4><?php echo htmlspecialchars($app['title']); ?></h4>
                                            <p class="company"><?php echo htmlspecialchars($app['company_name']); ?></p>
                                        </div>
                                        <div class="status">
                                            <span class="status-badge status-<?php echo $app['application_status']; ?>">
                                                <?php echo ucfirst($app['application_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pending Assessments -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>📋 Pending Assessments</h3>
                            <a href="#assessments" onclick="showSection('assessments')" class="view-all">View All</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($assessments)): ?>
                                <div class="empty-state">
                                    <p>No pending assessments. Take assessments to improve your job recommendations!</p>
                                    <button class="btn btn-primary" onclick="showSection('assessments')">Take Assessment</button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($assessments as $assessment): ?>
                                    <div class="assessment-item">
                                        <div class="assessment-info">
                                            <h4><?php echo htmlspecialchars($assessment['title']); ?></h4>
                                            <p class="time-limit">⏱️ <?php echo gmdate("H:i", $assessment['time_limit']); ?> minutes</p>
                                        </div>
                                        <div class="assessment-actions">
                                            <button class="btn btn-primary btn-sm" onclick="startAssessment(<?php echo $assessment['id']; ?>)">
                                                <?php echo $assessment['status'] === 'in_progress' ? 'Continue' : 'Start'; ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Profile Completion -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>👤 Profile Completion</h3>
                        </div>
                        <div class="card-content">
                            <?php
                            $completion_score = 0;
                            $completed_fields = 0;
                            $total_fields = 5; // location, skills, experience_years, education_level, bio
                            
                            if ($profile) {
                                // Check location
                                if (!empty($profile['location'])) {
                                    $completed_fields++;
                                }
                                
                                // Check skills (handle JSON format)
                                if (!empty($profile['skills'])) {
                                    $skills_data = json_decode($profile['skills'], true);
                                    if (is_array($skills_data) && count($skills_data) > 0) {
                                        $completed_fields++;
                                    } else if (!is_array($skills_data) && trim($profile['skills']) !== '') {
                                        $completed_fields++;
                                    }
                                }
                                
                                // Check experience_years (0 is valid, NULL is not)
                                if (isset($profile['experience_years']) && $profile['experience_years'] !== null) {
                                    $completed_fields++;
                                }
                                
                                // Check education_level
                                if (!empty($profile['education_level'])) {
                                    $completed_fields++;
                                }
                                
                                // Check bio
                                if (!empty($profile['bio'])) {
                                    $completed_fields++;
                                }
                                
                                $completion_score = ($completed_fields / $total_fields) * 100;
                            }
                            
                            // Also check if user has basic info from users table
                            $user_fields_completed = 0;
                            $user_total_fields = 2; // name, phone
                            
                            if ($user) {
                                if (!empty($user['name'])) {
                                    $user_fields_completed++;
                                }
                                if (!empty($user['phone'])) {
                                    $user_fields_completed++;
                                }
                            }
                            
                            // Combine profile and user completion
                            $total_completion_fields = $total_fields + $user_total_fields;
                            $total_completed = $completed_fields + $user_fields_completed;
                            $final_completion_score = ($total_completed / $total_completion_fields) * 100;
                            ?>
                            <div class="completion-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $final_completion_score; ?>%"></div>
                                </div>
                                <p class="completion-text"><?php echo number_format($final_completion_score, 0); ?>% Complete</p>
                            </div>
                            <?php if ($final_completion_score < 100): ?>
                                <div class="completion-details" style="margin-top: 10px; font-size: 14px; color: #666;">
                                    <p>Missing fields:</p>
                                    <ul style="margin: 5px 0; padding-left: 20px;">
                                        <?php if (empty($user['phone'])): ?><li>Phone number</li><?php endif; ?>
                                        <?php if (empty($profile['location'])): ?><li>Location</li><?php endif; ?>
                                        <?php if (empty($profile['skills']) || (json_decode($profile['skills'], true) && count(json_decode($profile['skills'], true)) == 0)): ?><li>Skills</li><?php endif; ?>
                                        <?php if (!isset($profile['experience_years']) || $profile['experience_years'] === null): ?><li>Years of experience</li><?php endif; ?>
                                        <?php if (empty($profile['education_level'])): ?><li>Education level</li><?php endif; ?>
                                        <?php if (empty($profile['bio'])): ?><li>Bio/Summary</li><?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <p class="completion-note">Complete your profile to get better job recommendations</p>
                            <button class="btn btn-primary" onclick="showSection('profile')">Complete Profile</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Other sections will be loaded dynamically -->
            <section id="recommendations-section" class="dashboard-section">
                <div class="section-header">
                    <h2>🎯 AI Job Recommendations</h2>
                    <p>Personalized job matches based on your profile and assessments</p>
                </div>
                <div id="recommendations-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="search-section" class="dashboard-section">
                <div class="section-header">
                    <h2>🔍 Search Jobs</h2>
                    <p>Find your next career opportunity</p>
                </div>
                <div id="search-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="resume-section" class="dashboard-section">
                <div class="section-header">
                    <h2>📝 My Resume</h2>
                    <p>Manage your resume and professional profile</p>
                </div>
                <div id="resume-content">
                    <div class="resume-manager">
                        <div class="resume-upload">
                            <h3>Upload Resume</h3>
                            <div class="upload-area" id="upload-area">
                                <div class="upload-content">
                                    <span class="upload-icon">📄</span>
                                    <p id="upload-text">Drag and drop your resume here or click to browse</p>
                                    <p class="upload-note">Supported formats: PDF, DOC, DOCX, TXT (Max 5MB)</p>
                                    <p id="selected-file" style="display: none; margin-top: 10px; color: #007bff; font-weight: bold;"></p>
                                </div>
                                <input type="file" id="resume-file" accept=".pdf,.doc,.docx,.txt" style="display: none;">
                            </div>
                            <button class="btn btn-primary" id="upload-resume-btn">Upload Resume</button>
                            <div id="upload-progress" style="display: none; margin-top: 10px;">
                                <div style="background: #f0f0f0; border-radius: 4px; height: 20px; overflow: hidden;">
                                    <div id="progress-bar" style="background: #007bff; height: 100%; width: 0%; transition: width 0.3s;"></div>
                                </div>
                                <p id="progress-text" style="margin-top: 5px; font-size: 0.9em;"></p>
                            </div>
                        </div>
                        
                        <div class="resume-builder">
                            <h3>Build Resume Online</h3>
                            <p>Create a professional resume using our guided builder</p>
                            <button class="btn btn-secondary" id="start-building-btn" onclick="openResumeBuilder()">Start Building</button>
                            <button class="btn btn-success" onclick="downloadResume()" style="margin-top: 10px;">📥 Download Resume</button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="assessments-section" class="dashboard-section">
                <div class="section-header">
                    <h2>📋 Assessments</h2>
                    <p>Take AI-powered assessments to showcase your skills</p>
                </div>
                <div id="assessments-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="applications-section" class="dashboard-section">
                <div class="section-header">
                    <h2>📄 My Applications</h2>
                    <p>Track your job applications and their status</p>
                </div>
                <div id="applications-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="assignments-section" class="dashboard-section">
                <div class="section-header">
                    <h2>📘 My Assignments</h2>
                    <p>Practice tasks and take-home assignments from companies</p>
                </div>
                <div id="assignments-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="profile-section" class="dashboard-section">
                <div class="section-header">
                    <h2>👤 Profile Settings</h2>
                    <p>Manage your personal information and preferences</p>
                </div>
                <div id="profile-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>

            <section id="chatbot-section" class="dashboard-section">
                <div class="section-header">
                    <h2>🤖 AI Assistant</h2>
                    <p>Get help with your job search and career guidance</p>
                </div>
                <div id="chatbot-content">
                    <!-- Content will be loaded here -->
                </div>
            </section>
        </main>
    </div>

    <!-- Notifications Panel -->
    <div id="notifications-panel" class="notifications-panel">
        <div class="panel-header">
            <h3>Notifications</h3>
            <button class="close-btn" onclick="toggleNotifications()">&times;</button>
        </div>
        <div class="panel-content">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <p>No new notifications</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item">
                        <div class="notification-icon">🔔</div>
                        <div class="notification-content">
                            <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/proctoring.js"></script>
    
    <script>
    function openResumeBuilder() {
        const modal = document.createElement('div');
        modal.id = 'resume-builder-modal';
        modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;z-index:10000;';
        modal.innerHTML = `
            <div style="background:#fff;border-radius:10px;max-width:700px;width:92%;max-height:85vh;overflow:auto;padding:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h2>Resume Builder</h2>
                    <button onclick="closeResumeBuilder()" style="border:none;background:#e74c3c;color:#fff;border-radius:50%;width:32px;height:32px;cursor:pointer">&times;</button>
                </div>
                <div style="display:grid;gap:16px;">
                    <div>
                        <label style="font-weight:bold;display:block;margin-bottom:6px;">Professional Summary</label>
                        <textarea id="rb-summary" rows="4" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;"></textarea>
                    </div>
                    <div>
                        <label style="font-weight:bold;display:block;margin-bottom:6px;">Skills (one per line)</label>
                        <textarea id="rb-skills" rows="5" placeholder="JavaScript\nCommunication\nProblem Solving" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;"></textarea>
                    </div>
                    <div>
                        <label style="font-weight:bold;display:block;margin-bottom:6px;">Experience</label>
                        <textarea id="rb-experience" rows="6" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;"></textarea>
                    </div>
                    <div>
                        <label style="font-weight:bold;display:block;margin-bottom:6px;">Education</label>
                        <textarea id="rb-education" rows="4" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;"></textarea>
                    </div>
                    <div id="rb-preview" style="border:1px solid #eee;background:#fafafa;padding:16px;border-radius:6px;display:none;"></div>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button class="btn btn-secondary" onclick="generatePreview()">Preview</button>
                        <button class="btn btn-primary" onclick="saveResumeBuilder()">Save</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    function closeResumeBuilder(){
        document.getElementById('resume-builder-modal')?.remove();
    }
    function generatePreview(){
        const summary = document.getElementById('rb-summary').value.trim();
        const skillsText = document.getElementById('rb-skills').value.trim();
        const exp = document.getElementById('rb-experience').value.trim();
        const edu = document.getElementById('rb-education').value.trim();
        const skills = skillsText ? skillsText.split(/\r?\n/).filter(Boolean) : [];
        let html = '';
        if (summary) html += `<h3>Summary</h3><p>${escapeHtml(summary)}</p>`;
        if (skills.length){
            html += '<h3>Skills</h3><ul>' + skills.map(s=>`<li>${escapeHtml(s)}</li>`).join('') + '</ul>';
        }
        if (exp) html += `<h3>Experience</h3><p>${escapeHtml(exp).replace(/\n/g,'<br>')}</p>`;
        if (edu) html += `<h3>Education</h3><p>${escapeHtml(edu).replace(/\n/g,'<br>')}</p>`;
        const prev = document.getElementById('rb-preview');
        prev.style.display = html ? 'block' : 'none';
        prev.innerHTML = html || '<em>No content</em>';
    }
    function escapeHtml(str){
        const div = document.createElement('div');
        div.innerText = str; return div.innerHTML;
    }
    async function saveResumeBuilder(){
        const summary = document.getElementById('rb-summary').value.trim();
        const skillsText = document.getElementById('rb-skills').value.trim();
        const exp = document.getElementById('rb-experience').value.trim();
        const edu = document.getElementById('rb-education').value.trim();
        const skills = skillsText ? skillsText.split(/\r?\n/).filter(Boolean) : [];
        try{
            const res = await fetch('../api/resume/save_builder.php',{
                method:'POST',
                headers:{'Content-Type':'application/json'},
                credentials:'same-origin',
                body: JSON.stringify({ summary, skills, experience: exp, education: edu })
            });
            const data = await res.json();
            if(data.success){
                alert('Resume saved');
                closeResumeBuilder();
            } else {
                alert(data.message || 'Failed to save');
            }
        }catch(e){
            console.error(e);
            alert('Error saving resume');
        }
    }
    // Resume upload with drag-and-drop support
    (function() {
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('resume-file');
        const uploadText = document.getElementById('upload-text');
        const selectedFile = document.getElementById('selected-file');
        const uploadBtn = document.getElementById('upload-resume-btn');
        const progressDiv = document.getElementById('upload-progress');
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        
        if (!uploadArea || !fileInput) return;
        
        // Click to browse
        uploadArea.addEventListener('click', () => fileInput.click());
        
        // Drag and drop handlers
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#007bff';
            uploadArea.style.backgroundColor = '#f8f9fa';
        });
        
        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '';
            uploadArea.style.backgroundColor = '';
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '';
            uploadArea.style.backgroundColor = '';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });
        
        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['application/pdf', 'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
            
            // Validate file type
            if (!allowedTypes.includes(file.type)) {
                alert('Please upload a PDF, DOC, DOCX, or TXT file.');
                return;
            }
            
            // Validate file size
            if (file.size > maxSize) {
                alert('File size must be less than 5MB.');
                return;
            }
            
            // Show selected file
            selectedFile.textContent = `Selected: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
            selectedFile.style.display = 'block';
            uploadText.textContent = 'File selected. Click "Upload Resume" to proceed.';
        }
    })();
    
    // Add event listener for upload button to use dashboard.js uploadResume function
    document.addEventListener('DOMContentLoaded', function() {
        const uploadBtn = document.getElementById('upload-resume-btn');
        if (uploadBtn) {
            uploadBtn.addEventListener('click', function() {
                // Call the uploadResume function from dashboard.js
                if (typeof uploadResume === 'function') {
                    uploadResume();
                } else {
                    alert('Upload function not available. Please refresh the page.');
                }
            });
        }
    });
    </script>
    <script>
        // Initialize dashboard manager
        let dashboardManager;
        
        document.addEventListener('DOMContentLoaded', function() {
            dashboardManager = new DashboardManager();
        });
        
        // Global function to show sections
        function showSection(sectionName) {
            if (dashboardManager) {
                dashboardManager.showSection(sectionName);
            }
        }
        
        // Global function to toggle notifications
        function toggleNotifications() {
            const panel = document.getElementById('notifications-panel');
            if (panel) {
                panel.classList.toggle('open');
            }
        }
        
        // Global logout function (use API then redirect to home)
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                // Call logout API to clear session, then redirect to landing page
                fetch('../api/auth/logout.php', { credentials: 'same-origin' })
                    .finally(() => { window.location.href = '../index.php'; });
            }
        }
    </script>
    
    <!-- Job Details Modal -->
    <div id="job-details-modal" class="modal" style="display: none;">
        <div class="modal-content job-details-modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <span class="close" onclick="closeJobDetailsModal()" style="position: absolute; right: 20px; top: 15px; font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa;">&times;</span>
            <div id="job-details-content"></div>
        </div>
    </div>
    
    <style>
        .job-details-modal-content {
            padding: 30px;
            margin: 5% auto;
        }
        .job-details-header {
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .job-details-header h2 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .company-name {
            font-size: 18px;
            color: #666;
            margin: 5px 0;
        }
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 15px 0;
        }
        .job-meta span {
            padding: 5px 12px;
            background: #f5f5f5;
            border-radius: 4px;
            font-size: 14px;
        }
        .salary, .deadline {
            margin: 10px 0;
            color: #555;
        }
        .job-details-content {
            margin: 20px 0;
        }
        .detail-section {
            margin-bottom: 25px;
        }
        .detail-section h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .detail-text {
            line-height: 1.6;
            color: #555;
            white-space: pre-wrap;
        }
        .job-details-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .job-details-actions .btn {
            flex: 1;
        }
    </style>
</body>
</html>
