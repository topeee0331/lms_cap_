<?php
/**
 * Fixed version of teacher notifications endpoint
 * Now checks if teacher has viewed their enrollment requests
 */

// Prevent any output before headers
ob_start();

// Set proper headers first
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'User not logged in or not a teacher'
    ]);
    exit();
}

$teacher_id = $_SESSION['user_id'];

try {
    // Get the absolute path to config files
    $config_path = dirname(__DIR__) . '/config/config.php';
    $database_path = dirname(__DIR__) . '/config/database.php';
    
    // Check if files exist
    if (!file_exists($config_path)) {
        throw new Exception('Config file not found at: ' . $config_path);
    }
    
    if (!file_exists($database_path)) {
        throw new Exception('Database file not found at: ' . $database_path);
    }
    
    // Load config files
    require_once $config_path;
    require_once $database_path;
    
    // Create database connection
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Test database connection
    $test_query = "SELECT 1 as test";
    $test_stmt = $pdo->prepare($test_query);
    $test_stmt->execute();
    $test_result = $test_stmt->fetch();
    
    if (!$test_result) {
        throw new Exception('Database connection test failed');
    }
    
    // Get academic year selection
    $selected_year_id = $_GET['academic_year_id'] ?? null;
    
    // Check when the teacher last viewed their enrollment requests
    $last_viewed = null;
    $view_stmt = $pdo->prepare("
        SELECT viewed_at 
        FROM teacher_notification_views 
        WHERE teacher_id = ? AND notification_type = 'enrollment_requests' AND academic_year_id = ?
    ");
    $view_stmt->execute([$teacher_id, $selected_year_id]);
    $view_result = $view_stmt->fetch();
    
    if ($view_result) {
        $last_viewed = $view_result['viewed_at'];
    }
    
    // Get pending enrollment requests count (only those newer than last viewed)
    $count_query = "
        SELECT COUNT(*) as pending_count
        FROM enrollment_requests er
        JOIN courses c ON er.course_id = c.id
        WHERE c.teacher_id = ? AND er.status = 'pending'
    ";
    
    // If teacher has viewed requests before, only count newer ones
    if ($last_viewed) {
        $count_query .= " AND er.requested_at > ?";
        $stmt = $pdo->prepare($count_query);
        $stmt->execute([$teacher_id, $last_viewed]);
    } else {
        $stmt = $pdo->prepare($count_query);
        $stmt->execute([$teacher_id]);
    }
    
    $pending_count = $stmt->fetch()['pending_count'];
    
    // Get recent notifications (only those newer than last viewed)
    $notifications_query = "
        SELECT 
            'enrollment_request' as type,
            er.id as related_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            c.course_name,
            er.requested_at as created_at,
            er.status
        FROM enrollment_requests er
        JOIN courses c ON er.course_id = c.id
        JOIN users u ON er.student_id = u.id
        WHERE c.teacher_id = ? AND er.status = 'pending'
    ";
    
    // If teacher has viewed requests before, only show newer ones
    if ($last_viewed) {
        $notifications_query .= " AND er.requested_at > ?";
        $stmt = $pdo->prepare($notifications_query);
        $stmt->execute([$teacher_id, $last_viewed]);
    } else {
        $stmt = $pdo->prepare($notifications_query);
        $stmt->execute([$teacher_id]);
    }
    
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
        'total_notifications' => count($formatted_notifications),
        'last_viewed' => $last_viewed,
        'has_viewed_requests' => !is_null($last_viewed)
    ];
    
    // Output the response
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_notifications_fixed.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage(),
        'pending_count' => 0,
        'notifications' => [],
        'total_notifications' => 0,
        'last_viewed' => null,
        'has_viewed_requests' => false
    ]);
}
?>
