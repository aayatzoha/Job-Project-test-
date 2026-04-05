# Gmail SMTP Setup Guide

## 🔧 Quick Setup for Gmail

### Step 1: Enable 2-Factor Authentication
1. Go to [Google Account Security](https://myaccount.google.com/security)
2. Enable **2-Step Verification** if not already enabled
3. This is required to generate App Passwords

### Step 2: Generate App Password
1. Go to [Google Account Security](https://myaccount.google.com/security)
2. Click on **App passwords** (under 2-Step Verification)
3. Select **Mail** and **Other (custom name)**
4. Enter "AI Job System" as the app name
5. Copy the generated 16-character password

### Step 3: Update Configuration
Edit `config/email.php` and update these values:

```php
// Replace with your Gmail address
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');

// Replace with your Gmail App Password (16 characters)
define('SMTP_PASSWORD', 'your-16-char-app-password');
```

### Step 4: Test Configuration
1. Go to `http://localhost/job/setup_email.php`
2. Click "Send Test OTP" with your email address
3. Check your Gmail inbox for the OTP email

## 🚨 Important Notes

- **Never use your regular Gmail password** - always use App Passwords
- **App Passwords are 16 characters** without spaces
- **Keep your App Password secure** - treat it like your regular password
- **You can revoke App Passwords** anytime from Google Account settings

## 🔍 Troubleshooting

### "Authentication failed" error:
- Make sure 2-Factor Authentication is enabled
- Verify you're using the App Password, not your regular password
- Check that the App Password is exactly 16 characters

### "Connection refused" error:
- Make sure you're using `smtp.gmail.com` and port `587`
- Check your internet connection
- Verify Gmail SMTP is not blocked by your firewall

### Emails going to spam:
- This is normal for new email addresses
- Check your spam folder
- Consider using a professional email service for production

## 📧 Alternative Email Services

If Gmail doesn't work, you can use:

### Mailtrap (For Testing)
- Sign up at [mailtrap.io](https://mailtrap.io)
- Free tier available
- Perfect for development and testing

### SendGrid
- Professional email service
- Free tier: 100 emails/day
- Good for production use

### Your Hosting Provider
- Most hosting providers offer SMTP
- Check your hosting control panel
- Usually more reliable for production

## 🧪 Testing Without Real Email

For development, you can also:
1. Check the XAMPP error logs for OTP codes
2. Use Mailtrap to catch emails without sending them
3. Log OTPs to a file for testing

The system will always log OTPs to the error log for development purposes.
