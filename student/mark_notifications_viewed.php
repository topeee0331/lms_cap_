<?php
/**
 * Endpoint to mark enrollment notifications as viewed when student visits the page
 * This ensures that reloading the page properly updates the viewed timestamp
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
    
    // Update the student_notification_views table to mark enrollment notifications as viewed
    $stmt = $pdo->prepare("
        INSERT INTO student_notification_views (student_id, viewed_at, notification_type) 
        VALUES (?, NOW(), 'enrollment_requests') 
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ");
    $stmt->execute([$studentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Enrollment notifications marked as viewed successfully',
        'student_id' => $studentId,
        'viewed_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in mark_notifications_viewed.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

ob_end_flush();
?>
