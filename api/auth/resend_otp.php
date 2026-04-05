<?php
header('Content-Type: application/json');
// Same-origin requests only; remove wildcard CORS so cookies are accepted

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

session_start();

try {
    // Check if user is in pending verification
    if (!isset($_SESSION['pending_user_id']) || !isset($_SESSION['pending_email'])) {
        throw new Exception('No pending verification found');
    }
    
    $user_id = $_SESSION['pending_user_id'];
    $email = $_SESSION['pending_email'];
    
    // Check if user exists and is not verified
    $user = $db->fetch(
        "SELECT * FROM users WHERE id = ? AND email = ? AND email_verified = FALSE",
        [$user_id, $email]
    );
    
    if (!$user) {
        throw new Exception('User not found or already verified');
    }
    
    // Check rate limiting (prevent spam) using session timestamp
    $cooldownSeconds = 30;
    $lastSentAt = $_SESSION['otp_last_sent_at'] ?? 0;
    if ($lastSentAt && (time() - (int)$lastSentAt) < $cooldownSeconds) {
        $wait = $cooldownSeconds - (time() - (int)$lastSentAt);
        throw new Exception('Please wait ' . $wait . ' seconds before requesting another OTP');
    }
    
    // Generate new OTP
    $otp = generateOTP();
    $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Update OTP in database
    $sql = "UPDATE users SET otp = ?, otp_expires_at = ?, updated_at = NOW() WHERE id = ?";
    $db->query($sql, [$otp, $otp_expires, $user_id]);
    
    // Send OTP email
    $emailSent = sendOTP($email, $otp);
    
    // Log activity (even if email fails, we still generated the OTP)
    try {
        logActivity($user_id, 'otp_resent', 'OTP resent for email verification');
    } catch (Exception $e) {
        // Log activity failure shouldn't block OTP resend
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // Update session cooldown
    $_SESSION['otp_last_sent_at'] = time();
    
    // Return success with OTP (for development/debugging)
    echo json_encode([
        'success' => true,
        'message' => 'OTP resent successfully. Please check your email.',
        'otp' => $otp, // Include OTP for development (remove in production if needed)
        'email_sent' => $emailSent
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
