<?php
$page_title = 'Video Statistics';
require_once '../config/config.php';
requireRole('teacher');
require_once '../includes/header.php';

$video_id = sanitizeInput($_GET['id'] ?? '');

if (!$video_id) {
    redirectWithMessage('videos.php', 'Video ID is required.', 'danger');
}

// Find the video in the JSON modules structure
$video = null;
$course = null;
$module = null;

// Get all courses for the teacher and search for the video
$stmt = $db->prepare("
    SELECT c.id, c.course_name, c.course_code, c.modules
    FROM courses c
    WHERE c.teacher_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$courses_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($courses_data as $course_data) {
    $modules_data = json_decode($course_data['modules'], true);
    if (is_array($modules_data)) {
        foreach ($modules_data as $mod) {
            if (isset($mod['videos']) && is_array($mod['videos'])) {
                foreach ($mod['videos'] as $vid) {
                    if (($vid['id'] ?? '') == $video_id) {
                        $video = $vid;
                        $course = $course_data;
                        $module = $mod;
                        break 3;
                    }
                }
            }
        }
    }
}

if (!$video || !$course) {
    redirectWithMessage('videos.php', 'Video not found or access denied.', 'danger');
}

// Handle video edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_video'])) {
    $new_title = trim($_POST['video_title'] ?? '');
    $new_description = trim($_POST['video_description'] ?? '');
    $new_url = trim($_POST['video_url'] ?? '');
    if ($new_title && $video_id) {
        $stmt = $db->prepare('UPDATE course_videos SET video_title = ?, video_description = ?, video_url = ? WHERE id = ?');
        $stmt->execute([$new_title, $new_description, $new_url, $video_id]);
        // Refresh $video with new data
        $stmt = $db->prepare("
            SELECT cv.*, c.course_name, c.course_code
            FROM course_videos cv
            JOIN courses c ON cv.course_id = c.id
            WHERE cv.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$video_id, $_SESSION['user_id']]);
        $video = $stmt->fetch();
        $edit_message = 'Video details updated successfully!';
    }
}

// Get video statistics
// Convert string video_id to integer using crc32 hash (same as in mark_video_watched_with_time.php)
$video_id_int = crc32($video_id);

$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT vv.student_id) as unique_viewers,
        COUNT(vv.id) as total_views,
        AVG(vv.completion_percentage) as avg_completion,
        AVG(vv.watch_duration) as avg_watch_duration,
        MIN(vv.viewed_at) as first_view,
        MAX(vv.viewed_at) as last_view
    FROM video_views vv
    WHERE vv.video_id = ?
");
$stmt->execute([$video_id_int]);
$stats = $stmt->fetch();

