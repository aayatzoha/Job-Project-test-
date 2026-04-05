-- Migration: Create assignment templates tables for saving assignments to job roles
-- This allows companies to save assignment templates and reuse them for similar job roles

CREATE TABLE IF NOT EXISTS assignment_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    questions_data JSON,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_job_id (job_id),
    INDEX idx_created_by (created_by)
);

CREATE TABLE IF NOT EXISTS assignment_template_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text', 'file_upload', 'multiple_choice') DEFAULT 'text',
    options JSON NULL,
    order_index INT DEFAULT 0,
    FOREIGN KEY (template_id) REFERENCES assignment_templates(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id)
);

