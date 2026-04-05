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
    if (!isset($input['otp']) || !isset($input['password'])) {
        throw new Exception('OTP and password are required');
    }
    
    // Start session to get user ID
    session_start();
    if (!isset($_SESSION['reset_user_id'])) {
        throw new Exception('Password reset session expired. Please try again.');
    }
    
    $user_id = $_SESSION['reset_user_id'];
    
    // Normalize OTP - only trim whitespace, remove any non-numeric characters, no htmlspecialchars needed
    $otp = trim($input['otp']);
    $otp = preg_replace('/\D/', '', $otp); // Remove non-numeric characters
    
    // Validate OTP length
    if (strlen($otp) !== 6) {
        throw new Exception('OTP must be exactly 6 digits');
    }
    
    $password = $input['password'];
    
    // Validate password
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    // Get user
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Normalize database OTP for comparison
    $db_otp = trim((string)$user['otp']);
    $db_otp = preg_replace('/\D/', '', $db_otp); // Remove non-numeric characters
    
    // Verify OTP
    if (!$user['otp'] || $db_otp !== $otp) {
        throw new Exception('Invalid OTP');
    }
    
    // Check if OTP is expired
    if (!$user['otp_expires_at'] || strtotime($user['otp_expires_at']) < time()) {
        throw new Exception('OTP has expired. Please request a new one.');
    }
    
    // Hash new password
    $password_hash = hashPassword($password);
    
    // Update password and clear OTP
    $db->query(
        "UPDATE users SET password_hash = ?, otp = NULL, otp_expires_at = NULL, updated_at = NOW() WHERE id = ?",
        [$password_hash, $user_id]
    );
    
    // Log activity
    logActivity($user_id, 'password_reset_successful', 'Password reset successful');
    
    // Clear session variables
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_email']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully. You can now login with your new password.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>