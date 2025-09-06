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
$academic_year_id = $_GET['academic_year_id'] ?? null;

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // If no academic year specified, get current active one
    if (!$academic_year_id) {
        $stmt = $pdo->prepare("SELECT id FROM academic_periods WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
        $academic_year_id = $current_year ? $current_year['id'] : null;
    }
    
    if (!$academic_year_id) {
        echo json_encode([
            'enrolled_courses' => [],
            'section_available_courses' => [],
            'summary_stats' => [
                'enrolled_count' => 0,
                'section_available_count' => 0,
                'non_section_count' => 0,
                'inactive_periods_count' => 0
            ],
            'semester_info' => [
                'current_year' => 'N/A',
                'current_semester' => 'N/A'
            ]
        ]);
        exit();
    }
    
    // Get enrolled courses
    $stmt = $pdo->prepare("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name, e.enrolled_at,
               ay.year as academic_year, s.name as semester_name,
               (SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id) as module_count,
               (SELECT COUNT(*) FROM assessments a JOIN course_modules cm ON a.module_id = cm.id WHERE cm.course_id = c.id) as assessment_count,
               (SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id AND cm.is_locked = 0) as unlocked_modules,
               c.year_level, c.created_at, c.updated_at
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        JOIN course_enrollments e ON c.id = e.course_id
        JOIN academic_periods ay ON c.academic_period_id = ay.id

        WHERE e.student_id = ? AND e.status = 'active' AND c.is_archived = 0 AND c.academic_year_id = ?
        ORDER BY e.enrolled_at ASC
    ");
    $stmt->execute([$user_id, $academic_year_id]);
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get section available courses
    $stmt = $pdo->prepare("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name, 
               (SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id) as module_count,
               (SELECT COUNT(*) FROM course_enrollments e WHERE e.course_id = c.id AND e.status = 'active') as enrolled_students,
               CASE WHEN e2.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled,
               CASE WHEN er.student_id IS NOT NULL AND er.status = 'pending' THEN 1 ELSE 0 END as has_pending_request,
               CASE WHEN er.student_id IS NOT NULL AND er.status = 'rejected' THEN 1 ELSE 0 END as has_rejected_request,
               er.rejection_reason,
               er.approved_at,
               1 as is_section_assigned,
               ay.is_active as academic_year_active,
               ay.year as academic_year,
               s.name as semester_name,
               s.is_active as semester_active,
               c.created_at,
               c.updated_at,
               c.year_level,
               (SELECT COUNT(*) FROM assessments a JOIN course_modules cm ON a.module_id = cm.id WHERE cm.course_id = c.id) as assessment_count,
               (SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id AND cm.is_locked = 0) as unlocked_modules
        FROM section_students ss
        JOIN course_sections cs ON ss.section_id = cs.section_id
        JOIN courses c ON cs.course_id = c.id
        JOIN users u ON c.teacher_id = u.id
        JOIN academic_periods ay ON c.academic_period_id = ay.id

        LEFT JOIN course_enrollments e2 ON c.id = e2.course_id AND e2.student_id = ? AND e2.status = 'active'
        LEFT JOIN enrollment_requests er ON c.id = er.course_id AND er.student_id = ?
        WHERE ss.student_id = ? AND c.is_archived = 0 AND c.academic_year_id = ? AND e2.student_id IS NULL
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $academic_year_id]);
    $section_available_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get non-section courses
    $stmt = $pdo->prepare("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name, 
               (SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id) as module_count,
               (SELECT COUNT(*) FROM course_enrollments e WHERE e.course_id = c.id AND e.status = 'active') as enrolled_students,
               CASE WHEN e2.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled,
               CASE WHEN er.student_id IS NOT NULL AND er.status = 'pending' THEN 1 ELSE 0 END as has_pending_request,
               CASE WHEN er.student_id IS NOT NULL AND er.status = 'rejected' THEN 1 ELSE 0 END as has_rejected_request,
               er.rejection_reason,
               er.approved_at,
               0 as is_section_assigned,
               ay.is_active as academic_year_active,
               ay.year as academic_year,
               s.name as semester_name,
               s.is_active as semester_active,
               c.created_at,
               c.updated_at,
               c.year_level,
               (SELECT COUNT(*) FROM assessments a JOIN course_modules cm ON a.module_id = cm.id WHERE cm.course_id = c.id) as assessment_count,
               (SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id AND cm.is_locked = 0) as unlocked_modules
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        JOIN academic_periods ay ON c.academic_period_id = ay.id

        LEFT JOIN course_enrollments e2 ON c.id = e2.course_id AND e2.student_id = ? AND e2.status = 'active'
        LEFT JOIN enrollment_requests er ON c.id = er.course_id AND er.student_id = ?
        WHERE c.is_archived = 0 AND c.academic_year_id = ? 
        AND c.id NOT IN (
            SELECT DISTINCT cs.course_id 
            FROM section_students ss 
            JOIN course_sections cs ON ss.section_id = cs.section_id 
            WHERE ss.student_id = ?
        )
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id, $academic_year_id, $user_id]);
    $non_section_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $summary_stats = [
        'enrolled_count' => count($enrolled_courses),
        'section_available_count' => count($section_available_courses),
        'non_section_count' => count($non_section_courses),
        'inactive_periods_count' => 0
    ];
    
    // Count inactive periods
    $all_courses = array_merge($enrolled_courses, $section_available_courses, $non_section_courses);
    foreach ($all_courses as $course) {
        if (!$course['academic_year_active'] || !$course['semester_active']) {
            $summary_stats['inactive_periods_count']++;
        }
    }
    
    // Get current academic period info
    $stmt = $pdo->prepare("
        SELECT academic_year, semester_name
        FROM academic_periods
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$academic_year_id]);
    $current_semester = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $semester_info = [
        'current_year' => $current_semester ? $current_semester['academic_year'] : 'N/A',
        'current_semester' => $current_semester ? $current_semester['semester_name'] : 'N/A'
    ];
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'enrolled_courses' => $enrolled_courses,
        'section_available_courses' => $section_available_courses,
        'non_section_courses' => $non_section_courses,
        'summary_stats' => $summary_stats,
        'semester_info' => $semester_info,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
