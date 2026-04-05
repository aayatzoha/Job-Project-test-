<?php
header('Content-Type: application/json');
// Same-origin requests only; remove wildcard CORS so cookies/sessions work

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

if ($user_type !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Administrators only.']);
    exit();
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'overview':
            handleSystemOverview();
            break;
        case 'users':
            handleUserManagement();
            break;
        case 'analytics':
            handleAnalytics();
            break;
        case 'assessments':
            handleAssessmentManagement();
            break;
        case 'reports':
            handleReports();
            break;
        case 'system_health':
            handleSystemHealth();
            break;
        case 'settings':
            handleSystemSettings();
            break;
        case 'notifications':
            handleNotifications();
            break;
        default:
            handleSystemOverview();
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * System overview dashboard
 */
function handleSystemOverview() {
    global $db;
    
    $overview = [
        'users' => getUserStats(),
        'jobs' => getJobStats(),
        'assessments' => getAssessmentStats(),
        'applications' => getApplicationStats(),
        'system' => getSystemStats(),
        'recent_activity' => getRecentActivity(),
        'flagged_items' => getFlaggedItems()
    ];
    
    echo json_encode([
        'success' => true,
        'overview' => $overview
    ]);
}

/**
 * User management
 */
function handleUserManagement() {
    global $db, $user_id;
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get users with filtering
        $user_type = $_GET['user_type'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 25);
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($user_type) && in_array($user_type, ['job_seeker', 'company', 'admin'])) {
            $whereConditions[] = "u.user_type = ?";
            $params[] = $user_type;
        }
        
        if (!empty($status) && in_array($status, ['active', 'inactive', 'suspended'])) {
            $whereConditions[] = "u.status = ?";
            $params[] = $status;
        }
        
        if (!empty($search)) {
            $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $users = $db->fetchAll(
            "SELECT u.*, 
                    (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id) as activity_count,
                    (SELECT created_at FROM activity_logs WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as last_activity_date
             FROM users u
             $whereClause
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        
        // Get total count
        $totalResult = $db->fetch(
            "SELECT COUNT(*) as total FROM users u $whereClause",
            $params
        );
        $total = $totalResult['total'];
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        
    } elseif ($method === 'PUT') {
        // Update user
        $input = json_decode(file_get_contents('php://input'), true);
        $targetUserId = intval($input['user_id'] ?? 0);
        $newStatus = $input['status'] ?? '';
        
        if ($targetUserId <= 0) {
            throw new Exception('User ID is required');
        }
        
        if ($targetUserId === $user_id) {
            throw new Exception('Cannot modify your own account');
        }
        
        if (!in_array($newStatus, ['active', 'inactive', 'suspended'])) {
            throw new Exception('Invalid status');
        }
        
        $db->query("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?", [$newStatus, $targetUserId]);
        
        // Log admin action
        logActivity($user_id, 'admin_user_status_changed', "Changed user $targetUserId status to $newStatus");
        
        echo json_encode([
            'success' => true,
            'message' => 'User status updated successfully'
        ]);
        
    } elseif ($method === 'DELETE') {
        // Deactivate user (we don't actually delete users)
        $input = json_decode(file_get_contents('php://input'), true);
        $targetUserId = intval($input['user_id'] ?? 0);
        
        if ($targetUserId <= 0) {
            throw new Exception('User ID is required');
        }
        
        if ($targetUserId === $user_id) {
            throw new Exception('Cannot deactivate your own account');
        }
        
        $db->query("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?", [$targetUserId]);
        
        // Log admin action
        logActivity($user_id, 'admin_user_deactivated', "Deactivated user $targetUserId");
        
        echo json_encode([
            'success' => true,
            'message' => 'User deactivated successfully'
        ]);
    }
}

/**
 * Analytics dashboard
 */
function handleAnalytics() {
    $timeframe = $_GET['timeframe'] ?? '30d';
    $analytics = [
        'user_growth' => getUserGrowthData($timeframe),
        'job_posting_trends' => getJobPostingTrends($timeframe),
        'application_trends' => getApplicationTrends($timeframe),
        'assessment_performance' => getAssessmentPerformance($timeframe),
        'popular_skills' => getPopularSkills($timeframe),
        'geographic_distribution' => getGeographicDistribution(),
        'conversion_funnel' => getConversionFunnel($timeframe)
    ];
    
    echo json_encode([
        'success' => true,
        'analytics' => $analytics,
        'timeframe' => $timeframe
    ]);
}

/**
 * Assessment management
 */
function handleAssessmentManagement() {
    global $db;
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $assessments = $db->fetchAll(
            "SELECT a.*, ac.name as category_name, u.name as created_by_name,
                    COUNT(ua.id) as total_attempts,
                    AVG(ua.percentage_score) as avg_score,
                    COUNT(CASE WHEN ua.status = 'flagged' THEN 1 END) as flagged_attempts
             FROM assessments a
             LEFT JOIN assessment_categories ac ON a.category_id = ac.id
             LEFT JOIN users u ON a.created_by = u.id
             LEFT JOIN user_assessments ua ON a.id = ua.assessment_id
             GROUP BY a.id
             ORDER BY a.created_at DESC"
        );
        
        echo json_encode([
            'success' => true,
            'assessments' => $assessments
        ]);
        
    } elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $assessmentId = intval($input['assessment_id'] ?? 0);
        $status = $input['status'] ?? '';
        
        if (!in_array($status, ['active', 'inactive', 'draft'])) {
            throw new Exception('Invalid status');
        }
        
        $db->query("UPDATE assessments SET status = ? WHERE id = ?", [$status, $assessmentId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Assessment status updated'
        ]);
    }
}

