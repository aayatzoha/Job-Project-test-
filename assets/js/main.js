// AI Job Recommendation System - Main JavaScript

// Global variables
let currentUserType = null;
let otpTimer = null;
let otpTimeLeft = 0;

// Validation helpers (fallback to Utility if available)
function validateEmail(email) {
    try {
        if (window.JobPortalUtils && typeof JobPortalUtils.validateEmail === 'function') {
            return JobPortalUtils.validateEmail(email);
        }
    } catch (e) {}
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
}

function validatePhone(phone) {
    try {
        if (window.JobPortalUtils && typeof JobPortalUtils.validatePhone === 'function') {
            return JobPortalUtils.validatePhone(phone);
        }
    } catch (e) {}
    const re = /^\+?[0-9]{7,15}$/;
    return re.test(String(phone));
}

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    checkPWAInstallation();
});

// Initialize application
function initializeApp() {
    // Add loading animation
    showLoading();
    
    // Check for saved user preferences
    loadUserPreferences();
    
    // Initialize service worker for PWA (support both dev server and Apache paths)
    if ('serviceWorker' in navigator) {
        const swPath = window.location.pathname.startsWith('/job/') ? '/job/sw.js' : '/sw.js';
        navigator.serviceWorker.register(swPath)
            .then(registration => {
                console.log('Service Worker registered:', registration);
            })
            .catch(error => {
                console.log('Service Worker registration failed:', error);
            });
    }
    
    // Hide loading after initialization
    setTimeout(hideLoading, 1000);
}

// Setup event listeners
function setupEventListeners() {
    // Modal close on outside click
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                closeAllModals();
            }
        });
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });
    
    // Form validation
    setupFormValidation();
    
    // OTP input formatting
    setupOTPInput();
    
    // Forgot Password form submission
    const forgotPasswordForm = document.getElementById('forgotPasswordFormElement');
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', function(event) {
            event.preventDefault();
            handleForgotPassword(event);
        });
    }
    
    // Reset Password form submission
    const resetPasswordForm = document.getElementById('resetPasswordFormElement');
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', function(event) {
            event.preventDefault();
            handleResetPassword(event);
        });
    }
    
    // Login form submission
    const loginForm = document.getElementById('loginFormElement');
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            event.preventDefault();
            handleLogin(event);
        });
    }
    
    // Registration form submission
    const registerForm = document.getElementById('registerFormElement');
    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            event.preventDefault();
            handleRegister(event);
        });
    }
    
    // OTP verification form submission
    const otpForm = document.getElementById('otpFormElement');
    if (otpForm) {
        otpForm.addEventListener('submit', function(event) {
            event.preventDefault();
            // Use the correct handler name
            handleOtpVerification(event);
        });
    }
    
    // Resend OTP button click
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    if (resendOtpBtn) {
        resendOtpBtn.addEventListener('click', resendOtp);
    }
}

