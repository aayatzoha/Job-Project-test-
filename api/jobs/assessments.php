<?php
header('Content-Type: application/json');
// Same-origin only so session cookies are sent

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

try {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['role'] ?? $_SESSION['user_type'];
    
    if ($user_type !== 'job_seeker') {
        throw new Exception('Only job seekers can access assessments');
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Start an assessment
        $input = json_decode(file_get_contents('php://input'), true);
        $assessment_id = $input['assessment_id'] ?? null;
        $proctored = !empty($input['proctored']);
        $shuffle = !empty($input['shuffle']);
        
        if (!$assessment_id) {
            throw new Exception('Assessment ID is required');
        }
        
        // Check if assessment exists
        $assessment = $db->fetch(
            "SELECT * FROM assessments WHERE id = ? AND status = 'active'",
            [$assessment_id]
        );
        
        if (!$assessment) {
            throw new Exception('Assessment not found or inactive');
        }
        
        // Check if user has already taken this assessment
        $existing = $db->fetch(
            "SELECT * FROM user_assessments WHERE user_id = ? AND assessment_id = ?",
            [$user_id, $assessment_id]
        );
        
        if ($existing) {
            if ($existing['status'] === 'completed') {
                throw new Exception('You have already completed this assessment');
            } else {
                // Continue existing assessment
                echo json_encode([
                    'success' => true,
                    'message' => 'Continuing existing assessment',
                    'user_assessment_id' => $existing['id']
                ]);
                return;
            }
        }
        
        // Create new user assessment
        $proctoringData = $proctored ? json_encode(['proctoring' => 'started', 'shuffle' => $shuffle]) : null;
        $sql = "INSERT INTO user_assessments (user_id, assessment_id, status, started_at, created_at, proctoring_data) 
                VALUES (?, ?, 'in_progress', NOW(), NOW(), ?)";
        
        $db->execute($sql, [$user_id, $assessment_id, $proctoringData]);
        $user_assessment_id = $db->getConnection()->lastInsertId();
        
        // Log activity
        logActivity($user_id, 'assessment_started', "Started: {$assessment['title']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Assessment started successfully',
            'user_assessment_id' => $user_assessment_id
        ]);
        
    } else {
        // Get available assessments
        $type = $_GET['type'] ?? 'available'; // available, assigned, history
        $skill_type = $_GET['skill_type'] ?? '';
        
        if ($type === 'assigned') {
            // Get assessments assigned to the user
            $sql = "SELECT ua.*, ua.id as user_assessment_id, ua.status,
                           a.id, a.title, a.description, a.time_limit, a.total_questions,
                           ac.name AS category_name, ac.skill_type
                    FROM user_assessments ua
                    JOIN assessments a ON ua.assessment_id = a.id
                    LEFT JOIN assessment_categories ac ON a.category_id = ac.id
                    WHERE ua.user_id = ? AND ua.status IN ('not_started', 'in_progress', 'completed')
                    ORDER BY 
                        CASE ua.status
                            WHEN 'not_started' THEN 1
                            WHEN 'in_progress' THEN 2
                            WHEN 'completed' THEN 3
                        END,
                        ua.created_at DESC";
            
            $assessments = $db->fetchAll($sql, [$user_id]);
            
            $results = [];
            foreach ($assessments as $assessment) {
                $statusDisplay = ucfirst(str_replace('_', ' ', $assessment['status']));
                $results[] = [
                    'id' => $assessment['id'],
                    'user_assessment_id' => $assessment['user_assessment_id'],
                    'title' => $assessment['title'],
                    'description' => $assessment['description'],
                    'category' => $assessment['category_name'] ?? '',
                    'time_limit' => $assessment['time_limit'],
                    'question_count' => $assessment['total_questions'],
                    'time_display' => gmdate("H:i", (int)$assessment['time_limit']),
                    'status' => $assessment['status'],
                    'status_display' => $statusDisplay,
                    'started_at' => $assessment['started_at'] ?? null,
                    'completed_at' => $assessment['completed_at'] ?? null
                ];
            }
            
            echo json_encode([
                'success' => true,
                'assessments' => $results
            ]);
            return;
            
        } elseif ($type === 'available') {
            // Get assessments not yet taken
            $sql = "SELECT a.*, ac.name AS category_name, ac.skill_type,
                           (SELECT COUNT(*) FROM user_assessments ua WHERE ua.assessment_id = a.id AND ua.user_id = ?) as taken
                    FROM assessments a
                    LEFT JOIN assessment_categories ac ON a.category_id = ac.id
                    WHERE a.status = 'active'" . (!empty($skill_type) ? " AND ac.skill_type = ?" : "") . "
                    ORDER BY ac.name, a.title";
            
            $assessments = !empty($skill_type) ? $db->fetchAll($sql, [$user_id, $skill_type]) : $db->fetchAll($sql, [$user_id]);
            
            // Filter out taken assessments
            $available = array_filter($assessments, function($assessment) {
                return $assessment['taken'] == 0;
            });
            
            $results = [];
            foreach ($available as $assessment) {
                $results[] = [
                    'id' => $assessment['id'],
                    'title' => $assessment['title'],
                    'description' => $assessment['description'],
                    'category' => $assessment['category_name'] ?? '',
                    'time_limit' => $assessment['time_limit'],
                    'question_count' => $assessment['total_questions'],
                    'difficulty' => 'Medium',
                    'time_display' => gmdate("H:i", (int)$assessment['time_limit'])
                ];
            }
            
        } elseif ($type === 'history') {
            // Get user's assessment history
            $sql = "SELECT ua.*, a.title, a.description, a.time_limit, ac.name AS category_name,
                           ua.percentage_score, ua.status, ua.started_at, ua.completed_at
                    FROM user_assessments ua
                    JOIN assessments a ON ua.assessment_id = a.id
                    LEFT JOIN assessment_categories ac ON a.category_id = ac.id
                    WHERE ua.user_id = ?
                    ORDER BY ua.created_at DESC";
            
            $history = $db->fetchAll($sql, [$user_id]);
            
            $results = [];
            foreach ($history as $assessment) {
                $results[] = [
                    'id' => $assessment['id'],
                    'assessment_id' => $assessment['assessment_id'],
                    'title' => $assessment['title'],
                    'description' => $assessment['description'],
                    'category' => $assessment['category_name'] ?? '',
                    'status' => $assessment['status'],
                    'score' => $assessment['percentage_score'],
                    'started_at' => $assessment['started_at'],
                    'completed_at' => $assessment['completed_at'],
                    'time_taken' => calculateTimeTaken($assessment['started_at'], $assessment['completed_at']),
                    'status_display' => ucfirst(str_replace('_', ' ', $assessment['status']))
                ];
            }
            
        } else {
            // Get in-progress assessments
            $sql = "SELECT ua.*, a.title, a.description, a.time_limit, ac.name AS category_name
                    FROM user_assessments ua
                    JOIN assessments a ON ua.assessment_id = a.id
                    LEFT JOIN assessment_categories ac ON a.category_id = ac.id
                    WHERE ua.user_id = ? AND ua.status = 'in_progress'
                    ORDER BY ua.started_at DESC";
            
            $in_progress = $db->fetchAll($sql, [$user_id]);
            
            $results = [];
            foreach ($in_progress as $assessment) {
                $results[] = [
                    'id' => $assessment['id'],
                    'assessment_id' => $assessment['assessment_id'],
                    'title' => $assessment['title'],
                    'description' => $assessment['description'],
                    'category' => $assessment['category_name'] ?? '',
                    'status' => $assessment['status'],
                    'started_at' => $assessment['started_at'],
                    'time_remaining' => calculateTimeRemaining($assessment['started_at'], $assessment['time_limit'])
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'assessments' => $results,
            'type' => $type,
            'count' => count($results)
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Calculate time taken for completed assessment
 */
function calculateTimeTaken($started_at, $completed_at) {
    if (!$completed_at) {
        return null;
    }
    
    $start = new DateTime($started_at);
    $end = new DateTime($completed_at);
    $interval = $start->diff($end);
    
    $minutes = $interval->i + ($interval->h * 60);
    return $minutes . ' minutes';
}

/**
 * Calculate time remaining for in-progress assessment
 */
function calculateTimeRemaining($started_at, $time_limit) {
    $start = new DateTime($started_at);
    $now = new DateTime();
    $elapsed = $start->diff($now);
    $elapsed_minutes = $elapsed->i + ($elapsed->h * 60) + ($elapsed->days * 24 * 60);
    
    $time_limit_minutes = $time_limit / 60; // Convert from seconds to minutes
    $remaining = max(0, $time_limit_minutes - $elapsed_minutes);
    
    return $remaining . ' minutes';
}
?>