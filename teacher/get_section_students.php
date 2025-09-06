<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$course_id = (int)($_GET['course_id'] ?? 0);
$section_id = (int)($_GET['section_id'] ?? 0);

if (!$course_id || !$section_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

// Verify teacher owns this course
$stmt = $db->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get students in the section
$sql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.is_irregular, u.identifier, u.created_at 
        FROM users u 
        WHERE u.role = 'student' 
        AND JSON_SEARCH((SELECT students FROM sections WHERE id = ?), 'one', u.id) IS NOT NULL
        ORDER BY u.last_name, u.first_name";

$stmt = $db->prepare($sql);
$stmt->execute([$section_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($students);
?>
