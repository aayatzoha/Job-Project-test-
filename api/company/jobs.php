<?php
header('Content-Type: application/json');
// Same-origin requests only; remove wildcard CORS so cookies/sessions work

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

// Get company ID; if missing, auto-create a minimal profile
$company = $db->fetch("SELECT * FROM companies WHERE user_id = ?", [$user_id]);
if (!$company) {
    // Attempt to auto-create a minimal company profile using the user's name
    $userRow = $db->fetch("SELECT name FROM users WHERE id = ?", [$user_id]);
    $company_name = ($userRow && !empty($userRow['name'])) ? $userRow['name'] : ("Company #" . $user_id);
    try {
        $db->execute("INSERT INTO companies (user_id, company_name) VALUES (?, ?)", [$user_id, $company_name]);
        $company = $db->fetch("SELECT * FROM companies WHERE user_id = ?", [$user_id]);
    } catch (Exception $e) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Company profile not found']);
        exit();
    }
}
$company_id = $company['id'];

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetJobs($company_id);
            break;
        case 'POST':
            handleCreateJob($company_id, $user_id);
            break;
        case 'PUT':
            handleUpdateJob($company_id, $user_id);
            break;
        case 'DELETE':
            handleDeleteJob($company_id, $user_id);
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

function handleGetJobs($company_id) {
    global $db;
    
    $job_id = intval($_GET['job_id'] ?? 0);
    
    if ($job_id > 0) {
        // Get specific job with applications
        $job = getJobWithDetails($company_id, $job_id);
        echo json_encode([
            'success' => true,
            'job' => $job
        ]);
    } else {
        // Get all jobs for company
        $jobs = getCompanyJobs($company_id);
        $stats = getCompanyJobStats($company_id);
        
        echo json_encode([
            'success' => true,
            'jobs' => $jobs,
            'stats' => $stats
        ]);
    }
}

