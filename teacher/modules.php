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
    $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
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
                
                // Ensure module order is valid
                if ($module_order < 1) {
                    $module_order = 1;
                }
                
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
                        $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
                        
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
                
                // Ensure module order is valid
                if ($module_order < 1) {
                    $module_order = 1;
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
                        $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
                        
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
                    $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
                    
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
                    $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
                    
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
                            $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
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
                
            case 'toggle_module_lock':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                $course_id = (int)($_POST['course_id'] ?? 0);
                $is_locked = (int)($_POST['is_locked'] ?? 0);
                
                // Verify course belongs to teacher
                $stmt = $db->prepare('SELECT id, modules FROM courses WHERE id = ? AND teacher_id = ?');
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                $course = $stmt->fetch();
                
                if ($course) {
                    $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
                    $module_found = false;
                    
                    // Update the module lock status
                    foreach ($modules_data as &$module) {
                        if ($module['id'] === $module_id) {
                            $module['is_locked'] = $is_locked;
                            $module_found = true;
                            break;
                        }
                    }
                    
                    if ($module_found) {
                        // Update course with updated modules JSON
                        $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                        $stmt->execute([json_encode($modules_data), $course_id]);
                        
                        $message = $is_locked ? 'Module locked successfully.' : 'Module unlocked successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Module not found.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'Invalid course selected.';
                    $message_type = 'danger';
                }
                break;
                
            case 'bulk_delete_modules':
                $module_ids = json_decode($_POST['module_ids'] ?? '[]', true);
                
                if (empty($module_ids)) {
                    $message = 'No modules selected for deletion.';
                    $message_type = 'danger';
                } else {
                    $deleted_count = 0;
                    
                    foreach ($module_ids as $module_id) {
                        // Find which course contains this module
                        foreach ($courses as $course) {
                            $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
                            $module_found = false;
                            
                            foreach ($modules_data as $key => $module) {
                                if ($module['id'] === $module_id) {
                                    unset($modules_data[$key]);
                                    $module_found = true;
                                    break;
                                }
                            }
                            
                            if ($module_found) {
                                // Update course with updated modules JSON
                                $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                                $stmt->execute([json_encode(array_values($modules_data)), $course['id']]);
                                $deleted_count++;
                                break;
                            }
                        }
                    }
                    
                    $message = "Successfully deleted {$deleted_count} module(s).";
                    $message_type = 'success';
                }
                break;
                
            case 'bulk_update_module_status':
                $module_ids = json_decode($_POST['module_ids'] ?? '[]', true);
                $is_locked = (int)($_POST['is_locked'] ?? 0);
                
                if (empty($module_ids)) {
                    $message = 'No modules selected for status update.';
                    $message_type = 'danger';
                } else {
                    $updated_count = 0;
                    
                    foreach ($module_ids as $module_id) {
                        // Find which course contains this module
                        foreach ($courses as $course) {
                            $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
                            $module_found = false;
                            
                            foreach ($modules_data as &$module) {
                                if ($module['id'] === $module_id) {
                                    $module['is_locked'] = $is_locked;
                                    $module_found = true;
                                    break;
                                }
                            }
                            
                            if ($module_found) {
                                // Update course with updated modules JSON
                                $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                                $stmt->execute([json_encode($modules_data), $course['id']]);
                                $updated_count++;
                                break;
                            }
                        }
                    }
                    
                    $action_text = $is_locked ? 'locked' : 'unlocked';
                    $message = "Successfully {$action_text} {$updated_count} module(s).";
                    $message_type = 'success';
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

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Return JSON response for AJAX requests
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $message_type === 'success',
        'message' => $message
    ]);
    exit;
}

// Sort modules by order
usort($all_modules, function($a, $b) {
    $order_a = isset($a['module_order']) ? (int)$a['module_order'] : 0;
    $order_b = isset($b['module_order']) ? (int)$b['module_order'] : 0;
    return $order_a - $order_b;
});

?>

<!-- Modern Modules Management Header -->
<div class="modules-management-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-3">
                    <i class="bi bi-collection me-3"></i>Modules Management
                    </h1>
                <p class="mb-0 opacity-90">Organize and manage your course modules efficiently across all courses.</p>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="module-stats">
                        <div class="module-stat-item">
                            <span class="module-stat-number"><?php echo count($all_modules); ?></span>
                            <span class="module-stat-label">Total Modules</span>
        </div>
                        <div class="module-stat-item">
                            <span class="module-stat-number"><?php echo count($courses); ?></span>
                            <span class="module-stat-label">Courses</span>
                        </div>
                        <div class="module-stat-item">
                            <span class="module-stat-number"><?php echo count(array_filter($all_modules, function($m) { return !isset($m['is_locked']) || $m['is_locked'] == 0; })); ?></span>
                            <span class="module-stat-label">Unlocked</span>
                        </div>
                    </div>
                </div>
    </div>
        </div>
        </div>
    </div>

