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
    // User growth analytics
    $user_growth = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            user_type,
            COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m'), user_type
        ORDER BY month DESC
    ");
    
    // Job posting trends
    $job_trends = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_jobs,
            SUM(total_applications) as total_applications
        FROM jobs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    
    // Application trends
    $application_trends = $db->fetchAll("
        SELECT 
            DATE_FORMAT(ja.application_date, '%Y-%m') as month,
            COUNT(*) as applications,
            SUM(CASE WHEN ja.status = 'offered' THEN 1 ELSE 0 END) as offers,
            SUM(CASE WHEN ja.status = 'interview' THEN 1 ELSE 0 END) as interviews
        FROM job_applications ja
        WHERE ja.application_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(ja.application_date, '%Y-%m')
        ORDER BY month DESC
    ");
    
    // Assessment analytics
    $assessment_analytics = $db->fetch("
        SELECT 
            COUNT(*) as total_assessments,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_assessments,
            AVG(CASE WHEN status = 'completed' THEN percentage_score END) as avg_score,
            AVG(CASE WHEN status = 'completed' THEN time_taken END) as avg_time_taken,
            COUNT(CASE WHEN status = 'completed' AND percentage_score >= 70 THEN 1 END) as passed_assessments
        FROM user_assessments
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    $assessment_analytics['completion_rate'] = $assessment_analytics['total_assessments'] > 0 ? 
        round(($assessment_analytics['completed_assessments'] / $assessment_analytics['total_assessments']) * 100, 2) : 0;
        
    $assessment_analytics['pass_rate'] = $assessment_analytics['completed_assessments'] > 0 ? 
        round(($assessment_analytics['passed_assessments'] / $assessment_analytics['completed_assessments']) * 100, 2) : 0;
        
    $assessment_analytics['avg_score'] = round($assessment_analytics['avg_score'] ?? 0, 2);
    $assessment_analytics['avg_time_taken'] = round($assessment_analytics['avg_time_taken'] ?? 0);
    
    // Top industries by job postings
    $top_industries = $db->fetchAll("
        SELECT 
            c.industry,
            COUNT(j.id) as job_count,
            SUM(j.total_applications) as total_applications,
            AVG(j.total_applications) as avg_applications_per_job
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE c.industry IS NOT NULL AND c.industry != ''
        GROUP BY c.industry
        ORDER BY job_count DESC
        LIMIT 10
    ");
    
    // User activity patterns
    $user_activity = $db->fetchAll("
        SELECT 
            HOUR(created_at) as hour_of_day,
            COUNT(*) as activity_count
        FROM activity_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(created_at)
        ORDER BY hour_of_day
    ");
    
    // Recommendation performance
    $recommendation_performance = $db->fetch("
        SELECT 
            COUNT(*) as total_recommendations,
            AVG(recommendation_score) as avg_score,
            COUNT(CASE WHEN status = 'applied' THEN 1 END) as applied_count,
            COUNT(CASE WHEN status = 'dismissed' THEN 1 END) as dismissed_count,
            AVG(skill_match_percentage) as avg_skill_match,
            AVG(experience_match_percentage) as avg_experience_match
        FROM ai_recommendations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    if ($recommendation_performance['total_recommendations'] > 0) {
        $recommendation_performance['apply_rate'] = round(
            ($recommendation_performance['applied_count'] / $recommendation_performance['total_recommendations']) * 100, 2
        );
        $recommendation_performance['dismiss_rate'] = round(
            ($recommendation_performance['dismissed_count'] / $recommendation_performance['total_recommendations']) * 100, 2
        );
    } else {
        $recommendation_performance['apply_rate'] = 0;
        $recommendation_performance['dismiss_rate'] = 0;
    }
    
    $recommendation_performance['avg_score'] = round($recommendation_performance['avg_score'] ?? 0, 2);
    $recommendation_performance['avg_skill_match'] = round($recommendation_performance['avg_skill_match'] ?? 0, 2);
    $recommendation_performance['avg_experience_match'] = round($recommendation_performance['avg_experience_match'] ?? 0, 2);
    
    // System performance metrics
    $system_performance = $db->fetch("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as daily_active_users,
            (SELECT COUNT(*) FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_active_users,
            (SELECT COUNT(*) FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_active_users,
            (SELECT COUNT(*) FROM jobs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as jobs_posted_today,
            (SELECT COUNT(*) FROM job_applications WHERE application_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as applications_today,
            (SELECT COUNT(*) FROM user_assessments WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as assessments_taken_today
    ");
    
    // Recent growth metrics
    $growth_metrics = $db->fetch("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_users_this_week,
            (SELECT COUNT(*) FROM users WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_users_last_week,
            (SELECT COUNT(*) FROM jobs WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_jobs_this_week,
            (SELECT COUNT(*) FROM jobs WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_jobs_last_week
    ");
    
    // Calculate growth percentages
    $growth_metrics['user_growth_rate'] = $growth_metrics['new_users_last_week'] > 0 ? 
        round((($growth_metrics['new_users_this_week'] - $growth_metrics['new_users_last_week']) / $growth_metrics['new_users_last_week']) * 100, 2) : 
        ($growth_metrics['new_users_this_week'] > 0 ? 100 : 0);
        
    $growth_metrics['job_growth_rate'] = $growth_metrics['new_jobs_last_week'] > 0 ? 
        round((($growth_metrics['new_jobs_this_week'] - $growth_metrics['new_jobs_last_week']) / $growth_metrics['new_jobs_last_week']) * 100, 2) : 
        ($growth_metrics['new_jobs_this_week'] > 0 ? 100 : 0);
    
    echo json_encode([
        'success' => true,
        'analytics' => [
            'user_growth' => $user_growth,
            'job_trends' => $job_trends,
            'application_trends' => $application_trends,
            'assessment_analytics' => $assessment_analytics,
            'top_industries' => $top_industries,
            'user_activity' => $user_activity,
            'recommendation_performance' => $recommendation_performance,
            'system_performance' => $system_performance,
            'growth_metrics' => $growth_metrics
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>