function handleCreateJob($company_id, $user_id) {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    if (!empty($input['bulk_seed'])) {
        $languages = $input['languages'] ?? ['Java','MySQL','JavaScript'];
        $location = sanitizeInput($input['location'] ?? 'Bangalore');
        $work_type = sanitizeInput($input['work_type'] ?? 'onsite');
        $job_type = sanitizeInput($input['job_type'] ?? 'full_time');
        $experience_level = 'senior';
        $salary_min = !empty($input['salary_min']) ? floatval($input['salary_min']) : 1200000;
        $salary_max = !empty($input['salary_max']) ? floatval($input['salary_max']) : 2400000;
        $currency = sanitizeInput($input['currency'] ?? 'INR');
        $status = 'active';
        $count = 0;
        foreach ($languages as $lang) {
            $title = sanitizeInput($lang . ' Developer');
            $description = sanitizeInput('Hiring experienced ' . $lang . ' developer.');
            $requirements = sanitizeInput('Strong ' . $lang . ' expertise with minimum 5 years of experience. Familiarity with testing, version control, and databases.');
            $responsibilities = sanitizeInput('Design, build, and maintain applications using ' . $lang . '. Collaborate with cross-functional teams.');
            $benefits = sanitizeInput('Competitive salary, health insurance, growth opportunities');
            $sql = "INSERT INTO jobs (company_id, title, description, requirements, responsibilities, benefits, category_id, job_type, experience_level, salary_min, salary_max, currency, location, work_type, status, application_deadline, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $db->execute($sql, [$company_id, $title, $description, $requirements, $responsibilities, $benefits, null, $job_type, $experience_level, $salary_min, $salary_max, $currency, $location, $work_type, $status, null]);
            $job_id = $db->lastInsertId();
            $count++;
            try { if (function_exists('logActivity')) { logActivity($user_id, 'job_posted', "Seeded job: $title (ID: $job_id)"); } } catch (Exception $e) {}
            try { if (function_exists('generateJobRecommendationsForJob')) { generateJobRecommendationsForJob($job_id); } } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'message' => 'Seeded jobs', 'count' => $count]);
        return;
    }
    
    // Validate required fields
    $required_fields = ['title', 'description', 'location', 'job_type', 'experience_level', 'work_type'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $title = sanitizeInput($input['title']);
    $description = sanitizeInput($input['description']);
    $requirements = sanitizeInput($input['requirements'] ?? '');
    $responsibilities = sanitizeInput($input['responsibilities'] ?? '');
    $benefits = sanitizeInput($input['benefits'] ?? '');
    $category_id = intval($input['category_id'] ?? 0) ?: null;
    $job_type = sanitizeInput($input['job_type']);
    $experience_level = sanitizeInput($input['experience_level']);
    $salary_min = !empty($input['salary_min']) ? floatval($input['salary_min']) : null;
    $salary_max = !empty($input['salary_max']) ? floatval($input['salary_max']) : null;
    $currency = sanitizeInput($input['currency'] ?? 'USD');
    $location = sanitizeInput($input['location']);
    $work_type = sanitizeInput($input['work_type']);
    $status = sanitizeInput($input['status'] ?? 'draft');
    $application_deadline = !empty($input['application_deadline']) ? $input['application_deadline'] : null;
    
    // Validate enums
    $valid_job_types = ['full_time', 'part_time', 'contract', 'internship', 'freelance'];
    $valid_experience_levels = ['entry', 'mid', 'senior', 'executive'];
    $valid_work_types = ['remote', 'onsite', 'hybrid'];
    $valid_statuses = ['active', 'paused', 'closed', 'draft'];
    
    if (!in_array($job_type, $valid_job_types)) {
        throw new Exception('Invalid job type');
    }
    if (!in_array($experience_level, $valid_experience_levels)) {
        throw new Exception('Invalid experience level');
    }
    if (!in_array($work_type, $valid_work_types)) {
        throw new Exception('Invalid work type');
    }
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status');
    }
    
    // Insert job
    $sql = "INSERT INTO jobs (company_id, title, description, requirements, responsibilities, 
            benefits, category_id, job_type, experience_level, salary_min, salary_max, 
            currency, location, work_type, status, application_deadline, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $db->execute($sql, [
        $company_id, $title, $description, $requirements, $responsibilities,
        $benefits, $category_id, $job_type, $experience_level, $salary_min, $salary_max,
        $currency, $location, $work_type, $status, $application_deadline
    ]);
    
    $job_id = $db->lastInsertId();
    
    // Log activity (don't fail if logging fails)
    try {
        if (function_exists('logActivity')) {
            logActivity($user_id, 'job_posted', "Job posted: $title (ID: $job_id)");
        }
    } catch (Exception $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
    
    // Generate recommendations for this job if it's active (don't fail if this fails)
    if ($status === 'active') {
        try {
            if (function_exists('generateJobRecommendationsForJob')) {
                generateJobRecommendationsForJob($job_id);
            }
        } catch (Exception $e) {
            error_log('Failed to generate recommendations: ' . $e->getMessage());
            // Don't fail the job creation if recommendations fail
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Job posted successfully',
        'job_id' => $job_id
    ]);
}

function handleUpdateJob($company_id, $user_id) {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $job_id = intval($input['job_id'] ?? 0);
    if ($job_id <= 0) {
        throw new Exception('Job ID is required');
    }
    
    // Verify job belongs to company
    $job = $db->fetch("SELECT * FROM jobs WHERE id = ? AND company_id = ?", [$job_id, $company_id]);
    if (!$job) {
        throw new Exception('Job not found or access denied');
    }
    
    // Build update query dynamically
    $updateFields = [];
    $updateValues = [];
    
    $allowedFields = [
        'title', 'description', 'requirements', 'responsibilities', 'benefits',
        'category_id', 'job_type', 'experience_level', 'salary_min', 'salary_max',
        'currency', 'location', 'work_type', 'status', 'application_deadline'
    ];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            if (in_array($field, ['salary_min', 'salary_max'])) {
                $value = floatval($input[$field]) ?: null;
            } elseif ($field === 'category_id') {
                $value = intval($input[$field]) ?: null;
            } else {
                $value = sanitizeInput($input[$field]);
            }
            
            $updateFields[] = "$field = ?";
            $updateValues[] = $value;
        }
    }
    
    if (empty($updateFields)) {
        throw new Exception('No fields to update');
    }
    
    $updateFields[] = 'updated_at = NOW()';
    $updateValues[] = $job_id;
    
    $sql = "UPDATE jobs SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $db->query($sql, $updateValues);
    
    // Log activity
    logActivity($user_id, 'job_updated', "Job updated: {$job['title']} (ID: $job_id)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Job updated successfully'
    ]);
}

