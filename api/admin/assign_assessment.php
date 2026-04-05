<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || getUserType() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $targetUserId = intval($input['user_id'] ?? 0);
    $assessmentId = intval($input['assessment_id'] ?? 0);
    if ($targetUserId <= 0 || $assessmentId <= 0) {
        throw new Exception('user_id and assessment_id are required');
    }

    // Validate assessment
    $assessment = $db->fetch("SELECT id, title FROM assessments WHERE id = ? AND status IN ('active','draft')", [$assessmentId]);
    if (!$assessment) {
        throw new Exception('Assessment not found');
    }

    // Create assignment if not exists
    $existing = $db->fetch("SELECT id FROM user_assessments WHERE user_id = ? AND assessment_id = ?", [$targetUserId, $assessmentId]);
    if ($existing) {
        echo json_encode(['success' => true, 'message' => 'Assessment already assigned']);
        exit();
    }

    $db->execute(
        "INSERT INTO user_assessments (user_id, assessment_id, status, created_at) VALUES (?, ?, 'not_started', NOW())",
        [$targetUserId, $assessmentId]
    );

    logActivity($_SESSION['user_id'], 'admin_assigned_assessment', "Assigned assessment {$assessmentId} to user {$targetUserId}");

    echo json_encode(['success' => true, 'message' => 'Assessment assigned']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


