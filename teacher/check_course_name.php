<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_name']) && isset($_POST['academic_period_id'])) {
    $course_name = trim($_POST['course_name']);
    $academic_period_id = intval($_POST['academic_period_id']);
    
    if (empty($course_name) || $academic_period_id <= 0) {
        echo json_encode(['exists' => false]);
        exit;
    }
    
    try {
        $stmt = $db->prepare('SELECT id FROM courses WHERE course_name = ? AND academic_period_id = ?');
        $stmt->execute([$course_name, $academic_period_id]);
        $exists = $stmt->fetch() !== false;
        
        echo json_encode(['exists' => $exists]);
    } catch (PDOException $e) {
        error_log("Error checking course name: " . $e->getMessage());
        echo json_encode(['exists' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['exists' => false, 'error' => 'Invalid request']);
}
?>