// Show loading animation
function showLoading() {
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'loadingOverlay';
    loadingDiv.innerHTML = `
        <div class="loading-container">
            <div class="loading-spinner"></div>
            <p>Loading AI Job System...</p>
        </div>
    `;
    loadingDiv.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.9);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    `;
    document.body.appendChild(loadingDiv);
}

// Hide loading animation
function hideLoading() {
    const loadingDiv = document.getElementById('loadingOverlay');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}

// Show login modal
function showLoginModal(userType = 'job_seeker') {
    // Default to job seeker if no type provided
    currentUserType = userType || 'job_seeker';
    const modal = document.getElementById('loginModal');
    const title = document.getElementById('loginTitle');
    
    const displayType = (currentUserType || 'job_seeker').replace('_', ' ').toUpperCase();
    title.textContent = `Login as ${displayType}`;
    modal.style.display = 'block';
    
    // Focus on email input
    setTimeout(() => {
        document.getElementById('email').focus();
    }, 100);
}

// Close login modal
function closeLoginModal() {
    document.getElementById('loginModal').style.display = 'none';
    currentUserType = null;
}

// Show register modal
function showRegisterModal() {
    closeLoginModal();
    const modal = document.getElementById('registerModal');
    modal.style.display = 'block';
    
    // Focus on name input
    setTimeout(() => {
        document.getElementById('reg_name').focus();
    }, 100);
}

// Close register modal
function closeRegisterModal() {
    document.getElementById('registerModal').style.display = 'none';
}

// Show OTP modal
function showOtpModal() {
    closeRegisterModal();
    const modal = document.getElementById('otpModal');
    modal.style.display = 'block';
    
    // Start OTP timer
    startOTPTimer();
    
    // Focus on OTP input
    setTimeout(() => {
        document.getElementById('otp').focus();
    }, 100);
}

// Show OTP on screen with large display
function showOtpOnScreen(otp, message, title = '🎉 Registration Successful!') {
    closeRegisterModal();
    
    // Create OTP display modal
    const otpDisplayModal = document.createElement('div');
    otpDisplayModal.className = 'modal';
    otpDisplayModal.id = 'otpDisplayModal';
    otpDisplayModal.innerHTML = `
        <div class="modal-content" style="max-width: 500px; text-align: center;">
            <span class="close" onclick="closeOtpDisplayModal()">&times;</span>
            <div class="otp-display">
                <h2 style="color: #2c3e50; margin-bottom: 20px;">${title}</h2>
                <p style="color: #7f8c8d; margin-bottom: 30px;">${message}</p>
                
                <div class="otp-container" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 15px; margin: 20px 0;">
                    <h3 style="color: white; margin-bottom: 15px;">Your OTP Code</h3>
                    <div class="otp-code-display" style="background: white; padding: 20px; border-radius: 10px; margin: 15px 0;">
                        <span style="font-size: 3rem; font-weight: bold; color: #2c3e50; letter-spacing: 10px;">${otp}</span>
                    </div>
                    <p style="color: white; font-size: 0.9rem; margin: 0;">This OTP is valid for 10 minutes</p>
                </div>
                
                <div class="otp-actions" style="margin-top: 30px;">
                    <button class="btn btn-primary" onclick="proceedToOtpVerification()" style="margin-right: 10px;">
                        Verify OTP
                    </button>
                    <button class="btn btn-secondary" onclick="closeOtpDisplayModal()">
                        Close
                    </button>
                </div>
                
                <div class="otp-timer" style="margin-top: 20px; color: #7f8c8d;">
                    <p>⏰ OTP expires in: <span id="otp-timer-display">10:00</span></p>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(otpDisplayModal);
    
    // Start countdown timer
    startOtpDisplayTimer();
}

// Close OTP display modal
function closeOtpDisplayModal() {
    const modal = document.getElementById('otpDisplayModal');
    if (modal) {
        modal.remove();
    }
    stopOtpDisplayTimer();
}

// Show Forgot Password Modal
function showForgotPasswordModal() {
    closeLoginModal();
    document.getElementById('forgotPasswordModal').style.display = 'block';
}

// Close Forgot Password Modal
function closeForgotPasswordModal() {
    document.getElementById('forgotPasswordModal').style.display = 'none';
}

// Show Reset Password Modal
function showResetPasswordModal() {
    closeForgotPasswordModal();
    document.getElementById('resetPasswordModal').style.display = 'block';
}

// Close Reset Password Modal
function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
}

