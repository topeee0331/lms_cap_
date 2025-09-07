<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
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

// Get videos and assessments from module data
$videos = $module['videos'] ?? [];
$assessments = $module['assessments'] ?? [];



// Remove duplicate assessments based on ID
$unique_assessments = [];
$seen_ids = [];
foreach ($assessments as $assessment) {
    if (!in_array($assessment['id'], $seen_ids)) {
        $unique_assessments[] = $assessment;
        $seen_ids[] = $assessment['id'];
    }
}
$assessments = $unique_assessments;

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
foreach ($videos as &$video) {
    $video['is_watched'] = isset($video_progress[$video['id']]) && $video_progress[$video['id']]['is_watched'] == 1;
    $video['watch_duration'] = isset($video_progress[$video['id']]) ? ($video_progress[$video['id']]['watch_duration'] ?? 0) : 0;
    $video['completion_percentage'] = isset($video_progress[$video['id']]) ? ($video_progress[$video['id']]['completion_percentage'] ?? 0) : 0;
}

// Sort videos by video_order to ensure proper display order
usort($videos, function($a, $b) {
    $order_a = $a['video_order'] ?? 999;
    $order_b = $b['video_order'] ?? 999;
    return $order_a - $order_b;
});


// Add comprehensive assessment attempt data
foreach ($assessments as &$assessment) {
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
    
    $assessment['attempt_count'] = $attempt_data['attempt_count'] ?? 0;
    $assessment['best_score'] = $attempt_data['best_score'] ?? null;
    $assessment['average_score'] = $attempt_data['average_score'] ?? null;
    $assessment['worst_score'] = $attempt_data['worst_score'] ?? null;
    $assessment['passed_attempts'] = $attempt_data['passed_attempts'] ?? 0;
    $assessment['has_ever_passed'] = $attempt_data['has_ever_passed'] ?? 0;
    $assessment['last_attempt_date'] = $attempt_data['last_attempt_date'] ?? null;
    $assessment['total_time_spent'] = $attempt_data['total_time_spent'] ?? 0;
    $assessment['pass_rate'] = $attempt_data['attempt_count'] > 0 ? 
        round(($attempt_data['passed_attempts'] / $attempt_data['attempt_count']) * 100, 1) : 0;
}

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

// Handle module completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_module'])) {
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
    
    $_SESSION['success'] = "Module marked as completed!";
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

// Calculate video progress
$total_videos = count($videos);
$watched_videos = 0;
$total_watch_time = 0;
$total_required_time = 0;

foreach ($videos as $video) {
    if ($video['is_watched']) {
        $watched_videos++;
        $total_watch_time += $video['watch_duration'] ?? 0;
    }
    $total_required_time += ($video['min_watch_time'] ?? 5) * 60; // Convert minutes to seconds
}

$video_progress_percentage = $total_videos > 0 ? round(($watched_videos / $total_videos) * 100) : 0;

// Determine unlocked status for each assessment by order and passing scores
$unlocked = [];
$best_scores = [];

