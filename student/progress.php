<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/score_calculator.php';

$db = new Database();
$pdo = $db->getConnection();

// Make $db available globally for functions that need it
$GLOBALS['db'] = $db;

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

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
    $course_module_progress_data = json_decode($course['module_progress'] ?? '{}', true) ?: [];
    $course_video_progress_data = json_decode($course['video_progress'] ?? '{}', true) ?: [];
    
    $course['total_modules'] = count($course_modules);
    $course['completed_modules'] = 0;
    $course['total_videos'] = 0;
    $course['watched_videos'] = 0;
    $course['total_assessments'] = 0;
    $course['completed_assessments'] = 0;
    
    foreach ($course_modules as $module) {
        // Count completed modules
        if (isset($course_module_progress_data[$module['id']]) && isset($course_module_progress_data[$module['id']]['is_completed']) && $course_module_progress_data[$module['id']]['is_completed']) {
            $course['completed_modules']++;
        }
        
        // Count videos
        if (isset($module['videos'])) {
            $course['total_videos'] += count($module['videos']);
            foreach ($module['videos'] as $video) {
                if (isset($course_video_progress_data[$video['id']]) && isset($course_video_progress_data[$video['id']]['is_watched']) && $course_video_progress_data[$video['id']]['is_watched']) {
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
}

// Calculate average score for each course
foreach ($course_progress as &$course) {
    $course['average_score'] = calculateCourseAverageScore($pdo, $user_id, $course['id']);
}
unset($course);

// Get recent activities using new database structure
$recent_activities = [];

// Get recent assessment completions
$stmt = $pdo->prepare("
    SELECT 'assessment' as type, a.assessment_title as title, 
           COALESCE(aa.completed_at, aa.started_at) as date, 
           'Completed assessment' as action, c.course_name as course_title
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE aa.student_id = ? AND aa.status = 'completed'
    ORDER BY COALESCE(aa.completed_at, aa.started_at) DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$assessment_activities = $stmt->fetchAll();

$recent_activities = array_merge($recent_activities, $assessment_activities);

// Sort by date and limit to 10
usort($recent_activities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recent_activities = array_slice($recent_activities, 0, 10);

// Get monthly progress data for chart using new database structure
$monthly_progress = [];

// Get monthly assessment completions
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(aa.completed_at, '%Y-%m') as month,
           COUNT(*) as assessments_completed
    FROM assessment_attempts aa
    WHERE aa.student_id = ? AND aa.status = 'completed' AND aa.completed_at IS NOT NULL
    GROUP BY DATE_FORMAT(aa.completed_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$stmt->execute([$user_id]);
$monthly_progress = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Learning Progress - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap');
        
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
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        .floating-shapes {
            position: absolute;
            top: 20px;
            right: 100px;
            width: 80px;
            height: 80px;
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
        
        .welcome-section .accent-line {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #7DCB80;
            border-radius: 0 0 20px 20px;
        }
        
        .progress-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .progress-card:hover {
            transform: translateY(-5px);
        }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .activity-item.module {
            border-left-color: #28a745;
        }
        .activity-item.video {
            border-left-color: #17a2b8;
        }
        .activity-item.assessment {
            border-left-color: #ffc107;
        }
        .progress {
            transition: all 0.3s ease;
        }
        .progress-bar {
            transition: width 0.6s ease;
        }
        .course-progress-item {
            transition: all 0.3s ease;
        }
        .course-progress-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .progress-percentage {
            font-weight: bold;
            font-size: 0.8rem;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        /* Enhanced Student Progress Scrolling */
        .course-progress-container {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
            border-radius: 8px;
            position: relative;
        }

        /* Custom scrollbar for course progress */
        .course-progress-container::-webkit-scrollbar {
            width: 8px;
        }

        .course-progress-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .course-progress-container::-webkit-scrollbar-thumb {
            background: #007bff;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .course-progress-container::-webkit-scrollbar-thumb:hover {
            background: #0056b3;
        }

        /* Firefox scrollbar styling */
        .course-progress-container {
            scrollbar-width: thin;
            scrollbar-color: #007bff #f1f1f1;
        }

        /* Recent activities scrolling */
        .recent-activities-container {
            max-height: 400px;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
            border-radius: 8px;
            position: relative;
        }

        /* Custom scrollbar for recent activities */
        .recent-activities-container::-webkit-scrollbar {
            width: 6px;
        }

        .recent-activities-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .recent-activities-container::-webkit-scrollbar-thumb {
            background: #28a745;
            border-radius: 3px;
            transition: background 0.3s ease;
        }

        .recent-activities-container::-webkit-scrollbar-thumb:hover {
            background: #218838;
        }

        /* Firefox scrollbar styling for activities */
        .recent-activities-container {
            scrollbar-width: thin;
            scrollbar-color: #28a745 #f1f1f1;
        }

        /* Enhanced course progress cards */
        .course-progress-container .course-progress-item {
            transition: all 0.3s ease;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid transparent;
        }

        .course-progress-container .course-progress-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.15);
            border-color: #007bff;
        }

        /* Enhanced activity items */
        .recent-activities-container .activity-item {
            transition: all 0.3s ease;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 12px;
            background: #fff;
            border: 1px solid transparent;
        }

        .recent-activities-container .activity-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .recent-activities-container .activity-item.module:hover {
            border-color: #28a745;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        }

        .recent-activities-container .activity-item.video:hover {
            border-color: #17a2b8;
            box-shadow: 0 2px 8px rgba(23, 162, 184, 0.2);
        }

        .recent-activities-container .activity-item.assessment:hover {
            border-color: #ffc107;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2);
        }

        /* Scroll indicators for course progress */
        .course-progress-scroll-indicator {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 15;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .course-progress-scroll-indicator.show {
            opacity: 1;
        }

        .course-progress-scroll-indicator-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .course-progress-scroll-indicator i {
            background: rgba(0, 123, 255, 0.8);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }

        .course-progress-scroll-indicator-top.hide,
        .course-progress-scroll-indicator-bottom.hide {
            opacity: 0.3;
        }

        /* Scroll indicators for recent activities */
        .activities-scroll-indicator {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 15;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .activities-scroll-indicator.show {
            opacity: 1;
        }

        .activities-scroll-indicator-content {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .activities-scroll-indicator i {
            background: rgba(40, 167, 69, 0.8);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .activities-scroll-indicator-top.hide,
        .activities-scroll-indicator-bottom.hide {
            opacity: 0.3;
        }

        /* Enhanced progress cards */
        .progress-card {
            transition: all 0.3s ease;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid transparent;
        }

        .progress-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #e0e0e0;
        }

        /* Enhanced progress bars */
        .progress {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 0.6s ease;
            border-radius: 10px;
        }

        .progress:hover {
            transform: scale(1.02);
        }

        /* Mobile responsiveness */
        @media (max-width: 991.98px) {
            .course-progress-container {
                max-height: 400px;
            }
            
            .recent-activities-container {
                max-height: 300px;
            }
        }

        @media (max-width: 575.98px) {
            .course-progress-container {
                max-height: 300px;
            }
            
            .recent-activities-container {
                max-height: 250px;
            }
            
            .course-progress-container .course-progress-item {
                margin-bottom: 12px;
            }
            
            .recent-activities-container .activity-item {
                padding: 10px 12px;
                margin-bottom: 10px;
            }
        }

        /* Loading and animation states */
        .progress-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .progress-item-enter {
            animation: progressItemEnter 0.5s ease-out;
        }

        @keyframes progressItemEnter {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .progress-item-exit {
            animation: progressItemExit 0.5s ease-in;
        }

        @keyframes progressItemExit {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(-100%);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar removed -->
            <!-- Main content -->
            <main class="col-12 px-md-4">
                <!-- Enhanced Welcome Section -->
                <div class="welcome-section">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="welcome-title">My Learning Progress</h1>
                            <p class="welcome-subtitle">Track your academic journey and achievements</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="welcome-actions">
                                <button class="btn btn-refresh" onclick="updateProgress()" title="Refresh Progress">
                                    <i class="fas fa-sync-alt"></i>
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="welcome-decoration">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="floating-shapes"></div>
                    <div class="accent-line"></div>
                </div>

                <!-- Enhanced Overall Progress -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3" data-progress-type="module-progress">
                        <div class="card text-center" style="background: linear-gradient(135deg, #2E5E4E 0%, #1e7e34 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-layer-group fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $module_progress; ?>%</h4>
                                <p class="card-text small">Module Progress</p>
                                <div class="progress mb-2" style="height: 8px; background: rgba(255,255,255,0.2);">
                                    <div class="progress-bar" style="width: <?php echo $module_progress; ?>%; background: #7DCB80;" role="progressbar" aria-valuenow="<?php echo $module_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small class="text-light"><?php echo $overall_stats['completed_modules']; ?> of <?php echo $overall_stats['total_modules']; ?> modules</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3" data-progress-type="video-progress">
                        <div class="card text-center" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-video fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $video_progress; ?>%</h4>
                                <p class="card-text small">Video Progress</p>
                                <div class="progress mb-2" style="height: 8px; background: rgba(255,255,255,0.2);">
                                    <div class="progress-bar" style="width: <?php echo $video_progress; ?>%; background: #20c997;" role="progressbar" aria-valuenow="<?php echo $video_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small class="text-light"><?php echo $overall_stats['watched_videos']; ?> of <?php echo $overall_stats['total_videos']; ?> videos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3" data-progress-type="assessment-progress">
                        <div class="card text-center" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-clipboard-check fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $assessment_progress; ?>%</h4>
                                <p class="card-text small">Assessment Progress</p>
                                <div class="progress mb-2" style="height: 8px; background: rgba(255,255,255,0.2);">
                                    <div class="progress-bar" style="width: <?php echo $assessment_progress; ?>%; background: #ffed4a;" role="progressbar" aria-valuenow="<?php echo $assessment_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small class="text-light"><?php echo $overall_stats['completed_assessments']; ?> of <?php echo $overall_stats['total_assessments']; ?> assessments</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo round($overall_stats['average_score'] ?? 0, 1); ?>%</h4>
                                <p class="card-text small">Average Score</p>
                                <small class="text-light">Across all assessments</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Course Progress -->
                    <div class="col-lg-8">
                        <div class="card" style="border: none; border-radius: 15px; box-shadow: 0 8px 30px rgba(0,0,0,0.08);">
                            <div class="card-header" style="background: linear-gradient(135deg, #2E5E4E 0%, #1e7e34 100%); color: white; border: none; border-radius: 15px 15px 0 0; padding: 1.5rem;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0" style="font-weight: 600; font-size: 1.3rem;">
                                        <i class="fas fa-graduation-cap me-2"></i>
                                        Course Progress
                                    </h5>
                                    <div class="d-flex align-items-center">
                                        <button class="btn btn-sm" onclick="updateProgress()" title="Refresh Progress" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; border-radius: 20px;">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <small class="ms-2" style="color: rgba(255,255,255,0.8);" id="last-update">Last updated: Just now</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($course_progress)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> No courses enrolled yet.
                                    </div>
                                <?php else: ?>
                                    <div class="course-progress-container">
                                        <?php foreach ($course_progress as $course): ?>
                                        <?php 
                                        $course_module_progress = $course['total_modules'] > 0 ? round(($course['completed_modules'] / $course['total_modules']) * 100) : 0;
                                        $course_video_progress = $course['total_videos'] > 0 ? round(($course['watched_videos'] / $course['total_videos']) * 100) : 0;
                                        $course_assessment_progress = $course['total_assessments'] > 0 ? round(($course['completed_assessments'] / $course['total_assessments']) * 100) : 0;
                                        
                                        // Calculate overall course progress (weighted average)
                                        $total_activities = $course['total_modules'] + $course['total_videos'] + $course['total_assessments'];
                                        $completed_activities = $course['completed_modules'] + $course['watched_videos'] + $course['completed_assessments'];
                                        $overall_course_progress = $total_activities > 0 ? round(($completed_activities / $total_activities) * 100) : 0;
                                        ?>
                                        <div class="card mb-3 course-progress-item">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="card-title"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></h6>
                                                        <p class="text-muted mb-0">by <?php echo htmlspecialchars($course['teacher_name'] ?? ''); ?></p>
                                                    </div>
                                                    <span class="badge bg-success"><?php echo $overall_course_progress; ?>%</span>
                                                </div>
                                                
                                                <!-- Overall Course Progress Bar -->
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <small class="text-muted">Overall Progress</small>
                                                        <small class="text-muted"><?php echo $completed_activities; ?>/<?php echo $total_activities; ?> activities</small>
                                                    </div>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-success" style="width: <?php echo $overall_course_progress; ?>%"></div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-4">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <small class="text-muted">
                                                                <i class="fas fa-layer-group"></i> Modules
                                                        </small>
                                                            <small class="text-muted"><?php echo $course['completed_modules']; ?>/<?php echo $course['total_modules']; ?></small>
                                                        </div>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-primary" style="width: <?php echo $course_module_progress; ?>%"></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <small class="text-muted">
                                                                <i class="fas fa-video"></i> Videos
                                                        </small>
                                                            <small class="text-muted"><?php echo $course['watched_videos']; ?>/<?php echo $course['total_videos']; ?></small>
                                                        </div>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-info" style="width: <?php echo $course_video_progress; ?>%"></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <small class="text-muted">
                                                                <i class="fas fa-clipboard-check"></i> Assessments
                                                        </small>
                                                            <small class="text-muted"><?php echo $course['completed_assessments']; ?>/<?php echo $course['total_assessments']; ?></small>
                                                        </div>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-warning" style="width: <?php echo $course_assessment_progress; ?>%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($course['average_score']): ?>
                                                    <div class="mt-2">
                                                    <small class="text-muted">
                                                            <i class="fas fa-chart-line"></i> Average Score: <strong><?php echo round($course['average_score'], 1); ?>%</strong>
                                                    </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="col-lg-4">
                        <div class="card" style="border: none; border-radius: 15px; box-shadow: 0 8px 30px rgba(0,0,0,0.08);">
                            <div class="card-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border: none; border-radius: 15px 15px 0 0; padding: 1.5rem;">
                                <h5 class="mb-0" style="font-weight: 600; font-size: 1.3rem;">
                                    <i class="fas fa-history me-2"></i>
                                    Recent Activities
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activities)): ?>
                                    <p class="text-muted">No recent activities.</p>
                                <?php else: ?>
                                    <div class="recent-activities-container">
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <div class="activity-item <?php echo $activity['type']; ?>">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($activity['title'] ?? ''); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo $activity['action']; ?> in <?php echo htmlspecialchars($activity['course_title'] ?? ''); ?></small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($activity['date'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Monthly Progress Chart -->
                        <div class="card mt-3" style="border: none; border-radius: 15px; box-shadow: 0 8px 30px rgba(0,0,0,0.08);">
                            <div class="card-header" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: white; border: none; border-radius: 15px 15px 0 0; padding: 1.5rem;">
                                <h6 class="mb-0" style="font-weight: 600; font-size: 1.2rem;">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Monthly Progress
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly progress chart
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_progress); ?>;
        
        const labels = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }).reverse();
        
        const data = monthlyData.map(item => item.modules_completed).reverse();
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Modules Completed',
                    data: data,
                    borderColor: '#2E5E4E',
                    backgroundColor: 'rgba(46, 94, 78, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#7DCB80',
                    pointBorderColor: '#2E5E4E',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Real-time progress updates
        let lastUpdateTime = Date.now();
        const UPDATE_INTERVAL = 30000; // 30 seconds
        
        function updateProgress(force = false) {
            // Only update if page is visible and user is active, or if forced
            if (!force && (document.hidden || Date.now() - lastUpdateTime < UPDATE_INTERVAL)) {
                return;
            }
            
            // Show loading state
            const refreshBtn = document.querySelector('button[onclick="updateProgress()"]');
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }
            
            fetch('ajax_get_progress_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateProgressBars(data.stats);
                        updateCourseProgress(data.course_progress);
                        lastUpdateTime = Date.now();
                        updateLastUpdateTime();
                    }
                })
                .catch(error => {
                    console.log('Progress update failed:', error);
                })
                .finally(() => {
                    // Reset button state
                    if (refreshBtn) {
                        refreshBtn.disabled = false;
                        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                    }
                });
        }
        
        function updateProgressBars(stats) {
            // Update overall progress bars
            updateProgressBar('module-progress', stats.module_progress, stats.overall_stats);
            updateProgressBar('video-progress', stats.video_progress, stats.overall_stats);
            updateProgressBar('assessment-progress', stats.assessment_progress, stats.overall_stats);
            
            // Update course progress bars
            if (stats.course_progress) {
                stats.course_progress.forEach(course => {
                    updateCourseProgress(course);
                });
            }
        }
        
        function updateProgressBar(type, percentage, stats = null) {
            const progressBar = document.querySelector(`[data-progress-type="${type}"] .progress-bar`);
            const progressText = document.querySelector(`[data-progress-type="${type}"] .progress-text`);
            const progressPercentage = document.querySelector(`[data-progress-type="${type}"] .progress-percentage`);
            
            if (progressBar) {
                progressBar.style.width = percentage + '%';
                progressBar.setAttribute('aria-valuenow', percentage);
            }
            if (progressPercentage) {
                progressPercentage.textContent = percentage + '%';
            }
            if (progressText && stats) {
                // Update the progress text with actual counts
                let text = '';
                switch(type) {
                    case 'module-progress':
                        text = `${stats.completed_modules} of ${stats.total_modules} modules`;
                        break;
                    case 'video-progress':
                        text = `${stats.watched_videos} of ${stats.total_videos} videos`;
                        break;
                    case 'assessment-progress':
                        text = `${stats.completed_assessments} of ${stats.total_assessments} assessments`;
                        break;
                }
                progressText.textContent = text;
            }
        }
        
        function updateCourseProgress(courseProgress) {
            if (!courseProgress) return;
            
            courseProgress.forEach((course, index) => {
                const courseCard = document.querySelector(`[data-course-id="${index}"]`);
                if (!courseCard) return;
                
                // Update overall course progress
                const overallProgressBar = courseCard.querySelector('.overall-progress .progress-bar');
                const overallBadge = courseCard.querySelector('.badge');
                if (overallProgressBar) {
                    overallProgressBar.style.width = course.overall_progress + '%';
                }
                if (overallBadge) {
                    overallBadge.textContent = course.overall_progress + '%';
                }
                
                // Update individual progress bars
                updateCourseProgressBar(courseCard, 'module', course.module_progress);
                updateCourseProgressBar(courseCard, 'video', course.video_progress);
                updateCourseProgressBar(courseCard, 'assessment', course.assessment_progress);
            });
        }
        
        function updateCourseProgressBar(courseCard, type, percentage) {
            const progressBar = courseCard.querySelector(`.${type}-progress .progress-bar`);
            const progressText = courseCard.querySelector(`.${type}-progress .progress-text`);
            
            if (progressBar) {
                progressBar.style.width = percentage + '%';
            }
            if (progressText) {
                progressText.textContent = percentage + '%';
            }
        }
        
        function updateLastUpdateTime() {
            const lastUpdateElement = document.getElementById('last-update');
            if (lastUpdateElement) {
                const now = new Date();
                lastUpdateElement.textContent = 'Last updated: ' + now.toLocaleTimeString();
            }
        }
        
        // Enhanced scrolling behavior for student progress
        function enhanceStudentProgressScrolling() {
            // Course progress scrolling
            const courseProgressContainer = document.querySelector('.course-progress-container');
            if (courseProgressContainer) {
                courseProgressContainer.style.scrollBehavior = 'smooth';
                const courseCard = courseProgressContainer.closest('.card');
                if (courseCard) {
                    addCourseProgressScrollIndicators(courseProgressContainer, courseCard);
                }
            }
            
            // Recent activities scrolling
            const activitiesContainer = document.querySelector('.recent-activities-container');
            if (activitiesContainer) {
                activitiesContainer.style.scrollBehavior = 'smooth';
                const activitiesCard = activitiesContainer.closest('.card');
                if (activitiesCard) {
                    addActivitiesScrollIndicators(activitiesContainer, activitiesCard);
                }
            }
        }
        
        // Add scroll indicators to course progress
        function addCourseProgressScrollIndicators(scrollContainer, cardContainer) {
            const scrollIndicator = document.createElement('div');
            scrollIndicator.className = 'course-progress-scroll-indicator';
            scrollIndicator.innerHTML = `
                <div class="course-progress-scroll-indicator-content">
                    <i class="fas fa-chevron-up course-progress-scroll-indicator-top"></i>
                    <i class="fas fa-chevron-down course-progress-scroll-indicator-bottom"></i>
                </div>
            `;
            
            cardContainer.style.position = 'relative';
            cardContainer.appendChild(scrollIndicator);
            
            function updateCourseProgressScrollIndicators() {
                const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
                const isAtTop = scrollContainer.scrollTop === 0;
                const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
                
                if (isScrollable) {
                    scrollIndicator.classList.add('show');
                    scrollIndicator.querySelector('.course-progress-scroll-indicator-top').classList.toggle('hide', isAtTop);
                    scrollIndicator.querySelector('.course-progress-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
                } else {
                    scrollIndicator.classList.remove('show');
                }
            }
            
            updateCourseProgressScrollIndicators();
            scrollContainer.addEventListener('scroll', updateCourseProgressScrollIndicators);
            window.addEventListener('resize', updateCourseProgressScrollIndicators);
        }
        
        // Add scroll indicators to recent activities
        function addActivitiesScrollIndicators(scrollContainer, cardContainer) {
            const scrollIndicator = document.createElement('div');
            scrollIndicator.className = 'activities-scroll-indicator';
            scrollIndicator.innerHTML = `
                <div class="activities-scroll-indicator-content">
                    <i class="fas fa-chevron-up activities-scroll-indicator-top"></i>
                    <i class="fas fa-chevron-down activities-scroll-indicator-bottom"></i>
                </div>
            `;
            
            cardContainer.style.position = 'relative';
            cardContainer.appendChild(scrollIndicator);
            
            function updateActivitiesScrollIndicators() {
                const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
                const isAtTop = scrollContainer.scrollTop === 0;
                const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
                
                if (isScrollable) {
                    scrollIndicator.classList.add('show');
                    scrollIndicator.querySelector('.activities-scroll-indicator-top').classList.toggle('hide', isAtTop);
                    scrollIndicator.querySelector('.activities-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
                } else {
                    scrollIndicator.classList.remove('show');
                }
            }
            
            updateActivitiesScrollIndicators();
            scrollContainer.addEventListener('scroll', updateActivitiesScrollIndicators);
            window.addEventListener('resize', updateActivitiesScrollIndicators);
        }

        // Initialize progress tracking
        document.addEventListener('DOMContentLoaded', function() {
            // Add data attributes to course cards
            const courseCards = document.querySelectorAll('.card.mb-3');
            courseCards.forEach((card, index) => {
                card.setAttribute('data-course-id', index);
            });
            
            // Initialize enhanced scrolling
            enhanceStudentProgressScrolling();
            
            // Start auto-update
            setInterval(updateProgress, UPDATE_INTERVAL);
            
            // Update on page visibility change
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    updateProgress();
                }
            });
        });
    </script>
</body>
</html> 