// Handle Forgot Password
async function handleForgotPassword(event) {
    event.preventDefault();
    
    const email = document.getElementById('forgot_email').value;
    const userType = document.getElementById('forgot_type').value;
    
    if (!email || !userType) {
        showAlert('Please fill in all fields', 'error');
        return;
    }
    
    showLoading();
    
    try {
        const result = await authManager.forgotPassword(email, userType);
        
        if (result.success) {
            showAlert(result.message, 'success');
            setTimeout(() => {
                showResetPasswordModal();
            }, 1000);
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        console.error('Forgot password error:', error);
        showAlert('An error occurred. Please try again later.', 'error');
    } finally {
        hideLoading();
    }
}

// Handle Reset Password
async function handleResetPassword(event) {
    event.preventDefault();
    
    const otp = document.getElementById('reset_otp').value;
    const password = document.getElementById('reset_password').value;
    const confirmPassword = document.getElementById('reset_confirm_password').value;
    
    if (!otp || !password || !confirmPassword) {
        showAlert('Please fill in all fields', 'error');
        return;
    }
    
    if (password !== confirmPassword) {
        showAlert('Passwords do not match', 'error');
        return;
    }
    
    if (password.length < 8) {
        showAlert('Password must be at least 8 characters long', 'error');
        return;
    }
    
    showLoading();
    
    try {
        const result = await authManager.resetPassword(otp, password);
        
        if (result.success) {
            showAlert(result.message, 'success');
            setTimeout(() => {
                closeResetPasswordModal();
                showLoginModal();
            }, 2000);
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        console.error('Reset password error:', error);
        showAlert('An error occurred. Please try again later.', 'error');
    } finally {
        hideLoading();
    }
}

// Proceed to OTP verification
function proceedToOtpVerification() {
    closeOtpDisplayModal();
    showOtpModal();
}

// OTP display timer
let otpDisplayTimer = null;
let otpDisplayTimeLeft = 600; // 10 minutes

function startOtpDisplayTimer() {
    otpDisplayTimeLeft = 600;
    updateOtpDisplayTimer();
    
    otpDisplayTimer = setInterval(() => {
        otpDisplayTimeLeft--;
        updateOtpDisplayTimer();
        
        if (otpDisplayTimeLeft <= 0) {
            stopOtpDisplayTimer();
        }
    }, 1000);
}

function stopOtpDisplayTimer() {
    if (otpDisplayTimer) {
        clearInterval(otpDisplayTimer);
        otpDisplayTimer = null;
    }
}

function updateOtpDisplayTimer() {
    const minutes = Math.floor(otpDisplayTimeLeft / 60);
    const seconds = otpDisplayTimeLeft % 60;
    const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    
    const timerDisplay = document.getElementById('otp-timer-display');
    if (timerDisplay) {
        timerDisplay.textContent = timeString;
        
        // Change color when time is running low
        if (otpDisplayTimeLeft <= 60) {
            timerDisplay.style.color = '#e74c3c';
        } else if (otpDisplayTimeLeft <= 300) {
            timerDisplay.style.color = '#f39c12';
        }
    }
}

// Close OTP modal
function closeOtpModal() {
    document.getElementById('otpModal').style.display = 'none';
    stopOTPTimer();
}

// Close all modals
function closeAllModals() {
    closeLoginModal();
    closeRegisterModal();
    closeOtpModal();
}

// Handle login form submission
async function handleLogin(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const email = formData.get('email');
    const password = formData.get('password');
    const userType = currentUserType || 'job_seeker';
    
    // Validate inputs
    if (!validateEmail(email)) {
        showAlert('Please enter a valid email address', 'error');
        return;
    }
    
    if (password.length < 6) {
        showAlert('Password must be at least 6 characters long', 'error');
        return;
    }
    
    // Show loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.innerHTML = '<span class="loading"></span> Logging in...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('api/auth/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                email: email,
                password: password,
                user_type: userType
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Login successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = result.redirect_url;
            }, 1500);
        } else if (result.needs_verification) {
            showAlert(result.message || 'Email needs verification. Check your inbox.', 'warning');
            closeLoginModal();
            showOtpModal();
        } else {
            showAlert(result.message || 'Login failed. Please try again.', 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showAlert('An error occurred. Please try again.', 'error');
    } finally {
        // Reset button
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

// Handle register form submission
let isRegistering = false; // Flag to prevent duplicate submissions

async function handleRegister(event) {
    event.preventDefault();
    
    // Prevent duplicate submissions
    if (isRegistering) {
        console.log('Registration already in progress, ignoring duplicate submission');
        return;
    }
    
    const formData = new FormData(event.target);
    const name = formData.get('name');
    const email = formData.get('email');
    const phone = formData.get('phone');
    const userType = formData.get('user_type');
    
    // Validate inputs
    if (!name.trim()) {
        showAlert('Please enter your full name', 'error');
        return;
    }
    
    if (!validateEmail(email)) {
        showAlert('Please enter a valid email address', 'error');
        return;
    }
    
    if (!validatePhone(phone)) {
        showAlert('Please enter a valid phone number', 'error');
        return;
    }
    
    if (!userType) {
        showAlert('Please select an account type', 'error');
        return;
    }
    
    // Set registering flag
    isRegistering = true;
    
    // Show loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    const originalDisabled = submitBtn.disabled;
    submitBtn.innerHTML = '<span class="loading"></span> Registering...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('api/auth/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                name: name,
                email: email,
                phone: phone,
                user_type: userType
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Always show success message first
            showAlert('Registration successful! Please verify your email with the OTP sent.', 'success');
            
            // Then handle OTP display
            if (result.otp) {
                // Show OTP on screen
                showOtpOnScreen(result.otp, result.message || 'Please use this OTP to verify your account');
                // Redirect to verification page so session-based verify works reliably
                setTimeout(() => { window.location.href = 'verify_email.php'; }, 1200);
            } else {
                // Just show the OTP modal if no OTP is provided in response
                setTimeout(() => {
                    showOtpModal();
                    window.location.href = 'verify_email.php';
                }, 1000);
            }
        } else {
            showAlert(result.message || 'Registration failed. Please try again.', 'error');
        }
    } catch (error) {
        console.error('Registration error:', error);
        showAlert('An error occurred. Please try again.', 'error');
    } finally {
        // Reset button and flag
        isRegistering = false;
        submitBtn.textContent = originalText;
        submitBtn.disabled = originalDisabled;
    }
}

