<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'job_seeker') {
    header('Location: /job/index.php');
    exit();
}

header('Location: /job/dashboard/job_seeker.php#resume');
exit();

