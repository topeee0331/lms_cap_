<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$video_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$video_id) {
    header('Location: courses.php');
    exit();
}

// Fetch video details with course and module information
$stmt = $pdo->prepare("
    SELECT cv.*, cm.module_title, cm.module_description, c.course_name, c.course_code, c.id as course_id,
           c.academic_period_id, ay.is_active as academic_period_active
    FROM course_videos cv
    JOIN course_modules cm ON cv.module_id = cm.id
    JOIN courses c ON cm.course_id = c.id
    JOIN academic_periods ay ON c.academic_period_id = ay.id
    JOIN course_enrollments e ON c.id = e.course_id
    WHERE cv.id = ? AND e.student_id = ? AND e.status = 'active'
");
$stmt->execute([$video_id, $student_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    $_SESSION['error'] = "Video not found or you are not enrolled in this course.";
    header('Location: courses.php');
    exit();
}

$is_acad_year_active = (bool)$video['academic_year_active'];

// Mark video as watched
if (!isset($_SESSION['videos_watched'][$video_id])) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO video_views (student_id, video_id, watched_at) VALUES (?, ?, NOW())");
    $stmt->execute([$student_id, $video_id]);
    $_SESSION['videos_watched'][$video_id] = true;
}

// Get video source
$video_file = $video['video_file'] ?? '';
$video_url = $video['video_url'] ?? '';
$play_source = $video_file ?: $video_url;

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

$is_url = preg_match('/^(https?:)?\/\//i', $play_source);

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
                            <li class="breadcrumb-item"><a href="courses.php">My Courses</a></li>
                            <li class="breadcrumb-item"><a href="course.php?id=<?php echo $video['course_id']; ?>"><?php echo htmlspecialchars($video['course_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="module.php?id=<?php echo $video['module_id']; ?>"><?php echo htmlspecialchars($video['module_title']); ?></a></li>
                            <li class="breadcrumb-item"><a href="videos.php?id=<?php echo $video['module_id']; ?>">Videos</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Video Details</li>
                        </ol>
                    </nav>
                    <h1 class="h2"><?php echo htmlspecialchars($video['video_title']); ?></h1>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="videos.php?id=<?php echo $video['module_id']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Videos
                    </a>
                </div>
            </div>

            <?php if (!$is_acad_year_active): ?>
                <div class="alert alert-warning mb-4">
                    <strong>Inactive Academic Year:</strong> This academic year is inactive. You can only view and review content.
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
                                <?php if ($is_url): ?>
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
                                <p class="mb-1"><strong>Course:</strong> <?php echo htmlspecialchars($video['course_name']); ?></p>
                                <p class="mb-1"><strong>Course Code:</strong> <?php echo htmlspecialchars($video['course_code']); ?></p>
                                <p class="mb-0"><strong>Module:</strong> <?php echo htmlspecialchars($video['module_title']); ?></p>
                            </div>

                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Video Details</h6>
                                <p class="mb-1"><strong>Order:</strong> <?php echo $video['video_order']; ?></p>
                                <p class="mb-1"><strong>Type:</strong> <?php echo $video['video_file'] ? 'Uploaded File' : 'External Link'; ?></p>
                                <?php if ($video['video_url']): ?>
                                    <p class="mb-0"><strong>Source:</strong> 
                                        <a href="<?php echo htmlspecialchars($video['video_url']); ?>" target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($video['video_url']); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Module Description</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($video['module_description'] ?: 'No module description available.')); ?></p>
                            </div>

                            <div class="d-grid">
                                <a href="videos.php?id=<?php echo $video['module_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-list me-1"></i>View All Videos
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<style>
body {
    margin-top: 0;
    padding-top: 80px;
}
.navbar-accent-bar {
    display: none !important;
}
main.flex-grow-1 {
    display: none !important;
}
</style> 