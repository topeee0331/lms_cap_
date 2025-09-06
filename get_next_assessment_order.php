<?php
require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    // Get the next available assessment order for a specific course
    $course_id = $_GET['course_id'] ?? '';
    
    if (empty($course_id)) {
        echo json_encode(['error' => 'Course ID is required']);
        exit;
    }
    
    // Get the highest assessment order for this course
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(assessment_order), 0) + 1 as next_order 
        FROM assessments 
        WHERE course_id = ?
    ");
    $stmt->execute([$course_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'next_order' => (int)$result['next_order']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
