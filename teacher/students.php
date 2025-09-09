<?php
$page_title = 'Students';
require_once '../config/config.php';
requireRole('teacher');
require_once '../includes/header.php';

$message = '';
$message_type = '';

// 1. Fetch all academic periods for the dropdown
$ay_stmt = $db->prepare('SELECT id, academic_year, semester_name, is_active FROM academic_periods ORDER BY academic_year DESC, semester_name');
$ay_stmt->execute();
$all_years = $ay_stmt->fetchAll();

// 2. Handle academic period selection (GET or SESSION)
if (isset($_GET['academic_period_id'])) {
    $_SESSION['teacher_students_academic_period_id'] = (int)$_GET['academic_period_id'];
}
// Find the first active academic year
$active_year = null;
foreach ($all_years as $year) {
    if ($year['is_active']) {
        $active_year = $year['id'];
        break;
    }
}
$selected_year_id = $_SESSION['teacher_students_academic_period_id'] ?? $active_year ?? ($all_years[0]['id'] ?? null);

// Handle student actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'enroll_student':
                $student_id = (int)($_POST['student_id'] ?? 0);
                $course_id = (int)($_POST['course_id'] ?? 0);
                
                if (!$student_id || !$course_id) {
                    $message = 'Student and course are required.';
                    $message_type = 'danger';
                } else {
                    // Verify course belongs to teacher and is in selected academic period
                    $stmt = $db->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ? AND academic_period_id = ?');
                    $stmt->execute([$course_id, $_SESSION['user_id'], $selected_year_id]);
                    if ($stmt->fetch()) {
                        // Check if already enrolled
                        $stmt = $db->prepare('SELECT id FROM course_enrollments WHERE student_id = ? AND course_id = ?');
                        $stmt->execute([$student_id, $course_id]);
                        
                        if ($stmt->fetch()) {
                            $message = 'Student is already enrolled in this course.';
                            $message_type = 'warning';
                        } else {
                            $stmt = $db->prepare('INSERT INTO course_enrollments (student_id, course_id, enrolled_at) VALUES (?, ?, NOW())');
                            $stmt->execute([$student_id, $course_id]);
                            $message = 'Student enrolled successfully.';
                            $message_type = 'success';
                        }
                    } else {
                        $message = 'Invalid course selected.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'kick_student':
                $student_id = (int)($_POST['student_id'] ?? 0);
                $course_id = (int)($_POST['course_id'] ?? 0);
                
                if (!$student_id || !$course_id) {
                    $message = 'Student and course are required.';
                    $message_type = 'danger';
                } else {
                    // Verify course belongs to teacher and is in selected academic period
                    $stmt = $db->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ? AND academic_period_id = ?');
                    $stmt->execute([$course_id, $_SESSION['user_id'], $selected_year_id]);
                    if ($stmt->fetch()) {
                        // Remove student from all course_enrollments for this course
                        $stmt = $db->prepare('DELETE FROM course_enrollments WHERE student_id = ? AND course_id = ?');
                        $stmt->execute([$student_id, $course_id]);
                        
                        // Remove from section students if applicable (simplified for JSON-based system)
                        $stmt = $db->prepare('
                            DELETE ss FROM section_students ss 
                            WHERE ss.student_id = ? AND ss.section_id IN (
                                SELECT JSON_UNQUOTE(JSON_EXTRACT(sections, CONCAT("$[", idx, "].id")))
                                FROM courses, JSON_TABLE(
                                    JSON_ARRAY_LENGTH(sections), 
                                    "$[*]" COLUMNS (idx FOR ORDINALITY)
                                ) AS t
                                WHERE id = ?
                            )
                        ');
                        $stmt->execute([$student_id, $course_id]);
                        
                        // Remove all progress data for this student in this course (simplified for JSON-based system)
                        $stmt = $db->prepare('
                            DELETE mp FROM module_progress mp 
                            WHERE mp.student_id = ? AND mp.module_id IN (
                                SELECT JSON_UNQUOTE(JSON_EXTRACT(modules, CONCAT("$[", idx, "].id")))
                                FROM courses, JSON_TABLE(
                                    JSON_ARRAY_LENGTH(modules), 
                                    "$[*]" COLUMNS (idx FOR ORDINALITY)
                                ) AS t
                                WHERE id = ?
                            )
                        ');
                        $stmt->execute([$student_id, $course_id]);
                        
                        // Remove video views for this course (simplified for JSON-based system)
                        $stmt = $db->prepare('
                            DELETE vv FROM video_views vv 
                            WHERE vv.student_id = ? AND vv.video_id IN (
                                SELECT JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(modules, CONCAT("$[", m_idx, "].videos")), CONCAT("$[", v_idx, "].id")))
                                FROM courses, 
                                JSON_TABLE(JSON_ARRAY_LENGTH(modules), "$[*]" COLUMNS (m_idx FOR ORDINALITY)) AS m,
                                JSON_TABLE(JSON_ARRAY_LENGTH(JSON_EXTRACT(modules, CONCAT("$[", m_idx-1, "].videos"))), "$[*]" COLUMNS (v_idx FOR ORDINALITY)) AS v
                                WHERE id = ?
                            )
                        ');
                        $stmt->execute([$student_id, $course_id]);
                        
                        // Remove assessment attempts for this course (simplified for JSON-based system)
                        $stmt = $db->prepare('
                            DELETE aa FROM assessment_attempts aa 
                            WHERE aa.student_id = ? AND aa.assessment_id IN (
                                SELECT JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(modules, CONCAT("$[", m_idx, "].assessments")), CONCAT("$[", a_idx, "].id")))
                                FROM courses, 
                                JSON_TABLE(JSON_ARRAY_LENGTH(modules), "$[*]" COLUMNS (m_idx FOR ORDINALITY)) AS m,
                                JSON_TABLE(JSON_ARRAY_LENGTH(JSON_EXTRACT(modules, CONCAT("$[", m_idx-1, "].assessments"))), "$[*]" COLUMNS (a_idx FOR ORDINALITY)) AS a
                                WHERE id = ?
                            )
                        ');
                        $stmt->execute([$student_id, $course_id]);
                        
                        $message = 'Student has been KICKED from the course successfully. All their progress data has been removed.';
                        $message_type = 'success';
                    } else {
                        $message = 'Invalid course selected.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'bulk_kick_students':
                $student_ids = $_POST['student_ids'] ?? [];
                
                if (empty($student_ids)) {
                    $message = 'No students selected.';
                    $message_type = 'warning';
                } else {
                    $kicked_count = 0;
                    
                    foreach ($student_ids as $section_student_id) {
                        // Get student and course info from section_student_id
                        // Note: This functionality needs to be updated for the new schema
                        // For now, we'll skip bulk operations until the schema is fully migrated
                        continue;
                        $enrollment_info = $stmt->fetch();
                        
                        if ($enrollment_info) {
                            $student_id = $enrollment_info['student_id'];
                            $course_id = $enrollment_info['course_id'];
                            
                            // Remove student from all course_enrollments for this course
                            $stmt = $db->prepare('DELETE FROM course_enrollments WHERE student_id = ? AND course_id = ?');
                            $stmt->execute([$student_id, $course_id]);
                            
                            // Remove from section students
                            $stmt = $db->prepare('UPDATE sections SET students = JSON_REMOVE(students, JSON_UNQUOTE(JSON_SEARCH(students, "one", ?))) WHERE JSON_SEARCH(students, "one", ?) IS NOT NULL');
                            $stmt->execute([$student_id, $student_id]);
                            
                            // Remove all progress data for this student in this course (simplified for JSON-based system)
                            $stmt = $db->prepare('
                                DELETE mp FROM module_progress mp 
                                WHERE mp.student_id = ? AND mp.module_id IN (
                                    SELECT JSON_UNQUOTE(JSON_EXTRACT(modules, CONCAT("$[", idx, "].id")))
                                    FROM courses, JSON_TABLE(
                                        JSON_ARRAY_LENGTH(modules), 
                                        "$[*]" COLUMNS (idx FOR ORDINALITY)
                                    ) AS t
                                    WHERE id = ?
                                )
                            ');
                            $stmt->execute([$student_id, $course_id]);
                            
                            // Remove video views for this course (simplified for JSON-based system)
                            $stmt = $db->prepare('
                                DELETE vv FROM video_views vv 
                                WHERE vv.student_id = ? AND vv.video_id IN (
                                    SELECT JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(modules, CONCAT("$[", m_idx, "].videos")), CONCAT("$[", v_idx, "].id")))
                                    FROM courses, 
                                    JSON_TABLE(JSON_ARRAY_LENGTH(modules), "$[*]" COLUMNS (m_idx FOR ORDINALITY)) AS m,
                                    JSON_TABLE(JSON_ARRAY_LENGTH(JSON_EXTRACT(modules, CONCAT("$[", m_idx-1, "].videos"))), "$[*]" COLUMNS (v_idx FOR ORDINALITY)) AS v
                                    WHERE id = ?
                                )
                            ');
                            $stmt->execute([$student_id, $course_id]);
                            
                            // Remove assessment attempts for this course (simplified for JSON-based system)
                            $stmt = $db->prepare('
                                DELETE aa FROM assessment_attempts aa 
                                WHERE aa.student_id = ? AND aa.assessment_id IN (
                                    SELECT JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(modules, CONCAT("$[", m_idx, "].assessments")), CONCAT("$[", a_idx, "].id")))
                                    FROM courses, 
                                    JSON_TABLE(JSON_ARRAY_LENGTH(modules), "$[*]" COLUMNS (m_idx FOR ORDINALITY)) AS m,
                                    JSON_TABLE(JSON_ARRAY_LENGTH(JSON_EXTRACT(modules, CONCAT("$[", m_idx-1, "].assessments"))), "$[*]" COLUMNS (a_idx FOR ORDINALITY)) AS a
                                    WHERE id = ?
                                )
                            ');
                            $stmt->execute([$student_id, $course_id]);
                            
                            $kicked_count++;
                        }
                    }
                    
                    $message = "Successfully KICKED {$kicked_count} student(s) from their courses. All their progress data has been removed.";
                    $message_type = 'success';
                }
                break;
                
            case 'unenroll_student':
                $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
                
                $stmt = $db->prepare('DELETE FROM course_enrollments WHERE id = ? AND course_id IN (SELECT id FROM courses WHERE teacher_id = ? AND academic_period_id = ?)');
                $stmt->execute([$enrollment_id, $_SESSION['user_id'], $selected_year_id]);
                $message = 'Student unenrolled successfully.';
                $message_type = 'success';
                break;
        }
    }
}

