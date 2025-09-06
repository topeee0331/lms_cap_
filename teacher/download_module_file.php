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
$file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$file_id) {
    http_response_code(400);
    exit('Invalid file ID.');
}

$db = new Database();
$pdo = $db->getConnection();

// Get file info and verify teacher ownership
$stmt = $pdo->prepare('
    SELECT mf.*, cm.course_id, c.course_name, c.course_code, cm.module_title 
    FROM module_files mf 
    JOIN course_modules cm ON mf.module_id = cm.id 
    JOIN courses c ON cm.course_id = c.id 
    WHERE mf.id = ? AND c.teacher_id = ?
');
$stmt->execute([$file_id, $teacher_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    exit('File not found or access denied.');
}

// Fix file path handling - construct absolute path properly
$file_path = $file['file_path'];
$real_path = '';

// Handle different path formats
if (strpos($file_path, '../') === 0) {
    // Relative path starting with ../
    $real_path = dirname(__DIR__) . '/' . substr($file_path, 3);
} elseif (strpos($file_path, './') === 0) {
    // Relative path starting with ./
    $real_path = dirname(__DIR__) . '/' . substr($file_path, 2);
} elseif (strpos($file_path, '/') === 0) {
    // Absolute path
    $real_path = $file_path;
} else {
    // Relative path without prefix
    $real_path = dirname(__DIR__) . '/' . $file_path;
}

// Additional safety check - ensure the path is within uploads directory
$uploads_dir = dirname(__DIR__) . '/uploads/';
$real_path = realpath($real_path);

if (!$real_path || !file_exists($real_path) || strpos($real_path, realpath($uploads_dir)) !== 0) {
    // Log the error for debugging
    error_log("Teacher file download error - File ID: $file_id, Original path: {$file['file_path']}, Resolved path: $real_path");
    
    // Try alternative path resolution methods
    $alternative_paths = [
        __DIR__ . '/uploads/' . basename($file['file_path']),
        dirname(__DIR__) . '/uploads/modules/' . basename($file['file_path']),
        $_SERVER['DOCUMENT_ROOT'] . '/lms_cap/uploads/modules/' . basename($file['file_path'])
    ];
    
    foreach ($alternative_paths as $alt_path) {
        if (file_exists($alt_path)) {
            $real_path = $alt_path;
            error_log("Alternative path found: $real_path");
            break;
        }
    }
    
    if (!$real_path || !file_exists($real_path)) {
        http_response_code(404);
        exit('File missing on server or invalid path. Please contact your administrator.');
    }
}

// Get MIME type
$mime_type = mime_content_type($real_path);
if (!$mime_type) {
    // Fallback MIME types for common extensions
    $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
    $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.sheet',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
}

// Set appropriate headers for download
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . filesize($real_path));

// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

readfile($real_path);
exit;
?>
