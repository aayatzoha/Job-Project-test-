// Using utility functions from utility.js

// Dashboard state management
class DashboardManager {
    constructor() {
        this.currentSection = 'dashboard';
        this.isLoading = false;
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadInitialData();
    }
    
    setupEventListeners() {
        // Handle section navigation
        document.addEventListener('click', (event) => {
            if (event.target.matches('[onclick*="showSection"]')) {
                event.preventDefault();
                const section = event.target.getAttribute('onclick').match(/showSection\('([^']+)'\)/)[1];
                this.showSection(section);
            }
        });
        
        // Handle mobile menu toggle
        document.addEventListener('click', (event) => {
            if (event.target.matches('.mobile-menu-toggle')) {
                this.toggleMobileMenu();
            }
        });
        
        // Handle notifications
        document.addEventListener('click', (event) => {
            if (event.target.matches('.notification-btn')) {
                this.toggleNotifications();
            }
        });
    }
    
    loadInitialData() {
        // Load dashboard data if needed
        this.updateActivityStatus();
        
        // Set up periodic updates
        setInterval(() => {
            this.updateActivityStatus();
        }, 30000); // Update every 30 seconds
    }
    
    showSection(sectionName) {
        if (this.isLoading) return;
        
        // Hide all sections
        document.querySelectorAll('.dashboard-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Remove active class from all nav items
        document.querySelectorAll('.sidebar-nav li').forEach(item => {
            item.classList.remove('active');
        });
        
        // Show selected section
        const targetSection = document.getElementById(`${sectionName}-section`);
        if (targetSection) {
            targetSection.classList.add('active');
            this.currentSection = sectionName;
            
            // Add active class to nav item
            const navItem = document.querySelector(`[onclick="showSection('${sectionName}')"]`).closest('li');
            if (navItem) {
                navItem.classList.add('active');
            }
            
            // Load section content
            this.loadSectionContent(sectionName);
        }
    }
    
    async loadSectionContent(sectionName) {
        const contentElement = document.getElementById(`${sectionName}-content`);
        if (!contentElement) return;
        
        // Show loading state
        contentElement.innerHTML = '<div class="loading-state"><div class="loading"></div><p>Loading...</p></div>';
        
        try {
            let content = '';
            
            switch (sectionName) {
                case 'recommendations':
                    content = await this.loadRecommendations();
                    break;
                case 'search':
                    content = await this.loadJobSearch();
                    break;
                case 'resume':
                    content = await this.loadResumeManager();
                    break;
                case 'assessments':
                    content = await this.loadAssessments();
                    break;
                case 'applications':
                    content = await this.loadApplications();
                    break;
                case 'assignments':
                    content = await this.loadAssignments();
                    break;
                case 'companies':
                    content = await this.loadCompanies();
                    break;
                case 'profile':
                    content = await this.loadProfile();
                    break;
                case 'chatbot':
                    content = await this.loadChatbot();
                    break;
                default:
                    content = '<p>Section not implemented yet.</p>';
            }
            
            contentElement.innerHTML = content;
            
            // Initialize section-specific functionality
            this.initializeSection(sectionName);
            
        } catch (error) {
            console.error('Error loading section content:', error);
            contentElement.innerHTML = '<div class="error-state"><p>Error loading content. Please try again.</p></div>';
        }
    }
    
    async loadRecommendations() {
        try {
            // Fetch all active jobs (enriched with AI data when available)
            const response = await fetch('../api/jobs/recommendations.php', { credentials: 'same-origin' });
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok) {
                console.error('Recommendations HTTP error:', response.status, response.statusText);
                return '<div class="error-state"><p>Error loading recommendations (HTTP error). Please try again.</p></div>';
            }
            if (!contentType.includes('application/json')) {
                console.error('Recommendations unexpected content-type:', contentType);
                return '<div class="error-state"><p>Error loading recommendations (unexpected response). Please log in again.</p></div>';
            }
            const data = await response.json();
            
            if (data.success) {
                return this.renderRecommendations(data.recommendations);
            } else {
                return '<div class="empty-state"><p>No recommendations available.</p></div>';
            }
        } catch (error) {
            console.error('Error loading recommendations:', error);
            return '<div class="error-state"><p>Error loading recommendations.</p></div>';
        }
    }
    
