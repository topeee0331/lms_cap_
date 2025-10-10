<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $course_id = (int)($input['course_id'] ?? 0);
    $badge_ids = $input['badge_ids'] ?? [];
    
    if (!$course_id) {
        throw new Exception('Course ID is required');
    }
    
    if (!is_array($badge_ids)) {
        throw new Exception('Badge IDs must be an array');
    }
    
    // Convert badge IDs to integers
    $badge_ids = array_map('intval', $badge_ids);
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Remove all existing course-badge relationships for this course
        $stmt = $db->prepare('DELETE FROM course_badges WHERE course_id = ?');
        $stmt->execute([$course_id]);
        
        // Add new course-badge relationships
        if (!empty($badge_ids)) {
            // Get the current user ID (teacher)
            $teacher_id = $_SESSION['user_id'] ?? null;
            
            // If no teacher ID in session, try to get it from the course owner
            if (!$teacher_id) {
                $stmt = $db->prepare('SELECT teacher_id FROM courses WHERE id = ?');
                $stmt->execute([$course_id]);
                $course = $stmt->fetch();
                $teacher_id = $course['teacher_id'] ?? null;
            }
            
            // If still no teacher ID, use a default teacher (first teacher in database)
            if (!$teacher_id) {
                $stmt = $db->prepare('SELECT id FROM users WHERE role = "teacher" LIMIT 1');
                $stmt->execute();
                $teacher = $stmt->fetch();
                $teacher_id = $teacher['id'] ?? 1; // Fallback to ID 1
            }
            
            // Debug: Log the teacher ID
            error_log("Using Teacher ID: " . $teacher_id);
            
            $stmt = $db->prepare('INSERT INTO course_badges (course_id, badge_id, created_by, is_active) VALUES (?, ?, ?, 1)');
            foreach ($badge_ids as $badge_id) {
                if ($badge_id > 0) {
                    $stmt->execute([$course_id, $badge_id, $teacher_id]);
                }
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Course badges saved successfully',
            'badge_count' => count($badge_ids)
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
