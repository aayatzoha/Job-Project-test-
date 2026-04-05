/**
 * Utility functions for the job portal application
 * This file centralizes common functions used across multiple JS files
 */

// Create a global utility object
window.JobPortalUtils = window.JobPortalUtils || {};

/**
 * Display a notification message to the user
 * @param {string} message - The message to display
 * @param {string} type - The type of message (info, success, warning, error)
 */
JobPortalUtils.showNotification = function(message, type = 'info') {
    const notificationContainer = document.getElementById('notification-container') || JobPortalUtils.createNotificationContainer();
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-message">${message}</span>
            <button class="notification-close">&times;</button>
        </div>
    `;
    
    notification.querySelector('.notification-close').addEventListener('click', function() {
        notification.remove();
    });
    
    notificationContainer.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 500);
    }, 5000);
};

/**
 * Create notification container if it doesn't exist
 * @returns {HTMLElement} The notification container
 */
JobPortalUtils.createNotificationContainer = function() {
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'notification-container';
        document.body.appendChild(container);
    }
    return container;
};

/**
 * Show a specific section and hide others
 * @param {string} sectionName - The ID of the section to show
 */
JobPortalUtils.showSection = function(sectionName) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Show the selected section
    const selectedSection = document.getElementById(sectionName);
    if (selectedSection) {
        selectedSection.style.display = 'block';
    }
    
    // Update active navigation item if applicable
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-section') === sectionName) {
            item.classList.add('active');
        }
    });
    
    // Save the current section to localStorage if user is logged in
    const userId = localStorage.getItem('user_id');
    if (userId) {
        localStorage.setItem('current_section', sectionName);
    }
};

/**
 * Format a phone number to a standard format
 * @param {string} phone - The phone number to format
 * @returns {string} The formatted phone number
 */
JobPortalUtils.formatPhoneNumber = function(phone) {
    if (!phone) return '';
    const cleaned = ('' + phone).replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return '(' + match[1] + ') ' + match[2] + '-' + match[3];
    }
    return phone;
};

/**
 * Debounce function to limit how often a function can be called
 * @param {Function} func - The function to debounce
 * @param {number} wait - The time to wait in milliseconds
 * @returns {Function} The debounced function
 */
JobPortalUtils.debounce = function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

/**
 * Validate an email address
 * @param {string} email - The email to validate
 * @returns {boolean} Whether the email is valid
 */
JobPortalUtils.validateEmail = function(email) {
    const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    return re.test(String(email).toLowerCase());
};

/**
 * Validate a phone number
 * @param {string} phone - The phone number to validate
 * @returns {boolean} Whether the phone number is valid
 */
JobPortalUtils.validatePhone = function(phone) {
    const re = /^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/;
    return re.test(phone);
};