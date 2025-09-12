<?php
$page_title = 'Assessments';
require_once '../config/config.php';
requireRole('teacher');

$message = '';
$message_type = '';

// 1. Fetch all academic periods for the dropdown
$ay_stmt = $db->prepare('SELECT id, academic_year, semester_name, is_active FROM academic_periods ORDER BY academic_year DESC, semester_name');
$ay_stmt->execute();
$all_years = $ay_stmt->fetchAll();

// 2. Handle academic period selection (GET or SESSION)
if (isset($_GET['academic_period_id'])) {
    $_SESSION['teacher_assessments_academic_period_id'] = (int)$_GET['academic_period_id'];
}
// Find the first active academic year
$active_year = null;
foreach ($all_years as $year) {
    if ($year['is_active']) {
        $active_year = $year['id'];
        break;
    }
}
$selected_year_id = $_SESSION['teacher_assessments_academic_period_id'] ?? $active_year ?? ($all_years[0]['id'] ?? null);

// Check if selected academic period is active
$year_stmt = $db->prepare('SELECT is_active FROM academic_periods WHERE id = ?');
$year_stmt->execute([$selected_year_id]);
$year_row = $year_stmt->fetch();
$is_acad_year_active = $year_row ? (bool)$year_row['is_active'] : true;

// Handle assessment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'toggle_status':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                $is_active = (int)($_POST['is_active'] ?? 0);
                
                // Update status field (new database structure uses status enum)
                $status = $is_active ? 'active' : 'inactive';
                $stmt = $db->prepare('UPDATE assessments SET status = ? WHERE id = ? AND course_id IN (SELECT id FROM courses WHERE teacher_id = ? AND academic_period_id = ?)');
                $stmt->execute([$status, $assessment_id, $_SESSION['user_id'], $selected_year_id]);
                
                $message = 'Assessment status updated successfully.';
                $message_type = 'success';
                break;
                
            case 'toggle_lock':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                $is_locked = (int)($_POST['is_locked'] ?? 0);
                
                $stmt = $db->prepare('UPDATE assessments SET is_locked = ? WHERE id = ? AND course_id IN (SELECT id FROM courses WHERE teacher_id = ? AND academic_period_id = ?)');
                $stmt->execute([$is_locked, $assessment_id, $_SESSION['user_id'], $selected_year_id]);
                $message = 'Assessment lock status updated successfully.';
                $message_type = 'success';
                break;
                
            case 'configure_lock':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                $lock_type = sanitizeInput($_POST['lock_type'] ?? 'manual');
                $is_locked = (int)($_POST['is_locked'] ?? 0);
                $prerequisite_assessment_id = !empty($_POST['prerequisite_assessment_id']) ? sanitizeInput($_POST['prerequisite_assessment_id']) : null;
                $prerequisite_score = !empty($_POST['prerequisite_score']) ? (float)$_POST['prerequisite_score'] : null;
                $prerequisite_video_count = !empty($_POST['prerequisite_video_count']) ? (int)$_POST['prerequisite_video_count'] : null;
                $unlock_date = !empty($_POST['unlock_date']) ? $_POST['unlock_date'] : null;
                $lock_message = sanitizeInput($_POST['lock_message'] ?? '');
                
                // Validate lock type specific requirements
                $valid = true;
                if ($lock_type === 'prerequisite_score' && empty($prerequisite_assessment_id)) {
                    $message = 'Prerequisite assessment is required for score-based locking.';
                    $valid = false;
                } elseif ($lock_type === 'prerequisite_videos' && empty($prerequisite_video_count)) {
                    $message = 'Video count is required for video-based locking.';
                    $valid = false;
                } elseif ($lock_type === 'date_based' && empty($unlock_date)) {
                    $message = 'Unlock date is required for date-based locking.';
                    $valid = false;
                }
                
                if ($valid) {
                    $stmt = $db->prepare('UPDATE assessments SET is_locked = ?, lock_type = ?, prerequisite_assessment_id = ?, prerequisite_score = ?, prerequisite_video_count = ?, unlock_date = ?, lock_message = ?, lock_updated_at = CURRENT_TIMESTAMP WHERE id = ? AND course_id IN (SELECT id FROM courses WHERE teacher_id = ? AND academic_period_id = ?)');
                    $stmt->execute([$is_locked, $lock_type, $prerequisite_assessment_id, $prerequisite_score, $prerequisite_video_count, $unlock_date, $lock_message, $assessment_id, $_SESSION['user_id'], $selected_year_id]);
                    $message = 'Assessment lock settings updated successfully.';
                    $message_type = 'success';
                } else {
                    $message_type = 'danger';
                }
                break;
                
            case 'delete':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                
                // Check if assessment has attempts
                $stmt = $db->prepare('SELECT COUNT(*) FROM assessment_attempts WHERE assessment_id = ?');
                $stmt->execute([$assessment_id]);
                $attempt_count = $stmt->fetchColumn();
                
                if ($attempt_count > 0) {
                    $message = 'Cannot delete assessment with existing attempts.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('DELETE FROM assessments WHERE id = ? AND course_id IN (SELECT id FROM courses WHERE teacher_id = ? AND academic_period_id = ?)');
                    $stmt->execute([$assessment_id, $_SESSION['user_id'], $selected_year_id]);
                    $message = 'Assessment deleted successfully.';
                    $message_type = 'success';
                }
                break;
        }
    }
}

