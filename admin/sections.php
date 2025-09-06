<?php
$page_title = 'Manage Sections';
require_once '../includes/header.php';
requireRole('admin');

// Helper function to format year with proper ordinal suffix
function formatYear($year) {
    $suffixes = [
        1 => 'st',
        2 => 'nd', 
        3 => 'rd',
        4 => 'th'
    ];
    return $year . ($suffixes[$year] ?? 'th') . ' Year';
}

// Fetch all active courses for assignment
$courses_for_assignment = [];
$course_stmt = $db->prepare("SELECT id, course_name, course_code FROM courses WHERE is_archived = 0 ORDER BY course_name");
$course_stmt->execute();
$courses_for_assignment = $course_stmt->fetchAll();

// Fetch all academic periods for assignment
$academic_periods = [];
$period_stmt = $db->prepare("SELECT id, CONCAT(academic_year, ' - ', semester_name) as period_name FROM academic_periods ORDER BY academic_year DESC, semester_name");
$period_stmt->execute();
$academic_periods = $period_stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_section'])) {
        $section_code = trim($_POST['section_code']);
        $section_year = intval($_POST['section_year']);
        $academic_period_id = intval($_POST['academic_period_id']);
        $section_description = trim($_POST['section_description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($section_code) && $section_year >= 1 && $section_year <= 4 && $academic_period_id > 0) {
            $stmt = $db->prepare("INSERT INTO sections (section_name, year_level, academic_period_id, description, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$section_code, $section_year, $academic_period_id, $section_description, $is_active]);
            $section_id = $db->lastInsertId();
            // Handle course assignments - update courses.sections JSON column
            $assigned_courses = $_POST['assigned_courses'] ?? [];
            if (!empty($assigned_courses)) {
                foreach ($assigned_courses as $course_id) {
                    // Get current sections for this course
                    $stmt = $db->prepare("SELECT sections FROM courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch();
                    
                    $current_sections = [];
                    if ($course && $course['sections']) {
                        $current_sections = json_decode($course['sections'], true) ?: [];
                    }
                    
                    // Add section if not already present
                    if (!in_array($section_id, $current_sections)) {
                        $current_sections[] = $section_id;
                        $sections_json = json_encode($current_sections);
                        
                        $stmt = $db->prepare("UPDATE courses SET sections = ? WHERE id = ?");
                        $stmt->execute([$sections_json, $course_id]);
                    }
                }
            }
            echo "<script>window.location.href='sections.php';</script>";
            exit;
        }
    }
    
    if (isset($_POST['update_section'])) {
        $section_id = intval($_POST['section_id']);
        $section_code = trim($_POST['section_code']);
        $section_year = intval($_POST['section_year']);
        $academic_period_id = intval($_POST['academic_period_id']);
        $section_description = trim($_POST['section_description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($section_code) && $section_year >= 1 && $section_year <= 4 && $academic_period_id > 0) {
            $stmt = $db->prepare("UPDATE sections SET section_name = ?, year_level = ?, academic_period_id = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$section_code, $section_year, $academic_period_id, $section_description, $is_active, $section_id]);
            // Handle course assignments - update courses.sections JSON column
            $assigned_courses = $_POST['assigned_courses'] ?? [];
            
            // First, remove this section from all courses
            $stmt = $db->prepare("SELECT id, sections FROM courses WHERE sections IS NOT NULL AND sections != '[]'");
            $stmt->execute();
            $all_courses = $stmt->fetchAll();
            
            foreach ($all_courses as $course) {
                if ($course['sections']) {
                    $current_sections = json_decode($course['sections'], true) ?: [];
                    $key = array_search($section_id, $current_sections);
                    if ($key !== false) {
                        unset($current_sections[$key]);
                        $sections_json = json_encode(array_values($current_sections));
                        
                        $stmt = $db->prepare("UPDATE courses SET sections = ? WHERE id = ?");
                        $stmt->execute([$sections_json, $course['id']]);
                    }
                }
            }
            
            // Now add this section to the assigned courses
            if (!empty($assigned_courses)) {
                foreach ($assigned_courses as $course_id) {
                    // Get current sections for this course
                    $stmt = $db->prepare("SELECT sections FROM courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch();
                    
                    $current_sections = [];
                    if ($course && $course['sections']) {
                        $current_sections = json_decode($course['sections'], true) ?: [];
                    }
                    
                    // Add section if not already present
                    if (!in_array($section_id, $current_sections)) {
                        $current_sections[] = $section_id;
                        $sections_json = json_encode($current_sections);
                        
                        $stmt = $db->prepare("UPDATE courses SET sections = ? WHERE id = ?");
                        $stmt->execute([$sections_json, $course_id]);
                    }
                }
            }
            echo "<script>window.location.href='sections.php';</script>";
            exit;
        }
    }
    
    if (isset($_POST['delete_section'])) {
        $section_id = intval($_POST['delete_section_id']);
        
        // First, delete all related records to avoid foreign key constraint violations
        $db->beginTransaction();
        try {
            // Clear existing assignments
            $stmt = $db->prepare("UPDATE sections SET students = '[]', teachers = '[]' WHERE id = ?");
            $stmt->execute([$section_id]);
            
            // Now delete the section itself
            $stmt = $db->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->execute([$section_id]);
            
            $db->commit();
            echo "<script>window.location.href='sections.php';</script>";
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            // You might want to show an error message here
            echo "<script>alert('Error deleting section: " . addslashes($e->getMessage()) . "'); window.location.href='sections.php';</script>";
            exit;
        }
    }
    
    if (isset($_POST['assign_users'])) {
        $section_id = intval($_POST['assign_section_id']);
        $students = $_POST['students'] ?? [];
        $teachers = $_POST['teachers'] ?? [];
        
        // Clear existing assignments
        $stmt = $db->prepare("UPDATE sections SET students = '[]', teachers = '[]' WHERE id = ?");
        $stmt->execute([$section_id]);
        
        // Assign students
        if (!empty($students)) {
            $students_json = json_encode($students);
            $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
            $stmt->execute([$students_json, $section_id]);
        }
        
        // Assign teachers
        if (!empty($teachers)) {
            $teachers_json = json_encode($teachers);
            $stmt = $db->prepare("UPDATE sections SET teachers = ? WHERE id = ?");
            $stmt->execute([$teachers_json, $section_id]);
        }
        
        echo "<script>window.location.href='sections.php';</script>";
        exit;
    }
    
    if (isset($_POST['add_students'])) {
        $section_id = intval($_POST['add_students_section_id']);
        $students_to_add = $_POST['students_to_add'] ?? [];
        
        if (!empty($students_to_add)) {
            // Get current students in the section
            $stmt = $db->prepare("SELECT students FROM sections WHERE id = ?");
            $stmt->execute([$section_id]);
            $section = $stmt->fetch();
            
            $current_students = [];
            if ($section && $section['students']) {
                $current_students = json_decode($section['students'], true) ?: [];
            }
            
            // Add new students (avoid duplicates)
            foreach ($students_to_add as $student_id) {
                if (!in_array($student_id, $current_students)) {
                    $current_students[] = $student_id;
                }
            }
            
            // Update the section with new students
            $students_json = json_encode($current_students);
            $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
            $stmt->execute([$students_json, $section_id]);
            
            echo "<script>alert('Students added successfully!'); window.location.href='sections.php';</script>";
        } else {
            echo "<script>alert('No students selected.'); window.location.href='sections.php';</script>";
        }
        exit;
    }
}

// Fetch all sections with academic period info and detailed statistics
$sections = [];
$section_sql = "SELECT s.*, ap.academic_year, ap.semester_name, ap.is_active as period_active,
                (SELECT COUNT(*) FROM course_enrollments ce 
                 WHERE ce.course_id IN (
                     SELECT c.id FROM courses c 
                     WHERE JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
                 ) AND ce.status = 'active') as enrolled_students_count
                FROM sections s 
                LEFT JOIN academic_periods ap ON s.academic_period_id = ap.id 
                ORDER BY s.academic_period_id DESC, s.year_level, s.section_name";
$section_result = $db->query($section_sql);
if ($section_result && $section_result->rowCount() > 0) {
    $sections = $section_result->fetchAll();
}

// --- FILTER LOGIC ---
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$period_filter = isset($_GET['period']) ? intval($_GET['period']) : '';

$filtered_sections = array_filter($sections, function($sec) use ($year_filter, $status_filter, $period_filter) {
    $ok = true;
    if ($year_filter && $sec['year_level'] != $year_filter) $ok = false;
    if ($status_filter !== '' && $status_filter !== 'all') {
        if ($status_filter === 'active' && !$sec['is_active']) $ok = false;
        if ($status_filter === 'inactive' && $sec['is_active']) $ok = false;
    }
    if ($period_filter && $sec['academic_period_id'] != $period_filter) $ok = false;
    return $ok;
});

// Build an array of used codes per year
$used_codes_per_year = [];
foreach ($sections as $sec) {
    $yr = (int)$sec['year_level'];
    $code = strtoupper($sec['section_name']);
    if (!isset($used_codes_per_year[$yr])) $used_codes_per_year[$yr] = [];
    $used_codes_per_year[$yr][] = $code;
}

// Get comprehensive statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_sections,
        SUM(JSON_LENGTH(COALESCE(students, '[]'))) as total_students_assigned,
        SUM(JSON_LENGTH(COALESCE(teachers, '[]'))) as total_teachers_assigned,
        COUNT(CASE WHEN s.is_active = 1 THEN 1 END) as active_sections,
        COUNT(CASE WHEN s.is_active = 0 THEN 1 END) as inactive_sections,
        COUNT(CASE WHEN ap.is_active = 1 THEN 1 END) as current_period_sections,
        COUNT(DISTINCT s.year_level) as year_levels_covered,
        COUNT(DISTINCT s.academic_period_id) as academic_periods_covered,
        (SELECT COUNT(*) FROM course_enrollments WHERE status = 'active') as total_enrolled_students,
        (SELECT COUNT(*) FROM courses WHERE is_archived = 0) as total_courses
    FROM sections s
    LEFT JOIN academic_periods ap ON s.academic_period_id = ap.id
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch();


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
                        <i class="bi bi-collection-fill fs-1 text-primary"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['total_sections'] ?></h3>
                    <p class="text-muted mb-0 small">Total Sections</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-check-circle-fill fs-1 text-success"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['active_sections'] ?></h3>
                    <p class="text-muted mb-0 small">Active Sections</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-calendar-check-fill fs-1 text-info"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['current_period_sections'] ?></h3>
                    <p class="text-muted mb-0 small">Current Period</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-people-fill fs-1 text-warning"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['total_students_assigned'] ?></h3>
                    <p class="text-muted mb-0 small">Students Assigned</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-mortarboard-fill fs-1 text-secondary"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['year_levels_covered'] ?></h3>
                    <p class="text-muted mb-0 small">Year Levels</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-book-fill fs-1 text-danger"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['total_courses'] ?></h3>
                    <p class="text-muted mb-0 small">Total Courses</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-x-circle-fill fs-1 text-danger"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['inactive_sections'] ?></h3>
                    <p class="text-muted mb-0 small">Inactive Sections</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-people-fill fs-1 text-warning"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['total_students_assigned'] ?></h3>
                    <p class="text-muted mb-0 small">Students Assigned</p>
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
                                <i class="bi bi-collection me-2"></i>Section Management
                            </h4>
                            <p class="text-muted mb-0 small">Manage sections with year levels and active/inactive status</p>
                        </div>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                            <i class="bi bi-plus-circle me-2"></i>Add Section
                        </button>
                        </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters above the table -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label for="year_filter" class="form-label fw-semibold">Year Level:</label>
                            <select name="year" id="year_filter" class="form-select form-select-sm">
                                <option value="">All Years</option>
                                <option value="1" <?= $year_filter === 1 ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= $year_filter === 2 ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= $year_filter === 3 ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= $year_filter === 4 ? 'selected' : '' ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status_filter" class="form-label fw-semibold">Status:</label>
                            <select name="status" id="status_filter" class="form-select form-select-sm">
                                <option value="all" <?= $status_filter === 'all' || $status_filter === '' ? 'selected' : '' ?>>All</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="period_filter" class="form-label fw-semibold">Academic Period:</label>
                            <select name="period" id="period_filter" class="form-select form-select-sm">
                                <option value="">All Periods</option>
                                <?php foreach ($academic_periods as $period): ?>
                                    <option value="<?= $period['id'] ?>" <?= (isset($_GET['period']) && $_GET['period'] == $period['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($period['period_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                            <a href="sections.php" class="btn btn-outline-secondary btn-sm ms-1">
                                <i class="bi bi-x-circle me-1"></i>Clear
                            </a>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <span class="badge bg-info fs-6">
                                    <i class="bi bi-list-ul me-1"></i><?= count($filtered_sections) ?> sections found
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Sections List -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-semibold">
                            <i class="bi bi-list-ul me-2"></i>All Sections
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-primary fs-6"><?= count($filtered_sections) ?> sections</span>
                            <?php if ($period_filter): ?>
                                <span class="badge bg-info fs-6">Filtered by Period</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($filtered_sections)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-collection fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No sections found</h5>
                            <p class="text-muted">Start by adding your first section for this course.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                                <i class="bi bi-plus-circle me-2"></i>Add Section
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="border-0">
                                            <i class="bi bi-collection me-2"></i>Section Code
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-calendar me-2"></i>Year Level
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-text-paragraph me-2"></i>Description
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-toggle-on me-2"></i>Status
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-people me-2"></i>Students
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-person-workspace me-2"></i>Teachers
                                        </th>
                                        <th class="border-0"><i class="bi bi-book me-2"></i>Courses</th>
                                        <th class="border-0 text-center">
                                            <i class="bi bi-gear me-2"></i>Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filtered_sections as $section): ?>
                                        <?php
                                        // Get student count for this section
                                        $student_count_stmt = $db->prepare("SELECT JSON_LENGTH(COALESCE(students, '[]')) as student_count FROM sections WHERE id = ?");
                                        $student_count_stmt->execute([$section['id']]);
                                        $student_count = $student_count_stmt->fetchColumn();
                                        
                                        // Get teacher count for this section
                                        $teacher_count_stmt = $db->prepare("SELECT JSON_LENGTH(COALESCE(teachers, '[]')) as teacher_count FROM sections WHERE id = ?");
                                        $teacher_count_stmt->execute([$section['id']]);
                                        $teacher_count = $teacher_count_stmt->fetchColumn();
                                        
                                        // Get actual enrolled students count
                                        $enrolled_count = $section['enrolled_students_count'] ?? 0;
                                        
                                        // Create display name with academic period info
                                        $display_name = "BSIT-{$section['year_level']}{$section['section_name']}";
                                        $period_info = $section['academic_year'] . ' - ' . $section['semester_name'];
                                        $is_current_period = $section['period_active'] == 1;
                                        ?>
                                        <tr data-section-id="<?= $section['id'] ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                            <i class="bi bi-collection text-white"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($display_name) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($section['section_name']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="badge bg-secondary fs-6 mb-1"><?= formatYear($section['year_level']) ?></span>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($period_info) ?>
                                                        <?php if ($is_current_period): ?>
                                                            <span class="badge bg-success ms-1">Current</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($section['description'] ?: 'No description') ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($section['is_active']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-x-circle me-1"></i>Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column align-items-start">
                                                    <div class="d-flex gap-1 mb-1">
                                                        <span class="badge bg-info">
                                                            <i class="bi bi-people me-1"></i><?= $student_count ?> assigned
                                                        </span>
                                                        <?php if ($enrolled_count > 0): ?>
                                                            <span class="badge bg-success">
                                                                <i class="bi bi-mortarboard me-1"></i><?= $enrolled_count ?> enrolled
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <?php if ($student_count > 0): ?>
                                                            <button class="btn btn-sm btn-outline-info" 
                                                                    onclick="viewSectionStudents(<?= $section['id'] ?>, '<?= htmlspecialchars($display_name) ?>')"
                                                                    title="View Students">
                                                                <i class="bi bi-eye me-1"></i>View
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="openAddStudentsModal(<?= $section['id'] ?>)"
                                                                title="Add Students">
                                                            <i class="bi bi-person-plus me-1"></i>Add
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-person-workspace me-1"></i><?= $teacher_count ?> teachers
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $assigned_courses = [];
                                                // Get courses that have this section assigned via JSON columns
                                                $cs_stmt = $db->prepare("SELECT c.course_name, c.course_code, c.year_level, c.status FROM courses c WHERE c.sections IS NOT NULL AND JSON_SEARCH(c.sections, 'one', ?) IS NOT NULL AND c.is_archived = 0");
                                                $cs_stmt->execute([$section['id']]);
                                                $assigned_courses = $cs_stmt->fetchAll();
                                                
                                                if (empty($assigned_courses)) {
                                                    echo '<span class="text-muted">No courses assigned</span>';
                                                } else {
                                                    echo '<div class="d-flex flex-wrap gap-1">';
                                                    foreach ($assigned_courses as $course) {
                                                        $status_class = $course['status'] === 'active' ? 'bg-success' : 'bg-secondary';
                                                        echo '<span class="badge ' . $status_class . ' small">';
                                                        echo htmlspecialchars($course['course_code']);
                                                        echo '</span>';
                                                    }
                                                    echo '</div>';
                                                    echo '<small class="text-muted d-block mt-1">' . count($assigned_courses) . ' course(s) assigned</small>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editSectionModal<?= $section['id'] ?>"
                                                            title="Edit Section">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#assignUsersModal<?= $section['id'] ?>"
                                                            title="Assign Users">
                                                        <i class="bi bi-people"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" 
                                                            onclick="openAddStudentsModal(<?= $section['id'] ?>)"
                                                            title="Add Students">
                                                        <i class="bi bi-person-plus"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            onclick="viewSectionStudents(<?= $section['id'] ?>, '<?= htmlspecialchars($display_name) ?>')"
                                                            title="View Students">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <form method="post" action="sections.php" 
                                                          style="display:inline;" 
                                                          onsubmit="return confirm('Are you sure you want to delete this section?');">
                                                    <input type="hidden" name="delete_section_id" value="<?= $section['id'] ?>">
                                                        <button type="submit" name="delete_section" class="btn btn-sm btn-outline-danger" title="Delete Section">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                </form>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Edit Section Modal -->
                                        <div class="modal fade" id="editSectionModal<?= $section['id'] ?>" tabindex="-1" aria-labelledby="editSectionLabel<?= $section['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="sections.php">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title" id="editSectionLabel<?= $section['id'] ?>">
                                                                <i class="bi bi-pencil-square me-2"></i>Edit Section
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="section_id" value="<?= $section['id'] ?>">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="section_code<?= $section['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-collection me-2"></i>Section Code
                                                                        </label>
                                                                        <input type="text" class="form-control" id="section_code<?= $section['id'] ?>" name="section_code" required value="<?= htmlspecialchars($section['section_name']) ?>" maxlength="1" placeholder="A-Z">
                                                                        <small class="text-muted">Single letter (A-Z)</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                            <div class="mb-3">
                                                                        <label for="section_year<?= $section['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-calendar me-2"></i>Year Level
                                                                        </label>
                                                                        <select class="form-select" id="section_year<?= $section['id'] ?>" name="section_year" required>
                                                                            <option value="1" <?= $section['year_level'] == 1 ? 'selected' : '' ?>>1st Year</option>
                                                                            <option value="2" <?= $section['year_level'] == 2 ? 'selected' : '' ?>>2nd Year</option>
                                                                            <option value="3" <?= $section['year_level'] == 3 ? 'selected' : '' ?>>3rd Year</option>
                                                                            <option value="4" <?= $section['year_level'] == 4 ? 'selected' : '' ?>>4th Year</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="academic_period<?= $section['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-calendar-week me-2"></i>Academic Period
                                                                        </label>
                                                                        <select class="form-select" id="academic_period<?= $section['id'] ?>" name="academic_period_id" required>
                                                                            <?php foreach ($academic_periods as $period): ?>
                                                                                <option value="<?= $period['id'] ?>" <?= $section['academic_period_id'] == $period['id'] ? 'selected' : '' ?>>
                                                                                    <?= htmlspecialchars($period['period_name']) ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="section_description<?= $section['id'] ?>" class="form-label fw-semibold">
                                                                    <i class="bi bi-text-paragraph me-2"></i>Description
                                                                </label>
                                                                <textarea class="form-control" id="section_description<?= $section['id'] ?>" name="section_description" rows="2"><?= htmlspecialchars($section['description']) ?></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="is_active<?= $section['id'] ?>" name="is_active" <?= $section['is_active'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label fw-semibold" for="is_active<?= $section['id'] ?>">
                                                                        <i class="bi bi-toggle-on me-2"></i>Active Section
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <?php
                                                            $assigned_course_ids = [];
                                                            // Get course IDs that have this section assigned via JSON columns
                                                            $cs_stmt = $db->prepare("SELECT c.id FROM courses c WHERE c.sections IS NOT NULL AND JSON_SEARCH(c.sections, 'one', ?) IS NOT NULL");
                                                            $cs_stmt->execute([$section['id']]);
                                                            $assigned_course_ids = $cs_stmt->fetchAll(PDO::FETCH_COLUMN);
                                                            ?>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold d-flex align-items-center">
                                                                    <i class="bi bi-book me-2"></i>Assign Courses
                                                                    <span class="badge bg-primary ms-2 selectedCoursesCountBadge">0</span>
                                                                </label>
                                                                <div class="position-relative mb-2">
                                                                    <input type="text" class="form-control pr-5" placeholder="Search courses..." onkeyup="searchCoursesInModal(this)" oninput="toggleClearBtn(this)" style="padding-right:2.5rem;">
                                                                    <span class="position-absolute top-50 end-0 translate-middle-y me-2" style="z-index:2; cursor:pointer; display:none;" onclick="clearCourseSearch(this)">
                                                                        <i class="bi bi-x-lg text-secondary"></i>
                                                                    </span>
                                                                </div>
                                                                <div class="border rounded p-2 assign-courses-list" style="max-height: 200px; overflow-y: auto; background: #f8f9fa;">
                                                                    <?php foreach ($courses_for_assignment as $course): ?>
                                                                        <div class="form-check mb-1">
                                                                            <input class="form-check-input assign-course-checkbox" type="checkbox" name="assigned_courses[]" value="<?= $course['id'] ?>" id="edit_course_<?= $section['id'] ?>_<?= $course['id'] ?>" <?= in_array($course['id'], $assigned_course_ids) ? 'checked' : '' ?>>
                                                                            <label class="form-check-label" for="edit_course_<?= $section['id'] ?>_<?= $course['id'] ?>">
                                                                                <?= htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')') ?>
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                                <small class="text-muted">Check to assign courses to this section.</small>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="bi bi-x-circle me-2"></i>Cancel
                                                            </button>
                                                            <button type="submit" name="update_section" class="btn btn-primary">
                                                                <i class="bi bi-check-circle me-2"></i>Save Changes
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Assign Users Modal -->
                                        <div class="modal fade" id="assignUsersModal<?= $section['id'] ?>" tabindex="-1" aria-labelledby="assignUsersLabel<?= $section['id'] ?>">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <form method="post" action="sections.php">
                                                        <div class="modal-header bg-info text-white">
                                                            <h5 class="modal-title" id="assignUsersLabel<?= $section['id'] ?>">
                                                                <i class="bi bi-people me-2"></i>Assign Users to <?= htmlspecialchars($display_name) ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="assign_section_id" value="<?= $section['id'] ?>">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-funnel me-2"></i>Filter Students
                                                                        </label>
                                                                        <select class="form-select" id="studentStatusFilter<?= $section['id'] ?>" onchange="filterStudents<?= $section['id'] ?>()">
                                                                            <option value="all">All Students</option>
                                                                            <option value="regular">Regular Students</option>
                                                                            <option value="irregular">Irregular Students</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-search me-2"></i>Search Students
                                                                        </label>
                                                                        <input type="text" class="form-control mb-2" id="studentSearch<?= $section['id'] ?>" placeholder="Type to search..." onkeyup="searchStudents<?= $section['id'] ?>()">
                                                                        <label class="form-label fw-semibold d-flex align-items-center">
    <i class="bi bi-people me-2"></i>Assign Students
    <span class="badge bg-primary ms-2 selectedStudentsCountBadge">0</span>
</label>
                                                                        <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto; background: #f8f9fa;">
                                                                    <?php
                                                                    $students = [];
                                                                    $stu_sql = "SELECT id, CONCAT(last_name, ', ', first_name) AS name, is_irregular FROM users WHERE role='student' ORDER BY last_name, first_name";
                                                                    $stu_res = $db->query($stu_sql);
                                                                    if ($stu_res && $stu_res->rowCount() > 0) {
                                                                        $students = $stu_res->fetchAll();
                                                                    }
                                                                    $assigned_students = [];
                                                                    $as_sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(students, '$[*]')) as student_ids FROM sections WHERE id = ?";
                                                                    $as_stmt = $db->prepare($as_sql);
                                                                    $as_stmt->execute([$section['id']]);
                                                                    $assigned_students_json = $as_stmt->fetchColumn();
                                                                    $assigned_students = json_decode($assigned_students_json, true) ?? [];
                                                                    foreach ($students as $stu) {
                                                                                $checked = in_array($stu['id'], $assigned_students) ? 'checked' : '';
                                                                        $status = ($stu['is_irregular'] ? 'irregular' : 'regular');
                                                                        $badge = $stu['is_irregular'] ? '<span class=\'badge bg-danger ms-2\'>Irregular</span>' : '<span class=\'badge bg-success ms-2\'>Regular</span>';
                                                                                echo "<div class='form-check student-option-{$status}' data-status='{$status}' style='margin-bottom: 4px;'>";
                                                                                echo "<input class='form-check-input' type='checkbox' name='students[]' value='{$stu['id']}' id='stu{$section['id']}_{$stu['id']}' $checked onchange='updateSelectedStudentsCount()'>";
                                                                                echo "<label class='form-check-label' for='stu{$section['id']}_{$stu['id']}'>" . htmlspecialchars($stu['name']) . " $badge</label>";
                                                                                echo "</div>";
                                                                    }
                                                                    ?>
                                                                        </div>
                                                                        <small class="text-muted">Check to assign students</small>
                                                                    </div>
                                                            </div>
                                                                <div class="col-md-6">
                                                            <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-search me-2"></i>Search Teachers
                                                                        </label>
                                                                        <input type="text" class="form-control mb-2" id="teacherSearch<?= $section['id'] ?>" placeholder="Type to search..." onkeyup="searchTeachers<?= $section['id'] ?>()">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-person-workspace me-2"></i>Assign Teachers
                                                                        </label>
                                                                        <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto; background: #f8f9fa;">
                                                                    <?php
                                                                    $teachers = [];
                                                                            $teach_sql = "SELECT id, CONCAT(last_name, ', ', first_name) AS name FROM users WHERE role='teacher' AND status='active' ORDER BY last_name, first_name";
                                                                    $teach_res = $db->query($teach_sql);
                                                                    if ($teach_res && $teach_res->rowCount() > 0) {
                                                                        $teachers = $teach_res->fetchAll();
                                                                    }
                                                                    $assigned_teachers = [];
                                                                    $at_sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(teachers, '$[*]')) as teacher_ids FROM sections WHERE id = ?";
                                                                    $at_stmt = $db->prepare($at_sql);
                                                                    $at_stmt->execute([$section['id']]);
                                                                    $assigned_teachers_json = $at_stmt->fetchColumn();
                                                                    $assigned_teachers = json_decode($assigned_teachers_json, true) ?? [];
                                                                    foreach ($teachers as $teach) {
                                                                                $checked = in_array($teach['id'], $assigned_teachers) ? 'checked' : '';
                                                                                echo "<div class='form-check' style='margin-bottom: 4px;'>";
                                                                                echo "<input class='form-check-input' type='checkbox' name='teachers[]' value='{$teach['id']}' id='teach{$section['id']}_{$teach['id']}' $checked>";
                                                                                echo "<label class='form-check-label' for='teach{$section['id']}_{$teach['id']}'>" . htmlspecialchars($teach['name']) . "</label>";
                                                                                echo "</div>";
                                                                            }
                                                                            ?>
                                                                        </div>
                                                                        <small class="text-muted">Check to assign teachers</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="bi bi-x-circle me-2"></i>Cancel
                                                            </button>
                                                            <button type="submit" name="assign_users" class="btn btn-info">
                                                                <i class="bi bi-check-circle me-2"></i>Save Assignments
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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

    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
            <form method="post" action="sections.php">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addSectionLabel">
                        <i class="bi bi-plus-circle me-2"></i>Add New Section
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                        <div class="mb-3">
                                <label for="section_year_add" class="form-label fw-semibold">
                                    <i class="bi bi-calendar me-2"></i>Year Level
                                </label>
                                <select class="form-select" id="section_year_add" name="section_year" required onchange="updateSectionCodeDropdown()">
                                    <option value="">Select Year</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                        <div class="mb-3">
                                <label for="section_code_add" class="form-label fw-semibold">
                                    <i class="bi bi-collection me-2"></i>Section Code
                                </label>
                                <select class="form-select" id="section_code_add" name="section_code" required>
                                    <option value="">Select Section Code</option>
                                    <?php
                                    // Default: show all enabled, JS will update on year change
                                    foreach (range('A', 'Z') as $letter) {
                                        echo "<option value=\"$letter\">$letter</option>";
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Choose a unique section code (A-Z) for the selected year</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="academic_period_add" class="form-label fw-semibold">
                                    <i class="bi bi-calendar-week me-2"></i>Academic Period
                                </label>
                                <select class="form-select" id="academic_period_add" name="academic_period_id" required>
                                    <option value="">Select Academic Period</option>
                                    <?php foreach ($academic_periods as $period): ?>
                                        <option value="<?= $period['id'] ?>">
                                            <?= htmlspecialchars($period['period_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="section_description_add" class="form-label fw-semibold">
                            <i class="bi bi-text-paragraph me-2"></i>Description
                        </label>
                            <textarea class="form-control" id="section_description_add" name="section_description" rows="2"></textarea>
                        </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active_add" name="is_active" checked>
                            <label class="form-check-label fw-semibold" for="is_active_add">
                                <i class="bi bi-toggle-on me-2"></i>Active Section
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-book me-2"></i>Assign Courses
                        </label>
                        <select class="form-select" name="assigned_courses[]" multiple>
                            <?php foreach ($courses_for_assignment as $course): ?>
                                <option value="<?= $course['id'] ?>">
                                    <?= htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple courses.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" name="add_section" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Add Section
                    </button>
                    </div>
                </form>
        </div>
    </div>
</div>

<!-- Add Students Modal Template -->
<div class="modal fade" id="addStudentsModalTemplate" tabindex="-1" aria-labelledby="addStudentsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="sections.php">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addStudentsLabel">
                        <i class="bi bi-person-plus me-2"></i>Add Students to Section
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_students_section_id" id="addStudentsSectionId">
                    
                    <!-- Student Search and Filter -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="studentSearchAdd" class="form-label fw-semibold">
                                <i class="bi bi-search me-2"></i>Search Students
                            </label>
                            <input type="text" class="form-control" id="studentSearchAdd" placeholder="Type student name..." onkeyup="searchStudentsAdd()">
                        </div>
                        <div class="col-md-6">
                            <label for="studentStatusFilterAdd" class="form-label fw-semibold">
                                <i class="bi bi-funnel me-2"></i>Filter by Status
                            </label>
                            <select class="form-select" id="studentStatusFilterAdd" onchange="filterStudentsAdd()">
                                <option value="all">All Students</option>
                                <option value="regular">Regular Students</option>
                                <option value="irregular">Irregular Students</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Student Selection -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-flex align-items-center">
                            <i class="bi bi-people me-2"></i>Select Students to Add
                            <span class="badge bg-primary ms-2" id="selectedStudentsCountAdd">0</span>
                        </label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto; background: #f8f9fa;">
                            <div id="studentsListAdd">
                                <!-- Students will be loaded here via AJAX -->
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-people fs-1"></i>
                                    <p>Loading students...</p>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">Check the students you want to add to this section.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" name="add_students" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Add Selected Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Used codes per year from PHP
const usedCodesPerYear = <?php echo json_encode($used_codes_per_year); ?>;
function updateSectionCodeDropdown() {
    const year = document.getElementById('section_year_add').value;
    const codeSelect = document.getElementById('section_code_add');
    // Enable all first
    for (let opt of codeSelect.options) {
        opt.disabled = false;
    }
    if (year && usedCodesPerYear[year]) {
        const used = usedCodesPerYear[year];
        for (let opt of codeSelect.options) {
            if (used.includes(opt.value)) {
                opt.disabled = true;
            }
        }
    }
    // Reset selection if current is disabled
    if (codeSelect.selectedOptions.length && codeSelect.selectedOptions[0].disabled) {
        codeSelect.value = '';
    }
}
function filterStudents<?= $section['id'] ?>() {
    var filter = document.getElementById('studentStatusFilter<?= $section['id'] ?>').value;
    var options = document.querySelectorAll('#assignUsersModal<?= $section['id'] ?> .form-check.student-option-regular, #assignUsersModal<?= $section['id'] ?> .form-check.student-option-irregular');
    options.forEach(function(opt) {
        if (filter === 'all' || opt.getAttribute('data-status') === filter) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    updateSelectedStudentsCount();
}
function searchStudents<?= $section['id'] ?>() {
    var input = document.getElementById('studentSearch<?= $section['id'] ?>').value.toLowerCase();
    var options = document.querySelectorAll('#assignUsersModal<?= $section['id'] ?> .form-check.student-option-regular, #assignUsersModal<?= $section['id'] ?> .form-check.student-option-irregular');
    options.forEach(function(opt) {
        var label = opt.querySelector('label');
        var text = label ? label.textContent.toLowerCase() : '';
        opt.style.display = text.includes(input) ? '' : 'none';
    });
    updateSelectedStudentsCount();
}
function updateSelectedStudentsCount(modal) {
    if (!modal) return;
    var count = modal.querySelectorAll('input[name="students[]"]:checked').length;
    var badge = modal.querySelector('.selectedStudentsCountBadge');
    if (badge) badge.textContent = count;
}
// On modal show, update the count for that modal
if (window.bootstrap && window.bootstrap.Modal) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function () {
            updateSelectedStudentsCount(modal);
        });
    });
} else {
    // Fallback for Bootstrap 4
    $(document).on('shown.bs.modal', '.modal', function() {
        updateSelectedStudentsCount(this);
    });
}
// On checkbox change, update the count for the parent modal only
document.addEventListener('change', function(e) {
    if (e.target.matches('input[name="students[]"]')) {
        var modal = e.target.closest('.modal');
        if (modal) updateSelectedStudentsCount(modal);
    }
});
function updateSelectedCoursesCount(modal) {
    if (!modal) return;
    var count = modal.querySelectorAll('input.assign-course-checkbox:checked').length;
    var badge = modal.querySelector('.selectedCoursesCountBadge');
    if (badge) badge.textContent = count;
}
// On modal show, update the count for that modal
if (window.bootstrap && window.bootstrap.Modal) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function () {
            updateSelectedCoursesCount(modal);
        });
    });
} else {
    // Fallback for Bootstrap 4
    $(document).on('shown.bs.modal', '.modal', function() {
        updateSelectedCoursesCount(this);
    });
}
// On checkbox change, update the count for the parent modal only
// (reuse if already present for students, otherwise add this)
document.addEventListener('change', function(e) {
    if (e.target.matches('input.assign-course-checkbox')) {
        var modal = e.target.closest('.modal');
        if (modal) updateSelectedCoursesCount(modal);
    }
});
function searchCoursesInModal(input) {
    var filter = input.value.toLowerCase();
    var list = input.closest('.mb-3').querySelector('.assign-courses-list');
    var options = list.querySelectorAll('.form-check');
    options.forEach(function(opt) {
        var label = opt.querySelector('label');
        var text = label ? label.textContent.toLowerCase() : '';
        opt.style.display = text.includes(filter) ? '' : 'none';
    });
}
function toggleClearBtn(input) {
    var clearBtn = input.parentElement.querySelector('span');
    clearBtn.style.display = input.value ? '' : 'none';
}
function clearCourseSearch(span) {
    var input = span.parentElement.querySelector('input');
    input.value = '';
    span.style.display = 'none';
    searchCoursesInModal(input);
    input.focus();
}

// Add Students Modal Functions
function openAddStudentsModal(sectionId) {
    // Create a unique modal for this section
    const modalId = 'addStudentsModal' + sectionId;
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        // Clone the template modal
        const template = document.getElementById('addStudentsModalTemplate');
        modal = template.cloneNode(true);
        modal.id = modalId;
        modal.setAttribute('aria-labelledby', 'addStudentsLabel' + sectionId);
        
        // Update the title
        const title = modal.querySelector('.modal-title');
        title.id = 'addStudentsLabel' + sectionId;
        
        // Update the form action and hidden input
        const form = modal.querySelector('form');
        const hiddenInput = modal.querySelector('#addStudentsSectionId');
        hiddenInput.value = sectionId;
        
        // Add to the document
        document.body.appendChild(modal);
        
        // Load students for this section
        loadStudentsForSection(sectionId, modal);
    }
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function loadStudentsForSection(sectionId, modal) {
    const studentsList = modal.querySelector('#studentsListAdd');
    studentsList.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-arrow-clockwise fs-1"></i><p>Loading students...</p></div>';
    
    // Fetch students via AJAX
    fetch('../ajax_get_available_students.php?section_id=' + sectionId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayStudentsList(data.students, studentsList, data.invalid_students, data.target_section);
            } else {
                studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error: ' + (data.message || 'Unknown error') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error fetching students:', error);
            studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error loading students: ' + error.message + '</p></div>';
        });
}

