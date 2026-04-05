<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || getUserType() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Get all assessments with statistics
    $assessments = $db->fetchAll("
        SELECT 
            a.*,
            ac.name as category_name,
            ac.skill_type,
            u.name as created_by_name,
            (SELECT COUNT(*) FROM assessment_questions_mapping aqm WHERE aqm.assessment_id = a.id) as total_questions,
            (SELECT COUNT(*) FROM user_assessments ua WHERE ua.assessment_id = a.id) as total_attempts,
            (SELECT COUNT(*) FROM user_assessments ua WHERE ua.assessment_id = a.id AND ua.status = 'completed') as completed_attempts,
            (SELECT AVG(ua.percentage_score) FROM user_assessments ua WHERE ua.assessment_id = a.id AND ua.status = 'completed') as avg_score,
            (SELECT COUNT(*) FROM user_assessments ua WHERE ua.assessment_id = a.id AND ua.status = 'completed' AND ua.percentage_score >= a.passing_score) as passed_attempts
        FROM assessments a
        LEFT JOIN assessment_categories ac ON a.category_id = ac.id
        LEFT JOIN users u ON a.created_by = u.id
        ORDER BY a.created_at DESC
    ");
    
    // Add computed fields
    foreach ($assessments as &$assessment) {
        $assessment['completion_rate'] = $assessment['total_attempts'] > 0 ? 
            round(($assessment['completed_attempts'] / $assessment['total_attempts']) * 100, 2) : 0;
        
        $assessment['pass_rate'] = $assessment['completed_attempts'] > 0 ? 
            round(($assessment['passed_attempts'] / $assessment['completed_attempts']) * 100, 2) : 0;
        
        $assessment['avg_score'] = $assessment['avg_score'] ? round($assessment['avg_score'], 2) : 0;
        
        $assessment['time_limit_minutes'] = round($assessment['time_limit'] / 60);
        
        // Status display
        $assessment['status_display'] = ucfirst($assessment['status']);
    }
    
    // Get assessment categories
    $categories = $db->fetchAll("SELECT * FROM assessment_categories ORDER BY name");
    
    // Get assessment statistics
    $stats = $db->fetch("
        SELECT 
            COUNT(*) as total_assessments,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_assessments,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_assessments,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_assessments,
            (SELECT COUNT(*) FROM user_assessments) as total_attempts,
            (SELECT COUNT(*) FROM user_assessments WHERE status = 'completed') as completed_attempts,
            (SELECT AVG(percentage_score) FROM user_assessments WHERE status = 'completed') as overall_avg_score
        FROM assessments
    ");
    
    $stats['overall_completion_rate'] = $stats['total_attempts'] > 0 ? 
        round(($stats['completed_attempts'] / $stats['total_attempts']) * 100, 2) : 0;
    
    $stats['overall_avg_score'] = $stats['overall_avg_score'] ? round($stats['overall_avg_score'], 2) : 0;
    
    echo json_encode([
        'success' => true,
        'assessments' => $assessments,
        'categories' => $categories,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Assessments API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>