function handleDeleteJob($company_id, $user_id) {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $job_id = intval($input['job_id'] ?? $_GET['job_id'] ?? 0);
    
    if ($job_id <= 0) {
        throw new Exception('Job ID is required');
    }
    
    // Verify job belongs to company
    $job = $db->fetch("SELECT * FROM jobs WHERE id = ? AND company_id = ?", [$job_id, $company_id]);
    if (!$job) {
        throw new Exception('Job not found or access denied');
    }
    
    // Check if there are applications
    $applicationCount = $db->fetch("SELECT COUNT(*) as count FROM job_applications WHERE job_id = ?", [$job_id])['count'];
    
    if ($applicationCount > 0) {
        // Don't actually delete, just close the job
        $db->query("UPDATE jobs SET status = 'closed', updated_at = NOW() WHERE id = ?", [$job_id]);
        $message = 'Job closed (has applications, cannot be deleted)';
    } else {
        // Safe to delete
        $db->query("DELETE FROM jobs WHERE id = ?", [$job_id]);
        $message = 'Job deleted successfully';
    }
    
    // Log activity
    logActivity($user_id, 'job_deleted', "Job deleted/closed: {$job['title']} (ID: $job_id)");
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
}

function getCompanyJobs($company_id) {
    global $db;
    
    return $db->fetchAll(
        "SELECT j.*, c.name as category_name,
                (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) as application_count,
                (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id AND status = 'applied') as new_applications
         FROM jobs j
         LEFT JOIN job_categories c ON j.category_id = c.id
         WHERE j.company_id = ?
         ORDER BY j.created_at DESC",
        [$company_id]
    );
}

function getJobWithDetails($company_id, $job_id) {
    global $db;
    
    // Get job details
    $job = $db->fetch(
        "SELECT j.*, c.name as category_name
         FROM jobs j
         LEFT JOIN job_categories c ON j.category_id = c.id
         WHERE j.id = ? AND j.company_id = ?",
        [$job_id, $company_id]
    );
    
    if (!$job) {
        throw new Exception('Job not found');
    }
    
    // Get applications with job seeker details
    $applications = $db->fetchAll(
        "SELECT ja.*, u.name, u.email, u.phone, js.resume_file, js.skills, js.experience_years,
                js.location, js.current_salary, js.expected_salary
         FROM job_applications ja
         JOIN job_seekers js ON ja.job_seeker_id = js.id
         JOIN users u ON js.user_id = u.id
         WHERE ja.job_id = ?
         ORDER BY ja.application_date DESC",
        [$job_id]
    );
    
    // Parse skills JSON for each application
    foreach ($applications as &$app) {
        $app['skills_parsed'] = json_decode($app['skills'] ?? '[]', true);
    }
    
    $job['applications'] = $applications;
    
    return $job;
}

function getCompanyJobStats($company_id) {
    global $db;
    
    $stats = [
        'total_jobs' => 0,
        'active_jobs' => 0,
        'total_applications' => 0,
        'new_applications' => 0,
        'interviews_scheduled' => 0
    ];
    
    // Total jobs
    $result = $db->fetch("SELECT COUNT(*) as count FROM jobs WHERE company_id = ?", [$company_id]);
    $stats['total_jobs'] = $result['count'];
    
    // Active jobs
    $result = $db->fetch("SELECT COUNT(*) as count FROM jobs WHERE company_id = ? AND status = 'active'", [$company_id]);
    $stats['active_jobs'] = $result['count'];
    
    // Total applications
    $result = $db->fetch(
        "SELECT COUNT(*) as count FROM job_applications ja 
         JOIN jobs j ON ja.job_id = j.id 
         WHERE j.company_id = ?", 
        [$company_id]
    );
    $stats['total_applications'] = $result['count'];
    
    // New applications (last 7 days)
    $result = $db->fetch(
        "SELECT COUNT(*) as count FROM job_applications ja 
         JOIN jobs j ON ja.job_id = j.id 
         WHERE j.company_id = ? AND ja.application_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)", 
        [$company_id]
    );
    $stats['new_applications'] = $result['count'];
    
    // Interviews scheduled
    $result = $db->fetch(
        "SELECT COUNT(*) as count FROM job_applications ja 
         JOIN jobs j ON ja.job_id = j.id 
         WHERE j.company_id = ? AND ja.status = 'interview'", 
        [$company_id]
    );
    $stats['interviews_scheduled'] = $result['count'];
    
    return $stats;
}

