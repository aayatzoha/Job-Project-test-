<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $role = $_SESSION['user_type'];
    switch ($role) {
        case 'job_seeker':
            header('Location: dashboard/job_seeker.php');
            break;
        case 'company':
            header('Location: dashboard/company.php');
            break;
        case 'admin':
            header('Location: dashboard/admin.php');
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Job Recommendation System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2196F3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="AI Job System">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <h1>AI Job Recommendation System</h1>
                <p>Intelligent Career Matching with Proctored Assessments</p>
            </div>
            <nav class="top-nav">
                <a href="dashboard/index.php" class="nav-link">Dashboard</a>
            </nav>
        </header>

        <main class="main-content">
            <div class="hero-section">
                <h2>Find Your Dream Job with AI-Powered Matching</h2>
                <p>Get personalized job recommendations based on your skills, experience, and AI-driven assessments</p>
                
                <div class="login-options">
                    <div class="login-card" onclick="showLoginModal('job_seeker')">
                        <div class="icon">👤</div>
                        <h3>Job Seeker</h3>
                        <p>Find jobs, take assessments, get recommendations</p>
                    </div>
                    
                    <div class="login-card" onclick="showLoginModal('company')">
                        <div class="icon">🏢</div>
                        <h3>Company</h3>
                        <p>Post jobs, find candidates, manage hiring</p>
                    </div>
                    
                    <div class="login-card" onclick="showLoginModal('admin')">
                        <div class="icon">⚙️</div>
                        <h3>Admin</h3>
                        <p>Manage system, monitor analytics</p>
                    </div>
                </div>
            </div>

            <div class="features-section">
                <h2>Key Features</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🤖</div>
                        <h3>AI-Powered Matching</h3>
                        <p>Advanced algorithms match you with the perfect job opportunities</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📝</div>
                        <h3>Smart Resume Builder</h3>
                        <p>Create or upload resumes with AI-powered optimization</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🎯</div>
                        <h3>Proctored Assessments</h3>
                        <p>Secure, AI-monitored tests to showcase your skills</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Analytics Dashboard</h3>
                        <p>Track your progress and get insights on your career</p>
                    </div>
                </div>
            </div>

            <!-- Assignment Quick Access -->
            <div class="assignment-access-section">
                <h2>Assignments</h2>
                <p>Companies create proctored assignments; job seekers take them securely.</p>
                <div class="assignment-cards">
                    <div class="assignment-card" onclick="location.href='pages/assignment-login-company.php?redirect=create-assignment.php'">
                        <div class="icon">🧑‍💼</div>
                        <h3>Company Login</h3>
                        <p>Log in to create and manage assignments</p>
                    </div>
                    <div class="assignment-card" onclick="location.href='pages/assignment-login-jobseeker.php'">
                        <div class="icon">📝</div>
                        <h3>Job Seeker Login</h3>
                        <p>Log in to take assignments and recordings</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Login Modal -->
        <div id="loginModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeLoginModal()">&times;</span>
                <div id="loginForm">
                    <h2 id="loginTitle">Login</h2>
                    <form id="loginFormElement" onsubmit="handleLogin(event)">
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Login</button>
                    </form>
                    <p class="register-link">
                        Don't have an account? 
                        <a href="#" onclick="showRegisterModal()">Register here</a>
                    </p>
                    <p class="forgot-password-link">
                        <a href="#" onclick="showForgotPasswordModal()">Forgot Password?</a>
                    </p>
                    <p class="verify-email-link">
                        <a href="verify_email.php">Verify your email</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Register Modal -->
        <div id="registerModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeRegisterModal()">&times;</span>
                <div id="registerForm">
                    <h2 id="registerTitle">Register</h2>
                    <form id="registerFormElement" onsubmit="handleRegister(event)">
                        <div class="form-group">
                            <label for="reg_name">Full Name:</label>
                            <input type="text" id="reg_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_email">Email:</label>
                            <input type="email" id="reg_email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_phone">Phone:</label>
                            <input type="tel" id="reg_phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_type">Account Type:</label>
                            <select id="reg_type" name="user_type" required>
                                <option value="">Select Type</option>
                                <option value="job_seeker">Job Seeker</option>
                                <option value="company">Company</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Register</button>
                    </form>
                    <p class="login-link">
                        Already have an account? 
                        <a href="#" onclick="showLoginModal('job_seeker')">Login here</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- OTP Modal -->
        <div id="otpModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeOtpModal()">&times;</span>
                <div id="otpForm">
                    <h2>Verify OTP</h2>
                    <p>We've sent a verification code to your email</p>
                    <form id="otpFormElement" onsubmit="handleOtpVerification(event)">
                        <div class="form-group">
                            <label for="otp">Enter OTP:</label>
                            <input type="text" id="otp" name="otp" maxlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Verify</button>
                    </form>
                    <p class="resend-link">
                        Didn't receive OTP? 
                        <a href="#" onclick="resendOtp(event)">Resend</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

        <!-- OTP Display Modal -->
        <div id="otpDisplayModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeOtpDisplayModal()">&times;</span>
                <div id="otpDisplayContent">
                    <h2>Your OTP Code</h2>
                    <div id="otpDisplayValue" class="otp-display"></div>
                    <p>Please use this code to verify your email.</p>
                    <div id="otpTimer" class="otp-timer">Expires in: <span id="otpTimerValue">5:00</span></div>
                </div>
            </div>
        </div>

        <!-- Forgot Password Modal -->
        <div id="forgotPasswordModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeForgotPasswordModal()">&times;</span>
                <div id="forgotPasswordContainer">
                    <h2>Forgot Password</h2>
                    <p>Enter your email address and account type to receive a password reset OTP.</p>
                    <form id="forgotPasswordFormElement">
                        <div class="form-group">
                            <label for="forgot_email">Email:</label>
                            <input type="email" id="forgot_email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="forgot_type">Account Type:</label>
                            <select id="forgot_type" name="user_type" required>
                                <option value="">Select Type</option>
                                <option value="job_seeker">Job Seeker</option>
                                <option value="company">Company</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Reset OTP</button>
                    </form>
                    <p class="login-link">
                        Remember your password? 
                        <a href="#" onclick="showLoginModal()">Login here</a>
                    </p>
                    <p class="verify-email-link">
                        <a href="verify_email.php">Verify your email</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div id="resetPasswordModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeResetPasswordModal()">&times;</span>
                <div id="resetPasswordContainer">
                    <h2>Reset Password</h2>
                    <p>Enter the OTP sent to your email and your new password.</p>
                    <form id="resetPasswordFormElement">
                        <div class="form-group">
                            <label for="reset_otp">OTP Code:</label>
                            <input type="text" id="reset_otp" name="otp" maxlength="6" required>
                        </div>
                        <div class="form-group">
                            <label for="reset_password">New Password:</label>
                            <input type="password" id="reset_password" name="password" required minlength="8">
                            <small>Password must be at least 8 characters long</small>
                        </div>
                        <div class="form-group">
                            <label for="reset_confirm_password">Confirm Password:</label>
                            <input type="password" id="reset_confirm_password" name="confirm_password" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </form>
                    <p class="resend-link">
                        Didn't receive OTP? 
                        <a href="#" onclick="showForgotPasswordModal()">Request again</a>
                    </p>
                </div>
            </div>
        </div>

        <footer class="footer">
            <p>&copy; 2023 AI Job Recommendation System. All rights reserved.</p>
        </footer>
    </div>

    <script src="assets/js/utility.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/auth.js"></script>
</body>
</html>