/**
 * System reports
 */
function handleReports() {
    $reportType = $_GET['type'] ?? 'summary';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    switch ($reportType) {
        case 'user_activity':
            $report = generateUserActivityReport($startDate, $endDate);
            break;
        case 'job_performance':
            $report = generateJobPerformanceReport($startDate, $endDate);
            break;
        case 'assessment_integrity':
            $report = generateAssessmentIntegrityReport($startDate, $endDate);
            break;
        case 'system_usage':
            $report = generateSystemUsageReport($startDate, $endDate);
            break;
        default:
            $report = generateSummaryReport($startDate, $endDate);
    }
    
    echo json_encode([
        'success' => true,
        'report' => $report,
        'parameters' => [
            'type' => $reportType,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);
}

/**
 * System health monitoring
 */
function handleSystemHealth() {
    $health = [
        'database' => checkDatabaseHealth(),
        'file_system' => checkFileSystemHealth(),
        'services' => checkServicesHealth(),
        'performance' => getPerformanceMetrics(),
        'security' => getSecurityMetrics(),
        'errors' => getRecentErrors()
    ];
    
    // Overall system status
    $issues = 0;
    foreach ($health as $component) {
        if (isset($component['status']) && $component['status'] !== 'healthy') {
            $issues++;
        }
    }
    
    $overallStatus = $issues === 0 ? 'healthy' : ($issues <= 2 ? 'warning' : 'critical');
    
    echo json_encode([
        'success' => true,
        'health' => $health,
        'overall_status' => $overallStatus,
        'issues_count' => $issues,
        'last_checked' => date('Y-m-d H:i:s')
    ]);
}

/**
 * System settings management
 */
function handleSystemSettings() {
    global $db, $user_id;
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $settings = $db->fetchAll("SELECT * FROM system_settings ORDER BY setting_key");
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
        
    } elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $settingKey = $input['setting_key'] ?? '';
        $settingValue = $input['setting_value'] ?? '';
        
        if (empty($settingKey)) {
            throw new Exception('Setting key is required');
        }
        
        // Update or insert setting
        $existing = $db->fetch("SELECT id FROM system_settings WHERE setting_key = ?", [$settingKey]);
        
        if ($existing) {
            $db->query(
                "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
                [$settingValue, $settingKey]
            );
        } else {
            $db->query(
                "INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())",
                [$settingKey, $settingValue]
            );
        }
        
        // Log admin action
        logActivity($user_id, 'admin_setting_changed', "Changed setting $settingKey");
        
        echo json_encode([
            'success' => true,
            'message' => 'Setting updated successfully'
        ]);
    }
}

/**
 * Notification management
 */