function generateJobRecommendationsForJob($job_id) {
    global $db;
    
    // Get job details
    $job = $db->fetch("SELECT * FROM jobs WHERE id = ?", [$job_id]);
    if (!$job) return;
    
    // Get all active job seekers
    $jobSeekers = $db->fetchAll(
        "SELECT js.*, u.id as user_id 
         FROM job_seekers js 
         JOIN users u ON js.user_id = u.id 
         WHERE u.status = 'active'"
    );
    
    foreach ($jobSeekers as $jobSeeker) {
        // Calculate match score (simplified version)
        $skills = json_decode($jobSeeker['skills'] ?? '[]', true);
        
        // Handle different skill formats: array of strings or array of objects
        $skillNames = [];
        if (!empty($skills)) {
            foreach ($skills as $skill) {
                if (is_array($skill) && isset($skill['name'])) {
                    $skillNames[] = $skill['name'];
                } elseif (is_string($skill)) {
                    $skillNames[] = $skill;
                }
            }
        }
        
        $score = calculateJobMatchScore($jobSeeker, $job, $skillNames);
        
        if ($score >= 35) { // Minimum threshold (35%)
            // Check if recommendation already exists
            $existing = $db->fetch(
                "SELECT id FROM ai_recommendations WHERE user_id = ? AND job_id = ?",
                [$jobSeeker['user_id'], $job_id]
            );
            
            if (!$existing) {
                // Insert recommendation
                $matchReasons = getMatchReasons($jobSeeker, $job, $skillNames);
                $sql = "INSERT INTO ai_recommendations 
                        (user_id, job_id, recommendation_score, match_reasons, 
                         skill_match_percentage, experience_match_percentage, 
                         location_match, salary_match, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $db->query($sql, [
                    $jobSeeker['user_id'],
                    $job_id,
                    $score,
                    json_encode($matchReasons),
                    calculateSkillMatch($skillNames, $job),
                    calculateExperienceMatch($jobSeeker, $job),
                    calculateLocationMatch($jobSeeker, $job) ? 1 : 0,
                    calculateSalaryMatch($jobSeeker, $job) ? 1 : 0
                ]);
            }
        }
    }
}

// Include the matching functions from recommendations/generate.php
function calculateJobMatchScore($jobSeeker, $job, $skillNames) {
    $score = 0;
    $weights = [
        'skills' => 0.4,
        'experience' => 0.25,
        'location' => 0.15,
        'salary' => 0.10,
        'education' => 0.10
    ];
    
    $skillMatch = calculateSkillMatch($skillNames, $job);
    $score += $skillMatch * $weights['skills'];
    
    $experienceMatch = calculateExperienceMatch($jobSeeker, $job);
    $score += $experienceMatch * $weights['experience'];
    
    $locationMatch = calculateLocationMatch($jobSeeker, $job);
    $score += ($locationMatch ? 100 : 0) * $weights['location'];
    
    $salaryMatch = calculateSalaryMatch($jobSeeker, $job);
    $score += ($salaryMatch ? 100 : 0) * $weights['salary'];
    
    return round($score, 2);
}

function calculateSkillMatch($userSkills, $job) {
    if (empty($userSkills) || !is_array($userSkills)) return 0;
    
    $jobText = strtolower(($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['requirements'] ?? ''));
    $matchedSkills = 0;
    
    foreach ($userSkills as $skill) {
        if (is_string($skill) && !empty(trim($skill))) {
            if (strpos($jobText, strtolower(trim($skill))) !== false) {
                $matchedSkills++;
            }
        }
    }
    
    return count($userSkills) > 0 ? ($matchedSkills / count($userSkills)) * 100 : 0;
}

function calculateExperienceMatch($jobSeeker, $job) {
    $userExperience = intval($jobSeeker['experience_years'] ?? 0);
    $jobLevel = $job['experience_level'];
    
    $levelRequirements = [
        'entry' => [0, 2],
        'mid' => [2, 5],
        'senior' => [5, 10],
        'executive' => [10, 20]
    ];
    
    if (!isset($levelRequirements[$jobLevel])) return 50;
    
    $minRequired = $levelRequirements[$jobLevel][0];
    $maxRequired = $levelRequirements[$jobLevel][1];
    
    if ($userExperience >= $minRequired && $userExperience <= $maxRequired) {
        return 100;
    } elseif ($userExperience < $minRequired) {
        return max(0, 100 - (($minRequired - $userExperience) * 20));
    } else {
        return max(0, 100 - (($userExperience - $maxRequired) * 10));
    }
}

function calculateLocationMatch($jobSeeker, $job) {
    $userLocation = strtolower($jobSeeker['location'] ?? '');
    $jobLocation = strtolower($job['location'] ?? '');
    $jobWorkType = $job['work_type'] ?? '';
    
    $pref = strtolower($jobSeeker['work_preference'] ?? '');
    if ($jobWorkType === 'remote') return ($pref === 'remote');
    
    $userPreference = $jobSeeker['work_preference'] ?? '';
    if ($userPreference === 'remote' && $jobWorkType === 'hybrid') return true;
    
    if (empty($userLocation) || empty($jobLocation)) return false;
    
    $split = function($s){
        $t = preg_split('/[\s,\-]+/', $s);
        $t = array_map('trim', $t);
        $t = array_filter($t, function($x){ return $x !== '' && strlen($x) >= 3; });
        return array_values($t);
    };
    $normCity = function($tokens){
        $map = [
            'mumbai' => 'mumbai','bombay' => 'mumbai',
            'bengaluru' => 'bengaluru','bangalore' => 'bengaluru','banglore' => 'bengaluru',
            'hyderabad' => 'hyderabad','secunderabad' => 'hyderabad',
            'chennai' => 'chennai','madras' => 'chennai',
            'pune' => 'pune','poona' => 'pune',
            'delhi' => 'delhi','new' => 'delhi',
            'kolkata' => 'kolkata','calcutta' => 'kolkata'
        ];
        foreach ($tokens as $t) { if (isset($map[$t])) return $map[$t]; }
        return '';
    };
    $uTokens = $split($userLocation);
    $jTokens = $split($jobLocation);
    $generic = ['india','country','earth'];
    $uTokens = array_values(array_diff($uTokens, $generic));
    $jTokens = array_values(array_diff($jTokens, $generic));
    
    $uCity = $normCity($uTokens);
    $jCity = $normCity($jTokens);
    if ($uCity !== '' && $jCity !== '') {
        return $uCity === $jCity;
    }
    // Strict: if cities cannot be resolved, treat as not matching for non-remote roles
    return false;
}

function calculateSalaryMatch($jobSeeker, $job) {
    $expectedSalary = floatval($jobSeeker['expected_salary'] ?? 0);
    $jobMinSalary = floatval($job['salary_min'] ?? 0);
    $jobMaxSalary = floatval($job['salary_max'] ?? 0);
    
    if ($expectedSalary == 0 || ($jobMinSalary == 0 && $jobMaxSalary == 0)) {
        return true;
    }
    
    return $expectedSalary >= $jobMinSalary && $expectedSalary <= $jobMaxSalary;
}

function getMatchReasons($jobSeeker, $job, $skillNames) {
    $reasons = [];
    
    $jobText = strtolower(($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['requirements'] ?? ''));
    $matchedSkills = [];
    
    if (is_array($skillNames)) {
        foreach ($skillNames as $skill) {
            if (is_string($skill) && !empty(trim($skill))) {
                if (strpos($jobText, strtolower(trim($skill))) !== false) {
                    $matchedSkills[] = $skill;
                }
            }
        }
    }
    
    if (!empty($matchedSkills)) {
        $reasons[] = "Skills match: " . implode(', ', array_slice($matchedSkills, 0, 3));
    }
    
    $experienceMatch = calculateExperienceMatch($jobSeeker, $job);
    if ($experienceMatch >= 80) {
        $reasons[] = "Experience level fits perfectly";
    }
    
    if (calculateLocationMatch($jobSeeker, $job)) {
        $reasons[] = "Location preference matches";
    }
    
    if (calculateSalaryMatch($jobSeeker, $job)) {
        $reasons[] = "Salary meets expectations";
    }
    
    return $reasons;
}
?>
