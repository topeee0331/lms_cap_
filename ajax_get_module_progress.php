<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$module_id = isset($_GET['module_id']) ? $_GET['module_id'] : '';

if (!$module_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Module ID required']);
    exit();
}

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Find the course and module that contains this module_id
    $stmt = $pdo->prepare("
        SELECT c.*, c.teacher_id, u.first_name, u.last_name
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL
    ");
    $stmt->execute([$module_id]);
    $course = $stmt->fetch();

    if (!$course) {
        http_response_code(404);
        echo json_encode(['error' => 'Module not found']);
        exit();
    }

    // Check if student is enrolled in this course
    $stmt = $pdo->prepare("
        SELECT * FROM course_enrollments 
        WHERE student_id = ? AND course_id = ? AND status = 'active'
    ");
    $stmt->execute([$user_id, $course['id']]);

    if ($stmt->rowCount() == 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Not enrolled in this course']);
        exit();
    }

    $enrollment = $stmt->fetch();

    // Parse modules and find the specific module
    $modules_data = json_decode($course['modules'] ?? '[]', true) ?: [];
    $module = null;

    foreach ($modules_data as $mod) {
        if ($mod['id'] === $module_id) {
            $module = $mod;
            break;
        }
    }

    if (!$module) {
        http_response_code(404);
        echo json_encode(['error' => 'Module not found']);
        exit();
    }

    // Get student's progress data
    $module_progress = [];
    $video_progress = [];
    
    if ($enrollment) {
        $module_progress = json_decode($enrollment['module_progress'] ?? '{}', true) ?: [];
        $video_progress = json_decode($enrollment['video_progress'] ?? '{}', true) ?: [];
    }

    // Get videos from module data
    $videos = $module['videos'] ?? [];

    // Add video watch status and duration
    foreach ($videos as $index => $video) {
        $videos[$index]['is_watched'] = isset($video_progress[$video['id']]) && $video_progress[$video['id']]['is_watched'] == 1;
        $videos[$index]['watch_duration'] = isset($video_progress[$video['id']]) ? ($video_progress[$video['id']]['watch_duration'] ?? 0) : 0;
        $videos[$index]['completion_percentage'] = isset($video_progress[$video['id']]) ? ($video_progress[$video['id']]['completion_percentage'] ?? 0) : 0;
    }

    // Get assessments that belong to the current module
    $assessments = [];
    if (isset($module['assessments']) && is_array($module['assessments'])) {
        $module_assessment_ids = array_column($module['assessments'], 'id');
        
        if (!empty($module_assessment_ids)) {
            $placeholders = str_repeat('?,', count($module_assessment_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT id, assessment_title, passing_rate
                FROM assessments 
                WHERE id IN ($placeholders) AND status = 'active'
            ");
            $stmt->execute($module_assessment_ids);
            $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Add assessment attempt data
    foreach ($assessments as $index => $assessment) {
        $stmt = $pdo->prepare("
            SELECT MAX(score) as best_score, MAX(has_passed) as has_passed
            FROM assessment_attempts 
            WHERE assessment_id = ? AND student_id = ? AND status = 'completed'
        ");
        $stmt->execute([$assessment['id'], $user_id]);
        $attempt = $stmt->fetch();
        
        $assessments[$index]['best_score'] = $attempt['best_score'] ?? 0;
        $assessments[$index]['has_passed'] = $attempt['has_passed'] ?? 0;
    }

    // Calculate video progress
    $total_videos = count($videos);
    $watched_videos = 0;
    $total_watch_time = 0;

    foreach ($videos as $video) {
        if ($video['is_watched']) {
            $watched_videos++;
            $total_watch_time += $video['watch_duration'] ?? 0;
        }
    }

    $video_progress_percentage = $total_videos > 0 ? round(($watched_videos / $total_videos) * 100) : 0;

    // Calculate assessment progress with detailed attempt data
    $completed_assessments = 0;
    $total_assessments_count = count($assessments);
    $total_assessment_points = 0;
    $total_attempts = 0;
    $successful_attempts = 0;
    $assessment_details = [];

    foreach ($assessments as $assessment) {
        $assessment_id = $assessment['id'];
        $passing_rate = $assessment['passing_rate'] ?? 70;
        
        // Get all attempts for this assessment
        $stmt = $pdo->prepare("
            SELECT score, has_passed, started_at, completed_at, time_taken
            FROM assessment_attempts 
            WHERE assessment_id = ? AND student_id = ? AND status = 'completed'
            ORDER BY score DESC
        ");
        $stmt->execute([$assessment_id, $user_id]);
        $attempts = $stmt->fetchAll();
        
        $assessment_attempts = count($attempts);
        $assessment_successful = 0;
        $best_score = 0;
        $latest_attempt = null;
        
        if (!empty($attempts)) {
            $total_attempts += $assessment_attempts;
            $best_score = $attempts[0]['score']; // Highest score
            $latest_attempt = $attempts[0]; // Most recent attempt
            
            $assessment_successful = count(array_filter($attempts, function($attempt) use ($passing_rate) {
                return $attempt['score'] >= $passing_rate && $attempt['has_passed'];
            }));
            
            $successful_attempts += $assessment_successful;
            
            // Add points only from passed attempts
            if ($best_score >= $passing_rate && $latest_attempt['has_passed']) {
                $completed_assessments++;
                $total_assessment_points += $best_score;
            }
        }
        
        // Store detailed assessment data
        $assessment_details[] = [
            'id' => $assessment_id,
            'title' => $assessment['assessment_title'],
            'passing_rate' => $passing_rate,
            'best_score' => $best_score,
            'total_attempts' => $assessment_attempts,
            'successful_attempts' => $assessment_successful,
            'is_passed' => $best_score >= $passing_rate && $latest_attempt && $latest_attempt['has_passed'],
            'latest_attempt' => $latest_attempt
        ];
    }

    $assessment_progress_percentage = $total_assessments_count > 0 ? round(($completed_assessments / $total_assessments_count) * 100) : 0;
    $success_rate = $total_attempts > 0 ? round(($successful_attempts / $total_attempts) * 100) : 0;

    // Check module completion requirements
    function checkModuleCompletionRequirements($module, $videos, $assessments, $video_progress, $user_id, $pdo) {
        $requirements = [
            'can_complete' => true,
            'missing_requirements' => [],
            'video_requirements' => [],
            'assessment_requirements' => []
        ];
        
        // Check video requirements
        if (!empty($videos)) {
            foreach ($videos as $video) {
                $video_id = $video['id'];
                $required_completion = 80; // Minimum completion percentage
                
                $is_watched = isset($video_progress[$video_id]) && $video_progress[$video_id]['is_watched'] == 1;
                $completion_percentage = isset($video_progress[$video_id]) ? ($video_progress[$video_id]['completion_percentage'] ?? 0) : 0;
                $watch_duration = isset($video_progress[$video_id]) ? ($video_progress[$video_id]['watch_duration'] ?? 0) : 0;
                
                $video_met = $is_watched || $completion_percentage >= $required_completion;
                
                $requirements['video_requirements'][] = [
                    'video_id' => $video_id,
                    'video_title' => $video['video_title'] ?? 'Untitled Video',
                    'required_completion' => $required_completion,
                    'current_completion' => $completion_percentage,
                    'is_met' => $video_met,
                    'is_watched' => $is_watched
                ];
                
                if (!$video_met) {
                    $requirements['can_complete'] = false;
                }
            }
        }
        
        // Check assessment requirements
        if (!empty($assessments)) {
            foreach ($assessments as $assessment) {
                $assessment_id = $assessment['id'];
                $required_score = $assessment['passing_rate'] ?? 70;
                
                // Get student's best score for this assessment
                $stmt = $pdo->prepare("
                    SELECT MAX(score) as best_score, MAX(has_passed) as has_passed
                    FROM assessment_attempts 
                    WHERE student_id = ? AND assessment_id = ? AND status = 'completed'
                ");
                $stmt->execute([$user_id, $assessment_id]);
                $attempt = $stmt->fetch();
                
                $student_score = $attempt['best_score'] ?? 0;
                $has_passed = $attempt['has_passed'] ?? 0;
                $assessment_met = $student_score >= $required_score && $has_passed;
                
                // Calculate progress percentage for the progress bar
                $progress_percentage = $required_score > 0 ? min(($student_score / $required_score) * 100, 100) : 0;
                
                $requirements['assessment_requirements'][] = [
                    'assessment_id' => $assessment_id,
                    'assessment_title' => $assessment['assessment_title'] ?? 'Untitled Assessment',
                    'required_score' => $required_score,
                    'current_score' => $student_score,
                    'progress_percentage' => $progress_percentage,
                    'has_passed' => $has_passed,
                    'is_met' => $assessment_met
                ];
                
                if (!$assessment_met) {
                    $requirements['can_complete'] = false;
                }
            }
        }
        
        return $requirements;
    }

    $requirements = checkModuleCompletionRequirements($module, $videos, $assessments, $video_progress, $user_id, $pdo);

    // Return progress data
    echo json_encode([
        'success' => true,
        'data' => [
            'total_videos' => $total_videos,
            'watched_videos' => $watched_videos,
            'video_progress_percentage' => $video_progress_percentage,
            'total_watch_time' => $total_watch_time,
            'total_assessments' => $total_assessments_count,
            'completed_assessments' => $completed_assessments,
            'assessment_progress_percentage' => $assessment_progress_percentage,
            'total_assessment_points' => $total_assessment_points,
            'total_attempts' => $total_attempts,
            'successful_attempts' => $successful_attempts,
            'success_rate' => $success_rate,
            'assessment_details' => $assessment_details,
            'videos' => $videos,
            'assessments' => $assessments,
            'requirements' => $requirements
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
