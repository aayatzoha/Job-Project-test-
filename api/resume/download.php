<?php
// Secure resume download for job seekers and companies

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();

// Require authentication
requireLogin();
$user_id = $_SESSION['user_id'] ?? null;
$user_type = getUserType();

// Get filename from query parameter (for companies viewing candidate resumes)
$resumeFile = isset($_GET['file']) ? basename($_GET['file']) : null;

if ($user_type === 'company' && $resumeFile) {
    // Companies can download resumes of candidates who applied to their jobs
    $company = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id]);
    if (!$company) {
        http_response_code(403);
        die('Access denied');
    }
    
    // Verify the resume belongs to a candidate who applied to this company's jobs
    $application = $db->fetch(
        "SELECT 1 FROM job_applications ja
         JOIN jobs j ON ja.job_id = j.id
         JOIN job_seekers js ON ja.job_seeker_id = js.id
         WHERE j.company_id = ? AND js.resume_file = ?",
        [$company['id'], $resumeFile]
    );
    
    if (!$application) {
        http_response_code(403);
        die('Access denied: Resume not associated with your company applications.');
    }
    
    $resumePath = __DIR__ . '/../../uploads/resumes/' . $resumeFile;
    
} elseif ($user_type === 'job_seeker') {
    // Job seekers can download their own resume
    if ($resumeFile) {
        // Verify it's their own resume
        $profile = $db->fetch("SELECT resume_file FROM job_seekers WHERE user_id = ?", [$user_id]);
        if (!$profile || $profile['resume_file'] !== $resumeFile) {
            http_response_code(403);
            die('Access denied');
        }
        $resumePath = __DIR__ . '/../../uploads/resumes/' . $resumeFile;
    } else {
        // Fetch resume filename from profile
        $profile = $db->fetch("SELECT resume_file FROM job_seekers WHERE user_id = ?", [$user_id]);
        if (!$profile || empty($profile['resume_file'])) {
            http_response_code(404);
            die('No resume found for your profile.');
        }
        $resumePath = __DIR__ . '/../../uploads/resumes/' . $profile['resume_file'];
    }
} else {
    http_response_code(403);
    die('Access denied');
}

if (!file_exists($resumePath)) {
    http_response_code(404);
    die('Resume file not found on the server.');
}

// Detect MIME type if possible
$mime = function_exists('mime_content_type') ? mime_content_type($resumePath) : 'application/octet-stream';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($resumePath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($resumePath));
readfile($resumePath);
exit;
?>
