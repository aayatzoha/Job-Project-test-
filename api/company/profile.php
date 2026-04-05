<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

session_start();
requireLogin();

try {
    $user_id = $_SESSION['user_id'];
    if (getUserType() !== 'company') {
        throw new Exception('Companies only');
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get company profile
        $company = $db->fetch("SELECT * FROM companies WHERE user_id = ?", [$user_id]);
        if (!$company) { 
            throw new Exception('Company profile not found'); 
        }

        echo json_encode(['success' => true, 'company' => $company]);
        
    } elseif ($method === 'PUT' || $method === 'POST') {
        // Update company profile
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input && !empty($_POST)) {
            $input = $_POST;
        }
        
        if (!$input) {
            throw new Exception('No data provided');
        }
        
        // Get existing company
        $company = $db->fetch("SELECT * FROM companies WHERE user_id = ?", [$user_id]);
        if (!$company) {
            throw new Exception('Company profile not found');
        }
        
        // Build update fields
        $updateFields = [];
        $updateValues = [];
        
        $allowedFields = [
            'company_name' => 'company_name',
            'industry' => 'industry',
            'company_size' => 'company_size',
            'website' => 'website',
            'description' => 'description',
            'headquarters' => 'headquarters'
        ];
        
        foreach ($allowedFields as $inputKey => $dbField) {
            if (isset($input[$inputKey])) {
                $updateFields[] = "$dbField = ?";
                $updateValues[] = sanitizeInput($input[$inputKey]);
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('No fields to update');
        }
        
        $updateValues[] = $user_id;
        
        $sql = "UPDATE companies SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
        $db->query($sql, $updateValues);
        
        // Get updated company data
        $updatedCompany = $db->fetch("SELECT * FROM companies WHERE user_id = ?", [$user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Company profile updated successfully',
            'company' => $updatedCompany
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