// Remove student from section
if (isset($_POST['remove_student'])) {
    $student_id = intval($_POST['student_id']);
    $section_id = intval($_POST['section_id']);
    
    // Get current students in section
    $stmt = $db->prepare("SELECT students FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $current_students = json_decode($stmt->fetchColumn(), true) ?? [];
    
    // Remove student from array
    $current_students = array_filter($current_students, function($id) use ($student_id) {
        return $id != $student_id;
    });
    
    // Update section
    $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
    $stmt->execute([json_encode($current_students), $section_id]);
    
    echo "<script>window.location.href='students.php';</script>";
    exit;
}

// Get filters
$course_filter = (int)($_GET['course'] ?? 0);
$status_filter = sanitizeInput($_GET['status'] ?? '');
$sort_by = sanitizeInput($_GET['sort'] ?? 'name');

// Get teacher's courses for selected academic period
$stmt = $db->prepare('SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? AND academic_period_id = ? ORDER BY course_name');
$stmt->execute([$_SESSION['user_id'], $selected_year_id]);
$courses = $stmt->fetchAll();

// Get enrolled students with filters
$where_conditions = ["c.teacher_id = ?", "c.academic_period_id = ?"];
$params = [$_SESSION['user_id'], $selected_year_id];

if ($course_filter > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$stmt = $db->prepare("
    SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at as user_created, u.identifier as neust_student_id,
           c.course_name, c.course_code, c.id as course_id, 
           COALESCE(s.section_name, 'Not Assigned') as section_name, 
           COALESCE(s.year_level, 'N/A') as section_year,
           e.enrolled_at, e.status as enrollment_status,
           e.progress_percentage as course_progress,
           e.last_accessed as last_activity,
           
           -- Assessment Statistics
           COALESCE(assessment_stats.total_assessments, 0) as total_assessments,
           COALESCE(assessment_stats.completed_assessments, 0) as completed_assessments,
           COALESCE(assessment_stats.avg_score, 0) as avg_score,
           COALESCE(assessment_stats.best_score, 0) as best_score,
           COALESCE(assessment_stats.total_attempts, 0) as total_attempts
           
    FROM course_enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    
    -- Assessment Statistics Subquery
    LEFT JOIN (
        SELECT 
            aa.student_id,
            c.id as course_id,
            COUNT(DISTINCT aa.assessment_id) as total_assessments,
            COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.assessment_id END) as completed_assessments,
            ROUND(AVG(aa.score), 2) as avg_score,
            MAX(aa.score) as best_score,
            COUNT(*) as total_attempts
        FROM assessment_attempts aa
        JOIN courses c ON JSON_SEARCH(c.modules, 'one', aa.assessment_id) IS NOT NULL
        WHERE c.teacher_id = ? AND c.academic_period_id = ?
        GROUP BY aa.student_id, c.id
    ) assessment_stats ON assessment_stats.student_id = e.student_id AND assessment_stats.course_id = e.course_id
    
    $where_clause
    ORDER BY " . getSortClause($sort_by) . "
");
$stmt->execute(array_merge($params, [$_SESSION['user_id'], $selected_year_id]));
$course_enrollments = $stmt->fetchAll();

// Get available students for enrollment (for courses in selected academic period)
$stmt = $db->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    WHERE u.role = 'student' AND u.id NOT IN (
        SELECT student_id FROM course_enrollments WHERE course_id IN (SELECT id FROM courses WHERE teacher_id = ? AND academic_period_id = ?)
    )
    ORDER BY u.first_name, u.last_name
");
$stmt->execute([$_SESSION['user_id'], $selected_year_id]);
$available_students = $stmt->fetchAll();

function getRandomIconClass($userId) {
    $icons = [
        'bi-person', 'bi-person-circle', 'bi-person-badge', 'bi-person-fill', 'bi-emoji-smile', 'bi-people', 'bi-person-lines-fill', 'bi-person-video', 'bi-person-check', 'bi-person-gear'
    ];
    return $icons[$userId % count($icons)];
}
function getRandomBgClass($userId) {
    $colors = [
        'bg-success', 'bg-warning', 'bg-danger', 'bg-info', 'bg-secondary', 'bg-dark', 'bg-green', 'bg-teal', 'bg-orange'
    ];
    return $colors[$userId % count($colors)];
}

function getSortClause($sort_by) {
    switch ($sort_by) {
        case 'name':
            return 'u.last_name ASC, u.first_name ASC';
        case 'course':
            return 'c.course_name ASC';
        case 'enrolled':
            return 'e.enrolled_at DESC';
        case 'progress':
            return 'e.progress_percentage DESC';
        case 'score':
            return 'assessment_stats.avg_score DESC';
        case 'activity':
            return 'e.last_accessed DESC';
        default:
            return 'u.last_name ASC, u.first_name ASC';
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Students</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollStudentModal">
                    <i class="bi bi-person-plus me-2"></i>Enroll Student
                </button>
            </div>
        </div>
    </div>

    <!-- Academic Year Selection -->
    <div class="row mb-3">
        <div class="col-12">
            <form method="get" class="d-flex align-items-center">
                <label for="academic_period_id" class="me-2 fw-bold">Academic Period:</label>
                <select name="academic_period_id" id="academic_period_id" class="form-select w-auto me-2" onchange="this.form.submit()">
                    <?php foreach ($all_years as $year): ?>
                        <option value="<?= $year['id'] ?>" <?= $selected_year_id == $year['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['academic_year'] . ' - ' . $year['semester_name']) ?><?= !$year['is_active'] ? ' (Inactive)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn btn-primary btn-sm">Go</button></noscript>
            </form>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <input type="hidden" name="academic_period_id" value="<?= $selected_year_id ?>">
                        <div class="col-md-6">
                            <label for="course" class="form-label">Filter by Course</label>
                            <select class="form-select" id="course" name="course" onchange="this.form.submit()">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Filter by Status</label>
                            <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="students.php?academic_period_id=<?= $selected_year_id ?>" class="btn btn-outline-secondary">Clear Filters</a>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Bulk Actions</label>
                            <div class="d-grid">
                                <button type="button" class="btn btn-danger" id="bulkKickBtn" disabled>
                                    <i class="bi bi-person-x-fill"></i> Kick Selected
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4 students-stats">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0 total-students"><?php echo count($course_enrollments); ?></h4>
                            <p class="mb-0">Total Students</p>
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
                            <h4 class="mb-0 active-students">
                                <?php 
                                $active_students = array_filter($course_enrollments, function($e) { 
                                    return ($e['enrollment_status'] ?? 'active') === 'active'; 
                                });
                                echo count($active_students);
                                ?>
                            </h4>
                            <p class="mb-0">Active Students</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-person-check fs-1"></i>
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
                            <h4 class="mb-0 avg-progress">
                                <?php 
                                $avg_progress = count($course_enrollments) > 0 ? 
                                    array_sum(array_column($course_enrollments, 'course_progress')) / count($course_enrollments) : 0;
                                echo number_format($avg_progress, 1);
                                ?>%
                            </h4>
                            <p class="mb-0">Avg Progress</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fs-1"></i>
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
                            <h4 class="mb-0 avg-score">
                                <?php 
                                $avg_score = count($course_enrollments) > 0 ? 
                                    array_sum(array_column($course_enrollments, 'avg_score')) / count($course_enrollments) : 0;
                                echo number_format($avg_score, 1);
                                ?>%
                            </h4>
                            <p class="mb-0">Avg Score</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-trophy fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students List -->
    <div class="row">
        <div class="col-12">
            <div class="card students-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Enrolled Students (<?php echo count($course_enrollments); ?>)</h5>
                    <div class="d-flex align-items-center">
                        <div id="updateIndicator" class="me-2" style="display: none;">
                            <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                            <small class="text-muted ms-1">Updating...</small>
                        </div>
                        <small class="text-muted" id="lastUpdate">
                            <i class="bi bi-clock me-1"></i>Last updated: Just now
                        </small>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="updateProgressData()" title="Refresh Progress">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($course_enrollments)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-people fs-1 text-muted mb-3"></i>
                            <h6>No Students Found</h6>
                            <p class="text-muted">No students enrolled in your courses for the selected academic year. Enroll students to start tracking their progress.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollStudentModal">
                                <i class="bi bi-person-plus me-1"></i>Enroll First Student
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive students-table-container">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name'])); ?>" class="text-decoration-none text-dark">
                                                Student <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                        <th>Student ID</th>
                                        <th>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'course'])); ?>" class="text-decoration-none text-dark">
                                                Course <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                        <th>Section</th>
                                        <th>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'enrolled'])); ?>" class="text-decoration-none text-dark">
                                                Enrolled <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'progress'])); ?>" class="text-decoration-none text-dark">
                                                Progress <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                        <th>Assessments</th>
                                        <th>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'score'])); ?>" class="text-decoration-none text-dark">
                                                Avg % <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'activity'])); ?>" class="text-decoration-none text-dark">
                                                Last Activity <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                        <th>Kick</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_enrollments as $enrollment): ?>
                                        <tr data-student-id="<?php echo $enrollment['student_id']; ?>">
                                            <td>
                                                <input type="checkbox" class="form-check-input student-checkbox" value="<?php echo $enrollment['student_id']; ?>">
                                            </td>
                                            <td>
                                                <img src="<?php echo getProfilePictureUrl($enrollment['profile_picture'] ?? null, 'medium'); ?>" class="profile-picture me-2" alt="Student" style="width: 48px; height: 48px; object-fit: cover;">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($enrollment['email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($enrollment['neust_student_id'])): ?>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($enrollment['neust_student_id']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">No ID</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($enrollment['course_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($enrollment['course_code']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($enrollment['section_name'] && $enrollment['section_name'] !== 'Not Assigned'): ?>
                                                    <span class="badge bg-light text-dark" style="font-size:0.98em; border-radius:1em; min-width:2.2em; border:1px solid #e5e7eb; color:var(--main-green);">
                                                        <?php echo 'BSIT-' . htmlspecialchars($enrollment['section_year'] . $enrollment['section_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php 
                                                    // Use actual enrollment date
                                                    if ($enrollment['enrolled_at']) {
                                                        echo date('M j, Y', strtotime($enrollment['enrolled_at']));
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php 
                                                    $progress_percentage = $enrollment['course_progress'] ?? 0;
                                                    $progress_color = $progress_percentage >= 80 ? 'bg-success' : 
                                                                     ($progress_percentage >= 60 ? 'bg-warning' : 
                                                                     ($progress_percentage >= 40 ? 'bg-info' : 'bg-danger'));
                                                    ?>
                                                    <div class="progress me-2" style="width: 60px; height: 6px;">
                                                        <div class="progress-bar <?php echo $progress_color; ?>" style="width: <?php echo min($progress_percentage, 100); ?>%"></div>
                                                    </div>
                                                    <small class="fw-bold progress-text"><?php echo number_format($progress_percentage, 1); ?>%</small>
                                                </div>
                                                <small class="text-muted">
                                                    Course Progress
                                                </small>
                                            </td>
                                            <td>
                                                <?php 
                                                $completed_assessments = $enrollment['completed_assessments'] ?? 0;
                                                $total_assessments = $enrollment['total_assessments'] ?? 0;
                                                $assessment_color = $completed_assessments == $total_assessments && $total_assessments > 0 ? 'bg-success' : 
                                                                   ($completed_assessments > 0 ? 'bg-warning' : 'bg-secondary');
                                                ?>
                                                <span class="badge <?php echo $assessment_color; ?> assessment-badge">
                                                    <i class="bi bi-file-text me-1"></i><?php echo $completed_assessments; ?>/<?php echo $total_assessments; ?>
                                                </span>
                                                <?php if ($enrollment['total_attempts'] > 0): ?>
                                                    <small class="text-muted d-block">
                                                        <?php echo $enrollment['total_attempts']; ?> attempts
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $avg_score = $enrollment['avg_score'] ?? 0;
                                                $best_score = $enrollment['best_score'] ?? 0;
                                                $score_color = $avg_score >= 80 ? 'bg-success' : 
                                                              ($avg_score >= 70 ? 'bg-warning' : 
                                                              ($avg_score >= 50 ? 'bg-info' : 'bg-danger'));
                                                ?>
                                                <span class="badge <?php echo $score_color; ?> score-badge">
                                                    <?php echo number_format($avg_score, 1); ?>%
                                                </span>
                                                <?php if ($best_score > 0): ?>
                                                    <small class="text-muted d-block">
                                                        Best: <?php echo number_format($best_score, 1); ?>%
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $last_activity = $enrollment['last_activity'] ?? null;
                                                if ($last_activity) {
                                                    $time_ago = time() - strtotime($last_activity);
                                                    $days_ago = floor($time_ago / (24 * 60 * 60));
                                                    
                                                    if ($days_ago == 0) {
                                                        $activity_text = 'Today';
                                                        $activity_color = 'bg-success';
                                                    } elseif ($days_ago == 1) {
                                                        $activity_text = 'Yesterday';
                                                        $activity_color = 'bg-warning';
                                                    } elseif ($days_ago <= 7) {
                                                        $activity_text = $days_ago . ' days ago';
                                                        $activity_color = 'bg-info';
                                                    } else {
                                                        $activity_text = $days_ago . ' days ago';
                                                        $activity_color = 'bg-danger';
                                                    }
                                                    
                                                    echo '<span class="badge ' . $activity_color . ' activity-badge">';
                                                    echo '<i class="bi bi-clock me-1"></i>' . $activity_text;
                                                    echo '</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">No Activity</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = $enrollment['enrollment_status'] === 'active' ? 'bg-success' : 
                                                              ($enrollment['enrollment_status'] === 'completed' ? 'bg-primary' : 'bg-warning');
                                                $status_text = ucfirst($enrollment['enrollment_status'] ?? 'active');
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($status_text); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="student_detail.php?id=<?php echo $enrollment['student_id']; ?>&course=<?php echo $enrollment['course_id']; ?>" 
                                                       class="btn btn-outline-primary" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="student_progress.php?id=<?php echo $enrollment['student_id']; ?>&course=<?php echo $enrollment['course_id']; ?>" 
                                                       class="btn btn-outline-info" title="Progress Report">
                                                        <i class="bi bi-graph-up"></i>
                                                    </a>
                                                </div>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to KICK this student from the course? This action cannot be undone.')">
                                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="kick_student">
                                                    <input type="hidden" name="student_id" value="<?php echo $enrollment['student_id']; ?>">
                                                    <input type="hidden" name="course_id" value="<?php echo $enrollment['course_id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Kick Student from Course">
                                                        <i class="bi bi-person-x-fill"></i>
                                                    </button>
                                                </form>
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

<style>
/* Enhanced Students Table Scrolling */
.students-table-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    position: relative;
}

/* Custom scrollbar for students table */
.students-table-container::-webkit-scrollbar {
    width: 8px;
}

.students-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.students-table-container::-webkit-scrollbar-thumb {
    background: #28a745;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.students-table-container::-webkit-scrollbar-thumb:hover {
    background: #218838;
}

/* Firefox scrollbar styling */
.students-table-container {
    scrollbar-width: thin;
    scrollbar-color: #28a745 #f1f1f1;
}

/* Enhanced table styling */
.students-table-container .table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.students-table-container .table thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 16px 12px;
}

.students-table-container .table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f0f0f0;
}

.students-table-container .table tbody tr:hover {
    background-color: rgba(40, 167, 69, 0.05);
    transform: translateX(3px);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.1);
}

.students-table-container .table tbody td {
    padding: 16px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

/* Enhanced button styling */
.students-table-container .btn-group .btn {
    padding: 6px 12px;
    font-size: 0.875rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    margin: 0 2px;
}

.students-table-container .btn-group .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Badge enhancements */
.students-table-container .badge {
    font-size: 0.75rem;
    padding: 6px 10px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.students-table-container .badge:hover {
    transform: scale(1.05);
}

/* Student profile picture styling */
.students-table-container .table tbody td .profile-picture {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.students-table-container .table tbody tr:hover .profile-picture {
    transform: scale(1.1);
    border-color: #28a745;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

/* Student name styling */
.students-table-container .table tbody td .fw-bold {
    transition: all 0.3s ease;
}

.students-table-container .table tbody tr:hover .fw-bold {
    color: #28a745;
    transform: translateX(2px);
}

/* Progress bar enhancements */
.students-table-container .progress {
    transition: all 0.3s ease;
}

.students-table-container .table tbody tr:hover .progress {
    transform: scale(1.05);
}

/* Scroll indicators for students table */
.students-scroll-indicator {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 15;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.students-scroll-indicator.show {
    opacity: 1;
}

.students-scroll-indicator-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.students-scroll-indicator i {
    background: rgba(40, 167, 69, 0.8);
    color: white;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.students-scroll-indicator-top.hide,
.students-scroll-indicator-bottom.hide {
    opacity: 0.3;
}

/* Card enhancements */
.students-card {
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
}

.students-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    padding: 16px 20px;
}

.students-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #495057;
}

/* Statistics cards enhancements */
.students-stats .card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.students-stats .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.students-stats .card .card-body {
    padding: 20px;
}

.students-stats .card i {
    transition: transform 0.3s ease;
}

.students-stats .card:hover i {
    transform: scale(1.1);
}

/* Mobile responsiveness for students table */
@media (max-width: 991.98px) {
    .students-table-container {
        max-height: 450px;
    }
    
    .students-table-container .table thead th,
    .students-table-container .table tbody td {
        padding: 12px 8px;
        font-size: 0.9rem;
    }
}

@media (max-width: 575.98px) {
    .students-table-container {
        max-height: 350px;
    }
    
    .students-table-container .table thead th,
    .students-table-container .table tbody td {
        padding: 8px 4px;
        font-size: 0.85rem;
    }
    
    .students-table-container .btn-group .btn {
        padding: 4px 8px;
        font-size: 0.75rem;
    }
    
    .students-table-container .table tbody td .profile-picture {
        width: 36px !important;
        height: 36px !important;
    }
}

/* Loading and animation states */
.students-table-loading {
    opacity: 0.6;
    pointer-events: none;
}

.student-row-enter {
    animation: studentRowEnter 0.5s ease-out;
}

@keyframes studentRowEnter {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.student-row-exit {
    animation: studentRowExit 0.5s ease-in;
}

@keyframes studentRowExit {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(-100%);
    }
}

/* Enhanced modal styling */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}

.modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    border-radius: 12px 12px 0 0;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 12px 12px;
}

/* Form enhancements */
.form-control:focus, .form-select:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}
</style>

<!-- Enroll Student Modal -->
<div class="modal fade" id="enrollStudentModal" tabindex="-1" aria-labelledby="enrollStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="enrollStudentModalLabel">Enroll Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="enroll_student">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Select Student</label>
                        <select class="form-select" id="student_id" name="student_id" required>
                            <option value="">Choose a student...</option>
                            <?php foreach ($available_students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Select Course</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Choose a course...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Bulk selection functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateBulkKickButton();
});

document.querySelectorAll('.student-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkKickButton);
});

function updateBulkKickButton() {
    const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
    const bulkKickBtn = document.getElementById('bulkKickBtn');
    
    if (selectedCheckboxes.length > 0) {
        bulkKickBtn.disabled = false;
        bulkKickBtn.textContent = `Kick Selected (${selectedCheckboxes.length})`;
    } else {
        bulkKickBtn.disabled = true;
        bulkKickBtn.innerHTML = '<i class="bi bi-person-x-fill"></i> Kick Selected';
    }
}

// Bulk kick functionality
document.getElementById('bulkKickBtn').addEventListener('click', function() {
    const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
    if (selectedCheckboxes.length === 0) return;
    
    const confirmMessage = `Are you sure you want to KICK ${selectedCheckboxes.length} student(s) from their courses? This action cannot be undone and will remove all their progress data.`;
    
    if (confirm(confirmMessage)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="bulk_kick_students">
            ${Array.from(selectedCheckboxes).map(checkbox => 
                `<input type="hidden" name="student_ids[]" value="${checkbox.value}">`
            ).join('')}
        `;
        document.body.appendChild(form);
        form.submit();
    }
});

// Real-time progress updates
let progressUpdateInterval;
let isPageVisible = true;

// Function to update progress data
function updateProgressData() {
    if (!isPageVisible) return;
    
    // Show update indicator
    const updateIndicator = document.getElementById('updateIndicator');
    const lastUpdate = document.getElementById('lastUpdate');
    
    if (updateIndicator) updateIndicator.style.display = 'block';
    
    const url = 'ajax_get_student_progress.php?' + new URLSearchParams({
        academic_period_id: '<?php echo $selected_year_id; ?>',
        course: '<?php echo $course_filter; ?>',
        status: '<?php echo $status_filter; ?>',
        sort: '<?php echo $sort_by; ?>'
    });
    
    console.log('Fetching progress data from:', url);
    
    fetch(url)
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Received data:', data);
        if (data.success) {
            updateProgressTable(data.students);
            updateSummaryStats(data.summary);
            
            // Update last update time
            if (lastUpdate) {
                const now = new Date();
                lastUpdate.innerHTML = `<i class="bi bi-clock me-1"></i>Last updated: ${now.toLocaleTimeString()}`;
            }
        } else {
            console.error('API returned error:', data.error);
        }
    })
    .catch(error => {
        console.error('Error updating progress:', error);
    })
    .finally(() => {
        // Hide update indicator
        if (updateIndicator) updateIndicator.style.display = 'none';
    });
}

// Function to update progress table
function updateProgressTable(students) {
    console.log('Updating progress table with', students.length, 'students');
    students.forEach(student => {
        const row = document.querySelector(`tr[data-student-id="${student.student_id}"]`);
        console.log('Looking for student ID:', student.student_id, 'Found row:', row);
        
        if (row) {
            // Update progress bar
            const progressBar = row.querySelector('.progress-bar');
            const progressText = row.querySelector('.progress-text');
            const progressPercentage = parseFloat(student.course_progress) || 0;
            
            console.log('Student', student.student_id, 'Progress:', progressPercentage, 'Found elements:', {
                progressBar: !!progressBar,
                progressText: !!progressText
            });
            
            if (progressBar && progressText) {
                progressBar.style.width = Math.min(progressPercentage, 100) + '%';
                progressText.textContent = progressPercentage.toFixed(1) + '%';
                
                // Update color based on progress
                progressBar.className = 'progress-bar ' + getProgressColor(progressPercentage);
                console.log('Updated progress for student', student.student_id, 'to', progressPercentage + '%');
            }
            
            // Update assessment data
            const assessmentBadge = row.querySelector('.assessment-badge');
            if (assessmentBadge) {
                const completed = student.completed_assessments || 0;
                const total = student.total_assessments || 0;
                assessmentBadge.innerHTML = `<i class="bi bi-file-text me-1"></i>${completed}/${total}`;
                assessmentBadge.className = 'badge ' + getAssessmentColor(completed, total);
            }
            
            // Update average score
            const scoreBadge = row.querySelector('.score-badge');
            if (scoreBadge) {
                const avgScore = parseFloat(student.avg_score) || 0;
                scoreBadge.textContent = avgScore.toFixed(1) + '%';
                scoreBadge.className = 'badge ' + getScoreColor(avgScore);
            }
            
            // Update last activity
            const activityBadge = row.querySelector('.activity-badge');
            if (activityBadge && student.last_activity) {
                const timeAgo = getTimeAgo(student.last_activity);
                activityBadge.innerHTML = `<i class="bi bi-clock me-1"></i>${timeAgo.text}`;
                activityBadge.className = 'badge ' + timeAgo.color;
            }
        }
    });
}

// Function to update summary statistics
function updateSummaryStats(summary) {
    // Update total students
    const totalStudents = document.querySelector('.total-students');
    if (totalStudents) {
        totalStudents.textContent = summary.total_students || 0;
    }
    
    // Update active students
    const activeStudents = document.querySelector('.active-students');
    if (activeStudents) {
        activeStudents.textContent = summary.active_students || 0;
    }
    
    // Update average progress
    const avgProgress = document.querySelector('.avg-progress');
    if (avgProgress) {
        avgProgress.textContent = (summary.avg_progress || 0).toFixed(1) + '%';
    }
    
    // Update average score
    const avgScore = document.querySelector('.avg-score');
    if (avgScore) {
        avgScore.textContent = (summary.avg_score || 0).toFixed(1) + '%';
    }
}

// Helper functions for color coding
function getProgressColor(percentage) {
    if (percentage >= 80) return 'bg-success';
    if (percentage >= 60) return 'bg-warning';
    if (percentage >= 40) return 'bg-info';
    return 'bg-danger';
}

function getAssessmentColor(completed, total) {
    if (completed === total && total > 0) return 'bg-success';
    if (completed > 0) return 'bg-warning';
    return 'bg-secondary';
}

function getScoreColor(score) {
    if (score >= 80) return 'bg-success';
    if (score >= 70) return 'bg-warning';
    if (score >= 50) return 'bg-info';
    return 'bg-danger';
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const activityTime = new Date(timestamp);
    const diffMs = now - activityTime;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) {
        return { text: 'Today', color: 'bg-success' };
    } else if (diffDays === 1) {
        return { text: 'Yesterday', color: 'bg-warning' };
    } else if (diffDays <= 7) {
        return { text: diffDays + ' days ago', color: 'bg-info' };
    } else {
        return { text: diffDays + ' days ago', color: 'bg-danger' };
    }
}

// Page visibility API to pause updates when tab is not visible
document.addEventListener('visibilitychange', function() {
    isPageVisible = !document.hidden;
    if (isPageVisible) {
        // Resume updates when page becomes visible
        updateProgressData();
    }
});

// Add CSS for smooth transitions
const style = document.createElement('style');
style.textContent = `
    .progress-bar, .badge, .progress-text {
        transition: all 0.3s ease-in-out;
    }
    .update-indicator {
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }
    .update-indicator.show {
        opacity: 1;
    }
`;
document.head.appendChild(style);

// Start real-time updates (every 30 seconds)
progressUpdateInterval = setInterval(updateProgressData, 30000);

// Test function to manually update progress bars
function testProgressBars() {
    console.log('Testing progress bars...');
    const rows = document.querySelectorAll('tr[data-student-id]');
    console.log('Found', rows.length, 'student rows');
    
    rows.forEach((row, index) => {
        const progressBar = row.querySelector('.progress-bar');
        const progressText = row.querySelector('.progress-text');
        
        if (progressBar && progressText) {
            const testProgress = (index + 1) * 20; // 20%, 40%, 60%, 80%, 100%
            progressBar.style.width = Math.min(testProgress, 100) + '%';
            progressText.textContent = testProgress + '%';
            progressBar.className = 'progress-bar ' + getProgressColor(testProgress);
            console.log('Updated row', index, 'to', testProgress + '%');
        } else {
            console.log('Row', index, 'missing progress elements');
        }
    });
}

// Add test button
const testButton = document.createElement('button');
testButton.textContent = 'Test Progress Bars';
testButton.className = 'btn btn-sm btn-warning ms-2';
testButton.onclick = testProgressBars;
document.querySelector('.card-header .d-flex').appendChild(testButton);

// Initial update after 5 seconds
setTimeout(updateProgressData, 5000);

// Enhanced scrolling behavior for students table
document.addEventListener('DOMContentLoaded', function() {
    function enhanceStudentsTableScrolling() {
        const tableContainer = document.querySelector('.students-table-container');
        
        if (tableContainer) {
            // Add smooth scrolling behavior
            tableContainer.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const cardContainer = tableContainer.closest('.card');
            if (cardContainer) {
                addStudentsTableScrollIndicators(tableContainer, cardContainer);
            }
        }
    }
    
    // Add scroll indicators to students table
    function addStudentsTableScrollIndicators(scrollContainer, cardContainer) {
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = 'students-scroll-indicator';
        scrollIndicator.innerHTML = `
            <div class="students-scroll-indicator-content">
                <i class="bi bi-chevron-up students-scroll-indicator-top"></i>
                <i class="bi bi-chevron-down students-scroll-indicator-bottom"></i>
            </div>
        `;
        
        cardContainer.style.position = 'relative';
        cardContainer.appendChild(scrollIndicator);
        
        // Update scroll indicators based on scroll position
        function updateStudentsScrollIndicators() {
            const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
            const isAtTop = scrollContainer.scrollTop === 0;
            const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
            
            if (isScrollable) {
                scrollIndicator.classList.add('show');
                scrollIndicator.querySelector('.students-scroll-indicator-top').classList.toggle('hide', isAtTop);
                scrollIndicator.querySelector('.students-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
            } else {
                scrollIndicator.classList.remove('show');
            }
        }
        
        // Initial check
        updateStudentsScrollIndicators();
        
        // Update on scroll
        scrollContainer.addEventListener('scroll', updateStudentsScrollIndicators);
        
        // Update on resize
        window.addEventListener('resize', updateStudentsScrollIndicators);
    }
    
    // Initialize enhanced students table scrolling
    enhanceStudentsTableScrolling();
});
</script>

<?php require_once '../includes/footer.php'; ?> 