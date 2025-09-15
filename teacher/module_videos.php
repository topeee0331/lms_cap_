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

<!-- Modern Module Videos Header with Back Button -->
<div class="module-videos-header">
    <div class="container">
        <!-- Back Button Row -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-start">
                    <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-light btn-back-icon" title="Back to Course">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Header Content -->
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-3">
                    <i class="bi bi-camera-video me-3"></i>Module Videos
                </h1>
                <p class="mb-0 opacity-90">
                    <strong><?php echo htmlspecialchars($module['module_title']); ?></strong> • 
                    <?php echo htmlspecialchars($course['course_name']); ?> • 
                    <?php echo htmlspecialchars($course['course_code']); ?>
                </p>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="video-stats">
                        <div class="video-stat-item">
                            <span class="video-stat-number"><?php echo count($videos); ?></span>
                            <span class="video-stat-label">Videos</span>
                        </div>
                        <div class="video-stat-item">
                            <span class="video-stat-number"><?php echo $total_views ?? 0; ?></span>
                            <span class="video-stat-label">Total Views</span>
                        </div>
                        <div class="video-stat-item">
                            <span class="video-stat-number"><?php echo $avg_completion ?? 0; ?>%</span>
                            <span class="video-stat-label">Avg Completion</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>


    <!-- Video Statistics Summary -->
    <?php if (!empty($videos)): ?>
        <?php
        $total_views = array_sum(array_column($video_stats, 'total_views'));
        $total_unique_viewers = array_sum(array_column($video_stats, 'unique_viewers'));
        $avg_completion = count($video_stats) > 0 ? round(array_sum(array_column($video_stats, 'avg_completion')) / count($video_stats), 1) : 0;
        $total_watch_time = array_sum(array_column($video_stats, 'total_watch_time'));
        ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card stats-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="stats-content">
                                <h3 class="stats-number"><?php echo $total_views; ?></h3>
                                <p class="stats-label mb-0">Total Views</p>
                                <small class="stats-subtitle">All video views</small>
                            </div>
                            <div class="stats-icon">
                                <i class="bi bi-eye"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card stats-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="stats-content">
                                <h3 class="stats-number"><?php echo $total_unique_viewers; ?></h3>
                                <p class="stats-label mb-0">Unique Viewers</p>
                                <small class="stats-subtitle">Individual students</small>
                            </div>
                            <div class="stats-icon">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card stats-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="stats-content">
                                <h3 class="stats-number"><?php echo $avg_completion; ?>%</h3>
                                <p class="stats-label mb-0">Avg Completion</p>
                                <small class="stats-subtitle">Watch completion rate</small>
                            </div>
                            <div class="stats-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card stats-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="stats-content">
                                <h3 class="stats-number"><?php echo gmdate("H:i:s", $total_watch_time); ?></h3>
                                <p class="stats-label mb-0">Total Watch Time</p>
                                <small class="stats-subtitle">Cumulative viewing</small>
                            </div>
                            <div class="stats-icon">
                                <i class="bi bi-clock"></i>
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
            <div class="card info-card border-0 shadow-sm">
                <div class="card-header info-header">
                    <h6 class="mb-0">
                        <i class="bi bi-clock me-2"></i>Video Time Requirements
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="text-primary mb-2">Individual Video Time Requirements</h6>
                            <p class="mb-0 small text-muted">
                                Each video can have its own minimum watch time requirement. Students must watch each video for the specified duration to have it counted as "viewed" in their progress. This prevents students from just clicking play without actually watching the content.
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <div class="d-flex flex-column gap-2">
                                    <span class="badge bg-info">
                                        <i class="bi bi-clock me-1"></i>Default: 5 minutes
                                    </span>
                                    <span class="badge bg-warning">
                                        <i class="bi bi-sliders me-1"></i>Range: 1-30 minutes
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


     <!-- Videos Grid with Header Background -->
     <div class="row">
         <div class="col-12">
             <div class="card videos-grid-card border-0 shadow-sm" style="background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);">
                 <div class="card-header videos-grid-header" style="background: transparent; border: none;">
                     <div class="d-flex justify-content-between align-items-center">
                         <h5 class="mb-0 text-white">
                             <i class="bi bi-camera-video me-2"></i>Videos
                             <span class="badge bg-light text-dark ms-2"><?php echo count($videos); ?></span>
                         </h5>
                         <div class="text-end">
                             <?php
                             $total_min_time = 0;
                             foreach ($videos as $video) {
                                 $total_min_time += $video['min_watch_time'] ?? 30;
                             }
                             $avg_min_time = count($videos) > 0 ? round($total_min_time / count($videos)) : 0;
                             ?>
                             <small class="text-white opacity-90">
                                 <i class="bi bi-clock me-1"></i>Avg min time: <?php echo $avg_min_time; ?>s
                             </small>
                         </div>
                     </div>
                 </div>
                 <div class="card-body" style="background: white; border-radius: 0 0 12px 12px;">
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
                         <div class="empty-state text-center py-5">
                             <div class="empty-state-content">
                                 <i class="bi bi-camera-video-off display-1 text-muted mb-4"></i>
                                 <h4 class="text-muted mb-3">No Videos Found</h4>
                                 <p class="text-muted mb-4">Add your first video link for this module to start creating engaging content.</p>
                                 <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#uploadVideoModal">
                                     <i class="bi bi-plus-circle me-2"></i>Add First Video Link
                                 </button>
                             </div>
                         </div>
                     <?php else: ?>
                         <!-- Videos Grid with Scrollable Container -->
                         <div class="videos-grid-scrollable-container">
                             <div class="row">
                                 <?php foreach ($videos as $index => $video): ?>
                                     <div class="col-md-6 col-lg-4 mb-4">
                                         <div class="card video-card h-100 border-0 shadow-sm">
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
                                                 <!-- Video Header -->
                                                 <div class="d-flex justify-content-between align-items-start mb-3">
                                                     <div class="flex-grow-1">
                                                         <h5 class="card-title mb-1 text-dark fw-bold"><?php echo htmlspecialchars($video['video_title']); ?></h5>
                                                         <div class="d-flex align-items-center gap-2">
                                                             <span class="badge bg-light text-dark">#<?php echo $index + 1; ?></span>
                                                             <?php 
                                                             if (!empty($video_url)) {
                                                                 if (preg_match('/youtu\.be|youtube\.com/', $video_url)) {
                                                                     echo '<span class="badge bg-danger"><i class="bi bi-youtube me-1"></i>YouTube</span>';
                                                                 } elseif (preg_match('/drive\.google\.com/', $video_url)) {
                                                                     echo '<span class="badge bg-primary"><i class="bi bi-google-drive me-1"></i>Google Drive</span>';
                                                                 } else {
                                                                     echo '<span class="badge bg-info"><i class="bi bi-link-45deg me-1"></i>External</span>';
                                                                 }
                                                             } elseif (!empty($video_file)) {
                                                                 echo '<span class="badge bg-success"><i class="bi bi-file-play me-1"></i>Uploaded</span>';
                                                             } else {
                                                                 echo '<span class="badge bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>No Link</span>';
                                                             }
                                                             ?>
                                                         </div>
                                                     </div>
                                                 </div>
                                                 
                                                 <!-- Video Description -->
                                                 <?php if (!empty($video['video_description'])): ?>
                                                     <div class="mb-3">
                                                         <p class="card-text text-muted small mb-0" style="line-height: 1.4;">
                                                             <?php echo htmlspecialchars($video['video_description']); ?>
                                                         </p>
                                                     </div>
                                                 <?php endif; ?>
                                                 
                                                 <!-- Statistics Row -->
                                                 <div class="row g-2 mb-3">
                                                     <div class="col-4">
                                                         <div class="text-center p-2 bg-light rounded">
                                                             <div class="fw-bold text-primary fs-5" style="color: #1976d2 !important;"><?php echo number_format($video['view_count'] ?? 0); ?></div>
                                                             <small class="text-muted d-block">Views</small>
                                                         </div>
                                                     </div>
                                                     <div class="col-4">
                                                         <div class="text-center p-2 bg-light rounded">
                                                             <div class="fw-bold text-info fs-5" style="color: #00bcd4 !important;"><?php echo number_format($video['unique_viewers'] ?? 0); ?></div>
                                                             <small class="text-muted d-block">Unique</small>
                                                         </div>
                                                     </div>
                                                     <div class="col-4">
                                                         <div class="text-center p-2 bg-light rounded">
                                                             <div class="fw-bold text-warning fs-5" style="color: #ff9800 !important;"><?php echo $video['min_watch_time'] ?? 30; ?>s</div>
                                                             <small class="text-muted d-block">Min Watch</small>
                                                         </div>
                                                     </div>
                                                 </div>
                                                 
                                                 <!-- Video URL Preview -->
                                                 <?php if (!empty($video_url)): ?>
                                                     <div class="mb-3">
                                                         <div class="d-flex align-items-center">
                                                             <i class="bi bi-link-45deg text-muted me-2"></i>
                                                             <small class="text-muted text-truncate" title="<?php echo htmlspecialchars($video_url); ?>">
                                                                 <?php echo htmlspecialchars($video_url); ?>
                                                             </small>
                                                         </div>
                                                     </div>
                                                 <?php endif; ?>
                                                 
                                                 <!-- Action Buttons -->
                                                 <div class="d-grid gap-2">
                                                     <div class="btn-group btn-group-sm" role="group">
                                                         <a href="video_details.php?id=<?php echo $video['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                             <i class="bi bi-eye me-1"></i>Details
                                                         </a>
                                                         <button class="btn btn-outline-secondary" onclick="editVideo(<?php echo htmlspecialchars(json_encode($video)); ?>)" title="Edit Video">
                                                             <i class="bi bi-pencil me-1"></i>Edit
                                                         </button>
                                                         <button class="btn btn-outline-info" onclick="viewVideoStats('<?php echo $video['id']; ?>')" title="View Statistics">
                                                             <i class="bi bi-graph-up me-1"></i>Stats
                                                         </button>
                                                     </div>
                                                     <button class="btn btn-outline-danger btn-sm delete-confirm" onclick="deleteVideo('<?php echo $video['id']; ?>', '<?php echo htmlspecialchars($video['video_title']); ?>')" title="Delete Video">
                                                         <i class="bi bi-trash me-1"></i>Delete Video
                                                     </button>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 <?php endforeach; ?>
                             </div>
                         </div>
                     <?php endif; ?>
                     
                     <!-- Floating Add Video Button - Inside Videos Grid -->
                     <div class="floating-add-video-btn-inside">
                         <button class="btn btn-primary btn-floating" data-bs-toggle="modal" data-bs-target="#uploadVideoModal" title="Add Video">
                             <i class="bi bi-plus"></i>
                         </button>
                     </div>
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

