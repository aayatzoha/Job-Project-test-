<?php
header('Content-Type: application/json');
// Allow both GET and POST for flexibility in links and fetches
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Handle CORS preflight (if ever needed); return early for OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['success' => true]);
    exit();
}

session_start();

try {
    // If no session, still return success so client can clean state
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    if ($user_id) {
        // Log logout activity
        logActivity($user_id, 'logout', 'User logged out');
        // Update last activity
        updateLastActivity($user_id);
    }

    // Clear all session data
    $_SESSION = [];
    
    // Destroy session
    if (session_id()) {
        session_destroy();
    }
    
    // Clear session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
