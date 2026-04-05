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
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['user_id']) || !isset($input['assignment_id'])) {
            throw new Exception('User ID and Assignment ID are required');
        }
        
        $target_user_id = intval($input['user_id']);
        $assignment_id = intval($input['assignment_id']);
        
        // Verify user exists and is a job seeker
        $user = $db->fetch("SELECT * FROM users WHERE id = ? AND user_type = 'job_seeker'", [$target_user_id]);
        if (!$user) {
            throw new Exception('Invalid user or user is not a job seeker');
        }
        
        // Verify assignment exists
        $assignment = $db->fetch("SELECT * FROM job_assignments WHERE id = ?", [$assignment_id]);
        if (!$assignment) {
            throw new Exception('Assignment not found');
        }
        
        // Check if assignment is already assigned to someone
        if ($assignment['assigned_to'] && $assignment['assigned_to'] != $target_user_id) {
            throw new Exception('Assignment is already assigned to another user');
        }
        
        // Assign the assignment to the user
        $db->execute(
            "UPDATE job_assignments SET assigned_to = ?, status = 'pending', updated_at = NOW() WHERE id = ?",
            [$target_user_id, $assignment_id]
        );
        
        // Create notification for the user
        createNotification(
            $target_user_id,
            'New Assignment',
            "You have been assigned a new task: {$assignment['title']}",
            [
                'type' => 'assignment',
                'assignment_id' => $assignment_id,
                'job_id' => $assignment['job_id']
            ]
        );
        
        // Log admin action
        logActivity($user_id, 'admin_assigned_assignment', "Admin assigned assignment #$assignment_id to user #$target_user_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'Assignment successfully assigned to user'
        ]);
        
    } elseif ($method === 'GET') {
        // Get available assignments and job seekers for assignment
        
        // Get all unassigned assignments or filter by job_id if provided
        $job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;
        
        $assignmentQuery = "SELECT ja.*, j.title as job_title, c.company_name 
                           FROM job_assignments ja
                           JOIN jobs j ON ja.job_id = j.id
                           JOIN companies c ON j.company_id = c.id
                           WHERE (ja.assigned_to IS NULL OR ja.assigned_to = 0)";
        
        $params = [];
        if ($job_id) {
            $assignmentQuery .= " AND ja.job_id = ?";
            $params[] = $job_id;
        }
        
        $assignmentQuery .= " ORDER BY ja.created_at DESC";
        $assignments = $db->fetchAll($assignmentQuery, $params);
        
        // Get all job seekers
        $jobSeekers = $db->fetchAll(
            "SELECT u.id, u.name, u.email, js.skills, js.experience_years
             FROM users u
             LEFT JOIN job_seekers js ON u.id = js.user_id
             WHERE u.user_type = 'job_seeker' AND u.status = 'active'
             ORDER BY u.name"
        );
        
        echo json_encode([
            'success' => true,
            'assignments' => $assignments,
            'job_seekers' => $jobSeekers
        ]);
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>