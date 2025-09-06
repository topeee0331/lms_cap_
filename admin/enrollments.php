<?php
$page_title = 'Active Enrollments';
require_once '../includes/header.php';
requireRole('admin');

// Get filters
$course_filter = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$status_filter = $_GET['status'] ?? 'active';
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($course_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

if ($status_filter) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

if ($year_filter) {
    $where_conditions[] = "c.academic_period_id = ?";
    $params[] = $year_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get enrollments
$stmt = $db->prepare("
    SELECT 
        e.*,
        u.first_name,
        u.last_name,
        u.email,
        u.username,
        c.course_name,
        c.course_code,
        c.description as course_description,
        t.first_name as teacher_first_name,
        t.last_name as teacher_last_name,
        ap.academic_year,
        ap.semester_name,
        s.section_name
    FROM course_enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    JOIN users t ON c.teacher_id = t.id
    JOIN academic_periods ap ON c.academic_period_id = ap.id
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    $where_clause
    ORDER BY e.enrolled_at DESC
");
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

// Get courses for filter
$stmt = $db->prepare("SELECT id, course_name, course_code FROM courses ORDER BY course_name");
$stmt->execute();
$courses = $stmt->fetchAll();

// Get academic periods for filter
$stmt = $db->prepare("SELECT id, CONCAT(academic_year, ' - ', semester_name) as period_name FROM academic_periods ORDER BY academic_year DESC, semester_name");
$stmt->execute();
$academic_years = $stmt->fetchAll();

// Calculate statistics
$total_enrollments = count($enrollments);
$active_enrollments = count(array_filter($enrollments, function($e) { return $e['status'] === 'active'; }));
$completed_enrollments = count(array_filter($enrollments, function($e) { return $e['status'] === 'completed'; }));
$unique_students = count(array_unique(array_column($enrollments, 'student_id')));
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Active Enrollments</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-funnel"></i> Filters
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="enrollments.php">Clear All Filters</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="enrollments.php?status=active">Active Only</a></li>
                        <li><a class="dropdown-item" href="enrollments.php?status=completed">Completed Only</a></li>
                        <li><a class="dropdown-item" href="enrollments.php?status=inactive">Inactive Only</a></li>
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
                            <h4 class="mb-0"><?php echo $total_enrollments; ?></h4>
                            <small>Total Enrollments</small>
                        </div>
                        <i class="bi bi-graph-up fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $active_enrollments; ?></h4>
                            <small>Active</small>
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
                            <h4 class="mb-0"><?php echo $unique_students; ?></h4>
                            <small>Unique Students</small>
                        </div>
                        <i class="bi bi-people fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $completed_enrollments; ?></h4>
                            <small>Completed</small>
                        </div>
                        <i class="bi bi-award fs-1"></i>
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
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Academic Period</label>
                    <select class="form-select" id="year" name="year">
                        <option value="">All Periods</option>
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo $year['id']; ?>" <?php echo $year_filter == $year['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['period_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="enrollments.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Enrollments Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Enrollments</h5>
        </div>
        <div class="card-body">
            <?php if (empty($enrollments)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-people-x fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No enrollments found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Teacher</th>
                                <th>Section</th>
                                <th>Academic Period</th>
                                <th>Status</th>
                                <th>Enrolled Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2">
                                                <i class="bi bi-person-circle fs-4"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($enrollment['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($enrollment['course_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($enrollment['course_code']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($enrollment['teacher_first_name'] . ' ' . $enrollment['teacher_last_name']); ?></div>
                                            <small class="text-muted">Teacher</small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($enrollment['section_name']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($enrollment['section_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($enrollment['academic_year'] . ' - ' . $enrollment['semester_name']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($enrollment['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($enrollment['status'] === 'completed'): ?>
                                            <span class="badge bg-primary">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($enrollment['enrolled_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="viewEnrollmentDetails(<?php echo $enrollment['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="editEnrollmentStatus(<?php echo $enrollment['id']; ?>, '<?php echo $enrollment['status']; ?>')">
                                                <i class="bi bi-pencil"></i>
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

<!-- Enrollment Details Modal -->
<div class="modal fade" id="enrollmentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enrollment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="enrollmentDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Edit Status Modal -->
<div class="modal fade" id="editStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Enrollment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStatusForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="enrollmentId" name="enrollment_id">
                    <div class="mb-3">
                        <label for="newStatus" class="form-label">Status</label>
                        <select class="form-select" id="newStatus" name="status" required>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewEnrollmentDetails(enrollmentId) {
    // Load enrollment details via AJAX
    fetch(`get_enrollment_details.php?id=${enrollmentId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('enrollmentDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('enrollmentDetailsModal')).show();
        })
        .catch(error => {
            console.error('Error loading enrollment details:', error);
            alert('Error loading enrollment details');
        });
}

function editEnrollmentStatus(enrollmentId, currentStatus) {
    document.getElementById('enrollmentId').value = enrollmentId;
    document.getElementById('newStatus').value = currentStatus;
    new bootstrap.Modal(document.getElementById('editStatusModal')).show();
}

// Handle status update form submission
document.getElementById('editStatusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('update_enrollment_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error updating enrollment status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating enrollment status');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?> 