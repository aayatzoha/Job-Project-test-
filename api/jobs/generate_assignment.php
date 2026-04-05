<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    requireLogin();
    
    if ($_SESSION['user_type'] !== 'company') {
        throw new Exception('Only companies can generate assignments');
    }
    
    $assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
    if ($assignment_id <= 0) {
        throw new Exception('Invalid assignment ID');
    }
    
    $user_id = $_SESSION['user_id'];
    $db = new Database();
    
    // Get assignment and job details
    $assignment = $db->fetch(
        "SELECT a.*, j.title as job_title, j.description as job_description, 
                j.requirements, j.responsibilities, j.experience_level, j.job_type, j.company_id
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
        throw new Exception('You do not have permission to generate questions for this assignment');
    }
    
    // Generate questions based on job requirements
    $questions = [];
    
    // Question 1: Technical skills assessment
    if (!empty($assignment['requirements'])) {
        $questions[] = [
            'question_text' => 'Based on the job requirements, describe your relevant technical experience and how it aligns with this role.',
            'question_type' => 'text',
            'order_index' => 1
        ];
    }
    
    // Question 2: Problem-solving
    $questions[] = [
        'question_text' => 'Describe a challenging problem you solved in a previous role. What was your approach and what was the outcome?',
        'question_type' => 'text',
        'order_index' => 2
    ];
    
    // Question 3: Role-specific question
    if (!empty($assignment['job_title'])) {
        $jobTitle = $assignment['job_title'];
        $questions[] = [
            'question_text' => "What interests you most about the {$jobTitle} position, and how do you see yourself contributing to our team?",
            'question_type' => 'text',
            'order_index' => 3
        ];
    }
    
    // Question 4: Experience level question
    if (!empty($assignment['experience_level'])) {
        $expLevel = ucfirst($assignment['experience_level']);
        $questions[] = [
            'question_text' => "As a {$expLevel} level professional, what key achievements or projects best demonstrate your capabilities?",
            'question_type' => 'text',
            'order_index' => 4
        ];
    }
    
    // Question 5: File upload for portfolio/resume
    $questions[] = [
        'question_text' => 'Please upload any relevant portfolio pieces, code samples, or additional documentation that showcases your work.',
        'question_type' => 'file_upload',
        'order_index' => 5
    ];
    
    // Insert generated questions
    $db->beginTransaction();
    $questions_generated = 0;
    
    try {
        foreach ($questions as $q) {
            $db->execute(
                'INSERT INTO assignment_questions (assignment_id, question_text, question_type, order_index) 
                 VALUES (?, ?, ?, ?)',
                [$assignment_id, $q['question_text'], $q['question_type'], $q['order_index']]
            );
            $questions_generated++;
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Assignment questions generated successfully',
            'questions_generated' => $questions_generated
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception('Failed to save generated questions: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

