<?php
// Assignment Questions API: manage questions and options for assignments
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';

function send_json($code, $payload) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// Require company session
function require_company() {
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'company') {
        send_json(401, ['success' => false, 'message' => 'Unauthorized: company login required']);
    }
}

// Ensure the assignment belongs to a job owned by this company
function ensure_assignment_ownership(Database $db, int $assignment_id, int $user_id) {
    // Resolve company id from user id
    $company = $db->fetch('SELECT id FROM companies WHERE user_id = ?', [$user_id]);
    if (!$company || !isset($company['id'])) {
        send_json(403, ['success' => false, 'message' => 'Company profile not found for current user']);
    }
    $company_id = (int)$company['id'];

    $row = $db->fetch(
        'SELECT a.id, j.company_id FROM assignments a JOIN jobs j ON a.job_id = j.id WHERE a.id = ?',
        [$assignment_id]
    );
    if (!$row) {
        send_json(404, ['success' => false, 'message' => 'Assignment not found']);
    }
    if ((int)$row['company_id'] !== $company_id) {
        send_json(403, ['success' => false, 'message' => 'You do not have permission to manage questions for this assignment']);
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $db = new Database();

    if ($method === 'GET') {
        require_company();
        $user_id = (int)$_SESSION['user_id'];
        $assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
        if ($assignment_id <= 0) {
            send_json(400, ['success' => false, 'message' => 'assignment_id is required']);
        }
        ensure_assignment_ownership($db, $assignment_id, $user_id);

        // Fetch questions and their options
        $questions = $db->fetchAll(
            'SELECT id, assignment_id, question_text, question_type, order_index FROM assignment_questions WHERE assignment_id = ? ORDER BY order_index, id',
            [$assignment_id]
        );
        $result = [];
        foreach ($questions ?: [] as $q) {
            $options = [];
            if ($q['question_type'] === 'multiple_choice') {
                $options = $db->fetchAll(
                    'SELECT id, option_text, order_index FROM assignment_question_options WHERE question_id = ? ORDER BY order_index, id',
                    [$q['id']]
                ) ?: [];
            }
            $result[] = [
                'id' => (int)$q['id'],
                'assignment_id' => (int)$q['assignment_id'],
                'question_text' => $q['question_text'],
                'question_type' => $q['question_type'],
                'order_index' => (int)$q['order_index'],
                'options' => $options,
            ];
        }
        send_json(200, ['success' => true, 'questions' => $result]);
    }

    if ($method === 'POST') {
        require_company();
        $user_id = (int)$_SESSION['user_id'];
        $assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
        $question_text = trim($_POST['question_text'] ?? '');
        $question_type = trim($_POST['question_type'] ?? '');
        $order_index = isset($_POST['order_index']) ? (int)$_POST['order_index'] : 0;
        $options_raw = $_POST['options'] ?? null; // may be array of strings or newline-separated string

        if ($assignment_id <= 0 || $question_text === '' || $question_type === '') {
            send_json(400, ['success' => false, 'message' => 'assignment_id, question_text and question_type are required']);
        }
        if (!in_array($question_type, ['text', 'file_upload', 'multiple_choice'], true)) {
            send_json(400, ['success' => false, 'message' => 'Invalid question_type']);
        }

        ensure_assignment_ownership($db, $assignment_id, $user_id);

        $db->beginTransaction();
        try {
            $db->execute(
                'INSERT INTO assignment_questions (assignment_id, question_text, question_type, order_index) VALUES (?, ?, ?, ?)',
                [$assignment_id, $question_text, $question_type, $order_index]
            );
            $question_id = (int)$db->lastInsertId();

            $options = [];
            if ($question_type === 'multiple_choice') {
                if (is_array($options_raw)) {
                    $options = $options_raw;
                } elseif (is_string($options_raw)) {
                    // split by newlines
                    $options = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $options_raw))));
                }
                foreach ($options as $idx => $opt_text) {
                    if ($opt_text === '') { continue; }
                    $db->execute(
                        'INSERT INTO assignment_question_options (question_id, option_text, order_index) VALUES (?, ?, ?)',
                        [$question_id, $opt_text, $idx]
                    );
                }
            }

            $db->commit();
            send_json(200, [
                'success' => true,
                'message' => 'Question created',
                'question_id' => $question_id,
            ]);
        } catch (Throwable $e) {
            $db->rollBack();
            send_json(500, ['success' => false, 'message' => 'Failed to create question: ' . $e->getMessage()]);
        }
    }

    // Method not allowed
    send_json(405, ['success' => false, 'message' => 'Method not allowed']);

} catch (Throwable $e) {
    error_log('Assignment Questions API error: ' . $e->getMessage());
    send_json(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

