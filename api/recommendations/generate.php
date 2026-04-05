<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get existing recommendations
    try {
        $user_id = $_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];
        
        if ($user_type !== 'job_seeker') {
            throw new Exception('Only job seekers can get recommendations');
        }
        
        $recommendations = getJobRecommendations($user_id);
        
        echo json_encode([
            'success' => true,
            'recommendations' => $recommendations
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate new recommendations
    try {
        $user_id = $_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];
        
        if ($user_type !== 'job_seeker') {
            throw new Exception('Only job seekers can generate recommendations');
        }
        
        // Generate recommendations using AI algorithm
        $recommendations = generateJobRecommendations($user_id);
        
        // Store recommendations in database
        storeRecommendations($user_id, $recommendations);
        
        // Log activity
        logActivity($user_id, 'recommendations_generated', 'Count: ' . count($recommendations));
        
        echo json_encode([
            'success' => true,
            'message' => 'Recommendations generated successfully',
            'count' => count($recommendations),
            'recommendations' => $recommendations
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

/**
 * Enhanced AI-powered job recommendation generation
 */
function generateJobRecommendations($user_id) {
    global $db;
    
    // Get job seeker profile
    $jobSeeker = $db->fetch(
        "SELECT js.*, u.name, u.email, u.phone 
         FROM job_seekers js 
         JOIN users u ON js.user_id = u.id 
         WHERE js.user_id = ?", 
        [$user_id]
    );
    
    if (!$jobSeeker) {
        throw new Exception('Job seeker profile not found');
    }
    
    // Parse skills from profile (supports array of objects or strings)
    $skills = json_decode($jobSeeker['skills'] ?? '[]', true);
    $skillNames = [];
    if (is_array($skills)) {
        foreach ($skills as $s) {
            if (is_array($s) && isset($s['name'])) {
                $skillNames[] = $s['name'];
            } elseif (is_string($s)) {
                $skillNames[] = $s;
            }
        }
    }
    
    // Get active jobs
    $jobs = $db->fetchAll(
        "SELECT j.*, c.company_name, c.industry, cat.name as category_name,
                j.title, j.description, j.requirements, j.location, j.salary_min, j.salary_max
         FROM jobs j
         JOIN companies c ON j.company_id = c.id
         LEFT JOIN job_categories cat ON j.category_id = cat.id
         WHERE j.status = 'active'
         ORDER BY j.created_at DESC"
    );
    
    $recommendations = [];
    
    foreach ($jobs as $job) {
        $score = calculateJobMatchScore($jobSeeker, $job, $skillNames);
        $skillMatch = calculateSkillMatch($skillNames, $job);
        
        if ($score >= 35 && $skillMatch > 0) {
            $recommendations[] = [
                'job_id' => $job['id'],
                'job' => $job,
                'recommendation_score' => $score,
                'match_reasons' => getMatchReasons($jobSeeker, $job, $skillNames),
                'skill_match_percentage' => $skillMatch,
                'experience_match_percentage' => calculateExperienceMatch($jobSeeker, $job),
                'location_match' => calculateLocationMatch($jobSeeker, $job),
                'salary_match' => calculateSalaryMatch($jobSeeker, $job)
            ];
        }
    }
    
    // Sort by recommendation score
    usort($recommendations, function($a, $b) {
        return $b['recommendation_score'] <=> $a['recommendation_score'];
    });
    
    // Return top 20 recommendations
    return array_slice($recommendations, 0, 20);
}

/**
 * Calculate job match score using weighted algorithm
 */
function calculateJobMatchScore($jobSeeker, $job, $skillNames) {
    $score = 0;
    $weights = [
        'skills' => 0.4,        // 40% weight
        'experience' => 0.25,   // 25% weight
        'location' => 0.15,     // 15% weight
        'salary' => 0.10,       // 10% weight
        'education' => 0.10     // 10% weight
    ];
    
    // Skills matching
    $skillMatch = calculateSkillMatch($skillNames, $job);
    $score += $skillMatch * $weights['skills'];
    
    // Experience matching
    $experienceMatch = calculateExperienceMatch($jobSeeker, $job);
    $score += $experienceMatch * $weights['experience'];
    
    // Location matching
    $locationMatch = calculateLocationMatch($jobSeeker, $job);
    $score += ($locationMatch ? 100 : 0) * $weights['location'];
    
    // Salary matching
    $salaryMatch = calculateSalaryMatch($jobSeeker, $job);
    $score += ($salaryMatch ? 100 : 0) * $weights['salary'];
    
    // Education matching
    $educationMatch = calculateEducationMatch($jobSeeker, $job);
    $score += $educationMatch * $weights['education'];
    
    return round($score, 2);
}

/**
 * Calculate skill matching percentage
 */
function calculateSkillMatch($userSkills, $job) {
    if (empty($userSkills)) return 0;
    
    $jobText = strtolower($job['title'] . ' ' . $job['description'] . ' ' . $job['requirements']);
    $matchedSkills = 0;
    
    foreach ($userSkills as $skill) {
        if (strpos($jobText, strtolower($skill)) !== false) {
            $matchedSkills++;
        }
    }
    
    return count($userSkills) > 0 ? ($matchedSkills / count($userSkills)) * 100 : 0;
}

/**
 * Calculate experience matching percentage
 */
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

/**
 * Calculate location matching
 */
function calculateLocationMatch($jobSeeker, $job) {
    $userLocation = strtolower($jobSeeker['location'] ?? '');
    $jobLocation = strtolower($job['location'] ?? '');
    $jobWorkType = $job['work_type'] ?? '';
    
    // Remote work matches everyone
    if ($jobWorkType === 'remote') return true;
    
    // If user prefers remote and job allows hybrid
    $userPreference = $jobSeeker['work_preference'] ?? '';
    if ($userPreference === 'remote' && $jobWorkType === 'hybrid') return true;
    
    // Location-based matching
    if (empty($userLocation) || empty($jobLocation)) return false;
    
    // Simple string matching (can be enhanced with geographical data)
    return strpos($jobLocation, $userLocation) !== false || 
           strpos($userLocation, $jobLocation) !== false;
}

/**
 * Calculate salary matching
 */
function calculateSalaryMatch($jobSeeker, $job) {
    $expectedSalary = floatval($jobSeeker['expected_salary'] ?? 0);
    $jobMinSalary = floatval($job['salary_min'] ?? 0);
    $jobMaxSalary = floatval($job['salary_max'] ?? 0);
    
    if ($expectedSalary == 0 || ($jobMinSalary == 0 && $jobMaxSalary == 0)) {
        return true; // If no salary info, assume match
    }
    
    return $expectedSalary >= $jobMinSalary && $expectedSalary <= $jobMaxSalary;
}

/**
 * Calculate education matching
 */
function calculateEducationMatch($jobSeeker, $job) {
    // Simple education level matching
    $userEducation = $jobSeeker['education_level'] ?? '';
    $jobRequirements = strtolower($job['requirements'] ?? '');
    
    $educationLevels = [
        'high_school' => 1,
        'diploma' => 2,
        'bachelor' => 3,
        'master' => 4,
        'phd' => 5
    ];
    
    $userLevel = $educationLevels[$userEducation] ?? 0;
    
    // Check if job requirements mention education
    foreach ($educationLevels as $level => $value) {
        if (strpos($jobRequirements, $level) !== false || 
            strpos($jobRequirements, str_replace('_', ' ', $level)) !== false) {
            return $userLevel >= $value ? 100 : max(0, 100 - (($value - $userLevel) * 25));
        }
    }
    
    return 50; // Neutral if no education requirements specified
}

/**
 * Get detailed match reasons
 */
function getMatchReasons($jobSeeker, $job, $skillNames) {
    $reasons = [];
    
    // Skill matches
    $jobText = strtolower($job['title'] . ' ' . $job['description'] . ' ' . $job['requirements']);
    $matchedSkills = [];
    
    foreach ($skillNames as $skill) {
        if (strpos($jobText, strtolower($skill)) !== false) {
            $matchedSkills[] = $skill;
        }
    }
    
    if (!empty($matchedSkills)) {
        $reasons[] = "Skills match: " . implode(', ', array_slice($matchedSkills, 0, 3));
    }
    
    // Experience match
    $experienceMatch = calculateExperienceMatch($jobSeeker, $job);
    if ($experienceMatch >= 80) {
        $reasons[] = "Experience level fits perfectly";
    }
    
    // Location match
    if (calculateLocationMatch($jobSeeker, $job)) {
        $reasons[] = "Location preference matches";
    }
    
    // Salary match
    if (calculateSalaryMatch($jobSeeker, $job)) {
        $reasons[] = "Salary meets expectations";
    }
    
    return $reasons;
}

/**
 * Store recommendations in database
 */
function storeRecommendations($user_id, $recommendations) {
    global $db;
    
    // Clear existing recommendations
    $db->query("DELETE FROM ai_recommendations WHERE user_id = ?", [$user_id]);
    
    // Insert new recommendations
    $sql = "INSERT INTO ai_recommendations 
            (user_id, job_id, recommendation_score, match_reasons, 
             skill_match_percentage, experience_match_percentage, 
             location_match, salary_match, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    foreach ($recommendations as $rec) {
        $matchReasonsJson = json_encode($rec['match_reasons']);
        
        $db->query($sql, [
            $user_id,
            $rec['job_id'],
            $rec['recommendation_score'],
            $matchReasonsJson,
            $rec['skill_match_percentage'],
            $rec['experience_match_percentage'],
            $rec['location_match'] ? 1 : 0,
            $rec['salary_match'] ? 1 : 0
        ]);
    }
}

/**
 * Get existing recommendations from database
 */
function getJobRecommendations($user_id) {
    global $db;
    
    return $db->fetchAll(
        "SELECT ar.*, j.title, j.description, j.location, j.job_type, 
                j.salary_min, j.salary_max, j.work_type,
                c.company_name, c.industry, cat.name as category_name
         FROM ai_recommendations ar
         JOIN jobs j ON ar.job_id = j.id
         JOIN companies c ON j.company_id = c.id
         LEFT JOIN job_categories cat ON j.category_id = cat.id
         WHERE ar.user_id = ? AND ar.status = 'active' AND j.status = 'active'
         ORDER BY ar.recommendation_score DESC
         LIMIT 20",
        [$user_id]
    );
}
?>
