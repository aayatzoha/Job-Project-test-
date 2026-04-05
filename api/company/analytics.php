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
    if (!$company) { throw new Exception('Company profile not found'); }
    $cid = $company['id'];

    $summary = $db->fetch(
        "SELECT 
            (SELECT COUNT(*) FROM jobs WHERE company_id = ?) as total_jobs,
            (SELECT COUNT(*) FROM jobs WHERE company_id = ? AND status = 'active') as active_jobs,
            (SELECT COUNT(*) FROM job_applications ja JOIN jobs j ON ja.job_id = j.id WHERE j.company_id = ?) as total_applications",
        [$cid, $cid, $cid]
    );

    echo json_encode(['success' => true, 'analytics' => $summary]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