function displayStudentsList(students, container, invalidStudents = [], targetSection = null) {
    let html = '';
    
    // Show target section info
    if (targetSection) {
        html += `
            <div class="alert alert-info mb-3">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Target Section:</strong> ${targetSection.section_name} (Year ${targetSection.year_level})
            </div>
        `;
    }
    
    // Show available students
    if (students.length === 0 && invalidStudents.length === 0) {
        html += '<div class="text-center text-muted py-4"><i class="bi bi-people fs-1"></i><p>No students available to add</p></div>';
    } else {
        // Available students section
        if (students.length > 0) {
            html += `
                <div class="mb-3">
                    <h6 class="text-success">
                        <i class="bi bi-check-circle me-2"></i>Available Students (${students.length})
                    </h6>
                    <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
            `;
            
            students.forEach(student => {
                const status = student.is_irregular ? 'irregular' : 'regular';
                const badge = student.is_irregular ? 
                    '<span class="badge bg-danger ms-2">Irregular</span>' : 
                    '<span class="badge bg-success ms-2">Regular</span>';
                const yearBadge = `<span class="badge bg-primary ms-1">${student.year_level_text}</span>`;
                
                html += `
                    <div class="form-check student-option-${status} mb-2" data-status="${status}">
                        <input class="form-check-input" type="checkbox" name="students_to_add[]" value="${student.id}" id="add_stu_${student.id}" onchange="updateSelectedStudentsCountAdd()">
                        <label class="form-check-label" for="add_stu_${student.id}">
                            ${student.name} ${badge} ${yearBadge}
                        </label>
                    </div>
                `;
            });
            
            html += '</div></div>';
        }
        
        // Invalid students section
        if (invalidStudents.length > 0) {
            html += `
                <div class="mb-3">
                    <h6 class="text-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>Students Not Eligible (${invalidStudents.length})
                    </h6>
                    <div class="alert alert-warning mb-0">
                        <small>These students cannot be assigned to this section due to year level restrictions:</small>
                        <div class="mt-2" style="max-height: 200px; overflow-y: auto;">
            `;
            
            invalidStudents.forEach(student => {
                const yearBadge = `<span class="badge bg-secondary ms-1">${student.year_level_text}</span>`;
                html += `
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span>${student.name} ${yearBadge}</span>
                        <small class="text-muted">${student.validation_error}</small>
                    </div>
                `;
            });
            
            html += '</div></div></div>';
        }
    }
    
    container.innerHTML = html;
    updateSelectedStudentsCountAdd();
}

