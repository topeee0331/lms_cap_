<?php
/**
 * Endpoint to mark a specific student notification as unread
 */

// Prevent any output before headers
ob_start();

// Disable error display
error_reporting(0);
ini_set('display_errors', 0);

// Load database
require_once '../config/database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if POST data is provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$student_id = $_SESSION['user_id'];
$notification_id = $_POST['notification_id'] ?? null;
$notification_type = $_POST['notification_type'] ?? null;

if (!$notification_id || !$notification_type) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing notification ID or type']);
    exit();
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Mark the specific notification as unread
    $stmt = $pdo->prepare("
        UPDATE student_notifications 
        SET is_read = 0 
        WHERE id = ? AND student_id = ? AND type = ?
    ");
    $stmt->execute([$notification_id, $student_id, $notification_type]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Notification not found or already unread");
    }
    
    // Update the student notification view to an earlier timestamp
    // This will make the notification appear as "new" again
    $stmt = $pdo->prepare("
        UPDATE student_notification_views 
        SET viewed_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)
        WHERE student_id = ? AND notification_type = 'enrollment_requests'
    ");
    $stmt->execute([$student_id]);
    
    // Clear output buffer
    ob_clean();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Notification marked as unread successfully',
        'notification_id' => $notification_id
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in mark_notification_unread.php: " . $e->getMessage());
    
    // Clear output buffer
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
