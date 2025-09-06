<?php
/**
 * AJAX endpoint to get enrollment requests for real-time updates
 */

// Prevent any output before headers
ob_start();

// Load only the necessary files, not the full header
require_once '../config/config.php';
require_once '../config/database.php';

// Start session
session_start();

// Simple CSRF token validation function (if needed)
function validateCSRFToken($token) {
    return !empty($token) && strlen($token) > 10;
}

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
    
    // Build the base query for pending requests
    $pending_query = "
        SELECT er.*, c.course_name, c.course_code, u.first_name, u.last_name, u.username,
               er.requested_at, er.status, er.rejection_reason, er.approved_at,
               s.section_name, s.year_level as academic_year,
               CASE WHEN JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL THEN 1 ELSE 0 END as is_irregular,
               CASE WHEN c.academic_period_id IS NOT NULL THEN 1 ELSE 0 END as is_section_assigned
        FROM enrollment_requests er
        JOIN courses c ON er.course_id = c.id
        JOIN users u ON er.student_id = u.id
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        WHERE c.teacher_id = ? AND er.status = 'pending'
    ";
    
    $params = [$teacher_id];
    
    // Add academic year filter if selected
    if ($selected_year_id) {
        $pending_query .= " AND s.id = ?";
        $params[] = $selected_year_id;
    }
    
    $pending_query .= " ORDER BY er.requested_at ASC";
    
    $stmt = $pdo->prepare($pending_query);
    $stmt->execute($params);
    $pending_requests = $stmt->fetchAll();
    
    // Get processed requests (approved/rejected)
    $processed_query = "
        SELECT er.*, c.course_name, c.course_code, u.first_name, u.last_name, u.username,
               er.requested_at, er.status, er.rejection_reason, er.approved_at,
               s.section_name, s.year_level as academic_year
        FROM enrollment_requests er
        JOIN courses c ON er.course_id = c.id
        JOIN users u ON er.student_id = u.id
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        WHERE c.teacher_id = ? AND er.status IN ('approved', 'rejected')
    ";
    
    $processed_params = [$teacher_id];
    
    // Add academic year filter if selected
    if ($selected_year_id) {
        $processed_query .= " AND s.id = ?";
        $processed_params[] = $selected_year_id;
    }
    
    $processed_query .= " ORDER BY er.approved_at DESC LIMIT 20";
    
    $stmt = $pdo->prepare($processed_query);
    $stmt->execute($processed_params);
    $processed_requests = $stmt->fetchAll();
    
    // Get pending count for badge
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
    
    // Prepare response data
    $response = [
        'success' => true,
        'pending_requests' => $pending_requests,
        'processed_requests' => $processed_requests,
        'pending_count' => $pending_count,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Clear any output buffer
    ob_clean();
    
    // Set JSON header
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>