function searchStudentsAdd() {
    const input = document.getElementById('studentSearchAdd');
    const filter = input.value.toLowerCase();
    const modal = input.closest('.modal');
    const options = modal.querySelectorAll('.student-option-regular, .student-option-irregular');
    
    options.forEach(opt => {
        const label = opt.querySelector('label');
        const text = label ? label.textContent.toLowerCase() : '';
        opt.style.display = text.includes(filter) ? '' : 'none';
    });
    
    updateSelectedStudentsCountAdd();
}

function filterStudentsAdd() {
    const filter = document.getElementById('studentStatusFilterAdd').value;
    const modal = document.getElementById('studentStatusFilterAdd').closest('.modal');
    const options = modal.querySelectorAll('.student-option-regular, .student-option-irregular');
    
    options.forEach(opt => {
        if (filter === 'all' || opt.getAttribute('data-status') === filter) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    
    updateSelectedStudentsCountAdd();
}

function updateSelectedStudentsCountAdd() {
    const modal = document.querySelector('.modal.show');
    if (!modal) return;
    
    const count = modal.querySelectorAll('input[name="students_to_add[]"]:checked').length;
    const badge = modal.querySelector('#selectedStudentsCountAdd');
    if (badge) badge.textContent = count;
}

// View Section Students Function
function viewSectionStudents(sectionId, sectionName) {
    // Create a modal to display students
    const modalId = 'viewStudentsModal' + sectionId;
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        // Create modal HTML
        modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = modalId;
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-people me-2"></i>Students in ${sectionName}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="studentsList${sectionId}">
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-arrow-clockwise fs-1"></i>
                                <p>Loading students...</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Load students for this section
        loadSectionStudents(sectionId, modal);
    }
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function loadSectionStudents(sectionId, modal) {
    const studentsList = modal.querySelector(`#studentsList${sectionId}`);
    
    // Fetch students via AJAX
    fetch('../ajax_get_section_students.php?section_id=' + sectionId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displaySectionStudentsList(data.students, studentsList, data.debug);
            } else {
                studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error: ' + (data.message || 'Unknown error') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error fetching section students:', error);
            studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error loading students: ' + error.message + '</p></div>';
        });
}

