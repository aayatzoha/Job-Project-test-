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
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $application_id = isset($input['application_id']) ? intval($input['application_id']) : 0;
    $status = isset($input['status']) ? trim($input['status']) : '';
    
    if ($application_id <= 0) {
        throw new Exception('Invalid application ID');
    }
    
    if (!in_array($status, ['applied', 'screening', 'interview', 'offered', 'rejected'])) {
        throw new Exception('Invalid status');
    }
    
    // Verify application belongs to company
    $application = $db->fetch(
        "SELECT ja.*, j.title
         FROM job_applications ja
         JOIN jobs j ON ja.job_id = j.id
         WHERE ja.id = ? AND j.company_id = ?",
        [$application_id, $company_id]
    );
    
    if (!$application) {
        throw new Exception('Application not found or access denied');
    }
    
    // Update application status
    $db->execute(
        "UPDATE job_applications 
         SET status = ? 
         WHERE id = ?",
        [$status, $application_id]
    );
    
    // Log activity
    logActivity($user_id, 'application_status_updated', "Application status updated to: $status (Application ID: $application_id)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Application status updated successfully'
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

