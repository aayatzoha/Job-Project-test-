/**
 * Camera Recording Module for Assignment Submissions
 */
class CameraRecording {
    constructor(options = {}) {
        this.options = {
            videoElement: null,
            startButton: null,
            stopButton: null,
            assignmentId: null,
            onStart: () => {},
            onStop: () => {},
            onError: () => {},
            ...options
        };
        
        this.mediaRecorder = null;
        this.recordedBlobs = [];
        this.stream = null;
        this.recordingId = null;
        this.isRecording = false;
        
        this.init();
    }
    
    init() {
        if (!this.options.videoElement) {
            this.handleError('Video element is required');
            return;
        }
        
        if (this.options.startButton) {
            this.options.startButton.addEventListener('click', () => this.startRecording());
        }
        
        if (this.options.stopButton) {
            this.options.stopButton.addEventListener('click', () => this.stopRecording());
        }
    }
    
    async requestCameraPermission() {
        try {
            const constraints = {
                audio: true,
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            };
            
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.options.videoElement.srcObject = this.stream;
            
            return true;
        } catch (error) {
            this.handleError('Error accessing camera: ' + error.message);
            return false;
        }
    }
    
    async startRecording() {
        if (this.isRecording) return;
        
        if (!this.stream) {
            const hasPermission = await this.requestCameraPermission();
            if (!hasPermission) return;
        }
        
        // Notify server to start recording session
        try {
            const response = await fetch('../api/jobs/recording.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'start',
                    assignment_id: this.options.assignmentId
                })
            });
            
            const data = await response.json();
            if (!data.success) {
                this.handleError('Server error: ' + data.message);
                return;
            }
            
            this.recordingId = data.data.recording_id;
            
            // Start recording
            this.recordedBlobs = [];
            const options = { mimeType: 'video/webm;codecs=vp9,opus' };
            
            try {
                this.mediaRecorder = new MediaRecorder(this.stream, options);
            } catch (e) {
                console.error('MediaRecorder error:', e);
                try {
                    // Try with different options
                    this.mediaRecorder = new MediaRecorder(this.stream);
                } catch (e) {
                    this.handleError('MediaRecorder not supported by this browser');
                    return;
                }
            }
            
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data && event.data.size > 0) {
                    this.recordedBlobs.push(event.data);
                }
            };
            
            this.mediaRecorder.start(100); // Collect 100ms chunks
            this.isRecording = true;
            
            if (this.options.onStart) {
                this.options.onStart(this.recordingId);
            }
            
        } catch (error) {
            this.handleError('Error starting recording: ' + error.message);
        }
    }
    
    async stopRecording() {
        if (!this.isRecording || !this.mediaRecorder) return;
        
        return new Promise((resolve) => {
            this.mediaRecorder.onstop = async () => {
                try {
                    // Notify server to stop recording session
                    const stopResponse = await fetch('../api/jobs/recording.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'stop',
                            recording_id: this.recordingId
                        })
                    });
                    
                    const stopData = await stopResponse.json();
                    if (!stopData.success) {
                        this.handleError('Server error stopping recording: ' + stopData.message);
                        resolve(false);
                        return;
                    }
                    
                    // Upload the video
                    const blob = new Blob(this.recordedBlobs, { type: 'video/webm' });
                    const formData = new FormData();
                    formData.append('video', blob, 'recording.webm');
                    formData.append('recording_id', this.recordingId);
                    formData.append('action', 'save');
                    
                    const uploadResponse = await fetch('../api/jobs/recording.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const uploadData = await uploadResponse.json();
                    if (!uploadData.success) {
                        this.handleError('Error uploading video: ' + uploadData.message);
                        resolve(false);
                        return;
                    }
                    
                    if (this.options.onStop) {
                        this.options.onStop(this.recordingId, uploadData.data.path);
                    }
                    
                    this.isRecording = false;
                    resolve(true);
                    
                } catch (error) {
                    this.handleError('Error finalizing recording: ' + error.message);
                    resolve(false);
                }
            };
            
            this.mediaRecorder.stop();
        });
    }
    
    handleError(message) {
        console.error(message);
        if (this.options.onError) {
            this.options.onError(message);
        }
    }
    
    cleanup() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
        
        this.isRecording = false;
        this.mediaRecorder = null;
        this.recordedBlobs = [];
    }
}