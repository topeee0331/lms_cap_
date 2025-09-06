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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3">Assessments</h1>
                    <p class="text-muted mb-0">Manage and monitor student assessment attempts</p>
                </div>
                <div class="btn-group">
                    <a href="attempt_history.php" class="btn btn-outline-info">
                        <i class="bi bi-history me-1"></i>Attempt History
                    </a>
                </div>
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

    <?php if (!$is_acad_year_active): ?>
        <div class="alert alert-warning mb-4">
            <strong>Inactive Academic Year:</strong> This academic year is inactive. You can only view and review content. All editing, adding, and uploading is disabled.
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <input type="hidden" name="academic_period_id" value="<?= $selected_year_id ?>">
                        <div class="col-md-3">
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
                        <div class="col-md-3">
                            <label for="section" class="form-label">Filter by Section</label>
                            <select class="form-select" id="section" name="section" onchange="this.form.submit()">
                                <option value="">All Sections</option>
                                <?php foreach ($sections_for_filter as $section): ?>
                                    <option value="<?php echo $section['id']; ?>" <?php echo $section_filter == $section['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(formatSectionName($section)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="module" class="form-label">Filter by Module</label>
                            <select class="form-select" id="module" name="module" onchange="this.form.submit()">
                                <option value="">All Modules</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module['id']; ?>" 
                                            <?php echo $module_filter == $module['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($module['module_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="difficulty" class="form-label">Filter by Difficulty</label>
                            <select class="form-select" id="difficulty" name="difficulty" onchange="this.form.submit()">
                                <option value="">All Difficulties</option>
                                <option value="easy" <?php echo $difficulty_filter === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo $difficulty_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo $difficulty_filter === 'hard' ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="assessments.php?academic_period_id=<?= $selected_year_id ?>" class="btn btn-outline-secondary">Clear Filters</a>
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
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Assessments (<?php echo count($assessments); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assessments)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clipboard-check fs-1 text-muted mb-3"></i>
                            <h6>No Assessments Found</h6>
                            <p class="text-muted">Create your first assessment to start evaluating student progress.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Assessment</th>
                                        <th>Course & Module</th>
                                        <th>Difficulty</th>
                                        <th>Time Limit</th>
                                        <th>Attempts</th>
                                        <th>Avg Score</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assessments as $assessment): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($assessment['assessment_title']); ?></h6>
                                                    <?php if ($assessment['description']): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($assessment['description'], 0, 100)) . '...'; ?></small>
                                                    <?php endif; ?>
                                                    <small class="text-muted">
                                                        Created by: <?php echo htmlspecialchars($assessment['creator_name'] . ' (' . $assessment['creator_username'] . ')'); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($assessment['course_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assessment['module_title'] ?? 'Module Assessment'); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $assessment['difficulty'] === 'easy' ? 'success' : ($assessment['difficulty'] === 'medium' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($assessment['difficulty']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($assessment['time_limit']): ?>
                                                    <span class="badge bg-info"><?php echo $assessment['time_limit']; ?> min</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No limit</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $assessment['attempt_count']; ?> attempts</span>
                                            </td>
                                            <td>
                                                <?php if ($assessment['average_score']): ?>
                                                    <span class="badge bg-<?php echo $assessment['average_score'] >= 90 ? 'success' : ($assessment['average_score'] >= 70 ? 'warning' : 'danger'); ?>">
                                                        <?php echo number_format($assessment['average_score'], 1); ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No attempts</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <?php if (($assessment['is_active'] ?? false)): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($assessment['is_locked']): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-lock me-1"></i>Locked
                                                        </span>
                                                        <small class="text-muted">
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
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-unlock me-1"></i>Unlocked
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="assessment_edit.php?id=<?php echo $assessment['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button class="btn btn-outline-<?php echo ($assessment['is_active'] ?? false) ? 'warning' : 'success'; ?>" 
                                                            onclick="toggleStatus('<?php echo $assessment['id']; ?>', <?php echo ($assessment['is_active'] ?? false) ? 0 : 1; ?>)"
                                                            title="<?php echo ($assessment['is_active'] ?? false) ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="bi bi-<?php echo ($assessment['is_active'] ?? false) ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                    
                                                    <button class="btn btn-outline-<?php echo $assessment['is_locked'] ? 'success' : 'danger'; ?>" 
                                                            onclick="toggleLock('<?php echo $assessment['id']; ?>', <?php echo $assessment['is_locked'] ? 0 : 1; ?>)"
                                                            title="<?php echo $assessment['is_locked'] ? 'Unlock' : 'Lock'; ?>">
                                                        <i class="bi bi-<?php echo $assessment['is_locked'] ? 'unlock' : 'lock'; ?>"></i>
                                                    </button>
                                                    
                                                    <button class="btn btn-outline-info" 
                                                            onclick="configureLock('<?php echo $assessment['id']; ?>', '<?php echo htmlspecialchars($assessment['assessment_title']); ?>', '<?php echo $assessment['lock_type']; ?>', <?php echo $assessment['is_locked']; ?>)"
                                                            title="Configure Lock Settings">
                                                        <i class="bi bi-gear"></i>
                                                    </button>
                                                    <?php if ($assessment['attempt_count'] == 0): ?>
                                                        <button class="btn btn-outline-danger delete-confirm" 
                                                                onclick="deleteAssessment('<?php echo $assessment['id']; ?>', '<?php echo htmlspecialchars($assessment['assessment_title']); ?>')"
                                                                title="Delete">
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
</script>

<?php require_once '../includes/footer.php'; ?> 