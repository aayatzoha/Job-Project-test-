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
    $user_ids = $input['user_ids'] ?? [];
    if (!is_array($user_ids) || empty($user_ids)) {
        throw new Exception('No users provided');
    }

    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $db->beginTransaction();
    try {
        $db->execute("UPDATE users SET email_verified = TRUE, otp = NULL, otp_expires_at = NULL, updated_at = NOW() WHERE id IN ($placeholders)", $user_ids);
        $db->execute("UPDATE users SET status = 'active', updated_at = NOW() WHERE id IN ($placeholders)", $user_ids);
        foreach ($user_ids as $uid) {
            logActivity($_SESSION['user_id'], 'admin_email_verified', "Verified email for user ID $uid (bulk)");
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    echo json_encode(['success' => true, 'message' => 'Users verified successfully']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


