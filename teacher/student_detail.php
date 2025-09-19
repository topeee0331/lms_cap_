<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

$student_id = (int)($_GET['id'] ?? 0);
$course_id = (int)($_GET['course'] ?? 0);

// Video watch requirements configuration
$MIN_WATCH_DURATION = 30; // Minimum 30 seconds must be watched
$VIDEO_COMPLETION_THRESHOLD = 0.8; // 80% completion for "fully watched" status
$ESTIMATED_VIDEO_DURATION = 300; // Estimated 5 minutes per video (since actual duration not stored)

// Verify teacher has access to this student and course
$stmt = $pdo->prepare("
    SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at as user_created, u.identifier as neust_student_id,
           c.course_name, c.course_code, c.description as course_description, 
           COALESCE(s.section_name, 'Not Assigned') as section_name, 
           COALESCE(s.year_level, 'N/A') as section_year,
           e.enrolled_at, e.status
    FROM course_enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE u.id = ? AND c.id = ? AND c.teacher_id = ?
");
$stmt->execute([$student_id, $course_id, $_SESSION['user_id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    $_SESSION['message'] = 'Student not found or access denied.';
    $_SESSION['message_type'] = 'danger';
    header('Location: students.php');
    exit();
}

$page_title = 'Student Course Progress';
require_once '../includes/header.php';

// Get student's assessment attempts with recalculated scores (normalized schema)
$stmt = $pdo->prepare("
    SELECT aa.*, a.assessment_title, a.difficulty, a.course_id,
           COALESCE(JSON_LENGTH(aa.answers), 0) as questions_answered,
           COALESCE(aa.score, 0) as calculated_points_earned,
           COALESCE(aa.max_score, a.num_questions, 0) as total_possible_points
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    WHERE aa.student_id = ? AND a.course_id = ? AND aa.status = 'completed'
    ORDER BY aa.completed_at DESC
");
$stmt->execute([$student_id, $course_id]);
$assessments = $stmt->fetchAll();



// Get modules from courses.modules JSON (normalized schema)
$stmt = $pdo->prepare("SELECT modules FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$modules_json = $stmt->fetchColumn();
$modules = [];
if ($modules_json) {
	$decoded_modules = json_decode($modules_json, true);
	if (is_array($decoded_modules)) {
		foreach ($decoded_modules as $index => $mod) {
			$modules[] = [
				'module_title' => isset($mod['module_title']) ? $mod['module_title'] : (isset($mod['title']) ? $mod['title'] : ('Module ' . ($index + 1))),
				'description' => isset($mod['description']) ? $mod['description'] : '',
				'is_completed' => false,
				'completed_at' => null,
				'watched_videos' => 0,
				'total_videos' => 0
			];
		}
	}
}

// Get recent activities (assessments only in normalized schema)
$stmt = $pdo->prepare("
    SELECT 'assessment' as type, aa.completed_at as activity_date,
           CONCAT('Completed assessment: ', a.assessment_title) as description,
           aa.score as score
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    WHERE aa.student_id = ? AND a.course_id = ? AND aa.status = 'completed'
    ORDER BY aa.completed_at DESC
    LIMIT 20
");
$stmt->execute([$student_id, $course_id]);
$activities = $stmt->fetchAll();

// Calculate overall statistics
$total_assessments = count($assessments);
$calculated_scores = [];
foreach ($assessments as $assessment) {
    $calculated_score = $assessment['total_possible_points'] > 0 ? 
        round(($assessment['calculated_points_earned'] / $assessment['total_possible_points']) * 100) : 0;
    $calculated_scores[] = $calculated_score;
}
$average_score = $total_assessments > 0 ? array_sum($calculated_scores) / $total_assessments : 0;
$completed_modules = count(array_filter($modules, function($m) { return $m['is_completed']; }));
$total_modules = count($modules);
$total_videos_watched = array_sum(array_column($modules, 'watched_videos'));
$total_videos = array_sum(array_column($modules, 'total_videos'));

// Calculate actual watch time vs total video duration
$total_watch_time = array_sum(array_column($modules, 'total_watch_time'));
$total_video_duration = array_sum(array_column($modules, 'total_video_duration'));
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Student Course Progress</h1>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars(($enrollment['first_name'] ?? '') . ' ' . ($enrollment['last_name'] ?? '')); ?> • 
                        <?php echo htmlspecialchars($enrollment['course_name']); ?> (<?php echo htmlspecialchars($enrollment['course_code']); ?>)
                    </p>
                </div>
                <div class="btn-group">
                    <a href="students.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Students
                    </a>
                    <a href="leaderboard.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-info">
                        <i class="bi bi-trophy me-1"></i>Leaderboard
                    </a>
                    <a href="student_progress.php?id=<?php echo $student_id; ?>&course=<?php echo $course_id; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-graph-up me-1"></i>Progress Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                                         <div class="d-flex align-items-center mb-3">
                         <img src="<?php echo getProfilePictureUrl($enrollment['profile_picture'] ?? null, 'large'); ?>" 
                              class="rounded-circle me-3" width="80" height="80" alt="Profile">
                        <div>
                            <h4 class="mb-1"><?php echo htmlspecialchars(($enrollment['first_name'] ?? '') . ' ' . ($enrollment['last_name'] ?? '')); ?></h4>
                            <p class="text-muted mb-1"><?php echo htmlspecialchars($enrollment['email'] ?? ''); ?></p>
                            <p class="text-muted mb-0">
                                <?php if (!empty($enrollment['neust_student_id'])): ?>
                                    Student ID: <span class="badge bg-primary"><?php echo htmlspecialchars($enrollment['neust_student_id']); ?></span>
                                <?php else: ?>
                                    Student ID: <span class="badge bg-warning">Not Assigned</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Course:</strong> <?php echo htmlspecialchars($enrollment['course_name'] ?? ''); ?><br>
                            <strong>Course Code:</strong> <?php echo htmlspecialchars($enrollment['course_code'] ?? ''); ?><br>
                            <strong>Enrolled:</strong> <?php echo $enrollment['enrolled_at'] ? date('M j, Y', strtotime($enrollment['enrolled_at'])) : 'N/A'; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> 
                            <?php if ($enrollment['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?><br>
                            <strong>Member Since:</strong> <?php echo $enrollment['user_created'] ? date('M j, Y', strtotime($enrollment['user_created'])) : 'N/A'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-2"><?php echo number_format($average_score, 1); ?>%</h3>
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar bg-<?php echo $average_score >= 90 ? 'success' : ($average_score >= 70 ? 'info' : ($average_score >= 50 ? 'warning' : 'danger')); ?>" 
                             style="width: <?php echo $average_score; ?>%">
                        </div>
                    </div>
                    <small class="text-muted">Average Assessment Score</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $total_assessments; ?></h4>
                            <small>Assessments Taken</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clipboard-check fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $completed_modules; ?>/<?php echo $total_modules; ?></h4>
                            <small>Modules Completed</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $total_videos_watched; ?>/<?php echo $total_videos; ?></h4>
                            <small>Videos Watched</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-play-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>%</h4>
                            <small>Course Progress</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Module Progress -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-layer-group me-2"></i>Course Module Progress
                    </h5>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary me-2">
                            <i class="fas fa-play-circle me-1"></i><?php echo $total_modules; ?> Total Modules
                        </span>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i><?php echo $completed_modules; ?> Completed
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($modules)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-folder-x fs-1 text-muted mb-3"></i>
                            <h6>No Modules Found</h6>
                            <p class="text-muted">This course doesn't have any modules yet.</p>
                        </div>
                    <?php else: ?>

                        
                        <!-- Video Watch Requirements Info -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="alert alert-info border-0">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-info-circle fs-4 me-3 text-info"></i>
                                        <div>
                                            <h6 class="mb-1">Video Watch Requirements</h6>
                                            <p class="mb-0 small">
                                                Videos are only counted as "watched" when students meet these criteria:
                                                <strong>Minimum <span id="currentMinDuration"><?php echo $MIN_WATCH_DURATION; ?></span> seconds</strong> of actual viewing time must be watched.
                                                This ensures accurate progress tracking and prevents students from just clicking play without watching.
                                                <em>Note: Since video duration is not stored, we use a fixed minimum watch time requirement.</em>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Module Progress Summary -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="progress-summary-card bg-light p-3 rounded">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0">Overall Course Progress</h6>
                                        <span class="badge bg-primary"><?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 20px; border-radius: 10px;">
                                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" 
                                             style="width: <?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>%; transition: width 1s ease;">
                                            <span class="progress-text fw-bold"><?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>%</span>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-2 d-block">
                                        <?php echo $completed_modules; ?> of <?php echo $total_modules; ?> modules completed
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progress-summary-card bg-light p-3 rounded">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0">Video Completion</h6>
                                        <span class="badge bg-info"><?php echo $total_videos > 0 ? round(($total_videos_watched / $total_videos) * 100) : 0; ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 20px; border-radius: 10px;">
                                        <div class="progress-bar bg-info progress-bar-striped progress-bar-animated" 
                                             style="width: <?php echo $total_videos > 0 ? round(($total_videos_watched / $total_videos) * 100) : 0; ?>%; transition: width 1s ease;">
                                            <span class="progress-text fw-bold"><?php echo $total_videos > 0 ? round(($total_videos_watched / $total_videos) * 100) : 0; ?>%</span>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-2 d-block">
                                        <?php echo $total_videos_watched; ?> of <?php echo $total_videos; ?> videos watched
                                    </small>
                                    <?php if ($total_video_duration > 0): ?>
                                        <small class="text-info d-block">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo gmdate('H:i:s', $total_watch_time); ?> / <?php echo gmdate('H:i:s', $total_video_duration); ?> total time
                                        </small>
                                        <small class="text-muted">
                                            (<?php echo round(($total_watch_time / $total_video_duration) * 100); ?>% of estimated duration)
                                        </small>
                                        <small class="text-warning d-block">
                                            <i class="fas fa-info-circle me-1"></i>Duration is estimated (5 min per video)
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th><i class="fas fa-layer-group me-1"></i>Module</th>
                                        <th><i class="fas fa-chart-line me-1"></i>Progress</th>
                                        <th><i class="fas fa-video me-1"></i>Videos</th>
                                        <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                        <th><i class="fas fa-calendar-check me-1"></i>Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modules as $module): ?>
                                        <tr class="module-progress-row">
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($module['module_title'] ?? ''); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($module['description'] ?? ''); ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php 
                                                    // Calculate progress percentage - if module is completed, show 100%, otherwise calculate from videos
                                                    if ($module['is_completed']) {
                                                        $overall_progress = 100;
                                                        $progress_color = 'bg-success';
                                                        $progress_text = '100%';
                                                    } else {
                                                        // Calculate video-based progress only if module is not completed
                                                        // Only count videos that meet the watch duration threshold
                                                        $video_progress = $module['total_videos'] > 0 ? ($module['watched_videos'] / $module['total_videos']) * 100 : 0;
                                                        $overall_progress = $video_progress;
                                                        $progress_color = $overall_progress >= 90 ? 'bg-success' : ($overall_progress >= 70 ? 'bg-info' : ($overall_progress >= 50 ? 'bg-warning' : 'bg-danger'));
                                                        $progress_text = round($overall_progress) . '%';
                                                    }
                                                    ?>
                                                    <div class="progress flex-grow-1 me-2" style="height: 12px; border-radius: 6px;">
                                                        <div class="progress-bar <?php echo $progress_color; ?> progress-bar-striped progress-bar-animated" 
                                                             style="width: <?php echo $overall_progress; ?>%; transition: width 0.6s ease;">
                                                        </div>
                                                    </div>
                                                    <div class="text-center" style="min-width: 45px;">
                                                        <small class="fw-bold <?php echo $progress_color === 'bg-success' ? 'text-success' : ($progress_color === 'bg-info' ? 'text-info' : ($progress_color === 'bg-warning' ? 'text-warning' : 'text-danger')); ?>">
                                                            <?php echo $progress_text; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        <?php if ($module['is_completed']): ?>
                                                            <span class="text-success"><i class="fas fa-check-circle me-1"></i>Module completed - All requirements met</span>
                                                        <?php else: ?>
                                                            <i class="fas fa-video me-1"></i><?php echo $module['watched_videos']; ?>/<?php echo $module['total_videos']; ?> videos
                                                            <span class="ms-2 text-info"><i class="fas fa-info-circle me-1"></i>Video progress</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column align-items-start">
                                                    <?php if ($module['is_completed']): ?>
                                                        <span class="badge bg-success fs-6 px-3 py-2 mb-1">
                                                            <i class="fas fa-check-circle me-1"></i>All Videos Watched
                                                        </span>
                                                        <small class="text-success">
                                                            <i class="fas fa-video me-1"></i><?php echo $module['total_videos']; ?> videos completed
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-info fs-6 px-3 py-2 mb-1">
                                                            <i class="fas fa-video me-1"></i><?php echo $module['watched_videos']; ?>/<?php echo $module['total_videos']; ?>
                                                        </span>
                                                        <?php if ($module['total_videos'] > 0): ?>
                                                            <small class="text-muted">
                                                                <?php echo round(($module['watched_videos'] / $module['total_videos']) * 100); ?>% watched
                                                            </small>
                                                            <small class="text-info d-block">
                                                                <i class="fas fa-clock me-1"></i>Min: <?php echo $MIN_WATCH_DURATION; ?> seconds
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($module['is_completed']): ?>
                                                    <span class="badge bg-success fs-6 px-3 py-2">
                                                        <i class="fas fa-check-circle me-1"></i>Completed
                                                    </span>
                                                <?php else: ?>
                                                    <?php 
                                                    // Calculate video progress for status display
                                                    $video_progress = $module['total_videos'] > 0 ? ($module['watched_videos'] / $module['total_videos']) * 100 : 0;
                                                    
                                                    // Determine status based on video progress
                                                    if ($video_progress >= 70) {
                                                        $status_color = 'bg-warning';
                                                        $status_text = 'Almost Done';
                                                        $status_icon = 'fas fa-clock';
                                                    } elseif ($video_progress >= 30) {
                                                        $status_color = 'bg-info';
                                                        $status_text = 'In Progress';
                                                        $status_icon = 'fas fa-play-circle';
                                                    } else {
                                                        $status_color = 'bg-secondary';
                                                        $status_text = 'Just Started';
                                                        $status_icon = 'fas fa-flag';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_color; ?> fs-6 px-3 py-2">
                                                        <i class="<?php echo $status_icon; ?> me-1"></i><?php echo $status_text; ?>
                                                    </span>
                                                    <small class="d-block mt-1 text-muted">
                                                        <?php echo round($video_progress); ?>% video progress
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($module['completed_at']): ?>
                                                    <small><?php echo date('M j, Y', strtotime($module['completed_at'])); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">Not completed</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Assessment History -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Course Assessment History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assessments)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clipboard-x fs-1 text-muted mb-3"></i>
                            <h6>No Assessments Taken</h6>
                            <p class="text-muted">This student hasn't taken any assessments yet.</p>
                            

                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Assessment</th>
                                        <th>Module</th>
                                        <th>Score</th>
                                        <th>Difficulty</th>
                                        <th>Time Taken</th>
                                        <th>Completed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assessments as $assessment): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($assessment['assessment_title'] ?? ''); ?></div>
                                                <small class="text-muted"><?php echo $assessment['questions_answered']; ?> questions answered</small>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($assessment['module_title'] ?? ''); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $calculated_score = $assessment['total_possible_points'] > 0 ? 
                                                    round(($assessment['calculated_points_earned'] / $assessment['total_possible_points']) * 100) : 0;
                                                ?>
                                                <span class="badge bg-<?php echo $calculated_score >= 90 ? 'success' : ($calculated_score >= 70 ? 'info' : ($calculated_score >= 50 ? 'warning' : 'danger')); ?>">
                                                    <?php echo $calculated_score; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $assessment['difficulty'] === 'easy' ? 'success' : ($assessment['difficulty'] === 'medium' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($assessment['difficulty']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($assessment['completed_at'] && $assessment['started_at']) {
                                                    $time_taken_seconds = strtotime($assessment['completed_at']) - strtotime($assessment['started_at']);
                                                    $hours = floor($time_taken_seconds / 3600);
                                                    $minutes = floor(($time_taken_seconds % 3600) / 60);
                                                    $seconds = $time_taken_seconds % 60;
                                                    
                                                    if ($hours > 0) {
                                                        echo sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
                                                    } elseif ($minutes > 0) {
                                                        echo sprintf('%dm %ds', $minutes, $seconds);
                                                    } else {
                                                        echo sprintf('%ds', $seconds);
                                                    }
                                                } else {
                                                    echo '<span class="text-muted">N/A</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($assessment['completed_at']): ?>
                                                    <small><?php echo date('M j, Y g:i A', strtotime($assessment['completed_at'])); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">Not completed</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="attempt_detail.php?id=<?php echo $assessment['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-Time Progress Updates -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-sync-alt me-2"></i>Real-Time Progress
                    </h5>
                    <div class="d-flex align-items-center">
                        <div class="form-check form-switch me-3">
                            <input class="form-check-input" type="checkbox" id="autoRefreshToggle" checked>
                            <label class="form-check-label" for="autoRefreshToggle">
                                Auto-refresh
                            </label>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="manualRefresh()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh Now
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="progress-stat">
                                <div class="progress-stat-icon bg-primary">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="progress-stat-info">
                                    <h6 class="progress-stat-number" id="realTimeAssessments"><?php echo $total_assessments; ?></h6>
                                    <small class="progress-stat-label">Assessments Taken</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="progress-stat">
                                <div class="progress-stat-icon bg-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="progress-stat-info">
                                    <h6 class="progress-stat-number" id="realTimeModules"><?php echo $completed_modules; ?>/<?php echo $total_modules; ?></h6>
                                    <small class="progress-stat-label">Modules Completed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="progress-stat">
                                <div class="progress-stat-icon bg-info">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                                <div class="progress-stat-info">
                                    <h6 class="progress-stat-number" id="realTimeVideos"><?php echo $total_videos_watched; ?>/<?php echo $total_videos; ?></h6>
                                    <small class="progress-stat-label">Videos Watched</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="progress-stat">
                                <div class="progress-stat-icon bg-warning">
                                    <i class="fas fa-graph-up"></i>
                                </div>
                                <div class="progress-stat-info">
                                    <h6 class="progress-stat-number" id="realTimeProgress"><?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>%</h6>
                                    <small class="progress-stat-label">Course Progress</small>
                                </div>
                            </div>
                        </div>
                    </div>
                                            <div class="mt-3">
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-success" id="overallProgressBar" 
                                     style="width: <?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>%">
                                    <span class="progress-text" id="overallProgressText">
                                        <?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>%
                                    </span>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                Last updated: <span id="lastUpdated"><?php echo date('M j, Y g:i:s A'); ?></span>
                                <span class="ms-2" id="refreshStatus">
                                    <i class="fas fa-circle text-success" id="statusIndicator"></i>
                                    <span id="statusText">Live</span>
                                </span>
                            </small>
                        </div>
                        
                        <!-- Live Activity Indicator -->
                        <div class="mt-3" id="liveActivityIndicator" style="display: none;">
                            <div class="alert alert-info alert-dismissible fade show py-2" role="alert">
                                <i class="fas fa-broadcast-tower me-2"></i>
                                <strong>Live Update:</strong> 
                                <span id="liveActivityText">Student activity detected</span>
                                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                            </div>
                        </div>
                        
                        <!-- Progress Trend -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Progress Trend</small>
                                <small class="text-muted" id="trendIndicator">
                                    <i class="fas fa-arrow-up text-success"></i> Improving
                                </small>
                            </div>
                            <div class="progress-trend-chart" id="progressTrendChart">
                                <div class="trend-bar" style="height: 20px; background: linear-gradient(90deg, #e9ecef 0%, #28a745 100%); border-radius: 4px; position: relative;">
                                    <div class="trend-marker" id="trendMarker" style="position: absolute; top: -5px; width: 10px; height: 30px; background: #dc3545; border-radius: 5px; transform: translateX(-50%);"></div>
                                </div>
                                <small class="text-muted mt-1 d-block">
                                    <span id="trendText">Course progress: <?php echo $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0; ?>%</span>
                                </small>
                            </div>
                        </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Course Activities</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-activity fs-1 text-muted mb-3"></i>
                            <h6>No Recent Activities</h6>
                            <p class="text-muted">This student hasn't had any recent activity in this course.</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($activities as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?php echo $activity['type'] === 'assessment' ? 'primary' : ($activity['type'] === 'module' ? 'success' : 'info'); ?>">
                                        <i class="bi bi-<?php echo $activity['type'] === 'assessment' ? 'clipboard-check' : ($activity['type'] === 'module' ? 'check-circle' : 'play-circle'); ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <p class="mb-1"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></p>
                                                <?php if ($activity['score']): ?>
                                                    <span class="badge bg-<?php echo $activity['score'] >= 90 ? 'success' : ($activity['score'] >= 70 ? 'info' : ($activity['score'] >= 50 ? 'warning' : 'danger')); ?>">
                                                        <?php echo $activity['score']; ?>%
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted"><?php echo $activity['activity_date'] ? date('M j, Y g:i A', strtotime($activity['activity_date'])) : 'N/A'; ?></small>
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
</div>

<style>
/* Progress Stats Styling */
.progress-stat {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.progress-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.progress-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    color: white;
    font-size: 1.2rem;
}

.progress-stat-info {
    flex: 1;
}

.progress-stat-number {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
}

.progress-stat-label {
    color: #6c757d;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.progress {
    background: #e9ecef;
    border-radius: 12px;
    overflow: hidden;
}

.progress-bar {
    transition: width 1s ease-in-out;
    position: relative;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

/* Timeline Styling */
.timeline {
    position: relative;
    padding-left: 30px;
}

/* Live Activity Indicator */
#liveActivityIndicator .alert {
    border-left: 4px solid #17a2b8;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.8; }
    100% {opacity: 1; }
}

/* Status Indicator */
#refreshStatus {
    font-size: 0.8rem;
}

#statusIndicator {
    animation: blink 2s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

/* Progress Stats Animation */
.progress-stat {
    animation: slideInUp 0.5s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Module Progress Styling */
.progress-summary-card {
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.progress-summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: #007bff;
}

.progress {
    background: #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

.progress-bar {
    transition: width 1s ease-in-out;
    position: relative;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.progress-bar-striped {
    background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent);
    background-size: 1rem 1rem;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
}

/* Table Styling */
.table-dark {
    background: linear-gradient(135deg, #343a40 0%, #495057 100%);
    color: white;
}

.table-dark th {
    border-bottom: 2px solid #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transform: scale(1.01);
    transition: all 0.2s ease;
}

/* Module Progress Row Styling */
.module-progress-row {
    transition: all 0.3s ease;
}

.module-progress-row:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-left: 4px solid #007bff;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #dee2e6;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: -20px;
    top: 30px;
    bottom: -20px;
    width: 2px;
    background: #dee2e6;
}
</style>

<!-- Real-time Progress Update Script -->
<script>
let autoRefreshInterval;
let isAutoRefreshEnabled = true;

// Initialize auto-refresh
document.addEventListener('DOMContentLoaded', function() {
    const autoRefreshToggle = document.getElementById('autoRefreshToggle');
    
    // Set up auto-refresh toggle
    autoRefreshToggle.addEventListener('change', function() {
        isAutoRefreshEnabled = this.checked;
        if (isAutoRefreshEnabled) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });
    
    // Start auto-refresh if enabled
    if (isAutoRefreshEnabled) {
        startAutoRefresh();
    }
});

// Start auto-refresh (every 15 seconds for more real-time feel)
function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    autoRefreshInterval = setInterval(refreshProgress, 15000); // 15 seconds
    console.log('Auto-refresh started');
    updateStatusIndicator('live');
}

// Stop auto-refresh
function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    console.log('Auto-refresh stopped');
    updateStatusIndicator('paused');
}

// Update status indicator
function updateStatusIndicator(status) {
    const statusIndicator = document.getElementById('statusIndicator');
    const statusText = document.getElementById('statusText');
    
    if (statusIndicator && statusText) {
        if (status === 'live') {
            statusIndicator.className = 'fas fa-circle text-success';
            statusText.textContent = 'Live';
            statusText.className = 'text-success';
        } else if (status === 'paused') {
            statusIndicator.className = 'fas fa-circle text-warning';
            statusText.textContent = 'Paused';
            statusText.className = 'text-warning';
        } else if (status === 'error') {
            statusIndicator.className = 'fas fa-circle text-danger';
            statusText.textContent = 'Error';
            statusText.className = 'text-danger';
        }
    }
}

// Refresh progress data
async function refreshProgress() {
    try {
        const response = await fetch(`ajax_get_student_progress.php?student_id=<?php echo $student_id; ?>&course_id=<?php echo $course_id; ?>`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            updateProgressDisplay(data.progress);
            updateLastUpdated();
            
            // Show success indicator
            showRefreshIndicator(true);
            updateStatusIndicator('live');
        } else {
            console.error('Failed to refresh progress:', data.message);
            showRefreshIndicator(false);
            updateStatusIndicator('error');
        }
    } catch (error) {
        console.error('Error refreshing progress:', error);
        showRefreshIndicator(false);
        updateStatusIndicator('error');
    }
}

// Store previous progress data for comparison
let previousProgress = null;

// Update progress display with new data
function updateProgressDisplay(progress) {
    // Check for changes and show live activity
    if (previousProgress) {
        const changes = detectProgressChanges(previousProgress, progress);
        if (changes.length > 0) {
            showLiveActivity(changes);
        }
    }
    
    // Store current progress for next comparison
    previousProgress = { ...progress };
    
    // Update assessment count
    const assessmentsElement = document.getElementById('realTimeAssessments');
    if (assessmentsElement) {
        assessmentsElement.textContent = progress.total_assessments;
    }
    
    // Update module completion
    const modulesElement = document.getElementById('realTimeModules');
    if (modulesElement) {
        modulesElement.textContent = `${progress.completed_modules}/${progress.total_modules}`;
    }
    
    // Update video count
    const videosElement = document.getElementById('realTimeVideos');
    if (videosElement) {
        videosElement.textContent = `${progress.total_videos_watched}/${progress.total_videos}`;
    }
    
    // Update overall progress
    const progressElement = document.getElementById('realTimeProgress');
    const progressBar = document.getElementById('overallProgressBar');
    const progressText = document.getElementById('overallProgressText');
    
    if (progressElement && progressBar && progressText) {
        const progressPercentage = progress.total_modules > 0 ? 
            Math.round((progress.completed_modules / progress.total_modules) * 100) : 0;
        
        progressElement.textContent = `${progressPercentage}%`;
        progressBar.style.width = `${progressPercentage}%`;
        progressText.textContent = `${progressPercentage}%`;
        
        // Update progress bar color based on percentage
        progressBar.className = `progress-bar ${getProgressBarColor(progressPercentage)}`;
        
        // Update trend indicator
        updateTrendIndicator(progressPercentage);
    }
}

// Detect changes in progress data
function detectProgressChanges(oldProgress, newProgress) {
    const changes = [];
    
    if (oldProgress.total_assessments !== newProgress.total_assessments) {
        const diff = newProgress.total_assessments - oldProgress.total_assessments;
        if (diff > 0) {
            changes.push(`Completed ${diff} new assessment${diff > 1 ? 's' : ''}`);
        }
    }
    
    if (oldProgress.completed_modules !== newProgress.completed_modules) {
        const diff = newProgress.completed_modules - oldProgress.completed_modules;
        if (diff > 0) {
            changes.push(`Completed ${diff} new module${diff > 1 ? 's' : ''}`);
        }
    }
    
    if (oldProgress.total_videos_watched !== newProgress.total_videos_watched) {
        const diff = newProgress.total_videos_watched - oldProgress.total_videos_watched;
        if (diff > 0) {
            changes.push(`Watched ${diff} new video${diff > 1 ? 's' : ''}`);
        }
    }
    
    if (oldProgress.course_progress !== newProgress.course_progress) {
        const diff = newProgress.course_progress - oldProgress.course_progress;
        if (diff > 0) {
            changes.push(`Course progress increased by ${diff}%`);
        }
    }
    
    return changes;
}

// Show live activity notification
function showLiveActivity(changes) {
    const liveActivityIndicator = document.getElementById('liveActivityIndicator');
    const liveActivityText = document.getElementById('liveActivityText');
    
    if (liveActivityIndicator && liveActivityText) {
        liveActivityText.textContent = changes.join(', ');
        liveActivityIndicator.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            liveActivityIndicator.style.display = 'none';
        }, 5000);
    }
}

// Update trend indicator
function updateTrendIndicator(progressPercentage) {
    const trendMarker = document.getElementById('trendMarker');
    const trendIndicator = document.getElementById('trendIndicator');
    const trendText = document.getElementById('trendText');
    
    if (trendMarker && trendIndicator && trendText) {
        // Position marker based on progress percentage
        trendMarker.style.left = `${progressPercentage}%`;
        
        // Update trend text
        trendText.textContent = `Course progress: ${progressPercentage}%`;
        
        // Update trend indicator based on progress
        if (progressPercentage >= 90) {
            trendIndicator.innerHTML = '<i class="fas fa-trophy text-warning"></i> Excellent';
            trendIndicator.className = 'text-muted text-warning';
        } else if (progressPercentage >= 70) {
            trendIndicator.innerHTML = '<i class="fas fa-arrow-up text-success"></i> Good Progress';
            trendIndicator.className = 'text-muted text-success';
        } else if (progressPercentage >= 50) {
            trendIndicator.innerHTML = '<i class="fas fa-arrow-up text-info"></i> Improving';
            trendIndicator.className = 'text-muted text-info';
        } else {
            trendIndicator.innerHTML = '<i class="fas fa-arrow-down text-warning"></i> Needs Attention';
            trendIndicator.className = 'text-muted text-warning';
        }
    }
}

// Get progress bar color based on percentage
function getProgressBarColor(percentage) {
    if (percentage >= 90) return 'bg-success';
    if (percentage >= 70) return 'bg-info';
    if (percentage >= 50) return 'bg-warning';
    return 'bg-danger';
}

// Update last updated timestamp
function updateLastUpdated() {
    const lastUpdatedElement = document.getElementById('lastUpdated');
    if (lastUpdatedElement) {
        const now = new Date();
        lastUpdatedElement.textContent = now.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
    }
}

// Show refresh indicator
function showRefreshIndicator(success) {
    const refreshBtn = document.querySelector('button[onclick="manualRefresh()"]');
    if (refreshBtn) {
        const originalText = refreshBtn.innerHTML;
        const originalClass = refreshBtn.className;
        
        if (success) {
            refreshBtn.innerHTML = '<i class="fas fa-check me-1"></i>Updated!';
            refreshBtn.className = 'btn btn-sm btn-success';
        } else {
            refreshBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Error';
            refreshBtn.className = 'btn btn-sm btn-danger';
        }
        
        // Reset button after 2 seconds
        setTimeout(() => {
            refreshBtn.innerHTML = originalText;
            refreshBtn.className = originalClass;
        }, 2000);
    }
}

// Manual refresh function
function manualRefresh() {
    showRefreshIndicator(false);
    refreshProgress();
}

// Animate progress bars on page load
function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach((bar, index) => {
        setTimeout(() => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        }, index * 200);
    });
}

// Initialize progress bar animations
document.addEventListener('DOMContentLoaded', function() {
    animateProgressBars();
});


</script>

<?php require_once '../includes/footer.php'; ?> 