    renderRecommendations(recommendations) {
        if (!recommendations || recommendations.length === 0) {
            return '<div class="empty-state"><p>No job recommendations available. Complete your profile and take assessments to get personalized recommendations!</p></div>';
        }
        
        let html = '<div class="recommendations-grid">';
        
        recommendations.forEach(rec => {
            // Safely parse match reasons (can be JSON string or array)
            let matchReasons = [];
            if (Array.isArray(rec.match_reasons)) {
                matchReasons = rec.match_reasons;
            } else if (typeof rec.match_reasons === 'string' && rec.match_reasons.length > 0) {
                try {
                    const parsed = JSON.parse(rec.match_reasons);
                    if (Array.isArray(parsed)) matchReasons = parsed;
                } catch (e) {
                    // Fallback to empty if parse fails
                    matchReasons = [];
                }
            }

            // Guard against null/undefined job_type
            const jobTypeDisplay = (rec.job_type || '').toString().replace('_', ' ') || 'Not specified';
            const companyName = rec.company_name || 'Unknown Company';
            const location = rec.location || 'Not specified';
            const score = (typeof rec.recommendation_score === 'number') ? Math.round(rec.recommendation_score) : 0;

            html += `
                <div class="recommendation-card">
                    <div class="card-header">
                        <h3>${rec.title || 'Untitled Role'}</h3>
                        <div class="match-score">
                            <span class="score">${score}%</span>
                        </div>
                    </div>
                    <div class="card-content">
                        <p class="company">${companyName}</p>
                        <p class="location">📍 ${location}</p>
                        <p class="job-type">${jobTypeDisplay}</p>
                        <div class="match-reasons">
                            <h4>Why this job matches you:</h4>
                            <ul>
                                ${matchReasons.map(reason => `<li>${reason}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                    <div class="card-actions">
                        ${rec.has_applied ? 
                            '<button class="btn btn-secondary" disabled>You Have Applied</button>' :
                            `<button class="btn btn-primary" onclick="applyToJob(${rec.job_id})">Apply Now</button>`
                        }
                        <button class="btn btn-secondary" onclick="viewJobDetails(${rec.job_id})">View Details</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    async loadJobSearch() {
        return `
            <div class="job-search-container">
                <div class="search-filters">
                    <div class="filter-group">
                        <label>Keywords</label>
                        <input type="text" id="search-keywords" placeholder="Job title, skills, company...">
                    </div>
                    <div class="filter-group">
                        <label>Location</label>
                        <input type="text" id="search-location" placeholder="City, state, or remote">
                    </div>
                    <div class="filter-group">
                        <label>Job Type</label>
                        <select id="search-job-type">
                            <option value="">All Types</option>
                            <option value="full_time">Full Time</option>
                            <option value="part_time">Part Time</option>
                            <option value="contract">Contract</option>
                            <option value="internship">Internship</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Experience Level</label>
                        <select id="search-experience">
                            <option value="">All Levels</option>
                            <option value="entry">Entry Level</option>
                            <option value="mid">Mid Level</option>
                            <option value="senior">Senior Level</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" onclick="searchJobs()">Search Jobs</button>
                </div>
                <div id="search-results" class="search-results">
                    <div class="empty-state">
                        <p>Enter search criteria and click "Search Jobs" to find opportunities.</p>
                    </div>
                </div>
            </div>
        `;
    }
    
    async loadResumeManager() {
        return `
            <div class="resume-manager">
                <div class="resume-upload">
                    <h3>Upload Resume</h3>
                    <div class="upload-area" id="upload-area">
                        <div class="upload-content">
                            <span class="upload-icon">📄</span>
                            <p>Drag and drop your resume here or click to browse</p>
                            <p class="upload-note">Supported formats: PDF, DOC, DOCX (Max 5MB)</p>
                        </div>
                        <input type="file" id="resume-file" accept=".pdf,.doc,.docx" style="display: none;">
                    </div>
                    <button class="btn btn-primary" onclick="uploadResume()">Upload Resume</button>
                </div>
                
                <div class="resume-builder">
                    <h3>Build Resume Online</h3>
                    <p>Create a professional resume using our guided builder</p>
                    <button class="btn btn-secondary" onclick="openResumeBuilder()">Start Building</button>
                </div>
                
                <div class="current-resume" id="current-resume">
                    <!-- Current resume info will be loaded here -->
                </div>

                <div class="download-resume">
                    <button class="btn btn-secondary" onclick="downloadResume()">Download Resume</button>
                </div>
            </div>
        `;
    }
    
    async loadAssessments() {
        try {
            // Skill type filter UI
            const skillType = document.getElementById('assessment-skill-type')?.value || '';
            
            // Load assigned assessments
            const assignedResponse = await fetch(`../api/jobs/assessments.php?type=assigned`, { credentials: 'same-origin' });
            const assignedData = await assignedResponse.json();
            
            // Load available assessments
            const availableResponse = await fetch(`../api/jobs/assessments.php?type=available${skillType ? `&skill_type=${encodeURIComponent(skillType)}` : ''}`, { credentials: 'same-origin' });
            const availableData = await availableResponse.json();
            
            // Load assessment history
            const historyResponse = await fetch('../api/jobs/assessments.php?type=history', { credentials: 'same-origin' });
            const historyData = await historyResponse.json();
            
            let assignedHtml = '';
            let availableHtml = '';
            let historyHtml = '';
            
            // Render assigned assessments
            if (assignedData.success && assignedData.assessments && assignedData.assessments.length > 0) {
                assignedHtml = '<div class="assessments-grid">';
                
                assignedData.assessments.forEach(assessment => {
                    const statusClass = assessment.status === 'not_started' ? 'not-started' : 
                                      assessment.status === 'in_progress' ? 'in-progress' : 'completed';
                    assignedHtml += `
                        <div class="assessment-card assigned-card">
                            <div class="assessment-header">
                                <h4>${assessment.title}</h4>
                                <div class="category">Assigned Assessment</div>
                                <span class="status-badge status-${statusClass}">${assessment.status_display || assessment.status}</span>
                            </div>
                            <div class="assessment-details">
                                <p>${assessment.description || 'No description available'}</p>
                                <div class="assessment-meta">
                                    <span>⏱️ ${assessment.time_display || 'N/A'}</span>
                                    <span>❓ ${assessment.question_count || 'N/A'} questions</span>
                                    ${assessment.deadline ? `<span>📅 Due: ${new Date(assessment.deadline).toLocaleDateString()}</span>` : ''}
                                </div>
                            </div>
                            <div class="assessment-actions">
                                ${assessment.status === 'not_started' || assessment.status === 'in_progress' ? 
                                    `<button class="btn btn-primary" onclick="startAssessment(${assessment.id}, ${assessment.user_assessment_id || 'null'})">${assessment.status === 'in_progress' ? 'Continue Assessment' : 'Start Assessment'}</button>` :
                                    `<button class="btn btn-secondary" onclick="viewAssessmentResult(${assessment.user_assessment_id})">View Results</button>`
                                }
                            </div>
                        </div>
                    `;
                });
                
                assignedHtml += '</div>';
            } else {
                assignedHtml = '<div class="empty-state"><p>No assigned assessments at this time.</p></div>';
            }
            
            // Render available assessments
            if (availableData.success && availableData.assessments && availableData.assessments.length > 0) {
                availableHtml = '<div class="assessments-grid">';
                
                availableData.assessments.forEach(assessment => {
                    availableHtml += `
                        <div class="assessment-card">
                            <div class="assessment-header">
                                <h4>${assessment.title}</h4>
                                <div class="category">${assessment.category}</div>
                            </div>
                            <div class="assessment-details">
                                <p>${assessment.description}</p>
                                <div class="assessment-meta">
                                    <span>⏱️ ${assessment.time_display}</span>
                                    <span>❓ ${assessment.question_count} questions</span>
                                    <span>📊 ${assessment.difficulty}</span>
                                </div>
                            </div>
                            <div class="assessment-actions">
                                <button class="btn btn-primary" onclick="startAssessment(${assessment.id})">Take Assessment</button>
                            </div>
                        </div>
                    `;
                });
                
                availableHtml += '</div>';
            } else {
                availableHtml = '<div class="empty-state"><p>No assessments available at this time.</p></div>';
            }
            
            // Render assessment history
            if (historyData.success && historyData.assessments && historyData.assessments.length > 0) {
                historyHtml = '<div class="history-grid">';
                
                historyData.assessments.forEach(assessment => {
                    historyHtml += `
                        <div class="history-item">
                            <div class="history-header">
                                <h5>${assessment.title}</h5>
                                <span class="status-badge status-${assessment.status}">${assessment.status_display}</span>
                            </div>
                            <div class="history-details">
                                <div class="score">${assessment.score ? assessment.score + '%' : 'N/A'}</div>
                                <div class="time">${assessment.time_taken || 'In progress'}</div>
                                <div class="date">${new Date(assessment.started_at).toLocaleDateString()}</div>
                            </div>
                        </div>
                    `;
                });
                
                historyHtml += '</div>';
            } else {
                historyHtml = '<div class="empty-state"><p>No assessment history yet.</p></div>';
            }
            
            return `
                <div class="assessments-container">
                    <div class="assigned-assessments-section">
                        <div class="section-header">
                            <h3>📋 My Assigned Assessments</h3>
                            <p>Assessments that have been assigned to you</p>
                        </div>
                        <div class="assigned-content">
                            ${assignedHtml}
                        </div>
                    </div>
                    
                    <div class="available-assessments-section" style="margin-top: 30px;">
                        <div class="assessments-header">
                            <h3>Available Assessments</h3>
                            <div>
                                <label style="margin-right:8px;">Skill Type:</label>
                                <select id="assessment-skill-type" onchange="window.dashboardManager.loadSectionContent('assessments')">
                                    <option value="">All</option>
                                    <option value="technical">Technical</option>
                                    <option value="soft_skill">Soft Skill</option>
                                    <option value="aptitude">Aptitude</option>
                                    <option value="domain_specific">Domain Specific</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="assessments-content">
                            ${availableHtml}
                        </div>
                    </div>
                    
                    <div class="assessment-history" style="margin-top: 30px;">
                        <h3>Assessment History</h3>
                        <div class="history-content">
                            ${historyHtml}
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error loading assessments:', error);
            return '<div class="error-state"><p>Error loading assessments. Please try again.</p></div>';
        }
    }
    
    async loadApplications() {
        try {
            const response = await fetch('../api/jobs/applications.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            let applicationsHtml = '';
            
            if (data.success && data.applications && data.applications.length > 0) {
                applicationsHtml = '<div class="applications-grid">';
                
                data.applications.forEach(app => {
                    applicationsHtml += `
                        <div class="application-card">
                            <div class="application-header">
                                <h4>${app.title}</h4>
                                <div class="company">${app.company_name}</div>
                            </div>
                            <div class="application-details">
                                <p class="location">📍 ${app.location}</p>
                                <p class="job-type">${app.job_type}</p>
                                <p class="salary">${app.salary_display}</p>
                            </div>
                            <div class="application-status">
                                <span class="status-badge status-${app.status}">${app.status_display}</span>
                                <small>Applied ${app.days_ago}</small>
                            </div>
                        </div>
                    `;
                });
                
                applicationsHtml += '</div>';
            } else {
                applicationsHtml = '<div class="empty-state"><p>No applications yet. Start applying to jobs!</p><button class="btn btn-primary" onclick="showSection(\'search\')">Search Jobs</button></div>';
            }
            
            return `
                <div class="applications-container">
                    <div class="applications-header">
                        <h3>My Job Applications</h3>
                        <p>Track the status of your job applications</p>
                    </div>
                    
                    <div class="applications-filters">
                        <select id="status-filter">
                            <option value="">All Status</option>
                            <option value="applied">Applied</option>
                            <option value="screening">Screening</option>
                            <option value="interview">Interview</option>
                            <option value="offered">Offered</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <button class="btn btn-secondary" onclick="filterApplications()">Filter</button>
                    </div>
                    
                    <div id="applications-list" class="applications-list">
                        ${applicationsHtml}
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error loading applications:', error);
            return '<div class="error-state"><p>Error loading applications. Please try again.</p></div>';
        }
    }
    
    async loadProfile() {
        try {
            // Get user profile data
            const response = await fetch('../api/profile/get.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            let profileData = {};
            if (data.success && data.profile) {
                profileData = data.profile;
            }
            
            // Get user name and email from sidebar
            const userName = document.querySelector('.user-details h4')?.textContent || '';
            const userEmail = document.querySelector('.user-details p')?.textContent || '';
            
            return `
                <div class="profile-container">
                    <div class="profile-sections">
                        <div class="profile-section">
                            <h3>Personal Information</h3>
                            <form id="personal-info-form">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" id="profile-name" value="${userName}">
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" id="profile-email" value="${userEmail}" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" id="profile-phone" value="${profileData.phone || ''}">
                                </div>
                                <div class="form-group">
                                    <label>Location</label>
                                    <input type="text" id="profile-location" value="${profileData.location || ''}" placeholder="City, State, Country">
                                </div>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                        
                        <div class="profile-section">
                            <h3>Professional Information</h3>
                            <form id="professional-info-form">
                                <div class="form-group">
                                    <label>Years of Experience</label>
                                    <input type="number" id="experience-years" value="${profileData.experience_years || 0}" min="0" max="50">
                                </div>
                                <div class="form-group">
                                    <label>Education Level</label>
                                    <select id="education-level">
                                        <option value="">Select Education</option>
                                        <option value="high_school" ${profileData.education_level === 'high_school' ? 'selected' : ''}>High School</option>
                                        <option value="diploma" ${profileData.education_level === 'diploma' ? 'selected' : ''}>Diploma</option>
                                        <option value="bachelor" ${profileData.education_level === 'bachelor' ? 'selected' : ''}>Bachelor's Degree</option>
                                        <option value="master" ${profileData.education_level === 'master' ? 'selected' : ''}>Master's Degree</option>
                                        <option value="phd" ${profileData.education_level === 'phd' ? 'selected' : ''}>PhD</option>
                                        <option value="other" ${profileData.education_level === 'other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Skills</label>
                                    <textarea id="skills" placeholder="Enter your skills separated by commas">${profileData.skills || ''}</textarea>
                                </div>
                                <div class="form-group">
                                    <label>Bio</label>
                                    <textarea id="bio" placeholder="Tell us about yourself">${profileData.bio || ''}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error loading profile:', error);
            return '<div class="error-state"><p>Error loading profile. Please try again.</p></div>';
        }
    }
    
    async loadChatbot() {
        return `
            <div class="chatbot-container">
                <div class="chatbot-header">
                    <h3>AI Career Assistant</h3>
                    <p>Get personalized career guidance and job search tips</p>
                </div>
                
                <div class="chatbot-messages" id="chatbot-messages">
                    <div class="message bot-message">
                        <div class="message-content">
                            <p>Hello! I'm your AI career assistant. How can I help you with your job search today?</p>
                        </div>
                    </div>
                </div>
                
                <div class="chatbot-input">
                    <input type="text" id="chatbot-input" placeholder="Ask me anything about your career...">
                    <button class="btn btn-primary" onclick="sendChatMessage()">Send</button>
                </div>
            </div>
        `;
    }
    
    initializeSection(sectionName) {
        switch (sectionName) {
            case 'search':
                this.initializeJobSearch();
                break;
            case 'resume':
                this.initializeResumeManager();
                break;
            case 'assessments':
                this.initializeAssessments();
                break;
            case 'applications':
                this.initializeApplications();
                break;
            case 'assignments':
                this.initializeAssignments();
                break;
            case 'companies':
                this.initializeCompanies();
                break;
            case 'profile':
                this.initializeProfile();
                break;
            case 'chatbot':
                this.initializeChatbot();
                break;
        }
    }
    
    initializeJobSearch() {
        // Initialize job search functionality
        const searchInput = document.getElementById('search-keywords');
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.searchJobs();
                }
            });
        }
    }
    
    initializeResumeManager() {
        // Initialize resume upload functionality
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('resume-file');
        
        if (uploadArea && fileInput) {
            uploadArea.addEventListener('click', () => fileInput.click());
            
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('drag-over');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('drag-over');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    this.handleFileSelection(files[0]);
                }
            });
            
            // Handle file input change
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.handleFileSelection(e.target.files[0]);
                }
            });
        }
    }
    
    handleFileSelection(file) {
        // Show file name and size
        const uploadArea = document.getElementById('upload-area');
        if (uploadArea) {
            const originalContent = uploadArea.innerHTML;
            uploadArea.innerHTML = `
                <div class="upload-content">
                    <span class="upload-icon">📄</span>
                    <p><strong>Selected:</strong> ${file.name}</p>
                    <p><small>Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</small></p>
                    <p class="upload-note">Click "Upload Resume" to proceed</p>
                </div>
            `;
            
            // Restore original content after 5 seconds if no upload
            setTimeout(() => {
                if (uploadArea.innerHTML.includes('Selected:')) {
                    uploadArea.innerHTML = originalContent;
                }
            }, 5000);
        }
    }
    
    initializeAssessments() {
        // Load available assessments
        this.loadAvailableAssessments();
        this.loadAssessmentHistory();
    }
    
    initializeApplications() {
        // Load user applications
        this.loadUserApplications();
    }
    
    initializeProfile() {
        // Add form submission handlers
        const personalInfoForm = document.getElementById('personal-info-form');
        const professionalInfoForm = document.getElementById('professional-info-form');
        
        if (personalInfoForm) {
            personalInfoForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveProfileData();
            });
        }
        
        if (professionalInfoForm) {
            professionalInfoForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveProfileData();
            });
        }
    }
    
    async saveProfileData() {
        // Get all form data
        const profileData = {
            name: document.getElementById('profile-name')?.value || '',
            phone: document.getElementById('profile-phone')?.value || '',
            location: document.getElementById('profile-location')?.value || '',
            experience_years: document.getElementById('experience-years')?.value || 0,
            education_level: document.getElementById('education-level')?.value || '',
            skills: document.getElementById('skills')?.value || '',
            bio: document.getElementById('bio')?.value || ''
        };
        
        // Show saving state
        const submitButtons = document.querySelectorAll('#personal-info-form .btn, #professional-info-form .btn');
        submitButtons.forEach(btn => {
            btn.textContent = 'Saving...';
            btn.disabled = true;
        });
        
        try {
            const response = await fetch('../api/profile/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(profileData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Profile updated successfully!');
                try {
                    const regen = await fetch('../api/recommendations/generate.php', {
                        method: 'POST',
                        credentials: 'same-origin'
                    });
                    const regenData = await regen.json();
                    if (!regenData.success) {
                        console.warn('Recommendation regeneration failed:', regenData.message);
                    }
                } catch (e) {
                    console.warn('Error regenerating recommendations:', e);
                }
                if (window.dashboardManager) {
                    window.dashboardManager.showSection('recommendations');
                }
            } else {
                alert('Error: ' + (result.message || 'Failed to save profile'));
            }
        } catch (error) {
            console.error('Profile save error:', error);
            alert('An error occurred while saving your information. Please try again.');
        } finally {
            // Restore button states
            submitButtons.forEach(btn => {
                btn.textContent = 'Save Changes';
                btn.disabled = false;
            });
        }
    }
    
    initializeChatbot() {
        // Initialize chatbot functionality
        const input = document.getElementById('chatbot-input');
        if (input) {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.sendChatMessage();
                }
            });
        }
    }
    
    async searchJobs() {
        const keywords = document.getElementById('search-keywords')?.value || '';
        const location = document.getElementById('search-location')?.value || '';
        const jobType = document.getElementById('search-job-type')?.value || '';
        const experience = document.getElementById('search-experience')?.value || '';
        
        const resultsContainer = document.getElementById('search-results');
        if (!resultsContainer) return;
        
        resultsContainer.innerHTML = '<div class="loading-state"><div class="loading"></div><p>Searching...</p></div>';
        
        try {
            const params = new URLSearchParams();
            if (keywords) params.append('keywords', keywords);
            if (location) params.append('location', location);
            if (jobType) params.append('job_type', jobType);
            if (experience) params.append('experience', experience);
            
            const url = `/job/api/jobs/search.php?${params.toString()}`;
            const response = await fetch(url, { credentials: 'same-origin' });
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Search response error:', response.status, text);
                resultsContainer.innerHTML = '<div class="error-state"><p>Search failed. Please try again.</p></div>';
                return;
            }
            const data = await response.json();
            
            if (data.success) {
                resultsContainer.innerHTML = this.renderJobSearchResults(data.jobs);
            } else {
                resultsContainer.innerHTML = `<div class=\"error-state\"><p>Search failed: ${data.message}</p></div>`;
            }
        } catch (error) {
            console.error('Search error:', error);
            resultsContainer.innerHTML = '<div class="error-state"><p>Search failed. Please try again.</p></div>';
        }
    }
    
    renderJobSearchResults(jobs) {
        if (!jobs || jobs.length === 0) {
            return '<div class="empty-state"><p>No jobs found matching your criteria.</p></div>';
        }
        
        let html = '<div class="job-results">';
        
        jobs.forEach(job => {
            html += `
                <div class="job-card">
                    <div class="job-header">
                        <h3>${job.title}</h3>
                        <div class="job-meta">
                            <span class="company">${job.company_name}</span>
                            <span class="location">📍 ${job.location}</span>
                        </div>
                    </div>
                    <div class="job-content">
                        <p class="job-type">${job.job_type.replace('_', ' ')}</p>
                        <p class="job-description">${job.description.substring(0, 200)}...</p>
                    </div>
                    <div class="job-actions">
                        <button class="btn btn-primary" onclick="applyToJob(${job.id})">Apply Now</button>
                        <button class="btn btn-secondary" onclick="viewJobDetails(${job.id})">View Details</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    async loadAvailableAssessments() {
        try {
            const response = await fetch('api/assessments/available.php');
            const data = await response.json();
            
            if (data.success) {
                const grid = document.getElementById('assessments-grid');
                if (grid) {
                    grid.innerHTML = this.renderAssessments(data.assessments);
                }
            }
        } catch (error) {
            console.error('Error loading assessments:', error);
        }
    }
    
    renderAssessments(assessments) {
        if (!assessments || assessments.length === 0) {
            return '<div class="empty-state"><p>No assessments available.</p></div>';
        }
        
        let html = '';
        
        assessments.forEach(assessment => {
            html += `
                <div class="assessment-card">
                    <div class="assessment-header">
                        <h3>${assessment.title}</h3>
                        <span class="assessment-type">${assessment.skill_type}</span>
                    </div>
                    <div class="assessment-content">
                        <p>${assessment.description}</p>
                        <div class="assessment-meta">
                            <span>⏱️ ${Math.floor(assessment.time_limit / 60)} minutes</span>
                            <span>📋 ${assessment.total_questions} questions</span>
                        </div>
                    </div>
                    <div class="assessment-actions">
                        <button class="btn btn-primary" onclick="startAssessment(${assessment.id})">Start Assessment</button>
                    </div>
                </div>
            `;
        });
        
        return html;
    }
    
    async loadAssessmentHistory() {
        try {
            const response = await fetch('api/assessments/history.php');
            const data = await response.json();
            
            if (data.success) {
                const container = document.getElementById('assessment-history-content');
                if (container) {
                    container.innerHTML = this.renderAssessmentHistory(data.history);
                }
            }
        } catch (error) {
            console.error('Error loading assessment history:', error);
        }
    }
    
    renderAssessmentHistory(history) {
        if (!history || history.length === 0) {
            return '<div class="empty-state"><p>No assessment history.</p></div>';
        }
        
        let html = '<div class="history-list">';
        
        history.forEach(item => {
            html += `
                <div class="history-item">
                    <div class="history-info">
                        <h4>${item.title}</h4>
                        <p class="history-date">${new Date(item.completed_at).toLocaleDateString()}</p>
                    </div>
                    <div class="history-score">
                        <span class="score">${Math.round(item.percentage_score)}%</span>
                        <span class="status status-${item.status}">${item.status}</span>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    async loadUserApplications() {
        try {
            const response = await fetch('../api/applications/list.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            if (data.success) {
                const container = document.getElementById('applications-list');
                if (container) {
                    container.innerHTML = this.renderApplications(data.applications);
                }
            }
        } catch (error) {
            console.error('Error loading applications:', error);
        }
    }
    
    renderApplications(applications) {
        if (!applications || applications.length === 0) {
            return '<div class="empty-state"><p>No applications found.</p></div>';
        }
        
        let html = '<div class="applications-list">';
        
        applications.forEach(app => {
            html += `
                <div class="application-card">
                    <div class="application-header">
                        <h3>${app.title}</h3>
                        <span class="application-date">${new Date(app.application_date).toLocaleDateString()}</span>
                    </div>
                    <div class="application-content">
                        <p class="company">${app.company_name}</p>
                        <p class="location">📍 ${app.location}</p>
                    </div>
                    <div class="application-status">
                        <span class="status-badge status-${app.status}">${app.status}</span>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }

    // Assignments section
    async loadAssignments() {
        try {
            const response = await fetch('../api/jobs/assignment.php', { credentials: 'same-origin' });
            const data = await response.json();

            if (data.success) {
                const assignmentsHtml = this.renderAssignments(data.assignments || []);
                return `
                    <div class="assignments-container">
                        <div class="section-header-inline">
                            <h3>📘 My Assignments</h3>
                            <p>Practice tasks and take-home assignments linked to your applications</p>
                        </div>
                        <div id="assignments-list" class="assignments-list">
                            ${assignmentsHtml}
                        </div>
                    </div>
                `;
            } else {
                return '<div class="empty-state"><p>No assignments found.</p></div>';
            }
        } catch (error) {
            console.error('Error loading assignments:', error);
            return '<div class="error-state"><p>Error loading assignments. Please try again.</p></div>';
        }
    }

    initializeAssignments() {
        // Placeholder for future interactive features in assignments section
    }

    renderAssignments(assignments) {
        if (!assignments || assignments.length === 0) {
            return '<div class="empty-state"><p>No assignments found. Complete assignments to demonstrate your skills to employers!</p></div>';
        }

        let html = '<div class="assignments-list-grid">';
        assignments.forEach(item => {
            const statusClass = (item.status || 'pending').replace('_', '-');
            const dueDate = item.due_date ? new Date(item.due_date).toLocaleDateString() : 'No due date';
            const cameraBadge = item.requires_camera ? '<span class="badge badge-warning" style="margin-left: 10px;">📹 Camera Required</span>' : '';
            
            html += `
                <div class="assignment-card">
                    <div class="assignment-header">
                        <h4>${item.title || 'Assignment'}</h4>
                        <div>
                            <span class="status-badge status-${statusClass}">${(item.status || 'pending').replace('_',' ')}</span>
                            ${cameraBadge}
                        </div>
                    </div>
                    <div class="assignment-meta">
                        <p class="company">🏢 ${item.company_name || 'Unknown Company'}</p>
                        <p class="job">💼 ${item.job_title || 'Job'}</p>
                        <p class="due">⏰ Due: ${dueDate}</p>
                    </div>
                    <div class="assignment-desc">
                        <p>${(item.description || '').substring(0,180)}${(item.description||'').length>180?'...':''}</p>
                    </div>
                    <div class="assignment-actions" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <button class="btn btn-primary btn-sm" onclick="window.dashboardManager.viewAssignment(${item.id})">View Details</button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }
    
    viewAssignment(assignmentId) {
        // Redirect to assignment detail page
        window.location.href = `../assignment_detail.php?id=${assignmentId}`;
    }

    // Companies section
    async loadCompanies() {
        try {
            const response = await fetch('../api/jobs/applied_companies.php', { credentials: 'same-origin' });
            const data = await response.json();

            if (data.success) {
                const companiesHtml = this.renderCompanies(data.companies || []);
                return `
                    <div class="companies-container">
                        <div class="section-header-inline">
                            <h3>🏢 Companies I Applied To</h3>
                            <p>Organizations associated with your job applications</p>
                        </div>
                        <div id="companies-list" class="companies-list">
                            ${companiesHtml}
                        </div>
                    </div>
                `;
            } else {
                return '<div class="empty-state"><p>No companies found.</p></div>';
            }
        } catch (error) {
            console.error('Error loading companies:', error);
            return '<div class="error-state"><p>Error loading companies. Please try again.</p></div>';
        }
    }

    initializeCompanies() {
        // Placeholder for future interactive features in companies section
    }

    renderCompanies(companies) {
        if (!companies || companies.length === 0) {
            return '<div class="empty-state"><p>No companies found.</p></div>';
        }

        let html = '<div class="companies-list-grid">';
        companies.forEach(c => {
            const website = c.website ? `<a href="${c.website}" target="_blank" rel="noopener">${c.website}</a>` : '';
            html += `
                <div class="company-card">
                    <div class="company-header">
                        <h4>${c.company_name || 'Company'}</h4>
                        <span class="industry">${c.industry || ''}</span>
                    </div>
                    <div class="company-meta">
                        ${c.location ? `<p>📍 ${c.location}</p>` : ''}
                        ${website}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }
    
    async loadUserProfile() {
        try {
            const response = await fetch('../api/profile/get.php');
            const data = await response.json();
            
            if (data.success && data.profile) {
                const profile = data.profile;
                
                // Populate form fields with correct mapping
                const fieldMappings = {
                    'profile-name': profile.name,
                    'profile-email': profile.email,
                    'profile-phone': profile.phone,
                    'profile-location': profile.location,
                    'experience-years': profile.experience_years,
                    'education-level': profile.education_level,
                    'skills': profile.skills,
                    'bio': profile.bio
                };
                
                Object.keys(fieldMappings).forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && fieldMappings[fieldId] !== null && fieldMappings[fieldId] !== undefined) {
                        field.value = fieldMappings[fieldId];
                    }
                });
            }
        } catch (error) {
            console.error('Error loading profile:', error);
        }
    }
    
    async savePersonalInfo() {
        const nameField = document.getElementById('profile-name');
        const phoneField = document.getElementById('profile-phone');
        const locationField = document.getElementById('profile-location');
        
        if (!nameField || !phoneField || !locationField) {
            alert('Form fields not found');
            return;
        }
        
        const data = {
            name: nameField.value.trim(),
            phone: phoneField.value.trim(),
            location: locationField.value.trim()
        };
        
        try {
            const submitButton = document.querySelector('#personal-info-form .btn-primary');
            const originalText = submitButton.textContent;
            submitButton.textContent = 'Saving...';
            submitButton.disabled = true;
            
            const response = await fetch('../api/profile/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Personal information saved successfully!');
                // Update sidebar user info
                const sidebarName = document.querySelector('.user-details h4');
                if (sidebarName) {
                    sidebarName.textContent = data.name;
                }
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error saving personal info:', error);
            alert('An error occurred while saving your information. Please try again.');
        } finally {
            const submitButton = document.querySelector('#personal-info-form .btn-primary');
            submitButton.textContent = 'Save Changes';
            submitButton.disabled = false;
        }
    }
    
    async saveProfessionalInfo() {
        const experienceField = document.getElementById('experience-years');
        const educationField = document.getElementById('education-level');
        const skillsField = document.getElementById('skills');
        const bioField = document.getElementById('bio');
        
        if (!experienceField || !educationField || !skillsField || !bioField) {
            alert('Form fields not found');
            return;
        }
        
        const data = {
            experience_years: experienceField.value,
            education_level: educationField.value,
            skills: skillsField.value.trim(),
            bio: bioField.value.trim()
        };
        
        try {
            const submitButton = document.querySelector('#professional-info-form .btn-primary');
            const originalText = submitButton.textContent;
            submitButton.textContent = 'Saving...';
            submitButton.disabled = true;
            
            const response = await fetch('../api/profile/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Professional information saved successfully!');
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error saving professional info:', error);
            alert('An error occurred while saving your information. Please try again.');
        } finally {
            const submitButton = document.querySelector('#professional-info-form .btn-primary');
            submitButton.textContent = 'Save Changes';
            submitButton.disabled = false;
        }
    }
    
    async sendChatMessage() {
        const input = document.getElementById('chatbot-input');
        const messagesContainer = document.getElementById('chatbot-messages');
        
        if (!input || !messagesContainer || !input.value.trim()) return;
        
        const message = input.value.trim();
        input.value = '';
        
        // Add user message
        messagesContainer.innerHTML += `
            <div class="message user-message">
                <div class="message-content">
                    <p>${message}</p>
                </div>
            </div>
        `;
        
        // Show typing indicator
        messagesContainer.innerHTML += `
            <div class="message bot-message typing">
                <div class="message-content">
                    <div class="typing-indicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        `;
        
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        try {
            const response = await fetch('../api/chatbot/simple_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message })
            });
            
            console.log('Chatbot response:', response.status, response);
            
            // Remove typing indicator
            const typingIndicator = messagesContainer.querySelector('.typing');
            if (typingIndicator) {
                typingIndicator.remove();
            }
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Chatbot data:', data);
            
            // Add bot response
            if (data.success && data.response) {
                const botMessage = data.response.message || 'I received your message but couldn\'t generate a proper response.';
                messagesContainer.innerHTML += `
                    <div class="message bot-message">
                        <div class="message-content">
                            <p>${botMessage.replace(/\n/g, '<br>')}</p>
                            ${data.response.suggestions && data.response.suggestions.length > 0 ? 
                                '<div class="suggestions">' + 
                                data.response.suggestions.map(s => `<button class="suggestion-btn" onclick="handleChatSuggestion('${s.replace(/'/g, '&apos;')}')">${s}</button>`).join('') + 
                                '</div>' : ''}
                        </div>
                    </div>
                `;
            } else {
                messagesContainer.innerHTML += `
                    <div class="message bot-message">
                        <div class="message-content">
                            <p>I apologize, but I encountered an error: ${data.message || 'Unknown error occurred'}</p>
                        </div>
                    </div>
                `;
            }
            
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
        } catch (error) {
            console.error('Error sending chat message:', error);
            
            // Remove typing indicator
            const typingIndicator = messagesContainer.querySelector('.typing');
            if (typingIndicator) {
                typingIndicator.remove();
            }
            
            // Add detailed error message for debugging
            let errorMsg = 'I apologize, but I\'m having trouble responding right now.';
            if (error.message.includes('401')) {
                errorMsg = 'Please make sure you\'re logged in to use the AI Assistant.';
            } else if (error.message.includes('fetch')) {
                errorMsg = 'Network connection error. Please check your internet connection.';
            } else {
                errorMsg += ' Error: ' + error.message;
            }
            
            messagesContainer.innerHTML += `
                <div class="message bot-message">
                    <div class="message-content">
                        <p>${errorMsg}</p>
                        <p><small>If this continues, please refresh the page and try again.</small></p>
                    </div>
                </div>
            `;
        }
    }
    
    toggleNotifications() {
        const panel = document.getElementById('notifications-panel');
        if (panel) {
            panel.classList.toggle('open');
        }
    }
    
    toggleMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('open');
        }
    }
    
    updateActivityStatus() {
        // Update user activity status
        if (typeof authManager !== 'undefined' && authManager.isLoggedIn()) {
            authManager.updateLastActivity();
        }
    }
}

// Global functions for dashboard interactions
// Using imported showSection from utility.js

function toggleNotifications() {
    if (window.dashboardManager) {
        window.dashboardManager.toggleNotifications();
    }
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('../api/auth/logout.php', { credentials: 'same-origin' })
            .then(() => { window.location.href = '../index.php'; })
            .catch(() => { window.location.href = '../index.php'; });
    }
}

async function applyToJob(jobId) {
    if (confirm('Are you sure you want to apply to this job?')) {
        try {
            const response = await fetch('../api/jobs/applications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ job_id: jobId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message with assignment info if available
                let message = result.message || 'Application submitted successfully!';
                if (result.assignments_assigned && result.assignments_assigned > 0) {
                    message += `\n\nYou have been assigned ${result.assignments_assigned} assignment(s) for this job. Check your assignments section to complete them.`;
                }
                alert(message);
                
                // Refresh current section if it's search, applications, recommendations, assignments, or dashboard
                if (window.dashboardManager) {
                    const currentSection = window.dashboardManager.currentSection;
                    if (currentSection === 'search' || currentSection === 'applications' || currentSection === 'recommendations' || currentSection === 'assignments' || currentSection === 'dashboard') {
                        window.dashboardManager.loadSectionContent(currentSection);
                    }
                }
            } else {
                alert('Application failed: ' + result.message);
            }
        } catch (error) {
            console.error('Application error:', error);
            alert('Application failed. Please try again.');
        }
    }
}

async function viewJobDetails(jobId) {
    if (!jobId) {
        alert('Invalid job ID');
        return;
    }
    
    try {
        // Show loading state
        const modal = document.getElementById('job-details-modal');
        if (modal) {
            modal.style.display = 'block';
            document.getElementById('job-details-content').innerHTML = '<div class="loading">Loading job details...</div>';
        }
        
        // Fetch job details
        const response = await fetch('../api/jobs/details.php?id=' + jobId, {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Failed to load job details');
        }
        
        const job = result.job;
        
        // Format the job details HTML
        const jobDetailsHTML = `
            <div class="job-details-header">
                <h2>${escapeHtml(job.title)}</h2>
                <p class="company-name">${escapeHtml(job.company_name)}</p>
                <div class="job-meta">
                    <span class="location">📍 ${escapeHtml(job.location)}</span>
                    <span class="job-type">${job.job_type_display}</span>
                    <span class="experience">${job.experience_display} Level</span>
                    <span class="work-type">${job.work_type_display}</span>
                </div>
                ${job.salary_display !== 'Not specified' ? `<p class="salary"><strong>Salary:</strong> ${job.salary_display}</p>` : ''}
                ${job.application_deadline ? `<p class="deadline"><strong>Application Deadline:</strong> ${new Date(job.application_deadline).toLocaleDateString()}</p>` : ''}
            </div>
            
            <div class="job-details-content">
                ${job.description ? `
                    <div class="detail-section">
                        <h3>Job Description</h3>
                        <div class="detail-text">${formatText(job.description)}</div>
                    </div>
                ` : ''}
                
                ${job.requirements ? `
                    <div class="detail-section">
                        <h3>Requirements</h3>
                        <div class="detail-text">${formatText(job.requirements)}</div>
                    </div>
                ` : ''}
                
                ${job.responsibilities ? `
                    <div class="detail-section">
                        <h3>Responsibilities</h3>
                        <div class="detail-text">${formatText(job.responsibilities)}</div>
                    </div>
                ` : ''}
                
                ${job.benefits ? `
                    <div class="detail-section">
                        <h3>Benefits</h3>
                        <div class="detail-text">${formatText(job.benefits)}</div>
                    </div>
                ` : ''}
                
                ${job.company_description ? `
                    <div class="detail-section">
                        <h3>About ${escapeHtml(job.company_name)}</h3>
                        <div class="detail-text">${formatText(job.company_description)}</div>
                        ${job.industry ? `<p><strong>Industry:</strong> ${escapeHtml(job.industry)}</p>` : ''}
                    </div>
                ` : ''}
            </div>
            
            <div class="job-details-actions">
                ${job.has_applied ? 
                    '<button class="btn btn-secondary" disabled>Already Applied</button>' :
                    `<button class="btn btn-primary" onclick="applyToJob(${job.id}); closeJobDetailsModal();">Apply Now</button>`
                }
                <button class="btn btn-secondary" onclick="closeJobDetailsModal()">Close</button>
            </div>
        `;
        
        // Update modal content
        if (modal) {
            document.getElementById('job-details-content').innerHTML = jobDetailsHTML;
        } else {
            // Create modal if it doesn't exist
            createJobDetailsModal(jobDetailsHTML);
        }
        
    } catch (error) {
        console.error('Error loading job details:', error);
        const content = document.getElementById('job-details-content');
        if (content) {
            content.innerHTML = `<div class="error-state"><p>Error loading job details: ${error.message}</p><button class="btn btn-secondary" onclick="closeJobDetailsModal()">Close</button></div>`;
        } else {
            alert('Error loading job details: ' + error.message);
        }
    }
}

function closeJobDetailsModal() {
    const modal = document.getElementById('job-details-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function createJobDetailsModal(content) {
    // Remove existing modal if any
    const existing = document.getElementById('job-details-modal');
    if (existing) {
        existing.remove();
    }
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'job-details-modal';
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content job-details-modal-content">
            <span class="close" onclick="closeJobDetailsModal()">&times;</span>
            <div id="job-details-content">${content}</div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // Close on outside click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeJobDetailsModal();
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatText(text) {
    if (!text) return '';
    // Convert line breaks to <br>
    return escapeHtml(text).replace(/\n/g, '<br>');
}

async function startAssessment(assessmentId, userAssessmentId = null) {
    if (confirm('Are you ready to start this assessment? Make sure you have a stable internet connection and good lighting for proctoring.')) {
        try {
            // Request camera access for proctoring
            let cameraGranted = false;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user'
                    }, 
                    audio: false 
                });
                // Stop stream after permission check
                stream.getTracks().forEach(track => track.stop());
                cameraGranted = true;
            } catch (e) {
                console.warn('Camera access denied or not available:', e);
                if (!confirm('Camera access is required for proctored assessments. Would you like to continue without proctoring?')) {
                    return;
                }
            }

            const response = await fetch('../api/jobs/assessments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ 
                    assessment_id: assessmentId, 
                    user_assessment_id: userAssessmentId,
                    proctored: cameraGranted, 
                    shuffle: true 
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Assessment started! Redirecting to assessment page.');
                window.location.href = `../assessment.php?id=${assessmentId}${userAssessmentId ? `&ua_id=${userAssessmentId}` : ''}`;
            } else {
                alert('Failed to start assessment: ' + result.message);
            }
        } catch (error) {
            console.error('Assessment start error:', error);
            alert('Failed to start assessment. Please try again.');
        }
    }
}

function viewAssessmentResult(userAssessmentId) {
    window.location.href = `../assessment.php?result=${userAssessmentId}`;
}

async function searchJobs() {
    const keywords = document.getElementById('search-keywords')?.value || '';
    const location = document.getElementById('search-location')?.value || '';
    const jobType = document.getElementById('search-job-type')?.value || '';
    const experience = document.getElementById('search-experience')?.value || '';
    
    const resultsContainer = document.getElementById('search-results');
    if (!resultsContainer) return;
    
    resultsContainer.innerHTML = '<div class="loading-state"><div class="loading"></div><p>Searching...</p></div>';
    
    try {
        const params = new URLSearchParams();
        if (keywords) params.append('keywords', keywords);
        if (location) params.append('location', location);
        if (jobType) params.append('job_type', jobType);
        if (experience) params.append('experience', experience);
        
        const url = `/job/api/jobs/search.php?${params.toString()}`;
        const response = await fetch(url, { credentials: 'same-origin' });
        const contentType = response.headers.get('content-type') || '';
        if (!response.ok || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Search response error:', response.status, text);
            resultsContainer.innerHTML = `<div class="error-state"><p>Search failed. Please try again.</p></div>`;
            return;
        }
        const data = await response.json();
        
        if (data.success) {
            displaySearchResults(data.jobs, data.total);
        } else {
            resultsContainer.innerHTML = `<div class="error-state"><p>Search failed: ${data.message}</p></div>`;
        }
    } catch (error) {
        console.error('Search error:', error);
        resultsContainer.innerHTML = '<div class="error-state"><p>Search failed. Please try again.</p></div>';
    }
}

function displaySearchResults(jobs, total) {
    const resultsContainer = document.getElementById('search-results');
    
    if (!jobs || jobs.length === 0) {
        resultsContainer.innerHTML = '<div class="empty-state"><p>No jobs found matching your criteria.</p></div>';
        return;
    }
    
    let html = `<div class="search-results-header"><h3>Found ${total} job${total !== 1 ? 's' : ''}</h3></div><div class="jobs-grid">`;
    
    jobs.forEach(job => {
        const jt = (job.job_type || '').toString().replace('_', ' ') || 'Not specified';
        const desc = (job.description || '').toString();
        html += `
            <div class="job-card">
                <div class="job-header">
                    <h4>${job.title}</h4>
                    <div class="company">${job.company_name}</div>
                </div>
                <div class="job-details">
                    <p class="location">📍 ${job.location}</p>
                    <p class="job-type">${jt}</p>
                    <p class="salary">${job.salary_display}</p>
                </div>
                <div class="job-description">
                    <p>${desc.substring(0, 150)}...</p>
                </div>
                <div class="job-actions">
                    ${job.has_applied ? 
                        '<button class="btn btn-secondary" disabled>Already Applied</button>' :
                        `<button class="btn btn-primary" onclick="applyToJob(${job.id})">Apply Now</button>`
                    }
                    <button class="btn btn-secondary" onclick="viewJobDetails(${job.id})">View Details</button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    resultsContainer.innerHTML = html;
}

async function uploadResume() {
    const fileInput = document.getElementById('resume-file');
    const uploadBtn = document.getElementById('upload-resume-btn');
    const progressDiv = document.getElementById('upload-progress');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const selectedFile = document.getElementById('selected-file');
    const uploadText = document.getElementById('upload-text');
    
    console.log('fileInput:', fileInput);
    console.log('fileInput.files:', fileInput.files);
    
    if (!fileInput || fileInput.files.length === 0) {
        alert('Please select a resume file first.');
        fileInput.click();
        return;
    }
    
    const file = fileInput.files[0];
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
    
    // Validate file size
    if (file.size > maxSize) {
        alert('File size must be less than 5MB.');
        return;
    }
    
    // Validate file type
    if (!allowedTypes.includes(file.type)) {
        alert('Please upload a PDF, DOC, DOCX, or TXT file.');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('resume', file, file.name);
        
        // Show upload progress
        if (uploadBtn) {
            uploadBtn.textContent = 'Uploading...';
            uploadBtn.disabled = true;
        }
        if (progressDiv) progressDiv.style.display = 'block';
        if (progressBar) progressBar.style.width = '0%';
        if (progressText) progressText.textContent = 'Preparing upload...';
        
        const xhr = new XMLHttpRequest();
        
        // Upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable && progressBar && progressText) {
                const percent = (e.loaded / e.total) * 100;
                progressBar.style.width = percent + '%';
                progressText.textContent = `Uploading... ${Math.round(percent)}%`;
            }
        });
        
        return new Promise((resolve, reject) => {
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            if (progressBar) progressBar.style.width = '100%';
                            if (progressText) progressText.textContent = 'Upload successful!';
                            
                            setTimeout(() => {
                                alert('Resume uploaded successfully! Your profile has been updated with the extracted information.');
                                
                                // Clear the file input and UI
                                fileInput.value = '';
                                if (selectedFile) selectedFile.style.display = 'none';
                                if (uploadText) uploadText.textContent = 'Drag and drop your resume here or click to browse';
                                if (progressDiv) progressDiv.style.display = 'none';
                                if (uploadBtn) {
                                    uploadBtn.disabled = false;
                                    uploadBtn.textContent = 'Upload Resume';
                                }
                                
                                // Optionally refresh the section to show uploaded resume info
                                if (window.dashboardManager) {
                                    window.dashboardManager.loadSectionContent('resume');
                                }
                                
                                resolve(data);
                            }, 500);
                        } else {
                            let errorMsg = data.message || 'Upload failed';
                            if (data.debug) {
                                console.error('Upload debug info:', data.debug);
                                if (data.debug.upload_error !== 'N/A' && data.debug.upload_error !== 0) {
                                    errorMsg += ' (Error code: ' + data.debug.upload_error + ')';
                                }
                            }
                            throw new Error(errorMsg);
                        }
                    } catch (e) {
                        reject(e);
                    }
                } else {
                    let errorMsg = `Server error: ${xhr.status}`;
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        if (errorData.message) {
                            errorMsg = errorData.message;
                        }
                    } catch (e) {
                        // Use default error message
                    }
                    reject(new Error(errorMsg));
                }
            });
            
            xhr.addEventListener('error', () => {
                reject(new Error('Network error occurred'));
            });
            
            xhr.open('POST', '../api/resume/upload.php');
            xhr.send(formData);
        });
        
    } catch (error) {
        console.error('Upload error:', error);
        alert('Upload failed: ' + error.message);
        
        // Restore UI on error
        if (progressDiv) progressDiv.style.display = 'none';
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Upload Resume';
        }
    }
}

function downloadResume() {
    window.location.href = '../api/resume/download.php';
}

function openResumeBuilder() {
    // This function is now handled by inline scripts in the dashboard
    // Keeping this as fallback
    if (confirm('The Resume Builder will help you create a professional resume step by step. Would you like to start building your resume now?')) {
        alert('Resume builder functionality is being loaded...');
    }
}

function showResumeBuilderModal() {
    // Create and show resume builder modal
    const modalHTML = `
        <div id="resume-builder-modal" style="
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            z-index: 2000;
        ">
            <div style="
                background: white; 
                padding: 30px; 
                border-radius: 10px; 
                max-width: 600px; 
                width: 90%; 
                max-height: 80vh; 
                overflow-y: auto;
            ">
                <h2>🚀 Resume Builder</h2>
                <p>Create a professional resume in minutes!</p>
                
                <div id="builder-step-1">
                    <h3>Step 1: Personal Information</h3>
                    <form id="personal-info">
                        <div style="margin-bottom: 15px;">
                            <label>Full Name:</label><br>
                            <input type="text" id="builder-name" style="width: 100%; padding: 8px; margin-top: 5px;" required>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label>Email:</label><br>
                            <input type="email" id="builder-email" style="width: 100%; padding: 8px; margin-top: 5px;" required>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label>Phone:</label><br>
                            <input type="tel" id="builder-phone" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label>Location:</label><br>
                            <input type="text" id="builder-location" style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="City, State">
                        </div>
                    </form>
                    
                    <button onclick="nextBuilderStep(2)" style="
                        background: #007bff; 
                        color: white; 
                        padding: 10px 20px; 
                        border: none; 
                        border-radius: 5px; 
                        margin-right: 10px;
                    ">Next: Professional Summary</button>
                </div>
                
                <div id="builder-step-2" style="display: none;">
                    <h3>Step 2: Professional Summary</h3>
                    <div style="margin-bottom: 15px;">
                        <label>Professional Summary:</label><br>
                        <textarea id="builder-summary" rows="4" style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="Brief overview of your professional background and key strengths..."></textarea>
                    </div>
                    
                    <button onclick="nextBuilderStep(1)" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-right: 10px;">Back</button>
                    <button onclick="nextBuilderStep(3)" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;">Next: Skills</button>
                </div>
                
                <div id="builder-step-3" style="display: none;">
                    <h3>Step 3: Skills</h3>
                    <div style="margin-bottom: 15px;">
                        <label>Key Skills (one per line):</label><br>
                        <textarea id="builder-skills" rows="6" style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="JavaScript\nPHP\nProject Management\nCommunication\n..."></textarea>
                    </div>
                    
                    <button onclick="nextBuilderStep(2)" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-right: 10px;">Back</button>
                    <button onclick="generateResume()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px;">Generate Resume</button>
                </div>
                
                <button onclick="closeResumeBuilder()" style="
                    position: absolute; 
                    top: 10px; 
                    right: 15px; 
                    background: none; 
                    border: none; 
                    font-size: 24px; 
                    cursor: pointer;
                ">&times;</button>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Pre-fill with user data if available
    const userName = document.querySelector('.user-details h4')?.textContent || '';
    const userEmail = document.querySelector('.user-details p')?.textContent || '';
    
    if (userName) document.getElementById('builder-name').value = userName;
    if (userEmail) document.getElementById('builder-email').value = userEmail;
}

function nextBuilderStep(step) {
    // Hide all steps
    for (let i = 1; i <= 3; i++) {
        const stepElement = document.getElementById(`builder-step-${i}`);
        if (stepElement) stepElement.style.display = 'none';
    }
    
    // Show target step
    const targetStep = document.getElementById(`builder-step-${step}`);
    if (targetStep) targetStep.style.display = 'block';
}

function closeResumeBuilder() {
    const modal = document.getElementById('resume-builder-modal');
    if (modal) modal.remove();
}

function generateResume() {
    const resumeData = {
        name: document.getElementById('builder-name').value,
        email: document.getElementById('builder-email').value,
        phone: document.getElementById('builder-phone').value,
        location: document.getElementById('builder-location').value,
        summary: document.getElementById('builder-summary').value,
        skills: document.getElementById('builder-skills').value.split('\n').filter(s => s.trim())
    };
    
    // Generate resume HTML
    const resumeHTML = generateResumeHTML(resumeData);
    
    // Show preview
    showResumePreview(resumeHTML, resumeData);
}

function generateResumeHTML(data) {
    return `
        <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 40px; line-height: 1.6;">
            <header style="border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px;">
                <h1 style="color: #333; margin: 0; font-size: 2.5em;">${data.name}</h1>
                <div style="color: #666; margin-top: 10px;">
                    <span>${data.email}</span> | 
                    <span>${data.phone}</span> | 
                    <span>${data.location}</span>
                </div>
            </header>
            
            ${data.summary ? `
            <section style="margin-bottom: 30px;">
                <h2 style="color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 5px;">Professional Summary</h2>
                <p style="margin-top: 15px;">${data.summary}</p>
            </section>
            ` : ''}
            
            ${data.skills.length > 0 ? `
            <section style="margin-bottom: 30px;">
                <h2 style="color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 5px;">Key Skills</h2>
                <div style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px;">
                    ${data.skills.map(skill => `<span style="background: #f8f9fa; padding: 5px 12px; border-radius: 15px; border: 1px solid #ddd;">${skill.trim()}</span>`).join('')}
                </div>
            </section>
            ` : ''}
            
            <section>
                <h2 style="color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 5px;">Experience</h2>
                <p style="color: #666; font-style: italic; margin-top: 15px;">Add your work experience in the profile section</p>
            </section>
        </div>
    `;
}

function showResumePreview(html, data) {
    const modal = document.getElementById('resume-builder-modal');
    if (modal) {
        modal.innerHTML = `
            <div style="background: white; padding: 20px; border-radius: 10px; max-width: 90%; width: 900px; max-height: 90vh; overflow-y: auto;">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                    <h2>📄 Resume Preview</h2>
                    <div>
                        <button onclick="saveResume()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-right: 10px;">Save Resume</button>
                        <button onclick="closeResumeBuilder()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px;">&times;</button>
                    </div>
                </div>
                
                <div style="border: 1px solid #ddd; background: white;">
                    ${html}
                </div>
            </div>
        `;
        
        // Store resume data for saving
        modal.resumeData = data;
    }
}

async function saveResume() {
    const modal = document.getElementById('resume-builder-modal');
    const data = modal?.resumeData;
    
    if (!data) {
        alert('No resume data to save.');
        return;
    }
    
    try {
        // Create a basic text resume
        const resumeText = `
${data.name}\n${data.email} | ${data.phone} | ${data.location}\n\nPROFESSIONAL SUMMARY\n${data.summary}\n\nKEY SKILLS\n${data.skills.join(', ')}
        `.trim();
        
        // Update profile with resume data
        const response = await fetch('../api/profile/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                bio: data.summary,
                skills: data.skills.join(', '),
                phone: data.phone,
                location: data.location
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Resume saved successfully! Your profile has been updated.');
            closeResumeBuilder();
            
            // Refresh the resume section
            if (window.dashboardManager) {
                window.dashboardManager.loadSectionContent('resume');
            }
        } else {
            alert('Failed to save resume: ' + result.message);
        }
    } catch (error) {
        console.error('Save error:', error);
        alert('Failed to save resume. Please try again.');
    }
}

function sendChatMessage() {
    if (window.dashboardManager) {
        window.dashboardManager.sendChatMessage();
    }
}

function sendSuggestion(suggestionText) {
    const input = document.getElementById('chatbot-input');
    if (input) {
        input.value = suggestionText;
        if (window.dashboardManager) {
            window.dashboardManager.sendChatMessage();
        }
    }
}

// Handle special chat suggestions with actions
function handleChatSuggestion(suggestion) {
    console.log('Handling chat suggestion:', suggestion);
    
    switch (suggestion.toLowerCase()) {
        case 'build resume':
        case 'build new resume':
            // Open the resume builder instead of sending text
            openResumeBuilderModal();
            addChatMessage('Great! I\'ve opened the resume builder for you. Create a professional resume step by step!', 'bot');
            break;
            
        case 'upload resume':
            // Switch to the resume section and focus on upload
            if (window.dashboardManager) {
                window.dashboardManager.showSection('resume');
            }
            addChatMessage('Perfect! I\'ve taken you to the resume section. You can upload your existing resume there.', 'bot');
            break;
            
        case 'resume tips':
            // Send as regular message but with tips
            sendSuggestion('Give me resume tips');
            break;
            
        case 'optimize resume':
            // Send as regular message
            sendSuggestion('How can I optimize my resume?');
            break;
            
        case 'find jobs':
        case 'search for jobs':
            // Switch to job search section
            if (window.dashboardManager) {
                window.dashboardManager.showSection('search');
            }
            addChatMessage('Let\'s find you some great job opportunities! I\'ve opened the job search section.', 'bot');
            break;
            
        case 'take assessment':
            // Switch to assessments section
            if (window.dashboardManager) {
                window.dashboardManager.showSection('assessments');
            }
            addChatMessage('Assessments help validate your skills! I\'ve opened the assessments section for you.', 'bot');
            break;
            
        case 'update profile':
        case 'profile setup':
            // Switch to profile section
            if (window.dashboardManager) {
                window.dashboardManager.showSection('profile');
            }
            addChatMessage('A complete profile helps with better job matching! I\'ve opened your profile section.', 'bot');
            break;
            
        case 'view all applications':
        case 'check applications':
            // Switch to applications section
            if (window.dashboardManager) {
                window.dashboardManager.showSection('applications');
            }
            addChatMessage('Here are your job applications! I\'ve opened the applications section.', 'bot');
            break;
            
        default:
            // For other suggestions, send as regular text message
            sendSuggestion(suggestion);
    }
}

// Helper function to add chat message without API call
function addChatMessage(message, sender = 'bot') {
    const messagesContainer = document.getElementById('chatbot-messages');
    if (!messagesContainer) return;
    
    const messageClass = sender === 'bot' ? 'bot-message' : 'user-message';
    messagesContainer.innerHTML += `
        <div class="message ${messageClass}">
            <div class="message-content">
                <p>${message.replace(/\n/g, '<br>')}</p>
            </div>
        </div>
    `;
    
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Create resume builder modal (used by chatbot)
function openResumeBuilderModal() {
    // Remove existing modal if any
    const existingModal = document.getElementById('resume-builder-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create the modal
    showResumeBuilderModal();
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Avoid initializing base dashboard on company dashboard pages
    const isCompanyDashboard = window.location.pathname.includes('company.php');
    if (isCompanyDashboard) {
        return; // Company dashboard has its own manager and initialization
    }

    window.dashboardManager = new DashboardManager();
    
    // Ensure global functions are accessible
    window.openResumeBuilder = openResumeBuilder;
    window.openResumeBuilderModal = openResumeBuilderModal;
    window.uploadResume = uploadResume;
    window.showSection = showSection;
    window.sendChatMessage = sendChatMessage;
    window.sendSuggestion = sendSuggestion;
    window.handleChatSuggestion = handleChatSuggestion;
    window.addChatMessage = addChatMessage;
    window.logout = logout;
});
