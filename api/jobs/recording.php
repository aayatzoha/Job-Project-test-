<?php
// Error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $error['message']]);
        exit;
    }
});

// Include database connection
require_once '../../config/database.php';
require_once '../../utils/auth.php';

// Create database connection
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
$user = checkAuth();
if (!$user) {
    sendResponse(false, 'Unauthorized access');
}

$user_id = $user['id'];
$user_type = $user['user_type'];

// Create uploads directory if it doesn't exist
$upload_dir = '../../uploads/recordings/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle request based on method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Parse input
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        // Check action
        $action = isset($input['action']) ? $input['action'] : '';
        
        if ($action === 'start') {
            // Start recording
            if (!isset($input['assignment_id'])) {
                sendResponse(false, 'Missing assignment ID');
            }
            
            $assignment_id = intval($input['assignment_id']);
            
            // Check if assignment exists and requires camera
            $stmt = $conn->prepare("SELECT requires_camera FROM assignments WHERE id = ?");
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                sendResponse(false, 'Assignment not found');
            }
            
            $assignment = $result->fetch_assoc();
            if (!$assignment['requires_camera']) {
                sendResponse(false, 'This assignment does not require camera recording');
            }
            
            // Create recording entry
            $stmt = $conn->prepare("INSERT INTO assignment_recordings (assignment_id, user_id, status) VALUES (?, ?, 'recording')");
            $stmt->bind_param("ii", $assignment_id, $user_id);
            $stmt->execute();
            
            $recording_id = $conn->insert_id;
            sendResponse(true, 'Recording started', ['recording_id' => $recording_id]);
        }
        else if ($action === 'stop') {
            // Stop recording
            if (!isset($input['recording_id'])) {
                sendResponse(false, 'Missing recording ID');
            }
            
            $recording_id = intval($input['recording_id']);
            
            // Update recording status
            $stmt = $conn->prepare("UPDATE assignment_recordings SET status = 'completed', end_time = NOW(), duration = TIMESTAMPDIFF(SECOND, start_time, NOW()) WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $recording_id, $user_id);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                sendResponse(false, 'Recording not found or not owned by user');
            }
            
            sendResponse(true, 'Recording stopped');
        }
        else if ($action === 'save') {
            // Save recording file
            if (!isset($_FILES['video']) || !isset($_POST['recording_id'])) {
                sendResponse(false, 'Missing video file or recording ID');
            }
            
            $recording_id = intval($_POST['recording_id']);
            
            // Check if recording exists and belongs to user
            $stmt = $conn->prepare("SELECT * FROM assignment_recordings WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $recording_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                sendResponse(false, 'Recording not found or not owned by user');
            }
            
            // Save file
            $file = $_FILES['video'];
            $filename = 'recording_' . $recording_id . '_' . time() . '.webm';
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Update recording with file path
                $relative_path = 'uploads/recordings/' . $filename;
                $stmt = $conn->prepare("UPDATE assignment_recordings SET video_path = ? WHERE id = ?");
                $stmt->bind_param("si", $relative_path, $recording_id);
                $stmt->execute();
                
                sendResponse(true, 'Recording saved', ['path' => $relative_path]);
            } else {
                sendResponse(false, 'Failed to save recording');
            }
        }
        else {
            sendResponse(false, 'Invalid action');
        }
        break;
        
    case 'GET':
        // Get recording details
        if (isset($_GET['id'])) {
            $recording_id = intval($_GET['id']);
            
            // Check if user has access to this recording
            if ($user_type === 'job_seeker') {
                // Job seekers can only access their own recordings
                $stmt = $conn->prepare("SELECT * FROM assignment_recordings WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $recording_id, $user_id);
            } else if ($user_type === 'company' || $user_type === 'admin') {
                // Companies can access recordings for assignments they created
                $stmt = $conn->prepare("
                    SELECT r.* FROM assignment_recordings r
                    JOIN assignments a ON r.assignment_id = a.id
                    WHERE r.id = ? AND (a.created_by = ? OR ? = 'admin')
                ");
                $stmt->bind_param("iis", $recording_id, $user_id, $user_type);
            } else {
                sendResponse(false, 'Unauthorized access');
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                sendResponse(false, 'Recording not found or access denied');
            }
            
            $recording = $result->fetch_assoc();
            sendResponse(true, 'Recording details', $recording);
        }
        else if (isset($_GET['assignment_id'])) {
            $assignment_id = intval($_GET['assignment_id']);
            
            // Check if user has access to this assignment's recordings
            if ($user_type === 'job_seeker') {
                // Job seekers can only access their own recordings
                $stmt = $conn->prepare("
                    SELECT r.* FROM assignment_recordings r
                    JOIN assignments a ON r.assignment_id = a.id
                    WHERE a.id = ? AND r.user_id = ?
                ");
                $stmt->bind_param("ii", $assignment_id, $user_id);
            } else if ($user_type === 'company' || $user_type === 'admin') {
                // Companies can access recordings for assignments they created
                $stmt = $conn->prepare("
                    SELECT r.* FROM assignment_recordings r
                    JOIN assignments a ON r.assignment_id = a.id
                    WHERE a.id = ? AND (a.created_by = ? OR ? = 'admin')
                ");
                $stmt->bind_param("iis", $assignment_id, $user_id, $user_type);
            } else {
                sendResponse(false, 'Unauthorized access');
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $recordings = [];
            while ($row = $result->fetch_assoc()) {
                $recordings[] = $row;
            }
            
            sendResponse(true, 'Assignment recordings', $recordings);
        }
        else {
            sendResponse(false, 'Missing recording ID or assignment ID');
        }
        break;
        
    default:
        sendResponse(false, 'Method not allowed');
}

// Helper function to send JSON response
function sendResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}
?>