<?php
// Debug version of chatbot API
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

$debug_messages = [];

try {
    require_once __DIR__ . '/../../config/database.php';
    $debug_messages[] = 'Database loaded';
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

try {
    require_once __DIR__ . '/../../includes/functions.php';
    $debug_messages[] = 'Functions loaded';
} catch (Exception $e) {
    echo json_encode(['error' => 'Functions error: ' . $e->getMessage()]);
    exit;
}

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in', 'session' => $_SESSION]);
    exit;
}

$debug_messages[] = 'User authenticated: ' . $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['error' => 'No JSON input', 'raw_input' => file_get_contents('php://input')]);
            exit;
        }
        
        $message = $input['message'] ?? '';
        
        if (empty($message)) {
            echo json_encode(['error' => 'No message provided', 'input' => $input]);
            exit;
        }
        
        // Simple response without complex processing
        echo json_encode([
            'success' => true,
            'debug_messages' => $debug_messages,
            'response' => [
                'message' => "Hello! You said: " . $message,
                'message_type' => 'text',
                'suggestions' => ['Tell me more', 'Help me find jobs', 'Career advice']
            ],
            'session_id' => 'debug_' . time()
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Processing error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Method not allowed', 'method' => $_SERVER['REQUEST_METHOD']]);
}
?>