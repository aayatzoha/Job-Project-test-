<?php
header('Content-Type: application/json');
// Same-origin requests do not need wildcard CORS. Removing to allow cookies.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    session_start();
    
    // Rate limiting: prevent duplicate registrations within 5 seconds
    $rateLimitKey = 'registration_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $lastRegistration = $_SESSION[$rateLimitKey] ?? 0;
    $rateLimitWindow = 5; // seconds
    
    if ($lastRegistration && (time() - $lastRegistration) < $rateLimitWindow) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Please wait a few seconds before registering again.'
        ]);
        exit();
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required_fields = ['name', 'email', 'phone', 'user_type'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Sanitize inputs
    $name = sanitizeInput($input['name']);
    $email = sanitizeInput($input['email']);
    $phone = sanitizeInput($input['phone']);
    $user_type = sanitizeInput($input['user_type']);
    
    // Validate email format
    if (!validateEmail($email)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate user type
    $allowed_types = ['job_seeker', 'company'];
    if (!in_array($user_type, $allowed_types)) {
        throw new Exception('Invalid user type');
    }
    
    // Check if email already exists
    $existing_user = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing_user) {
        throw new Exception('Email already registered');
    }
    
    // Update rate limit timestamp
    $_SESSION[$rateLimitKey] = time();
    
    // Generate OTP
    $otp = generateOTP();
    $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Start transaction
    $db->getConnection()->beginTransaction();
    
    try {
        // Insert user record
        $sql = "INSERT INTO users (name, email, phone, user_type, otp, otp_expires_at, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $db->query($sql, [$name, $email, $phone, $user_type, $otp, $otp_expires]);
        $user_id = $db->lastInsertId();
        
        // Create profile based on user type
        if ($user_type === 'job_seeker') {
            $sql = "INSERT INTO job_seekers (user_id) VALUES (?)";
            $db->query($sql, [$user_id]);
        } elseif ($user_type === 'company') {
            // For company, we need to provide company_name (NOT NULL field)
            // Use the user's name as default company name
            $sql = "INSERT INTO companies (user_id, company_name) VALUES (?, ?)";
            $db->query($sql, [$user_id, $name]);
        }
        
        // Send OTP email (with fallback for development)
        $emailSent = sendOTP($email, $otp);
        
        // Log activity
        logActivity($user_id, 'user_registered', "User type: $user_type");
        
        // Commit transaction
        $db->getConnection()->commit();
        
        // Store user data in session for OTP verification (session already started above)
        $_SESSION['pending_user_id'] = $user_id;
        $_SESSION['pending_email'] = $email;
        $_SESSION['pending_user_type'] = $user_type;
        
        // Return success response with OTP for verification
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please use the OTP to verify your account.',
            'user_id' => $user_id,
            'otp' => $otp,
            'email_sent' => $emailSent
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
