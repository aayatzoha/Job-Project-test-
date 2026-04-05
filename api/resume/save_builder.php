<?php
header('Content-Type: application/json');
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
    $userId = $_SESSION['user_id'];
    if (getUserType() !== 'job_seeker') {
        throw new Exception('Only job seekers can save resume');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { throw new Exception('Invalid JSON'); }
    $summary = trim($input['summary'] ?? '');
    $skills = $input['skills'] ?? [];
    $experience = trim($input['experience'] ?? '');
    $education = trim($input['education'] ?? '');

    $resumeTextParts = array_filter([$summary, $experience, $education]);
    $resumeText = implode("\n\n", $resumeTextParts);
    $skillsJson = json_encode(array_values($skills));

    $sql = "UPDATE job_seekers SET resume_text = ?, skills = ? WHERE user_id = ?";
    $ok = $db->execute($sql, [$resumeText, $skillsJson, $userId]);
    if (!$ok) { throw new Exception('Failed to save resume'); }
    logActivity($userId, 'resume_builder_saved', 'Saved resume via builder');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


