<?php
require_once '../config/config.php';
requireRole('teacher');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$assessment_id = (int)($_POST['assessment_id'] ?? 0);

if (!$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'Assessment ID is required']);
    exit;
}

try {
    // Verify the assessment belongs to the teacher
    $stmt = $db->prepare("
        SELECT a.id 
        FROM assessments a 
        JOIN modules m ON a.module_id = m.id 
        JOIN courses c ON m.course_id = c.id 
        WHERE a.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$assessment_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Assessment not found or access denied']);
        exit;
    }
    
    // Get the actual question count
    $stmt = $db->prepare("SELECT COUNT(*) FROM questions WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'count' => (int)$count,
        'assessment_id' => $assessment_id
    ]);
    
} catch (Exception $e) {
    error_log("Error getting question count: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving question count']);
}
?>
