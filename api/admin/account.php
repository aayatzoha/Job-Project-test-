<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();

if (!isset($_SESSION['user_id']) || getUserType() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    if ($action !== 'change_password') {
        throw new Exception('Invalid action');
    }
    $current = $input['current_password'] ?? '';
    $new = $input['new_password'] ?? '';
    if (strlen($new) < 6) {
        throw new Exception('New password too short');
    }
    $userId = $_SESSION['user_id'];
    $user = $db->fetch('SELECT id, password_hash FROM users WHERE id = ?', [$userId]);
    if (!$user || empty($user['password_hash'])) {
        throw new Exception('Account not found');
    }
    if (!verifyPassword($current, $user['password_hash'])) {
        throw new Exception('Current password is incorrect');
    }
    $newHash = hashPassword($new);
    $db->query('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?', [$newHash, $userId]);
    logActivity($userId, 'admin_password_changed', 'Admin updated own password');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


