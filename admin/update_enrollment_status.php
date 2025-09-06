<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$enrollment_id = isset($_POST['enrollment_id']) ? (int)$_POST['enrollment_id'] : 0;
$new_status = $_POST['status'] ?? '';

if (!$enrollment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid enrollment ID']);
    exit;
}

if (!in_array($new_status, ['active', 'completed', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Verify enrollment exists
    $stmt = $db->prepare("SELECT id FROM course_enrollments WHERE id = ?");
    $stmt->execute([$enrollment_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
        exit;
    }
    
    // Update enrollment status
    $stmt = $db->prepare("UPDATE course_enrollments SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $enrollment_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Enrollment status updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error updating enrollment status: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating enrollment status: ' . $e->getMessage()
    ]);
}
?>
