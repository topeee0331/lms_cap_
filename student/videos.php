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
$module_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$module_id) {
    header('Location: courses.php');
    exit();
}

// Fetch module and course details, including academic period status
$stmt = $pdo->prepare("SELECT c.course_name, c.academic_period_id, ap.is_active as academic_period_active FROM courses c JOIN academic_periods ap ON c.academic_period_id = ap.id WHERE c.id = ?");
$stmt->execute([$module_id]);
$course_info = $stmt->fetch(PDO::FETCH_ASSOC);
$is_acad_period_active = $course_info ? (bool)$course_info['academic_period_active'] : true;

// Get module info from JSON
$stmt = $pdo->prepare("SELECT modules FROM courses WHERE id = ?");
$stmt->execute([$module_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course || !$course['modules']) {
    $_SESSION['error'] = 'Course not found or has no modules.';
    header('Location: courses.php');
    exit();
}

$modules = json_decode($course['modules'], true);
$module_info = null;
foreach ($modules as $index => $module) {
    if ($index == $module_id) {
        $module_info = [
            'id' => $index,
            'module_title' => $module['title'] ?? 'Module ' . ($index + 1),
            'course_name' => $course_info['course_name']
        ];
        break;
    }
}

if (!$module_info) {
    $_SESSION['error'] = 'You are not enrolled in this module or it does not exist.';
    header('Location: courses.php');
    exit();
}

// Fetch all videos for this module from JSON
$videos = [];
if (isset($modules[$module_id]['videos']) && is_array($modules[$module_id]['videos'])) {
    $videos = $modules[$module_id]['videos'];
    // Add index as id for compatibility
    foreach ($videos as $index => &$video) {
        $video['id'] = $index;
    }
    unset($video);
}

// After fetching $videos, fetch watched video IDs for this student
$watched_ids = [];
// For now, we'll use session-based tracking since video_views table might not exist
if (isset($_SESSION['videos_watched'])) {
    $watched_ids = $_SESSION['videos_watched'];
}

$page_title = 'Videos: ' . htmlspecialchars($module_info['module_title']);

// Add this function to handle YouTube URLs
function get_youtube_embed_url($url) {
    if (preg_match('/(youtu\\.be\\/|v=)([\\w-]+)/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[2];
    }
    return null;
}

// Add a function to get YouTube thumbnail
function get_youtube_thumbnail($url) {
    if (preg_match('/youtu(?:\\.be|be\\.com)\\/(?:watch\\?v=)?([\\w\\-]+)/i', $url, $yt_match)) {
        return 'https://img.youtube.com/vi/' . $yt_match[1] . '/mqdefault.jpg';
    }
    return null;
}

// Add Google Drive link handling functions
function convert_google_drive_link($url) {
    // Convert Google Drive sharing links to direct access links
    if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $file_id = $matches[1];
        return 'https://drive.google.com/uc?export=view&id=' . $file_id;
    }
    
    // Handle Google Drive sharing links with different formats
    if (preg_match('/drive\.google\.com\/open\?id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $file_id = $matches[1];
        return 'https://drive.google.com/uc?export=view&id=' . $file_id;
    }
    
    return $url;
}

function is_google_drive_link($url) {
    return strpos($url, 'drive.google.com') !== false;
}

function get_google_drive_embed_url($url) {
    // Extract file ID from various Google Drive URL formats
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
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Removed Sidebar -->
        <main class="col-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-0 pb-2 mb-2 border-bottom">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="courses.php">My Courses</a></li>
                            <li class="breadcrumb-item"><a href="course.php?id=<?php echo $module_info['course_id']; ?>"><?php echo htmlspecialchars($module_info['course_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="module.php?id=<?php echo $module_info['id']; ?>"><?php echo htmlspecialchars($module_info['module_title']); ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Videos</li>
                        </ol>
                    </nav>
                    <h1 class="h2">Videos (<?php echo count($videos); ?>)</h1>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="module.php?id=<?php echo $module_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Module
                    </a>
                </div>
            </div>

            <?php if (!$is_acad_year_active): ?>
                <div class="alert alert-warning mb-4">
                    <strong>Inactive Academic Year:</strong> This academic year is inactive. You can only view and review content for this module. All actions are disabled.
                </div>
            <?php endif; ?>

            <div class="row">
                <?php if (empty($videos)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No videos have been added to this module yet.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($videos as $video): ?>
                        <?php
                        $video_file = $video['video_file'] ?? '';
                        $video_url = $video['video_url'] ?? '';
                        $play_source = $video_file ?: $video_url;
                        
                        // Handle Google Drive links
                        if (is_google_drive_link($play_source)) {
                            $play_source = get_google_drive_embed_url($play_source);
                        }
                        
                        $youtube_thumb = get_youtube_thumbnail($play_source);
                        $is_url = preg_match('/^(https?:)?\/\//i', $play_source);
                        $is_google_drive = is_google_drive_link($video_url);
                        $video_id = $video['id'];
                        ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100 shadow-sm video-card">
                                <div class="card-img-top-container">
                                    <div class="thumbnail-wrapper" id="thumb-wrap-<?php echo $video_id; ?>">
                                        <?php if ($youtube_thumb): ?>
                                            <img src="<?php echo htmlspecialchars($youtube_thumb); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($video['video_title']); ?>">
                                        <?php elseif ($is_url): ?>
                                            <div class="no-thumbnail"><i class="fas fa-link"></i></div>
                                        <?php else: ?>
                                            <video class="card-img-top" preload="metadata">
                                                <source src="/lms_cap/uploads/videos/<?php echo htmlspecialchars($play_source); ?>#t=0.5" type="video/mp4">
                                            </video>
                                        <?php endif; ?>
                                        <div class="play-icon-overlay" onclick="playInlineVideo(<?php echo $video_id; ?>, <?php echo $is_url ? 'true' : 'false'; ?>, '<?php echo htmlspecialchars(addslashes($play_source)); ?>', '<?php echo htmlspecialchars(addslashes($video['video_title'])); ?>')">
                                            <i class="fas fa-play-circle fa-4x"></i>
                                        </div>
                                        <?php if (!empty($watched_ids[$video_id])): ?>
                                            <span class="badge bg-success position-absolute top-0 start-0 m-2 watched-badge">Watched</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($video['video_title']); ?></h6>
                                    <p class="card-text small text-muted"><?php echo htmlspecialchars(substr($video['video_description'], 0, 100)); ?>...</p>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <a href="video_details.php?id=<?php echo $video_id; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                        <?php if (empty($watched_ids[$video_id])): ?>
                                            <button class="btn btn-outline-success btn-sm mark-watched-btn" data-video-id="<?php echo $video_id; ?>">Mark as Watched</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
function markVideoWatched(videoId) {
    fetch('mark_video_watched.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'video_id=' + encodeURIComponent(videoId)
    });
    // Show badge immediately
    var thumbWrap = document.getElementById('thumb-wrap-' + videoId);
    if (thumbWrap && !thumbWrap.querySelector('.watched-badge')) {
        var badge = document.createElement('span');
        badge.className = 'badge bg-success position-absolute top-0 start-0 m-2 watched-badge';
        badge.textContent = 'Watched';
        thumbWrap.appendChild(badge);
    }
}
function playInlineVideo(id, isUrl, playSource, videoTitle) {
    var wrap = document.getElementById('thumb-wrap-' + id);
    if (!wrap) return;
    if (isUrl) {
        // Check if it's a Google Drive link
        if (playSource.includes('drive.google.com')) {
            // Convert to Google Drive embed URL
            var fileId = playSource.match(/\/d\/([a-zA-Z0-9_-]+)/);
            if (fileId) {
                var embedUrl = 'https://drive.google.com/file/d/' + fileId[1] + '/preview';
                wrap.innerHTML = '<div class="ratio ratio-16x9" style="min-height:400px;"><iframe src="' + embedUrl + '" allowfullscreen frameborder="0" title="' + videoTitle + '" style="width:100%;height:100%"></iframe></div>';
            } else {
                // Fallback for other Google Drive formats
                wrap.innerHTML = '<div class="ratio ratio-16x9" style="min-height:400px;"><iframe src="' + playSource + '" allowfullscreen frameborder="0" title="' + videoTitle + '" style="width:100%;height:100%"></iframe></div>';
            }
        } else {
            // YouTube embed
            var ytMatch = playSource.match(/youtu(?:\.be|be\.com)\/(?:watch\?v=)?([\w\-]+)/i);
            var embedUrl = playSource;
            if (ytMatch) {
                embedUrl = 'https://www.youtube.com/embed/' + ytMatch[1];
            }
            wrap.innerHTML = '<div class="ratio ratio-16x9" style="min-height:400px;"><iframe src="' + embedUrl + '" allowfullscreen frameborder="0" title="' + videoTitle + '" style="width:100%;height:100%"></iframe></div>';
        }
    } else {
        // Uploaded file
        wrap.innerHTML = '<div class="ratio ratio-16x9" style="min-height:400px;"><video controls autoplay class="w-100 h-100" style="background:#000;border-radius:8px;" allowfullscreen><source src="/lms_cap/uploads/videos/' + playSource + '" type="video/mp4">Your browser does not support the video tag.</video></div>';
    }
    markVideoWatched(id);
}
document.querySelectorAll('.mark-watched-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var videoId = this.getAttribute('data-video-id');
        markVideoWatched(videoId);
        // Show badge
        var thumbWrap = document.getElementById('thumb-wrap-' + videoId);
        if (thumbWrap && !thumbWrap.querySelector('.watched-badge')) {
            var badge = document.createElement('span');
            badge.className = 'badge bg-success position-absolute top-0 start-0 m-2 watched-badge';
            badge.textContent = 'Watched';
            thumbWrap.appendChild(badge);
        }
        // Disable/hide button
        this.disabled = true;
        this.style.display = 'none';
    });
});
</script>

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
.video-card .card-img-top-container {
    position: relative;
    overflow: hidden;
    height: 250px;
    background-color: #000;
}
.video-card .thumbnail-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
}
.video-card .card-img-top {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.video-card .play-icon-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(0, 0, 0, 0.3);
    color: white;
    opacity: 0;
    transition: opacity 0.3s ease;
    cursor: pointer;
}
.video-card:hover .play-icon-overlay {
    opacity: 1;
}
.video-card .no-thumbnail {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f0f0f0;
    color: #aaa;
    font-size: 3rem;
}
.video-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.video-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.video-card.watched {
    border-left: 4px solid #28a745;
}
.assessment-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.assessment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.progress-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: conic-gradient(#007bff 0deg, #007bff <?php echo $video_progress * 3.6; ?>deg, #e9ecef <?php echo $video_progress * 3.6; ?>deg, #e9ecef 360deg);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}
.progress-circle::before {
    content: '';
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: white;
}
.progress-text {
    position: absolute;
    font-size: 1rem;
    font-weight: bold;
    color: #007bff;
}

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
</style> 