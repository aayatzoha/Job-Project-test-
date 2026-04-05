<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    requireLogin();
    if (getUserType() !== 'job_seeker') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Job seeker access required']);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $db = new Database();

    // Distinct companies from applications
    $sql = "SELECT DISTINCT c.id, c.company_name, c.industry, c.headquarters, c.website
            FROM job_applications ja
            JOIN jobs j ON ja.job_id = j.id
            JOIN companies c ON j.company_id = c.id
            WHERE ja.job_seeker_id = ?
            ORDER BY c.company_name";
    $companies = $db->fetchAll($sql, [$user_id]);

    echo json_encode(['success' => true, 'companies' => $companies ?: []]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