<style>
/* Modern Module Videos Styles - Matching Design System */

/* Module Videos Header */
.module-videos-header {
    background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    position: relative;
}

.module-videos-header h1 {
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.module-videos-header .opacity-90 {
    opacity: 0.9;
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
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
    color: white !important;
    background: rgba(255, 255, 255, 0.1) !important;
    transition: all 0.3s ease !important;
}

.btn-back-icon:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    color: white !important;
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

/* Module Info Card */
.module-info-card {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.module-info-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    border-radius: 12px 12px 0 0;
}

/* Statistics Cards */
.stats-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stats-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
}

.stats-success {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
}

.stats-warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #212529;
}

.stats-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
}

.stats-number {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stats-label {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.stats-subtitle {
    font-size: 0.875rem;
    opacity: 0.8;
}

.stats-icon {
    font-size: 3rem;
    opacity: 0.3;
}

/* Info Card */
.info-card {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.info-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    border-radius: 12px 12px 0 0;
}

/* Videos Grid */
.videos-grid-card {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

.videos-grid-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 1.25rem 1.5rem;
}

/* Videos Grid Scrollable Container */
.videos-grid-scrollable-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: hidden;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    background: white;
    border: 1px solid #e9ecef;
    padding: 1rem;
}

.videos-grid-scrollable-container::-webkit-scrollbar {
    width: 8px;
}

.videos-grid-scrollable-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.videos-grid-scrollable-container::-webkit-scrollbar-thumb {
    background: #2E5E4E;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.videos-grid-scrollable-container::-webkit-scrollbar-thumb:hover {
    background: #1e3d32;
}

.videos-grid-scrollable-container {
    scrollbar-width: thin;
    scrollbar-color: #2E5E4E #f1f1f1;
}

/* Badge enhancements - matching students table */
.videos-table-scrollable-container .badge {
    font-size: 0.75rem;
    padding: 6px 10px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.videos-table-scrollable-container .badge:hover {
    transform: scale(1.05);
}

/* Enhanced button styling - matching students table */
.videos-table-scrollable-container .btn {
    padding: 6px 12px;
    font-size: 0.875rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    margin: 0 2px;
}

.videos-table-scrollable-container .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Enhanced Video Cards with Vibrant Colors */
.video-card {
    border-radius: 16px;
    transition: all 0.3s ease;
    overflow: hidden;
    border: 1px solid #e3f2fd;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
}

.video-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px rgba(33, 150, 243, 0.25);
    border-color: #2196f3;
}

.video-card-preview {
    position: relative;
    height: 200px;
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 16px 16px 0 0;
}

.video-card-preview iframe,
.video-card-preview video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border: none;
}

