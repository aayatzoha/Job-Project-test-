// AI Job Recommendation System - Authentication JavaScript

// Authentication state management
class AuthManager {
    constructor() {
        this.isAuthenticated = false;
        this.user = null;
        this.token = null;
        this.init();
    }
    
    init() {
        this.loadAuthState();
        this.setupAuthListeners();
    }
    
    // Load authentication state from localStorage
    loadAuthState() {
        const authData = localStorage.getItem('authData');
        if (authData) {
            try {
                const parsed = JSON.parse(authData);
                this.user = parsed.user;
                this.token = parsed.token;
                this.isAuthenticated = true;
                
                // Check if token is still valid
                if (this.isTokenExpired()) {
                    this.logout();
                }
            } catch (error) {
                console.error('Error loading auth state:', error);
                this.logout();
            }
        }
    }
    
    // Save authentication state to localStorage
    saveAuthState(user, token) {
        const authData = {
            user: user,
            token: token,
            timestamp: Date.now()
        };
        localStorage.setItem('authData', JSON.stringify(authData));
        this.user = user;
        this.token = token;
        this.isAuthenticated = true;
    }
    
    // Clear authentication state
    clearAuthState() {
        localStorage.removeItem('authData');
        this.user = null;
        this.token = null;
        this.isAuthenticated = false;
    }
    
    // Check if token is expired
    isTokenExpired() {
        if (!this.token) return true;
        
        try {
            const payload = JSON.parse(atob(this.token.split('.')[1]));
            return Date.now() >= payload.exp * 1000;
        } catch (error) {
            return true;
        }
    }
    
    // Setup authentication event listeners
    setupAuthListeners() {
        // Listen for storage changes (logout from other tabs)
        window.addEventListener('storage', (event) => {
            if (event.key === 'authData' && !event.newValue) {
                this.logout();
            }
        });
        
        // Listen for beforeunload to save state
        window.addEventListener('beforeunload', () => {
            if (this.isAuthenticated) {
                this.updateLastActivity();
            }
        });
    }
    
    // Login user
    async login(email, password, userType) {
        try {
            const response = await fetch('api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    password: password,
                    user_type: userType
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.saveAuthState(result.user, result.token);
                this.dispatchAuthEvent('login', result.user);
                return { success: true, user: result.user, redirect: result.redirect_url };
            } else {
                return { success: false, message: result.message };
            }
        } catch (error) {
            console.error('Login error:', error);
            return { success: false, message: 'An error occurred during login' };
        }
    }
    
    // Register user
    async register(userData) {
        try {
            const response = await fetch('api/auth/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Registration error:', error);
            return { success: false, message: 'An error occurred during registration' };
        }
    }
    
    // Verify OTP
    async verifyOTP(otp) {
        try {
            const response = await fetch('api/auth/verify_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ otp: otp })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.saveAuthState(result.user, result.token);
                this.dispatchAuthEvent('login', result.user);
                return { success: true, user: result.user, redirect: result.redirect_url };
            } else {
                return { success: false, message: result.message };
            }
        } catch (error) {
            console.error('OTP verification error:', error);
            return { success: false, message: 'An error occurred during verification' };
        }
    }
    
    // Resend OTP
    async resendOTP() {
        try {
            const response = await fetch('api/auth/resend_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Resend OTP error:', error);
            return { success: false, message: 'An error occurred while resending OTP' };
        }
    }
    
    // Forgot Password - Request OTP
    async forgotPassword(email, userType) {
        try {
            const response = await fetch('api/auth/forgot_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    user_type: userType
                })
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Forgot password error:', error);
            return { success: false, message: 'An error occurred while processing your request' };
        }
    }
    
    // Reset Password with OTP
    async resetPassword(otp, password) {
        try {
            const response = await fetch('api/auth/reset_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    otp: otp,
                    password: password
                })
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Reset password error:', error);
            return { success: false, message: 'An error occurred while resetting your password' };
        }
    }
    
    // Logout user
    async logout() {
        try {
            if (this.token) {
                await fetch('api/auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Content-Type': 'application/json',
                    }
                });
            }
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            this.clearAuthState();
            this.dispatchAuthEvent('logout');
            window.location.href = 'index.php';
        }
    }
    
    // Update last activity
    async updateLastActivity() {
        if (!this.isAuthenticated) return;
        
        try {
            await fetch('api/auth/update_activity.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json',
                }
            });
        } catch (error) {
            console.error('Update activity error:', error);
        }
    }
    
    // Get current user
    getCurrentUser() {
        return this.user;
    }
    
    // Check if user is authenticated
    isLoggedIn() {
        return this.isAuthenticated && !this.isTokenExpired();
    }
    
    // Get user type
    getUserType() {
        return this.user ? this.user.user_type : null;
    }
    
    // Check if user has specific role
    hasRole(role) {
        return this.user && this.user.user_type === role;
    }
    
    // Dispatch authentication events
    dispatchAuthEvent(eventType, data = null) {
        const event = new CustomEvent('authEvent', {
            detail: { type: eventType, data: data }
        });
        window.dispatchEvent(event);
    }
    
    // Make authenticated API request
    async makeAuthenticatedRequest(url, options = {}) {
        if (!this.isAuthenticated) {
            throw new Error('User not authenticated');
        }
        
        const defaultOptions = {
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json',
            }
        };
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };
        
        const response = await fetch(url, mergedOptions);
        
        // Check if token is expired
        if (response.status === 401) {
            this.logout();
            throw new Error('Session expired');
        }
        
        return response;
    }
}

