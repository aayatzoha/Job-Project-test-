/**
 * AI-Powered Proctoring System
 * Handles video monitoring, face detection, and cheating prevention during assessments
 */
class ProctoringSystem {
    constructor(assessmentId, userAssessmentId) {
        this.assessmentId = assessmentId;
        this.userAssessmentId = userAssessmentId;
        this.isActive = false;
        this.stream = null;
        this.videoElement = null;
        this.canvas = null;
        this.context = null;
        this.faceDetector = null;
        
        // Monitoring data
        this.proctoringData = {
            tab_switches: 0,
            window_focus_lost: 0,
            fullscreen_exits: 0,
            face_detection_failures: 0,
            multiple_faces_detected: 0,
            suspicious_movements: 0,
            video_feed_interruptions: 0,
            audio_anomalies: 0
        };
        
        // Thresholds
        this.thresholds = {
            face_detection_interval: 2000, // Check every 2 seconds
            movement_sensitivity: 0.3,
            face_confidence_threshold: 0.7
        };
        
        // Timers
        this.faceDetectionTimer = null;
        this.movementDetectionTimer = null;
        
        this.initializeProctoring();
    }
    
    async initializeProctoring() {
        try {
            await this.setupMediaDevices();
            await this.loadFaceDetection();
            this.setupEventListeners();
            this.startMonitoring();
            
            console.log('Proctoring system initialized successfully');
        } catch (error) {
            console.error('Failed to initialize proctoring:', error);
            this.showError('Camera and microphone access required for proctored assessment');
        }
    }
    
