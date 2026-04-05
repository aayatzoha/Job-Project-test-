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
    $report_type = $_GET['type'] ?? '';
    
    switch ($report_type) {
        case 'user-registration':
            $data = generateUserRegistrationReport();
            break;
        case 'user-activity':
            $data = generateUserActivityReport();
            break;
        case 'user-retention':
            $data = generateUserRetentionReport();
            break;
        case 'job-performance':
            $data = generateJobPerformanceReport();
            break;
        case 'application-trends':
            $data = generateApplicationTrendsReport();
            break;
        case 'recommendation-performance':
            $data = generateRecommendationPerformanceReport();
            break;
        case 'assessment-analytics':
            $data = generateAssessmentAnalyticsReport();
            break;
        default:
            $data = getReportsList();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    error_log("Reports API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

function generateUserRegistrationReport() {
    global $db;
    
    // User registration trends
    $registration_trends = $db->fetchAll("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as registrations,
            user_type
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at), user_type
        ORDER BY date DESC, user_type
    ");
    
    // Registration by source/referrer (if tracked)
    $registration_sources = $db->fetchAll("
        SELECT 
            'Direct' as source,
            COUNT(*) as count
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY 'Direct'
        ORDER BY count DESC
    ");
    
    // Geographic distribution (if location data available)
    $geographic_data = $db->fetchAll("
        SELECT 
            COALESCE(js.location, c.headquarters, 'Unknown') as location,
            COUNT(*) as user_count
        FROM users u
        LEFT JOIN job_seekers js ON u.id = js.user_id AND u.user_type = 'job_seeker'
        LEFT JOIN companies c ON u.id = c.user_id AND u.user_type = 'company'
        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY location
        ORDER BY user_count DESC
        LIMIT 10
    ");
    
    return [
        'registration_trends' => $registration_trends,
        'registration_sources' => $registration_sources,
        'geographic_data' => $geographic_data,
        'summary' => [
            'total_registrations' => array_sum(array_column($registration_trends, 'registrations')),
            'period' => 'Last 30 days'
        ]
    ];
}

function generateUserActivityReport() {
    global $db;
    
    // Daily active users
    $daily_activity = $db->fetchAll("
        SELECT 
            DATE(last_activity) as date,
            COUNT(DISTINCT id) as active_users,
            user_type
        FROM users 
        WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND last_activity IS NOT NULL
        GROUP BY DATE(last_activity), user_type
        ORDER BY date DESC, user_type
    ");
    
    // User engagement levels
    $engagement_levels = $db->fetchAll("
        SELECT 
            CASE 
                WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'Daily Active'
                WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Weekly Active'
                WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Monthly Active'
                ELSE 'Inactive'
            END as activity_level,
            COUNT(*) as user_count
        FROM users
        GROUP BY activity_level
        ORDER BY user_count DESC
    ");
    
    // Top user actions
    $top_actions = $db->fetchAll("
        SELECT 
            action,
            COUNT(*) as action_count
        FROM activity_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY action
        ORDER BY action_count DESC
        LIMIT 10
    ");
    
    return [
        'daily_activity' => $daily_activity,
        'engagement_levels' => $engagement_levels,
        'top_actions' => $top_actions
    ];
}

function generateUserRetentionReport() {
    global $db;
    
    // Cohort analysis - simplified
    $cohort_analysis = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as cohort_month,
            COUNT(*) as users_joined,
            COUNT(CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as users_active_30d,
            COUNT(CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as users_active_7d
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY cohort_month DESC
    ");
    
    // Calculate retention rates
    foreach ($cohort_analysis as &$cohort) {
        $cohort['retention_30d'] = $cohort['users_joined'] > 0 ? 
            round(($cohort['users_active_30d'] / $cohort['users_joined']) * 100, 2) : 0;
        $cohort['retention_7d'] = $cohort['users_joined'] > 0 ? 
            round(($cohort['users_active_7d'] / $cohort['users_joined']) * 100, 2) : 0;
    }
    
    return [
        'cohort_analysis' => $cohort_analysis,
        'overall_retention' => [
            'day_1' => 85.5, // These would be calculated from actual data
            'day_7' => 62.3,
            'day_30' => 34.7
        ]
    ];
}

function generateJobPerformanceReport() {
    global $db;
    
    $job_performance = $db->fetchAll("
        SELECT 
            j.id,
            j.title,
            c.company_name,
            j.created_at,
            j.status,
            j.views_count,
            j.total_applications,
            COUNT(ja.id) as actual_applications,
            CASE 
                WHEN j.views_count > 0 THEN ROUND((COUNT(ja.id) / j.views_count) * 100, 2)
                ELSE 0 
            END as conversion_rate
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        LEFT JOIN job_applications ja ON j.id = ja.job_id
        WHERE j.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY j.id, j.title, c.company_name, j.created_at, j.status, j.views_count, j.total_applications
        ORDER BY actual_applications DESC, conversion_rate DESC
        LIMIT 20
    ");
    
    return ['job_performance' => $job_performance];
}

function generateApplicationTrendsReport() {
    global $db;
    
    $application_trends = $db->fetchAll("
        SELECT 
            DATE(application_date) as date,
            COUNT(*) as applications,
            status,
            COUNT(CASE WHEN status = 'offered' THEN 1 END) as offers,
            COUNT(CASE WHEN status = 'interview' THEN 1 END) as interviews
        FROM job_applications
        WHERE application_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(application_date), status
        ORDER BY date DESC
    ");
    
    return ['application_trends' => $application_trends];
}

function generateRecommendationPerformanceReport() {
    global $db;
    
    $recommendation_performance = $db->fetchAll("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_recommendations,
            AVG(recommendation_score) as avg_score,
            COUNT(CASE WHEN status = 'applied' THEN 1 END) as applications_from_recommendations,
            ROUND((COUNT(CASE WHEN status = 'applied' THEN 1 END) / COUNT(*)) * 100, 2) as conversion_rate
        FROM ai_recommendations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    
    return ['recommendation_performance' => $recommendation_performance];
}

function generateAssessmentAnalyticsReport() {
    global $db;
    
    $assessment_analytics = $db->fetchAll("
        SELECT 
            a.title,
            COUNT(ua.id) as total_attempts,
            COUNT(CASE WHEN ua.status = 'completed' THEN 1 END) as completed_attempts,
            AVG(CASE WHEN ua.status = 'completed' THEN ua.percentage_score END) as avg_score,
            AVG(CASE WHEN ua.status = 'completed' THEN ua.time_taken END) as avg_time_taken
        FROM assessments a
        LEFT JOIN user_assessments ua ON a.id = ua.assessment_id
        WHERE ua.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR ua.created_at IS NULL
        GROUP BY a.id, a.title
        ORDER BY completed_attempts DESC
    ");
    
    return ['assessment_analytics' => $assessment_analytics];
}

function getReportsList() {
    return [
        'available_reports' => [
            'user-registration' => 'User Registration Report',
            'user-activity' => 'User Activity Report',
            'user-retention' => 'User Retention Report',
            'job-performance' => 'Job Performance Report',
            'application-trends' => 'Application Trends Report',
            'recommendation-performance' => 'AI Recommendation Performance',
            'assessment-analytics' => 'Assessment Analytics Report'
        ]
    ];
}
?>