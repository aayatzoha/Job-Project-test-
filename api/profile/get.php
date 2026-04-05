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
    
    // Get user basic info
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Get job seeker profile
    $profile = $db->fetch("SELECT * FROM job_seekers WHERE user_id = ?", [$user_id]);
    
    // Process skills for display (convert JSON back to comma-separated string)
    $skills_display = '';
    if ($profile && !empty($profile['skills'])) {
        $skills_json = $profile['skills'];
        $skills_array = json_decode($skills_json, true);
        if (is_array($skills_array)) {
            $skills_display = implode(', ', $skills_array);
        } else {
            // Fallback if it's not JSON (legacy data)
            $skills_display = $skills_json;
        }
    }
    
    // Prepare response data
    $response_data = [
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => isset($user['phone']) ? $user['phone'] : '',
        'location' => $profile ? ($profile['location'] ?? '') : '',
        'experience_years' => $profile ? ($profile['experience_years'] ?? 0) : 0,
        'education_level' => $profile ? ($profile['education_level'] ?? '') : '',
        'skills' => $skills_display,
        'bio' => $profile ? ($profile['bio'] ?? '') : ''
    ];
    
    echo json_encode([
        'success' => true,
        'profile' => $response_data
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>