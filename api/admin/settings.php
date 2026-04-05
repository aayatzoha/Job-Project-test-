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
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all system settings
        $settings_raw = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
        
        $settings = [];
        foreach ($settings_raw as $setting) {
            $settings[$setting['setting_key']] = $setting['setting_value'];
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update system settings
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
            exit();
        }
        
        $db->beginTransaction();
        
        try {
            foreach ($input as $key => $value) {
                // Validate setting key to prevent unauthorized settings
                $allowed_settings = [
                    'site_name', 'site_description', 'otp_expiry_minutes', 
                    'max_file_size', 'assessment_time_limit', 'proctoring_enabled',
                    'ai_recommendation_threshold', 'email_notifications', 
                    'push_notifications', 'maintenance_mode', 'smtp_host', 
                    'smtp_port', 'smtp_username', 'smtp_password'
                ];
                
                if (in_array($key, $allowed_settings)) {
                    // Check if setting exists
                    $existing = $db->fetch("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
                    
                    if ($existing) {
                        $db->execute(
                            "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
                            [$value, $key]
                        );
                    } else {
                        $db->execute(
                            "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)",
                            [$key, $value]
                        );
                    }
                }
            }
            
            // Log the settings update
            logActivity($_SESSION['user_id'], 'settings_updated', 'System settings updated');
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Settings API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>