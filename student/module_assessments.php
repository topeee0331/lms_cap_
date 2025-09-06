<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/score_calculator.php';
require_once '../includes/assessment_pass_tracker.php';

$db = new Database();
$pdo = $db->getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$module_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$module_id) {
    header('Location: courses.php');
    exit();
}

// Get module and course details for breadcrumbs and to verify enrollment
$stmt = $pdo->prepare("
    SELECT m.id AS module_id, m.module_title, c.id AS course_id, c.course_name
    FROM course_modules m
    JOIN courses c ON m.course_id = c.id
    JOIN course_enrollments e ON c.id = e.course_id
    WHERE m.id = ? AND e.student_id = ?
");
$stmt->execute([$module_id, $student_id]);
$module_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module_info) {
    $_SESSION['error'] = 'You are not enrolled in this module or it does not exist.';
    header('Location: courses.php');
    exit();
}

// Fetch all assessments for this module with comprehensive details
$stmt = $pdo->prepare("
    SELECT a.*, m.course_id, c.academic_period_id, 
           ay.is_active as academic_period_active,
           c.course_name, m.module_title, u.first_name, u.last_name,
           a.is_locked, a.lock_type, a.prerequisite_assessment_id, 
           a.prerequisite_score, a.prerequisite_video_count, a.unlock_date, a.lock_message
    FROM assessments a
    JOIN course_modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    JOIN academic_periods ay ON c.academic_period_id = ay.id
    WHERE a.module_id = ?
    ORDER BY a.created_at ASC
");
$stmt->execute([$module_id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enrich each assessment with student-specific information
foreach ($assessments as &$assessment) {
    // Get attempt count and best score for current student
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM assessment_attempts WHERE student_id = ? AND assessment_id = ? AND status = 'completed'");
    $stmt2->execute([$student_id, $assessment['id']]);
    $row = $stmt2->fetch();
    $assessment['attempt_count'] = $row['attempt_count'] ?? 0;
    
    // Calculate average score using the helper function
    $assessment['average_score'] = calculateAssessmentAverageScore($pdo, $student_id, $assessment['id']);

    // Add question count
    $stmt3 = $pdo->prepare("SELECT COUNT(*) as question_count FROM assessment_questions WHERE assessment_id = ?");
    $stmt3->execute([$assessment['id']]);
    $qrow = $stmt3->fetch();
    $assessment['question_count'] = $qrow['question_count'] ?? 0;
    
    // Add attempt limit info
    $assessment['attempt_limit'] = $assessment['attempt_limit'] ?? 3; // Default to 3 if not set
    $assessment['can_retake'] = $assessment['attempt_limit'] == 0 || $assessment['attempt_count'] < $assessment['attempt_limit'];
    
    // Add pass status information
    $pass_stats = getAssessmentPassStats($pdo, $student_id, $assessment['id']);
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
                    $prereq_stmt->execute([$student_id, $assessment['prerequisite_assessment_id']]);
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
                    // Count videos watched by student in this module
                    $video_stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT cv.id) as watched_videos
                        FROM course_videos cv
                        JOIN video_views vv ON cv.id = vv.video_id
                        WHERE cv.module_id = ? 
                        AND vv.student_id = ? AND vv.watch_duration >= 30
                    ");
                    $video_stmt->execute([$module_id, $student_id]);
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
    if (!$assessment['is_active']) {
        $assessment['is_accessible'] = false;
        $assessment['lock_reason'] = 'Assessment deactivated';
        $assessment['lock_details'] = 'This assessment has been deactivated by your teacher.';
    }
}
unset($assessment);

$page_title = 'Assessments: ' . htmlspecialchars($module_info['module_title']);

// Define course themes matching the module.php file
$course_themes = [
    ['bg' => 'bg-primary', 'icon' => 'fas fa-code', 'color' => '#0d6efd'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-database', 'color' => '#198754'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-network-wired', 'color' => '#0dcaf0'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-server', 'color' => '#ffc107'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-shield-alt', 'color' => '#dc3545'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-cloud', 'color' => '#6c757d'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-microchip', 'color' => '#0d6efd'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-laptop-code', 'color' => '#198754'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-mobile-alt', 'color' => '#0dcaf0'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-wifi', 'color' => '#ffc107'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-keyboard', 'color' => '#dc3545'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-bug', 'color' => '#6c757d'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-terminal', 'color' => '#0d6efd'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-cogs', 'color' => '#198754'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-rocket', 'color' => '#6c757d'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-robot', 'color' => '#ffc107'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-brain', 'color' => '#dc3545'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-chart-line', 'color' => '#6c757d'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-fire', 'color' => '#0d6efd'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-lightbulb', 'color' => '#198754']
];

// Get the theme for this course
$theme = $course_themes[$module_info['course_id'] % count($course_themes)];
$theme_color = $theme['color'];
$theme_icon = $theme['icon'];
?>

<?php include '../includes/header.php'; ?>

<style>
    :root {
        /* Bootstrap 5 Color Scheme - Matching Module Banner Colors */
        --primary-color: #0d6efd;      /* bg-primary */
        --secondary-color: #6c757d;    /* bg-secondary */
        --success-color: #198754;      /* bg-success */
        --info-color: #0dcaf0;         /* bg-info */
        --warning-color: #ffc107;      /* bg-warning */
        --danger-color: #dc3545;       /* bg-danger */
        --light-color: #f8f9fa;       /* bg-light */
        --dark-color: #212529;         /* bg-dark */
        
        /* Enhanced Colors for Better Contrast */
        --primary-dark: #0b5ed7;
        --success-dark: #157347;
        --info-dark: #0aa2c0;
        --warning-dark: #e0a800;
        --danger-dark: #bb2d3b;
        
        /* Neutral Colors */
        --border-radius: 12px;
        --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --box-shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --border-color: #dee2e6;
        --text-muted: #6c757d;
        
        /* Gradient System Based on Bootstrap Colors */
        --gradient-primary: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        --gradient-success: linear-gradient(135deg, #198754 0%, #157347 100%);
        --gradient-info: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);
        --gradient-warning: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        --gradient-danger: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
        --gradient-secondary: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    }

    body {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        color: var(--dark-color);
        line-height: 1.6;
        min-height: 100vh;
    }

    .assessment-header {
        background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-dark) 100%);
        color: white;
        font-size: 1.5rem;
        font-weight: 800;
        padding: 2rem 2.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        box-shadow: 0 8px 25px var(--theme-color-light);
        text-align: center;
        position: relative;
        overflow: hidden;
        letter-spacing: -0.025em;
    }

    .assessment-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
        pointer-events: none;
    }

    .assessment-header::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    /* Premium Assessment Card Styles */
    .modern-assessment-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        overflow: hidden;
        transition: var(--transition);
        height: 100%;
        position: relative;
        border: none;
        backdrop-filter: blur(10px);
        background: rgba(255, 255, 255, 0.95);
    }

    .modern-assessment-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--box-shadow-hover);
        border-color: transparent;
    }

    .modern-assessment-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--theme-color);
        z-index: 1;
        box-shadow: 0 2px 8px var(--theme-color-light);
    }

    .modern-assessment-card.completed::before {
        background: var(--gradient-success);
        box-shadow: 0 2px 8px rgba(79, 172, 254, 0.4);
    }

    .modern-assessment-card.locked::before {
        background: var(--gradient-danger);
        box-shadow: 0 2px 8px rgba(255, 154, 158, 0.4);
    }

    /* Premium Header Section */
    .card-header-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        position: relative;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        backdrop-filter: blur(10px);
    }

    .card-header-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.4) 100%);
        pointer-events: none;
    }

    .card-header-section::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--theme-color);
        opacity: 0.8;
    }

    .status-indicator {
        position: relative;
        z-index: 2;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1rem;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.8rem;
        color: white;
        transition: var(--transition);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.completed {
        background: var(--gradient-success);
        box-shadow: 0 4px 12px rgba(79, 172, 254, 0.3);
    }

    .status-badge.available {
        background: var(--gradient-primary);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .status-badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }

    .status-badge i {
        font-size: 1rem;
    }

    /* Premium Score Display */
    .score-display {
        text-align: center;
        position: relative;
        z-index: 2;
    }

    .score-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: var(--gradient-success);
        color: white;
        transition: var(--transition);
        box-shadow: 0 4px 12px rgba(79, 172, 254, 0.3);
        position: relative;
        overflow: hidden;
    }

    .score-circle::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.05) 100%);
        pointer-events: none;
    }

    .score-circle.new {
        background: var(--gradient-primary);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .score-circle:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .score-value {
        font-size: 1rem;
        font-weight: 700;
        line-height: 1;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .score-label {
        font-size: 0.7rem;
        font-weight: 500;
        opacity: 0.95;
        margin-top: 0.2rem;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    /* Premium Content Section */
    .card-content-section {
        padding: 1.5rem;
        background: white;
    }

    .title-section {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--light-color);
    }

    .assessment-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--dark-color);
        margin-bottom: 0.75rem;
        line-height: 1.3;
        letter-spacing: -0.025em;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
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

    /* Premium Quick Stats */
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        padding: 1rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: var(--border-radius);
        border: 1px solid var(--border-color);
    }

    .stat-box {
        background: white;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 0.75rem;
        text-align: center;
        transition: var(--transition);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .stat-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--theme-color);
        opacity: 0;
        transition: var(--transition);
    }

    .stat-box:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .stat-box:hover::before {
        opacity: 1;
    }

    .stat-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.5rem;
        font-size: 0.9rem;
        color: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        transition: var(--transition);
    }

    .stat-icon:hover {
        transform: scale(1.1);
    }

    .stat-icon.time {
        background: var(--gradient-primary);
    }

    .stat-icon.questions {
        background: var(--gradient-warning);
    }

    .stat-icon.attempts {
        background: var(--gradient-primary);
    }

    .stat-icon.passed {
        background: var(--gradient-success);
    }

    .stat-icon.not-passed {
        background: var(--gradient-danger);
    }

    .stat-icon.limit {
        background: var(--gradient-primary);
    }

    .stat-info {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .stat-number {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--dark-color);
        line-height: 1;
    }

    .stat-text {
        font-size: 0.7rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    /* Progress Section */
    .progress-section {
        margin-bottom: 1rem;
    }

    .progress-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .progress-label {
        font-size: 0.85rem;
        color: var(--dark-color);
        font-weight: 500;
    }

    .progress-value {
        font-size: 0.85rem;
        color: var(--primary-color);
        font-weight: 600;
    }

    .progress-bar-container {
        position: relative;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }

    .progress-bar {
        height: 100%;
        background: var(--primary-color);
        transition: width 0.3s ease;
    }

    .progress-bar.passed {
        background: var(--success-color);
    }

    .progress-bar.not-passed {
        background: var(--danger-color);
    }

    .student-score-marker {
        position: absolute;
        top: -2px;
        width: 12px;
        height: 12px;
        background: var(--warning-color);
        border-radius: 50%;
        transform: translateX(-50%);
        border: 2px solid white;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .student-score-marker.passed {
        background: var(--success-color);
    }

    .student-score-marker.not-passed {
        background: var(--danger-color);
    }

    .progress-description {
        margin-bottom: 0.5rem;
    }

    .student-performance-info {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .progress-legend {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }

    .legend-color.passed {
        background: var(--success-color);
    }

    .legend-color.marker {
        background: var(--warning-color);
    }

    /* Premium Action Section */
    .action-section {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid var(--light-color);
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        margin: 1.5rem -1.5rem -1.5rem -1.5rem;
        padding: 1.5rem;
        border-radius: 0 0 var(--border-radius) var(--border-radius);
    }

    .action-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 1rem 2rem;
        background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-dark) 100%);
        color: white;
        text-decoration: none;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 1rem;
        transition: var(--transition);
        border: none;
        cursor: pointer;
        width: 100%;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px var(--theme-color-light);
        position: relative;
        overflow: hidden;
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
        background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-dark) 100%);
        color: white;
        text-decoration: none;
        transform: translateY(-3px);
        box-shadow: 0 8px 20px var(--theme-color-light);
    }

    .action-button.retake {
        background: var(--gradient-warning);
        box-shadow: 0 4px 12px rgba(250, 112, 154, 0.3);
    }

    .action-button.retake:hover {
        box-shadow: 0 8px 20px rgba(250, 112, 154, 0.4);
    }

    .action-button.start {
        background: var(--gradient-success);
        box-shadow: 0 4px 12px rgba(79, 172, 254, 0.3);
    }

    .action-button.start:hover {
        box-shadow: 0 8px 20px rgba(79, 172, 254, 0.4);
    }

    .action-button.completed {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        cursor: default;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }

    .action-button.completed:hover {
        transform: none;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }

    .action-button.disabled {
        background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-dark) 100%);
        cursor: not-allowed;
        opacity: 0.6;
        filter: grayscale(100%);
    }

    .action-button.disabled:hover {
        transform: none;
        box-shadow: 0 4px 12px var(--theme-color-light);
    }

    .warning-message {
        background: #fff3cd;
        color: #856404;
        padding: 0.75rem;
        border-radius: var(--border-radius);
        border: 1px solid #ffeaa7;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }

    /* Lock notice styles */
    .lock-notice {
        margin-top: 0.75rem;
    }
    
    .alert-sm {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        border-radius: 0.375rem;
        margin-bottom: 0;
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
    
    .lock-badge.prerequisite {
        background-color: #fd7e14;
        color: white;
    }
    
    .lock-badge.date-based {
        background-color: #17a2b8;
        color: white;
    }

    /* Premium Summary Cards */
    .summary-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        background: white;
        backdrop-filter: blur(10px);
        background: rgba(255, 255, 255, 0.95);
        position: relative;
        overflow: hidden;
    }

    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--theme-color);
        opacity: 0;
        transition: var(--transition);
    }

    .summary-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--box-shadow-hover);
    }

    .summary-card:hover::before {
        opacity: 1;
    }

    .summary-card .card-body {
        padding: 1.5rem;
        text-align: center;
    }
    
    .summary-card .card-title {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.75rem;
        background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-dark) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1;
    }
    
    .summary-card .card-text {
        font-size: 0.9rem;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Additional Styles */
    .attempt-reminder {
        background: var(--light-color);
    }

    .attempt-reminder.warning {
        background: #fff3cd;
        border-color: #ffeaa7;
    }

    .attempt-reminder.warning .stat-icon {
        background: var(--warning-color);
    }

    .attempts-remaining {
        background: var(--info-color);
    }

    .attempts-remaining.warning {
        background: var(--warning-color);
    }

    /* Description Section */
    .description-section {
        margin-bottom: 1rem;
        padding: 0.75rem;
        background: var(--light-color);
        border-radius: var(--border-radius);
        border: 1px solid var(--border-color);
    }

    .description-text {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.9rem;
        line-height: 1.5;
    }

    /* Premium Animations */
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

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .modern-assessment-card {
        animation: slideInUp 0.6s ease-out;
    }

    .summary-card {
        animation: fadeIn 0.8s ease-out;
    }

    .assessment-header {
        animation: fadeIn 1s ease-out;
    }

    /* Glassmorphism Effects */
    .glass-effect {
        background: rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
    }

    /* Premium Hover States */
    .stat-box:hover .stat-icon {
        animation: pulse 0.6s ease-in-out;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    /* Dynamic Theme Colors */
    .theme-primary {
        --theme-color: <?php echo $theme_color; ?>;
        --theme-color-light: <?php echo $theme_color; ?>20;
        --theme-color-dark: <?php echo $theme_color; ?>e6;
    }

    /* Premium Background Effects */
    .page-background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        pointer-events: none;
    }

    .floating-shapes {
        position: absolute;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    .shape {
        position: absolute;
        background: linear-gradient(135deg, var(--theme-color-light) 0%, rgba(25, 135, 84, 0.1) 100%);
        border-radius: 50%;
        animation: float-shape 20s infinite linear;
    }

    .shape:nth-child(1) {
        width: 80px;
        height: 80px;
        top: 20%;
        left: 10%;
        animation-delay: 0s;
    }

    .shape:nth-child(2) {
        width: 120px;
        height: 120px;
        top: 60%;
        right: 10%;
        animation-delay: -5s;
    }

    .shape:nth-child(3) {
        width: 60px;
        height: 60px;
        bottom: 20%;
        left: 20%;
        animation-delay: -10s;
    }

    @keyframes float-shape {
        0% {
            transform: translateY(0px) rotate(0deg);
            opacity: 0.7;
        }
        50% {
            transform: translateY(-100px) rotate(180deg);
            opacity: 0.3;
        }
        100% {
            transform: translateY(0px) rotate(360deg);
            opacity: 0.7;
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .assessment-header {
            font-size: 1.1rem;
            padding: 0.8rem 1rem;
        }
        .quick-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        .summary-card .card-title {
            font-size: 1.5rem;
        }
        .summary-card .card-text {
            font-size: 0.8rem;
        }
    }
    
    @media (max-width: 600px) {
        .assessment-header {
            font-size: 1rem;
            padding: 0.6rem 0.8rem;
        }
        .quick-stats {
            grid-template-columns: 1fr;
        }
        .summary-card .card-title {
            font-size: 1.25rem;
        }
        .summary-card .card-text {
            font-size: 0.75rem;
        }
    }
</style>

<!-- Premium Background Effects -->
<div class="page-background">
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
</div>

<div class="container-fluid theme-primary">
    <div class="row">
        <!-- Removed Sidebar -->
        <main class="col-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-0 pb-2 mb-2 border-bottom">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="courses.php">My Courses</a></li>
                            <li class="breadcrumb-item"><a href="course.php?id=<?php echo $module_info['course_id']; ?>"><?php echo htmlspecialchars($module_info['course_name']); ?></a></li>
                            <li class="breadcrumb-item"><a href="module.php?id=<?php echo $module_info['module_id']; ?>"><?php echo htmlspecialchars($module_info['module_title']); ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Assessments</li>
                        </ol>
                    </nav>
                    <h1 class="h2">Assessments</h1>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="module.php?id=<?php echo $module_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Module
                    </a>
                </div>
            </div>

            <!-- Module Assessment Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center summary-card">
                        <div class="card-body">
                            <h5 class="card-title text-primary"><?php echo count($assessments); ?></h5>
                            <p class="card-text">Total Assessments</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center summary-card">
                        <div class="card-body">
                            <h5 class="card-title text-success"><?php echo count(array_filter($assessments, function($a) { return $a['attempt_count'] > 0; })); ?></h5>
                            <p class="card-text">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center summary-card">
                        <div class="card-body">
                            <h5 class="card-title text-info"><?php echo count(array_filter($assessments, function($a) { return $a['is_accessible'] && $a['attempt_count'] == 0; })); ?></h5>
                            <p class="card-text">Available</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center summary-card">
                        <div class="card-body">
                            <h5 class="card-title text-warning"><?php echo count(array_filter($assessments, function($a) { return !$a['is_accessible']; })); ?></h5>
                            <p class="card-text">Locked</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="assessment-header mb-2">
                <i class="<?php echo $theme_icon; ?> me-3"></i>
                Module Assessments
            </div>
            <?php if (empty($assessments)): ?>
                <div class="alert alert-info">No assessments have been added to this module yet.</div>
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
                                        
                                        <div class="stat-box">
                                            <div class="stat-icon attempts">
                                                <i class="fas fa-redo"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-number"><?php echo $assessment['attempt_count']; ?></span>
                                                <span class="stat-text">Attempts</span>
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
                                                    Best: <?php echo $assessment['best_score']; ?>%
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
                                    
                                    <!-- Lock Notice (if locked but not deactivated) -->
                                    <?php if (!$assessment['is_accessible'] && $assessment['lock_reason'] !== 'Assessment deactivated'): ?>
                                        <div class="lock-notice">
                                            <div class="alert alert-warning alert-sm" role="alert">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <strong><?php echo htmlspecialchars($assessment['lock_reason']); ?></strong>
                                                <?php if ($assessment['lock_details']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($assessment['lock_details']); ?></small>
                                                <?php endif; ?>
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
                                    
                                    <?php if (!$assessment['is_accessible']): ?>
                                        <!-- Assessment is locked or deactivated -->
                                        <div class="action-button disabled">
                                            <?php if ($assessment['lock_reason'] === 'Assessment deactivated'): ?>
                                                <i class="fas fa-ban me-1"></i>Assessment Deactivated
                                            <?php else: ?>
                                                <i class="fas fa-lock me-1"></i>Assessment Locked
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (!$assessment['can_retake']): ?>
                                        <!-- Attempt limit reached -->
                                        <div class="action-button disabled">
                                            <i class="fas fa-lock me-1"></i>Attempt Limit Reached
                                        </div>
                                    <?php elseif (!$assessment['academic_year_active'] || !$assessment['semester_active']): ?>
                                        <!-- Inactive academic year/semester - view only -->
                                        <a href="assessment.php?id=<?php echo $assessment['id']; ?>" class="action-button view-only">
                                            <i class="fas fa-eye me-1"></i>View Assessment
                                        </a>
                                    <?php else: ?>
                                        <!-- Assessment is accessible - direct link to take assessment -->
                                        <?php 
                                        // Check if student has ever passed this assessment
                                        $has_ever_passed = hasStudentPassedAssessment($pdo, $user_id, $assessment['id']);
                                        ?>
                                        <?php if ($has_ever_passed): ?>
                                            <!-- Assessment already passed - show completed status -->
                                            <div class="action-button completed">
                                                <i class="fas fa-check-circle me-1"></i>Assessment Passed
                                            </div>
                                        <?php else: ?>
                                            <!-- Assessment not passed yet - allow taking/retaking -->
                                            <a href="assessment.php?id=<?php echo $assessment['id']; ?>" class="action-button <?php echo $assessment['attempt_count'] > 0 ? 'retake' : 'start'; ?>">
                                                <?php if ($assessment['attempt_count'] > 0): ?>
                                                    <i class="fas fa-redo me-1"></i>Retake Assessment
                                                <?php else: ?>
                                                    <i class="fas fa-play me-1"></i>Take Assessment
                                                <?php endif; ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<style>
body {
    margin-top: 0;
    padding-top: 80px;
}
.navbar-accent-bar {
    display: none !important;
}
main.flex-grow-1 {
    display: none !important;
}
</style> 