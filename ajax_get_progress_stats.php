<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/score_calculator.php';

$db = new Database();
$pdo = $db->getConnection();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - Login required'
    ]);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get overall progress statistics using new database structure
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT e.course_id) as total_courses
        FROM course_enrollments e
        WHERE e.student_id = ? AND e.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $total_courses = $stmt->fetchColumn();

    // Get module progress from course_enrollments.module_progress JSON
    $stmt = $pdo->prepare("
        SELECT e.module_progress, e.video_progress, c.modules
        FROM course_enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ? AND e.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $completed_modules = 0;
    $total_modules = 0;
    $watched_videos = 0;
    $total_videos = 0;

    foreach ($enrollments as $enrollment) {
        // Parse course modules
        $course_modules = json_decode($enrollment['modules'] ?? '[]', true) ?: [];
        $total_modules += count($course_modules);
        
        // Parse student's module progress
        $module_progress = json_decode($enrollment['module_progress'] ?? '{}', true) ?: [];
        $video_progress = json_decode($enrollment['video_progress'] ?? '{}', true) ?: [];
        
        // Count completed modules
        foreach ($module_progress as $module_id => $progress) {
            if (isset($progress['is_completed']) && $progress['is_completed']) {
                $completed_modules++;
            }
        }
        
        // Count videos and watched videos
        foreach ($course_modules as $module) {
            if (isset($module['videos'])) {
                $total_videos += count($module['videos']);
                
                foreach ($module['videos'] as $video) {
                    if (isset($video_progress[$video['id']]) && $video_progress[$video['id']]['is_watched']) {
                        $watched_videos++;
                    }
                }
            }
        }
    }

    // Get assessment progress
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT aa.assessment_id) as completed_assessments
        FROM assessment_attempts aa
        WHERE aa.student_id = ? AND aa.status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $completed_assessments = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.id) as total_assessments
        FROM assessments a
        JOIN course_enrollments e ON a.course_id = e.course_id
        WHERE e.student_id = ? AND e.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $total_assessments = $stmt->fetchColumn();

    $overall_stats = [
        'total_courses' => $total_courses,
        'completed_modules' => $completed_modules,
        'total_modules' => $total_modules,
        'watched_videos' => $watched_videos,
        'total_videos' => $total_videos,
        'completed_assessments' => $completed_assessments,
        'total_assessments' => $total_assessments
    ];

    // Calculate average score using the correct calculation
    $overall_stats['average_score'] = calculateAverageScore($pdo, $user_id);

    // Calculate percentages
    $module_progress = $overall_stats['total_modules'] > 0 ? round(($overall_stats['completed_modules'] / $overall_stats['total_modules']) * 100) : 0;
    $video_progress = $overall_stats['total_videos'] > 0 ? round(($overall_stats['watched_videos'] / $overall_stats['total_videos']) * 100) : 0;
    $assessment_progress = $overall_stats['total_assessments'] > 0 ? round(($overall_stats['completed_assessments'] / $overall_stats['total_assessments']) * 100) : 0;

    // Get course-wise progress
    $stmt = $pdo->prepare("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
               e.module_progress, e.video_progress, e.progress_percentage
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        JOIN course_enrollments e ON c.id = e.course_id
        WHERE e.student_id = ? AND e.status = 'active'
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $course_progress = $stmt->fetchAll();

    // Process each course to calculate progress using new database structure
    foreach ($course_progress as &$course) {
        $course_modules = json_decode($course['modules'] ?? '[]', true) ?: [];
        $module_progress = json_decode($course['module_progress'] ?? '{}', true) ?: [];
        $video_progress = json_decode($course['video_progress'] ?? '{}', true) ?: [];
        
        $course['total_modules'] = count($course_modules);
        $course['completed_modules'] = 0;
        $course['total_videos'] = 0;
        $course['watched_videos'] = 0;
        $course['total_assessments'] = 0;
        $course['completed_assessments'] = 0;
        
        foreach ($course_modules as $module) {
            // Count completed modules
            if (isset($module_progress[$module['id']]) && isset($module_progress[$module['id']]['is_completed']) && $module_progress[$module['id']]['is_completed']) {
                $course['completed_modules']++;
            }
            
            // Count videos
            if (isset($module['videos'])) {
                $course['total_videos'] += count($module['videos']);
                foreach ($module['videos'] as $video) {
                    if (isset($video_progress[$video['id']]) && isset($video_progress[$video['id']]['is_watched']) && $video_progress[$video['id']]['is_watched']) {
                        $course['watched_videos']++;
                    }
                }
            }
            
            // Count assessments
            if (isset($module['assessments'])) {
                $course['total_assessments'] += count($module['assessments']);
            }
        }
        
        // Count completed assessments
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT aa.assessment_id) as completed_assessments
            FROM assessment_attempts aa
            JOIN assessments a ON aa.assessment_id = a.id
            WHERE aa.student_id = ? AND a.course_id = ? AND aa.status = 'completed'
        ");
        $stmt->execute([$user_id, $course['id']]);
        $course['completed_assessments'] = $stmt->fetchColumn();
        
        // Calculate progress percentages for each course
        $course['module_progress'] = $course['total_modules'] > 0 ? round(($course['completed_modules'] / $course['total_modules']) * 100) : 0;
        $course['video_progress'] = $course['total_videos'] > 0 ? round(($course['watched_videos'] / $course['total_videos']) * 100) : 0;
        $course['assessment_progress'] = $course['total_assessments'] > 0 ? round(($course['completed_assessments'] / $course['total_assessments']) * 100) : 0;
        
        // Calculate overall course progress
        $total_activities = $course['total_modules'] + $course['total_videos'] + $course['total_assessments'];
        $completed_activities = $course['completed_modules'] + $course['watched_videos'] + $course['completed_assessments'];
        $course['overall_progress'] = $total_activities > 0 ? round(($completed_activities / $total_activities) * 100) : 0;
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'module_progress' => $module_progress,
            'video_progress' => $video_progress,
            'assessment_progress' => $assessment_progress,
            'overall_stats' => $overall_stats
        ],
        'course_progress' => $course_progress,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Error in ajax_get_progress_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