function displaySectionStudentsList(students, container, debugInfo = null) {
    if (students.length === 0) {
        let debugHtml = '';
        if (debugInfo && debugInfo.total_in_json > 0) {
            debugHtml = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Data Mismatch Detected!</strong><br>
                    JSON shows ${debugInfo.total_in_json} students, but only ${debugInfo.found_students} were found.<br>
                    Missing Student IDs: ${debugInfo.missing_student_ids.join(', ')}
                </div>`;
        }
        
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-people fs-1"></i>
                <p class="mt-2">No students assigned to this section</p>
                <small class="text-muted">Use the "Add Students" button to assign students to this section</small>
            </div>
            ${debugHtml}`;
        return;
    }
    
    let debugHtml = '';
    if (debugInfo && debugInfo.total_in_json !== debugInfo.found_students) {
        debugHtml = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Data Mismatch Detected!</strong><br>
                JSON shows ${debugInfo.total_in_json} students, but only ${debugInfo.found_students} were found.<br>
                Missing Student IDs: ${debugInfo.missing_student_ids.join(', ')}
            </div>`;
    }
    
    let html = `
        ${debugHtml}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
                <i class="bi bi-people-fill text-info me-2"></i>
                Total Students: <span class="badge bg-info">${students.length}</span>
                ${debugInfo ? `<small class="text-muted ms-2">(JSON: ${debugInfo.total_in_json})</small>` : ''}
            </h6>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="exportSectionStudents(${students[0].section_id})" title="Export to CSV">
                    <i class="bi bi-download me-1"></i>Export
                </button>
                <button class="btn btn-sm btn-outline-success" onclick="openAddStudentsModal(${students[0].section_id})" title="Add More Students">
                    <i class="bi bi-person-plus me-1"></i>Add More
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr>
                        <th><i class="bi bi-hash me-1"></i>#</th>
                        <th><i class="bi bi-person me-1"></i>Name</th>
                        <th><i class="bi bi-card-text me-1"></i>Student ID</th>
                        <th><i class="bi bi-tag me-1"></i>Status</th>
                        <th class="text-center"><i class="bi bi-gear me-1"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>`;
    
    students.forEach((student, index) => {
        const status = student.is_irregular ? 'irregular' : 'regular';
        const badge = student.is_irregular ? 
            '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Irregular</span>' : 
            '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Regular</span>';
        
        html += `
            <tr>
                <td class="text-muted">${index + 1}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                            <i class="bi bi-person text-white"></i>
                        </div>
                        <strong>${student.name}</strong>
                    </div>
                </td>
                <td><code class="bg-light px-2 py-1 rounded">${student.identifier || 'No ID'}</code></td>
                <td>${badge}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-info" onclick="viewStudentProfile(${student.id})" title="View Profile">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="editStudentSection(${student.id}, ${student.section_id})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="removeStudentFromSection(${student.id}, ${student.section_id})" title="Remove from Section">
                            <i class="bi bi-person-x"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Function to export section students to CSV
function exportSectionStudents(sectionId) {
    // Get section name first
    const sectionRow = document.querySelector(`tr[data-section-id="${sectionId}"]`);
    const sectionName = sectionRow ? sectionRow.querySelector('h6')?.textContent || 'Section' : 'Section';
    
    // Fetch students data
    fetch(`../ajax_get_section_students.php?section_id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.students.length > 0) {
                // Create CSV content
                let csvContent = 'data:text/csv;charset=utf-8,';
                
                // Add header
                csvContent += 'No.,Name,Student ID,Status,Section\n';
                
                // Add data rows
                data.students.forEach((student, index) => {
                    const status = student.is_irregular ? 'Irregular' : 'Regular';
                    const row = `${index + 1},"${student.name}","${student.identifier || 'No ID'}","${status}","${sectionName}"\n`;
                    csvContent += row;
                });
                
                // Create download link
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', `${sectionName}_Students_${new Date().toISOString().split('T')[0]}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Show success message
                showAlert('success', `CSV exported successfully! ${data.students.length} students exported.`);
            } else {
                showAlert('warning', 'No students to export.');
            }
        })
        .catch(error => {
            console.error('Export error:', error);
            showAlert('danger', 'Error exporting CSV. Please try again.');
        });
}

// Function to view student profile
function viewStudentProfile(studentId) {
    // Fetch student data
    fetch(`../ajax_get_student_profile.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStudentProfileModal(data.student);
            } else {
                showAlert('danger', data.message || 'Error loading student profile.');
            }
        })
        .catch(error => {
            console.error('Profile error:', error);
            showAlert('danger', 'Error loading student profile. Please try again.');
        });
}

// Function to show student profile modal
function showStudentProfileModal(student) {
    // Create modal HTML
    const modalHtml = `
        <div class="modal fade" id="studentProfileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-person-circle me-2"></i>Student Profile
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="mb-3">
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                        <i class="bi bi-person text-white" style="font-size: 3rem;"></i>
                                    </div>
                                </div>
                                <h5 class="mb-1">${student.first_name} ${student.last_name}</h5>
                                <span class="badge ${student.is_irregular ? 'bg-danger' : 'bg-success'} mb-2">
                                    ${student.is_irregular ? 'Irregular' : 'Regular'}
                                </span>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-muted">Student ID</label>
                                            <p class="mb-0">${student.identifier || 'No ID'}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-muted">Email</label>
                                            <p class="mb-0">${student.email}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-muted">Username</label>
                                            <p class="mb-0">${student.username}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-muted">Status</label>
                                            <p class="mb-0">
                                                <span class="badge ${student.status === 'active' ? 'bg-success' : 'bg-danger'}">
                                                    ${student.status}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-muted">Enrolled Courses</label>
                                    <div class="list-group list-group-flush">
                                        ${student.enrolled_courses && student.enrolled_courses.length > 0 ? 
                                            student.enrolled_courses.map(course => 
                                                `<div class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span>${course.course_name}</span>
                                                    <span class="badge bg-info">${course.progress || 0}%</span>
                                                </div>`
                                            ).join('') : 
                                            '<p class="text-muted mb-0">No courses enrolled</p>'
                                        }
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Close
                        </button>
                        <button type="button" class="btn btn-primary" onclick="editStudentSection(${student.id}, ${student.section_id || 0})">
                            <i class="bi bi-pencil me-2"></i>Edit Section
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('studentProfileModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('studentProfileModal'));
    modal.show();
}

// Function to edit student section assignment
function editStudentSection(studentId, sectionId) {
    // Fetch current sections and student info
    Promise.all([
        fetch('../ajax_get_all_sections.php'),
        fetch(`../ajax_get_student_profile.php?student_id=${studentId}`)
    ])
    .then(responses => Promise.all(responses.map(r => r.json())))
    .then(([sectionsData, studentData]) => {
        if (sectionsData.success && studentData.success) {
            showEditStudentSectionModal(studentData.student, sectionsData.sections, sectionId);
        } else {
            showAlert('danger', 'Error loading data. Please try again.');
        }
    })
    .catch(error => {
        console.error('Edit error:', error);
        showAlert('danger', 'Error loading data. Please try again.');
    });
}

// Function to show edit student section modal
function showEditStudentSectionModal(student, sections, currentSectionId) {
    const modalHtml = `
        <div class="modal fade" id="editStudentSectionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil-square me-2"></i>Edit Student Section
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 60px; height: 60px;">
                                <i class="bi bi-person text-white" style="font-size: 1.5rem;"></i>
                            </div>
                            <h6 class="mb-1">${student.first_name} ${student.last_name}</h6>
                            <small class="text-muted">${student.identifier || 'No ID'}</small>
                        </div>
                        
                        <form id="editStudentSectionForm">
                            <input type="hidden" name="student_id" value="${student.id}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-collection me-2"></i>Current Section
                                </label>
                                <p class="text-muted mb-2">
                                    ${currentSectionId > 0 ? 
                                        sections.find(s => s.id == currentSectionId)?.section_name || 'Unknown' : 
                                        'Not assigned to any section'
                                    }
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <label for="newSectionId" class="form-label fw-semibold">
                                    <i class="bi bi-arrow-right me-2"></i>New Section
                                </label>
                                <select class="form-select" id="newSectionId" name="new_section_id" required>
                                    <option value="">Select a section...</option>
                                    <option value="0">Remove from all sections</option>
                                    ${sections.map(section => 
                                        `<option value="${section.id}" ${section.id == currentSectionId ? 'selected' : ''}>
                                            ${section.section_name} (Year ${section.year_level})
                                        </option>`
                                    ).join('')}
                                </select>
                                <small class="text-muted">Choose a section or select "Remove from all sections" to unassign</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-info-circle me-2"></i>Student Status
                                </label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isIrregular" name="is_irregular" ${student.is_irregular ? 'checked' : ''}>
                                    <label class="form-check-label" for="isIrregular">
                                        Mark as Irregular Student
                                    </label>
                                </div>
                                <small class="text-muted">Irregular students may have different requirements or schedules</small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-warning" onclick="saveStudentSectionChanges()">
                            <i class="bi bi-check-circle me-2"></i>Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('editStudentSectionModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editStudentSectionModal'));
    modal.show();
}

// Function to save student section changes
function saveStudentSectionChanges() {
    const form = document.getElementById('editStudentSectionForm');
    const formData = new FormData(form);
    
    // Get additional form data
    const newSectionId = document.getElementById('newSectionId').value;
    const isIrregular = document.getElementById('isIrregular').checked;
    
    const data = {
        student_id: formData.get('student_id'),
        new_section_id: newSectionId,
        is_irregular: isIrregular ? 1 : 0
    };
    
    // Send update request
    fetch('../ajax_update_student_section.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('success', 'Student section updated successfully!');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editStudentSectionModal'));
            modal.hide();
            
            // Refresh the page to show updated data
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('danger', result.message || 'Error updating student section.');
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        showAlert('danger', 'Error saving changes. Please try again.');
    });
}

// Function to show alerts
function showAlert(type, message) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create alert HTML
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Add alert to body
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Function to remove student from section
function removeStudentFromSection(studentId, sectionId) {
    if (!confirm('Are you sure you want to remove this student from the section?')) {
        return;
    }
    
    const data = {
        student_id: studentId,
        new_section_id: 0, // 0 means remove from all sections
        is_irregular: 0 // Keep current irregular status
    };
    
    fetch('../ajax_update_student_section.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('success', 'Student removed from section successfully!');
            // Refresh the page to show updated data
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('danger', result.message || 'Error removing student from section.');
        }
    })
    .catch(error => {
        console.error('Remove error:', error);
        showAlert('danger', 'Error removing student. Please try again.');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?> 