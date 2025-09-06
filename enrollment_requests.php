<?php
/**
 * Main Enrollment Requests Page
 * Redirects users to their role-specific enrollment requests page
 * For teachers: marks notifications as read when they view all requests
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Start session
session_start();

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

// Redirect based on user role
switch ($user_role) {
    case 'teacher':
        // For teachers, mark their pending enrollment requests as "viewed" to clear notifications
        if (isset($user_id) && $user_id) {
            try {
                $db = new Database();
                $pdo = $db->getConnection();
                
                // Get current academic year if available
                $academic_year_id = $_GET['academic_year_id'] ?? null;
                
                // Record that this teacher has viewed their enrollment requests
                $stmt = $pdo->prepare("
                    INSERT INTO teacher_notification_views (teacher_id, notification_type, academic_year_id) 
                    VALUES (?, 'enrollment_requests', ?) 
                    ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$user_id, $academic_year_id]);
                
                // Log that teacher viewed all requests (for debugging)
                error_log("Teacher {$user_id} viewed all enrollment requests - notifications cleared");
                
            } catch (Exception $e) {
                error_log("Error processing teacher enrollment requests view: " . $e->getMessage());
            }
        }
        
        // Redirect to teacher's enrollment requests page
        header('Location: teacher/enrollment_requests.php');
        exit();
        break;
        
    case 'student':
        // Redirect to student's enrollment requests page
        header('Location: student/enrollment_requests.php');
        exit();
        break;
        
    case 'admin':
        // For admins, show a comprehensive view of all enrollment requests
        // Redirect to admin dashboard or create admin-specific view
        header('Location: admin/dashboard.php');
        exit();
        break;
        
    default:
        // Unknown role, redirect to dashboard
        header('Location: index.php');
        exit();
        break;
}
?>
