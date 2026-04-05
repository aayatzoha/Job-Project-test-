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
    // Get chat history
    try {
        $user_id = $_SESSION['user_id'];
        $session_id = $_GET['session_id'] ?? '';
        $limit = intval($_GET['limit'] ?? 50);
        
        if (empty($session_id)) {
            // Get all recent sessions
            $sessions = getChatSessions($user_id, 10);
            echo json_encode([
                'success' => true,
                'sessions' => $sessions
            ]);
        } else {
            // Get messages for specific session
            $messages = getChatMessages($user_id, $session_id, $limit);
            echo json_encode([
                'success' => true,
                'session_id' => $session_id,
                'messages' => $messages
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Send message to chatbot
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $user_id = $_SESSION['user_id'];
        $message = sanitizeInput($input['message'] ?? '');
        $session_id = sanitizeInput($input['session_id'] ?? '');
        $language = sanitizeInput($input['language'] ?? 'en');
        
        if (empty($message)) {
            throw new Exception('Message is required');
        }
        
        // Generate session ID if not provided
        if (empty($session_id)) {
            $session_id = generateSessionId();
        }
        
        // Save user message
        saveMessage($user_id, $session_id, $message, true);
        
        // Process message and generate bot response
        $botResponse = processChatMessage($user_id, $message, $session_id, $language);
        
        // Save bot response
        saveMessage($user_id, $session_id, $botResponse['message'], false, $botResponse['message_type']);
        
        // Log activity
        logActivity($user_id, 'chatbot_interaction', "Session: $session_id");
        
        echo json_encode([
            'success' => true,
            'session_id' => $session_id,
            'response' => $botResponse
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
 * Generate unique session ID
 */
function generateSessionId() {
    return 'chat_' . uniqid() . '_' . time();
}

/**
 * Save chat message to database
 */
function saveMessage($user_id, $session_id, $message, $is_user_message, $message_type = 'text') {
    global $db;
    
    $sql = "INSERT INTO chat_messages 
            (user_id, session_id, message, is_user_message, message_type, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $db->execute($sql, [$user_id, $session_id, $message, $is_user_message ? 1 : 0, $message_type]);
}

/**
 * Get chat sessions for user
 */
function getChatSessions($user_id, $limit = 10) {
    global $db;
    
    return $db->fetchAll(
        "SELECT session_id, 
                MIN(created_at) as started_at,
                MAX(created_at) as last_activity,
                COUNT(*) as message_count,
                (SELECT message FROM chat_messages WHERE user_id = ? AND session_id = cm.session_id ORDER BY created_at DESC LIMIT 1) as last_message
         FROM chat_messages cm
         WHERE user_id = ?
         GROUP BY session_id
         ORDER BY last_activity DESC
         LIMIT ?",
        [$user_id, $user_id, $limit]
    );
}

/**
 * Get chat messages for session
 */
function getChatMessages($user_id, $session_id, $limit = 50) {
    global $db;
    
    return $db->fetchAll(
        "SELECT * FROM chat_messages 
         WHERE user_id = ? AND session_id = ?
         ORDER BY created_at ASC
         LIMIT ?",
        [$user_id, $session_id, $limit]
    );
}

/**
 * Main chatbot message processing function
 */
function processChatMessage($user_id, $message, $session_id, $language = 'en') {
    // Get user context
    $userContext = getUserContext($user_id);
    
    // Analyze message intent
    $intent = analyzeMessageIntent($message, $language);
    
    // Generate appropriate response based on intent
    switch ($intent['category']) {
        case 'job_search':
            return handleJobSearchQuery($user_id, $message, $intent, $userContext, $language);
        case 'resume':
            return handleResumeQuery($user_id, $message, $intent, $userContext, $language);
        case 'career_advice':
            return handleCareerAdviceQuery($user_id, $message, $intent, $userContext, $language);
        case 'assessment':
            return handleAssessmentQuery($user_id, $message, $intent, $userContext, $language);
        case 'application_status':
            return handleApplicationStatusQuery($user_id, $message, $intent, $userContext, $language);
        case 'greeting':
            return handleGreeting($user_id, $userContext, $language);
        case 'help':
            return handleHelpQuery($user_id, $message, $intent, $userContext, $language);
        default:
            return handleGeneralQuery($user_id, $message, $intent, $userContext, $language);
    }
}

/**
 * Get user context for personalized responses
 */
function getUserContext($user_id) {
    global $db;
    
    $user = getUserInfo($user_id);
    $context = [
        'user' => $user,
        'user_type' => $user['user_type'],
        'name' => $user['name']
    ];
    
    if ($user['user_type'] === 'job_seeker') {
        // Get job seeker profile
        $profile = $db->fetch("SELECT * FROM job_seekers WHERE user_id = ?", [$user_id]);
        $context['profile'] = $profile;
        
        // Get recent applications
        $applications = $db->fetchAll(
            "SELECT ja.*, j.title, c.company_name 
             FROM job_applications ja 
             JOIN jobs j ON ja.job_id = j.id 
             JOIN companies c ON j.company_id = c.id 
             WHERE ja.job_seeker_id = ? 
             ORDER BY ja.application_date DESC 
             LIMIT 5",
            [$profile['id'] ?? 0]
        );
        $context['recent_applications'] = $applications;
        
        // Get skills
        $skills = json_decode($profile['skills'] ?? '[]', true);
        $context['skills'] = array_column($skills, 'name');
    }
    
    return $context;
}

/**
 * Analyze message intent using keyword matching and patterns
 */
function analyzeMessageIntent($message, $language = 'en') {
    $message_lower = strtolower($message);
    
    // Define intent patterns
    $intents = [
        'greeting' => [
            'patterns' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'greetings'],
            'confidence' => 0.9
        ],
        'job_search' => [
            'patterns' => ['job', 'jobs', 'position', 'vacancy', 'opening', 'career', 'work', 'employment', 'find work'],
            'confidence' => 0.8
        ],
        'resume' => [
            'patterns' => ['resume', 'cv', 'curriculum vitae', 'profile', 'upload', 'download'],
            'confidence' => 0.8
        ],
        'career_advice' => [
            'patterns' => ['advice', 'help', 'guidance', 'tips', 'career path', 'skill development', 'interview'],
            'confidence' => 0.7
        ],
        'assessment' => [
            'patterns' => ['test', 'assessment', 'exam', 'quiz', 'evaluation', 'skill test'],
            'confidence' => 0.8
        ],
        'application_status' => [
            'patterns' => ['application', 'applied', 'status', 'response', 'feedback', 'interview'],
            'confidence' => 0.8
        ],
        'help' => [
            'patterns' => ['help', 'how to', 'what is', 'explain', 'tutorial', 'guide'],
            'confidence' => 0.7
        ]
    ];
    
    $bestMatch = ['category' => 'general', 'confidence' => 0.0, 'matched_keywords' => []];
    
    foreach ($intents as $category => $data) {
        $matches = 0;
        $matchedKeywords = [];
        
        foreach ($data['patterns'] as $pattern) {
            if (strpos($message_lower, $pattern) !== false) {
                $matches++;
                $matchedKeywords[] = $pattern;
            }
        }
        
        if ($matches > 0) {
            $confidence = ($matches / count($data['patterns'])) * $data['confidence'];
            if ($confidence > $bestMatch['confidence']) {
                $bestMatch = [
                    'category' => $category,
                    'confidence' => $confidence,
                    'matched_keywords' => $matchedKeywords
                ];
            }
        }
    }
    
    return $bestMatch;
}

/**
 * Handle job search queries
 */
function handleJobSearchQuery($user_id, $message, $intent, $context, $language) {
    global $db;
    
    // Extract job search parameters from message
    $searchTerms = extractJobSearchTerms($message);
    
    // Get personalized job recommendations
    $recommendations = $db->fetchAll(
        "SELECT ar.*, j.title, j.location, c.company_name 
         FROM ai_recommendations ar 
         JOIN jobs j ON ar.job_id = j.id 
         JOIN companies c ON j.company_id = c.id 
         WHERE ar.user_id = ? AND ar.status = 'active' 
         ORDER BY ar.recommendation_score DESC 
         LIMIT 3",
        [$user_id]
    );
    
    if (!empty($recommendations)) {
        $response = "Here are some personalized job recommendations for you:\n\n";
        foreach ($recommendations as $rec) {
            $response .= "🎯 **{$rec['title']}** at {$rec['company_name']}\n";
            $response .= "📍 {$rec['location']}\n";
            $response .= "⭐ Match Score: " . round($rec['recommendation_score']) . "%\n\n";
        }
        
        $response .= "Would you like me to help you apply to any of these positions or find more jobs in a specific field?";
    } else {
        $response = "I'd love to help you find jobs! To get started, could you tell me:\n";
        $response .= "• What type of role are you looking for?\n";
        $response .= "• What's your preferred location or work style (remote/onsite)?\n";
        $response .= "• What's your experience level?\n\n";
        $response .= "You can also update your profile with your skills and preferences for better job matching!";
    }
    
    return [
        'message' => $response,
        'message_type' => 'text',
        'suggestions' => ['Update my profile', 'Search for jobs', 'View my applications'],
        'data' => ['recommendations' => $recommendations]
    ];
}

/**
 * Handle resume-related queries
 */
function handleResumeQuery($user_id, $message, $intent, $context, $language) {
    $response = "";
    
    if ($context['profile']['resume_file'] ?? null) {
        $response = "I see you have a resume uploaded! Here are some things I can help you with:\n\n";
        $response .= "📝 **Resume Tips:**\n";
        $response .= "• Make sure your skills are up-to-date\n";
        $response .= "• Highlight your recent achievements\n";
        $response .= "• Tailor your resume for specific job applications\n\n";
        $response .= "Would you like me to analyze your current resume or help you update specific sections?";
    } else {
        $response = "I notice you haven't uploaded a resume yet. A well-crafted resume is essential for job applications!\n\n";
        $response .= "🚀 **Getting Started:**\n";
        $response .= "• Upload your existing resume (PDF, DOC, DOCX)\n";
        $response .= "• Use our AI-powered resume builder\n";
        $response .= "• Get personalized suggestions for improvement\n\n";
        $response .= "Would you like me to guide you through uploading your resume?";
    }
    
    return [
        'message' => $response,
        'message_type' => 'text',
        'suggestions' => ['Upload resume', 'Resume tips', 'Build new resume'],
        'data' => ['has_resume' => !empty($context['profile']['resume_file'])]
    ];
}

/**
 * Handle career advice queries
 */
function handleCareerAdviceQuery($user_id, $message, $intent, $context, $language) {
    $userSkills = $context['skills'] ?? [];
    $experienceYears = $context['profile']['experience_years'] ?? 0;
    
    $response = "I'd be happy to provide career guidance! Based on your profile:\n\n";
    
    if ($experienceYears == 0) {
        $response .= "🌟 **For Entry-Level Professionals:**\n";
        $response .= "• Focus on building foundational skills\n";
        $response .= "• Consider internships or junior positions\n";
        $response .= "• Build a portfolio of projects\n";
        $response .= "• Network with industry professionals\n\n";
    } elseif ($experienceYears <= 3) {
        $response .= "🚀 **For Growing Professionals:**\n";
        $response .= "• Develop specialized skills in your field\n";
        $response .= "• Take on more responsibilities\n";
        $response .= "• Consider certification programs\n";
        $response .= "• Start mentoring junior colleagues\n\n";
    } else {
        $response .= "👑 **For Experienced Professionals:**\n";
        $response .= "• Consider leadership roles\n";
        $response .= "• Explore cross-functional opportunities\n";
        $response .= "• Share knowledge through speaking/writing\n";
        $response .= "• Consider entrepreneurial ventures\n\n";
    }
    
    if (!empty($userSkills)) {
        $response .= "Based on your skills (" . implode(', ', array_slice($userSkills, 0, 3)) . "), ";
        $response .= "I recommend focusing on staying current with industry trends and emerging technologies.\n\n";
    }
    
    $response .= "What specific aspect of your career would you like to discuss?";
    
    return [
        'message' => $response,
        'message_type' => 'text',
        'suggestions' => ['Skill development', 'Interview tips', 'Salary negotiation', 'Career change'],
        'data' => ['experience_level' => getExperienceLevel($experienceYears)]
    ];
}

/**
 * Handle assessment-related queries
 */
function handleAssessmentQuery($user_id, $message, $intent, $context, $language) {
    global $db;
    
    // Get pending assessments
    $assessments = $db->fetchAll(
        "SELECT ua.*, a.title, a.description 
         FROM user_assessments ua 
         JOIN assessments a ON ua.assessment_id = a.id 
         WHERE ua.user_id = ? AND ua.status IN ('not_started', 'in_progress') 
         ORDER BY ua.created_at DESC 
         LIMIT 3",
        [$user_id]
    );
    
    if (!empty($assessments)) {
        $response = "You have pending assessments! Here's what's waiting for you:\n\n";
        foreach ($assessments as $assessment) {
            $response .= "📋 **{$assessment['title']}**\n";
            $response .= "Status: " . ucfirst($assessment['status']) . "\n";
            if ($assessment['description']) {
                $response .= "Description: {$assessment['description']}\n";
            }
            $response .= "\n";
        }
        $response .= "Taking assessments can significantly improve your job matching score. Would you like to start one now?";
    } else {
        $response = "Assessments are a great way to showcase your skills to employers!\n\n";
        $response .= "🎯 **Benefits of Taking Assessments:**\n";
        $response .= "• Validate your technical skills\n";
        $response .= "• Improve job matching accuracy\n";
        $response .= "• Stand out to employers\n";
        $response .= "• Get personalized feedback\n\n";
        $response .= "I can help you find relevant assessments based on your skills and career goals.";
    }
    
    return [
        'message' => $response,
        'message_type' => 'text',
        'suggestions' => ['Take assessment', 'View results', 'Assessment tips'],
        'data' => ['pending_assessments' => count($assessments)]
    ];
}

/**
 * Handle application status queries
 */
function handleApplicationStatusQuery($user_id, $message, $intent, $context, $language) {
    $applications = $context['recent_applications'] ?? [];
    
    if (empty($applications)) {
        $response = "You haven't submitted any job applications yet. Let's change that!\n\n";
        $response .= "🎯 **Next Steps:**\n";
        $response .= "• Check out your personalized job recommendations\n";
        $response .= "• Update your profile and resume\n";
        $response .= "• Apply to positions that match your skills\n\n";
        $response .= "Would you like me to show you some job recommendations?";
    } else {
        $response = "Here's the status of your recent applications:\n\n";
        foreach (array_slice($applications, 0, 3) as $app) {
            $response .= "📄 **{$app['title']}** at {$app['company_name']}\n";
            $response .= "Status: " . ucfirst($app['status']) . "\n";
            $response .= "Applied: " . formatDate($app['application_date'], 'M j, Y') . "\n\n";
        }
        
        if (count($applications) > 3) {
            $response .= "...and " . (count($applications) - 3) . " more applications.\n\n";
        }
        
        $response .= "Keep applying to increase your chances! Would you like to see more job recommendations?";
    }
    
    return [
        'message' => $response,
        'message_type' => 'text',
        'suggestions' => ['View all applications', 'Find more jobs', 'Application tips'],
        'data' => ['application_count' => count($applications)]
    ];
}

/**
 * Handle greetings
 */
function handleGreeting($user_id, $context, $language) {
    $name = $context['name'];
    $timeOfDay = date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening');
    
    $response = "Good $timeOfDay, $name! 👋\n\n";
    $response .= "I'm your AI career assistant. I'm here to help you with:\n";
    $response .= "🎯 Finding the perfect job opportunities\n";
    $response .= "📝 Resume and profile optimization\n";
    $response .= "💡 Career advice and guidance\n";
    $response .= "📊 Assessment preparation\n";
    $response .= "📈 Application tracking\n\n";
    $response .= "What can I help you with today?";
    
    return [
        'message' => $response,
        'message_type' => 'text',
        'suggestions' => ['Find jobs', 'Career advice', 'Check applications', 'Take assessment'],
        'data' => ['user_type' => $context['user_type']]
    ];
}

/**
 * Handle help queries
 */
function handleHelpQuery($user_id, $message, $intent, $context, $language) {
    $response = "I'm here to help! Here's what I can assist you with:\n\n";
    $response .= "🔍 **Job Search**\n";
    $response .= "• Find personalized job recommendations\n";
    $response .= "• Search for specific positions\n";
    $response .= "• Filter by location, salary, and more\n\n";
    
    $response .= "📝 **Resume & Profile**\n";
    $response .= "• Upload and optimize your resume\n";
    $response .= "• Get AI-powered suggestions\n";
    $response .= "• Update your skills and experience\n\n";
    
    $response .= "💼 **Career Development**\n";
    $response .= "• Get personalized career advice\n";
    $response .= "• Skill development recommendations\n";
    $response .= "• Interview preparation tips\n\n";
    
    $response .= "📊 **Assessments**\n";
    $response .= "• Take skill assessments\n";
    $response .= "• View your results and feedback\n";
    $response .= "• Improve your job matching score\n\n";
    
    $response .= "What would you like to know more about?";
    
    return [
        'message' => $response,
        'message_type' => 'text',
        'suggestions' => ['Job search help', 'Resume tips', 'Assessment guide', 'Profile setup'],
        'data' => ['help_topics' => ['job_search', 'resume', 'assessments', 'career_advice']]
    ];
}

/**
 * Handle general queries
 */
function handleGeneralQuery($user_id, $message, $intent, $context, $language) {
    $responses = [
        "I understand you're asking about something, but I'm specifically designed to help with career and job search matters. Could you tell me more about what you're looking for in terms of jobs or career development?",
        "That's an interesting question! While I specialize in career guidance and job search assistance, I'd be happy to help you with your professional goals. What aspect of your career can I assist you with?",
        "I want to make sure I give you the most helpful response. Could you clarify how this relates to your job search or career development? I'm here to help with finding opportunities, improving your profile, or providing career advice.",
    ];
    
    $response = $responses[array_rand($responses)];
    
    return [
        'message' => $response,
        'message_type' => 'text',
        'suggestions' => ['Find jobs', 'Career advice', 'Update profile', 'Help'],
        'data' => ['intent' => $intent]
    ];
}

/**
 * Extract job search terms from message
 */
function extractJobSearchTerms($message) {
    // Simple keyword extraction - can be enhanced with NLP
    $jobTitles = ['developer', 'engineer', 'manager', 'analyst', 'designer', 'consultant', 'coordinator'];
    $locations = ['remote', 'new york', 'san francisco', 'london', 'toronto'];
    
    $terms = [
        'job_titles' => [],
        'locations' => [],
        'skills' => []
    ];
    
    $message_lower = strtolower($message);
    
    foreach ($jobTitles as $title) {
        if (strpos($message_lower, $title) !== false) {
            $terms['job_titles'][] = $title;
        }
    }
    
    foreach ($locations as $location) {
        if (strpos($message_lower, $location) !== false) {
            $terms['locations'][] = $location;
        }
    }
    
    return $terms;
}

/**
 * Get experience level category
 */
function getExperienceLevel($years) {
    if ($years == 0) return 'entry';
    if ($years <= 3) return 'junior';
    if ($years <= 7) return 'mid';
    if ($years <= 12) return 'senior';
    return 'executive';
}
?>