// Get viewer details
$stmt = $db->prepare("
    SELECT vv.*, u.first_name, u.last_name, u.email, u.profile_picture
    FROM video_views vv
    JOIN users u ON vv.student_id = u.id
    WHERE vv.video_id = ?
    ORDER BY vv.viewed_at DESC
");
$stmt->execute([$video_id_int]);
$viewers = $stmt->fetchAll();

// Get completion rate distribution
$stmt = $db->prepare("
    SELECT 
        CASE 
            WHEN completion_percentage >= 90 THEN '90-100%'
            WHEN completion_percentage >= 75 THEN '75-89%'
            WHEN completion_percentage >= 50 THEN '50-74%'
            WHEN completion_percentage >= 25 THEN '25-49%'
            ELSE '0-24%'
        END as completion_range,
        COUNT(*) as count
    FROM video_views
    WHERE video_id = ?
    GROUP BY completion_range
    ORDER BY completion_range DESC
");
$stmt->execute([$video_id_int]);
$completion_distribution = $stmt->fetchAll();

// Get daily view trends (last 30 days)
$stmt = $db->prepare("
    SELECT 
        DATE(viewed_at) as view_date,
        COUNT(*) as view_count,
        COUNT(DISTINCT student_id) as unique_viewers
    FROM video_views 
    WHERE video_id = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(viewed_at)
    ORDER BY view_date
");
$stmt->execute([$video_id_int]);
$daily_trends = $stmt->fetchAll();

// Debug: Log the daily trends data
error_log("Daily trends data for video $video_id_int: " . json_encode($daily_trends));

// Get engagement by time of day
$stmt = $db->prepare("
    SELECT 
        HOUR(viewed_at) as hour,
        COUNT(*) as view_count
    FROM video_views
    WHERE video_id = ?
    GROUP BY HOUR(viewed_at)
    ORDER BY hour
");
$stmt->execute([$video_id_int]);
$hourly_engagement = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Video Statistics</h1>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($video['video_title'] ?? 'Unknown Video'); ?> • 
                        <?php echo htmlspecialchars($course['course_name'] ?? 'Unknown Course'); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="videos.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Videos
                    </a>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editVideoModal">
                        <i class="bi bi-pencil me-1"></i>Edit Video
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($edit_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $edit_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Video Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <?php if (!empty($video['video_url'])): ?>
                                <?php
                                $url = $video['video_url'];
                                if (preg_match('/youtu\.be|youtube\.com/', $url)) {
                                    // YouTube embed
                                    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:embed/|v/|watch\?v=|watch\?.+&v=))([^&?/]+)~', $url, $matches)) {
                                        $youtube_id = $matches[1];
                                        echo '<iframe class="w-100" style="height: 200px;" src="https://www.youtube.com/embed/' . htmlspecialchars($youtube_id) . '" frameborder="0" allowfullscreen></iframe>';
                                    } else {
                                        echo '<div class="text-danger">Invalid YouTube link</div>';
                                    }
                                } elseif (preg_match('/drive\.google\.com/', $url)) {
                                    // Google Drive embed
                                    if (preg_match('~/d/([a-zA-Z0-9_-]+)~', $url, $matches)) {
                                        $drive_id = $matches[1];
                                        echo '<iframe class="w-100" style="height: 200px;" src="https://drive.google.com/file/d/' . htmlspecialchars($drive_id) . '/preview" allowfullscreen></iframe>';
                                    } else {
                                        echo '<div class="text-danger">Invalid Google Drive link</div>';
                                    }
                                } elseif (preg_match('/\.mp4$|\.webm$|\.ogg$/', $url)) {
                                    // Direct video file
                                    echo '<video class="w-100" style="height: 200px; object-fit: cover;" controls><source src="' . htmlspecialchars($url) . '">Your browser does not support the video tag.</video>';
                                } else {
                                    // Generic iframe
                                    echo '<iframe class="w-100" style="height: 200px;" src="' . htmlspecialchars($url) . '" allowfullscreen></iframe>';
                                }
                                ?>
                            <?php elseif (!empty($video['video_file'])): ?>
                                <video class="w-100" controls>
                                    <source src="../uploads/videos/<?php echo $video['video_file']; ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <h5 class="mb-2"><?php echo htmlspecialchars($video['video_title'] ?? 'Unknown Video'); ?></h5>
                            <?php if (!empty($video['video_description'])): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($video['video_description'] ?? ''); ?></p>
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-6">
                                    <strong>Module:</strong> <?php echo htmlspecialchars($module['module_title'] ?? $module['title'] ?? 'Unknown Module'); ?><br>
                                    <strong>Course:</strong> <?php echo htmlspecialchars($course['course_name'] ?? 'Unknown Course'); ?><br>
                                </div>
                                <div class="col-6">
                                    <strong>Order:</strong> <?php echo $video['video_order'] ?? 'Not set'; ?><br>
                                    <strong>Uploaded:</strong> <?php echo isset($video['created_at']) ? date('M j, Y', strtotime($video['created_at'])) : 'Unknown'; ?><br>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-2" id="completion-percentage"><?php echo number_format($stats['avg_completion'] ?? 0, 1); ?>%</h3>
                    <div class="progress mb-3" style="height: 25px; border-radius: 12px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                             id="completion-progress-bar" 
                             role="progressbar" 
                             style="width: 0%; border-radius: 12px; transition: width 1.5s ease-in-out;"
                             aria-valuenow="<?php echo $stats['avg_completion'] ?? 0; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <span class="progress-text" style="color: white; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                                <?php echo number_format($stats['avg_completion'] ?? 0, 1); ?>%
                            </span>
                        </div>
                    </div>
                    <small class="text-muted">Average Completion Rate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo $stats['unique_viewers'] ?? 0; ?></h4>
                    <small>Unique Viewers</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo $stats['total_views'] ?? 0; ?></h4>
                    <small>Total Views</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo formatDuration($stats['avg_watch_duration'] ?? 0); ?></h4>
                    <small>Avg Watch Time</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0"><?php echo $stats['total_views'] > 0 ? round(($stats['total_views'] / $stats['unique_viewers']) * 100) : 0; ?>%</h4>
                    <small>Re-watch Rate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Daily View Trends (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($daily_trends)): ?>
                        <div style="position: relative; height: 400px;">
                            <canvas id="dailyTrendsChart"></canvas>
                        </div>
                        <!-- Debug info (remove in production) -->
                        <?php if (isset($_GET['debug'])): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6>Debug Info:</h6>
                            <small class="text-muted">
                                <strong>Data points:</strong> <?php echo count($daily_trends); ?><br>
                                <strong>Date range:</strong> <?php echo min(array_column($daily_trends, 'view_date')); ?> to <?php echo max(array_column($daily_trends, 'view_date')); ?><br>
                                <strong>Total views:</strong> <?php echo array_sum(array_column($daily_trends, 'view_count')); ?><br>
                                <strong>Max daily views:</strong> <?php echo max(array_column($daily_trends, 'view_count')); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="bi bi-graph-up" style="font-size: 3rem; color: #cbd5e0;"></i>
                            </div>
                            <h6 class="text-muted mb-2">No View Data Available</h6>
                            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                                This video hasn't been viewed yet. Data will appear here once students start watching.
                            </p>
                            <small class="text-muted mt-2 d-block">
                                Video ID: <?php echo htmlspecialchars($video_id); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Completion Rate Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($completion_distribution)): ?>
                        <div style="position: relative; height: 400px;">
                            <canvas id="completionChart"></canvas>
                        </div>
                        <!-- Completion Statistics Summary -->
                        <div class="mt-3">
                            <div class="row text-center">
                                <?php 
                                $totalStudents = array_sum(array_column($completion_distribution, 'count'));
                                $highCompletion = 0;
                                $mediumCompletion = 0;
                                $lowCompletion = 0;
                                
                                foreach ($completion_distribution as $range) {
                                    if (in_array($range['completion_range'], ['90-100%', '75-89%'])) {
                                        $highCompletion += $range['count'];
                                    } elseif (in_array($range['completion_range'], ['50-74%', '25-49%'])) {
                                        $mediumCompletion += $range['count'];
                                    } else {
                                        $lowCompletion += $range['count'];
                                    }
                                }
                                ?>
                                <div class="col-4">
                                    <div class="p-2 bg-success bg-opacity-10 rounded">
                                        <h6 class="mb-1 text-success"><?php echo $highCompletion; ?></h6>
                                        <small class="text-muted">High (75%+)</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-warning bg-opacity-10 rounded">
                                        <h6 class="mb-1 text-warning"><?php echo $mediumCompletion; ?></h6>
                                        <small class="text-muted">Medium (25-74%)</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-danger bg-opacity-10 rounded">
                                        <h6 class="mb-1 text-danger"><?php echo $lowCompletion; ?></h6>
                                        <small class="text-muted">Low (0-24%)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Debug info (remove in production) -->
                        <?php if (isset($_GET['debug'])): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6>Debug Info:</h6>
                            <small class="text-muted">
                                <strong>Data points:</strong> <?php echo count($completion_distribution); ?><br>
                                <strong>Total students:</strong> <?php echo $totalStudents; ?><br>
                                <strong>Highest completion range:</strong> <?php 
                                    $maxRange = array_reduce($completion_distribution, function($carry, $item) {
                                        return ($carry === null || $item['count'] > $carry['count']) ? $item : $carry;
                                    });
                                    echo $maxRange ? $maxRange['completion_range'] . ' (' . $maxRange['count'] . ' students)' : 'N/A';
                                ?><br>
                                <strong>Completion ranges:</strong> <?php echo implode(', ', array_column($completion_distribution, 'completion_range')); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="bi bi-pie-chart" style="font-size: 3rem; color: #cbd5e0;"></i>
                            </div>
                            <h6 class="text-muted mb-2">No Completion Data Available</h6>
                            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                                This video hasn't been viewed yet. Completion distribution data will appear here once students start watching.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Hourly Engagement Trend -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Engagement by Time of Day</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($hourly_engagement)): ?>
                        <div style="position: relative; height: 400px;">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                        <!-- Debug info (remove in production) -->
                        <?php if (isset($_GET['debug'])): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6>Debug Info:</h6>
                            <small class="text-muted">
                                <strong>Data points:</strong> <?php echo count($hourly_engagement); ?><br>
                                <strong>Peak hour:</strong> <?php 
                                    $maxHour = array_reduce($hourly_engagement, function($carry, $item) {
                                        return ($carry === null || $item['view_count'] > $carry['view_count']) ? $item : $carry;
                                    });
                                    echo $maxHour ? $maxHour['hour'] . ':00 (' . $maxHour['view_count'] . ' views)' : 'N/A';
                                ?><br>
                                <strong>Total views:</strong> <?php echo array_sum(array_column($hourly_engagement, 'view_count')); ?><br>
                                <strong>Active hours:</strong> <?php echo count(array_filter($hourly_engagement, function($h) { return $h['view_count'] > 0; })); ?> out of 24
                            </small>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="bi bi-clock" style="font-size: 3rem; color: #cbd5e0;"></i>
                            </div>
                            <h6 class="text-muted mb-2">No Time-Based Data Available</h6>
                            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                                This video hasn't been viewed yet. Time-based engagement data will appear here once students start watching.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Viewer Details -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Viewer Details (<?php echo count($viewers); ?> views)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($viewers)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-eye-slash fs-1 text-muted mb-3"></i>
                            <h6>No Views Yet</h6>
                            <p class="text-muted">This video hasn't been viewed by any students yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Watched</th>
                                        <th>Duration</th>
                                        <th>Completion</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($viewers as $viewer): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                                                    <img src="<?php echo getProfilePictureUrl($viewer['profile_picture'] ?? null, 'small'); ?>" 
                                     class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($viewer['first_name'] . ' ' . $viewer['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($viewer['email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y g:i A', strtotime($viewer['viewed_at'] ?? '1970-01-01 00:00:00')); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo formatDuration($viewer['watch_duration'] ?? 0); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($viewer['completion_percentage'] ?? 0) >= 90 ? 'success' : (($viewer['completion_percentage'] ?? 0) >= 70 ? 'info' : (($viewer['completion_percentage'] ?? 0) >= 50 ? 'warning' : 'danger')); ?>">
                                                    <?php echo $viewer['completion_percentage'] ?? 0; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px; border-radius: 10px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                                                    <div class="progress-bar viewer-progress-bar" 
                                                         data-width="<?php echo $viewer['completion_percentage'] ?? 0; ?>"
                                                         style="width: 0%; border-radius: 10px; transition: width 1s ease-in-out;"
                                                         role="progressbar" 
                                                         aria-valuenow="<?php echo $viewer['completion_percentage'] ?? 0; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <span style="color: white; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                                                            <?php echo $viewer['completion_percentage'] ?? 0; ?>%
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Video Modal -->
<div class="modal fade" id="editVideoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Video Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_video_title" class="form-label">Video Title</label>
                        <input type="text" class="form-control" id="edit_video_title" name="video_title" value="<?php echo htmlspecialchars($video['video_title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_video_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_video_description" name="video_description" rows="3"><?php echo htmlspecialchars($video['video_description'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_video_url" class="form-label">Video Link (YouTube, Google Drive, or direct .mp4 link)</label>
                        <input type="url" class="form-control" id="edit_video_url" name="video_url" value="<?php echo htmlspecialchars($video['video_url'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_video" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Enhanced Progress Bar Styling */
.progress {
    background-color: #e9ecef;
    border: 1px solid #dee2e6;
    overflow: hidden;
}

.progress-bar {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.progress-bar.progress-bar-striped {
    background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent);
    background-size: 1rem 1rem;
}

.progress-bar.progress-bar-animated {
    animation: progress-bar-stripes 1s linear infinite;
}

@keyframes progress-bar-stripes {
    0% { background-position: 1rem 0; }
    100% { background-position: 0 0; }
}

.progress-text {
    font-size: 0.875rem;
    white-space: nowrap;
}

/* Hover effects for progress bars */
.progress:hover .progress-bar {
    transform: scale(1.02);
    transition: transform 0.2s ease;
}

/* Color variations for different completion rates */
.progress-bar.bg-success {
    background: linear-gradient(45deg, #28a745, #20c997);
}

.progress-bar.bg-info {
    background: linear-gradient(45deg, #17a2b8, #6f42c1);
}

.progress-bar.bg-warning {
    background: linear-gradient(45deg, #ffc107, #fd7e14);
}

.progress-bar.bg-danger {
    background: linear-gradient(45deg, #dc3545, #e83e8c);
}

/* Enhanced Chart Styling */
.card canvas {
    border-radius: 8px;
}

/* Time-based chart specific styling */
#hourlyChart {
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.05) 0%, rgba(255, 107, 107, 0.02) 100%);
    border-radius: 8px;
}

/* Completion distribution chart specific styling */
#completionChart {
    background: linear-gradient(135deg, rgba(156, 39, 176, 0.05) 0%, rgba(156, 39, 176, 0.02) 100%);
    border-radius: 8px;
}

/* Chart container hover effect */
.card:hover canvas {
    transform: scale(1.01);
    transition: transform 0.3s ease;
}
</style>
<script>
// Daily Trends Chart
<?php if (!empty($daily_trends)): ?>
console.log('📊 Initializing Daily Trends Chart...');
console.log('📈 Daily trends data:', <?php echo json_encode($daily_trends); ?>);

const dailyCtx = document.getElementById('dailyTrendsChart');
if (dailyCtx) {
    const dailyChart = new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(function($d) { return date('M j', strtotime($d['view_date'])); }, $daily_trends)); ?>,
            datasets: [{
                label: 'Total Views',
                data: <?php echo json_encode(array_column($daily_trends, 'view_count')); ?>,
                borderColor: '#2E5E4E',
                backgroundColor: 'rgba(46, 94, 78, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#2E5E4E',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#7DCB80',
                pointHoverBorderColor: '#2E5E4E',
                pointHoverBorderWidth: 3
            }, {
                label: 'Unique Viewers',
                data: <?php echo json_encode(array_column($daily_trends, 'unique_viewers')); ?>,
                borderColor: '#7DCB80',
                backgroundColor: 'rgba(125, 203, 128, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#7DCB80',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#2E5E4E',
                pointHoverBorderColor: '#7DCB80',
                pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Daily View Trends (Last 30 Days)',
                    font: {
                        size: 16,
                        weight: 'bold'
                    },
                    color: '#2E5E4E'
                },
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#2E5E4E',
                    borderWidth: 2,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        title: function(context) {
                            return 'Date: ' + context[0].label;
                        },
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y;
                            return label + ': ' + value + ' ' + (value === 1 ? 'view' : 'views');
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date',
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        color: '#2E5E4E'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        maxTicksLimit: 10,
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    display: true,
                    title: {
                        display: true,
                        text: 'Number of Views',
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        color: '#2E5E4E'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 10
                        },
                        callback: function(value) {
                            return Math.floor(value) === value ? value : '';
                        }
                    }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            }
        }
    });
    
    console.log('✅ Daily Trends Chart initialized successfully');
} else {
    console.error('❌ Daily trends chart canvas not found');
}
<?php else: ?>
console.log('⚠️ No daily trends data available');
<?php endif; ?>

