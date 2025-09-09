<?php
// Start output buffering to prevent header issues
ob_start();

require_once '../config/config.php';
requireRole('teacher');

$page_title = 'Module Videos';
require_once '../includes/header.php';

// Ensure database connection is available
if (!isset($db) || !$db) {
    $db = new Database();
}





$module_id = sanitizeInput($_GET['module_id'] ?? '');

// Verify teacher owns this module by finding it in the course's modules JSON
$stmt = $db->prepare("
    SELECT c.*, c.modules
    FROM courses c
    WHERE c.teacher_id = ? AND JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL
");
$stmt->execute([$_SESSION['user_id'], $module_id]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php?error=Module not found or access denied.');
    exit;
}

// Extract module data from JSON
$modules_data = json_decode($course['modules'], true);
$module = null;
foreach ($modules_data as $mod) {
    if ($mod['id'] === $module_id) {
        $module = $mod;
        break;
    }
}

if (!$module) {
    header('Location: courses.php?error=Module not found or access denied.');
    exit;
}

$message = '';
$message_type = '';

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = 'Video link added successfully.';
    $message_type = 'success';
} elseif (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $message = 'Video updated successfully.';
    $message_type = 'success';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $message = 'Video deleted successfully.';
    $message_type = 'success';
}

// Handle video actions (same method as videos.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'upload_video':
                $video_title = sanitizeInput($_POST['video_title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $min_watch_time = (int)($_POST['min_watch_time'] ?? 30);
                $video_url = trim($_POST['video_url'] ?? '');
                
                if (empty($video_title)) {
                    $message = 'Video title is required.';
                    $message_type = 'danger';
                } elseif (empty($video_url)) {
                    $message = 'Video link is required.';
                    $message_type = 'danger';
                } elseif ($min_watch_time < 1 || $min_watch_time > 30) {
                    $message = 'Minimum watch time must be between 1 and 30 minutes.';
                    $message_type = 'danger';
                } else {
                    // Add video to module's JSON (working approach)
                    $modules_data = json_decode($course['modules'], true);
                    if (is_array($modules_data)) {
                        foreach ($modules_data as &$module) {
                            if ($module['id'] === $module_id) {
                                if (!isset($module['videos'])) {
                                    $module['videos'] = [];
                                }
                                
                                $module['videos'][] = [
                                    'id' => uniqid('vid_'),
                                    'video_title' => $video_title,
                                    'video_description' => $description,
                                    'video_url' => $video_url,
                                    'min_watch_time' => $min_watch_time,
                                    'created_at' => date('Y-m-d H:i:s')
                                ];
                                
                                // Update course with updated modules JSON
                                $update_stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                                $result = $update_stmt->execute([json_encode($modules_data), $course['id']]);
                                
                                if ($result) {
                                    // Use proper PHP header redirect
                                    header('Location: module_videos.php?module_id=' . urlencode($module_id) . '&success=1');
                                    exit();
                                } else {
                                    $message = 'Failed to update course with new video.';
                                    $message_type = 'danger';
                                }
                                break;
                            }
                        }
                    } else {
                        $message = 'Failed to decode course modules.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'update_video':
                $video_id = sanitizeInput($_POST['video_id'] ?? '');
                $video_title = sanitizeInput($_POST['video_title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $min_watch_time = (int)($_POST['min_watch_time'] ?? 30);
                $video_url = trim($_POST['video_url'] ?? '');
                
                if (empty($video_title) || empty($video_id)) {
                    $message = 'Video title and ID are required.';
                    $message_type = 'danger';
                } elseif ($min_watch_time < 1 || $min_watch_time > 30) {
                    $message = 'Minimum watch time must be between 1 and 30 minutes.';
                    $message_type = 'danger';
                } else {
                    // Update video in module's JSON
                    $modules_data = json_decode($course['modules'], true);
                    if (is_array($modules_data)) {
                        $video_updated = false;
                        foreach ($modules_data as &$module) {
                            if ($module['id'] === $module_id) {
                                if (isset($module['videos']) && is_array($module['videos'])) {
                                    foreach ($module['videos'] as &$video) {
                                        if ($video['id'] === $video_id) {
                                            $video['video_title'] = $video_title;
                                            $video['video_description'] = $description;
                                            $video['video_url'] = $video_url;
                                            $video['min_watch_time'] = $min_watch_time;
                                            $video['updated_at'] = date('Y-m-d H:i:s');
                                            $video_updated = true;
                                            break;
                                        }
                                    }
                                }
                                break;
                            }
                        }
                        
                        if ($video_updated) {
                            // Update course with updated modules JSON
                            $update_stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                            $result = $update_stmt->execute([json_encode($modules_data), $course['id']]);
                            
                            if ($result) {
                                // Use proper PHP header redirect
                                header('Location: module_videos.php?module_id=' . urlencode($module_id) . '&updated=1');
                                exit();
                            } else {
                                $message = 'Failed to update course with video changes.';
                                $message_type = 'danger';
                            }
                        } else {
                            $message = 'Video not found.';
                            $message_type = 'danger';
                        }
                    } else {
                        $message = 'Failed to decode course modules.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'delete_video':
                $video_id = sanitizeInput($_POST['video_id'] ?? '');
                
                if (empty($video_id)) {
                    $message = 'Video ID is required.';
                    $message_type = 'danger';
                } else {
                    // Delete video from module's JSON
                    $modules_data = json_decode($course['modules'], true);
                    if (is_array($modules_data)) {
                        $video_deleted = false;
                        foreach ($modules_data as &$module) {
                            if ($module['id'] === $module_id) {
                                if (isset($module['videos']) && is_array($module['videos'])) {
                                    // Find and remove the video
                                    foreach ($module['videos'] as $index => $video) {
                                        if (isset($video['id']) && $video['id'] === $video_id) {
                                            // Remove video from array
                                            array_splice($module['videos'], $index, 1);
                                            $video_deleted = true;
                                            break;
                                        }
                                    }
                                }
                                break;
                            }
                        }
                        
                        if ($video_deleted) {
                            // Update course with updated modules JSON
                            $update_stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                            $result = $update_stmt->execute([json_encode($modules_data), $course['id']]);
                            
                            if ($result) {
                                // Use proper PHP header redirect
                                header('Location: module_videos.php?module_id=' . urlencode($module_id) . '&deleted=1');
                                exit();
                            } else {
                                $message = 'Failed to update course after video deletion.';
                                $message_type = 'danger';
                            }
                        } else {
                            $message = 'Video not found or could not be deleted.';
                            $message_type = 'danger';
                        }
                    } else {
                        $message = 'Failed to decode course modules.';
                        $message_type = 'danger';
                    }
                }
                break;
        }
    }
}

// Get videos from the current module (JSON-based approach) - using videos.php logic
$videos = [];

// Check if the current module has videos
if (isset($module['videos']) && is_array($module['videos'])) {
    foreach ($module['videos'] as $video) {
        // Skip videos that don't have required fields
        if (!isset($video['id']) || !isset($video['video_title'])) {
            continue;
        }
        
        $video['course_name'] = $course['course_name'];
        $video['course_code'] = $course['course_code'];
        $video['module_title'] = $module['module_title'] ?? 'Unknown Module';
        $video['course_id'] = $course['id'];
        $video['module_id'] = $module['id'];
        $video['view_count'] = 0; // Placeholder - would need to calculate from video_progress
        $video['avg_completion'] = 0; // Placeholder - would need to calculate from video_progress
        $video['unique_viewers'] = 0; // Placeholder
        $video['total_watch_time'] = 0; // Placeholder
        
        $videos[] = $video;
    }
}

// Sort videos by creation time (newest first)
usort($videos, function($a, $b) {
    $time_a = strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
    $time_b = strtotime($b['created_at'] ?? '1970-01-01 00:00:00');
    return $time_b - $time_a; // Newest first
});

// Debug logging for video count - using videos.php logic
error_log("Total videos found in module: " . count($videos));

// Get students in sections for this course - using videos.php logic
$student_names = [];
$student_ids = [];
$video_viewers = [];
$video_stats = [];

if ($course['sections']) {
    $section_ids = json_decode($course['sections'], true);
    if (is_array($section_ids)) {
        $placeholders = str_repeat('?,', count($section_ids) - 1) . '?';
        $stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name 
                              FROM sections s 
                              JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
                              WHERE s.id IN ($placeholders)");
        $stmt->execute($section_ids);
        foreach ($stmt->fetchAll() as $stu) {
            $student_names[$stu['id']] = $stu['last_name'] . ', ' . $stu['first_name'];
            $student_ids[] = $stu['id'];
        }
        
        // Get video statistics from video_views table (if it exists)
        if ($videos && $student_ids) {
            // Check if video_views table exists
            $table_exists = false;
            try {
                $stmt = $db->prepare("SHOW TABLES LIKE 'video_views'");
                $stmt->execute();
                $table_exists = $stmt->rowCount() > 0;
            } catch (Exception $e) {
                error_log("Error checking video_views table: " . $e->getMessage());
                $table_exists = false;
            }
            
            if ($table_exists) {
                foreach ($videos as $video) {
                    $video_id = $video['id'];
                    $video_viewers[$video_id] = [];
                    $video_stats[$video_id] = [
                        'total_views' => 0,
                        'unique_viewers' => 0,
                        'avg_completion' => 0,
                        'total_watch_time' => 0
                    ];
                    
                    try {
                        // Convert string video_id to integer using crc32 hash (same as in mark_video_watched_with_time.php)
                        $video_id_int = crc32($video_id);
                        
                        // Get video view statistics
                        $stmt = $db->prepare("
                            SELECT 
                                COUNT(*) as total_views,
                                COUNT(DISTINCT student_id) as unique_viewers,
                                AVG(completion_percentage) as avg_completion,
                                SUM(watch_duration) as total_watch_time,
                                GROUP_CONCAT(DISTINCT student_id) as viewer_ids
                            FROM video_views 
                            WHERE video_id = ? AND student_id IN (" . str_repeat('?,', count($student_ids) - 1) . "?)
                        ");
                        $params = array_merge([$video_id_int], $student_ids);
                        $stmt->execute($params);
                        $stats = $stmt->fetch();
                        
                        if ($stats) {
                            $video_stats[$video_id] = [
                                'total_views' => (int)$stats['total_views'],
                                'unique_viewers' => (int)$stats['unique_viewers'],
                                'avg_completion' => round((float)$stats['avg_completion'], 1),
                                'total_watch_time' => (int)$stats['total_watch_time']
                            ];
                            
                            // Get individual viewers
                            if ($stats['viewer_ids']) {
                                $viewer_ids = explode(',', $stats['viewer_ids']);
                                $video_viewers[$video_id] = array_map('intval', $viewer_ids);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching video stats for video $video_id: " . $e->getMessage());
                        // Continue with default stats
                    }
                }
            } else {
                // Table doesn't exist, use default stats
                foreach ($videos as $video) {
                    $video_id = $video['id'];
                    $video_viewers[$video_id] = [];
                    $video_stats[$video_id] = [
                        'total_views' => 0,
                        'unique_viewers' => 0,
                        'avg_completion' => 0,
                        'total_watch_time' => 0
                    ];
                }
            }
        }
    }
}

// Update video data with real statistics
foreach ($videos as &$video) {
    $video_id = $video['id'];
    if (isset($video_stats[$video_id])) {
        $video['view_count'] = $video_stats[$video_id]['total_views'];
        $video['unique_viewers'] = $video_stats[$video_id]['unique_viewers'];
        $video['avg_completion'] = $video_stats[$video_id]['avg_completion'];
        $video['total_watch_time'] = $video_stats[$video_id]['total_watch_time'];
    }
}

// Debug function to check video tracking
function checkVideoTracking($video_id, $db) {
    try {
        // Check if video_views table exists first
        $stmt = $db->prepare("SHOW TABLES LIKE 'video_views'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            return 0; // Table doesn't exist
        }
        
        // Convert string video_id to integer using crc32 hash (same as in mark_video_watched_with_time.php)
        $video_id_int = crc32($video_id);
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM video_views WHERE video_id = ?");
        $stmt->execute([$video_id_int]);
        return $stmt->fetch()['count'];
    } catch (Exception $e) {
        error_log("Error checking video tracking: " . $e->getMessage());
        return 0;
    }
}

// Add debug information if requested
$debug_info = [];
if (isset($_GET['debug'])) {
    foreach ($videos as $video) {
        $debug_info[$video['id']] = [
            'video_title' => $video['video_title'],
            'total_views_in_db' => checkVideoTracking($video['id'], $db),
            'displayed_views' => $video['view_count'] ?? 0,
            'unique_viewers' => $video['unique_viewers'] ?? 0,
            'avg_completion' => $video['avg_completion'] ?? 0
        ];
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Videos for <?php echo htmlspecialchars($module['module_title']); ?></h1>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($course['course_name']); ?> • 
                        <?php echo htmlspecialchars($course['course_code']); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Course
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadVideoModal">
                        <i class="bi bi-upload me-2"></i>Add Video Link
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Module Info -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="card-title">Module Description</h6>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($module['module_description'])); ?></p>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <span class="badge bg-secondary">Module <?php echo $module['module_order']; ?></span>
                                <?php if ($module['is_locked']): ?>
                                    <span class="badge bg-warning ms-1">Locked (<?php echo $module['unlock_score']; ?>% required)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Statistics Summary -->
    <?php if (!empty($videos)): ?>
        <?php
        $total_views = array_sum(array_column($video_stats, 'total_views'));
        $total_unique_viewers = array_sum(array_column($video_stats, 'unique_viewers'));
        $avg_completion = count($video_stats) > 0 ? round(array_sum(array_column($video_stats, 'avg_completion')) / count($video_stats), 1) : 0;
        $total_watch_time = array_sum(array_column($video_stats, 'total_watch_time'));
        ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Module Video Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary mb-1"><?php echo $total_views; ?></h4>
                                    <small class="text-muted">Total Views</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-info mb-1"><?php echo $total_unique_viewers; ?></h4>
                                    <small class="text-muted">Unique Viewers</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-success mb-1"><?php echo $avg_completion; ?>%</h4>
                                    <small class="text-muted">Avg Completion</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-warning mb-1"><?php echo gmdate("H:i:s", $total_watch_time); ?></h4>
                                    <small class="text-muted">Total Watch Time</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Video Time Requirements Info -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-clock me-2"></i>Video Time Requirements</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <p class="mb-0 small">
                                <strong>Individual Video Time Requirements:</strong> Each video can have its own minimum watch time requirement. 
                                Students must watch each video for the specified duration to have it counted as "viewed" in their progress.
                                This prevents students from just clicking play without actually watching the content.
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <span class="badge bg-info">Default: 5 minutes</span>
                                <span class="badge bg-warning">Range: 1-30 minutes</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Videos Grid -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Videos (<?php echo count($videos); ?>)</h5>
                        <div class="text-end">
                            <?php
                            $total_min_time = 0;
                            foreach ($videos as $video) {
                                $total_min_time += $video['min_watch_time'] ?? 30;
                            }
                            $avg_min_time = count($videos) > 0 ? round($total_min_time / count($videos)) : 0;
                            ?>
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>Avg min time: <?php echo $avg_min_time; ?>s
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                        <!-- Debug info - only show when debug=1 is in URL -->
                        <div class="alert alert-info">
                            <strong>Debug Info:</strong><br>
                            Videos count: <?php echo count($videos); ?><br>
                            Module ID: <?php echo htmlspecialchars($module_id); ?><br>
                            Module has videos key: <?php echo isset($module['videos']) ? 'YES' : 'NO'; ?><br>
                            <?php if (isset($module['videos'])): ?>
                                Module videos count: <?php echo count($module['videos']); ?><br>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    

                    
                    <?php if (empty($videos)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-camera-video-off fs-1 text-muted mb-3"></i>
                            <h6>No Videos Found</h6>
                            <p class="text-muted">Add your first video link for this module to start creating engaging content.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadVideoModal">
                                <i class="bi bi-upload me-1"></i>Add First Video Link
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($videos as $video): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="video-card-preview">
                                            <?php 
                                            $video_url = $video['video_url'] ?? '';
                                            $video_file = $video['video_file'] ?? '';
                                            
                                            // Check if video_file contains a URL (from old data)
                                            if (!empty($video_file) && (strpos($video_file, 'http') === 0 || strpos($video_file, 'www.') === 0)) {
                                                $video_url = $video_file;
                                                $video_file = '';
                                            }
                                            
                                            if (!empty($video_url)) {
                                                if (preg_match('/youtu\.be|youtube\.com/', $video_url)) {
                                                    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:embed/|v/|watch\?v=|watch\?.+&v=))([^&?/]+)~', $video_url, $matches)) {
                                                            $youtube_id = $matches[1];
                                                            echo '<iframe class="card-img-top" style="height: 200px; width: 100%;" src="https://www.youtube.com/embed/' . htmlspecialchars($youtube_id) . '" frameborder="0" allowfullscreen></iframe>';
                                                        } else {
                                                            echo '<div class="text-danger">Invalid YouTube link</div>';
                                                        }
                                                } elseif (preg_match('/drive\.google\.com/', $video_url)) {
                                                    // Google Drive
                                                    if (preg_match('~/d/([a-zA-Z0-9_-]+)~', $video_url, $matches)) {
                                                            $drive_id = $matches[1];
                                                            echo '<iframe class="card-img-top" style="height: 200px; width: 100%;" src="https://drive.google.com/file/d/' . htmlspecialchars($drive_id) . '/preview" allowfullscreen></iframe>';
                                                        } else {
                                                            echo '<div class="text-danger">Invalid Google Drive link</div>';
                                                        }
                                                } elseif (preg_match('/\.mp4$|\.webm$|\.ogg$/', $video_url)) {
                                                        // Direct video file
                                                    echo '<video class="card-img-top" style="height: 200px; object-fit: cover; width: 100%;" controls><source src="' . htmlspecialchars($video_url) . '">Your browser does not support the video tag.</video>';
                                                    } else {
                                                        // Generic iframe
                                                    echo '<iframe class="card-img-top" style="height: 200px; width: 100%;" src="' . htmlspecialchars($video_url) . '" allowfullscreen></iframe>';
                                                }
                                            } elseif (!empty($video_file)) {
                                                $ext = strtolower(pathinfo($video_file, PATHINFO_EXTENSION));
                                                $mime = 'video/mp4';
                                                if ($ext === 'webm') $mime = 'video/webm';
                                                elseif ($ext === 'ogg' || $ext === 'ogv') $mime = 'video/ogg';
                                                echo '<video class="card-img-top" style="height: 200px; object-fit: cover; width: 100%;" controls><source src="/lms_cap/uploads/videos/' . htmlspecialchars($video_file) . '" type="' . $mime . '">Your browser does not support the video tag.</video>';
                                            }
                                            ?>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($video['video_title']); ?></h6>
                                            <?php if ($video['video_description']): ?>
                                                <p class="card-text small text-muted"><?php echo htmlspecialchars(substr($video['video_description'], 0, 100)) . '...'; ?></p>
                                            <?php endif; ?>
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i>Min Watch: <?php echo $video['min_watch_time'] ?? 30; ?> seconds
                                                </small>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-eye me-1"></i><?php echo $video['view_count']; ?> views
                                                </small>
                                                <small class="text-muted">
                                                            <i class="bi bi-people me-1"></i><?php echo $video['unique_viewers'] ?? 0; ?> unique viewers
                                                </small>
                                            </div>
                                            <div class="btn-group btn-group-sm w-100">
                                                <a href="video_details.php?id=<?php echo $video['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i>View Details
                                                </a>
                                                <button class="btn btn-outline-secondary" onclick="editVideo(<?php echo htmlspecialchars(json_encode($video)); ?>)">
                                                    <i class="bi bi-pencil me-1"></i>Edit
                                                </button>
                                                <button class="btn btn-outline-info" onclick="viewVideoStats('<?php echo $video['id']; ?>')">
                                                    <i class="bi bi-graph-up me-1"></i>Stats
                                                </button>
                                                <button class="btn btn-outline-danger delete-confirm" onclick="deleteVideo('<?php echo $video['id']; ?>', '<?php echo htmlspecialchars($video['video_title']); ?>')">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Video Modal -->
<div class="modal fade" id="uploadVideoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Video Link to <?php echo htmlspecialchars($module['module_title']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="upload_video">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="upload_video_title" class="form-label">Video Title</label>
                        <input type="text" class="form-control" id="upload_video_title" name="video_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="upload_description" class="form-label">Description</label>
                        <textarea class="form-control" id="upload_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="upload_min_watch_time" class="form-label">Minimum Watch Time (minutes)</label>
                        <input type="number" class="form-control" id="upload_min_watch_time" name="min_watch_time" 
                               value="5" min="1" max="30" step="1" required>
                        <div class="form-text">Minimum time students must watch to count as "viewed" (1-30 minutes)</div>
                    </div>
                    <div class="mb-3">
                        <label for="upload_video_url" class="form-label">Video Link (YouTube, Google Drive, or direct .mp4 link)</label>
                        <input type="url" class="form-control" id="upload_video_url" name="video_url" placeholder="https://..." required>
                        <div class="form-text">Paste a YouTube, Google Drive, or direct video link. File uploads are no longer supported.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Video Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Video Modal -->
<div class="modal fade" id="editVideoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_video">
                    <input type="hidden" name="video_id" id="edit_video_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="edit_video_title" class="form-label">Video Title</label>
                        <input type="text" class="form-control" id="edit_video_title" name="video_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_min_watch_time" class="form-label">Minimum Watch Time (minutes)</label>
                        <input type="number" class="form-control" id="edit_min_watch_time" name="min_watch_time" 
                               min="1" max="30" step="1" required>
                        <div class="form-text">Minimum time students must watch to count as "viewed" (1-30 minutes)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Video</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Video Form -->
<form id="deleteVideoForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_video">
    <input type="hidden" name="video_id" id="delete_video_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<script>
function editVideo(video) {
    console.log('editVideo called with:', video);
    
    try {
        document.getElementById('edit_video_id').value = video.id;
        document.getElementById('edit_video_title').value = video.video_title || '';
        document.getElementById('edit_description').value = video.video_description || '';
        document.getElementById('edit_min_watch_time').value = video.min_watch_time || 30;

        const modal = new bootstrap.Modal(document.getElementById('editVideoModal'));
        modal.show();
    } catch (error) {
        console.error('Error in editVideo:', error);
        alert('Error opening edit modal. Please check console for details.');
    }
}

function deleteVideo(videoId, videoTitle) {
    console.log('deleteVideo called with:', videoId, videoTitle);
    
    try {
        if (confirm(`Are you sure you want to delete "${videoTitle}"? This action cannot be undone.`)) {
            document.getElementById('delete_video_id').value = videoId;
            document.getElementById('deleteVideoForm').submit();
        }
    } catch (error) {
        console.error('Error in deleteVideo:', error);
        alert('Error deleting video. Please check console for details.');
    }
}

function viewVideoStats(videoId) {
    console.log('viewVideoStats called with:', videoId);
    
    try {
        // Redirect to video statistics page
        window.location.href = `video_stats.php?id=${videoId}`;
    } catch (error) {
        console.error('Error in viewVideoStats:', error);
        alert('Error opening video stats. Please check console for details.');
    }
}


// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Debug: Page loaded, checking video display...');
    console.log('🔍 Videos count from PHP:', <?php echo count($videos); ?>);
    console.log('🔍 Videos data from PHP:', <?php echo json_encode($videos); ?>);
    
    // Check if we have videos to display
    const videoCards = document.querySelectorAll('.col-md-6.col-lg-4.mb-4');
    console.log('🔍 Video cards found in DOM:', videoCards.length);
    
         // Auto-hide success messages after 5 seconds to prevent them from staying visible
     const successAlert = document.querySelector('.alert-success');
     if (successAlert) {
        console.log('🔍 Success alert found:', successAlert.textContent);
         setTimeout(() => {
             successAlert.style.transition = 'opacity 0.5s ease-out';
             successAlert.style.opacity = '0';
             setTimeout(() => {
                 if (successAlert.parentNode) {
                     successAlert.remove();
                 }
             }, 500);
         }, 5000);
     }
});
</script>

<?php
function formatDuration($seconds) {
    $minutes = floor($seconds / 60);
    $remaining_seconds = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $remaining_seconds);
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
}
?>

<?php require_once '../includes/footer.php'; ?> 