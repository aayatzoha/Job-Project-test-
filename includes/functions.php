<?php
// Utility functions for the AI Job Recommendation System

/**
 * Generate a random OTP
 */
function generateOTP($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Send OTP via email using PHPMailer
 */
function sendOTP($email, $otp) {
    try {
        require_once 'EmailService.php';
        $emailService = new EmailService();
        
        // Send OTP email
        $result = $emailService->sendOTP($email, $otp);
        
        // For development, also log OTP to file
        error_log("OTP for $email: $otp");
        
        return $result;
    } catch (Exception $e) {
        error_log("OTP sending error: " . $e->getMessage());
        
        // Fallback: log OTP for development
        error_log("OTP for $email: $otp");
        
        return false;
    }
}

/**
 * Send Password Reset OTP via email
 */
function sendPasswordResetOTP($email, $otp, $name = 'User') {
    try {
        require_once 'EmailService.php';
        $emailService = new EmailService();
        
        // Send password reset OTP email
        $result = $emailService->sendOTP($email, $otp, $name, 'password_reset');
        
        // For development, also log OTP to file
        error_log("Password Reset OTP for $email: $otp");
        
        return $result;
    } catch (Exception $e) {
        error_log("Password Reset OTP sending error: " . $e->getMessage());
        
        // Fallback: log OTP for development
        error_log("Password Reset OTP for $email: $otp");
        
        return false;
    }
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Backwards-compatibility aliases used by some older endpoints
function is_logged_in() { return isLoggedIn(); }
function get_user_type() { return getUserType(); }
function require_login() { return requireLogin(); }

/**
 * Check user type
 */
function getUserType() {
    return isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Prefer API-friendly behavior: return 401 JSON for API routes;
        // otherwise redirect to the main login page.
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isApiRequest = strpos($requestUri, '/api/') !== false;
        if ($isApiRequest) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
        } else {
            header('Location: /job/index.php');
        }
        exit();
    }
}

/**
 * Redirect based on user type
 */
function redirectByUserType() {
    if (!isLoggedIn()) {
        return;
    }
    
    $userType = getUserType();
    switch ($userType) {
        case 'job_seeker':
            header('Location: dashboard/job_seeker.php');
            break;
        case 'company':
            header('Location: dashboard/company.php');
            break;
        case 'admin':
            header('Location: dashboard/admin.php');
            break;
    }
    exit();
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

/**
 * Calculate time ago
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Upload file with validation
 */
function uploadFile($file, $uploadDir, $allowedTypes = ['pdf', 'doc', 'docx']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }
    
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB'];
    }
    
    // Normalize upload directory path
    $uploadDir = rtrim($uploadDir, '/\\');
    
    // Ensure upload directory exists with proper permissions
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true)) {
            error_log("Failed to create upload directory: $uploadDir");
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
        // Set proper permissions
        @chmod($uploadDir, 0777);
    } else {
        // Ensure existing directory has proper permissions
        @chmod($uploadDir, 0777);
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        error_log("Upload directory is not writable: $uploadDir");
        return ['success' => false, 'message' => 'Upload directory is not writable. Please contact administrator.'];
    }
    
    $filename = generateUniqueFilename($file['name']);
    $filepath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Verify file was moved successfully
        if (file_exists($filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
        } else {
            error_log("File move appeared successful but file doesn't exist: $filepath");
            return ['success' => false, 'message' => 'File upload verification failed'];
        }
    }
    
    // Get last error
    $error = error_get_last();
    error_log("Failed to move uploaded file. Source: {$file['tmp_name']}, Destination: $filepath. Error: " . ($error ? $error['message'] : 'Unknown'));
    
    return ['success' => false, 'message' => 'Failed to save uploaded file'];
}

/**
 * Extract text from various file formats
 */
function extractTextFromFile($filepath) {
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'pdf':
            return extractTextFromPDF($filepath);
        case 'doc':
        case 'docx':
            return extractTextFromDoc($filepath);
        case 'txt':
            return file_get_contents($filepath);
        default:
            throw new Exception('Unsupported file format');
    }
}

/**
 * Enhanced PDF text extraction using regex parsing
 */