.video-card-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.video-card-preview .play-button {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
    color: white;
    border: none;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
}

.video-card-preview .play-button:hover {
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    transform: translate(-50%, -50%) scale(1.1);
    box-shadow: 0 6px 20px rgba(33, 150, 243, 0.6);
}

/* Card Body Enhancements with Blue Theme */
.video-card .card-body {
    padding: 1.5rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
}

.video-card .card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1565c0;
    line-height: 1.3;
}

.video-card .badge {
    font-size: 0.7rem;
    padding: 0.4rem 0.6rem;
    border-radius: 8px;
    font-weight: 600;
}

/* Statistics Boxes with Blue Theme */
.video-card .bg-light {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
    border: 1px solid #90caf9;
    transition: all 0.3s ease;
}

.video-card:hover .bg-light {
    background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%) !important;
    transform: scale(1.02);
    border-color: #2196f3;
}

/* Button Enhancements with Vibrant Colors */
.video-card .btn-group .btn {
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.video-card .btn-outline-primary:hover {
    background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
    border-color: #2196f3;
}

.video-card .btn-outline-secondary:hover {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
    border-color: #ff9800;
    color: white;
}

.video-card .btn-outline-info:hover {
    background: linear-gradient(135deg, #00bcd4 0%, #0097a7 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 188, 212, 0.4);
    border-color: #00bcd4;
}

.video-card .btn-outline-danger:hover {
    background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
    border-color: #f44336;
}

/* Empty State */
.empty-state {
    padding: 3rem 2rem;
    text-align: center;
}

.empty-state-content {
    max-width: 400px;
    margin: 0 auto;
}

.empty-state i {
    color: #6c757d;
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: #495057;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 2rem;
}

/* Floating Add Video Button - Inside Videos Grid */
.floating-add-video-btn-inside {
    position: absolute;
    bottom: 20px;
    right: 20px;
    z-index: 10;
}

.videos-grid-card {
    position: relative;
}

.btn-floating {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
    transition: all 0.3s ease;
    border: none;
    padding: 0;
    background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
}

.btn-floating:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(33, 150, 243, 0.6);
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
}

.btn-floating:active {
    transform: translateY(0);
}

.btn-floating i {
    margin: 0;
}

/* Responsive Design - Videos Grid */
@media (max-width: 768px) {
    .video-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .module-videos-header {
        padding: 1.5rem 0;
    }
    
    .videos-grid-scrollable-container {
        max-height: 500px;
        padding: 0.75rem;
    }
    
    .video-card-preview {
        height: 150px;
    }
    
    .floating-add-video-btn-inside {
        bottom: 15px;
        right: 15px;
    }
    
    .btn-floating {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
    }
}

@media (max-width: 576px) {
    .stats-card .card-body {
        padding: 1rem;
    }
    
    .stats-number {
        font-size: 2rem;
    }
    
    .stats-icon {
        font-size: 2rem;
    }
    
    .videos-grid-scrollable-container {
        max-height: 400px;
        padding: 0.5rem;
    }
    
    .video-card-preview {
        height: 120px;
    }
    
    .video-card .card-body {
        padding: 1rem;
    }
    
    .video-card .card-title {
        font-size: 1rem;
    }
    
    .video-card .btn-group .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .floating-add-video-btn-inside {
        bottom: 10px;
        right: 10px;
    }
    
    .btn-floating {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?> 