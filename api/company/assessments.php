<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

if ($user_type !== 'company') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Companies only.']);
    exit();
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetAssessments($user_id);
            break;
        case 'POST':
            handleCreateAssessment($user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetAssessments($user_id) {
    global $db;

    // Optional skill type filter
    $skillType = isset($_GET['skill_type']) ? sanitizeInput($_GET['skill_type']) : '';

    // List assessments created by this company user, optionally filtered by skill type
    $sql = "SELECT a.*, ac.name as category_name, ac.skill_type,
                (SELECT COUNT(*) FROM user_assessments ua WHERE ua.assessment_id = a.id) as total_attempts
            FROM assessments a
            LEFT JOIN assessment_categories ac ON a.category_id = ac.id
            WHERE a.created_by = ?" . (!empty($skillType) ? " AND ac.skill_type = ?" : "") . "
            ORDER BY a.created_at DESC";

    $list = !empty($skillType) ? $db->fetchAll($sql, [$user_id, $skillType]) : $db->fetchAll($sql, [$user_id]);

    echo json_encode(['success' => true, 'assessments' => $list]);
}

function handleCreateAssessment($user_id) {
    global $db;

    // Check if this is a multipart/form-data request (file upload)
    $isMultipart = !empty($_FILES);
    
    if ($isMultipart) {
        // Handle FormData (file upload)
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $skill_type = sanitizeInput($_POST['skill_type'] ?? '');
        $time_limit = intval($_POST['time_limit'] ?? 0);
        $passing_score = floatval($_POST['passing_score'] ?? 0);
        $is_public = isset($_POST['is_public']) && $_POST['is_public'] === '1';
        $status = sanitizeInput($_POST['status'] ?? 'active');
        $questions_json = $_POST['questions'] ?? '[]';
        $questions = json_decode($questions_json, true);
        
        // Handle file upload
        $quiz_file_path = null;
        if (isset($_FILES['quiz_file']) && $_FILES['quiz_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/assessments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['quiz_file'];
            $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'json', 'csv'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Allowed: PDF, DOC, DOCX, TXT, JSON, CSV');
            }
            
            if ($file['size'] > 10 * 1024 * 1024) { // 10MB max
                throw new Exception('File size exceeds 10MB limit');
            }
            
            $file_name = uniqid('quiz_', true) . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $quiz_file_path = 'uploads/assessments/' . $file_name;
            } else {
                throw new Exception('Failed to upload file');
            }
        }
    } else {
        // Handle JSON request (backward compatibility)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception('Invalid input');
        }
        
        $title = sanitizeInput($input['title'] ?? '');
        $description = sanitizeInput($input['description'] ?? '');
        $skill_type = sanitizeInput($input['skill_type'] ?? '');
        $time_limit = intval($input['time_limit'] ?? 0);
        $passing_score = floatval($input['passing_score'] ?? 0);
        $is_public = isset($input['is_public']) && $input['is_public'] === true;
        $status = sanitizeInput($input['status'] ?? 'active');
        $questions = $input['questions'] ?? [];
        $quiz_file_path = null;
    }

    // Validate required fields
    $required_fields = ['title', 'description', 'skill_type', 'time_limit', 'passing_score', 'questions'];
    foreach ($required_fields as $field) {
        if ($field === 'questions') {
            if (empty($questions) || !is_array($questions)) {
                throw new Exception("Field '$field' is required and must be an array");
            }
        } else {
            $value = $$field;
            if (empty($value) && $value !== '0') {
                throw new Exception("Field '$field' is required");
            }
        }
    }

    if (!is_array($questions) || empty($questions)) {
        throw new Exception('Assessment must have at least one question');
    }

    // Validate skill type
    $valid_skill_types = ['technical', 'soft', 'aptitude', 'domain'];
    if (!in_array($skill_type, $valid_skill_types)) {
        throw new Exception('Invalid skill type');
    }

    // Get or create category for this skill type
    $category = $db->fetch(
        "SELECT id FROM assessment_categories WHERE skill_type = ? LIMIT 1",
        [$skill_type]
    );

    if (!$category) {
        // Create a default category for this skill type
        $category_name = ucfirst($skill_type) . ' Skills';
        $db->execute(
            "INSERT INTO assessment_categories (name, skill_type, created_at) VALUES (?, ?, NOW())",
            [$category_name, $skill_type]
        );
        $category_id = $db->lastInsertId();
    } else {
        $category_id = $category['id'];
    }

    $db->getConnection()->beginTransaction();

    try {
        // Generate share link if public
        $share_token = null;
        $share_link = null;
        if ($is_public) {
            $share_token = bin2hex(random_bytes(16));
            $share_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                         '://' . $_SERVER['HTTP_HOST'] . 
                         dirname(dirname($_SERVER['PHP_SELF'])) . 
                         '/quiz.php?token=' . $share_token;
        }
        
        // Create assessment - check if quiz_file and is_public columns exist
        // First, try with new columns, fallback to old structure
        try {
            $sql = "INSERT INTO assessments (title, description, category_id,
                    total_questions, time_limit, passing_score, is_proctored,
                    status, created_by, quiz_file, is_public, share_token, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, NOW())";

            $db->execute($sql, [
                $title, $description, $category_id,
                count($questions), $time_limit, $passing_score,
                $status, $user_id, $quiz_file_path, $is_public ? 1 : 0, $share_token
            ]);
        } catch (Exception $e) {
            // If columns don't exist, use old structure
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $sql = "INSERT INTO assessments (title, description, category_id,
                        total_questions, time_limit, passing_score, is_proctored,
                        status, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())";

                $db->execute($sql, [
                    $title, $description, $category_id,
                    count($questions), $time_limit, $passing_score,
                    $status, $user_id
                ]);
                
                // Store file path and public status in a separate table or JSON field if needed
                // For now, we'll just note that these features require DB update
            } else {
                throw $e;
            }
        }

        $assessment_id = $db->lastInsertId();

        // Add questions
        foreach ($questions as $index => $question) {
            $question_id = createOrGetQuestion($question, $category_id);

            // Map question to assessment
            $mapSql = "INSERT INTO assessment_questions_mapping
                      (assessment_id, question_id, order_index)
                      VALUES (?, ?, ?)";
            $db->execute($mapSql, [$assessment_id, $question_id, $index + 1]);
        }

        $db->getConnection()->commit();

        // Log activity
        logActivity($user_id, 'assessment_created', "Assessment: $title");

        $response = [
            'success' => true,
            'message' => 'Assessment created successfully',
            'assessment_id' => $assessment_id
        ];
        
        if ($is_public && $share_link) {
            $response['share_link'] = $share_link;
            $response['share_token'] = $share_token;
        }
        
        if ($quiz_file_path) {
            $response['quiz_file'] = $quiz_file_path;
        }
        
        echo json_encode($response);

    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        throw $e;
    }
}

