<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/pusher.php';
require_once '../includes/assessment_pass_tracker.php';

$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$module_id = isset($_GET['id']) ? $_GET['id'] : '';

if (!$module_id) {
    header('Location: courses.php');
    exit();
}

// Find the course and module that contains this module_id with comprehensive details
$stmt = $pdo->prepare("
    SELECT c.*, c.teacher_id, u.first_name, u.last_name, u.email as teacher_email, 
           ay.academic_year, ay.semester_name, ay.is_active as academic_period_active,
           ay.start_date, ay.end_date, c.credits, c.year_level
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    JOIN academic_periods ay ON c.academic_period_id = ay.id
    WHERE JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL
");
$stmt->execute([$module_id]);
$course = $stmt->fetch();

if (!$course) {
    $_SESSION['error'] = "Module not found.";
    header('Location: courses.php');
    exit();
}

// Check if student is enrolled in this course
$stmt = $pdo->prepare("
    SELECT * FROM course_enrollments 
    WHERE student_id = ? AND course_id = ? AND status = 'active'
");
$stmt->execute([$user_id, $course['id']]);

if ($stmt->rowCount() == 0) {
    $_SESSION['error'] = "You are not enrolled in this course.";
    header('Location: courses.php');
    exit();
}

// Parse modules and find the specific module
$modules_data = json_decode($course['modules'] ?? '[]', true) ?: [];
$module = null;
$module_index = -1;

foreach ($modules_data as $index => $mod) {
    if ($mod['id'] === $module_id) {
        $module = $mod;
        $module_index = $index;
        break;
    }
}

if (!$module) {
    $_SESSION['error'] = "Module not found.";
    header('Location: courses.php');
    exit();
}

// Get student's progress data
$stmt = $pdo->prepare("
    SELECT module_progress, video_progress 
    FROM course_enrollments 
    WHERE student_id = ? AND course_id = ?
");
$stmt->execute([$user_id, $course['id']]);
$enrollment = $stmt->fetch();

$module_progress = [];
$video_progress = [];

if ($enrollment) {
    $module_progress = json_decode($enrollment['module_progress'] ?? '{}', true) ?: [];
    $video_progress = json_decode($enrollment['video_progress'] ?? '{}', true) ?: [];
}

// Check if module is completed
$is_completed = isset($module_progress[$module_id]) && $module_progress[$module_id]['is_completed'] == 1;
$completed_at = isset($module_progress[$module_id]) ? $module_progress[$module_id]['completed_at'] : null;

// Get videos from module data
$videos = $module['videos'] ?? [];

// Video data loaded successfully

// Get assessments that belong to the current module from course modules JSON
$assessments = [];
if (isset($module['assessments']) && is_array($module['assessments'])) {
    $module_assessment_ids = array_column($module['assessments'], 'id');
    
    if (!empty($module_assessment_ids)) {
        $placeholders = str_repeat('?,', count($module_assessment_ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT id, assessment_title, description, time_limit, difficulty, 
                   num_questions, passing_rate, attempt_limit, assessment_order,
                   is_locked, lock_type, prerequisite_assessment_id, 
                   prerequisite_score, prerequisite_video_count, unlock_date, lock_message,
                   created_at
            FROM assessments 
            WHERE id IN ($placeholders) AND status = 'active'
            ORDER BY assessment_order ASC
        ");
        $stmt->execute($module_assessment_ids);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}



// Assessments are already unique since they come from database

// Remove duplicate videos based on ID (similar to assessments)
$unique_videos = [];
$seen_video_ids = [];
foreach ($videos as $video) {
    if (!in_array($video['id'], $seen_video_ids)) {
        $unique_videos[] = $video;
        $seen_video_ids[] = $video['id'];
    }
}
$videos = $unique_videos;

// Add video watch status and duration
foreach ($videos as $index => $video) {
    $videos[$index]['is_watched'] = isset($video_progress[$video['id']]) && $video_progress[$video['id']]['is_watched'] == 1;
    $videos[$index]['watch_duration'] = isset($video_progress[$video['id']]) ? ($video_progress[$video['id']]['watch_duration'] ?? 0) : 0;
    $videos[$index]['completion_percentage'] = isset($video_progress[$video['id']]) ? ($video_progress[$video['id']]['completion_percentage'] ?? 0) : 0;
}

// Sort videos by creation time (oldest first - original order)
usort($videos, function($a, $b) {
    $time_a = strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
    $time_b = strtotime($b['created_at'] ?? '1970-01-01 00:00:00');
    return $time_a - $time_b; // Oldest first (original order)
});

// Video processing completed successfully


// Add comprehensive assessment attempt data
foreach ($assessments as $index => $assessment) {
$stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count, 
               MAX(score) as best_score,
               AVG(score) as average_score,
               MIN(score) as worst_score,
               SUM(CASE WHEN has_passed = 1 THEN 1 ELSE 0 END) as passed_attempts,
               MAX(has_ever_passed) as has_ever_passed,
               MAX(completed_at) as last_attempt_date,
               SUM(time_taken) as total_time_spent
        FROM assessment_attempts 
        WHERE assessment_id = ? AND student_id = ? AND status = 'completed'
    ");
    $stmt->execute([$assessment['id'], $user_id]);
    $attempt_data = $stmt->fetch();
    
    $assessments[$index]['attempt_count'] = $attempt_data['attempt_count'] ?? 0;
    $assessments[$index]['best_score'] = $attempt_data['best_score'] ?? null;
    $assessments[$index]['average_score'] = $attempt_data['average_score'] ?? null;
    $assessments[$index]['worst_score'] = $attempt_data['worst_score'] ?? null;
    $assessments[$index]['passed_attempts'] = $attempt_data['passed_attempts'] ?? 0;
    $assessments[$index]['has_ever_passed'] = $attempt_data['has_ever_passed'] ?? 0;
    $assessments[$index]['last_attempt_date'] = $attempt_data['last_attempt_date'] ?? null;
    $assessments[$index]['total_time_spent'] = $attempt_data['total_time_spent'] ?? 0;
    $assessments[$index]['pass_rate'] = $attempt_data['attempt_count'] > 0 ? 
        round(($attempt_data['passed_attempts'] / $attempt_data['attempt_count']) * 100, 1) : 0;
}

// Check module completion requirements (after videos and assessments are defined)
$requirements = checkModuleCompletionRequirements($module, $videos, $assessments, $video_progress, $user_id, $pdo);

// Check academic period status
$is_acad_year_active = (bool)$course['academic_period_active'];
$is_semester_active = $is_acad_year_active;
$is_view_only = !$is_acad_year_active || !$is_semester_active;

// Handle video view tracking (legacy - now handled by video_player.php)
if (isset($_POST['mark_video_watched'])) {
    // This is now handled by the video player with time tracking
    // Redirect to prevent accidental double-submission
    header('Location: module.php?id=' . $module_id);
    exit();
}

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
            $min_watch_time = ($video['min_watch_time'] ?? 5) * 60; // Convert to seconds
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
                'required_watch_time' => $min_watch_time,
                'current_watch_time' => $watch_duration,
                'is_met' => $video_met,
                'is_watched' => $is_watched
            ];
            
            if (!$video_met) {
                $requirements['can_complete'] = false;
                $requirements['missing_requirements'][] = [
                    'type' => 'video',
                    'title' => $video['video_title'] ?? 'Untitled Video',
                    'message' => "Video '{$video['video_title']}' must be watched completely (minimum {$required_completion}% completion)"
                ];
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
                $requirements['missing_requirements'][] = [
                    'type' => 'assessment',
                    'title' => $assessment['assessment_title'] ?? 'Untitled Assessment',
                    'message' => "Assessment '{$assessment['assessment_title']}' must be passed with at least {$required_score}% (current: {$student_score}%)"
                ];
            }
        }
    }
    
    return $requirements;
}

// Handle module completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_module'])) {
    // Check if student meets all requirements
    $requirements = checkModuleCompletionRequirements($module, $videos, $assessments, $video_progress, $user_id, $pdo);
    
    if ($requirements['can_complete']) {
        // Update module progress
        $module_progress[$module_id] = [
            'is_completed' => 1,
            'completed_at' => date('Y-m-d H:i:s')
        ];
        
        // Calculate progress percentage
        $total_modules = count($modules_data);
        $completed_count = count($module_progress);
        $progress_percentage = $total_modules > 0 ? round(($completed_count / $total_modules) * 100, 2) : 0;
        
        // Update enrollment
        $stmt = $pdo->prepare("
            UPDATE course_enrollments 
            SET module_progress = ?, progress_percentage = ?
            WHERE student_id = ? AND course_id = ?
        ");
        $stmt->execute([
            json_encode($module_progress),
            $progress_percentage,
            $user_id,
            $course['id']
        ]);
        
        // Update module_progress table
        $stmt = $pdo->prepare("
            INSERT INTO module_progress (student_id, module_id, course_id, is_completed, completed_at, progress_percentage) 
            VALUES (?, ?, ?, 1, NOW(), 100.00)
            ON DUPLICATE KEY UPDATE 
            is_completed = 1, 
            completed_at = NOW(), 
            progress_percentage = 100.00,
            updated_at = NOW()
        ");
        $stmt->execute([$user_id, $module_id, $course['id']]);
        
        // Award module-specific badges
        require_once '../includes/module_completion_handler.php';
        $moduleCompletionHandler = new ModuleCompletionHandler($pdo);
        $moduleCompletionHandler->awardModuleBadges($user_id, $module_id);
        
        // Check if course is now completed and award badges
        require_once '../includes/course_completion_handler.php';
        $completionHandler = new CourseCompletionHandler($pdo);
        $course_completed = $completionHandler->updateCourseCompletion($user_id, $course['id']);
        
        // Send Pusher notifications
        require_once '../config/pusher.php';
        require_once '../includes/pusher_notifications.php';
        
        // Send notification to student
        PusherNotifications::sendModuleCompleted(
            $user_id,
            $module['module_title'],
            $course['course_name']
        );
        
        // Send notification to teacher
        PusherNotifications::sendModuleProgressToTeacher(
            $course['teacher_id'],
            $_SESSION['first_name'] . ' ' . $_SESSION['last_name'],
            $module['module_title'],
            $course['course_name']
        );
        
        if ($course_completed) {
            $_SESSION['success'] = "Module completed! Course completed! You've earned course badges!";
        } else {
            $_SESSION['success'] = "Module marked as completed!";
        }
    } else {
        // Build detailed error message
        $error_messages = [];
        foreach ($requirements['missing_requirements'] as $req) {
            $error_messages[] = $req['message'];
        }
        $_SESSION['error'] = "Cannot complete module. Requirements not met:\n• " . implode("\n• ", $error_messages);
    }
    
    header('Location: module.php?id=' . $module_id);
    exit();
}

// Define course themes with IT icons
$course_themes = [
    ['bg' => 'bg-primary', 'icon' => 'fas fa-code'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-database'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-network-wired'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-server'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-shield-alt'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-cloud'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-microchip'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-laptop-code'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-mobile-alt'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-wifi'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-keyboard'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-bug'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-terminal'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-cogs'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-rocket'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-robot'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-brain'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-chart-line'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-fire'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-lightbulb']
];

// Calculate video progress with more accurate data
$total_videos = count($videos);
$watched_videos = 0;
$total_watch_time = 0;
$total_required_time = 0;
$total_video_completion = 0; // Sum of all completion percentages

foreach ($videos as $video) {
    $completion_percentage = $video['completion_percentage'] ?? 0;
    $total_video_completion += $completion_percentage;
    
    // Check if video is watched based on completion percentage >= 80% or is_watched flag
    $is_watched = $video['is_watched'] || $completion_percentage >= 80;
    
    if ($is_watched) {
        $watched_videos++;
    }
    
    $total_watch_time += $video['watch_duration'] ?? 0;
    $total_required_time += ($video['min_watch_time'] ?? 5) * 60; // Convert minutes to seconds
}

// Calculate video progress as average completion percentage
$video_progress_percentage = $total_videos > 0 ? round($total_video_completion / $total_videos) : 0;

// Calculate assessment progress
$total_assessments = count($assessments);
$completed_assessments = 0;
$total_points_earned = 0;
$total_attempts = 0;
$successful_attempts = 0;
$total_assessment_progress = 0; // Sum of all assessment progress percentages

foreach ($assessments as $assessment) {
    $assessment_id = $assessment['id'];
    $passing_rate = $assessment['passing_rate'] ?? 70;
    
    // Get best attempt for this assessment
    $best_attempt = null;
    $best_score = 0;
    $assessment_progress = 0;
    
    if (isset($assessment['attempts']) && is_array($assessment['attempts'])) {
        foreach ($assessment['attempts'] as $attempt) {
            $total_attempts++;
            if ($attempt['has_passed']) {
                $successful_attempts++;
            }
            if ($attempt['score'] > $best_score) {
                $best_score = $attempt['score'];
                $best_attempt = $attempt;
            }
        }
        
        // Calculate progress for this assessment based on best score vs passing rate
        if ($best_score > 0) {
            $assessment_progress = min(100, round(($best_score / $passing_rate) * 100));
        }
    }
    
    $total_assessment_progress += $assessment_progress;
    
    // Count as completed if passed
    if ($best_attempt && $best_attempt['score'] >= $passing_rate && $best_attempt['has_passed']) {
        $completed_assessments++;
        $total_points_earned += $best_attempt['score'];
    } else {
        // Still add points for attempts (partial credit)
        $total_points_earned += $best_score;
    }
}

// Calculate assessment progress as average progress percentage
$assessment_progress_percentage = $total_assessments > 0 ? round($total_assessment_progress / $total_assessments) : 0;

// Format total watch time
$total_watch_time_formatted = gmdate("H:i:s", $total_watch_time);

// Calculate average score and success rate
$average_score = $total_attempts > 0 ? round(($successful_attempts / $total_attempts) * 100) : 0;
$success_rate = $total_attempts > 0 ? round(($successful_attempts / $total_attempts) * 100) : 0;

// Determine unlocked status for each assessment by order and passing scores
$unlocked = [];
$best_scores = [];

// Assessments already have all the data from database query

// Sort assessments by assessment_order (manual order set by teacher)
usort($assessments, function($a, $b) {
    $order_a = $a['assessment_order'] ?? 999;
    $order_b = $b['assessment_order'] ?? 999;
    
    // If orders are the same, sort by creation date as fallback
    if ($order_a == $order_b) {
        $date_a = strtotime($a['created_at'] ?? '1970-01-01');
        $date_b = strtotime($b['created_at'] ?? '1970-01-01');
        return $date_a - $date_b;
    }
    
    return $order_a - $order_b;
});

foreach ($assessments as $index => $a) {
    $assessment_id = $a['id'];
    $best_score = $a['best_score'] ?? 0;
    $passing_rate = $a['passing_rate'] ?? 70;
    $prerequisite_id = $a['prerequisite_assessment_id'] ?? null;
    
    $best_scores[$assessment_id] = $best_score;
    
        // Determine if this assessment is unlocked based on assessment order
    $assessment_order = $a['assessment_order'] ?? 1;
    $is_first_assessment = ($assessment_order == 1);
    
    if ($is_first_assessment) {
        // First assessment (order 1) is always unlocked
        $unlocked[$assessment_id] = true;
    } else {
        // For assessments with order > 1, check if the previous order assessment is completed
        $previous_order = $assessment_order - 1;
        $previous_assessment_completed = false;
        $previous_assessment_title = '';
        
        // Get all assessments from the course (not just current module)
        $stmt = $pdo->prepare("
            SELECT a.id, a.assessment_title, a.passing_rate, a.assessment_order
            FROM assessments a
            WHERE a.course_id = ? AND a.status = 'active'
            ORDER BY a.assessment_order ASC
        ");
        $stmt->execute([$course['id']]);
        $all_course_assessments = $stmt->fetchAll();
        
        // Find the assessment with the previous order from all course assessments
        foreach ($all_course_assessments as $prev_assessment) {
            $prev_order = $prev_assessment['assessment_order'] ?? 1;
            if ($prev_order == $previous_order) {
                // Get student's best score for this assessment
                $stmt = $pdo->prepare("
                    SELECT MAX(score) as best_score
                    FROM assessment_attempts 
                    WHERE student_id = ? AND assessment_id = ?
                ");
                $stmt->execute([$user_id, $prev_assessment['id']]);
                $score_result = $stmt->fetch();
                $prev_best_score = $score_result['best_score'] ?? 0;
                $prev_passing_rate = $prev_assessment['passing_rate'] ?? 70;
                $previous_assessment_title = $prev_assessment['assessment_title'] ?? 'Previous Assessment';
                $previous_assessment_completed = ($prev_best_score >= $prev_passing_rate);
                break;
            }
        }
        
        $unlocked[$assessment_id] = $previous_assessment_completed;
        
        // Store prerequisite information for display
        if (!$previous_assessment_completed) {
            $a['prerequisite_title'] = $previous_assessment_title;
            $a['prerequisite_order'] = $previous_order;
        }
    }
}

// Get comprehensive student progress statistics for this course
$stmt = $pdo->prepare("
    SELECT 
        e.progress_percentage,
        e.enrolled_at,
        e.last_accessed,
        e.final_grade,
        e.is_completed as course_completed,
        (SELECT COUNT(*) FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE a.course_id = ? AND aa.student_id = ? AND aa.status = 'completed') as total_assessment_attempts,
        (SELECT COUNT(*) FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE a.course_id = ? AND aa.student_id = ? AND aa.status = 'completed' AND aa.has_passed = 1) as passed_assessments,
        (SELECT AVG(aa.score) FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE a.course_id = ? AND aa.student_id = ? AND aa.status = 'completed') as average_course_score
    FROM course_enrollments e
    WHERE e.student_id = ? AND e.course_id = ?
");
$stmt->execute([$course['id'], $user_id, $course['id'], $user_id, $course['id'], $user_id, $user_id, $course['id']]);
$student_progress = $stmt->fetch();

// Get module files (if any exist in the new structure)
$module_files = []; // This would need to be implemented based on how files are stored in the new structure

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($module['module_title'] ?? ''); ?> - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        /* Root Variables */
        :root {
            --primary-color: #2E5E4E;
            --primary-dark: #1e5631;
            --success-color: #7DCB80;
            --success-light: #d1fae5;
            --danger-color: #ef4444;
            --danger-light: #fee2e2;
            --warning-color: #f59e0b;
            --warning-light: #fef3c7;
            --info-color: #7DCB80;
            --info-light: #dbeafe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Global Styles */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            color: var(--gray-800);
        }

        .container-fluid {
            background: transparent;
        }

        /* Header Styling */
        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .breadcrumb-item a {
            color: var(--gray-600);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb-item a:hover {
            color: var(--primary-color);
        }

        .breadcrumb-item.active {
            color: var(--gray-800);
            font-weight: 500;
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--gray-800);
            font-weight: 600;
        }

        .text-muted {
            color: var(--gray-600) !important;
        }

        /* Card Styling */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            transition: var(--transition);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 1.25rem 1.5rem;
        }
        
        /* Module Header Styling */
        .module-header-fullscreen {
            color: var(--gray-800);
            padding: 4rem 0;
            margin: 0 -15px 2rem -15px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 249, 250, 0.9) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: var(--shadow-lg);
        }
        
        .module-header-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.05;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .module-header-bg i {
            font-size: 20rem;
            color: var(--primary-color);
            opacity: 0.2;
        }
        
        .module-header-content {
            position: relative;
            z-index: 2;
        }
        
        .header-main-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .module-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .module-title-new {
            font-family: 'Inter', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .course-title-new {
            font-family: 'Inter', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 1.5rem;
        }
        
        .course-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .meta-item i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .instructor-info, .academic-info {
            display: flex;
            align-items: center;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .instructor-info i, .academic-info i {
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .header-visual {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
        
        .course-icon-wrapper {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .course-icon {
            font-size: 3rem;
            color: white;
        }
        
        .module-description-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #f0f0f0;
        }
        
        .description-header {
            display: flex;
            align-items: center;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        
        .description-content {
            color: var(--gray-700);
            line-height: 1.6;
            font-size: 1rem;
        }
        
        
        /* Tabbed Interface Styling */
        .nav-tabs .nav-link {
            border: none;
            border-radius: 0;
            color: var(--gray-600);
            font-weight: 500;
            padding: 1rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            border: none;
            color: var(--primary-color);
            background-color: rgba(125, 203, 128, 0.1);
        }
        
        .nav-tabs .nav-link.active {
            background: rgba(255, 255, 255, 0.95);
            color: var(--primary-color);
            border: none;
            font-weight: 600;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            position: relative;
            z-index: 1;
        }
        
        .nav-tabs .nav-link.active:hover {
            background: rgba(125, 203, 128, 0.1);
            color: var(--primary-color);
        }
        
        .tab-content {
            padding: 1.5rem 0;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 0 0 var(--border-radius-lg) var(--border-radius-lg);
            margin-top: -1px;
        }
        
        .assessment-performance-card {
            transition: all 0.3s ease;
            border-radius: 12px;
        }
        
        .assessment-performance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .performance-stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Video and Assessment Cards */
        .video-card, .assessment-card {
            background: #ffffff;
            border: 1px solid rgba(46, 94, 78, 0.1);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
        }

        .video-card::before, .assessment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark), var(--primary-color));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .video-card:hover, .assessment-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(46, 94, 78, 0.15);
            border-color: rgba(46, 94, 78, 0.3);
        }

        .video-card:hover::before, .assessment-card:hover::before {
            opacity: 1;
        }

        .video-card.watched, .assessment-card.completed {
            border-color: rgba(40, 167, 69, 0.3);
            background: #ffffff;
        }

        .video-card.watched::before, .assessment-card.completed::before {
            background: linear-gradient(90deg, #28a745, #20c997, #28a745);
            opacity: 1;
        }

        /* Video Card Preview Styles */
        .video-card-preview {
            position: relative;
            overflow: hidden;
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            height: 160px;
        }

        .video-card-preview iframe,
        .video-card-preview video {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 15px 15px 0 0;
            transition: transform 0.3s ease;
        }

        .video-card-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 15px 15px 0 0;
            transition: transform 0.3s ease;
        }

        .video-card:hover .video-card-preview iframe,
        .video-card:hover .video-card-preview video,
        .video-card:hover .video-card-preview img {
            transform: scale(1.05);
        }

        .video-card-preview .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, rgba(46, 94, 78, 0.9), rgba(30, 86, 49, 0.9));
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(46, 94, 78, 0.3);
            backdrop-filter: blur(10px);
        }

        .video-card-preview .play-button:hover {
            background: linear-gradient(135deg, rgba(46, 94, 78, 1), rgba(30, 86, 49, 1));
            transform: translate(-50%, -50%) scale(1.15);
            box-shadow: 0 12px 35px rgba(46, 94, 78, 0.4);
        }

        .video-card-preview .play-button::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .video-card-preview .play-button:hover::before {
            opacity: 1;
        }

        /* Real-time progress update animations */
        .video-card.progress-updated {
            animation: progressUpdate 2s ease-in-out;
            border: 2px solid #28a745 !important;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.3) !important;
        }

        @keyframes progressUpdate {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        /* Highlight updated progress */
        .text-warning {
            transition: all 0.3s ease;
        }

        .text-warning.updated {
            background-color: #d4edda;
            border-radius: 4px;
            padding: 2px 4px;
            animation: progressHighlight 1s ease-in-out;
        }

        @keyframes progressHighlight {
            0% { background-color: #d4edda; }
            50% { background-color: #c3e6cb; }
            100% { background-color: transparent; }
        }

        /* Video Card Content Styling */
        .video-card .card-body {
            padding: 1rem;
            background: #ffffff;
        }

        .video-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .video-card .card-text {
            color: #718096;
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 0.75rem;
        }

        .video-card .badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Video Watched Badge Positioning */
        .video-watched-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }

        .video-watched-badge .badge {
            font-size: 0.7rem;
            padding: 0.4rem 0.6rem;
            border-radius: 15px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
            backdrop-filter: blur(10px);
        }

        /* Assessment Passed Badge Positioning */
        .assessment-passed-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }

        .assessment-passed-badge .badge {
            font-size: 0.7rem;
            padding: 0.4rem 0.6rem;
            border-radius: 15px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
            backdrop-filter: blur(10px);
        }

        /* Assessment Card Content Styling */
        .assessment-card .card-body {
            padding: 1.5rem;
            background: #ffffff;
        }

        .assessment-card .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .assessment-card .card-text {
            color: #718096;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .assessment-card .badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Section Headers */
        #video-section h3, #assessment-section h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        #video-section h3::after, #assessment-section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            border-radius: 2px;
        }

        /* Enhanced Card States */
        .video-card.watched {
            border-left: 4px solid var(--success-color);
            background: #ffffff;
            position: relative;
        }

        .video-card.watched::after {
            content: '✓';
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .assessment-card.completed {
            border-left: 4px solid var(--success-color);
            background: linear-gradient(135deg, #f0fff4 0%, #ffffff 100%);
            position: relative;
        }

        .assessment-card.completed::after {
            content: '✓';
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .assessment-card.locked {
            opacity: 0.7;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .assessment-card.locked::before {
            background: linear-gradient(90deg, #6c757d, #495057, #6c757d);
        }

        /* Loading Animation for Cards */
        .video-card, .assessment-card {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Staggered Animation for Multiple Cards */
        .video-card:nth-child(1) { animation-delay: 0.1s; }
        .video-card:nth-child(2) { animation-delay: 0.2s; }
        .video-card:nth-child(3) { animation-delay: 0.3s; }
        .video-card:nth-child(4) { animation-delay: 0.4s; }
        .video-card:nth-child(5) { animation-delay: 0.5s; }
        .video-card:nth-child(6) { animation-delay: 0.6s; }

        .assessment-card:nth-child(1) { animation-delay: 0.1s; }
        .assessment-card:nth-child(2) { animation-delay: 0.2s; }
        .assessment-card:nth-child(3) { animation-delay: 0.3s; }
        .assessment-card:nth-child(4) { animation-delay: 0.4s; }
        .assessment-card:nth-child(5) { animation-delay: 0.5s; }
        .assessment-card:nth-child(6) { animation-delay: 0.6s; }

        /* Assessment Progress Indicators */
        .assessment-progress {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 0.75rem;
            margin: 1rem 0;
        }

        .assessment-progress-bar {
            height: 8px;
            background: linear-gradient(90deg, #e9ecef, #dee2e6);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .assessment-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 4px;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .assessment-progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Assessment Stats */
        .assessment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .stat-item {
            text-align: center;
            padding: 0.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 8px;
            border: 1px solid rgba(46, 94, 78, 0.1);
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Difficulty Badges */
        .difficulty-badge {
            font-size: 0.7rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .difficulty-easy {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .difficulty-medium {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }

        .difficulty-hard {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        /* Video Overview Card Styles */
        .progress-ring {
            transform: rotate(-90deg);
        }

        .progress-ring circle {
            transition: stroke-dashoffset 1.5s ease-in-out;
        }

        .video-overview-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }

        .video-overview-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer-overview 3s infinite;
        }

        @keyframes shimmer-overview {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .video-overview-card .card-body {
            position: relative;
            z-index: 1;
        }

        .video-overview-card .bg-white.bg-opacity-20 {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .video-overview-card .bg-white.bg-opacity-20:hover {
            background: rgba(255, 255, 255, 0.3) !important;
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }

        /* Video List Style */
        .video-list-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid rgba(46, 94, 78, 0.1);
            border-radius: 15px;
            margin-bottom: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .video-list-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .video-list-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 94, 78, 0.15);
            border-color: rgba(46, 94, 78, 0.3);
        }

        .video-list-item:hover::before {
            opacity: 1;
        }

        .video-list-item.watched {
            border-color: rgba(40, 167, 69, 0.3);
            background: linear-gradient(145deg, #f0fff4 0%, #ffffff 100%);
        }

        .video-list-item.watched::before {
            background: linear-gradient(90deg, #28a745, #20c997);
            opacity: 1;
        }

        .video-list-thumbnail {
            width: 80px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            position: relative;
            flex-shrink: 0;
            margin-right: 1rem;
        }

        .video-list-thumbnail iframe,
        .video-list-thumbnail video,
        .video-list-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }

        .video-list-thumbnail .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, rgba(46, 94, 78, 0.9), rgba(30, 86, 49, 0.9));
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(46, 94, 78, 0.3);
        }

        .video-list-thumbnail .play-button:hover {
            background: linear-gradient(135deg, rgba(46, 94, 78, 1), rgba(30, 86, 49, 1));
            transform: translate(-50%, -50%) scale(1.1);
        }

        .video-list-content {
            flex: 1;
            min-width: 0;
        }

        .video-list-title {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-list-description {
            color: #718096;
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-list-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .video-list-stats {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .video-list-status {
            margin-left: auto;
        }

        .video-list-status .badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
        }

        /* View Toggle Button */
        .view-toggle {
            display: flex;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 4px;
            border: 1px solid #dee2e6;
        }

        .view-toggle-btn {
            background: none;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            color: #6c757d;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .view-toggle-btn:hover {
            color: var(--primary-color);
            background: rgba(46, 94, 78, 0.1);
        }

        .view-toggle-btn.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(46, 94, 78, 0.3);
        }

        /* View Container */
        .video-view-container {
            transition: all 0.3s ease;
        }

        .video-card-view {
            display: none;
        }

        .video-card-view.active {
            display: block;
        }

        .video-list-view {
            display: block;
        }

        .video-list-view.active {
            display: block;
        }

        .video-list-view:not(.active) {
            display: none;
        }

        /* Assessment List Style */
        .assessment-list-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid rgba(46, 94, 78, 0.1);
            border-radius: 15px;
            margin-bottom: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .assessment-list-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .assessment-list-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 94, 78, 0.15);
            border-color: rgba(46, 94, 78, 0.3);
        }

        .assessment-list-item:hover::before {
            opacity: 1;
        }

        .assessment-list-item.completed {
            border-color: rgba(40, 167, 69, 0.3);
            background: linear-gradient(145deg, #f0fff4 0%, #ffffff 100%);
        }

        .assessment-list-item.completed::before {
            background: linear-gradient(90deg, #28a745, #20c997);
            opacity: 1;
        }

        .assessment-list-item.locked {
            opacity: 0.7;
            background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .assessment-list-item.locked::before {
            background: linear-gradient(90deg, #6c757d, #495057);
        }

        .assessment-list-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
            margin-right: 1rem;
            position: relative;
        }

        .assessment-list-icon.locked {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .assessment-list-icon.completed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .assessment-list-content {
            flex: 1;
            min-width: 0;
        }

        .assessment-list-title {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .assessment-list-description {
            color: #718096;
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .assessment-list-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .assessment-list-stats {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .assessment-list-status {
            margin-left: auto;
        }

        .assessment-list-status .badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
        }

        /* Assessment View Container */
        .assessment-card-view {
            display: none;
        }

        .assessment-card-view.active {
            display: block;
        }

        .assessment-list-view {
            display: block;
        }

        .assessment-list-view.active {
            display: block;
        }

        .assessment-list-view:not(.active) {
            display: none;
        }
        
        .assessment-card.locked::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 49%, #dee2e6 50%, transparent 51%);
            background-size: 20px 20px;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .assessment-card.locked .card-body {
            position: relative;
            z-index: 1;
        }

        /* Progress Circle */
        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(
                var(--primary-color) 0deg, 
                var(--primary-color) <?php echo $video_progress_percentage * 3.6; ?>deg, 
                var(--gray-200) <?php echo $video_progress_percentage * 3.6; ?>deg, 
                var(--gray-200) 360deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .progress-circle:hover {
            transform: scale(1.05);
        }

        .progress-circle::before {
            content: '';
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: white;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-text {
            position: absolute;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
            z-index: 2;
        }

        /* Badge Styling */
        .badge {
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, var(--success-color) 0%, var(--primary-color) 100%) !important;
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%) !important;
        }

        .badge.bg-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%) !important;
        }

        .badge.bg-secondary {
            background: linear-gradient(135deg, var(--gray-500) 0%, var(--gray-600) 100%) !important;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, var(--primary-color) 100%) !important;
        }

        /* Button Styling */
        .btn {
            font-weight: 600;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1e5631 0%, #2E5E4E 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, var(--primary-color) 100%);
            box-shadow: var(--shadow-md);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #1e5631 0%, #2E5E4E 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-outline-secondary:hover {
            background: #1e5631;
            border-color: #1e5631;
            color: white;
            transform: translateY(-2px);
        }

        .btn-outline-success {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid var(--success-color);
            color: var(--success-color);
            backdrop-filter: blur(10px);
        }

        .btn-outline-success:hover {
            background: #1e5631;
            border-color: #1e5631;
            color: white;
            transform: translateY(-2px);
        }

        /* Alert Styling */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            color: var(--success-color);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            color: var(--danger-color);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
            color: var(--warning-color);
            border-color: rgba(245, 158, 11, 0.2);
        }

        /* Action Button Styling */
        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.2rem 2.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(46, 94, 78, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .action-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .action-button:hover::before {
            left: 100%;
        }

        .action-button:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            color: white;
            text-decoration: none;
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 15px 40px rgba(46, 94, 78, 0.3);
        }

        .action-button.retake {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.25);
        }

        .action-button.retake:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            box-shadow: 0 15px 40px rgba(245, 158, 11, 0.35);
        }

        .action-button.start {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.25);
        }

        .action-button.start:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.35);
        }

        .action-button.completed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            cursor: default;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.25);
            position: relative;
        }

        .action-button.completed::after {
            content: '✓';
            position: absolute;
            top: -5px;
            right: -5px;
            background: #059669;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .action-button.completed:hover {
            transform: none;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.25);
        }

        .action-button.view-only {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            box-shadow: 0 8px 25px rgba(107, 114, 128, 0.25);
            cursor: not-allowed;
        }

        .action-button.view-only:hover {
            transform: none;
            box-shadow: 0 8px 25px rgba(107, 114, 128, 0.25);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .module-header-fullscreen {
                margin: 0 -15px 2rem -15px;
                padding: 2rem 0;
            }
            
            .module-title-new {
                font-size: 2rem;
            }
            
            .course-title-new {
                font-size: 1.2rem;
            }
            
            .header-main-card {
                padding: 1.5rem;
            }
            
            .course-meta {
                flex-direction: column;
                gap: 0.8rem;
            }
            
            .course-icon-wrapper {
                width: 80px;
                height: 80px;
            }
            
            .course-icon {
                font-size: 2rem;
            }
            
            
            .module-header-bg i {
                font-size: 10rem;
            }
            
            .progress-circle {
                width: 100px;
                height: 100px;
            }
            
            .progress-circle::before {
                width: 75px;
                height: 75px;
            }
            
            .progress-text {
                font-size: 1.2rem;
            }
            
            .action-button {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }
            
            /* Tab responsive design */
            .nav-tabs {
                flex-wrap: wrap;
            }
            
            .nav-tabs .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            
            .progress-circle-large {
                width: 120px;
                height: 120px;
            }
            
            .progress-circle-large::before {
                width: 90px;
                height: 90px;
            }
            
            .progress-text-large {
                font-size: 1.4rem;
            }
            
            .tab-content {
                padding: 1rem 0;
            }
        }

        /* Animation for progress reveal */
        @keyframes progressReveal {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .progress-circle {
            animation: progressReveal 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Large progress circle for progress tab */
        .progress-circle-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: conic-gradient(
                var(--primary-color) 0deg, 
                var(--primary-color) <?php echo $video_progress_percentage * 3.6; ?>deg, 
                var(--gray-200) <?php echo $video_progress_percentage * 3.6; ?>deg, 
                var(--gray-200) 360deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .progress-circle-large:hover {
            transform: scale(1.05);
        }

        .progress-circle-large::before {
            content: '';
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: white;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-text-large {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
            z-index: 1;
        }

        /* Tab styling */
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--primary-color);
            background-color: rgba(125, 203, 128, 0.1);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            position: relative;
            z-index: 1;
        }
        
        .nav-tabs .nav-link.active:hover {
            color: var(--primary-color);
            background-color: rgba(125, 203, 128, 0.1);
            border: none;
        }

        .tab-content {
            padding: 1.5rem 0;
        }

        /* Nested tabs styling */
        .nav-pills .nav-link {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .nav-pills .nav-link:hover {
            background-color: rgba(13, 110, 253, 0.1);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }

        .nav-pills .nav-link.active:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Optimize spacing for progress overview section */
        .progress-stats-row {
            margin-bottom: 1.5rem !important;
        }
        
        .progress-stats-row .col-md-3 {
            margin-bottom: 1rem;
        }
        
        .progress-stats-row .d-flex {
            padding: 0.75rem;
            background: rgba(248, 249, 250, 0.5);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .progress-stats-row .d-flex:hover {
            background: rgba(248, 249, 250, 0.8);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        /* Reduce spacing for videos and assessments sections */
        #video-section, #assessment-section {
            margin-top: -1.5rem !important;
        }
        
        #video-section h3, #assessment-section h3 {
            margin-bottom: 0.75rem !important;
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Reduce spacing between videos and assessments */
        #video-section {
            margin-bottom: 0.25rem !important;
        }
        
        #assessment-section {
            margin-top: -0.5rem !important;
        }
        
        /* Optimize video cards spacing */
        .video-card {
            margin-bottom: 1rem !important;
        }
        
        .video-card .card-body {
            padding: 1rem;
        }
        
        .video-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .video-card .card-text {
            font-size: 0.875rem;
            line-height: 1.4;
            margin-bottom: 0.75rem;
        }
        
        /* Improve progress bars */
        .progress {
            height: 6px !important;
            border-radius: 3px;
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .progress-bar {
            border-radius: 3px;
            transition: width 0.6s ease;
        }
        
        /* Compact statistics layout */
        .progress-stats-row .d-flex .me-3 {
            margin-right: 0.75rem !important;
        }
        
        .progress-stats-row .d-flex h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .progress-stats-row .d-flex p {
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }
        
        /* Video card improvements */
        .video-card {
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .video-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        }
        
        .video-card-preview {
            position: relative;
            overflow: hidden;
        }
        
        .video-card-preview::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }
        
        .video-card:hover .video-card-preview::after {
            transform: translateX(100%);
        }

        /* Staggered animation for cards */
        .video-card, .assessment-card {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) both;
        }

        .video-card:nth-child(1) { animation-delay: 0.1s; }
        .video-card:nth-child(2) { animation-delay: 0.2s; }
        .video-card:nth-child(3) { animation-delay: 0.3s; }
        .video-card:nth-child(4) { animation-delay: 0.4s; }
        .video-card:nth-child(5) { animation-delay: 0.5s; }
        .video-card:nth-child(6) { animation-delay: 0.6s; }

        .assessment-card:nth-child(1) { animation-delay: 0.1s; }
        .assessment-card:nth-child(2) { animation-delay: 0.2s; }
        .assessment-card:nth-child(3) { animation-delay: 0.3s; }
        .assessment-card:nth-child(4) { animation-delay: 0.4s; }
        .assessment-card:nth-child(5) { animation-delay: 0.5s; }
        .assessment-card:nth-child(6) { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            0% {
                transform: translateY(30px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* File Preview Hover Effects */
        .hover-shadow:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-2px);
        }
        
        .hover-shadow {
            border: 1px solid #e5e7eb;
        }
        
        .hover-shadow:hover {
            border-color: var(--primary-color);
        }
        
        /* File Preview Modal Styles */
        #filePreviewModal .modal-dialog {
            max-width: 90vw;
            height: 90vh;
        }
        
        #filePreviewModal .modal-content {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        #filePreviewModal .modal-body {
            flex: 1;
            overflow: hidden;
        }
        
        #filePreviewModal iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 8px;
        }
        
        .modal-xl {
            max-width: 95vw;
        }
        
        /* Enhanced Alert Animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        @keyframes slideInUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes celebration {
            0% { transform: scale(1) rotate(0deg); }
            25% { transform: scale(1.1) rotate(5deg); }
            50% { transform: scale(1.2) rotate(-5deg); }
            75% { transform: scale(1.1) rotate(3deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        
        
        /* Enhanced Requirement Cards */
        .requirement-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }
        
        .requirement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .requirement-card.completed {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
            animation: slideInUp 0.6s ease-out;
        }
        
        .requirement-card.pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
        }
        
        .requirement-card.failed {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 2px solid #dc3545;
        }
        
        .requirement-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Celebration Effects - Simplified */
        
        /* Enhanced Progress Bars */
        .progress-enhanced {
            height: 12px;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }
        
        .progress-enhanced .progress-bar {
            border-radius: 6px;
            position: relative;
            overflow: hidden;
        }
        
        .progress-enhanced .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 25%, rgba(255, 255, 255, 0.3) 25%, rgba(255, 255, 255, 0.3) 50%, transparent 50%, transparent 75%, rgba(255, 255, 255, 0.3) 75%);
            background-size: 20px 20px;
            animation: progress-stripes 1s linear infinite;
        }

        /* Ensure progress bars are visible and working */
        .progress-enhanced .progress-bar {
            transition: width 0.6s ease-in-out;
            min-width: 0%;
        }

        .progress-enhanced .progress-bar.bg-success {
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
        }

        .progress-enhanced .progress-bar.bg-warning {
            background: linear-gradient(90deg, #ffc107 0%, #fd7e14 100%);
        }

        .progress-enhanced .progress-bar.bg-danger {
            background: linear-gradient(90deg, #dc3545 0%, #e74c3c 100%);
        }

        /* Add pulse animation for active progress bars */
        .progress-enhanced .progress-bar:not(.bg-success) {
            animation: progress-pulse 2s ease-in-out infinite;
        }

        @keyframes progress-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        /* Assessment Performance Cards */
        .assessment-performance-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .assessment-performance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .assessment-performance-card.border-success {
            border-color: #28a745 !important;
            background: linear-gradient(135deg, #d4edda 0%, #f8f9fa 100%);
        }

        .assessment-performance-card.border-warning {
            border-color: #ffc107 !important;
            background: linear-gradient(135deg, #fff3cd 0%, #f8f9fa 100%);
        }

        .performance-stat {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        @keyframes progress-stripes {
            0% { background-position: 0 0; }
            100% { background-position: 20px 0; }
        }
        
        /* Auto-completion notification */
        .auto-completion-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1025;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
            animation: slideInRight 0.5s ease-out;
            max-width: 350px;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .module-description-header {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .text-white-75 {
            color: rgba(255, 255, 255, 0.85) !important;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="col-12 px-md-4">
                <!-- Module Header with IT Icon Background -->
                <?php 
                $theme = $course_themes[$course['id'] % count($course_themes)];
                ?>
                <!-- Fullscreen Header -->
                <div class="module-header-fullscreen <?php echo $theme['bg']; ?>">
                    <div class="module-header-bg">
                        <i class="<?php echo $theme['icon']; ?>"></i>
                    </div>
                    <div class="module-header-content">
                        <div class="container-fluid px-4">
                            <div class="row justify-content-center">
                                <div class="col-12">
                                    <!-- Main Header Card -->
                                    <div class="header-main-card">
                                        <div class="row align-items-center">
                                            <div class="col-lg-8">
                                                <div class="header-info">
                                                    <div class="module-badge">
                                                        <i class="fas fa-book me-2"></i>Module
                                                    </div>
                                                    <h1 class="module-title-new">
                            <?php echo htmlspecialchars($module['module_title'] ?? 'N/A'); ?>
                        </h1>
                                                    <h2 class="course-title-new">
                                                        <?php echo htmlspecialchars($course['course_name'] ?? ''); ?>
                                                    </h2>
                                                    
                                                    <div class="course-meta">
                                                        <div class="meta-item">
                                                            <i class="fas fa-code me-1"></i>
                                                            <span><?php echo htmlspecialchars($course['course_code'] ?? ''); ?></span>
                                                        </div>
                                                        <div class="meta-item">
                                                            <i class="fas fa-graduation-cap me-1"></i>
                                                            <span><?php echo htmlspecialchars($course['year_level'] ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div class="meta-item">
                                                            <i class="fas fa-star me-1"></i>
                                                            <span><?php echo htmlspecialchars($course['credits'] ?? 0); ?> Credits</span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="instructor-info">
                                                        <i class="fas fa-user-tie me-2"></i>
                                                        <span>Instructor: <strong><?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></strong></span>
                                                    </div>
                                                    
                                                    <div class="academic-info">
                                                        <i class="fas fa-calendar me-2"></i>
                                                        <span><?php echo htmlspecialchars($course['academic_year'] ?? ''); ?> • <?php echo htmlspecialchars($course['semester_name'] ?? ''); ?></span>
                                <?php if ($course['start_date'] && $course['end_date']): ?>
                                                            <span class="date-range">
                                    • <?php echo date('M j', strtotime($course['start_date'])); ?> - <?php echo date('M j, Y', strtotime($course['end_date'])); ?>
                                                            </span>
                                <?php endif; ?>
                                                    </div>
                    </div>
                </div>

                                            <div class="col-lg-4">
                                                <div class="header-visual">
                                                    <div class="course-icon-wrapper">
                                                        <i class="<?php echo $theme['icon']; ?> course-icon"></i>
                    </div>
                                                </div>
                    </div>
                </div>
                                        
                                        <!-- Module Description in Header -->
                                        <?php if (!empty($module['module_description'])): ?>
                                        <div class="module-description-section">
                                            <div class="description-header">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <span>Module Description</span>
                                            </div>
                                            <div class="description-content">
                                                <?php echo nl2br(htmlspecialchars($module['module_description'])); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Combined Progress and Assessment Statistics -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="progressTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="true">
                                            <i class="fas fa-chart-line me-2"></i>Progress Overview
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="assessments-tab" data-bs-toggle="tab" data-bs-target="#assessments" type="button" role="tab" aria-controls="assessments" aria-selected="false">
                                            <i class="fas fa-clipboard-check me-2"></i>Assessment Details
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="module-info-tab" data-bs-toggle="tab" data-bs-target="#module-info" type="button" role="tab" aria-controls="module-info" aria-selected="false">
                                            <i class="fas fa-info-circle me-2"></i>Module Information
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="progressTabsContent">
                                    <!-- Progress Overview Tab -->
                                    <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                                        <!-- Progress Statistics -->
                                <div class="row text-center progress-stats-row">
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <div class="me-3">
                                                <i class="fas fa-video fa-2x text-primary"></i>
                                            </div>
                                            <div>
                                                <h3 class="mb-0" id="video-progress-count"><?php echo $watched_videos; ?> / <?php echo $total_videos; ?></h3>
                                                <p class="text-muted mb-0">Videos Watched</p>
                                                <div class="progress mt-2" style="height: 8px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" 
                                                         style="width: <?php echo $video_progress_percentage; ?>%" id="video-progress-bar">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <div class="me-3">
                                                        <i class="fas fa-clipboard-check fa-2x text-success"></i>
                                            </div>
                                            <div>
                                                        <h3 class="mb-0" id="assessment-progress-count"><?php echo $completed_assessments; ?> / <?php echo $total_assessments; ?></h3>
                                                        <p class="text-muted mb-0">Assessments Completed</p>
                                                        <div class="progress mt-2" style="height: 8px;">
                                                            <div class="progress-bar bg-success" role="progressbar" 
                                                                 style="width: <?php echo $assessment_progress_percentage; ?>%" id="assessment-progress-bar">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="me-3">
                                                        <i class="fas fa-star fa-2x text-warning"></i>
                                                    </div>
                                                    <div>
                                                        <h3 class="mb-0" id="points-earned"><?php echo $total_points_earned; ?></h3>
                                                        <p class="text-muted mb-0">Total Points Earned</p>
                                                        <div class="progress mt-2" style="height: 8px;">
                                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                                 style="width: 100%" id="points-progress-bar">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="me-3">
                                                        <i class="fas fa-clock fa-2x text-info"></i>
                                                    </div>
                                                    <div>
                                                        <h3 class="mb-0" id="watch-time"><?php echo $total_watch_time_formatted; ?></h3>
                                                        <p class="text-muted mb-0">Total Watch Time</p>
                                                        <div class="progress mt-2" style="height: 8px;">
                                                            <div class="progress-bar bg-info" role="progressbar" 
                                                                 style="width: <?php echo $video_progress_percentage; ?>%" id="time-progress-bar">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Module Information Tab -->
                                    <div class="tab-pane fade" id="module-info" role="tabpanel" aria-labelledby="module-info-tab">
                                        <!-- Module Files -->
                                        <div class="mb-4">
                                            <h6 class="text-primary mb-3">
                                                <i class="fas fa-paperclip me-2"></i>Module Files
                                            </h6>
                                            <?php if (isset($module['files']) && !empty($module['files']) && is_array($module['files'])): ?>
                                                <?php foreach ($module['files'] as $file): ?>
                                                    <div class="d-flex align-items-center p-3 bg-light rounded hover-shadow mb-3" 
                                                         style="transition: all 0.3s ease; cursor: pointer;"
                                                         onclick="openFilePreview('<?php echo $module['id']; ?>', '<?php echo urlencode($file['filename']); ?>', '<?php echo urlencode($file['original_name']); ?>', '<?php echo $file['file_size']; ?>', '<?php echo $file['uploaded_at']; ?>')"
                                                         onmouseover="this.style.backgroundColor='#f8f9fa'" 
                                                         onmouseout="this.style.backgroundColor='#f8f9fa'">
                                                        <i class="fas fa-file me-3 text-primary fs-4"></i>
                                                        <div class="flex-grow-1">
                                                            <div class="fw-semibold text-dark fs-6"><?php echo htmlspecialchars($file['original_name']); ?></div>
                                                            <small class="text-muted">
                                                                <?php echo round($file['file_size'] / 1024, 1); ?> KB • 
                                                                Uploaded <?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <span class="badge bg-primary fs-6">
                                                            <i class="fas fa-eye"></i> Preview
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php elseif (isset($module['file']) && !empty($module['file'])): ?>
                                                <div class="d-flex align-items-center p-3 bg-light rounded hover-shadow" 
                                                     style="transition: all 0.3s ease; cursor: pointer;"
                                                     onclick="openFilePreview('<?php echo $module['id']; ?>', '<?php echo urlencode($module['file']['filename']); ?>', '<?php echo urlencode($module['file']['original_name']); ?>', '<?php echo $module['file']['file_size']; ?>', '<?php echo $module['file']['uploaded_at']; ?>')"
                                                     onmouseover="this.style.backgroundColor='#f8f9fa'" 
                                                     onmouseout="this.style.backgroundColor='#f8f9fa'">
                                                    <i class="fas fa-file me-3 text-primary fs-4"></i>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-semibold text-dark fs-6"><?php echo htmlspecialchars($module['file']['original_name']); ?></div>
                                                        <small class="text-muted">
                                                            <?php echo round($module['file']['file_size'] / 1024, 1); ?> KB • 
                                                            Uploaded <?php echo date('M j, Y', strtotime($module['file']['uploaded_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <span class="badge bg-primary fs-6">
                                                        <i class="fas fa-eye"></i> Preview
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center text-muted py-4">
                                                    <i class="fas fa-file fa-3x mb-3"></i>
                                                    <p>No files available for this module.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Module Statistics -->
                                                <div class="mb-4">
                                                    <h6 class="text-primary mb-3">
                                                        <i class="fas fa-chart-bar me-2"></i>Module Statistics
                                                    </h6>
                                                    <div class="row">
                                                        <div class="col-md-3 col-6 mb-3">
                                                            <div class="text-center p-3 bg-primary bg-opacity-10 rounded">
                                                                <i class="fas fa-video text-primary fs-3 mb-2"></i>
                                                                <div class="fw-bold text-primary fs-4"><?php echo count($videos); ?></div>
                                                                <small class="text-muted">Videos</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3 col-6 mb-3">
                                                            <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                                                                <i class="fas fa-question-circle text-success fs-3 mb-2"></i>
                                                                <div class="fw-bold text-success fs-4"><?php echo count($assessments); ?></div>
                                                                <small class="text-muted">Assessments</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3 col-6 mb-3">
                                                            <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                                                                <i class="fas fa-file text-info fs-3 mb-2"></i>
                                                                <div class="fw-bold text-info fs-4"><?php echo (isset($module['files']) && is_array($module['files'])) ? count($module['files']) : (isset($module['file']) ? 1 : 0); ?></div>
                                                                <small class="text-muted">Files</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3 col-6 mb-3">
                                                            <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
                                                                <i class="fas fa-calendar text-warning fs-3 mb-2"></i>
                                                                <div class="fw-bold text-warning fs-4"><?php echo $module['module_order'] ?? 1; ?></div>
                                                                <small class="text-muted">Module</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Prerequisites -->
                                                <div class="mb-4">
                                                    <h6 class="text-primary mb-3">
                                                        <i class="fas fa-lock me-2"></i>Prerequisites
                                                    </h6>
                                                    <?php if (isset($module['unlock_score']) && $module['unlock_score'] > 0): ?>
                                                        <div class="text-center py-3">
                                                            <i class="fas fa-lock text-warning fa-3x mb-3"></i>
                                                            <h6 class="text-warning mb-2">Prerequisites Required</h6>
                                                            <p class="text-muted mb-3">
                                                                This module requires a minimum score of <strong class="text-warning"><?php echo $module['unlock_score']; ?>%</strong> from previous assessments to unlock.
                                                            </p>
                                                            <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                                Score Required: <?php echo $module['unlock_score']; ?>%
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center py-3">
                                                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                                            <h6 class="text-success mb-2">No Prerequisites</h6>
                                                            <p class="text-muted mb-0">
                                                                This module has no prerequisites and is available for immediate access.
                                                            </p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>


                                    
                                    <!-- Assessment Details Tab -->
                                    <div class="tab-pane fade" id="assessments" role="tabpanel" aria-labelledby="assessments-tab">
                                        <?php if (!empty($assessments)): ?>
                                        <div class="row" id="assessment-breakdown">
                                            <?php foreach ($assessments as $assessment): ?>
                                                <?php
                                                $assessment_id = $assessment['id'];
                                                $passing_rate = $assessment['passing_rate'] ?? 70;
                                                
                                                // Get detailed attempts for this assessment
                                                $stmt = $pdo->prepare("
                                                    SELECT score, has_passed, started_at, completed_at, time_taken
                                                    FROM assessment_attempts 
                                                    WHERE assessment_id = ? AND student_id = ? AND status = 'completed'
                                                    ORDER BY score DESC
                                                ");
                                                $stmt->execute([$assessment_id, $user_id]);
                                                $attempts = $stmt->fetchAll();
                                                
                                                $total_attempts = count($attempts);
                                                $successful_attempts = 0;
                                                $best_score = 0;
                                                $latest_score = 0;
                                                $is_passed = false;
                                                
                                                if (!empty($attempts)) {
                                                    $best_score = $attempts[0]['score'];
                                                    $latest_score = end($attempts)['score'];
                                                    $successful_attempts = count(array_filter($attempts, function($attempt) use ($passing_rate) {
                                                        return $attempt['score'] >= $passing_rate && $attempt['has_passed'];
                                                    }));
                                                    $is_passed = $best_score >= $passing_rate && $attempts[0]['has_passed'];
                                                }
                                                
                                                // success_rate is now calculated globally above
                                                ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card assessment-performance-card <?php echo $is_passed ? 'border-success' : 'border-warning'; ?>">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <h6 class="card-title mb-0">
                                                                    <i class="fas fa-question-circle me-2 text-primary"></i>
                                                                    <?php echo htmlspecialchars($assessment['assessment_title']); ?>
                                                                </h6>
                                                                <span class="badge <?php echo $is_passed ? 'bg-success' : 'bg-warning'; ?>">
                                                                    <?php echo $is_passed ? 'Passed' : 'In Progress'; ?>
                                                                </span>
                                                            </div>
                                                            
                                                            <div class="row text-center mb-3">
                                                                <div class="col-4">
                                                                    <div class="performance-stat">
                                                                        <div class="stat-value text-primary"><?php echo $best_score; ?>%</div>
                                                                        <div class="stat-label">Best Score</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-4">
                                                                    <div class="performance-stat">
                                                                        <div class="stat-value text-info"><?php echo $total_attempts; ?></div>
                                                                        <div class="stat-label">Attempts</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-4">
                                                                    <div class="performance-stat">
                                                                        <div class="stat-value <?php echo $success_rate >= 50 ? 'text-success' : 'text-warning'; ?>"><?php echo $success_rate; ?>%</div>
                                                                        <div class="stat-label">Success Rate</div>
                        </div>
                    </div>
                </div>

                                                            <div class="progress mb-2" style="height: 6px;">
                                                                <div class="progress-bar <?php echo $is_passed ? 'bg-success' : 'bg-warning'; ?>" 
                                                                     style="width: <?php echo min(($best_score / $passing_rate) * 100, 100); ?>%">
                                                                </div>
                                                            </div>
                                                            
                                                            <small class="text-muted">
                                                                Required: <?php echo $passing_rate; ?>% | 
                                                                <?php if ($total_attempts > 0): ?>
                                                                    Latest: <?php echo $latest_score; ?>% | 
                                                                    <?php echo $successful_attempts; ?> of <?php echo $total_attempts; ?> passed
                                                                <?php else: ?>
                                                                    No attempts yet
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Assessments Available</h5>
                                            <p class="text-muted">There are no assessments for this module yet.</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                                    </div>
                                    </div>
                                    </div>
                            </div>




                <!-- Videos Section -->
                <?php if (!empty($videos)): ?>
                    
                    <!-- Videos Section Card -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 15px; overflow: hidden;">
                        <div class="card-header bg-white border-0 py-2 px-3" style="border-radius: 15px 15px 0 0;">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="mb-0"><i class="fas fa-video me-2 text-primary"></i>Videos</h5>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center text-muted">
                                        <i class="fas fa-play-circle me-1"></i>
                                        <small class="fw-bold"><?php echo count($videos); ?> Video<?php echo count($videos) !== 1 ? 's' : ''; ?></small>
                                                </div>
                                    <div class="view-toggle">
                                        <button class="view-toggle-btn active" data-view="list" title="List View">
                                            <i class="fas fa-list"></i>
                                            <span>List</span>
                                        </button>
                                        <button class="view-toggle-btn" data-view="cards" title="Card View">
                                            <i class="fas fa-th"></i>
                                            <span>Cards</span>
                                        </button>
                                            </div>
                                                </div>
                                            </div>
                                                </div>
                        <div class="card-body p-3">
                            <!-- List View -->
                            <div class="video-list-view active" id="video-list-view">
                                <div class="video-list-container">
                                <?php foreach ($videos as $index => $video): ?>
                                <div class="video-list-item <?php echo $video['is_watched'] ? 'watched' : ''; ?>" data-video-id="<?php echo htmlspecialchars($video['id']); ?>">
                                    <!-- Watched Badge -->
                                    <?php if ($video['is_watched']): ?>
                                        <div class="video-watched-badge">
                                            <span class="badge bg-success">
                                                Watched
                                                    </span>
                        </div>
                                        <?php endif; ?>
                                    
                                    <!-- Video Thumbnail -->
                                    <div class="video-list-thumbnail">
                                            <?php 
                                            $video_url = $video['video_url'] ?? '';
                                            $video_file = $video['video_file'] ?? '';
                                            
                                            // Check if video_file contains a URL (from old data)
                                            if (!empty($video_file) && (strpos($video_file, 'http') === 0 || strpos($video_file, 'www.') === 0)) {
                                                $video_url = $video_file;
                                                $video_file = '';
                                            }
                                            
                                            if (!empty($video_url)) {
                                                if (preg_match('/youtu\.be|youtube\.com/', $video_url)) {
                                                    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:embed/|v/|watch\?v=|watch\?.+&v=))([^&?/]+)~', $video_url, $matches)) {
                                                        $youtube_id = $matches[1];
                                                        echo '<img src="https://img.youtube.com/vi/' . htmlspecialchars($youtube_id) . '/mqdefault.jpg" alt="YouTube Video">';
                                                    } else {
                                                        echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                        echo '<i class="fas fa-video text-white fs-4"></i>';
                                                        echo '</div>';
                                                    }
                                                } elseif (preg_match('/drive\.google\.com/', $video_url)) {
                                                    echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                    echo '<i class="fab fa-google-drive text-white fs-4"></i>';
                                                    echo '</div>';
                                                } elseif (preg_match('/\.mp4$/', $video_url)) {
                                                    echo '<video style="width: 100%; height: 100%; object-fit: cover;" preload="metadata">';
                                                    echo '<source src="' . htmlspecialchars($video_url) . '" type="video/mp4">';
                                                    echo '</video>';
                                                } else {
                                                    echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                    echo '<i class="fas fa-link text-white fs-4"></i>';
                                                    echo '</div>';
                                                }
                                            } elseif (!empty($video_file)) {
                                                echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                echo '<i class="fas fa-file-video text-white fs-4"></i>';
                                                echo '</div>';
                                            } else {
                                                echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                echo '<i class="fas fa-video text-white fs-4"></i>';
                                                echo '</div>';
                                            }
                                            ?>
                                        <button class="play-button">
                                            <i class="fas fa-play"></i>
                                        </button>
                                </div>
                                        
                                    <!-- Video Content -->
                                    <div class="video-list-content">
                                        <div class="video-list-title"><?php echo htmlspecialchars($video['video_title'] ?? ''); ?></div>
                                        <div class="video-list-description"><?php echo htmlspecialchars($video['video_description'] ?? 'No description available.'); ?></div>
                                        
                                        <div class="video-list-meta">
                                            <div class="video-list-stats">
                                                <span><i class="fas fa-clock me-1"></i><?php echo $video['min_watch_time'] ?? 5; ?>m</span>
                                                <span><i class="fas fa-percentage me-1"></i><?php echo number_format($video['completion_percentage'] ?? 0, 1); ?>%</span>
                                                <span><i class="fas fa-stopwatch me-1"></i><?php echo gmdate("H:i:s", $video['watch_duration'] ?? 0); ?></span>
                                    </div>
                                            
                                            <div class="video-list-status">
                                                <?php if (!$video['is_watched']): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock me-1"></i>Not Started
                                                    </span>
                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <div class="ms-3">
                                        <?php if ($video['video_url'] ?? $video['video_file'] ?? ''): ?>
                                            <a href="video_player.php?id=<?php echo $video['id']; ?>&module_id=<?php echo $module_id; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-play me-1"></i>Watch
                                            </a>
                                    <?php endif; ?>
                                        </div>
                                            </div>
                            <?php endforeach; ?>
                    </div>
                </div>

                            <!-- Card View -->
                            <div class="video-card-view" id="video-card-view">
                        <div class="row g-3">
                            <?php foreach ($videos as $index => $video): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card video-card h-100 border-0 shadow-sm <?php echo $video['is_watched'] ? 'watched' : ''; ?>" data-video-id="<?php echo htmlspecialchars($video['id']); ?>">
                                        <!-- Watched Badge -->
                                        <?php if ($video['is_watched']): ?>
                                            <div class="video-watched-badge">
                                                <span class="badge bg-success">
                                                    Watched
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Video Preview Section -->
                                        <div class="video-card-preview">
                                            <?php 
                                            $video_url = $video['video_url'] ?? '';
                                            $video_file = $video['video_file'] ?? '';
                                            
                                            // Check if video_file contains a URL (from old data)
                                            if (!empty($video_file) && (strpos($video_file, 'http') === 0 || strpos($video_file, 'www.') === 0)) {
                                                $video_url = $video_file;
                                                $video_file = '';
                                            }
                                            
                                            if (!empty($video_url)) {
                                                if (preg_match('/youtu\.be|youtube\.com/', $video_url)) {
                                                    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:embed/|v/|watch\?v=|watch\?.+&v=))([^&?/]+)~', $video_url, $matches)) {
                                                        $youtube_id = $matches[1];
                                                            echo '<iframe style="height: 160px; width: 100%;" src="https://www.youtube.com/embed/' . htmlspecialchars($youtube_id) . '" frameborder="0" allowfullscreen></iframe>';
                                                    } else {
                                                            echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                        echo '<div class="text-center text-muted">';
                                                        echo '<i class="fas fa-video display-4 mb-2"></i>';
                                                        echo '<div class="fw-bold">YouTube Video</div>';
                                                        echo '<small>Click to watch</small>';
                                                        echo '</div>';
                                                        echo '</div>';
                                                    }
                                                } elseif (preg_match('/drive\.google\.com/', $video_url)) {
                                                        echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                    echo '<div class="text-center text-muted">';
                                                    echo '<i class="fab fa-google-drive display-4 mb-2"></i>';
                                                    echo '<div class="fw-bold">Google Drive Video</div>';
                                                    echo '<small>Click to watch</small>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                } elseif (preg_match('/\.mp4$/', $video_url)) {
                                                        echo '<video style="height: 160px; width: 100%; object-fit: cover;" controls preload="metadata">';
                                                    echo '<source src="' . htmlspecialchars($video_url) . '" type="video/mp4">';
                                                    echo 'Your browser does not support the video tag.';
                                                    echo '</video>';
                                                } else {
                                                        echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                    echo '<div class="text-center text-muted">';
                                                    echo '<i class="fas fa-link display-4 mb-2"></i>';
                                                    echo '<div class="fw-bold">Video Link</div>';
                                                    echo '<small>Click to watch</small>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                }
                                            } elseif (!empty($video_file)) {
                                                    echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                echo '<div class="text-center text-muted">';
                                                echo '<i class="fas fa-file-video display-4 mb-2"></i>';
                                                echo '<div class="fw-bold">Video File</div>';
                                                echo '<small>Click to watch</small>';
                                                echo '</div>';
                                                echo '</div>';
                                            } else {
                                                    echo '<div class="d-flex align-items-center justify-content-center h-100">';
                                                echo '<div class="text-center text-muted">';
                                                    echo '<i class="fas fa-video display-4 mb-2"></i>';
                                                    echo '<div class="fw-bold">No Preview</div>';
                                                    echo '<small>Click to watch</small>';
                                                echo '</div>';
                                                echo '</div>';
                                            }
                                            ?>
                                                <button class="play-button">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                        </div>
                                        
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <h5 class="card-title"><?php echo htmlspecialchars($video['video_title'] ?? ''); ?></h5>
                                            </div>
                                            
                                            <p class="card-text text-muted"><?php echo htmlspecialchars(substr($video['video_description'] ?? 'No description available.', 0, 100)); ?><?php echo strlen($video['video_description'] ?? '') > 100 ? '...' : ''; ?></p>
                                            
                                            <!-- Statistics Row -->
                                            <div class="row g-2 mb-3">
                                                <div class="col-4">
                                                    <div class="text-center p-2 bg-light rounded">
                                                            <div class="fw-bold text-primary fs-5"><?php echo $video['min_watch_time'] ?? 5; ?>m</div>
                                                        <small class="text-muted d-block">Duration</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-center p-2 bg-light rounded">
                                                            <div class="fw-bold text-info fs-5"><?php echo $index + 1; ?></div>
                                                        <small class="text-muted d-block">Order</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-center p-2 bg-light rounded">
                                                            <div class="fw-bold text-warning fs-5"><?php echo $video['completion_percentage'] ?? 0; ?>%</div>
                                                        <small class="text-muted d-block">Progress</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="d-grid gap-2">
                                                <?php if ($video['video_url'] ?? $video['video_file'] ?? ''): ?>
                                                    <a href="video_player.php?id=<?php echo $video['id']; ?>&module_id=<?php echo $module_id; ?>" class="action-button start">
                                                        <i class="fas fa-play"></i>
                                                        <span>Watch Video</span>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($video['is_watched']): ?>
                                                    <div class="text-center">
                                                        <span class="badge bg-success">
                                                            Watched
                                                        </span>
                                                        <?php if (isset($video_progress[$video['id']]['watch_duration'])): ?>
                                                            <div class="small text-muted mt-1">
                                                                Watched for <?php echo gmdate("H:i:s", $video_progress[$video['id']]['watch_duration']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center">
                                                            <span class="badge bg-warning">
                                                                <i class="fas fa-clock"></i> Not Started
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Assessments Section -->
                <?php if (!empty($assessments)): ?>
                    <!-- Assessments Section Card -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 15px; overflow: hidden;">
                        <div class="card-header bg-white border-0 py-2 px-3" style="border-radius: 15px 15px 0 0;">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="mb-0"><i class="fas fa-clipboard-check me-2 text-primary"></i>Assessments</h5>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center text-muted">
                                        <i class="fas fa-tasks me-2"></i>
                                        <small class="fw-bold"><?php echo count($assessments); ?> Assessment<?php echo count($assessments) !== 1 ? 's' : ''; ?></small>
                                    </div>
                                    <div class="view-toggle">
                                        <button class="view-toggle-btn active" data-view="list" data-section="assessment" title="List View">
                                            <i class="fas fa-list"></i>
                                            <span>List</span>
                                        </button>
                                        <button class="view-toggle-btn" data-view="cards" data-section="assessment" title="Card View">
                                            <i class="fas fa-th"></i>
                                            <span>Cards</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                        <!-- List View -->
                        <div class="assessment-list-view active" id="assessment-list-view">
                            <div class="assessment-list-container">
                            <?php foreach ($assessments as $assessment): ?>
                                <?php $is_locked = !$unlocked[$assessment['id']]; ?>
                                <div class="assessment-list-item <?php echo $assessment['best_score'] >= ($assessment['passing_rate'] ?? 70) ? 'completed' : ''; ?> <?php echo $is_locked ? 'locked' : ''; ?>" data-assessment-id="<?php echo htmlspecialchars($assessment['id']); ?>">
                                    <!-- Passed Badge -->
                                    <?php if ($assessment['best_score'] >= ($assessment['passing_rate'] ?? 70)): ?>
                                        <div class="assessment-passed-badge">
                                            <span class="badge bg-success">
                                                Passed
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Assessment Icon -->
                                    <div class="assessment-list-icon <?php echo $is_locked ? 'locked' : ($assessment['best_score'] >= ($assessment['passing_rate'] ?? 70) ? 'completed' : ''); ?>">
                                        <?php if ($is_locked): ?>
                                            <i class="fas fa-lock"></i>
                                        <?php elseif ($assessment['best_score'] >= ($assessment['passing_rate'] ?? 70)): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            <i class="fas fa-clipboard-check"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Assessment Content -->
                                    <div class="assessment-list-content">
                                        <div class="assessment-list-title">
                                            <span class="badge bg-<?php echo $is_locked ? 'secondary' : 'primary'; ?> me-2">
                                                <?php echo $assessment['assessment_order'] ?? 1; ?>
                                            </span>
                                            <?php echo htmlspecialchars($assessment['assessment_title'] ?? ''); ?>
                                        </div>
                                        <div class="assessment-list-description"><?php echo htmlspecialchars($assessment['description'] ?? 'No description available.'); ?></div>
                                        
                                        <div class="assessment-list-meta">
                                            <div class="assessment-list-stats">
                                                <span><i class="fas fa-clock me-1"></i><?php echo $assessment['time_limit'] ?? 0; ?>m</span>
                                                <span><i class="fas fa-question-circle me-1"></i><?php echo $assessment['num_questions'] ?? 0; ?> questions</span>
                                                <span><i class="fas fa-percentage me-1"></i><?php echo $assessment['passing_rate'] ?? 70; ?>% pass</span>
                                                <span><i class="fas fa-signal me-1"></i><?php echo ucfirst($assessment['difficulty'] ?? 'medium'); ?></span>
                                            </div>
                                            
                                            <div class="assessment-list-status">
                                                <?php if ($is_locked): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-lock me-1"></i>Locked
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-play me-1"></i>Available
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <div class="ms-3">
                                        <?php if ($is_locked): ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-lock me-1"></i>Locked
                                            </button>
                                        <?php else: ?>
                                            <a href="assessment.php?id=<?php echo $assessment['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-play me-1"></i>Take
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Card View -->
                        <div class="assessment-card-view" id="assessment-card-view">
                            <div class="row g-4">
                            <?php foreach ($assessments as $assessment): ?>
                                <?php $is_locked = !$unlocked[$assessment['id']]; ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card assessment-card h-100 <?php echo $assessment['best_score'] >= ($assessment['passing_rate'] ?? 70) ? 'completed' : ''; ?> <?php echo $is_locked ? 'locked' : ''; ?>">
                                        <!-- Passed Badge -->
                                        <?php if ($assessment['best_score'] >= ($assessment['passing_rate'] ?? 70)): ?>
                                            <div class="assessment-passed-badge">
                                                <span class="badge bg-success">
                                                    Passed
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <h5 class="card-title">
                                                    <span class="badge bg-<?php echo $is_locked ? 'secondary' : 'primary'; ?> me-2">
                                                        <?php echo $assessment['assessment_order'] ?? 1; ?>
                                                    </span>
                                                    <?php echo htmlspecialchars($assessment['assessment_title'] ?? ''); ?>
                                                    <?php if ($unlocked[$assessment['id']] && !$is_locked && ($assessment['assessment_order'] ?? 1) == 1): ?>
                                                        <span class="badge bg-success ms-2">
                                                            <i class="fas fa-star"></i> First
                                                        </span>
                                                    <?php endif; ?>
                                </h5>
                                                <?php if ($is_locked): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-lock"></i> Locked
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="card-text"><?php echo htmlspecialchars(substr($assessment['description'] ?? 'No description available.', 0, 100)); ?><?php echo strlen($assessment['description'] ?? '') > 100 ? '...' : ''; ?></p>
                                            
                                            <!-- Enhanced Stats Grid -->
                                            <div class="assessment-stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $assessment['time_limit'] ?? 0; ?></div>
                                                    <div class="stat-label">Minutes</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $assessment['num_questions'] ?? 0; ?></div>
                                                    <div class="stat-label">Questions</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $assessment['passing_rate'] ?? 70; ?>%</div>
                                                    <div class="stat-label">Pass Rate</div>
                                                </div>
                            </div>
                                            
                                            <!-- Difficulty Badge -->
                                            <div class="text-center mb-3">
                                                <span class="difficulty-badge difficulty-<?php echo strtolower($assessment['difficulty'] ?? 'medium'); ?>">
                                                    <i class="fas fa-<?php echo strtolower($assessment['difficulty'] ?? 'medium') === 'easy' ? 'smile' : (strtolower($assessment['difficulty'] ?? 'medium') === 'hard' ? 'fire' : 'star'); ?> me-1"></i>
                                                    <?php echo ucfirst($assessment['difficulty'] ?? 'medium'); ?>
                                                </span>
                            </div>
                                            
                                            <!-- Assessment Performance Stats -->
                                            <?php if ($assessment['attempt_count'] > 0): ?>
                                                <div class="assessment-progress">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="fw-bold text-dark">Progress</span>
                                                        <span class="text-primary fw-bold"><?php echo number_format($assessment['best_score'], 1); ?>%</span>
                                                        </div>
                                                    <div class="assessment-progress-bar">
                                                        <div class="assessment-progress-fill" style="width: <?php echo min(100, $assessment['best_score']); ?>%"></div>
                                                        </div>
                                                    
                                                    <div class="assessment-stats mt-3">
                                                        <div class="stat-item">
                                                            <div class="stat-value text-success"><?php echo number_format($assessment['best_score'], 1); ?>%</div>
                                                            <div class="stat-label">Best Score</div>
                                                        </div>
                                                        <div class="stat-item">
                                                            <div class="stat-value text-info"><?php echo number_format($assessment['average_score'], 1); ?>%</div>
                                                            <div class="stat-label">Average</div>
                                                        </div>
                                                        <div class="stat-item">
                                                            <div class="stat-value text-primary"><?php echo $assessment['attempt_count']; ?></div>
                                                            <div class="stat-label">Attempts</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($assessment['last_attempt_date']): ?>
                                                        <div class="text-center mt-2">
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                Last attempt: <?php echo date('M j, Y', strtotime($assessment['last_attempt_date'])); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($assessment['total_time_spent'] > 0): ?>
                                                        <div class="mt-1">
                                                            <small class="text-muted">
                                                                Total time: <?php echo gmdate('H:i:s', $assessment['total_time_spent']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-grid gap-2">
                                                <?php 
                                                // Check if student has ever passed this assessment
                                                $has_ever_passed = hasStudentPassedAssessment($pdo, $user_id, $assessment['id']);
                                                ?>
                                                <?php if ($is_locked): ?>
                                                    <div class="action-button view-only" style="cursor: not-allowed; opacity: 0.7;">
                                                        <i class="fas fa-lock"></i>
                                                        <span>
                                                            Complete Assessment <?php echo ($assessment['assessment_order'] ?? 1) - 1; ?> first
                                                            <?php if (isset($assessment['prerequisite_title'])): ?>
                                                                <br><small class="text-muted">(<?php echo htmlspecialchars($assessment['prerequisite_title']); ?>)</small>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted text-center">
                                                        You must pass Assessment <?php echo ($assessment['assessment_order'] ?? 1) - 1; ?> to unlock this assessment
                                                    </small>
                                                <?php elseif ($has_ever_passed): ?>
                                                    <div class="action-button completed">
                                                        <i class="fas fa-check-circle"></i>
                                                        <span>Assessment Passed</span>
                                                    </div>
                                                    <small class="text-muted text-center">
                                                        No retakes allowed for passed assessments
                                                    </small>
                                                <?php else: ?>
                                                    <a href="assessment.php?id=<?php echo $assessment['id']; ?>" class="action-button start">
                                                        <i class="fas fa-play"></i>
                                                        <span>Take Assessment</span>
                                                    </a>
                                                    <?php if ($assessment['attempt_count'] > 0): ?>
                                                        <small class="text-muted text-center">
                                                            <?php echo $assessment['attempt_count']; ?> attempt(s) taken
                                                        </small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        </div>
                    </div>
                <?php endif; ?>





                <!-- Module Completion -->
                <div class="mb-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <?php if ($is_completed): ?>
                                <h5 class="card-title text-success">
                                    <i class="fas fa-check-circle"></i> Module Completed
                                </h5>
                                <p class="card-text">You completed this module on <?php echo date('M j, Y', strtotime($completed_at)); ?></p>
                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Course
                                </a>
                            <?php else: ?>
                                <h5 class="card-title">Complete Module</h5>
                                <p class="card-text">
                                    <?php if ($requirements['can_complete']): ?>
                                        All requirements have been met. You can now mark this module as completed.
                                    <?php else: ?>
                                        Complete all videos and assessments to unlock module completion.
                                    <?php endif; ?>
                                </p>
                                
                                <?php if ($requirements['can_complete']): ?>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="complete_module" class="action-button start">
                                            <i class="fas fa-check"></i>
                                            <span>Mark as Complete</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="action-button view-only" disabled>
                                        <i class="fas fa-lock"></i>
                                        <span>Requirements Not Met</span>
                                    </button>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Complete all requirements above to unlock module completion
                                        </small>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- File Preview Modal -->
    <div class="modal fade" id="filePreviewModal" tabindex="-1" aria-labelledby="filePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filePreviewModalLabel">
                        <i class="fas fa-file me-2"></i>
                        <span id="modalFileName">File Preview</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="filePreviewContent" class="text-center p-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading file preview...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="d-flex justify-content-between w-100">
                        <div id="fileInfo" class="text-muted small">
                            <!-- File info will be populated here -->
                        </div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // File preview modal functionality
        function openFilePreview(moduleId, filename, originalName, fileSize, uploadedAt) {
            console.log('Opening file preview:', {moduleId, filename, originalName, fileSize, uploadedAt});
            
            // Update modal title
            document.getElementById('modalFileName').textContent = originalName;
            
            // Update file info
            const fileInfo = document.getElementById('fileInfo');
            fileInfo.innerHTML = `
                <strong>Size:</strong> ${(fileSize / 1024).toFixed(1)} KB • 
                <strong>Uploaded:</strong> ${new Date(uploadedAt).toLocaleDateString()}
            `;
            
            // Show loading state
            const content = document.getElementById('filePreviewContent');
            content.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading file preview...</p>
                </div>
            `;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
            modal.show();
            
            // Load file content after a short delay to ensure modal is shown
            setTimeout(() => {
                loadFileContent(moduleId, filename, originalName);
            }, 500);
        }
        
        function loadFileContent(moduleId, filename, originalName) {
            const content = document.getElementById('filePreviewContent');
            const fileExtension = originalName.split('.').pop().toLowerCase();
            
            console.log('Loading file content:', {moduleId, filename, originalName, fileExtension});
            
            // Clear content first
            content.innerHTML = '';
            
            // Check if it's a previewable file type
            const previewableTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'txt', 'mp4', 'avi', 'mov', 'wmv', 'mp3', 'wav'];
            const docxPreviewableTypes = ['docx'];
            const pptxPreviewableTypes = ['pptx'];
            const nonPreviewableTypes = ['doc', 'xlsx', 'xls', 'ppt', 'zip', 'rar', '7z'];
            
            if (docxPreviewableTypes.includes(fileExtension)) {
                // Simple iframe approach for DOCX files
                const iframe = document.createElement('iframe');
                iframe.src = `../preview_docx.php?module_id=${moduleId}&filename=${filename}&original_name=${originalName}`;
                iframe.style.width = '100%';
                iframe.style.height = '700px';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                iframe.style.minHeight = '600px';
                
                // Add iframe immediately
                content.appendChild(iframe);
                
                // Set up error handling with timeout
                let hasLoaded = false;
                
                iframe.onload = function() {
                    hasLoaded = true;
                    console.log('DOCX iframe loaded successfully');
                };
                
                // Handle iframe error after timeout
                setTimeout(() => {
                    if (!hasLoaded) {
                        console.log('DOCX iframe failed to load, showing fallback');
                        showFileInfo(moduleId, filename, originalName, fileExtension);
                    }
                }, 5000);
            } else if (pptxPreviewableTypes.includes(fileExtension)) {
                // Simple iframe approach for PPTX files
                const iframe = document.createElement('iframe');
                iframe.src = `../preview_pptx.php?module_id=${moduleId}&filename=${filename}&original_name=${originalName}`;
                iframe.style.width = '100%';
                iframe.style.height = '700px';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                iframe.style.minHeight = '600px';
                
                // Add iframe immediately
                content.appendChild(iframe);
                
                // Set up error handling with timeout
                let hasLoaded = false;
                
                iframe.onload = function() {
                    hasLoaded = true;
                    console.log('PPTX iframe loaded successfully');
                };
                
                // Handle iframe error after timeout
                setTimeout(() => {
                    if (!hasLoaded) {
                        console.log('PPTX iframe failed to load, showing fallback');
                        showFileInfo(moduleId, filename, originalName, fileExtension);
                    }
                }, 5000);
            } else if (nonPreviewableTypes.includes(fileExtension)) {
                // Show file info for non-previewable files (like XLSX, PPTX, etc.)
                showFileInfo(moduleId, filename, originalName, fileExtension);
            } else if (previewableTypes.includes(fileExtension)) {
                // Use different approaches based on file type
                if (fileExtension === 'pdf') {
                    // For PDFs, use object tag for better rendering
                    const object = document.createElement('object');
                    object.data = `../preview_module_file.php?module_id=${moduleId}&filename=${filename}&original_name=${originalName}`;
                    object.type = 'application/pdf';
                    object.style.width = '100%';
                    object.style.height = '700px';
                    object.style.border = 'none';
                    object.style.borderRadius = '8px';
                    object.style.minHeight = '600px';
                    
                    // Add fallback content
                    const fallback = document.createElement('div');
                    fallback.innerHTML = `
                        <div class="text-center p-4">
                            <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                            <h5>PDF Preview</h5>
                            <p class="text-muted">Your browser doesn't support PDF preview.</p>
                            <a href="../preview_module_file.php?module_id=${moduleId}&filename=${filename}&original_name=${originalName}" 
                               target="_blank" class="btn btn-primary">
                                <i class="fas fa-external-link-alt me-1"></i>Open PDF in New Tab
                            </a>
                        </div>
                    `;
                    object.appendChild(fallback);
                    
                    content.appendChild(object);
                } else {
                    // For other files, use iframe
                    const iframe = document.createElement('iframe');
                    iframe.src = `../preview_module_file.php?module_id=${moduleId}&filename=${filename}&original_name=${originalName}`;
                    iframe.style.width = '100%';
                    iframe.style.height = '700px';
                    iframe.style.border = 'none';
                    iframe.style.borderRadius = '8px';
                    iframe.style.minHeight = '600px';
                    
                    // Add iframe immediately
                    content.appendChild(iframe);
                    
                    // Set up error handling with timeout
                    let hasLoaded = false;
                    
                    iframe.onload = function() {
                        hasLoaded = true;
                        console.log('File iframe loaded successfully');
                    };
                    
                    // Handle iframe error after timeout
                    setTimeout(() => {
                        if (!hasLoaded) {
                            console.log('File iframe failed to load, showing fallback');
                            showFileInfo(moduleId, filename, originalName, fileExtension);
                        }
                    }, 5000);
                }
            } else {
                // Show file info for unknown file types
                showFileInfo(moduleId, filename, originalName, fileExtension);
            }
        }
        
        
        function showFileInfo(moduleId, filename, originalName, fileExtension) {
            const content = document.getElementById('filePreviewContent');
            const nonPreviewableTypes = ['doc', 'xlsx', 'xls', 'pptx', 'ppt', 'zip', 'rar', '7z'];
            
            let iconClass = 'fas fa-file';
            let message = 'This file type cannot be previewed directly in the browser.';
            
            if (nonPreviewableTypes.includes(fileExtension)) {
                iconClass = 'fas fa-file-alt';
                message = `This ${fileExtension.toUpperCase()} file cannot be previewed directly in the browser. Please download it to view with the appropriate application.`;
            }
            
            content.innerHTML = `
                <div class="text-center p-4">
                    <i class="${iconClass} fa-5x text-primary mb-3"></i>
                    <h4>File Preview</h4>
                    <p class="text-muted mb-4">${message}</p>
                    
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">File Information</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>Name:</strong> ${originalName}</li>
                                        <li><strong>Type:</strong> ${fileExtension.toUpperCase()} file</li>
                                        <li><strong>Module ID:</strong> ${moduleId}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="../preview_module_file.php?module_id=${moduleId}&filename=${filename}&original_name=${originalName}" 
                           class="btn btn-primary me-2" target="_blank">
                            <i class="fas fa-external-link-alt me-1"></i>Open in New Tab
                        </a>
                        <button class="btn btn-outline-secondary" onclick="bootstrap.Modal.getInstance(document.getElementById('filePreviewModal')).hide()">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                    </div>
                </div>
            `;
        }
        
        // Test modal function
        function testModal() {
            console.log('Testing modal...');
            const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
            document.getElementById('modalFileName').textContent = 'Test File.pdf';
            document.getElementById('fileInfo').innerHTML = '<strong>Test:</strong> This is a test modal';
            document.getElementById('filePreviewContent').innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                    <h4>Modal is Working!</h4>
                    <p class="text-muted">This is a test to verify the modal functionality.</p>
                </div>
            `;
            modal.show();
        }
        
        // Initialize modal event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Mark initial load as complete to prevent auto-completion on page load
            window.initialLoadComplete = true;
            
            // Animate progress bars on page load
            setTimeout(() => {
                animateProgressBars();
            }, 500);
            
            const modal = document.getElementById('filePreviewModal');
            
            // Reset modal content when hidden
            modal.addEventListener('hidden.bs.modal', function() {
                const content = document.getElementById('filePreviewContent');
                content.innerHTML = `
                    <div class="text-center p-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading file preview...</p>
                    </div>
                `;
            });

            // Pusher real-time video progress updates
            const pusherConfig = <?php echo json_encode(PusherConfig::getConfig()); ?>;
            
            if (pusherConfig && pusherConfig.available) {
                console.log('🎥 Initializing Pusher for student video progress...');
                
                // Initialize Pusher
                const pusher = new Pusher(pusherConfig.app_key, {
                    cluster: pusherConfig.cluster,
                    encrypted: true
                });
                
                // Subscribe to student-specific channel
                const studentChannel = pusher.subscribe(`user-<?php echo $user_id; ?>`);
            } else {
                console.warn('⚠️ Pusher not available for video progress');
            }

        // Animate progress bars on page load
        function animateProgressBars() {
            const progressBars = document.querySelectorAll('.progress-enhanced .progress-bar');
            progressBars.forEach((bar, index) => {
                setTimeout(() => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    bar.style.transition = 'width 0.8s ease-in-out';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                }, index * 200);
            });
        }

        // Auto-complete module when all requirements are met
        function autoCompleteModule() {
                console.log('Checking auto-completion conditions...');
                
                // Double-check that requirements are actually met
                const statusAlert = document.getElementById('requirements-status');
                if (!statusAlert || !statusAlert.classList.contains('alert-success')) {
                    console.log('Requirements not met, skipping auto-completion');
                    return;
                }
                
                // Check if module is already completed
                const completionButton = document.querySelector('.action-button.start');
                if (!completionButton || completionButton.disabled) {
                    console.log('Module already completed or button disabled');
                    return;
                }
                
                // Additional validation: check if there are any pending requirements
                const pendingCards = document.querySelectorAll('.requirement-card.pending, .requirement-card.failed');
                if (pendingCards.length > 0) {
                    console.log('Still have pending requirements, skipping auto-completion');
                    return;
                }
                
                console.log('All conditions met, proceeding with auto-completion');
                
                // Show simple notification
                showCompletionNotification();
                
                // Auto-submit the completion form after a short delay
                setTimeout(() => {
                    const form = document.querySelector('form[method="POST"]');
                    if (form) {
                        console.log('Submitting completion form...');
                        // Create a hidden input for the completion
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'complete_module';
                        input.value = '1';
                        form.appendChild(input);
                        
                        // Submit the form
                        form.submit();
                    } else {
                        console.log('No completion form found');
                    }
                }, 2000); // 2 second delay
            }
            
            // Show simple completion notification
            function showCompletionNotification() {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = 'auto-completion-notification';
                notification.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-check-circle fa-2x text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-white">Module Complete!</h6>
                            <p class="mb-0 small text-white">All requirements met! Auto-completing module...</p>
                        </div>
                    </div>
                `;
                
                // Add to page
                document.body.appendChild(notification);
                
                // Remove after 3 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 3000);
            }
            
            // Update requirement status in real-time
            function updateRequirementStatus(requirements) {
                // Update video requirements
                if (requirements.video_requirements) {
                    requirements.video_requirements.forEach(videoReq => {
                        const videoCards = document.querySelectorAll('.requirement-card.pending, .requirement-card.completed');
                        videoCards.forEach(card => {
                            const titleElement = card.querySelector('.card-title');
                            if (titleElement && titleElement.textContent.includes(videoReq.video_title)) {
                                // Update card class and styling
                                if (videoReq.is_met) {
                                    card.className = 'card requirement-card completed';
                                    const badge = card.querySelector('.badge');
                                    if (badge) {
                                        badge.className = 'badge bg-success';
                                        badge.innerHTML = 'Complete';
                                        badge.style.animation = 'celebration 1s ease-in-out';
                                    }
                                    
                                    // Update status text
                                    const statusText = card.querySelector('.text-muted');
                                    if (statusText) {
                                        statusText.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>Video completed successfully!';
                                    }
                                } else {
                                    card.className = 'card requirement-card pending';
                                    const badge = card.querySelector('.badge');
                                    if (badge) {
                                        badge.className = 'badge bg-warning';
                                        badge.innerHTML = `<i class="fas fa-clock"></i> ${videoReq.current_completion}%`;
                                    }
                                    
                                    // Update status text
                                    const statusText = card.querySelector('.text-muted');
                                    if (statusText) {
                                        statusText.innerHTML = `<i class="fas fa-clock text-warning me-1"></i>${100 - videoReq.current_completion}% remaining`;
                                    }
                                }
                                
                                // Update progress bar
                                const progressBar = card.querySelector('.progress-bar');
                                if (progressBar) {
                                    const newWidth = Math.min(videoReq.current_completion, 100);
                                    progressBar.style.width = `${newWidth}%`;
                                    progressBar.className = `progress-bar ${videoReq.is_met ? 'bg-success' : 'bg-warning'}`;
                                    
                                    // Add animation class for smooth transition
                                    progressBar.classList.add('progress-bar-animated');
                                    
                                    // Remove animation class after transition
                                    setTimeout(() => {
                                        progressBar.classList.remove('progress-bar-animated');
                                    }, 600);
                                }
                            }
                        });
                    });
                }
                
                // Update assessment requirements
                if (requirements.assessment_requirements) {
                    requirements.assessment_requirements.forEach(assessmentReq => {
                        const assessmentCards = document.querySelectorAll('.requirement-card.failed, .requirement-card.completed');
                        assessmentCards.forEach(card => {
                            const titleElement = card.querySelector('.card-title');
                            if (titleElement && titleElement.textContent.includes(assessmentReq.assessment_title)) {
                                // Update card class and styling
                                if (assessmentReq.is_met) {
                                    card.className = 'card requirement-card completed';
                                    const badge = card.querySelector('.badge');
                                    if (badge) {
                                        badge.className = 'badge bg-success';
                                        badge.innerHTML = 'Passed';
                                        badge.style.animation = 'celebration 1s ease-in-out';
                                    }
                                    
                                    // Update status text
                                    const statusText = card.querySelector('.text-muted');
                                    if (statusText) {
                                        statusText.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>Assessment passed successfully!';
                                    }
                                } else {
                                    card.className = 'card requirement-card failed';
                                    const badge = card.querySelector('.badge');
                                    if (badge) {
                                        badge.className = 'badge bg-danger';
                                        badge.innerHTML = `<i class="fas fa-times"></i> ${assessmentReq.current_score}%`;
                                    }
                                    
                                    // Update status text
                                    const statusText = card.querySelector('.text-muted');
                                    if (statusText) {
                                        statusText.innerHTML = `<i class="fas fa-exclamation-triangle text-danger me-1"></i>Need ${assessmentReq.required_score - assessmentReq.current_score}% more to pass`;
                                    }
                                }
                                
                                // Update progress bar
                                const progressBar = card.querySelector('.progress-bar');
                                if (progressBar) {
                                    const newWidth = assessmentReq.progress_percentage || 0;
                                    progressBar.style.width = `${newWidth}%`;
                                    progressBar.className = `progress-bar ${assessmentReq.is_met ? 'bg-success' : 'bg-danger'}`;
                                    
                                    // Add animation class for smooth transition
                                    progressBar.classList.add('progress-bar-animated');
                                    
                                    // Remove animation class after transition
                                    setTimeout(() => {
                                        progressBar.classList.remove('progress-bar-animated');
                                    }, 600);
                                }
                            }
                        });
                    });
                }
                
                // Update overall status alert
                const statusAlert = document.getElementById('requirements-status');
                if (statusAlert) {
                    if (requirements.can_complete) {
                        statusAlert.className = 'alert alert-success border-0 shadow-lg';
                        statusAlert.style.background = 'linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%)';
                        statusAlert.style.borderLeft = '4px solid #28a745 !important';
                        statusAlert.innerHTML = `
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="me-3">
                                    <i class="fas fa-check-circle fa-2x text-success" style="animation: pulse 2s infinite;"></i>
                                </div>
                                <div>
                                    <h5 class="alert-heading mb-1 text-success">
                                        <strong>🎉 All Requirements Met!</strong>
                                    </h5>
                                    <p class="mb-0 text-success">
                                        Congratulations! You've completed all videos and assessments. 
                                        <span class="fw-bold">This module will be automatically marked as complete.</span>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="progress" style="height: 8px; border-radius: 4px;">
                                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 100%">
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        statusAlert.className = 'alert alert-warning border-0 shadow-lg';
                        statusAlert.style.background = 'linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%)';
                        statusAlert.style.borderLeft = '4px solid #ffc107 !important';
                        
                        // Calculate progress
                        const totalRequirements = (requirements.video_requirements?.length || 0) + (requirements.assessment_requirements?.length || 0);
                        const metRequirements = (requirements.video_requirements?.filter(r => r.is_met).length || 0) + (requirements.assessment_requirements?.filter(r => r.is_met).length || 0);
                        const progressPercentage = totalRequirements > 0 ? Math.round((metRequirements / totalRequirements) * 100) : 0;
                        
                        statusAlert.innerHTML = `
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="me-3">
                                    <i class="fas fa-exclamation-triangle fa-2x text-warning" style="animation: bounce 2s infinite;"></i>
                                </div>
                                <div>
                                    <h5 class="alert-heading mb-1 text-warning">
                                        <strong>⚠️ Requirements Not Met</strong>
                                    </h5>
                                    <p class="mb-0 text-warning">
                                        Please complete all videos and assessments before this module can be marked as complete.
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="progress" style="height: 8px; border-radius: 4px;">
                                    <div class="progress-bar bg-warning progress-bar-striped" 
                                         role="progressbar" style="width: ${progressPercentage}%">
                                    </div>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    ${metRequirements} of ${totalRequirements} requirements completed (${progressPercentage}%)
                                </small>
                            </div>
                        `;
                    }
                }
                
                // Update completion button
                const completionButton = document.querySelector('.action-button.start, .action-button.view-only');
                if (completionButton) {
                    if (requirements.can_complete) {
                        completionButton.className = 'action-button start';
                        completionButton.innerHTML = '<i class="fas fa-check"></i><span>Mark as Complete</span>';
                        completionButton.disabled = false;
                    } else {
                        completionButton.className = 'action-button view-only';
                        completionButton.innerHTML = '<i class="fas fa-lock"></i><span>Requirements Not Met</span>';
                        completionButton.disabled = true;
                    }
                }
            }

            // Real-time progress updates
            function updateModuleProgress() {
                fetch(`../ajax_get_module_progress.php?module_id=<?php echo $module_id; ?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update video progress
                            document.getElementById('video-progress-count').textContent = `${data.data.watched_videos} / ${data.data.total_videos}`;
                            document.getElementById('video-progress-bar').style.width = `${data.data.video_progress_percentage}%`;
                            
                            // Update assessment progress
                            document.getElementById('assessment-progress-count').textContent = `${data.data.completed_assessments} / ${data.data.total_assessments}`;
                            document.getElementById('assessment-progress-bar').style.width = `${data.data.assessment_progress_percentage}%`;
                            
                            // Update points earned with detailed info
                            document.getElementById('points-earned').textContent = parseFloat(data.data.total_assessment_points).toFixed(1);
                            document.getElementById('points-progress-bar').style.width = `${data.data.assessment_progress_percentage}%`;
                            
                            // Update attempts info
                            const attemptsInfo = document.getElementById('attempts-info');
                            if (attemptsInfo) {
                                attemptsInfo.textContent = `${data.data.successful_attempts} of ${data.data.total_attempts} attempts successful (${data.data.success_rate}%)`;
                            }
                            
                            // Update watch time
                            const watchTime = new Date(data.data.total_watch_time * 1000).toISOString().substr(11, 5);
                            document.getElementById('watch-time').textContent = watchTime;
                            document.getElementById('time-progress-bar').style.width = `${data.data.video_progress_percentage}%`;
                            
                            // Update main progress circle
                            const progressCircle = document.querySelector('.progress-circle');
                            const progressText = document.querySelector('.progress-text');
                            if (progressCircle && progressText) {
                                progressCircle.style.background = `conic-gradient(
                                    var(--primary-color) 0deg, 
                                    var(--primary-color) ${data.data.video_progress_percentage * 3.6}deg, 
                                    var(--gray-200) ${data.data.video_progress_percentage * 3.6}deg, 
                                    var(--gray-200) 360deg
                                )`;
                                progressText.textContent = `${data.data.video_progress_percentage}%`;
                            }
                            
                            // Update video cards with new progress data
                            data.data.videos.forEach(video => {
                                const videoCard = document.querySelector(`[data-video-id="${video.id}"]`);
                                if (videoCard) {
                                    // Update completion percentage
                                    const progressElement = videoCard.querySelector('.text-warning');
                                    if (progressElement) {
                                        progressElement.textContent = `${video.completion_percentage}%`;
                                    }
                                    
                                    // Update watch status
                                    if (video.is_watched && !videoCard.classList.contains('watched')) {
                                        videoCard.classList.add('watched');
                                        
                                        // Update badge
                                        const badgeElement = videoCard.querySelector('.badge');
                                        if (badgeElement) {
                                            badgeElement.className = 'badge bg-success';
                                            badgeElement.innerHTML = 'Watched';
                                        }
                                        
                                        // Update action button
                                        const actionButton = videoCard.querySelector('.action-button');
                                        if (actionButton) {
                                            actionButton.className = 'action-button completed';
                                            actionButton.innerHTML = '<i class="fas fa-check"></i><span>Completed</span>';
                                        }
                                    }
                                }
                            });
                            
                            // Update assessment cards with new progress data
                            data.data.assessments.forEach(assessment => {
                                const assessmentCards = document.querySelectorAll('.assessment-card');
                                assessmentCards.forEach(card => {
                                    const titleElement = card.querySelector('.card-title');
                                    if (titleElement && titleElement.textContent.includes(assessment.assessment_title)) {
                                        // Update completion status
                                        if (assessment.best_score >= (assessment.passing_rate || 70) && !card.classList.contains('completed')) {
                                            card.classList.add('completed');
                                            
                                            // Update badge
                                            const badgeElement = card.querySelector('.badge');
                                            if (badgeElement && badgeElement.textContent.includes('Locked')) {
                                                badgeElement.className = 'badge bg-success';
                                                badgeElement.innerHTML = 'Passed';
                                            }
                                        }
                                    }
                                });
                            });
                            
                            // Update requirement status
                            if (data.data.requirements) {
                                updateRequirementStatus(data.data.requirements);
                                
                                // Only auto-complete if requirements are met AND this is a real-time update
                                // Don't auto-complete on initial page load
                                if (data.data.requirements.can_complete && !window.initialLoadComplete) {
                                    // Add a small delay to ensure UI is updated
                                    setTimeout(() => {
                                        autoCompleteModule();
                                    }, 1000);
                                }
                            }
                            
                            console.log('✅ Module progress updated successfully');
                        }
                    })
                    .catch(error => {
                        console.error('Error updating module progress:', error);
                    });
            }

            // Update progress every 30 seconds
            setInterval(updateModuleProgress, 30000);
            
            // Also update when page becomes visible (user switches back to tab)
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    updateModuleProgress();
                }
            });
            
            // View Toggle Functionality
            const toggleButtons = document.querySelectorAll('.view-toggle-btn');
            
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const view = this.getAttribute('data-view');
                    const section = this.getAttribute('data-section') || 'video';
                    
                    // Remove active class from buttons in the same section
                    const sectionButtons = document.querySelectorAll(`[data-section="${section}"]`);
                    sectionButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Toggle views based on section
                    if (section === 'video') {
                        const listView = document.getElementById('video-list-view');
                        const cardView = document.getElementById('video-card-view');
                        
                        if (view === 'list') {
                            listView.classList.add('active');
                            cardView.classList.remove('active');
                        } else if (view === 'cards') {
                            listView.classList.remove('active');
                            cardView.classList.add('active');
                        }
                    } else if (section === 'assessment') {
                        const listView = document.getElementById('assessment-list-view');
                        const cardView = document.getElementById('assessment-card-view');
                        
                        if (view === 'list') {
                            listView.classList.add('active');
                            cardView.classList.remove('active');
                        } else if (view === 'cards') {
                            listView.classList.remove('active');
                            cardView.classList.add('active');
                        }
                    }
                });
            });
        });
        
    </script>
</body>
</html> 