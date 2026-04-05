/**
 * Camera Access and Proctoring Module for Assignments
 */

let stream = null;
let screenshotInterval = null;
let videoElement = null;

// Initialize camera access functionality
function initCameraAccess() {
    // Add event listeners to assignment start buttons
    document.addEventListener('DOMContentLoaded', () => {
        const startButtons = document.querySelectorAll('.start-assignment-btn');
        if (startButtons.length > 0) {
            startButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    showCameraAccessModal();
                });
            });
        }
        
        // Setup modal close buttons
        const closeButtons = document.querySelectorAll('.close-camera-modal');
        if (closeButtons.length > 0) {
            closeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    closeCameraModal();
                });
            });
        }
        
        // Setup camera access confirmation button
        const confirmButton = document.getElementById('confirm-camera-access');
        if (confirmButton) {
            confirmButton.addEventListener('click', () => {
                requestCameraAccess();
            });
        }
    });
}

// Show camera access modal
function showCameraAccessModal() {
    const modal = document.getElementById('camera-access-modal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

// Close camera modal and stop any active streams
function closeCameraModal() {
    const modal = document.getElementById('camera-access-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
    
    // Stop any active streams
    stopCameraStream();
}

// Request camera access from user
function requestCameraAccess() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showMessage('Camera access is not supported in your browser.', 'error');
        return;
    }
    
    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
        .then((mediaStream) => {
            stream = mediaStream;
            startProctoring();
        })
        .catch((err) => {
            console.error('Error accessing camera:', err);
            showMessage('Camera access denied. You must allow camera access to continue with the assignment.', 'error');
        });
}

// Start proctoring with camera feed
function startProctoring() {
    // Hide the permission modal
    document.getElementById('camera-permission-container').classList.add('hidden');
    
    // Show the video container
    const videoContainer = document.getElementById('video-container');
    videoContainer.classList.remove('hidden');
    
    // Setup video element
    videoElement = document.getElementById('proctor-video');
    if (videoElement) {
        videoElement.srcObject = stream;
        videoElement.onloadedmetadata = () => {
            videoElement.play();
        };
    }
    
    // Show the assignment content
    document.getElementById('assignment-content').classList.remove('hidden');
    
    // Start monitoring
    startMonitoring();
    
    // Show success message
    showMessage('Proctoring started. You can now begin your assignment.', 'success');
}

// Start monitoring activities
function startMonitoring() {
    // Take screenshots periodically
    screenshotInterval = setInterval(() => {
        takeScreenshot();
    }, 60000); // Every minute
    
    // Monitor tab visibility
    document.addEventListener('visibilitychange', handleVisibilityChange);
    
    // Monitor window focus
    window.addEventListener('blur', () => {
        logActivity('User switched to another window');
    });
    
    window.addEventListener('focus', () => {
        logActivity('User returned to assignment window');
    });
}

// Handle visibility change
function handleVisibilityChange() {
    if (document.hidden) {
        logActivity('User left the assignment tab');
    } else {
        logActivity('User returned to the assignment tab');
    }
}

// Take screenshot for proctoring
function takeScreenshot() {
    if (!videoElement || !stream) return;
    
    try {
        const canvas = document.createElement('canvas');
        canvas.width = videoElement.videoWidth;
        canvas.height = videoElement.videoHeight;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
        
        // Convert to data URL
        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
        
        // Here you would typically send this to your server
        // For now, we'll just log it
        console.log('Screenshot taken:', new Date().toISOString());
        
        // In a real implementation, you would send this to your server:
        // sendScreenshotToServer(dataUrl);
    } catch (err) {
        console.error('Error taking screenshot:', err);
    }
}

// Log proctoring activity
function logActivity(activity) {
    console.log('Proctoring activity:', activity, new Date().toISOString());
    
    // In a real implementation, you would send this to your server:
    // sendActivityLogToServer(activity);
}

// Stop camera stream and monitoring
function stopCameraStream() {
    if (stream) {
        stream.getTracks().forEach(track => {
            track.stop();
        });
        stream = null;
    }
    
    if (screenshotInterval) {
        clearInterval(screenshotInterval);
        screenshotInterval = null;
    }
    
    // Remove event listeners
    document.removeEventListener('visibilitychange', handleVisibilityChange);
}

// Using utility functions from utility.js

function showMessage(message, type = 'info') {
    // Use the utility function
    JobPortalUtils.showNotification(message, type);
}

// Initialize the module
initCameraAccess();