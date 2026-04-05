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
    $reason = $input['reason'] ?? 'Administrative action';
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    
    // Check if user exists and is not already suspended
    $user = $db->fetch("SELECT id, name, email, user_type, status FROM users WHERE id = ?", [$user_id]);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Prevent suspending other admins (safety measure)
    if ($user['user_type'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot suspend admin users']);
        exit();
    }
    
    // Prevent suspending already suspended users
    if ($user['status'] === 'suspended') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User is already suspended']);
        exit();
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Update user status to suspended
        $db->execute(
            "UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?",
            [$user_id]
        );
        
        // Log the action
        logActivity($_SESSION['user_id'], 'user_suspended', "Suspended user: {$user['name']} ({$user['email']}). Reason: $reason");
        
        // Log the action for the suspended user as well
        logActivity($user_id, 'account_suspended', "Account suspended by administrator. Reason: $reason");
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'User suspended successfully',
            'user' => [
                'id' => $user_id,
                'name' => $user['name'],
                'email' => $user['email'],
                'status' => 'suspended'
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Suspend User API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>