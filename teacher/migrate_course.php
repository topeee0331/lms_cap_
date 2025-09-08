<?php
/**
 * Course Migration Handler
 * 
 * Handles the migration of courses from inactive to active academic periods
 */

session_start();
require_once '../config/config.php';
require_once '../includes/content_migration.php';
require_once '../includes/semester_security.php';
requireRole('teacher');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: courses.php');
        exit();
    }
    
    $source_course_id = sanitizeInput($_POST['source_course_id'] ?? '');
    $target_academic_period_id = (int)($_POST['target_academic_period_id'] ?? 0);
    
    if (empty($source_course_id) || $target_academic_period_id <= 0) {
        $_SESSION['error'] = 'Invalid course or academic period selection.';
        header('Location: courses.php');
        exit();
    }
    
    // Perform migration
    $result = migrateCourse($db, $source_course_id, $target_academic_period_id, $_SESSION['user_id']);
    
    if ($result['success']) {
        $_SESSION['success'] = $result['message'] . ' New course created successfully.';
        // Redirect to the new course
        header('Location: course.php?id=' . $result['new_course_id']);
        exit();
    } else {
        $_SESSION['error'] = 'Migration failed: ' . $result['message'];
        header('Location: courses.php');
        exit();
    }
} else {
    // Invalid request method
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: courses.php');
    exit();
}
?>
