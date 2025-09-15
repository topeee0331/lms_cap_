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

<!-- Modern Video Details Header with Back Button -->
<div class="video-details-header">
    <div class="container">
        <!-- Back Button Row -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-start">
                    <a href="module_videos.php?module_id=<?php echo $module['id']; ?>" class="btn btn-outline-light btn-back-icon" title="Back to Module Videos">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Header Content -->
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-3">
                    <i class="bi bi-camera-video me-3"></i>Video Details
                </h1>
                <p class="mb-0 opacity-90">
                    <strong><?php echo htmlspecialchars($video['video_title']); ?></strong> • 
                    <?php echo htmlspecialchars($course['course_name']); ?> • 
                    <?php echo htmlspecialchars($course['course_code']); ?>
                </p>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="video-stats">
                        <div class="video-stat-item">
                            <span class="video-stat-number"><?php echo $video['min_watch_time'] ?? 30; ?>s</span>
                            <span class="video-stat-label">Min Watch</span>
                        </div>
                        <div class="video-stat-item">
                            <span class="video-stat-number"><?php echo $video['video_order'] ?? 'N/A'; ?></span>
                            <span class="video-stat-label">Order</span>
                        </div>
                        <div class="video-stat-item">
                            <span class="video-stat-number"><?php echo isset($video['created_at']) ? date('M j', strtotime($video['created_at'])) : 'N/A'; ?></span>
                            <span class="video-stat-label">Created</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">

            <?php if (!$is_acad_period_active): ?>
                <div class="alert alert-warning mb-4">
                    <strong>Inactive Academic Period:</strong> This academic period is inactive. You can only view and review content.
                </div>
            <?php endif; ?>

    <div class="row">
        <!-- Video Player Section -->
        <div class="col-lg-8">
            <div class="card video-player-card border-0 shadow-sm mb-4">
                <div class="card-header video-player-header">
                    <h5 class="mb-0">
                        <i class="bi bi-play-circle me-2"></i>Video Player
                    </h5>
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
            <div class="card video-info-card border-0 shadow-sm mb-4">
                <div class="card-header video-info-header">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Video Information
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Description -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-2 fw-bold">
                            <i class="bi bi-file-text me-1"></i>Description
                        </h6>
                        <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($video['video_description'] ?: 'No description available.')); ?></p>
                    </div>

                    <!-- Course Details -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3 fw-bold">
                            <i class="bi bi-book me-1"></i>Course Details
                        </h6>
                        <div class="info-item">
                            <span class="info-label">Course:</span>
                            <span class="info-value"><?php echo htmlspecialchars($course['course_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Course Code:</span>
                            <span class="info-value"><?php echo htmlspecialchars($course['course_code']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Module:</span>
                            <span class="info-value"><?php echo htmlspecialchars($video['module_title']); ?></span>
                        </div>
                    </div>

                    <!-- Video Details -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3 fw-bold">
                            <i class="bi bi-gear me-1"></i>Video Details
                        </h6>
                        <div class="info-item">
                            <span class="info-label">Order:</span>
                            <span class="info-value"><?php echo $video['video_order'] ?? 'Not set'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Type:</span>
                            <span class="info-value">
                                <?php 
                                $video_file = $video['video_file'] ?? '';
                                $video_url = $video['video_url'] ?? '';
                                
                                if ($video_file && !preg_match('/^https?:\/\//', $video_file)) {
                                    echo '<span class="badge bg-success"><i class="bi bi-file-play me-1"></i>Uploaded File</span>';
                                } elseif ($video_url || ($video_file && preg_match('/^https?:\/\//', $video_file))) {
                                    if (strpos($video_url ?: $video_file, 'youtube.com') !== false || strpos($video_url ?: $video_file, 'youtu.be') !== false) {
                                        echo '<span class="badge bg-danger"><i class="bi bi-youtube me-1"></i>YouTube</span>';
                                    } elseif (strpos($video_url ?: $video_file, 'drive.google.com') !== false) {
                                        echo '<span class="badge bg-primary"><i class="bi bi-google-drive me-1"></i>Google Drive</span>';
                                    } else {
                                        echo '<span class="badge bg-info"><i class="bi bi-link-45deg me-1"></i>External Link</span>';
                                    }
                                } else {
                                    echo '<span class="badge bg-secondary"><i class="bi bi-question-circle me-1"></i>Unknown</span>';
                                }
                                ?>
                            </span>
                        </div>
                        <?php if ($video_url || ($video_file && preg_match('/^https?:\/\//', $video_file))): ?>
                            <div class="info-item">
                                <span class="info-label">Source:</span>
                                <span class="info-value">
                                    <a href="<?php echo htmlspecialchars($video_url ?: $video_file); ?>" target="_blank" class="text-decoration-none text-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>Open Link
                                    </a>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($video['min_watch_time'])): ?>
                            <div class="info-item">
                                <span class="info-label">Min Watch Time:</span>
                                <span class="info-value">
                                    <span class="badge bg-warning">
                                        <i class="bi bi-clock me-1"></i><?php echo $video['min_watch_time']; ?> seconds
                                    </span>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($video['created_at'])): ?>
                            <div class="info-item">
                                <span class="info-label">Created:</span>
                                <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($video['created_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($video['updated_at'])): ?>
                            <div class="info-item">
                                <span class="info-label">Last Updated:</span>
                                <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($video['updated_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <a href="module_videos.php?module_id=<?php echo $module['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-list me-1"></i>Back to Module Videos
                        </a>
                        <?php if ($is_acad_period_active): ?>
                            <a href="video_stats.php?id=<?php echo $video_id; ?>" class="btn btn-outline-info">
                                <i class="bi bi-graph-up me-1"></i>View Statistics
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


<style>
/* Modern Video Details Styles - Matching Videos Management Design */

/* Video Details Header */
.video-details-header {
    background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    position: relative;
}

/* Back Button in Header */
.video-details-header .btn-outline-light {
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    background: rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    font-weight: 600;
    border-radius: 8px;
}

.video-details-header .btn-outline-light:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Back Button Icon Only - Left Corner */
.btn-back-icon {
    width: 45px !important;
    height: 45px !important;
    border-radius: 50% !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 1.2rem !important;
    position: absolute !important;
    top: 20px !important;
    left: 20px !important;
    z-index: 10 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
}

.btn-back-icon:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
}

.video-details-header h1 {
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.video-details-header .opacity-90 {
    opacity: 0.9;
}

.video-stats {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.video-stat-item {
    text-align: center;
}

.video-stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.video-stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Video Player Card */
.video-player-card {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    overflow: hidden;
}

.video-player-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 1.25rem 1.5rem;
    font-weight: 600;
    color: #495057;
}

/* Video Info Card */
.video-info-card {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

.video-info-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 1.25rem 1.5rem;
    font-weight: 600;
    color: #495057;
}

/* Info Items */
.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f4;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.9rem;
    flex: 0 0 40%;
}

.info-value {
    color: #495057;
    font-size: 0.9rem;
    text-align: right;
    flex: 1;
}

/* Badge Enhancements */
.badge {
    font-size: 0.75rem;
    padding: 0.4rem 0.6rem;
    border-radius: 6px;
    font-weight: 600;
}

/* Button Enhancements */
.btn {
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}

.btn-outline-info:hover {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    box-shadow: 0 4px 12px rgba(23,162,184,0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .video-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .video-details-header {
        padding: 1.5rem 0;
    }
    
    .video-stat-number {
        font-size: 1.5rem;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .info-value {
        text-align: left;
    }
}

@media (max-width: 576px) {
    .video-details-header h1 {
        font-size: 1.5rem;
    }
    
    .video-stat-number {
        font-size: 1.25rem;
    }
    
    .video-stat-label {
        font-size: 0.75rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?> 