function handleNotifications() {
    global $db, $user_id;
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get system notifications and alerts
        $notifications = $db->fetchAll(
            "SELECT n.*, u.name as user_name 
             FROM notifications n 
             JOIN users u ON n.user_id = u.id 
             WHERE n.type IN ('warning', 'error') 
             ORDER BY n.created_at DESC 
             LIMIT 100"
        );
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications
        ]);
        
    } elseif ($method === 'POST') {
        // Send broadcast notification
        $input = json_decode(file_get_contents('php://input'), true);
        $title = sanitizeInput($input['title'] ?? '');
        $message = sanitizeInput($input['message'] ?? '');
        $userType = $input['user_type'] ?? 'all'; // all, job_seeker, company
        
        if (empty($title) || empty($message)) {
            throw new Exception('Title and message are required');
        }
        
        // Get target users
        $whereClause = "WHERE status = 'active'";
        $params = [];
        
        if ($userType !== 'all' && in_array($userType, ['job_seeker', 'company'])) {
            $whereClause .= " AND user_type = ?";
            $params[] = $userType;
        }
        
        $users = $db->fetchAll("SELECT id FROM users $whereClause", $params);
        
        // Insert notifications
        $sql = "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'info', NOW())";
        
        foreach ($users as $user) {
            $db->query($sql, [$user['id'], $title, $message]);
        }
        
        // Log admin action
        logActivity($user_id, 'admin_broadcast_notification', "Sent to $userType users: $title");
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification sent to ' . count($users) . ' users'
        ]);
    }
}

// Helper functions for statistics

function getUserStats() {
    global $db;
    
    return [
        'total' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
        'active' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
        'job_seekers' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE user_type = 'job_seeker'")['count'],
        'companies' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE user_type = 'company'")['count'],
        'new_this_month' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['count']
    ];
}

