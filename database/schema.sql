-- AI Job Recommendation System Database Schema
-- Created for MySQL/MariaDB

-- Create database
CREATE DATABASE IF NOT EXISTS ai_job_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai_job_system;

-- Users table (for all user types)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255),
    user_type ENUM('job_seeker', 'company', 'admin') NOT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    otp VARCHAR(6),
    otp_expires_at TIMESTAMP NULL,
    profile_image VARCHAR(255),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_activity TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user_type (user_type),
    INDEX idx_status (status)
);

-- Job seekers profile
CREATE TABLE job_seekers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say'),
    location VARCHAR(255),
    current_salary DECIMAL(10,2),
    expected_salary DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    availability ENUM('immediate', '2_weeks', '1_month', '2_months', '3_months', 'negotiable'),
    work_preference ENUM('remote', 'onsite', 'hybrid'),
    resume_file VARCHAR(255),
    resume_text TEXT,
    skills JSON,
    experience_years INT DEFAULT 0,
    education_level ENUM('high_school', 'diploma', 'bachelor', 'master', 'phd', 'other'),
    linkedin_url VARCHAR(255),
    portfolio_url VARCHAR(255),
    bio TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_location (location),
    INDEX idx_skills (skills)
);

-- Companies profile
CREATE TABLE companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    industry VARCHAR(255),
    company_size ENUM('1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'),
    website VARCHAR(255),
    description TEXT,
    logo VARCHAR(255),
    founded_year YEAR,
    headquarters VARCHAR(255),
    company_type ENUM('startup', 'small_business', 'medium_business', 'enterprise', 'non_profit'),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_company_name (company_name),
    INDEX idx_industry (industry)
);

-- Job categories
CREATE TABLE job_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES job_categories(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_parent_id (parent_id)
);

-- Job postings
CREATE TABLE jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT,
    responsibilities TEXT,
    benefits TEXT,
    category_id INT,
    job_type ENUM('full_time', 'part_time', 'contract', 'internship', 'freelance') NOT NULL,
    experience_level ENUM('entry', 'mid', 'senior', 'executive') NOT NULL,
    salary_min DECIMAL(10,2),
    salary_max DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    location VARCHAR(255),
    work_type ENUM('remote', 'onsite', 'hybrid') NOT NULL,
    status ENUM('active', 'paused', 'closed', 'draft') DEFAULT 'draft',
    application_deadline DATE,
    total_applications INT DEFAULT 0,
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES job_categories(id) ON DELETE SET NULL,
    INDEX idx_company_id (company_id),
    INDEX idx_category_id (category_id),
    INDEX idx_job_type (job_type),
    INDEX idx_experience_level (experience_level),
    INDEX idx_status (status),
    INDEX idx_location (location),
    FULLTEXT idx_search (title, description, requirements)
);

-- Job applications
CREATE TABLE job_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    job_seeker_id INT NOT NULL,
    status ENUM('applied', 'screening', 'interview', 'offered', 'rejected', 'withdrawn') DEFAULT 'applied',
    cover_letter TEXT,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    screening_score DECIMAL(5,2),
    interview_score DECIMAL(5,2),
    notes TEXT,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (job_seeker_id) REFERENCES job_seekers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (job_id, job_seeker_id),
    INDEX idx_job_id (job_id),
    INDEX idx_job_seeker_id (job_seeker_id),
    INDEX idx_status (status)
);

-- Assessment categories
CREATE TABLE assessment_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    skill_type ENUM('technical', 'soft_skill', 'aptitude', 'domain_specific') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_skill_type (skill_type)
);

-- Assessment questions
CREATE TABLE assessment_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'coding', 'essay', 'practical') NOT NULL,
    options JSON,
    correct_answer TEXT,
    explanation TEXT,
    difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    time_limit INT DEFAULT 60, -- in seconds
    points INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES assessment_categories(id) ON DELETE CASCADE,
    INDEX idx_category_id (category_id),
    INDEX idx_question_type (question_type),
    INDEX idx_difficulty_level (difficulty_level)
);

