<?php
require_once __DIR__ . '/includes/EmailService.php';

// Initialize variables
$message = '';
$status = '';
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        $message = 'Please enter an email address';
        $status = 'error';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address';
        $status = 'error';
    } else {
        // Generate a random 6-digit OTP
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        
        // Send OTP email
        $emailService = new EmailService();
        $result = $emailService->sendOTP($email, $otp);
        
        if ($result) {
            $message = "OTP sent successfully to $email. Check your inbox (and spam folder).";
            $status = 'success';
            
            // Log the OTP for testing purposes
            error_log("TEST OTP for $email: $otp");
        } else {
            $message = 'Failed to send OTP. Check server logs for details.';
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
    <title>Email Setup Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
        }
        h1 {
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="email"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
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
        .config-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #6c757d;
            margin: 20px 0;
        }
        code {
            background-color: #f1f1f1;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <h1>Email Setup Test</h1>
    
    <div class="container">
        <h2>Test OTP Email</h2>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $status; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            
            <button type="submit">Send Test OTP</button>
        </form>
    </div>
    
    <div class="config-info">
        <h3>Current Email Configuration</h3>
        <p>SMTP Host: <code><?php echo defined('SMTP_HOST') ? SMTP_HOST : 'Not defined'; ?></code></p>
        <p>SMTP Port: <code><?php echo defined('SMTP_PORT') ? SMTP_PORT : 'Not defined'; ?></code></p>
        <p>SMTP Username: <code><?php echo defined('SMTP_USERNAME') ? (SMTP_USERNAME === 'your-actual-email@gmail.com' ? 'Not configured (using placeholder)' : 'Configured') : 'Not defined'; ?></code></p>
        <p>SMTP Password: <code><?php echo defined('SMTP_PASSWORD') ? (SMTP_PASSWORD === 'your-16-digit-app-password' ? 'Not configured (using placeholder)' : 'Configured (hidden)') : 'Not defined'; ?></code></p>
    </div>
    
    <p><a href="index.php">← Back to Home</a></p>
</body>
</html>