<div class="container-fluid">

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

    <!-- Enhanced Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card filter-card border-0">
                <div class="card-header filter-header">
                    <h6 class="mb-0">
                        <i class="bi bi-funnel me-2"></i>Filter & Search
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
                    <button class="btn btn-outline-primary" onclick="showBulkActions()" id="bulkActionsBtn" style="display: none;">
                        <i class="bi bi-gear me-1"></i>Bulk Actions
                    </button>
                    <button class="btn btn-outline-success" onclick="exportModules()">
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

    <!-- Modules Grid -->
    <div class="row">
        <div class="col-12">
                    <?php if (empty($all_modules)): ?>
                <div class="empty-state text-center py-5">
                    <div class="empty-state-content">
                                <i class="bi bi-folder-x display-1 text-muted mb-4"></i>
                                <h4 class="text-muted mb-3">No Modules Found</h4>
                                <p class="text-muted mb-4">Create your first module to start organizing your course content.</p>
                        <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#createModuleModal">
                                    <i class="bi bi-plus-circle me-2"></i>Create Your First Module
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span class="me-3">
                                <span id="selectedCount">0</span> modules selected
                            </span>
                            <button class="btn btn-sm btn-outline-danger me-2" onclick="bulkDelete()">
                                <i class="bi bi-trash me-1"></i>Delete Selected
                            </button>
                            <button class="btn btn-sm btn-outline-success me-2" onclick="bulkUnlock()">
                                <i class="bi bi-unlock me-1"></i>Unlock Selected
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="bulkLock()">
                                <i class="bi bi-lock me-1"></i>Lock Selected
                            </button>
                                            </div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                            <i class="bi bi-x me-1"></i>Clear Selection
                        </button>
                    </div>
                </div>

                <!-- Modules Grid -->
                <div class="modules-grid">
                    <?php 
                    // Define green color variations for module cards
                    $module_colors = [
                        '#2ecc71', // Emerald Green
                        '#27ae60', // Nephritis Green
                        '#16a085', // Turquoise Green
                        '#1abc9c', // Green Sea
                        '#00b894', // Mint Green
                        '#26de81', // Light Green
                        '#20bf6b', // Medium Green
                        '#0fb9b1', // Teal Green
                        '#45b7d1', // Blue Green
                        '#96ceb4', // Pale Green
                        '#6ab04c', // Forest Green
                        '#badc58'  // Lime Green
                    ];
                    $color_index = 0;
                    ?>
                    <?php foreach ($all_modules as $module): ?>
                        <?php 
                        $card_color = $module_colors[$color_index % count($module_colors)];
                        $color_index++;
                        ?>
                        <div class="module-card" style="border-left: 4px solid <?php echo $card_color; ?>; background-color: <?php echo $card_color; ?>;">
                            <!-- Module Status Badge -->
                            <div class="module-status <?php echo $module['is_locked'] ? 'status-locked' : 'status-unlocked'; ?>">
                                <?php echo $module['is_locked'] ? 'Locked' : 'Unlocked'; ?>
                            </div>
                            
                            <!-- Module Preview -->
                            <div class="module-card-preview" style="background-color: <?php echo $card_color; ?>;">
                                <i class="bi bi-folder module-icon"></i>
                            </div>
                            
                            <!-- Module Content -->
                            <div class="module-card-body">
                                <h6 class="module-title"><?php echo htmlspecialchars($module['module_title'] ?? 'Untitled Module'); ?></h6>
                                
                                <?php if (!empty($module['module_description'])): ?>
                                    <p class="module-description"><?php echo htmlspecialchars(substr($module['module_description'], 0, 120)) . (strlen($module['module_description']) > 120 ? '...' : ''); ?></p>
                                <?php endif; ?>
                                
                                <!-- Module Meta -->
                                <div class="module-meta">
                                    <div class="module-stats">
                                        <div class="module-stat">
                                            <span class="module-stat-number"><?php echo $module['video_count'] ?? 0; ?></span>
                                            <span class="module-stat-label">Videos</span>
                                        </div>
                                        <div class="module-stat">
                                            <span class="module-stat-number"><?php echo $module['assessment_count'] ?? 0; ?></span>
                                            <span class="module-stat-label">Assessments</span>
                                        </div>
                                        <div class="module-stat">
                                            <span class="module-stat-number"><?php echo $module['module_order'] ?? 1; ?></span>
                                            <span class="module-stat-label">Order</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Module Actions -->
                                <div class="module-actions">
                                    <a href="javascript:void(0)" onclick="viewModule(<?php echo $module['id']; ?>)" class="module-action-btn btn-view" title="View Details">
                                        <i class="bi bi-eye"></i>
                                        <span>View</span>
                                    </a>
                                    <a href="javascript:void(0)" onclick="editModule(<?php echo $module['id']; ?>)" class="module-action-btn btn-edit" title="Edit Module">
                                        <i class="bi bi-pencil"></i>
                                        <span>Edit</span>
                                    </a>
                                    <a href="module_videos.php?module_id=<?php echo $module['id']; ?>" class="module-action-btn btn-videos" title="Manage Videos">
                                        <i class="bi bi-camera-video"></i>
                                        <span>Videos</span>
                                    </a>
                                    <a href="javascript:void(0)" onclick="toggleModuleStatus(<?php echo $module['id']; ?>, <?php echo $module['is_locked'] ? 'false' : 'true'; ?>)" class="module-action-btn btn-toggle" title="<?php echo $module['is_locked'] ? 'Unlock' : 'Lock'; ?> Module">
                                        <i class="bi bi-<?php echo $module['is_locked'] ? 'unlock' : 'lock'; ?>"></i>
                                        <span><?php echo $module['is_locked'] ? 'Unlock' : 'Lock'; ?></span>
                                    </a>
                                    <a href="javascript:void(0)" onclick="deleteModule(<?php echo $module['id']; ?>)" class="module-action-btn btn-delete" title="Delete Module">
                                        <i class="bi bi-trash"></i>
                                        <span>Delete</span>
                                    </a>
                                </div>
                                
                                <!-- Module Info -->
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-book me-1"></i><?php echo htmlspecialchars($module['course_title'] ?? 'Unknown Course'); ?>
                                        <span class="ms-2">
                                            <i class="bi bi-calendar me-1"></i><?php echo date('M d, Y', strtotime($module['created_at'])); ?>
                                        </span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                            <label for="create_module_order" class="form-label">
                                <i class="bi bi-sort-numeric-up me-1"></i>Module Order
                                <small class="text-muted">(Auto-assigned)</small>
                            </label>
                            <input type="number" class="form-control" id="create_module_order" name="module_order" min="1" required readonly>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>Order will be automatically assigned based on existing modules
                            </div>
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
// JavaScript is loading...

