<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['module_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Module ID is required']);
    exit;
}

$module_id = $_GET['module_id'];

try {
    // Get badges that can be attached to modules (context: module, global, or requirement)
    $stmt = $db->prepare("
        SELECT id, badge_name, badge_description, badge_icon, badge_type, points_value, 
               category, context, award_condition, min_requirement_value, is_restricted
        FROM badges 
        WHERE is_active = 1 AND context IN ('module', 'global', 'requirement')
        ORDER BY category ASC, badge_name ASC
    ");
    $stmt->execute();
    $available_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get badges already assigned to this module
    $stmt = $db->prepare("
        SELECT b.id, b.badge_name, b.badge_description, b.badge_icon, b.badge_type, b.points_value
        FROM badges b
        INNER JOIN module_badges mb ON b.id = mb.badge_id
        WHERE mb.module_id = ? AND b.is_active = 1
        ORDER BY b.badge_name ASC
    ");
    $stmt->execute([$module_id]);
    $assigned_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'available_badges' => $available_badges,
        'assigned_badges' => $assigned_badges
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
