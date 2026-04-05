<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

try {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['role'] ?? $_SESSION['user_type'];
    
    if ($user_type !== 'job_seeker') {
        throw new Exception('Only job seekers can search jobs');
    }
    
    // Get search parameters
    $keywords = $_GET['keywords'] ?? $_POST['keywords'] ?? '';
    $location = $_GET['location'] ?? $_POST['location'] ?? '';
    $job_type = $_GET['job_type'] ?? $_POST['job_type'] ?? '';
    $experience = $_GET['experience'] ?? $_POST['experience'] ?? '';
    $limit = min(50, (int)($_GET['limit'] ?? $_POST['limit'] ?? 20));
    $offset = max(0, (int)($_GET['offset'] ?? $_POST['offset'] ?? 0));
    
    // Build search query
    $where_conditions = ["j.status = 'active'"];
    $params = [];
    
    if (!empty($keywords)) {
        $where_conditions[] = "(j.title LIKE ? OR j.description LIKE ? OR j.requirements LIKE ? OR c.company_name LIKE ?)";
        $keyword_param = '%' . $keywords . '%';
        $params[] = $keyword_param;
        $params[] = $keyword_param;
        $params[] = $keyword_param;
        $params[] = $keyword_param;
    }
    
    if (!empty($location)) {
        $where_conditions[] = "j.location LIKE ?";
        $params[] = '%' . $location . '%';
    }
    
    if (!empty($job_type)) {
        $where_conditions[] = "j.job_type = ?";
        $params[] = $job_type;
    }
    
    if (!empty($experience)) {
        $exp_conditions = [];
        switch ($experience) {
            case 'entry':
                $exp_conditions[] = "j.requirements LIKE '%entry%'";
                $exp_conditions[] = "j.requirements LIKE '%junior%'";
                $exp_conditions[] = "j.requirements LIKE '%0-2 years%'";
                break;
            case 'mid':
                $exp_conditions[] = "j.requirements LIKE '%mid%'";
                $exp_conditions[] = "j.requirements LIKE '%2-5 years%'";
                $exp_conditions[] = "j.requirements LIKE '%3-5 years%'";
                break;
            case 'senior':
                $exp_conditions[] = "j.requirements LIKE '%senior%'";
                $exp_conditions[] = "j.requirements LIKE '%5+ years%'";
                $exp_conditions[] = "j.requirements LIKE '%lead%'";
                break;
        }
        if (!empty($exp_conditions)) {
            $where_conditions[] = "(" . implode(" OR ", $exp_conditions) . ")";
        }
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total 
                  FROM jobs j 
                  JOIN companies c ON j.company_id = c.id 
                  WHERE $where_clause";
    
    $total_result = $db->fetch($count_sql, $params);
    $total_jobs = $total_result['total'];
    
    // Get search results
    $sql = "SELECT j.*, c.company_name, c.industry, c.logo_url,
            (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id AND ja.job_seeker_id = ?) as has_applied
            FROM jobs j
            JOIN companies c ON j.company_id = c.id
            WHERE $where_clause
            ORDER BY j.created_at DESC
            LIMIT ? OFFSET ?";
    
    $search_params = array_merge([$user_id], $params, [$limit, $offset]);
    $jobs = $db->fetchAll($sql, $search_params);
    
    // Format results
    $results = [];
    foreach ($jobs as $job) {
        $results[] = [
            'id' => $job['id'],
            'title' => $job['title'],
            'description' => $job['description'],
            'location' => $job['location'],
            'job_type' => $job['job_type'],
            'salary_min' => $job['salary_min'],
            'salary_max' => $job['salary_max'],
            'requirements' => $job['requirements'],
            'benefits' => $job['benefits'],
            'company_name' => $job['company_name'],
            'industry' => $job['industry'],
            'logo_url' => $job['logo_url'],
            'created_at' => $job['created_at'],
            'has_applied' => (bool)$job['has_applied'],
            'salary_display' => formatSalary($job['salary_min'], $job['salary_max'])
        ];
    }
    
    // Log search activity
    logActivity($user_id, 'job_search', "Keywords: $keywords, Location: $location");
    
    echo json_encode([
        'success' => true,
        'jobs' => $results,
        'total' => $total_jobs,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => ($offset + $limit) < $total_jobs,
        'search_params' => [
            'keywords' => $keywords,
            'location' => $location,
            'job_type' => $job_type,
            'experience' => $experience
        ]
    ]);
    
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
?>