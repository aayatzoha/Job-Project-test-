<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $useMailtrap = false; // Set to true for testing with Mailtrap
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->useMailtrap ? MAILTRAP_HOST : SMTP_HOST;
            $this->mailer->SMTPAuth = SMTP_AUTH;
            $this->mailer->Username = $this->useMailtrap ? MAILTRAP_USERNAME : SMTP_USERNAME;
            $this->mailer->Password = $this->useMailtrap ? MAILTRAP_PASSWORD : SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_SECURE;
            $this->mailer->Port = $this->useMailtrap ? MAILTRAP_PORT : SMTP_PORT;
            $this->mailer->CharSet = 'UTF-8';
            
            // Enable debug output (for testing)
            if (SMTP_DEBUG) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            
            // Recipients
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
        } catch (Exception $e) {
            error_log("Email setup error: " . $e->getMessage());
        }
    }
    
    public function sendOTP($email, $otp, $name = 'User', $type = 'registration') {
        try {
            // Clear any previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Recipients
            $this->mailer->addAddress($email, $name);
            
            // Content
            $this->mailer->isHTML(true);
            
            if ($type === 'password_reset') {
                $this->mailer->Subject = 'Password Reset OTP - AI Job System';
                $emailBody = $this->getPasswordResetTemplate($otp);
            } else {
                $this->mailer->Subject = OTP_EMAIL_SUBJECT;
                // Replace OTP code in template
                $emailBody = str_replace('{OTP_CODE}', $otp, OTP_EMAIL_TEMPLATE);
            }
            
            $this->mailer->Body = $emailBody;
            
            // Send email
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("OTP email sent successfully to: $email (type: $type)");
                return true;
            } else {
                error_log("Failed to send OTP email to: $email");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("OTP email error: " . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    public function sendWelcomeEmail($email, $name, $userType) {
        try {
            $this->mailer->addAddress($email, $name);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Welcome to AI Job System!';
            
            $emailBody = $this->getWelcomeEmailTemplate($name, $userType);
            $this->mailer->Body = $emailBody;
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Welcome email sent successfully to: $email");
                return true;
            }
            
        } catch (Exception $e) {
            error_log("Welcome email error: " . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    public function sendJobApplicationNotification($email, $jobTitle, $companyName) {
        try {
            $this->mailer->addAddress($email);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Job Application Confirmation - AI Job System';
            
            $emailBody = $this->getApplicationNotificationTemplate($jobTitle, $companyName);
            $this->mailer->Body = $emailBody;
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Application notification sent successfully to: $email");
                return true;
            }
            
        } catch (Exception $e) {
            error_log("Application notification error: " . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    private function getWelcomeEmailTemplate($name, $userType) {
        $userTypeText = ucfirst(str_replace('_', ' ', $userType));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Welcome to AI Job System</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to AI Job System!</h1>
                </div>
                <div class='content'>
                    <h2>Hello $name!</h2>
                    <p>Welcome to AI Job System! Your $userTypeText account has been successfully created and verified.</p>
                    
                    <p>You can now:</p>
                    <ul>
                        <li>Complete your profile</li>
                        <li>Search for jobs (if you're a job seeker)</li>
                        <li>Post jobs (if you're a company)</li>
                        <li>Take assessments to showcase your skills</li>
                        <li>Get AI-powered job recommendations</li>
                    </ul>
                    
                    <p>Get started by logging into your dashboard!</p>
                    
                    <p>Best regards,<br>AI Job System Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getApplicationNotificationTemplate($jobTitle, $companyName) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Application Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Application Submitted!</h1>
                </div>
                <div class='content'>
                    <h2>Thank you for your application!</h2>
                    <p>Your application for the position <strong>$jobTitle</strong> at <strong>$companyName</strong> has been successfully submitted.</p>
                    
                    <p>What happens next:</p>
                    <ul>
                        <li>The company will review your application</li>
                        <li>You may be contacted for further assessment</li>
                        <li>Check your dashboard for updates</li>
                    </ul>
                    
                    <p>Good luck with your application!</p>
                    
                    <p>Best regards,<br>AI Job System Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getPasswordResetTemplate($otp) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .otp-code { background: #fff; border: 2px solid #e74c3c; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
                .otp-code h2 { color: #e74c3c; margin: 0; font-size: 2em; letter-spacing: 5px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 15px 0; color: #856404; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔒 Password Reset</h1>
                    <p>AI Job System</p>
                </div>
                <div class='content'>
                    <h2>Password Reset Request</h2>
                    <p>We received a request to reset your password. Use the following OTP code to proceed:</p>
                    
                    <div class='otp-code'>
                        <h2>$otp</h2>
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Security Notice:</strong>
                        <ul>
                            <li>This OTP is valid for 15 minutes only</li>
                            <li>Do not share this code with anyone</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                            <li>Your password will remain unchanged until you complete the reset process</li>
                        </ul>
                    </div>
                    
                    <p>If you have any concerns, please contact our support team immediately.</p>
                    
                    <p>Best regards,<br>AI Job System Security Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated security message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    public function testConnection() {
        try {
            $this->mailer->smtpConnect();
            $this->mailer->smtpClose();
            return true;
        } catch (Exception $e) {
            error_log("SMTP connection test failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
