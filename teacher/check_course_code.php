<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_code'])) {
    $course_code = trim($_POST['course_code']);
    
    if (empty($course_code)) {
        echo json_encode(['exists' => false]);
        exit;
    }
    
    try {
        $stmt = $db->prepare('SELECT id FROM courses WHERE course_code = ?');
        $stmt->execute([$course_code]);
        $exists = $stmt->fetch() !== false;
        
        echo json_encode(['exists' => $exists]);
    } catch (PDOException $e) {
        error_log("Error checking course code: " . $e->getMessage());
        echo json_encode(['exists' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['exists' => false, 'error' => 'Invalid request']);
}
?>
