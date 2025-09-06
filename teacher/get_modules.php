<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('teacher');

header('Content-Type: application/json');

$course_id = (int)($_GET['course_id'] ?? 0);

if (!$course_id) {
    echo json_encode([]);
    exit;
}

// Verify teacher owns this course
$stmt = $db->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ?');
$stmt->execute([$course_id, $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    echo json_encode([]);
    exit;
}

// Get modules for the course from JSON
$stmt = $db->prepare('SELECT modules FROM courses WHERE id = ?');
$stmt->execute([$course_id]);
$course = $stmt->fetch();

$modules = [];
if ($course && $course['modules']) {
    $modules_data = json_decode($course['modules'], true);
    if (is_array($modules_data)) {
        foreach ($modules_data as $module) {
            $modules[] = [
                'id' => $module['id'],
                'module_title' => $module['module_title'] ?? $module['title'] ?? 'Module'
            ];
        }
    }
}

// Debug logging
error_log("get_modules.php - Course ID: $course_id, Modules found: " . count($modules));
error_log("Modules data: " . json_encode($modules));

echo json_encode($modules);
?> 