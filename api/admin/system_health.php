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
    // Database connectivity check
    $db_status = 'connected';
    $db_response_time = 0;
    
    try {
        $start_time = microtime(true);
        $db->fetch("SELECT 1");
        $db_response_time = round((microtime(true) - $start_time) * 1000, 2); // in milliseconds
    } catch (Exception $e) {
        $db_status = 'disconnected';
        $db_response_time = -1;
    }
    
    // System information
    $system_info = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2), // MB
        'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2), // MB
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];
    
    // Database statistics
    $db_stats = [];
    if ($db_status === 'connected') {
        try {
            // Get database size
            $db_name = DB_NAME; // Use configured database name
            $size_query = "
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = ?
            ";
            $db_size = $db->fetch($size_query, [$db_name]);
            
            // Get table count
            $table_count = $db->fetch("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [$db_name]);
            
            $db_stats = [
                'size_mb' => $db_size['size_mb'] ?? 0,
                'table_count' => $table_count['count'] ?? 0,
                'response_time_ms' => $db_response_time
            ];
            
        } catch (Exception $e) {
            $db_stats = [
                'size_mb' => 'N/A',
                'table_count' => 'N/A',
                'response_time_ms' => $db_response_time
            ];
        }
    }
    
    // Application health metrics
    $app_health = [];
    if ($db_status === 'connected') {
        try {
            $app_health = $db->fetch("
                SELECT 
                    (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
                    (SELECT COUNT(*) FROM jobs WHERE status = 'active') as active_jobs,
                    (SELECT COUNT(*) FROM user_assessments WHERE status = 'in_progress') as ongoing_assessments,
                    (SELECT COUNT(*) FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as recent_activity,
                    (SELECT COUNT(*) FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as daily_active_users
            ");
        } catch (Exception $e) {
            $app_health = [
                'active_users' => 'N/A',
                'active_jobs' => 'N/A',
                'ongoing_assessments' => 'N/A',
                'recent_activity' => 'N/A',
                'daily_active_users' => 'N/A'
            ];
        }
    }
    
    // Error log check (last 24 hours)
    $error_count = 0;
    $error_log_path = ini_get('error_log');
    if ($error_log_path && file_exists($error_log_path)) {
        // This is a simplified check - in production you might want more sophisticated error tracking
        $error_count = 'Available'; // Placeholder
    }
    
    // Disk space check
    $disk_usage = [
        'total_space' => round(disk_total_space('.') / 1024 / 1024 / 1024, 2), // GB
        'free_space' => round(disk_free_space('.') / 1024 / 1024 / 1024, 2), // GB
    ];
    $disk_usage['used_space'] = round($disk_usage['total_space'] - $disk_usage['free_space'], 2);
    $disk_usage['usage_percentage'] = $disk_usage['total_space'] > 0 ? 
        round(($disk_usage['used_space'] / $disk_usage['total_space']) * 100, 2) : 0;
    
    // Overall health status
    $health_status = 'healthy';
    $health_issues = [];
    
    if ($db_status !== 'connected') {
        $health_status = 'critical';
        $health_issues[] = 'Database connection failed';
    } elseif ($db_response_time > 1000) {
        $health_status = 'warning';
        $health_issues[] = 'Database response time is slow';
    }
    
    if ($system_info['memory_usage'] > 512) {
        if ($health_status !== 'critical') $health_status = 'warning';
        $health_issues[] = 'High memory usage detected';
    }
    
    if ($disk_usage['usage_percentage'] > 85) {
        if ($health_status !== 'critical') $health_status = 'warning';
        $health_issues[] = 'Low disk space available';
    }
    
    // Performance metrics
    $performance_metrics = [
        'response_time_ms' => $db_response_time,
        'memory_efficiency' => $system_info['memory_peak'] > 0 ? 
            round(($system_info['memory_usage'] / $system_info['memory_peak']) * 100, 2) : 100,
        'uptime' => 'N/A' // This would require system-specific commands
    ];
    
    echo json_encode([
        'success' => true,
        'health' => [
            'status' => $health_status,
            'issues' => $health_issues,
            'last_check' => date('Y-m-d H:i:s'),
            'database' => [
                'status' => $db_status,
                'stats' => $db_stats
            ],
            'system' => $system_info,
            'application' => $app_health,
            'disk' => $disk_usage,
            'performance' => $performance_metrics,
            'error_log' => [
                'status' => $error_count !== 0 ? 'available' : 'not_available',
                'recent_errors' => $error_count
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("System Health API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'health' => [
            'status' => 'critical',
            'issues' => ['System health check failed'],
            'last_check' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>