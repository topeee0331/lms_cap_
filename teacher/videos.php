<?php
// Start output buffering to prevent header issues
ob_start();

$page_title = 'Videos';
require_once '../includes/header.php';
requireRole('teacher');

$message = '';
$message_type = '';

// Check if redirected from successful deletion
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $message = 'Video deleted successfully.';
    $message_type = 'success';
}

// Handle GET request deletion (alternative method)
if (isset($_GET['action']) && $_GET['action'] === 'delete_video' && isset($_GET['video_id']) && isset($_GET['confirm'])) {
    $video_id = sanitizeInput($_GET['video_id']);
    
    error_log("GET Delete video attempt - Video ID: '$video_id'");
    
    if (!empty($video_id)) {
        // Find and remove the video from the JSON structure
        $stmt = $db->prepare('SELECT c.id, c.modules FROM courses c WHERE c.teacher_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $courses_data = $stmt->fetchAll();
        
        $video_deleted = false;
        
        foreach ($courses_data as $course) {
            $modules_data = json_decode($course['modules'], true);
            if (is_array($modules_data)) {
                foreach ($modules_data as &$module) {
                    if (isset($module['videos']) && is_array($module['videos'])) {
                        foreach ($module['videos'] as $index => $video) {
                            if (isset($video['id']) && $video['id'] === $video_id) {
                                // Delete file if it exists and is not a URL
                                if (isset($video['video_file']) && !empty($video['video_file']) && !filter_var($video['video_file'], FILTER_VALIDATE_URL)) {
                                    $filepath = dirname(__DIR__) . '/uploads/videos/' . $video['video_file'];
                                    if (file_exists($filepath)) {
                                        unlink($filepath);
                                    }
                                }
                                
                                // Remove video from array
                                array_splice($module['videos'], $index, 1);
                                $video_deleted = true;
                                break 2;
                            }
                        }
                    }
                }
                
                if ($video_deleted) {
                    // Update course with updated modules JSON
                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                    $stmt->execute([json_encode($modules_data), $course['id']]);
                    break;
                }
            }
        }
        
        if ($video_deleted) {
            // Clear output buffer and redirect
            ob_clean();
            header("Location: videos.php?deleted=1");
            exit();
        } else {
            $message = 'Video not found or could not be deleted.';
            $message_type = 'danger';
        }
    }
}

    // Handle video actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
        
        
        if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'upload_video_disabled':
                $message = 'Video upload functionality has been disabled.';
                $message_type = 'warning';
                break;
                
            case 'update_video':
                $video_id = (int)($_POST['video_id'] ?? 0);
                $video_title = sanitizeInput($_POST['video_title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                $video_order = (int)($_POST['video_order'] ?? 1);
                $source_type = $_POST['edit_video_source_type'] ?? 'keep';
                $video_url = sanitizeInput($_POST['video_url'] ?? '');
                
                if (empty($video_title) || empty($module_id) || empty($video_id)) {
                    $message = 'Video ID, title, and module are required.';
                    $message_type = 'danger';
                } else {
                    $update_file = false;
                    $filename = '';

                    if ($source_type === 'url') {
                        if (empty($video_url)) {
                            $message = 'Video URL is required if source is URL.';
                            $message_type = 'danger';
                        } else {
                            $filename = ''; // Don't save URL as filename
                            $update_file = true;
                        }
                    } elseif ($source_type === 'file') {
                        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                            // (File upload handling logic from the upload case)
                            $file = $_FILES['video_file'];
                            $upload_dir = dirname(__DIR__) . '/uploads/videos/';
                            $unique_filename = uniqid() . '_' . sanitizeFilename($file['name']);
                            $filepath = $upload_dir . $unique_filename;
                            
                            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                                $filename = $unique_filename;
                                $update_file = true;
                            } else {
                                $message = 'Failed to upload new video file.';
                                $message_type = 'danger';
                            }
                        } else {
                            $message = 'Please select a new file to upload.';
                            $message_type = 'danger';
                        }
                    }

                    if (empty($message)) {
                        // Find and update the video in the JSON structure
                        $stmt = $db->prepare('SELECT c.id, c.modules FROM courses c WHERE c.teacher_id = ?');
                        $stmt->execute([$_SESSION['user_id']]);
                        $courses_data = $stmt->fetchAll();
                        
                        $video_updated = false;
                        foreach ($courses_data as $course) {
                            $modules_data = json_decode($course['modules'], true);
                            if (is_array($modules_data)) {
                                foreach ($modules_data as &$module) {
                                    if (isset($module['videos']) && is_array($module['videos'])) {
                                        foreach ($module['videos'] as &$video) {
                                            if ($video['id'] === $video_id) {
                                                $video['video_title'] = $video_title;
                                                $video['video_description'] = $description;
                                                $video['video_order'] = $video_order;
                                                if ($update_file) {
                                                    if ($source_type === 'url') {
                                                        $video['video_url'] = $video_url;
                                                        unset($video['video_file']); // Remove old file reference
                                                    } else {
                                                        $video['video_file'] = $filename;
                                                        unset($video['video_url']); // Remove old URL reference
                                                    }
                                                }
                                                $video['updated_at'] = date('Y-m-d H:i:s');
                                                $video_updated = true;
                                                break 2;
                                            }
                                        }
                                    }
                                }
                                
                                if ($video_updated) {
                                    // Update course with updated modules JSON
                                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                                    $stmt->execute([json_encode($modules_data), $course['id']]);
                                    break;
                                }
                            }
                        }
                        
                        if ($video_updated) {
                            $message = 'Video updated successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Video not found.';
                            $message_type = 'danger';
                        }
                    }
                }
                break;
                
            case 'delete_video':
                $video_id = sanitizeInput($_POST['video_id'] ?? '');
                
                // Debug logging
                error_log("Delete video attempt - Video ID: '$video_id'");
                error_log("POST data: " . print_r($_POST, true));
                
                if (empty($video_id)) {
                    $message = 'Video ID is required.';
                    $message_type = 'danger';
                    error_log("Video ID is empty!");
                    break;
                }
                
                // Find and remove the video from the JSON structure
                $stmt = $db->prepare('SELECT c.id, c.modules FROM courses c WHERE c.teacher_id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $courses_data = $stmt->fetchAll();
                
                $video_deleted = false;
                $deleted_video_info = null;
                
                foreach ($courses_data as $course) {
                    error_log("Checking course: " . $course['course_name']);
                    $modules_data = json_decode($course['modules'], true);
                    if (is_array($modules_data)) {
                        foreach ($modules_data as &$module) {
                            error_log("Checking module: " . ($module['module_title'] ?? 'Unknown'));
                            if (isset($module['videos']) && is_array($module['videos'])) {
                                error_log("Module has " . count($module['videos']) . " videos");
                                // Find and remove the video
                                foreach ($module['videos'] as $index => $video) {
                                    error_log("Checking video ID: " . ($video['id'] ?? 'NO ID') . " against target: $video_id");
                                    if (isset($video['id']) && $video['id'] === $video_id) {
                                        // Store video info for file deletion
                                        $deleted_video_info = $video;
                                        
                                        // Delete file if it exists and is not a URL
                                        if (isset($video['video_file']) && !empty($video['video_file']) && !filter_var($video['video_file'], FILTER_VALIDATE_URL)) {
                                            $filepath = dirname(__DIR__) . '/uploads/videos/' . $video['video_file'];
                                            if (file_exists($filepath)) {
                                                unlink($filepath);
                                                error_log("Deleted file: $filepath");
                                            }
                                        }
                                        
                                        // Remove video from array
                                        array_splice($module['videos'], $index, 1);
                                        $video_deleted = true;
                                        error_log("Video removed from module: " . $module['module_title']);
                                        break 2;
                                    }
                                }
                            }
                        }
                        
                        if ($video_deleted) {
                            // Update course with updated modules JSON
                            $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                            $stmt->execute([json_encode($modules_data), $course['id']]);
                            error_log("Updated course modules for course ID: " . $course['id']);
                            break;
                        }
                    }
                }
                
                if ($video_deleted) {
                    error_log("Video deletion successful for ID: $video_id");
                    
                    // Clear output buffer and redirect
                    ob_clean();
                    header("Location: videos.php?deleted=1");
                    exit();
                } else {
                    $message = 'Video not found or could not be deleted.';
                    $message_type = 'danger';
                    error_log("Video deletion failed for ID: $video_id");
                }
                break;
        }
    }
}

