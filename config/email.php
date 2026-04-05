<?php
// Email configuration for AI Job System

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'aibasedjobrecomendationsystem@gmail.com'); // Gmail address
define('SMTP_PASSWORD', 'taojttdatezdmqkj'); // Gmail App Password
define('SMTP_FROM_EMAIL', 'aibasedjobrecomendationsystem@gmail.com'); // Gmail address
define('SMTP_FROM_NAME', 'AI Job System');

// Email settings
define('SMTP_SECURE', 'tls'); // Use 'tls' for port 587, 'ssl' for port 465
define('SMTP_AUTH', true);
define('SMTP_DEBUG', false); // Disabled for production

// Alternative: Use a different email service
// For testing, you can use services like:
// - Mailtrap (for testing)
// - SendGrid
// - Mailgun
// - Amazon SES

// Mailtrap configuration (for testing - replace with your credentials)
define('MAILTRAP_HOST', 'smtp.mailtrap.io');
define('MAILTRAP_PORT', 2525);
define('MAILTRAP_USERNAME', 'your-mailtrap-username');
define('MAILTRAP_PASSWORD', 'your-mailtrap-password');

// Email templates
define('OTP_EMAIL_SUBJECT', 'OTP Verification - AI Job System');
define('OTP_EMAIL_TEMPLATE', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OTP Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .otp-code { background: #fff; border: 2px solid #667eea; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .otp-code h2 { color: #667eea; margin: 0; font-size: 2em; letter-spacing: 5px; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>AI Job System</h1>
            <p>Email Verification</p>
        </div>
        <div class="content">
            <h2>Hello!</h2>
            <p>Thank you for registering with AI Job System. To complete your registration, please use the following OTP code:</p>
            
            <div class="otp-code">
                <h2>{OTP_CODE}</h2>
            </div>
            
            <p><strong>Important:</strong></p>
            <ul>
                <li>This OTP is valid for 10 minutes only</li>
                <li>Do not share this code with anyone</li>
                <li>If you did not request this, please ignore this email</li>
            </ul>
            
            <p>If you have any questions, please contact our support team.</p>
            
            <p>Best regards,<br>AI Job System Team</p>
        </div>
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
');
?>
