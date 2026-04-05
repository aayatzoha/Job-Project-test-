// AI Job Recommendation System - Company Dashboard JavaScript

// Global function to show sections
function showSection(sectionName) {
    // Hide all sections
    document.querySelectorAll('.dashboard-section').forEach(section => {
        section.classList.remove('active');
        // Clear any inline display styles that may have been set
        if (section.style && section.style.display) {
            section.style.display = '';
        }
    });
    
    // Show the selected section
    const selectedSection = document.getElementById(`${sectionName}-section`);
    if (selectedSection) {
        // Ensure the section is visible
        selectedSection.style.display = '';
        selectedSection.classList.add('active');
    }
    
    // Update active state in sidebar
    document.querySelectorAll('.sidebar-nav li').forEach(item => {
        item.classList.remove('active');
    });
    
    const navItem = document.querySelector(`.sidebar-nav li a[href="#${sectionName}"]`).parentNode;
    if (navItem) {
        navItem.classList.add('active');
    }
    
    // Load section content if needed
    if (window.dashboardManager) {
        window.dashboardManager.loadSectionContent(sectionName);
    }
}

// Global functions for job and assessment creation
function showJobCreationForm() {
    console.log('showJobCreationForm called');
    console.log('window.dashboardManager:', window.dashboardManager);
    if (window.dashboardManager) {
        console.log('Calling showJobCreationForm method');
        window.dashboardManager.showJobCreationForm();
    } else {
        console.error('window.dashboardManager is not defined!');
    }
}

function showAssessmentCreationForm() {
    console.log('showAssessmentCreationForm called');
    console.log('window.dashboardManager:', window.dashboardManager);
    if (window.dashboardManager) {
        console.log('Calling showAssessmentCreationForm method');
        window.dashboardManager.showAssessmentCreationForm();
    } else {
        console.error('window.dashboardManager is not defined!');
    }
}

// Company Dashboard Manager
class CompanyDashboardManager extends DashboardManager {
    constructor() {
        super();
        this.initCompanySpecific();
    }
    
