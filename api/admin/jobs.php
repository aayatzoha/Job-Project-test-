<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || getUserType() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        if ($action === 'seed_demo') {
            $limit = intval($input['limit'] ?? 25);
            $locations = $input['locations'] ?? [
                'Bangalore,Karnataka,India',
                'Mumbai,Maharashtra,India',
                'Hyderabad,Telangana,India',
                'Chennai,Tamil Nadu,India',
                'Pune,Maharashtra,India'
            ];
            $roles = $input['roles'] ?? ['Software Engineer','Backend Developer','Frontend Developer','Data Analyst','DevOps Engineer'];
            $job_types = ['full_time','contract','internship'];
            $work_types = ['onsite','hybrid','remote'];
            $companies = $db->fetchAll("SELECT id, company_name FROM companies ORDER BY id LIMIT ?", [$limit]);
            $created = 0;
            foreach ($companies as $c) {
                $company_id = $c['id'];
                foreach ($roles as $role) {
                    $years = rand(0,10);
                    $exp_level = ($years <= 2) ? 'entry' : (($years <= 5) ? 'mid' : 'senior');
                    $location = $locations[array_rand($locations)];
                    $job_type = $job_types[array_rand($job_types)];
                    $work_type = $work_types[array_rand($work_types)];
                    $title = $role;
                    $description = "Hiring $role for innovative projects";
                    $requirements = "Strong fundamentals. Minimum $years years of experience.";
                    $responsibilities = "Build and maintain applications; collaborate across teams";
                    $benefits = "Competitive salary, insurance, growth opportunities";
                    $currency = 'INR';
                    $salary_min = 600000;
                    $salary_max = 1800000;
                    $status = 'active';
                    $sql = "INSERT INTO jobs (company_id, title, description, requirements, responsibilities, benefits, category_id, job_type, experience_level, salary_min, salary_max, currency, location, work_type, status, application_deadline, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $db->execute($sql, [$company_id, $title, $description, $requirements, $responsibilities, $benefits, null, $job_type, $exp_level, $salary_min, $salary_max, $currency, $location, $work_type, $status, null]);
                    $created++;
                }
            }
            echo json_encode(['success' => true, 'message' => 'Seeded demo jobs', 'jobs_created' => $created, 'companies_targeted' => count($companies)]);
            return;
        }
    }
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $category = $_GET['category'] ?? '';
    $job_type = $_GET['job_type'] ?? '';
    $experience_level = $_GET['experience_level'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(j.title LIKE ? OR j.description LIKE ? OR c.company_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status)) {
        $where_conditions[] = "j.status = ?";
        $params[] = $status;
    }
    
    if (!empty($category)) {
        $where_conditions[] = "j.category_id = ?";
        $params[] = $category;
    }
    
    if (!empty($job_type)) {
        $where_conditions[] = "j.job_type = ?";
        $params[] = $job_type;
    }
    
    if (!empty($experience_level)) {
        $where_conditions[] = "j.experience_level = ?";
        $params[] = $experience_level;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get total count for pagination
    $count_query = "
        SELECT COUNT(*) as total 
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        LEFT JOIN job_categories cat ON j.category_id = cat.id
        $where_clause
    ";
    
    $total_jobs = $db->fetch($count_query, $params)['total'] ?? 0;
    
    // Get jobs with pagination
    $query = "
        SELECT 
            j.id,
            j.title,
            j.description,
            j.requirements,
            j.job_type,
            j.experience_level,
            j.salary_min,
            j.salary_max,
            j.currency,
            j.location,
            j.work_type,
            j.status,
            j.application_deadline,
            j.total_applications,
            j.views_count,
            j.created_at,
            j.updated_at,
            c.id as company_id,
            c.company_name,
            c.industry,
            cat.name as category_name,
            u.name as company_contact_name,
            u.email as company_email,
            (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id) as application_count
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        JOIN users u ON c.user_id = u.id
        LEFT JOIN job_categories cat ON j.category_id = cat.id
        $where_clause
        ORDER BY j.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $jobs = $db->fetchAll($query, $params);
    
    // Add computed fields
    foreach ($jobs as &$job) {
        // Calculate days since posting
        $created = new DateTime($job['created_at']);
        $now = new DateTime();
        $job['days_since_posting'] = $now->diff($created)->days;
        
        // Format salary range
        if ($job['salary_min'] && $job['salary_max']) {
            $job['salary_display'] = number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']) . ' ' . $job['currency'];
        } elseif ($job['salary_min']) {
            $job['salary_display'] = 'From ' . number_format($job['salary_min']) . ' ' . $job['currency'];
        } elseif ($job['salary_max']) {
            $job['salary_display'] = 'Up to ' . number_format($job['salary_max']) . ' ' . $job['currency'];
        } else {
            $job['salary_display'] = 'Not specified';
        }
        
        // Format job type and experience level
        $job['job_type_display'] = ucwords(str_replace('_', ' ', $job['job_type']));
        $job['experience_level_display'] = ucfirst($job['experience_level']);
        $job['work_type_display'] = ucfirst($job['work_type']);
        
        // Calculate application rate (applications per view)
        $job['application_rate'] = $job['views_count'] > 0 ? round(($job['application_count'] / $job['views_count']) * 100, 2) : 0;
        
        // Check if deadline is approaching
        if ($job['application_deadline']) {
            $deadline = new DateTime($job['application_deadline']);
            $days_to_deadline = $now->diff($deadline)->days;
            $job['deadline_status'] = $deadline < $now ? 'expired' : ($days_to_deadline <= 7 ? 'expiring_soon' : 'active');
            $job['days_to_deadline'] = $deadline < $now ? -$days_to_deadline : $days_to_deadline;
        } else {
            $job['deadline_status'] = 'no_deadline';
            $job['days_to_deadline'] = null;
        }
    }
    
    // Get job statistics
    $stats = $db->fetch("
        SELECT 
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_jobs,
            SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused_jobs,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_jobs,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_jobs,
            SUM(total_applications) as total_applications,
            SUM(views_count) as total_views,
            AVG(total_applications) as avg_applications_per_job,
            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as jobs_posted_last_week,
            COUNT(CASE WHEN application_deadline < NOW() AND status = 'active' THEN 1 END) as expired_active_jobs
        FROM jobs
    ");
    
    // Get job categories for filtering
    $categories = $db->fetchAll("SELECT id, name FROM job_categories ORDER BY name");
    
    echo json_encode([
        'success' => true,
        'jobs' => $jobs,
        'pagination' => [
            'total' => $total_jobs,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_jobs / $limit)
        ],
        'stats' => $stats,
        'categories' => $categories,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'category' => $category,
            'job_type' => $job_type,
            'experience_level' => $experience_level
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Jobs API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
