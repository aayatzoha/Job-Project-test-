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
    // Get recommendations with detailed information
    $recommendations = $db->fetchAll("
        SELECT 
            ar.id,
            ar.recommendation_score,
            ar.skill_match_percentage,
            ar.experience_match_percentage,
            ar.location_match,
            ar.salary_match,
            ar.status,
            ar.created_at,
            u.name as user_name,
            u.email as user_email,
            u.user_type,
            j.title as job_title,
            j.location as job_location,
            j.job_type,
            j.experience_level,
            j.status as job_status,
            c.company_name,
            c.industry,
            (SELECT COUNT(*) FROM job_applications ja 
             JOIN job_seekers js ON ja.job_seeker_id = js.id 
             WHERE js.user_id = ar.user_id AND ja.job_id = ar.job_id) as has_applied
        FROM ai_recommendations ar
        JOIN users u ON ar.user_id = u.id
        JOIN jobs j ON ar.job_id = j.id
        JOIN companies c ON j.company_id = c.id
        ORDER BY ar.created_at DESC
        LIMIT 100
    ");
    
    // Add computed fields
    foreach ($recommendations as &$rec) {
        $rec['overall_match'] = round(
            ($rec['skill_match_percentage'] + $rec['experience_match_percentage']) / 2 + 
            ($rec['location_match'] ? 10 : 0) + 
            ($rec['salary_match'] ? 10 : 0), 2
        );
        
        $rec['recommendation_score'] = round($rec['recommendation_score'], 2);
        $rec['skill_match_percentage'] = round($rec['skill_match_percentage'], 2);
        $rec['experience_match_percentage'] = round($rec['experience_match_percentage'], 2);
        
        $rec['status_display'] = ucwords(str_replace('_', ' ', $rec['status']));
        $rec['job_type_display'] = ucwords(str_replace('_', ' ', $rec['job_type']));
        $rec['experience_level_display'] = ucfirst($rec['experience_level']);
        
        $rec['application_status'] = $rec['has_applied'] > 0 ? 'Applied' : 'Not Applied';
        
        // Calculate days since recommendation
        $created = new DateTime($rec['created_at']);
        $now = new DateTime();
        $rec['days_since_recommendation'] = $now->diff($created)->days;
    }
    
    // Get recommendation statistics
    $stats = $db->fetch("
        SELECT 
            COUNT(*) as total_recommendations,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_recommendations,
            SUM(CASE WHEN status = 'viewed' THEN 1 ELSE 0 END) as viewed_recommendations,
            SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied_recommendations,
            SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed_recommendations,
            AVG(recommendation_score) as avg_recommendation_score,
            AVG(skill_match_percentage) as avg_skill_match,
            AVG(experience_match_percentage) as avg_experience_match,
            COUNT(CASE WHEN location_match = 1 THEN 1 END) as location_matches,
            COUNT(CASE WHEN salary_match = 1 THEN 1 END) as salary_matches,
            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recommendations_last_week
        FROM ai_recommendations
    ");
    
    // Calculate rates
    if ($stats['total_recommendations'] > 0) {
        $stats['view_rate'] = round(($stats['viewed_recommendations'] / $stats['total_recommendations']) * 100, 2);
        $stats['apply_rate'] = round(($stats['applied_recommendations'] / $stats['total_recommendations']) * 100, 2);
        $stats['dismiss_rate'] = round(($stats['dismissed_recommendations'] / $stats['total_recommendations']) * 100, 2);
        $stats['location_match_rate'] = round(($stats['location_matches'] / $stats['total_recommendations']) * 100, 2);
        $stats['salary_match_rate'] = round(($stats['salary_matches'] / $stats['total_recommendations']) * 100, 2);
    } else {
        $stats['view_rate'] = 0;
        $stats['apply_rate'] = 0;
        $stats['dismiss_rate'] = 0;
        $stats['location_match_rate'] = 0;
        $stats['salary_match_rate'] = 0;
    }
    
    $stats['avg_recommendation_score'] = round($stats['avg_recommendation_score'] ?? 0, 2);
    $stats['avg_skill_match'] = round($stats['avg_skill_match'] ?? 0, 2);
    $stats['avg_experience_match'] = round($stats['avg_experience_match'] ?? 0, 2);
    
    // Get top performing recommendations
    $top_recommendations = $db->fetchAll("
        SELECT 
            j.title as job_title,
            c.company_name,
            COUNT(*) as recommendation_count,
            AVG(ar.recommendation_score) as avg_score,
            SUM(CASE WHEN ar.status = 'applied' THEN 1 ELSE 0 END) as applications
        FROM ai_recommendations ar
        JOIN jobs j ON ar.job_id = j.id
        JOIN companies c ON j.company_id = c.id
        GROUP BY j.id, j.title, c.company_name
        HAVING recommendation_count >= 5
        ORDER BY avg_score DESC, applications DESC
        LIMIT 10
    ");
    
    echo json_encode([
        'success' => true,
        'recommendations' => $recommendations,
        'stats' => $stats,
        'top_recommendations' => $top_recommendations
    ]);
    
} catch (Exception $e) {
    error_log("Recommendations API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>