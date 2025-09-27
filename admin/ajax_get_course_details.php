<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get course code from request
$course_code = isset($_GET['course_code']) ? trim($_GET['course_code']) : '';

if (empty($course_code)) {
    echo json_encode(['success' => false, 'message' => 'Course code is required']);
    exit;
}

try {
    // Get course details
    $stmt = $db->prepare("
        SELECT c.*, 
               CASE 
                   WHEN c.year_level = 1 THEN '1st Year'
                   WHEN c.year_level = 2 THEN '2nd Year'
                   WHEN c.year_level = 3 THEN '3rd Year'
                   WHEN c.year_level = 4 THEN '4th Year'
                   ELSE CONCAT(c.year_level, 'th Year')
               END as year_level_text
        FROM courses c 
        WHERE c.course_code = ? AND c.is_archived = 0
    ");
    $stmt->execute([$course_code]);
    $course = $stmt->fetch();
    
    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        exit;
    }
    
    // Get the teacher assigned to this course
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role
        FROM users u
        WHERE u.id = ? AND u.role = 'teacher'
    ");
    $stmt->execute([$course['teacher_id']]);
    $teacher = $stmt->fetch();
    
    // Format teacher data
    $teachers = [];
    if ($teacher) {
        $teachers[] = [
            'id' => $teacher['id'],
            'first_name' => $teacher['first_name'],
            'last_name' => $teacher['last_name'],
            'email' => $teacher['email'],
            'role' => $teacher['role']
        ];
    }
    
    // Get sections assigned to this course
    $stmt = $db->prepare("
        SELECT s.id, s.section_name, s.year_level, s.is_active,
               ap.academic_year, ap.semester_name,
               CASE 
                   WHEN s.year_level = 1 THEN '1st Year'
                   WHEN s.year_level = 2 THEN '2nd Year'
                   WHEN s.year_level = 3 THEN '3rd Year'
                   WHEN s.year_level = 4 THEN '4th Year'
                   ELSE CONCAT(s.year_level, 'th Year')
               END as year_level_text
        FROM sections s
        LEFT JOIN academic_periods ap ON s.academic_period_id = ap.id
        WHERE JSON_SEARCH(?, 'one', s.id) IS NOT NULL
        ORDER BY s.year_level, s.section_name
    ");
    $stmt->execute([$course['sections']]);
    $sections = $stmt->fetchAll();
    
    // Format course data
    $formatted_course = [
        'id' => $course['id'],
        'course_code' => $course['course_code'],
        'course_name' => $course['course_name'],
        'year_level' => $course['year_level'],
        'year_level_text' => $course['year_level_text'],
        'description' => $course['description'],
        'status' => $course['status'],
        'created_at' => $course['created_at'],
        'updated_at' => $course['updated_at']
    ];
    
    // Format teachers data
    $formatted_teachers = [];
    foreach ($teachers as $teacher) {
        $formatted_teachers[] = [
            'id' => $teacher['id'],
            'first_name' => $teacher['first_name'],
            'last_name' => $teacher['last_name'],
            'email' => $teacher['email'],
            'role' => $teacher['role']
        ];
    }
    
    // Format sections data
    $formatted_sections = [];
    foreach ($sections as $section) {
        $formatted_sections[] = [
            'id' => $section['id'],
            'section_name' => $section['section_name'],
            'year_level' => $section['year_level'],
            'year_level_text' => $section['year_level_text'],
            'is_active' => $section['is_active'],
            'academic_year' => $section['academic_year'],
            'semester_name' => $section['semester_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'course' => $formatted_course,
        'teachers' => $formatted_teachers,
        'sections' => $formatted_sections,
        'total_teachers' => count($formatted_teachers),
        'total_sections' => count($formatted_sections)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching course details: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching course details: ' . $e->getMessage()
    ]);
}
?>
