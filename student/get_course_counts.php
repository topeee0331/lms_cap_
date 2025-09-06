<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get current academic year
    $stmt = $pdo->prepare("SELECT id FROM academic_periods WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
    $year_id = $current_year ? $current_year['id'] : null;
    
    if (!$year_id) {
        echo json_encode([
            'enrolled_count' => 0,
            'section_available_count' => 0,
            'non_section_count' => 0,
            'inactive_periods_count' => 0
        ]);
        exit();
    }
    
    // Get enrolled courses count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM course_enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ? AND e.status = 'active' AND c.is_archived = 0 AND c.academic_year_id = ?
    ");
    $stmt->execute([$user_id, $year_id]);
    $enrolled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get section available courses count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as count
        FROM section_students ss
        JOIN course_sections cs ON ss.section_id = cs.section_id
        JOIN courses c ON cs.course_id = c.id
        LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.student_id = ? AND e.status = 'active'
        WHERE ss.student_id = ? AND c.is_archived = 0 AND c.academic_year_id = ? AND e.student_id IS NULL
    ");
    $stmt->execute([$user_id, $user_id, $year_id]);
    $section_available_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get non-section courses count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as count
        FROM courses c
        LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.student_id = ? AND e.status = 'active'
        WHERE c.is_archived = 0 AND c.academic_year_id = ? AND e.student_id IS NULL
        AND c.id NOT IN (
            SELECT DISTINCT cs.course_id 
            FROM section_students ss 
            JOIN course_sections cs ON ss.section_id = cs.section_id 
            WHERE ss.student_id = ?
        )
    ");
    $stmt->execute([$user_id, $year_id, $user_id]);
    $non_section_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get inactive periods count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as count
        FROM courses c
        JOIN academic_periods ay ON c.academic_period_id = ay.id
        WHERE c.is_archived = 0 AND c.academic_period_id = ? AND ay.is_active = 0
    ");
    $stmt->execute([$year_id]);
    $inactive_periods_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'enrolled_count' => (int)$enrolled_count,
        'section_available_count' => (int)$section_available_count,
        'non_section_count' => (int)$non_section_count,
        'inactive_periods_count' => (int)$inactive_periods_count,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
