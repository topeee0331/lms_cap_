<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/score_calculator.php';
require_once '../includes/assessment_pass_tracker.php';
require_once '../includes/semester_security.php';

$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include header for Bootstrap and navigation
require_once '../includes/header.php';

// Get all course IDs the student is enrolled in
$enrolled_stmt = $pdo->prepare('SELECT course_id FROM course_enrollments WHERE student_id = ? AND status = "active"');
$enrolled_stmt->execute([$user_id]);
$all_course_ids = $enrolled_stmt->fetchAll(PDO::FETCH_COLUMN);

$assessments = [];
if (!empty($all_course_ids)) {
    $in = str_repeat('?,', count($all_course_ids) - 1) . '?';
    $params = $all_course_ids;
    $stmt = $pdo->prepare("
        SELECT a.*, c.id as course_id, c.academic_period_id, 
               ap.is_active as academic_period_active,
               c.course_name, u.first_name, u.last_name,
               a.is_locked, a.lock_type, a.prerequisite_assessment_id, 
               a.prerequisite_score, a.prerequisite_video_count, a.unlock_date, a.lock_message
        FROM assessments a
        JOIN courses c ON a.course_id = c.id
        JOIN users u ON c.teacher_id = u.id
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        WHERE c.id IN ($in)
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    $assessments = $stmt->fetchAll();

    // Extract module titles from JSON and add academic period status
    foreach ($assessments as &$assessment) {
        $module_title = 'Module Assessment';
        if ($assessment['course_id']) {
            $stmt = $pdo->prepare("SELECT modules FROM courses WHERE id = ?");
            $stmt->execute([$assessment['course_id']]);
            $course_data = $stmt->fetch();
            if ($course_data && $course_data['modules']) {
                $modules_data = json_decode($course_data['modules'], true);
                if ($modules_data) {
                    // Find the module that contains this assessment
                    foreach ($modules_data as $module) {
                        if (isset($module['assessments']) && is_array($module['assessments'])) {
                            foreach ($module['assessments'] as $module_assessment) {
                                if ($module_assessment['id'] === $assessment['id']) {
                                    $module_title = $module['module_title'] ?? 'Module Assessment';
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
        $assessment['module_title'] = $module_title;
        $assessment['academic_year_active'] = $assessment['academic_period_active'];
        $assessment['semester_active'] = $assessment['academic_period_active'];
    }

    // Enrich each assessment with attempt_count and best_score for the current student
    foreach ($assessments as &$assessment) {
        $stmt2 = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM assessment_attempts WHERE student_id = ? AND assessment_id = ? AND status = 'completed'");
        $stmt2->execute([$user_id, $assessment['id']]);
        $row = $stmt2->fetch();
        $assessment['attempt_count'] = $row['attempt_count'] ?? 0;
        
        // Calculate average score using the helper function
        $assessment['average_score'] = calculateAssessmentAverageScore($pdo, $user_id, $assessment['id']);

        // Add question_count
        $stmt3 = $pdo->prepare("SELECT COUNT(*) as question_count FROM questions WHERE assessment_id = ?");
        $stmt3->execute([$assessment['id']]);
        $qrow = $stmt3->fetch();
        $assessment['question_count'] = $qrow['question_count'] ?? 0;
        
        // Add attempt limit info
        $assessment['attempt_limit'] = $assessment['attempt_limit'] ?? 3; // Default to 3 if not set
        $assessment['can_retake'] = $assessment['attempt_limit'] == 0 || $assessment['attempt_count'] < $assessment['attempt_limit'];
        
        // Add pass status information
        $pass_stats = getAssessmentPassStats($pdo, $user_id, $assessment['id']);
        $assessment['has_ever_passed'] = $pass_stats['has_ever_passed'];
        $assessment['pass_rate'] = $pass_stats['pass_rate'];
        $assessment['best_score'] = $pass_stats['best_score'];
        
        // Ensure we have a passing rate value
        if (!isset($assessment['passing_rate']) || $assessment['passing_rate'] === null) {
            $stmt4 = $pdo->prepare("SELECT passing_rate FROM assessments WHERE id = ?");
            $stmt4->execute([$assessment['id']]);
            $pr_row = $stmt4->fetch();
            $assessment['passing_rate'] = $pr_row['passing_rate'] ?? 70;
        }
        
        // Check assessment accessibility based on locking conditions
        $assessment['is_accessible'] = true;
        $assessment['lock_reason'] = '';
        $assessment['lock_details'] = '';
        
        // Check if assessment is locked
        if ($assessment['is_locked']) {
            $assessment['is_accessible'] = false;
            
            switch ($assessment['lock_type']) {
                case 'prerequisite_score':
                    if ($assessment['prerequisite_assessment_id']) {
                        // Check if student has taken the prerequisite assessment
                        $prereq_stmt = $pdo->prepare("
                            SELECT aa.score, a.passing_rate 
                            FROM assessment_attempts aa 
                            JOIN assessments a ON aa.assessment_id = a.id 
                            WHERE aa.student_id = ? AND aa.assessment_id = ? AND aa.status = 'completed' 
                            ORDER BY aa.score DESC LIMIT 1
                        ");
                        $prereq_stmt->execute([$user_id, $assessment['prerequisite_assessment_id']]);
                        $prereq_result = $prereq_stmt->fetch();
                        
                        if (!$prereq_result) {
                            $assessment['lock_reason'] = 'Prerequisite assessment not completed';
                            $assessment['lock_details'] = 'You must complete the prerequisite assessment first.';
                        } else {
                            $score_percentage = ($prereq_result['score'] / $prereq_result['passing_rate']) * 100;
                            if ($score_percentage < $assessment['prerequisite_score']) {
                                $assessment['lock_reason'] = 'Prerequisite score not met';
                                $assessment['lock_details'] = "You need at least {$assessment['prerequisite_score']}% on the prerequisite assessment. Your best score: " . round($score_percentage, 1) . "%";
                            } else {
                                $assessment['is_accessible'] = true;
                            }
                        }
                    }
                    break;
                    
                case 'prerequisite_videos':
                    if ($assessment['prerequisite_video_count']) {
                        // Count videos watched by student in this course
                        $video_stmt = $pdo->prepare("
                            SELECT COUNT(DISTINCT cv.id) as watched_videos
                            FROM course_videos cv
                            JOIN video_views vv ON cv.id = vv.video_id
                            WHERE cv.course_id = (SELECT course_id FROM assessments WHERE id = ?) 
                            AND vv.student_id = ? AND vv.watch_duration >= 30
                        ");
                        $video_stmt->execute([$assessment['id'], $user_id]);
                        $video_result = $video_stmt->fetch();
                        $watched_count = $video_result['watched_videos'] ?? 0;
                        
                        if ($watched_count < $assessment['prerequisite_video_count']) {
                            $assessment['lock_reason'] = 'Video requirements not met';
                            $assessment['lock_details'] = "You need to watch {$assessment['prerequisite_video_count']} videos. You have watched {$watched_count} videos.";
                        } else {
                            $assessment['is_accessible'] = true;
                        }
                    }
                    break;
                    
                case 'date_based':
                    if ($assessment['unlock_date']) {
                        $current_time = new DateTime();
                        $unlock_time = new DateTime($assessment['unlock_date']);
                        
                        if ($current_time < $unlock_time) {
                            $assessment['lock_reason'] = 'Assessment not yet available';
                            $assessment['lock_details'] = 'This assessment will be available on ' . $unlock_time->format('M j, Y \a\t g:i A');
                        } else {
                            $assessment['is_accessible'] = true;
                        }
                    }
                    break;
                    
                default: // manual lock
                    $assessment['lock_reason'] = 'Assessment locked by teacher';
                    $assessment['lock_details'] = $assessment['lock_message'] ?: 'This assessment is currently locked by your teacher.';
                    break;
            }
        }
        
        // Check if assessment is active
        if ($assessment['status'] !== 'active') {
            $assessment['is_accessible'] = false;
            $assessment['lock_reason'] = 'Assessment deactivated';
            $assessment['lock_details'] = 'This assessment has been deactivated by your teacher.';
        }
        

    }
    unset($assessment);
}



// Calculate statistics
$total_assessments = count($assessments);
$completed_assessments = 0;
$total_score = 0;
$attempt_count = 0;

foreach ($assessments as $assessment) {
    if ($assessment['attempt_count'] > 0) {
        $completed_assessments++;
        $total_score += $assessment['average_score'];
        $attempt_count += $assessment['attempt_count'];
    }
}

$average_score = $completed_assessments > 0 ? round($total_score / $completed_assessments, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assessments - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2E5E4E;
            --secondary-color: #95a5a6;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #2E5E4E;
            --accent-color: #7DCB80;
            --border-radius: 8px;
            --box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            --transition: all 0.2s ease;
            --border-color: #e9ecef;
            --text-muted: #6c757d;
        }
        
        /* Enhanced Welcome Section */
        .welcome-section {
            background: #2E5E4E;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }
        
        .welcome-title {
            color: white;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .welcome-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 1.1rem;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        .welcome-actions {
            position: relative;
            z-index: 1;
        }
        
        .welcome-actions .btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .welcome-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        /* Decorative elements */
        .welcome-section::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }
        
        .welcome-decoration {
            position: absolute;
            top: 25px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }
        
        .welcome-decoration i {
            font-size: 1.5rem;
            color: rgba(255,255,255,0.8);
        }
        
        /* Auto-close alert animation */
        @keyframes countdown {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        .alert-progress-bar {
            border-radius: 0 0 0.375rem 0.375rem;
        }
        
        /* Top center positioning for attempt limits reminder */
        #attempt-limits-reminder {
            position: fixed !important;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            max-width: 600px;
            width: 90%;
            margin: 0 !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .welcome-section .accent-line {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #7DCB80;
            border-radius: 0 0 20px 20px;
        }
        
        .welcome-section .floating-shapes {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            z-index: 1;
        }
        
        .welcome-section .floating-shapes::before {
            content: '';
            position: absolute;
            top: 50px;
            left: 20px;
            width: 20px;
            height: 20px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        body {
            background-color: #fafafa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--dark-color);
        }

        .assessment-card {
            transition: var(--transition);
            height: 100%;
            border: none;
            border-radius: var(--border-radius);
            overflow: hidden;
            background: white;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            position: relative;
        }

        .assessment-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
        }

        .assessment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }

        .assessment-card .card-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
            border-bottom: 1px solid #e3e6f0;
            padding: 1.25rem;
        }

        .assessment-card .card-body {
            padding: 1.5rem;
        }

        .card-title {
            font-weight: 700;
            color: var(--dark-color);
            font-size: 1.1rem;
            line-height: 1.4;
        }

        .score-badge {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .info-section {
            background: #f8f9fc;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
        }

        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }
        
        /* Lock notice styles */
        .lock-notice {
            margin-top: 0.75rem;
        }
        
        .alert-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 0.375rem;
        }
        
        .locked-assessment {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #6c757d;
        }
        
        .view-only {
            background-color: #e3f2fd;
            border: 1px solid #2196f3;
            color: #1976d2;
        }
        
        .view-only:hover {
            background-color: #bbdefb;
            color: #1565c0;
        }
        
        /* Assessment card lock status indicator */
        .assessment-lock-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 10;
        }
        
        .lock-badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 0.25rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .lock-badge.locked {
            background-color: #dc3545;
            color: white;
        }
        
        .lock-badge.deactivated {
            background-color: #6c757d;
            color: white;
        }
        
        .lock-badge.prerequisite {
            background-color: #fd7e14;
            color: white;
        }
        
        .lock-badge.date-based {
            background-color: #17a2b8;
            color: white;
        }

        .info-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.9rem;
        }

        .info-text {
            flex: 1;
        }

        .info-label {
            font-size: 0.8rem;
            color: var(--secondary-color);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 0.95rem;
            color: var(--dark-color);
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: var(--transition);
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .attempts-clickable {
            transition: all 0.2s ease;
        }

        .attempts-clickable:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%);
        }

        .attempts-clickable:active {
            transform: translateY(0);
        }

        .click-indicator {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            color: var(--info-color);
            font-size: 0.7rem;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }

        .attempts-clickable:hover .click-indicator {
            opacity: 1;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: 1.1rem;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--secondary-color);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .description-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .description-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #856404;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .description-text {
            font-size: 0.9rem;
            color: #856404;
            line-height: 1.5;
            margin: 0;
        }

        .passing-rate-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
            border: 1px solid #c3e6c3;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .passing-rate-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .passing-rate-label {
            font-size: 0.9rem;
            color: #2d5a2d;
            font-weight: 600;
        }

        .passing-rate-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .date-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
        }

        .date-item {
            display: flex;
            align-items: center;
        }

        .date-icon {
            margin-right: 0.5rem;
            color: var(--info-color);
        }

        .status-alert {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-alert i {
            margin-right: 0.5rem;
        }

        .btn-assessment {
            border-radius: 8px;
            font-weight: 600;
            padding: 1rem 1.5rem;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn-assessment::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-assessment:hover::before {
            left: 100%;
        }

        .btn-assessment:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }



        .assessment-details-box {
            background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .status-indicator {
            border-radius: 8px;
            font-size: 0.85rem;
        }

        /* Color variations for different statuses */
        .status-not-attempted {
            border-left-color: var(--secondary-color);
        }

        .status-attempted {
            border-left-color: var(--warning-color);
        }

        .status-completed {
            border-left-color: var(--success-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .date-info {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .assessment-card .card-body {
                padding: 1rem;
            }
        }

        /* 3-column layout adjustments */
        @media (min-width: 992px) {
            .assessment-item {
                margin-bottom: 1.25rem;
            }
        }

        @media (max-width: 991px) and (min-width: 768px) {
            .assessment-item {
                margin-bottom: 1rem;
            }
        }

        /* Animation for card entrance */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .assessment-item {
            animation: slideInUp 0.6s ease-out;
        }

        .assessment-item:nth-child(1) { animation-delay: 0.1s; }
        .assessment-item:nth-child(2) { animation-delay: 0.2s; }
        .assessment-item:nth-child(3) { animation-delay: 0.3s; }
        .assessment-item:nth-child(4) { animation-delay: 0.4s; }
        .assessment-item:nth-child(5) { animation-delay: 0.5s; }
        .assessment-item:nth-child(6) { animation-delay: 0.6s; }

        /* Filter styling */
        .form-select-sm {
            border-radius: 6px;
            border: 1px solid #e3e6f0;
            font-size: 0.875rem;
        }

        .form-select-sm:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
        }

        /* Filter info icon */
        .fa-info-circle {
            cursor: help;
            transition: color 0.2s ease;
        }

        .fa-info-circle:hover {
            color: var(--primary-color) !important;
        }

        /* Assessment status indicators */
        .assessment-item[data-status="completed"] .status-badge {
            background: linear-gradient(135deg, var(--success-color), #17a673);
        }

        .assessment-item[data-status="not-attempted"] .status-badge {
            background: linear-gradient(135deg, var(--secondary-color), #6c757d);
        }

        /* Minimalist Assessment Card Styles */
        .modern-assessment-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
            position: relative;
            border: 1px solid var(--border-color);
        }

        .modern-assessment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            border-color: var(--primary-color);
        }

        .modern-assessment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-color);
            z-index: 1;
        }

        /* Header Section */
        .card-header-section {
            background: white;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .status-indicator {
            position: relative;
            z-index: 2;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.75rem;
            color: white;
            transition: var(--transition);
        }

        .status-badge.completed {
            background: var(--success-color);
        }

        .status-badge.available {
            background: var(--primary-color);
        }

        .status-badge i {
            font-size: 0.9rem;
        }

        /* Score Display */
        .score-display {
            text-align: center;
        }

        .score-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--success-color);
            color: white;
            transition: var(--transition);
        }

        .score-circle.new {
            background: var(--primary-color);
        }

        .score-circle:hover {
            transform: scale(1.02);
        }

        .score-value {
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1;
        }

        .score-label {
            font-size: 0.65rem;
            font-weight: 400;
            opacity: 0.9;
            margin-top: 0.15rem;
        }

        /* Content Section */
        .card-content-section {
            padding: 1rem;
        }

        .title-section {
            margin-bottom: 1rem;
        }

        .assessment-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .course-details {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .course-name, .module-name {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .course-name i, .module-name i {
            color: var(--primary-color);
            font-size: 0.9rem;
            width: 16px;
            text-align: center;
        }

        .module-name i {
            color: var(--info-color);
        }

        .course-name span, .module-name span, .teacher-name span {
            font-weight: 400;
            color: var(--text-muted);
        }

        .teacher-name {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .teacher-name i {
            color: var(--success-color);
            font-size: 0.9rem;
            width: 16px;
            text-align: center;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .stat-box {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 0.5rem;
            text-align: center;
            transition: var(--transition);
        }

        .stat-box:hover {
            border-color: var(--primary-color);
        }

        .stat-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.4rem;
            font-size: 0.8rem;
            color: white;
        }

        .stat-icon.time {
            background: var(--warning-color);
        }

        .stat-icon.questions {
            background: var(--info-color);
        }

        .stat-icon.attempts {
            background: var(--success-color);
        }

        .stat-icon.limit {
            background: var(--warning-color);
        }
        
        .stat-icon.passed {
            background: var(--success-color);
        }
        
        .stat-icon.not-passed {
            background: var(--danger-color);
        }
        
        .stat-box.passed {
            border-color: var(--success-color);
        }
        
        .stat-box.not-passed {
            border-color: var(--danger-color);
        }

        .stat-icon.attempts-remaining {
            background: var(--info-color);
        }

        .attempt-reminder.warning .stat-icon.attempts-remaining {
            background: var(--warning-color);
        }

        .attempt-reminder.warning .stat-number {
            color: var(--warning-color);
            font-weight: 700;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
            line-height: 1;
        }

        .stat-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 400;
            margin-top: 0.2rem;
        }

        /* Description Section */
        .description-section {
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .description-text {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
            font-weight: 400;
        }

        /* Progress Section */
        .progress-section {
            margin-bottom: 1.25rem;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .progress-label {
            font-weight: 500;
            color: var(--dark-color);
            font-size: 0.9rem;
        }

        .progress-value {
            font-weight: 600;
            color: var(--success-color);
            font-size: 1rem;
        }

        .progress-bar-container {
            width: 100%;
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background: var(--success-color);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .progress-bar.passed {
            background: linear-gradient(90deg, var(--success-color), #2ecc71);
        }

        .progress-bar.not-passed {
            background: linear-gradient(90deg, var(--warning-color), #f39c12);
        }

        .progress-description {
            text-align: center;
            margin-top: 0.5rem;
        }

        .progress-description small {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .student-performance-info {
            margin-top: 0.5rem;
            text-align: center;
        }

        .student-performance-info small {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .progress-legend {
            margin-top: 0.5rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .legend-color {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .legend-color.passed {
            background: var(--success-color);
        }

        .legend-color.marker {
            background: var(--warning-color);
        }

        /* Modal Styling */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            border-bottom: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .attempt-count-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 400;
        }

        .assessment-title-modal {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .course-name-modal {
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .assessment-summary {
            padding: 0.75rem;
            background: #f8f9fc;
            border-radius: 6px;
            border: 1px solid #e3e6f0;
        }

        .summary-item {
            display: inline-block;
            font-size: 0.8rem;
        }

        .summary-item i {
            color: var(--info-color);
        }

        .score-source-legend {
            border-top: 1px solid #e3e6f0;
            padding-top: 0.75rem;
        }

        .score-source-legend .legend-item {
            display: inline-block;
            font-size: 0.75rem;
        }

        .attempt-item-modal {
            background: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }

        .attempt-item-modal:hover {
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .attempt-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .attempt-number {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .attempt-date {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .attempt-score {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .attempt-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .attempt-status.passed {
            background: var(--success-color);
            color: white;
        }

        .attempt-status.failed {
            background: var(--danger-color);
            color: white;
        }

        .attempt-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
        }

        .score-source-info {
            color: var(--text-muted);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .score-source-info i {
            font-size: 0.7rem;
        }

        .no-attempts-message {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        .no-attempts-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .student-score-marker {
            position: absolute;
            top: -2px;
            width: 10px;
            height: 10px;
            background: var(--warning-color);
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            transform: translateX(-50%);
        }

        .student-score-marker.passed {
            background: var(--success-color);
            border-color: var(--success-color);
        }

        .student-score-marker.not-passed {
            background: var(--danger-color);
            border-color: var(--danger-color);
        }

        .student-score-marker:hover {
            transform: translateX(-50%) scale(1.2);
            transition: transform 0.2s ease;
        }

        /* Action Section */
        .action-section {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .warning-message {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-muted);
            font-weight: 400;
            font-size: 0.85rem;
        }

        .warning-message i {
            color: var(--warning-color);
            font-size: 0.9rem;
        }

        .action-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .action-button.start {
            background: var(--primary-color);
            color: white;
        }

        .action-button.retake {
            background: var(--success-color);
            color: white;
        }

        .action-button.completed {
            background: #28a745;
            color: white;
            cursor: default;
        }

        .action-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .action-button i {
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .card-header-section {
                flex-direction: column;
                gap: 0.75rem;
                align-items: center;
            }

            .quick-stats {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .assessment-title {
                font-size: 1.1rem;
            }

            .score-circle {
                width: 50px;
                height: 50px;
            }

            .score-value {
                font-size: 0.9rem;
            }

            .stat-box {
                padding: 0.5rem;
            }

            .stat-number {
                font-size: 1rem;
            }
        }

        /* Animation for card entrance */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .assessment-item {
            animation: slideInUp 0.6s ease-out;
        }

        .assessment-item:nth-child(1) { animation-delay: 0.1s; }
        .assessment-item:nth-child(2) { animation-delay: 0.2s; }
        .assessment-item:nth-child(3) { animation-delay: 0.3s; }
        .assessment-item:nth-child(4) { animation-delay: 0.4s; }
        .assessment-item:nth-child(5) { animation-delay: 0.5s; }
        .assessment-item:nth-child(6) { animation-delay: 0.6s; }
        .assessment-item:nth-child(7) { animation-delay: 0.7s; }
        .assessment-item:nth-child(8) { animation-delay: 0.8s; }
        .assessment-item:nth-child(9) { animation-delay: 0.9s; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Removed Sidebar -->
            <!-- Main content -->
            <main class="col-12 px-md-4">
                <!-- Enhanced Welcome Section -->
                <div class="welcome-section">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="welcome-title">My Assessments</h1>
                            <p class="welcome-subtitle">Track your progress and take assessments</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="welcome-actions">
                                <button id="refresh-assessments" class="btn btn-refresh" title="Refresh assessment data">
                                    <i class="fas fa-sync-alt me-2"></i>
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="welcome-decoration">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="floating-shapes"></div>
                    <div class="accent-line"></div>
                </div>

                <!-- Enhanced Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #2E5E4E 0%, #1e7e34 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-clipboard-list fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $total_assessments; ?></h4>
                                <p class="card-text small">Total Assessments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $completed_assessments; ?></h4>
                                <p class="card-text small">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $average_score; ?>%</h4>
                                <p class="card-text small">Average Score</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #212529; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-redo fa-2x mb-2" style="color: rgba(33,37,41,0.8);"></i>
                                <h4 class="card-title mb-1"><?php echo $attempt_count; ?></h4>
                                <p class="card-text small">Total Attempts</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Available Assessments -->
                    <div class="col-12">
                        <div class="card" style="border: none; border-radius: 15px; box-shadow: 0 8px 30px rgba(0,0,0,0.08);">
                            <div class="card-header" style="background: linear-gradient(135deg, #2E5E4E 0%, #1e7e34 100%); color: white; border: none; border-radius: 15px 15px 0 0; padding: 1.5rem;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0" style="font-weight: 600; font-size: 1.3rem;">
                                        <i class="fas fa-clipboard-list me-2"></i>
                                        Available Assessments
                                    </h5>
                                    <div class="d-flex gap-2 align-items-center">
                                        <div class="d-flex align-items-center me-3">
                                            <label for="statusFilter" class="form-label mb-0 me-2 small" style="color: rgba(255,255,255,0.9);">Filter by:</label>
                                        <select class="form-select form-select-sm" id="statusFilter" style="width: auto; background: rgba(255,255,255,0.9); border: none; border-radius: 8px;">
                                            <option value="all">All Status</option>
                                            <option value="not-attempted">Not Attempted</option>
                                                <option value="completed">Completed (Any Score)</option>
                                        </select>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <label for="courseFilter" class="form-label mb-0 me-2 small" style="color: rgba(255,255,255,0.9);">Course:</label>
                                        <select class="form-select form-select-sm" id="courseFilter" style="width: auto; background: rgba(255,255,255,0.9); border: none; border-radius: 8px;">
                                            <option value="all">All Courses</option>
                                            <?php 
                                            $unique_courses = [];
                                            foreach ($assessments as $assessment) {
                                                $course_name = $assessment['course_name'];
                                                if (!in_array($course_name, $unique_courses)) {
                                                    $unique_courses[] = $course_name;
                                                    echo '<option value="' . htmlspecialchars($course_name) . '">' . htmlspecialchars($course_name) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        </div>
                                        <div class="ms-2">
                                            <i class="fas fa-info-circle" 
                                               style="color: rgba(255,255,255,0.8);"
                                               data-bs-toggle="tooltip" 
                                               data-bs-placement="top" 
                                               title="'Completed' means you have taken the assessment at least once, regardless of your score."></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php
                                // Check for assessments with limited attempts remaining
                                $attempts_warning = false;
                                $attempts_info = [];
                                
                                foreach ($assessments as $assessment) {
                                    if ($assessment['attempt_limit'] > 0 && $assessment['attempt_count'] > 0) {
                                        $attempts_warning = true;
                                        $remaining = $assessment['attempt_limit'] - $assessment['attempt_count'];
                                        if ($remaining <= 2) {
                                            $attempts_info[] = [
                                                'title' => $assessment['assessment_title'],
                                                'remaining' => $remaining,
                                                'limit' => $assessment['attempt_limit']
                                            ];
                                        }
                                    }
                                }
                                ?>
                                
                                <?php if ($attempts_warning): ?>
                                    <div class="alert alert-warning fade show mb-4" id="attempt-limits-reminder" role="alert" style="position: relative;">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-exclamation-triangle me-3 fa-lg"></i>
                                            <div class="flex-grow-1">
                                                <h6 class="alert-heading mb-2">⚠️ Attempt Limits Reminder</h6>
                                                <p class="mb-2">You have assessments with limited attempts remaining:</p>
                                                <ul class="mb-0">
                                                    <?php foreach ($attempts_info as $info): ?>
                                                        <li>
                                                            <strong><?php echo htmlspecialchars($info['title']); ?></strong>: 
                                                            <?php if ($info['remaining'] > 0): ?>
                                                                <span class="text-warning"><?php echo $info['remaining']; ?> attempt<?php echo $info['remaining'] > 1 ? 's' : ''; ?> left</span>
                                                            <?php else: ?>
                                                                <span class="text-danger">Limit reached</span>
                                                            <?php endif; ?>
                                                            (Max: <?php echo $info['limit']; ?>)
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="alert-progress" style="position: absolute; bottom: 0; left: 0; height: 3px; background: rgba(255,193,7,0.3); width: 100%; border-radius: 0 0 0.375rem 0.375rem;">
                                            <div class="alert-progress-bar" style="height: 100%; background: #ffc107; width: 100%; animation: countdown 5s linear forwards;"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($assessments)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> No assessments are currently available for your enrolled courses.
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($assessments as $assessment): ?>
                                            <div class="col-12 col-md-6 col-lg-4 mb-4 assessment-item" 
                                                 data-status="<?php echo $assessment['attempt_count'] > 0 ? 'completed' : 'not-attempted'; ?>"
                                                 data-course="<?php echo htmlspecialchars($assessment['course_name']); ?>"
                                                 data-score="<?php echo $assessment['average_score'] ?? 0; ?>"
                                                 data-attempts="<?php echo $assessment['attempt_count']; ?>">
                                                
                                                <!-- Modern Assessment Card -->
                                                <div class="modern-assessment-card">
                                                    <!-- Lock Status Badge -->
                                                    <?php if (!$assessment['is_accessible'] && $assessment['lock_reason'] !== 'Assessment deactivated'): ?>
                                                        <div class="assessment-lock-status">
                                                            <?php if ($assessment['lock_type'] === 'prerequisite_score'): ?>
                                                                <span class="lock-badge prerequisite">
                                                                    <i class="fas fa-lock me-1"></i>Prerequisite
                                                                </span>
                                                            <?php elseif ($assessment['lock_type'] === 'prerequisite_videos'): ?>
                                                                <span class="lock-badge prerequisite">
                                                                    <i class="fas fa-video me-1"></i>Videos Required
                                                                </span>
                                                            <?php elseif ($assessment['lock_type'] === 'date_based'): ?>
                                                                <span class="lock-badge date-based">
                                                                    <i class="fas fa-calendar me-1"></i>Date Locked
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="lock-badge locked">
                                                                    <i class="fas fa-lock me-1"></i>Locked
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Header Section -->
                                                    <div class="card-header-section">
                                                        <div class="status-indicator">
                                                            <?php if ($assessment['attempt_count'] > 0): ?>
                                                                <div class="status-badge completed">
                                                                    <i class="fas fa-check-circle"></i>
                                                                    <span>Completed</span>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="status-badge available">
                                                                    <i class="fas fa-play-circle"></i>
                                                                    <span>Available</span>
                                                                </div>
                                                            <?php endif; ?>
                                                    </div>
                                                    
                                                        <div class="score-display">
                                                            <?php if ($assessment['attempt_count'] > 0): ?>
                                                                <div class="score-circle">
                                                                    <span class="score-value"><?php echo $assessment['average_score'] ?? 0; ?>%</span>
                                                                    <span class="score-label">Average</span>
                                                            </div>
                                                            <?php else: ?>
                                                                <div class="score-circle new">
                                                                    <span class="score-value">NEW</span>
                                                                    <span class="score-label">Assessment</span>
                                                            </div>
                                                            <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                    <!-- Main Content -->
                                                    <div class="card-content-section">
                                                        <!-- Title and Course Info -->
                                                        <div class="title-section">
                                                            <h3 class="assessment-title">
                                                                <?php echo htmlspecialchars($assessment['assessment_title'] ?? ''); ?>
                                                            </h3>
                                                            <div class="course-details">
                                                                <div class="course-name">
                                                                    <i class="fas fa-graduation-cap"></i>
                                                                    <span><?php echo htmlspecialchars($assessment['course_name'] ?? ''); ?></span>
                                                            </div>
                                                                <div class="module-name">
                                                                    <i class="fas fa-book-open"></i>
                                                                    <span><?php echo htmlspecialchars($assessment['module_title'] ?? ''); ?></span>
                                                                </div>
                                                                <div class="teacher-name">
                                                                    <i class="fas fa-user"></i>
                                                                    <span><?php echo htmlspecialchars($assessment['first_name'] ?? '') . ' ' . htmlspecialchars($assessment['last_name'] ?? ''); ?></span>
                                                            </div>
                                                                </div>
                                                            </div>
                                                        
                                                        <!-- Quick Stats -->
                                                        <div class="quick-stats">
                                                            <div class="stat-box">
                                                                <div class="stat-icon time">
                                                                    <i class="fas fa-clock"></i>
                                                                </div>
                                                                <div class="stat-info">
                                                                    <span class="stat-number"><?php echo $assessment['time_limit']; ?></span>
                                                                    <span class="stat-text">Minutes</span>
                                                            </div>
                                                        </div>
                                                        
                                                            <div class="stat-box">
                                                                <div class="stat-icon questions">
                                                                    <i class="fas fa-question-circle"></i>
                                                                </div>
                                                                <div class="stat-info">
                                                                    <span class="stat-number"><?php echo $assessment['question_count']; ?></span>
                                                                    <span class="stat-text">Questions</span>
                                                            </div>
                                                        </div>
                                                        
                                                            <div class="stat-box attempts-clickable" 
                                                                 data-assessment-id="<?php echo $assessment['id']; ?>"
                                                                 data-assessment-title="<?php echo htmlspecialchars($assessment['assessment_title'] ?? ''); ?>"
                                                                 data-course-name="<?php echo htmlspecialchars($assessment['course_name'] ?? ''); ?>"
                                                                 style="cursor: pointer;"
                                                                 title="Click to view attempts">
                                                                <div class="stat-icon attempts">
                                                                    <i class="fas fa-redo"></i>
                                                                </div>
                                                                <div class="stat-info">
                                                                    <span class="stat-number"><?php echo $assessment['attempt_count']; ?></span>
                                                                    <span class="stat-text">Attempts</span>
                                                                </div>
                                                                <div class="click-indicator">
                                                                    <i class="fas fa-external-link-alt"></i>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Pass Status Indicator -->
                                                            <?php if ($assessment['attempt_count'] > 0): ?>
                                                            <div class="stat-box <?php echo $assessment['has_ever_passed'] ? 'passed' : 'not-passed'; ?>">
                                                                <div class="stat-icon <?php echo $assessment['has_ever_passed'] ? 'passed' : 'not-passed'; ?>">
                                                                    <i class="fas fa-<?php echo $assessment['has_ever_passed'] ? 'trophy' : 'times-circle'; ?>"></i>
                                                                </div>
                                                                <div class="stat-info">
                                                                    <span class="stat-number">
                                                                        <?php echo $assessment['has_ever_passed'] ? 'PASSED' : 'NOT PASSED'; ?>
                                                                    </span>
                                                                    <span class="stat-text">
                                                                        <?php if ($assessment['has_ever_passed']): ?>
                                                                            Best: <?php echo $assessment['best_score']; ?>%
                                                                        <?php else: ?>
                                                                            Best: <?php echo $assessment['best_score']; ?>%
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>
                                                            
                                                            <div class="stat-box">
                                                                <div class="stat-icon limit">
                                                                    <i class="fas fa-lock"></i>
                                                                </div>
                                                                <div class="stat-info">
                                                                    <span class="stat-number">
                                                                        <?php if ($assessment['attempt_limit'] > 0): ?>
                                                                            <?php echo $assessment['attempt_limit']; ?>
                                                                        <?php else: ?>
                                                                            ∞
                                                                        <?php endif; ?>
                                                                    </span>
                                                                    <span class="stat-text">
                                                                        <?php echo $assessment['attempt_limit'] > 0 ? 'Max Attempts' : 'Unlimited'; ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Attempt Reminder Box -->
                                                            <div class="stat-box attempt-reminder <?php echo $assessment['attempt_limit'] > 0 && $assessment['attempt_count'] > 0 ? 'warning' : ''; ?>">
                                                                <div class="stat-icon attempts-remaining">
                                                                    <i class="fas fa-<?php echo $assessment['attempt_limit'] > 0 && $assessment['attempt_count'] > 0 ? 'exclamation-triangle' : 'info-circle'; ?>"></i>
                                                                </div>
                                                                <div class="stat-info">
                                                                    <span class="stat-number">
                                                                        <?php if ($assessment['attempt_limit'] > 0): ?>
                                                                            <?php echo $assessment['attempt_limit'] - $assessment['attempt_count']; ?>
                                                                        <?php else: ?>
                                                                            ∞
                                                                        <?php endif; ?>
                                                                    </span>
                                                                    <span class="stat-text">
                                                                        <?php if ($assessment['attempt_limit'] > 0): ?>
                                                                            <?php if ($assessment['attempt_count'] == 0): ?>
                                                                                Attempts Left
                                                                            <?php elseif ($assessment['attempt_count'] >= $assessment['attempt_limit']): ?>
                                                                                Limit Reached
                                                                            <?php else: ?>
                                                                                Attempts Left
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            Unlimited
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Description -->
                                                        <?php if (!empty($assessment['description'])): ?>
                                                            <div class="description-section">
                                                                <p class="description-text">
                                                                    <?php echo htmlspecialchars($assessment['description']); ?>
                                                                </p>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                        <!-- Progress Bar -->
                                                        <div class="progress-section">
                                                            <div class="progress-info">
                                                                <span class="progress-label">Assessment Passing Rate</span>
                                                                <span class="progress-value"><?php echo $assessment['passing_rate'] ?? 70; ?>%</span>
                                                                <?php if (!isset($assessment['passing_rate'])): ?>
                                                                    <small class="text-muted ms-2">(Default: 70%)</small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="progress-bar-container">
                                                                <div class="progress-bar <?php echo ($assessment['attempt_count'] > 0 && $assessment['has_ever_passed']) ? 'passed' : 'not-passed'; ?>" style="width: <?php echo $assessment['passing_rate'] ?? 70; ?>%"></div>
                                                                <?php if ($assessment['attempt_count'] > 0 && isset($assessment['best_score'])): ?>
                                                                    <div class="student-score-marker <?php echo $assessment['has_ever_passed'] ? 'passed' : 'not-passed'; ?>" 
                                                                         style="left: <?php echo min(100, max(0, $assessment['best_score'])); ?>%;"
                                                                         title="Your best score: <?php echo $assessment['best_score']; ?>%"></div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="progress-description">
                                                                <small class="text-muted">
                                                                    The bar shows the minimum score needed to pass this assessment
                                                                </small>
                                                            </div>
                                                            <?php if ($assessment['attempt_count'] > 0): ?>
                                                                <div class="student-performance-info">
                                                                    <small class="text-muted">
                                                                        Your best score: <?php echo $assessment['best_score'] ?? 0; ?>% 
                                                                        (<?php echo $assessment['has_ever_passed'] ? 'Passed' : 'Not passed'; ?>)
                                                                    </small>
                                                                    <div class="progress-legend">
                                                                        <small class="text-muted">
                                                                            <span class="legend-item">
                                                                                <span class="legend-color passed"></span> Passing threshold
                                                                            </span>
                                                                            <span class="legend-item">
                                                                                <span class="legend-color marker"></span> Your best score
                                                                            </span>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Action Section -->
                                                        <div class="action-section">
                                                            <?php if (!$assessment['academic_year_active'] || !$assessment['semester_active']): ?>
                                                                <div class="warning-message">
                                                                    <i class="fas fa-exclamation-triangle"></i>
                                                                    <span>
                                                                        <?php if (!$assessment['academic_year_active']): ?>
                                                                            Inactive Academic Year
                                                                        <?php endif; ?>
                                                                        <?php if (!$assessment['academic_year_active'] && !$assessment['semester_active']): ?>
                                                                            - 
                                                                        <?php endif; ?>
                                                                        <?php if (!$assessment['semester_active']): ?>
                                                                            Inactive Semester
                                                                        <?php endif; ?>
                                                                        - View Only
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!$assessment['is_accessible'] && $assessment['lock_reason'] !== 'Assessment deactivated'): ?>
                                                                <!-- Assessment is locked (but not deactivated) -->
                                                                <div class="action-button disabled locked-assessment" style="opacity: 0.6; cursor: not-allowed;">
                                                                    <i class="fas fa-lock"></i>
                                                                    <span>Assessment Locked</span>
                                                                </div>
                                                                <!-- Lock reason display -->
                                                                <div class="lock-notice mt-2">
                                                                    <div class="alert alert-warning alert-sm" role="alert">
                                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                                        <strong><?php echo htmlspecialchars($assessment['lock_reason']); ?></strong>
                                                                        <?php if ($assessment['lock_details']): ?>
                                                                            <br><small class="text-muted"><?php echo htmlspecialchars($assessment['lock_details']); ?></small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php elseif (!$assessment['is_accessible'] && $assessment['lock_reason'] === 'Assessment deactivated'): ?>
                                                                <!-- Assessment is deactivated - no alert, just disabled button -->
                                                                <div class="action-button disabled locked-assessment" style="opacity: 0.6; cursor: not-allowed;">
                                                                    <i class="fas fa-ban"></i>
                                                                    <span>Assessment Deactivated</span>
                                                                </div>
                                                            <?php elseif (!$assessment['can_retake']): ?>
                                                                <!-- Attempt limit reached -->
                                                                <div class="action-button disabled" style="opacity: 0.6; cursor: not-allowed;">
                                                                    <i class="fas fa-lock"></i>
                                                                    <span>Attempt Limit Reached</span>
                                                                </div>
                                                            <?php elseif (!$assessment['academic_year_active'] || !$assessment['semester_active']): ?>
                                                                <!-- Inactive academic year/semester - view only -->
                                                                <a href="assessment.php?id=<?php echo $assessment['id']; ?>" class="action-button view-only">
                                                                    <i class="fas fa-eye"></i>
                                                                    <span>View Assessment</span>
                                                                </a>
                                                            <?php else: ?>
                                                                <!-- Assessment is accessible -->
                                                                <?php 
                                                                // Check if student has ever passed this assessment
                                                                $has_ever_passed = hasStudentPassedAssessment($pdo, $user_id, $assessment['id']);
                                                                ?>
                                                                <?php if ($has_ever_passed): ?>
                                                                    <!-- Assessment already passed - show completed status -->
                                                                    <div class="action-button completed">
                                                                        <i class="fas fa-check-circle"></i>
                                                                        <span>Assessment Passed</span>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <!-- Assessment not passed yet - allow taking/retaking -->
                                                                    <a href="assessment.php?id=<?php echo $assessment['id']; ?>" class="action-button <?php echo $assessment['attempt_count'] > 0 ? 'retake' : 'start'; ?>">
                                                                        <i class="fas fa-<?php echo $assessment['attempt_count'] > 0 ? 'redo' : 'play'; ?>"></i>
                                                                        <span><?php echo $assessment['attempt_count'] > 0 ? 'Retake Assessment' : 'Start Assessment'; ?></span>
                                                                    </a>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Attempts Modal -->
    <div class="modal fade" id="attemptsModal" tabindex="-1" aria-labelledby="attemptsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attemptsModalLabel">
                        <i class="fas fa-history me-2"></i>
                        Assessment Attempts
                        <span class="attempt-count-badge ms-2"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="assessment-info mb-3">
                        <h6 class="assessment-title-modal"></h6>
                        <p class="course-name-modal text-muted"></p>
                                            <div class="assessment-summary mt-2">
                        <small class="text-muted">
                            <span class="summary-item">
                                <i class="fas fa-clock me-1"></i>
                                <span class="time-limit-modal"></span> min time limit
                            </span>
                            <span class="summary-item ms-3">
                                <i class="fas fa-question-circle me-1"></i>
                                <span class="question-count-modal"></span> questions
                            </span>
                            <span class="summary-item ms-3">
                                <i class="fas fa-percentage me-1"></i>
                                <span class="passing-rate-modal"></span>% to pass
                            </span>
                        </small>
                        <div class="score-source-legend mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Score Information:</strong>
                                <span class="legend-item ms-2">
                                    <i class="fas fa-database me-1"></i> Original Score
                                </span>
                                <span class="legend-item ms-2">
                                    <i class="fas fa-calculator me-1"></i> Calculated Score
                                </span>
                            </small>
                        </div>
                    </div>
                    </div>
                    <div id="attemptsList">
                        <!-- Attempts will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Assessment filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto-close attempt limits reminder after 5 seconds
            const attemptLimitsReminder = document.getElementById('attempt-limits-reminder');
            if (attemptLimitsReminder) {
                setTimeout(function() {
                    attemptLimitsReminder.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
                    attemptLimitsReminder.style.opacity = '0';
                    attemptLimitsReminder.style.transform = 'translateX(-50%) translateY(-20px)';
                    setTimeout(function() {
                        attemptLimitsReminder.style.display = 'none';
                    }, 500);
                }, 5000); // 5 seconds
            }

            // Initialize attempts modal functionality
            const attemptsModal = new bootstrap.Modal(document.getElementById('attemptsModal'));
            const attemptsClickables = document.querySelectorAll('.attempts-clickable');
            
            attemptsClickables.forEach(clickable => {
                clickable.addEventListener('click', function() {
                    const assessmentId = this.dataset.assessmentId;
                    const assessmentTitle = this.dataset.assessmentTitle;
                    const courseName = this.dataset.courseName;
                    
                    // Get additional data from the assessment card
                    const assessmentCard = this.closest('.modern-assessment-card');
                    const timeLimit = assessmentCard.querySelector('.stat-box .stat-number')?.textContent || 'N/A';
                    const questionCount = assessmentCard.querySelectorAll('.stat-box')[1]?.querySelector('.stat-number')?.textContent || 'N/A';
                    const passingRate = assessmentCard.querySelector('.progress-value')?.textContent?.replace('%', '') || '70';
                    const attemptCount = this.querySelector('.stat-number')?.textContent || '0';
                    
                    // Update modal content
                    document.querySelector('.assessment-title-modal').textContent = assessmentTitle;
                    document.querySelector('.course-name-modal').textContent = courseName;
                    document.querySelector('.time-limit-modal').textContent = timeLimit;
                    document.querySelector('.question-count-modal').textContent = questionCount;
                    document.querySelector('.passing-rate-modal').textContent = passingRate;
                    document.querySelector('.attempt-count-badge').textContent = `(${attemptCount})`;
                    
                    // Load attempts data
                    loadAssessmentAttempts(assessmentId);
                    
                    // Show modal
                    attemptsModal.show();
                });
            });
            
            const statusFilter = document.getElementById('statusFilter');
            const courseFilter = document.getElementById('courseFilter');
            const assessmentItems = document.querySelectorAll('.assessment-item');
            
            function filterAssessments() {
                const selectedStatus = statusFilter.value;
                const selectedCourse = courseFilter.value;
                
                assessmentItems.forEach(item => {
                    const itemStatus = item.dataset.status;
                    const itemCourse = item.dataset.course;
                    const itemScore = parseFloat(item.dataset.score);
                    
                    let showItem = true;
                    
                    // Status filter
                    if (selectedStatus !== 'all') {
                        if (selectedStatus === 'completed' && itemStatus === 'not-attempted') {
                            showItem = false;
                        } else if (selectedStatus === 'not-attempted' && itemStatus === 'completed') {
                            showItem = false;
                        }
                    }
                    
                    // Course filter
                    if (selectedCourse !== 'all' && itemCourse !== selectedCourse) {
                        showItem = false;
                    }
                    
                    // Show/hide item
                    if (showItem) {
                        item.style.display = 'block';
                        item.style.animation = 'fadeIn 0.3s ease-in';
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Show message if no results
                const visibleItems = document.querySelectorAll('.assessment-item[style*="display: block"]');
                const noResultsMsg = document.querySelector('.no-results-message');
                
                if (visibleItems.length === 0) {
                    if (!noResultsMsg) {
                        const message = document.createElement('div');
                        message.className = 'alert alert-info no-results-message text-center';
                        message.innerHTML = '<i class="fas fa-info-circle me-2"></i>No assessments match the selected filters.';
                        document.querySelector('.card-body .row').appendChild(message);
                    }
                } else {
                    if (noResultsMsg) {
                        noResultsMsg.remove();
                    }
                }
            }
            
            // Add event listeners
            statusFilter.addEventListener('change', filterAssessments);
            courseFilter.addEventListener('change', filterAssessments);
            
            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `;
            document.head.appendChild(style);

            // Function to load assessment attempts
            function loadAssessmentAttempts(assessmentId) {
                const attemptsList = document.getElementById('attemptsList');
                
                // Show loading state
                attemptsList.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading attempts...</p>
                    </div>
                `;

                const url = `../ajax_get_assessment_attempts.php?assessment_id=${assessmentId}`;

                // Fetch attempts data
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.attempts && data.attempts.length > 0) {
                            displayAttempts(data.attempts);
                        } else {
                            displayNoAttempts();
                        }
                    })
                    .catch(error => {
                        console.error('Error loading attempts:', error);
                        displayError(error.message);
                    });
            }

            // Function to display attempts
            function displayAttempts(attempts) {
                const attemptsList = document.getElementById('attemptsList');
                let html = '';

                attempts.forEach((attempt, index) => {
                    const attemptNumber = index + 1;
                    const attemptDate = new Date(attempt.completed_at || attempt.started_at).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    const statusClass = attempt.has_passed ? 'passed' : 'failed';
                    const statusText = attempt.has_passed ? 'PASSED' : 'FAILED';
                    
                    const scoreSource = attempt.score_source || 'unknown';
                    const scoreSourceText = scoreSource === 'stored' ? 'Original Score' : 'Calculated Score';
                    const scoreSourceIcon = scoreSource === 'stored' ? 'fas fa-database' : 'fas fa-calculator';
                    
                    html += `
                        <div class="attempt-item-modal">
                            <div class="attempt-header">
                                <span class="attempt-number">Attempt #${attemptNumber}</span>
                                <span class="attempt-date">${attemptDate}</span>
                            </div>
                            <div class="attempt-score">Score: ${attempt.score || 0}%</div>
                            <div class="attempt-details">
                                <span class="attempt-status ${statusClass}">${statusText}</span>
                                <small class="score-source-info">
                                    <i class="${scoreSourceIcon}"></i>
                                    ${scoreSourceText}
                                </small>
                            </div>
                        </div>
                    `;
                });

                attemptsList.innerHTML = html;
            }

            // Function to display no attempts message
            function displayNoAttempts() {
                const attemptsList = document.getElementById('attemptsList');
                attemptsList.innerHTML = `
                    <div class="no-attempts-message">
                        <i class="fas fa-inbox"></i>
                        <h6>No attempts found</h6>
                        <p>You haven't attempted this assessment yet.</p>
                    </div>
                `;
            }

            // Function to display error message
            function displayError(errorMessage = '') {
                const attemptsList = document.getElementById('attemptsList');
                attemptsList.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error loading attempts:</strong><br>
                        ${errorMessage || 'An error occurred while loading attempts. Please try again.'}
                        <br><br>
                        <small class="text-muted">Please check the browser console for more details.</small>
                    </div>
                `;
            }
        });
    </script>
</body>
</html> 