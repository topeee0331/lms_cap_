<?php
// Start output buffering to prevent any output before headers
ob_start();

// Include necessary files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear any output that might have been sent
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - Student access required'
    ]);
    ob_end_flush();
    exit();
}

try {
    // Get student ID
    $studentId = $_SESSION['user_id'];
    
    // Check when student last viewed their enrollment requests
    $view_stmt = $pdo->prepare("
        SELECT viewed_at 
        FROM student_notification_views 
        WHERE student_id = ? AND notification_type = 'enrollment_requests'
    ");
    $view_stmt->execute([$studentId]);
    $view_result = $view_stmt->fetch();
    $last_viewed = $view_result['viewed_at'] ?? null;
    
    // Count notifications based on when they were last viewed
    $unread_count = 0;
    if ($last_viewed) {
        // Count status changes that happened after the student last viewed
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM enrollment_requests er
            WHERE er.student_id = ? 
            AND er.status IN ('approved', 'rejected')
            AND (
                (er.approved_at IS NOT NULL AND er.approved_at > ?) OR
                (er.updated_at IS NOT NULL AND er.updated_at > ?)
            )
        ");
        $count_stmt->execute([$studentId, $last_viewed, $last_viewed]);
        $result = $count_stmt->fetch();
        $unread_count = (int)($result['count'] ?? 0);
    } else {
        // If never viewed, count all status changes
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM enrollment_requests er
            WHERE er.student_id = ? 
            AND er.status IN ('approved', 'rejected')
        ");
        $count_stmt->execute([$studentId]);
        $result = $count_stmt->fetch();
        $unread_count = (int)($result['count'] ?? 0);
    }
    
    // Count pending requests (these should always show a badge)
    $pending_stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM enrollment_requests 
        WHERE student_id = ? AND status = 'pending'
    ");
    $pending_stmt->execute([$studentId]);
    $pending_result = $pending_stmt->fetch();
    $pending_count = (int)($pending_result['count'] ?? 0);
    
    // Total count is unread status changes + pending requests
    $total_count = $unread_count + $pending_count;
    
    echo json_encode([
        'success' => true,
        'pending_count' => $total_count,
        'pending_requests' => $pending_count,
        'status_changes' => $unread_count,
        'last_viewed' => $last_viewed,
        'message' => 'Student enrollment request count retrieved successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error in ajax_get_student_enrollment_requests.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'pending_count' => 0
    ]);
}

ob_end_flush();
?>