// Handle OTP verification
async function handleOtpVerification(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    let otp = formData.get('otp');
    
    // Normalize OTP - trim whitespace and remove any non-numeric characters
    otp = otp.toString().trim().replace(/\D/g, '');
    
    // Validate OTP - must be exactly 6 digits
    if (!otp || otp.length !== 6) {
        showAlert('Please enter a valid 6-digit OTP', 'error');
        return;
    }
    
    // Show loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.innerHTML = '<span class="loading"></span> Verifying...';
    submitBtn.disabled = true;
    
    try {
        // Get email from form or session if available
        const emailInput = document.getElementById('otp-email') || document.getElementById('reg_email');
        const email = emailInput ? emailInput.value : '';
        
        const requestBody = {
            otp: otp
        };
        
        // Include email if available (for fallback verification if session is lost)
        if (email && email.trim()) {
            requestBody.email = email.trim();
        }
        
        const response = await fetch('api/auth/verify_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestBody)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Email verified successfully! Redirecting to dashboard...', 'success');
            setTimeout(() => {
                window.location.href = result.redirect_url;
            }, 1500);
        } else {
            showAlert(result.message || 'Invalid OTP. Please try again.', 'error');
        }
    } catch (error) {
        console.error('OTP verification error:', error);
        showAlert('An error occurred. Please try again.', 'error');
    } finally {
        // Reset button
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

// Resend OTP
async function resendOtp(event) {
    // Prevent default if it's a link click
    if (event) {
        event.preventDefault();
    }
    
    try {
        // Disable the resend link/button temporarily
        const resendLink = document.querySelector('.resend-link');
        if (resendLink) {
            resendLink.style.pointerEvents = 'none';
            resendLink.style.opacity = '0.5';
        }
        
        const response = await fetch('api/auth/resend_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('OTP resent successfully! Please check your email.', 'success');
            
            // Show OTP on screen if provided (for development)
            if (result.otp) {
                showOtpOnScreen(result.otp, 'A new OTP has been sent. Please use this code to verify your account.', '📧 New OTP Sent!');
            }
            
            // Restart the OTP timer
            startOTPTimer();
        } else {
            showAlert(result.message || 'Failed to resend OTP. Please try again.', 'error');
            
            // Re-enable resend link on error
            if (resendLink) {
                resendLink.style.pointerEvents = 'auto';
                resendLink.style.opacity = '1';
            }
        }
    } catch (error) {
        console.error('Resend OTP error:', error);
        showAlert('An error occurred while resending OTP. Please try again.', 'error');
        
        // Re-enable resend link on error
        const resendLink = document.querySelector('.resend-link');
        if (resendLink) {
            resendLink.style.pointerEvents = 'auto';
            resendLink.style.opacity = '1';
        }
    }
}

// Start OTP timer
function startOTPTimer() {
    otpTimeLeft = 300; // 5 minutes
    updateOTPTimer();
    
    otpTimer = setInterval(() => {
        otpTimeLeft--;
        updateOTPTimer();
        
        if (otpTimeLeft <= 0) {
            stopOTPTimer();
        }
    }, 1000);
}

// Stop OTP timer
function stopOTPTimer() {
    if (otpTimer) {
        clearInterval(otpTimer);
        otpTimer = null;
    }
}

// Update OTP timer display
function updateOTPTimer() {
    const minutes = Math.floor(otpTimeLeft / 60);
    const seconds = otpTimeLeft % 60;
    const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    
    const resendLink = document.querySelector('.resend-link');
    if (resendLink) {
        if (otpTimeLeft > 0) {
            resendLink.innerHTML = `Resend OTP in ${timeString}`;
            resendLink.style.pointerEvents = 'none';
            resendLink.style.opacity = '0.5';
        } else {
            resendLink.innerHTML = 'Didn\'t receive OTP? <a href="#" onclick="resendOtp()">Resend</a>';
            resendLink.style.pointerEvents = 'auto';
            resendLink.style.opacity = '1';
        }
    }
}

// Setup form validation
function setupFormValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
    });
}