// Completion Distribution Trend Chart
<?php if (!empty($completion_distribution)): ?>
console.log('📊 Initializing Completion Distribution Trend Chart...');
console.log('📈 Completion distribution data:', <?php echo json_encode($completion_distribution); ?>);

const completionCtx = document.getElementById('completionChart');
if (completionCtx) {
    // Create ordered completion ranges for trend visualization
    const completionRanges = ['90-100%', '75-89%', '50-74%', '25-49%', '0-24%'];
    const completionData = new Array(completionRanges.length).fill(0);
    const completionLabels = [];
    
    // Fill in the actual data
    <?php echo json_encode($completion_distribution); ?>.forEach(function(item) {
        const index = completionRanges.indexOf(item.completion_range);
        if (index !== -1) {
            completionData[index] = item.count;
        }
    });
    
    // Create labels with better formatting
    completionRanges.forEach(function(range) {
        const [min, max] = range.split('-');
        const maxVal = max.replace('%', '');
        completionLabels.push(`${min}-${maxVal}%`);
    });
    
    const completionChart = new Chart(completionCtx, {
        type: 'line',
        data: {
            labels: completionLabels,
            datasets: [{
                label: 'Number of Students',
                data: completionData,
                borderColor: '#9C27B0',
                backgroundColor: 'rgba(156, 39, 176, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#9C27B0',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#BA68C8',
                pointHoverBorderColor: '#9C27B0',
                pointHoverBorderWidth: 3,
                spanGaps: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Completion Rate Distribution Trend',
                    font: {
                        size: 16,
                        weight: 'bold'
                    },
                    color: '#2E5E4E'
                },
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#9C27B0',
                    borderWidth: 2,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        title: function(context) {
                            return 'Completion Range: ' + context[0].label;
                        },
                        label: function(context) {
                            const value = context.parsed.y;
                            const range = context.label;
                            return 'Students: ' + value + ' ' + (value === 1 ? 'student' : 'students');
                        },
                        afterLabel: function(context) {
                            const value = context.parsed.y;
                            const total = completionData.reduce((sum, val) => sum + val, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `(${percentage}% of total)`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Completion Percentage Range',
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        color: '#2E5E4E'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    display: true,
                    title: {
                        display: true,
                        text: 'Number of Students',
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        color: '#2E5E4E'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 10
                        },
                        callback: function(value) {
                            return Math.floor(value) === value ? value : '';
                        }
                    }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            }
        }
    });
    
    console.log('✅ Completion Distribution Trend Chart initialized successfully');
} else {
    console.error('❌ Completion distribution chart canvas not found');
}
<?php else: ?>
console.log('⚠️ No completion distribution data available');
<?php endif; ?>

// Hourly Engagement Trend Chart
<?php if (!empty($hourly_engagement)): ?>
console.log('🕐 Initializing Hourly Engagement Trend Chart...');
console.log('📊 Hourly engagement data:', <?php echo json_encode($hourly_engagement); ?>);

const hourlyCtx = document.getElementById('hourlyChart');
if (hourlyCtx) {
    // Create a complete 24-hour dataset with zeros for missing hours
    const hourlyData = new Array(24).fill(0);
    const hourlyLabels = [];
    
    // Fill in the actual data
    <?php echo json_encode($hourly_engagement); ?>.forEach(function(item) {
        hourlyData[item.hour] = item.view_count;
    });
    
    // Create labels for all 24 hours
    for (let i = 0; i < 24; i++) {
        hourlyLabels.push(i.toString().padStart(2, '0') + ':00');
    }
    
    const hourlyChart = new Chart(hourlyCtx, {
        type: 'line',
        data: {
            labels: hourlyLabels,
            datasets: [{
                label: 'Views by Hour',
                data: hourlyData,
                borderColor: '#FF6B6B',
                backgroundColor: 'rgba(255, 107, 107, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#FF6B6B',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#FF8E8E',
                pointHoverBorderColor: '#FF6B6B',
                pointHoverBorderWidth: 3,
                spanGaps: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Engagement by Time of Day (24-Hour Trend)',
                    font: {
                        size: 16,
                        weight: 'bold'
                    },
                    color: '#2E5E4E'
                },
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#FF6B6B',
                    borderWidth: 2,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        title: function(context) {
                            const hour = context[0].label;
                            const hourNum = parseInt(hour.split(':')[0]);
                            let timeOfDay = '';
                            if (hourNum >= 6 && hourNum < 12) timeOfDay = 'Morning';
                            else if (hourNum >= 12 && hourNum < 17) timeOfDay = 'Afternoon';
                            else if (hourNum >= 17 && hourNum < 21) timeOfDay = 'Evening';
                            else timeOfDay = 'Night';
                            return hour + ' (' + timeOfDay + ')';
                        },
                        label: function(context) {
                            const value = context.parsed.y;
                            return 'Views: ' + value + ' ' + (value === 1 ? 'view' : 'views');
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Time of Day (24-Hour Format)',
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        color: '#2E5E4E'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        maxTicksLimit: 12,
                        font: {
                            size: 10
                        },
                        callback: function(value, index) {
                            // Show every 2 hours
                            return index % 2 === 0 ? this.getLabelForValue(value) : '';
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    display: true,
                    title: {
                        display: true,
                        text: 'Number of Views',
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        color: '#2E5E4E'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 10
                        },
                        callback: function(value) {
                            return Math.floor(value) === value ? value : '';
                        }
                    }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            }
        }
    });
    
    console.log('✅ Hourly Engagement Trend Chart initialized successfully');
} else {
    console.error('❌ Hourly engagement chart canvas not found');
}
<?php else: ?>
console.log('⚠️ No hourly engagement data available');
<?php endif; ?>

// Animate progress bars on page load
document.addEventListener('DOMContentLoaded', function() {
    // Animate the main completion rate progress bar
    const completionBar = document.getElementById('completion-progress-bar');
    if (completionBar) {
        const targetWidth = completionBar.getAttribute('aria-valuenow');
        const percentage = parseFloat(targetWidth) || 0;
        
        // Set the progress bar color based on completion rate
        if (percentage >= 90) {
            completionBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-success';
        } else if (percentage >= 70) {
            completionBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
        } else if (percentage >= 50) {
            completionBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
        } else {
            completionBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-danger';
        }
        
        // Animate the progress bar
        setTimeout(() => {
            completionBar.style.width = percentage + '%';
        }, 500);
    }
    
    // Animate individual viewer progress bars
    const viewerBars = document.querySelectorAll('.viewer-progress-bar');
    viewerBars.forEach((bar, index) => {
        const targetWidth = bar.getAttribute('data-width');
        const percentage = parseFloat(targetWidth) || 0;
        
        // Set color based on completion rate
        if (percentage >= 90) {
            bar.className = 'progress-bar bg-success';
        } else if (percentage >= 70) {
            bar.className = 'progress-bar bg-info';
        } else if (percentage >= 50) {
            bar.className = 'progress-bar bg-warning';
        } else {
            bar.className = 'progress-bar bg-danger';
        }
        
        // Animate with staggered delay
        setTimeout(() => {
            bar.style.width = percentage + '%';
        }, 800 + (index * 100));
    });
});
</script>

<?php
function formatDuration($seconds) {
    // Convert to integer to avoid deprecated float-string conversion warning
    $seconds = (int) $seconds;
    $minutes = floor($seconds / 60);
    $remaining_seconds = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $remaining_seconds);
}

function formatFileSize($filepath) {
    if (!file_exists($filepath)) {
        return 'Unknown';
    }
    
    $size = filesize($filepath);
    $units = ['B', 'KB', 'MB', 'GB'];
    $unit = 0;
    
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    
    return round($size, 2) . ' ' . $units[$unit];
}
?>

<?php require_once '../includes/footer.php'; ?> 