    // Override showSection to handle company-specific sections
    showSection(sectionName) {
        if (this.isLoading) {
            console.log('Dashboard is loading, skipping section change');
            return;
        }
        
        console.log('CompanyDashboardManager showing section:', sectionName);
        
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
            const navLinks = document.querySelectorAll(`.sidebar-nav li a[href="#${sectionName}"]`);
            if (navLinks.length > 0) {
                navLinks.forEach(link => {
                    const navItem = link.closest('li');
                    if (navItem) {
                        navItem.classList.add('active');
                    }
                });
            }
            
            // Load section content (async, don't wait)
            this.loadSectionContent(sectionName).catch(error => {
                console.error('Error in loadSectionContent:', error);
            });
        } else {
            console.warn(`Section element not found: ${sectionName}-section`);
        }
    }
    
    // Override base initializeSection to avoid job seeker-specific setups
    initializeSection(sectionName) {
        switch (sectionName) {
            case 'jobs':
            case 'candidates':
            case 'applications':
            case 'assignments':
            case 'analytics':
            case 'profile':
            case 'settings':
                // No special JS initialization needed currently
                break;
            case 'assessments':
                // Setup assessment-specific handlers
                this.setupAssessmentCreation();
                // Also setup direct button handlers after content loads
                setTimeout(() => {
                    // Find all buttons that should trigger assessment creation
                    const createBtns = document.querySelectorAll('[onclick*="showAssessmentCreationForm"], [onclick*="Create Assessment"], button.btn-primary');
                    createBtns.forEach(btn => {
                        const btnText = btn.textContent || btn.innerText;
                        if ((btnText.includes('Create Assessment') || btnText.includes('Create Quiz') || btn.hasAttribute('onclick')) && !btn.hasAttribute('data-handler-setup')) {
                            btn.setAttribute('data-handler-setup', 'true');
                            const originalOnclick = btn.getAttribute('onclick');
                            if (originalOnclick && originalOnclick.includes('showAssessmentCreationForm')) {
                                btn.removeAttribute('onclick');
                                btn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    if (window.dashboardManager && window.dashboardManager.showAssessmentCreationForm) {
                                        window.dashboardManager.showAssessmentCreationForm();
                                    }
                                });
                            }
                        }
                    });
                }, 200);
                break;
            default:
                // Fallback to base for unknown sections
                if (super.initializeSection) {
                    super.initializeSection(sectionName);
                }
        }
    }

    initCompanySpecific() {
        // Initialize company-specific functionality
        this.setupJobPostingHandlers();
        this.setupCandidateManagement();
        this.setupAssessmentCreation();
    }
    
    setupJobPostingHandlers() {
        // Setup job posting form handlers - only for buttons with onclick attributes
        // Individual buttons will have their own handlers added in setupJobButtons()
        document.addEventListener('click', (event) => {
            // Only handle buttons with onclick attributes, not IDs (those are handled separately)
            if (event.target.matches('[onclick*="showJobCreationForm"]') ||
                event.target.matches('[onclick*="createJob"]')) {
                event.preventDefault();
                console.log('Job creation button clicked via event delegation');
                if (this.showJobCreationForm) {
                    this.showJobCreationForm();
                } else if (window.showJobCreationForm) {
                    window.showJobCreationForm();
                }
                return;
            }

            // Handle edit job button clicks
            if (event.target.matches('[onclick*="editJob"]') || event.target.closest('[onclick*="editJob"]')) {
                event.preventDefault();
                event.stopPropagation();
                const target = event.target.closest('[onclick*="editJob"]') || event.target;
                const onclickAttr = target.getAttribute('onclick') || '';
                const match = onclickAttr.match(/editJob\((\d+)\)/);
                if (match) {
                    const jobId = parseInt(match[1]);
                    if (window.dashboardManager && window.dashboardManager.editJob) {
                        window.dashboardManager.editJob(jobId);
                    } else {
                        editJob(jobId);
                    }
                }
                return;
            }

            // Handle view applications button clicks
            if (event.target.matches('[onclick*="viewJobApplications"]') || event.target.closest('[onclick*="viewJobApplications"]')) {
                event.preventDefault();
                event.stopPropagation();
                const target = event.target.closest('[onclick*="viewJobApplications"]') || event.target;
                const onclickAttr = target.getAttribute('onclick') || '';
                const match = onclickAttr.match(/viewJobApplications\((\d+)\)/);
                if (match) {
                    const jobId = parseInt(match[1]);
                    if (window.dashboardManager && window.dashboardManager.viewJobApplications) {
                        window.dashboardManager.viewJobApplications(jobId);
                    }
                }
                return;
            }

            // Handle delete job button clicks
            if (event.target.matches('[onclick*="deleteJob"]') || event.target.closest('[onclick*="deleteJob"]')) {
                event.preventDefault();
                event.stopPropagation();
                const target = event.target.closest('[onclick*="deleteJob"]') || event.target;
                const onclickAttr = target.getAttribute('onclick') || '';
                const match = onclickAttr.match(/deleteJob\((\d+)\)/);
                if (match) {
                    const jobId = parseInt(match[1]);
                    if (window.dashboardManager && window.dashboardManager.deleteJob) {
                        window.dashboardManager.deleteJob(jobId);
                    } else {
                        deleteJob(jobId);
                    }
                }
                return;
            }
        });
    }
    
    setupJobButtons() {
        // Remove existing listeners first to prevent duplicates
        const createFirstJobBtn = document.getElementById('create-first-job-btn');
        const createFirstJobEmptyBtn = document.getElementById('create-first-job-empty-btn');
        
        // Clone and replace to remove all event listeners
        if (createFirstJobBtn) {
            const newBtn = createFirstJobBtn.cloneNode(true);
            createFirstJobBtn.parentNode.replaceChild(newBtn, createFirstJobBtn);
            newBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Create First Job button clicked');
                if (this.showJobCreationForm) {
                    this.showJobCreationForm();
                } else if (window.showJobCreationForm) {
                    window.showJobCreationForm();
                }
            });
        }
        
        if (createFirstJobEmptyBtn) {
            const newBtn = createFirstJobEmptyBtn.cloneNode(true);
            createFirstJobEmptyBtn.parentNode.replaceChild(newBtn, createFirstJobEmptyBtn);
            newBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Create First Job (empty) button clicked');
                if (this.showJobCreationForm) {
                    this.showJobCreationForm();
                } else if (window.showJobCreationForm) {
                    window.showJobCreationForm();
                }
            });
        }
        
        // Setup other job action buttons
        document.querySelectorAll('[onclick*="window.dashboardManager.showJobCreationForm"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (this.showJobCreationForm) {
                    this.showJobCreationForm();
                }
            });
        });
    }
    
    setupCandidateManagement() {
        // Setup candidate management handlers
        document.addEventListener('click', (event) => {
            if (event.target.matches('[onclick*="viewCandidate"]')) {
                const candidateId = event.target.getAttribute('data-candidate-id');
                this.viewCandidateProfile(candidateId);
            }
            
            // Handle view application button clicks
            if (event.target.matches('[onclick*="viewApplication"]') || event.target.closest('[onclick*="viewApplication"]')) {
                event.preventDefault();
                event.stopPropagation();
                const target = event.target.closest('[onclick*="viewApplication"]') || event.target;
                const onclickAttr = target.getAttribute('onclick') || '';
                const match = onclickAttr.match(/viewApplication\((\d+)\)/);
                if (match) {
                    const applicationId = parseInt(match[1]);
                    if (window.dashboardManager && window.dashboardManager.viewApplication) {
                        window.dashboardManager.viewApplication(applicationId);
                    } else {
                        viewApplication(applicationId);
                    }
                }
                return;
            }
            
            // Handle update application status button clicks
            if (event.target.matches('[onclick*="updateApplicationStatus"]') || event.target.closest('[onclick*="updateApplicationStatus"]')) {
                event.preventDefault();
                event.stopPropagation();
                const target = event.target.closest('[onclick*="updateApplicationStatus"]') || event.target;
                const onclickAttr = target.getAttribute('onclick') || '';
                const match = onclickAttr.match(/updateApplicationStatus\((\d+),\s*['"]([^'"]+)['"]\)/);
                if (match) {
                    const applicationId = parseInt(match[1]);
                    const status = match[2];
                    if (window.dashboardManager && window.dashboardManager.updateApplicationStatus) {
                        window.dashboardManager.updateApplicationStatus(applicationId, status);
                    } else {
                        updateApplicationStatus(applicationId, status);
                    }
                }
                return;
            }
        });
    }
    
    setupAssessmentCreation() {
        // Setup assessment creation handlers
        document.addEventListener('click', (event) => {
            // Check if button calls showAssessmentCreationForm
            if (event.target.matches('[onclick*="showAssessmentCreationForm"]') ||
                event.target.matches('[onclick*="createAssessment"]')) {
                event.preventDefault();
                console.log('Assessment creation button clicked via event delegation');
                this.showAssessmentCreationForm();
            }

            if (event.target.matches('[onclick*="editAssessment"]')) {
                const assessmentId = event.target.getAttribute('data-assessment-id');
                this.editAssessment(assessmentId);
            }

            if (event.target.matches('[onclick*="deleteAssessment"]')) {
                const assessmentId = event.target.getAttribute('data-assessment-id');
                this.deleteAssessment(assessmentId);
            }
        });
    }
    
    setupAssignmentButtons() {
        // Setup assignment creation buttons
        const createAssignmentBtn = document.getElementById('create-assignment-btn');
        const createFirstAssignmentBtn = document.getElementById('create-first-assignment-btn');
        
        if (createAssignmentBtn) {
            createAssignmentBtn.addEventListener('click', () => {
                console.log('Create Assignment button clicked');
                if (this.showAssignmentCreationForm) {
                    this.showAssignmentCreationForm();
                }
            });
        }
        
        if (createFirstAssignmentBtn) {
            createFirstAssignmentBtn.addEventListener('click', () => {
                console.log('Create First Assignment button clicked');
                if (this.showAssignmentCreationForm) {
                    this.showAssignmentCreationForm();
                }
            });
        }
        
        // Setup event delegation for assignment action buttons
        document.addEventListener('click', (event) => {
            if (event.target.matches('[onclick*="viewAssignment"]') ||
                event.target.closest('[onclick*="viewAssignment"]')) {
                const onclickAttr = event.target.getAttribute('onclick') || 
                                   event.target.closest('[onclick]')?.getAttribute('onclick') || '';
                const match = onclickAttr.match(/viewAssignment\((\d+)\)/);
                if (match) {
                    event.preventDefault();
                    this.viewAssignment(parseInt(match[1]));
                }
            }
            
            if (event.target.matches('[onclick*="editAssignment"]') ||
                event.target.closest('[onclick*="editAssignment"]')) {
                const onclickAttr = event.target.getAttribute('onclick') || 
                                   event.target.closest('[onclick]')?.getAttribute('onclick') || '';
                const match = onclickAttr.match(/editAssignment\((\d+)\)/);
                if (match) {
                    event.preventDefault();
                    this.editAssignment(parseInt(match[1]));
                }
            }
            
            if (event.target.matches('[onclick*="reviewAssignment"]') ||
                event.target.closest('[onclick*="reviewAssignment"]')) {
                const onclickAttr = event.target.getAttribute('onclick') || 
                                   event.target.closest('[onclick]')?.getAttribute('onclick') || '';
                const match = onclickAttr.match(/reviewAssignment\((\d+)\)/);
                if (match) {
                    event.preventDefault();
                    this.reviewAssignment(parseInt(match[1]));
                }
            }
            
            if (event.target.matches('[onclick*="deleteAssignment"]') ||
                event.target.closest('[onclick*="deleteAssignment"]')) {
                const onclickAttr = event.target.getAttribute('onclick') || 
                                   event.target.closest('[onclick]')?.getAttribute('onclick') || '';
                const match = onclickAttr.match(/deleteAssignment\((\d+)\)/);
                if (match) {
                    event.preventDefault();
                    this.deleteAssignment(parseInt(match[1]));
                }
            }
        });
    }
    
    async loadSectionContent(sectionName) {
        // Dashboard section is already loaded in PHP, don't try to load content
        if (sectionName === 'dashboard') {
            return;
        }
        
        const contentElement = document.getElementById(`${sectionName}-content`);
        if (!contentElement) {
            console.warn(`Content element not found for section: ${sectionName}`);
            return;
        }
        
        // Show loading state
        contentElement.innerHTML = '<div class="loading-state"><div class="loading"></div><p>Loading...</p></div>';
        
        try {
            let content = '';
            
            switch (sectionName) {
                case 'jobs':
                    content = await this.loadJobPostings();
                    break;
                case 'candidates':
                    content = await this.loadCandidates();
                    break;
                case 'applications':
                    content = await this.loadApplications();
                    break;
                case 'assessments':
                    content = await this.loadAssessments();
                    break;
                case 'assignments':
                    content = await this.loadAssignments();
                    break;
                case 'analytics':
                    content = await this.loadAnalytics();
                    break;
                case 'profile':
                    content = await this.loadCompanyProfile();
                    break;
                case 'settings':
                    content = await this.loadSettings();
                    break;
                default:
                    // Try parent class if exists
                    if (super.loadSectionContent) {
                        content = await super.loadSectionContent(sectionName);
                    } else {
                        content = '<div class="empty-state"><p>Section not implemented yet.</p></div>';
                    }
            }
            
            if (content) {
                // Remove any existing filter messages to prevent duplicates
                if (sectionName === 'applications') {
                    const existingFilter = contentElement.querySelector('#job-filter-message');
                    if (existingFilter) {
                        existingFilter.remove();
                    }
                }
                
                contentElement.innerHTML = content;
                this.initializeSection(sectionName);
                
                // Setup button handlers after content is rendered
                if (sectionName === 'jobs') {
                    this.setupJobButtons();
                } else if (sectionName === 'assignments') {
                    this.setupAssignmentButtons();
                } else if (sectionName === 'profile') {
                    this.setupProfileForm();
                }
            }
            
        } catch (error) {
            console.error('Error loading section content:', error);
            console.error('Error stack:', error.stack);
            contentElement.innerHTML = `
                <div class="error-state">
                    <p>Error loading content. Please try again.</p>
                    <p style="font-size: 0.9em; color: #999;">${error.message}</p>
                    <button class="btn btn-secondary" onclick="location.reload()">Reload Page</button>
                </div>
            `;
        }
    }
    
    async loadJobPostings() {
        try {
            const response = await fetch('../api/company/jobs.php', { credentials: 'same-origin' });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();

            if (data.success && data.jobs) {
                const html = this.renderJobPostings(data.jobs);
                // Button handlers will be set up by setupJobButtons() after content is inserted
                return html;
            } else {
                return `
                    <div class="empty-state">
                        <p>No job postings found.</p>
                        <button class="btn btn-primary" id="create-first-job-empty-btn">Create First Job</button>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading job postings:', error);
            return `
                <div class="error-state">
                    <p>Error loading job postings.</p>
                    <p style="font-size: 0.9em; color: #999;">${error.message}</p>
                    <button class="btn btn-secondary" onclick="window.dashboardManager && window.dashboardManager.loadSectionContent('jobs')">Retry</button>
                </div>
            `;
        }
    }
    
    renderJobPostings(jobs) {
        if (!jobs || jobs.length === 0) {
            return `
                <div class="empty-state">
                    <p>No job postings yet. Create your first job posting to start attracting candidates!</p>
                    <button class="btn btn-primary" id="create-first-job-btn">Create First Job</button>
                </div>
            `;
        }

        let html = `
            <div class="jobs-header">
                <button class="btn btn-primary" onclick="window.dashboardManager.showJobCreationForm()">
                    <span class="icon">➕</span>
                    Post New Job
                </button>
            </div>
            <div class="jobs-grid">
        `;
        
        jobs.forEach(job => {
            html += `
                <div class="job-card">
                    <div class="job-header">
                        <h3>${job.title}</h3>
                        <div class="job-meta">
                            <span class="location">📍 ${job.location}</span>
                            <span class="job-type">${job.job_type.replace('_', ' ')}</span>
                        </div>
                    </div>
                    <div class="job-content">
                        <p class="job-description">${job.description.substring(0, 150)}...</p>
                        <div class="job-stats">
                            <span class="applications">📄 ${job.application_count || 0} applications</span>
                            <span class="views">👁️ ${job.views_count || 0} views</span>
                        </div>
                    </div>
                    <div class="job-actions">
                        <span class="status-badge status-${job.status}">${job.status}</span>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-secondary" onclick="window.dashboardManager.editJob(${job.id})">Edit</button>
                            <button class="btn btn-sm btn-primary" onclick="window.dashboardManager.viewJobApplications(${job.id})">View Applications</button>
                            <button class="btn btn-sm btn-danger" onclick="window.dashboardManager.deleteJob(${job.id})">Delete</button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    async loadCandidates() {
        try {
            const response = await fetch('../api/company/candidates.php', { credentials: 'same-origin' });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.candidates) {
                return this.renderCandidates(data.candidates);
            } else {
                return '<div class="empty-state"><p>No candidates found.</p></div>';
            }
        } catch (error) {
            console.error('Error loading candidates:', error);
            return `
                <div class="error-state">
                    <p>Error loading candidates.</p>
                    <p style="font-size: 0.9em; color: #999;">${error.message}</p>
                </div>
            `;
        }
    }
    
    renderCandidates(candidates) {
        if (!candidates || candidates.length === 0) {
            return '<div class="empty-state"><p>No candidates found.</p></div>';
        }
        
        let html = `
            <div class="candidates-filters">
                <input type="text" id="candidate-search" placeholder="Search candidates...">
                <select id="experience-filter">
                    <option value="">All Experience Levels</option>
                    <option value="0-2">0-2 years</option>
                    <option value="3-5">3-5 years</option>
                    <option value="6-10">6-10 years</option>
                    <option value="10+">10+ years</option>
                </select>
                <button class="btn btn-secondary" onclick="filterCandidates()">Filter</button>
            </div>
            <div class="candidates-grid">
        `;
        
        candidates.forEach(candidate => {
            html += `
                <div class="candidate-card">
                    <div class="candidate-header">
                        <div class="candidate-avatar">
                            ${candidate.name.charAt(0).toUpperCase()}
                        </div>
                        <div class="candidate-info">
                            <h3>${candidate.name}</h3>
                            <p class="candidate-title">${candidate.title || 'Job Seeker'}</p>
                        </div>
                    </div>
                    <div class="candidate-content">
                        <p class="candidate-location">📍 ${candidate.location || 'Location not specified'}</p>
                        <p class="candidate-experience">💼 ${candidate.experience_years || 0} years experience</p>
                        <div class="candidate-skills">
                            ${candidate.skills ? JSON.parse(candidate.skills).slice(0, 3).map(skill => 
                                `<span class="skill-tag">${skill}</span>`
                            ).join('') : ''}
                        </div>
                    </div>
                    <div class="candidate-actions">
                        <button class="btn btn-primary" data-candidate-id="${candidate.job_seeker_id || candidate.id || candidate.user_id}" onclick="viewCandidate(${candidate.job_seeker_id || candidate.id || candidate.user_id})">View Profile</button>
                        <button class="btn btn-secondary" onclick="contactCandidate(${candidate.id})">Contact</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    async loadApplications() {
        try {
            // Check if there's a job filter in sessionStorage
            const filterJobId = sessionStorage.getItem('filter_job_id');
            let url = '../api/company/applications.php';
            if (filterJobId) {
                url += `?job_id=${filterJobId}`;
            }
            
            const response = await fetch(url, { credentials: 'same-origin' });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.applications) {
                let html = this.renderApplications(data.applications);
                
                // Add filter message if filtering by job (only once)
                if (filterJobId) {
                    const filterMsg = `<div class="alert alert-info" id="job-filter-message" style="margin-bottom: 15px; padding: 12px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px;">
                        Showing applications for Job ID: ${filterJobId}. 
                        <button onclick="sessionStorage.removeItem('filter_job_id'); window.dashboardManager.loadSectionContent('applications');" class="btn btn-sm btn-secondary" style="margin-left: 10px;">Clear Filter</button>
                    </div>`;
                    html = filterMsg + html;
                }
                
                return html;
            } else {
                return '<div class="empty-state"><p>No applications found.</p></div>';
            }
        } catch (error) {
            console.error('Error loading applications:', error);
            return `
                <div class="error-state">
                    <p>Error loading applications.</p>
                    <p style="font-size: 0.9em; color: #999;">${error.message}</p>
                </div>
            `;
        }
    }
    
    renderApplications(applications) {
        if (!applications || applications.length === 0) {
            return '<div class="empty-state"><p>No applications found.</p></div>';
        }
        
        let html = `
            <div class="applications-filters">
                <select id="status-filter">
                    <option value="">All Status</option>
                    <option value="applied">Applied</option>
                    <option value="screening">Screening</option>
                    <option value="interview">Interview</option>
                    <option value="offered">Offered</option>
                    <option value="rejected">Rejected</option>
                </select>
                <select id="job-filter">
                    <option value="">All Jobs</option>
                    <!-- Job options will be populated dynamically -->
                </select>
                <button class="btn btn-secondary" onclick="filterApplications()">Filter</button>
            </div>
            <div class="applications-list">
        `;
        
        applications.forEach(app => {
            html += `
                <div class="application-card">
                    <div class="application-header">
                        <div class="candidate-info">
                            <h3>${app.candidate_name || 'Unknown Candidate'}</h3>
                            <p class="job-title">${app.job_title || app.title || 'Job Title Not Available'}</p>
                            <p class="application-date">Applied ${app.application_date ? new Date(app.application_date).toLocaleDateString() : 'Date not available'}</p>
                        </div>
                        <div class="application-status">
                            <span class="status-badge status-${app.status}">${app.status}</span>
                        </div>
                    </div>
                    <div class="application-content">
                        ${app.cover_letter ? `<p class="cover-letter">${app.cover_letter.substring(0, 200)}...</p>` : ''}
                        <div class="application-scores">
                            ${app.screening_score ? `<span class="score">Screening: ${app.screening_score}%</span>` : ''}
                            ${app.interview_score ? `<span class="score">Interview: ${app.interview_score}%</span>` : ''}
                        </div>
                    </div>
                    <div class="application-actions">
                        <button class="btn btn-sm btn-primary" onclick="window.dashboardManager.viewApplication(${app.id})">View Details</button>
                        <div class="status-actions">
                            <button class="btn btn-sm btn-success" onclick="window.dashboardManager.updateApplicationStatus(${app.id}, 'screening')">Screen</button>
                            <button class="btn btn-sm btn-warning" onclick="window.dashboardManager.updateApplicationStatus(${app.id}, 'interview')">Interview</button>
                            <button class="btn btn-sm btn-success" onclick="window.dashboardManager.updateApplicationStatus(${app.id}, 'offered')">Offer</button>
                            <button class="btn btn-sm btn-danger" onclick="window.dashboardManager.updateApplicationStatus(${app.id}, 'rejected')">Reject</button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    async loadAssessments() {
        try {
            const skillType = document.getElementById('company-assessment-skill-type')?.value || '';
            const url = `../api/company/assessments.php${skillType ? `?skill_type=${encodeURIComponent(skillType)}` : ''}`;
            const response = await fetch(url, { credentials: 'same-origin' });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();

            if (data.success && data.assessments) {
                return this.renderAssessments(data.assessments, skillType);
            } else {
                return `
                    <div class="empty-state">
                        <p>No assessments found.</p>
                        <button class="btn btn-primary" onclick="window.dashboardManager && window.dashboardManager.showAssessmentCreationForm ? window.dashboardManager.showAssessmentCreationForm() : showAssessmentCreationForm()">Create Assessment</button>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading assessments:', error);
            return `
                <div class="error-state">
                    <p>Error loading assessments.</p>
                    <p style="font-size: 0.9em; color: #999;">${error.message}</p>
                </div>
            `;
        }
    }
    
    renderAssessments(assessments, currentSkillType = '') {
        if (!assessments || assessments.length === 0) {
            return `
                <div class="empty-state">
                    <p>No assessments created yet. Create assessments to evaluate candidates effectively!</p>
                    <button class="btn btn-primary" onclick="window.dashboardManager.showAssessmentCreationForm()">Create Assessment</button>
                </div>
            `;
        }

        let html = `
            <div class="assessments-header">
                <div class="assessments-actions">
                    <button class="btn btn-primary" onclick="window.companyDashboardManager.showAssessmentCreationForm()">
                        <span class="icon">➕</span>
                        Create Assessment
                    </button>
                    <div class="filter-group" style="margin-left: 12px;">
                        <label>Skill Type</label>
                        <select id="company-assessment-skill-type" onchange="window.companyDashboardManager.loadSectionContent('assessments')">
                            <option value="" ${!currentSkillType ? 'selected' : ''}>All</option>
                            <option value="technical" ${currentSkillType === 'technical' ? 'selected' : ''}>Technical</option>
                            <option value="soft" ${currentSkillType === 'soft' ? 'selected' : ''}>Soft Skills</option>
                            <option value="aptitude" ${currentSkillType === 'aptitude' ? 'selected' : ''}>Aptitude</option>
                            <option value="domain" ${currentSkillType === 'domain' ? 'selected' : ''}>Domain</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="assessments-grid">
        `;
        
        assessments.forEach(assessment => {
            html += `
                <div class="assessment-card">
                    <div class="assessment-header">
                        <h3>${assessment.title}</h3>
                        <span class="assessment-status status-${assessment.status}">${assessment.status}</span>
                    </div>
                    <div class="assessment-content">
                        <p>${assessment.description}</p>
                        <div class="assessment-meta">
                            <span>📋 ${assessment.total_questions} questions</span>
                            <span>⏱️ ${Math.floor(assessment.time_limit / 60)} minutes</span>
                            <span>🎯 ${assessment.passing_score}% passing score</span>
                        </div>
                    </div>
                    <div class="assessment-actions">
                        <button class="btn btn-sm btn-secondary" onclick="window.companyDashboardManager.editAssessment(${assessment.id})">Edit</button>
                        <button class="btn btn-sm btn-primary" onclick="window.companyDashboardManager.viewAssessmentResults(${assessment.id})">View Results</button>
                        <button class="btn btn-sm btn-danger" onclick="window.companyDashboardManager.deleteAssessment(${assessment.id})">Delete</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    async loadAnalytics() {
        try {
            const response = await fetch('../api/company/analytics.php', { credentials: 'same-origin' });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.analytics) {
                return this.renderAnalytics(data.analytics);
            } else {
                return '<div class="empty-state"><p>No analytics data available.</p></div>';
            }
        } catch (error) {
            console.error('Error loading analytics:', error);
            return `
                <div class="error-state">
                    <p>Error loading analytics.</p>
                    <p style="font-size: 0.9em; color: #999;">${error.message}</p>
                </div>
            `;
        }
    }
    
    renderAnalytics(analytics) {
        // Handle the analytics data structure from API
        const totalJobs = analytics.total_jobs || 0;
        const activeJobs = analytics.active_jobs || 0;
        const totalApplications = analytics.total_applications || 0;
        
        return `
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3>📊 Summary Statistics</h3>
                    <div class="stats-container">
                        <div class="stat-item">
                            <span class="stat-label">Total Jobs</span>
                            <span class="stat-value">${totalJobs}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Active Jobs</span>
                            <span class="stat-value">${activeJobs}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Applications</span>
                            <span class="stat-value">${totalApplications}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Avg Applications per Job</span>
                            <span class="stat-value">${totalJobs > 0 ? Math.round(totalApplications / totalJobs) : 0}</span>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>📈 Job Status Distribution</h3>
                    <div class="chart-container">
                        <div class="chart-placeholder">
                            <p>Active Jobs: ${activeJobs}</p>
                            <p>Total Jobs: ${totalJobs}</p>
                            ${totalJobs > 0 ? `
                                <div style="margin-top: 20px;">
                                    <div style="background: #4CAF50; height: 30px; width: ${(activeJobs / totalJobs) * 100}%; margin-bottom: 5px;"></div>
                                    <small>Active: ${Math.round((activeJobs / totalJobs) * 100)}%</small>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>📄 Application Insights</h3>
                    <div class="insights-container">
                        <div class="insight-item">
                            <span class="insight-icon">📊</span>
                            <div class="insight-content">
                                <h4>Total Applications</h4>
                                <p>${totalApplications} applications received</p>
                            </div>
                        </div>
                        <div class="insight-item">
                            <span class="insight-icon">💼</span>
                            <div class="insight-content">
                                <h4>Jobs Posted</h4>
                                <p>${totalJobs} total jobs, ${activeJobs} currently active</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    async loadAssignments() {
        try {
            console.log('loadAssignments() called');
            const response = await fetch('../api/jobs/assignment.php', { 
                method: 'GET',
                credentials: 'same-origin' 
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Assignments data:', data);
            
            if (data.success && data.assignments) {
                return this.renderAssignments(data.assignments);
            } else {
                return `
                    <div class="empty-state">
                        <p>No assignments found. Create an assignment for a job posting.</p>
                        <button class="btn btn-primary" onclick="window.dashboardManager.showAssignmentCreationForm()">Create Assignment</button>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading assignments:', error);
            return `
                <div class="error-state">
                    <p>Error loading assignments.</p>
                    <p style="font-size: 0.9em; color: #999;">${error.message}</p>
                    <button class="btn btn-primary mt-3" onclick="window.dashboardManager.loadSectionContent('assignments')">Try Again</button>
                </div>
            `;
        }
    }
    
    renderAssignments(assignments) {
        if (!assignments || assignments.length === 0) {
            return `
                <div class="empty-state">
                    <p>No assignments created yet. Create assignments to give tasks to candidates!</p>
                    <button class="btn btn-primary" id="create-first-assignment-btn">Create Assignment</button>
                </div>
            `;
        }

        let html = `
            <div class="assignments-header">
                <div class="assignments-actions">
                    <button class="btn btn-primary" id="create-assignment-btn">
                        <span class="icon">➕</span>
                        Create Assignment
                    </button>
                </div>
            </div>
            <div class="assignments-grid">
        `;
        
        assignments.forEach(assignment => {
            const statusClass = assignment.status || 'pending';
            const dueDate = assignment.due_date ? new Date(assignment.due_date).toLocaleDateString() : 'No due date';
            
            html += `
                <div class="assignment-card">
                    <div class="assignment-header">
                        <h3>${assignment.title || 'Untitled Assignment'}</h3>
                        <span class="assignment-status status-${statusClass}">${statusClass}</span>
                    </div>
                    <div class="assignment-content">
                        <p>${assignment.description ? assignment.description.substring(0, 150) + '...' : 'No description'}</p>
                        <div class="assignment-meta">
                            <span>📋 Job: ${assignment.job_title || 'N/A'}</span>
                            <span>👤 Assignee: ${assignment.assignee_name || 'Unassigned'}</span>
                            <span>📅 Due: ${dueDate}</span>
                            ${assignment.creator_name ? `<span>✍️ Created by: ${assignment.creator_name}</span>` : ''}
                        </div>
                    </div>
                    <div class="assignment-actions">
                        <button class="btn btn-sm btn-primary" onclick="window.dashboardManager.viewAssignment(${assignment.id})">View Details</button>
                        <button class="btn btn-sm btn-secondary" onclick="window.dashboardManager.editAssignment(${assignment.id})">Edit</button>
                        ${assignment.status === 'submitted' ? 
                            `<button class="btn btn-sm btn-success" onclick="window.dashboardManager.reviewAssignment(${assignment.id})">Review</button>` : 
                            ''
                        }
                        <button class="btn btn-sm btn-danger" onclick="window.dashboardManager.deleteAssignment(${assignment.id})">Delete</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    async loadCompanyProfile() {
        try {
            const response = await fetch('../api/company/profile.php', { credentials: 'same-origin' });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.company) {
                return this.renderCompanyProfile(data.company);
            } else {
                return '<div class="error-state"><p>Error loading company profile.</p></div>';
            }
        } catch (error) {
            console.error('Error loading company profile:', error);
            return `
                <div class="error-state">
                    <p>Error loading company profile.</p>
                    <p style="font-size: 0.9em; color: #999;">${error.message}</p>
                </div>
            `;
        }
    }
    
    renderCompanyProfile(profile) {
        // Escape HTML to prevent XSS
        const escapeHtml = (str) => {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        };
        
        return `
            <div class="profile-form">
                <form id="company-profile-form">
                    <div class="form-section">
                        <h3>Company Information</h3>
                        <div class="form-group">
                            <label>Company Name *</label>
                            <input type="text" id="company-name" name="company_name" value="${escapeHtml(profile.company_name || '')}" required>
                        </div>
                        <div class="form-group">
                            <label>Industry</label>
                            <input type="text" id="industry" name="industry" value="${escapeHtml(profile.industry || '')}" placeholder="e.g., Technology, Healthcare, Finance">
                        </div>
                        <div class="form-group">
                            <label>Company Size</label>
                            <select id="company-size" name="company_size">
                                <option value="">Select Size</option>
                                <option value="1-10" ${profile.company_size === '1-10' ? 'selected' : ''}>1-10 employees</option>
                                <option value="11-50" ${profile.company_size === '11-50' ? 'selected' : ''}>11-50 employees</option>
                                <option value="51-200" ${profile.company_size === '51-200' ? 'selected' : ''}>51-200 employees</option>
                                <option value="201-500" ${profile.company_size === '201-500' ? 'selected' : ''}>201-500 employees</option>
                                <option value="501-1000" ${profile.company_size === '501-1000' ? 'selected' : ''}>501-1000 employees</option>
                                <option value="1000+" ${profile.company_size === '1000+' ? 'selected' : ''}>1000+ employees</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Website</label>
                            <input type="url" id="website" name="website" value="${escapeHtml(profile.website || '')}" placeholder="https://example.com">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea id="description" name="description" rows="6" placeholder="Describe your company...">${escapeHtml(profile.description || '')}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Headquarters</label>
                            <input type="text" id="headquarters" name="headquarters" value="${escapeHtml(profile.headquarters || '')}" placeholder="City, State, Country">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="save-profile-btn">Save Changes</button>
                        <span id="profile-save-status" style="margin-left: 10px;"></span>
                    </div>
                </form>
            </div>
        `;
    }
    
    setupProfileForm() {
        const profileForm = document.getElementById('company-profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.updateCompanyProfile();
            });
        }
    }
    
    async updateCompanyProfile() {
        const submitBtn = document.getElementById('save-profile-btn');
        const statusSpan = document.getElementById('profile-save-status');
        
        if (!submitBtn) {
            console.error('Save button not found');
            return;
        }
        
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        if (statusSpan) {
            statusSpan.textContent = '';
            statusSpan.style.color = '';
        }
        
        const profileData = {
            company_name: document.getElementById('company-name').value.trim(),
            industry: document.getElementById('industry').value.trim(),
            company_size: document.getElementById('company-size').value,
            website: document.getElementById('website').value.trim(),
            description: document.getElementById('description').value.trim(),
            headquarters: document.getElementById('headquarters').value.trim()
        };
        
        // Validate required fields
        if (!profileData.company_name) {
            alert('Company name is required');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }
        
        try {
            const response = await fetch('../api/company/profile.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(profileData)
            });
            
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response:', parseError);
                console.error('Response was:', responseText);
                throw new Error('Server returned invalid response');
            }
            
            if (data.success) {
                if (statusSpan) {
                    statusSpan.textContent = '✓ Saved successfully';
                    statusSpan.style.color = '#4CAF50';
                }
                setTimeout(() => {
                    if (statusSpan) {
                        statusSpan.textContent = '';
                    }
                }, 3000);
                
                // Optionally reload the profile to show updated data
                setTimeout(() => {
                    this.loadSectionContent('profile');
                }, 1000);
            } else {
                throw new Error(data.message || 'Failed to update profile');
            }
            
        } catch (error) {
            console.error('Error updating company profile:', error);
            alert('Error updating profile: ' + error.message);
            if (statusSpan) {
                statusSpan.textContent = '✗ Error saving';
                statusSpan.style.color = '#f44336';
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
    
    async loadSettings() {
        return `
            <div class="settings-container">
                <div class="settings-section">
                    <h3>Account Settings</h3>
                    <form id="account-settings-form">
                        <div class="form-group">
                            <label>Email Notifications</label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="email-notifications" checked>
                                Receive email notifications for new applications
                            </label>
                        </div>
                        <div class="form-group">
                            <label>Application Alerts</label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="application-alerts" checked>
                                Get alerts for new job applications
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
                
                <div class="settings-section">
                    <h3>Privacy Settings</h3>
                    <form id="privacy-settings-form">
                        <div class="form-group">
                            <label>Profile Visibility</label>
                            <select id="profile-visibility">
                                <option value="public">Public</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        `;
    }
    
    showJobCreationForm(jobData = null) {
        console.log('=== showJobCreationForm METHOD CALLED ===', jobData);
        const isEditMode = jobData && jobData.id;
        
        // Show job creation modal/form
        // Check if modal already exists to prevent duplicates
        if (document.querySelector('.modal')) {
            console.log('Modal already open - preventing duplicate');
            return;
        }
        
        // Create and append modal to body
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block'; // Ensure modal is visible
        
        // Add responsive styles directly to the modal
        modal.style.position = 'fixed';
        modal.style.zIndex = '1000';
        modal.style.left = '0';
        modal.style.top = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.overflow = 'auto';
        modal.style.backgroundColor = 'rgba(0,0,0,0.4)';
        
        document.body.appendChild(modal);
        
        // Pre-fill values if editing
        const titleValue = jobData ? (jobData.title || '') : '';
        const descValue = jobData ? (jobData.description || '') : '';
        const reqValue = jobData ? (jobData.requirements || '') : '';
        const respValue = jobData ? (jobData.responsibilities || '') : '';
        const locValue = jobData ? (jobData.location || '') : '';
        const typeValue = jobData ? (jobData.job_type || '') : '';
        const expValue = jobData ? (jobData.experience_level || '') : '';
        const workValue = jobData ? (jobData.work_type || '') : '';
        const salaryMin = jobData ? (jobData.salary_min || '') : '';
        const salaryMax = jobData ? (jobData.salary_max || '') : '';
        let skillsValue = '';
        if (jobData) {
            skillsValue = jobData.required_skills || '';
            if (!skillsValue) {
                const rv = jobData.requirements || '';
                const lines = rv.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
                const withComma = lines.filter(l => l.includes(','));
                if (withComma.length > 0) {
                    skillsValue = withComma[withComma.length - 1];
                }
            }
        }
        const deadlineValue = jobData ? (jobData.application_deadline ? jobData.application_deadline.split(' ')[0] : '') : '';
        
        modal.innerHTML = `
            <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px; border-radius: 5px; max-height: 80vh; overflow-y: auto;">
                <span class="close" onclick="this.closest('.modal').remove()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                <h2>${isEditMode ? 'Edit Job Posting' : 'Create New Job Posting'}</h2>
                ${isEditMode ? '<input type="hidden" id="job-id" value="' + jobData.id + '">' : ''}
                <form id="job-creation-form">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Job Title</label>
                        <input type="text" id="job-title" value="${escapeHtml(titleValue)}" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Job Description</label>
                        <textarea id="job-description" rows="6" required style="width: 100%; padding: 8px; box-sizing: border-box;">${escapeHtml(descValue)}</textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Requirements</label>
                        <textarea id="job-requirements" rows="4" style="width: 100%; padding: 8px; box-sizing: border-box;">${escapeHtml(reqValue)}</textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Responsibilities</label>
                        <textarea id="job-responsibilities" rows="4" style="width: 100%; padding: 8px; box-sizing: border-box;">${escapeHtml(respValue)}</textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Location</label>
                        <input type="text" id="job-location" value="${escapeHtml(locValue)}" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Job Type</label>
                        <select id="job-type" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                            <option value="">Select Type</option>
                            <option value="full_time" ${typeValue === 'full_time' ? 'selected' : ''}>Full Time</option>
                            <option value="part_time" ${typeValue === 'part_time' ? 'selected' : ''}>Part Time</option>
                            <option value="contract" ${typeValue === 'contract' ? 'selected' : ''}>Contract</option>
                            <option value="internship" ${typeValue === 'internship' ? 'selected' : ''}>Internship</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Experience Level</label>
                        <select id="experience-level" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                            <option value="">Select Level</option>
                            <option value="entry" ${expValue === 'entry' ? 'selected' : ''}>Entry Level</option>
                            <option value="mid" ${expValue === 'mid' ? 'selected' : ''}>Mid Level</option>
                            <option value="senior" ${expValue === 'senior' ? 'selected' : ''}>Senior Level</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Years of Experience (1–10)</label>
                        <input type="number" id="experience-years" min="1" max="10" step="1" placeholder="e.g. 5" style="width: 100%; padding: 8px; box-sizing: border-box;">
                        <small style="color:#666; display:block; margin-top:5px;">If provided, we will set the level automatically.</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Work Type</label>
                        <select id="work-type" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                            <option value="">Select Work Type</option>
                            <option value="remote" ${workValue === 'remote' ? 'selected' : ''}>Remote</option>
                            <option value="onsite" ${workValue === 'onsite' ? 'selected' : ''}>Onsite</option>
                            <option value="hybrid" ${workValue === 'hybrid' ? 'selected' : ''}>Hybrid</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Salary Range (Optional)</label>
                        <div class="salary-range" style="display: flex; gap: 10px; align-items: center;">
                            <input type="number" id="salary-min" value="${salaryMin}" placeholder="Min" style="flex: 1; padding: 8px; box-sizing: border-box;">
                            <span>to</span>
                            <input type="number" id="salary-max" value="${salaryMax}" placeholder="Max" style="flex: 1; padding: 8px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Required Skills (comma separated)</label>
                        <input type="text" id="job-skills" value="${escapeHtml(skillsValue)}" placeholder="e.g. JavaScript, React, Node.js" style="width: 100%; padding: 8px; box-sizing: border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">Application Deadline</label>
                        <input type="date" id="job-deadline" value="${deadlineValue}" style="width: 100%; padding: 8px; box-sizing: border-box;">
                    </div>
                    <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()" style="padding: 8px 16px; cursor: pointer; border-radius: 4px; border: none; background-color: #6c757d; color: white;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px; cursor: pointer; border-radius: 4px; border: none; background-color: #007bff; color: white;">${isEditMode ? 'Update Job' : 'Create Job'}</button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Handle form submission
        const form = modal.querySelector('#job-creation-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createJob(modal, isEditMode);
            });
        }
    }
    
    showAssessmentCreationForm() {
        console.log('=== showAssessmentCreationForm METHOD CALLED ===');
        // Show assessment creation modal/form
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
                <h2>Create New Assessment</h2>
                <form id="assessment-creation-form">
                    <div class="form-group">
                        <label>Assessment Title</label>
                        <input type="text" id="assessment-title" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="assessment-description" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Skill Type</label>
                        <select id="assessment-skill-type" required>
                            <option value="">Select Skill Type</option>
                            <option value="technical">Technical</option>
                            <option value="soft">Soft Skills</option>
                            <option value="aptitude">Aptitude</option>
                            <option value="domain">Domain Knowledge</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Time Limit (minutes)</label>
                        <input type="number" id="assessment-time-limit" min="5" max="180" value="30" required>
                    </div>
                    <div class="form-group">
                        <label>Passing Score (%)</label>
                        <input type="number" id="assessment-passing-score" min="1" max="100" value="70" required>
                    </div>
                    <div class="form-group">
                        <label>Upload Quiz File (Optional)</label>
                        <input type="file" id="assessment-quiz-file" accept=".pdf,.doc,.docx,.txt,.json,.csv">
                        <small style="color: #666; display: block; margin-top: 5px;">Supported formats: PDF, DOC, DOCX, TXT, JSON, CSV (Max 10MB)</small>
                        <div id="quiz-file-preview" style="margin-top: 10px; display: none;">
                            <span id="quiz-file-name" style="color: #007bff;"></span>
                            <button type="button" onclick="document.getElementById('assessment-quiz-file').value=''; document.getElementById('quiz-file-preview').style.display='none';" style="margin-left: 10px; color: #dc3545; background: none; border: none; cursor: pointer;">✕</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="assessment-is-public" style="margin-right: 5px;">
                            Make Quiz Public (Host for anyone to take)
                        </label>
                        <small style="color: #666; display: block; margin-top: 5px;">Public quizzes can be accessed via a shareable link</small>
                    </div>
                    <div class="form-group" id="share-link-group" style="display: none; padding: 10px; background: #f8f9fa; border-radius: 5px; margin-top: 10px;">
                        <label>Shareable Link</label>
                        <div style="display: flex; gap: 5px;">
                            <input type="text" id="assessment-share-link" readonly style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <button type="button" onclick="window.dashboardManager.copyShareLink()" class="btn btn-sm btn-secondary" style="padding: 8px 15px;">Copy Link</button>
                        </div>
                        <small style="color: #666; display: block; margin-top: 5px;">This link will be generated after creating the quiz</small>
                    </div>
                    <div class="form-group">
                        <label>Questions</label>
                        <div id="questions-container">
                            <div class="question-item">
                                <input type="text" class="question-text" placeholder="Question text" required>
                                <div class="options-container">
                                    <div class="option-item">
                                        <input type="text" class="option-text" placeholder="Option 1" required>
                                        <label><input type="radio" name="correct-0" value="0" checked> Correct</label>
                                    </div>
                                    <div class="option-item">
                                        <input type="text" class="option-text" placeholder="Option 2" required>
                                        <label><input type="radio" name="correct-0" value="1"> Correct</label>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary add-option">Add Option</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="add-question">Add Question</button>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Assessment</button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Handle file upload preview
        const fileInput = modal.querySelector('#assessment-quiz-file');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const preview = modal.querySelector('#quiz-file-preview');
                    const fileName = modal.querySelector('#quiz-file-name');
                    if (preview && fileName) {
                        fileName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                        preview.style.display = 'block';
                    }
                }
            });
        }
        
        // Handle public checkbox
        const isPublicCheckbox = modal.querySelector('#assessment-is-public');
        if (isPublicCheckbox) {
            isPublicCheckbox.addEventListener('change', (e) => {
                const shareLinkGroup = modal.querySelector('#share-link-group');
                if (shareLinkGroup) {
                    shareLinkGroup.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        }
        
        // Handle form submission
        const form = modal.querySelector('#assessment-creation-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createAssessment(modal);
            });
        }
        
        // Add question button handler
        const addQuestionBtn = modal.querySelector('#add-question');
        if (addQuestionBtn) {
            addQuestionBtn.addEventListener('click', () => {
                const questionsContainer = modal.querySelector('#questions-container');
                const questionCount = questionsContainer.querySelectorAll('.question-item').length;
                
                const questionItem = document.createElement('div');
                questionItem.className = 'question-item';
                questionItem.innerHTML = `
                    <input type="text" class="question-text" placeholder="Question text" required>
                    <div class="options-container">
                        <div class="option-item">
                            <input type="text" class="option-text" placeholder="Option 1" required>
                            <label><input type="radio" name="correct-${questionCount}" value="0" checked> Correct</label>
                        </div>
                        <div class="option-item">
                            <input type="text" class="option-text" placeholder="Option 2" required>
                            <label><input type="radio" name="correct-${questionCount}" value="1"> Correct</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary add-option">Add Option</button>
                    <button type="button" class="btn btn-sm btn-danger remove-question">Remove Question</button>
                `;
                
                questionsContainer.appendChild(questionItem);
                
                // Add option button handler
                const addOptionBtn = questionItem.querySelector('.add-option');
                addOptionBtn.addEventListener('click', (e) => {
                    const optionsContainer = e.target.previousElementSibling;
                    const optionCount = optionsContainer.querySelectorAll('.option-item').length;
                    
                    const optionItem = document.createElement('div');
                    optionItem.className = 'option-item';
                    optionItem.innerHTML = `
                        <input type="text" class="option-text" placeholder="Option ${optionCount + 1}" required>
                        <label><input type="radio" name="correct-${questionCount}" value="${optionCount}"> Correct</label>
                        <button type="button" class="btn btn-sm btn-danger remove-option">Remove</button>
                    `;
                    
                    optionsContainer.appendChild(optionItem);
                    
                    // Remove option button handler
                    const removeOptionBtn = optionItem.querySelector('.remove-option');
                    removeOptionBtn.addEventListener('click', () => {
                        optionItem.remove();
                    });
                });
                
                // Remove question button handler
                const removeQuestionBtn = questionItem.querySelector('.remove-question');
                removeQuestionBtn.addEventListener('click', () => {
                    questionItem.remove();
                });
            });
        }
        
        // Add option button handlers for initial question
        const addOptionBtns = modal.querySelectorAll('.add-option');
        addOptionBtns.forEach((btn, index) => {
            btn.addEventListener('click', (e) => {
                const optionsContainer = e.target.previousElementSibling;
                const optionCount = optionsContainer.querySelectorAll('.option-item').length;
                
                const optionItem = document.createElement('div');
                optionItem.className = 'option-item';
                optionItem.innerHTML = `
                    <input type="text" class="option-text" placeholder="Option ${optionCount + 1}" required>
                    <label><input type="radio" name="correct-${index}" value="${optionCount}"> Correct</label>
                    <button type="button" class="btn btn-sm btn-danger remove-option">Remove</button>
                `;
                
                optionsContainer.appendChild(optionItem);
                
                // Remove option button handler
                const removeOptionBtn = optionItem.querySelector('.remove-option');
                removeOptionBtn.addEventListener('click', () => {
                    optionItem.remove();
                });
            });
        });
    }
    
    async createJob(modal, isEditMode = false) {
        console.log('=== createJob METHOD CALLED ===', isEditMode);
        
        // Debug log to track execution
        console.log('Starting job ' + (isEditMode ? 'update' : 'creation') + ' process');

        // Get form elements from the modal
        const jobIdInput = modal.querySelector('#job-id');
        const titleInput = modal.querySelector('#job-title');
        const descriptionInput = modal.querySelector('#job-description');
        const requirementsInput = modal.querySelector('#job-requirements');
        const responsibilitiesInput = modal.querySelector('#job-responsibilities');
        const locationInput = modal.querySelector('#job-location');
        const jobTypeInput = modal.querySelector('#job-type');
        const experienceLevelInput = modal.querySelector('#experience-level');
        const workTypeInput = modal.querySelector('#work-type');
        const experienceYearsInput = modal.querySelector('#experience-years');
        const salaryMinInput = modal.querySelector('#salary-min');
        const salaryMaxInput = modal.querySelector('#salary-max');
        const deadlineInput = modal.querySelector('#job-deadline');
        const skillsInput = modal.querySelector('#job-skills');
        
        console.log('Form elements retrieved:', {
            title: titleInput?.value,
            description: descriptionInput?.value,
            location: locationInput?.value,
            jobType: jobTypeInput?.value,
            experienceLevel: experienceLevelInput?.value,
            workType: workTypeInput?.value
        });

        if (!titleInput || !descriptionInput || !locationInput || !jobTypeInput || !experienceLevelInput || !workTypeInput) {
            alert('Error: Form fields not found. Please try refreshing the page.');
            console.error('Form elements missing:', {
                title: !!titleInput,
                description: !!descriptionInput,
                location: !!locationInput,
                jobType: !!jobTypeInput,
                experienceLevel: !!experienceLevelInput,
                workType: !!workTypeInput
            });
            return;
        }

        let expYears = null;
        if (experienceYearsInput && experienceYearsInput.value) {
            const val = parseInt(experienceYearsInput.value, 10);
            if (!isNaN(val)) {
                expYears = Math.max(1, Math.min(10, val));
            }
        }

        const jobData = {
            title: titleInput.value.trim(),
            description: descriptionInput.value.trim(),
            requirements: requirementsInput ? requirementsInput.value.trim() : '',
            responsibilities: responsibilitiesInput ? responsibilitiesInput.value.trim() : '',
            location: locationInput.value.trim(),
            job_type: jobTypeInput.value,
            experience_level: (expYears !== null) ? (expYears <= 2 ? 'entry' : (expYears <= 5 ? 'mid' : 'senior')) : experienceLevelInput.value,
            work_type: workTypeInput.value,
            salary_min: salaryMinInput && salaryMinInput.value ? salaryMinInput.value : null,
            salary_max: salaryMaxInput && salaryMaxInput.value ? salaryMaxInput.value : null,
            application_deadline: deadlineInput && deadlineInput.value ? deadlineInput.value : null,
            status: 'active'
        };

        const skillsRaw = skillsInput && skillsInput.value ? skillsInput.value.trim() : '';
        if (skillsRaw) {
            const rv = jobData.requirements || '';
            const lines = rv.split(/\r?\n/);
            let replaced = false;
            for (let i = lines.length - 1; i >= 0; i--) {
                if (lines[i].includes(',')) {
                    lines[i] = skillsRaw;
                    replaced = true;
                    break;
                }
            }
            jobData.requirements = replaced ? lines.join('\n') : (rv ? `${rv}\n${skillsRaw}` : skillsRaw);
        }

        if (expYears !== null) {
            const req = jobData.requirements || '';
            if (!/\byears? of experience\b/i.test(req)) {
                jobData.requirements = req ? `${req}\nMinimum ${expYears} years of experience.` : `Minimum ${expYears} years of experience.`;
            }
        }
        
        // Add job_id if editing
        if (isEditMode && jobIdInput) {
            jobData.job_id = parseInt(jobIdInput.value);
        }

        console.log('Job data to send:', jobData);

        // Validate required fields
        if (!jobData.title || !jobData.description || !jobData.location ||
            !jobData.job_type || !jobData.experience_level || !jobData.work_type) {
            alert('Please fill in all required fields (Title, Description, Location, Job Type, Experience Level, and Work Type)');
            console.error('Validation failed: Missing required fields', jobData);
            return;
        }

        // Disable submit button during submission
        const submitBtn = modal.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = isEditMode ? 'Updating...' : 'Creating...';
        }

        try {
            const method = isEditMode ? 'PUT' : 'POST';
            console.log(`Sending ${method} request to ../api/company/jobs.php`);
            const response = await fetch('../api/company/jobs.php', {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(jobData)
            });

            console.log('Response status:', response.status);

            const responseText = await response.text();
            console.log('Response text:', responseText);

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response:', parseError);
                console.error('Response was:', responseText);
                alert('Server returned invalid response. Please check the console for details.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
                return;
            }

            console.log('Parsed response data:', data);

            if (data.success) {
                alert(isEditMode ? 'Job updated successfully!' : 'Job created successfully!');
                modal.remove();
                
                console.log('Job ' + (isEditMode ? 'updated' : 'created') + ' successfully, switching to jobs section');
                // Use the section switcher to handle visibility and content
                this.showSection('jobs');
                
                // Reload jobs section
                this.loadSectionContent('jobs');
            } else {
                console.error('Server returned error:', data.message);
                alert('Error ' + (isEditMode ? 'updating' : 'creating') + ' job: ' + (data.message || 'Unknown error'));
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            }
        } catch (error) {
            console.error('Error ' + (isEditMode ? 'updating' : 'creating') + ' job:', error);
            console.error('Error stack:', error.stack);
            alert('Error ' + (isEditMode ? 'updating' : 'creating') + ' job: ' + error.message + '\n\nPlease check the console for more details.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        }
    }
    
    async createAssessment(modal) {
        // Gather assessment data
        const title = document.getElementById('assessment-title').value;
        const description = document.getElementById('assessment-description').value;
        const skillType = document.getElementById('assessment-skill-type').value;
        const timeLimit = document.getElementById('assessment-time-limit').value * 60; // Convert to seconds
        const passingScore = document.getElementById('assessment-passing-score').value;
        const isPublic = document.getElementById('assessment-is-public').checked;
        const quizFile = document.getElementById('assessment-quiz-file').files[0];
        
        // Gather questions and options
        const questions = [];
        const questionItems = modal.querySelectorAll('.question-item');
        
        questionItems.forEach((item) => {
            const questionText = item.querySelector('.question-text').value;
            const options = [];
            const optionItems = item.querySelectorAll('.option-item');
            let hasCorrect = false;

            optionItems.forEach((optItem) => {
                const optionText = (optItem.querySelector('.option-text')?.value || '').trim();
                const radio = optItem.querySelector('input[type="radio"]');
                const isCorrect = !!(radio && radio.checked);
                if (isCorrect) hasCorrect = true;
                options.push({ text: optionText, is_correct: isCorrect });
            });

            if (!hasCorrect) {
                throw new Error('Each question must have one correct option selected');
            }

            questions.push({ question_text: questionText, options });
        });
        
        // Use FormData if file is uploaded, otherwise use JSON
        const formData = new FormData();
        formData.append('title', title);
        formData.append('description', description);
        formData.append('skill_type', skillType);
        formData.append('time_limit', timeLimit);
        formData.append('passing_score', passingScore);
        formData.append('is_public', isPublic ? '1' : '0');
        formData.append('questions', JSON.stringify(questions));
        formData.append('status', 'active');
        
        if (quizFile) {
            // Validate file size (10MB max)
            if (quizFile.size > 10 * 1024 * 1024) {
                alert('File size exceeds 10MB limit. Please choose a smaller file.');
                return;
            }
            formData.append('quiz_file', quizFile);
        }
        
        try {
            const submitBtn = modal.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn ? submitBtn.textContent : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating...';
            }
            
            const response = await fetch('../api/company/assessments.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show share link if quiz is public
                if (isPublic && data.share_link) {
                    const shareLinkInput = document.getElementById('assessment-share-link');
                    if (shareLinkInput) {
                        shareLinkInput.value = data.share_link;
                        document.getElementById('share-link-group').style.display = 'block';
                    }
                    alert('Assessment created successfully! Share link: ' + data.share_link);
                } else {
                    alert('Assessment created successfully!');
                }
                
                // Don't remove modal if showing share link
                if (!isPublic || !data.share_link) {
                    modal.remove();
                    this.loadSectionContent('assessments');
                } else {
                    // Reset submit button
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                    }
                }
            } else {
                alert('Error creating assessment: ' + (data.message || 'Unknown error'));
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            }
        } catch (error) {
            console.error('Error creating assessment:', error);
            alert('Error creating assessment. Please try again.');
            const submitBtn = modal.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Assessment';
            }
        }
    }
    
    copyShareLink() {
        const shareLinkInput = document.getElementById('assessment-share-link');
        if (shareLinkInput && shareLinkInput.value) {
            shareLinkInput.select();
            shareLinkInput.setSelectionRange(0, 99999); // For mobile devices
            try {
                document.execCommand('copy');
                alert('Share link copied to clipboard!');
            } catch (err) {
                // Fallback for browsers that don't support execCommand
                navigator.clipboard.writeText(shareLinkInput.value).then(() => {
                    alert('Share link copied to clipboard!');
                }).catch(() => {
                    alert('Please manually copy the link: ' + shareLinkInput.value);
                });
            }
        }
    }
    
    async viewApplication(applicationId) {
        if (!applicationId) {
            alert('Invalid application ID');
            return;
        }
        try {
            // Fetch application details
            const response = await fetch(`../api/company/application_details.php?id=${applicationId}`, {
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.success && data.application) {
                const app = data.application;
                
                // Show application details in a modal
                const modal = document.createElement('div');
                modal.className = 'modal';
                modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;z-index:10000;';
                modal.innerHTML = `
                    <div style="background:#fff;border-radius:10px;max-width:800px;width:92%;max-height:85vh;overflow:auto;padding:24px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                            <h2>Application Details</h2>
                            <button onclick="this.closest('.modal').remove()" style="border:none;background:#e74c3c;color:#fff;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:20px;">&times;</button>
                        </div>
                        <div style="display:grid;gap:16px;">
                            <div>
                                <strong>Candidate:</strong> ${app.candidate_name || 'N/A'}<br>
                                <strong>Email:</strong> ${app.candidate_email || 'N/A'}<br>
                                <strong>Job:</strong> ${app.job_title || app.title || 'N/A'}<br>
                                <strong>Status:</strong> <span class="status-badge status-${app.status || 'applied'}">${(app.status || 'applied').charAt(0).toUpperCase() + (app.status || 'applied').slice(1)}</span><br>
                                <strong>Applied:</strong> ${app.application_date ? new Date(app.application_date).toLocaleDateString() : 'N/A'}
                            </div>
                            ${app.cover_letter ? `<div><strong>Cover Letter:</strong><p style="margin-top:8px;padding:12px;background:#f8f9fa;border-radius:4px;">${app.cover_letter.replace(/\n/g, '<br>')}</p></div>` : ''}
                            ${app.resume_file ? `<div><strong>Resume:</strong> <a href="../api/resume/download.php?file=${encodeURIComponent(app.resume_file)}" target="_blank" class="btn btn-sm btn-primary">Download Resume</a></div>` : ''}
                        </div>
                        <div style="margin-top:20px;padding-top:20px;border-top:1px solid #eee;">
                            <h3>Update Status</h3>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
                                <button onclick="window.dashboardManager.updateApplicationStatus(${applicationId}, 'screening')" class="btn btn-sm btn-success">Screen</button>
                                <button onclick="window.dashboardManager.updateApplicationStatus(${applicationId}, 'interview')" class="btn btn-sm btn-warning">Interview</button>
                                <button onclick="window.dashboardManager.updateApplicationStatus(${applicationId}, 'offered')" class="btn btn-sm btn-success">Offer</button>
                                <button onclick="window.dashboardManager.updateApplicationStatus(${applicationId}, 'rejected')" class="btn btn-sm btn-danger">Reject</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                
                // Close on outside click
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) modal.remove();
                });
            } else {
                alert('Failed to load application details: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading application details:', error);
            alert('Error loading application details. Please try again.');
        }
    }
    
    async updateApplicationStatus(applicationId, status) {
        if (!applicationId || !status) {
            alert('Invalid application ID or status');
            return;
        }
        try {
            const response = await fetch('../api/company/update_application_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    application_id: applicationId,
                    status: status
                })
            });
            
            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseErr) {
                console.error('JSON parse error:', parseErr, 'Response:', responseText);
                alert('Server returned invalid response. Please check the console.');
                return;
            }
            
            if (data.success) {
                alert('Application status updated successfully!');
                // Reload applications section
                this.loadSectionContent('applications');
            } else {
                alert('Error updating status: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error updating application status:', error);
            alert('Error updating status. Please try again.');
        }
    }
    
    showAssignmentCreationForm(jobId = null) {
        console.log('=== showAssignmentCreationForm METHOD CALLED ===');
        // First, load available jobs for the dropdown
        this.loadJobsForAssignment().then(jobs => {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'block'; // Ensure modal is visible
            modal.style.position = 'fixed';
            modal.style.zIndex = '1000';
            modal.style.left = '0';
            modal.style.top = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.overflow = 'auto';
            modal.style.backgroundColor = 'rgba(0,0,0,0.4)';
            
            document.body.appendChild(modal); // Ensure modal is added to DOM
            
            modal.innerHTML = `
                <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px; border-radius: 5px; max-height: 80vh; overflow-y: auto;">
                    <span class="close" onclick="this.closest('.modal').remove()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                    <h2>Create New Assignment</h2>
                    <form id="assignment-creation-form">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Select Job *</label>
                            <select id="assignment-job-id" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                                <option value="">Select a job</option>
                                ${jobs.map(job => `<option value="${job.id}" ${jobId && job.id == jobId ? 'selected' : ''}>${job.title}</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Assignment Title *</label>
                            <input type="text" id="assignment-title" required placeholder="e.g., Complete coding challenge" style="width: 100%; padding: 8px; box-sizing: border-box;">
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Description *</label>
                            <textarea id="assignment-description" rows="6" required placeholder="Describe what the candidate needs to do..." style="width: 100%; padding: 8px; box-sizing: border-box;"></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Due Date *</label>
                            <input type="date" id="assignment-due-date" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Assign To (Optional)</label>
                            <select id="assignment-assign-to" style="width: 100%; padding: 8px; box-sizing: border-box;">
                                <option value="">Leave unassigned (assign later)</option>
                                <!-- Will be populated dynamically if needed -->
                            </select>
                            <small style="display: block; margin-top: 5px;">Leave unassigned to create a template assignment that can be assigned to candidates later</small>
                        </div>
                        <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                            <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()" style="padding: 8px 16px; cursor: pointer; border-radius: 4px; border: none; background-color: #6c757d; color: white;">Cancel</button>
                            <button type="submit" class="btn btn-primary" style="padding: 8px 16px; cursor: pointer; border-radius: 4px; border: none; background-color: #007bff; color: white;">Create Assignment</button>
                        </div>
                    </form>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Set minimum date to today
            const dueDateInput = modal.querySelector('#assignment-due-date');
            if (dueDateInput) {
                dueDateInput.min = new Date().toISOString().split('T')[0];
            }
            
            // Handle form submission
            const form = modal.querySelector('#assignment-creation-form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.createAssignment(modal);
                });
            }
        }).catch(error => {
            console.error('Error loading jobs for assignment:', error);
            alert('Error loading jobs. Please try again.');
        });
    }
    
    async loadJobsForAssignment() {
        try {
            const response = await fetch('../api/company/jobs.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            if (data.success && data.jobs) {
                return data.jobs;
            }
            return [];
        } catch (error) {
            console.error('Error loading jobs:', error);
            return [];
        }
    }
    
    async createAssignment(modal) {
        console.log('=== createAssignment METHOD CALLED ===');

        const assignmentData = {
            job_id: parseInt(document.getElementById('assignment-job-id').value),
            title: document.getElementById('assignment-title').value,
            description: document.getElementById('assignment-description').value,
            due_date: document.getElementById('assignment-due-date').value,
            assigned_to: document.getElementById('assignment-assign-to').value || null
        };

        console.log('Assignment data to send:', assignmentData);

        // Validate required fields
        if (!assignmentData.job_id || !assignmentData.title || !assignmentData.description || !assignmentData.due_date) {
            alert('Please fill in all required fields');
            console.error('Validation failed: Missing required fields');
            return;
        }

        try {
            console.log('Sending POST request to ../api/jobs/assignment.php');
            const response = await fetch('../api/jobs/assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(assignmentData)
            });

            console.log('Response status:', response.status);

            const responseText = await response.text();
            console.log('Response text:', responseText);

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response:', parseError);
                console.error('Response was:', responseText);
                alert('Server returned invalid response. Check console for details.');
                return;
            }

            console.log('Parsed response data:', data);

            if (data.success) {
                alert('Assignment created successfully!');
                modal.remove();
                // Force reload of assignments section
                this.loadSectionContent('assignments');
                // Explicitly show assignments section
                showSection('assignments');
                // Update navigation
                document.querySelectorAll('.sidebar-menu a').forEach(link => {
                    link.classList.remove('active');
                });
                document.querySelector('.sidebar-menu a[href="#assignments"]').classList.add('active');
            } else {
                console.error('Server returned error:', data.message);
                alert('Error creating assignment: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error creating assignment:', error);
            console.error('Error stack:', error.stack);
            alert('Error creating assignment. Please try again. Check console for details.');
        }
    }
    
    viewAssignment(assignmentId) {
        console.log('View assignment:', assignmentId);
        // Redirect to assignment detail page
        window.location.href = `assignment_detail.php?id=${assignmentId}`;
    }
    
    editAssignment(assignmentId) {
        console.log('Edit assignment:', assignmentId);
        // Redirect to create_assignment.php with the assignment ID for editing
        window.location.href = `../create_assignment.php?edit=${assignmentId}`;
    }
    
    reviewAssignment(assignmentId) {
        console.log('Review assignment:', assignmentId);
        // TODO: Implement assignment review
        alert('Assignment review will be implemented soon.');
    }
    
    deleteAssignment(assignmentId) {
        if (confirm('Are you sure you want to delete this assignment?')) {
            console.log('Delete assignment:', assignmentId);
            // TODO: Implement assignment deletion
            alert('Assignment deletion will be implemented soon.');
        }
    }
    async editJob(jobId) {
        if (!jobId) {
            alert('Invalid job ID');
            return;
        }
        try {
            // Fetch job details
            const response = await fetch(`../api/company/jobs.php?job_id=${jobId}`, {
                credentials: 'same-origin'
            });
            const data = await response.json();
            
            if (!data.success || !data.job) {
                alert('Failed to load job details: ' + (data.message || 'Unknown error'));
                return;
            }
            
            const job = data.job;
            
            // Show job creation form with pre-filled data
            this.showJobCreationForm(job);
        } catch (err) {
            console.error('Error loading job for editing:', err);
            alert('Error loading job details. Please try again.');
        }
    }
    
    async viewJobApplications(jobId) {
        if (!jobId) {
            alert('Invalid job ID');
            return;
        }
        try {
            // Store the job ID filter
            sessionStorage.setItem('filter_job_id', jobId);
            
            // Switch to applications section and filter by job
            showSection('applications');
            
            // Reload applications section (this will automatically show the filter message)
            this.loadSectionContent('applications');
        } catch (err) {
            console.error('Error viewing job applications:', err);
            alert('Error loading applications. Please try again.');
        }
    }
    
    async deleteJob(jobId) {
        if (!jobId) {
            alert('Invalid job ID');
            return;
        }
        if (!confirm('Are you sure you want to delete this job posting? This action cannot be undone.')) {
            return;
        }
        try {
            const response = await fetch('../api/company/jobs.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ job_id: jobId })
            });
            
            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseErr) {
                console.error('JSON parse error:', parseErr, 'Response:', responseText);
                alert('Server returned invalid response. Please check the console.');
                return;
            }
            
            if (data.success) {
                alert(data.message || 'Job deleted successfully');
                // Reload jobs section
                this.loadSectionContent('jobs');
            } else {
                alert('Failed to delete job: ' + (data.message || 'Unknown error'));
            }
        } catch (err) {
            console.error('Error deleting job:', err);
            alert('Error deleting job. Please try again.');
        }
    }

    async viewCandidateProfile(candidateId) {
        if (!candidateId) {
            alert('Invalid candidate identifier');
            return;
        }
        try {
            const response = await fetch(`../api/company/candidates.php?candidate_id=${encodeURIComponent(candidateId)}`, {
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!data.success) {
                alert('Failed to load candidate profile: ' + (data.message || 'Unknown error'));
                return;
            }
            const c = data.candidate || {};
            // Basic modal rendering
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <span class="close-modal" style="cursor:pointer;float:right;font-size:20px">&times;</span>
                    <div class="candidate-profile">
                        <h2>${c.name || c.full_name || 'Candidate'}</h2>
                        <p><strong>Email:</strong> ${c.email || 'N/A'}</p>
                        <p><strong>Location:</strong> ${c.location || 'N/A'}</p>
                        <p><strong>Experience:</strong> ${c.experience_years || 0} years</p>
                        <div>
                            <strong>Skills:</strong>
                            ${(function(){
                                try {
                                    const skills = typeof c.skills === 'string' ? JSON.parse(c.skills) : (c.skills || []);
                                    return (skills || []).slice(0,5).map(s => `<span class=\"skill-tag\">${typeof s === 'string' ? s : (s.name || '')}</span>`).join(' ');
                                } catch(_) { return ''; }
                            })()}
                        </div>
                        ${c.resume_file ? `<p><a href=\"../api/resume/download.php?file=${encodeURIComponent(c.resume_file)}\" target=\"_blank\">Download Resume</a></p>` : ''}
                    </div>
                </div>`;
            document.body.appendChild(modal);
            const close = modal.querySelector('.close-modal');
            close.addEventListener('click', () => modal.remove());
            modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
        } catch (err) {
            console.error('Error loading candidate profile:', err);
            alert('Error loading candidate profile.');
        }
    }
}

// Global functions for company dashboard
function editJob(jobId) {
    // Implement job editing
    console.log('Edit job:', jobId);
}

function deleteJob(jobId) {
    if (window.dashboardManager && typeof window.dashboardManager.deleteJob === 'function') {
        window.dashboardManager.deleteJob(jobId);
    } else {
        console.log('Delete job:', jobId);
    }
}

function viewJobApplications(jobId) {
    // Implement view job applications
    console.log('View applications for job:', jobId);
}

function viewCandidate(candidateId) {
    if (window.dashboardManager && typeof window.dashboardManager.viewCandidateProfile === 'function') {
        window.dashboardManager.viewCandidateProfile(candidateId);
    } else {
        console.log('View candidate:', candidateId);
    }
}

function contactCandidate(candidateId) {
    // Implement contact candidate
    console.log('Contact candidate:', candidateId);
}

function updateApplicationStatus(applicationId, status) {
    if (window.companyDashboardManager) {
        window.companyDashboardManager.updateApplicationStatus(applicationId, status);
    }
}

function viewApplication(applicationId) {
    if (window.dashboardManager && typeof window.dashboardManager.viewApplication === 'function') {
        window.dashboardManager.viewApplication(applicationId);
    } else {
        console.log('View application:', applicationId);
        alert('Application details feature is not available. Please refresh the page.');
    }
}

function editAssessment(assessmentId) {
    // Implement assessment editing
    console.log('Edit assessment:', assessmentId);
}

function deleteAssessment(assessmentId) {
    if (confirm('Are you sure you want to delete this assessment?')) {
        // Implement assessment deletion
        console.log('Delete assessment:', assessmentId);
    }
}

function viewAssessmentResults(assessmentId) {
    // Implement view assessment results
    console.log('View assessment results:', assessmentId);
}

function filterCandidates() {
    // Implement candidate filtering
    console.log('Filter candidates');
}

function filterApplications() {
    // Implement application filtering
    console.log('Filter applications');
}

// Initialize company dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Company Dashboard Initializing ===');
    
    // Prevent double initialization from base dashboard.js
    // Only initialize if we're on the company dashboard page
    if (window.location.pathname.includes('company.php')) {
        // Create company dashboard manager
        window.dashboardManager = new CompanyDashboardManager();
        window.companyDashboardManager = window.dashboardManager;
        
        console.log('window.dashboardManager created:', window.dashboardManager);
        console.log('showJobCreationForm method exists:', typeof window.dashboardManager.showJobCreationForm);
        console.log('showAssessmentCreationForm method exists:', typeof window.dashboardManager.showAssessmentCreationForm);

        // Expose global functions
        window.showJobCreationForm = showJobCreationForm;
        window.showAssessmentCreationForm = showAssessmentCreationForm;
        window.showSection = showSection;
        window.editJob = editJob;
        window.deleteJob = deleteJob;
        window.editAssessment = editAssessment;
        window.deleteAssessment = deleteAssessment;
        console.log('Global functions exposed to window');

        // Add event listeners for section navigation
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const sectionName = link.getAttribute('href').replace('#', '');
                if (window.dashboardManager && window.dashboardManager.showSection) {
                    window.dashboardManager.showSection(sectionName);
                } else {
                    showSection(sectionName);
                }
            });
        });

        // Initialize with the dashboard section or the section from URL hash
        const initialSection = window.location.hash ? window.location.hash.substring(1) : 'dashboard';
        if (window.dashboardManager && window.dashboardManager.showSection) {
            window.dashboardManager.showSection(initialSection);
        } else {
            showSection(initialSection);
        }
        
        console.log('=== Company Dashboard Initialized ===');
    }
});
