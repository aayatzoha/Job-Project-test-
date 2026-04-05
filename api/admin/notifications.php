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
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get notifications data
        $notifications = $db->fetchAll("
            SELECT 
                n.*,
                u.name as user_name,
                u.email as user_email
            FROM notifications n
            LEFT JOIN users u ON n.user_id = u.id
            ORDER BY n.created_at DESC
            LIMIT 100
        ");
        
        // Get notification statistics
        $stats = $db->fetch("
            SELECT 
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as sent_today,
                COUNT(CASE WHEN is_read = 1 THEN 1 END) as total_read,
                COUNT(*) as total_sent,
                AVG(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) * 100 as read_rate
            FROM notifications 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'stats' => $stats
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Send new notification
        $input = json_decode(file_get_contents('php://input'), true);
        
        $title = $input['title'] ?? '';
        $message = $input['message'] ?? '';
        $type = $input['type'] ?? 'info';
        $recipients = $input['recipients'] ?? [];
        
        if (empty($title) || empty($message)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Title and message are required']);
            exit();
        }
        
        // Build user query based on recipients
        $user_conditions = [];
        if (in_array('all_users', $recipients)) {
            $user_conditions[] = "1=1";
        } else {
            if (in_array('job_seekers', $recipients)) {
                $user_conditions[] = "user_type = 'job_seeker'";
            }
            if (in_array('companies', $recipients)) {
                $user_conditions[] = "user_type = 'company'";
            }
            if (in_array('active_users', $recipients)) {
                $user_conditions[] = "status = 'active' AND last_activity > DATE_SUB(NOW(), INTERVAL 7 DAY)";
            }
        }
        
        if (empty($user_conditions)) {
            $user_conditions[] = "user_type = 'job_seeker'"; // Default
        }
        
        $user_where = implode(' OR ', $user_conditions);
        
        // Get target users
        $target_users = $db->fetchAll("SELECT id FROM users WHERE ($user_where) AND status = 'active'");
        
        $db->beginTransaction();
        
        try {
            $sent_count = 0;
            foreach ($target_users as $user) {
                $db->execute(
                    "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())",
                    [$user['id'], $title, $message, $type]
                );
                $sent_count++;
            }
            
            // Log the action
            logActivity($_SESSION['user_id'], 'bulk_notification_sent', "Sent notification '$title' to $sent_count users");
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Notification sent to $sent_count users",
                'sent_count' => $sent_count
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
} catch (Exception $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>