// Get filters
$course_filter = (int)($_GET['course'] ?? $_GET['course_id'] ?? 0);
$module_filter = (int)($_GET['module'] ?? $_GET['module_id'] ?? 0);

// Get teacher's courses
$stmt = $db->prepare('SELECT id, course_name, course_code, academic_period_id FROM courses WHERE teacher_id = ? ORDER BY course_name');
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

// Check if selected academic period is active
$selected_period_id = $_GET['academic_period_id'] ?? null;
if (!$selected_period_id && !empty($courses)) {
    $selected_period_id = $courses[0]['academic_period_id'] ?? null;
}
$is_acad_period_active = true;
if ($selected_period_id) {
    $period_stmt = $db->prepare('SELECT is_active FROM academic_periods WHERE id = ?');
    $period_stmt->execute([$selected_period_id]);
    $period_row = $period_stmt->fetch();
    $is_acad_period_active = $period_row ? (bool)$period_row['is_active'] : true;
}

// Get modules for selected course (from JSON)
$modules = [];
if ($course_filter > 0) {
    $stmt = $db->prepare('SELECT modules FROM courses WHERE id = ?');
    $stmt->execute([$course_filter]);
    $course = $stmt->fetch();
    if ($course && $course['modules']) {
        $modules_json = json_decode($course['modules'], true);
        if (is_array($modules_json)) {
            foreach ($modules_json as $module) {
                $modules[] = [
                    'id' => $module['id'],
                    'module_title' => $module['module_title'] ?? $module['title'] ?? 'Module'
                ];
            }
        }
    }
}

