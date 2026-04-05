<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/EmailService.php';

$message = '';
$status = '';

// Prefer session, but allow fallback via email input if session not set
$has_pending = isset($_SESSION['pending_user_id']) && isset($_SESSION['pending_email']);
$user_id = $has_pending ? $_SESSION['pending_user_id'] : null;
$email = $has_pending ? $_SESSION['pending_email'] : '';
$user_type = $has_pending ? ($_SESSION['pending_user_type'] ?? '') : '';

// Get user data if known
$user = null;
if ($user_id) {
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    $input_email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($otp)) {
        $message = "OTP is required";
        $status = 'error';
    } else {
        // Resolve user context if session missing
        if (!$has_pending) {
            if (empty($input_email)) {
                $message = "Email is required";
                $status = 'error';
            } else {
                $valid_user = $db->fetch("SELECT * FROM users WHERE email = ?", [$input_email]);
                if ($valid_user) {
                    $user_id = $valid_user['id'];
                    $email = $valid_user['email'];
                    $user_type = $valid_user['user_type'];
                }
            }
        }

        // Verify OTP
        $valid_user = $db->fetch(
            "SELECT * FROM users WHERE id = ? AND email = ? AND otp = ?",
            [$user_id, $email, $otp]
        );
        
        if (!$valid_user) {
            $message = "Invalid OTP";
            $status = 'error';
        } else if (strtotime($valid_user['otp_expires_at']) < time()) {
            $message = "OTP has expired. Please request a new one.";
            $status = 'error';
        } else {
            // Start transaction
            $db->getConnection()->beginTransaction();
            
            try {
                // Update user as verified
                $sql = "UPDATE users SET email_verified = TRUE, otp = NULL, otp_expires_at = NULL, updated_at = NOW() WHERE id = ?";
                $db->query($sql, [$user_id]);
                
                // If user doesn't have a password yet, show password form
                if (empty($valid_user['password_hash'])) {
                    $_SESSION['set_password_user_id'] = $user_id;
                    $db->getConnection()->commit();
                    header('Location: set_password.php');
                    exit();
                }
                
                // Log activity
                logActivity($user_id, 'email_verified', 'OTP verification successful');
                
                // Commit transaction
                $db->getConnection()->commit();
                
                // Create session
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_type'] = $user_type;
                $_SESSION['email'] = $email;
                $_SESSION['name'] = ($user ? $user['name'] : $valid_user['name']);
                $_SESSION['email_verified'] = true;
                
                // Clear pending session data
                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_email']);
                unset($_SESSION['pending_user_type']);
                
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
                
                header("Location: $redirect_url");
                exit();
            } catch (Exception $e) {
                // Rollback transaction
                $db->getConnection()->rollBack();
                $message = "Error: " . $e->getMessage();
                $status = 'error';
            }
        }
    }
}

// Handle resend OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    // Check if we can resend (rate limiting) using session timestamp
    $cooldownSeconds = 30;
    $lastSentAt = $_SESSION['otp_last_sent_at'] ?? 0;
    $can_resend = true;
    if ($lastSentAt && (time() - (int)$lastSentAt) < $cooldownSeconds) {
        $can_resend = false;
        $message = "Please wait " . ($cooldownSeconds - (time() - (int)$lastSentAt)) . " seconds before requesting another OTP";
        $status = 'error';
    }
    
    if ($can_resend) {
        // Generate new OTP
        $otp = generateOTP();
        $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Update user with new OTP
        $sql = "UPDATE users SET otp = ?, otp_expires_at = ? WHERE id = ?";
        $db->query($sql, [$otp, $otp_expires, $user_id]);
        
        // Send OTP email
        $emailService = new EmailService();
        $emailSent = $emailService->sendOTP($email, $otp);
        
        if ($emailSent) {
            $_SESSION['otp_last_sent_at'] = time();
            $message = "A new OTP has been sent to your email";
            $status = 'success';
        } else {
            $message = "Failed to send OTP email. Please try again.";
            $status = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - AI Job Recommendation System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Verify Your Email</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $status; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($has_pending && $email): ?>
            <p>An OTP has been sent to <strong><?php echo htmlspecialchars($email); ?></strong>. Please enter it below to verify your email address.</p>
        <?php else: ?>
            <p>Enter your email and the OTP you received to verify your account.</p>
        <?php endif; ?>
        
        
        <form method="post" action="">
            <?php if (!$has_pending): ?>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="otp">Enter OTP:</label>
                <input type="text" id="otp" name="otp" maxlength="6" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="verify_otp" class="btn">Verify OTP</button>
                <button type="submit" name="resend_otp" class="btn btn-secondary">Resend OTP</button>
            </div>
        </form>
        
        <p><a href="index.php">Back to Home</a></p>
    </div>
</body>
</html>