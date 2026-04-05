/**
 * Push Notification Service for AI Job Recommendation System
 * Handles push notification subscription and management
 */
class PushNotificationService {
    constructor() {
        this.swRegistration = null;
        this.isSubscribed = false;
        this.serverKey = 'YOUR_VAPID_PUBLIC_KEY'; // Replace with your VAPID public key
        this.apiEndpoint = '/job/api/notifications/push.php';
        
        this.init();
    }
    
    async init() {
        // Check for service worker support
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                // Register service worker
                this.swRegistration = await navigator.serviceWorker.register('/job/sw.js');
                console.log('Service Worker registered:', this.swRegistration);
                
                // Check current subscription status
                await this.checkSubscriptionStatus();
                
                // Set up UI
                this.setupUI();
                
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        } else {
            console.warn('Push messaging is not supported');
        }
    }
    
    async checkSubscriptionStatus() {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            this.isSubscribed = !(subscription === null);
            
            if (this.isSubscribed) {
                console.log('User is subscribed to push notifications');
                this.updateSubscriptionOnServer(subscription);
            } else {
                console.log('User is not subscribed to push notifications');
            }
            
            return this.isSubscribed;
        } catch (error) {
            console.error('Error checking subscription status:', error);
            return false;
        }
    }
    
    setupUI() {
        // Create notification permission request UI
        if (!this.hasNotificationUI()) {
            this.createNotificationUI();
        }
        
        // Update UI based on subscription status
        this.updateUI();
    }
    
    hasNotificationUI() {
        return document.getElementById('push-notification-container') !== null;
    }
    
    createNotificationUI() {
        const container = document.createElement('div');
        container.id = 'push-notification-container';
        container.className = 'notification-prompt';
        container.innerHTML = `
            <div class="notification-card">
                <div class="notification-icon">🔔</div>
                <div class="notification-content">
                    <h4>Stay Updated!</h4>
                    <p>Get notified about new job recommendations and updates</p>
                    <div class="notification-actions">
                        <button id="enable-notifications" class="btn btn-primary">Enable Notifications</button>
                        <button id="dismiss-notifications" class="btn btn-secondary">Not Now</button>
                    </div>
                </div>
                <button id="close-notification" class="close-btn">&times;</button>
            </div>
        `;
        
        // Add styles
        const style = document.createElement('style');
        style.textContent = `
            .notification-prompt {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 350px;
                animation: slideIn 0.3s ease-out;
            }
            
            .notification-card {
                background: white;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.1);
                padding: 20px;
                border: 1px solid #e1e5e9;
                position: relative;
            }
            
            .notification-icon {
                font-size: 24px;
                text-align: center;
                margin-bottom: 10px;
            }
            
            .notification-content h4 {
                margin: 0 0 8px 0;
                color: #2c3e50;
                font-size: 16px;
            }
            
            .notification-content p {
                margin: 0 0 15px 0;
                color: #7f8c8d;
                font-size: 14px;
                line-height: 1.4;
            }
            
            .notification-actions {
                display: flex;
                gap: 10px;
            }
            
            .notification-actions .btn {
                flex: 1;
                padding: 8px 16px;
                border: none;
                border-radius: 6px;
                font-size: 13px;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .btn-primary {
                background: #3498db;
                color: white;
            }
            
            .btn-primary:hover {
                background: #2980b9;
            }
            
            .btn-secondary {
                background: #ecf0f1;
                color: #7f8c8d;
            }
            
            .btn-secondary:hover {
                background: #d5dbdb;
            }
            
            .close-btn {
                position: absolute;
                top: 10px;
                right: 15px;
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: #bdc3c7;
                padding: 0;
                line-height: 1;
            }
            
            .close-btn:hover {
                color: #7f8c8d;
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @media (max-width: 768px) {
                .notification-prompt {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
            }
        `;
        
        document.head.appendChild(style);
        document.body.appendChild(container);
        
        // Add event listeners
        this.addEventListeners();
    }
    
    addEventListeners() {
        const enableBtn = document.getElementById('enable-notifications');
        const dismissBtn = document.getElementById('dismiss-notifications');
        const closeBtn = document.getElementById('close-notification');
        
        if (enableBtn) {
            enableBtn.addEventListener('click', () => this.subscribeUser());
        }
        
        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => this.dismissPrompt());
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.dismissPrompt());
        }
    }
    
    updateUI() {
        const container = document.getElementById('push-notification-container');
        const enableBtn = document.getElementById('enable-notifications');
        
        if (container && this.isSubscribed) {
            // Hide notification prompt if user is already subscribed
            container.style.display = 'none';
        }
        
        if (enableBtn) {
            enableBtn.textContent = this.isSubscribed ? 'Notifications Enabled' : 'Enable Notifications';
            enableBtn.disabled = this.isSubscribed;
        }
    }
    
    async subscribeUser() {
        try {
            // Check notification permission
            const permission = await this.requestNotificationPermission();
            
            if (permission !== 'granted') {
                throw new Error('Notification permission denied');
            }
            
            // Subscribe to push notifications
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.serverKey)
            });
            
            console.log('User subscribed to push notifications:', subscription);
            
            // Send subscription to server
            const success = await this.updateSubscriptionOnServer(subscription);
            
            if (success) {
                this.isSubscribed = true;
                this.updateUI();
                this.showSuccessMessage('Notifications enabled successfully!');
                this.dismissPrompt(2000);
            } else {
                throw new Error('Failed to save subscription on server');
            }
            
        } catch (error) {
            console.error('Failed to subscribe user:', error);
            this.showErrorMessage('Failed to enable notifications. Please try again.');
        }
    }
    
    async unsubscribeUser() {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            
            if (subscription) {
                await subscription.unsubscribe();
                console.log('User unsubscribed from push notifications');
                
                // Remove subscription from server
                await this.removeSubscriptionFromServer(subscription);
                
                this.isSubscribed = false;
                this.updateUI();
                this.showSuccessMessage('Notifications disabled');
            }
            
        } catch (error) {
            console.error('Failed to unsubscribe user:', error);
            this.showErrorMessage('Failed to disable notifications');
        }
    }
    
    async requestNotificationPermission() {\n        if ('Notification' in window) {\n            const permission = await Notification.requestPermission();\n            return permission;\n        }\n        return 'denied';\n    }\n    \n    async updateSubscriptionOnServer(subscription) {\n        try {\n            const response = await fetch(this.apiEndpoint, {\n                method: 'POST',\n                headers: {\n                    'Content-Type': 'application/json',\n                },\n                body: JSON.stringify({\n                    action: 'subscribe',\n                    subscription: subscription.toJSON()\n                })\n            });\n            \n            const result = await response.json();\n            return result.success;\n            \n        } catch (error) {\n            console.error('Error updating subscription on server:', error);\n            return false;\n        }\n    }\n    \n    async removeSubscriptionFromServer(subscription) {\n        try {\n            const response = await fetch(this.apiEndpoint, {\n                method: 'POST',\n                headers: {\n                    'Content-Type': 'application/json',\n                },\n                body: JSON.stringify({\n                    action: 'unsubscribe',\n                    subscription: subscription.toJSON()\n                })\n            });\n            \n            const result = await response.json();\n            return result.success;\n            \n        } catch (error) {\n            console.error('Error removing subscription from server:', error);\n            return false;\n        }\n    }\n    \n    urlBase64ToUint8Array(base64String) {\n        const padding = '='.repeat((4 - base64String.length % 4) % 4);\n        const base64 = (base64String + padding)\n            .replace(/-/g, '+')\n            .replace(/_/g, '/');\n        \n        const rawData = window.atob(base64);\n        const outputArray = new Uint8Array(rawData.length);\n        \n        for (let i = 0; i < rawData.length; ++i) {\n            outputArray[i] = rawData.charCodeAt(i);\n        }\n        \n        return outputArray;\n    }\n    \n    dismissPrompt(delay = 0) {\n        const container = document.getElementById('push-notification-container');\n        if (container) {\n            setTimeout(() => {\n                container.style.animation = 'slideOut 0.3s ease-in';\n                setTimeout(() => {\n                    if (container.parentNode) {\n                        container.parentNode.removeChild(container);\n                    }\n                }, 300);\n            }, delay);\n        }\n        \n        // Store dismissal to avoid showing again for some time\n        localStorage.setItem('notification-prompt-dismissed', Date.now().toString());\n    }\n    \n    shouldShowPrompt() {\n        if (this.isSubscribed) return false;\n        \n        const dismissed = localStorage.getItem('notification-prompt-dismissed');\n        if (dismissed) {\n            const dismissTime = parseInt(dismissed);\n            const dayInMs = 24 * 60 * 60 * 1000;\n            \n            // Don't show again for 24 hours after dismissal\n            if (Date.now() - dismissTime < dayInMs) {\n                return false;\n            }\n        }\n        \n        return true;\n    }\n    \n    showSuccessMessage(message) {\n        this.showToast(message, 'success');\n    }\n    \n    showErrorMessage(message) {\n        this.showToast(message, 'error');\n    }\n    \n    showToast(message, type = 'info') {\n        const toast = document.createElement('div');\n        toast.className = `toast toast-${type}`;\n        toast.textContent = message;\n        \n        const style = document.createElement('style');\n        style.textContent = `\n            .toast {\n                position: fixed;\n                bottom: 20px;\n                right: 20px;\n                padding: 12px 24px;\n                border-radius: 6px;\n                color: white;\n                font-size: 14px;\n                z-index: 10001;\n                animation: toastIn 0.3s ease-out;\n                max-width: 300px;\n            }\n            \n            .toast-success {\n                background: #27ae60;\n            }\n            \n            .toast-error {\n                background: #e74c3c;\n            }\n            \n            .toast-info {\n                background: #3498db;\n            }\n            \n            @keyframes toastIn {\n                from {\n                    transform: translateY(100%);\n                    opacity: 0;\n                }\n                to {\n                    transform: translateY(0);\n                    opacity: 1;\n                }\n            }\n        `;\n        \n        document.head.appendChild(style);\n        document.body.appendChild(toast);\n        \n        // Auto remove after 3 seconds\n        setTimeout(() => {\n            if (toast.parentNode) {\n                toast.style.animation = 'toastIn 0.3s ease-in reverse';\n                setTimeout(() => {\n                    if (toast.parentNode) {\n                        toast.parentNode.removeChild(toast);\n                    }\n                    if (style.parentNode) {\n                        style.parentNode.removeChild(style);\n                    }\n                }, 300);\n            }\n        }, 3000);\n    }\n    \n    // Send test notification\n    async sendTestNotification() {\n        try {\n            const response = await fetch('/job/api/notifications/test.php', {\n                method: 'POST',\n                headers: {\n                    'Content-Type': 'application/json'\n                }\n            });\n            \n            const result = await response.json();\n            if (result.success) {\n                this.showSuccessMessage('Test notification sent!');\n            } else {\n                this.showErrorMessage('Failed to send test notification');\n            }\n        } catch (error) {\n            console.error('Error sending test notification:', error);\n            this.showErrorMessage('Error sending test notification');\n        }\n    }\n}\n\n// Initialize push notification service when DOM is loaded\nif (document.readyState === 'loading') {\n    document.addEventListener('DOMContentLoaded', () => {\n        window.pushNotificationService = new PushNotificationService();\n    });\n} else {\n    window.pushNotificationService = new PushNotificationService();\n}\n\n// Export for module systems\nif (typeof module !== 'undefined' && module.exports) {\n    module.exports = PushNotificationService;\n}