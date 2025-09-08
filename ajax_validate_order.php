<?php
/**
 * AJAX endpoint to validate assessment order
 */

require_once '../config/config.php';
require_once '../includes/assessment_order_manager.php';

header('Content-Type: application/json');

try {
    // Get parameters from POST data
    $course_id = (int)($_POST['course_id'] ?? 0);
    $order = (int)($_POST['order'] ?? 0);
    $exclude_id = $_POST['exclude_id'] ?? null;
    
    if ($course_id <= 0) {
        throw new Exception('Invalid course ID');
    }
    
    if ($order <= 0) {
        throw new Exception('Invalid order number');
    }
    
    // Validate the order
    $is_valid = validateAssessmentOrder($db, $course_id, $order, $exclude_id);
    
    if ($is_valid) {
        echo json_encode([
            'success' => true,
            'message' => "Order $order is available"
        ]);
    } else {
        // Get available orders for suggestion
        $available_orders = getAvailableAssessmentOrders($db, $course_id, 5);
        $next_available = autoAssignAssessmentOrder($db, $course_id);
        
        echo json_encode([
            'success' => false,
            'message' => "Order $order is already taken. Available orders: " . implode(', ', $available_orders),
            'suggested_orders' => $available_orders,
            'next_available' => $next_available
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error validating order: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to validate order'
    ]);
}
?>
