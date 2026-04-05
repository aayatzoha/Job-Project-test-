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
        throw new Exception('Only job seekers can view recommendations');
    }
    
    // Get job_seeker_id for has_applied check
    $job_seeker = $db->fetch("SELECT id FROM job_seekers WHERE user_id = ?", [$user_id]);
    $job_seeker_id = $job_seeker ? $job_seeker['id'] : null;
    
    // Pull ALL active jobs, enriched with AI recommendation data when available
    // NOTE: Build params in the exact order of placeholders in the SQL.
    $params = [];
    $sql = "SELECT 
                j.id as job_id,
                j.title,
                j.description,
                j.location,
                j.job_type,
                j.salary_min,
                j.salary_max,
                j.requirements,
                j.benefits,
                j.work_type,
                j.experience_level,
                j.created_at,
                c.company_name,
                c.industry,
                IFNULL(ar.match_reasons, '[]') as match_reasons,
                IFNULL(ar.recommendation_score, 0) as recommendation_score,
                IFNULL(ar.status, 'active') as status,
                IFNULL(ar.skill_match_percentage, 0) as skill_match_percentage,
                IFNULL(ar.experience_match_percentage, 0) as experience_match_percentage,
                IFNULL(ar.location_match, 0) as location_match,
                IFNULL(ar.salary_match, 0) as salary_match";
    
    if ($job_seeker_id) {
        $sql .= ",
                (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id AND ja.job_seeker_id = ?) as has_applied";
        // First placeholder: job_seeker_id for has_applied subquery
        $params[] = $job_seeker_id;
    } else {
        $sql .= ", 0 as has_applied";
    }
    
    $sql .= "
            FROM jobs j
            JOIN companies c ON j.company_id = c.id
            LEFT JOIN ai_recommendations ar
                ON ar.job_id = j.id
                AND ar.user_id = ?
                AND ar.status = 'active'
            WHERE j.status = 'active'
            ORDER BY ar.recommendation_score DESC, j.created_at DESC";

    // Second placeholder: user_id for AI recommendations join
    $params[] = $user_id;
    $recommendations = $db->fetchAll($sql, $params);
    
    // Convert has_applied to boolean and normalize match reasons
    if ($recommendations === false) {
        $recommendations = [];
    }
    $minScore = isset($_GET['min_score']) ? max(0, min(100, (int)$_GET['min_score'])) : 35;
    $profile = $db->fetch("SELECT * FROM job_seekers WHERE user_id = ?", [$user_id]);
    $skillNames = [];
    if ($profile && !empty($profile['skills'])) {
        $skillsDecoded = json_decode($profile['skills'], true);
        if (is_array($skillsDecoded)) {
            foreach ($skillsDecoded as $s) {
                if (is_array($s) && isset($s['name'])) {
                    $skillNames[] = strtolower($s['name']);
                } elseif (is_string($s)) {
                    $skillNames[] = strtolower($s);
                }
            }
        }
    }
    $filtered = [];
    foreach ($recommendations as $rec) {
        $rec['has_applied'] = (bool)($rec['has_applied'] ?? 0);
        $score = (float)($rec['recommendation_score'] ?? 0);
        // Determine skill match presence
        $hasSkillMatch = false;
        if (isset($rec['skill_match_percentage'])) {
            $hasSkillMatch = ((float)$rec['skill_match_percentage'] > 0);
        }
        
        if ($score <= 0) {
            $score = calculateBasicMatchScore($rec, $profile);
            $rec['recommendation_score'] = $score;
            $rec['match_reasons'] = json_encode(generateMatchReasons($rec, $profile));
            if (!$hasSkillMatch && !empty($skillNames)) {
                $jobText = strtolower(($rec['title'] ?? '') . ' ' . ($rec['description'] ?? '') . ' ' . ($rec['requirements'] ?? ''));
                foreach ($skillNames as $sk) {
                    if ($sk !== '' && strpos($jobText, $sk) !== false) { $hasSkillMatch = true; break; }
                }
            }
        } else {
            if (is_null($rec['match_reasons']) || $rec['match_reasons'] === '') {
                $rec['match_reasons'] = json_encode([
                    'New opportunity available now',
                    'Active job posting from ' . ($rec['company_name'] ?? 'company')
                ]);
            }
            if (!$hasSkillMatch && !empty($skillNames)) {
                $jobText = strtolower(($rec['title'] ?? '') . ' ' . ($rec['description'] ?? '') . ' ' . ($rec['requirements'] ?? ''));
                foreach ($skillNames as $sk) {
                    if ($sk !== '' && strpos($jobText, $sk) !== false) { $hasSkillMatch = true; break; }
                }
            }
        }
        $userExperience = (int)($profile['experience_years'] ?? 0);
        $jobLevel = strtolower($rec['experience_level'] ?? '');
        if ($userExperience === 0) {
            $reqText = strtolower(($rec['requirements'] ?? '') . ' ' . ($rec['description'] ?? ''));
            $mentionsYears = preg_match('/(minimum\s*[2-9]\s*years?|[2-9]\s*\+?\s*years?|\bsenior\b|\bmid\b)/', $reqText);
            if (($jobLevel && $jobLevel !== 'entry') || $mentionsYears) {
                continue;
            }
        }
        $userLocation = strtolower(trim($profile['location'] ?? ''));
        $jobLocation = strtolower(trim($rec['location'] ?? ''));
        $workType = strtolower(trim($rec['work_type'] ?? ''));
        $pref = strtolower(trim($profile['work_preference'] ?? ''));
        if (!empty($userLocation)) {
            $locationOk = true;
            if ($workType === 'remote') {
                $locationOk = ($pref === 'remote');
            } else {
                if (!empty($jobLocation)) {
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
                    $generic = ['india','country','earth','state'];
                    $uTokens = array_values(array_diff($uTokens, $generic));
                    $jTokens = array_values(array_diff($jTokens, $generic));
                    $uCity = $normCity($uTokens);
                    $jCity = $normCity($jTokens);
                    if ($uCity !== '' && $jCity !== '') {
                        $locationOk = ($uCity === $jCity);
                    } else {
                        // If we cannot resolve both cities, be strict and do not match non-remote cross-city results
                        $locationOk = false;
                    }
                } else {
                    $locationOk = false;
                }
            }
            if (!$locationOk) {
                continue;
            }
        }
        if ($score >= $minScore && ($hasSkillMatch || empty($skillNames))) {
            $filtered[] = $rec;
        }
    }
    usort($filtered, function($a, $b) {
        $sa = (float)($a['recommendation_score'] ?? 0);
        $sb = (float)($b['recommendation_score'] ?? 0);
        if ($sb === $sa) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        }
        return $sb <=> $sa;
    });
    if (count($filtered) === 0) {
        $fallback = generateBasicRecommendations($user_id);
        $pref = strtolower(trim($profile['work_preference'] ?? ''));
        $userLocation = strtolower(trim($profile['location'] ?? ''));
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
        $uCity = $normCity($split($userLocation));
        $filtered = [];
        foreach ($fallback as $job) {
            $workType = strtolower(trim($job['work_type'] ?? ''));
            $jobCity = $normCity($split(strtolower(trim($job['location'] ?? ''))));
            $ok = false;
            if ($workType === 'remote') {
                $ok = ($pref === 'remote');
            } else {
                $ok = ($uCity !== '' && $jobCity !== '' && $uCity === $jobCity);
            }
            if ($ok) {
                $filtered[] = $job;
            }
        }
    }
    $recommendations = $filtered;
    
    echo json_encode([
        'success' => true,
        'recommendations' => $recommendations,
        'count' => count($recommendations)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Generate basic job recommendations when AI recommendations aren't available
 */
function generateBasicRecommendations($user_id) {
    global $db;
    
    // Get user's profile for basic matching
    $profile = $db->fetch("SELECT * FROM job_seekers WHERE user_id = ?", [$user_id]);
    $job_seeker_id = $profile ? $profile['id'] : null;
    
    // Get available jobs with has_applied status
    $sql = "SELECT j.*, c.company_name, c.industry";
    
    if ($job_seeker_id) {
        $sql .= ", (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id AND ja.job_seeker_id = ?) as has_applied";
    } else {
        $sql .= ", 0 as has_applied";
    }
    
    $sql .= " FROM jobs j
            JOIN companies c ON j.company_id = c.id
            WHERE j.status = 'active'
            ORDER BY j.created_at DESC
            LIMIT 10";
    
    $params = $job_seeker_id ? [$job_seeker_id] : [];
    $jobs = $db->fetchAll($sql, $params);
    
    $recommendations = [];
    foreach ($jobs as $job) {
        $score = calculateBasicMatchScore($job, $profile);
        $reasons = generateMatchReasons($job, $profile);
        
        $recommendations[] = [
            'job_id' => $job['id'],
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
            'recommendation_score' => $score,
            'match_reasons' => json_encode($reasons),
            'has_applied' => (bool)($job['has_applied'] ?? 0),
            'status' => 'active'
        ];
    }
    
    // Sort by score
    usort($recommendations, function($a, $b) {
        return $b['recommendation_score'] <=> $a['recommendation_score'];
    });
    
    return $recommendations;
}

/**
 * Calculate basic match score between job and profile
 */
function calculateBasicMatchScore($job, $profile) {
    $score = 50; // Base score
    
    if ($profile) {
        // Match skills
        if (!empty($profile['skills']) && !empty($job['requirements'])) {
            $profileSkills = json_decode($profile['skills'], true) ?? [];
            $jobRequirements = strtolower($job['requirements']);
            
            $matchingSkills = 0;
            foreach ($profileSkills as $skill) {
                if (is_string($skill)) {
                    $skillName = strtolower($skill);
                } else if (isset($skill['name'])) {
                    $skillName = strtolower($skill['name']);
                } else {
                    continue;
                }
                
                if (strpos($jobRequirements, $skillName) !== false) {
                    $matchingSkills++;
                }
            }
            
            if ($matchingSkills > 0) {
                $score += min(30, $matchingSkills * 10);
            }
        }
        
        // Match experience level
        if (!empty($profile['experience_years']) && !empty($job['requirements'])) {
            $experience = $profile['experience_years'];
            $requirements = strtolower($job['requirements']);
            
            if ($experience >= 5 && strpos($requirements, 'senior') !== false) {
                $score += 15;
            } elseif ($experience >= 2 && strpos($requirements, 'mid') !== false) {
                $score += 15;
            } elseif ($experience < 2 && (strpos($requirements, 'entry') !== false || strpos($requirements, 'junior') !== false)) {
                $score += 15;
            }
        }
    }
    
    return min(95, $score); // Cap at 95%
}

/**
 * Generate match reasons
 */
function generateMatchReasons($job, $profile) {
    $reasons = [];
    
    if ($profile) {
        if (!empty($profile['skills'])) {
            $reasons[] = "Your skills align with job requirements";
        }
        
        if (!empty($profile['experience_years'])) {
            $experience = $profile['experience_years'];
            if ($experience >= 3) {
                $reasons[] = "Your {$experience} years of experience matches the role";
            } else {
                $reasons[] = "Great opportunity to grow your career";
            }
        }
        
        if (!empty($profile['location']) && !empty($job['location'])) {
            $userLocation = strtolower(trim($profile['location']));
            $jobLocation = strtolower(trim($job['location']));
            $workType = strtolower(trim($job['work_type'] ?? ''));
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
                    'delhi' => 'delhi',
                    'kolkata' => 'kolkata','calcutta' => 'kolkata'
                ];
                foreach ($tokens as $t) { if (isset($map[$t])) return $map[$t]; }
                return '';
            };
            $uTokens = $split($userLocation);
            $jTokens = $split($jobLocation);
            $generic = ['india','country','earth','state'];
            $uTokens = array_values(array_diff($uTokens, $generic));
            $jTokens = array_values(array_diff($jTokens, $generic));
            $uCity = $normCity($uTokens);
            $jCity = $normCity($jTokens);
            if ($workType === 'remote' || ($uCity !== '' && $jCity !== '' && $uCity === $jCity)) {
                $reasons[] = "Job location aligns with your preferences";
            }
        }
    }
    
    // Add generic reasons if none found
    if (empty($reasons)) {
        $reasons[] = "New opportunity in " . ($job['industry'] ?? 'your field');
        $reasons[] = "Growing company with career potential";
    }
    
    return $reasons;
}
?>
