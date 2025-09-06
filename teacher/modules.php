<?php
$page_title = 'Modules';
require_once '../includes/header.php';
require_once '../config/pusher.php';
require_once '../includes/pusher_notifications.php';
requireRole('teacher');

// Helper functions for file handling
function getFileIcon($extension) {
    $icon_map = [
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'txt' => 'fas fa-file-alt',
        'rtf' => 'fas fa-file-alt',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image',
        'bmp' => 'fas fa-file-image',
        'mp4' => 'fas fa-file-video',
        'avi' => 'fas fa-file-video',
        'mov' => 'fas fa-file-video',
        'wmv' => 'fas fa-file-video',
        'mp3' => 'fas fa-file-audio',
        'wav' => 'fas fa-file-audio',
        'zip' => 'fas fa-file-archive',
        'rar' => 'fas fa-file-archive',
        '7z' => 'fas fa-file-archive',
        'tar' => 'fas fa-file-archive',
        'gz' => 'fas fa-file-archive'
    ];
    
    return $icon_map[strtolower($extension)] ?? 'fas fa-file';
}

function getFileSize($file_path) {
    $real_path = realpath($file_path);
    if (!$real_path || !file_exists($real_path)) {
        return false;
    }
    
    $size = filesize($real_path);
    if ($size === false) {
        return false;
    }
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    
    return round($size, 1) . ' ' . $units[$i];
}

function validateModuleFile($file) {
    $allowed_types = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'mp4', 'avi', 'mov', 'wmv',
        'mp3', 'wav', 'zip', 'rar', '7z', 'tar', 'gz'
    ];
    
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds 10MB limit.'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed.'];
    }
    
    return ['success' => true, 'extension' => $file_extension];
}

function uploadModuleFile($file, $module_id) {
    $upload_dir = '../uploads/modules/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $module_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return [
            'success' => true,
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_path' => $file_path,
            'file_size' => $file['size']
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file.'];
}

// Helper function to get next available module order
function getNextAvailableModuleOrder($modules) {
    if (empty($modules)) {
        return 1;
    }
    
    $orders = array_column($modules, 'module_order');
    $orders = array_filter($orders, function($order) {
        return is_numeric($order) && $order > 0;
    });
    
    return empty($orders) ? 1 : max($orders) + 1;
}

// Helper function to validate module order uniqueness
function validateModuleOrder($modules, $new_order, $exclude_id = null) {
    foreach ($modules as $module) {
        if ($exclude_id && $module['id'] === $exclude_id) {
            continue; // Skip the module being edited
        }
        if (isset($module['module_order']) && $module['module_order'] == $new_order) {
            return false; // Order already exists
        }
    }
    return true; // Order is unique
}

$message = '';
$message_type = '';

// Get all courses for this teacher
$stmt = $db->prepare('SELECT id, course_name, modules FROM courses WHERE teacher_id = ? ORDER BY course_name');
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

// Collect all modules from all courses
$all_modules = [];
$course_modules_map = [];

foreach ($courses as $course) {
    $modules_data = json_decode($course['modules'], true) ?: [];
    $course_modules_map[$course['id']] = $modules_data;
    
    foreach ($modules_data as $module) {
        $module['course_id'] = $course['id'];
        $module['course_title'] = $course['course_name'];
        $module['course_code'] = $course['course_code'] ?? '';
        // Ensure is_locked is always set
        if (!isset($module['is_locked'])) {
            $module['is_locked'] = 0;
        }
        
        // Count videos and assessments for this module
        $module['video_count'] = isset($module['videos']) ? count($module['videos']) : 0;
        $module['assessment_count'] = isset($module['assessments']) ? count($module['assessments']) : 0;
        
        $all_modules[] = $module;
    }
}

// Apply filters
$selected_course_id = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$selected_status = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

// Filter by course
if ($selected_course_id > 0) {
    $all_modules = array_filter($all_modules, function($module) use ($selected_course_id) {
        return $module['course_id'] == $selected_course_id;
    });
}

// Filter by status
if ($selected_status !== '') {
    $all_modules = array_filter($all_modules, function($module) use ($selected_status) {
        if ($selected_status === 'locked') {
            return isset($module['is_locked']) && $module['is_locked'] == 1;
        } elseif ($selected_status === 'unlocked') {
            return !isset($module['is_locked']) || $module['is_locked'] == 0;
        }
        return true;
    });
}

// Filter by search term
if (!empty($search_term)) {
    $search_term = strtolower(trim($search_term));
    $all_modules = array_filter($all_modules, function($module) use ($search_term) {
        $title = strtolower($module['module_title'] ?? '');
        $description = strtolower($module['module_description'] ?? '');
        $course_title = strtolower($module['course_title'] ?? '');
        
        return strpos($title, $search_term) !== false || 
               strpos($description, $search_term) !== false || 
               strpos($course_title, $search_term) !== false;
    });
}

// If no courses found, show appropriate message
if (empty($courses)) {
    $message = 'No courses found. Please create a course first before adding modules.';
    $message_type = 'info';
}

