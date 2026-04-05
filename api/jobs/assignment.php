<?php
// Minimal Assignment API: POST create only, JSON responses
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';

function send_json($code, $payload) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

try {
    // Handle GET request to fetch assignments
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: login required']);
            exit;
        }

        $user_id = (int)$_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];
        $db = new Database();

        try {
            if ($user_type === 'company') {
                // Resolve company_id from user_id
                $company = $db->fetch('SELECT id FROM companies WHERE user_id = ?', [$user_id]);
                if (!$company || !isset($company['id'])) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Company profile not found for current user']);
                    exit;
                }
                $company_id = (int)$company['id'];

                // Fetch assignments for jobs owned by this company OR created by this user
                $sql = "SELECT 
                            a.id, a.title, a.description, a.due_date, a.status, a.requires_camera,
                            j.title AS job_title,
                            u.name AS creator_name,
                            uassignee.name AS assignee_name
                        FROM assignments a
                        JOIN jobs j ON a.job_id = j.id
                        LEFT JOIN users u ON u.id = a.created_by
                        LEFT JOIN users uassignee ON uassignee.id = a.assigned_to
                        WHERE j.company_id = ? OR a.created_by = ?
                        ORDER BY a.created_at DESC";
                $assignments = $db->fetchAll($sql, [$company_id, $user_id]);
            } elseif ($user_type === 'job_seeker') {
                // Fetch assignments for job seekers:
                // 1. Directly assigned to them
                // 2. Unassigned assignments for jobs they applied to
                // 3. All unassigned assignments for active jobs (available to all job seekers)
                // First get job_seeker_id
                $job_seeker = $db->fetch("SELECT id FROM job_seekers WHERE user_id = ?", [$user_id]);
                
                if ($job_seeker) {
                    $job_seeker_id = (int)$job_seeker['id'];
                    
                    $sql = "SELECT 
                                a.id, a.title, a.description, a.due_date, a.status, a.requires_camera,
                                j.title AS job_title,
                                c.company_name,
                                u.name AS creator_name
                            FROM assignments a
                            JOIN jobs j ON a.job_id = j.id
                            JOIN companies c ON j.company_id = c.id
                            LEFT JOIN users u ON u.id = a.created_by
                            WHERE 
                                  a.assigned_to = ? 
                               OR (a.assigned_to IS NULL AND EXISTS (
                                   SELECT 1 FROM job_applications ja 
                                   WHERE ja.job_id = j.id AND ja.job_seeker_id = ?
                               ))
                               OR (a.assigned_to IS NULL AND j.status = 'active')
                            ORDER BY 
                                CASE 
                                    WHEN a.assigned_to = ? THEN 1
                                    WHEN a.assigned_to IS NULL AND EXISTS (
                                        SELECT 1 FROM job_applications ja 
                                        WHERE ja.job_id = j.id AND ja.job_seeker_id = ?
                                    ) THEN 2
                                    ELSE 3
                                END,
                                a.created_at DESC";
                    $assignments = $db->fetchAll($sql, [$user_id, $job_seeker_id, $user_id, $job_seeker_id]);
                } else {
                    // If no job_seeker profile, show all unassigned assignments for active jobs
                    $sql = "SELECT 
                                a.id, a.title, a.description, a.due_date, a.status, a.requires_camera,
                                j.title AS job_title,
                                c.company_name,
                                u.name AS creator_name
                            FROM assignments a
                            JOIN jobs j ON a.job_id = j.id
                            JOIN companies c ON j.company_id = c.id
                            LEFT JOIN users u ON u.id = a.created_by
                            WHERE a.assigned_to IS NULL AND j.status = 'active'
                            ORDER BY a.created_at DESC";
                    $assignments = $db->fetchAll($sql);
                }
            } else {
                // Admin can see all assignments
                $sql = "SELECT 
                            a.id, a.title, a.description, a.due_date, a.status, a.requires_camera,
                            j.title AS job_title,
                            c.company_name,
                            u.name AS creator_name,
                            uassignee.name AS assignee_name
                        FROM assignments a
                        JOIN jobs j ON a.job_id = j.id
                        JOIN companies c ON j.company_id = c.id
                        LEFT JOIN users u ON u.id = a.created_by
                        LEFT JOIN users uassignee ON uassignee.id = a.assigned_to
                        ORDER BY a.created_at DESC";
                $assignments = $db->fetchAll($sql);
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'assignments' => $assignments ?: []]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to fetch assignments: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        send_json(405, [
            'success' => false,
            'message' => 'Method not allowed',
        ]);
    }

    // Ensure authenticated company session
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'company') {
        send_json(401, [
            'success' => false,
            'message' => 'Unauthorized: company login required',
        ]);
    }

    $user_id = (int)$_SESSION['user_id'];
    $db = new Database();

    // Validate inputs (support both JSON and form-encoded)
    $input = [];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode(file_get_contents('php://input'), true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
    // Fallback to $_POST when not JSON
    $title = trim(($input['title'] ?? ($_POST['title'] ?? '')));
    $description = trim(($input['description'] ?? ($_POST['description'] ?? '')));
    $job_id = isset($input['job_id']) ? (int)$input['job_id'] : (isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0);
    $due_date = trim(($input['due_date'] ?? ($_POST['due_date'] ?? ''))); // YYYY-MM-DD
    $requires_camera = isset($input['requires_camera']) ? (int)$input['requires_camera'] : (isset($_POST['requires_camera']) ? (int)$_POST['requires_camera'] : 0); // 0/1

    if ($title === '' || $job_id <= 0) {
        send_json(400, [
            'success' => false,
            'message' => 'title and job_id are required',
        ]);
    }

    // Get company id for current user
    $company = $db->fetch('SELECT id FROM companies WHERE user_id = ?', [$user_id]);
    if (!$company) {
        send_json(403, [
            'success' => false,
            'message' => 'Company profile not found for current user',
        ]);
    }
    $company_id = (int)$company['id'];

    // Verify job belongs to this company
    $job = $db->fetch('SELECT id, company_id FROM jobs WHERE id = ?', [$job_id]);
    if (!$job) {
        send_json(404, [
            'success' => false,
            'message' => 'Job not found',
        ]);
    }
    if ((int)$job['company_id'] !== $company_id) {
        send_json(403, [
            'success' => false,
            'message' => 'You do not have permission to add assignments to this job',
        ]);
    }

    // Get assigned_to if provided
    $assigned_to = isset($input['assigned_to']) ? (int)$input['assigned_to'] : (isset($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null);
    if ($assigned_to && $assigned_to <= 0) {
        $assigned_to = null;
    }

    // Check if this is an update
    $edit_mode = isset($input['edit_mode']) ? (int)$input['edit_mode'] : (isset($_POST['edit_mode']) ? (int)$_POST['edit_mode'] : 0);
    $assignment_id = isset($input['assignment_id']) ? (int)$input['assignment_id'] : (isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0);

    if ($edit_mode && $assignment_id > 0) {
        // Update existing assignment
        $db->execute(
            'UPDATE assignments SET title = ?, description = ?, due_date = ?, assigned_to = ?, requires_camera = ?, updated_at = NOW()
             WHERE id = ? AND created_by = ?',
            [$title, $description, $due_date !== '' ? $due_date : null, $assigned_to, $requires_camera ? 1 : 0, $assignment_id, $user_id]
        );
    } else {
        // Insert new assignment
        $params = [
            $job_id,
            $title,
            $description,
            $due_date !== '' ? $due_date : null,
            $user_id,
            $requires_camera ? 1 : 0,
            $assigned_to,
        ];

        $db->execute(
            'INSERT INTO assignments (job_id, title, description, due_date, created_by, requires_camera, status, assigned_to)
             VALUES (?, ?, ?, ?, ?, ?, "pending", ?)',
            $params
        );

        $assignment_id = (int)$db->lastInsertId();
    }

    // Handle assignment questions if provided
    $questions = $input['questions'] ?? ($_POST['questions'] ?? []);
    $question_types = $input['question_types'] ?? ($_POST['question_types'] ?? []);
    $options = $input['options'] ?? ($_POST['options'] ?? []);

    if (!is_array($questions)) {
        $questions = [];
    }

    // Delete existing questions if updating
    if ($edit_mode && $assignment_id > 0) {
        $db->execute('DELETE FROM assignment_questions WHERE assignment_id = ?', [$assignment_id]);
    }

    // Insert questions
    if (!empty($questions) && $assignment_id > 0) {
        foreach ($questions as $index => $question_text) {
            if (empty(trim($question_text))) {
                continue;
            }

            $qtype = isset($question_types[$index]) ? $question_types[$index] : 'text';
            $qoptions = null;

            // Handle options for multiple choice questions
            if ($qtype === 'multiple_choice' && isset($options[$index]) && !empty(trim($options[$index]))) {
                $option_lines = explode("\n", trim($options[$index]));
                $option_lines = array_filter(array_map('trim', $option_lines));
                if (!empty($option_lines)) {
                    $qoptions = json_encode(array_values($option_lines));
                }
            }

            $db->execute(
                'INSERT INTO assignment_questions (assignment_id, question_text, question_type, options, order_index)
                 VALUES (?, ?, ?, ?, ?)',
                [$assignment_id, trim($question_text), $qtype, $qoptions, $index]
            );
        }
    }

    send_json(200, [
        'success' => true,
        'message' => $edit_mode ? 'Assignment updated successfully' : 'Assignment created successfully',
        'assignment_id' => $assignment_id,
    ]);

} catch (Throwable $e) {
    error_log('Assignment API error: ' . $e->getMessage());
    send_json(500, [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ]);
}
?>

