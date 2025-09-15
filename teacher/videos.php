<?php
// Start output buffering to prevent header issues
ob_start();

$page_title = 'Videos';
require_once '../config/config.php';
requireRole('teacher');
require_once '../includes/header.php';

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
            $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
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
                            $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
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
                    $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
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
        $modules_json = json_decode($course['modules'] ?? '', true) ?: [];
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
    
                            $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
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

    <style>
    /* Light Theme Only */
    :root {
        --primary-color: #28a745;
        --card-bg: #fff;
        --text-primary: #2c3e50;
        --text-secondary: #6c757d;
        --text-muted: #6c757d;
        --border-color: #dee2e6;
        --shadow: 0 4px 20px rgba(0,0,0,0.08);
        --hover-shadow: 0 12px 40px rgba(0,0,0,0.15);
        --bg-light: #f8f9fa;
        --bg-secondary: #e9ecef;
    }

    /* Modern Video Management Styling */
    .video-management-header {
        background: var(--primary-color);
        color: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
        border-radius: 15px;
        position: relative;
        overflow: hidden;
    }
    
    .video-management-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        opacity: 0.3;
    }
    
    .video-management-header .container {
        position: relative;
        z-index: 2;
    }
    
    .video-stats {
        display: flex;
        gap: 2rem;
        margin-top: 1rem;
    }
    
    .video-stat-item {
        text-align: center;
    }
    
    .video-stat-number {
        font-size: 2rem;
        font-weight: 700;
        display: block;
    }
    
    .video-stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    /* Enhanced Filter Section */
    .filter-card {
        background: var(--card-bg);
        border-radius: 15px;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .filter-card .card-header {
        background: var(--bg-light);
        border-bottom: 1px solid var(--border-color);
        padding: 1.5rem;
    }
    
    .filter-card .card-body {
        padding: 2rem;
        background: var(--card-bg);
    }
    
    .filter-section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }
    
    .filter-section-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    /* Modern Video Cards */
    .video-card {
        background: var(--card-bg);
        border-radius: 15px;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
    }
    
    .video-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--hover-shadow);
    }
    
    .video-card-preview {
        position: relative;
        overflow: hidden;
        border-radius: 15px 15px 0 0;
    }
    
    .video-card-preview iframe,
    .video-card-preview video {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border: none;
        transition: transform 0.3s ease;
    }
    
    .video-card:hover .video-card-preview iframe,
    .video-card:hover .video-card-preview video {
        transform: scale(1.05);
    }
    
    .video-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(0,0,0,0.1), transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .video-card:hover .video-overlay {
        opacity: 1;
    }
    
    .video-play-button {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255,255,255,0.9);
        border: none;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #28a745;
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .video-card:hover .video-play-button {
        opacity: 1;
    }
    
    .video-card-body {
        padding: 1.5rem;
    }
    
    .video-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .video-description {
        color: var(--text-secondary);
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .video-meta {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .video-meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    
    .video-meta-item i {
        width: 16px;
        color: #28a745;
    }
    
    .video-stats-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding: 0.75rem;
        background: var(--bg-light);
        border-radius: 10px;
        border: 1px solid var(--border-color);
    }
    
    .video-views {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    
    .video-views i {
        color: #28a745;
    }
    
    .video-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .video-action-btn {
        flex: 1;
        min-width: 0;
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }
    
    .video-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .video-action-btn.btn-outline-primary:hover {
        background: #0d6efd;
        color: white;
        border-color: #0d6efd;
    }
    
    .video-action-btn.btn-outline-secondary:hover {
        background: #6c757d;
        color: white;
        border-color: #6c757d;
    }
    
    .video-action-btn.btn-outline-info:hover {
        background: #0dcaf0;
        color: white;
        border-color: #0dcaf0;
    }
    
    .video-action-btn.btn-outline-danger:hover {
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--bg-light);
        border-radius: 15px;
        margin: 2rem 0;
        border: 1px solid var(--border-color);
    }
    
    .empty-state-icon {
        font-size: 4rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }
    
    .empty-state-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
    }
    
    .empty-state-description {
        color: var(--text-secondary);
        margin-bottom: 2rem;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .empty-state-action {
        background: #28a745;
        border: none;
        color: white;
        padding: 0.75rem 2rem;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .empty-state-action:hover {
        background: #218838;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        color: white;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .video-stats {
            flex-direction: column;
            gap: 1rem;
        }
        
        .video-stat-number {
            font-size: 1.5rem;
        }
        
        .video-actions {
            flex-direction: column;
        }
        
        .video-action-btn {
            flex: none;
        }
        
        .filter-card .card-body {
            padding: 1.5rem;
        }
    }
    
    /* Loading Animation */
    .loading-shimmer {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
    }
    
    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    
    /* Custom Scrollbar */
    .video-grid::-webkit-scrollbar {
        width: 8px;
    }
    
    .video-grid::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .video-grid::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }
    
    .video-grid::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }


    /* Form elements styling */
    .form-select,
    .form-control {
        background-color: var(--card-bg);
        border-color: var(--border-color);
        color: var(--text-primary);
    }

    .form-select:focus,
    .form-control:focus {
        background-color: var(--card-bg);
        border-color: #28a745;
        color: var(--text-primary);
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }

    .form-label {
        color: var(--text-primary);
    }

    /* Alert styling */
    .alert {
        background-color: var(--bg-light);
        border-color: var(--border-color);
        color: var(--text-primary);
    }

    .alert-warning {
        background-color: rgba(255, 193, 7, 0.1);
        border-color: rgba(255, 193, 7, 0.3);
        color: var(--text-primary);
    }

    /* Badge styling */
    .badge {
        background-color: #28a745 !important;
    }

    /* Button styling */
    .btn-outline-secondary {
        color: var(--text-secondary);
        border-color: var(--border-color);
    }

    .btn-outline-secondary:hover {
        background-color: var(--text-secondary);
        border-color: var(--text-secondary);
        color: var(--card-bg);
    }
    </style>

    <!-- Video Management Header -->
    <div class="video-management-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-3">
                        <i class="bi bi-camera-video me-3"></i>Video Management
                    </h1>
                    <p class="mb-0 opacity-90">Manage and organize your educational videos across all courses and modules.</p>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="video-stats">
                            <div class="video-stat-item">
                                <span class="video-stat-number"><?php echo count($videos); ?></span>
                                <span class="video-stat-label">Total Videos</span>
                            </div>
                            <div class="video-stat-item">
                                <span class="video-stat-number"><?php echo count($courses); ?></span>
                                <span class="video-stat-label">Courses</span>
                            </div>
                            <div class="video-stat-item">
                                <span class="video-stat-number"><?php echo count($modules); ?></span>
                                <span class="video-stat-label">Modules</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card filter-card">
                <div class="card-header">
                    <h5 class="filter-section-title mb-0">
                        <i class="bi bi-funnel me-2"></i>Filter Videos
                    </h5>
                    <p class="filter-section-subtitle mb-0">Narrow down your video collection by course and module</p>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-5">
                            <label for="course" class="form-label fw-semibold">
                                <i class="bi bi-book me-1"></i>Filter by Course
                            </label>
                            <select class="form-select form-select-lg" id="course" name="course" onchange="loadModules(this.value)">
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
                            <label for="module" class="form-label fw-semibold">
                                <i class="bi bi-folder me-1"></i>Filter by Module
                            </label>
                            <select class="form-select form-select-lg" id="module" name="module" onchange="this.form.submit()">
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
                            <label class="form-label fw-semibold">&nbsp;</label>
                            <div class="d-grid">
                                <a href="videos.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Clear
                                </a>
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
            <div class="card filter-card">
                <div class="card-header">
                    <h5 class="filter-section-title mb-0">
                        <i class="bi bi-collection-play me-2"></i>Video Collection
                        <span class="badge bg-primary ms-2"><?php echo count($videos); ?></span>
                    </h5>
                    <p class="filter-section-subtitle mb-0">Manage and organize your educational video content</p>
                </div>
                <div class="card-body">
                    <?php if (empty($videos)): ?>
                        <div class="empty-state">
                            <i class="bi bi-camera-video-off empty-state-icon"></i>
                            <h5 class="empty-state-title">No Videos Found</h5>
                            <p class="empty-state-description">
                                Upload your first video to start creating engaging content for your students. 
                                Videos help make learning more interactive and effective.
                            </p>
                            <?php if ($is_acad_period_active): ?>
                                <button class="btn empty-state-action" data-bs-toggle="modal" data-bs-target="#uploadVideoModal">
                                    <i class="bi bi-upload me-2"></i>Upload First Video
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="row video-grid">
                            <?php foreach ($videos as $video): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card video-card h-100">
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
                                                        echo '<iframe src="https://www.youtube.com/embed/' . htmlspecialchars($youtube_id) . '" frameborder="0" allowfullscreen></iframe>';
                                                    } else {
                                                        echo '<div class="text-danger p-3">Invalid YouTube link</div>';
                                                    }
                                                } elseif (preg_match('/drive\.google\.com/', $video_url)) {
                                                    // Google Drive
                                                    if (preg_match('~/d/([a-zA-Z0-9_-]+)~', $video_url, $matches)) {
                                                        $drive_id = $matches[1];
                                                        echo '<iframe src="https://drive.google.com/file/d/' . htmlspecialchars($drive_id) . '/preview" allowfullscreen></iframe>';
                                                    } else {
                                                        echo '<div class="text-danger p-3">Invalid Google Drive link</div>';
                                                    }
                                                } elseif (preg_match('/\.mp4$|\.webm$|\.ogg$/', $video_url)) {
                                                    // Direct video file
                                                    echo '<video controls><source src="' . htmlspecialchars($video_url) . '">Your browser does not support the video tag.</video>';
                                                } else {
                                                    // Generic iframe
                                                    echo '<iframe src="' . htmlspecialchars($video_url) . '" allowfullscreen></iframe>';
                                                }
                                            } elseif (!empty($video_file)) {
                                                $ext = strtolower(pathinfo($video_file, PATHINFO_EXTENSION));
                                                $mime = 'video/mp4';
                                                if ($ext === 'webm') $mime = 'video/webm';
                                                elseif ($ext === 'ogg' || $ext === 'ogv') $mime = 'video/ogg';
                                                echo '<video controls><source src="/lms_cap/uploads/videos/' . htmlspecialchars($video_file) . '" type="' . $mime . '">Your browser does not support the video tag.</video>';
                                            } else {
                                                // Placeholder for videos without preview
                                                echo '<div class="d-flex align-items-center justify-content-center" style="height: 200px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                                        <i class="bi bi-camera-video fs-1 text-muted"></i>
                                                      </div>';
                                            }
                                            ?>
                                            <div class="video-overlay"></div>
                                            <button class="video-play-button" onclick="openVideoModal('<?php echo htmlspecialchars($video_url ?: '/lms_cap/uploads/videos/' . $video_file); ?>', '<?php echo htmlspecialchars($video['video_title']); ?>', <?php echo !empty($video_url) ? 'true' : 'false'; ?>)">
                                                <i class="bi bi-play-fill"></i>
                                            </button>
                                        </div>
                                        <div class="video-card-body">
                                            <h6 class="video-title"><?php echo htmlspecialchars($video['video_title']); ?></h6>
                                            <?php if ($video['video_description']): ?>
                                                <p class="video-description"><?php echo htmlspecialchars(substr($video['video_description'], 0, 100)) . '...'; ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="video-meta">
                                                <div class="video-meta-item">
                                                    <i class="bi bi-folder"></i>
                                                    <span><?php echo htmlspecialchars($video['module_title']); ?></span>
                                            </div>
                                                <div class="video-meta-item">
                                                    <i class="bi bi-collection"></i>
                                                    <span><?php echo htmlspecialchars($video['course_name']); ?></span>
                                            </div>
                                            </div>
                                            
                                            <div class="video-stats-row">
                                                <div class="video-views">
                                                    <i class="bi bi-eye"></i>
                                                    <span><?php echo $video['view_count']; ?> views</span>
                                                </div>
                                                <div class="text-muted small">
                                                    Order: <?php echo $video['video_order']; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="video-actions">
                                                <a href="video_details.php?id=<?php echo $video['id']; ?>" class="btn video-action-btn btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                                <?php if ($is_acad_period_active): ?>
                                                    <button class="btn video-action-btn btn-outline-secondary" onclick="editVideo(<?php echo htmlspecialchars(json_encode($video)); ?>)">
                                                        <i class="bi bi-pencil me-1"></i>Edit
                                                    </button>
                                                    <button class="btn video-action-btn btn-outline-info" onclick="viewVideoStats('<?php echo $video['id']; ?>')">
                                                        <i class="bi bi-graph-up me-1"></i>Stats
                                                    </button>
                                                    <a href="?action=delete_video&video_id=<?php echo urlencode($video['id']); ?>&confirm=1" 
                                                       class="btn video-action-btn btn-outline-danger" 
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