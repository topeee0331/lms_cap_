<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get parameters
$student_id = (int)($_GET['student_id'] ?? 0);
$course_id = (int)($_GET['course_id'] ?? 0);

if (!$student_id || !$course_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Verify teacher has access to this student and course
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as access_count
        FROM courses c
        JOIN sections s ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        WHERE JSON_SEARCH(s.students, 'one', ?) IS NOT NULL AND c.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$student_id, $course_id, $_SESSION['user_id']]);
    $access = $stmt->fetch();
    
    if (!$access || $access['access_count'] == 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied to this student/course']);
        exit();
    }
    
    // Get student's assessment attempts with recalculated scores
    $stmt = $pdo->prepare("
        SELECT aa.*, a.assessment_title, a.difficulty, cm.module_title, cm.course_id,
               (SELECT COUNT(*) FROM student_answers sa WHERE sa.attempt_id = aa.id) as questions_answered,
               (
                   SELECT COALESCE(SUM(
                       CASE 
                           WHEN aq.question_type = 'multiple_choice' THEN 
                               CASE WHEN sa.selected_option_id = (
                                   SELECT qo.id FROM question_options qo 
                                   WHERE qo.question_id = aq.id AND qo.is_correct = 1 
                                   LIMIT 1
                               ) THEN aq.points ELSE 0 END
                           WHEN aq.question_type = 'true_false' THEN 
                               CASE WHEN sa.selected_option_id = (
                                   SELECT qo.id FROM question_options qo 
                                   WHERE qo.question_id = aq.id AND qo.is_correct = 1 
                                   LIMIT 1
                               ) THEN aq.points ELSE 0 END
                           WHEN aq.question_type = 'identification' THEN 
                               CASE WHEN UPPER(TRIM(sa.student_answer)) = UPPER(TRIM((
                                   SELECT qo.option_text FROM question_options qo 
                                   WHERE qo.question_id = aq.id AND qo.is_correct = 1 
                                   LIMIT 1
                               ))) THEN aq.points ELSE 0 END
                           ELSE COALESCE(sa.points_earned, 0)
                       END
                   ), 0)
                   FROM student_answers sa
                   JOIN assessment_questions aq ON sa.question_id = aq.id
                   WHERE sa.attempt_id = aa.id
               ) as calculated_points_earned,
               (
                   SELECT COALESCE(SUM(aq.points), 0)
                   FROM assessment_questions aq
                   WHERE aq.assessment_id = aa.assessment_id
               ) as total_possible_points
        FROM assessment_attempts aa
        JOIN assessments a ON aa.assessment_id = a.id
        WHERE aa.student_id = ? AND a.course_id = ? AND aa.status = 'completed'
        ORDER BY aa.completed_at DESC
    ");
    $stmt->execute([$student_id, $course_id]);
    $assessments = $stmt->fetchAll();
    
    // Get module progress - modules are now stored as JSON in courses.modules
    $stmt = $pdo->prepare("
        SELECT c.modules, e.module_progress, e.video_progress,
               COUNT(cv.id) as total_videos,
               COUNT(vv.id) as watched_videos
        FROM courses c
        LEFT JOIN course_enrollments e ON e.course_id = c.id AND e.student_id = ?
        LEFT JOIN course_videos cv ON cv.course_id = c.id
        LEFT JOIN video_views vv ON cv.id = vv.video_id AND vv.student_id = ?
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$student_id, $student_id, $course_id]);
    $course_data = $stmt->fetch();
    
    // Parse modules from JSON and create module array
    $modules = [];
    $module_progress = [];
    $video_progress = [];
    
    if ($course_data) {
        // Parse progress data from JSON
        if ($course_data['module_progress']) {
            $module_progress = json_decode($course_data['module_progress'], true) ?: [];
        }
        if ($course_data['video_progress']) {
            $video_progress = json_decode($course_data['video_progress'], true) ?: [];
        }
        
        if ($course_data['modules']) {
            $modules_json = json_decode($course_data['modules'], true);
            if (is_array($modules_json)) {
                foreach ($modules_json as $index => $module) {
                    $module_id = $module['id'];
                    $is_completed = isset($module_progress[$module_id]) && $module_progress[$module_id]['is_completed'] == 1;
                    $completed_at = isset($module_progress[$module_id]) ? $module_progress[$module_id]['completed_at'] : null;
                    $progress_percentage = isset($module_progress[$module_id]) ? $module_progress[$module_id]['progress_percentage'] : 0;
                    
                    // Count videos and watched videos for this module
                    $module_videos = $module['videos'] ?? [];
                    $total_videos = count($module_videos);
                    $watched_videos = 0;
                    
                    foreach ($module_videos as $video) {
                        if (isset($video_progress[$video['id']]) && $video_progress[$video['id']]['is_watched']) {
                            $watched_videos++;
                        }
                    }
                    
                    $modules[] = [
                        'id' => $module_id,
                        'module_title' => $module['module_title'] ?? $module['title'] ?? 'Module ' . ($index + 1),
                        'module_order' => $module['module_order'] ?? $index,
                        'is_completed' => $is_completed,
                        'completed_at' => $completed_at,
                        'progress_percentage' => $progress_percentage,
                        'total_videos' => $total_videos,
                        'watched_videos' => $watched_videos
                    ];
                }
            }
        }
    }
    
    // Calculate overall statistics
    $total_assessments = count($assessments);
    $calculated_scores = [];
    foreach ($assessments as $assessment) {
        $calculated_score = $assessment['total_possible_points'] > 0 ? 
            round(($assessment['calculated_points_earned'] / $assessment['total_possible_points']) * 100) : 0;
        $calculated_scores[] = $calculated_score;
    }
    $average_score = $total_assessments > 0 ? array_sum($calculated_scores) / $total_assessments : 0;
    $completed_modules = count(array_filter($modules, function($m) { return $m['is_completed']; }));
    $total_modules = count($modules);
    $total_videos_watched = array_sum(array_column($modules, 'watched_videos'));
    $total_videos = array_sum(array_column($modules, 'total_videos'));
    
    // Prepare response data
    $progress_data = [
        'total_assessments' => $total_assessments,
        'completed_modules' => $completed_modules,
        'total_modules' => $total_modules,
        'total_videos_watched' => $total_videos_watched,
        'total_videos' => $total_videos,
        'average_score' => round($average_score, 1),
        'course_progress' => $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0,
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    // Return success response
    echo json_encode([
        'success' => true,
        'progress' => $progress_data,
        'message' => 'Progress data retrieved successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error in ajax_get_student_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while retrieving progress data'
    ]);
}
?>
