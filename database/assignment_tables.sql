-- Assignment tables for AI Job System
USE ai_job_system;

-- Assignments table
CREATE TABLE IF NOT EXISTS assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE,
    created_by INT NOT NULL,
    assigned_to INT NULL,
    status ENUM('pending', 'submitted', 'reviewed', 'completed') DEFAULT 'pending',
    requires_camera BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_job_id (job_id),
    INDEX idx_created_by (created_by),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_status (status)
);

-- Assignment questions table
CREATE TABLE IF NOT EXISTS assignment_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text', 'file_upload', 'multiple_choice') NOT NULL,
    order_index INT DEFAULT 0,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    INDEX idx_assignment_id (assignment_id)
);

-- Assignment question options table
CREATE TABLE IF NOT EXISTS assignment_question_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    order_index INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES assignment_questions(id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id)
);

-- Assignment submissions table
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    user_id INT NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted', 'reviewed', 'rejected') DEFAULT 'submitted',
    feedback TEXT,
    score DECIMAL(5,2),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission (assignment_id, user_id),
    INDEX idx_assignment_id (assignment_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Assignment submission answers table
CREATE TABLE IF NOT EXISTS assignment_submission_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT,
    file_path VARCHAR(255),
    FOREIGN KEY (submission_id) REFERENCES assignment_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES assignment_questions(id) ON DELETE CASCADE,
    INDEX idx_submission_id (submission_id),
    INDEX idx_question_id (question_id)
);

-- Assignment recording videos table
CREATE TABLE IF NOT EXISTS assignment_recordings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    video_path VARCHAR(255) NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration INT DEFAULT 0,
    status ENUM('recording', 'completed', 'failed') DEFAULT 'recording',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES assignment_submissions(id) ON DELETE CASCADE,
    INDEX idx_submission_id (submission_id)
);