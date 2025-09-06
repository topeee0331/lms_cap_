<?php
/**
 * AJAX endpoint to get student notifications for the notification bell
 */

// Prevent any output before headers
ob_start();

// Disable error display for this AJAX endpoint
error_reporting(0);
ini_set('display_errors', 0);

// Debug: Log that we're starting
error_log("Student notifications endpoint called");

// Load only the necessary files
try {
    require_once '../config/database.php';
} catch (Exception $e) {
    // Log error and return JSON error response
    error_log("Error loading database config: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database configuration error']);
    exit();
}

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log session info
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Session role: " . ($_SESSION['role'] ?? 'not set'));

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    error_log("Unauthorized access attempt");
    ob_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];
error_log("Processing notifications for student ID: " . $student_id);

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Get academic year selection if available
    $selected_year_id = $_GET['academic_year_id'] ?? null;
    
    // Check when student last viewed their enrollment requests
    $view_stmt = $pdo->prepare("
        SELECT viewed_at 
        FROM student_notification_views 
        WHERE student_id = ? AND notification_type = 'enrollment_requests'
    ");
    $view_stmt->execute([$student_id]);
    $view_result = $view_stmt->fetch();
    $last_viewed = $view_result['viewed_at'] ?? null;
    
    // Get count of unread enrollment notifications (status changes)
    $enrollment_count = 0;
    try {
        // Count only unviewed enrollment notifications
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM notifications n
            JOIN enrollment_requests er ON n.related_id = er.id
            LEFT JOIN student_notification_views snv ON (
                snv.student_id = n.user_id 
                AND snv.notification_type = 'enrollment_status' 
                AND snv.notification_id = n.id
            )
            WHERE n.user_id = ? 
            AND n.type IN ('enrollment_approved', 'enrollment_rejected')
            AND snv.id IS NULL  -- Only count unviewed notifications
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        $enrollment_count = $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error counting enrollment notifications: " . $e->getMessage());
        $enrollment_count = 0;
    }
    
    error_log("Enrollment count (unviewed only): " . $enrollment_count);
    
    // Get recent enrollment status notifications (only those newer than last viewed)
    $enrollment_notifications = [];
    if ($enrollment_count > 0) {
        // First, get all enrollment status notifications
        $notifications_query = "
            SELECT 
                n.id as notification_id,
                er.id as enrollment_request_id,
                er.status,
                er.approved_at,
                er.updated_at,
                er.rejection_reason,
                c.course_name,
                c.course_code,
                u.first_name,
                u.last_name,
                n.created_at as notification_created_at
            FROM notifications n
            JOIN enrollment_requests er ON n.related_id = er.id
            JOIN courses c ON er.course_id = c.id
            JOIN users u ON c.teacher_id = u.id
            WHERE n.user_id = ? 
            AND n.type IN ('enrollment_approved', 'enrollment_rejected')
        ";
        
        $notif_params = [$student_id];
        
        // If student has viewed before, only show newer notifications
        if ($last_viewed) {
            $notifications_query .= " AND n.created_at > ?";
            $notif_params[] = $last_viewed;
        }
        
        $notifications_query .= " ORDER BY n.created_at DESC LIMIT 10";
        
        $stmt = $pdo->prepare($notifications_query);
        $stmt->execute($notif_params);
        $enrollment_results = $stmt->fetchAll();
        
        error_log("Found " . count($enrollment_results) . " enrollment notifications before filtering");
        
        // Now filter out notifications that have been individually marked as read
        $filtered_notifications = [];
        foreach ($enrollment_results as $notif) {
            // Check if this specific notification has been viewed
            $viewed_stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM student_notification_views 
                WHERE student_id = ? 
                AND notification_type = 'enrollment_status' 
                AND notification_id = ?
            ");
            $viewed_stmt->execute([$student_id, $notif['notification_id']]);
            $viewed_result = $viewed_stmt->fetch();
            
            // Only include if not viewed individually
            if ($viewed_result['count'] == 0) {
                $filtered_notifications[] = $notif;
            }
        }
        
        error_log("Found " . count($filtered_notifications) . " unviewed enrollment notifications after filtering");
        
        foreach ($filtered_notifications as $notif) {
            $status_icon = $notif['status'] === 'approved' ? 'bi-check-circle text-success' : 'bi-x-circle text-danger';
            $status_text = $notif['status'] === 'approved' ? 'Approved' : 'Rejected';
            $timestamp = $notif['notification_created_at'];
            
            $enrollment_notifications[] = [
                'id' => $notif['notification_id'],
                'type' => 'enrollment_' . $notif['status'],
                'title' => 'Enrollment ' . ucfirst($notif['status']),
                'message' => "Your enrollment request for '{$notif['course_name']}' has been {$notif['status']}.",
                'course_name' => $notif['course_name'],
                'course_code' => $notif['course_code'],
                'teacher_name' => ($notif['first_name'] ?? '') . ' ' . ($notif['last_name'] ?? ''),
                'status' => $notif['status'],
                'rejection_reason' => $notif['rejection_reason'],
                'created_at' => date('M j, Y g:i A', strtotime($timestamp)),
                'timestamp' => $timestamp
            ];
        }
    }
    
    // Get unread announcements count
    $announcement_count = 0;
    try {
        $ann_stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM announcements a
            WHERE (a.read_by IS NULL OR JSON_SEARCH(a.read_by, 'one', ?) IS NULL)
            AND a.is_active = 1
        ");
        $ann_stmt->execute([$student_id]);
        $ann_result = $ann_stmt->fetch();
        $announcement_count = $ann_result['count'] ?? 0;
    } catch (Exception $e) {
        $announcement_count = 0;
    }
    
    error_log("Announcement count: " . $announcement_count);
    
    // Clear any output buffer to ensure clean JSON
    ob_clean();
    
    // Prepare response data
    $response = [
        'success' => true,
        'enrollment_count' => $enrollment_count,
        'announcement_count' => $announcement_count,
        'total_count' => $enrollment_count + $announcement_count,
        'enrollment_notifications' => $enrollment_notifications,
        'last_viewed' => $last_viewed
    ];
    
    error_log("Sending response: " . json_encode($response));
    
    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Output the response
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in student get_notifications.php: " . $e->getMessage());
    
    // Clear any output buffer
    ob_clean();
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
?>
