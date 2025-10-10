<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $course_id = (int)($_GET['course_id'] ?? 0);
    
    if (!$course_id) {
        throw new Exception('Course ID is required');
    }
    
    // Get badges that can be attached to courses (context: course, global, or requirement)
    $stmt = $db->prepare('
        SELECT id, badge_name, badge_description, badge_icon, badge_type, points_value,
               category, context, award_condition, min_requirement_value, is_restricted
        FROM badges 
        WHERE is_active = 1 AND context IN ("course", "global", "requirement")
        ORDER BY category ASC, badge_name ASC
    ');
    $stmt->execute();
    $available_badges = $stmt->fetchAll();
    
    // Get badges already assigned to this course
    $stmt = $db->prepare('
        SELECT cb.badge_id, b.badge_name, b.badge_description, b.badge_icon, b.badge_type, b.points_value
        FROM course_badges cb
        JOIN badges b ON cb.badge_id = b.id
        WHERE cb.course_id = ? AND cb.is_active = 1
        ORDER BY b.badge_name
    ');
    $stmt->execute([$course_id]);
    $assigned_badges = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'available_badges' => $available_badges,
        'assigned_badges' => $assigned_badges
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
