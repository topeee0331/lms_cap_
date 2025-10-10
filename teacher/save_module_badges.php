<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['module_id']) || !isset($input['badge_ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Module ID and badge IDs are required']);
    exit;
}

$module_id = $input['module_id'];
$badge_ids = $input['badge_ids'];

// Validate that badge_ids is an array
if (!is_array($badge_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Badge IDs must be an array']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Get teacher ID
    $teacher_id = $_SESSION['user_id'];
    
    // Delete existing module-badge relationships
    $stmt = $db->prepare("DELETE FROM module_badges WHERE module_id = ?");
    $stmt->execute([$module_id]);
    
    // Insert new module-badge relationships
    if (!empty($badge_ids)) {
        $stmt = $db->prepare("
            INSERT INTO module_badges (module_id, badge_id, created_by, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        foreach ($badge_ids as $badge_id) {
            $stmt->execute([$module_id, $badge_id, $teacher_id]);
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Module badges saved successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
