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
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $user_type = $_GET['user_type'] ?? '';
    $status = $_GET['status'] ?? '';
    $email_verified = $_GET['email_verified'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($user_type)) {
        $where_conditions[] = "u.user_type = ?";
        $params[] = $user_type;
    }
    
    if (!empty($status)) {
        $where_conditions[] = "u.status = ?";
        $params[] = $status;
    }

    if ($email_verified !== '') {
        $where_conditions[] = "u.email_verified = ?";
        $params[] = $email_verified === '1' ? 1 : 0;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get total count for pagination
    $count_query = "
        SELECT COUNT(*) as total 
        FROM users u 
        $where_clause
    ";
    
    $total_users = $db->fetch($count_query, $params)['total'] ?? 0;
    
    // Get users with pagination
    $query = "
        SELECT 
            u.id,
            u.name,
            u.email,
            u.phone,
            u.user_type,
            u.email_verified,
            u.status,
            u.last_activity,
            u.created_at,
            u.updated_at,
            CASE 
                WHEN u.user_type = 'job_seeker' THEN js.location
                WHEN u.user_type = 'company' THEN c.company_name
                ELSE NULL
            END as additional_info,
            CASE 
                WHEN u.user_type = 'company' THEN c.industry
                ELSE NULL
            END as company_industry
        FROM users u
        LEFT JOIN job_seekers js ON u.id = js.user_id AND u.user_type = 'job_seeker'
        LEFT JOIN companies c ON u.id = c.user_id AND u.user_type = 'company'
        $where_clause
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $users = $db->fetchAll($query, $params);
    
    // Add some computed fields
    foreach ($users as &$user) {
        // Calculate days since last activity
        if ($user['last_activity']) {
            $last_activity = new DateTime($user['last_activity']);
            $now = new DateTime();
            $user['days_inactive'] = $now->diff($last_activity)->days;
        } else {
            $user['days_inactive'] = null;
        }
        
        // Format user type display
        $user['user_type_display'] = ucwords(str_replace('_', ' ', $user['user_type']));
        
        // Add profile completeness (simplified calculation)
        $completeness = 50; // Base score
        if ($user['phone']) $completeness += 10;
        if ($user['email_verified']) $completeness += 20;
        if ($user['additional_info']) $completeness += 20;
        $user['profile_completeness'] = min(100, $completeness);
    }
    
    // Get user statistics
    $stats = $db->fetch(" 
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN user_type = 'job_seeker' THEN 1 ELSE 0 END) as job_seekers,
            SUM(CASE WHEN user_type = 'company' THEN 1 ELSE 0 END) as companies,
            SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
            SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_users,
            SUM(CASE WHEN email_verified = 0 THEN 1 ELSE 0 END) as unverified_users,
            SUM(CASE WHEN last_activity > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_last_30_days
        FROM users
    ");
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'pagination' => [
            'total' => $total_users,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_users / $limit)
        ],
        'stats' => $stats,
        'filters' => [
            'search' => $search,
            'user_type' => $user_type,
            'status' => $status,
            'email_verified' => $email_verified
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>