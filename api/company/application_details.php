<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

try {
    $user_id = $_SESSION['user_id'];
    if (getUserType() !== 'company') {
        throw new Exception('Companies only');
    }
    
    $company = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id]);
    if (!$company) {
        throw new Exception('Company profile not found');
    }
    $company_id = $company['id'];
    
    $application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($application_id <= 0) {
        throw new Exception('Invalid application ID');
    }
    
    // Get application details with job and candidate information
    $application = $db->fetch(
        "SELECT ja.*, 
                j.title as job_title, j.location, j.description as job_description,
                u.name as candidate_name, u.email as candidate_email, u.phone as candidate_phone,
                js.resume_file, js.skills, js.experience_years, js.location as candidate_location,
                js.bio, js.linkedin_url, js.portfolio_url
         FROM job_applications ja
         JOIN jobs j ON ja.job_id = j.id
         JOIN job_seekers js ON ja.job_seeker_id = js.id
         JOIN users u ON js.user_id = u.id
         WHERE ja.id = ? AND j.company_id = ?",
        [$application_id, $company_id]
    );
    
    if (!$application) {
        throw new Exception('Application not found or access denied');
    }
    
    // Parse skills if they exist
    if ($application['skills']) {
        $application['skills_parsed'] = json_decode($application['skills'], true);
    }
    
    echo json_encode([
        'success' => true,
        'application' => $application
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

