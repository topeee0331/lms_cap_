<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get teacher ID from POST data
$teacher_id = $_POST['teacher_id'] ?? null;

if (!$teacher_id) {
    echo json_encode(['success' => false, 'error' => 'Teacher ID is required']);
    exit;
}

try {
    // Fetch basic teacher details
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.username,
            u.email,
            u.identifier,
            u.status,
            u.profile_picture,
            u.department,
            u.created_at,
            u.updated_at
        FROM users u 
        WHERE u.id = ? AND u.role = 'teacher'
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        echo json_encode(['success' => false, 'error' => 'Teacher not found']);
        exit;
    }
    
    // Generate profile picture URL
    $teacher['profile_picture_url'] = getProfilePictureUrl($teacher['profile_picture'] ?? null, 'large');
    
    // Get teacher's courses with detailed information
    $courses_stmt = $db->prepare("
        SELECT 
            c.id,
            c.course_name,
            c.course_code,
            c.description,
            c.status,
            c.year_level,
            c.credits,
            c.created_at,
            c.modules,
            c.sections,
            ap.academic_year,
            ap.semester_name
        FROM courses c
        LEFT JOIN academic_periods ap ON c.academic_period_id = ap.id
        WHERE c.teacher_id = ? AND c.is_archived = 0
        ORDER BY c.created_at DESC
    ");
    $courses_stmt->execute([$teacher_id]);
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate course statistics and get student counts
    $total_courses = count($courses);
    $active_courses = count(array_filter($courses, function($course) { return $course['status'] === 'active'; }));
    
    // Get student counts for each course by checking sections
    $total_students = 0;
    foreach ($courses as &$course) {
        $modules = json_decode($course['modules'] ?? '[]', true);
        $course['module_count'] = is_array($modules) ? count($modules) : 0;
        
        $total_videos = 0;
        $total_assessments = 0;
        
        if (is_array($modules)) {
            foreach ($modules as $module) {
                if (isset($module['videos']) && is_array($module['videos'])) {
                    $total_videos += count($module['videos']);
                }
                if (isset($module['assessments']) && is_array($module['assessments'])) {
                    $total_assessments += count($module['assessments']);
                }
            }
        }
        
        $course['video_count'] = $total_videos;
        $course['assessment_count'] = $total_assessments;
        
        // Get student count for this course
        $course_student_count = 0;
        if (!empty($course['sections'])) {
            $section_ids = json_decode($course['sections'], true);
            if (is_array($section_ids) && !empty($section_ids)) {
                $placeholders = str_repeat('?,', count($section_ids) - 1) . '?';
                $student_count_stmt = $db->prepare("
                    SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(s.students, CONCAT('$[', numbers.n, ']')))) as student_count
                    FROM sections s
                    CROSS JOIN (
                        SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION 
                        SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION 
                        SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION 
                        SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION 
                        SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION 
                        SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION 
                        SELECT 30 UNION SELECT 31 UNION SELECT 32 UNION SELECT 33 UNION SELECT 34 UNION 
                        SELECT 35 UNION SELECT 36 UNION SELECT 37 UNION SELECT 38 UNION SELECT 39 UNION 
                        SELECT 40 UNION SELECT 41 UNION SELECT 42 UNION SELECT 43 UNION SELECT 44 UNION 
                        SELECT 45 UNION SELECT 46 UNION SELECT 47 UNION SELECT 48 UNION SELECT 49
                    ) numbers
                    WHERE s.id IN ($placeholders)
                    AND JSON_UNQUOTE(JSON_EXTRACT(s.students, CONCAT('$[', numbers.n, ']'))) IS NOT NULL
                    AND JSON_UNQUOTE(JSON_EXTRACT(s.students, CONCAT('$[', numbers.n, ']'))) != ''
                ");
                $student_count_stmt->execute($section_ids);
                $student_count_result = $student_count_stmt->fetch(PDO::FETCH_ASSOC);
                $course_student_count = $student_count_result['student_count'] ?? 0;
            }
        }
        
        $course['student_count'] = $course_student_count;
        $total_students += $course_student_count;
    }
    unset($course);
    
    // Get teacher's assigned sections through courses
    $sections_stmt = $db->prepare("
        SELECT DISTINCT
            s.id,
            s.section_name,
            s.year_level,
            s.students,
            ap.academic_year,
            ap.semester_name
        FROM sections s
        LEFT JOIN academic_periods ap ON s.academic_period_id = ap.id
        INNER JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        WHERE c.teacher_id = ? AND s.is_active = 1
        ORDER BY s.year_level, s.section_name
    ");
    $sections_stmt->execute([$teacher_id]);
    $sections_raw = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate student counts for each section
    $sections = [];
    foreach ($sections_raw as $section) {
        $student_count = 0;
        if (!empty($section['students'])) {
            $students = json_decode($section['students'], true);
            if (is_array($students)) {
                $student_count = count(array_filter($students, function($student) {
                    return !empty($student);
                }));
            }
        }
        
        $sections[] = [
            'id' => $section['id'],
            'section_name' => $section['section_name'],
            'year_level' => $section['year_level'],
            'academic_year' => $section['academic_year'],
            'semester_name' => $section['semester_name'],
            'student_count' => $student_count
        ];
    }
    
    // Get assessment statistics
    $assessment_stats_stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT a.id) as total_assessments,
            COUNT(DISTINCT aa.id) as total_attempts,
            AVG(aa.score) as average_score,
            COUNT(DISTINCT aa.student_id) as students_taken
        FROM courses c
        LEFT JOIN assessments a ON c.id = a.course_id
        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.status = 'completed'
        WHERE c.teacher_id = ? AND c.is_archived = 0
    ");
    $assessment_stats_stmt->execute([$teacher_id]);
    $assessment_stats = $assessment_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activity (last 30 days)
    $recent_activity_stmt = $db->prepare("
        SELECT 
            'course_created' as activity_type,
            c.course_name as title,
            c.created_at as activity_date
        FROM courses c
        WHERE c.teacher_id = ? AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'assessment_completed' as activity_type,
            CONCAT('Assessment completed: ', a.assessment_title) as title,
            aa.completed_at as activity_date
        FROM courses c
        LEFT JOIN assessments a ON c.id = a.course_id
        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id
        WHERE c.teacher_id = ? AND aa.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        ORDER BY activity_date DESC
        LIMIT 10
    ");
    $recent_activity_stmt->execute([$teacher_id, $teacher_id]);
    $recent_activity = $recent_activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compile comprehensive teacher data
    $teacher_data = [
        'basic_info' => $teacher,
        'courses' => $courses,
        'sections' => $sections,
        'statistics' => [
            'total_courses' => $total_courses,
            'active_courses' => $active_courses,
            'total_students' => $total_students,
            'total_sections' => count($sections),
            'total_assessments' => $assessment_stats['total_assessments'] ?? 0,
            'total_attempts' => $assessment_stats['total_attempts'] ?? 0,
            'average_score' => round($assessment_stats['average_score'] ?? 0, 2),
            'students_taken_assessments' => $assessment_stats['students_taken'] ?? 0
        ],
        'recent_activity' => $recent_activity
    ];
    
    echo json_encode([
        'success' => true,
        'teacher' => $teacher_data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