    async setupMediaDevices() {
        try {
            // Request camera and microphone permissions
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                },
                audio: true
            });
            
            // Create video element for monitoring
            this.videoElement = document.createElement('video');
            this.videoElement.srcObject = this.stream;
            this.videoElement.autoplay = true;
            this.videoElement.muted = true;
            this.videoElement.style.position = 'fixed';
            this.videoElement.style.top = '20px';
            this.videoElement.style.right = '20px';
            this.videoElement.style.width = '200px';
            this.videoElement.style.height = '150px';
            this.videoElement.style.border = '2px solid #007bff';
            this.videoElement.style.borderRadius = '8px';
            this.videoElement.style.zIndex = '9999';
            this.videoElement.style.background = '#000';
            
            // Add to page
            document.body.appendChild(this.videoElement);
            
            // Create canvas for face detection
            this.canvas = document.createElement('canvas');
            this.context = this.canvas.getContext('2d');
            
            return true;
        } catch (error) {
            throw new Error('Camera/Microphone access denied: ' + error.message);
        }
    }
    
    async loadFaceDetection() {
        // Load face detection library (using face-api.js as example)
        try {
            if (typeof faceapi !== 'undefined') {
                // Load models
                await faceapi.nets.tinyFaceDetector.loadFromUri('/job/assets/models');
                await faceapi.nets.faceLandmark68Net.loadFromUri('/job/assets/models');
                await faceapi.nets.faceRecognitionNet.loadFromUri('/job/assets/models');
                
                this.faceDetector = faceapi;
                console.log('Face detection models loaded');
            } else {
                // Fallback to basic detection
                console.warn('Face detection library not available, using basic monitoring');
                this.faceDetector = null;
            }
        } catch (error) {
            console.warn('Face detection setup failed:', error);
            this.faceDetector = null;
        }
    }
    
    setupEventListeners() {
        // Tab switching detection
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.proctoringData.tab_switches++;
                this.logSuspiciousActivity('tab_switch', 'User switched tabs or minimized window');
            }
        });
        
        // Window focus detection
        window.addEventListener('blur', () => {
            this.proctoringData.window_focus_lost++;
            this.logSuspiciousActivity('focus_lost', 'Window lost focus');
        });
        
        // Fullscreen exit detection
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                this.proctoringData.fullscreen_exits++;
                this.logSuspiciousActivity('fullscreen_exit', 'User exited fullscreen mode');
            }
        });
        
        // Keyboard shortcuts prevention
        document.addEventListener('keydown', (e) => {
            // Prevent common cheating shortcuts
            const forbiddenKeys = [
                'F12', // DevTools
                'F5',  // Refresh
                'F11', // Fullscreen toggle
            ];
            
            if (forbiddenKeys.includes(e.key) || 
                (e.ctrlKey && ['a', 'c', 'v', 'x', 'z', 'y', 'f', 'h'].includes(e.key.toLowerCase())) ||
                (e.altKey && e.key === 'Tab')) {
                
                e.preventDefault();
                this.logSuspiciousActivity('forbidden_key', `Attempted to use ${e.key}`);
                return false;
            }
        });
        
        // Right-click prevention
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.logSuspiciousActivity('context_menu', 'Right-click attempted');
        });
        
        // Selection prevention
        document.addEventListener('selectstart', (e) => {
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
            }
        });
    }
    
    startMonitoring() {
        if (!this.isActive) {
            this.isActive = true;
            
            // Start face detection
            if (this.faceDetector) {
                this.faceDetectionTimer = setInterval(() => {
                    this.detectFaces();
                }, this.thresholds.face_detection_interval);
            }
            
            // Start movement detection
            this.movementDetectionTimer = setInterval(() => {
                this.detectMovement();
            }, 1000);
            
            // Force fullscreen
            this.enterFullscreen();
            
            console.log('Monitoring started');
        }
    }
    
    stopMonitoring() {
        this.isActive = false;
        
        // Clear timers
        if (this.faceDetectionTimer) {
            clearInterval(this.faceDetectionTimer);
        }
        if (this.movementDetectionTimer) {
            clearInterval(this.movementDetectionTimer);
        }
        
        // Stop video stream
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
        
        // Remove video element
        if (this.videoElement && this.videoElement.parentNode) {
            this.videoElement.parentNode.removeChild(this.videoElement);
        }
        
        // Exit fullscreen
        if (document.fullscreenElement) {
            document.exitFullscreen();
        }
        
        console.log('Monitoring stopped');
    }
    
    async detectFaces() {
        if (!this.videoElement || !this.faceDetector) return;
        
        try {
            // Set canvas dimensions
            this.canvas.width = this.videoElement.videoWidth;
            this.canvas.height = this.videoElement.videoHeight;
            
            // Draw video frame to canvas
            this.context.drawImage(this.videoElement, 0, 0, this.canvas.width, this.canvas.height);
            
            // Detect faces
            const detections = await this.faceDetector
                .detectAllFaces(this.canvas, new this.faceDetector.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptors();
            
            if (detections.length === 0) {
                this.proctoringData.face_detection_failures++;
                this.logSuspiciousActivity('no_face', 'No face detected');
                this.showWarning('Please ensure your face is visible');
            } else if (detections.length > 1) {
                this.proctoringData.multiple_faces_detected++;
                this.logSuspiciousActivity('multiple_faces', `${detections.length} faces detected`);
                this.showWarning('Multiple people detected in frame');
            } else {
                // Single face detected - check quality
                const detection = detections[0];
                if (detection.detection.score < this.thresholds.face_confidence_threshold) {
                    this.proctoringData.face_detection_failures++;
                    this.showWarning('Face not clearly visible');
                }
            }
            
            // Analyze facial landmarks for suspicious behavior
            if (detections.length === 1) {
                this.analyzeFacialBehavior(detections[0]);
            }
            
        } catch (error) {
            console.error('Face detection error:', error);
            this.proctoringData.face_detection_failures++;
        }
    }
    
    analyzeFacialBehavior(detection) {
        // Analyze eye movement, head position, etc.
        const landmarks = detection.landmarks;
        
        if (landmarks) {
            // Get eye positions
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();
            
            // Check if user is looking away (basic implementation)
            const eyeCenter = {
                x: (leftEye[0].x + rightEye[0].x) / 2,
                y: (leftEye[0].y + rightEye[0].y) / 2
            };
            
            // Store previous eye position for movement tracking
            if (this.previousEyePosition) {
                const movement = Math.sqrt(
                    Math.pow(eyeCenter.x - this.previousEyePosition.x, 2) +
                    Math.pow(eyeCenter.y - this.previousEyePosition.y, 2)
                );
                
                if (movement > this.thresholds.movement_sensitivity * 100) {
                    this.proctoringData.suspicious_movements++;
                }
            }
            
            this.previousEyePosition = eyeCenter;
        }
    }
    
    detectMovement() {
        // Basic movement detection using canvas frame comparison
        if (!this.previousFrame) {
            this.captureFrame();
            return;
        }
        
        const currentFrame = this.captureFrame();
        const diff = this.compareFrames(this.previousFrame, currentFrame);
        
        if (diff > 0.5) { // Threshold for significant movement
            this.proctoringData.suspicious_movements++;
        }
        
        this.previousFrame = currentFrame;
    }
    
    captureFrame() {
        if (!this.videoElement || !this.canvas) return null;
        
        this.canvas.width = this.videoElement.videoWidth;
        this.canvas.height = this.videoElement.videoHeight;
        this.context.drawImage(this.videoElement, 0, 0, this.canvas.width, this.canvas.height);
        
        return this.context.getImageData(0, 0, this.canvas.width, this.canvas.height);
    }
    
    compareFrames(frame1, frame2) {
        if (!frame1 || !frame2) return 0;
        
        let diff = 0;
        const data1 = frame1.data;
        const data2 = frame2.data;
        
        for (let i = 0; i < data1.length; i += 4) {
            diff += Math.abs(data1[i] - data2[i]) + 
                   Math.abs(data1[i + 1] - data2[i + 1]) + 
                   Math.abs(data1[i + 2] - data2[i + 2]);
        }
        
        return diff / (data1.length / 4) / (255 * 3);
    }
    
    enterFullscreen() {
        const element = document.documentElement;
        if (element.requestFullscreen) {
            element.requestFullscreen();
        } else if (element.mozRequestFullScreen) {
            element.mozRequestFullScreen();
        } else if (element.webkitRequestFullscreen) {
            element.webkitRequestFullscreen();
        } else if (element.msRequestFullscreen) {
            element.msRequestFullscreen();
        }
    }
    
    logSuspiciousActivity(type, details) {
        console.warn(`Suspicious activity detected: ${type} - ${details}`);
        
        // Send to server for logging
        fetch('/job/api/assessments/log_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_assessment_id: this.userAssessmentId,
                activity_type: type,
                details: details,
                timestamp: new Date().toISOString(),
                proctoring_data: this.proctoringData
            })
        });
        
        // Show warning to user
        if (this.proctoringData.tab_switches > 3 || 
            this.proctoringData.window_focus_lost > 5 ||
            this.proctoringData.fullscreen_exits > 2) {
            this.showSevereWarning();
        }
    }
    
    showWarning(message) {
        // Create warning modal
        const warning = document.createElement('div');
        warning.className = 'proctoring-warning';
        warning.innerHTML = `
            <div class="warning-content">
                <div class="warning-icon">⚠️</div>
                <div class="warning-message">${message}</div>
            </div>
        `;
        warning.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 193, 7, 0.95);
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            text-align: center;
            max-width: 400px;
        `;
        
        document.body.appendChild(warning);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (warning.parentNode) {
                warning.parentNode.removeChild(warning);
            }
        }, 3000);
    }
    
    showSevereWarning() {
        const warning = document.createElement('div');
        warning.className = 'proctoring-severe-warning';
        warning.innerHTML = `
            <div class="severe-warning-content">
                <div class="warning-icon">🚨</div>
                <h3>Assessment Integrity Warning</h3>
                <p>Multiple violations detected. Continued violations may result in assessment termination.</p>
                <button onclick="this.parentElement.parentElement.remove()">I Understand</button>
            </div>
        `;
        warning.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(220, 53, 69, 0.95);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20000;
            text-align: center;
        `;
        
        document.body.appendChild(warning);
    }
    
    showError(message) {
        alert('Proctoring Error: ' + message);
    }
    
    getProctoringData() {
        return this.proctoringData;
    }
    
    // Record assessment session data
    recordAssessmentData() {
        return {
            ...this.proctoringData,
            assessment_id: this.assessmentId,
            user_assessment_id: this.userAssessmentId,
            session_duration: Date.now() - this.startTime,
            browser_info: navigator.userAgent,
            screen_resolution: `${screen.width}x${screen.height}`,
            timestamp: new Date().toISOString()
        };
    }
}

// Proctoring utilities
const ProctoringUtils = {
    // Check if browser supports required features
    checkBrowserCompatibility() {
        const required = [
            'mediaDevices' in navigator,
            'getUserMedia' in navigator.mediaDevices,
            'requestFullscreen' in document.documentElement,
            'hidden' in document
        ];
        
        return required.every(Boolean);
    },
    
    // Initialize proctoring for assessment
    initializeForAssessment(assessmentId, userAssessmentId) {
        if (!this.checkBrowserCompatibility()) {
            throw new Error('Browser not compatible with proctoring features');
        }
        
        return new ProctoringSystem(assessmentId, userAssessmentId);
    }
};

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ProctoringSystem, ProctoringUtils };
}