<?php
/**
 * AJAX endpoint to get teacher notifications for the notification bell
 */

// Prevent any output before headers
ob_start();

// Load only the necessary files
require_once '../config/config.php';
require_once '../config/database.php';

// Start session
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['user_id'];

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get academic year selection
    $selected_year_id = $_GET['academic_year_id'] ?? null;
    
    // Get pending enrollment requests count
    $count_query = "
        SELECT COUNT(*) as pending_count
        FROM enrollment_requests er
        JOIN courses c ON er.course_id = c.id
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', er.student_id) IS NOT NULL
        WHERE c.teacher_id = ? AND er.status = 'pending'
    ";
    
    $count_params = [$teacher_id];
    
    if ($selected_year_id) {
        $count_query .= " AND s.id = ?";
        $count_params[] = $selected_year_id;
    }
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $pending_count = $stmt->fetch()['pending_count'];
    
    // Get recent notifications (enrollment requests, assessment results, etc.)
    $notifications_query = "
        SELECT 
            'enrollment_request' as type,
            er.id as related_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            c.course_name,
            er.requested_at as created_at,
            er.status,
            er.rejection_reason
        FROM enrollment_requests er
        JOIN courses c ON er.course_id = c.id
        JOIN users u ON er.student_id = u.id
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', er.student_id) IS NOT NULL
        WHERE c.teacher_id = ? AND er.status = 'pending'
        " . ($selected_year_id ? "AND c.academic_year_id = ?" : "") . "
        ORDER BY er.requested_at DESC
        LIMIT 10
    ";
    
    $notif_params = [$teacher_id];
    if ($selected_year_id) {
        $notif_params[] = $selected_year_id;
    }
    
    $stmt = $pdo->prepare($notifications_query);
    $stmt->execute($notif_params);
    $notifications = $stmt->fetchAll();
    
    // Format notifications for display
    $formatted_notifications = [];
    foreach ($notifications as $notif) {
        $formatted_notifications[] = [
            'id' => $notif['related_id'],
            'type' => $notif['type'],
            'title' => 'New Enrollment Request',
            'message' => "{$notif['student_name']} requested enrollment in {$notif['course_name']}",
            'student_name' => $notif['student_name'],
            'course_name' => $notif['course_name'],
            'created_at' => date('M j, Y g:i A', strtotime($notif['created_at'])),
            'status' => $notif['status']
        ];
    }
    
    // Prepare response data
    $response = [
        'success' => true,
        'pending_count' => $pending_count,
        'notifications' => $formatted_notifications,
        'total_notifications' => count($formatted_notifications)
    ];
    
    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Output the response
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_notifications.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'pending_count' => 0,
        'notifications' => [],
        'total_notifications' => 0
    ]);
}
?>
