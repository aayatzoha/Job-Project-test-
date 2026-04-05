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
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate OTP
    if (!isset($input['otp']) || empty(trim($input['otp']))) {
        throw new Exception('OTP is required');
    }
    
    // Normalize OTP - only trim whitespace, remove any non-numeric characters, no htmlspecialchars needed
    $otp = trim($input['otp']);
    $otp = preg_replace('/\D/', '', $otp); // Remove non-numeric characters
    
    // Validate OTP length (should be 1-6 digits, will pad to 6 if needed)
    if (strlen($otp) === 0) {
        throw new Exception('OTP cannot be empty');
    }
    if (strlen($otp) > 6) {
        throw new Exception('OTP must be 6 digits or less');
    }
    
    // Pad with leading zeros to ensure 6 digits (only if length < 6)
    if (strlen($otp) < 6) {
        $otp = str_pad($otp, 6, '0', STR_PAD_LEFT);
    }
    
    // Check if user is in pending verification
    // Allow fallback: if session data is missing, try to find user by email if provided
    $user_id = null;
    $email = null;
    $user_type = null;
    
    if (isset($_SESSION['pending_user_id']) && isset($_SESSION['pending_email'])) {
        // Use session data if available
        $user_id = $_SESSION['pending_user_id'];
        $email = $_SESSION['pending_email'];
        $user_type = $_SESSION['pending_user_type'] ?? null;
    } elseif (isset($input['email']) && !empty(trim($input['email']))) {
        // Fallback: try to find user by email
        $email = sanitizeInput($input['email']);
        $user = $db->fetch("SELECT id, user_type FROM users WHERE email = ? AND email_verified = FALSE", [$email]);
        if ($user) {
            $user_id = $user['id'];
            $user_type = $user['user_type'];
            // Update session for future requests
            $_SESSION['pending_user_id'] = $user_id;
            $_SESSION['pending_email'] = $email;
            $_SESSION['pending_user_type'] = $user_type;
        } else {
            throw new Exception('No pending verification found for this email');
        }
    } else {
        throw new Exception('No pending verification found. Please provide your email or register again.');
    }
    
    // Verify OTP - Debug output
    error_log("Verifying OTP: $otp for user ID: $user_id, email: $email");
    
    // Verify OTP - Cast OTP to CHAR to ensure it's returned as string (preserves leading zeros)
    // Use CAST and ensure string comparison
    $user = $db->fetch(
        "SELECT id, name, email, user_type, CAST(otp AS CHAR(6)) as otp, otp_expires_at FROM users WHERE id = ? AND email = ?",
        [$user_id, $email]
    );
    
    // Debug the actual OTP in the database vs the provided OTP
    if ($user) {
        // Check if OTP exists in database
        if (empty($user['otp'])) {
            error_log("No OTP found in database for user ID: $user_id");
            throw new Exception('No OTP found. Please request a new one.');
        }
        
        // Normalize database OTP - ensure it's a string, trim, and remove non-numeric chars
        $db_otp_raw = $user['otp'];
        $db_otp = trim((string)$db_otp_raw);
        $db_otp = preg_replace('/\D/', '', $db_otp); // Remove non-numeric characters
        
        // Pad with leading zeros to ensure 6 digits (in case database stored it as integer)
        // But only if the length is less than 6 - if it's already 6, keep it as is
        if (strlen($db_otp) < 6) {
            $db_otp = str_pad($db_otp, 6, '0', STR_PAD_LEFT);
        }
        
        // Detailed debugging
        error_log("=== OTP Verification Debug ===");
        error_log("User ID: $user_id, Email: $email");
        error_log("Raw DB OTP: " . var_export($db_otp_raw, true));
        error_log("Raw DB OTP type: " . gettype($db_otp_raw));
        error_log("Normalized DB OTP: '$db_otp' (length: " . strlen($db_otp) . ")");
        error_log("Raw Provided OTP: " . var_export($input['otp'], true));
        error_log("Normalized Provided OTP: '$otp' (length: " . strlen($otp) . ")");
        
        error_log("String comparison ($db_otp === $otp): " . ($db_otp === $otp ? "MATCH" : "NO MATCH"));
        error_log("=== End Debug ===");
        
        // Compare normalized OTPs as strings (both should be exactly 6 digits after padding)
        // Try string comparison first, then integer comparison as fallback
        $otp_matches = false;
        
        if ($db_otp === $otp) {
            // String comparison matches
            $otp_matches = true;
            error_log("OTP match via string comparison");
        } elseif ((int)$db_otp === (int)$otp) {
            // Integer comparison matches (for cases where leading zeros might be stripped)
            $otp_matches = true;
            error_log("OTP match via integer comparison (DB: '$db_otp' -> " . (int)$db_otp . ", Provided: '$otp' -> " . (int)$otp . ")");
        } else {
            error_log("OTP mismatch: Database has '$db_otp' (type: " . gettype($db_otp) . ", int: " . (int)$db_otp . ") but user provided '$otp' (type: " . gettype($otp) . ", int: " . (int)$otp . ")");
            error_log("String comparison: " . ($db_otp === $otp ? 'MATCH' : 'NO MATCH'));
            error_log("Integer comparison: " . ((int)$db_otp === (int)$otp ? 'MATCH' : 'NO MATCH'));
            throw new Exception('Invalid OTP. Please check and try again. If the problem persists, request a new OTP.');
        }
        
        error_log("OTP verified successfully: $otp");
    } else {
        error_log("No user found with ID: $user_id and email: $email");
        throw new Exception('User not found. Please register again.');
    }
    
    // This condition is now handled above
    
    // Check if OTP is expired separately to provide a clearer error message
    if (strtotime($user['otp_expires_at']) < time()) {
        error_log("OTP expired at: " . $user['otp_expires_at'] . ", current time: " . date('Y-m-d H:i:s'));
        throw new Exception('OTP has expired. Please request a new one.');
    }
    
    // Start transaction
    $db->getConnection()->beginTransaction();
    
    try {
        // Update user as verified
        $sql = "UPDATE users SET email_verified = TRUE, otp = NULL, otp_expires_at = NULL, updated_at = NOW() WHERE id = ?";
        $db->query($sql, [$user_id]);
        
        // Generate a temporary password (user will be prompted to change it)
        $temp_password = generateOTP(8);
        $password_hash = hashPassword($temp_password);
        
        // Update password
        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $db->query($sql, [$password_hash, $user_id]);
        
        // Log activity
        logActivity($user_id, 'email_verified', 'OTP verification successful');
        
        // Commit transaction
        $db->getConnection()->commit();
        
        // Create session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_type'] = $user_type;
        $_SESSION['email'] = $email;
        $_SESSION['name'] = $user['name'];
        $_SESSION['email_verified'] = true;
        $_SESSION['temp_password'] = true; // Flag to prompt password change
        
        // Clear pending session data
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_email']);
        unset($_SESSION['pending_user_type']);
        
        // Determine redirect URL based on user type
        $redirect_url = '';
        switch ($user_type) {
            case 'job_seeker':
                $redirect_url = 'dashboard/job_seeker.php?setup=profile';
                break;
            case 'company':
                $redirect_url = 'dashboard/company.php?setup=profile';
                break;
            case 'admin':
                $redirect_url = 'dashboard/admin.php';
                break;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Email verified successfully! Please set your password.',
            'redirect_url' => $redirect_url,
            'temp_password' => $temp_password,
            'user' => [
                'id' => $user_id,
                'name' => $user['name'],
                'email' => $email,
                'user_type' => $user_type
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->getConnection()->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
