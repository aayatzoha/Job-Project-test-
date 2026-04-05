<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $user_id = $_SESSION['user_id'];
    $user_assessment_id = intval($input['user_assessment_id'] ?? 0);
    $activity_type = sanitizeInput($input['activity_type'] ?? '');
    $details = sanitizeInput($input['details'] ?? '');
    $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');
    $proctoring_data = $input['proctoring_data'] ?? [];
    
    // Validate required fields
    if ($user_assessment_id <= 0) {
        throw new Exception('Invalid user assessment ID');
    }
    
    if (empty($activity_type)) {
        throw new Exception('Activity type is required');
    }
    
    // Verify user owns this assessment session
    $userAssessment = $db->fetch(
        "SELECT * FROM user_assessments WHERE id = ? AND user_id = ?",
        [$user_assessment_id, $user_id]
    );
    
    if (!$userAssessment) {
        throw new Exception('Assessment session not found or access denied');
    }
    
    // Log the activity in activity_logs table
    $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) 
            VALUES (?, ?, ?, ?)";
    $db->query($sql, [
        $user_id, 
        "proctoring_{$activity_type}", 
        json_encode([
            'user_assessment_id' => $user_assessment_id,
            'activity_type' => $activity_type,
            'details' => $details,
            'proctoring_data' => $proctoring_data,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]),
        $timestamp
    ]);
    
    // Update user assessment with latest proctoring data
    if (!empty($proctoring_data)) {
        $existingData = json_decode($userAssessment['proctoring_data'] ?? '{}', true);
        $mergedData = array_merge($existingData, $proctoring_data);
        
        $updateSql = "UPDATE user_assessments 
                      SET proctoring_data = ? 
                      WHERE id = ?";
        $db->query($updateSql, [json_encode($mergedData), $user_assessment_id]);
        
        // Check for automatic termination conditions
        checkForAutoTermination($user_assessment_id, $mergedData);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Activity logged successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Check if assessment should be automatically terminated due to violations
 */
function checkForAutoTermination($user_assessment_id, $proctoringData) {
    global $db;
    
    $violations = 0;
    $criticalViolations = [];
    
    // Define violation thresholds
    $thresholds = [
        'tab_switches' => ['limit' => 5, 'critical' => true],
        'window_focus_lost' => ['limit' => 8, 'critical' => false],
        'fullscreen_exits' => ['limit' => 3, 'critical' => true],
        'face_detection_failures' => ['limit' => 20, 'critical' => false],
        'multiple_faces_detected' => ['limit' => 3, 'critical' => true],
        'suspicious_movements' => ['limit' => 15, 'critical' => false]
    ];
    
    foreach ($thresholds as $type => $config) {
        if (isset($proctoringData[$type]) && $proctoringData[$type] > $config['limit']) {
            $violations++;
            if ($config['critical']) {
                $criticalViolations[] = $type;
            }
        }
    }
    
    // Auto-terminate conditions:
    // 1. More than 2 critical violations
    // 2. More than 5 total violations
    if (count($criticalViolations) >= 2 || $violations > 5) {
        terminateAssessment($user_assessment_id, $criticalViolations, $violations);
    }
}

/**
 * Terminate assessment due to violations
 */
function terminateAssessment($user_assessment_id, $criticalViolations, $totalViolations) {
    global $db;
    
    // Get user assessment details
    $userAssessment = $db->fetch(
        "SELECT ua.*, u.name, u.email, a.title as assessment_title
         FROM user_assessments ua
         JOIN users u ON ua.user_id = u.id  
         JOIN assessments a ON ua.assessment_id = a.id
         WHERE ua.id = ?",
        [$user_assessment_id]
    );
    
    if (!$userAssessment) return;
    
    // Mark assessment as flagged
    $sql = "UPDATE user_assessments 
            SET status = 'flagged',
                completed_at = NOW(),
                percentage_score = 0,
                cheating_flags = ?
            WHERE id = ?";
    
    $cheatingFlags = [
        'auto_terminated' => true,
        'termination_reason' => 'excessive_violations',
        'critical_violations' => $criticalViolations,
        'total_violations' => $totalViolations,
        'terminated_at' => date('Y-m-d H:i:s')
    ];
    
    $db->query($sql, [json_encode($cheatingFlags), $user_assessment_id]);
    
    // Log the termination
    logActivity($userAssessment['user_id'], 'assessment_auto_terminated', 
               "Assessment terminated due to {$totalViolations} violations");
    
    // Send notification to user
    $notificationSql = "INSERT INTO notifications (user_id, title, message, type, data, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())";
    
    $db->query($notificationSql, [
        $userAssessment['user_id'],
        'Assessment Terminated',
        "Your assessment '{$userAssessment['assessment_title']}' was terminated due to multiple policy violations.",
        'warning',
        json_encode([
            'assessment_id' => $userAssessment['assessment_id'],
            'user_assessment_id' => $user_assessment_id,
            'violations' => $cheatingFlags
        ])
    ]);
    
    // Notify administrators
    $adminUsers = $db->fetchAll("SELECT * FROM users WHERE user_type = 'admin'");
    foreach ($adminUsers as $admin) {
        $db->query($notificationSql, [
            $admin['id'],
            'Assessment Violation Alert',
            "User {$userAssessment['name']} ({$userAssessment['email']}) had their assessment auto-terminated due to violations.",
            'warning',
            json_encode([
                'user_id' => $userAssessment['user_id'],
                'assessment_id' => $userAssessment['assessment_id'],
                'violations' => $cheatingFlags
            ])
        ]);
    }
}
?>