// Handle module actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'create_module':
                $module_title = sanitizeInput($_POST['module_title'] ?? '');
                $description = sanitizeInput($_POST['module_description'] ?? '');
                $course_id = (int)($_POST['course_id'] ?? 0);
                $module_order = (int)($_POST['module_order'] ?? 1);
                $is_locked = isset($_POST['is_locked']) ? 1 : 0;
                
                // Handle file upload
                $file_info = null;
                if (isset($_FILES['module_file']) && $_FILES['module_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file_validation = validateModuleFile($_FILES['module_file']);
                    if (!$file_validation['success']) {
                        $message = $file_validation['message'];
                        $message_type = 'danger';
                        break;
                    }
                }
                
                if (empty($module_title) || empty($course_id)) {
                    $message = 'Module title and course are required.';
                    $message_type = 'danger';
                } else {
                    // Verify course belongs to teacher
                    $stmt = $db->prepare('SELECT id, modules FROM courses WHERE id = ? AND teacher_id = ?');
                    $stmt->execute([$course_id, $_SESSION['user_id']]);
                    $course = $stmt->fetch();
                    
                    if ($course) {
                        $modules_data = json_decode($course['modules'], true) ?: [];
                        
                        // Validate module order
                        if (!validateModuleOrder($modules_data, $module_order)) {
                            $message = 'Module order already exists. Please choose a different order.';
                        $message_type = 'danger';
                        } else {
                            // Create new module
                            $module_id = 'mod_' . uniqid();
                            $new_module = [
                                'id' => $module_id,
                                'module_title' => $module_title,
                                'module_description' => $description,
                                'module_order' => $module_order,
                                'is_locked' => $is_locked,
                                'created_at' => date('Y-m-d H:i:s'),
                                'is_active' => 1
                            ];
                            
                            // Handle file upload if present
                            if (isset($_FILES['module_file']) && $_FILES['module_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                                $upload_result = uploadModuleFile($_FILES['module_file'], $module_id);
                                if ($upload_result['success']) {
                                    $new_module['file'] = [
                                        'filename' => $upload_result['filename'],
                                        'original_name' => $upload_result['original_name'],
                                        'file_size' => $upload_result['file_size'],
                                        'uploaded_at' => date('Y-m-d H:i:s')
                                    ];
                                } else {
                                    $message = $upload_result['message'];
                                    $message_type = 'danger';
                                    break;
                                }
                            }
                            
                            $modules_data[] = $new_module;
                            
                            
                            // Update course with new modules JSON
                            $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                            $stmt->execute([json_encode($modules_data), $course_id]);
                            
                            $message = 'Module created successfully.';
                            $message_type = 'success';
                            
                            // Don't redirect, just set message and continue
                            // The page will reload naturally and show updated status
                        }
                } else {
                        $message = 'Invalid course selected.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'update_module':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                $module_title = sanitizeInput($_POST['module_title'] ?? '');
                $description = sanitizeInput($_POST['module_description'] ?? '');
                $course_id = (int)($_POST['course_id'] ?? 0);
                $module_order = (int)($_POST['module_order'] ?? 1);
                $is_locked = isset($_POST['is_locked']) ? 1 : 0;
                
                if (empty($module_title) || empty($course_id)) {
                    $message = 'Module title and course are required.';
                    $message_type = 'danger';
                } else {
                    // Verify course belongs to teacher
                    $stmt = $db->prepare('SELECT id, modules FROM courses WHERE id = ? AND teacher_id = ?');
                    $stmt->execute([$course_id, $_SESSION['user_id']]);
                    $course = $stmt->fetch();
                    
                    if ($course) {
                        $modules_data = json_decode($course['modules'], true) ?: [];
                        
                        // Validate module order
                        if (!validateModuleOrder($modules_data, $module_order, $module_id)) {
                            $message = 'Module order already exists. Please choose a different order.';
                            $message_type = 'danger';
                        } else {
                            // Get the old module data to check if lock status changed
                            $old_module_data = null;
                            foreach ($modules_data as $module) {
                                if ($module['id'] === $module_id) {
                                    $old_module_data = $module;
                                    break;
                                }
                            }
                            
                            // Handle file upload if present
                            if (isset($_FILES['module_file']) && $_FILES['module_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                                $file_validation = validateModuleFile($_FILES['module_file']);
                                if (!$file_validation['success']) {
                                    $message = $file_validation['message'];
                                    $message_type = 'danger';
                                    break;
                                }
                            }
                            
                            // Update module
                            foreach ($modules_data as &$module) {
                                if ($module['id'] === $module_id) {
                                    $module['module_title'] = $module_title;
                                    $module['module_description'] = $description;
                                    $module['module_order'] = $module_order;
                                    $module['is_locked'] = $is_locked;
                                    
                                    // Handle file upload if present
                                    if (isset($_FILES['module_file']) && $_FILES['module_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                                        // Delete old file if exists
                                        if (isset($module['file']) && !empty($module['file']['filename'])) {
                                            $old_file_path = '../uploads/modules/' . $module['file']['filename'];
                                            if (file_exists($old_file_path)) {
                                                unlink($old_file_path);
                                            }
                                        }
                                        
                                        // Upload new file
                                        $upload_result = uploadModuleFile($_FILES['module_file'], $module_id);
                                        if ($upload_result['success']) {
                                            $module['file'] = [
                                                'filename' => $upload_result['filename'],
                                                'original_name' => $upload_result['original_name'],
                                                'file_size' => $upload_result['file_size'],
                                                'uploaded_at' => date('Y-m-d H:i:s')
                                            ];
                                        } else {
                                            $message = $upload_result['message'];
                                            $message_type = 'danger';
                                            break 2;
                                        }
                                    }
                                    
                                    break;
                                }
                            }
                            
                            // Update course with updated modules JSON
                            $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                            $stmt->execute([json_encode($modules_data), $course_id]);
                            
                            // Send real-time notification via Pusher
                            $module_data = null;
                            foreach ($modules_data as $module) {
                                if ($module['id'] === $module_id) {
                                    $module_data = $module;
                                    break;
                                }
                            }
                            
                            if ($module_data) {
                                // Send module update notification
                                PusherNotifications::sendModuleUpdate([
                                    'module_id' => $module_id,
                                    'module_title' => $module_data['module_title'],
                                    'module_description' => $module_data['module_description'],
                                    'course_id' => $course_id,
                                    'course_name' => $course['course_name'] ?? 'Unknown Course',
                                    'module_order' => $module_data['module_order'],
                                    'is_locked' => $module_data['is_locked'],
                                    'teacher_id' => $_SESSION['user_id'],
                                    'timestamp' => time(),
                                    'update_type' => 'edit' // Mark this as an edit operation
                                ]);
                                
                                // Only send lock update notification if the lock status actually changed
                                $old_lock_status = isset($old_module_data['is_locked']) ? (int)$old_module_data['is_locked'] : 0;
                                $new_lock_status = (int)$is_locked;
                                
                                // Debug logging
                                error_log("Edit module - Old lock status: $old_lock_status, New lock status: $new_lock_status");
                                
                                if ($old_module_data && $old_lock_status !== $new_lock_status) {
                                    PusherNotifications::sendModuleLockUpdate([
                                        'module_id' => $module_id,
                                        'module_title' => $module_data['module_title'],
                                        'course_id' => $course_id,
                                        'course_name' => $course['course_name'] ?? 'Unknown Course',
                                        'is_locked' => $is_locked,
                                        'teacher_id' => $_SESSION['user_id'],
                                        'timestamp' => time()
                                    ]);
                                }
                            }
                            
                            $message = 'Module updated successfully.';
                        $message_type = 'success';
                            
                            // Don't redirect, just set message and continue
                            // The page will reload naturally and show updated status
                        }
                    } else {
                        $message = 'Invalid course selected.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'delete_module':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                $course_id = (int)($_POST['course_id'] ?? 0);
                
                // Verify course belongs to teacher
                $stmt = $db->prepare('SELECT id, modules FROM courses WHERE id = ? AND teacher_id = ?');
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                $course = $stmt->fetch();
                
                if ($course) {
                    $modules_data = json_decode($course['modules'], true) ?: [];
                    
                    // Remove module
                    $modules_data = array_filter($modules_data, function($module) use ($module_id) {
                        return $module['id'] !== $module_id;
                    });
                    
                    // Update course with updated modules JSON
                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                    $stmt->execute([json_encode($modules_data), $course_id]);
                    
                                                                     $message = 'Module deleted successfully.';
                     $message_type = 'success';
                     
                     // Don't redirect, just set message and continue
                     // The page will reload naturally and show updated status
                 } else {
                    $message = 'Invalid course selected.';
                    $message_type = 'danger';
                }
                break;
                
            case 'toggle_lock':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                $course_id = (int)($_POST['course_id'] ?? 0);
                $new_lock_status = (int)($_POST['is_locked'] ?? 0);
                
                // Debug logging
                error_log("Toggle lock request - Module ID: $module_id, Course ID: $course_id, New Status: $new_lock_status");
                
                // Verify course belongs to teacher
                $stmt = $db->prepare('SELECT id, modules FROM courses WHERE id = ? AND teacher_id = ?');
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                $course = $stmt->fetch();
                
                if ($course) {
                    $modules_data = json_decode($course['modules'], true) ?: [];
                    
                    // Find and update module lock status
                    $module_found = false;
                    foreach ($modules_data as &$module) {
                        if ($module['id'] === $module_id) {
                            $module['is_locked'] = $new_lock_status;
                            $module_found = true;
                            break;
                        }
                    }
                    
                    if ($module_found) {
                        // Update course with updated modules JSON
                        $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                        $stmt->execute([json_encode($modules_data), $course_id]);
                        
                        $status_text = $new_lock_status ? 'locked' : 'unlocked';
                        $message = "Module successfully {$status_text}.";
                        $message_type = 'success';
                        
                        // Debug logging
                        error_log("Module lock status updated successfully - Module ID: $module_id, New Status: $new_lock_status");
                        
                        // Send real-time notification via Pusher
                        $module_data = null;
                        foreach ($modules_data as $module) {
                            if ($module['id'] === $module_id) {
                                $module_data = $module;
                                break;
                            }
                        }
                        
                        if ($module_data) {
                            PusherNotifications::sendModuleLockUpdate([
                                'module_id' => $module_id,
                                'module_title' => $module_data['module_title'],
                                'course_id' => $course_id,
                                'course_name' => $course['course_name'] ?? 'Unknown Course',
                                'is_locked' => $new_lock_status,
                                'teacher_id' => $_SESSION['user_id'],
                                'timestamp' => time(),
                                'update_type' => 'lock_change' // Mark this as a lock change operation
                            ]);
                        }
                        
                        // Refresh the modules data to show updated status
                        $all_modules = [];
                        foreach ($courses as $course) {
                            $modules_data = json_decode($course['modules'], true) ?: [];
                            foreach ($modules_data as $module) {
                                $module['course_id'] = $course['id'];
                                $module['course_title'] = $course['course_name'];
                                // Ensure is_locked is always set
                                if (!isset($module['is_locked'])) {
                                    $module['is_locked'] = 0;
                                }
                                $all_modules[] = $module;
                            }
                        }
                        
                        // Sort modules by order
                        usort($all_modules, function($a, $b) {
                            $order_a = isset($a['module_order']) ? (int)$a['module_order'] : 0;
                            $order_b = isset($b['module_order']) ? (int)$b['module_order'] : 0;
                            return $order_a - $order_b;
                        });
                    } else {
                        $message = 'Module not found.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'Invalid course selected.';
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Check for success message from redirects
if (isset($_GET['success'])) {
    $message = $_GET['success'];
    $message_type = 'success';
}

// Sort modules by order
usort($all_modules, function($a, $b) {
    $order_a = isset($a['module_order']) ? (int)$a['module_order'] : 0;
    $order_b = isset($b['module_order']) ? (int)$b['module_order'] : 0;
    return $order_a - $order_b;
});

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2" style="color: var(--main-green);">
                        <i class="bi bi-collection me-2"></i>Modules Management
                    </h1>
                    <p class="text-muted mb-0">Organize and manage your course modules efficiently</p>
            </div>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <span class="badge fs-6 px-3 py-2" style="background-color: var(--main-green); color: white;">
                            <i class="bi bi-folder me-1"></i><?php echo count($all_modules); ?> Modules
                            <?php if ($selected_course_id > 0 || $selected_status !== '' || !empty($search_term)): ?>
                                (Filtered)
                            <?php endif; ?>
                        </span>
        </div>
    </div>
        </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : ($message_type === 'danger' ? 'exclamation-triangle-fill' : 'info-circle-fill'); ?> me-2 fs-4"></i>
                <div class="flex-grow-1">
                    <strong class="me-2"><?php echo ucfirst($message_type); ?>:</strong>
            <?php echo $message; ?>
                </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light border-0">
                    <h6 class="mb-0" style="color: var(--main-green);">
                        <i class="bi bi-funnel me-2"></i>Filter Options
                    </h6>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="course" class="form-label fw-semibold">
                                <i class="bi bi-book me-1"></i>Filter by Course
                            </label>
                            <select class="form-select" id="course" name="course" onchange="this.form.submit()">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo isset($_GET['course']) && $_GET['course'] == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                        <?php if (!empty($course['course_code'])): ?>
                                            (<?php echo htmlspecialchars($course['course_code']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label fw-semibold">
                                <i class="bi bi-toggle-on me-1"></i>Filter by Status
                            </label>
                            <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="unlocked" <?php echo isset($_GET['status']) && $_GET['status'] == 'unlocked' ? 'selected' : ''; ?>>Unlocked</option>
                                <option value="locked" <?php echo isset($_GET['status']) && $_GET['status'] == 'locked' ? 'selected' : ''; ?>>Locked</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label fw-semibold">
                                <i class="bi bi-search me-1"></i>Search Modules
                            </label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by title or description..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                   onkeyup="if(event.key==='Enter') this.form.submit()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i>Search
                                </button>
                                <a href="modules.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Summary -->
    <?php if ($selected_course_id > 0 || $selected_status !== '' || !empty($search_term)): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-info d-flex align-items-center">
                    <i class="bi bi-funnel me-2"></i>
                    <div class="flex-grow-1">
                        <strong>Active Filters:</strong>
                        <?php if ($selected_course_id > 0): ?>
                            <?php 
                            $selected_course = array_filter($courses, function($c) use ($selected_course_id) {
                                return $c['id'] == $selected_course_id;
                            });
                            $selected_course = reset($selected_course);
                            ?>
                            <span class="badge bg-primary me-2">
                                Course: <?php echo htmlspecialchars($selected_course['course_name'] ?? 'Unknown'); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($selected_status !== ''): ?>
                            <span class="badge bg-<?php echo $selected_status === 'locked' ? 'danger' : 'success'; ?> me-2">
                                Status: <?php echo ucfirst($selected_status); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($search_term)): ?>
                            <span class="badge bg-info me-2">
                                Search: "<?php echo htmlspecialchars($search_term); ?>"
                            </span>
                        <?php endif; ?>
                    </div>
                    <a href="modules.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Clear All
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2">
                    <button class="btn" onclick="showBulkActions()" id="bulkActionsBtn" style="display: none; border-color: var(--main-green); color: var(--main-green);">
                        <i class="bi bi-gear me-1"></i>Bulk Actions
                    </button>
                    <button class="btn" onclick="exportModules()" style="border-color: var(--accent-green); color: var(--accent-green);">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                </div>
        <div>
                    <button class="btn btn-success btn-lg px-4" data-bs-toggle="modal" data-bs-target="#createModuleModal" <?php echo empty($courses) ? 'disabled' : ''; ?>>
                        <i class="bi bi-plus-circle me-2"></i>Create New Module
            </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modules List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header border-0" style="background-color: var(--main-green); color: white;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-white">
                            <i class="bi bi-collection me-2"></i>Modules Overview
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge fs-6 px-3 py-2" style="background-color: white; color: var(--main-green);">
                                <?php echo count($all_modules); ?> Total
                                <?php if ($selected_course_id > 0 || $selected_status !== '' || !empty($search_term)): ?>
                                    (Filtered)
                                <?php endif; ?>
                            </span>
                </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($all_modules)): ?>
                        <div class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-folder-x display-1 text-muted mb-4"></i>
                                <h4 class="text-muted mb-3">No Modules Found</h4>
                                <p class="text-muted mb-4">Create your first module to start organizing your course content.</p>
                                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createModuleModal">
                                    <i class="bi bi-plus-circle me-2"></i>Create Your First Module
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead style="background-color: var(--main-green); color: white;">
                                    <tr>
                                        <th class="border-0 ps-4">
                                            <div class="form-check">
                                            <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleSelectAll()">
                                                <label class="form-check-label fw-semibold" for="selectAll">All</label>
                                            </div>
                                        </th>
                                        <th class="border-0 fw-semibold">Module Details</th>
                                        <th class="border-0 fw-semibold">Course</th>
                                        <th class="border-0 fw-semibold text-center">Order</th>
                                        <th class="border-0 fw-semibold text-center">Content</th>
                                        <th class="border-0 fw-semibold text-center">Status</th>
                                        <th class="border-0 fw-semibold text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_modules as $module): ?>
                                        <tr class="module-row">
                                            <td class="ps-4">
                                                <div class="form-check">
                                                <input type="checkbox" class="form-check-input module-checkbox" 
                                                       value="<?php echo $module['id']; ?>" 
                                                       onchange="updateSelectedCount()">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-start">
                                                    <div class="module-icon me-3">
                                                        <i class="bi bi-folder-fill text-primary fs-4"></i>
            </div>
                                                    <div class="module-info">
                                                        <h6 class="mb-1 fw-semibold text-dark">
                                                            <?php echo htmlspecialchars($module['module_title']); ?>
                                                            <?php if (isset($module['file']) && !empty($module['file'])): ?>
                                                                <i class="fas fa-paperclip text-primary ms-1" title="Module has attached file"></i>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <?php if (!empty($module['module_description'])): ?>
                                                            <p class="text-muted mb-0 small"><?php echo htmlspecialchars(substr($module['module_description'], 0, 100)) . '...'; ?></p>
                            <?php endif; ?>
                        </div>
                                                </div>
                                            </td>
                                            <td>
                                                 <div class="course-info">
                                                     <div class="fw-bold" style="color: var(--main-green);"><?php echo htmlspecialchars($module['course_title']); ?></div>
                                                     <small class="text-muted">
                                                         <?php if (!empty($module['course_code'])): ?>
                                                             <?php echo htmlspecialchars($module['course_code']); ?> • 
                                                         <?php endif; ?>
                                                         ID: <?php echo $module['course_id']; ?>
                                                     </small>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary fs-6 px-3 py-2"><?php echo $module['module_order'] ?? 'N/A'; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-column gap-1 content-details" 
                                                     style="cursor: pointer;" 
                                                     data-module-id="<?php echo $module['id']; ?>"
                                                     data-module-title="<?php echo htmlspecialchars($module['module_title']); ?>"
                                                     data-video-count="<?php echo $module['video_count']; ?>"
                                                     data-assessment-count="<?php echo $module['assessment_count']; ?>"
                                                     data-has-file="<?php echo isset($module['file']) && !empty($module['file']) ? 'true' : 'false'; ?>"
                                                     data-file-name="<?php echo isset($module['file']) ? htmlspecialchars($module['file']['original_name']) : ''; ?>"
                                                     data-videos='<?php echo json_encode($module['videos'] ?? []); ?>'
                                                     data-assessments='<?php echo json_encode($module['assessments'] ?? []); ?>'
                                                     onclick="showContentDetailsFromElement(this)">
                                                    <span class="badge bg-info bg-opacity-75">
                                                        <i class="fas fa-video me-1"></i><?php echo $module['video_count']; ?> videos
                                                    </span>
                                                    <span class="badge bg-warning bg-opacity-75">
                                                        <i class="fas fa-question-circle me-1"></i><?php echo $module['assessment_count']; ?> assessments
                                                    </span>
                                                    <?php if (isset($module['file']) && !empty($module['file'])): ?>
                                                        <span class="badge bg-primary bg-opacity-75" title="Module File: <?php echo htmlspecialchars($module['file']['original_name']); ?>">
                                                            <i class="fas fa-paperclip me-1"></i>File
                                                        </span>
                                                    <?php endif; ?>
                                                    <small class="text-muted mt-1">
                                                        <i class="fas fa-eye me-1"></i>Click to view details
                                                    </small>
                                                </div>
                                            </td>
                                                                                         <td class="text-center">
                                                 <span class="badge module-status-badge <?php echo isset($module['is_locked']) && $module['is_locked'] ? 'bg-danger bg-opacity-75' : 'bg-success bg-opacity-75'; ?> fs-6 px-3 py-2">
                                                     <i class="bi bi-<?php echo isset($module['is_locked']) && $module['is_locked'] ? 'lock-fill' : 'unlock-fill'; ?> me-1"></i>
                                                     <?php echo isset($module['is_locked']) && $module['is_locked'] ? 'Locked' : 'Unlocked'; ?>
                                                    </span>
                                            </td>
                                            <td class="text-center">
                                                                                                 <div class="btn-group-vertical btn-group-sm">
                                                     <button class="btn btn-sm mb-1 edit-module-btn" 
                                                             data-module-id="<?php echo $module['id']; ?>"
                                                             data-module-title="<?php echo htmlspecialchars($module['module_title'], ENT_QUOTES); ?>"
                                                             data-module-description="<?php echo htmlspecialchars($module['module_description'] ?? '', ENT_QUOTES); ?>"
                                                             data-course-id="<?php echo $module['course_id']; ?>"
                                                             data-module-order="<?php echo $module['module_order'] ?? 1; ?>"
                                                             data-is-locked="<?php echo $module['is_locked'] ? 1 : 0; ?>"
                                                             data-file-name="<?php echo isset($module['file']) ? htmlspecialchars($module['file']['original_name'], ENT_QUOTES) : ''; ?>"
                                                             title="Edit Module"
                                                             style="border-color: var(--main-green); color: var(--main-green);">
                                                         <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                                             <button class="btn btn-sm mb-1 lock-module-btn" 
                                data-module-id="<?php echo $module['id']; ?>"
                                onclick="toggleModuleLock('<?php echo $module['id']; ?>', <?php echo $module['course_id']; ?>, <?php echo isset($module['is_locked']) && $module['is_locked'] ? 1 : 0; ?>, event)"
                                title="<?php echo isset($module['is_locked']) && $module['is_locked'] ? 'Unlock Module' : 'Lock Module'; ?>"
                                style="border-color: var(--highlight-yellow); color: var(--highlight-yellow);">
                            <i class="bi bi-<?php echo isset($module['is_locked']) && $module['is_locked'] ? 'unlock-fill' : 'lock-fill'; ?>"></i>
                                                    </button>
                                                    <?php if (isset($module['file']) && !empty($module['file'])): ?>
                                                        <button class="btn btn-sm mb-1 download-module-btn" 
                                                                onclick="downloadModuleFile('<?php echo $module['id']; ?>', '<?php echo htmlspecialchars($module['file']['filename']); ?>', '<?php echo htmlspecialchars($module['file']['original_name']); ?>', event)"
                                                                title="Download Module File"
                                                                style="border-color: var(--accent-blue); color: var(--accent-blue);">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                     <button class="btn btn-sm delete-module-btn" 
                                                             data-module-id="<?php echo $module['id']; ?>"
                                                             onclick="deleteModule('<?php echo $module['id']; ?>', '<?php echo htmlspecialchars($module['module_title']); ?>', <?php echo $module['course_id']; ?>, event)"
                                                             title="Delete Module"
                                                             style="border-color: #dc3545; color: #dc3545;">
                                                         <i class="bi bi-trash3"></i>
                                                        </button>
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

<!-- Edit Module Modal -->
<div class="modal fade" id="editModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0" style="background-color: var(--main-green); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Edit Module
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data" onsubmit="handleEditFormSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_module">
                    <input type="hidden" name="module_id" id="edit_module_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="edit_module_title" class="form-label">Module Title</label>
                        <input type="text" class="form-control" id="edit_module_title" name="module_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_course_id" class="form-label">Course</label>
                            <select class="form-select" id="edit_course_id" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_module_order" class="form-label">Module Order</label>
                            <input type="number" class="form-control" id="edit_module_order" name="module_order" min="1" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_module_file" class="form-label">Module File (Optional)</label>
                        <input type="file" class="form-control" id="edit_module_file" name="module_file" 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png,.gif,.bmp,.mp4,.avi,.mov,.wmv,.mp3,.wav,.zip,.rar,.7z,.tar,.gz">
                        <div class="form-text">Upload a new file to replace the current one (Max 10MB)</div>
                        <div id="current_file_info" class="mt-2" style="display: none;">
                            <small class="text-muted">Current file: <span id="current_file_name"></span></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_locked" name="is_locked">
                            <label class="form-check-label" for="edit_is_locked">
                                Lock this module (students cannot access until unlocked)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background-color: var(--main-green); border-color: var(--main-green); color: white;">Update Module</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Module Modal -->
<div class="modal fade" id="createModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0" style="background-color: var(--accent-green); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Create New Module
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_module">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label for="create_module_title" class="form-label">Module Title</label>
                        <input type="text" class="form-control" id="create_module_title" name="module_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="create_description" class="form-label">Description</label>
                        <textarea class="form-control" id="create_description" name="module_description" rows="3"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="create_course_id" class="form-label">Course</label>
                            <select class="form-select" id="create_course_id" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="create_module_order" class="form-label">Module Order</label>
                            <input type="number" class="form-control" id="create_module_order" name="module_order" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="create_module_file" class="form-label">Module File (Optional)</label>
                        <input type="file" class="form-control" id="create_module_file" name="module_file" 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png,.gif,.bmp,.mp4,.avi,.mov,.wmv,.mp3,.wav,.zip,.rar,.7z,.tar,.gz">
                        <div class="form-text">Upload a file to accompany this module (Max 10MB)</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="create_is_locked" name="is_locked">
                            <label class="form-check-label" for="create_is_locked">
                                Lock this module (students cannot access until unlocked)
                            </label>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background-color: var(--accent-green); border-color: var(--accent-green); color: white;">Create Module</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Lock Form -->
<form id="toggleLockForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="toggle_lock">
    <input type="hidden" name="module_id" id="toggle_module_id">
    <input type="hidden" name="course_id" id="toggle_course_id">
    <input type="hidden" name="is_locked" id="toggle_is_locked">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Delete Module Form -->
<form id="deleteModuleForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_module">
    <input type="hidden" name="module_id" id="delete_module_id">
    <input type="hidden" name="course_id" id="delete_course_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Content Details Modal -->
<div class="modal fade" id="contentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--main-green); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>Module Content Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <h4 id="contentModuleTitle" class="text-primary mb-3"></h4>
                    </div>
                </div>
                
                <!-- Videos Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-video me-2"></i>Videos 
                                    <span class="badge bg-light text-dark ms-2" id="videoCount">0</span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="videosList">
                                    <!-- Videos will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Assessments Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0">
                                    <i class="fas fa-question-circle me-2"></i>Assessments 
                                    <span class="badge bg-light text-dark ms-2" id="assessmentCount">0</span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="assessmentsList">
                                    <!-- Assessments will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Module File Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-paperclip me-2"></i>Module Files
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="moduleFilesList">
                                    <!-- Module files will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Download Options Modal -->
<div class="modal fade" id="downloadOptionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-download me-2"></i>Download Options
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Choose how you want to download the selected modules:</p>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-archive fa-3x text-primary mb-3"></i>
                                <h6 class="card-title">Download as ZIP</h6>
                                <p class="card-text small">All module files will be compressed into a single ZIP file for easy download.</p>
                                <button class="btn btn-primary btn-sm" onclick="downloadModulesAsZip()">
                                    <i class="fas fa-download me-1"></i>Download ZIP
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-folder-open fa-3x text-success mb-3"></i>
                                <h6 class="card-title">Organized Structure</h6>
                                <p class="card-text small">Files will be organized by module with proper folder structure and metadata.</p>
                                <button class="btn btn-success btn-sm" onclick="downloadModulesAsZip()">
                                    <i class="fas fa-download me-1"></i>Download Organized
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> Large downloads may take a few moments to prepare. Please be patient.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
// Content details modal function - reads from data attributes
function showContentDetailsFromElement(element) {
    console.log('Content details clicked!', element);
    
    // First, let's test if the click is working
    alert('Content details clicked! Check console for details.');
    
    const moduleId = element.getAttribute('data-module-id');
    const moduleTitle = element.getAttribute('data-module-title');
    const videoCount = parseInt(element.getAttribute('data-video-count'));
    const assessmentCount = parseInt(element.getAttribute('data-assessment-count'));
    const hasFile = element.getAttribute('data-has-file') === 'true';
    const fileName = element.getAttribute('data-file-name');
    const videos = JSON.parse(element.getAttribute('data-videos') || '[]');
    const assessments = JSON.parse(element.getAttribute('data-assessments') || '[]');
    
    console.log('Data extracted:', {
        moduleId, moduleTitle, videoCount, assessmentCount, hasFile, fileName, videos, assessments
    });
    
    showContentDetails(moduleId, moduleTitle, videoCount, assessmentCount, hasFile, fileName, videos, assessments);
}

// Content details modal function
function showContentDetails(moduleId, moduleTitle, videoCount, assessmentCount, hasFile, fileName, videos, assessments) {
    console.log('showContentDetails called with:', arguments);
    
    // Check if modal exists
    const modal = document.getElementById('contentDetailsModal');
    if (!modal) {
        console.error('Content details modal not found!');
        alert('Modal not found. Please refresh the page.');
        return;
    }
    
    // Set module title
    const titleElement = document.getElementById('contentModuleTitle');
    if (titleElement) {
        titleElement.textContent = moduleTitle;
    } else {
        console.error('contentModuleTitle element not found!');
    }
    
    // Update counts
    const videoCountElement = document.getElementById('videoCount');
    const assessmentCountElement = document.getElementById('assessmentCount');
    
    if (videoCountElement) {
        videoCountElement.textContent = videoCount;
    } else {
        console.error('videoCount element not found!');
    }
    
    if (assessmentCountElement) {
        assessmentCountElement.textContent = assessmentCount;
    } else {
        console.error('assessmentCount element not found!');
    }
    
    // Populate videos list
    const videosList = document.getElementById('videosList');
    if (videos && videos.length > 0) {
        let videosHtml = '<div class="row">';
        videos.forEach((video, index) => {
            videosHtml += `
                <div class="col-md-6 mb-3">
                    <div class="card border-info">
                        <div class="card-body">
                            <h6 class="card-title text-info">
                                <i class="fas fa-video me-2"></i>${video.video_title || 'Untitled Video'}
                            </h6>
                            <p class="card-text small text-muted">
                                ${video.video_description || 'No description available'}
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>${video.min_watch_time || 0} min
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-sort-numeric-up me-1"></i>Order ${video.video_order || index + 1}
                                </small>
                            </div>
                            ${video.video_url ? `
                                <div class="mt-2">
                                    <a href="${video.video_url}" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-external-link-alt me-1"></i>View Video
                                    </a>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        videosHtml += '</div>';
        videosList.innerHTML = videosHtml;
    } else {
        videosList.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-video-slash fa-2x mb-2"></i><p>No videos available for this module</p></div>';
    }
    
    // Populate assessments list
    const assessmentsList = document.getElementById('assessmentsList');
    if (assessments && assessments.length > 0) {
        let assessmentsHtml = '<div class="row">';
        assessments.forEach((assessment, index) => {
            assessmentsHtml += `
                <div class="col-md-6 mb-3">
                    <div class="card border-warning">
                        <div class="card-body">
                            <h6 class="card-title text-warning">
                                <i class="fas fa-question-circle me-2"></i>${assessment.assessment_title || 'Untitled Assessment'}
                            </h6>
                            <p class="card-text small text-muted">
                                ${assessment.description || 'No description available'}
                            </p>
                            <div class="row text-center">
                                <div class="col-4">
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i><br>
                                        ${assessment.time_limit || 0} min
                                    </small>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">
                                        <i class="fas fa-question-circle"></i><br>
                                        ${assessment.num_questions || 0} questions
                                    </small>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">
                                        <i class="fas fa-signal"></i><br>
                                        ${(assessment.difficulty || 'medium').charAt(0).toUpperCase() + (assessment.difficulty || 'medium').slice(1)}
                                    </small>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-warning text-dark">
                                    Passing Rate: ${assessment.passing_rate || 70}%
                                </span>
                                <span class="badge bg-secondary ms-1">
                                    Order: ${assessment.assessment_order || index + 1}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        assessmentsHtml += '</div>';
        assessmentsList.innerHTML = assessmentsHtml;
    } else {
        assessmentsList.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-question-circle-slash fa-2x mb-2"></i><p>No assessments available for this module</p></div>';
    }
    
    // Populate module files list
    const moduleFilesList = document.getElementById('moduleFilesList');
    if (hasFile && fileName) {
        moduleFilesList.innerHTML = `
            <div class="d-flex align-items-center p-3 bg-light rounded">
                <i class="fas fa-file fa-2x text-primary me-3"></i>
                <div class="flex-grow-1">
                    <h6 class="mb-1">${fileName}</h6>
                    <small class="text-muted">Module file attached</small>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="downloadModuleFile('${moduleId}', '${fileName}', '${fileName}', event)">
                    <i class="fas fa-download me-1"></i>Download
                </button>
            </div>
        `;
    } else {
        moduleFilesList.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-file-slash fa-2x mb-2"></i><p>No files attached to this module</p></div>';
    }
    
    // Show the modal
    console.log('Attempting to show modal...');
    const modalElement = document.getElementById('contentDetailsModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        console.log('Modal should be showing now');
    } else {
        console.error('Modal element not found!');
        alert('Modal element not found. Please refresh the page.');
    }
}

// Module management functions
function toggleModuleLock(moduleId, courseId, isLocked, event) {
    // Prevent event bubbling and default behavior
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const action = isLocked ? 'unlock' : 'lock';
    if (confirm('Are you sure you want to ' + action + ' this module?')) {
        // Set form values
    document.getElementById('toggle_module_id').value = moduleId;
        document.getElementById('toggle_course_id').value = courseId;
    document.getElementById('toggle_is_locked').value = isLocked ? 0 : 1;
        
        // Show loading state
        const button = event ? event.target.closest('button') : document.querySelector(`button[onclick*="toggleModuleLock('${moduleId}'"]`);
        const originalContent = button ? button.innerHTML : '';
        button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        button.disabled = true;
        
        // Submit the form via AJAX for real-time updates
        fetch('modules.php', {
            method: 'POST',
            body: new FormData(document.getElementById('toggleLockForm')),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(data => {
            // Restore button state
            button.innerHTML = originalContent;
            button.disabled = false;
            
            // Show success message
            showSuccessMessage(`Module successfully ${action}ed!`);
            
            // The real-time update will be handled by Pusher
            console.log('✅ Module lock status updated via AJAX');
        })
        .catch(error => {
            console.error('❌ Error updating module lock status:', error);
            
            // Restore button state
            button.innerHTML = originalContent;
            button.disabled = false;
            
            // Show error message
            showErrorMessage('Failed to update module status. Please try again.');
        });
    }
}

function deleteModule(moduleId, moduleTitle, courseId, event) {
    // Prevent event bubbling and default behavior
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    if (confirm('Are you sure you want to delete the module "' + moduleTitle + '"? This action cannot be undone.')) {
        document.getElementById('delete_module_id').value = moduleId;
        document.getElementById('delete_course_id').value = courseId;
        
        // Show loading state
        const button = event ? event.target.closest('button') : document.querySelector(`button[onclick*="deleteModule('${moduleId}'"]`);
        const originalContent = button ? button.innerHTML : '';
        button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        button.disabled = true;
        
        // Submit the form via AJAX
        fetch('modules.php', {
            method: 'POST',
            body: new FormData(document.getElementById('deleteModuleForm')),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(data => {
            // Show success message
            showSuccessMessage('Module deleted successfully!');
            
            // Remove the row from the table
            const moduleRows = document.querySelectorAll('.module-row');
            moduleRows.forEach(row => {
                const checkbox = row.querySelector('.module-checkbox');
                if (checkbox && checkbox.value === moduleId) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-100%)';
                    setTimeout(() => {
                        row.remove();
                        updateModuleCount();
                    }, 300);
                }
            });
            
            console.log('✅ Module deleted via AJAX');
        })
        .catch(error => {
            console.error('❌ Error deleting module:', error);
            
            // Restore button state
            button.innerHTML = originalContent;
            button.disabled = false;
            
            // Show error message
            showErrorMessage('Failed to delete module. Please try again.');
        });
    }
}

function downloadModuleFile(moduleId, filename, originalName, event) {
    // Prevent event bubbling and default behavior
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Create a temporary link to download the file
    const link = document.createElement('a');
    link.href = `download_module_file.php?module_id=${moduleId}&filename=${encodeURIComponent(filename)}&original_name=${encodeURIComponent(originalName)}`;
    link.download = originalName;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function editModule(moduleId, moduleTitle, description, courseId, moduleOrder, isLocked, fileName, event) {
    // Prevent event bubbling and default behavior
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    document.getElementById('edit_module_id').value = moduleId;
    document.getElementById('edit_module_title').value = moduleTitle;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_module_order').value = moduleOrder;
    document.getElementById('edit_is_locked').checked = isLocked == 1;
    
    // Clear file input
    document.getElementById('edit_module_file').value = '';
    
    // Show current file info if exists
    if (fileName && fileName.trim() !== '') {
        document.getElementById('current_file_name').textContent = fileName;
        document.getElementById('current_file_info').style.display = 'block';
    } else {
        document.getElementById('current_file_info').style.display = 'none';
    }
    
    const editModal = new bootstrap.Modal(document.getElementById('editModuleModal'));
    editModal.show();
}

// Handle create form submission with AJAX
function handleCreateFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    const originalContent = submitButton.innerHTML;
    submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating...';
    submitButton.disabled = true;
    
    // Submit via AJAX
    fetch('modules.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(data => {
        // Restore button state
        submitButton.innerHTML = originalContent;
        submitButton.disabled = false;
        
        // Close modal
        const createModal = bootstrap.Modal.getInstance(document.getElementById('createModuleModal'));
        createModal.hide();
        
        // Clear form
        form.reset();
        
        // Show success message
        showSuccessMessage('Module created successfully!');
        
        // Reload the page to show the new module (since we need to get the new module ID)
        setTimeout(() => {
            window.location.reload();
        }, 1000);
        
        console.log('✅ Module created via AJAX');
    })
    .catch(error => {
        console.error('❌ Error creating module:', error);
        
        // Restore button state
        submitButton.innerHTML = originalContent;
        submitButton.disabled = false;
        
        // Show error message
        showErrorMessage('Failed to create module. Please try again.');
    });
}

// Handle edit form submission with AJAX
function handleEditFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    const originalContent = submitButton.innerHTML;
    submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...';
    submitButton.disabled = true;
    
    // Submit via AJAX
    fetch('modules.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(data => {
        // Restore button state
        submitButton.innerHTML = originalContent;
        submitButton.disabled = false;
        
        // Close modal
        const editModal = bootstrap.Modal.getInstance(document.getElementById('editModuleModal'));
        editModal.hide();
        
        // Show success message
        showSuccessMessage('Module updated successfully!');
        
        // Update the row in real-time
        try {
            updateModuleRowInTable(formData);
        } catch (error) {
            console.error('❌ Error updating module row:', error);
            // Still show success message even if UI update fails
        }
        
        console.log('✅ Module updated via AJAX');
    })
    .catch(error => {
        console.error('❌ Error updating module:', error);
        
        // Restore button state
        submitButton.innerHTML = originalContent;
        submitButton.disabled = false;
        
        // Show error message
        showErrorMessage('Failed to update module. Please try again.');
    });
}

// Update the module row in the table after edit
function updateModuleRowInTable(formData) {
    const moduleId = formData.get('module_id');
    const moduleTitle = formData.get('module_title') || '';
    const description = formData.get('module_description') || '';
    const courseId = formData.get('course_id');
    const moduleOrder = formData.get('module_order');
    const isLocked = formData.get('is_locked') ? 1 : 0;
    
    // Validate required fields
    if (!moduleId || !courseId) {
        console.error('❌ Missing required fields for module update');
        return;
    }
    
    // Find the module row
    const moduleRows = document.querySelectorAll('.module-row');
    let targetRow = null;
    
    moduleRows.forEach(row => {
        const checkbox = row.querySelector('.module-checkbox');
        if (checkbox && checkbox.value === moduleId) {
            targetRow = row;
        }
    });
    
    if (targetRow) {
        // Update module title
        const titleElement = targetRow.querySelector('.module-info h6');
        if (titleElement) {
            titleElement.textContent = moduleTitle;
        }
        
        // Update module description
        const descElement = targetRow.querySelector('.module-info p');
        if (descElement) {
            if (description) {
                descElement.textContent = description.length > 100 ? description.substring(0, 100) + '...' : description;
                descElement.style.display = 'block';
            } else {
                descElement.style.display = 'none';
            }
        }
        
        // Update module order
        const orderBadge = targetRow.querySelector('td:nth-child(4) .badge');
        if (orderBadge) {
            orderBadge.textContent = moduleOrder;
        }
        
        // Update lock status
        const statusBadge = targetRow.querySelector('.module-status-badge');
        if (statusBadge) {
            if (isLocked) {
                statusBadge.className = 'badge module-status-badge bg-danger bg-opacity-75 fs-6 px-3 py-2';
                statusBadge.innerHTML = '<i class="bi bi-lock-fill me-1"></i>Locked';
            } else {
                statusBadge.className = 'badge module-status-badge bg-success bg-opacity-75 fs-6 px-3 py-2';
                statusBadge.innerHTML = '<i class="bi bi-unlock-fill me-1"></i>Unlocked';
            }
        }
        
        // Update lock/unlock button
        const lockButton = targetRow.querySelector('.lock-module-btn');
        if (lockButton) {
            if (isLocked) {
                lockButton.title = 'Unlock Module';
                lockButton.innerHTML = '<i class="bi bi-unlock-fill"></i>';
                lockButton.setAttribute('onclick', `toggleModuleLock('${moduleId}', ${courseId}, 1, event)`);
            } else {
                lockButton.title = 'Lock Module';
                lockButton.innerHTML = '<i class="bi bi-lock-fill"></i>';
                lockButton.setAttribute('onclick', `toggleModuleLock('${moduleId}', ${courseId}, 0, event)`);
            }
        }
        
        // Update edit button data attributes
        const editButton = targetRow.querySelector('.edit-module-btn');
        if (editButton) {
            editButton.setAttribute('data-module-id', moduleId);
            editButton.setAttribute('data-module-title', moduleTitle || '');
            editButton.setAttribute('data-module-description', description || '');
            editButton.setAttribute('data-course-id', courseId);
            editButton.setAttribute('data-module-order', moduleOrder);
            editButton.setAttribute('data-is-locked', isLocked);
            editButton.setAttribute('data-file-name', ''); // File info not available in this context
        }
        
        // Update delete button onclick
        const deleteButton = targetRow.querySelector('.delete-module-btn');
        if (deleteButton) {
            const safeTitle = moduleTitle ? moduleTitle.replace(/'/g, "\\'") : '';
            deleteButton.setAttribute('onclick', `deleteModule('${moduleId}', '${safeTitle}', ${courseId}, event)`);
        }
        
        // Add animation to show the update
        targetRow.style.transition = 'all 0.3s ease';
        targetRow.style.backgroundColor = '#f0fff4';
        setTimeout(() => {
            targetRow.style.backgroundColor = '';
        }, 1000);
        
        console.log('✅ Module row updated in real-time');
    }
}

// Select all functionality
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const moduleCheckboxes = document.querySelectorAll('.module-checkbox');
    
    moduleCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelectedCount();
}

function updateSelectedCount() {
    const selectedCheckboxes = document.querySelectorAll('.module-checkbox:checked');
    const bulkActionsBtn = document.getElementById('bulkActionsBtn');
    
    if (selectedCheckboxes.length > 0) {
        bulkActionsBtn.style.display = 'block';
        bulkActionsBtn.innerHTML = `<i class="bi bi-gear me-1"></i>Bulk Actions (${selectedCheckboxes.length})`;
    } else {
        bulkActionsBtn.style.display = 'none';
    }
}

function showBulkActions() {
    const selectedCheckboxes = document.querySelectorAll('.module-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        alert('Please select modules first.');
        return;
    }
    
    const action = prompt('Choose action: delete, lock, unlock, or export');
    if (!action) return;
    
    const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
    
    switch(action.toLowerCase()) {
        case 'delete':
            if (confirm(`Are you sure you want to delete ${selectedIds.length} modules?`)) {
                // Implement bulk delete
                console.log('Bulk delete:', selectedIds);
            }
            break;
        case 'lock':
            if (confirm(`Lock ${selectedIds.length} modules?`)) {
                // Implement bulk lock
                console.log('Bulk lock:', selectedIds);
            }
            break;
        case 'unlock':
            if (confirm(`Unlock ${selectedIds.length} modules?`)) {
                // Implement bulk unlock
                console.log('Bulk unlock:', selectedIds);
            }
            break;
        case 'export':
            exportSelectedModules(selectedIds);
            break;
        default:
            alert('Invalid action. Please choose: delete, lock, unlock, or export');
    }
}

function exportModules() {
    const allModules = <?php echo json_encode($all_modules); ?>;
    exportModulesData(allModules, 'all_modules');
}

function exportSelectedModules(moduleIds) {
    const allModules = <?php echo json_encode($all_modules); ?>;
    const selectedModules = allModules.filter(m => moduleIds.includes(m.id));
    exportModulesData(selectedModules, 'selected_modules');
}

function exportModulesData(modules, filename) {
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Module Title,Description,Course,Order,Status\n";
    
    modules.forEach(module => {
        const row = [
            module.module_title,
            module.module_description || '',
            module.course_title,
            module.module_order || 'N/A',
            module.is_locked ? 'Locked' : 'Unlocked'
        ].map(field => `"${field}"`).join(',');
        csvContent += row + "\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `${filename}_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Set default module order when course is selected
document.addEventListener('DOMContentLoaded', function() {
    const createCourseSelect = document.getElementById('create_course_id');
    const createOrderInput = document.getElementById('create_module_order');
    
    if (createCourseSelect && createOrderInput) {
        createCourseSelect.addEventListener('change', function() {
            const courseId = this.value;
            if (courseId) {
                // Get the next available order for this course
                const courseModules = <?php echo json_encode($course_modules_map); ?>;
                if (courseModules[courseId]) {
                    const nextOrder = getNextAvailableOrder(courseModules[courseId]);
                    createOrderInput.value = nextOrder;
                }
            }
        });
    }
    
    // Add fade-in effect to page elements
    const elements = document.querySelectorAll('.card, .alert, .table');
    elements.forEach((el, index) => {
        setTimeout(() => {
            el.classList.add('fade-in');
        }, index * 100);
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

function getNextAvailableOrder(modules) {
    if (!modules || modules.length === 0) {
        return 1;
    }
    
    const orders = modules.map(m => parseInt(m.module_order) || 0).filter(o => o > 0);
    return orders.length > 0 ? Math.max(...orders) + 1 : 1;
}

// Helper functions for showing messages
function showSuccessMessage(message) {
    showMessage(message, 'success');
}

function showErrorMessage(message) {
    showMessage(message, 'danger');
}

function showMessage(message, type) {
    // Create alert element
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <div class="d-flex align-items-center">
                <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2 fs-4"></i>
                <div class="flex-grow-1">
                    <strong class="me-2">${type === 'success' ? 'Success' : 'Error'}:</strong>
                    ${message}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    `;
    
    // Add to page
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert:last-of-type');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

function updateModuleCount() {
    const moduleRows = document.querySelectorAll('.module-row');
    const count = moduleRows.length;
    
    // Update the badge in the header
    const badge = document.querySelector('.badge[style*="background-color: var(--main-green)"]');
    if (badge) {
        badge.innerHTML = `<i class="bi bi-folder me-1"></i>${count} Modules`;
    }
    
    // Update the badge in the table header
    const tableBadge = document.querySelector('.badge[style*="background-color: white"]');
    if (tableBadge) {
        tableBadge.innerHTML = `${count} Total`;
    }
}

// Prevent double-clicks on action buttons and set up edit module event delegation
document.addEventListener('DOMContentLoaded', function() {
    // Set up event delegation for edit module buttons
    document.addEventListener('click', function(event) {
        if (event.target.closest('.edit-module-btn')) {
            event.preventDefault();
            event.stopPropagation();
            
            const button = event.target.closest('.edit-module-btn');
            const moduleId = button.getAttribute('data-module-id');
            const moduleTitle = button.getAttribute('data-module-title');
            const description = button.getAttribute('data-module-description');
            const courseId = button.getAttribute('data-course-id');
            const moduleOrder = button.getAttribute('data-module-order');
            const isLocked = button.getAttribute('data-is-locked');
            const fileName = button.getAttribute('data-file-name');
            
            editModule(moduleId, moduleTitle, description, courseId, moduleOrder, isLocked, fileName, event);
        }
    });
    
    // Handle other action buttons
    document.querySelectorAll('.lock-module-btn, .delete-module-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            // Prevent multiple rapid clicks
            if (this.disabled) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            
            // Add a small delay to prevent double-clicks
            this.disabled = true;
            setTimeout(() => {
                this.disabled = false;
            }, 1000);
        });
    });
});
</script>

<style>
/* Enhanced Module Management Styles */
:root {
    --main-green: #2E5E4E;
    --accent-green: #7DCB80;
    --highlight-yellow: #FFE066;
    --off-white: #F7FAF7;
    --white: #FFFFFF;
    --border-radius: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --light-green: rgba(125, 203, 128, 0.1);
    --medium-green: rgba(46, 94, 78, 0.1);
}

/* Card Enhancements */
.card {
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.card-header {
    border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
}

/* Table Enhancements */
.table {
    margin-bottom: 0;
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.85rem;
}

.table tbody tr {
    transition: var(--transition);
}

.table tbody tr:hover {
    background-color: var(--light-green) !important;
    transform: scale(1.01);
}

/* Module Row Styling */
.module-row {
    border-left: 4px solid transparent;
    transition: var(--transition);
}

.module-row:hover {
    border-left-color: var(--accent-green);
    background-color: var(--light-green) !important;
}

/* Module Icon */
.module-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--main-green), var(--accent-green));
    border-radius: 50%;
    color: white;
}

/* Badge Enhancements */
.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
}

/* Button Enhancements */
.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: var(--transition);
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-group-vertical .btn {
    border-radius: 6px;
    margin-bottom: 2px;
}

/* Modal Enhancements */
.modal-content {
    border-radius: var(--border-radius);
    overflow: hidden;
}

.modal-header {
    padding: 1.5rem;
}

.modal-body {
    padding: 2rem;
}

/* Form Enhancements */
.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--accent-green);
    box-shadow: 0 0 0 0.2rem var(--light-green);
}

/* Empty State */
.empty-state {
    padding: 3rem 1rem;
}

.empty-state i {
    opacity: 0.6;
}

/* Alert Enhancements */
.alert {
    border-radius: var(--border-radius);
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Filter Card */
.card-header.bg-light {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .btn-group-vertical {
        flex-direction: row;
    gap: 0.25rem;
}

    .btn-group-vertical .btn {
        margin-bottom: 0;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
}

/* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Action button improvements */
        .btn-group-vertical .btn {
            position: relative;
            z-index: 1;
        }
        
        .btn-group-vertical .btn:focus {
            z-index: 2;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .edit-module-btn:hover {
            background-color: var(--main-green) !important;
            color: white !important;
    transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

        .lock-module-btn:hover {
            background-color: var(--highlight-yellow) !important;
            color: white !important;
    transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .delete-module-btn:hover {
            background-color: #dc3545 !important;
            color: white !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-group-vertical .btn:active {
            transform: translateY(0);
        }

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Hover Effects */
.module-info h6:hover {
    color: var(--main-green) !important;
}

.course-info .fw-bold:hover {
    color: var(--accent-green) !important;
}

/* Status Badge Hover */
.badge:hover {
    transform: scale(1.05);
    transition: var(--transition);
}

/* Action Button Hover States */
.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Custom button hover states for action buttons */
.btn[style*="border-color: var(--main-green)"]:hover {
    background: var(--main-green);
    color: white !important;
}

.btn[style*="border-color: var(--highlight-yellow)"]:hover {
    background: var(--highlight-yellow);
    color: #333 !important;
}

.btn[style*="border-color: #dc3545"]:hover {
    background: #dc3545;
    color: white !important;
}

/* Content Details Modal Styles */
.content-details {
    transition: var(--transition);
    border-radius: 8px;
    padding: 8px;
}

.content-details:hover {
    background-color: var(--light-green) !important;
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.content-details:hover .badge {
    transform: scale(1.05);
}

/* Modal Content Cards */
#contentDetailsModal .card {
    border-radius: 12px;
    transition: var(--transition);
}

#contentDetailsModal .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

#contentDetailsModal .card-header {
    border-radius: 12px 12px 0 0 !important;
    font-weight: 600;
}

#contentDetailsModal .card-body {
    padding: 1.5rem;
}

/* Video and Assessment Cards in Modal */
#contentDetailsModal .card.border-info {
    border-left: 4px solid #17a2b8 !important;
}

#contentDetailsModal .card.border-warning {
    border-left: 4px solid #ffc107 !important;
}

#contentDetailsModal .card.border-primary {
    border-left: 4px solid #007bff !important;
}

/* Empty State Styling */
#contentDetailsModal .text-center.text-muted {
    padding: 2rem 1rem;
}

#contentDetailsModal .text-center.text-muted i {
    opacity: 0.5;
}

/* Badge Enhancements in Modal */
#contentDetailsModal .badge {
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
}

/* File Download Button */
#contentDetailsModal .btn-outline-primary:hover {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
}

/* Video Link Button */
#contentDetailsModal .btn-outline-info:hover {
    background-color: #17a2b8;
    border-color: #17a2b8;
    color: white;
}
</style>

<?php require_once '../includes/footer.php'; ?> 