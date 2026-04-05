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
        throw new Exception('Only job seekers can view applications');
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Apply to a job
        $input = json_decode(file_get_contents('php://input'), true);
        $job_id = $input['job_id'] ?? null;
        
        if (!$job_id) {
            throw new Exception('Job ID is required');
        }
        
        // Check if already applied
        // First get the job_seeker_id from job_seekers table
        $job_seeker = $db->fetch(
            "SELECT id FROM job_seekers WHERE user_id = ?",
            [$user_id]
        );
        
        if (!$job_seeker) {
            throw new Exception('Job seeker profile not found. Please complete your profile first.');
        }
        
        $job_seeker_id = $job_seeker['id'];
        
        $existing = $db->fetch(
            "SELECT id FROM job_applications WHERE job_seeker_id = ? AND job_id = ?",
            [$job_seeker_id, $job_id]
        );
        
        if ($existing) {
            throw new Exception('You have already applied to this job');
        }
        
        // Get job details
        $job = $db->fetch(
            "SELECT j.*, c.company_name FROM jobs j JOIN companies c ON j.company_id = c.id WHERE j.id = ?",
            [$job_id]
        );
        
        if (!$job) {
            throw new Exception('Job not found');
        }
        
        // Get the job_seeker_id from job_seekers table using user_id
        $job_seeker = $db->fetch(
            "SELECT id FROM job_seekers WHERE user_id = ?",
            [$user_id]
        );
        
        if (!$job_seeker) {
            throw new Exception('Job seeker profile not found. Please complete your profile first.');
        }
        
        $job_seeker_id = $job_seeker['id'];
        
        // Create application
        $sql = "INSERT INTO job_applications (job_seeker_id, job_id, application_date, status, cover_letter) 
                VALUES (?, ?, NOW(), 'applied', ?)";
        
        $cover_letter = $input['cover_letter'] ?? "I am interested in the {$job['title']} position at {$job['company_name']}.";
        
        $db->execute($sql, [$job_seeker_id, $job_id, $cover_letter]);
        
        // Automatically assign active assignments for this job to the applicant
        // Find all active, unassigned assignments for this job
        $assignments = $db->fetchAll(
            "SELECT id FROM assignments 
             WHERE job_id = ? 
               AND status = 'active' 
               AND (assigned_to IS NULL OR assigned_to = 0)",
            [$job_id]
        );
        
        $assignments_assigned = 0;
        if (!empty($assignments)) {
            foreach ($assignments as $assignment) {
                // Assign the assignment to the job seeker (using user_id, not job_seeker_id)
                $db->execute(
                    "UPDATE assignments 
                     SET assigned_to = ?, updated_at = NOW() 
                     WHERE id = ?",
                    [$user_id, $assignment['id']]
                );
                $assignments_assigned++;
            }
        }
        
        // Log activity
        logActivity($user_id, 'job_application', "Applied to: {$job['title']} at {$job['company_name']}" . ($assignments_assigned > 0 ? " (Assigned {$assignments_assigned} assignment(s))" : ""));
        
        $message = 'Application submitted successfully';
        if ($assignments_assigned > 0) {
            $message .= ". {$assignments_assigned} assignment(s) have been assigned to you.";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'assignments_assigned' => $assignments_assigned
        ]);
        
    } else {
        // Get applications list
        $status_filter = $_GET['status'] ?? '';
        $limit = min(50, (int)($_GET['limit'] ?? 20));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        
        // Get the job_seeker_id from job_seekers table
        $job_seeker = $db->fetch(
            "SELECT id FROM job_seekers WHERE user_id = ?",
            [$user_id]
        );
        
        if (!$job_seeker) {
            throw new Exception('Job seeker profile not found. Please complete your profile first.');
        }
        
        $job_seeker_id = $job_seeker['id'];
        
        $where_conditions = ["ja.job_seeker_id = ?"];
        $params = [$job_seeker_id];
        
        if (!empty($status_filter)) {
            $where_conditions[] = "ja.status = ?";
            $params[] = $status_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM job_applications ja WHERE $where_clause";
        $total_result = $db->fetch($count_sql, $params);
        $total_applications = $total_result['total'];
        
        // Get applications
        $sql = "SELECT ja.*, j.title, j.location, j.job_type, j.salary_min, j.salary_max,
                       c.company_name, c.industry
                FROM job_applications ja
                JOIN jobs j ON ja.job_id = j.id
                JOIN companies c ON j.company_id = c.id
                WHERE $where_clause
                ORDER BY ja.application_date DESC
                LIMIT ? OFFSET ?";
        
        $applications = $db->fetchAll($sql, array_merge($params, [$limit, $offset]));
        
        // Format results
        $results = [];
        foreach ($applications as $app) {
            $results[] = [
                'id' => $app['id'],
                'job_id' => $app['job_id'],
                'title' => $app['title'],
                'company_name' => $app['company_name'],
                'location' => $app['location'],
                'job_type' => $app['job_type'],
                'industry' => $app['industry'],
                'status' => $app['status'],
                'application_date' => $app['application_date'],
                'salary_display' => formatSalary($app['salary_min'], $app['salary_max']),
                'status_display' => ucfirst(str_replace('_', ' ', $app['status'])),
                'days_ago' => calculateDaysAgo($app['application_date'])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'applications' => $results,
            'total' => $total_applications,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_applications
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
 * Format salary range for display
 */
function formatSalary($min, $max) {
    if (!$min && !$max) {
        return 'Salary not specified';
    }
    
    if ($min && $max) {
        return '$' . number_format($min) . ' - $' . number_format($max);
    }
    
    if ($min) {
        return 'From $' . number_format($min);
    }
    
    return 'Up to $' . number_format($max);
}

/**
 * Calculate days ago from application date
 */
function calculateDaysAgo($date) {
    $now = new DateTime();
    $app_date = new DateTime($date);
    $interval = $now->diff($app_date);
    
    if ($interval->days == 0) {
        return 'Today';
    } elseif ($interval->days == 1) {
        return '1 day ago';
    } else {
        return $interval->days . ' days ago';
    }
}
?>