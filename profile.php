<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /job/index.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';

switch ($userType) {
    case 'job_seeker':
        header('Location: /job/dashboard/job_seeker.php#profile');
        break;
    case 'company':
        header('Location: /job/dashboard/company.php#profile');
        break;
    case 'admin':
        header('Location: /job/dashboard/admin.php#profile');
        break;
    default:
        header('Location: /job/index.php');
        break;
}
exit();

