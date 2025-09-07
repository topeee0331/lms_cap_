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
    SELECT e.module_progress, c.modules
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
        .progress-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #e9ecef; /* Fallback for older browsers */
            background: conic-gradient(#007bff 0deg, #007bff <?php echo $module_progress * 3.6; ?>deg, #e9ecef <?php echo $module_progress * 3.6; ?>deg, #e9ecef 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
        }
        .progress-circle::before {
            content: '';
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .progress-text {
            position: relative;
            z-index: 1;
            font-size: 1.2rem;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <!-- Header temporarily disabled for debugging -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">NEUST-MGT BSIT LMS</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

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
                    <div class="col-md-3">
                        <div class="card progress-card text-center">
                            <div class="card-body">
                                <div class="position-relative">
                                    <div class="progress-circle"></div>
                                    <div class="progress-text"><?php echo $module_progress; ?>%</div>
                                </div>
                                <h5 class="card-title mt-3">Module Progress</h5>
                                <p class="card-text"><?php echo $overall_stats['completed_modules']; ?> of <?php echo $overall_stats['total_modules']; ?> modules</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card progress-card text-center">
                            <div class="card-body">
                                <i class="fas fa-video fa-3x text-info mb-3"></i>
                                <h5 class="card-title">Video Progress</h5>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-info" style="width: <?php echo $video_progress; ?>%"></div>
                                </div>
                                <p class="card-text"><?php echo $overall_stats['watched_videos']; ?> of <?php echo $overall_stats['total_videos']; ?> videos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card progress-card text-center">
                            <div class="card-body">
                                <i class="fas fa-clipboard-check fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Assessment Progress</h5>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $assessment_progress; ?>%"></div>
                                </div>
                                <p class="card-text"><?php echo $overall_stats['completed_assessments']; ?> of <?php echo $overall_stats['total_assessments']; ?> assessments</p>
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
                            <div class="card-header">
                                <h5 class="mb-0">Course Progress</h5>
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
                                        ?>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="card-title"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></h6>
                                                        <p class="text-muted mb-0">by <?php echo htmlspecialchars($course['teacher_name'] ?? ''); ?></p>
                                                    </div>
                                                    <span class="badge bg-primary"><?php echo $course_module_progress; ?>%</span>
                                                </div>
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-layer-group"></i> Modules: <?php echo $course['completed_modules']; ?>/<?php echo $course['total_modules']; ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-video"></i> Videos: <?php echo $course['watched_videos']; ?>/<?php echo $course['total_videos']; ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clipboard-check"></i> Assessments: <?php echo $course['completed_assessments']; ?>/<?php echo $course['total_assessments']; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <div class="progress mb-2">
                                                    <div class="progress-bar" style="width: <?php echo $course_module_progress; ?>%"></div>
                                                </div>
                                                
                                                <?php if ($course['average_score']): ?>
                                                    <small class="text-muted">
                                                        Average Score: <?php echo round($course['average_score'], 1); ?>%
                                                    </small>
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
    </script>
</body>
</html> 