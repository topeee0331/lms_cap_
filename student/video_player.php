<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$video_id = isset($_GET['id']) ? $_GET['id'] : '';
$module_id = isset($_GET['module_id']) ? $_GET['module_id'] : '';

if (!$video_id || !$module_id) {
    header('Location: courses.php');
    exit();
}

// Find the course and module that contains this video
$stmt = $pdo->prepare("
    SELECT c.*, c.modules
    FROM courses c
    WHERE JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL
");
$stmt->execute([$module_id]);
$course = $stmt->fetch();

if (!$course) {
    $_SESSION['error'] = "Module not found.";
    header('Location: courses.php');
    exit();
}

// Check if student is enrolled
$stmt = $pdo->prepare("
    SELECT * FROM course_enrollments 
    WHERE student_id = ? AND course_id = ? AND status = 'active'
");
$stmt->execute([$user_id, $course['id']]);

if ($stmt->rowCount() == 0) {
    $_SESSION['error'] = "You are not enrolled in this course.";
    header('Location: courses.php');
    exit();
}

// Parse modules and find the specific module and video
$modules_data = json_decode($course['modules'] ?? '[]', true) ?: [];
$module = null;
$video = null;

foreach ($modules_data as $mod) {
    if ($mod['id'] === $module_id) {
        $module = $mod;
        if (isset($mod['videos'])) {
            foreach ($mod['videos'] as $vid) {
                if ($vid['id'] === $video_id) {
                    $video = $vid;
                    break 2;
                }
            }
        }
        break;
    }
}

if (!$module || !$video) {
    $_SESSION['error'] = "Video not found.";
    header('Location: courses.php');
    exit();
}

// Get student's video progress
$stmt = $pdo->prepare("
    SELECT video_progress 
    FROM course_enrollments 
    WHERE student_id = ? AND course_id = ?
");
$stmt->execute([$user_id, $course['id']]);
$enrollment = $stmt->fetch();

$video_progress = [];
if ($enrollment && $enrollment['video_progress']) {
    $video_progress = json_decode($enrollment['video_progress'], true);
}

// Check if video is already watched
$is_watched = isset($video_progress[$video_id]) && $video_progress[$video_id]['is_watched'] == 1;
$watch_duration = isset($video_progress[$video_id]) ? ($video_progress[$video_id]['watch_duration'] ?? 0) : 0;
$completion_percentage = isset($video_progress[$video_id]) ? ($video_progress[$video_id]['completion_percentage'] ?? 0) : 0;

// Get video source
$video_url = $video['video_url'] ?? '';
$video_file = $video['video_file'] ?? '';
$video_source = $video_url ?: $video_file;

// Minimum watch time in seconds (convert from minutes)
$min_watch_time_minutes = $video['min_watch_time'] ?? 5; // Default 5 minutes
$min_watch_time = $min_watch_time_minutes * 60; // Convert minutes to seconds
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['video_title']); ?> - Video Player</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .video-container iframe,
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .progress-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .time-requirement {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .watch-progress {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .video-completed {
            background: #d4edda;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1"><?php echo htmlspecialchars($video['video_title']); ?></h1>
                        <p class="text-muted mb-0">
                            <?php echo htmlspecialchars($course['course_name']); ?> • 
                            <?php echo htmlspecialchars($module['module_title']); ?>
                        </p>
                    </div>
                    <div>
                        <a href="module.php?id=<?php echo $module_id; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Module
                        </a>
                    </div>
                </div>

                <!-- Video Player -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="video-container">
                                    <?php if ($video_source): ?>
                                        <?php if (preg_match('/youtu\.be|youtube\.com/', $video_source)): ?>
                                            <!-- YouTube embed -->
                                            <?php if (preg_match('~(?:youtu\.be/|youtube\.com/(?:embed/|v/|watch\?v=|watch\?.+&v=))([^&?/]+)~', $video_source, $matches)): ?>
                                                <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($matches[1]); ?>" 
                                                        frameborder="0" allowfullscreen id="videoPlayer"></iframe>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-center h-100 text-danger">
                                                    <div class="text-center">
                                                        <i class="bi bi-exclamation-triangle fs-1"></i>
                                                        <p>Invalid YouTube link</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif (preg_match('/drive\.google\.com/', $video_source)): ?>
                                            <!-- Google Drive embed -->
                                            <?php if (preg_match('~/d/([a-zA-Z0-9_-]+)~', $video_source, $matches)): ?>
                                                <iframe src="https://drive.google.com/file/d/<?php echo htmlspecialchars($matches[1]); ?>/preview" 
                                                        allowfullscreen id="videoPlayer"></iframe>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-center h-100 text-danger">
                                                    <div class="text-center">
                                                        <i class="bi bi-exclamation-triangle fs-1"></i>
                                                        <p>Invalid Google Drive link</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Generic iframe -->
                                            <iframe src="<?php echo htmlspecialchars($video_source); ?>" 
                                                    allowfullscreen id="videoPlayer"></iframe>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                            <div class="text-center">
                                                <i class="bi bi-camera-video-off fs-1"></i>
                                                <p>No video source available</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Video Information -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Video Information</h6>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($video['video_description'] ?? 'No description available.')); ?></p>
                                
                                <div class="mb-3">
                                    <h6>Watch Requirements</h6>
                                    <div class="time-requirement">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-clock me-1"></i>Minimum Watch Time:</span>
                                            <strong><?php echo $min_watch_time_minutes; ?> minutes</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <span><i class="bi bi-percent me-1"></i>Completion Required:</span>
                                            <strong>80%</strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Watch Progress -->
                                <div class="mb-3">
                                    <h6>Your Progress</h6>
                                    <?php if ($is_watched): ?>
                                        <div class="video-completed">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                <div>
                                                    <strong>Video Completed!</strong>
                                                    <div class="small text-muted">
                                                        Watched for <?php echo gmdate("H:i:s", $watch_duration); ?> 
                                                        (<?php echo number_format($completion_percentage, 1); ?>% complete)
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="watch-progress">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Watch Time:</span>
                                                <span id="currentTime">0:00</span>
                                            </div>
                                            <div class="progress mb-2">
                                                <div class="progress-bar" id="timeProgress" role="progressbar" style="width: 0%"></div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Required:</span>
                                                <span><?php echo gmdate("H:i:s", $min_watch_time); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="d-grid gap-2">
                                    <?php if (!$is_watched): ?>
                                        <button class="btn btn-success" id="markWatchedBtn" disabled>
                                            <i class="bi bi-check-circle me-1"></i>Mark as Watched
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-success" disabled>
                                            <i class="bi bi-check-circle me-1"></i>Already Watched
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="module.php?id=<?php echo $module_id; ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-left me-1"></i>Back to Module
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let watchStartTime = null;
        let totalWatchTime = 0;
        let isWatching = false;
        let watchInterval = null;
        
        const minWatchTime = <?php echo $min_watch_time; ?>;
        const videoId = '<?php echo $video_id; ?>';
        const moduleId = '<?php echo $module_id; ?>';
        const currentWatchTime = <?php echo $watch_duration; ?>;
        
        // Initialize
        totalWatchTime = currentWatchTime;
        updateDisplay();
        
        // Start watching when page loads
        startWatching();
        
        function startWatching() {
            if (isWatching) return;
            
            isWatching = true;
            watchStartTime = Date.now();
            
            watchInterval = setInterval(() => {
                const currentTime = Math.floor((Date.now() - watchStartTime) / 1000);
                totalWatchTime = currentWatchTime + currentTime;
                
                updateDisplay();
                updateProgress();
                
                // Check if minimum watch time is met
                if (totalWatchTime >= minWatchTime) {
                    enableMarkWatched();
                }
            }, 1000);
        }
        
        function stopWatching() {
            if (!isWatching) return;
            
            isWatching = false;
            if (watchInterval) {
                clearInterval(watchInterval);
                watchInterval = null;
            }
        }
        
        function updateDisplay() {
            const currentTimeElement = document.getElementById('currentTime');
            if (currentTimeElement) {
                currentTimeElement.textContent = formatTime(totalWatchTime);
            }
        }
        
        function updateProgress() {
            const progressBar = document.getElementById('timeProgress');
            if (progressBar) {
                const progress = Math.min((totalWatchTime / minWatchTime) * 100, 100);
                progressBar.style.width = progress + '%';
                progressBar.setAttribute('aria-valuenow', progress);
            }
        }
        
        function enableMarkWatched() {
            const markBtn = document.getElementById('markWatchedBtn');
            if (markBtn) {
                markBtn.disabled = false;
                markBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Mark as Watched';
            }
        }
        
        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            if (hours > 0) {
                return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            } else {
                return `${minutes}:${secs.toString().padStart(2, '0')}`;
            }
        }
        
        // Handle mark as watched
        document.getElementById('markWatchedBtn').addEventListener('click', function() {
            if (totalWatchTime < minWatchTime) {
                alert('You need to watch the video for at least ' + Math.floor(minWatchTime / 60) + ' minutes to mark it as watched.');
                return;
            }
            
            // Stop watching
            stopWatching();
            
            // Send data to server
            const formData = new FormData();
            formData.append('video_id', videoId);
            formData.append('module_id', moduleId);
            formData.append('watch_duration', totalWatchTime);
            formData.append('completion_percentage', Math.min((totalWatchTime / minWatchTime) * 100, 100));
            
            fetch('mark_video_watched_with_time.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data === 'ok') {
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show';
                    alert.innerHTML = `
                        <i class="bi bi-check-circle me-1"></i>Video marked as watched successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.container-fluid').insertBefore(alert, document.querySelector('.row'));
                    
                    // Disable button
                    this.disabled = true;
                    this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Already Watched';
                    
                    // Update progress display
                    const progressContainer = document.querySelector('.watch-progress');
                    if (progressContainer) {
                        progressContainer.className = 'video-completed';
                        progressContainer.innerHTML = `
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <div>
                                    <strong>Video Completed!</strong>
                                    <div class="small text-muted">
                                        Watched for ${formatTime(totalWatchTime)} 
                                        (${Math.min((totalWatchTime / minWatchTime) * 100, 100).toFixed(1)}% complete)
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    alert('Error marking video as watched. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error marking video as watched. Please try again.');
            });
        });
        
        // Track page visibility to pause/resume watching
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopWatching();
            } else {
                startWatching();
            }
        });
        
        // Track when user leaves the page
        window.addEventListener('beforeunload', function() {
            stopWatching();
        });
    </script>
</body>
</html>
