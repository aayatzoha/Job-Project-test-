<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get assessment for taking
    try {
        $assessment_id = intval($_GET['assessment_id'] ?? 0);
        $user_id = $_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];
        
        if ($user_type !== 'job_seeker') {
            throw new Exception('Only job seekers can take assessments');
        }
        
        if ($assessment_id <= 0) {
            throw new Exception('Invalid assessment ID');
        }
        
        // Check if assessment exists and is active
        $assessment = $db->fetch(
            "SELECT * FROM assessments WHERE id = ? AND status = 'active'",
            [$assessment_id]
        );
        
        if (!$assessment) {
            throw new Exception('Assessment not found or inactive');
        }
        
        // Check if user has already completed this assessment
        $existingAttempt = $db->fetch(
            "SELECT * FROM user_assessments 
             WHERE user_id = ? AND assessment_id = ? AND status = 'completed'",
            [$user_id, $assessment_id]
        );
        
        if ($existingAttempt) {
            throw new Exception('Assessment already completed');
        }
        
        // Get or create user assessment session
        $userAssessment = getOrCreateUserAssessment($user_id, $assessment_id);
        
        // Get questions for this assessment
        $questions = getAssessmentQuestions($assessment_id);
        
        // Remove correct answers from questions (for security)
        foreach ($questions as &$question) {
            unset($question['correct_answer']);
        }
        
        echo json_encode([
            'success' => true,
            'assessment' => $assessment,
            'user_assessment' => $userAssessment,
            'questions' => $questions
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Submit answer or complete assessment
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $action = $input['action'] ?? 'submit_answer';
        $user_id = $_SESSION['user_id'];
        
        if ($action === 'submit_answer') {
            $result = submitAnswer($input, $user_id);
        } elseif ($action === 'complete_assessment') {
            $result = completeAssessment($input, $user_id);
        } else {
            throw new Exception('Invalid action');
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

/**
 * Get or create user assessment session
 */
function getOrCreateUserAssessment($user_id, $assessment_id, $job_application_id = null) {
    global $db;
    
    // Check for existing session
    $existing = $db->fetch(
        "SELECT * FROM user_assessments 
         WHERE user_id = ? AND assessment_id = ? AND status IN ('not_started', 'in_progress')",
        [$user_id, $assessment_id]
    );
    
    if ($existing) {
        return $existing;
    }
    
    // Create new assessment session
    $sql = "INSERT INTO user_assessments 
            (user_id, assessment_id, job_application_id, status, created_at)
            VALUES (?, ?, ?, 'not_started', NOW())";
    
    $db->query($sql, [$user_id, $assessment_id, $job_application_id]);
    
    return $db->fetch(
        "SELECT * FROM user_assessments WHERE id = ?",
        [$db->lastInsertId()]
    );
}

/**
 * Get assessment questions with proper ordering
 */
function getAssessmentQuestions($assessment_id) {
    global $db;
    
    return $db->fetchAll(
        "SELECT aq.*, aqm.order_index
         FROM assessment_questions aq
         JOIN assessment_questions_mapping aqm ON aq.id = aqm.question_id
         WHERE aqm.assessment_id = ?
         ORDER BY aqm.order_index ASC",
        [$assessment_id]
    );
}

/**
 * Submit answer to a question
 */
function submitAnswer($input, $user_id) {
    global $db;
    
    $required_fields = ['user_assessment_id', 'question_id', 'answer'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $user_assessment_id = intval($input['user_assessment_id']);
    $question_id = intval($input['question_id']);
    $answer = sanitizeInput($input['answer']);
    $time_taken = intval($input['time_taken'] ?? 0);
    
    // Verify user owns this assessment session
    $userAssessment = $db->fetch(
        "SELECT * FROM user_assessments WHERE id = ? AND user_id = ?",
        [$user_assessment_id, $user_id]
    );
    
    if (!$userAssessment) {
        throw new Exception('Assessment session not found');
    }
    
    // Update assessment status to in_progress if not started
    if ($userAssessment['status'] === 'not_started') {
        $db->query(
            "UPDATE user_assessments SET status = 'in_progress', started_at = NOW() WHERE id = ?",
            [$user_assessment_id]
        );
    }
    
    // Get question details for answer checking
    $question = $db->fetch(
        "SELECT * FROM assessment_questions WHERE id = ?",
        [$question_id]
    );
    
    if (!$question) {
        throw new Exception('Question not found');
    }
    
    // Check if answer is correct
    $is_correct = checkAnswerCorrectness($question, $answer);
    
    // Save or update answer
    $existingAnswer = $db->fetch(
        "SELECT id FROM user_assessment_answers 
         WHERE user_assessment_id = ? AND question_id = ?",
        [$user_assessment_id, $question_id]
    );
    
    if ($existingAnswer) {
        // Update existing answer
        $sql = "UPDATE user_assessment_answers 
                SET answer = ?, is_correct = ?, time_taken = ?, answered_at = NOW()
                WHERE id = ?";
        $db->query($sql, [$answer, $is_correct ? 1 : 0, $time_taken, $existingAnswer['id']]);
    } else {
        // Insert new answer
        $sql = "INSERT INTO user_assessment_answers 
                (user_assessment_id, question_id, answer, is_correct, time_taken, answered_at)
                VALUES (?, ?, ?, ?, ?, NOW())";
        $db->query($sql, [$user_assessment_id, $question_id, $answer, $is_correct ? 1 : 0, $time_taken]);
    }
    
    return [
        'success' => true,
        'message' => 'Answer submitted successfully',
        'is_correct' => $is_correct
    ];
}

/**
 * Complete assessment and calculate scores
 */
function completeAssessment($input, $user_id) {
    global $db;
    
    $user_assessment_id = intval($input['user_assessment_id'] ?? 0);
    $proctoring_data = $input['proctoring_data'] ?? [];
    
    if ($user_assessment_id <= 0) {
        throw new Exception('Invalid assessment session ID');
    }
    
    // Verify user owns this assessment session
    $userAssessment = $db->fetch(
        "SELECT ua.*, a.passing_score, a.total_questions
         FROM user_assessments ua
         JOIN assessments a ON ua.assessment_id = a.id
         WHERE ua.id = ? AND ua.user_id = ?",
        [$user_assessment_id, $user_id]
    );
    
    if (!$userAssessment) {
        throw new Exception('Assessment session not found');
    }
    
    if ($userAssessment['status'] === 'completed') {
        throw new Exception('Assessment already completed');
    }
    
    // Calculate scores
    $results = calculateAssessmentScore($user_assessment_id);
    
    $total_score = $results['total_score'];
    $percentage_score = $results['percentage_score'];
    $time_taken = $results['total_time_taken'];
    
    // Analyze proctoring data for cheating detection
    $cheating_flags = analyzeProctoringData($proctoring_data);
    
    // Update user assessment with final results
    $sql = "UPDATE user_assessments SET 
            status = 'completed',
            completed_at = NOW(),
            total_score = ?,
            percentage_score = ?,
            time_taken = ?,
            proctoring_data = ?,
            cheating_flags = ?
            WHERE id = ?";
    
    $db->query($sql, [
        $total_score,
        $percentage_score,
        $time_taken,
        json_encode($proctoring_data),
        json_encode($cheating_flags),
        $user_assessment_id
    ]);
    
    // Log activity
    logActivity($user_id, 'assessment_completed', 
               "Score: $percentage_score%, Time: {$time_taken}s");
    
    return [
        'success' => true,
        'message' => 'Assessment completed successfully',
        'results' => [
            'total_score' => $total_score,
            'percentage_score' => $percentage_score,
            'time_taken' => $time_taken,
            'passed' => $percentage_score >= $userAssessment['passing_score'],
            'passing_score' => $userAssessment['passing_score'],
            'cheating_flags' => $cheating_flags
        ]
    ];
}

/**
 * Check if answer is correct based on question type
 */
function checkAnswerCorrectness($question, $userAnswer) {
    $correctAnswer = $question['correct_answer'];
    $questionType = $question['question_type'];
    
    switch ($questionType) {
        case 'multiple_choice':
        case 'true_false':
            return strtolower(trim($userAnswer)) === strtolower(trim($correctAnswer));
            
        case 'coding':
            // For coding questions, this would need more sophisticated checking
            // For now, basic string comparison
            return trim($userAnswer) === trim($correctAnswer);
            
        case 'essay':
            // Essay questions require manual grading
            // For now, return true to allow submission
            return true;
            
        case 'practical':
            // Practical questions might need file uploads or specific formats
            return !empty(trim($userAnswer));
            
        default:
            return false;
    }
}

/**
 * Calculate assessment score
 */
function calculateAssessmentScore($user_assessment_id) {
    global $db;
    
    $answers = $db->fetchAll(
        "SELECT uaa.*, aq.points
         FROM user_assessment_answers uaa
         JOIN assessment_questions aq ON uaa.question_id = aq.id
         WHERE uaa.user_assessment_id = ?",
        [$user_assessment_id]
    );
    
    $total_points = 0;
    $earned_points = 0;
    $total_time = 0;
    
    foreach ($answers as $answer) {
        $points = intval($answer['points']);
        $total_points += $points;
        
        if ($answer['is_correct']) {
            $earned_points += $points;
        }
        
        $total_time += intval($answer['time_taken']);
    }
    
    $percentage = $total_points > 0 ? ($earned_points / $total_points) * 100 : 0;
    
    return [
        'total_score' => $earned_points,
        'max_score' => $total_points,
        'percentage_score' => round($percentage, 2),
        'total_time_taken' => $total_time
    ];
}

/**
 * Analyze proctoring data for cheating detection
 */
function analyzeProctoringData($proctoringData) {
    $flags = [];
    
    // Check for suspicious activities
    if (isset($proctoringData['tab_switches']) && $proctoringData['tab_switches'] > 3) {
        $flags[] = 'excessive_tab_switching';
    }
    
    if (isset($proctoringData['window_focus_lost']) && $proctoringData['window_focus_lost'] > 5) {
        $flags[] = 'frequent_window_focus_loss';
    }
    
    if (isset($proctoringData['fullscreen_exits']) && $proctoringData['fullscreen_exits'] > 2) {
        $flags[] = 'multiple_fullscreen_exits';
    }
    
    if (isset($proctoringData['face_detection_failures']) && $proctoringData['face_detection_failures'] > 10) {
        $flags[] = 'face_not_visible';
    }
    
    if (isset($proctoringData['multiple_faces_detected']) && $proctoringData['multiple_faces_detected'] > 0) {
        $flags[] = 'multiple_persons_detected';
    }
    
    if (isset($proctoringData['suspicious_movements']) && $proctoringData['suspicious_movements'] > 5) {
        $flags[] = 'suspicious_behavior';
    }
    
    return $flags;
}
?>