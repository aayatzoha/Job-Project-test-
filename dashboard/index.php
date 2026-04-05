<?php
session_start();
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit();
}

// Redirect to appropriate dashboard based on role
$role = $_SESSION['role'];
switch ($role) {
    case 'job_seeker':
        header('Location: job_seeker.php');
        break;
    case 'company':
        header('Location: company.php');
        break;
    case 'admin':
        header('Location: admin.php');
        break;
    default:
        header('Location: ../index.php');
        break;
}
exit();
?>