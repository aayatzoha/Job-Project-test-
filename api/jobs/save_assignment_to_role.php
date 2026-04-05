<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    requireLogin();
    
    if ($_SESSION['user_type'] !== 'company') {
        throw new Exception('Only companies can save assignments to job roles');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $assignment_id = isset($input['assignment_id']) ? (int)$input['assignment_id'] : 0;
    
    if ($assignment_id <= 0) {
        throw new Exception('Invalid assignment ID');
    }
    
    $user_id = $_SESSION['user_id'];
    $db = new Database();
    
    // Get assignment and job details
    $assignment = $db->fetch(
        "SELECT a.*, j.id as job_id, j.title as job_title, j.company_id
         FROM assignments a
         JOIN jobs j ON a.job_id = j.id
         WHERE a.id = ?",
        [$assignment_id]
    );
    
    if (!$assignment) {
        throw new Exception('Assignment not found');
    }
    
    // Verify ownership
    $company = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id]);
    if (!$company || (int)$company['id'] !== (int)$assignment['company_id']) {
        throw new Exception('You do not have permission to save this assignment');
    }
    
    // Create tables if they don't exist
    // First check if tables exist, if not create them without foreign keys first, then add foreign keys
    try {
        // Check if assignment_templates exists
        $tableExists = $db->fetch("SHOW TABLES LIKE 'assignment_templates'");
        if (!$tableExists) {
            // Create without foreign keys first to avoid dependency issues
            $db->execute("
                CREATE TABLE assignment_templates (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    job_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    questions_data JSON,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_job_id (job_id),
                    INDEX idx_created_by (created_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Add foreign keys separately
            try {
                $db->execute("ALTER TABLE assignment_templates ADD FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE");
            } catch (Exception $fkErr) {
                error_log("FK job_id note: " . $fkErr->getMessage());
            }
            
            try {
                $db->execute("ALTER TABLE assignment_templates ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE");
            } catch (Exception $fkErr) {
                error_log("FK created_by note: " . $fkErr->getMessage());
            }
        }
        
        // Check if assignment_template_questions exists
        $tableExists2 = $db->fetch("SHOW TABLES LIKE 'assignment_template_questions'");
        if (!$tableExists2) {
            $db->execute("
                CREATE TABLE assignment_template_questions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    template_id INT NOT NULL,
                    question_text TEXT NOT NULL,
                    question_type ENUM('text', 'file_upload', 'multiple_choice') DEFAULT 'text',
                    options JSON NULL,
                    order_index INT DEFAULT 0,
                    INDEX idx_template_id (template_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Add foreign key separately
            try {
                $db->execute("ALTER TABLE assignment_template_questions ADD FOREIGN KEY (template_id) REFERENCES assignment_templates(id) ON DELETE CASCADE");
            } catch (Exception $fkErr) {
                error_log("FK template_id note: " . $fkErr->getMessage());
            }
        }
    } catch (Exception $e) {
        // Log but continue - tables might already exist or have different structure
        error_log("Table creation note: " . $e->getMessage());
    }
    
    // Get assignment questions
    $questions = $db->fetchAll(
        "SELECT question_text, question_type, options, order_index 
         FROM assignment_questions 
         WHERE assignment_id = ? 
         ORDER BY order_index",
        [$assignment_id]
    );
    
    if (empty($questions)) {
        throw new Exception('Cannot save assignment template: No questions found. Please add questions to the assignment first. You can use the "Generate Assignment" button to auto-create questions based on the job requirements.');
    }
    
    // Check if assignment template already exists for this job
    $existing = null;
    try {
        $existing = $db->fetch(
            "SELECT id FROM assignment_templates 
             WHERE job_id = ? AND title = ?",
            [$assignment['job_id'], $assignment['title']]
        );
    } catch (Exception $e) {
        // Table might not exist yet, will be created above
        error_log("Template check note: " . $e->getMessage());
    }
    
    $db->beginTransaction();
    
    try {
        if ($existing) {
            // Update existing template
            $template_id = $existing['id'];
            $db->execute(
                "UPDATE assignment_templates 
                 SET description = ?, questions_data = ?, updated_at = NOW()
                 WHERE id = ?",
                [
                    $assignment['description'],
                    json_encode($questions),
                    $template_id
                ]
            );
            
            // Delete old template questions
            $db->execute("DELETE FROM assignment_template_questions WHERE template_id = ?", [$template_id]);
        } else {
            // Create new template
            $db->execute(
                "INSERT INTO assignment_templates (job_id, title, description, questions_data, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    $assignment['job_id'],
                    $assignment['title'],
                    $assignment['description'],
                    json_encode($questions),
                    $user_id
                ]
            );
            $template_id = $db->lastInsertId();
        }
        
        // Save template questions
        foreach ($questions as $q) {
            // Handle null options
            $optionsValue = null;
            if (!empty($q['options'])) {
                if (is_string($q['options'])) {
                    $optionsValue = $q['options'];
                } else {
                    $optionsValue = json_encode($q['options']);
                }
            }
            
            $db->execute(
                "INSERT INTO assignment_template_questions (template_id, question_text, question_type, options, order_index)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $template_id,
                    $q['question_text'] ?? '',
                    $q['question_type'] ?? 'text',
                    $optionsValue,
                    $q['order_index'] ?? 0
                ]
            );
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Assignment saved to job role successfully',
            'template_id' => $template_id,
            'job_title' => $assignment['job_title']
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Save assignment template error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        throw new Exception('Failed to save assignment template: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    http_response_code(400);
    error_log('Save assignment API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Save assignment fatal error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_details' => $e->getMessage()
    ]);
}
?>

