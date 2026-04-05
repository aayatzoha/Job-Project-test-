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
    // Get security events (using activity_logs as security events)
    $security_events = $db->fetchAll("
        SELECT 
            al.*,
            u.name as user_name,
            u.email as user_email,
            u.user_type
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.action IN ('login_successful', 'login_failed', 'password_changed', 'user_suspended', 'user_deleted', 'admin_access')
        ORDER BY al.created_at DESC
        LIMIT 50
    ");
    
    // Get failed login attempts
    $failed_logins = $db->fetchAll("
        SELECT 
            COUNT(*) as attempts,
            ip_address,
            MAX(created_at) as last_attempt
        FROM activity_logs 
        WHERE action = 'login_failed' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY ip_address
        HAVING attempts >= 3
        ORDER BY attempts DESC, last_attempt DESC
    ");
    
    // Get active sessions (simulated - in real implementation this would be session storage)
    $active_sessions = $db->fetchAll("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.last_activity,
            al.ip_address,
            al.user_agent
        FROM users u
        LEFT JOIN (
            SELECT user_id, ip_address, user_agent,
                   ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) as rn
            FROM activity_logs 
            WHERE action = 'login_successful'
        ) al ON u.id = al.user_id AND al.rn = 1
        WHERE u.last_activity > DATE_SUB(NOW(), INTERVAL 4 HOUR)
        AND u.status = 'active'
        ORDER BY u.last_activity DESC
    ");
    
    // Security statistics
    $security_stats = $db->fetch("
        SELECT 
            (SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as failed_logins_24h,
            (SELECT COUNT(*) FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as active_sessions,
            (SELECT COUNT(*) FROM activity_logs WHERE action IN ('login_failed', 'suspicious_activity') AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as security_alerts_24h,
            (SELECT COUNT(DISTINCT ip_address) FROM activity_logs WHERE action = 'login_failed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as suspicious_ips
    ");
    
    // Calculate security score
    $security_score = 100;
    if ($security_stats['failed_logins_24h'] > 10) $security_score -= 10;
    if ($security_stats['security_alerts_24h'] > 5) $security_score -= 15;
    if ($security_stats['suspicious_ips'] > 3) $security_score -= 20;
    
    $security_level = 'Good';
    if ($security_score < 70) $security_level = 'Warning';
    if ($security_score < 50) $security_level = 'Critical';
    
    // System vulnerabilities check (simplified)
    $vulnerabilities = [];
    
    // Check for weak passwords (simplified check)
    $weak_passwords = $db->fetchAll("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE LENGTH(password_hash) < 60 -- bcrypt hashes are typically 60+ chars
    ");
    
    if ($weak_passwords[0]['count'] > 0) {
        $vulnerabilities[] = [
            'type' => 'weak_passwords',
            'severity' => 'medium',
            'description' => 'Some users have weak passwords',
            'count' => $weak_passwords[0]['count']
        ];
    }
    
    // Check for admin accounts without recent activity
    $inactive_admins = $db->fetchAll("
        SELECT COUNT(*) as count
        FROM users 
        WHERE user_type = 'admin' 
        AND (last_activity IS NULL OR last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY))
    ");
    
    if ($inactive_admins[0]['count'] > 0) {
        $vulnerabilities[] = [
            'type' => 'inactive_admins',
            'severity' => 'high',
            'description' => 'Admin accounts with no recent activity',
            'count' => $inactive_admins[0]['count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'security_events' => $security_events,
        'failed_logins' => $failed_logins,
        'active_sessions' => $active_sessions,
        'security_stats' => array_merge($security_stats, [
            'security_score' => $security_score,
            'security_level' => $security_level
        ]),
        'vulnerabilities' => $vulnerabilities
    ]);
    
} catch (Exception $e) {
    error_log("Security API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>