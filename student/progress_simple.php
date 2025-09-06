<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/score_calculator.php';

$db = new Database();
$pdo = $db->getConnection();
$GLOBALS['db'] = $db;

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get overall progress statistics
$stmt = $pdo->prepare("
    SELECT e.*, c.course_name, c.modules
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = ? AND e.status = 'active'
");
$stmt->execute([$user_id]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_courses = count($enrollments);
$completed_modules = 0;
$total_modules = 0;
$watched_videos = 0;
$total_videos = 0;

foreach ($enrollments as $enrollment) {
    $course_modules = json_decode($enrollment['modules'], true) ?: [];
    $total_modules += count($course_modules);
    
    $module_progress = json_decode($enrollment['module_progress'], true) ?: [];
    $video_progress = json_decode($enrollment['video_progress'] ?? '{}', true) ?: [];
    
    foreach ($module_progress as $module_id => $progress) {
        if (isset($progress['is_completed']) && $progress['is_completed']) {
            $completed_modules++;
        }
    }
    
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

$overall_stats['average_score'] = calculateAverageScore($pdo, $user_id);

$module_progress = $overall_stats['total_modules'] > 0 ? round(($overall_stats['completed_modules'] / $overall_stats['total_modules']) * 100) : 0;
$video_progress = $overall_stats['total_videos'] > 0 ? round(($overall_stats['watched_videos'] / $overall_stats['total_videos']) * 100) : 0;
$assessment_progress = $overall_stats['total_assessments'] > 0 ? round(($overall_stats['completed_assessments'] / $overall_stats['total_assessments']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
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
            <main class="col-12 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Learning Progress</h1>
                </div>

                <!-- Overall Progress -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Module Progress</h5>
                                <p class="card-text"><?php echo $module_progress; ?>%</p>
                                <p class="card-text"><?php echo $overall_stats['completed_modules']; ?> of <?php echo $overall_stats['total_modules']; ?> modules</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-video fa-3x text-info mb-3"></i>
                                <h5 class="card-title">Video Progress</h5>
                                <p class="card-text"><?php echo $video_progress; ?>%</p>
                                <p class="card-text"><?php echo $overall_stats['watched_videos']; ?> of <?php echo $overall_stats['total_videos']; ?> videos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-clipboard-check fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Assessment Progress</h5>
                                <p class="card-text"><?php echo $assessment_progress; ?>%</p>
                                <p class="card-text"><?php echo $overall_stats['completed_assessments']; ?> of <?php echo $overall_stats['total_assessments']; ?> assessments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-star fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Average Score</h5>
                                <p class="card-text"><?php echo $overall_stats['average_score']; ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Progress -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Course Progress</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($enrollments)): ?>
                                    <p class="text-muted">No courses enrolled.</p>
                                <?php else: ?>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <div class="mb-3">
                                            <h6><?php echo htmlspecialchars($enrollment['course_name']); ?></h6>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $enrollment['progress_percentage']; ?>%">
                                                    <?php echo $enrollment['progress_percentage']; ?>%
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
