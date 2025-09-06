<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$video_id = isset($_GET['id']) ? sanitizeInput($_GET['id']) : '';

if (!$video_id) {
    header('Location: videos.php');
    exit();
}

// Fetch video details from JSON modules structure
$video = null;
$course = null;
$module = null;

// Get all courses for the teacher and search for the video
$stmt = $pdo->prepare("
    SELECT c.id, c.course_name, c.course_code, c.modules, c.academic_period_id
    FROM courses c
    WHERE c.teacher_id = ?
");
$stmt->execute([$teacher_id]);
$courses_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug logging
error_log("video_details.php - Searching for video ID: $video_id");
error_log("video_details.php - Found " . count($courses_data) . " courses for teacher");

foreach ($courses_data as $course_data) {
    $modules_data = json_decode($course_data['modules'], true);
    if (is_array($modules_data)) {
        error_log("video_details.php - Course " . $course_data['id'] . " has " . count($modules_data) . " modules");
        foreach ($modules_data as $mod) {
            if (isset($mod['videos']) && is_array($mod['videos'])) {
                error_log("video_details.php - Module " . ($mod['id'] ?? 'unknown') . " has " . count($mod['videos']) . " videos");
                foreach ($mod['videos'] as $vid) {
                    error_log("video_details.php - Checking video ID: " . ($vid['id'] ?? 'null') . " against: $video_id");
                    if (($vid['id'] ?? '') == $video_id) {
                        $video = $vid;
                        $course = $course_data;
                        $module = $mod;
                        error_log("video_details.php - Found video! Course: " . $course['course_name'] . ", Module: " . ($module['module_title'] ?? $module['title'] ?? 'unknown'));
                        break 3;
                    }
                }
            }
        }
    }
}

if (!$video || !$course) {
    error_log("video_details.php - Video or course not found. Video: " . ($video ? 'found' : 'not found') . ", Course: " . ($course ? 'found' : 'not found'));
    $_SESSION['error'] = "Video not found or you don't have access to it.";
    header('Location: videos.php');
    exit();
}

// Validate that required fields exist
if (!isset($video['video_title'])) {
    error_log("video_details.php - Video missing title: " . json_encode($video));
    $_SESSION['error'] = "Video data is incomplete.";
    header('Location: videos.php');
    exit();
}

// Check if academic period is active
$stmt = $pdo->prepare("SELECT is_active FROM academic_periods WHERE id = ?");
$stmt->execute([$course['academic_period_id']]);
$period_data = $stmt->fetch(PDO::FETCH_ASSOC);
$is_acad_period_active = $period_data ? (bool)$period_data['is_active'] : true;

// Get video source
$video_file = $video['video_file'] ?? '';
$video_url = $video['video_url'] ?? '';

// Determine the play source - prioritize video_file if it's a URL, otherwise use video_url
if ($video_file && preg_match('/^https?:\/\//', $video_file)) {
    $play_source = $video_file; // video_file contains a URL
} elseif ($video_url) {
    $play_source = $video_url; // video_url is set
} else {
    $play_source = $video_file; // video_file is a local file
}

// Add missing fields that the display expects
$video['course_id'] = $course['id'];
$video['module_title'] = $module['module_title'] ?? $module['title'] ?? 'Module';

// Handle Google Drive links
function is_google_drive_link($url) {
    return strpos($url, 'drive.google.com') !== false;
}

function get_google_drive_embed_url($url) {
    $file_id = null;
    
    if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $file_id = $matches[1];
    } elseif (preg_match('/drive\.google\.com\/open\?id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $file_id = $matches[1];
    } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $file_id = $matches[1];
    }
    
    if ($file_id) {
        return 'https://drive.google.com/file/d/' . $file_id . '/preview';
    }
    
    return $url;
}

if (is_google_drive_link($play_source)) {
    $play_source = get_google_drive_embed_url($play_source);
}



