<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please login to use the AI Assistant'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['message'])) {
        throw new Exception('Message is required');
    }
    
    $user_id = $_SESSION['user_id'];
    $message = trim($input['message']);
    $session_id = $input['session_id'] ?? 'session_' . time();
    
    // Get user info for personalization
    $user = getUserInfo($user_id);
    $userName = $user['name'] ?? 'there';
    
    // Simple intent-based responses
    $response = generateSimpleResponse($message, $userName);
    
    // Save conversation to database (optional)
    try {
        $db->execute(
            "INSERT INTO chat_messages (user_id, session_id, message, is_user_message, message_type, created_at) VALUES (?, ?, ?, 1, 'text', NOW())",
            [$user_id, $session_id, $message]
        );
        
        $db->execute(
            "INSERT INTO chat_messages (user_id, session_id, message, is_user_message, message_type, created_at) VALUES (?, ?, ?, 0, 'text', NOW())",
            [$user_id, $session_id, $response['message']]
        );
    } catch (Exception $e) {
        // Continue even if logging fails
        error_log("Chat logging failed: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'response' => $response,
        'session_id' => $session_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generateSimpleResponse($message, $userName) {
    $message_lower = strtolower($message);
    
    // Greeting responses
    if (preg_match('/\b(hi|hello|hey|good morning|good afternoon|good evening)\b/', $message_lower)) {
        return [
            'message' => "Hello $userName! 👋 I'm your AI career assistant. I can help you with job searching, resume advice, career guidance, and more. What would you like to know?",
            'message_type' => 'text',
            'suggestions' => ['Find jobs', 'Resume help', 'Career advice', 'Take assessments']
        ];
    }
    
    // Job search responses
    if (preg_match('/\b(job|jobs|work|employment|position|vacancy|career|find work)\b/', $message_lower)) {
        return [
            'message' => "I'd love to help you find the perfect job! 🎯\n\nHere's how I can assist:\n• Search for jobs matching your skills\n• Get personalized recommendations\n• Help with job applications\n• Provide interview tips\n\nTo get started, make sure your profile is complete with your skills and experience. Would you like me to help you update your profile?",
            'message_type' => 'text',
            'suggestions' => ['Update profile', 'Search jobs', 'View recommendations', 'Application tips']
        ];
    }
    
    // Resume responses
    if (preg_match('/\b(resume|cv|curriculum vitae)\b/', $message_lower)) {
        return [
            'message' => "I can help you with your resume! 📝\n\n• Upload your existing resume\n• Build a new resume with our AI-powered builder\n• Get optimization suggestions\n• Tailor your resume for specific jobs\n\nA strong resume is crucial for landing interviews. What would you like to do with your resume?",
            'message_type' => 'text',
            'suggestions' => ['Upload resume', 'Build resume', 'Resume tips', 'Optimize resume']
        ];
    }
    
    // Career advice responses
    if (preg_match('/\b(advice|help|guidance|tips|career path|development)\b/', $message_lower)) {
        return [
            'message' => "I'm here to provide career guidance! 💡\n\n• Skill development recommendations\n• Career path planning\n• Interview preparation\n• Salary negotiation tips\n• Professional networking advice\n\nWhat specific area would you like guidance on?",
            'message_type' => 'text',
            'suggestions' => ['Skill development', 'Interview tips', 'Career planning', 'Networking']
        ];
    }
    
    // Assessment responses
    if (preg_match('/\b(assessment|test|exam|quiz|evaluation|skill test)\b/', $message_lower)) {
        return [
            'message' => "Taking assessments is a great way to showcase your skills! 📊\n\n• Validate your technical abilities\n• Improve your job matching score\n• Stand out to employers\n• Get detailed feedback\n\nAssessments help employers understand your capabilities better. Ready to take one?",
            'message_type' => 'text',
            'suggestions' => ['Take assessment', 'View results', 'Assessment tips', 'Find assessments']
        ];
    }
    
    // Application status responses
    if (preg_match('/\b(application|applied|status)\b/', $message_lower)) {
        return [
            'message' => "Let me help you track your applications! 📄\n\n• View all your applications\n• Check application statuses\n• Get follow-up reminders\n• Application tips and best practices\n\nStaying organized with your applications is key to a successful job search!",
            'message_type' => 'text',
            'suggestions' => ['View applications', 'Application status', 'Follow-up tips', 'Apply to jobs']
        ];
    }
    
    // Default response
    return [
        'message' => "I understand you're looking for help! I'm your AI career assistant, specialized in:\n\n🎯 **Job Search** - Find opportunities that match your skills\n📝 **Resume Help** - Create and optimize your resume\n💼 **Career Advice** - Get guidance for your career path\n📊 **Assessments** - Showcase your abilities\n📱 **Application Tracking** - Monitor your job applications\n\nWhat specific area can I help you with today?",
        'message_type' => 'text',
        'suggestions' => ['Find jobs', 'Resume help', 'Career advice', 'Take assessments', 'Help']
    ];
}
?>