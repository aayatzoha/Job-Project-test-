<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$message = '';
$status = '';

// Check if user is authorized to set password
if (!isset($_SESSION['set_password_user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['set_password_user_id'];

// Get user data
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
if (!$user) {
    header('Location: index.php');
    exit();
}

// Handle password setting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate password
    if (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long";
        $status = 'error';
    } else if ($password !== $confirm_password) {
        $message = "Passwords do not match";
        $status = 'error';
    } else {
        // Hash password and update user
        $password_hash = hashPassword($password);
        
        $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
        $db->query($sql, [$password_hash, $user_id]);
        
        // Log activity
        logActivity($user_id, 'password_set', 'User set password');
        
        // Clear session variable
        unset($_SESSION['set_password_user_id']);
        
        // Create session for logged in user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email_verified'] = $user['email_verified'];
        
        // Determine redirect URL based on user type
        $redirect_url = '';
        switch ($user['user_type']) {
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
        
        // Set success message and redirect
        $_SESSION['message'] = "Password set successfully. You are now logged in.";
        $_SESSION['message_status'] = 'success';
        
        header("Location: $redirect_url");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password - AI Job Recommendation System</title>
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
        .password-requirements {
            margin-top: 10px;
            font-size: 14px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Set Your Password</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $status; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <p>Please create a password for your account.</p>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required>
                <div class="password-requirements">
                    Password must be at least 8 characters long
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn">Set Password</button>
        </form>
    </div>
</body>
</html>