<!-- Camera Access Modal -->
<div id="camera-access-modal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Camera Access Required</h3>
            <button type="button" class="close-camera-modal">&times;</button>
        </div>
        
        <div id="camera-permission-container">
            <div class="modal-body">
                <div class="camera-info">
                    <div class="camera-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                    </div>
                    <p class="camera-message">
                        This assignment requires camera access for proctoring purposes. 
                        Your camera will be used to verify your identity and ensure academic integrity.
                    </p>
                    <div class="camera-notice">
                        <p><strong>Important:</strong></p>
                        <ul>
                            <li>Your camera feed will be monitored during the assignment</li>
                            <li>Periodic screenshots may be taken</li>
                            <li>Leaving the assignment tab may be logged</li>
                            <li>You must allow camera access to continue</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-camera-modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-camera-access">Allow Camera Access</button>
            </div>
        </div>
        
        <div id="video-container" class="hidden">
            <video id="proctor-video" class="proctor-video" autoplay muted></video>
            <p class="proctor-notice">Proctoring is active. Please keep your face visible in the camera.</p>
        </div>
    </div>
</div>

<!-- Message Container for Notifications -->
<div id="message-container"></div>