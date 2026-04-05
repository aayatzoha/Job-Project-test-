<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    requireLogin();
    if (getUserType() !== 'job_seeker') {
        throw new Exception('Only job seekers can submit assignments', 403);
    }

    $assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
    if ($assignment_id <= 0) {
        throw new Exception('Invalid assignment ID');
    }

    $user_id = $_SESSION['user_id'];
    $db = new Database();
    $inTransaction = false;

    $assignment = $db->fetch(
        "SELECT a.*, j.status as job_status
         FROM assignments a
         JOIN jobs j ON a.job_id = j.id
         WHERE a.id = ?",
        [$assignment_id]
    );

    if (!$assignment) {
        throw new Exception('Assignment not found');
    }

    $canSubmit = false;
    if ((int)$assignment['assigned_to'] === $user_id) {
        $canSubmit = true;
    } elseif (is_null($assignment['assigned_to']) && $assignment['job_status'] === 'active') {
        $canSubmit = true;
    }

    if (!$canSubmit) {
        throw new Exception('You do not have permission to submit this assignment', 403);
    }

    $db->beginTransaction();
    $inTransaction = true;

    $submission = $db->fetch(
        "SELECT id FROM assignment_submissions WHERE assignment_id = ? AND user_id = ?",
        [$assignment_id, $user_id]
    );

    if ($submission) {
        $submission_id = $submission['id'];
        $db->execute(
            "UPDATE assignment_submissions
             SET status = 'submitted', submission_date = NOW()
             WHERE id = ?",
            [$submission_id]
        );
        $db->execute("DELETE FROM assignment_submission_answers WHERE submission_id = ?", [$submission_id]);
    } else {
        $db->execute(
            "INSERT INTO assignment_submissions (assignment_id, user_id, submission_date, status)
             VALUES (?, ?, NOW(), 'submitted')",
            [$assignment_id, $user_id]
        );
        $submission_id = $db->lastInsertId();
    }

    $questions = $db->fetchAll(
        "SELECT id, question_type
         FROM assignment_questions
         WHERE assignment_id = ?
         ORDER BY order_index",
        [$assignment_id]
    );

    // Handle assignments with no questions by creating a placeholder
    if (empty($questions)) {
        $generalSubmission = trim($_POST['submission'] ?? '');
        if ($generalSubmission === '') {
            throw new Exception('Please provide your assignment response.');
        }

        $db->execute(
            "INSERT INTO assignment_questions (assignment_id, question_text, question_type, order_index)
             VALUES (?, 'General Submission Response', 'text', 0)",
            [$assignment_id]
        );
        $placeholderId = $db->lastInsertId();
        $questions = [
            [
                'id' => $placeholderId,
                'question_type' => 'text'
            ]
        ];
        $_POST['answers'][$placeholderId] = $generalSubmission;
    }

    $uploadDir = __DIR__ . '/../../uploads/assignment_submissions/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    foreach ($questions as $question) {
        $questionId = $question['id'];
        $questionType = $question['question_type'];
        $answerText = null;
        $filePath = null;

        if ($questionType === 'file_upload') {
            $fileInfo = $_FILES['files'] ?? null;
            if (
                !$fileInfo ||
                !isset($fileInfo['error'][$questionId]) ||
                $fileInfo['error'][$questionId] === UPLOAD_ERR_NO_FILE
            ) {
                throw new Exception('Please upload the required file for question ' . $questionId);
            }

            if ($fileInfo['error'][$questionId] !== UPLOAD_ERR_OK) {
                throw new Exception('Error uploading file for question ' . $questionId);
            }

            $extension = pathinfo($fileInfo['name'][$questionId], PATHINFO_EXTENSION);
            $safeExtension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
            $filename = uniqid('assignment_', true) . ($safeExtension ? '.' . $safeExtension : '');
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($fileInfo['tmp_name'][$questionId], $destination)) {
                throw new Exception('Failed to save uploaded file for question ' . $questionId);
            }

            $filePath = 'uploads/assignment_submissions/' . $filename;
        } else {
            $answerText = trim($_POST['answers'][$questionId] ?? '');
            if ($answerText === '') {
                throw new Exception('Please provide answers for all questions.');
            }
        }

        $db->execute(
            "INSERT INTO assignment_submission_answers (submission_id, question_id, answer_text, file_path)
             VALUES (?, ?, ?, ?)",
            [$submission_id, $questionId, $answerText, $filePath]
        );
    }

    $db->execute(
        "UPDATE assignments SET status = 'submitted', updated_at = NOW() WHERE id = ?",
        [$assignment_id]
    );

    $db->commit();
    $inTransaction = false;

    echo json_encode([
        'success' => true,
        'message' => 'Assignment submitted successfully!'
    ]);
} catch (Exception $e) {
    if (isset($db) && isset($inTransaction) && $inTransaction) {
        $db->rollBack();
    }

    $code = $e->getCode();
    if ($code < 400 || $code >= 600) {
        $code = 400;
    }
    http_response_code($code);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