// Get videos with filters
$where_conditions = ["c.teacher_id = ?"];
$params = [$_SESSION['user_id']];

if ($course_filter > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

if ($module_filter > 0) {
    // Note: Module filtering is now handled in the frontend since modules are JSON
    // This is kept for backward compatibility but doesn't affect the query
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get videos from JSON modules instead of course_videos table
$videos = [];
$stmt = $db->prepare("
    SELECT c.id, c.course_name, c.course_code, c.modules
    FROM courses c
    WHERE c.teacher_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$courses_data = $stmt->fetchAll();

foreach ($courses_data as $course) {
    if ($course_filter > 0 && $course['id'] != $course_filter) {
        continue; // Skip if course filter is set and doesn't match
    }
    
    $modules_data = json_decode($course['modules'], true);
    if (is_array($modules_data)) {
        foreach ($modules_data as $module) {
            if ($module_filter > 0 && $module['id'] != $module_filter) {
                continue; // Skip if module filter is set and doesn't match
            }
            
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
                    $videos[] = $video;
                }
            }
        }
    }
}

// Sort videos by order
usort($videos, function($a, $b) {
    return ($a['video_order'] ?? 0) - ($b['video_order'] ?? 0);
});

// Debug logging for video count
error_log("Total videos found: " . count($videos));
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Videos</h1>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    

    <?php if (!$is_acad_period_active): ?>
        <div class="alert alert-warning mb-4">
            <strong>Inactive Academic Period:</strong> This academic period is inactive. You can only view and review content. All editing, adding, and uploading is disabled.
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-5">
                            <label for="course" class="form-label">Filter by Course</label>
                            <select class="form-select" id="course" name="course" onchange="loadModules(this.value)">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="module" class="form-label">Filter by Module</label>
                            <select class="form-select" id="module" name="module" onchange="this.form.submit()">
                                <option value="">All Modules</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module['id']; ?>" 
                                            <?php echo $module_filter == $module['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($module['module_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="videos.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Videos Grid -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Videos (<?php echo count($videos); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($videos)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-camera-video-off fs-1 text-muted mb-3"></i>
                            <h6>No Videos Found</h6>
                            <p class="text-muted">Upload your first video to start creating engaging content for your students.</p>
                            <?php if ($is_acad_period_active): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadVideoModal">
                                    <i class="bi bi-upload me-1"></i>Upload First Video
                                </button>
                            <?php endif; ?>
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
                                                    <i class="bi bi-folder me-1"></i><?php echo htmlspecialchars($video['module_title']); ?><br>
                                                    <i class="bi bi-collection me-1"></i><?php echo htmlspecialchars($video['course_name']); ?>
                                                </small>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-eye me-1"></i><?php echo $video['view_count']; ?> views
                                                </small>
                                                <!-- Removed completion percentage display -->
                                            </div>
                                            <div class="btn-group btn-group-sm w-100">
                                                <a href="video_details.php?id=<?php echo $video['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i>View Details
                                                </a>
                                                <?php if ($is_acad_period_active): ?>
                                                    <button class="btn btn-outline-secondary" onclick="editVideo(<?php echo htmlspecialchars(json_encode($video)); ?>)">
                                                        <i class="bi bi-pencil me-1"></i>Edit
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="viewVideoStats('<?php echo $video['id']; ?>')">
                                                        <i class="bi bi-graph-up me-1"></i>Stats
                                                    </button>
                                                    <a href="?action=delete_video&video_id=<?php echo urlencode($video['id']); ?>&confirm=1" 
                                                       class="btn btn-outline-danger" 
                                                       onclick="return confirm('Are you sure you want to delete \'<?php echo htmlspecialchars($video['video_title']); ?>\'? This action cannot be undone.')">
                                                        <i class="bi bi-trash me-1"></i>Delete
                                                    </a>
                                                <?php endif; ?>
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


<!-- Edit Video Modal -->
<div class="modal fade" id="editVideoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
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
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_course" class="form-label">Course</label>
                            <select class="form-select" id="edit_course" name="course" required onchange="loadEditModules(this.value)">
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_module_id" class="form-label">Module</label>
                            <select class="form-select" id="edit_module_id" name="module_id" required>
                                <option value="">Select Module</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_video_order" class="form-label">Video Order</label>
                            <input type="number" class="form-control" id="edit_video_order" name="video_order" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_duration" class="form-label">Duration (seconds)</label>
                            <input type="number" class="form-control" id="edit_duration" name="duration" min="1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_video_source_type" class="form-label">Video Source</label>
                        <select class="form-select" id="edit_video_source_type" name="video_source_type">
                            <option value="file">Upload New File</option>
                            <option value="url">Use URL</option>
                            <option value="keep" selected>Keep Current File/URL</option>
                        </select>
                    </div>

                    <div id="edit_file_upload_section" style="display: none;">
                        <label for="edit_video_file" class="form-label">New Video File</label>
                        <input type="file" class="form-control" id="edit_video_file" name="video_file" accept="video/*">
                        <div class="form-text">If you upload a new file, it will replace the old one.</div>
                    </div>

                    <div id="edit_url_input_section" style="display: none;">
                        <label for="edit_video_url" class="form-label">Video URL</label>
                        <input type="text" class="form-control" id="edit_video_url" name="video_url" placeholder="Enter video URL">
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

<div class="modal fade" id="videoPlayerModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="videoPlayerModalTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" id="videoPlayerModalBody" style="background:#000;">
        <!-- Video will be injected here -->
      </div>
    </div>
  </div>
</div>
<script>
function openVideoModal(playSource, videoTitle, isUrl) {
  var modalTitle = document.getElementById('videoPlayerModalTitle');
  var modalBody = document.getElementById('videoPlayerModalBody');
  modalTitle.textContent = videoTitle;
  if (isUrl) {
    // Robust YouTube embed
    var ytId = null;
    var ytMatch = playSource.match(/(?:youtu(?:\.be|be\.com)\/(?:watch\?v=)?([\w\-]+))/i);
    if (ytMatch && ytMatch[1]) {
      ytId = ytMatch[1];
    }
    if (ytId) {
      var embedUrl = 'https://www.youtube.com/embed/' + ytId;
      modalBody.innerHTML = '<div class="ratio ratio-16x9" style="min-height:350px;"><iframe src="' + embedUrl + '" allowfullscreen frameborder="0" title="' + videoTitle + '" style="width:100%;height:100%"></iframe></div>';
    } else {
      modalBody.innerHTML = '<div class="text-center text-white p-5">Unable to embed this video link.<br>Please use a valid YouTube URL.</div>';
    }
  } else {
    // Uploaded file
    modalBody.innerHTML = '<div class="ratio ratio-16x9" style="min-height:350px;"><video controls autoplay class="w-100 h-100" style="background:#000;border-radius:8px;" allowfullscreen><source src="/lms_cap/uploads/videos/' + playSource + '" type="video/mp4">Your browser does not support the video tag.</video></div>';
  }
  var modal = new bootstrap.Modal(document.getElementById('videoPlayerModal'));
  modal.show();
}
</script>
<script>
function loadModules(courseId) {
    const moduleSelect = document.getElementById('module');
    moduleSelect.innerHTML = '<option value="">Loading modules...</option>';
    
    if (!courseId) {
        moduleSelect.innerHTML = '<option value="">All Modules</option>';
        return;
    }
    
    fetch(`get_modules.php?course_id=${courseId}`)
        .then(response => response.json())
        .then(modules => {
            moduleSelect.innerHTML = '<option value="">All Modules</option>';
            modules.forEach(module => {
                moduleSelect.innerHTML += `<option value="${module.id}">${module.module_title}</option>`;
            });
        })
        .catch(error => {
            console.error('Error loading modules:', error);
            moduleSelect.innerHTML = '<option value="">Error loading modules</option>';
        });
}


function loadEditModules(courseId) {
    const moduleSelect = document.getElementById('edit_module_id');
    moduleSelect.innerHTML = '<option value="">Loading modules...</option>';
    
    if (!courseId) {
        moduleSelect.innerHTML = '<option value="">Select Module</option>';
        return;
    }
    
    fetch(`get_modules.php?course_id=${courseId}`)
        .then(response => response.json())
        .then(modules => {
            moduleSelect.innerHTML = '<option value="">Select Module</option>';
            modules.forEach(module => {
                moduleSelect.innerHTML += `<option value="${module.id}">${module.module_title}</option>`;
            });
        })
        .catch(error => {
            console.error('Error loading modules:', error);
            moduleSelect.innerHTML = '<option value="">Error loading modules</option>';
        });
}

function editVideo(video) {
    document.getElementById('edit_video_id').value = video.id;
    document.getElementById('edit_video_title').value = video.video_title;
    document.getElementById('edit_description').value = video.video_description;
    document.getElementById('edit_video_order').value = video.video_order;
    document.getElementById('edit_duration').value = video.duration;
    
    // Set course and load modules
    const courseSelect = document.getElementById('edit_course');
    courseSelect.value = video.course_id;
    loadEditModules(video.course_id);
    
    // Set module after a short delay to allow modules to load
    setTimeout(() => {
        document.getElementById('edit_module_id').value = video.module_id;
    }, 500);
    
    // Reset and trigger the source type dropdown
    const editSourceType = document.getElementById('edit_video_source_type');
    editSourceType.value = 'keep';
    editSourceType.dispatchEvent(new Event('change'));
    
    // If the current video_file is a URL, pre-fill the URL field
    const videoUrlInput = document.getElementById('edit_video_url');
    if (video.video_file && (video.video_file.startsWith('http') || video.video_file.startsWith('www'))) {
        videoUrlInput.value = video.video_file;
    } else {
        videoUrlInput.value = '';
    }
    
    new bootstrap.Modal(document.getElementById('editVideoModal')).show();
}

function deleteVideo(videoId, videoTitle) {
    console.log('Delete video called with ID:', videoId, 'Title:', videoTitle);
    
    if (confirm(`Are you sure you want to delete "${videoTitle}"? This action cannot be undone.`)) {
        console.log('User confirmed deletion, setting video ID:', videoId);
        document.getElementById('delete_video_id').value = videoId;
        console.log('Form video ID set to:', document.getElementById('delete_video_id').value);
        document.getElementById('deleteVideoForm').submit();
    }
}

function viewVideoStats(videoId) {
    // Redirect to video statistics page
    window.location.href = `video_stats.php?id=${videoId}`;
}

// Upload functionality removed - no longer needed


// Add this script for the edit modal
document.addEventListener('DOMContentLoaded', function() {
    const editSourceType = document.getElementById('edit_video_source_type');
    if (editSourceType) {
        editSourceType.addEventListener('change', function() {
            const fileSection = document.getElementById('edit_file_upload_section');
            const urlSection = document.getElementById('edit_url_input_section');
            const fileInput = document.getElementById('edit_video_file');
            const urlInput = document.getElementById('edit_video_url');
            
            if (fileSection) fileSection.style.display = 'none';
            if (urlSection) urlSection.style.display = 'none';
            if (fileInput) fileInput.required = false;
            if (urlInput) urlInput.required = false;

            if (this.value === 'file' && fileSection && fileInput) {
                fileSection.style.display = 'block';
                fileInput.required = true;
            } else if (this.value === 'url' && urlSection && urlInput) {
                urlSection.style.display = 'block';
                urlInput.required = true;
            }
        });
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

<?php 
// Clean output buffer and send headers
ob_end_flush();
require_once '../includes/footer.php'; 
?> 