<?php
$page_title = 'Manage Courses';
require_once '../includes/header.php';
requireRole('admin');

$message = '';
$message_type = '';

// Handle course actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'archive':
                $course_id = (int)($_POST['course_id'] ?? 0);
                $stmt = $db->prepare('UPDATE courses SET is_archived = 1 WHERE id = ?');
                $stmt->execute([$course_id]);
                $message = 'Course archived successfully.';
                $message_type = 'success';
                break;
                
            case 'unarchive':
                $course_id = (int)($_POST['course_id'] ?? 0);
                $stmt = $db->prepare('UPDATE courses SET is_archived = 0 WHERE id = ?');
                $stmt->execute([$course_id]);
                $message = 'Course unarchived successfully.';
                $message_type = 'success';
                break;
                
            case 'delete':
                $course_id = (int)($_POST['course_id'] ?? 0);
                
                // Check if course has enrollments
                $stmt = $db->prepare('SELECT COUNT(*) FROM course_enrollments WHERE course_id = ?');
                $stmt->execute([$course_id]);
                $enrollment_count = $stmt->fetchColumn();
                
                if ($enrollment_count > 0) {
                    $message = 'Cannot delete course with existing enrollments.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('DELETE FROM courses WHERE id = ?');
                    $stmt->execute([$course_id]);
                    $message = 'Course deleted successfully.';
                    $message_type = 'success';
                }
                break;
            case 'create':
                $course_name = sanitizeInput($_POST['course_name'] ?? '');
                $course_code = sanitizeInput($_POST['course_code'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $teacher_id = (int)($_POST['teacher_id'] ?? 0);
                $academic_period_id = (int)($_POST['academic_period_id'] ?? 0);
                if (empty($course_name) || empty($course_code) || !$teacher_id || !$academic_period_id) {
                    $message = 'All fields are required.';
                    $message_type = 'danger';
                } else {
                    // Check for duplicate course code
                    $stmt = $db->prepare('SELECT id FROM courses WHERE course_code = ? LIMIT 1');
                    $stmt->execute([$course_code]);
                    if ($stmt->fetch()) {
                        $message = 'Course code already exists.';
                        $message_type = 'danger';
                    } else {
                        $stmt = $db->prepare('INSERT INTO courses (course_name, course_code, description, teacher_id, academic_period_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                        $stmt->execute([$course_name, $course_code, $description, $teacher_id, $academic_period_id]);
                        $message = 'Course created successfully.';
                        $message_type = 'success';
                    }
                }
                break;
            case 'update':
                $course_id = (int)($_POST['course_id'] ?? 0);
                $course_name = sanitizeInput($_POST['course_name'] ?? '');
                $course_code = sanitizeInput($_POST['course_code'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $teacher_id = (int)($_POST['teacher_id'] ?? 0);
                $academic_period_id = (int)($_POST['academic_period_id'] ?? 0);
                if (empty($course_name) || empty($course_code) || !$teacher_id || !$academic_period_id) {
                    $message = 'All fields are required.';
                    $message_type = 'danger';
                } else {
                    // Check for duplicate course code (excluding current course)
                    $stmt = $db->prepare('SELECT id FROM courses WHERE course_code = ? AND id != ? LIMIT 1');
                    $stmt->execute([$course_code, $course_id]);
                    if ($stmt->fetch()) {
                        $message = 'Course code already exists.';
                        $message_type = 'danger';
                    } else {
                        $stmt = $db->prepare('UPDATE courses SET course_name = ?, course_code = ?, description = ?, teacher_id = ?, academic_period_id = ? WHERE id = ?');
                        $stmt->execute([$course_name, $course_code, $description, $teacher_id, $academic_period_id, $course_id]);
                        $message = 'Course updated successfully.';
                        $message_type = 'success';
                    }
                }
                break;
        }
    }
}

// Get courses with search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$academic_year_filter = (int)($_GET['academic_year'] ?? 0);
$status_filter = sanitizeInput($_GET['status'] ?? '');

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.course_name LIKE ? OR c.course_code LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($academic_year_filter > 0) {
    $where_conditions[] = "c.academic_period_id = ?";
    $params[] = $academic_year_filter;
}

if ($status_filter === 'archived') {
    $where_conditions[] = "c.is_archived = 1";
} elseif ($status_filter === 'active') {
    $where_conditions[] = "c.is_archived = 0";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Update the courses query to join academic_periods instead of separate academic_years and semesters
$stmt = $db->prepare("
    SELECT c.*, u.first_name, u.last_name, u.username, ap.academic_year, ap.semester_name,
           COUNT(e.student_id) as student_count,
           JSON_LENGTH(c.modules) as module_count
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    JOIN academic_periods ap ON c.academic_period_id = ap.id
    LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
    $where_clause
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get academic years for filter
$stmt = $db->prepare('SELECT id, CONCAT(academic_year, " - ", semester_name) as period_name FROM academic_periods ORDER BY academic_year DESC, semester_name');
$stmt->execute();
$academic_periods = $stmt->fetchAll();

// 1. Fetch all semesters for use in the modal:
$period_stmt = $db->prepare('SELECT * FROM academic_periods ORDER BY academic_year DESC, semester_name');
$period_stmt->execute();
$all_periods = $period_stmt->fetchAll();

// Fetch only active academic years for the modal:
$stmt = $db->prepare('SELECT id, CONCAT(academic_year, " - ", semester_name) as period_name FROM academic_periods WHERE is_active = 1 ORDER BY academic_year DESC, semester_name');
$stmt->execute();
$active_academic_periods = $stmt->fetchAll();
// Fetch only active semesters for the modal:
$active_period_stmt = $db->prepare('SELECT * FROM academic_periods WHERE is_active = 1 ORDER BY academic_year DESC, semester_name');
$active_period_stmt->execute();
$active_periods = $active_period_stmt->fetchAll();

// Get statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_courses,
        COUNT(CASE WHEN is_archived = 0 THEN 1 END) as active_courses,
        COUNT(CASE WHEN is_archived = 1 THEN 1 END) as archived_courses,
        COUNT(DISTINCT teacher_id) as unique_teachers,
        COUNT(DISTINCT academic_period_id) as periods_with_courses,
        SUM(CASE WHEN is_archived = 0 THEN 1 ELSE 0 END) as current_courses
    FROM courses
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get total students and modules
$total_stats_stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT e.student_id) as total_students,
        SUM(JSON_LENGTH(c.modules)) as total_modules
    FROM courses c
    LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
    WHERE c.is_archived = 0
");
$total_stats_stmt->execute();
$total_stats = $total_stats_stmt->fetch();
?>

<div class="container-fluid py-4">
    <!-- Navigation Back to Dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <a href="../admin/dashboard.php" class="btn btn-outline-primary mb-3">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-book-fill fs-1 text-primary"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['total_courses'] ?></h3>
                    <p class="text-muted mb-0 small">Total Courses</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-play-circle-fill fs-1 text-success"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['active_courses'] ?></h3>
                    <p class="text-muted mb-0 small">Active Courses</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-people-fill fs-1 text-info"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $total_stats['total_students'] ?></h3>
                    <p class="text-muted mb-0 small">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-collection-fill fs-1 text-warning"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $total_stats['total_modules'] ?></h3>
                    <p class="text-muted mb-0 small">Total Modules</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-person-workspace fs-1 text-danger"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['unique_teachers'] ?></h3>
                    <p class="text-muted mb-0 small">Active Teachers</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-archive-fill fs-1 text-secondary"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['archived_courses'] ?></h3>
                    <p class="text-muted mb-0 small">Archived Courses</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-semibold">
                                <i class="bi bi-book me-2"></i>Course Management
                            </h4>
                            <p class="text-muted mb-0 small">Manage all courses and their assignments</p>
                        </div>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                            <i class="bi bi-plus-circle me-2"></i>Create Course
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-search me-2"></i>Search & Filter
                    </h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label fw-semibold">
                                <i class="bi bi-search me-2"></i>Search
                            </label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by course name, code, or description">
                        </div>
                        <div class="col-md-3">
                            <label for="academic_year" class="form-label fw-semibold">
                                <i class="bi bi-calendar-event me-2"></i>Academic Year
                            </label>
                            <select class="form-select" id="academic_year" name="academic_year">
                                <option value="">All Years</option>
                                <?php foreach ($academic_periods as $period): ?>
                                    <option value="<?php echo $period['id']; ?>" 
                                            <?php echo $academic_year_filter == $period['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($period['period_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label fw-semibold">
                                <i class="bi bi-toggle-on me-2"></i>Status
                            </label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel me-2"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-semibold">
                            <i class="bi bi-list-ul me-2"></i>All Courses
                        </h5>
                        <span class="badge bg-primary fs-6"><?php echo count($courses); ?> courses</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($courses)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-book fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No courses found</h5>
                            <p class="text-muted">Start by creating your first course.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                                <i class="bi bi-plus-circle me-2"></i>Create Course
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="border-0">
                                            <i class="bi bi-book me-2"></i>Course
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-person-workspace me-2"></i>Teacher
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-calendar-event me-2"></i>Academic Year
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-people me-2"></i>Students
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-collection me-2"></i>Modules
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-toggle-on me-2"></i>Status
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-calendar me-2"></i>Created
                                        </th>
                                        <th class="border-0 text-center">
                                            <i class="bi bi-gear me-2"></i>Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <div class="bg-<?= $course['is_archived'] ? 'secondary' : 'primary' ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                            <i class="bi bi-book text-white"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="bi bi-code me-1"></i><?php echo htmlspecialchars($course['course_code']); ?>
                                                        </small>
                                                        <?php if ($course['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($course['description'], 0, 50)) . '...'; ?></small>
                                                        <?php endif; ?>
                                                        <small class="text-muted">
                                                            Created by: <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name'] . ' (' . $course['username'] . ')'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo getProfilePictureUrl($course['profile_picture'] ?? null, 'medium'); ?>" 
                                                         class="rounded-circle me-2" alt="Teacher" style="width: 32px; height: 32px; object-fit: cover;">
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($course['academic_year']) ?></div>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar2-week me-1"></i><?= htmlspecialchars($course['semester_name']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <i class="bi bi-people me-1"></i><?php echo $course['student_count']; ?> students
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-collection me-1"></i><?php echo $course['module_count']; ?> modules
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($course['is_archived']): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-archive me-1"></i>Archived
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Active
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i><?php echo formatDate($course['created_at']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewCourseModal<?= $course['id'] ?>"
                                                            title="View Course">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editCourseModal<?= $course['id'] ?>"
                                                            title="Edit Course">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if (!$course['is_archived']): ?>
                                                        <form method="post" action="courses.php" style="display:inline;" 
                                                              onsubmit="return confirm('Archive this course?');">
                                                            <input type="hidden" name="action" value="archive">
                                                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Archive Course">
                                                                <i class="bi bi-archive"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" action="courses.php" style="display:inline;" 
                                                              onsubmit="return confirm('Unarchive this course?');">
                                                            <input type="hidden" name="action" value="unarchive">
                                                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Unarchive Course">
                                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" action="courses.php" style="display:inline;" 
                                                          onsubmit="return confirm('Are you sure you want to delete this course?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Course">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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
    </div>
</div>

<!-- Archive Course Form -->
<form id="archiveCourseForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="archive">
    <input type="hidden" name="course_id" id="archive_course_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Unarchive Course Form -->
<form id="unarchiveCourseForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="unarchive">
    <input type="hidden" name="course_id" id="unarchive_course_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Delete Course Form -->
<form id="deleteCourseForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="course_id" id="delete_course_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Create Course Modal -->
<div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="courses.php">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="createCourseModalLabel">
            <i class="bi bi-plus-circle me-2"></i>Create New Course
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
          <div class="mb-3">
            <label for="course_name" class="form-label fw-semibold">
              <i class="bi bi-book me-2"></i>Course Name
            </label>
            <input type="text" class="form-control" id="course_name" name="course_name" required>
          </div>
          <div class="mb-3">
            <label for="course_code" class="form-label fw-semibold">
              <i class="bi bi-code me-2"></i>Course Code
            </label>
            <input type="text" class="form-control" id="course_code" name="course_code" required>
          </div>
          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">
              <i class="bi bi-text-paragraph me-2"></i>Description
            </label>
            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label for="teacher_id" class="form-label fw-semibold">
              <i class="bi bi-person-workspace me-2"></i>Teacher
            </label>
            <select class="form-select" id="teacher_id" name="teacher_id" required>
              <option value="">Select Teacher</option>
              <?php
              $teacher_stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'teacher' AND (status = 'active' OR status IS NULL) ORDER BY last_name, first_name");
              $teacher_stmt->execute();
              $teachers = $teacher_stmt->fetchAll();
              foreach ($teachers as $teacher): ?>
                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="academic_period_id" class="form-label fw-semibold">
              <i class="bi bi-calendar-event me-2"></i>Academic Period
            </label>
            <select class="form-select" id="academic_period_id" name="academic_period_id" required>
              <option value="">Select Period</option>
              <?php foreach ($active_academic_periods as $period): ?>
                <option value="<?= $period['id']; ?>"><?= htmlspecialchars($period['period_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-2"></i>Cancel
          </button>
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle me-2"></i>Create Course
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($courses as $course): ?>
<div class="modal fade" id="viewCourseModal<?= $course['id'] ?>" tabindex="-1" aria-labelledby="viewCourseLabel<?= $course['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="viewCourseLabel<?= $course['id'] ?>">
          <i class="bi bi-eye me-2"></i>Course Details: <?= htmlspecialchars($course['course_name']) ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <dl class="row">
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-code me-2"></i>Course Code
          </dt>
          <dd class="col-sm-9"><?= htmlspecialchars($course['course_code']) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-text-paragraph me-2"></i>Description
          </dt>
          <dd class="col-sm-9"><?= nl2br(htmlspecialchars($course['description'])) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-person-workspace me-2"></i>Teacher
          </dt>
          <dd class="col-sm-9"><?= htmlspecialchars($course['last_name'] . ', ' . $course['first_name']) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-calendar-event me-2"></i>Academic Year
          </dt>
          <dd class="col-sm-9"><?= htmlspecialchars($course['academic_year']) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-calendar2-week me-2"></i>Semester
          </dt>
          <dd class="col-sm-9"><?= htmlspecialchars($course['semester_name']) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-people me-2"></i>Students
          </dt>
          <dd class="col-sm-9"><?= (int)$course['student_count'] ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-collection me-2"></i>Modules
          </dt>
          <dd class="col-sm-9"><?= (int)$course['module_count'] ?></dd>
        </dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-2"></i>Close
        </button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php foreach ($courses as $course): ?>
<div class="modal fade" id="editCourseModal<?= $course['id'] ?>" tabindex="-1" aria-labelledby="editCourseLabel<?= $course['id'] ?>" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="courses.php">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="editCourseLabel<?= $course['id'] ?>">
            <i class="bi bi-pencil-square me-2"></i>Edit Course: <?= htmlspecialchars($course['course_name']) ?>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
          <div class="mb-3">
            <label for="course_name_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-book me-2"></i>Course Name
            </label>
            <input type="text" class="form-control" id="course_name_edit<?= $course['id'] ?>" name="course_name" required value="<?= htmlspecialchars($course['course_name']) ?>">
          </div>
          <div class="mb-3">
            <label for="course_code_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-code me-2"></i>Course Code
            </label>
            <input type="text" class="form-control" id="course_code_edit<?= $course['id'] ?>" name="course_code" required value="<?= htmlspecialchars($course['course_code']) ?>">
          </div>
          <div class="mb-3">
            <label for="description_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-text-paragraph me-2"></i>Description
            </label>
            <textarea class="form-control" id="description_edit<?= $course['id'] ?>" name="description" rows="2"><?= htmlspecialchars($course['description']) ?></textarea>
          </div>
          <div class="mb-3">
            <label for="teacher_id_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-person-workspace me-2"></i>Teacher
            </label>
            <select class="form-select" id="teacher_id_edit<?= $course['id'] ?>" name="teacher_id" required>
              <option value="">Select Teacher</option>
              <?php
              $active_teachers_stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'teacher' AND (status = 'active' OR status IS NULL) ORDER BY last_name, first_name");
              $active_teachers_stmt->execute();
              $active_teachers = $active_teachers_stmt->fetchAll();
              foreach ($active_teachers as $teacher): ?>
                <option value="<?= $teacher['id'] ?>" <?= $course['teacher_id'] == $teacher['id'] ? 'selected' : '' ?>><?= htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="academic_period_id_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-calendar-event me-2"></i>Academic Period
            </label>
            <select class="form-select" id="academic_period_id_edit<?= $course['id'] ?>" name="academic_period_id" required>
              <option value="">Select Period</option>
              <?php foreach ($active_academic_periods as $period): ?>
                <option value="<?= $period['id'] ?>" <?= $course['academic_period_id'] == $period['id'] ? 'selected' : '' ?>><?= htmlspecialchars($period['period_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-2"></i>Cancel
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-2"></i>Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
function archiveCourse(courseId) {
    if (confirm('Are you sure you want to archive this course? It will be read-only.')) {
        document.getElementById('archive_course_id').value = courseId;
        document.getElementById('archiveCourseForm').submit();
    }
}

function unarchiveCourse(courseId) {
    if (confirm('Are you sure you want to unarchive this course?')) {
        document.getElementById('unarchive_course_id').value = courseId;
        document.getElementById('unarchiveCourseForm').submit();
    }
}

function deleteCourse(courseId, courseName) {
    if (confirm(`Are you sure you want to delete "${courseName}"? This action cannot be undone.`)) {
        document.getElementById('delete_course_id').value = courseId;
        document.getElementById('deleteCourseForm').submit();
    }
}

// Academic period change event handler (no longer needed since we have a single select)
</script>

<?php require_once '../includes/footer.php'; ?> 