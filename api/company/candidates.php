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
    // Company ID
    $company = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id]);
    if (!$company) { throw new Exception('Company profile not found'); }
    $company_id = $company['id'];

    // If a specific candidate_id is requested, return detailed profile
    $candidate_id = isset($_GET['candidate_id']) ? intval($_GET['candidate_id']) : 0;
    if ($candidate_id > 0) {
        // Ensure candidate has applied to this company's jobs
        $applied = $db->fetch(
            "SELECT 1 FROM job_applications ja JOIN jobs j ON ja.job_id = j.id WHERE ja.job_seeker_id = ? AND j.company_id = ? LIMIT 1",
            [$candidate_id, $company_id]
        );
        if (!$applied) {
            throw new Exception('Candidate not found in your applications');
        }

        $candidate = $db->fetch(
            "SELECT js.id as job_seeker_id, u.id as user_id, u.name as full_name, u.email, u.phone, 
                    js.location, js.experience_years, js.skills, js.resume_file, js.bio, js.linkedin_url, js.portfolio_url
             FROM job_seekers js
             JOIN users u ON js.user_id = u.id
             WHERE js.id = ?",
            [$candidate_id]
        );
        echo json_encode(['success' => true, 'candidate' => $candidate]);
        exit;
    }

    // Otherwise, list candidates who applied to this company's jobs
    $candidates = $db->fetchAll(
        "SELECT DISTINCT js.id as job_seeker_id, u.id as user_id, u.name, u.email, js.location, js.experience_years
         FROM job_applications ja
         JOIN jobs j ON ja.job_id = j.id
         JOIN job_seekers js ON ja.job_seeker_id = js.id
         JOIN users u ON js.user_id = u.id
         WHERE j.company_id = ?
         ORDER BY ja.application_date DESC
         LIMIT 100",
        [$company_id]
    );

    echo json_encode(['success' => true, 'candidates' => $candidates]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


