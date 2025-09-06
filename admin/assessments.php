<?php
$page_title = 'Assessment Attempts';
require_once '../includes/header.php';
requireRole('admin');

// Get filters
$course_filter = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$student_filter = isset($_GET['student']) ? (int)$_GET['student'] : 0;
$status_filter = $_GET['status'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($course_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

if ($student_filter) {
    $where_conditions[] = "u.id = ?";
    $params[] = $student_filter;
}

if ($status_filter) {
    $where_conditions[] = "aa.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get assessment attempts
$stmt = $db->prepare("
    SELECT 
        aa.*,
        a.assessment_title,
        a.difficulty,
        a.time_limit,
        a.num_questions,
        c.course_name,
        c.course_code,
        u.first_name,
        u.last_name,
        u.email,
        'N/A' as module_title
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON aa.student_id = u.id
    $where_clause
    ORDER BY aa.started_at DESC
");
$stmt->execute($params);
$attempts = $stmt->fetchAll();

// Get courses for filter
$stmt = $db->prepare("SELECT id, course_name, course_code FROM courses ORDER BY course_name");
$stmt->execute();
$courses = $stmt->fetchAll();

// Get students for filter
$stmt = $db->prepare("SELECT id, first_name, last_name, email FROM users WHERE role = 'student' ORDER BY last_name, first_name");
$stmt->execute();
$students = $stmt->fetchAll();

// Calculate statistics
$total_attempts = count($attempts);
$completed_attempts = count(array_filter($attempts, function($a) { return $a['status'] === 'completed'; }));
$average_score = $completed_attempts > 0 ? array_sum(array_column(array_filter($attempts, function($a) { return $a['status'] === 'completed'; }), 'score')) / $completed_attempts : 0;
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Assessment Attempts</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-funnel"></i> Filters
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="assessments.php">Clear All Filters</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="assessments.php?status=completed">Completed Only</a></li>
                        <li><a class="dropdown-item" href="assessments.php?status=in_progress">In Progress</a></li>
                    </ul>
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
                            <small>Total Attempts</small>
                        </div>
                        <i class="bi bi-clipboard-check fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $completed_attempts; ?></h4>
                            <small>Completed</small>
                        </div>
                        <i class="bi bi-check-circle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo number_format($average_score, 1); ?>%</h4>
                            <small>Average Score</small>
                        </div>
                        <i class="bi bi-graph-up fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $total_attempts - $completed_attempts; ?></h4>
                            <small>In Progress</small>
                        </div>
                        <i class="bi bi-clock fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="course" class="form-label">Course</label>
                    <select class="form-select" id="course" name="course">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="student" class="form-label">Student</label>
                    <select class="form-select" id="student" name="student">
                        <option value="">All Students</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>" <?php echo $student_filter == $student['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="assessments.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Assessment Attempts Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Assessment Attempts</h5>
        </div>
        <div class="card-body">
            <?php if (empty($attempts)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No assessment attempts found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Assessment</th>
                                <th>Course</th>
                                <th>Module</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Started</th>
                                <th>Completed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2">
                                                <i class="bi bi-person-circle fs-4"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($attempt['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($attempt['assessment_title']); ?></div>
                                            <small class="text-muted">
                                                <?php echo ucfirst($attempt['difficulty']); ?> • 
                                                <?php echo $attempt['num_questions']; ?> questions
                                                <?php if ($attempt['time_limit']): ?>
                                                    • <?php echo $attempt['time_limit']; ?> min
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($attempt['course_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($attempt['course_code']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($attempt['module_title']); ?></td>
                                    <td>
                                        <?php if ($attempt['status'] === 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attempt['status'] === 'completed'): ?>
                                            <span class="fw-bold <?php echo $attempt['score'] >= 70 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $attempt['score']; ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($attempt['started_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($attempt['completed_at']): ?>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($attempt['completed_at'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="viewAttemptDetails(<?php echo $attempt['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
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

<!-- Attempt Details Modal -->
<div class="modal fade" id="attemptDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assessment Attempt Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="attemptDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewAttemptDetails(attemptId) {
    // Load attempt details via AJAX
    fetch(`get_attempt_details.php?id=${attemptId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('attemptDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('attemptDetailsModal')).show();
        })
        .catch(error => {
            console.error('Error loading attempt details:', error);
            alert('Error loading attempt details');
        });
}
</script>

<?php require_once '../includes/footer.php'; ?> 