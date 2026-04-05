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
    if (!isset($input['email']) || !isset($input['user_type'])) {
        throw new Exception('Email and user type are required');
    }
    
    // Sanitize inputs
    $email = sanitizeInput($input['email']);
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
        throw new Exception('No account found with this email and user type');
    }
    
    // Generate OTP
    $otp = generateOTP();
    
    // Store OTP in database (reusing existing OTP columns)
    $otp_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $db->query(
        "UPDATE users SET otp = ?, otp_expires_at = ? WHERE id = ?",
        [$otp, $otp_expires, $user['id']]
    );
    
    // Send OTP via email (using password reset template)
    $emailSent = sendPasswordResetOTP($email, $otp, $user['name']);
    
    if (!$emailSent) {
        throw new Exception('Failed to send OTP. Please try again later.');
    }
    
    // Store user info in session for verification
    session_start();
    $_SESSION['reset_user_id'] = $user['id'];
    $_SESSION['reset_email'] = $user['email'];
    
    // Log activity
    logActivity($user['id'], 'password_reset_requested', 'Password reset OTP sent');
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset OTP has been sent to your email',
        'email_sent' => true
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>