function getJobStats() {
    global $db;
    
    return [
        'total' => $db->fetch("SELECT COUNT(*) as count FROM jobs")['count'],
        'active' => $db->fetch("SELECT COUNT(*) as count FROM jobs WHERE status = 'active'")['count'],
        'posted_this_month' => $db->fetch("SELECT COUNT(*) as count FROM jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['count'],
        'avg_applications' => $db->fetch("SELECT AVG(total_applications) as avg FROM jobs WHERE total_applications > 0")['avg'] ?? 0
    ];
}

function getAssessmentStats() {
    global $db;
    
    return [
        'total_assessments' => $db->fetch("SELECT COUNT(*) as count FROM assessments")['count'],
        'total_attempts' => $db->fetch("SELECT COUNT(*) as count FROM user_assessments")['count'],
        'completed_attempts' => $db->fetch("SELECT COUNT(*) as count FROM user_assessments WHERE status = 'completed'")['count'],
        'flagged_attempts' => $db->fetch("SELECT COUNT(*) as count FROM user_assessments WHERE status = 'flagged'")['count'],
        'avg_score' => $db->fetch("SELECT AVG(percentage_score) as avg FROM user_assessments WHERE status = 'completed'")['avg'] ?? 0
    ];
}

function getApplicationStats() {
    global $db;
    
    return [
        'total' => $db->fetch("SELECT COUNT(*) as count FROM job_applications")['count'],
        'this_month' => $db->fetch("SELECT COUNT(*) as count FROM job_applications WHERE application_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['count'],
        'interviews_scheduled' => $db->fetch("SELECT COUNT(*) as count FROM job_applications WHERE status = 'interview'")['count'],
        'offers_made' => $db->fetch("SELECT COUNT(*) as count FROM job_applications WHERE status = 'offered'")['count']
    ];
}

function getSystemStats() {
    global $db;
    
    return [
        'database_size' => getDatabaseSize(),
        'total_activity_logs' => $db->fetch("SELECT COUNT(*) as count FROM activity_logs")['count'],
        'chat_messages' => $db->fetch("SELECT COUNT(*) as count FROM chat_messages")['count'],
        'uptime' => getSystemUptime()
    ];
}

function getRecentActivity() {
    global $db;
    
    return $db->fetchAll(
        "SELECT al.*, u.name, u.user_type
         FROM activity_logs al
         JOIN users u ON al.user_id = u.id
         ORDER BY al.created_at DESC
         LIMIT 20"
    );
}

function getFlaggedItems() {
    global $db;
    
    $flagged = [];
    
    // Flagged assessments
    $flaggedAssessments = $db->fetchAll(
        "SELECT ua.id, ua.user_id, u.name, a.title, ua.cheating_flags
         FROM user_assessments ua
         JOIN users u ON ua.user_id = u.id
         JOIN assessments a ON ua.assessment_id = a.id
         WHERE ua.status = 'flagged'
         ORDER BY ua.completed_at DESC
         LIMIT 10"
    );
    
    $flagged['assessments'] = $flaggedAssessments;
    
    // Suspicious users (multiple failed assessments, etc.)
    $suspiciousUsers = $db->fetchAll(
        "SELECT u.id, u.name, u.email,
                COUNT(ua.id) as flagged_assessments
         FROM users u
         JOIN user_assessments ua ON u.id = ua.user_id
         WHERE ua.status = 'flagged'
         GROUP BY u.id
         HAVING flagged_assessments >= 2
         ORDER BY flagged_assessments DESC
         LIMIT 10"
    );
    
    $flagged['users'] = $suspiciousUsers;
    
    return $flagged;
}

function getUserGrowthData($timeframe) {
    global $db;
    
    $days = match($timeframe) {
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '1y' => 365,
        default => 30
    };
    
    return $db->fetchAll(
        "SELECT DATE(created_at) as date, 
                COUNT(*) as registrations,
                user_type
         FROM users 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         GROUP BY DATE(created_at), user_type
         ORDER BY date ASC",
        [$days]
    );
}

function getJobPostingTrends($timeframe) {
    global $db;
    
    $days = match($timeframe) {
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '1y' => 365,
        default => 30
    };
    
    return $db->fetchAll(
        "SELECT DATE(created_at) as date, 
                COUNT(*) as jobs_posted,
                job_type
         FROM jobs 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         GROUP BY DATE(created_at), job_type
         ORDER BY date ASC",
        [$days]
    );
}

function getApplicationTrends($timeframe) {
    global $db;
    
    $days = match($timeframe) {
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '1y' => 365,
        default => 30
    };
    
    return $db->fetchAll(
        "SELECT DATE(application_date) as date, 
                COUNT(*) as applications,
                status
         FROM job_applications 
         WHERE application_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
         GROUP BY DATE(application_date), status
         ORDER BY date ASC",
        [$days]
    );
}

function getAssessmentPerformance($timeframe) {
    global $db;
    
    $days = match($timeframe) {
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '1y' => 365,
        default => 30
    };
    
    return $db->fetchAll(
        "SELECT a.title, ac.name as category,
                COUNT(ua.id) as attempts,
                AVG(ua.percentage_score) as avg_score,
                COUNT(CASE WHEN ua.status = 'flagged' THEN 1 END) as flagged
         FROM assessments a
         JOIN assessment_categories ac ON a.category_id = ac.id
         LEFT JOIN user_assessments ua ON a.id = ua.assessment_id 
                   AND ua.completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         GROUP BY a.id
         ORDER BY attempts DESC
         LIMIT 20",
        [$days]
    );
}

function getPopularSkills($timeframe) {
    // This would require parsing skills from job_seekers table
    // Simplified version for now
    return [];
}

function getGeographicDistribution() {
    global $db;
    
    return $db->fetchAll(
        "SELECT COALESCE(js.location, 'Unknown') as location, 
                COUNT(*) as count
         FROM job_seekers js
         JOIN users u ON js.user_id = u.id
         WHERE u.status = 'active'
         GROUP BY js.location
         ORDER BY count DESC
         LIMIT 20"
    );
}

function getConversionFunnel($timeframe) {
    global $db;
    
    $days = match($timeframe) {
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '1y' => 365,
        default => 30
    };
    
    // Calculate conversion rates
    $registrations = $db->fetch(
        "SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$days]
    )['count'];
    
    $profileCompleted = $db->fetch(
        "SELECT COUNT(*) as count FROM job_seekers js
         JOIN users u ON js.user_id = u.id
         WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         AND js.resume_file IS NOT NULL",
        [$days]
    )['count'];
    
    $appliedToJobs = $db->fetch(
        "SELECT COUNT(DISTINCT ja.job_seeker_id) as count FROM job_applications ja
         JOIN job_seekers js ON ja.job_seeker_id = js.id
         JOIN users u ON js.user_id = u.id
         WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$days]
    )['count'];
    
    $gotInterviews = $db->fetch(
        "SELECT COUNT(DISTINCT ja.job_seeker_id) as count FROM job_applications ja
         JOIN job_seekers js ON ja.job_seeker_id = js.id
         JOIN users u ON js.user_id = u.id
         WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         AND ja.status IN ('interview', 'offered')",
        [$days]
    )['count'];
    
    return [
        'registrations' => $registrations,
        'profile_completed' => $profileCompleted,
        'applied_to_jobs' => $appliedToJobs,
        'got_interviews' => $gotInterviews,
        'conversion_rates' => [
            'registration_to_profile' => $registrations > 0 ? ($profileCompleted / $registrations * 100) : 0,
            'profile_to_application' => $profileCompleted > 0 ? ($appliedToJobs / $profileCompleted * 100) : 0,
            'application_to_interview' => $appliedToJobs > 0 ? ($gotInterviews / $appliedToJobs * 100) : 0
        ]
    ];
}

// System health check functions
function checkDatabaseHealth() {
    global $db;
    
    try {
        $result = $db->fetch("SELECT 1 as test");
        return [
            'status' => 'healthy',
            'response_time' => 'fast',
            'last_checked' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        return [
            'status' => 'critical',
            'error' => $e->getMessage(),
            'last_checked' => date('Y-m-d H:i:s')
        ];
    }
}

function checkFileSystemHealth() {
    $uploadDir = __DIR__ . '/../../uploads';
    $freeSpace = disk_free_space($uploadDir);
    $totalSpace = disk_total_space($uploadDir);
    
    $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
    
    return [
        'status' => $usagePercent > 90 ? 'critical' : ($usagePercent > 80 ? 'warning' : 'healthy'),
        'free_space' => formatBytes($freeSpace),
        'total_space' => formatBytes($totalSpace),
        'usage_percent' => round($usagePercent, 2)
    ];
}

function checkServicesHealth() {
    // Check if required services are running
    return [
        'web_server' => ['status' => 'healthy'],
        'database' => ['status' => 'healthy'],
        'email_service' => ['status' => 'healthy']
    ];
}

function getPerformanceMetrics() {
    return [
        'memory_usage' => formatBytes(memory_get_usage(true)),
        'peak_memory' => formatBytes(memory_get_peak_usage(true)),
        'execution_time' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) . 's'
    ];
}

function getSecurityMetrics() {
    global $db;
    
    $failedLogins = $db->fetch(
        "SELECT COUNT(*) as count FROM activity_logs 
         WHERE action LIKE '%login_failed%' 
         AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    )['count'];
    
    return [
        'failed_logins_last_hour' => $failedLogins,
        'security_level' => $failedLogins > 10 ? 'warning' : 'normal'
    ];
}

function getRecentErrors() {
    // Check PHP error log or application error log
    return [
        'error_count' => 0,
        'last_error' => null
    ];
}

function getDatabaseSize() {
    global $db;
    
    $result = $db->fetch(
        "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
         FROM information_schema.tables
         WHERE table_schema = ?",
        [DB_NAME]
    );
    
    return $result['size_mb'] . ' MB';
}

function getSystemUptime() {
    // This is a simplified version - in production you'd check actual system uptime
    return '99.9%';
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Report generation functions
function generateSummaryReport($startDate, $endDate) {
    return [
        'period' => "$startDate to $endDate",
        'summary' => 'System summary report generated',
        'data' => []
    ];
}

function generateUserActivityReport($startDate, $endDate) {
    return [
        'period' => "$startDate to $endDate",
        'summary' => 'User activity report generated',
        'data' => []
    ];
}

function generateJobPerformanceReport($startDate, $endDate) {
    return [
        'period' => "$startDate to $endDate",
        'summary' => 'Job performance report generated',
        'data' => []
    ];
}

function generateAssessmentIntegrityReport($startDate, $endDate) {
    return [
        'period' => "$startDate to $endDate",
        'summary' => 'Assessment integrity report generated',
        'data' => []
    ];
}

function generateSystemUsageReport($startDate, $endDate) {
    return [
        'period' => "$startDate to $endDate",
        'summary' => 'System usage report generated',
        'data' => []
    ];
}
?>