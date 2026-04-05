// AI Job Recommendation System - Admin Dashboard JavaScript

// Admin Dashboard Manager
class AdminDashboardManager extends DashboardManager {
    constructor() {
        super();
        this.initAdminSpecific();
    }
    
    // Override showSection to handle admin-specific behavior
    showSection(sectionName) {
        console.log('AdminDashboardManager: Switching to section:', sectionName);
        
        if (this.isLoading) {
            console.warn('AdminDashboardManager: Section loading in progress, ignoring request');
            return;
        }
        
        try {
            // Hide all sections
            document.querySelectorAll('.dashboard-section').forEach(section => {
                section.classList.remove('active');
                console.log('Hidden section:', section.id);
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
                console.log('Showing section:', targetSection.id);
                
                // Add active class to nav item
                const navItem = document.querySelector(`[onclick="showSection('${sectionName}')"]`)?.closest('li');
                if (navItem) {
                    navItem.classList.add('active');
                    console.log('Activated nav item for:', sectionName);
                } else {
                    console.warn('Nav item not found for section:', sectionName);
                }
                
                // Load section content
                this.loadSectionContent(sectionName);
            } else {
                console.error('Section not found:', `${sectionName}-section`);
                // Try to show dashboard section as fallback
                const dashboardSection = document.getElementById('dashboard-section');
                if (dashboardSection) {
                    dashboardSection.classList.add('active');
                    console.log('Fallback to dashboard section');
                }
            }
        } catch (error) {
            console.error('Error in showSection:', error);
        }
    }
    
    initAdminSpecific() {
        // Initialize admin-specific functionality
        this.setupUserManagement();
        this.setupSystemMonitoring();
        this.setupDataExport();
    }
    
    setupUserManagement() {
        // Setup user management handlers
        document.addEventListener('click', (event) => {
            if (event.target.matches('[onclick*="suspendUser"]')) {
                const userId = event.target.getAttribute('data-user-id');
                this.suspendUser(userId);
            }
            
            if (event.target.matches('[onclick*="activateUser"]')) {
                const userId = event.target.getAttribute('data-user-id');
                this.activateUser(userId);
            }
            
            if (event.target.matches('[onclick*="deleteUser"]')) {
                const userId = event.target.getAttribute('data-user-id');
                this.deleteUser(userId);
            }
        });
    }
    
    setupSystemMonitoring() {
        // Setup system monitoring
        this.startSystemMonitoring();
    }
    
    setupDataExport() {
        // Setup data export functionality
        document.addEventListener('click', (event) => {
            if (event.target.matches('[onclick*="exportData"]')) {
                this.showExportOptions();
            }
        });
    }
    
    startSystemMonitoring() {
        // Monitor system health every 30 seconds
        setInterval(() => {
            this.updateSystemHealth();
        }, 30000);
    }
    
    async loadSectionContent(sectionName) {
        console.log('AdminDashboardManager: Loading content for section:', sectionName);
        
        const contentElement = document.getElementById(`${sectionName}-content`);
        if (!contentElement) {
            console.error('Content element not found:', `${sectionName}-content`);
            return;
        }
        
        // Set loading flag
        this.isLoading = true;
        
        // Show loading state
        contentElement.innerHTML = '<div class="loading-state"><div class="loading"></div><p>Loading...</p></div>';
        
        try {
            let content = '';
            console.log('Determining content loader for section:', sectionName);
            
            switch (sectionName) {
                case 'users':
                    console.log('Loading users section...');
                    content = await this.loadUsers();
                    break;
                case 'jobs':
                    console.log('Loading jobs section...');
                    content = await this.loadJobs();
                    break;
                case 'assessments':
                    console.log('Loading assessments section...');
                    content = await this.loadAssessments();
                    break;
                case 'recommendations':
                    console.log('Loading recommendations section...');
                    content = await this.loadRecommendations();
                    break;
                case 'analytics':
                    console.log('Loading analytics section...');
                    content = await this.loadAnalytics();
                    break;
                case 'system':
                    console.log('Loading system section...');
                    content = await this.loadSystem();
                    break;
                case 'settings':
                    console.log('Loading settings section...');
                    content = await this.loadSettings();
                    break;
                case 'notifications':
                    console.log('Loading notifications section...');
                    content = await this.loadNotifications();
                    break;
                case 'security':
                    console.log('Loading security section...');
                    content = await this.loadSecurity();
                    break;
                case 'reports':
                    console.log('Loading reports section...');
                    content = await this.loadReports();
                    break;
                case 'monitoring':
                    console.log('Loading monitoring section...');
                    content = await this.loadMonitoring();
                    break;
                default:
                    console.log('Delegating to parent class for section:', sectionName);
                    content = await super.loadSectionContent(sectionName);
            }
            
            console.log('Content loaded successfully, updating DOM');
            contentElement.innerHTML = content;
            
            // Initialize section-specific functionality
            console.log('Initializing section:', sectionName);
            this.initializeSection(sectionName);
            
        } catch (error) {
            console.error('Error loading section content for', sectionName, ':', error);
            contentElement.innerHTML = `
                <div class="error-state">
                    <p>Error loading content. Please try again.</p>
                    <p><small>Error: ${error.message}</small></p>
                    <button class="btn btn-primary" onclick="window.adminDashboardManager.loadSectionContent('${sectionName}')">Retry</button>
                </div>
            `;
        } finally {
            // Clear loading flag
            this.isLoading = false;
        }
    }
    
    async loadUsers() {
        try {
            console.log('Fetching users from API...');
            const response = await fetch('../api/admin/users.php', { credentials: 'same-origin' });
            console.log('Users API response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Users API data:', data);
            
            if (data.success && data.users) {
                return this.renderUsers(data.users);
            } else {
                console.warn('No users data or unsuccessful response:', data);
                return this.getUsersPlaceholderContent();
            }
        } catch (error) {
            console.error('Error loading users:', error);
            return this.getUsersErrorContent(error.message);
        }
    }
    
    getUsersPlaceholderContent() {
        return `
            <div class="users-container">
                <div class="users-header">
                    <h3>👥 User Management</h3>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="createUser()">
                            <span class="icon">➕</span> Add User
                        </button>
                        <button class="btn btn-secondary" onclick="window.adminDashboardManager.loadSectionContent('users')">
                            <span class="icon">🔄</span> Refresh
                        </button>
                    </div>
                </div>
                
                <div class="user-stats">
                    <div class="stat-card">
                        <div class="stat-icon">👤</div>
                        <div class="stat-content">
                            <h4>Job Seekers</h4>
                            <span class="stat-number">--</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🏢</div>
                        <div class="stat-content">
                            <h4>Companies</h4>
                            <span class="stat-number">--</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👨‍💼</div>
                        <div class="stat-content">
                            <h4>Administrators</h4>
                            <span class="stat-number">--</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-content">
                            <h4>Active Users</h4>
                            <span class="stat-number">--</span>
                        </div>
                    </div>
                </div>
                
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>No User Data Available</h3>
                    <p>Unable to load user data from the database. This could be due to:</p>
                    <ul style="text-align: left; display: inline-block;">
                        <li>Database connection issues</li>
                        <li>No users registered yet</li>
                        <li>Permission restrictions</li>
                    </ul>
                    <div class="empty-actions">
                        <button class="btn btn-primary" onclick="window.adminDashboardManager.loadSectionContent('users')">
                            Try Again
                        </button>
                        <button class="btn btn-secondary" onclick="createUser()">
                            Add First User
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    getUsersErrorContent(errorMessage) {
        return `
            <div class="users-container">
                <div class="error-state">
                    <div class="error-icon">❌</div>
                    <h3>Error Loading Users</h3>
                    <p>There was an error loading the user data:</p>
                    <div class="error-details">
                        <code>${errorMessage}</code>
                    </div>
                    <div class="error-actions">
                        <button class="btn btn-primary" onclick="window.adminDashboardManager.loadSectionContent('users')">
                            Retry
                        </button>
                        <button class="btn btn-secondary" onclick="console.log('Debug info:', {url: '../api/admin/users.php', timestamp: new Date()})">
                            Debug Info
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    renderUsers(users) {
        if (!users || users.length === 0) {
            return '<div class="empty-state"><p>No users found.</p></div>';
        }
        
        let html = `
            <div class="users-filters">
                <input type="text" id="user-search" placeholder="Search users...">
                <select id="user-type-filter">
                    <option value="">All Types</option>
                    <option value="job_seeker">Job Seekers</option>
                    <option value="company">Companies</option>
                    <option value="admin">Admins</option>
                </select>
                <select id="user-status-filter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
                <select id="user-verified-filter">
                    <option value="">All</option>
                    <option value="0">Unverified</option>
                    <option value="1">Verified</option>
                </select>
                <button class="btn btn-secondary" onclick="filterUsers()">Filter</button>
            </div>
            <div class="users-table-container">
                <div class="table-actions">
                    <button class="btn btn-sm btn-primary" onclick="bulkVerify()">Verify Selected</button>
                </div>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-users" onchange="toggleAllUsers()"></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        users.forEach(user => {
            html += `
                <tr>
                    <td><input type="checkbox" class="user-checkbox" value="${user.id}"></td>
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td>
                        <span class="user-type-badge type-${user.user_type}">
                            ${user.user_type.replace('_', ' ')}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-${user.status}">
                            ${user.status}
                        </span>
                    </td>
                    <td>${user.last_activity ? new Date(user.last_activity).toLocaleDateString() : 'Never'}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-secondary" onclick="viewUser(${user.id})">View</button>
                            ${user.status === 'active' ? 
                                `<button class="btn btn-sm btn-warning" onclick="suspendUser(${user.id})">Suspend</button>` :
                                `<button class="btn btn-sm btn-success" onclick="activateUser(${user.id})">Activate</button>`
                            }
                            ${user.email_verified ? '' : `<button class="btn btn-sm btn-primary" onclick="verifyUser(${user.id})">Verify Email</button>`}
                            <button class="btn btn-sm btn-info" onclick="assignAssessment(${user.id})">Assign Assessment</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        return html;
    }
    
    async loadJobs() {
        try {
            console.log('Fetching jobs from API...');
            const response = await fetch('../api/admin/jobs.php', { credentials: 'same-origin' });
            console.log('Jobs API response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Jobs API data:', data);
            
            if (data.success && data.jobs) {
                return this.renderJobs(data.jobs);
            } else {
                console.warn('No jobs data or unsuccessful response:', data);
                return this.getJobsPlaceholderContent();
            }
        } catch (error) {
            console.error('Error loading jobs:', error);
            return this.getJobsErrorContent(error.message);
        }
    }
    
    getJobsPlaceholderContent() {
        return `
            <div class="jobs-container">
                <div class="jobs-header">
                    <div class="jobs-actions">
                        <button class="btn btn-primary" onclick="createJob()">➕ Create Job</button>
                        <button class="btn btn-secondary" onclick="window.adminDashboardManager.loadSectionContent('jobs')">🔄 Refresh</button>
                        <button class="btn btn-info" onclick="bulkJobActions()">📋 Bulk Actions</button>
                        <button class="btn btn-success" onclick="featuredJobs()">⭐ Featured Jobs</button>
                    </div>
                </div>
                
                <div class="job-stats-row">
                    <div class="job-stat">
                        <h4>📊 Job Statistics</h4>
                        <div class="stat-items">
                            <div class="stat-item">
                                <span class="label">Total Jobs:</span>
                                <span class="value">--</span>
                            </div>
                            <div class="stat-item">
                                <span class="label">Active Jobs:</span>
                                <span class="value">--</span>
                            </div>
                            <div class="stat-item">
                                <span class="label">Applications:</span>
                                <span class="value">--</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="empty-state">
                    <div class="empty-icon">💼</div>
                    <h3>No Job Data Available</h3>
                    <p>Unable to load job postings. This could be because:</p>
                    <ul style="text-align: left; display: inline-block;">
                        <li>No jobs have been posted yet</li>
                        <li>Database connection issues</li>
                        <li>Permission restrictions</li>
                    </ul>
                    <div class="empty-actions">
                        <button class="btn btn-primary" onclick="window.adminDashboardManager.loadSectionContent('jobs')">
                            Try Again
                        </button>
                        <button class="btn btn-secondary" onclick="createJob()">
                            Create First Job
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    getJobsErrorContent(errorMessage) {
        return `
            <div class="jobs-container">
                <div class="error-state">
                    <div class="error-icon">❌</div>
                    <h3>Error Loading Jobs</h3>
                    <p>There was an error loading the job data:</p>
                    <div class="error-details">
                        <code>${errorMessage}</code>
                    </div>
                    <div class="error-actions">
                        <button class="btn btn-primary" onclick="window.adminDashboardManager.loadSectionContent('jobs')">
                            Retry
                        </button>
                        <button class="btn btn-secondary" onclick="console.log('Jobs Debug info:', {url: '../api/admin/jobs.php', timestamp: new Date()})">
                            Debug Info
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    renderJobs(jobs) {
        if (!jobs || jobs.length === 0) {
            return '<div class="empty-state"><p>No jobs found.</p></div>';
        }
        
        let html = `
            <div class="jobs-header">
                <div class="jobs-actions">
                    <button class="btn btn-primary" onclick="createJob()">➕ Create Job</button>
                    <button class="btn btn-info" onclick="bulkJobActions()">📋 Bulk Actions</button>
                    <button class="btn btn-success" onclick="featuredJobs()">⭐ Featured Jobs</button>
                    <button class="btn btn-warning" onclick="jobAnalytics()">📊 Analytics</button>
                </div>
            </div>
            
            <div class="jobs-filters">
                <input type="text" id="job-search" placeholder="Search jobs by title, company, location...">
                <select id="job-status-filter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="paused">Paused</option>
                    <option value="closed">Closed</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending Approval</option>
                </select>
                <select id="job-type-filter">
                    <option value="">All Types</option>
                    <option value="full_time">Full Time</option>
                    <option value="part_time">Part Time</option>
                    <option value="contract">Contract</option>
                    <option value="internship">Internship</option>
                </select>
                <select id="experience-level-filter">
                    <option value="">All Levels</option>
                    <option value="entry">Entry Level</option>
                    <option value="mid">Mid Level</option>
                    <option value="senior">Senior Level</option>
                </select>
                <button class="btn btn-secondary" onclick="filterJobs()">Filter</button>
                <button class="btn btn-outline-secondary" onclick="resetJobFilters()">Reset</button>
            </div>
            
            <div class="job-stats-row">
                <div class="job-stat">
                    <h4>📊 Performance Overview</h4>
                    <div class="stat-items">
                        <div class="stat-item">
                            <span class="label">Total Views:</span>
                            <span class="value">${jobs.reduce((sum, job) => sum + (job.views_count || 0), 0)}</span>
                        </div>
                        <div class="stat-item">
                            <span class="label">Avg Applications:</span>
                            <span class="value">${(jobs.reduce((sum, job) => sum + (job.application_count || 0), 0) / jobs.length).toFixed(1)}</span>
                        </div>
                        <div class="stat-item">
                            <span class="label">Success Rate:</span>
                            <span class="value">${((jobs.filter(j => (j.application_count || 0) > 0).length / jobs.length) * 100).toFixed(1)}%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="jobs-table-container">
                <table class="jobs-table enhanced">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-jobs" onchange="toggleAllJobs()"></th>
                            <th>Job Details</th>
                            <th>Performance</th>
                            <th>Status & Timeline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        jobs.forEach(job => {
            const applicationRate = job.views_count > 0 ? ((job.application_count || 0) / job.views_count * 100).toFixed(1) : '0';
            const daysActive = Math.floor((new Date() - new Date(job.created_at)) / (1000 * 60 * 60 * 24));
            
            html += `
                <tr data-job-id="${job.id}">
                    <td><input type="checkbox" class="job-checkbox" value="${job.id}"></td>
                    <td>
                        <div class="job-details">
                            <h4 class="job-title">${job.title}</h4>
                            <p class="company-info">
                                <span class="company-name">${job.company_name}</span>
                                ${job.company_industry ? `<span class="industry">(${job.company_industry})</span>` : ''}
                            </p>
                            <div class="job-meta">
                                <span class="location">📍 ${job.location || 'Remote'}</span>
                                <span class="job-type">${job.job_type_display || job.job_type}</span>
                                <span class="experience">${job.experience_level_display || job.experience_level}</span>
                            </div>
                            ${job.salary_display ? `<div class="salary">💰 ${job.salary_display}</div>` : ''}
                        </div>
                    </td>
                    <td>
                        <div class="performance-metrics">
                            <div class="metric">
                                <span class="metric-label">Views:</span>
                                <span class="metric-value">${job.views_count || 0}</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Applications:</span>
                                <span class="metric-value">${job.application_count || 0}</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Rate:</span>
                                <span class="metric-value ${parseFloat(applicationRate) > 5 ? 'good' : 'poor'}">${applicationRate}%</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="status-timeline">
                            <span class="status-badge status-${job.status}">
                                ${job.status.charAt(0).toUpperCase() + job.status.slice(1)}
                            </span>
                            <div class="timeline-info">
                                <small>Created: ${daysActive} days ago</small>
                                ${job.application_deadline ? 
                                    `<small class="deadline ${new Date(job.application_deadline) < new Date() ? 'expired' : 'active'}">
                                        Deadline: ${new Date(job.application_deadline).toLocaleDateString()}
                                    </small>` : 
                                    '<small class="no-deadline">No deadline</small>'
                                }
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="action-buttons-enhanced">
                            <button class="btn btn-sm btn-primary" onclick="viewJobDetails(${job.id})" title="View Details">👁️</button>
                            <button class="btn btn-sm btn-secondary" onclick="editJob(${job.id})" title="Edit Job">✏️</button>
                            <button class="btn btn-sm btn-info" onclick="viewApplications(${job.id})" title="View Applications">📄</button>
                            <button class="btn btn-sm btn-success" onclick="promoteJob(${job.id})" title="Promote Job">📢</button>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" onclick="toggleJobMenu(${job.id})" title="More Options">⋯</button>
                                <div class="dropdown-menu" id="job-menu-${job.id}" style="display: none;">
                                    <a onclick="duplicateJob(${job.id})">Duplicate</a>
                                    <a onclick="pauseJob(${job.id})">Pause</a>
                                    <a onclick="closeJob(${job.id})">Close</a>
                                    <a onclick="deleteJob(${job.id})" class="danger">Delete</a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
            
            <div class="jobs-pagination">
                <div class="pagination-info">
                    Showing ${jobs.length} jobs
                </div>
                <div class="pagination-controls">
                    <button class="btn btn-sm btn-outline-primary" onclick="loadJobsPage(1)">First</button>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadJobsPage('prev')">Previous</button>
                    <span class="page-indicator">Page 1</span>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadJobsPage('next')">Next</button>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadJobsPage(-1)">Last</button>
                </div>
            </div>
        `;
        
        return html;
    }
    
    async loadAssessments() {
        try {
            const response = await fetch('../api/admin/assessments.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            if (data.success) {
                return this.renderAssessments(data.assessments);
            } else {
                return '<div class="empty-state"><p>No assessments found.</p></div>';
            }
        } catch (error) {
            console.error('Error loading assessments:', error);
            return '<div class="error-state"><p>Error loading assessments.</p></div>';
        }
    }
    
    renderAssessments(assessments) {
        if (!assessments || assessments.length === 0) {
            return '<div class="empty-state"><p>No assessments found.</p></div>';
        }
        
        let html = `
            <div class="assessments-header">
                <button class="btn btn-primary" onclick="createAssessment()">
                    <span class="icon">➕</span>
                    Create Assessment
                </button>
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
                        <button class="btn btn-sm btn-secondary" onclick="editAssessment(${assessment.id})">Edit</button>
                        <button class="btn btn-sm btn-primary" onclick="viewAssessmentResults(${assessment.id})">Results</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteAssessment(${assessment.id})">Delete</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    async loadRecommendations() {
        try {
            const response = await fetch('../api/admin/recommendations.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            if (data.success) {
                return this.renderRecommendations(data.recommendations);
            } else {
                return '<div class="empty-state"><p>No recommendations found.</p></div>';
            }
        } catch (error) {
            console.error('Error loading recommendations:', error);
            return '<div class="error-state"><p>Error loading recommendations.</p></div>';
        }
    }
    
    renderRecommendations(recommendations) {
        if (!recommendations || recommendations.length === 0) {
            return '<div class="empty-state"><p>No recommendations found.</p></div>';
        }
        
        let html = `
            <div class="recommendations-stats">
                <div class="stat-item">
                    <h3>${recommendations.length}</h3>
                    <p>Total Recommendations</p>
                </div>
                <div class="stat-item">
                    <h3>${recommendations.filter(r => r.status === 'active').length}</h3>
                    <p>Active Recommendations</p>
                </div>
                <div class="stat-item">
                    <h3>${recommendations.filter(r => r.status === 'applied').length}</h3>
                    <p>Applied Recommendations</p>
                </div>
            </div>
            <div class="recommendations-table-container">
                <table class="recommendations-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Job</th>
                            <th>Company</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        recommendations.forEach(rec => {
            html += `
                <tr>
                    <td>${rec.user_name}</td>
                    <td>${rec.job_title}</td>
                    <td>${rec.company_name}</td>
                    <td>
                        <span class="score-badge">${Math.round(rec.recommendation_score)}%</span>
                    </td>
                    <td>
                        <span class="status-badge status-${rec.status}">
                            ${rec.status}
                        </span>
                    </td>
                    <td>${new Date(rec.created_at).toLocaleDateString()}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        return html;
    }
    
    async loadAnalytics() {
        try {
            const response = await fetch('../api/admin/analytics.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            if (data.success) {
                return this.renderAnalytics(data.analytics);
            } else {
                return '<div class="empty-state"><p>No analytics data available.</p></div>';
            }
        } catch (error) {
            console.error('Error loading analytics:', error);
            return '<div class="error-state"><p>Error loading analytics.</p></div>';
        }
    }
    
    renderAnalytics(analytics) {
        return `
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3>📊 User Growth</h3>
                    <div class="chart-container">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>💼 Job Posting Trends</h3>
                    <div class="chart-container">
                        <canvas id="jobTrendsChart"></canvas>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>📄 Application Trends</h3>
                    <div class="chart-container">
                        <canvas id="applicationTrendsChart"></canvas>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>🎯 Recommendation Performance</h3>
                    <div class="chart-container">
                        <canvas id="recommendationChart"></canvas>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>📋 Assessment Statistics</h3>
                    <div class="assessment-stats">
                        <div class="stat-item">
                            <h4>${analytics.total_assessments || 0}</h4>
                            <p>Total Assessments</p>
                        </div>
                        <div class="stat-item">
                            <h4>${analytics.avg_score || 0}%</h4>
                            <p>Average Score</p>
                        </div>
                        <div class="stat-item">
                            <h4>${analytics.completion_rate || 0}%</h4>
                            <p>Completion Rate</p>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>🏆 Top Industries</h3>
                    <div class="top-industries">
                        ${analytics.top_industries ? analytics.top_industries.map(industry => `
                            <div class="industry-item">
                                <span class="industry-name">${industry.name}</span>
                                <span class="industry-count">${industry.count} jobs</span>
                            </div>
                        `).join('') : '<p>No data available</p>'}
                    </div>
                </div>
            </div>
        `;
    }
    
    async loadSystem() {
        try {
            const response = await fetch('../api/admin/system_health.php', { credentials: 'same-origin' });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Failed to load');
            const health = data.health || {};
            const db = (health.database && health.database.stats) || {};
            const sys = health.system || {};
            const perf = health.performance || {};
            return `
                <div class="system-container">
                    <div class="system-section">
                        <h3>🖥️ System Information</h3>
                        <div class="system-info">
                            <div class="info-item"><span class="info-label">PHP Version:</span><span class="info-value">${sys.php_version || '-'}</span></div>
                            <div class="info-item"><span class="info-label">Server:</span><span class="info-value">${sys.server_software || '-'}</span></div>
                            <div class="info-item"><span class="info-label">Timezone:</span><span class="info-value">${sys.timezone || '-'}</span></div>
                        </div>
                    </div>
                    <div class="system-section">
                        <h3>💾 Database Status</h3>
                        <div class="database-status">
                            <div class="status-item"><span class="status-label">Status:</span><span class="status-value ${health.status === 'healthy' ? 'status-success' : 'status-warning'}">${health.status || 'unknown'}</span></div>
                            <div class="status-item"><span class="status-label">Size:</span><span class="status-value">${(db.size_mb || 0)} MB</span></div>
                            <div class="status-item"><span class="status-label">Tables:</span><span class="status-value">${db.table_count || 0}</span></div>
                        </div>
                    </div>
                    <div class="system-section">
                        <h3>⚡ Performance</h3>
                        <div class="system-info">
                            <div class="info-item"><span class="info-label">Response Time:</span><span class="info-value">${perf.response_time_ms || 0} ms</span></div>
                            <div class="info-item"><span class="info-label">Memory Efficiency:</span><span class="info-value">${perf.memory_efficiency || 0}%</span></div>
                        </div>
                    </div>
                </div>
            `;
        } catch (e) {
            console.error('loadSystem error:', e);
            return '<div class="error-state"><p>Error loading system status. Please try again.</p></div>';
        }
    }
    
    async loadSettings() {
        try {
            const response = await fetch('../api/admin/settings.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            if (data.success) {
                return this.renderSettings(data.settings);
            } else {
                return '<div class="error-state"><p>Error loading settings.</p></div>';
            }
        } catch (error) {
            console.error('Error loading settings:', error);
            return '<div class="error-state"><p>Error loading settings.</p></div>';
        }
    }
    
    renderSettings(settings) {
        return `
            <div class="settings-container">
                <form id="system-settings-form">
                    <div class="settings-section">
                        <h3>General Settings</h3>
                        <div class="form-group">
                            <label>Site Name</label>
                            <input type="text" id="site-name" value="${settings.site_name || ''}">
                        </div>
                        <div class="form-group">
                            <label>Site Description</label>
                            <textarea id="site-description" rows="3">${settings.site_description || ''}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Maintenance Mode</label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="maintenance-mode" ${settings.maintenance_mode === 'true' ? 'checked' : ''}>
                                Enable maintenance mode
                            </label>
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3>Email Settings</h3>
                        <div class="form-group">
                            <label>Email Notifications</label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="email-notifications" ${settings.email_notifications === 'true' ? 'checked' : ''}>
                                Enable email notifications
                            </label>
                        </div>
                        <div class="form-group">
                            <label>SMTP Host</label>
                            <input type="text" id="smtp-host" value="${settings.smtp_host || ''}">
                        </div>
                        <div class="form-group">
                            <label>SMTP Port</label>
                            <input type="number" id="smtp-port" value="${settings.smtp_port || 587}">
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3>AI Settings</h3>
                        <div class="form-group">
                            <label>Recommendation Threshold</label>
                            <input type="number" id="recommendation-threshold" value="${settings.ai_recommendation_threshold || 70}" min="0" max="100">
                        </div>
                        <div class="form-group">
                            <label>Proctoring Enabled</label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="proctoring-enabled" ${settings.proctoring_enabled === 'true' ? 'checked' : ''}>
                                Enable proctoring for assessments
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        `;
    }
    
    // Removed getSystemInfo() PHP-dependent stub; now using API data
    
    async updateSystemHealth() {
        try {
            const response = await fetch('../api/admin/system_health.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            if (data.success) {
                // Update system health indicators
                const statusIndicator = document.querySelector('.status-indicator');
                if (statusIndicator) {
                    statusIndicator.className = `status-indicator ${data.health.status}`;
                }
            }
        } catch (error) {
            console.error('Error updating system health:', error);
        }
    }
    
    showExportOptions() {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
                <h2>Export Data</h2>
                <div class="export-options">
                    <div class="export-option">
                        <h3>Users Data</h3>
                        <p>Export all user information</p>
                        <button class="btn btn-primary" onclick="exportData('users')">Export Users</button>
                    </div>
                    <div class="export-option">
                        <h3>Jobs Data</h3>
                        <p>Export all job postings</p>
                        <button class="btn btn-primary" onclick="exportData('jobs')">Export Jobs</button>
                    </div>
                    <div class="export-option">
                        <h3>Applications Data</h3>
                        <p>Export all job applications</p>
                        <button class="btn btn-primary" onclick="exportData('applications')">Export Applications</button>
                    </div>
                    <div class="export-option">
                        <h3>Assessments Data</h3>
                        <p>Export all assessment results</p>
                        <button class="btn btn-primary" onclick="exportData('assessments')">Export Assessments</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    async suspendUser(userId) {
        if (confirm('Are you sure you want to suspend this user?')) {
            try {
                const response = await fetch('../api/admin/suspend_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ user_id: userId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('User suspended successfully!');
                    this.loadSectionContent('users');
                } else {
                    alert('Error suspending user: ' + data.message);
                }
            } catch (error) {
                console.error('Error suspending user:', error);
                alert('Error suspending user. Please try again.');
            }
        }
    }
    
    async activateUser(userId) {
        try {
            const response = await fetch('../api/admin/activate_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ user_id: userId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('User activated successfully!');
                this.loadSectionContent('users');
            } else {
                alert('Error activating user: ' + data.message);
            }
        } catch (error) {
            console.error('Error activating user:', error);
            alert('Error activating user. Please try again.');
        }
    }
    
    async deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.\n\nThis will also remove all associated data including job applications, assessments, and other related records.')) {
            try {
                const response = await fetch('../api/admin/delete_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ 
                        user_id: userId,
                        confirmation: true 
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('User deleted successfully!' + 
                          (data.dependencies && data.dependencies.length > 0 ? 
                           '\n\nRemoved data: ' + data.dependencies.join(', ') : ''));
                    this.loadSectionContent('users');
                } else {
                    alert('Error deleting user: ' + data.message);
                }
            } catch (error) {
                console.error('Error deleting user:', error);
                alert('Error deleting user. Please try again.');
            }
        }
    }
    
    // New section loaders for additional features
    async loadNotifications() {
        try {
            const response = await fetch('../api/admin/notifications.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            return this.renderNotifications(data.notifications || []);
        } catch (error) {
            console.error('Error loading notifications:', error);
            return '<div class="error-state"><p>Error loading notifications.</p></div>';
        }
    }
    
    async loadSecurity() {
        try {
            const response = await fetch('../api/admin/security.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            return this.renderSecurity(data);
        } catch (error) {
            console.error('Error loading security data:', error);
            return '<div class="error-state"><p>Error loading security data.</p></div>';
        }
    }
    
    async loadReports() {
        try {
            const response = await fetch('../api/admin/reports.php', { credentials: 'same-origin' });
            const data = await response.json();
            
            return this.renderReports(data);
        } catch (error) {
            console.error('Error loading reports:', error);
            return '<div class="error-state"><p>Error loading reports.</p></div>';
        }
    }
    
    async loadMonitoring() {
        return this.renderLiveMonitoring();
    }
    
    renderNotifications(notifications) {
        return `
            <div class="notifications-container">
                <div class="notifications-header">
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="createNotification()">📧 Send Notification</button>
                        <button class="btn btn-secondary" onclick="createEmailCampaign()">📨 Email Campaign</button>
                        <button class="btn btn-info" onclick="viewTemplates()">📝 Templates</button>
                    </div>
                </div>
                
                <div class="notification-stats">
                    <div class="stat-card">
                        <h4>📤 Sent Today</h4>
                        <span class="stat-number">0</span>
                    </div>
                    <div class="stat-card">
                        <h4>📊 Open Rate</h4>
                        <span class="stat-number">0%</span>
                    </div>
                    <div class="stat-card">
                        <h4>🎯 Click Rate</h4>
                        <span class="stat-number">0%</span>
                    </div>
                    <div class="stat-card">
                        <h4>👥 Active Users</h4>
                        <span class="stat-number">0</span>
                    </div>
                </div>
                
                <div class="notifications-tabs">
                    <button class="tab-btn active" onclick="showNotificationTab('recent')">Recent Notifications</button>
                    <button class="tab-btn" onclick="showNotificationTab('campaigns')">Email Campaigns</button>
                    <button class="tab-btn" onclick="showNotificationTab('templates')">Templates</button>
                    <button class="tab-btn" onclick="showNotificationTab('settings')">Settings</button>
                </div>
                
                <div id="notifications-tab-content">
                    <div class="notification-composer">
                        <h3>📝 Quick Notification</h3>
                        <form id="quick-notification-form">
                            <div class="form-group">
                                <label>Recipients</label>
                                <select id="notification-recipients" multiple>
                                    <option value="all_users">All Users</option>
                                    <option value="job_seekers">Job Seekers</option>
                                    <option value="companies">Companies</option>
                                    <option value="active_users">Active Users Only</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" id="notification-title" placeholder="Notification title">
                            </div>
                            <div class="form-group">
                                <label>Message</label>
                                <textarea id="notification-message" rows="4" placeholder="Your message here..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Type</label>
                                <select id="notification-type">
                                    <option value="info">Information</option>
                                    <option value="success">Success</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Notification</button>
                        </form>
                    </div>
                </div>
            </div>
        `;
    }
    
    renderSecurity(data) {
        return `
            <div class="security-container">
                <div class="security-overview">
                    <div class="security-stat">
                        <div class="stat-icon">🛡️</div>
                        <div class="stat-content">
                            <h3>Security Score</h3>
                            <div class="score-display">85%</div>
                            <small>Good Security</small>
                        </div>
                    </div>
                    <div class="security-stat">
                        <div class="stat-icon">🔐</div>
                        <div class="stat-content">
                            <h3>Active Sessions</h3>
                            <div class="score-display">12</div>
                            <small>2 suspicious</small>
                        </div>
                    </div>
                    <div class="security-stat">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-content">
                            <h3>Security Alerts</h3>
                            <div class="score-display">3</div>
                            <small>Today</small>
                        </div>
                    </div>
                    <div class="security-stat">
                        <div class="stat-icon">📊</div>
                        <div class="stat-content">
                            <h3>Failed Logins</h3>
                            <div class="score-display">7</div>
                            <small>Last 24h</small>
                        </div>
                    </div>
                </div>
                
                <div class="security-sections">
                    <div class="security-section">
                        <h3>🔍 Recent Security Events</h3>
                        <div class="events-list">
                            <div class="event-item warning">
                                <span class="event-time">2 mins ago</span>
                                <span class="event-desc">Multiple failed login attempts from IP 192.168.1.100</span>
                                <button class="btn btn-sm btn-danger">Block IP</button>
                            </div>
                            <div class="event-item info">
                                <span class="event-time">15 mins ago</span>
                                <span class="event-desc">Admin login successful from new location</span>
                                <button class="btn btn-sm btn-secondary">Details</button>
                            </div>
                            <div class="event-item success">
                                <span class="event-time">1 hour ago</span>
                                <span class="event-desc">Security scan completed - No threats detected</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="security-section">
                        <h3>👥 Active Sessions</h3>
                        <div class="sessions-table">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>IP Address</th>
                                        <th>Location</th>
                                        <th>Last Activity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>admin@test.com</td>
                                        <td>192.168.1.105</td>
                                        <td>San Francisco, CA</td>
                                        <td>Active now</td>
                                        <td><button class="btn btn-sm btn-danger">Terminate</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="security-section">
                        <h3>🛡️ Security Settings</h3>
                        <div class="security-settings">
                            <div class="setting-item">
                                <label>Two-Factor Authentication</label>
                                <label class="switch">
                                    <input type="checkbox" id="2fa-enabled" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <label>IP Whitelist</label>
                                <button class="btn btn-secondary" onclick="manageIPWhitelist()">Configure</button>
                            </div>
                            <div class="setting-item">
                                <label>Session Timeout (minutes)</label>
                                <input type="number" value="30" min="5" max="480">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    renderReports(data) {
        return `
            <div class="reports-container">
                <div class="reports-header">
                    <h3>📊 Report Generator</h3>
                    <button class="btn btn-primary" onclick="generateCustomReport()">Create Custom Report</button>
                </div>
                
                <div class="report-categories">
                    <div class="report-category">
                        <h4>👥 User Reports</h4>
                        <div class="report-items">
                            <div class="report-item" onclick="generateReport('user-registration')">
                                <span class="report-icon">📈</span>
                                <span class="report-name">User Registration Report</span>
                                <span class="report-desc">New user signups and trends</span>
                            </div>
                            <div class="report-item" onclick="generateReport('user-activity')">
                                <span class="report-icon">🎯</span>
                                <span class="report-name">User Activity Report</span>
                                <span class="report-desc">User engagement and activity levels</span>
                            </div>
                            <div class="report-item" onclick="generateReport('user-retention')">
                                <span class="report-icon">🔄</span>
                                <span class="report-name">User Retention Report</span>
                                <span class="report-desc">User retention and churn analysis</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="report-category">
                        <h4>💼 Job Reports</h4>
                        <div class="report-items">
                            <div class="report-item" onclick="generateReport('job-performance')">
                                <span class="report-icon">📊</span>
                                <span class="report-name">Job Performance Report</span>
                                <span class="report-desc">Job posting success rates and metrics</span>
                            </div>
                            <div class="report-item" onclick="generateReport('application-trends')">
                                <span class="report-icon">📄</span>
                                <span class="report-name">Application Trends</span>
                                <span class="report-desc">Application volumes and conversion rates</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="report-category">
                        <h4>🎯 AI & Matching Reports</h4>
                        <div class="report-items">
                            <div class="report-item" onclick="generateReport('recommendation-performance')">
                                <span class="report-icon">🤖</span>
                                <span class="report-name">AI Recommendation Performance</span>
                                <span class="report-desc">AI matching accuracy and success rates</span>
                            </div>
                            <div class="report-item" onclick="generateReport('assessment-analytics')">
                                <span class="report-icon">📋</span>
                                <span class="report-name">Assessment Analytics</span>
                                <span class="report-desc">Assessment completion and performance data</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="scheduled-reports">
                    <h3>⏰ Scheduled Reports</h3>
                    <div class="scheduled-list">
                        <div class="scheduled-item">
                            <span class="report-name">Weekly User Growth Report</span>
                            <span class="schedule-info">Every Monday at 9:00 AM</span>
                            <div class="schedule-actions">
                                <button class="btn btn-sm btn-secondary">Edit</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-outline-primary" onclick="scheduleReport()">+ Schedule New Report</button>
                </div>
                
                <div class="recent-reports">
                    <h3>📑 Recent Reports</h3>
                    <div class="reports-table">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Report Name</th>
                                    <th>Generated</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>User Registration Report</td>
                                    <td>2 hours ago</td>
                                    <td>PDF</td>
                                    <td>2.4 MB</td>
                                    <td>
                                        <button class="btn btn-sm btn-primary">Download</button>
                                        <button class="btn btn-sm btn-secondary">View</button>
                                        <button class="btn btn-sm btn-danger">Delete</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }
    
    initializeSection(sectionName) {
        console.log('Initializing admin section:', sectionName);
        
        switch (sectionName) {
            case 'users':
                this.initializeUsersSection();
                break;
            case 'jobs':
                this.initializeJobsSection();
                break;
            case 'assessments':
                this.initializeAssessmentsSection();
                break;
            case 'notifications':
                this.initializeNotificationsSection();
                break;
            case 'security':
                this.initializeSecuritySection();
                break;
            case 'reports':
                this.initializeReportsSection();
                break;
            case 'monitoring':
                this.initializeMonitoringSection();
                break;
            default:
                console.log('No specific initialization for section:', sectionName);
        }
    }
    
    initializeUsersSection() {
        console.log('Initializing users section functionality');
        // Add user filtering and search functionality
    }
    
    initializeJobsSection() {
        console.log('Initializing jobs section functionality');
        // Add job filtering and bulk operations
    }
    
    initializeAssessmentsSection() {
        console.log('Initializing assessments section functionality');
        // Add assessment management functionality
    }
    
    initializeNotificationsSection() {
        console.log('Initializing notifications section functionality');
        // Add notification form handlers
    }
    
    initializeSecuritySection() {
        console.log('Initializing security section functionality');
        // Add security monitoring functionality
    }
    
    initializeReportsSection() {
        console.log('Initializing reports section functionality');
        // Add report generation functionality
    }
    
    initializeMonitoringSection() {
        console.log('Initializing monitoring section functionality');
        // Set up real-time monitoring
        this.startRealtimeMonitoring();
    }
    
    startRealtimeMonitoring() {
        // Start real-time monitoring updates
        if (this.monitoringInterval) {
            clearInterval(this.monitoringInterval);
        }
        
        this.monitoringInterval = setInterval(() => {
            this.updateMonitoringData();
        }, 10000); // Update every 10 seconds
    }
    
    async updateMonitoringData() {
        try {
            // Update performance metrics
            const metrics = await fetch('../api/admin/system_health.php');
            const data = await metrics.json();
            
            if (data.success) {
                this.updateMetricsDisplay(data.metrics);
            }
        } catch (error) {
            console.error('Error updating monitoring data:', error);
        }
    }
    
    updateMetricsDisplay(metrics) {
        // Update the monitoring display with new data
        const elements = {
            'active-users-count': metrics.active_users || 0,
            'requests-per-sec': metrics.requests_per_sec || 0,
            'avg-response-time': metrics.avg_response_time || 0,
            'memory-usage': metrics.memory_usage || 0,
            'cpu-usage': metrics.cpu_usage || 0,
            'disk-usage': metrics.disk_usage || 0
        };
        
        Object.keys(elements).forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = elements[id] + (id.includes('usage') ? '%' : '');
            }
        });
    }
    
    renderLiveMonitoring() {
        return `
            <div class="monitoring-container">
                <div class="monitoring-header">
                    <h3>📡 Live System Monitor</h3>
                    <div class="refresh-controls">
                        <label>Auto-refresh: 
                            <select id="refresh-interval" onchange="setRefreshInterval(this.value)">
                                <option value="5">5 seconds</option>
                                <option value="10" selected>10 seconds</option>
                                <option value="30">30 seconds</option>
                                <option value="60">1 minute</option>
                                <option value="0">Off</option>
                            </select>
                        </label>
                        <span id="last-update">Last updated: --</span>
                    </div>
                </div>
                
                <div class="system-status-grid">
                    <div class="status-card online">
                        <div class="status-indicator"></div>
                        <h4>Database</h4>
                        <span class="status-text">Online</span>
                        <small>Response: <span id="db-response-time">--ms</span></small>
                    </div>
                    <div class="status-card online">
                        <div class="status-indicator"></div>
                        <h4>Web Server</h4>
                        <span class="status-text">Online</span>
                        <small>Load: <span id="server-load">--</span></small>
                    </div>
                    <div class="status-card online">
                        <div class="status-indicator"></div>
                        <h4>Email Service</h4>
                        <span class="status-text">Online</span>
                        <small>Queue: <span id="email-queue">0</span> pending</small>
                    </div>
                    <div class="status-card warning">
                        <div class="status-indicator"></div>
                        <h4>Storage</h4>
                        <span class="status-text">Warning</span>
                        <small>78% used</small>
                    </div>
                </div>
                
                <div class="monitoring-charts">
                    <div class="chart-container">
                        <h4>🔄 Real-time Activity</h4>
                        <canvas id="realTimeActivityChart" width="400" height="200"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4>💾 System Resources</h4>
                        <canvas id="systemResourcesChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <div class="live-logs">
                    <div class="logs-header">
                        <h4>📜 Live System Logs</h4>
                        <div class="log-controls">
                            <select id="log-level">
                                <option value="all">All Levels</option>
                                <option value="error">Errors Only</option>
                                <option value="warning">Warnings Only</option>
                                <option value="info">Info Only</option>
                            </select>
                            <button class="btn btn-sm btn-secondary" onclick="clearLogs()">Clear</button>
                            <button class="btn btn-sm btn-primary" onclick="exportLogs()">Export</button>
                        </div>
                    </div>
                    <div class="logs-container" id="live-logs">
                        <div class="log-entry info">
                            <span class="log-time">${new Date().toLocaleTimeString()}</span>
                            <span class="log-level">INFO</span>
                            <span class="log-message">System monitoring started</span>
                        </div>
                    </div>
                </div>
                
                <div class="performance-metrics">
                    <h4>⚡ Performance Metrics</h4>
                    <div class="metrics-grid">
                        <div class="metric-item">
                            <span class="metric-label">Active Users</span>
                            <span class="metric-value" id="active-users-count">--</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Requests/sec</span>
                            <span class="metric-value" id="requests-per-sec">--</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Avg Response Time</span>
                            <span class="metric-value" id="avg-response-time">--ms</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Memory Usage</span>
                            <span class="metric-value" id="memory-usage">--%</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">CPU Usage</span>
                            <span class="metric-value" id="cpu-usage">--%</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Disk Usage</span>
                            <span class="metric-value" id="disk-usage">--%</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
}

// Global functions for admin dashboard
function exportData(type) {
    // Implement data export
    console.log('Exporting data:', type);
    window.open(`../api/admin/export.php?type=${type}`, '_blank');
}

function filterUsers() {
    const search = document.getElementById('user-search')?.value || '';
    const type = document.getElementById('user-type-filter')?.value || '';
    const status = document.getElementById('user-status-filter')?.value || '';
    const verified = document.getElementById('user-verified-filter')?.value || '';
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (type) params.set('user_type', type);
    if (status) params.set('status', status);
    if (verified !== '') params.set('email_verified', verified);
    const url = `../api/admin/users.php?${params.toString()}`;
    fetch(url, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const html = window.adminDashboardManager.renderUsers(data.users);
                document.getElementById('users-content').innerHTML = html;
            }
        });
}

function filterJobs() {
    // Implement job filtering
    console.log('Filtering jobs');
}

function viewUser(userId) {
    // Implement view user details
    console.log('View user:', userId);
}

function suspendUser(userId) {
    if (window.adminDashboardManager) {
        window.adminDashboardManager.suspendUser(userId);
    }
}

function activateUser(userId) {
    if (window.adminDashboardManager) {
        window.adminDashboardManager.activateUser(userId);
    }
}

function deleteUser(userId) {
    if (window.adminDashboardManager) {
        window.adminDashboardManager.deleteUser(userId);
    }
}

async function assignAssessment(userId) {
    const id = prompt('Enter assessment ID to assign to this user:');
    const assessmentId = parseInt(id, 10);
    if (!assessmentId) return;
    try {
        const res = await fetch('../api/admin/assign_assessment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ user_id: userId, assessment_id: assessmentId })
        });
        const data = await res.json();
        if (data.success) {
            alert('Assessment assigned');
        } else {
            alert(data.message || 'Failed to assign');
        }
    } catch (e) {
        console.error(e);
        alert('Error assigning assessment');
    }
}

function toggleAllUsers() {
    const master = document.getElementById('select-all-users');
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = master.checked);
}

async function bulkVerify() {
    const ids = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => parseInt(cb.value, 10));
    if (ids.length === 0) { alert('Select at least one user'); return; }
    if (!confirm(`Verify ${ids.length} user(s)?`)) return;
    try {
        const res = await fetch('../api/admin/bulk_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ user_ids: ids })
        });
        const data = await res.json();
        if (data.success) {
            alert('Selected users verified');
            window.adminDashboardManager.loadSectionContent('users');
        } else {
            alert(data.message || 'Failed to verify');
        }
    } catch (e) {
        console.error(e);
        alert('Error performing bulk verify');
    }
}

async function verifyUser(userId) {
    if (!confirm('Mark this user as email verified?')) return;
    try {
        const res = await fetch('../api/admin/verify_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ user_id: userId })
        });
        const data = await res.json();
        if (data.success) {
            alert('User verified');
            if (window.adminDashboardManager) window.adminDashboardManager.loadSectionContent('users');
        } else {
            alert(data.message || 'Failed to verify');
        }
    } catch (e) {
        console.error(e);
        alert('Error verifying user');
    }
}

function viewJob(jobId) {
    // Implement view job details
    console.log('View job:', jobId);
}

function editJob(jobId) {
    // Implement edit job
    console.log('Edit job:', jobId);
}

function deleteJob(jobId) {
    if (confirm('Are you sure you want to delete this job?')) {
        // Implement delete job
        console.log('Delete job:', jobId);
    }
}

function createAssessment() {
    // Implement create assessment
    console.log('Create assessment');
}

function editAssessment(assessmentId) {
    // Implement edit assessment
    console.log('Edit assessment:', assessmentId);
}

function deleteAssessment(assessmentId) {
    if (confirm('Are you sure you want to delete this assessment?')) {
        // Implement delete assessment
        console.log('Delete assessment:', assessmentId);
    }
}

function viewAssessmentResults(assessmentId) {
    // Implement view assessment results
    console.log('View assessment results:', assessmentId);
}

function clearCache() {
    if (confirm('Are you sure you want to clear the cache?')) {
        // Implement clear cache
        console.log('Clear cache');
    }
}

function optimizeDatabase() {
    if (confirm('Are you sure you want to optimize the database?')) {
        // Implement optimize database
        console.log('Optimize database');
    }
}

function backupDatabase() {
    if (confirm('Are you sure you want to backup the database?')) {
        // Implement backup database
        console.log('Backup database');
    }
}

// Global function override for admin dashboard
function showSection(sectionName) {
    console.log('Global showSection called with:', sectionName);
    if (window.adminDashboardManager) {
        console.log('Using AdminDashboardManager');
        window.adminDashboardManager.showSection(sectionName);
    } else if (window.dashboardManager) {
        console.log('Using generic DashboardManager');
        window.dashboardManager.showSection(sectionName);
    } else {
        console.error('No dashboard manager available');
        alert('Dashboard manager not initialized. Please refresh the page.');
    }
}

// Initialize admin dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing AdminDashboardManager');
    try {
        window.adminDashboardManager = new AdminDashboardManager();
        window.dashboardManager = window.adminDashboardManager; // For compatibility
        
        // Verify initialization
        console.log('AdminDashboardManager initialized:', !!window.adminDashboardManager);
        
        // Make showSection globally available
        window.showSection = function(sectionName) {
            if (window.dashboardManager) {
                window.dashboardManager.showSection(sectionName);
            } else {
                console.error('Dashboard manager not initialized. Please refresh the page.');
                alert('Dashboard manager not initialized. Please refresh the page.');
            }
        };
    } catch (error) {
        console.error('Failed to initialize AdminDashboardManager:', error);
    }
});

// Account: update admin password
async function updateAdminPassword(event) {
    event.preventDefault();
    const current = document.getElementById('current-password').value.trim();
    const next = document.getElementById('new-password').value.trim();
    const confirm = document.getElementById('confirm-password').value.trim();
    if (!current || !next || !confirm) { alert('Please fill all fields'); return false; }
    if (next !== confirm) { alert('Passwords do not match'); return false; }
    try {
        const res = await fetch('../api/admin/account.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'change_password', current_password: current, new_password: next })
        });
        const data = await res.json();
        if (data.success) {
            alert('Password updated successfully');
            document.getElementById('admin-password-form').reset();
        } else {
            alert(data.message || 'Failed to update password');
        }
    } catch (e) {
        console.error(e);
        alert('Error updating password');
    }
    return false;
}
