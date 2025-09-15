<?php
require_once '../includes/header.php';
requireRole('teacher');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed');
}

// Validate CSRF token
$csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
if (!validateCSRFToken($csrf_token)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Invalid CSRF token');
}

$module_ids = $_POST['module_ids'] ?? [];

if (empty($module_ids)) {
    header('HTTP/1.1 400 Bad Request');
    exit('No modules selected');
}

// Get teacher's courses and modules
$stmt = $db->prepare('SELECT id, course_name, modules FROM courses WHERE teacher_id = ? ORDER BY course_name');
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

// Collect all modules from teacher's courses
$all_modules = [];
foreach ($courses as $course) {
    $modules_data = json_decode($course['modules'] ?? '', true) ?: [];
    foreach ($modules_data as $module) {
        $module['course_id'] = $course['id'];
        $module['course_name'] = $course['course_name'];
        $all_modules[] = $module;
    }
}

// Filter modules by requested IDs and ensure they belong to this teacher
$selected_modules = array_filter($all_modules, function($module) use ($module_ids) {
    return in_array($module['id'], $module_ids);
});

if (empty($selected_modules)) {
    header('HTTP/1.1 404 Not Found');
    exit('No valid modules found');
}

// Filter modules that have files
$modules_with_files = array_filter($selected_modules, function($module) {
    return isset($module['file']) && !empty($module['file']['filename']);
});

if (empty($modules_with_files)) {
    header('HTTP/1.1 404 Not Found');
    exit('No modules with files found');
}

// Create ZIP file
$zip = new ZipArchive();
$zip_filename = 'modules_' . date('Y-m-d_H-i-s') . '.zip';
$zip_path = sys_get_temp_dir() . '/' . $zip_filename;

if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Cannot create ZIP file');
}

$files_added = 0;

foreach ($modules_with_files as $module) {
    $file_info = $module['file'];
    $file_path = '../uploads/modules/' . $file_info['filename'];
    
    if (file_exists($file_path)) {
        // Create a clean filename for the ZIP
        $course_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $module['course_name']);
        $module_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $module['module_title']);
        $original_name = $file_info['original_name'] ?? $file_info['filename'];
        
        // Create folder structure: Course_Name/Module_Title/file.ext
        $zip_file_path = $course_name . '/' . $module_title . '/' . $original_name;
        
        if ($zip->addFile($file_path, $zip_file_path)) {
            $files_added++;
        }
        
        // Add module info as a text file
        $module_info = "Module: " . $module['module_title'] . "\n";
        $module_info .= "Course: " . $module['course_name'] . "\n";
        $module_info .= "Description: " . ($module['module_description'] ?? 'No description') . "\n";
        $module_info .= "Order: " . ($module['module_order'] ?? 'N/A') . "\n";
        $module_info .= "Status: " . (isset($module['is_locked']) && $module['is_locked'] ? 'Locked' : 'Unlocked') . "\n";
        $module_info .= "Created: " . ($module['created_at'] ?? 'Unknown') . "\n";
        $module_info .= "File Size: " . ($file_info['file_size'] ?? 'Unknown') . " bytes\n";
        
        $info_file_path = $course_name . '/' . $module_title . '/module_info.txt';
        $zip->addFromString($info_file_path, $module_info);
    }
}

// Add a summary file
$summary = "Module Export Summary\n";
$summary .= "=====================\n\n";
$summary .= "Export Date: " . date('Y-m-d H:i:s') . "\n";
$summary .= "Teacher: " . ($_SESSION['user_name'] ?? 'Unknown') . "\n";
$summary .= "Total Modules: " . count($modules_with_files) . "\n";
$summary .= "Files Added: " . $files_added . "\n\n";

$summary .= "Modules Included:\n";
$summary .= "-----------------\n";
foreach ($modules_with_files as $module) {
    $summary .= "- " . $module['module_title'] . " (" . $module['course_name'] . ")\n";
}

$zip->addFromString('export_summary.txt', $summary);

$zip->close();

if ($files_added === 0) {
    unlink($zip_path);
    header('HTTP/1.1 404 Not Found');
    exit('No valid files found to add to ZIP');
}

// Send the ZIP file
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($zip_path));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Output the file
readfile($zip_path);

// Clean up
unlink($zip_path);
exit();
?>