// Content details modal function - reads from data attributes
function showContentDetailsFromElement(element) {
    const moduleId = element.getAttribute('data-module-id');
    const moduleTitle = element.getAttribute('data-module-title');
    const videoCount = parseInt(element.getAttribute('data-video-count'));
    const assessmentCount = parseInt(element.getAttribute('data-assessment-count'));
    const hasFile = element.getAttribute('data-has-file') === 'true';
    const fileName = element.getAttribute('data-file-name');
    const videos = JSON.parse(element.getAttribute('data-videos') || '[]');
    const assessments = JSON.parse(element.getAttribute('data-assessments') || '[]');
    
    showContentDetails(moduleId, moduleTitle, videoCount, assessmentCount, hasFile, fileName, videos, assessments);
}

// Content details modal function
function showContentDetails(moduleId, moduleTitle, videoCount, assessmentCount, hasFile, fileName, videos, assessments) {
    // Check if modal exists
    const modal = document.getElementById('contentDetailsModal');
    if (!modal) {
        console.error('Content details modal not found!');
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
    const modalElement = document.getElementById('contentDetailsModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        console.error('Modal element not found!');
    }
}

// Module management functions - Global scope
function viewModule(moduleId) {
    
    // Find the module data
    const allModules = <?php echo json_encode($all_modules ?: []); ?>;
    const module = allModules.find(m => m.id == moduleId);
    
    // View module function
    
    if (!module) {
        showErrorMessage('Module not found');
        return;
    }
    
    // Create modal content
    const modalContent = `
        <div class="modal fade" id="viewModuleModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-0" style="background-color: var(--primary-color); color: white;">
                        <h5 class="modal-title">
                            <i class="bi bi-eye me-2"></i>Module Details
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <h6 class="fw-bold text-primary">${module.module_title || 'Untitled Module'}</h6>
                                <p class="text-muted mb-3">${module.module_description || 'No description available'}</p>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Module Information</h6>
                                        <p class="mb-2"><strong>Course:</strong> ${module.course_title || 'Unknown'}</p>
                                        <p class="mb-2"><strong>Order:</strong> ${module.module_order || 'N/A'}</p>
                                        <p class="mb-2"><strong>Status:</strong> 
                                            <span class="badge ${(!module.is_locked || module.is_locked == 0) ? 'bg-success' : 'bg-danger'}">
                                                ${(!module.is_locked || module.is_locked == 0) ? 'Unlocked' : 'Locked'}
                                            </span>
                                        </p>
                                        <p class="mb-0"><strong>Files:</strong> ${module.video_count || 0} videos, ${module.assessment_count || 0} assessments</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="editModule(${moduleId})" data-bs-dismiss="modal">
                            <i class="bi bi-pencil me-1"></i>Edit Module
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('viewModuleModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalContent);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('viewModuleModal'));
    modal.show();
    
    // Clean up when modal is hidden
    document.getElementById('viewModuleModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Global scope functions
function toggleModuleStatus(moduleId, shouldLock) {
    const action = shouldLock ? 'lock' : 'unlock';
    
    if (!confirm(`Are you sure you want to ${action} this module?`)) {
        return;
    }
    
    // Find the module data to get course_id
    const allModules = <?php echo json_encode($all_modules ?: []); ?>;
    const module = allModules.find(m => m.id == moduleId);
    
    if (!module) {
        showErrorMessage('Module not found');
        return;
    }
    
    const courseId = module.course_id;
    const isLocked = shouldLock ? 1 : 0;
    
    // Show loading state
    const button = document.querySelector(`button[onclick*="toggleModuleStatus(${moduleId}")]`);
    const originalContent = button ? button.innerHTML : '';
    if (button) button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    
    // Make AJAX request
    fetch('modules.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'toggle_module_lock',
            module_id: moduleId,
            course_id: courseId,
            is_locked: isLocked,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            // Update the button state
            updateModuleStatusButton(moduleId, isLocked);
            // Reload the page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showErrorMessage(data.message);
            // Restore button state
            if (button) button.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('An error occurred while updating the module status.');
        // Restore button state
        if (button) button.innerHTML = originalContent;
    });
}

function updateModuleStatusButton(moduleId, isLocked) {
    const button = document.querySelector(`button[onclick*="toggleModuleStatus(${moduleId}")]`);
    if (!button) return;
    
    if (isLocked) {
        button.className = button.className.replace('btn-outline-warning', 'btn-outline-success');
        button.innerHTML = '<i class="bi bi-unlock"></i>';
        button.title = 'Unlock';
        button.setAttribute('onclick', `toggleModuleStatus(${moduleId}, false)`);
    } else {
        button.className = button.className.replace('btn-outline-success', 'btn-outline-warning');
        button.innerHTML = '<i class="bi bi-lock"></i>';
        button.title = 'Lock';
        button.setAttribute('onclick', `toggleModuleStatus(${moduleId}, true)`);
    }
}

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
        const button = event ? event.target.closest('button') : document.querySelector(`button[onclick*="toggleModuleLock('${moduleId}')]`);
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

// Global scope functions
function deleteModule(moduleId) {
    // Find the module data
    const allModules = <?php echo json_encode($all_modules ?: []); ?>;
    const module = allModules.find(m => m.id == moduleId);
    
    if (!module) {
        showErrorMessage('Module not found');
        return;
    }
    
    const moduleTitle = module.module_title || 'Untitled Module';
    const courseId = module.course_id;
    
    if (!confirm(`Are you sure you want to delete the module "${moduleTitle}"? This action cannot be undone.`)) {
        return;
    }
    
    // Show loading state
    const button = document.querySelector(`button[onclick*="deleteModule(${moduleId}")]`);
    const originalContent = button ? button.innerHTML : '';
    if (button) {
        button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        button.disabled = true;
    }
    
    // Make AJAX request
    fetch('modules.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'delete_module',
            module_id: moduleId,
            course_id: courseId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            // Remove the module card from the DOM
            const moduleCard = document.querySelector(`button[onclick*="deleteModule(${moduleId}")]`)?.closest('.col-lg-4');
            if (moduleCard) {
                moduleCard.remove();
            }
            // Update module count
            updateModuleCount();
        } else {
            showErrorMessage(data.message);
            // Restore button state
            if (button) {
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('An error occurred while deleting the module.');
        // Restore button state
        if (button) {
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    });
}

function deleteModuleOld(moduleId, moduleTitle, courseId, event) {
    // Prevent event bubbling and default behavior
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    if (confirm('Are you sure you want to delete the module "' + moduleTitle + '"? This action cannot be undone.')) {
        document.getElementById('delete_module_id').value = moduleId;
        document.getElementById('delete_course_id').value = courseId;
        
        // Show loading state
        const button = event ? event.target.closest('button') : document.querySelector(`button[onclick*="deleteModule('${moduleId}')]`);
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

// Global scope functions
function editModule(moduleId) {
    // Find the module data
    const allModules = <?php echo json_encode($all_modules ?: []); ?>;
    const module = allModules.find(m => m.id == moduleId);
    
    if (!module) {
        showErrorMessage('Module not found');
        return;
    }
    
    // Populate the edit form
    document.getElementById('edit_module_id').value = moduleId;
    document.getElementById('edit_module_title').value = module.module_title || '';
    document.getElementById('edit_description').value = module.module_description || '';
    document.getElementById('edit_course_id').value = module.course_id;
    document.getElementById('edit_module_order').value = module.module_order || 1;
    document.getElementById('edit_is_locked').checked = module.is_locked == 1;
    
    // Show the edit modal
    const editModal = new bootstrap.Modal(document.getElementById('editModuleModal'));
    editModal.show();
}

function editModuleOld(moduleId, moduleTitle, description, courseId, moduleOrder, isLocked, fileName, event) {
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
        
        // Module created successfully
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
        showErrorMessage('Please select modules first.');
        return;
    }
    
    const action = prompt('Choose action: delete, lock, unlock, or export');
    if (!action) return;
    
    const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
    
    switch(action.toLowerCase()) {
        case 'delete':
            bulkDelete();
            break;
        case 'lock':
            bulkLock();
            break;
        case 'unlock':
            bulkUnlock();
            break;
        case 'export':
            exportSelectedModules(selectedIds);
            break;
        default:
            showErrorMessage('Invalid action. Please choose: delete, lock, unlock, or export');
    }
}

function exportModules() {
    const allModules = <?php echo json_encode($all_modules ?: []); ?>;
    exportModulesData(allModules, 'all_modules');
}

function exportSelectedModules(moduleIds) {
    const allModules = <?php echo json_encode($all_modules ?: []); ?>;
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

function downloadModulesAsZip() {
    // Get all modules with files
    const allModules = <?php echo json_encode($all_modules ?: []); ?>;
    const modulesWithFiles = allModules.filter(module => module.file && module.file.filename);
    
    if (modulesWithFiles.length === 0) {
        showErrorMessage('No modules with files found to download.');
        return;
    }
    
    // Create a form to submit to a PHP script that will create the ZIP
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'download_modules_zip.php';
    form.target = '_blank';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '<?php echo CSRF_TOKEN_NAME; ?>';
    csrfInput.value = '<?php echo generateCSRFToken(); ?>';
    form.appendChild(csrfInput);
    
    // Add module IDs
    modulesWithFiles.forEach(module => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'module_ids[]';
        input.value = module.id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function manageModuleVideos(moduleId) {
    // Navigate to the module videos management page
    window.location.href = `module_videos.php?module_id=${moduleId}`;
}

// Set default module order when course is selected
document.addEventListener('DOMContentLoaded', function() {
    const createCourseSelect = document.getElementById('create_course_id');
    const createOrderInput = document.getElementById('create_module_order');
    const createModuleTitle = document.getElementById('create_module_title');
    
    if (createCourseSelect && createOrderInput && createModuleTitle) {
        // Set initial order when page loads
        setInitialOrder();
        
        // Auto-set order when course is selected
        createCourseSelect.addEventListener('change', function() {
            const courseId = this.value;
            if (courseId) {
                setOrderForCourse(courseId);
            } else {
                createOrderInput.value = '';
                createOrderInput.placeholder = 'Select a course first';
            }
        });
        
        // Auto-set order when modal opens
        const createModal = document.getElementById('createModuleModal');
        if (createModal) {
            createModal.addEventListener('show.bs.modal', function() {
                // Reset form
                createModuleTitle.value = '';
                createCourseSelect.value = '';
                createOrderInput.value = '';
                createOrderInput.placeholder = 'Select a course first';
                
                // If there's only one course, auto-select it
                if (createCourseSelect.options.length === 2) { // 1 option + empty option
                    createCourseSelect.value = createCourseSelect.options[1].value;
                    setOrderForCourse(createCourseSelect.value);
                }
            });
        }
        
        // Add form validation before submission
        const createForm = createModal?.querySelector('form');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                e.preventDefault();
                validateAndSubmitModule();
            });
        }
    }
    
    function setInitialOrder() {
        const courseId = createCourseSelect.value;
        if (courseId) {
            setOrderForCourse(courseId);
        } else {
            createOrderInput.placeholder = 'Select a course first';
        }
    }
    
    function setOrderForCourse(courseId) {
        // Get the next available order for this course
        const courseModules = <?php echo json_encode($course_modules_map); ?>;
        if (courseModules[courseId]) {
            const nextOrder = getNextAvailableOrder(courseModules[courseId]);
            createOrderInput.value = nextOrder;
            createOrderInput.placeholder = `Next order: ${nextOrder}`;
            
            // Add visual feedback
            createOrderInput.classList.add('border-success');
            setTimeout(() => {
                createOrderInput.classList.remove('border-success');
            }, 2000);
        } else {
            createOrderInput.value = 1;
            createOrderInput.placeholder = 'First module (Order: 1)';
        }
    }
    
    function validateAndSubmitModule() {
        const moduleTitle = createModuleTitle.value.trim();
        const courseId = createCourseSelect.value;
        const courseModules = <?php echo json_encode($course_modules_map); ?>;
        
        // Check if course is selected
        if (!courseId) {
            showAlert('Please select a course first.', 'warning');
            createCourseSelect.focus();
            return;
        }
        
        // Check if module title is provided
        if (!moduleTitle) {
            showAlert('Please enter a module title.', 'warning');
            createModuleTitle.focus();
            return;
        }
        
        // Check for similar module names in the selected course
        if (courseModules[courseId]) {
            const similarModules = courseModules[courseId].filter(module => {
                const existingTitle = module.module_title.toLowerCase().trim();
                const newTitle = moduleTitle.toLowerCase();
                
                // Check for exact match
                if (existingTitle === newTitle) {
                    return true;
                }
                
                // Check for similar names (80% similarity)
                const similarity = calculateSimilarity(existingTitle, newTitle);
                return similarity > 0.8;
            });
            
            if (similarModules.length > 0) {
                const similarTitles = similarModules.map(m => m.module_title).join(', ');
                const message = `Warning: Similar module names found in this course:\n\n${similarTitles}\n\nDo you want to continue creating this module?`;
                
                if (confirm(message)) {
                    submitModuleForm();
                }
                return;
            }
        }
        
        // No similar modules found, proceed with submission
        submitModuleForm();
    }
    
    function calculateSimilarity(str1, str2) {
        const longer = str1.length > str2.length ? str1 : str2;
        const shorter = str1.length > str2.length ? str2 : str1;
        
        if (longer.length === 0) {
            return 1.0;
        }
        
        const editDistance = levenshteinDistance(longer, shorter);
        return (longer.length - editDistance) / longer.length;
    }
    
    function levenshteinDistance(str1, str2) {
        const matrix = [];
        
        for (let i = 0; i <= str2.length; i++) {
            matrix[i] = [i];
        }
        
        for (let j = 0; j <= str1.length; j++) {
            matrix[0][j] = j;
        }
        
        for (let i = 1; i <= str2.length; i++) {
            for (let j = 1; j <= str1.length; j++) {
                if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1];
                } else {
                    matrix[i][j] = Math.min(
                        matrix[i - 1][j - 1] + 1,
                        matrix[i][j - 1] + 1,
                        matrix[i - 1][j] + 1
                    );
                }
            }
        }
        
        return matrix[str2.length][str1.length];
    }
    
    function submitModuleForm() {
        const form = document.querySelector('#createModuleModal form');
        if (form) {
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Creating...';
            submitBtn.disabled = true;
            
            // Submit the form
            form.submit();
        }
    }
    
    function showAlert(message, type = 'info') {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert-temp');
        existingAlerts.forEach(alert => alert.remove());
        
        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-temp`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
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
    
    // Enhanced scrolling behavior for modules table
    function enhanceModulesTableScrolling() {
        const tableContainer = document.querySelector('.modules-table-container');
        
        if (tableContainer) {
            // Add smooth scrolling behavior
            tableContainer.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const cardContainer = tableContainer.closest('.card');
            if (cardContainer) {
                addModulesTableScrollIndicators(tableContainer, cardContainer);
            }
        }
    }
    
    // Add scroll indicators to modules table
    function addModulesTableScrollIndicators(scrollContainer, cardContainer) {
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = 'modules-scroll-indicator';
        scrollIndicator.innerHTML = `
            <div class="modules-scroll-indicator-content">
                <i class="bi bi-chevron-up modules-scroll-indicator-top"></i>
                <i class="bi bi-chevron-down modules-scroll-indicator-bottom"></i>
            </div>
        `;
        
        cardContainer.style.position = 'relative';
        cardContainer.appendChild(scrollIndicator);
        
        // Update scroll indicators based on scroll position
        function updateModulesScrollIndicators() {
            const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
            const isAtTop = scrollContainer.scrollTop === 0;
            const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
            
            if (isScrollable) {
                scrollIndicator.classList.add('show');
                scrollIndicator.querySelector('.modules-scroll-indicator-top').classList.toggle('hide', isAtTop);
                scrollIndicator.querySelector('.modules-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
            } else {
                scrollIndicator.classList.remove('show');
            }
        }
        
        // Initial check
        updateModulesScrollIndicators();
        
        // Update on scroll
        scrollContainer.addEventListener('scroll', updateModulesScrollIndicators);
        
        // Update on resize
        window.addEventListener('resize', updateModulesScrollIndicators);
    }
    
    // Initialize enhanced modules table scrolling
    enhanceModulesTableScrolling();
    
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
    
    // Extract all valid order numbers
    const orders = modules
        .map(m => parseInt(m.module_order) || 0)
        .filter(o => o > 0);
    
    if (orders.length === 0) {
        return 1;
    }
    
    // Find the maximum order and add 1
    const maxOrder = Math.max(...orders);
    return maxOrder + 1;
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
    const moduleCards = document.querySelectorAll('.module-card');
    const count = moduleCards.length;
    
    // Update the badge in the header
    const badge = document.querySelector('.badge[style*="background-color: var(--main-green)"]');
    if (badge) {
        badge.innerHTML = `<i class="bi bi-folder me-1"></i>${count} Modules`;
    }
}

// Global scope functions - Bulk actions
function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.module-checkbox:checked');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    
    if (checkboxes.length > 0) {
        bulkActionsBar.style.display = 'block';
        selectedCount.textContent = checkboxes.length;
    } else {
        bulkActionsBar.style.display = 'none';
    }
}

function showBulkActions() {
    const checkboxes = document.querySelectorAll('.module-checkbox');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    
    if (checkboxes.length > 0) {
        bulkActionsBar.style.display = 'block';
        updateBulkActions();
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.module-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    updateBulkActions();
}

function bulkDelete() {
    const checkboxes = document.querySelectorAll('.module-checkbox:checked');
    if (checkboxes.length === 0) {
        showErrorMessage('No modules selected');
        return;
    }
    
    const moduleIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (!confirm(`Are you sure you want to delete ${moduleIds.length} selected modules? This action cannot be undone.`)) {
        return;
    }
    
    // Show loading state
    const button = document.querySelector('button[onclick="bulkDelete()"]');
    const originalContent = button ? button.innerHTML : '';
    if (button) {
        button.innerHTML = '<i class="bi bi-hourglass-split"></i> Deleting...';
        button.disabled = true;
    }
    
    // Make AJAX request
    fetch('modules.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'bulk_delete_modules',
            module_ids: JSON.stringify(moduleIds),
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            // Remove selected module cards
            checkboxes.forEach(checkbox => {
                const moduleCard = checkbox.closest('.col-lg-4');
                if (moduleCard) {
                    moduleCard.remove();
                }
            });
            // Clear selection
            clearSelection();
            // Update module count
            updateModuleCount();
        } else {
            showErrorMessage(data.message);
        }
        // Restore button state
        if (button) {
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('An error occurred while deleting modules.');
        // Restore button state
        if (button) {
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    });
}

function bulkUnlock() {
    const checkboxes = document.querySelectorAll('.module-checkbox:checked');
    if (checkboxes.length === 0) {
        showErrorMessage('No modules selected');
        return;
    }
    
    const moduleIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (!confirm(`Are you sure you want to unlock ${moduleIds.length} selected modules?`)) {
        return;
    }
    
    bulkUpdateStatus(moduleIds, 0, 'unlock');
}

function bulkLock() {
    const checkboxes = document.querySelectorAll('.module-checkbox:checked');
    if (checkboxes.length === 0) {
        showErrorMessage('No modules selected');
        return;
    }
    
    const moduleIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (!confirm(`Are you sure you want to lock ${moduleIds.length} selected modules?`)) {
        return;
    }
    
    bulkUpdateStatus(moduleIds, 1, 'lock');
}

function bulkUpdateStatus(moduleIds, isLocked, action) {
    // Show loading state
    const button = document.querySelector(`button[onclick="bulk${action.charAt(0).toUpperCase() + action.slice(1)}()"]`);
    const originalContent = button ? button.innerHTML : '';
    if (button) {
        button.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...';
        button.disabled = true;
    }
    
    // Make AJAX request
    fetch('modules.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'bulk_update_module_status',
            module_ids: JSON.stringify(moduleIds),
            is_locked: isLocked,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            // Reload the page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showErrorMessage(data.message);
        }
        // Restore button state
        if (button) {
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('An error occurred while updating modules.');
        // Restore button state
        if (button) {
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    });
}
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
/* Modern Module Management Styles */
:root {
    --primary-color: #28a745;
    --primary-dark: #1e7e34;
    --card-bg: #ffffff;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --text-muted: #adb5bd;
    --border-color: #dee2e6;
    --shadow: 0 2px 4px rgba(0,0,0,0.1);
    --hover-shadow: 0 4px 8px rgba(0,0,0,0.15);
    --bg-light: #f8f9fa;
    --bg-secondary: #e9ecef;
    --border-radius: 12px;
    --transition: all 0.3s ease;
}

/* Modern Header */
.modules-management-header {
    background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modules-management-header h1 {
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.modules-management-header .opacity-90 {
    opacity: 0.9;
}

.module-stats {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.module-stat-item {
    text-align: center;
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem 1.5rem;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.module-stat-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
}

.module-stat-number {
    display: block;
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.module-stat-label {
    display: block;
    font-size: 0.875rem;
    opacity: 0.95;
    margin-top: 0.5rem;
    font-weight: 500;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

/* Enhanced Filter Card */
.filter-card {
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.filter-header {
    background: var(--bg-light);
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
    color: var(--text-primary);
}

.filter-card .form-label {
    color: var(--text-primary);
    font-weight: 600;
}

.filter-card .form-control,
.filter-card .form-select {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    transition: var(--transition);
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* Modules Container */
/* Modules Grid */
.modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    padding: 1rem 0;
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: hidden;
}

@media (max-width: 768px) {
    .modules-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        max-height: 60vh;
    }
}

@media (max-width: 576px) {
    .modules-grid {
        max-height: 50vh;
    }
}

/* Custom scrollbar for modules grid */
.modules-grid::-webkit-scrollbar {
    width: 8px;
}

.modules-grid::-webkit-scrollbar-track {
    background: #f1f3f4;
    border-radius: 4px;
}

.modules-grid::-webkit-scrollbar-thumb {
    background: #c1c8cd;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.modules-grid::-webkit-scrollbar-thumb:hover {
    background: #a8b2ba;
}

/* Firefox scrollbar styling */
.modules-grid {
    scrollbar-width: thin;
    scrollbar-color: #c1c8cd #f1f3f4;
}


/* Module Cards */
/* Modern Module Cards */
.module-card {
    background: var(--card-bg);
    border-radius: 15px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
    height: 100%;
}

.module-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--hover-shadow);
}

.module-card-preview {
    position: relative;
    overflow: hidden;
    border-radius: 15px 15px 0 0;
    height: 200px;
    background: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
}

.module-icon {
    font-size: 4rem;
    color: rgba(255, 255, 255, 0.8);
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}


.module-card-body {
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.9);
    height: calc(100% - 200px);
    display: flex;
    flex-direction: column;
}

.module-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.module-description {
    color: var(--text-muted);
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 1rem;
    flex-grow: 1;
}

.module-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 8px;
}

.module-stats {
    display: flex;
    gap: 1rem;
}

.module-stat {
    text-align: center;
}

.module-stat-number {
    display: block;
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--main-green);
}

.module-stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.module-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.module-action-btn {
    flex: 1;
    min-width: 80px;
    font-size: 0.8rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border: none;
    transition: all 0.3s ease;
    text-decoration: none;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
}

.module-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    opacity: 0.9;
}

.btn-view {
    background: #27ae60;
    color: white;
}

.btn-edit {
    background: #f1c40f;
    color: white;
}

.btn-videos {
    background: #3498db;
    color: white;
}

.btn-assessments {
    background: #f39c12;
    color: white;
}

.btn-toggle {
    background: #e67e22;
    color: white;
}

.btn-delete {
    background: #e74c3c;
    color: white;
}

.module-status {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-locked {
    background: rgba(255, 107, 107, 0.9);
    color: white;
}

.status-unlocked {
    background: rgba(67, 233, 123, 0.9);
    color: white;
}


.module-status {
    margin-bottom: 1rem;
}

.status-badge {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
}

.status-unlocked {
    background-color: #d4edda;
    color: #155724;
}

.status-locked {
    background-color: #f8d7da;
    color: #721c24;
}

.module-card-footer {
    background: rgba(255, 255, 255, 0.8);
    border-top: 1px solid rgba(0,0,0,0.1);
    padding: 1rem;
    backdrop-filter: blur(10px);
}

.module-actions .btn-group-sm .btn {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
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
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 2rem;
}

/* Bulk Actions Bar */
.bulk-actions-bar {
    background: var(--primary-color);
    color: white;
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
    box-shadow: var(--shadow);
}

.bulk-actions-bar .btn {
    color: white;
    border-color: rgba(255,255,255,0.3);
}

.bulk-actions-bar .btn:hover {
    background-color: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.5);
}

/* Read-only input styling */
.form-control[readonly] {
    background-color: var(--bg-light);
    border-color: var(--border-color);
    color: var(--text-primary);
    cursor: not-allowed;
}

.form-control[readonly]:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* Auto-assigned order feedback */
.border-success {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .module-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .module-stat-item {
        text-align: center;
    }
    
    .modules-management-header {
        padding: 1.5rem 0;
    }
    
    .module-actions .btn-group-sm {
        flex-direction: column;
    }
    
    .module-actions .btn-group-sm .btn {
        margin-bottom: 0.25rem;
    }
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

/* Enhanced Modules Table Scrolling */
.modules-table-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    position: relative;
}

/* Custom scrollbar for modules table */
.modules-table-container::-webkit-scrollbar {
    width: 8px;
}

.modules-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.modules-table-container::-webkit-scrollbar-thumb {
    background: #2E5E4E;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.modules-table-container::-webkit-scrollbar-thumb:hover {
    background: #1e3d32;
}

/* Firefox scrollbar styling */
.modules-table-container {
    scrollbar-width: thin;
    scrollbar-color: #2E5E4E #f1f1f1;
}

/* Enhanced table styling */
.modules-table-container .table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.modules-table-container .table thead th {
    position: sticky;
    top: 0;
    background: var(--main-green);
    z-index: 10;
    border-bottom: 2px solid #1e3d32;
    font-weight: 600;
    color: white;
    padding: 16px 12px;
}

.modules-table-container .table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f0f0f0;
}

.modules-table-container .table tbody tr:hover {
    background-color: rgba(46, 94, 78, 0.05);
    transform: translateX(3px);
    box-shadow: 0 2px 8px rgba(46, 94, 78, 0.1);
}

.modules-table-container .table tbody td {
    padding: 16px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

/* Enhanced button styling */
.modules-table-container .btn-group .btn {
    padding: 6px 12px;
    font-size: 0.875rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    margin: 0 2px;
}

.modules-table-container .btn-group .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Badge enhancements */
.modules-table-container .badge {
    font-size: 0.75rem;
    padding: 6px 10px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.modules-table-container .badge:hover {
    transform: scale(1.05);
}

/* Module icon styling */
.modules-table-container .table tbody td .module-icon i {
    transition: all 0.3s ease;
}

.modules-table-container .table tbody tr:hover .module-icon i {
    transform: scale(1.1);
    color: var(--main-green) !important;
}

/* Scroll indicators for modules table */
.modules-scroll-indicator {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 15;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modules-scroll-indicator.show {
    opacity: 1;
}

.modules-scroll-indicator-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.modules-scroll-indicator i {
    background: rgba(46, 94, 78, 0.8);
    color: white;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(46, 94, 78, 0.3);
}

.modules-scroll-indicator-top.hide,
.modules-scroll-indicator-bottom.hide {
    opacity: 0.3;
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

/* Mobile responsiveness for modules table */
@media (max-width: 991.98px) {
    .modules-table-container {
        max-height: 450px;
    }
    
    .modules-table-container .table thead th,
    .modules-table-container .table tbody td {
        padding: 12px 8px;
        font-size: 0.9rem;
    }
}

@media (max-width: 575.98px) {
    .modules-table-container {
        max-height: 350px;
    }
    
    .modules-table-container .table thead th,
    .modules-table-container .table tbody td {
        padding: 8px 4px;
        font-size: 0.85rem;
    }
    
    .modules-table-container .btn-group .btn {
        padding: 4px 8px;
        font-size: 0.75rem;
    }
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
