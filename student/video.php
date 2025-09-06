<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$video_id = isset($_GET['id']) ? $_GET['id'] : '';

if (!$video_id) {
    header('Location: courses.php');
    exit();
}

// Find the course and module that contains this video_id
$stmt = $pdo->prepare("
    SELECT c.*, c.teacher_id, u.first_name, u.last_name
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL
");
$stmt->execute([$video_id]);
$course = $stmt->fetch();

if (!$course) {
    $_SESSION['error'] = "Video not found.";
    header('Location: courses.php');
    exit();
}

// Check if student is enrolled in this course
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

// Parse modules and find the specific video
$modules_data = json_decode($course['modules'], true);
$video = null;
$module = null;

foreach ($modules_data as $mod) {
    if (isset($mod['videos'])) {
        foreach ($mod['videos'] as $vid) {
            if ($vid['id'] === $video_id) {
                $video = $vid;
                $module = $mod;
                break 2;
            }
        }
    }
}

if (!$video || !$module) {
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

// Record video view if not already watched
if (!isset($video_progress[$video_id]) || !$video_progress[$video_id]['is_watched']) {
    $video_progress[$video_id] = [
        'is_watched' => 1,
        'watched_at' => date('Y-m-d H:i:s')
    ];
    
    // Update enrollment
    $stmt = $pdo->prepare("
        UPDATE course_enrollments 
        SET video_progress = ?
        WHERE student_id = ? AND course_id = ?
    ");
    $stmt->execute([
        json_encode($video_progress),
        $user_id,
        $course['id']
    ]);
}

// Handle video completion tracking
if (isset($_POST['mark_video_completed'])) {
    $video_progress[$video_id] = [
        'is_watched' => 1,
        'watched_at' => date('Y-m-d H:i:s'),
        'completed_at' => date('Y-m-d H:i:s')
    ];
    
    // Update enrollment
    $stmt = $pdo->prepare("
        UPDATE course_enrollments 
        SET video_progress = ?
        WHERE student_id = ? AND course_id = ?
    ");
    $stmt->execute([
        json_encode($video_progress),
        $user_id,
        $course['id']
    ]);
    
    $_SESSION['success'] = "Video marked as completed!";
    header('Location: video.php?id=' . $video_id);
    exit();
}

// Determine video source
$video_url = $video['video_url'] ?? '';
$video_file = $video['video_file'] ?? '';
$video_source = '';

if ($video_url) {
    $video_source = $video_url;
} elseif ($video_file) {
    // Handle uploaded video files
    if (strpos($video_file, 'http') === 0) {
        $video_source = $video_file;
    } else {
        $video_source = '../uploads/videos/' . $video_file;
    }
}

// Define course themes with IT icons
$course_themes = [
    ['bg' => 'bg-primary', 'icon' => 'fas fa-code'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-database'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-network-wired'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-server'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-shield-alt'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-cloud'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-microchip'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-laptop-code'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-mobile-alt'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-wifi'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-keyboard'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-bug'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-terminal'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-cogs'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-rocket'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-robot'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-brain'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-chart-line'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-fire'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-lightbulb']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['video_title'] ?? ''); ?> - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap');
        
        /* Button Hover Effects */
        .btn:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-primary:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-success:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-secondary:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-danger:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-warning:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
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
        
        .video-info-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .video-info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Video Header Styling */
        .video-header {
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .video-header-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .video-header-bg i {
            font-size: 12rem;
            color: rgba(255, 255, 255, 0.9);
            opacity: 0.75;
        }
        
        .video-header-content {
            position: relative;
            z-index: 2;
        }
        
        .video-title-text {
            font-family: 'Poppins', 'Arial', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            mix-blend-mode: overlay;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="col-12 px-md-4">
                <!-- Video Header with IT Icon Background -->
                <?php 
                $theme = $course_themes[$course['id'] % count($course_themes)];
                ?>
                <div class="video-header <?php echo $theme['bg']; ?>">
                    <div class="video-header-bg">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="video-header-content text-center">
                        <h1 class="video-title-text">
                            <?php echo htmlspecialchars($video['video_title'] ?? 'Video'); ?>
                        </h1>
                        <h2 class="h3 mb-3"><?php echo htmlspecialchars($module['module_title'] ?? ''); ?></h2>
                        <p class="lead mb-0"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></p>
                    </div>
                </div>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2"><?php echo htmlspecialchars($video['video_title'] ?? ''); ?></h1>
                        <p class="text-muted"><?php echo htmlspecialchars($module['module_title'] ?? ''); ?> - <?php echo htmlspecialchars($course['course_name'] ?? ''); ?></p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="module.php?id=<?php echo $module['id']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Module
                        </a>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Video Player -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body p-0">
                                <?php if ($video_source): ?>
                                    <div class="video-container">
                                        <?php if (strpos($video_source, 'youtube.com') !== false || strpos($video_source, 'youtu.be') !== false): ?>
                                            <?php
                                            // Extract YouTube video ID
                                            $youtube_id = '';
                                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video_source, $matches)) {
                                                $youtube_id = $matches[1];
                                            }
                                            ?>
                                            <?php if ($youtube_id): ?>
                                                <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>" 
                                                        frameborder="0" 
                                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                        allowfullscreen>
                                                </iframe>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-center h-100">
                                                    <div class="text-center text-white">
                                                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                                        <p>Invalid YouTube URL</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif (strpos($video_source, 'drive.google.com') !== false): ?>
                                            <div class="d-flex align-items-center justify-content-center h-100">
                                                <div class="text-center text-white">
                                                    <i class="fas fa-external-link-alt fa-3x mb-3"></i>
                                                    <p>Google Drive Video</p>
                                                    <a href="<?php echo htmlspecialchars($video_source); ?>" target="_blank" class="btn btn-primary">
                                                        <i class="fas fa-external-link-alt"></i> Open in Google Drive
                                                    </a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <video controls class="w-100 h-100">
                                                <source src="<?php echo htmlspecialchars($video_source); ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="video-container">
                                        <div class="d-flex align-items-center justify-content-center h-100">
                                            <div class="text-center text-white">
                                                <i class="fas fa-video-slash fa-3x mb-3"></i>
                                                <p>No video source available</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card video-info-card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-info-circle"></i> Video Information
                                </h5>
                                
                                <div class="mb-3">
                                    <h6>Description</h6>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($video['video_description'] ?? 'No description available.')); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6>Module</h6>
                                    <p class="text-muted"><?php echo htmlspecialchars($module['module_title'] ?? ''); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6>Course</h6>
                                    <p class="text-muted"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></p>
                                </div>
                                
                                <?php if (isset($video['min_watch_time']) && $video['min_watch_time'] > 0): ?>
                                    <div class="mb-3">
                                        <h6>Minimum Watch Time</h6>
                                        <p class="text-muted"><?php echo $video['min_watch_time']; ?> seconds</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <h6>Status</h6>
                                    <?php if (isset($video_progress[$video_id]) && $video_progress[$video_id]['is_watched']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Watched
                                        </span>
                                        <?php if (isset($video_progress[$video_id]['watched_at'])): ?>
                                            <small class="d-block text-muted mt-1">
                                                Watched on <?php echo date('M j, Y g:i A', strtotime($video_progress[$video_id]['watched_at'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-clock"></i> Not Watched
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <?php if (!isset($video_progress[$video_id]) || !$video_progress[$video_id]['is_watched']): ?>
                                        <form method="POST">
                                            <button type="submit" name="mark_video_completed" class="btn btn-success w-100">
                                                <i class="fas fa-check"></i> Mark as Completed
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-success w-100" disabled>
                                            <i class="fas fa-check"></i> Video Completed
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="module.php?id=<?php echo $module['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Module
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="module.php?id=<?php echo $module['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Module
                                    </a>
                                    
                                    <div class="text-center">
                                        <small class="text-muted">
                                            Video <?php echo $video['video_order'] ?? 1; ?> of <?php echo count($module['videos'] ?? []); ?>
                                        </small>
                                    </div>
                                    
                                    <div>
                                        <!-- Next video button could be added here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>