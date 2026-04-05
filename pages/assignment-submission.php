<?php
require_once '../config/database.php';
// Use session directly for role check to ensure consistent behavior
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'job_seeker') {
    // Preserve assignment id for post-login redirect
    $idParam = isset($_GET['id']) ? ('?id=' . intval($_GET['id'])) : '';
    header('Location: assignment-login-jobseeker.php' . $idParam);
    exit;
}

// Get assignment ID from URL
if (!isset($_GET['id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$assignment_id = intval($_GET['id']);

// Create database connection
$db = new Database();
$conn = $db->getConnection();

// Get assignment details
$stmt = $conn->prepare("
    SELECT a.*, j.title as job_title, c.company_name 
    FROM assignments a
    JOIN jobs j ON a.job_id = j.id
    JOIN companies c ON j.company_id = c.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ../dashboard.php');
    exit;
}

$assignment = $result->fetch_assoc();

// Get assignment questions
$stmt = $conn->prepare("
    SELECT * FROM assignment_questions 
    WHERE assignment_id = ?
    ORDER BY order_index ASC
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$questions_result = $stmt->get_result();

$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $questions[] = $row;
}

// Check if this assignment requires camera recording
$requires_camera = $assignment['requires_camera'] == 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Assignment - AI Job System</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .camera-container {
            position: relative;
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .camera-container video {
            width: 100%;
            height: auto;
            background-color: #000;
        }
        
        .camera-controls {
            margin-top: 15px;
            text-align: center;
        }
        
        .recording-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background-color: red;
            display: none;
        }
        
        .recording-indicator.active {
            display: block;
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .recording-timer {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background-color: rgba(0,0,0,0.5);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            display: none;
        }
        
        .recording-timer.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <h2>Submit Assignment</h2>
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                        <p class="text-muted">
                            Job: <?php echo htmlspecialchars($assignment['job_title']); ?> | 
                            Company: <?php echo htmlspecialchars($assignment['company_name']); ?>
                        </p>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                        
                        <?php if ($requires_camera): ?>
                        <div class="alert alert-info">
                            <strong>Note:</strong> This assignment requires camera recording during submission.
                        </div>
                        
                        <div class="mb-4">
                            <h5>Camera Recording</h5>
                            <div class="camera-container">
                                <video id="video" autoplay muted></video>
                                <div class="recording-indicator" id="recordingIndicator"></div>
                                <div class="recording-timer" id="recordingTimer">00:00</div>
                            </div>
                            <div class="camera-controls">
                                <button type="button" class="btn btn-primary" id="startRecording">Start Recording</button>
                                <button type="button" class="btn btn-danger" id="stopRecording" disabled>Stop Recording</button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <form id="assignmentForm" method="post">
                            <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                            <input type="hidden" name="recording_id" id="recordingId" value="">
                            
                            <?php foreach ($questions as $index => $question): ?>
                            <div class="mb-3">
                                <label for="question_<?php echo $question['id']; ?>" class="form-label">
                                    <strong>Question <?php echo $index + 1; ?>:</strong> <?php echo htmlspecialchars($question['question_text']); ?>
                                </label>
                                
                                <?php if ($question['question_type'] === 'text'): ?>
                                <textarea class="form-control" id="question_<?php echo $question['id']; ?>" 
                                    name="answers[<?php echo $question['id']; ?>][text]" rows="3" required></textarea>
                                
                                <?php elseif ($question['question_type'] === 'file_upload'): ?>
                                <input type="file" class="form-control" id="question_<?php echo $question['id']; ?>" 
                                    name="answers[<?php echo $question['id']; ?>][file]">
                                
                                <?php elseif ($question['question_type'] === 'multiple_choice'): ?>
                                <?php
                                // Get options for this question
                                $stmt = $conn->prepare("
                                    SELECT * FROM assignment_question_options 
                                    WHERE question_id = ?
                                    ORDER BY order_index ASC
                                ");
                                $stmt->bind_param("i", $question['id']);
                                $stmt->execute();
                                $options_result = $stmt->get_result();
                                
                                while ($option = $options_result->fetch_assoc()):
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" 
                                        name="answers[<?php echo $question['id']; ?>][option]" 
                                        id="option_<?php echo $option['id']; ?>" 
                                        value="<?php echo $option['id']; ?>" required>
                                    <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                        <?php echo htmlspecialchars($option['option_text']); ?>
                                    </label>
                                </div>
                                <?php endwhile; ?>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-success" id="submitBtn" 
                                    <?php echo $requires_camera ? 'disabled' : ''; ?>>
                                    Submit Assignment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <?php if ($requires_camera): ?>
    <script src="../assets/js/camera-recording.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const videoElement = document.getElementById('video');
            const startButton = document.getElementById('startRecording');
            const stopButton = document.getElementById('stopRecording');
            const submitBtn = document.getElementById('submitBtn');
            const recordingIndicator = document.getElementById('recordingIndicator');
            const recordingTimer = document.getElementById('recordingTimer');
            const recordingIdInput = document.getElementById('recordingId');
            
            let timerInterval;
            let recordingSeconds = 0;
            
            function updateTimer() {
                recordingSeconds++;
                const minutes = Math.floor(recordingSeconds / 60).toString().padStart(2, '0');
                const seconds = (recordingSeconds % 60).toString().padStart(2, '0');
                recordingTimer.textContent = `${minutes}:${seconds}`;
            }
            
            const cameraRecording = new CameraRecording({
                videoElement: videoElement,
                startButton: startButton,
                stopButton: stopButton,
                assignmentId: <?php echo $assignment_id; ?>,
                onStart: (recordingId) => {
                    console.log('Recording started with ID:', recordingId);
                    recordingIdInput.value = recordingId;
                    startButton.disabled = true;
                    stopButton.disabled = false;
                    recordingIndicator.classList.add('active');
                    recordingTimer.classList.add('active');
                    
                    // Start timer
                    recordingSeconds = 0;
                    updateTimer();
                    timerInterval = setInterval(updateTimer, 1000);
                },
                onStop: (recordingId, path) => {
                    console.log('Recording stopped:', recordingId, path);
                    startButton.disabled = false;
                    stopButton.disabled = true;
                    submitBtn.disabled = false;
                    recordingIndicator.classList.remove('active');
                    
                    // Stop timer
                    clearInterval(timerInterval);
                    
                    // Show success message
                    alert('Recording completed successfully! You can now submit your assignment.');
                },
                onError: (message) => {
                    console.error('Recording error:', message);
                    alert('Error: ' + message);
                    startButton.disabled = false;
                    stopButton.disabled = true;
                }
            });
            
            // Request camera permission on page load
            cameraRecording.requestCameraPermission();
            
            // Handle form submission
            document.getElementById('assignmentForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (<?php echo $requires_camera ? 'true' : 'false'; ?> && !recordingIdInput.value) {
                    alert('You must complete the camera recording before submitting.');
                    return;
                }
                
                const formData = new FormData(this);
                
                fetch('../api/jobs/assignment.php', {
                    method: 'PUT',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Assignment submitted successfully!');
                        window.location.href = '../dashboard.php';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while submitting the assignment.');
                });
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
