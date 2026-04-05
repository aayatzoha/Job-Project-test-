<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    requireLogin();
    
    if ($_SESSION['user_type'] !== 'company') {
        throw new Exception('Only companies can post assignments');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $assignment_id = isset($input['assignment_id']) ? (int)$input['assignment_id'] : 0;
    
    if ($assignment_id <= 0) {
        throw new Exception('Invalid assignment ID');
    }
    
    $user_id = $_SESSION['user_id'];
    $db = new Database();
    
    // Get assignment and verify ownership
    $assignment = $db->fetch(
        "SELECT a.*, j.id as job_id, j.company_id, j.title as job_title
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
        throw new Exception('You do not have permission to post this assignment');
    }
    
    // Check if assignment has questions
    $questions = $db->fetchAll(
        "SELECT id FROM assignment_questions WHERE assignment_id = ? LIMIT 1",
        [$assignment_id]
    );
    
    if (empty($questions)) {
        throw new Exception('Cannot post assignment: No questions found. Please add questions to the assignment first. You can use the "Generate Assignment" button to auto-create questions.');
    }
    
    // Update assignment status to 'active' to make it visible
    $db->execute(
        "UPDATE assignments 
         SET status = 'active', updated_at = NOW() 
         WHERE id = ?",
        [$assignment_id]
    );
    
    // Log activity
    logActivity($user_id, 'assignment_posted', "Assignment posted to job: {$assignment['job_title']} (Assignment ID: $assignment_id)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment posted to job successfully',
        'assignment_id' => $assignment_id,
        'job_title' => $assignment['job_title']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    error_log('Post assignment API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Post assignment fatal error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

