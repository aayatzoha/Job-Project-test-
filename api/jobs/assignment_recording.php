<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Set error handling to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');

// Helper function to send JSON response
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Ensure uploads directory exists
$uploadsDir = '../../uploads/recordings';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Handle POST request (Start/Stop recording)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Unauthorized access');
    }

    try {
        // Parse input
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $action = isset($input['action']) ? $input['action'] : null;
        $assignment_id = isset($input['assignment_id']) ? intval($input['assignment_id']) : null;

        if (!$action || !$assignment_id) {
            sendResponse(false, 'Missing required parameters');
        }

        // Check if assignment exists and requires camera
        $stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ? AND requires_camera = 1");
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendResponse(false, 'Assignment not found or does not require camera');
        }
        
        $assignment = $result->fetch_assoc();
        
        // Check if user is assigned to this assignment
        if ($_SESSION['user_id'] != $assignment['assigned_to']) {
            sendResponse(false, 'You are not assigned to this assignment');
        }

        // Start recording
        if ($action === 'start') {
            // Create submission if not exists
            $stmt = $conn->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, user_id, status) VALUES (?, ?, 'submitted')");
                $stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
                $stmt->execute();
                $submission_id = $stmt->insert_id;
            } else {
                $submission = $result->fetch_assoc();
                $submission_id = $submission['id'];
            }
            
            // Create recording entry
            $video_path = 'uploads/recordings/' . uniqid('recording_') . '.webm';
            $stmt = $conn->prepare("INSERT INTO assignment_recordings (submission_id, video_path) VALUES (?, ?)");
            $stmt->bind_param("is", $submission_id, $video_path);
            $stmt->execute();
            $recording_id = $stmt->insert_id;
            
            sendResponse(true, 'Recording started', [
                'recording_id' => $recording_id,
                'video_path' => $video_path
            ]);
        }
        // End recording
        elseif ($action === 'stop') {
            $recording_id = isset($input['recording_id']) ? intval($input['recording_id']) : null;
            
            if (!$recording_id) {
                sendResponse(false, 'Missing recording ID');
            }
            
            // Update recording status
            $stmt = $conn->prepare("UPDATE assignment_recordings SET status = 'completed', end_time = CURRENT_TIMESTAMP, duration = TIMESTAMPDIFF(SECOND, start_time, CURRENT_TIMESTAMP) WHERE id = ?");
            $stmt->bind_param("i", $recording_id);
            $stmt->execute();
            
            sendResponse(true, 'Recording completed');
        }
        // Save recording data
        elseif ($action === 'save') {
            $recording_id = isset($input['recording_id']) ? intval($input['recording_id']) : null;
            
            if (!$recording_id) {
                sendResponse(false, 'Missing recording ID');
            }
            
            // Get recording path
            $stmt = $conn->prepare("SELECT video_path FROM assignment_recordings WHERE id = ?");
            $stmt->bind_param("i", $recording_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                sendResponse(false, 'Recording not found');
            }
            
            $recording = $result->fetch_assoc();
            $video_path = '../../' . $recording['video_path'];
            
            // Save video data
            if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                if (move_uploaded_file($_FILES['video']['tmp_name'], $video_path)) {
                    sendResponse(true, 'Recording saved successfully');
                } else {
                    sendResponse(false, 'Failed to save recording');
                }
            } elseif (isset($input['blob'])) {
                // Handle base64 encoded video data
                $blob = $input['blob'];
                $data = base64_decode(preg_replace('#^data:video/\w+;base64,#i', '', $blob));
                
                if (file_put_contents($video_path, $data)) {
                    sendResponse(true, 'Recording saved successfully');
                } else {
                    sendResponse(false, 'Failed to save recording');
                }
            } else {
                sendResponse(false, 'No video data provided');
            }
        } else {
            sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage());
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get recording
    try {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            sendResponse(false, 'Unauthorized access');
        }
        
        $recording_id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if (!$recording_id) {
            sendResponse(false, 'Missing recording ID');
        }
        
        // Get recording
        $stmt = $conn->prepare("
            SELECT r.*, s.assignment_id, s.user_id 
            FROM assignment_recordings r 
            JOIN assignment_submissions s ON r.submission_id = s.id 
            WHERE r.id = ?
        ");
        $stmt->bind_param("i", $recording_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendResponse(false, 'Recording not found');
        }
        
        $recording = $result->fetch_assoc();
        
        // Check if user has permission to view this recording
        if ($_SESSION['user_type'] === 'user' && $_SESSION['user_id'] != $recording['user_id']) {
            sendResponse(false, 'You do not have permission to view this recording');
        }
        
        if ($_SESSION['user_type'] === 'company') {
            // Check if company owns the job
            $stmt = $conn->prepare("
                SELECT j.company_id 
                FROM assignments a 
                JOIN jobs j ON a.job_id = j.id 
                WHERE a.id = ?
            ");
            $stmt->bind_param("i", $recording['assignment_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                sendResponse(false, 'Assignment not found');
            }
            
            $job = $result->fetch_assoc();
            
            if ($job['company_id'] != $_SESSION['company_id']) {
                sendResponse(false, 'You do not have permission to view this recording');
            }
        }
        
        sendResponse(true, 'Recording retrieved successfully', $recording);
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage());
    }
} else {
    sendResponse(false, 'Invalid request method');
}
?>