<?php
/**
 * Endpoint to mark enrollment notifications as viewed when teacher visits the enrollment requests page
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

if (!isLoggedIn() || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Teacher access required']);
    ob_end_flush();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    ob_end_flush();
    exit();
}

try {
    $teacherId = $_SESSION['user_id'];
    $academicYearId = $_POST['academic_year_id'] ?? null;
    
    // Update the teacher_notification_views table to mark enrollment notifications as viewed
    if ($academicYearId) {
        $stmt = $pdo->prepare("
            INSERT INTO teacher_notification_views (teacher_id, notification_type, academic_year_id, viewed_at) 
            VALUES (?, 'enrollment_requests', ?, NOW()) 
            ON DUPLICATE KEY UPDATE viewed_at = NOW()
        ");
        $stmt->execute([$teacherId, $academicYearId]);
    } else {
        // If no academic year specified, mark as viewed for all academic years
        $stmt = $pdo->prepare("
            INSERT INTO teacher_notification_views (teacher_id, notification_type, viewed_at) 
            VALUES (?, 'enrollment_requests', NOW()) 
            ON DUPLICATE KEY UPDATE viewed_at = NOW()
        ");
        $stmt->execute([$teacherId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Enrollment notifications marked as viewed successfully',
        'teacher_id' => $teacherId,
        'academic_year_id' => $academicYearId,
        'viewed_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in mark_notifications_viewed.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

ob_end_flush();
?>