function extractTextFromPDF($filepath) {
    // Prefer shell tool if available; otherwise, fallback to naive extraction
    $output = null;
    if (function_exists('shell_exec')) {
        $escaped = escapeshellarg($filepath);
        $output = @shell_exec("pdftotext $escaped -");
    }
    if ($output && strlen(trim($output)) > 0) {
        return $output;
    }
    // Fallback method - naive PDF parsing (may be partial)
    $content = @file_get_contents($filepath);
    if ($content === false) {
        return '';
    }
    if (preg_match_all('/\((.*?)\)/', $content, $matches)) {
        return implode(' ', $matches[1]);
    }
    return '';
}

/**
 * Enhanced DOC/DOCX text extraction
 */
function extractTextFromDoc($filepath) {
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    
    if ($extension === 'docx') {
        // Extract from DOCX
        $zip = new ZipArchive;
        if ($zip->open($filepath) === TRUE) {
            $content = $zip->getFromName('word/document.xml');
            $zip->close();
            
            // Remove XML tags
            $content = strip_tags($content);
            return $content;
        }
    }
    
    // Fallback for DOC files
    return 'Text extraction from DOC files requires additional libraries';
}

/**
 * Enhanced AI-powered resume parsing
 */
function parseResumeWithAI($text) {
    $parsedData = [
        'skills' => [],
        'experience' => [],
        'education' => [],
        'summary' => '',
        'contact' => []
    ];
    
    // Extract skills using keyword matching
    $skillKeywords = [
        'programming' => ['php', 'javascript', 'python', 'java', 'c++', 'c#', 'ruby', 'go', 'rust'],
        'web' => ['html', 'css', 'react', 'vue', 'angular', 'node.js', 'bootstrap', 'jquery'],
        'database' => ['mysql', 'postgresql', 'mongodb', 'sqlite', 'oracle', 'sql server'],
        'tools' => ['git', 'docker', 'kubernetes', 'jenkins', 'aws', 'azure', 'linux'],
        'soft_skills' => ['leadership', 'communication', 'project management', 'teamwork', 'problem solving']
    ];
    
    $text_lower = strtolower($text);
    foreach ($skillKeywords as $category => $skills) {
        foreach ($skills as $skill) {
            if (strpos($text_lower, strtolower($skill)) !== false) {
                $parsedData['skills'][] = [
                    'name' => $skill,
                    'category' => $category,
                    'confidence' => 0.8
                ];
            }
        }
    }
    
    // Extract experience using regex patterns
    $experiencePatterns = [
        '/(\d+)\s*[-–]\s*(\d+)\s*years?\s+(?:of\s+)?experience/i',
        '/(\d+)\+?\s*years?\s+(?:of\s+)?experience/i',
        '/experience:\s*(\d+)\s*years?/i'
    ];
    
    foreach ($experiencePatterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $years = isset($matches[2]) ? $matches[2] : $matches[1];
            $parsedData['experience_years'] = intval($years);
            break;
        }
    }
    
    // Extract education
    $educationKeywords = ['bachelor', 'master', 'phd', 'diploma', 'degree', 'university', 'college'];
    foreach ($educationKeywords as $keyword) {
        if (stripos($text, $keyword) !== false) {
            $parsedData['education'][] = [
                'level' => $keyword,
                'confidence' => 0.7
            ];
        }
    }
    
    // Generate summary (first few sentences)
    $sentences = preg_split('/[.!?]+/', $text, 3);
    $parsedData['summary'] = trim(implode('. ', array_slice($sentences, 0, 2)));
    
    // Extract contact information
    if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $matches)) {
        $parsedData['contact']['email'] = $matches[1];
    }
    
    if (preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $text, $matches)) {
        $parsedData['contact']['phone'] = $matches[0];
    }
    
    return $parsedData;
}

/**
 * Generate job recommendations (placeholder)
 */
function generateJobRecommendations($userId) {
    // TODO: Implement AI recommendation algorithm
    return [];
}

/**
 * Log user activity
 */
function logActivity($userId, $action, $details = '') {
    global $db;
    
    $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    $db->query($sql, [$userId, $action, $details]);
}

/**
 * Get user info
 */
function getUserInfo($userId) {
    global $db;
    
    $sql = "SELECT * FROM users WHERE id = ?";
    return $db->fetch($sql, [$userId]);
}

/**
 * Update user last activity
 */
function updateLastActivity($userId) {
    global $db;
    
    $sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
    $db->query($sql, [$userId]);
}
?>
