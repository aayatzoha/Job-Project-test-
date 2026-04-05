<?php
// Include database connection
require_once '../config/database.php';
require_once '../includes/functions.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Get user information based on user type
if ($user_type == 'company') {
    $user = $db->fetch("SELECT u.*, c.name as company_name, c.logo, c.description, c.industry, c.website, c.founded_year, c.size, c.location 
                       FROM users u 
                       LEFT JOIN companies c ON u.id = c.user_id 
                       WHERE u.id = ?", [$user_id]);
} else {
    $user = $db->fetch("SELECT u.*, js.resume, js.skills, js.experience, js.education, js.bio 
                       FROM users u 
                       LEFT JOIN job_seekers js ON u.id = js.user_id 
                       WHERE u.id = ?", [$user_id]);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Job Portal</title>
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
                <h1>Your Profile</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                </div>
            </header>

            <div class="dashboard-section active">
                <div class="profile-container">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="../uploads/profile_images/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <button class="change-avatar-btn" id="changeAvatarBtn">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo $user_type == 'company' ? htmlspecialchars($user['company_name']) : htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                            <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
                            <?php if ($user_type == 'company' && !empty($user['industry'])): ?>
                                <p class="profile-industry"><?php echo htmlspecialchars($user['industry']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-tabs">
                        <button class="tab-btn active" data-tab="personal">Personal Information</button>
                        <?php if ($user_type == 'company'): ?>
                            <button class="tab-btn" data-tab="company">Company Details</button>
                        <?php else: ?>
                            <button class="tab-btn" data-tab="resume">Resume & Skills</button>
                        <?php endif; ?>
                        <button class="tab-btn" data-tab="security">Security</button>
                    </div>

                    <div class="profile-content">
                        <!-- Personal Information Tab -->
                        <div class="tab-content active" id="personal-tab">
                            <h3>Personal Information</h3>
                            <form id="personalInfoForm">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="firstName">First Name</label>
                                        <input type="text" id="firstName" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="lastName">Last Name</label>
                                        <input type="text" id="lastName" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="location">Location</label>
                                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>

                        <?php if ($user_type == 'company'): ?>
                            <!-- Company Details Tab -->
                            <div class="tab-content" id="company-tab">
                                <h3>Company Details</h3>
                                <form id="companyDetailsForm">
                                    <div class="form-group">
                                        <label for="companyName">Company Name</label>
                                        <input type="text" id="companyName" name="company_name" value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="industry">Industry</label>
                                            <input type="text" id="industry" name="industry" value="<?php echo htmlspecialchars($user['industry'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="companySize">Company Size</label>
                                            <select id="companySize" name="size">
                                                <option value="1-10" <?php echo ($user['size'] ?? '') == '1-10' ? 'selected' : ''; ?>>1-10 employees</option>
                                                <option value="11-50" <?php echo ($user['size'] ?? '') == '11-50' ? 'selected' : ''; ?>>11-50 employees</option>
                                                <option value="51-200" <?php echo ($user['size'] ?? '') == '51-200' ? 'selected' : ''; ?>>51-200 employees</option>
                                                <option value="201-500" <?php echo ($user['size'] ?? '') == '201-500' ? 'selected' : ''; ?>>201-500 employees</option>
                                                <option value="501+" <?php echo ($user['size'] ?? '') == '501+' ? 'selected' : ''; ?>>501+ employees</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="website">Website</label>
                                            <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="foundedYear">Founded Year</label>
                                            <input type="number" id="foundedYear" name="founded_year" value="<?php echo htmlspecialchars($user['founded_year'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="companyDescription">Company Description</label>
                                        <textarea id="companyDescription" name="description" rows="5"><?php echo htmlspecialchars($user['description'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="companyLogo">Company Logo</label>
                                        <div class="file-upload">
                                            <input type="file" id="companyLogo" name="logo" accept="image/*">
                                            <label for="companyLogo" class="file-upload-label">
                                                <i class="fas fa-cloud-upload-alt"></i> Choose File
                                            </label>
                                            <span class="file-name"><?php echo !empty($user['logo']) ? htmlspecialchars($user['logo']) : 'No file chosen'; ?></span>
                                        </div>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Resume & Skills Tab -->
                            <div class="tab-content" id="resume-tab">
                                <h3>Resume & Skills</h3>
                                <form id="resumeSkillsForm">
                                    <div class="form-group">
                                        <label for="skills">Skills (comma separated)</label>
                                        <input type="text" id="skills" name="skills" value="<?php echo htmlspecialchars($user['skills'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="bio">Professional Bio</label>
                                        <textarea id="bio" name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="experience">Work Experience</label>
                                        <textarea id="experience" name="experience" rows="6"><?php echo htmlspecialchars($user['experience'] ?? ''); ?></textarea>
                                        <small>Format: Company Name | Position | Start Date - End Date | Description</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="education">Education</label>
                                        <textarea id="education" name="education" rows="6"><?php echo htmlspecialchars($user['education'] ?? ''); ?></textarea>
                                        <small>Format: Institution | Degree | Start Date - End Date | Description</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="resume">Resume</label>
                                        <div class="file-upload">
                                            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx">
                                            <label for="resume" class="file-upload-label">
                                                <i class="fas fa-cloud-upload-alt"></i> Choose File
                                            </label>
                                            <span class="file-name"><?php echo !empty($user['resume']) ? htmlspecialchars($user['resume']) : 'No file chosen'; ?></span>
                                        </div>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- Security Tab -->
                        <div class="tab-content" id="security-tab">
                            <h3>Security Settings</h3>
                            <form id="securityForm">
                                <div class="form-group">
                                    <label for="currentPassword">Current Password</label>
                                    <input type="password" id="currentPassword" name="current_password" required>
                                </div>
                                <div class="form-group">
                                    <label for="newPassword">New Password</label>
                                    <input type="password" id="newPassword" name="new_password" required>
                                </div>
                                <div class="form-group">
                                    <label for="confirmPassword">Confirm New Password</label>
                                    <input type="password" id="confirmPassword" name="confirm_password" required>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </div>
                            </form>
                            
                            <div class="security-options">
                                <h4>Account Security</h4>
                                <div class="toggle-option">
                                    <span>Two-Factor Authentication</span>
                                    <label class="switch">
                                        <input type="checkbox" id="twoFactorAuth">
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <div class="toggle-option">
                                    <span>Email Notifications for Login Attempts</span>
                                    <label class="switch">
                                        <input type="checkbox" id="loginNotifications" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Avatar Upload Modal -->
    <div id="avatarModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Change Profile Picture</h2>
            <form id="avatarForm">
                <div class="avatar-upload-container">
                    <div class="avatar-preview">
                        <img id="avatarPreview" src="<?php echo !empty($user['profile_image']) ? '../uploads/profile_images/' . htmlspecialchars($user['profile_image']) : '../assets/images/default-avatar.png'; ?>" alt="Avatar Preview">
                    </div>
                    <div class="file-upload">
                        <input type="file" id="avatarUpload" name="avatar" accept="image/*">
                        <label for="avatarUpload" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i> Choose Image
                        </label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" id="cancelAvatarBtn" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            document.querySelectorAll('.tab-btn').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons and tabs
                    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding tab
                    this.classList.add('active');
                    document.getElementById(this.getAttribute('data-tab') + '-tab').classList.add('active');
                });
            });
            
            // Avatar modal
            const avatarModal = document.getElementById('avatarModal');
            const changeAvatarBtn = document.getElementById('changeAvatarBtn');
            const closeModal = document.querySelector('.close-modal');
            const cancelAvatarBtn = document.getElementById('cancelAvatarBtn');
            
            changeAvatarBtn.addEventListener('click', function() {
                avatarModal.style.display = 'block';
            });
            
            closeModal.addEventListener('click', function() {
                avatarModal.style.display = 'none';
            });
            
            cancelAvatarBtn.addEventListener('click', function() {
                avatarModal.style.display = 'none';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === avatarModal) {
                    avatarModal.style.display = 'none';
                }
            });
            
            // Avatar preview
            document.getElementById('avatarUpload').addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('avatarPreview').src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
            
            // File upload name display
            document.querySelectorAll('input[type="file"]').forEach(input => {
                input.addEventListener('change', function() {
                    const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                    this.parentElement.querySelector('.file-name').textContent = fileName;
                });
            });
            
            // Form submissions
            document.getElementById('personalInfoForm').addEventListener('submit', function(e) {
                e.preventDefault();
                updateProfile('personal', new FormData(this));
            });
            
            <?php if ($user_type == 'company'): ?>
                document.getElementById('companyDetailsForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    updateProfile('company', new FormData(this));
                });
            <?php else: ?>
                document.getElementById('resumeSkillsForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    updateProfile('resume', new FormData(this));
                });
            <?php endif; ?>
            
            document.getElementById('securityForm').addEventListener('submit', function(e) {
                e.preventDefault();
                updateSecurity(new FormData(this));
            });
            
            document.getElementById('avatarForm').addEventListener('submit', function(e) {
                e.preventDefault();
                updateAvatar(new FormData(this));
            });
            
            // Update profile function
            function updateProfile(type, formData) {
                formData.append('type', type);
                
                fetch('../api/profile/update.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Profile updated successfully', 'success');
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred. Please try again.', 'error');
                });
            }
            
            // Update security function
            function updateSecurity(formData) {
                fetch('../api/profile/update_password.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Password updated successfully', 'success');
                        document.getElementById('securityForm').reset();
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred. Please try again.', 'error');
                });
            }
            
            // Update avatar function
            function updateAvatar(formData) {
                fetch('../api/profile/update_avatar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Profile picture updated successfully', 'success');
                        avatarModal.style.display = 'none';
                        // Update profile image on page
                        const profileImage = document.querySelector('.profile-avatar img');
                        if (profileImage) {
                            profileImage.src = '../uploads/profile_images/' + data.filename;
                        } else {
                            const placeholder = document.querySelector('.avatar-placeholder');
                            if (placeholder) {
                                placeholder.parentElement.innerHTML = `<img src="../uploads/profile_images/${data.filename}" alt="Profile Image">`;
                            }
                        }
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred. Please try again.', 'error');
                });
            }
            
            // Notification function
            function showNotification(message, type) {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.innerHTML = `
                    <span>${message}</span>
                    <button class="close-notification">&times;</button>
                `;
                document.body.appendChild(notification);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    notification.remove();
                }, 5000);
                
                // Close button
                notification.querySelector('.close-notification').addEventListener('click', function() {
                    notification.remove();
                });
            }
        });
    </script>
</body>
</html>