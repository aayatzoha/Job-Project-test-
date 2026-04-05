<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Not logged in. Please login first.\n";
    exit;
}

$user_id = $_SESSION['user_id'];

echo "Debugging Profile Completion for User ID: $user_id\n";
echo "================================================\n\n";

// Get user data
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
echo "User Data:\n";
print_r($user);

// Get profile data
$profile = $db->fetch("SELECT * FROM job_seekers WHERE user_id = ?", [$user_id]);
echo "\nProfile Data:\n";
print_r($profile);

// Calculate completion
echo "\nCompletion Analysis:\n";
echo "-------------------\n";

$completed_fields = 0;
$total_fields = 5;

if ($profile) {
    echo "Location: '" . ($profile['location'] ?? 'NULL') . "' - " . (!empty($profile['location']) ? "✓ Complete" : "✗ Missing") . "\n";
    if (!empty($profile['location'])) $completed_fields++;
    
    echo "Skills: '" . ($profile['skills'] ?? 'NULL') . "' - ";
    if (!empty($profile['skills'])) {
        $skills_data = json_decode($profile['skills'], true);
        if (is_array($skills_data) && count($skills_data) > 0) {
            echo "✓ Complete (JSON array with " . count($skills_data) . " items)\n";
            $completed_fields++;
        } else if (!is_array($skills_data) && trim($profile['skills']) !== '') {
            echo "✓ Complete (plain text)\n";
            $completed_fields++;
        } else {
            echo "✗ Missing (empty or invalid)\n";
        }
    } else {
        echo "✗ Missing\n";
    }
    
    echo "Experience Years: '" . ($profile['experience_years'] ?? 'NULL') . "' - ";
    if (isset($profile['experience_years']) && $profile['experience_years'] !== null) {
        echo "✓ Complete\n";
        $completed_fields++;
    } else {
        echo "✗ Missing\n";
    }
    
    echo "Education Level: '" . ($profile['education_level'] ?? 'NULL') . "' - " . (!empty($profile['education_level']) ? "✓ Complete" : "✗ Missing") . "\n";
    if (!empty($profile['education_level'])) $completed_fields++;
    
    echo "Bio: '" . (isset($profile['bio']) ? substr($profile['bio'], 0, 50) . "..." : 'NULL') . "' - " . (!empty($profile['bio']) ? "✓ Complete" : "✗ Missing") . "\n";
    if (!empty($profile['bio'])) $completed_fields++;
} else {
    echo "No profile record found!\n";
}

// User fields
$user_fields_completed = 0;
$user_total_fields = 2;

if ($user) {
    echo "\nUser Fields:\n";
    echo "Name: '" . ($user['name'] ?? 'NULL') . "' - " . (!empty($user['name']) ? "✓ Complete" : "✗ Missing") . "\n";
    if (!empty($user['name'])) $user_fields_completed++;
    
    echo "Phone: '" . ($user['phone'] ?? 'NULL') . "' - " . (!empty($user['phone']) ? "✓ Complete" : "✗ Missing") . "\n";
    if (!empty($user['phone'])) $user_fields_completed++;
}

$total_completion_fields = $total_fields + $user_total_fields;
$total_completed = $completed_fields + $user_fields_completed;
$final_completion_score = ($total_completed / $total_completion_fields) * 100;

echo "\nFinal Calculation:\n";
echo "Profile fields completed: $completed_fields / $total_fields\n";
echo "User fields completed: $user_fields_completed / $user_total_fields\n";
echo "Total completed: $total_completed / $total_completion_fields\n";
echo "Completion percentage: " . number_format($final_completion_score, 1) . "%\n";
?>