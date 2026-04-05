<?php
header('Content-Type: application/json');
// Same-origin requests only; remove wildcard CORS so cookies/sessions work

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['role'] ?? $_SESSION['user_type'];
    
    // Debug logging
    error_log("Resume upload attempt - User ID: $user_id, User Type: $user_type");
    error_log("Files array: " . print_r($_FILES, true));
    
    if ($user_type !== 'job_seeker') {
        error_log("Resume upload denied - User type mismatch: $user_type");
        throw new Exception('Only job seekers can upload resumes');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['resume']) || empty($_FILES['resume']['name']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        error_log("Resume upload error: No file in \$_FILES['resume'] or upload error");
        throw new Exception('No file uploaded or upload error. Please select a file first.');
    }
    
    $file = $_FILES['resume'];
    
    // Debug file upload data
    error_log("Resume file data: " . json_encode($file));
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
        error_log("Resume upload error code: {$file['error']} - $errorMsg");
        throw new Exception("Upload error: $errorMsg");
    }
    
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        error_log("Resume upload error: Invalid file or not uploaded via POST");
        throw new Exception('Invalid file upload');
    }
    
    $uploadDir = __DIR__ . '/../../uploads/resumes';
    $allowedTypes = ['pdf', 'doc', 'docx', 'txt'];
    
    // Ensure upload directory exists with proper permissions
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            error_log("Failed to create upload directory: $uploadDir");
            throw new Exception('Failed to create upload directory');
        }
        // Set proper permissions
        chmod($uploadDir, 0777);
        error_log("Created upload directory: $uploadDir with full permissions");
    } else {
        // Ensure existing directory has proper permissions
        chmod($uploadDir, 0777);
    }
    
    // Check directory permissions
    if (!is_writable($uploadDir)) {
        error_log("Upload directory is not writable: $uploadDir - Attempting to fix permissions");
        chmod($uploadDir, 0777);
        
        // Check again after permission change
        if (!is_writable($uploadDir)) {
            error_log("Upload directory still not writable after permission change: $uploadDir");
            throw new Exception('Upload directory is not writable. Please contact administrator.');
        }
    }
    
    error_log("Upload directory: $uploadDir (exists: " . (is_dir($uploadDir) ? 'yes' : 'no') . ", writable: " . (is_writable($uploadDir) ? 'yes' : 'no') . ")");
    error_log("File info: name={$file['name']}, size={$file['size']}, type={$file['type']}, tmp_name={$file['tmp_name']}");
    
    // Upload file
    $uploadResult = uploadFile($file, $uploadDir, $allowedTypes);
    if (!$uploadResult['success']) {
        error_log("Upload failed: " . $uploadResult['message']);
        throw new Exception($uploadResult['message']);
    }
    
    $filename = $uploadResult['filename'];
    $filepath = $uploadResult['filepath'];
    
    // Extract text from uploaded file
    $extractedText = extractTextFromFile($filepath);
    
    // Parse resume with AI/NLP
    $parsedData = parseResumeWithAI($extractedText);
    
    // Update job seeker profile
    $sql = "UPDATE job_seekers SET 
            resume_file = ?, 
            resume_text = ?, 
            skills = ?, 
            bio = ?
            WHERE user_id = ?";
    
    $skillsJson = json_encode($parsedData['skills']);
    $bio = $parsedData['summary'] ?? '';
    
    $db->execute($sql, [$filename, $extractedText, $skillsJson, $bio, $user_id]);
    
    // Log activity
    logActivity($user_id, 'resume_uploaded', "File: $filename");
    
    echo json_encode([
        'success' => true,
        'message' => 'Resume uploaded successfully',
        'filename' => $filename,
        'parsed_data' => $parsedData
    ]);
    exit();
    
} catch (Exception $e) {
    error_log("Resume upload exception: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file_uploaded' => isset($_FILES['resume']),
            'upload_error' => isset($_FILES['resume']['error']) ? $_FILES['resume']['error'] : 'N/A',
            'user_id' => $_SESSION['user_id'] ?? 'N/A',
            'user_type' => $_SESSION['user_type'] ?? 'N/A'
        ]
    ]);
    exit();
}

?>