// Password strength checker
class PasswordStrengthChecker {
    constructor() {
        this.requirements = {
            minLength: 8,
            hasUppercase: /[A-Z]/,
            hasLowercase: /[a-z]/,
            hasNumbers: /\d/,
            hasSpecialChar: /[!@#$%^&*(),.?":{}|<>]/
        };
    }
    
    checkStrength(password) {
        const result = {
            score: 0,
            feedback: [],
            strength: 'weak'
        };
        
        if (password.length >= this.requirements.minLength) {
            result.score += 1;
        } else {
            result.feedback.push(`At least ${this.requirements.minLength} characters`);
        }
        
        if (this.requirements.hasUppercase.test(password)) {
            result.score += 1;
        } else {
            result.feedback.push('One uppercase letter');
        }
        
        if (this.requirements.hasLowercase.test(password)) {
            result.score += 1;
        } else {
            result.feedback.push('One lowercase letter');
        }
        
        if (this.requirements.hasNumbers.test(password)) {
            result.score += 1;
        } else {
            result.feedback.push('One number');
        }
        
        if (this.requirements.hasSpecialChar.test(password)) {
            result.score += 1;
        } else {
            result.feedback.push('One special character');
        }
        
        // Determine strength
        if (result.score >= 4) {
            result.strength = 'strong';
        } else if (result.score >= 2) {
            result.strength = 'medium';
        }
        
        return result;
    }
    
    displayStrengthIndicator(password, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        const strength = this.checkStrength(password);
        
        container.innerHTML = `
            <div class="password-strength">
                <div class="strength-bar">
                    <div class="strength-fill strength-${strength.strength}" 
                         style="width: ${(strength.score / 5) * 100}%"></div>
                </div>
                <div class="strength-text">Password strength: ${strength.strength}</div>
                ${strength.feedback.length > 0 ? `
                    <div class="strength-requirements">
                        <small>Requirements: ${strength.feedback.join(', ')}</small>
                    </div>
                ` : ''}
            </div>
        `;
    }
}

// Form validation helper
class FormValidator {
    constructor() {
        this.rules = {
            email: {
                required: true,
                pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                message: 'Please enter a valid email address'
            },
            phone: {
                required: true,
                pattern: /^[\+]?[1-9][\d]{0,15}$/,
                message: 'Please enter a valid phone number'
            },
            name: {
                required: true,
                minLength: 2,
                message: 'Name must be at least 2 characters long'
            },
            password: {
                required: true,
                minLength: 6,
                message: 'Password must be at least 6 characters long'
            }
        };
    }
    
    validate(fieldName, value) {
        const rule = this.rules[fieldName];
        if (!rule) return { valid: true };
        
        if (rule.required && (!value || value.trim() === '')) {
            return { valid: false, message: `${fieldName} is required` };
        }
        
        if (rule.minLength && value.length < rule.minLength) {
            return { valid: false, message: rule.message };
        }
        
        if (rule.pattern && !rule.pattern.test(value)) {
            return { valid: false, message: rule.message };
        }
        
        return { valid: true };
    }
    
    validateForm(formData) {
        const errors = {};
        
        for (const [fieldName, value] of formData.entries()) {
            const validation = this.validate(fieldName, value);
            if (!validation.valid) {
                errors[fieldName] = validation.message;
            }
        }
        
        return {
            valid: Object.keys(errors).length === 0,
            errors: errors
        };
    }
}

// Initialize global auth manager
const authManager = new AuthManager();
const passwordChecker = new PasswordStrengthChecker();
const formValidator = new FormValidator();

// Export for global access
window.authManager = authManager;
window.passwordChecker = passwordChecker;
window.formValidator = formValidator;
