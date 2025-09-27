<?php
$page_title = 'Course Management';
require_once '../config/config.php';
requireRole('teacher');
require_once '../includes/header.php';

// Helper function to get next available module order
function getNextAvailableModuleOrder($modules) {
    if (empty($modules)) {
        return 1;
    }
    
    $existing_orders = array_column($modules, 'module_order');
    $existing_orders = array_filter($existing_orders, function($order) {
        return is_numeric($order) && $order > 0;
    });
    
    if (empty($existing_orders)) {
        return 1;
    }
    
    // Find the next available order number
    $max_order = max($existing_orders);
    $next_order = $max_order + 1;
    
    // Check for gaps in the sequence
    for ($i = 1; $i <= $max_order; $i++) {
        if (!in_array($i, $existing_orders)) {
            return $i;
        }
    }
    
    return $next_order;
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

// Helper functions for file handling
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
    $filename = $module_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
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

function deleteModuleFile($filename) {
    $file_path = '../uploads/modules/' . $filename;
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return true; // File doesn't exist, consider it deleted
}
?>

<style>
/* Modern Course Management Styles */
:root {
    --primary-color: #28a745;
    --primary-dark: #1e7e34;
    --main-green: #2E5E4E;
    --accent-green: #7DCB80;
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
.course-management-header {
    background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.course-management-header h1 {
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.course-management-header .opacity-90 {
    opacity: 0.9;
}

/* Improved Stats Cards */
.stats-card {
    background: #ffffff;
    border: 2px solid #e9ecef;
    border-radius: 1.2rem;
    padding: 2rem 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: #2E5E4E;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2E5E4E, #7DCB80);
}

.stats-card i {
    color: #2E5E4E;
    margin-bottom: 1rem;
    font-size: 2.5rem;
}

.stats-card h3 {
    color: #2E5E4E;
    font-weight: 700;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.stats-card p {
    color: #6c757d;
    font-weight: 500;
    margin-bottom: 0;
    font-size: 0.95rem;
}

/* Different colors for different stats */
.stats-card.students {
    border-color: #28a745;
}

.stats-card.students::before {
    background: linear-gradient(90deg, #28a745, #20c997);
}

.stats-card.students i,
.stats-card.students h3 {
    color: #28a745;
}

.stats-card.modules {
    border-color: #007bff;
}

.stats-card.modules::before {
    background: linear-gradient(90deg, #007bff, #17a2b8);
}

.stats-card.modules i,
.stats-card.modules h3 {
    color: #007bff;
}

.stats-card.videos {
    border-color: #fd7e14;
}

.stats-card.videos::before {
    background: linear-gradient(90deg, #fd7e14, #ffc107);
}

.stats-card.videos i,
.stats-card.videos h3 {
    color: #fd7e14;
}

.stats-card.assessments {
    border-color: #dc3545;
}

.stats-card.assessments::before {
    background: linear-gradient(90deg, #dc3545, #e83e8c);
}

.stats-card.assessments i,
.stats-card.assessments h3 {
    color: #dc3545;
}

.stats-card.files {
    border-color: #6f42c1;
}

.stats-card.files::before {
    background: linear-gradient(90deg, #6f42c1, #e83e8c);
}

.stats-card.files i,
.stats-card.files h3 {
    color: #6f42c1;
}

/* Scrollable Modules Container */
.modules-scrollable-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 1rem;
    margin: -1rem;
    border-radius: 0 0 15px 15px;
}

/* Custom scrollbar for modules container */
.modules-scrollable-container::-webkit-scrollbar {
    width: 8px;
}

.modules-scrollable-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.modules-scrollable-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.modules-scrollable-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Modules Grid */
.modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    padding: 1rem 0;
}

/* Enhanced Add Module Button Styles */
.btn-success.btn-lg {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    position: relative;
    overflow: hidden;
}

.btn-success.btn-lg:hover {
    background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
    transform: translateY(-1px);
}

.btn-success.btn-xl {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    box-shadow: 0 6px 25px rgba(40, 167, 69, 0.4);
    position: relative;
    overflow: hidden;
}

.btn-success.btn-xl:hover {
    background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
    box-shadow: 0 8px 30px rgba(40, 167, 69, 0.5);
    transform: translateY(-2px);
}

/* Add Module Button - Bottom Right Corner of Card */
.floating-add-btn {
    position: absolute;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    color: white;
    font-size: 20px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.floating-add-btn:hover {
    background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
    box-shadow: 0 6px 25px rgba(40, 167, 69, 0.6);
    transform: translateY(-2px) scale(1.05);
    color: white;
}

.floating-add-btn:active {
    transform: translateY(0) scale(0.95);
}

/* Pulse animation for attention */
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}

.btn-success.btn-lg.pulse {
    animation: pulse 2s infinite;
}

@media (max-width: 768px) {
    .modules-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .modules-scrollable-container {
        max-height: 500px;
        padding: 0.5rem;
        margin: -0.5rem;
    }
    
    .floating-add-btn {
        width: 45px;
        height: 45px;
        font-size: 18px;
        bottom: 15px;
        right: 15px;
    }
}

/* Module Cards */
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

.btn-files {
    background: #9b59b6;
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
    z-index: 2;
}

.status-unlocked {
    background: rgba(255, 193, 7, 0.9);
    color: #212529;
    font-weight: 700;
}

.status-locked {
    background: rgba(220, 53, 69, 0.9);
    color: white;
    font-weight: 700;
}

/* Clickable Status Styling */
.clickable-status {
    cursor: pointer;
    transition: all 0.3s ease;
    user-select: none;
}

.clickable-status:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.clickable-status:active {
    transform: scale(0.95);
}

.clickable-status.status-unlocked:hover {
    background: rgba(255, 193, 7, 1);
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
    color: #000;
}

.clickable-status.status-locked:hover {
    background: rgba(220, 53, 69, 1);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    color: white;
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

/* Alert Enhancements */
.alert {
    border-radius: var(--border-radius);
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* File Display Styles */
.file-item {
    margin-bottom: 1rem;
}

.file-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.file-card:hover {
    background: #e9ecef;
    border-color: #9b59b6;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.file-icon {
    font-size: 2.5rem;
    color: #9b59b6;
    margin-right: 1rem;
    min-width: 60px;
    text-align: center;
}

.file-details {
    flex-grow: 1;
    margin-right: 1rem;
}

.file-name {
    margin-bottom: 0.5rem;
    color: #2c3e50;
    font-weight: 600;
    word-break: break-word;
}

.file-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.875rem;
    color: #6c757d;
}

.file-size {
    font-weight: 500;
}

.file-date {
    font-style: italic;
}

.file-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.file-actions .btn {
    white-space: nowrap;
}

/* File Preview Modal Styles */
#filePreviewModal .modal-dialog {
    max-width: 90vw;
    width: 90vw;
}

#filePreviewModal .modal-body {
    background: #f8f9fa;
}

#filePreviewContent iframe {
    border-radius: 0.375rem;
}

#filePreviewContent img {
    border-radius: 0.375rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

#filePreviewContent video {
    border-radius: 0.375rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

#filePreviewContent audio {
    border-radius: 0.375rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .module-actions {
        flex-direction: column;
    }
    
    .module-action-btn {
        margin-bottom: 0.25rem;
    }
    
    .file-card {
        flex-direction: column;
        text-align: center;
    }
    
    .file-icon {
        margin-right: 0;
        margin-bottom: 1rem;
    }
    
    .file-details {
        margin-right: 0;
        margin-bottom: 1rem;
    }
    
    .file-actions {
        justify-content: center;
    }
}
</style>

<?php

$course_id = (int)($_GET['id'] ?? 0);

// Verify teacher owns this course
$stmt = $db->prepare("
    SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
    FROM courses c
    JOIN academic_periods ap ON c.academic_period_id = ap.id
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ? AND c.teacher_id = ?
");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php?error=Course not found or access denied.');
    exit;
}





$message = '';
$message_type = '';

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
                $module_description = sanitizeInput($_POST['module_description'] ?? '');
                $module_order = (int)($_POST['module_order'] ?? 1);
                $is_locked = isset($_POST['is_locked']) ? 1 : 0;
                $unlock_score = (int)($_POST['unlock_score'] ?? 70);
                
                if (empty($module_title)) {
                    $message = 'Module title is required.';
                    $message_type = 'danger';
                } else {
                    // Get current modules JSON
                    $current_modules = $course['modules'] ? json_decode($course['modules'], true) : [];
                    if (!is_array($current_modules)) {
                        $current_modules = [];
                    }
                    
                    // Validate module order uniqueness
                    if (!validateModuleOrder($current_modules, $module_order)) {
                        $message = 'Module order ' . $module_order . ' is already taken. Please choose a different order number.';
                        $message_type = 'danger';
                    } else {
                        // Create new module
                    $new_module = [
                        'id' => uniqid('mod_'),
                        'module_title' => $module_title,
                        'module_description' => $module_description,
                        'module_order' => $module_order,
                        'is_locked' => $is_locked,
                        'unlock_score' => $unlock_score,
                        'videos' => [],
                        'assessments' => [],
                        'files' => [],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $current_modules[] = $new_module;
                    
                    // Update course with new modules JSON
                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                    $stmt->execute([json_encode($current_modules), $course_id]);
                    
                    // Refresh course data to show updated modules
                    $stmt = $db->prepare("
                        SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
                        FROM courses c
                        JOIN academic_periods ap ON c.academic_period_id = ap.id
                        JOIN users u ON c.teacher_id = u.id
                        WHERE c.id = ? AND c.teacher_id = ?
                    ");
                    $stmt->execute([$course_id, $_SESSION['user_id']]);
                    $course = $stmt->fetch();
                    
                    $message = 'Module created successfully.';
                    $message_type = 'success';
                    }
                }
                break;
                
            case 'update_module':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                $module_title = sanitizeInput($_POST['module_title'] ?? '');
                $module_description = sanitizeInput($_POST['module_description'] ?? '');
                $module_order = (int)($_POST['module_order'] ?? 1);
                $is_locked = isset($_POST['is_locked']) ? 1 : 0;
                $unlock_score = (int)($_POST['unlock_score'] ?? 70);
                
                if (empty($module_title)) {
                    $message = 'Module title is required.';
                    $message_type = 'danger';
                } else {
                    // Get current modules JSON
                    $current_modules = $course['modules'] ? json_decode($course['modules'], true) : [];
                    if (!is_array($current_modules)) {
                        $current_modules = [];
                    }
                    
                    // Validate module order uniqueness
                    if (!validateModuleOrder($current_modules, $module_order, $module_id)) {
                        $message = 'Module order ' . $module_order . ' is already taken. Please choose a different order number.';
                        $message_type = 'danger';
                    } else {
                        // Find and update the module
                    foreach ($current_modules as &$module) {
                        if ($module['id'] === $module_id) {
                            $module['module_title'] = $module_title;
                            $module['module_description'] = $module_description;
                            $module['module_order'] = $module_order;
                            $module['is_locked'] = $is_locked;
                            $module['unlock_score'] = $unlock_score;
                            $module['updated_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    
                    // Update course with updated modules JSON
                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                    $stmt->execute([json_encode($current_modules), $course_id]);
                    
                    // Refresh course data to show updated modules
                    $stmt = $db->prepare("
                        SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
                        FROM courses c
                        JOIN academic_periods ap ON c.academic_period_id = ap.id
                        JOIN users u ON c.teacher_id = u.id
                        WHERE c.id = ? AND c.teacher_id = ?
                    ");
                    $stmt->execute([$course_id, $_SESSION['user_id']]);
                    $course = $stmt->fetch();
                    
                    $message = 'Module updated successfully.';
                    $message_type = 'success';
                    }
                }
                break;
                
            case 'delete_module':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                
                // Get current modules JSON
                $current_modules = $course['modules'] ? json_decode($course['modules'], true) : [];
                if (!is_array($current_modules)) {
                    $current_modules = [];
                }
                
                // Find and delete associated files
                foreach ($current_modules as $module) {
                    if ($module['id'] === $module_id) {
                        if (isset($module['files']) && is_array($module['files'])) {
                            foreach ($module['files'] as $file) {
                                deleteModuleFile($file['filename']);
                            }
                        }
                        break;
                    }
                }
                
                // Remove the module
                $current_modules = array_filter($current_modules, function($module) use ($module_id) {
                    return $module['id'] !== $module_id;
                });
                
                // Update course with updated modules JSON
                $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                $stmt->execute([json_encode(array_values($current_modules)), $course_id]);
                
                // Refresh course data to show updated modules
                $stmt = $db->prepare("
                    SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
                    FROM courses c
                    JOIN academic_periods ap ON c.academic_period_id = ap.id
                    JOIN users u ON c.teacher_id = u.id
                    WHERE c.id = ? AND c.teacher_id = ?
                ");
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                $course = $stmt->fetch();
                
                $message = 'Module deleted successfully.';
                $message_type = 'success';
                break;
                
            case 'upload_file':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                
                if (empty($module_id)) {
                    $message = 'Module ID is required.';
                    $message_type = 'danger';
                    break;
                }
                
                // Get current modules JSON
                $current_modules = $course['modules'] ? json_decode($course['modules'], true) : [];
                if (!is_array($current_modules)) {
                    $current_modules = [];
                }
                
                // Find the module
                $module_found = false;
                foreach ($current_modules as &$module) {
                    if ($module['id'] === $module_id) {
                        $module_found = true;
                        
                        // Initialize files array if not exists
                        if (!isset($module['files']) || !is_array($module['files'])) {
                            $module['files'] = [];
                        }
                        
                        // Handle file upload
                        if (isset($_FILES['module_file']) && $_FILES['module_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                            $file_validation = validateModuleFile($_FILES['module_file']);
                            if (!$file_validation['success']) {
                                $message = $file_validation['message'];
                                $message_type = 'danger';
                                break 2;
                            }
                            
                            $upload_result = uploadModuleFile($_FILES['module_file'], $module_id);
                            if ($upload_result['success']) {
                                $module['files'][] = [
                                    'filename' => $upload_result['filename'],
                                    'original_name' => $upload_result['original_name'],
                                    'file_size' => $upload_result['file_size'],
                                    'uploaded_at' => date('Y-m-d H:i:s')
                                ];
                                
                                // Update course with updated modules JSON
                                $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                                $stmt->execute([json_encode($current_modules), $course_id]);
                                
                                // Refresh course data
                                $stmt = $db->prepare("
                                    SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
                                    FROM courses c
                                    JOIN academic_periods ap ON c.academic_period_id = ap.id
                                    JOIN users u ON c.teacher_id = u.id
                                    WHERE c.id = ? AND c.teacher_id = ?
                                ");
                                $stmt->execute([$course_id, $_SESSION['user_id']]);
                                $course = $stmt->fetch();
                                
                                $message = 'File uploaded successfully.';
                                $message_type = 'success';
                            } else {
                                $message = $upload_result['message'];
                                $message_type = 'danger';
                            }
                        } else {
                            $message = 'No file selected.';
                            $message_type = 'danger';
                        }
                        break;
                    }
                }
                
                if (!$module_found) {
                    $message = 'Module not found.';
                    $message_type = 'danger';
                }
                break;
                
            case 'delete_file':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                $filename = sanitizeInput($_POST['filename'] ?? '');
                
                if (empty($module_id) || empty($filename)) {
                    $message = 'Module ID and filename are required.';
                    $message_type = 'danger';
                    break;
                }
                
                // Get current modules JSON
                $current_modules = $course['modules'] ? json_decode($course['modules'], true) : [];
                if (!is_array($current_modules)) {
                    $current_modules = [];
                }
                
                // Find and update the module
                foreach ($current_modules as &$module) {
                    if ($module['id'] === $module_id) {
                        if (isset($module['files']) && is_array($module['files'])) {
                            // Find and remove the file
                            $module['files'] = array_filter($module['files'], function($file) use ($filename) {
                                return $file['filename'] !== $filename;
                            });
                            
                            // Delete the physical file
                            deleteModuleFile($filename);
                            
                            // Update course with updated modules JSON
                            $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                            $stmt->execute([json_encode($current_modules), $course_id]);
                            
                            // Refresh course data
                            $stmt = $db->prepare("
                                SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
                                FROM courses c
                                JOIN academic_periods ap ON c.academic_period_id = ap.id
                                JOIN users u ON c.teacher_id = u.id
                                WHERE c.id = ? AND c.teacher_id = ?
                            ");
                            $stmt->execute([$course_id, $_SESSION['user_id']]);
                            $course = $stmt->fetch();
                            
                            $message = 'File deleted successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'No files found in module.';
                            $message_type = 'danger';
                        }
                        break;
                    }
                }
                break;
        }
    }
}

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year_level']}{$section['section_name']}";
}

// Get course modules from JSON field
$modules = [];
if ($course['modules']) {
    $modules_data = json_decode($course['modules'], true);
    if (is_array($modules_data)) {
        foreach ($modules_data as $module) {
            // Add placeholder counts for now - these would need to be calculated from the JSON data
            $module['video_count'] = isset($module['videos']) ? count($module['videos']) : 0;
            $module['assessment_count'] = isset($module['assessments']) ? count($module['assessments']) : 0;
            $module['file_count'] = isset($module['files']) ? count($module['files']) : 0;
            $modules[] = $module;
        }
    }
}

// Get students in sections for this course
$stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.is_irregular, u.identifier
                      FROM sections s 
                      JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
                      WHERE JSON_SEARCH((SELECT sections FROM courses WHERE id = ?), 'one', s.id) IS NOT NULL 
                      ORDER BY u.last_name, u.first_name");
$stmt->execute([$course_id]);
$students = $stmt->fetchAll();

// Get course statistics (fix student count to use assigned sections)
$stats_sql = "
    SELECT 
        (SELECT COUNT(DISTINCT u.id)
         FROM sections s
         JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
         WHERE JSON_SEARCH((SELECT sections FROM courses WHERE id = ?), 'one', s.id) IS NOT NULL) as enrolled_students,
        (SELECT JSON_LENGTH(modules) FROM courses WHERE id = ?) as total_modules,
        (SELECT 
            COALESCE((
                SELECT SUM(JSON_LENGTH(JSON_EXTRACT(modules, '$[*].videos')))
                FROM courses 
                WHERE id = ?
            ), 0)
        ) as total_videos,
        (SELECT 
            COALESCE((
                SELECT SUM(JSON_LENGTH(JSON_EXTRACT(modules, '$[*].assessments')))
                FROM courses 
                WHERE id = ?
            ), 0)
        ) as total_assessments,
        (SELECT 
            COALESCE((
                SELECT SUM(JSON_LENGTH(JSON_EXTRACT(modules, '$[*].files')))
                FROM courses 
                WHERE id = ?
            ), 0)
        ) as total_files
";
$stmt = $db->prepare($stats_sql);
$stmt->execute([$course_id, $course_id, $course_id, $course_id, $course_id]);
$stats = $stmt->fetch();
?>

<div class="container-fluid">
    <a href="courses.php" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Back to My Courses
    </a>
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1"><?php echo htmlspecialchars($course['course_name']); ?></h1>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($course['course_code']); ?> • 
                        <?php echo htmlspecialchars($course['academic_year']); ?> • 
                        By <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="course_edit.php?id=<?php echo $course_id; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Edit Course
                    </a>
                    <a href="course_students.php?id=<?php echo $course_id; ?>" class="btn btn-outline-info">
                        <i class="bi bi-people me-1"></i>Students
                    </a>
                    <a href="course_analytics.php?id=<?php echo $course_id; ?>" class="btn btn-outline-success">
                        <i class="bi bi-graph-up me-1"></i>Analytics
                    </a>
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

    <!-- Course Description -->
    <?php if ($course['description']): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Course Description</h6>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Course Statistics -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stats-card students text-center">
                <i class="bi bi-people"></i>
                <h3><?php echo $stats['enrolled_students']; ?></h3>
                <p class="mb-0">Enrolled Students</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card modules text-center">
                <i class="bi bi-collection"></i>
                <h3><?php echo $stats['total_modules']; ?></h3>
                <p class="mb-0">Modules</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card videos text-center">
                <i class="bi bi-play-circle"></i>
                <h3><?php echo $stats['total_videos']; ?></h3>
                <p class="mb-0">Videos</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card assessments text-center">
                <i class="bi bi-clipboard-check"></i>
                <h3><?php echo $stats['total_assessments']; ?></h3>
                <p class="mb-0">Assessments</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card files text-center">
                <i class="bi bi-file-earmark"></i>
                <h3><?php echo $stats['total_files']; ?></h3>
                <p class="mb-0">Files</p>
            </div>
        </div>
    </div>

    <!-- Leaderboard Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="card-title mb-2">
                                <i class="bi bi-trophy text-warning me-2"></i>
                                Course Leaderboard
                            </h5>
                            <p class="card-text text-muted mb-0">
                                View student performance rankings and filter by sections to track progress across your course.
                            </p>
                        </div>
                        <div class="col-md-4">
                            <a href="leaderboard.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary btn-lg">
                                <i class="bi bi-bar-chart me-2"></i>
                                View Leaderboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modules Section -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Course Modules</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($modules)): ?>
                        <div class="empty-state">
                            <div class="empty-state-content">
                                <i class="bi bi-collection display-1 text-muted mb-4"></i>
                                <h4 class="text-muted mb-3">No Modules Yet</h4>
                                <p class="text-muted mb-4">Create your first module to start building your course content.</p>
                                <button class="btn btn-success btn-xl px-4 py-3 shadow-lg" data-bs-toggle="modal" data-bs-target="#createModuleModal" title="Create Your First Module" style="font-weight: 700; border-radius: 12px; transition: all 0.3s ease; transform: translateY(0);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                                    <i class="bi bi-plus-circle fs-2"></i>
                            </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Scrollable Modules Container -->
                        <div class="modules-scrollable-container">
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
                            <?php foreach ($modules as $module): ?>
                                <?php 
                                $card_color = $module_colors[$color_index % count($module_colors)];
                                $color_index++;
                                ?>
                                <div class="module-card" style="border-left: 4px solid <?php echo $card_color; ?>; background-color: <?php echo $card_color; ?>;">
                                    <!-- Module Status Badge - Clickable -->
                                    <div class="module-status <?php echo $module['is_locked'] ? 'status-locked' : 'status-unlocked'; ?> clickable-status" 
                                         onclick="toggleModuleStatus('<?php echo $module['id']; ?>', <?php echo $module['is_locked'] ? 'false' : 'true'; ?>)"
                                         title="Click to <?php echo $module['is_locked'] ? 'unlock' : 'lock'; ?> module">
                                        <i class="bi bi-<?php echo $module['is_locked'] ? 'lock-fill' : 'unlock-fill'; ?> me-1"></i>
                                        <?php echo $module['is_locked'] ? 'Locked' : 'Available'; ?>
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
                                                    <span class="module-stat-number"><?php echo $module['file_count'] ?? 0; ?></span>
                                                    <span class="module-stat-label">Files</span>
                                                </div>
                                                <div class="module-stat">
                                                    <span class="module-stat-number"><?php echo $module['module_order'] ?? 1; ?></span>
                                                    <span class="module-stat-label">Order</span>
                                                </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Module Actions -->
                                        <div class="module-actions">
                                            <a href="javascript:void(0)" onclick="editModule(<?php echo htmlspecialchars(json_encode($module)); ?>)" class="module-action-btn btn-edit" title="Edit Module">
                                                <i class="bi bi-pencil"></i>
                                                <span>Edit</span>
                                            </a>
                                            <a href="module_videos.php?module_id=<?php echo $module['id']; ?>" class="module-action-btn btn-videos" title="Manage Videos">
                                                <i class="bi bi-camera-video"></i>
                                                <span>Videos</span>
                                            </a>
                                            <a href="module_assessments.php?module_id=<?php echo $module['id']; ?>" class="module-action-btn btn-assessments" title="Manage Assessments">
                                                <i class="bi bi-clipboard-check"></i>
                                                <span>Assessments</span>
                                            </a>
                                            <a href="javascript:void(0)" onclick="showModuleFiles('<?php echo $module['id']; ?>', <?php echo htmlspecialchars(json_encode($module)); ?>)" class="module-action-btn btn-files" title="Manage Files">
                                                <i class="bi bi-file-earmark"></i>
                                                <span>Files</span>
                                            </a>
                                            <a href="javascript:void(0)" onclick="deleteModule('<?php echo $module['id']; ?>', '<?php echo htmlspecialchars($module['module_title']); ?>')" class="module-action-btn btn-delete" title="Delete Module">
                                                <i class="bi bi-trash"></i>
                                                <span>Delete</span>
                                                    </a>
                                                </div>
                                        
                                        <!-- Module Info -->
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="bi bi-sort-numeric-up me-1"></i>Module <?php echo $module['module_order']; ?>
                                                <?php if ($module['is_locked']): ?>
                                                    <span class="ms-2">
                                                        <i class="bi bi-lock me-1"></i>Locked (<?php echo $module['unlock_score']; ?>% required)
                                                    </span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Add Module Button - Bottom Right Corner -->
                <div class="position-relative">
                    <button class="floating-add-btn" data-bs-toggle="modal" data-bs-target="#createModuleModal" title="Add New Module" data-bs-toggle="tooltip" data-bs-placement="left">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Module Modal -->
<div class="modal fade" id="createModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_module">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="create_module_title" class="form-label">Module Title</label>
                            <input type="text" class="form-control" id="create_module_title" name="module_title" required>
                        </div>
                        <div class="col-md-4">
                            <label for="create_module_order" class="form-label">Order</label>
                            <input type="number" class="form-control" id="create_module_order" name="module_order" 
                                   value="<?php echo getNextAvailableModuleOrder($modules); ?>" min="1" required>
                            <div class="form-text">Next available order number</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_module_description" class="form-label">Description</label>
                        <textarea class="form-control" id="create_module_description" name="module_description" rows="3"></textarea>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="create_is_locked" name="is_locked">
                                <label class="form-check-label" for="create_is_locked">
                                    Lock this module
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="create_unlock_score" class="form-label">Unlock Score (%)</label>
                            <input type="number" class="form-control" id="create_unlock_score" name="unlock_score" 
                                   value="70" min="0" max="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Module</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Module Modal -->
<div class="modal fade" id="editModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_module">
                    <input type="hidden" name="module_id" id="edit_module_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="edit_module_title" class="form-label">Module Title</label>
                            <input type="text" class="form-control" id="edit_module_title" name="module_title" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_module_order" class="form-label">Order</label>
                            <input type="number" class="form-control" id="edit_module_order" name="module_order" min="1" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_module_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_module_description" name="module_description" rows="3"></textarea>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_locked" name="is_locked">
                                <label class="form-check-label" for="edit_is_locked">
                                    Lock this module
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_unlock_score" class="form-label">Unlock Score (%)</label>
                            <input type="number" class="form-control" id="edit_unlock_score" name="unlock_score" 
                                   min="0" max="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Module</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Module Files Modal -->
<div class="modal fade" id="moduleFilesModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark me-2"></i>
                    Module Files
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="moduleFilesContent">
                    <!-- Files will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- File Preview Modal -->
<div class="modal fade" id="filePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-eye me-2"></i>
                    <span id="previewFileName">File Preview</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="filePreviewContent" style="min-height: 500px;">
                    <!-- File preview will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Module Form -->
<form id="deleteModuleForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_module">
    <input type="hidden" name="module_id" id="delete_module_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<script>
// Module management functions
function editModule(module) {
    document.getElementById('edit_module_id').value = module.id;
    document.getElementById('edit_module_title').value = module.module_title;
    document.getElementById('edit_module_description').value = module.module_description;
    document.getElementById('edit_module_order').value = module.module_order;
    document.getElementById('edit_is_locked').checked = module.is_locked == 1;
    document.getElementById('edit_unlock_score').value = module.unlock_score;
    
    new bootstrap.Modal(document.getElementById('editModuleModal')).show();
}

function deleteModule(moduleId, moduleTitle) {
    if (confirm(`Are you sure you want to delete "${moduleTitle}"? This will also delete all videos and assessments in this module.`)) {
        document.getElementById('delete_module_id').value = moduleId;
        document.getElementById('deleteModuleForm').submit();
    }
}

function showModuleFiles(moduleId, module) {
    const modal = new bootstrap.Modal(document.getElementById('moduleFilesModal'));
    const content = document.getElementById('moduleFilesContent');
    
    // Update modal title with module name
    const modalTitle = document.querySelector('#moduleFilesModal .modal-title');
    modalTitle.innerHTML = `<i class="bi bi-file-earmark me-2"></i>Module Files - ${module.module_title}`;
    
    // Generate file content
    let fileHtml = '';
    
    if (module.files && module.files.length > 0) {
        module.files.forEach(file => {
            const fileExtension = file.original_name ? file.original_name.split('.').pop().toLowerCase() : 'file';
            const fileSize = file.file_size ? formatFileSize(file.file_size) : 'Unknown size';
            const uploadDate = file.uploaded_at ? new Date(file.uploaded_at).toLocaleDateString() : 'Unknown date';
            
            // Get file icon based on extension
            const fileIcon = getFileIcon(fileExtension);
            
            fileHtml += `
                <div class="file-item">
                    <div class="file-card">
                        <div class="file-icon">
                            <i class="bi ${fileIcon}"></i>
                        </div>
                        <div class="file-details">
                            <h6 class="file-name">${file.original_name || 'Unknown file'}</h6>
                            <div class="file-meta">
                                <span class="file-size">${fileSize}</span>
                                <span class="file-date">Uploaded: ${uploadDate}</span>
                            </div>
                        </div>
                        <div class="file-actions">
                            <button onclick="previewFile('${moduleId}', '${file.filename}', '${file.original_name}', '${fileExtension}')" 
                                    class="btn btn-primary btn-sm me-1" title="Preview File">
                                <i class="bi bi-eye"></i> Preview
                            </button>
                            <button onclick="deleteFile('${moduleId}', '${file.filename}', '${file.original_name}')" 
                                    class="btn btn-danger btn-sm" title="Delete File">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        // Add upload section
        fileHtml += `
            <div class="mt-4">
                <hr>
                <h6 class="mb-3">Upload New File</h6>
                <form id="uploadFileForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_file">
                    <input type="hidden" name="module_id" value="${moduleId}">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="file" class="form-control" name="module_file" id="module_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png,.gif,.bmp,.mp4,.avi,.mov,.wmv,.mp3,.wav,.zip,.rar,.7z,.tar,.gz" required>
                            <div class="form-text">Maximum file size: 10MB. Allowed types: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, RTF, JPG, PNG, GIF, MP4, AVI, MOV, MP3, WAV, ZIP, RAR, 7Z</div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-upload me-1"></i>Upload File
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        `;
    } else {
        fileHtml = `
            <div class="text-center py-4">
                <i class="bi bi-file-earmark-x display-1 text-muted mb-3"></i>
                <h5 class="text-muted">No Files Attached</h5>
                <p class="text-muted">This module doesn't have any files attached yet.</p>
                <hr>
                <h6 class="mb-3">Upload Your First File</h6>
                <form id="uploadFileForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_file">
                    <input type="hidden" name="module_id" value="${moduleId}">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="file" class="form-control" name="module_file" id="module_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png,.gif,.bmp,.mp4,.avi,.mov,.wmv,.mp3,.wav,.zip,.rar,.7z,.tar,.gz" required>
                            <div class="form-text">Maximum file size: 10MB. Allowed types: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, RTF, JPG, PNG, GIF, MP4, AVI, MOV, MP3, WAV, ZIP, RAR, 7Z</div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-upload me-1"></i>Upload File
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        `;
    }
    
    content.innerHTML = fileHtml;
    modal.show();
    
    // Add form submission handler
    const form = document.getElementById('uploadFileForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            uploadFile(moduleId);
        });
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function uploadFile(moduleId) {
    const form = document.getElementById('uploadFileForm');
    const formData = new FormData(form);
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Uploading...';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Reload the page to show updated files
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('An error occurred while uploading the file.');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function deleteFile(moduleId, filename, originalName) {
    if (confirm(`Are you sure you want to delete "${originalName}"?`)) {
        const formData = new FormData();
        formData.append('action', 'delete_file');
        formData.append('module_id', moduleId);
        formData.append('filename', filename);
        formData.append('<?php echo CSRF_TOKEN_NAME; ?>', '<?php echo generateCSRFToken(); ?>');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Reload the page to show updated files
            window.location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorMessage('An error occurred while deleting the file.');
        });
    }
}

function getFileIcon(extension) {
    const iconMap = {
        'pdf': 'bi-file-pdf',
        'doc': 'bi-file-word',
        'docx': 'bi-file-word',
        'xls': 'bi-file-excel',
        'xlsx': 'bi-file-excel',
        'ppt': 'bi-file-ppt',
        'pptx': 'bi-file-ppt',
        'txt': 'bi-file-text',
        'jpg': 'bi-file-image',
        'jpeg': 'bi-file-image',
        'png': 'bi-file-image',
        'gif': 'bi-file-image',
        'mp4': 'bi-file-play',
        'avi': 'bi-file-play',
        'mov': 'bi-file-play',
        'zip': 'bi-file-zip',
        'rar': 'bi-file-zip',
        '7z': 'bi-file-zip'
    };
    return iconMap[extension] || 'bi-file-earmark';
}

function showFileInfo(moduleId, filename, originalName, fileExtension) {
    const content = document.getElementById('filePreviewContent');
    content.innerHTML = `
        <div class="text-center p-5">
            <i class="bi bi-file-earmark-x text-muted" style="font-size: 4rem;"></i>
            <h4 class="mt-3 text-muted">Preview Not Available</h4>
            <p class="text-muted mb-4">
                This file type (${fileExtension.toUpperCase()}) cannot be previewed directly in the browser.
            </p>
            <div class="card bg-light mb-4">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-file-earmark me-2"></i>File Information
                    </h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><strong>Name:</strong> ${originalName}</li>
                        <li class="mb-2"><strong>Type:</strong> ${fileExtension.toUpperCase()} file</li>
                        <li class="mb-2"><strong>Module:</strong> ${moduleId}</li>
                    </ul>
                </div>
            </div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-center mb-4">
                <a href="../download_module_file.php?module_id=${moduleId}&filename=${filename}&original_name=${encodeURIComponent(originalName)}" 
                   class="btn btn-primary me-md-2" target="_blank">
                    <i class="bi bi-download me-1"></i>Download File
                </a>
                <button onclick="bootstrap.Modal.getInstance(document.getElementById('filePreviewModal')).hide()" 
                        class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Tip:</strong> Download the file and open it with the appropriate application to view the content.
            </div>
        </div>
    `;
}

function previewFile(moduleId, filename, originalName, fileExtension) {
    const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
    const content = document.getElementById('filePreviewContent');
    const title = document.getElementById('previewFileName');
    
    // Update modal title
    title.textContent = originalName;
    
    // Show loading state
    content.innerHTML = `
        <div class="d-flex justify-content-center align-items-center" style="min-height: 500px;">
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted">Loading file preview...</p>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Determine preview method based on file type (using same logic as student)
    const previewableTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'txt', 'mp4', 'avi', 'mov', 'wmv', 'mp3', 'wav'];
    const docxPreviewableTypes = ['docx'];
    const nonPreviewableTypes = ['doc', 'xlsx', 'xls', 'pptx', 'ppt', 'zip', 'rar', '7z'];
    
    if (docxPreviewableTypes.includes(fileExtension)) {
        // Use DOCX preview with PhpOffice/PhpWord (same as student)
        const iframe = document.createElement('iframe');
        iframe.src = `../preview_docx.php?module_id=${moduleId}&filename=${filename}&original_name=${encodeURIComponent(originalName)}`;
        iframe.style.width = '100%';
        iframe.style.height = '700px';
        iframe.style.border = 'none';
        iframe.style.borderRadius = '8px';
        iframe.style.minHeight = '600px';
        
        content.innerHTML = '';
        content.appendChild(iframe);
        
        // Add fallback timeout
        setTimeout(() => {
            if (content.innerHTML.includes('iframe')) {
                console.log('DOCX iframe loaded successfully');
            } else {
                console.log('DOCX iframe failed to load, showing fallback');
                showFileInfo(moduleId, filename, originalName, fileExtension);
            }
        }, 5000);
        
    } else if (nonPreviewableTypes.includes(fileExtension)) {
        // Show file info for non-previewable files
        showFileInfo(moduleId, filename, originalName, fileExtension);
        
    } else if (previewableTypes.includes(fileExtension)) {
        // Use different approaches based on file type (same as student)
        const previewUrl = `../preview_module_file.php?module_id=${moduleId}&filename=${filename}&original_name=${encodeURIComponent(originalName)}`;
        
        if (fileExtension === 'pdf') {
            // For PDFs, use iframe with the improved PDF preview
            const iframe = document.createElement('iframe');
            iframe.src = previewUrl;
            iframe.style.width = '100%';
            iframe.style.height = '700px';
            iframe.style.border = 'none';
            iframe.style.borderRadius = '8px';
            iframe.style.minHeight = '600px';
            
            content.innerHTML = '';
            content.appendChild(iframe);
            
        } else if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(fileExtension)) {
            content.innerHTML = `
                <div class="text-center p-4">
                    <img src="${previewUrl}" 
                         alt="${originalName}" 
                         style="max-width: 100%; max-height: 600px; object-fit: contain;"
                         class="img-fluid">
                </div>
            `;
        } else if (['mp4', 'avi', 'mov', 'wmv'].includes(fileExtension)) {
            content.innerHTML = `
                <div class="text-center p-4">
                    <video controls style="max-width: 100%; max-height: 600px;">
                        <source src="${previewUrl}" type="video/${fileExtension}">
                        Your browser does not support the video tag.
                    </video>
                </div>
            `;
        } else if (['mp3', 'wav'].includes(fileExtension)) {
            content.innerHTML = `
                <div class="text-center p-4">
                    <audio controls style="width: 100%;">
                        <source src="${previewUrl}" type="audio/${fileExtension}">
                        Your browser does not support the audio tag.
                    </audio>
                </div>
            `;
        } else if (fileExtension === 'txt') {
            fetch(previewUrl)
                .then(response => response.text())
                .then(text => {
                    content.innerHTML = `
                        <div class="p-4">
                            <pre style="white-space: pre-wrap; word-wrap: break-word; font-family: 'Courier New', monospace; background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; max-height: 600px; overflow-y: auto;">${text}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="text-center p-4">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">Error Loading File</h5>
                            <p class="text-muted">Unable to load the file preview.</p>
                        </div>
                    `;
                });
        } else {
            // For other files, use iframe (same as student)
            const iframe = document.createElement('iframe');
            iframe.src = previewUrl;
            iframe.style.width = '100%';
            iframe.style.height = '700px';
            iframe.style.border = 'none';
            iframe.style.borderRadius = '8px';
            iframe.style.minHeight = '600px';
            
            content.innerHTML = '';
            content.appendChild(iframe);
        }
    } else {
        // Show unsupported file type message
        showFileInfo(moduleId, filename, originalName, fileExtension);
    }
}

function toggleModuleStatus(moduleId, shouldLock) {
    const action = shouldLock ? 'lock' : 'unlock';
    const actionText = shouldLock ? 'lock' : 'unlock';
    
    if (confirm(`Are you sure you want to ${actionText} this module?`)) {
        // Show loading state
        const statusElement = document.querySelector(`[onclick*="toggleModuleStatus('${moduleId}'"]`);
        const originalContent = statusElement.innerHTML;
        statusElement.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Updating...';
        statusElement.style.pointerEvents = 'none';
        
        // Send AJAX request
        fetch('toggle_module_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                module_id: moduleId,
                action: action,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the status display
                const isLocked = action === 'lock';
                statusElement.className = `module-status ${isLocked ? 'status-locked' : 'status-unlocked'} clickable-status`;
                statusElement.innerHTML = `<i class="bi bi-${isLocked ? 'lock-fill' : 'unlock-fill'} me-1"></i>${isLocked ? 'Locked' : 'Available'}`;
                statusElement.setAttribute('onclick', `toggleModuleStatus('${moduleId}', ${!isLocked})`);
                statusElement.setAttribute('title', `Click to ${isLocked ? 'unlock' : 'lock'} module`);
                
                // Show success message
                showSuccessMessage(data.message);
            } else {
                showErrorMessage(data.message);
                // Restore original content
                statusElement.innerHTML = originalContent;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorMessage('An error occurred while updating module status.');
            // Restore original content
            statusElement.innerHTML = originalContent;
        })
        .finally(() => {
            statusElement.style.pointerEvents = 'auto';
        });
    }
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
    
    // Remove existing alerts
    document.querySelectorAll('.alert').forEach(alert => alert.remove());
    
    // Add new alert
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Form validation functions for module orders
function validateModuleOrderInput(input, isEdit = false) {
    const orderValue = parseInt(input.value);
    const currentModules = <?php echo json_encode($modules ?? []); ?>;
    
    // Check if order is already taken
    for (let module of currentModules) {
        if (isEdit && module.id === document.getElementById('edit_module_id').value) {
            continue; // Skip the module being edited
        }
        if (module.module_order == orderValue) {
            input.setCustomValidity('This order number is already taken. Please choose a different number.');
            return false;
        }
    }
    
    input.setCustomValidity('');
    return true;
}

// Add event listeners for order validation
document.addEventListener('DOMContentLoaded', function() {
    const createOrderInput = document.getElementById('create_module_order');
    const editOrderInput = document.getElementById('edit_module_order');
    
    if (createOrderInput) {
        createOrderInput.addEventListener('input', function() {
            validateModuleOrderInput(this, false);
        });
    }
    
    if (editOrderInput) {
        editOrderInput.addEventListener('input', function() {
            validateModuleOrderInput(this, true);
        });
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>


<?php require_once '../includes/footer.php'; ?> 