-- Assessment tests
CREATE TABLE assessments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    job_id INT NULL, -- If assessment is specific to a job
    total_questions INT DEFAULT 0,
    time_limit INT DEFAULT 3600, -- in seconds
    passing_score DECIMAL(5,2) DEFAULT 60.00,
    is_proctored BOOLEAN DEFAULT TRUE,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES assessment_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_category_id (category_id),
    INDEX idx_job_id (job_id),
    INDEX idx_created_by (created_by),
    INDEX idx_status (status)
);

-- Assessment questions mapping
CREATE TABLE assessment_questions_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assessment_id INT NOT NULL,
    question_id INT NOT NULL,
    order_index INT DEFAULT 0,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES assessment_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_mapping (assessment_id, question_id),
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_question_id (question_id),
    INDEX idx_order_index (order_index)
);

-- User assessment attempts
CREATE TABLE user_assessments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    assessment_id INT NOT NULL,
    job_application_id INT NULL, -- If assessment is part of job application
    status ENUM('not_started', 'in_progress', 'completed', 'abandoned', 'flagged') DEFAULT 'not_started',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    total_score DECIMAL(5,2) DEFAULT 0.00,
    percentage_score DECIMAL(5,2) DEFAULT 0.00,
    time_taken INT DEFAULT 0, -- in seconds
    proctoring_data JSON, -- Store proctoring analysis data
    cheating_flags JSON, -- Store any cheating detection flags
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (job_application_id) REFERENCES job_applications(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_job_application_id (job_application_id),
    INDEX idx_status (status)
);

-- User assessment answers
CREATE TABLE user_assessment_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_assessment_id INT NOT NULL,
    question_id INT NOT NULL,
    answer TEXT,
    is_correct BOOLEAN DEFAULT FALSE,
    time_taken INT DEFAULT 0, -- in seconds
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_assessment_id) REFERENCES user_assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES assessment_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_answer (user_assessment_id, question_id),
    INDEX idx_user_assessment_id (user_assessment_id),
    INDEX idx_question_id (question_id)
);

-- AI recommendations
CREATE TABLE ai_recommendations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    recommendation_score DECIMAL(5,2) NOT NULL,
    match_reasons JSON, -- Reasons for the recommendation
    skill_match_percentage DECIMAL(5,2),
    experience_match_percentage DECIMAL(5,2),
    location_match BOOLEAN DEFAULT FALSE,
    salary_match BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'viewed', 'applied', 'dismissed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_job_id (job_id),
    INDEX idx_recommendation_score (recommendation_score),
    INDEX idx_status (status)
);

-- Activity logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'job_recommendation', 'assessment_reminder') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    data JSON, -- Additional data for the notification
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Chat messages (for AI chatbot)
CREATE TABLE chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_user_message BOOLEAN NOT NULL,
    message_type ENUM('text', 'image', 'file') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at)
);

-- Insert default data
INSERT INTO job_categories (name, description) VALUES
('Technology', 'Software development, IT, and technology-related positions'),
('Healthcare', 'Medical, nursing, and healthcare services'),
('Finance', 'Banking, accounting, and financial services'),
('Education', 'Teaching, training, and educational services'),
('Marketing', 'Digital marketing, advertising, and communications'),
('Sales', 'Sales, business development, and customer relations'),
('Human Resources', 'HR, recruitment, and people management'),
('Operations', 'Operations, logistics, and supply chain'),
('Design', 'Graphic design, UI/UX, and creative services'),
('Engineering', 'Mechanical, electrical, civil, and other engineering roles');

