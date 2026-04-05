<?php
header('Content-Type: application/json');
// Same-origin only; allow cookies for session

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

try {
    // Check if user is logged in
    requireLogin();
    
    $user_id = $_SESSION['user_id'];
    $user_type = getUserType();
    
    // Get input (support both JSON and form data)
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST)) {
            // Form data
            $input = $_POST;
        } else {
            // JSON input
            $json_input = json_decode(file_get_contents('php://input'), true);
            if ($json_input) {
                $input = $json_input;
            }
        }
    }
    
    // Validate required fields
    if (empty($input)) {
        throw new Exception('No data provided');
    }
    
    $db->beginTransaction();
    
    // Update user table (name, phone)
    $user_updates = [];
    $user_params = [];
    
    // Handle both 'name' and 'fullname' field names
    $name = $input['name'] ?? $input['fullname'] ?? '';
    if (!empty($name)) {
        $user_updates[] = "name = ?";
        $user_params[] = trim($name);
    }
    
    if (isset($input['phone']) && !empty($input['phone'])) {
        $user_updates[] = "phone = ?";
        $user_params[] = trim($input['phone']);
    }
    
    if (!empty($user_updates)) {
        $user_params[] = $user_id;
        $db->execute(
            "UPDATE users SET " . implode(", ", $user_updates) . " WHERE id = ?",
            $user_params
        );
    }
    
    // Handle job seeker profile
    if ($user_type === 'job_seeker') {
        // Check if job seeker profile exists
        $profile = $db->fetch("SELECT id FROM job_seekers WHERE user_id = ?", [$user_id]);
        
        // Validate education level if provided
        $valid_education_levels = ['high_school', 'diploma', 'bachelor', 'master', 'phd', 'other'];
        $education_level = isset($input['education_level']) ? trim($input['education_level']) : '';
        
        if (!empty($education_level) && !in_array($education_level, $valid_education_levels)) {
            throw new Exception('Invalid education level');
        }
        
        // Process skills - convert to JSON format if needed
        $skills_input = isset($input['skills']) ? trim($input['skills']) : '';
        $skills_json = null;
        
        if (!empty($skills_input)) {
            // Check if it's already JSON
            $decoded = json_decode($skills_input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $skills_json = json_encode($decoded);
            } else {
                // Convert comma-separated skills to JSON array
                $skills_array = array_map('trim', explode(',', $skills_input));
                $skills_array = array_filter($skills_array); // Remove empty values
                if (!empty($skills_array)) {
                    $skills_json = json_encode(array_values($skills_array));
                }
            }
        }
        
        // Prepare profile data
        $profile_data = [
            'location' => isset($input['location']) ? trim($input['location']) : '',
            'experience_years' => isset($input['experience_years']) ? (int)$input['experience_years'] : 0,
            'education_level' => empty($education_level) ? null : $education_level,
            'skills' => $skills_json,
            'bio' => isset($input['bio']) ? trim($input['bio']) : ''
        ];
        
        if ($profile) {
            // Update existing profile
            $db->execute(
                "UPDATE job_seekers SET location = ?, experience_years = ?, education_level = ?, skills = ?, bio = ? WHERE user_id = ?",
                [
                    $profile_data['location'],
                    $profile_data['experience_years'],
                    $profile_data['education_level'],
                    $profile_data['skills'],
                    $profile_data['bio'],
                    $user_id
                ]
            );
        } else {
            // Insert new profile
            $db->execute(
                "INSERT INTO job_seekers (user_id, location, experience_years, education_level, skills, bio) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $user_id,
                    $profile_data['location'],
                    $profile_data['experience_years'],
                    $profile_data['education_level'],
                    $profile_data['skills'],
                    $profile_data['bio']
                ]
            );
        }
    }
    
    // Handle company profile
    if ($user_type === 'company') {
        $company = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$user_id]);
        
        $company_updates = [];
        $company_params = [];
        
        if (isset($input['company_name']) && !empty($input['company_name'])) {
            $company_updates[] = "company_name = ?";
            $company_params[] = trim($input['company_name']);
        }
        
        if (isset($input['industry']) && !empty($input['industry'])) {
            $company_updates[] = "industry = ?";
            $company_params[] = trim($input['industry']);
        }
        
        if (isset($input['description']) && !empty($input['description'])) {
            $company_updates[] = "description = ?";
            $company_params[] = trim($input['description']);
        }
        
        if (!empty($company_updates)) {
            if ($company) {
                $company_params[] = $user_id;
                $db->execute(
                    "UPDATE companies SET " . implode(", ", $company_updates) . " WHERE user_id = ?",
                    $company_params
                );
            } else {
                // Insert new company profile
                $fields = [];
                $values = [];
                $params = [];
                
                // Extract field names and values
                $field_map = [
                    'company_name' => isset($input['company_name']) ? trim($input['company_name']) : null,
                    'industry' => isset($input['industry']) ? trim($input['industry']) : null,
                    'description' => isset($input['description']) ? trim($input['description']) : null
                ];
                
                foreach ($field_map as $field => $value) {
                    if ($value !== null && $value !== '') {
                        $fields[] = $field;
                        $values[] = '?';
                        $params[] = $value;
                    }
                }
                
                if (!empty($fields)) {
                    $fields[] = 'user_id';
                    $values[] = '?';
                    $params[] = $user_id;
                    
                    $db->execute(
                        "INSERT INTO companies (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")",
                        $params
                    );
                }
            }
        }
    }
    
    $db->commit();
    
    // Log activity
    if (function_exists('logActivity')) {
        logActivity($user_id, 'profile_updated', 'User updated their profile information');
    }

    try {
        regenerateRecommendationsForUser($user_id);
    } catch (Exception $e) {}
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db)) {
        try {
            $db->rollBack();
        } catch (Exception $rollbackError) {
            error_log('Rollback failed: ' . $rollbackError->getMessage());
        }
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
}

