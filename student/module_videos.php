<?php
$page_title = 'Module Videos';
require_once '../config/config.php';
requireRole('student');
require_once '../includes/header.php';
require_once '../includes/video_player.php';

$module_id = $_GET['module_id'] ?? '';
$course_id = $_GET['course_id'] ?? '';

if (empty($module_id)) {
    header('Location: courses.php?error=Invalid module ID.');
    exit;
}

// If course_id is not provided, find it
if (empty($course_id)) {
    $stmt = $db->prepare('SELECT id, modules FROM courses WHERE JSON_SEARCH(sections, "one", (SELECT section_id FROM users WHERE id = ?)) IS NOT NULL');
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll();
    
    foreach ($courses as $course) {
        $modules = json_decode($course['modules'], true) ?: [];
        foreach ($modules as $module) {
            if ($module['id'] === $module_id) {
                $course_id = $course['id'];
                break 2;
            }
        }
    }
    
    if (empty($course_id)) {
        header('Location: courses.php?error=Module not found.');
        exit;
    }
}

// Get course and module data
$stmt = $db->prepare("
    SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
    FROM courses c
    JOIN academic_periods ap ON c.academic_period_id = ap.id
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ? AND JSON_SEARCH(c.sections, 'one', (SELECT section_id FROM users WHERE id = ?)) IS NOT NULL
");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php?error=Course not found or access denied.');
    exit;
}

// Get the specific module
$modules = json_decode($course['modules'], true) ?: [];
$current_module = null;
foreach ($modules as $module) {
    if ($module['id'] === $module_id) {
        $current_module = $module;
        break;
    }
}

if (!$current_module) {
    header('Location: course.php?id=' . $course_id . '&error=Module not found.');
    exit;
}

// Sort videos by order
$videos = $current_module['videos'] ?? [];
usort($videos, function($a, $b) {
    return ($a['video_order'] ?? 0) - ($b['video_order'] ?? 0);
});
?>

<style>
.video-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    border-radius: 0.5rem;
    overflow: hidden;
    margin-bottom: 2rem;
}

.video-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    border-color: #2E5E4E;
}

.video-header {
    background: linear-gradient(135deg, #2E5E4E, #7DCB80);
    color: white;
    padding: 1rem;
}

.video-title {
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 1.2rem;
}

.video-meta {
    font-size: 0.9rem;
    opacity: 0.9;
}

.video-content {
    padding: 1.5rem;
}

.video-description {
    color: #6c757d;
    margin-bottom: 1rem;
    line-height: 1.6;
}

.required-badge {
    background: #ffc107;
    color: #000;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 500;
}

.module-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: #dee2e6;
}

.progress-bar {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2E5E4E, #7DCB80);
    transition: width 0.3s ease;
}
</style>

<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="courses.php">My Courses</a></li>
            <li class="breadcrumb-item"><a href="course.php?id=<?php echo $course_id; ?>"><?php echo htmlspecialchars($course['course_name']); ?></a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($current_module['module_title']); ?></li>
        </ol>
    </nav>

    <!-- Module Header -->
    <div class="module-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 mb-2"><?php echo htmlspecialchars($current_module['module_title']); ?></h1>
                    <p class="mb-0 opacity-75">
                        <?php echo htmlspecialchars($course['course_name']); ?> • 
                        <?php echo htmlspecialchars($course['course_code']); ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex align-items-center justify-content-md-end">
                        <div class="me-3">
                            <small class="opacity-75">Progress</small>
                            <div class="progress-bar" style="width: 200px;">
                                <div class="progress-fill" style="width: 0%;"></div>
                            </div>
                        </div>
                        <span class="badge bg-light text-dark"><?php echo count($videos); ?> videos</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Module Description -->
    <?php if (!empty($current_module['module_description'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">About This Module</h6>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($current_module['module_description'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Videos List -->
    <div class="row">
        <?php if (empty($videos)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <i class="bi bi-play-circle"></i>
                            <h5>No Videos Available</h5>
                            <p>This module doesn't have any videos yet. Check back later!</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($videos as $index => $video): ?>
                <div class="col-12">
                    <div class="card video-card">
                        <div class="video-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="video-title">
                                        <?php echo htmlspecialchars($video['video_title']); ?>
                                        <?php if ($video['is_required'] ?? false): ?>
                                            <span class="required-badge ms-2">Required</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="video-meta">
                                        Video <?php echo $video['video_order'] ?? ($index + 1); ?>
                                        <?php if (isset($video['duration']) && $video['duration'] > 0): ?>
                                            • <i class="bi bi-clock me-1"></i><?php echo $video['duration']; ?> min
                                        <?php endif; ?>
                                        <?php if (isset($video['min_watch_time']) && $video['min_watch_time'] > 0): ?>
                                            • <i class="bi bi-eye me-1"></i>Min: <?php echo $video['min_watch_time']; ?> min
                                        <?php endif; ?>
                                        <?php if (isset($video['file'])): ?>
                                            • <i class="bi bi-download me-1"></i>Download Available
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button class="btn btn-outline-light btn-sm" onclick="toggleVideo('video_<?php echo $video['id']; ?>')">
                                    <i class="bi bi-chevron-down" id="icon_<?php echo $video['id']; ?>"></i>
                                </button>
                            </div>
                        </div>
                        <div class="video-content" id="video_<?php echo $video['id']; ?>" style="display: none;">
                            <?php echo renderVideoPlayer($video, [
                                'width' => '100%',
                                'height' => '400px',
                                'show_title' => false,
                                'show_description' => true
                            ]); ?>
                            
                            <!-- Video Progress Tracker -->
                            <div class="video-progress mt-3" id="progress_<?php echo $video['id']; ?>" style="display: none;">
                                <div class="progress mb-2">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">
                                    <span id="watch_time_<?php echo $video['id']; ?>">0</span> seconds watched
                                    <?php if (isset($video['min_watch_time']) && $video['min_watch_time'] > 0): ?>
                                        (Minimum: <?php echo $video['min_watch_time'] * 60; ?> seconds)
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <script>
                            // Initialize video tracker for this video
                            document.addEventListener('DOMContentLoaded', function() {
                                const videoData = {
                                    videoId: '<?php echo $video['id']; ?>',
                                    moduleId: '<?php echo $module_id; ?>',
                                    minWatchTime: <?php echo $video['min_watch_time'] ?? 5; ?>
                                };
                                
                                if (window.VideoTracker) {
                                    new VideoTracker(videoData.videoId, videoData.moduleId, videoData.minWatchTime);
                                }
                            });
                            </script>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="video_tracker.js"></script>
<script>
function toggleVideo(videoId) {
    const videoElement = document.getElementById(videoId);
    const iconElement = document.getElementById('icon_' + videoId.replace('video_', ''));
    const progressElement = document.getElementById('progress_' + videoId.replace('video_', ''));
    
    if (videoElement.style.display === 'none') {
        videoElement.style.display = 'block';
        iconElement.className = 'bi bi-chevron-up';
        if (progressElement) {
            progressElement.style.display = 'block';
        }
    } else {
        videoElement.style.display = 'none';
        iconElement.className = 'bi bi-chevron-down';
        if (progressElement) {
            progressElement.style.display = 'none';
        }
    }
}

// Auto-open first video
document.addEventListener('DOMContentLoaded', function() {
    const firstVideo = document.querySelector('.video-card');
    if (firstVideo) {
        const firstVideoId = firstVideo.querySelector('[id^="video_"]').id;
        toggleVideo(firstVideoId);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