INSERT INTO assessment_categories (name, description, skill_type) VALUES
('Programming', 'Software development and coding skills', 'technical'),
('Data Analysis', 'Data science, analytics, and statistics', 'technical'),
('Project Management', 'Project planning and management skills', 'soft_skill'),
('Communication', 'Verbal and written communication skills', 'soft_skill'),
('Problem Solving', 'Analytical thinking and problem-solving', 'aptitude'),
('Leadership', 'Team leadership and management skills', 'soft_skill'),
('Database Management', 'Database design and administration', 'technical'),
('Web Development', 'Frontend and backend web development', 'technical'),
('Machine Learning', 'AI and machine learning concepts', 'technical'),
('Business Analysis', 'Business process analysis and improvement', 'domain_specific');

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'AI Job Recommendation System', 'Name of the application'),
('site_description', 'Intelligent job matching with AI-powered assessments', 'Site description'),
('otp_expiry_minutes', '10', 'OTP expiry time in minutes'),
('max_file_size', '5242880', 'Maximum file upload size in bytes (5MB)'),
('assessment_time_limit', '3600', 'Default assessment time limit in seconds'),
('proctoring_enabled', 'true', 'Enable proctoring for assessments'),
('ai_recommendation_threshold', '70', 'Minimum score for AI recommendations'),
('email_notifications', 'true', 'Enable email notifications'),
('push_notifications', 'true', 'Enable push notifications'),
('maintenance_mode', 'false', 'Enable maintenance mode');

-- Create admin user (password: password)
INSERT INTO users (name, email, password_hash, user_type, email_verified, status) VALUES
('System Administrator', 'admin@aijobsystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE, 'active');

-- Create indexes for better performance
CREATE INDEX idx_users_search ON users(name, email);
CREATE INDEX idx_companies_search ON companies(company_name, industry);
CREATE INDEX idx_job_seekers_location ON job_seekers(location);

-- Create views for common queries
CREATE VIEW active_jobs AS
SELECT j.*, c.company_name, c.industry, cat.name as category_name
FROM jobs j
JOIN companies c ON j.company_id = c.id
LEFT JOIN job_categories cat ON j.category_id = cat.id
WHERE j.status = 'active';

CREATE VIEW user_recommendations AS
SELECT ar.*, j.title as job_title, j.description as job_description, 
       c.company_name, ar.recommendation_score
FROM ai_recommendations ar
JOIN jobs j ON ar.job_id = j.id
JOIN companies c ON j.company_id = c.id
WHERE ar.status = 'active';

CREATE VIEW assessment_summary AS
SELECT ua.*, a.title as assessment_title, u.name as user_name,
       ua.percentage_score, ua.time_taken
FROM user_assessments ua
JOIN assessments a ON ua.assessment_id = a.id
JOIN users u ON ua.user_id = u.id;

CREATE OR REPLACE VIEW user_recommendations AS
SELECT
    ar.*,
    j.title AS job_title,
    j.description AS job_description,
    c.company_name
FROM ai_recommendations ar
JOIN jobs j ON ar.job_id = j.id
JOIN companies c ON j.company_id = c.id
WHERE ar.status = 'active';

-- =============================================================
-- Assignments: table used by api/jobs/assignment.php
-- =============================================================
CREATE TABLE IF NOT EXISTS job_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    due_date DATE NOT NULL,
    created_by INT NOT NULL,
    assigned_to INT NULL,
    status ENUM('pending','submitted','approved','rejected') DEFAULT 'pending',
    submission TEXT NULL,
    submission_date DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_job_assignments_job
        FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_job_assignments_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_job_assignments_assigned_to
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Helpful indexes
CREATE INDEX IF NOT EXISTS idx_job_assignments_job_id ON job_assignments(job_id);
CREATE INDEX IF NOT EXISTS idx_job_assignments_assigned_to ON job_assignments(assigned_to);
CREATE INDEX IF NOT EXISTS idx_job_assignments_created_by ON job_assignments(created_by);
CREATE INDEX IF NOT EXISTS idx_job_assignments_status ON job_assignments(status);

-- =============================================================
-- Assignment Questions: table for storing questions for assignments
-- =============================================================
CREATE TABLE IF NOT EXISTS assignment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text', 'multiple_choice', 'file_upload') DEFAULT 'text',
    options JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignment_questions_assignment
        FOREIGN KEY (assignment_id) REFERENCES job_assignments(id) ON DELETE CASCADE
);

-- Helpful indexes
CREATE INDEX IF NOT EXISTS idx_assignment_questions_assignment_id ON assignment_questions(assignment_id);