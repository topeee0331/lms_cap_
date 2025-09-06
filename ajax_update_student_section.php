<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$student_id = isset($input['student_id']) ? intval($input['student_id']) : 0;
$new_section_id = isset($input['new_section_id']) ? intval($input['new_section_id']) : 0;
$is_irregular = isset($input['is_irregular']) ? intval($input['is_irregular']) : 0;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Update student irregular status
    $stmt = $db->prepare("UPDATE users SET is_irregular = ? WHERE id = ? AND role = 'student'");
    $stmt->execute([$is_irregular, $student_id]);
    
    // Remove student from all sections first
    $stmt = $db->prepare("
        UPDATE sections 
        SET students = JSON_REMOVE(students, JSON_UNQUOTE(JSON_SEARCH(students, 'one', ?)))
        WHERE JSON_SEARCH(students, 'one', ?) IS NOT NULL
    ");
    $stmt->execute([$student_id, $student_id]);
    
    // Add student to new section if specified
    if ($new_section_id > 0) {
        $stmt = $db->prepare("
            UPDATE sections 
            SET students = CASE 
                WHEN students IS NULL THEN JSON_ARRAY(?)
                ELSE JSON_ARRAY_APPEND(students, '$', ?)
            END
            WHERE id = ?
        ");
        $stmt->execute([$student_id, $student_id, $new_section_id]);
        
        // Verify the section exists
        $stmt = $db->prepare("SELECT id FROM sections WHERE id = ?");
        $stmt->execute([$new_section_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Section not found');
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Student section updated successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error updating student section: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating student section: ' . $e->getMessage()
    ]);
}
?>
