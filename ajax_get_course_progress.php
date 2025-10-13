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
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if (!$course_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Course ID required']);
    exit();
}

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Check if student is enrolled in this course (allow both active and completed statuses)
    $stmt = $pdo->prepare("
        SELECT * FROM course_enrollments 
        WHERE student_id = ? AND course_id = ? AND status IN ('active', 'completed')
    ");
    $stmt->execute([$user_id, $course_id]);

    if ($stmt->rowCount() == 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Not enrolled in this course']);
        exit();
    }

    $enrollment = $stmt->fetch();

    // Get course details
    $stmt = $pdo->prepare("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ? AND c.is_archived = 0
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        http_response_code(404);
        echo json_encode(['error' => 'Course not found']);
        exit();
    }

    // Get modules from JSON data in courses table
    $modules = [];
    if (!empty($course['modules'])) {
        $modules_data = json_decode($course['modules'] ?? '[]', true) ?: [];
        
        // Get student's progress data
        $module_progress = [];
        $video_progress = [];
        
        if ($enrollment) {
            $module_progress = json_decode($enrollment['module_progress'] ?? '{}', true) ?: [];
            $video_progress = json_decode($enrollment['video_progress'] ?? '{}', true) ?: [];
        }
        
        // Process modules and add progress information
        foreach ($modules_data as $module) {
            $module_id = $module['id'];
            $video_count = isset($module['videos']) ? count($module['videos']) : 0;
            $assessment_count = isset($module['assessments']) ? count($module['assessments']) : 0;
            
            // Count files from both new multiple files structure and legacy single file structure
            $file_count = 0;
            if (isset($module['files']) && is_array($module['files'])) {
                $file_count = count($module['files']);
            } elseif (isset($module['file']) && !empty($module['file'])) {
                $file_count = 1; // Legacy single file
            }
            
            // Check if module is completed
            $is_completed = isset($module_progress[$module_id]) && $module_progress[$module_id]['is_completed'] == 1;
            
            $modules[] = [
                'id' => $module_id,
                'module_title' => $module['module_title'],
                'video_count' => $video_count,
                'assessment_count' => $assessment_count,
                'file_count' => $file_count,
                'is_completed' => $is_completed,
                'videos' => $module['videos'] ?? [],
                'assessments' => $module['assessments'] ?? []
            ];
        }
        
        // Sort modules by order
        usort($modules, function($a, $b) {
            return $a['module_order'] - $b['module_order'];
        });
    }

    // Calculate real-time progress statistics
    $total_videos = 0;
    $total_assessments = 0;
    $total_files = 0;
    $completed_videos = 0;
    $completed_assessments = 0;
    $total_points_earned = 0;
    $completed_modules = 0;

    // Calculate totals and completed counts
    foreach ($modules as $module) {
        $total_videos += $module['video_count'];
        $total_assessments += $module['assessment_count'];
        $total_files += $module['file_count'];
        
        if ($module['is_completed']) {
            $completed_modules++;
        }
        
        // Count completed videos for this module
        if (isset($module['videos']) && is_array($module['videos'])) {
            foreach ($module['videos'] as $video) {
                $video_id = is_array($video) ? $video['id'] : $video;
                if (isset($video_progress[$video_id]) && $video_progress[$video_id]['is_watched'] == 1) {
                    $completed_videos++;
                }
            }
        }
        
        // Count completed assessments for this module
        if (isset($module['assessments']) && is_array($module['assessments'])) {
            foreach ($module['assessments'] as $assessment) {
                $assessment_id = is_array($assessment) ? $assessment['id'] : $assessment;
                
                // Get student's best score for this assessment
                $stmt = $pdo->prepare("
                    SELECT MAX(score) as best_score, MAX(has_passed) as has_passed
                    FROM assessment_attempts 
                    WHERE student_id = ? AND assessment_id = ? AND status = 'completed'
                ");
                $stmt->execute([$user_id, $assessment_id]);
                $attempt = $stmt->fetch();
                
                if ($attempt && $attempt['best_score'] > 0) {
                    $completed_assessments++;
                    $total_points_earned += $attempt['best_score'];
                }
            }
        }
    }

    $total_modules = count($modules);
    $course_progress = $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0;

    // Return progress data
    echo json_encode([
        'success' => true,
        'data' => [
            'total_videos' => $total_videos,
            'total_assessments' => $total_assessments,
            'total_files' => $total_files,
            'total_modules' => $total_modules,
            'completed_videos' => $completed_videos,
            'completed_assessments' => $completed_assessments,
            'completed_modules' => $completed_modules,
            'total_points_earned' => $total_points_earned,
            'course_progress' => $course_progress,
            'video_progress_percentage' => $total_videos > 0 ? round(($completed_videos / $total_videos) * 100) : 0,
            'assessment_progress_percentage' => $total_assessments > 0 ? round(($completed_assessments / $total_assessments) * 100) : 0
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