// Get teacher's assessments with filters
$course_filter = (int)($_GET['course'] ?? $_GET['course_id'] ?? 0);
$module_filter = (int)($_GET['module'] ?? $_GET['module_id'] ?? 0);
$difficulty_filter = sanitizeInput($_GET['difficulty'] ?? '');
$section_filter = (int)($_GET['section'] ?? 0);

$where_conditions = ["c.teacher_id = ?", "c.academic_period_id = ?"];
$params = [$_SESSION['user_id'], $selected_year_id];

if ($course_filter > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

// Module filtering - only apply if module_filter is greater than 0
if ($module_filter > 0) {
    // Since modules are stored as JSON in courses table, we need to check if the assessment
    // belongs to a course that has this module
    $where_conditions[] = "JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL";
    $params[] = (string)$module_filter;
}

if (!empty($difficulty_filter)) {
    $where_conditions[] = "a.difficulty = ?";
    $params[] = $difficulty_filter;
}

// Section filtering - only apply if section_filter is greater than 0
if ($section_filter > 0) {
    // Since sections are stored as JSON in courses table, we need to check if the assessment
    // belongs to a course that has this section
    $where_conditions[] = "JSON_SEARCH(c.sections, 'one', ?) IS NOT NULL";
    $params[] = (string)$section_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$stmt = $db->prepare("
    SELECT a.*, c.teacher_id, c.course_name, c.course_code,
           COUNT(aa.id) as attempt_count,
           AVG(aa.score) as average_score,
           a.is_locked, a.lock_type, a.prerequisite_assessment_id, 
           a.prerequisite_score, a.prerequisite_video_count, a.unlock_date, a.lock_message,
           (a.status = 'active') as is_active,
           'Module Assessment' as module_title
    FROM assessments a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.status = 'completed'
    $where_clause
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$assessments = $stmt->fetchAll();

// After fetching assessments, get teacher info for each assessment
foreach ($assessments as &$assessment) {
    $stmt = $db->prepare('SELECT first_name, last_name, username FROM users WHERE id = ?');
    $stmt->execute([$assessment['teacher_id']]);
    $creator = $stmt->fetch();
    $assessment['creator_name'] = $creator ? $creator['first_name'] . ' ' . $creator['last_name'] : '';
    $assessment['creator_username'] = $creator['username'] ?? '';
}
unset($assessment);

// Get teacher's courses for filter (for selected academic period)
$stmt = $db->prepare('SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? AND academic_period_id = ? ORDER BY course_name');
$stmt->execute([$_SESSION['user_id'], $selected_year_id]);
$courses = $stmt->fetchAll();

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year_level']}{$section['section_name']}";
}

// Fetch sections for the selected course (for section filter)
$sections_for_filter = [];
if ($course_filter > 0) {
    // Get sections from course JSON
    $stmt = $db->prepare("SELECT sections FROM courses WHERE id = ?");
    $stmt->execute([$course_filter]);
    $course_sections = $stmt->fetchColumn();
    
    if ($course_sections) {
        $section_sql = "SELECT s.id, s.section_name, s.year_level FROM sections s WHERE JSON_SEARCH(?, 'one', s.id) IS NOT NULL ORDER BY s.year_level, s.section_name";
        $section_stmt = $db->prepare($section_sql);
        $section_stmt->execute([$course_sections]);
        $sections_for_filter = $section_stmt->fetchAll();
    }
} else {
    // If no course is selected, show all sections for the academic period
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.section_name, s.year_level 
        FROM sections s 
        JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL 
        WHERE c.teacher_id = ? AND c.academic_period_id = ? 
        ORDER BY s.year_level, s.section_name
    ");
    $stmt->execute([$_SESSION['user_id'], $selected_year_id]);
    $sections_for_filter = $stmt->fetchAll();
}

// Get modules for selected course (from JSON)
$modules = [];
if ($course_filter > 0) {
    $stmt = $db->prepare('SELECT modules FROM courses WHERE id = ?');
    $stmt->execute([$course_filter]);
    $course_modules = $stmt->fetchColumn();
    
    if ($course_modules) {
        $modules_data = json_decode($course_modules, true);
        if ($modules_data) {
            foreach ($modules_data as $module_id => $module_data) {
                $modules[] = [
                    'id' => $module_id,
                    'module_title' => $module_data['title'] ?? 'Unknown Module'
                ];
            }
        }
    }
} else {
    // If no course is selected, show all modules for the academic period
    $stmt = $db->prepare('
        SELECT DISTINCT modules FROM courses 
        WHERE teacher_id = ? AND academic_period_id = ? AND modules IS NOT NULL
    ');
    $stmt->execute([$_SESSION['user_id'], $selected_year_id]);
    $all_course_modules = $stmt->fetchAll();
    
    $all_modules = [];
    foreach ($all_course_modules as $course_module) {
        if ($course_module['modules']) {
            $modules_data = json_decode($course_module['modules'], true);
            if ($modules_data) {
                foreach ($modules_data as $module_id => $module_data) {
                    if (!isset($all_modules[$module_id])) {
                        $all_modules[$module_id] = [
                            'id' => $module_id,
                            'module_title' => $module_data['title'] ?? 'Unknown Module'
                        ];
                    }
                }
            }
        }
    }
    $modules = array_values($all_modules);
}

// Get available assessments for prerequisite selection (excluding current assessment)
$available_assessments = [];
if ($course_filter > 0) {
    $stmt = $db->prepare('
        SELECT a.id, a.assessment_title, a.course_id
        FROM assessments a 
        WHERE a.course_id = ? 
        ORDER BY a.created_at
    ');
    $stmt->execute([$course_filter]);
    $available_assessments = $stmt->fetchAll();
}

// Include header after all potential redirects
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-title-section">
                        <div class="d-flex align-items-center mb-2">
                            <div class="page-icon me-3">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <div>
                                <h1 class="page-title mb-0">Assessments</h1>
                                <p class="page-subtitle mb-0">Manage and monitor student assessment attempts</p>
                            </div>
                        </div>
                        <div class="page-stats">
                            <span class="stat-item">
                                <i class="bi bi-graph-up text-success me-1"></i>
                                <span class="fw-semibold"><?php echo count($assessments); ?></span> Total
                            </span>
                            <span class="stat-item">
                                <i class="bi bi-check-circle text-primary me-1"></i>
                                <span class="fw-semibold"><?php echo count(array_filter($assessments, function($a) { return $a['is_active']; })); ?></span> Active
                            </span>
                            <span class="stat-item">
                                <i class="bi bi-lock text-warning me-1"></i>
                                <span class="fw-semibold"><?php echo count(array_filter($assessments, function($a) { return $a['is_locked']; })); ?></span> Locked
                            </span>
                        </div>
                    </div>
                    <div class="page-actions">
                        <a href="attempt_history.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-history me-2"></i>Attempt History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Year Selection -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="academic-year-selector">
                <form method="get" class="d-flex align-items-center">
                    <div class="selector-label me-3">
                        <i class="bi bi-calendar3 me-2"></i>
                        <span class="fw-semibold">Academic Period:</span>
                    </div>
                    <div class="selector-dropdown">
                        <select name="academic_period_id" id="academic_period_id" class="form-select academic-period-select" onchange="this.form.submit()">
                            <?php foreach ($all_years as $year): ?>
                                <option value="<?= $year['id'] ?>" <?= $selected_year_id == $year['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year['academic_year'] . ' - ' . $year['semester_name']) ?><?= !$year['is_active'] ? ' (Inactive)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <noscript><button type="submit" class="btn btn-primary btn-sm ms-2">Go</button></noscript>
                </form>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$is_acad_year_active): ?>
        <div class="alert alert-warning mb-4">
            <strong>Inactive Academic Year:</strong> This academic year is inactive. You can only view and review content. All editing, adding, and uploading is disabled.
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="filters-card">
                <div class="filters-header">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-funnel me-2"></i>
                        <h6 class="mb-0 fw-semibold">Filter Assessments</h6>
                    </div>
                    <div class="filter-count">
                        <?php 
                        $active_filters = 0;
                        if ($course_filter > 0) $active_filters++;
                        if ($section_filter > 0) $active_filters++;
                        if ($module_filter > 0) $active_filters++;
                        if (!empty($difficulty_filter)) $active_filters++;
                        ?>
                        <span class="badge bg-primary"><?= $active_filters ?> Active</span>
                    </div>
                </div>
                <div class="filters-body">
                    <form method="get" class="row g-3">
                        <input type="hidden" name="academic_period_id" value="<?= $selected_year_id ?>">
                        <div class="col-lg-3 col-md-6">
                            <label for="course" class="form-label filter-label">
                                <i class="bi bi-book me-1"></i>Course
                            </label>
                            <select class="form-select filter-select" id="course" name="course" onchange="this.form.submit()">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="section" class="form-label filter-label">
                                <i class="bi bi-people me-1"></i>Section
                            </label>
                            <select class="form-select filter-select" id="section" name="section" onchange="this.form.submit()">
                                <option value="">All Sections</option>
                                <?php foreach ($sections_for_filter as $section): ?>
                                    <option value="<?php echo $section['id']; ?>" <?php echo $section_filter == $section['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(formatSectionName($section)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label for="module" class="form-label filter-label">
                                <i class="bi bi-collection me-1"></i>Module
                            </label>
                            <select class="form-select filter-select" id="module" name="module" onchange="this.form.submit()">
                                <option value="">All Modules</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module['id']; ?>" 
                                            <?php echo $module_filter == $module['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($module['module_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label for="difficulty" class="form-label filter-label">
                                <i class="bi bi-speedometer2 me-1"></i>Difficulty
                            </label>
                            <select class="form-select filter-select" id="difficulty" name="difficulty" onchange="this.form.submit()">
                                <option value="">All Levels</option>
                                <option value="easy" <?php echo $difficulty_filter === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo $difficulty_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo $difficulty_filter === 'hard' ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-12">
                            <label class="form-label filter-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="assessments.php?academic_period_id=<?= $selected_year_id ?>" class="btn btn-outline-secondary filter-clear-btn">
                                    <i class="bi bi-x-circle me-1"></i>Clear All
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Assessments List -->
    <div class="row">
        <div class="col-12">
            <div class="assessments-card">
                <div class="assessments-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="assessments-title">
                            <h5 class="mb-0">
                                <i class="bi bi-clipboard-check me-2"></i>
                                Assessments
                            </h5>
                            <small class="text-muted"><?php echo count($assessments); ?> total assessments</small>
                        </div>
                        <div class="assessments-actions">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshAssessments()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportAssessments()">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="assessments-body">
                    <?php if (empty($assessments)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <h6 class="empty-title">No Assessments Found</h6>
                            <p class="empty-description">Create your first assessment to start evaluating student progress.</p>
                            <a href="assessment_create.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Create Assessment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive assessments-table-container">
                            <table class="table assessments-table">
                                <thead>
                                    <tr>
                                        <th class="assessment-col">
                                            <i class="bi bi-clipboard-check me-1"></i>Assessment
                                        </th>
                                        <th class="course-col">
                                            <i class="bi bi-book me-1"></i>Course & Module
                                        </th>
                                        <th class="difficulty-col">
                                            <i class="bi bi-speedometer2 me-1"></i>Difficulty
                                        </th>
                                        <th class="time-col">
                                            <i class="bi bi-clock me-1"></i>Time Limit
                                        </th>
                                        <th class="attempts-col">
                                            <i class="bi bi-graph-up me-1"></i>Attempts
                                        </th>
                                        <th class="score-col">
                                            <i class="bi bi-trophy me-1"></i>Avg Score
                                        </th>
                                        <th class="status-col">
                                            <i class="bi bi-toggle-on me-1"></i>Status
                                        </th>
                                        <th class="actions-col">
                                            <i class="bi bi-gear me-1"></i>Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assessments as $index => $assessment): ?>
                                        <tr class="assessment-row" data-assessment-id="<?= $assessment['id'] ?>">
                                            <td class="assessment-cell">
                                                <div class="assessment-info">
                                                    <div class="assessment-title">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($assessment['assessment_title']); ?></h6>
                                                        <?php if ($assessment['description']): ?>
                                                            <p class="assessment-description"><?php echo htmlspecialchars(substr($assessment['description'], 0, 100)) . '...'; ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="assessment-meta">
                                                        <small class="text-muted">
                                                            <i class="bi bi-person me-1"></i>
                                                            Created by: <?php echo htmlspecialchars($assessment['creator_name'] . ' (' . $assessment['creator_username'] . ')'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="course-cell">
                                                <div class="course-info">
                                                    <div class="course-name">
                                                        <i class="bi bi-book me-1"></i>
                                                        <?php echo htmlspecialchars($assessment['course_name']); ?>
                                                    </div>
                                                    <div class="module-name">
                                                        <i class="bi bi-collection me-1"></i>
                                                        <?php echo htmlspecialchars($assessment['module_title'] ?? 'Module Assessment'); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="difficulty-cell">
                                                <span class="difficulty-badge difficulty-<?= $assessment['difficulty'] ?>">
                                                    <i class="bi bi-<?= $assessment['difficulty'] === 'easy' ? 'check-circle' : ($assessment['difficulty'] === 'medium' ? 'exclamation-triangle' : 'x-circle'); ?> me-1"></i>
                                                    <?php echo ucfirst($assessment['difficulty']); ?>
                                                </span>
                                            </td>
                                            <td class="time-cell">
                                                <?php if ($assessment['time_limit']): ?>
                                                    <span class="time-badge">
                                                        <i class="bi bi-clock me-1"></i>
                                                        <?php echo $assessment['time_limit']; ?> min
                                                    </span>
                                                <?php else: ?>
                                                    <span class="time-badge no-limit">
                                                        <i class="bi bi-infinity me-1"></i>
                                                        No limit
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="attempts-cell">
                                                <span class="attempts-badge">
                                                    <i class="bi bi-graph-up me-1"></i>
                                                    <?php echo $assessment['attempt_count']; ?> attempts
                                                </span>
                                            </td>
                                            <td class="score-cell">
                                                <?php if ($assessment['average_score']): ?>
                                                    <span class="score-badge score-<?= $assessment['average_score'] >= 90 ? 'excellent' : ($assessment['average_score'] >= 70 ? 'good' : 'poor'); ?>">
                                                        <i class="bi bi-trophy me-1"></i>
                                                        <?php echo number_format($assessment['average_score'], 1); ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="score-badge no-data">
                                                        <i class="bi bi-dash-circle me-1"></i>
                                                        No attempts
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="status-cell">
                                                <div class="status-group">
                                                    <?php if (($assessment['is_active'] ?? false)): ?>
                                                        <span class="status-badge active">
                                                            <i class="bi bi-check-circle me-1"></i>Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-badge inactive">
                                                            <i class="bi bi-pause-circle me-1"></i>Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($assessment['is_locked']): ?>
                                                        <span class="lock-badge locked">
                                                            <i class="bi bi-lock me-1"></i>Locked
                                                        </span>
                                                        <small class="lock-reason">
                                                            <?php 
                                                            switch($assessment['lock_type']) {
                                                                case 'prerequisite_score':
                                                                    echo 'Requires ' . $assessment['prerequisite_score'] . '% on previous';
                                                                    break;
                                                                case 'prerequisite_videos':
                                                                    echo 'Requires ' . $assessment['prerequisite_video_count'] . ' videos';
                                                                    break;
                                                                case 'date_based':
                                                                    echo 'Available ' . date('M j', strtotime($assessment['unlock_date']));
                                                                    break;
                                                                default:
                                                                    echo 'Manually locked';
                                                            }
                                                            ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="lock-badge unlocked">
                                                            <i class="bi bi-unlock me-1"></i>Unlocked
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="actions-cell">
                                                <div class="action-buttons">
                                                    <a href="assessment_edit.php?id=<?php echo $assessment['id']; ?>" 
                                                       class="btn btn-action btn-edit" title="Edit Assessment">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button class="btn btn-action btn-<?php echo ($assessment['is_active'] ?? false) ? 'pause' : 'play'; ?>" 
                                                            onclick="toggleStatus('<?php echo $assessment['id']; ?>', <?php echo ($assessment['is_active'] ?? false) ? 0 : 1; ?>)"
                                                            title="<?php echo ($assessment['is_active'] ?? false) ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="bi bi-<?php echo ($assessment['is_active'] ?? false) ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                    
                                                    <button class="btn btn-action btn-<?php echo $assessment['is_locked'] ? 'unlock' : 'lock'; ?>" 
                                                            onclick="toggleLock('<?php echo $assessment['id']; ?>', <?php echo $assessment['is_locked'] ? 0 : 1; ?>)"
                                                            title="<?php echo $assessment['is_locked'] ? 'Unlock' : 'Lock'; ?>">
                                                        <i class="bi bi-<?php echo $assessment['is_locked'] ? 'unlock' : 'lock'; ?>"></i>
                                                    </button>
                                                    
                                                    <button class="btn btn-action btn-settings" 
                                                            onclick="configureLock('<?php echo $assessment['id']; ?>', '<?php echo htmlspecialchars($assessment['assessment_title']); ?>', '<?php echo $assessment['lock_type']; ?>', <?php echo $assessment['is_locked']; ?>)"
                                                            title="Configure Lock Settings">
                                                        <i class="bi bi-gear"></i>
                                                    </button>
                                                    <?php if ($assessment['attempt_count'] == 0): ?>
                                                        <button class="btn btn-action btn-delete" 
                                                                onclick="deleteAssessment('<?php echo $assessment['id']; ?>', '<?php echo htmlspecialchars($assessment['assessment_title']); ?>')"
                                                                title="Delete Assessment">
                                                            <i class="bi bi-trash"></i>
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
    </div>
</div>

<style>
/* Page Header Styles */
.page-header {
    background: #2E5E4E;
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(46, 94, 78, 0.3);
}

.page-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    backdrop-filter: blur(10px);
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.page-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.page-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    opacity: 0.9;
}

.page-actions .btn {
    border-radius: 12px;
    padding: 12px 24px;
    font-weight: 600;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    transition: all 0.3s ease;
}

.page-actions .btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

/* Academic Year Selector */
.academic-year-selector {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}

.selector-label {
    font-size: 1.1rem;
    color: #495057;
}

.academic-period-select {
    min-width: 300px;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 12px 16px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.academic-period-select:focus {
    border-color: #2E5E4E;
    box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25);
}

/* Filters Card */
.filters-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    overflow: hidden;
}

.filters-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: between;
    align-items: center;
}

.filters-header h6 {
    color: #495057;
    font-weight: 600;
}

.filter-count .badge {
    font-size: 0.8rem;
    padding: 6px 12px;
    border-radius: 20px;
}

.filters-body {
    padding: 2rem;
}

.filter-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.filter-select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

.filter-select:focus {
    border-color: #2E5E4E;
    box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25);
}

.filter-clear-btn {
    border-radius: 8px;
    padding: 12px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.filter-clear-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Assessments Card */
.assessments-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    overflow: hidden;
}

.assessments-header {
    background: #f8f9fa;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #dee2e6;
}

.assessments-title h5 {
    color: #495057;
    font-weight: 600;
    margin: 0;
}

.assessments-actions .btn {
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.assessments-actions .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: #2E5E4E;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: white;
}

.empty-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.empty-description {
    color: #6c757d;
    margin-bottom: 2rem;
}

/* Table Styles */
.assessments-table-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
    position: relative;
}

.assessments-table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.assessments-table thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 20px 16px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.assessments-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f0f0f0;
}

.assessments-table tbody tr:hover {
    background: rgba(46, 94, 78, 0.05);
    transform: translateX(4px);
    box-shadow: 0 4px 20px rgba(46, 94, 78, 0.1);
}

.assessments-table tbody td {
    padding: 20px 16px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

/* Assessment Info */
.assessment-info {
    max-width: 300px;
}

.assessment-title h6 {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    transition: all 0.3s ease;
}

.assessment-row:hover .assessment-title h6 {
    color: #2E5E4E;
    transform: translateX(2px);
}

.assessment-description {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 8px;
    line-height: 1.4;
}

.assessment-meta {
    font-size: 0.8rem;
}

/* Course Info */
.course-info {
    max-width: 200px;
}

.course-name, .module-name {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.course-name {
    font-weight: 600;
    color: #495057;
}

.module-name {
    color: #6c757d;
}

/* Badge Styles */
.difficulty-badge {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.difficulty-easy {
    background: #2E5E4E;
    color: white;
}

.difficulty-medium {
    background: #ffc107;
    color: white;
}

.difficulty-hard {
    background: #dc3545;
    color: white;
}

.time-badge, .attempts-badge, .score-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 0.8rem;
    font-weight: 600;
}

.time-badge {
    background: #17a2b8;
    color: white;
}

.time-badge.no-limit {
    background: #6c757d;
    color: white;
}

.attempts-badge {
    background: #2E5E4E;
    color: white;
}

.score-badge.excellent {
    background: #2E5E4E;
    color: white;
}

.score-badge.good {
    background: #ffc107;
    color: white;
}

.score-badge.poor {
    background: #dc3545;
    color: white;
}

.score-badge.no-data {
    background: #6c757d;
    color: white;
}

/* Status Badges */
.status-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.status-badge, .lock-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-badge.active {
    background: #2E5E4E;
    color: white;
}

.status-badge.inactive {
    background: #6c757d;
    color: white;
}

.lock-badge.locked {
    background: #dc3545;
    color: white;
}

.lock-badge.unlocked {
    background: #2E5E4E;
    color: white;
}

.lock-reason {
    color: #6c757d;
    font-size: 0.75rem;
    margin-top: 4px;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.btn-action {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.btn-edit {
    background: #2E5E4E;
    color: white;
    border-color: #2E5E4E;
}

.btn-play {
    background: #2E5E4E;
    color: white;
    border-color: #2E5E4E;
}

.btn-pause {
    background: #ffc107;
    color: white;
    border-color: #ffc107;
}

.btn-lock {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.btn-unlock {
    background: #2E5E4E;
    color: white;
    border-color: #2E5E4E;
}

.btn-settings {
    background: #17a2b8;
    color: white;
    border-color: #17a2b8;
}

.btn-delete {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Custom Scrollbar */
.assessments-table-container::-webkit-scrollbar {
    width: 8px;
}

.assessments-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.assessments-table-container::-webkit-scrollbar-thumb {
    background: #2E5E4E;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.assessments-table-container::-webkit-scrollbar-thumb:hover {
    background: #7DCB80;
}

.assessments-table-container {
    scrollbar-width: thin;
    scrollbar-color: #2E5E4E #f1f1f1;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .assessments-table-container {
        max-height: 500px;
    }
}

@media (max-width: 991.98px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .page-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .assessments-table-container {
        max-height: 450px;
    }
    
    .assessments-table thead th,
    .assessments-table tbody td {
        padding: 16px 12px;
        font-size: 0.9rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 4px;
    }
}

@media (max-width: 767.98px) {
    .page-header {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .filters-body {
        padding: 1rem;
    }
    
    .assessments-table-container {
        max-height: 400px;
    }
    
    .assessments-table thead th,
    .assessments-table tbody td {
        padding: 12px 8px;
        font-size: 0.85rem;
    }
    
    .assessment-info, .course-info {
        max-width: 200px;
    }
}

@media (max-width: 575.98px) {
    .assessments-table-container {
        max-height: 350px;
    }
    
    .assessments-table thead th,
    .assessments-table tbody td {
        padding: 8px 4px;
        font-size: 0.8rem;
    }
    
    .btn-action {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }
}

/* Animations */
.assessment-row {
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

.assessment-row:hover {
    animation: pulse 0.6s ease-in-out;
}

@keyframes pulse {
    0% { transform: translateX(4px) scale(1); }
    50% { transform: translateX(4px) scale(1.02); }
    100% { transform: translateX(4px) scale(1); }
}
</style>

<!-- Toggle Status Form -->
<form id="toggleStatusForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="assessment_id" id="toggle_assessment_id">
    <input type="hidden" name="is_active" id="toggle_is_active">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Toggle Lock Form -->
<form id="toggleLockForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="toggle_lock">
    <input type="hidden" name="assessment_id" id="toggle_lock_assessment_id">
    <input type="hidden" name="is_locked" id="toggle_lock_is_locked">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Delete Assessment Form -->
<form id="deleteAssessmentForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="assessment_id" id="delete_assessment_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Assessment Lock Configuration Modal -->
<div class="modal fade" id="lockConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Assessment Lock Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="configure_lock">
                    <input type="hidden" name="assessment_id" id="config_assessment_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6 id="config_assessment_title" class="text-primary"></h6>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Lock Type</label>
                            <select class="form-select" id="config_lock_type" name="lock_type" onchange="updateLockFields()">
                                <option value="manual">Manual Lock/Unlock</option>
                                <option value="prerequisite_score">Prerequisite Assessment Score</option>
                                <option value="prerequisite_videos">Prerequisite Video Completion</option>
                                <option value="date_based">Date-Based Availability</option>
                            </select>
                            <small class="text-muted">Choose how this assessment will be locked</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Lock Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="config_is_locked" name="is_locked" value="1">
                                <label class="form-check-label" for="config_is_locked">
                                    Assessment is locked
                                </label>
                            </div>
                            <small class="text-muted">Students cannot take locked assessments</small>
                        </div>
                    </div>
                    
                    <!-- Prerequisite Assessment Fields -->
                    <div id="prerequisite_score_fields" class="row mb-3" style="display: none;">
                        <div class="col-md-6">
                            <label class="form-label">Prerequisite Assessment</label>
                            <select class="form-select" name="prerequisite_assessment_id" id="config_prerequisite_assessment">
                                <option value="">Select Assessment</option>
                                <?php foreach ($available_assessments as $assess): ?>
                                    <option value="<?php echo $assess['id']; ?>">
                                        <?php echo htmlspecialchars($assess['assessment_title'] . ' (' . $assess['module_title'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Score Required (%)</label>
                            <input type="number" class="form-control" name="prerequisite_score" id="config_prerequisite_score" 
                                   min="0" max="100" step="5" value="70">
                        </div>
                    </div>
                    
                    <!-- Prerequisite Video Fields -->
                    <div id="prerequisite_video_fields" class="row mb-3" style="display: none;">
                        <div class="col-md-6">
                            <label class="form-label">Minimum Videos to Watch</label>
                            <input type="number" class="form-control" name="prerequisite_video_count" id="config_prerequisite_video_count" 
                                   min="1" max="50" value="5">
                        </div>
                    </div>
                    
                    <!-- Date-Based Fields -->
                    <div id="date_based_fields" class="row mb-3" style="display: none;">
                        <div class="col-md-6">
                            <label class="form-label">Unlock Date & Time</label>
                            <input type="datetime-local" class="form-control" name="unlock_date" id="config_unlock_date">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Custom Lock Message</label>
                            <textarea class="form-control" name="lock_message" id="config_lock_message" rows="3" 
                                      placeholder="Optional: Custom message shown to students when assessment is locked"></textarea>
                            <small class="text-muted">Leave empty to use default message</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Lock Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleStatus(assessmentId, isActive) {
    const action = isActive ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this assessment?`)) {
        document.getElementById('toggle_assessment_id').value = assessmentId;
        document.getElementById('toggle_is_active').value = isActive;
        document.getElementById('toggleStatusForm').submit();
    }
}

function deleteAssessment(assessmentId, assessmentTitle) {
    if (confirm(`Are you sure you want to delete "${assessmentTitle}"? This action cannot be undone.`)) {
        document.getElementById('delete_assessment_id').value = assessmentId;
        document.getElementById('deleteAssessmentForm').submit();
    }
}

function toggleLock(assessmentId, isLocked) {
    const action = isLocked ? 'unlock' : 'lock';
    if (confirm(`Are you sure you want to ${action} this assessment?`)) {
        document.getElementById('toggle_lock_assessment_id').value = assessmentId;
        document.getElementById('toggle_lock_is_locked').value = isLocked;
        document.getElementById('toggleLockForm').submit();
    }
}

function configureLock(assessmentId, assessmentTitle, currentLockType, isLocked) {
    // Populate modal fields
    document.getElementById('config_assessment_id').value = assessmentId;
    document.getElementById('config_assessment_title').textContent = assessmentTitle;
    document.getElementById('config_lock_type').value = currentLockType;
    document.getElementById('config_is_locked').checked = isLocked == 1;
    
    // Show/hide fields based on current lock type
    updateLockFields();
    
    // Show the modal
    new bootstrap.Modal(document.getElementById('lockConfigModal')).show();
}

function updateLockFields() {
    const lockType = document.getElementById('config_lock_type').value;
    
    // Hide all conditional fields first
    document.getElementById('prerequisite_score_fields').style.display = 'none';
    document.getElementById('prerequisite_video_fields').style.display = 'none';
    document.getElementById('date_based_fields').style.display = 'none';
    
    // Show relevant fields based on lock type
    switch(lockType) {
        case 'prerequisite_score':
            document.getElementById('prerequisite_score_fields').style.display = 'block';
            break;
        case 'prerequisite_videos':
            document.getElementById('prerequisite_video_fields').style.display = 'block';
            break;
        case 'date_based':
            document.getElementById('date_based_fields').style.display = 'block';
            break;
    }
}

function refreshAssessments() {
    // Add loading state
    const tableContainer = document.querySelector('.assessments-table-container');
    if (tableContainer) {
        tableContainer.classList.add('assessments-table-loading');
    }
    
    // Reload the page
    window.location.reload();
}

function exportAssessments() {
    // Create a simple CSV export
    const assessments = <?php echo json_encode($assessments); ?>;
    const csvContent = generateCSV(assessments);
    downloadCSV(csvContent, 'assessments_export.csv');
}

function generateCSV(assessments) {
    const headers = ['Assessment Title', 'Course', 'Module', 'Difficulty', 'Time Limit', 'Attempts', 'Average Score', 'Status', 'Lock Status'];
    const rows = assessments.map(assessment => [
        assessment.assessment_title,
        assessment.course_name,
        assessment.module_title || 'Module Assessment',
        assessment.difficulty,
        assessment.time_limit ? assessment.time_limit + ' min' : 'No limit',
        assessment.attempt_count,
        assessment.average_score ? assessment.average_score.toFixed(1) + '%' : 'No attempts',
        assessment.is_active ? 'Active' : 'Inactive',
        assessment.is_locked ? 'Locked' : 'Unlocked'
    ]);
    
    return [headers, ...rows].map(row => row.map(field => `"${field}"`).join(',')).join('\n');
}

function downloadCSV(csvContent, filename) {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Enhanced scrolling behavior for assessments table
document.addEventListener('DOMContentLoaded', function() {
    function enhanceAssessmentsTableScrolling() {
        const tableContainer = document.querySelector('.assessments-table-container');
        
        if (tableContainer) {
            // Add smooth scrolling behavior
            tableContainer.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const cardContainer = tableContainer.closest('.card');
            if (cardContainer) {
                addAssessmentsTableScrollIndicators(tableContainer, cardContainer);
            }
        }
    }
    
    // Add scroll indicators to assessments table
    function addAssessmentsTableScrollIndicators(scrollContainer, cardContainer) {
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = 'assessments-scroll-indicator';
        scrollIndicator.innerHTML = `
            <div class="assessments-scroll-indicator-content">
                <i class="bi bi-chevron-up assessments-scroll-indicator-top"></i>
                <i class="bi bi-chevron-down assessments-scroll-indicator-bottom"></i>
            </div>
        `;
        
        cardContainer.style.position = 'relative';
        cardContainer.appendChild(scrollIndicator);
        
        // Update scroll indicators based on scroll position
        function updateAssessmentsScrollIndicators() {
            const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
            const isAtTop = scrollContainer.scrollTop === 0;
            const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
            
            if (isScrollable) {
                scrollIndicator.classList.add('show');
                scrollIndicator.querySelector('.assessments-scroll-indicator-top').classList.toggle('hide', isAtTop);
                scrollIndicator.querySelector('.assessments-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
            } else {
                scrollIndicator.classList.remove('show');
            }
        }
        
        // Initial check
        updateAssessmentsScrollIndicators();
        
        // Update on scroll
        scrollContainer.addEventListener('scroll', updateAssessmentsScrollIndicators);
        
        // Update on resize
        window.addEventListener('resize', updateAssessmentsScrollIndicators);
    }
    
    // Initialize enhanced assessments table scrolling
    enhanceAssessmentsTableScrolling();
});
</script>

<?php require_once '../includes/footer.php'; ?> 
<?php require_once '../includes/footer.php'; ?> 