// Validate individual field
function validateField(field) {
    const value = field.value.trim();
    const fieldName = field.name;
    
    clearFieldError(field);
    
    switch (fieldName) {
        case 'email':
        case 'reg_email':
            if (value && !validateEmail(value)) {
                showFieldError(field, 'Please enter a valid email address');
            }
            break;
        case 'phone':
        case 'reg_phone':
            if (value && !validatePhone(value)) {
                showFieldError(field, 'Please enter a valid phone number');
            }
            break;
        case 'password':
            if (value && value.length < 6) {
                showFieldError(field, 'Password must be at least 6 characters long');
            }
            break;
        case 'name':
        case 'reg_name':
            if (value && value.length < 2) {
                showFieldError(field, 'Name must be at least 2 characters long');
            }
            break;
    }
}

// Show field error
function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.cssText = 'color: #e74c3c; font-size: 0.8rem; margin-top: 5px;';
    
    field.parentNode.appendChild(errorDiv);
    field.style.borderColor = '#e74c3c';
}

// Clear field error
function clearFieldError(field) {
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
    field.style.borderColor = '#e1e8ed';
}

// Setup OTP input formatting
function setupOTPInput() {
    const otpInput = document.getElementById('otp');
    if (otpInput) {
        otpInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 6);
        });
        
        otpInput.addEventListener('keydown', function(event) {
            if (event.key === 'Backspace' && this.value.length === 0) {
                event.preventDefault();
            }
        });
    }
}

// Include utility.js before this file in HTML

// Show alert message (wrapper for showNotification for backward compatibility)
function showAlert(message, type = 'info') {
    // Use the utility function
    JobPortalUtils.showNotification(message, type);
    
    // For backward compatibility with existing code
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    // Insert at the top of the modal content
    const modalContent = document.querySelector('.modal-content');
    if (modalContent) {
        modalContent.insertBefore(alertDiv, modalContent.firstChild);
    } else {
        document.body.insertBefore(alertDiv, document.body.firstChild);
    }
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Load user preferences
function loadUserPreferences() {
    const preferences = localStorage.getItem('userPreferences');
    if (preferences) {
        try {
            const prefs = JSON.parse(preferences);
            applyUserPreferences(prefs);
        } catch (error) {
            console.error('Error loading user preferences:', error);
        }
    }
}

// Apply user preferences
function applyUserPreferences(preferences) {
    if (preferences.theme === 'dark') {
        document.body.classList.add('dark-theme');
    }
    
    if (preferences.language) {
        // TODO: Implement language switching
    }
}

// Check PWA installation
function checkPWAInstallation() {
    if (window.matchMedia('(display-mode: standalone)').matches) {
        console.log('App is running as PWA');
    }
}

// Install PWA
function installPWA() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.ready.then(registration => {
            if (registration.waiting) {
                registration.waiting.postMessage({ action: 'skipWaiting' });
            }
        });
    }
}

// Utility function to format phone number
function formatPhoneNumber(phone) {
    const cleaned = phone.replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return '(' + match[1] + ') ' + match[2] + '-' + match[3];
    }
    return phone;
}

// Utility function to debounce
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export functions for global access
window.showLoginModal = showLoginModal;
window.closeLoginModal = closeLoginModal;
window.showRegisterModal = showRegisterModal;
window.closeRegisterModal = closeRegisterModal;
window.closeOtpModal = closeOtpModal;
window.closeOtpDisplayModal = closeOtpDisplayModal;
window.proceedToOtpVerification = proceedToOtpVerification;
window.handleLogin = handleLogin;
window.handleRegister = handleRegister;
window.handleOtpVerification = handleOtpVerification;
window.resendOtp = resendOtp;
