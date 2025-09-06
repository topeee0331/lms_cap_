<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get student ID from request
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit;
}

try {
    // Get student details
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, username, identifier, is_irregular, status
        FROM users 
        WHERE id = ? AND role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    // Get enrolled courses for this student
    $stmt = $db->prepare("
        SELECT c.course_name, c.course_code, e.progress_percentage as progress
        FROM course_enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ? AND e.status = 'active'
        ORDER BY c.course_name
    ");
    $stmt->execute([$student_id]);
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current section for this student
    $stmt = $db->prepare("
        SELECT s.id as section_id, s.section_name, s.year_level
        FROM sections s
        WHERE JSON_SEARCH(s.students, 'one', ?) IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add section info to student data
    if ($section) {
        $student['section_id'] = $section['section_id'];
        $student['current_section'] = $section['section_name'] . ' (Year ' . $section['year_level'] . ')';
    } else {
        $student['section_id'] = null;
        $student['current_section'] = 'Not assigned';
    }
    
    // Add enrolled courses to student data
    $student['enrolled_courses'] = $enrolled_courses;
    
    echo json_encode([
        'success' => true,
        'student' => $student
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching student profile: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching student profile: ' . $e->getMessage()
    ]);
}
?>
