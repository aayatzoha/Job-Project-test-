<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || getUserType() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $type = $_GET['type'] ?? '';
    
    if (empty($type)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Export type is required']);
        exit();
    }
    
    $filename = '';
    $data = [];
    
    switch ($type) {
        case 'users':
            $data = $db->fetchAll("
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.phone,
                    u.user_type,
                    u.status,
                    u.email_verified,
                    u.created_at,
                    u.last_activity,
                    CASE 
                        WHEN u.user_type = 'job_seeker' THEN js.location
                        WHEN u.user_type = 'company' THEN c.company_name
                        ELSE NULL
                    END as additional_info
                FROM users u
                LEFT JOIN job_seekers js ON u.id = js.user_id AND u.user_type = 'job_seeker'
                LEFT JOIN companies c ON u.id = c.user_id AND u.user_type = 'company'
                ORDER BY u.created_at DESC
            ");
            $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
            break;
            
        case 'jobs':
            $data = $db->fetchAll("
                SELECT 
                    j.id,
                    j.title,
                    c.company_name,
                    j.location,
                    j.job_type,
                    j.experience_level,
                    j.salary_min,
                    j.salary_max,
                    j.status,
                    j.total_applications,
                    j.views_count,
                    j.created_at
                FROM jobs j
                JOIN companies c ON j.company_id = c.id
                ORDER BY j.created_at DESC
            ");
            $filename = 'jobs_export_' . date('Y-m-d_H-i-s') . '.csv';
            break;
            
        case 'applications':
            $data = $db->fetchAll("
                SELECT 
                    ja.id,
                    u.name as applicant_name,
                    u.email as applicant_email,
                    j.title as job_title,
                    c.company_name,
                    ja.status,
                    ja.application_date,
                    ja.screening_score,
                    ja.interview_score
                FROM job_applications ja
                JOIN job_seekers js ON ja.job_seeker_id = js.id
                JOIN users u ON js.user_id = u.id
                JOIN jobs j ON ja.job_id = j.id
                JOIN companies c ON j.company_id = c.id
                ORDER BY ja.application_date DESC
            ");
            $filename = 'applications_export_' . date('Y-m-d_H-i-s') . '.csv';
            break;
            
        case 'assessments':
            $data = $db->fetchAll("
                SELECT 
                    ua.id,
                    u.name as user_name,
                    u.email as user_email,
                    a.title as assessment_title,
                    ua.status,
                    ua.percentage_score,
                    ua.time_taken,
                    ua.started_at,
                    ua.completed_at
                FROM user_assessments ua
                JOIN users u ON ua.user_id = u.id
                JOIN assessments a ON ua.assessment_id = a.id
                ORDER BY ua.started_at DESC
            ");
            $filename = 'assessments_export_' . date('Y-m-d_H-i-s') . '.csv';
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid export type']);
            exit();
    }
    
    if (empty($data)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No data found to export']);
        exit();
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV header row
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    
    // Log the export action
    logActivity($_SESSION['user_id'], 'data_exported', "Exported $type data: " . count($data) . " records");
    
} catch (Exception $e) {
    error_log("Export API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>