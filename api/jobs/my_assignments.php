<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    requireLogin();
    if (getUserType() !== 'job_seeker') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Job seeker access required']);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $db = new Database();

    // Fetch assignments assigned to this job seeker
    $sql = "SELECT 
                a.id, a.title, a.description, a.due_date, a.status, a.requires_camera,
                j.title AS job_title,
                c.company_name
            FROM assignments a
            JOIN jobs j ON a.job_id = j.id
            JOIN companies c ON j.company_id = c.id
            WHERE a.assigned_to = ?
            ORDER BY a.created_at DESC";
    $assignments = $db->fetchAll($sql, [$user_id]);

    echo json_encode(['success' => true, 'assignments' => $assignments ?: []]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

