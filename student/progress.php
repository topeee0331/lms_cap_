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
    <title>Progress - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar removed -->
            <!-- Main content -->
            <main class="col-12 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Learning Progress</h1>
                </div>

                <!-- Overall Progress -->
                <div class="row mb-4">
                    <div class="col-md-3" data-progress-type="module-progress">
                        <div class="card progress-card text-center">
                            <div class="card-body">
                                <i class="fas fa-layer-group fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Module Progress</h5>
                                <div class="progress mb-2" style="height: 20px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $module_progress; ?>%" role="progressbar" aria-valuenow="<?php echo $module_progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <span class="progress-percentage"><?php echo $module_progress; ?>%</span>
                                    </div>
                                </div>
                                <p class="card-text progress-text"><?php echo $overall_stats['completed_modules']; ?> of <?php echo $overall_stats['total_modules']; ?> modules</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3" data-progress-type="video-progress">
                        <div class="card progress-card text-center">
                            <div class="card-body">
                                <i class="fas fa-video fa-3x text-info mb-3"></i>
                                <h5 class="card-title">Video Progress</h5>
                                <div class="progress mb-2" style="height: 20px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $video_progress; ?>%" role="progressbar" aria-valuenow="<?php echo $video_progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <span class="progress-percentage"><?php echo $video_progress; ?>%</span>
                                    </div>
                                </div>
                                <p class="card-text progress-text"><?php echo $overall_stats['watched_videos']; ?> of <?php echo $overall_stats['total_videos']; ?> videos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3" data-progress-type="assessment-progress">
                        <div class="card progress-card text-center">
                            <div class="card-body">
                                <i class="fas fa-clipboard-check fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Assessment Progress</h5>
                                <div class="progress mb-2" style="height: 20px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $assessment_progress; ?>%" role="progressbar" aria-valuenow="<?php echo $assessment_progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <span class="progress-percentage"><?php echo $assessment_progress; ?>%</span>
                                    </div>
                                </div>
                                <p class="card-text progress-text"><?php echo $overall_stats['completed_assessments']; ?> of <?php echo $overall_stats['total_assessments']; ?> assessments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card progress-card text-center">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Average Score</h5>
                                <p class="card-text display-6"><?php echo round($overall_stats['average_score'] ?? 0, 1); ?>%</p>
                                <small class="text-muted">Across all assessments</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Course Progress -->
                    <div class="col-lg-8">
                        <div class="card progress-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Course Progress</h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="updateProgress()" title="Refresh Progress">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <small class="text-muted ms-2" id="last-update">Last updated: Just now</small>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($course_progress)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> No courses enrolled yet.
                                    </div>
                                <?php else: ?>
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
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="col-lg-4">
                        <div class="card progress-card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activities)): ?>
                                    <p class="text-muted">No recent activities.</p>
                                <?php else: ?>
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
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Monthly Progress Chart -->
                        <div class="card progress-card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">Monthly Progress</h6>
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
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
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
        
        // Initialize progress tracking
        document.addEventListener('DOMContentLoaded', function() {
            // Add data attributes to course cards
            const courseCards = document.querySelectorAll('.card.mb-3');
            courseCards.forEach((card, index) => {
                card.setAttribute('data-course-id', index);
            });
            
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