function regenerateRecommendationsForUser($user_id) {
    global $db;
    $profile = $db->fetch("SELECT * FROM job_seekers WHERE user_id = ?", [$user_id]);
    if (!$profile) return;
    $skills = json_decode($profile['skills'] ?? '[]', true);
    $skillNames = [];
    if (is_array($skills)) {
        foreach ($skills as $s) {
            if (is_array($s) && isset($s['name'])) { $skillNames[] = $s['name']; }
            elseif (is_string($s)) { $skillNames[] = $s; }
        }
    }
    $jobs = $db->fetchAll(
        "SELECT j.*, c.company_name, c.industry, cat.name as category_name
         FROM jobs j
         JOIN companies c ON j.company_id = c.id
         LEFT JOIN job_categories cat ON j.category_id = cat.id
         WHERE j.status = 'active'
         ORDER BY j.created_at DESC"
    );
    $recs = [];
    foreach ($jobs as $job) {
        $score = _calcScore($profile, $job, $skillNames);
        if ($score >= 35) {
            $recs[] = [
                'job_id' => $job['id'],
                'recommendation_score' => $score,
                'match_reasons' => _matchReasons($profile, $job, $skillNames)
            ];
        }
    }
    $db->query("DELETE FROM ai_recommendations WHERE user_id = ?", [$user_id]);
    foreach ($recs as $rec) {
        $db->query(
            "INSERT INTO ai_recommendations (user_id, job_id, recommendation_score, match_reasons, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$user_id, $rec['job_id'], $rec['recommendation_score'], json_encode($rec['match_reasons'])]
        );
    }
}

