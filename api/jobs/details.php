<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    requireLogin();
    
    $user_id = $_SESSION['user_id'];
    $user_type = getUserType();
    $job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($job_id <= 0) {
        throw new Exception('Invalid job ID');
    }
    
    // Get job seeker ID if user is a job seeker
    $job_seeker_id = null;
    if ($user_type === 'job_seeker') {
        $job_seeker = $db->fetch("SELECT id FROM job_seekers WHERE user_id = ?", [$user_id]);
        $job_seeker_id = $job_seeker ? $job_seeker['id'] : null;
    }
    
    // Get job details with company information
    $params = [];
    $has_applied_sql = '0';
    
    if ($job_seeker_id) {
        $has_applied_sql = "(SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id AND ja.job_seeker_id = ?)";
        $params[] = $job_seeker_id;
    }
    
    $params[] = $job_id;
    
    $job = $db->fetch(
        "SELECT j.*, 
                c.company_name, c.industry, c.description as company_description,
                cat.name as category_name,
                $has_applied_sql as has_applied
         FROM jobs j
         JOIN companies c ON j.company_id = c.id
         LEFT JOIN job_categories cat ON j.category_id = cat.id
         WHERE j.id = ? AND j.status = 'active'",
        $params
    );
    
    if (!$job) {
        throw new Exception('Job not found or not available');
    }
    
    // Format salary
    $salary_display = 'Not specified';
    if ($job['salary_min'] || $job['salary_max']) {
        if ($job['salary_min'] && $job['salary_max']) {
            $salary_display = '$' . number_format($job['salary_min']) . ' - $' . number_format($job['salary_max']);
        } elseif ($job['salary_min']) {
            $salary_display = 'From $' . number_format($job['salary_min']);
        } elseif ($job['salary_max']) {
            $salary_display = 'Up to $' . number_format($job['salary_max']);
        }
    }
    
    // Format job type
    $job_type_display = ucwords(str_replace('_', ' ', $job['job_type']));
    $experience_display = ucwords($job['experience_level']);
    $work_type_display = ucwords($job['work_type']);
    
    echo json_encode([
        'success' => true,
        'job' => [
            'id' => $job['id'],
            'title' => $job['title'],
            'description' => $job['description'],
            'requirements' => $job['requirements'],
            'responsibilities' => $job['responsibilities'],
            'benefits' => $job['benefits'],
            'location' => $job['location'],
            'job_type' => $job['job_type'],
            'job_type_display' => $job_type_display,
            'experience_level' => $job['experience_level'],
            'experience_display' => $experience_display,
            'work_type' => $job['work_type'],
            'work_type_display' => $work_type_display,
            'salary_min' => $job['salary_min'],
            'salary_max' => $job['salary_max'],
            'salary_display' => $salary_display,
            'currency' => $job['currency'] ?? 'USD',
            'application_deadline' => $job['application_deadline'],
            'company_name' => $job['company_name'],
            'company_description' => $job['company_description'],
            'industry' => $job['industry'],
            'category_name' => $job['category_name'],
            'created_at' => $job['created_at'],
            'has_applied' => (bool)$job['has_applied']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

