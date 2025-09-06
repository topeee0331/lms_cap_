<?php
session_start();
require_once 'config/database.php';
require_once 'includes/header.php';

// Check if user is logged in and is a teacher only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    die('Access denied. Teacher role required for downloads.');
}

// Get parameters
$module_id = $_GET['module_id'] ?? '';
$filename = $_GET['filename'] ?? '';
$original_name = $_GET['original_name'] ?? '';

if (empty($module_id) || empty($filename) || empty($original_name)) {
    http_response_code(400);
    die('Missing required parameters.');
}

try {
    // For teachers: verify the module belongs to them
    $stmt = $db->prepare('SELECT id, modules FROM courses WHERE teacher_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll();
    
    $module_found = false;
    foreach ($courses as $course) {
        $modules_data = json_decode($course['modules'], true) ?: [];
        foreach ($modules_data as $module) {
            if ($module['id'] === $module_id && isset($module['file']) && $module['file']['filename'] === $filename) {
                $module_found = true;
                break 2;
            }
        }
    }
    
    if (!$module_found) {
        http_response_code(404);
        die('Module file not found or access denied.');
    }
    
    // Construct file path
    $file_path = '../uploads/modules/' . $filename;
    
    // Check if file exists
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found on server.');
    }
    
    // Set headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $original_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read and output file
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    error_log('Download error: ' . $e->getMessage());
    http_response_code(500);
    die('An error occurred while downloading the file.');
}
?>