function _calcScore($profile, $job, $skillNames) {
    $score = 0;
    $weights = ['skills'=>0.4,'experience'=>0.25,'location'=>0.15,'salary'=>0.10,'education'=>0.10];
    $score += _skillMatch($skillNames, $job) * $weights['skills'];
    $score += _experienceMatch($profile, $job) * $weights['experience'];
    $score += (_locationMatch($profile, $job) ? 100 : 0) * $weights['location'];
    $score += (_salaryMatch($profile, $job) ? 100 : 0) * $weights['salary'];
    $score += _educationMatch($profile, $job) * $weights['education'];
    return round($score, 2);
}

function _skillMatch($userSkills, $job) {
    if (empty($userSkills)) return 0;
    $text = strtolower(($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['requirements'] ?? ''));
    $matched = 0;
    foreach ($userSkills as $s) { if (is_string($s) && $s !== '' && strpos($text, strtolower($s)) !== false) { $matched++; } }
    return count($userSkills) > 0 ? ($matched / count($userSkills)) * 100 : 0;
}

function _experienceMatch($profile, $job) {
    $years = intval($profile['experience_years'] ?? 0);
    $level = $job['experience_level'] ?? '';
    $ranges = ['entry'=>[0,2],'mid'=>[2,5],'senior'=>[5,10],'executive'=>[10,20]];
    if (!isset($ranges[$level])) return 50;
    $min = $ranges[$level][0]; $max = $ranges[$level][1];
    if ($years >= $min && $years <= $max) return 100;
    if ($years < $min) return max(0, 100 - (($min - $years) * 20));
    return max(0, 100 - (($years - $max) * 10));
}

function _locationMatch($profile, $job) {
    $u = strtolower($profile['location'] ?? '');
    $j = strtolower($job['location'] ?? '');
    $wt = strtolower($job['work_type'] ?? '');
    $pref = strtolower($profile['work_preference'] ?? '');
    if ($wt === 'remote') return ($pref === 'remote');
    if ($pref === 'remote' && $wt === 'hybrid') return true;
    if (empty($u) || empty($j)) return false;
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
    $uTokens = $split($u);
    $jTokens = $split($j);
    $generic = ['india','country','earth','state'];
    $uTokens = array_values(array_diff($uTokens, $generic));
    $jTokens = array_values(array_diff($jTokens, $generic));
    $uCity = $normCity($uTokens);
    $jCity = $normCity($jTokens);
    if ($uCity !== '' && $jCity !== '' ) {
        return $uCity === $jCity;
    }
    return false;
}

function _salaryMatch($profile, $job) {
    $exp = floatval($profile['expected_salary'] ?? 0);
    $min = floatval($job['salary_min'] ?? 0);
    $max = floatval($job['salary_max'] ?? 0);
    if ($exp == 0 || ($min == 0 && $max == 0)) return true;
    return $exp >= $min && $exp <= $max;
}

function _educationMatch($profile, $job) {
    $edu = $profile['education_level'] ?? '';
    $req = strtolower($job['requirements'] ?? '');
    $levels = ['high_school'=>1,'diploma'=>2,'bachelor'=>3,'master'=>4,'phd'=>5];
    $u = $levels[$edu] ?? 0;
    foreach ($levels as $k=>$v) {
        if (strpos($req, $k) !== false || strpos($req, str_replace('_',' ', $k)) !== false) {
            return $u >= $v ? 100 : max(0, 100 - (($v - $u) * 25));
        }
    }
    return 50;
}

function _matchReasons($profile, $job, $skillNames) {
    $reasons = [];
    $text = strtolower(($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['requirements'] ?? ''));
    $matched = [];
    foreach ($skillNames as $s) { if (strpos($text, strtolower($s)) !== false) { $matched[] = $s; } }
    if (!empty($matched)) { $reasons[] = 'Skills match: ' . implode(', ', array_slice($matched, 0, 3)); }
    if (_experienceMatch($profile, $job) >= 80) { $reasons[] = 'Experience level fits perfectly'; }
    if (_locationMatch($profile, $job)) { $reasons[] = 'Location preference matches'; }
    if (_salaryMatch($profile, $job)) { $reasons[] = 'Salary meets expectations'; }
    return $reasons;
}
?>
