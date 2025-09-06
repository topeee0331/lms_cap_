<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    redirectWithMessage('login.php', 'Please log in as a teacher.', 'danger');
}

$student_id = (int)($_GET['id'] ?? 0);
$course_id = (int)($_GET['course'] ?? 0);

// Verify teacher has access to this student and course
$stmt = $db->prepare("
    SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at as user_created, u.identifier as neust_student_id,
           c.course_name, c.course_code, c.description as course_description,
           COALESCE(s.section_name, 'Not Assigned') as section_name, COALESCE(s.year_level, 'N/A') as section_year,
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
    redirectWithMessage('students.php', 'Student not found or access denied.', 'danger');
}

$page_title = 'Student Progress Report';
require_once '../includes/header.php';

// Get assessment performance over time
$stmt = $db->prepare("
    SELECT aa.score, aa.completed_at, a.assessment_title, a.difficulty
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    WHERE aa.student_id = ? AND a.course_id = ? AND aa.status = 'completed'
    ORDER BY aa.completed_at ASC
");
$stmt->execute([$student_id, $course_id]);
$assessment_history = $stmt->fetchAll();

// Get module completion timeline
$module_completions = [];

// Get video watching patterns
$video_views = [];

// Get performance by difficulty
$stmt = $db->prepare("
    SELECT a.difficulty, 
           COUNT(aa.id) as attempts,
           AVG(aa.score) as average_score,
           MIN(aa.score) as lowest_score,
           MAX(aa.score) as highest_score
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    WHERE aa.student_id = ? AND a.course_id = ? AND aa.status = 'completed'
    GROUP BY a.difficulty
");
$stmt->execute([$student_id, $course_id]);
$difficulty_stats = $stmt->fetchAll();

// Get weekly activity
$stmt = $db->prepare("\n    SELECT DATE(aa.completed_at) as activity_date,\n           COUNT(aa.id) as assessments,\n           0 as modules_completed,\n           0 as videos_watched\n    FROM assessment_attempts aa\n    JOIN assessments a ON aa.assessment_id = a.id\n    WHERE aa.student_id = ? AND a.course_id = ? AND aa.status = 'completed'\n      AND aa.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)\n    GROUP BY DATE(aa.completed_at)\n    ORDER BY activity_date DESC\n");
$stmt->execute([$student_id, $course_id]);
$weekly_activity = $stmt->fetchAll();

// Calculate overall statistics
$total_assessments = count($assessment_history);
$average_score = $total_assessments > 0 ? array_sum(array_column($assessment_history, 'score')) / $total_assessments : 0;
$total_modules_completed = count($module_completions);
$total_videos_watched = count($video_views);
$total_watch_time = array_sum(array_column($video_views, 'watch_duration'));
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Progress Report</h1>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?> • 
                        <?php echo htmlspecialchars($enrollment['course_name']); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="student_detail.php?id=<?php echo $student_id; ?>&course=<?php echo $course_id; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Student
                    </a>
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Info Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                                         <div class="d-flex align-items-center">
                         <img src="<?php echo getProfilePictureUrl($enrollment['profile_picture'] ?? null, 'large'); ?>" 
                              class="rounded-circle me-3" width="60" height="60" alt="Profile">
                        <div class="flex-grow-1">
                            <h5 class="mb-1"><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></h5>
                            <p class="text-muted mb-0">
                                <?php echo htmlspecialchars($enrollment['email']); ?> • 
                                Student ID: <?php echo htmlspecialchars($enrollment['student_id']); ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <div class="h4 mb-0"><?php echo number_format($average_score, 1); ?>%</div>
                            <small class="text-muted">Average Score</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-2">
                    <h5 class="mb-1"><?php echo $total_assessments; ?></h5>
                    <small>Assessments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center py-2">
                    <h5 class="mb-1"><?php echo $total_modules_completed; ?></h5>
                    <small>Modules</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center py-2">
                    <h5 class="mb-1"><?php echo $total_videos_watched; ?></h5>
                    <small>Videos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center py-2">
                    <h5 class="mb-1"><?php echo round($total_watch_time / 60, 1); ?></h5>
                    <small>Hours</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Assessment Performance Chart -->
    <?php if (!empty($assessment_history)): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0">Assessment Performance Over Time</h6>
                </div>
                <div class="card-body py-2">
                    <canvas id="assessmentChart" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Performance by Difficulty -->
    <?php if (!empty($difficulty_stats)): ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0">Performance by Difficulty</h6>
                </div>
                <div class="card-body py-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Difficulty</th>
                                    <th>Attempts</th>
                                    <th>Avg Score</th>
                                    <th>Range</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($difficulty_stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $stat['difficulty'] === 'easy' ? 'success' : ($stat['difficulty'] === 'medium' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($stat['difficulty']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $stat['attempts']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $stat['average_score'] >= 90 ? 'success' : ($stat['average_score'] >= 70 ? 'info' : ($stat['average_score'] >= 50 ? 'warning' : 'danger')); ?>">
                                                <?php echo number_format($stat['average_score'], 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo $stat['lowest_score']; ?>% - <?php echo $stat['highest_score']; ?>%</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0">Score Distribution</h6>
                </div>
                <div class="card-body py-2">
                    <canvas id="scoreDistributionChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Weekly Activity -->
    <?php if (!empty($weekly_activity)): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0">Weekly Activity (Last 30 Days)</h6>
                </div>
                <div class="card-body py-2">
                    <canvas id="weeklyActivityChart" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Module Completion Timeline -->
    <?php if (!empty($module_completions)): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0">Module Completion Timeline</h6>
                </div>
                <div class="card-body py-2">
                    <div class="timeline">
                        <?php foreach ($module_completions as $module): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($module['module_title']); ?></h6>
                                            <p class="mb-0 text-muted small">Module completed with <?php echo $module['progress_percentage']; ?>% progress</p>
                                        </div>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($module['completed_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Video Activity -->
    <?php if (!empty($video_views)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0">Recent Video Activity</h6>
                </div>
                <div class="card-body py-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Video</th>
                                    <th>Watched</th>
                                    <th>Duration</th>
                                    <th>Completion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($video_views, 0, 5) as $video): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold small"><?php echo htmlspecialchars($video['video_title']); ?></div>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y g:i A', strtotime($video['watched_at'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo round($video['watch_duration'] / 60, 1); ?> min</span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $video['completion_percentage']; ?>%">
                                                    <?php echo $video['completion_percentage']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 25px;
}

.timeline-item {
    position: relative;
    margin-bottom: 15px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 6px;
    border-left: 3px solid #dee2e6;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: -17px;
    top: 25px;
    bottom: -15px;
    width: 2px;
    background: #dee2e6;
}

@media print {
    .btn-group, .btn {
        display: none !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Assessment Performance Chart
<?php if (!empty($assessment_history)): ?>
const assessmentCtx = document.getElementById('assessmentChart').getContext('2d');
new Chart(assessmentCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($a) { return date('M j', strtotime($a['completed_at'])); }, $assessment_history)); ?>,
        datasets: [{
            label: 'Assessment Score (%)',
            data: <?php echo json_encode(array_column($assessment_history, 'score')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
<?php endif; ?>

// Score Distribution Chart
<?php if (!empty($assessment_history)): ?>
const distributionCtx = document.getElementById('scoreDistributionChart').getContext('2d');
const scores = <?php echo json_encode(array_column($assessment_history, 'score')); ?>;
const scoreRanges = {
    '90-100': scores.filter(s => s >= 90).length,
    '80-89': scores.filter(s => s >= 80 && s < 90).length,
    '70-79': scores.filter(s => s >= 70 && s < 80).length,
    '60-69': scores.filter(s => s >= 60 && s < 70).length,
    '0-59': scores.filter(s => s < 60).length
};

new Chart(distributionCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(scoreRanges),
        datasets: [{
            data: Object.values(scoreRanges),
            backgroundColor: [
                '#28a745',
                '#17a2b8',
                '#ffc107',
                '#fd7e14',
                '#dc3545'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 4,
                    font: {
                        size: 10
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Weekly Activity Chart
<?php if (!empty($weekly_activity)): ?>
const activityCtx = document.getElementById('weeklyActivityChart').getContext('2d');
new Chart(activityCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($a) { return date('M j', strtotime($a['activity_date'])); }, $weekly_activity)); ?>,
        datasets: [{
            label: 'Assessments',
            data: <?php echo json_encode(array_column($weekly_activity, 'assessments')); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.8)'
        }, {
            label: 'Modules Completed',
            data: <?php echo json_encode(array_column($weekly_activity, 'modules_completed')); ?>,
            backgroundColor: 'rgba(40, 167, 69, 0.8)'
        }, {
            label: 'Videos Watched',
            data: <?php echo json_encode(array_column($weekly_activity, 'videos_watched')); ?>,
            backgroundColor: 'rgba(23, 162, 184, 0.8)'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?> 