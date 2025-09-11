<?php
/**
 * Endpoint to mark student notifications as read
 * This allows students to mark individual notifications as read and remove them from display
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Student access required']);
    ob_end_flush();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    ob_end_flush();
    exit();
}

try {
    $studentId = $_SESSION['user_id'];
    $notificationId = $_POST['notification_id'] ?? null;
    
    if (!$notificationId) {
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        ob_end_flush();
        exit();
    }
    
    // First, check if this notification exists and belongs to the student
    // Handle both enrollment notifications and course_kicked notifications
    $stmt = $pdo->prepare("
        SELECT n.*, er.student_id, er.status as enrollment_status
        FROM notifications n
        LEFT JOIN enrollment_requests er ON n.related_id = er.id AND n.type IN ('enrollment_approved', 'enrollment_rejected')
        WHERE n.id = ? 
        AND n.type IN ('enrollment_approved', 'enrollment_rejected', 'course_kicked')
        AND n.user_id = ?  -- This should match the current student
    ");
    $stmt->execute([$notificationId, $studentId]);
    $notification = $stmt->fetch();
    
    if (!$notification) {
        echo json_encode(['success' => false, 'error' => 'Notification not found or does not belong to this student']);
        ob_end_flush();
        exit();
    }
    
    // For enrollment notifications, double-check that the student_id from enrollment request matches
    if ($notification['type'] !== 'course_kicked' && $notification['student_id'] != $studentId) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized - Notification does not belong to this student']);
        ob_end_flush();
        exit();
    }
    
    // Mark the notification as read in the notifications table
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ?
    ");
    $stmt->execute([$notificationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read successfully',
        'student_id' => $studentId,
        'notification_id' => $notificationId,
        'enrollment_status' => $notification['enrollment_status'],
        'viewed_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in mark_notification_read.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
}

ob_end_flush();
?>
