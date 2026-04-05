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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($input['user_id'] ?? 0);
    $confirmation = $input['confirmation'] ?? false;
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    
    // Check if user exists
    $user = $db->fetch("SELECT id, name, email, user_type, status FROM users WHERE id = ?", [$user_id]);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Prevent deleting admin users (safety measure)
    if ($user['user_type'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot delete admin users']);
        exit();
    }
    
    // Prevent self-deletion
    if ($user_id === $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        exit();
    }
    
    // Additional confirmation required for deletion
    if (!$confirmation) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Confirmation required for user deletion']);
        exit();
    }
    
    // Check for dependent data and provide warning
    $dependencies = [];
    
    if ($user['user_type'] === 'job_seeker') {
        $applications = $db->fetch("SELECT COUNT(*) as count FROM job_applications ja JOIN job_seekers js ON ja.job_seeker_id = js.id WHERE js.user_id = ?", [$user_id])['count'] ?? 0;
        $assessments = $db->fetch("SELECT COUNT(*) as count FROM user_assessments WHERE user_id = ?", [$user_id])['count'] ?? 0;
        
        if ($applications > 0) $dependencies[] = "$applications job applications";
        if ($assessments > 0) $dependencies[] = "$assessments assessment results";
    } elseif ($user['user_type'] === 'company') {
        $jobs = $db->fetch("SELECT COUNT(*) as count FROM jobs j JOIN companies c ON j.company_id = c.id WHERE c.user_id = ?", [$user_id])['count'] ?? 0;
        
        if ($jobs > 0) $dependencies[] = "$jobs job postings";
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Log the action before deletion
        logActivity($_SESSION['user_id'], 'user_deleted', "Deleted user: {$user['name']} ({$user['email']}) - Type: {$user['user_type']}");
        
        // Due to CASCADE DELETE constraints in the schema, deleting the user will automatically
        // remove related records from job_seekers, companies, job_applications, user_assessments, etc.
        $db->execute("DELETE FROM users WHERE id = ?", [$user_id]);
        
        // Commit transaction
        $db->commit();
        
        $message = 'User deleted successfully';
        if (!empty($dependencies)) {
            $message .= '. Associated data removed: ' . implode(', ', $dependencies);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'user' => [
                'id' => $user_id,
                'name' => $user['name'],
                'email' => $user['email']
            ],
            'dependencies' => $dependencies
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Delete User API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>