// First, get assessment data from the database to have complete information
$assessment_ids = array_column($assessments, 'id');
if (!empty($assessment_ids)) {
    $placeholders = str_repeat('?,', count($assessment_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT id, assessment_title, prerequisite_assessment_id, passing_rate, assessment_order
        FROM assessments 
        WHERE id IN ($placeholders)
        ORDER BY assessment_order ASC
    ");
    $stmt->execute($assessment_ids);
    $db_assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a lookup array for database assessment data
    $db_assessment_lookup = [];
    foreach ($db_assessments as $db_assessment) {
        $db_assessment_lookup[$db_assessment['id']] = $db_assessment;
    }
    
    // Merge database data with module assessment data
    foreach ($assessments as &$assessment) {
        $assessment_id = $assessment['id'];
        if (isset($db_assessment_lookup[$assessment_id])) {
            $db_data = $db_assessment_lookup[$assessment_id];
            $assessment['prerequisite_assessment_id'] = $db_data['prerequisite_assessment_id'];
            $assessment['passing_rate'] = $db_data['passing_rate'] ?? $assessment['passing_rate'] ?? 70;
            $assessment['assessment_order'] = $db_data['assessment_order'] ?? 1;
        }
    }
}

// Sort assessments by assessment_order (manual order set by teacher)
usort($assessments, function($a, $b) {
    $order_a = $a['assessment_order'] ?? 999;
    $order_b = $b['assessment_order'] ?? 999;
    
    // Pure manual ordering - no date fallback
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
        
        // Find the assessment with the previous order
        foreach ($assessments as $prev_assessment) {
            $prev_order = $prev_assessment['assessment_order'] ?? 1;
            if ($prev_order == $previous_order) {
                $prev_best_score = $prev_assessment['best_score'] ?? 0;
                $prev_passing_rate = $prev_assessment['passing_rate'] ?? 70;
                $previous_assessment_completed = ($prev_best_score >= $prev_passing_rate);
                break;
            }
        }
        
        $unlocked[$assessment_id] = $previous_assessment_completed;
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
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --success-color: #10b981;
            --success-light: #d1fae5;
            --danger-color: #ef4444;
            --danger-light: #fee2e2;
            --warning-color: #f59e0b;
            --warning-light: #fef3c7;
            --info-color: #3b82f6;
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
        .module-header {
            color: var(--gray-800);
            padding: 4rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border-radius: var(--border-radius-lg);
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
            opacity: 0.1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .module-header-bg i {
            font-size: 15rem;
            color: var(--primary-color);
            opacity: 0.3;
        }
        
        .module-header-content {
            position: relative;
            z-index: 2;
        }
        
        .module-title-text {
            font-family: 'Inter', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-800);
            text-align: center;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Video and Assessment Cards */
        .video-card, .assessment-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            overflow: hidden;
        }

        .video-card:hover, .assessment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .video-card.watched {
            border-left: 4px solid var(--success-color);
            background: linear-gradient(135deg, var(--success-light) 0%, rgba(255, 255, 255, 0.95) 100%);
        }

        .assessment-card.completed {
            border-left: 4px solid var(--success-color);
            background: linear-gradient(135deg, var(--success-light) 0%, rgba(255, 255, 255, 0.95) 100%);
        }

        .assessment-card.locked {
            opacity: 0.7;
            background: linear-gradient(135deg, var(--gray-100) 0%, rgba(255, 255, 255, 0.95) 100%);
        }

        /* Progress Circle */
        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(
                var(--success-color) 0deg, 
                var(--success-color) <?php echo $video_progress_percentage * 3.6; ?>deg, 
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
            color: var(--success-color);
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
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%) !important;
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
            background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%) !important;
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
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            box-shadow: var(--shadow-md);
        }

        .btn-success:hover {
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
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
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
            background: var(--success-color);
            border-color: var(--success-color);
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
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            text-decoration: none;
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .action-button.retake {
            background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .action-button.retake:hover {
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4);
        }

        .action-button.start {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .action-button.start:hover {
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .action-button.completed {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
            cursor: default;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .action-button.completed:hover {
            transform: none;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .action-button.view-only {
            background: linear-gradient(135deg, var(--gray-500) 0%, var(--gray-600) 100%);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }

        .action-button.view-only:hover {
            box-shadow: 0 8px 20px rgba(107, 114, 128, 0.4);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .module-title-text {
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
                <div class="module-header <?php echo $theme['bg']; ?>">
                    <div class="module-header-bg">
                        <i class="<?php echo $theme['icon']; ?>"></i>
                    </div>
                    <div class="module-header-content text-center">
                        <h1 class="module-title-text">
                            <?php echo htmlspecialchars($module['module_title'] ?? 'N/A'); ?>
                        </h1>
                        <h2 class="h1 mb-2"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></h2>
                        <p class="lead mb-1">
                            <strong><?php echo htmlspecialchars($course['course_code'] ?? ''); ?></strong> • 
                            <?php echo htmlspecialchars($course['year_level'] ?? 'N/A'); ?> • 
                            <?php echo htmlspecialchars($course['credits'] ?? 0); ?> Credits
                        </p>
                        <p class="lead mb-1">
                            by <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                        </p>
                        <p class="mb-0">
                            <small class="text-white-50">
                                <?php echo htmlspecialchars($course['academic_year'] ?? ''); ?> • 
                                <?php echo htmlspecialchars($course['semester_name'] ?? ''); ?>
                                <?php if ($course['start_date'] && $course['end_date']): ?>
                                    • <?php echo date('M j', strtotime($course['start_date'])); ?> - <?php echo date('M j, Y', strtotime($course['end_date'])); ?>
                                <?php endif; ?>
                            </small>
                        </p>
                    </div>
                </div>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2"><?php echo htmlspecialchars($module['module_title'] ?? ''); ?></h1>
                        <p class="text-muted"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?> - by <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Course
                        </a>
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

                <!-- Module Overview -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Module Description</h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($module['module_description'] ?? 'No description available.')); ?></p>

                                <!-- Module Files Section -->
                                <?php if (isset($module['file']) && !empty($module['file'])): ?>
                                    <div class="mt-3">
                                        <h6 class="text-primary">
                                            <i class="fas fa-paperclip me-2"></i>Module Files
                                        </h6>
                                        <div class="d-flex align-items-center p-2 bg-light rounded hover-shadow" 
                                             style="transition: all 0.3s ease; cursor: pointer;"
                                             onclick="openFilePreview('<?php echo $module['id']; ?>', '<?php echo urlencode($module['file']['filename']); ?>', '<?php echo urlencode($module['file']['original_name']); ?>', '<?php echo $module['file']['file_size']; ?>', '<?php echo $module['file']['uploaded_at']; ?>')"
                                             onmouseover="this.style.backgroundColor='#f8f9fa'" 
                                             onmouseout="this.style.backgroundColor='#f8f9fa'">
                                            <i class="fas fa-file me-2 text-primary"></i>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($module['file']['original_name']); ?></div>
                                                <small class="text-muted">
                                                    <?php echo round($module['file']['file_size'] / 1024, 1); ?> KB • 
                                                    Uploaded <?php echo date('M j, Y', strtotime($module['file']['uploaded_at'])); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-eye me-1"></i>Click to Preview
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Module Statistics -->
                                <div class="row mt-3">
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <i class="fas fa-video"></i> <?php echo count($videos); ?> videos
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <i class="fas fa-question-circle"></i> <?php echo count($assessments); ?> assessments
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> Module <?php echo $module['module_order'] ?? 1; ?>
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> 
                                            <?php 
                                            $total_video_time = 0;
                                            foreach ($videos as $video) {
                                                $total_video_time += $video['min_watch_time'] ?? 0;
                                            }
                                            echo $total_video_time > 0 ? $total_video_time . ' min' : 'N/A';
                                            ?>
                                        </small>
                                </div>
                            </div>

                                <!-- Module Prerequisites -->
                                <?php if (isset($module['unlock_score']) && $module['unlock_score'] > 0): ?>
                                    <div class="mt-3">
                                        <h6 class="text-warning">
                                            <i class="fas fa-lock me-2"></i>Prerequisites
                                        </h6>
                                        <p class="text-muted mb-0">
                                            <small>This module requires a minimum score of <?php echo $module['unlock_score']; ?>% from previous assessments to unlock.</small>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="position-relative">
                                    <div class="progress-circle"></div>
                                    <div class="progress-text"><?php echo $video_progress_percentage; ?>%</div>
                                </div>
                                <h5 class="card-title mt-3">Video Progress</h5>
                                <p class="card-text"><?php echo $watched_videos; ?> of <?php echo $total_videos; ?> videos watched</p>
                                
                                <?php if ($total_watch_time > 0): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Total Watch Time: </small>
                                        <strong class="text-info"><?php echo gmdate("H:i:s", $total_watch_time); ?></strong>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Additional Progress Stats -->
                                <div class="mt-3">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <small class="text-muted">Course Progress</small>
                                            <div class="fw-bold text-primary">
                                                <?php echo number_format($student_progress['progress_percentage'] ?? 0, 1); ?>%
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Avg Score</small>
                                            <div class="fw-bold text-success">
                                                <?php echo $student_progress['average_course_score'] ? number_format($student_progress['average_course_score'], 1) . '%' : 'N/A'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($student_progress['enrolled_at']): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                Enrolled: <?php echo date('M j, Y', strtotime($student_progress['enrolled_at'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($student_progress['last_accessed']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                Last accessed: <?php echo date('M j, Y', strtotime($student_progress['last_accessed'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Videos Section -->
                <?php if (!empty($videos)): ?>
                    <div class="mb-4">
                        <h3>Videos</h3>
                        <div class="row">
                            <?php foreach ($videos as $video): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card video-card <?php echo $video['is_watched'] ? 'watched' : ''; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title"><?php echo htmlspecialchars($video['video_title'] ?? ''); ?></h5>
                                                <?php if ($video['is_watched']): ?>
                                        <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Watched
                                        </span>
                                                <?php endif; ?>
                                    </div>
                                            
                                            <p class="card-text"><?php echo htmlspecialchars(substr($video['video_description'] ?? 'No description available.', 0, 100)); ?><?php echo strlen($video['video_description'] ?? '') > 100 ? '...' : ''; ?></p>
                                            
                                            <!-- Video Details -->
                                            <div class="row text-center mb-2">
                                                <div class="col-6">
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock"></i><br>
                                                        <?php echo $video['min_watch_time'] ?? 5; ?> min
                                                    </small>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">
                                                        <i class="fas fa-sort-numeric-up"></i><br>
                                                        Order <?php echo $video['video_order'] ?? 1; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            
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
                                                            <i class="fas fa-check"></i> Completed
                                                        </span>
                                                        <?php if (isset($video_progress[$video['id']]['watch_duration'])): ?>
                                                            <div class="small text-muted mt-1">
                                                                Watched for <?php echo gmdate("H:i:s", $video_progress[$video['id']]['watch_duration']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center">
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-clock"></i> Not Watched
                                                        </span>
                                                        <div class="small text-muted mt-1">
                                                            Min: <?php echo $video['min_watch_time'] ?? 5; ?> min
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                        </div>
                    </div>
                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Assessments Section -->
                <?php if (!empty($assessments)): ?>
                    <div class="mb-4">
                        <h3>Assessments</h3>
                        <div class="row">
                            <?php foreach ($assessments as $assessment): ?>
                                <?php $is_locked = !$unlocked[$assessment['id']]; ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card assessment-card <?php echo $assessment['best_score'] >= ($assessment['passing_rate'] ?? 70) ? 'completed' : ''; ?> <?php echo $is_locked ? 'locked' : ''; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title">
                                                    <?php echo htmlspecialchars($assessment['assessment_title'] ?? ''); ?>
                                                    <?php if ($unlocked[$assessment['id']] && !$is_locked && ($assessment['assessment_order'] ?? 1) == 1): ?>
                                                        <span class="badge bg-primary ms-2">
                                                            <i class="fas fa-star"></i> First
                                                        </span>
                                                    <?php endif; ?>
                                </h5>
                                                <?php if ($assessment['best_score'] >= ($assessment['passing_rate'] ?? 70)): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Passed
                                                    </span>
                                                <?php elseif ($is_locked): ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-lock"></i> Locked
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="card-text"><?php echo htmlspecialchars(substr($assessment['description'] ?? 'No description available.', 0, 100)); ?><?php echo strlen($assessment['description'] ?? '') > 100 ? '...' : ''; ?></p>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-4">
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock"></i><br>
                                                        <?php echo $assessment['time_limit'] ?? 0; ?> min
                                                    </small>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">
                                                        <i class="fas fa-question-circle"></i><br>
                                                        <?php echo $assessment['num_questions'] ?? 0; ?> questions
                                                    </small>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">
                                                        <i class="fas fa-signal"></i><br>
                                                        <?php echo ucfirst($assessment['difficulty'] ?? 'medium'); ?>
                                                    </small>
                                                </div>
                            </div>
                                            
                                            <!-- Assessment Performance Stats -->
                                            <?php if ($assessment['attempt_count'] > 0): ?>
                                                <div class="mb-2">
                                                    <div class="row text-center">
                                                        <div class="col-4">
                                                            <small class="text-muted">Best Score</small><br>
                                                            <strong class="text-success"><?php echo number_format($assessment['best_score'], 1); ?>%</strong>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted">Avg Score</small><br>
                                                            <strong class="text-info"><?php echo number_format($assessment['average_score'], 1); ?>%</strong>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted">Attempts</small><br>
                                                            <strong class="text-primary"><?php echo $assessment['attempt_count']; ?></strong>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($assessment['last_attempt_date']): ?>
                                                        <div class="mt-1">
                                                            <small class="text-muted">
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
                                                        <span>Complete Assessment <?php echo ($assessment['assessment_order'] ?? 1) - 1; ?> first</span>
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
                                <p class="card-text">Mark this module as completed when you're done with all videos and assessments.</p>
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="complete_module" class="action-button start">
                                        <i class="fas fa-check"></i>
                                        <span>Mark as Complete</span>
                                    </button>
                                </form>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
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
            const nonPreviewableTypes = ['doc', 'xlsx', 'xls', 'pptx', 'ppt', 'zip', 'rar', '7z'];
            
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
        });
    </script>
</body>
</html> 