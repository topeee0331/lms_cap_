<?php
require_once '../config/config.php';
requireRole('teacher');

$assessment_id = (int)($_GET['id'] ?? 0);

// Verify teacher owns this assessment
$stmt = $db->prepare("
    SELECT a.*, c.course_name, c.course_code
    FROM assessments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ? AND c.teacher_id = ?
");
$stmt->execute([$assessment_id, $_SESSION['user_id']]);
$assessment = $stmt->fetch();

if (!$assessment) {
    redirectWithMessage('assessments.php', 'Assessment not found or access denied.', 'danger');
}

$page_title = 'Assessment Results';
require_once '../includes/header.php';

// Get assessment statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT aa.student_id) as total_students,
        COUNT(aa.id) as total_attempts,
        AVG(aa.score) as average_score,
        MIN(aa.score) as lowest_score,
        MAX(aa.score) as highest_score,
        COUNT(CASE WHEN aa.score >= 90 THEN 1 END) as excellent_count,
        COUNT(CASE WHEN aa.score >= 70 AND aa.score < 90 THEN 1 END) as good_count,
        COUNT(CASE WHEN aa.score >= 50 AND aa.score < 70 THEN 1 END) as fair_count,
        COUNT(CASE WHEN aa.score < 50 THEN 1 END) as poor_count
    FROM assessment_attempts aa
    WHERE aa.assessment_id = ? AND aa.status = 'completed'
");
$stmt->execute([$assessment_id]);
$stats = $stmt->fetch();

// Get recent attempts
$stmt = $db->prepare("
    SELECT aa.*, u.first_name, u.last_name, u.email, u.profile_picture
    FROM assessment_attempts aa
    JOIN users u ON aa.student_id = u.id
    WHERE aa.assessment_id = ? AND aa.status = 'completed'
    ORDER BY aa.completed_at DESC
    LIMIT 10
");
$stmt->execute([$assessment_id]);
$recent_attempts = $stmt->fetchAll();

// Get all attempts for detailed view
$stmt = $db->prepare("
    SELECT aa.*, u.first_name, u.last_name, u.email, u.profile_picture
    FROM assessment_attempts aa
    JOIN users u ON aa.student_id = u.id
    WHERE aa.assessment_id = ? AND aa.status = 'completed'
    ORDER BY aa.score DESC, aa.completed_at DESC
");
$stmt->execute([$assessment_id]);
$all_attempts = $stmt->fetchAll();

// Get question statistics
$stmt = $db->prepare("
    SELECT 
        aq.id,
        aq.question_text,
        aq.question_type,
        aq.points,
        COUNT(aa.id) as attempt_count,
        AVG(CASE WHEN aqa.is_correct = 1 THEN 1 ELSE 0 END) * 100 as correct_percentage
    FROM assessment_questions aq
    LEFT JOIN assessment_attempts aa ON aq.assessment_id = aa.assessment_id AND aa.status = 'completed'
    LEFT JOIN assessment_question_answers aqa ON aq.id = aqa.question_id AND aqa.attempt_id = aa.id
    WHERE aq.assessment_id = ?
    GROUP BY aq.id
    ORDER BY aq.question_order
");
$stmt->execute([$assessment_id]);
$question_stats = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Assessment Results</h1>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($assessment['assessment_title']); ?> • 
                        <?php echo htmlspecialchars($assessment['course_name']); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="assessments.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Assessments
                    </a>
                    <a href="assessment_edit.php?id=<?php echo $assessment_id; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Edit Assessment
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Assessment Info -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Assessment Order:</strong>
                            <div><?php echo htmlspecialchars($assessment['assessment_order'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="col-md-3">
                            <strong>Difficulty:</strong>
                            <span class="badge bg-<?php echo $assessment['difficulty'] === 'easy' ? 'success' : ($assessment['difficulty'] === 'medium' ? 'warning' : 'danger'); ?>">
                                <?php echo ucfirst($assessment['difficulty']); ?>
                            </span>
                        </div>
                        <div class="col-md-3">
                            <strong>Time Limit:</strong>
                            <?php if ($assessment['time_limit']): ?>
                                <span class="badge bg-info"><?php echo $assessment['time_limit']; ?> minutes</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No limit</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Status:</strong>
                            <?php if ($assessment['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
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
                            <h4 class="mb-0"><?php echo $stats['total_students'] ?? 0; ?></h4>
                            <small>Students Attempted</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people fs-1"></i>
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
                            <h4 class="mb-0"><?php echo $stats['total_attempts'] ?? 0; ?></h4>
                            <small>Total Attempts</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clipboard-check fs-1"></i>
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
                            <h4 class="mb-0"><?php echo number_format($stats['average_score'] ?? 0, 1); ?>%</h4>
                            <small>Average Score</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fs-1"></i>
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
                            <h4 class="mb-0"><?php echo $stats['highest_score'] ?? 0; ?>%</h4>
                            <small>Highest Score</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-trophy fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Score Distribution -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Score Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-3">
                            <div class="text-success">
                                <h4><?php echo $stats['excellent_count'] ?? 0; ?></h4>
                                <small>Excellent (90%+)</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="text-info">
                                <h4><?php echo $stats['good_count'] ?? 0; ?></h4>
                                <small>Good (70-89%)</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="text-warning">
                                <h4><?php echo $stats['fair_count'] ?? 0; ?></h4>
                                <small>Fair (50-69%)</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="text-danger">
                                <h4><?php echo $stats['poor_count'] ?? 0; ?></h4>
                                <small>Poor (<50%)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Score Range</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <strong>Lowest Score:</strong>
                            <div class="h5 text-danger"><?php echo $stats['lowest_score'] ?? 0; ?>%</div>
                        </div>
                        <div class="col-6">
                            <strong>Highest Score:</strong>
                            <div class="h5 text-success"><?php echo $stats['highest_score'] ?? 0; ?>%</div>
                        </div>
                    </div>
                    <div class="progress mt-3">
                        <div class="progress-bar bg-danger" style="width: <?php echo $stats['lowest_score'] ?? 0; ?>%"></div>
                        <div class="progress-bar bg-warning" style="width: <?php echo ($stats['average_score'] ?? 0) - ($stats['lowest_score'] ?? 0); ?>%"></div>
                        <div class="progress-bar bg-success" style="width: <?php echo ($stats['highest_score'] ?? 0) - ($stats['average_score'] ?? 0); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Question Analysis -->
    <?php if (!empty($question_stats)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Question Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Type</th>
                                    <th>Points</th>
                                    <th>Attempts</th>
                                    <th>Success Rate</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($question_stats as $question): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars(substr($question['question_text'], 0, 100)) . '...'; ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $question['points']; ?> pts</span>
                                        </td>
                                        <td>
                                            <?php echo $question['attempt_count']; ?>
                                        </td>
                                        <td>
                                            <?php echo number_format($question['correct_percentage'], 1); ?>%
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $question['correct_percentage'] >= 80 ? 'success' : ($question['correct_percentage'] >= 60 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $question['correct_percentage']; ?>%">
                                                    <?php echo number_format($question['correct_percentage'], 0); ?>%
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

    <!-- Recent Attempts -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Attempts</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_attempts)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clipboard-x fs-1 text-muted mb-3"></i>
                            <h6>No Attempts Yet</h6>
                            <p class="text-muted">Students haven't taken this assessment yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Score</th>
                                        <th>Time Taken</th>
                                        <th>Completed</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attempts as $attempt): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo getProfilePictureUrl($attempt['profile_picture'] ?? null, 'small'); ?>" 
                                                         class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($attempt['email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $attempt['score'] >= 90 ? 'success' : ($attempt['score'] >= 70 ? 'info' : ($attempt['score'] >= 50 ? 'warning' : 'danger')); ?>">
                                                    <?php echo $attempt['score']; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($attempt['completed_at'] && $attempt['started_at']) {
                                                    $time_taken_seconds = strtotime($attempt['completed_at']) - strtotime($attempt['started_at']);
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
                                                <?php 
                                                if (!empty($attempt['completed_at'])) {
                                                    echo date('M j, Y g:i A', strtotime($attempt['completed_at']));
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">Completed</span>
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

    <!-- All Attempts -->
    <?php if (!empty($all_attempts)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">All Attempts (<?php echo count($all_attempts); ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="attemptsTable">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student</th>
                                    <th>Score</th>
                                    <th>Time Taken</th>
                                    <th>Completed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_attempts as $index => $attempt): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                #<?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo getProfilePictureUrl($attempt['profile_picture'] ?? null, 'small'); ?>" 
                                                     class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($attempt['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $attempt['score'] >= 90 ? 'success' : ($attempt['score'] >= 70 ? 'info' : ($attempt['score'] >= 50 ? 'warning' : 'danger')); ?>">
                                                <?php echo $attempt['score']; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($attempt['completed_at'] && $attempt['started_at']) {
                                                $time_taken_seconds = strtotime($attempt['completed_at']) - strtotime($attempt['started_at']);
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
                                            <?php 
                                            if (!empty($attempt['completed_at'])) {
                                                echo date('M j, Y g:i A', strtotime($attempt['completed_at']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="attempt_detail.php?id=<?php echo $attempt['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-eye me-1"></i>View Details
                                            </a>
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

<script>
// Initialize DataTable for better table functionality
$(document).ready(function() {
    if ($('#attemptsTable').length) {
        $('#attemptsTable').DataTable({
            order: [[2, 'desc']], // Sort by score descending
            pageLength: 25,
            responsive: true
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 