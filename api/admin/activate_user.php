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
    
    // Check if user is already active
    if ($user['status'] === 'active') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User is already active']);
        exit();
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Update user status to active
        $db->execute(
            "UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?",
            [$user_id]
        );
        
        // Log the action
        logActivity($_SESSION['user_id'], 'user_activated', "Activated user: {$user['name']} ({$user['email']})");
        
        // Log the action for the activated user as well
        logActivity($user_id, 'account_activated', "Account activated by administrator");
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'User activated successfully',
            'user' => [
                'id' => $user_id,
                'name' => $user['name'],
                'email' => $user['email'],
                'status' => 'active'
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Activate User API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>