$page_title = 'Video Details: ' . htmlspecialchars($video['video_title']);
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <main class="col-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-0 pb-2 mb-2 border-bottom">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="courses.php">My Courses</a></li>
                            <li class="breadcrumb-item"><a href="course.php?id=<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="videos.php?course_id=<?php echo $course['id']; ?>">Course Videos</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Video Details</li>
                        </ol>
                    </nav>
                    <h1 class="h2"><?php echo htmlspecialchars($video['video_title']); ?></h1>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="videos.php?course_id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Course Videos
                    </a>
                </div>
            </div>

            <?php if (!$is_acad_period_active): ?>
                <div class="alert alert-warning mb-4">
                    <strong>Inactive Academic Period:</strong> This academic period is inactive. You can only view and review content.
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Video Player Section -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Video Player</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="ratio ratio-16x9">
                                <?php if (preg_match('/^https?:\/\//', $play_source)): ?>
                                    <?php if (strpos($play_source, 'youtube.com') !== false || strpos($play_source, 'youtu.be') !== false): ?>
                                        <?php
                                        $yt_match = preg_match('/youtu(?:\.be|be\.com)\/(?:watch\?v=)?([\w\-]+)/i', $play_source, $matches);
                                        $embed_url = $yt_match ? 'https://www.youtube.com/embed/' . $matches[1] : $play_source;
                                        ?>
                                        <iframe src="<?php echo htmlspecialchars($embed_url); ?>" 
                                                allowfullscreen frameborder="0" 
                                                title="<?php echo htmlspecialchars($video['video_title']); ?>">
                                        </iframe>
                                    <?php elseif (strpos($play_source, 'drive.google.com') !== false): ?>
                                        <iframe src="<?php echo htmlspecialchars($play_source); ?>" 
                                                allowfullscreen frameborder="0" 
                                                title="<?php echo htmlspecialchars($video['video_title']); ?>">
                                        </iframe>
                                    <?php else: ?>
                                        <video controls class="w-100 h-100">
                                            <source src="<?php echo htmlspecialchars($play_source); ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <video controls class="w-100 h-100">
                                        <source src="/lms_cap/uploads/videos/<?php echo htmlspecialchars($play_source); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Video Information Section -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Video Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Description</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($video['video_description'] ?: 'No description available.')); ?></p>
                            </div>

                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Course Details</h6>
                                <p class="mb-1"><strong>Course:</strong> <?php echo htmlspecialchars($course['course_name']); ?></p>
                                <p class="mb-1"><strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?></p>
                                <p class="mb-0"><strong>Module:</strong> <?php echo htmlspecialchars($video['module_title']); ?></p>
                            </div>

                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Video Details</h6>
                                <p class="mb-1"><strong>Order:</strong> <?php echo $video['video_order'] ?? 'Not set'; ?></p>
                                <p class="mb-1"><strong>Type:</strong> 
                                    <?php 
                                    if ($video['video_file'] && !preg_match('/^https?:\/\//', $video['video_file'])) {
                                        echo 'Uploaded File';
                                    } elseif ($video['video_url'] || ($video['video_file'] && preg_match('/^https?:\/\//', $video['video_file']))) {
                                        echo 'External Link';
                                    } else {
                                        echo 'Unknown';
                                    }
                                    ?>
                                </p>
                                <?php if (($video['video_url'] ?? '') || ($video['video_file'] && preg_match('/^https?:\/\//', $video['video_file']))): ?>
                                    <p class="mb-0"><strong>Source:</strong> 
                                        <a href="<?php echo htmlspecialchars($video['video_url'] ?: $video['video_file']); ?>" target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($video['video_url'] ?: $video['video_file']); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                                <?php if (isset($video['min_watch_time'])): ?>
                                    <p class="mb-0"><strong>Min Watch Time:</strong> <?php echo $video['min_watch_time']; ?> seconds</p>
                                <?php endif; ?>
                                <?php if (isset($video['created_at'])): ?>
                                    <p class="mb-0"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($video['created_at'])); ?></p>
                                <?php endif; ?>
                                <?php if (isset($video['updated_at'])): ?>
                                    <p class="mb-0"><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($video['updated_at'])); ?></p>
                                <?php endif; ?>
                            </div>



                            <div class="d-grid gap-2">
                                <a href="videos.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-list me-1"></i>View All Videos
                                </a>
                                <?php if ($is_acad_period_active): ?>
                                    <button class="btn btn-outline-secondary" onclick="editVideo(<?php echo htmlspecialchars(json_encode($video)); ?>)">
                                        <i class="fas fa-edit me-1"></i>Edit Video
                                    </button>
                                    <a href="video_stats.php?id=<?php echo $video_id; ?>" class="btn btn-outline-info">
                                        <i class="fas fa-chart-bar me-1"></i>View Statistics
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function editVideo(video) {
    // Populate edit modal with video data
    document.getElementById('edit_video_id').value = video.id;
    document.getElementById('edit_video_title').value = video.video_title;
    document.getElementById('edit_description').value = video.video_description;
    document.getElementById('edit_video_order').value = video.video_order;
    
    // Show the edit modal
    new bootstrap.Modal(document.getElementById('editVideoModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?> 