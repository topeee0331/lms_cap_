<?php
$page_title = 'Assessment Attempt History';
require_once '../includes/header.php';
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];

// Get filter parameters
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$assessment_filter = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the query
$where_conditions = ["c.teacher_id = ?"];
$params = [$teacher_id];

if ($course_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

if ($assessment_filter) {
    $where_conditions[] = "a.id = ?";
    $params[] = $assessment_filter;
}

if ($status_filter) {
    switch ($status_filter) {
        case 'limit_reached':
            $where_conditions[] = "aa.attempt_count >= a.attempt_limit AND a.attempt_limit > 0";
            break;
        case 'approaching_limit':
            $where_conditions[] = "aa.attempt_count >= (a.attempt_limit - 1) AND a.attempt_limit > 1";
            break;
        case 'multiple_attempts':
            $where_conditions[] = "aa.attempt_count > 1";
            break;
        case 'single_attempt':
            $where_conditions[] = "aa.attempt_count = 1";
            break;
    }
}

if ($date_from) {
    $where_conditions[] = "aa.started_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $where_conditions[] = "aa.started_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$where_clause = implode(' AND ', $where_conditions);

// Get attempt history with detailed information
$stmt = $db->prepare("
    SELECT 
        aa.id as attempt_id,
        aa.started_at,
        aa.completed_at,
        aa.score,
        aa.max_score,
        aa.status,
        a.id as assessment_id,
        a.assessment_title,
        a.attempt_limit,
        a.assessment_order,
        c.id as course_id,
        c.course_name,
        c.course_code,
        u.id as student_id,
        u.first_name,
        u.last_name,
        u.identifier as student_number,
        (SELECT COUNT(*) FROM assessment_attempts aa2 
         WHERE aa2.assessment_id = a.id AND aa2.student_id = u.id AND aa2.status = 'completed') as attempt_count
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON aa.student_id = u.id
    WHERE $where_clause
    ORDER BY aa.started_at DESC
    LIMIT 1000
");
$stmt->execute($params);
$attempts = $stmt->fetchAll();

// Add assessment order info for display
foreach ($attempts as &$attempt) {
    $attempt['display_title'] = $attempt['assessment_title'];
    if (!empty($attempt['assessment_order'])) {
        $attempt['display_title'] = "Assessment " . $attempt['assessment_order'] . ": " . $attempt['assessment_title'];
    }
}

// Get courses for filter dropdown
$courses_stmt = $db->prepare("SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name");
$courses_stmt->execute([$teacher_id]);
$courses = $courses_stmt->fetchAll();

// Get assessments for filter dropdown
$assessments_stmt = $db->prepare("
    SELECT DISTINCT a.id, a.assessment_title, c.course_name 
    FROM assessments a 
    JOIN courses c ON a.course_id = c.id 
    WHERE c.teacher_id = ? 
    ORDER BY a.assessment_title
");
$assessments_stmt->execute([$teacher_id]);
$assessments = $assessments_stmt->fetchAll();

// Calculate statistics
$total_attempts = count($attempts);
$limit_reached_count = 0;
$approaching_limit_count = 0;

foreach ($attempts as $attempt) {
    if ($attempt['attempt_limit'] > 0 && $attempt['attempt_count'] >= $attempt['attempt_limit']) {
        $limit_reached_count++;
    } elseif ($attempt['attempt_limit'] > 1 && $attempt['attempt_count'] >= ($attempt['attempt_limit'] - 1)) {
        $approaching_limit_count++;
    }
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Assessment Attempt History</h1>
                    <p class="text-muted mb-0">Monitor student attempts and identify those reaching limits</p>
                </div>
                <div class="btn-group">
                    <a href="assessments.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Assessments
                    </a>
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
                            <h4 class="mb-0"><?php echo $total_attempts; ?></h4>
                            <p class="mb-0">Total Attempts</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-2x"></i>
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
                            <h4 class="mb-0"><?php echo $limit_reached_count; ?></h4>
                            <p class="mb-0">Limit Reached</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
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
                            <h4 class="mb-0"><?php echo $approaching_limit_count; ?></h4>
                            <p class="mb-0">Approaching Limit</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-2">
                    <label for="course_id" class="form-label">Course</label>
                    <select class="form-select" id="course_id" name="course_id">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="assessment_id" class="form-label">Assessment</label>
                    <select class="form-select" id="assessment_id" name="assessment_id">
                        <option value="">All Assessments</option>
                        <?php foreach ($assessments as $assessment): ?>
                            <option value="<?php echo $assessment['id']; ?>" <?php echo $assessment_filter == $assessment['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($assessment['assessment_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="limit_reached" <?php echo $status_filter === 'limit_reached' ? 'selected' : ''; ?>>Limit Reached</option>
                        <option value="approaching_limit" <?php echo $status_filter === 'approaching_limit' ? 'selected' : ''; ?>>Approaching Limit</option>
                        <option value="multiple_attempts" <?php echo $status_filter === 'multiple_attempts' ? 'selected' : ''; ?>>Multiple Attempts</option>
                        <option value="single_attempt" <?php echo $status_filter === 'single_attempt' ? 'selected' : ''; ?>>Single Attempt</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="attempt_history.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>



    <!-- Attempt History Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-history me-2"></i>Attempt History
                <span class="badge bg-secondary ms-2"><?php echo $total_attempts; ?> records</span>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($attempts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h6>No Attempts Found</h6>
                    <p class="text-muted">No assessment attempts match your current filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Assessment</th>
                                <th>Course</th>
                                <th>Attempt #</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Attempt Limit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): ?>
                                <?php
                                $attempt_status = '';
                                $status_class = '';
                                
                                if ($attempt['attempt_limit'] > 0 && $attempt['attempt_count'] >= $attempt['attempt_limit']) {
                                    $attempt_status = 'Limit Reached';
                                    $status_class = 'danger';
                                } elseif ($attempt['attempt_limit'] > 1 && $attempt['attempt_count'] >= ($attempt['attempt_limit'] - 1)) {
                                    $attempt_status = 'Approaching Limit';
                                    $status_class = 'warning';
                                } elseif ($attempt['attempt_count'] > 1) {
                                    $attempt_status = 'Multiple Attempts';
                                    $status_class = 'info';
                                } else {
                                    $attempt_status = 'First Attempt';
                                    $status_class = 'success';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($attempt['student_number']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($attempt['display_title']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($attempt['course_name']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($attempt['course_code']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $attempt['attempt_count']; ?>
                                            <?php if ($attempt['attempt_limit'] > 0): ?>
                                                / <?php echo $attempt['attempt_limit']; ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($attempt['score'] !== null): ?>
                                            <span class="badge bg-<?php echo $attempt['score'] >= 70 ? 'success' : ($attempt['score'] >= 50 ? 'warning' : 'danger'); ?>">
                                                <?php echo $attempt['score']; ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $attempt_status; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('M j, Y g:i A', strtotime($attempt['started_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($attempt['attempt_limit'] > 0): ?>
                                            <span class="badge bg-warning"><?php echo $attempt['attempt_limit']; ?> max</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Unlimited</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="assessment_results.php?id=<?php echo $attempt['assessment_id']; ?>" 
                                               class="btn btn-outline-info btn-sm" title="View Results">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                            <?php if ($attempt['attempt_limit'] > 0 && $attempt['attempt_count'] >= $attempt['attempt_limit']): ?>
                                                <button class="btn btn-outline-warning btn-sm" title="Student has reached attempt limit">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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

<script>
// Auto-submit form when filters change
document.getElementById('course_id').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('assessment_id').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('status').addEventListener('change', function() {
    this.form.submit();
});
</script>

<?php require_once '../includes/footer.php'; ?>