/**
 * Create or get existing question
 */
function createOrGetQuestion($questionData, $category_id) {
    global $db;

    $question_text = sanitizeInput($questionData['question_text']);
    $options = isset($questionData['options']) ? $questionData['options'] : [];

    if (empty($options) || !is_array($options)) {
        throw new Exception('Question must have options');
    }

    // Find the correct answer
    $correct_answer = '';
    foreach ($options as $index => $option) {
        if (isset($option['is_correct']) && $option['is_correct']) {
            $correct_answer = $option['text'];
            break;
        }
    }

    if (empty($correct_answer)) {
        throw new Exception('Question must have a correct answer');
    }

    // Check if question already exists
    $existing = $db->fetch(
        "SELECT id FROM assessment_questions WHERE question_text = ? AND category_id = ?",
        [$question_text, $category_id]
    );

    if ($existing) {
        return $existing['id'];
    }

    // Create new question
    $sql = "INSERT INTO assessment_questions (category_id, question_text, question_type, options,
            correct_answer, difficulty_level, time_limit, points, created_at)
            VALUES (?, ?, 'multiple_choice', ?, ?, 'medium', 60, 1, NOW())";

    $db->execute($sql, [
        $category_id,
        $question_text,
        json_encode($options),
        $correct_answer
    ]);

    return $db->lastInsertId();
}
?>


