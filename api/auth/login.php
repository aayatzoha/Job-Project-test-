<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    if (!isset($input['email']) || !isset($input['password']) || !isset($input['user_type'])) {
        throw new Exception('Email, password, and user type are required');
    }
    
    // Sanitize inputs
    $email = sanitizeInput($input['email']);
    $password = $input['password'];
    $user_type = sanitizeInput($input['user_type']);
    
    // Validate email format
    if (!validateEmail($email)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate user type
    $allowed_types = ['job_seeker', 'company', 'admin'];
    if (!in_array($user_type, $allowed_types)) {
        throw new Exception('Invalid user type');
    }
    
    // Find user
    $user = $db->fetch(
        "SELECT * FROM users WHERE email = ? AND user_type = ? AND status = 'active'",
        [$email, $user_type]
    );
    
    if (!$user) {
        throw new Exception('Invalid email or user type');
    }
    
    // Check if email is verified
    if (!$user['email_verified']) {
        // Store user info in session for verification
        session_start();
        $_SESSION['pending_user_id'] = $user['id'];
        $_SESSION['pending_email'] = $user['email'];
        $_SESSION['pending_user_type'] = $user['user_type'];
        
        // Return response indicating verification needed
        echo json_encode([
            'success' => false,
            'needs_verification' => true,
            'message' => 'Please verify your email address first',
            'redirect_url' => '../verify_email.php'
        ]);
        exit();
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        // Log failed login attempt
        logActivity($user['id'], 'login_failed', 'Invalid password');
        throw new Exception('Invalid password');
    }
    
    // Update last activity
    updateLastActivity($user['id']);
    
    // Create session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email_verified'] = $user['email_verified'];
    
    // Log successful login
    logActivity($user['id'], 'login_successful', 'User logged in');
    
    // Determine redirect URL based on user type
    $redirect_url = '';
    switch ($user_type) {
        case 'job_seeker':
            $redirect_url = 'dashboard/job_seeker.php';
            break;
        case 'company':
            $redirect_url = 'dashboard/company.php';
            break;
        case 'admin':
            $redirect_url = 'dashboard/admin.php';
            break;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'redirect_url' => $redirect_url,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'user_type' => $user['user_type'],
            'email_verified' => $user['email_verified']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
