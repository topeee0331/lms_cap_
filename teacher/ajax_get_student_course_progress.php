<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get parameters
$student_id = (int)($_GET['student_id'] ?? 0);
$course_id = (int)($_GET['course_id'] ?? 0);
$academic_period_id = (int)($_GET['academic_period_id'] ?? 0);

if (!$student_id || !$course_id || !$academic_period_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Student ID, Course ID, and Academic Period ID are required']);
    exit();
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get student basic info
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.identifier,
               s.section_name, s.year_level
        FROM users u
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', ?) IS NOT NULL
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id, $student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }
    
    // Get course details and verify teacher access
    $stmt = $pdo->prepare("
        SELECT c.id, c.course_name, c.course_code, c.description,
               e.enrolled_at, e.status, e.progress_percentage, e.last_accessed,
               e.module_progress, e.video_progress
        FROM courses c
        LEFT JOIN course_enrollments e ON e.course_id = c.id AND e.student_id = ?
        WHERE c.id = ? AND c.teacher_id = ? AND c.academic_period_id = ?
    ");
    $stmt->execute([$student_id, $course_id, $_SESSION['user_id'], $academic_period_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Course not found or you do not have access to this course']);
        exit();
    }
    
    // Get assessment statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT a.id) as total_assessments,
            COUNT(DISTINCT aa.assessment_id) as attempted_assessments,
            COUNT(DISTINCT CASE WHEN aa.score >= a.passing_rate THEN aa.assessment_id END) as passed_assessments,
            ROUND(AVG(aa.score), 2) as avg_score,
            MAX(aa.score) as best_score,
            MIN(aa.score) as worst_score,
            COUNT(aa.id) as total_attempts,
            ROUND(AVG(aa.time_taken), 0) as avg_time_taken
        FROM assessments a
        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id = ?
        WHERE a.course_id = ?
    ");
    $stmt->execute([$student_id, $course_id]);
    $assessment_stats = $stmt->fetch();
    
    // Get recent assessment attempts
    $stmt = $pdo->prepare("
        SELECT aa.*, a.assessment_title, a.passing_rate
        FROM assessment_attempts aa
        JOIN assessments a ON aa.assessment_id = a.id
        WHERE aa.student_id = ? AND a.course_id = ?
        ORDER BY aa.completed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$student_id, $course_id]);
    $recent_attempts = $stmt->fetchAll();
    
    // Get module progress
    $module_progress = [];
    if ($course['module_progress']) {
        $module_progress = json_decode($course['module_progress'], true) ?: [];
    }
    
    // Get video progress
    $video_progress = [];
    if ($course['video_progress']) {
        $video_progress = json_decode($course['video_progress'], true) ?: [];
    }
    
    // Calculate overall progress
    $total_modules = count($module_progress);
    $completed_modules = count(array_filter($module_progress, function($module) {
        return isset($module['is_completed']) && $module['is_completed'] == 1;
    }));
    
    $total_videos = count($video_progress);
    $watched_videos = count(array_filter($video_progress, function($video) {
        return isset($video['is_watched']) && $video['is_watched'] == 1;
    }));
    
    $overall_progress = 0;
    if ($total_modules > 0) {
        $overall_progress = round(($completed_modules / $total_modules) * 100);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'student' => $student,
        'course' => $course,
        'assessment_stats' => $assessment_stats,
        'recent_attempts' => $recent_attempts,
        'module_progress' => $module_progress,
        'video_progress' => $video_progress,
        'overall_progress' => $overall_progress,
        'completed_modules' => $completed_modules,
        'total_modules' => $total_modules,
        'watched_videos' => $watched_videos,
        'total_videos' => $total_videos,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log("Error in ajax_get_student_course_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while retrieving student course progress'
    ]);
}
?>
