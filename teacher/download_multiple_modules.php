<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    exit('Access denied. Only teachers can access this resource.');
}

$teacher_id = $_SESSION['user_id'];

// Validate CSRF token
if (!isset($_POST[CSRF_TOKEN_NAME]) || !validateCSRFToken($_POST[CSRF_TOKEN_NAME])) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

// Get module IDs from POST data
$module_ids_json = $_POST['module_ids'] ?? '';
$module_ids = json_decode($module_ids_json, true);

if (!$module_ids || !is_array($module_ids)) {
    http_response_code(400);
    exit('Invalid module IDs provided.');
}

$db = new Database();
$pdo = $db->getConnection();

// Get modules and verify teacher ownership
$placeholders = str_repeat('?,', count($module_ids) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT cm.id, cm.module_title, c.course_name, c.course_code
    FROM course_modules cm 
    JOIN courses c ON cm.course_id = c.id 
    WHERE cm.id IN ($placeholders) AND c.teacher_id = ?
    ORDER BY c.course_name, cm.module_order
");

$params = array_merge($module_ids, [$teacher_id]);
$stmt->execute($params);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($modules)) {
    http_response_code(404);
    exit('No modules found or access denied.');
}

// Get all files for the selected modules
$module_ids_for_files = array_column($modules, 'id');
$placeholders = str_repeat('?,', count($module_ids_for_files) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT mf.*, cm.module_title, c.course_name, c.course_code
    FROM module_files mf 
    JOIN course_modules cm ON mf.module_id = cm.id 
    JOIN courses c ON cm.course_id = c.id 
    WHERE mf.module_id IN ($placeholders) AND c.teacher_id = ?
    ORDER BY c.course_name, cm.module_order, mf.uploaded_at
");

$params = array_merge($module_ids_for_files, [$teacher_id]);
$stmt->execute($params);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($files)) {
    http_response_code(404);
    exit('No files found in the selected modules.');
}

// Create ZIP file
$zip_filename = sanitizeFilename('Multiple_Modules_' . date('Y-m-d_H-i-s') . '.zip');
$zip = new ZipArchive();
$temp_zip = tempnam(sys_get_temp_dir(), 'modules_zip_');

if ($zip->open($temp_zip, ZipArchive::CREATE) !== TRUE) {
    http_response_code(500);
    exit('Could not create ZIP file.');
}

// Add files to ZIP with organized structure
$total_files = 0;
$total_size = 0;

foreach ($files as $file) {
    $real_path = realpath($file['file_path']);
    if ($real_path && file_exists($real_path)) {
        // Create organized folder structure: Course/Module/Filename
        $zip_path = sanitizeFilename($file['course_name']) . '/' . 
                   sanitizeFilename($file['module_title']) . '/' . 
                   $file['file_name'];
        
        $zip->addFile($real_path, $zip_path);
        $total_files++;
        $total_size += filesize($real_path);
    }
}

// Add comprehensive info file
$info_content = "Multiple Modules Download Report\n";
$info_content .= "================================\n\n";
$info_content .= "Downloaded: " . date('Y-m-d H:i:s') . "\n";
$info_content .= "Total Modules: " . count($modules) . "\n";
$info_content .= "Total Files: " . $total_files . "\n";
$info_content .= "Total Size: " . formatFileSize($total_size) . "\n\n";

$info_content .= "Modules Included:\n";
$info_content .= "==================\n";
foreach ($modules as $module) {
    $info_content .= "- " . $module['course_name'] . " > " . $module['module_title'] . "\n";
}

$info_content .= "\nFiles Included:\n";
$info_content .= "================\n";
$current_course = '';
$current_module = '';
foreach ($files as $file) {
    if ($file['course_name'] !== $current_course) {
        $current_course = $file['course_name'];
        $info_content .= "\n" . $current_course . ":\n";
    }
    if ($file['module_title'] !== $current_module) {
        $current_module = $file['module_title'];
        $info_content .= "  " . $current_module . ":\n";
    }
    $info_content .= "    - " . $file['file_name'] . " (uploaded: " . $file['uploaded_at'] . ")\n";
}

$zip->addFromString('Download_Info.txt', $info_content);

// Add course structure file
$structure_content = "Course Structure\n";
$structure_content .= "================\n\n";
foreach ($modules as $module) {
    $structure_content .= $module['course_name'] . "\n";
    $structure_content .= "  └── " . $module['module_title'] . "\n";
    
    // Count files in this module
    $module_files = array_filter($files, function($f) use ($module) {
        return $f['module_id'] == $module['id'];
    });
    
    foreach ($module_files as $file) {
        $structure_content .= "      └── " . $file['file_name'] . "\n";
    }
    $structure_content .= "\n";
}

$zip->addFromString('Course_Structure.txt', $structure_content);

$zip->close();

// Send ZIP file
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($temp_zip));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

readfile($temp_zip);
unlink($temp_zip); // Clean up temp file
exit;

function sanitizeFilename($filename) {
    // Remove or replace invalid characters
    $filename = preg_replace('/[^a-zA-Z0-9\s\-_\.]/', '', $filename);
    $filename = str_replace(' ', '_', $filename);
    $filename = trim($filename, '._-');
    return $filename;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}
?>
