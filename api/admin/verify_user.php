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
    $user_id = intval($input['user_id'] ?? 0);
    if ($user_id <= 0) {
        throw new Exception('Invalid user ID');
    }

    $user = $db->fetch("SELECT id, name, email, email_verified, status FROM users WHERE id = ?", [$user_id]);
    if (!$user) {
        throw new Exception('User not found');
    }

    $db->beginTransaction();
    try {
        $db->execute(
            "UPDATE users SET email_verified = TRUE, otp = NULL, otp_expires_at = NULL, updated_at = NOW() WHERE id = ?",
            [$user_id]
        );
        // Optionally ensure active status
        if ($user['status'] !== 'active') {
            $db->execute("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?", [$user_id]);
        }
        logActivity($_SESSION['user_id'], 'admin_email_verified', "Verified email for user ID $user_id");
        logActivity($user_id, 'email_verified', 'Verified by administrator');
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    echo json_encode(['success' => true, 'message' => 'User verified successfully']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


