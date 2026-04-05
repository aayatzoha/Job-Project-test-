<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    
    if (!in_array($user_type, ['company', 'admin'])) {
        throw new Exception('Only companies and admins can create assessments');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required_fields = ['title', 'category_id', 'questions'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $title = sanitizeInput($input['title']);
    $description = sanitizeInput($input['description'] ?? '');
    $category_id = intval($input['category_id']);
    $job_id = isset($input['job_id']) ? intval($input['job_id']) : null;
    $time_limit = intval($input['time_limit'] ?? 3600); // Default 1 hour
    $passing_score = floatval($input['passing_score'] ?? 60.0);
    $is_proctored = isset($input['is_proctored']) ? boolval($input['is_proctored']) : true;
    $questions = $input['questions'];
    
    if (!is_array($questions) || empty($questions)) {
        throw new Exception('Assessment must have at least one question');
    }
    
    $db->getConnection()->beginTransaction();
    
    try {
        // Create assessment
        $sql = "INSERT INTO assessments (title, description, category_id, job_id, 
                total_questions, time_limit, passing_score, is_proctored, 
                status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())";
        
        $db->query($sql, [
            $title, $description, $category_id, $job_id,
            count($questions), $time_limit, $passing_score, 
            $is_proctored ? 1 : 0, $user_id
        ]);
        
        $assessment_id = $db->lastInsertId();
        
        // Add questions
        foreach ($questions as $index => $question) {
            $question_id = createOrGetQuestion($question, $category_id);
            
            // Map question to assessment
            $mapSql = "INSERT INTO assessment_questions_mapping 
                      (assessment_id, question_id, order_index)
                      VALUES (?, ?, ?)";
            $db->query($mapSql, [$assessment_id, $question_id, $index + 1]);
        }
        
        $db->getConnection()->commit();
        
        // Log activity
        logActivity($user_id, 'assessment_created', "Assessment: $title");
        
        echo json_encode([
            'success' => true,
            'message' => 'Assessment created successfully',
            'assessment_id' => $assessment_id
        ]);
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Create or get existing question
 */
function createOrGetQuestion($questionData, $category_id) {
    global $db;
    
    $question_text = sanitizeInput($questionData['question_text']);
    $question_type = sanitizeInput($questionData['question_type']);
    $options = isset($questionData['options']) ? json_encode($questionData['options']) : null;
    $correct_answer = sanitizeInput($questionData['correct_answer'] ?? '');
    $explanation = sanitizeInput($questionData['explanation'] ?? '');
    $difficulty_level = sanitizeInput($questionData['difficulty_level'] ?? 'medium');
    $time_limit = intval($questionData['time_limit'] ?? 60);
    $points = intval($questionData['points'] ?? 1);
    
    // Check if question already exists
    $existing = $db->fetch(
        "SELECT id FROM assessment_questions 
         WHERE question_text = ? AND category_id = ? AND question_type = ?",
        [$question_text, $category_id, $question_type]
    );
    
    if ($existing) {
        return $existing['id'];
    }
    
    // Create new question
    $sql = "INSERT INTO assessment_questions 
            (category_id, question_text, question_type, options, correct_answer,
             explanation, difficulty_level, time_limit, points, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $db->query($sql, [
        $category_id, $question_text, $question_type, $options,
        $correct_answer, $explanation, $difficulty_level, $time_limit, $points
    ]);
    
    return $db->lastInsertId();
}
?>