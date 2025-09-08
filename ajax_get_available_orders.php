<?php
/**
 * AJAX endpoint to get available assessment orders for a course
 */

require_once '../config/config.php';
require_once '../includes/assessment_order_manager.php';

header('Content-Type: application/json');

try {
    // Get course ID from POST data
    $course_id = (int)($_POST['course_id'] ?? 0);
    
    if ($course_id <= 0) {
        throw new Exception('Invalid course ID');
    }
    
    // Get available orders
    $available_orders = getAvailableAssessmentOrders($db, $course_id, 10);
    
    echo json_encode([
        'success' => true,
        'available_orders' => $available_orders
    ]);
    
} catch (Exception $e) {
    error_log("Error getting available orders: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get available orders'
    ]);
}
?>
