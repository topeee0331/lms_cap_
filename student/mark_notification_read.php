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
    // The notification.user_id should match the student's ID
    $stmt = $pdo->prepare("
        SELECT n.*, er.student_id, er.status as enrollment_status
        FROM notifications n
        JOIN enrollment_requests er ON n.related_id = er.id
        WHERE n.id = ? 
        AND n.type IN ('enrollment_approved', 'enrollment_rejected')
        AND n.user_id = ?  -- This should match the current student
    ");
    $stmt->execute([$notificationId, $studentId]);
    $notification = $stmt->fetch();
    
    if (!$notification) {
        echo json_encode(['success' => false, 'error' => 'Notification not found or does not belong to this student']);
        ob_end_flush();
        exit();
    }
    
    // Double-check that the student_id from enrollment request matches
    if ($notification['student_id'] != $studentId) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized - Notification does not belong to this student']);
        ob_end_flush();
        exit();
    }
    
    // Mark the notification as read by updating the student_notification_views table
    // We'll insert with notification_id for individual tracking
    $stmt = $pdo->prepare("
        INSERT INTO student_notification_views (student_id, notification_type, notification_id, viewed_at)
        VALUES (?, 'enrollment_status', ?, NOW())
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ");
    $stmt->execute([$studentId, $notificationId]);
    
    // Also mark the notification as read in the notifications table if it has a read_at field
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET read_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$notificationId]);
    } catch (Exception $e) {
        // If read_at field doesn't exist, that's okay
        error_log("Note: notifications table may not have read_at field: " . $e->getMessage());
    }
    
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
