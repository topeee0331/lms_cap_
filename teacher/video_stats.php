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
                    <h3 class="mb-2"><?php echo number_format($stats['avg_completion'] ?? 0, 1); ?>%</h3>
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $stats['avg_completion'] ?? 0; ?>%">
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
        <?php if (!empty($daily_trends)): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Daily View Trends (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyTrendsChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($completion_distribution)): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Completion Rate Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="completionChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Hourly Engagement -->
    <?php if (!empty($hourly_engagement)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Engagement by Time of Day</h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-<?php echo ($viewer['completion_percentage'] ?? 0) >= 90 ? 'success' : (($viewer['completion_percentage'] ?? 0) >= 70 ? 'info' : (($viewer['completion_percentage'] ?? 0) >= 50 ? 'warning' : 'danger')); ?>" 
                                                         style="width: <?php echo $viewer['completion_percentage'] ?? 0; ?>%">
                                                        <?php echo $viewer['completion_percentage'] ?? 0; ?>%
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
<script>
// Daily Trends Chart
<?php if (!empty($daily_trends)): ?>
const dailyCtx = document.getElementById('dailyTrendsChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($d) { return date('M j', strtotime($d['view_date'])); }, $daily_trends)); ?>,
        datasets: [{
            label: 'Total Views',
            data: <?php echo json_encode(array_column($daily_trends, 'view_count')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }, {
            label: 'Unique Viewers',
            data: <?php echo json_encode(array_column($daily_trends, 'unique_viewers')); ?>,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>

// Completion Distribution Chart
<?php if (!empty($completion_distribution)): ?>
const completionCtx = document.getElementById('completionChart').getContext('2d');
new Chart(completionCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($completion_distribution, 'completion_range')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($completion_distribution, 'count')); ?>,
            backgroundColor: [
                '#28a745',
                '#17a2b8',
                '#ffc107',
                '#fd7e14',
                '#dc3545'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Hourly Engagement Chart
<?php if (!empty($hourly_engagement)): ?>
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($h) { return $h['hour'] . ':00'; }, $hourly_engagement)); ?>,
        datasets: [{
            label: 'Views',
            data: <?php echo json_encode(array_column($hourly_engagement, 'view_count')); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.8)'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>
</script>

<?php
function formatDuration($seconds) {
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