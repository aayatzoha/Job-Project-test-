<?php
require_once '../config/database.php';
require_once '../utils/auth.php';

// Check if user is logged in
$user = checkAuth();
if (!$user || ($user['user_type'] !== 'company' && $user['user_type'] !== 'admin')) {
    header('Location: ../login.php');
    exit;
}

// Get recording ID from URL
if (!isset($_GET['id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$recording_id = intval($_GET['id']);

// Create database connection
$db = new Database();
$conn = $db->getConnection();

// Get recording details with user and assignment info
$stmt = $conn->prepare("
    SELECT r.*, a.title as assignment_title, u.first_name, u.last_name, u.email
    FROM assignment_recordings r
    JOIN assignments a ON r.assignment_id = a.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ? AND (a.created_by = ? OR ? = 'admin')
");
$stmt->bind_param("iis", $recording_id, $user['id'], $user['user_type']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ../dashboard.php');
    exit;
}

$recording = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Recording - AI Job System</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .video-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        video {
            width: 100%;
            border-radius: 8px;
        }
        
        .recording-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <h2>Assignment Recording</h2>
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><?php echo htmlspecialchars($recording['assignment_title']); ?></h4>
                        <p class="text-muted">
                            Submitted by: <?php echo htmlspecialchars($recording['first_name'] . ' ' . $recording['last_name']); ?> (<?php echo htmlspecialchars($recording['email']); ?>)
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="video-container">
                            <video controls>
                                <source src="../<?php echo htmlspecialchars($recording['video_path']); ?>" type="video/webm">
                                Your browser does not support the video tag.
                            </video>
                            
                            <div class="recording-info">
                                <h5>Recording Details</h5>
                                <table class="table">
                                    <tr>
                                        <th>Start Time:</th>
                                        <td><?php echo date('F j, Y, g:i a', strtotime($recording['start_time'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>End Time:</th>
                                        <td><?php echo $recording['end_time'] ? date('F j, Y, g:i a', strtotime($recording['end_time'])) : 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Duration:</th>
                                        <td><?php echo $recording['duration'] ? gmdate('H:i:s', $recording['duration']) : 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="badge bg-<?php echo $recording['status'] === 'completed' ? 'success' : ($recording['status'] === 'recording' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($recording['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="javascript:history.back()" class="btn btn-secondary">Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>