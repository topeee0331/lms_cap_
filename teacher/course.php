<?php
$page_title = 'Course Management';
require_once '../includes/header.php';
requireRole('teacher');

// Helper function to get next available module order
function getNextAvailableModuleOrder($modules) {
    if (empty($modules)) {
        return 1;
    }
    
    $existing_orders = array_column($modules, 'module_order');
    $existing_orders = array_filter($existing_orders, function($order) {
        return is_numeric($order) && $order > 0;
    });
    
    if (empty($existing_orders)) {
        return 1;
    }
    
    // Find the next available order number
    $max_order = max($existing_orders);
    $next_order = $max_order + 1;
    
    // Check for gaps in the sequence
    for ($i = 1; $i <= $max_order; $i++) {
        if (!in_array($i, $existing_orders)) {
            return $i;
        }
    }
    
    return $next_order;
}

// Helper function to validate module order uniqueness
function validateModuleOrder($modules, $new_order, $exclude_id = null) {
    foreach ($modules as $module) {
        if ($exclude_id && $module['id'] === $exclude_id) {
            continue; // Skip the module being edited
        }
        if (isset($module['module_order']) && $module['module_order'] == $new_order) {
            return false; // Order already exists
        }
    }
    return true; // Order is unique
}
?>

<style>
/* Improved Stats Cards */
.stats-card {
    background: #ffffff;
    border: 2px solid #e9ecef;
    border-radius: 1.2rem;
    padding: 2rem 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: #2E5E4E;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2E5E4E, #7DCB80);
}

.stats-card i {
    color: #2E5E4E;
    margin-bottom: 1rem;
    font-size: 2.5rem;
}

.stats-card h3 {
    color: #2E5E4E;
    font-weight: 700;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.stats-card p {
    color: #6c757d;
    font-weight: 500;
    margin-bottom: 0;
    font-size: 0.95rem;
}

/* Different colors for different stats */
.stats-card.students {
    border-color: #28a745;
}

.stats-card.students::before {
    background: linear-gradient(90deg, #28a745, #20c997);
}

.stats-card.students i,
.stats-card.students h3 {
    color: #28a745;
}

.stats-card.modules {
    border-color: #007bff;
}

.stats-card.modules::before {
    background: linear-gradient(90deg, #007bff, #17a2b8);
}

.stats-card.modules i,
.stats-card.modules h3 {
    color: #007bff;
}

.stats-card.videos {
    border-color: #fd7e14;
}

.stats-card.videos::before {
    background: linear-gradient(90deg, #fd7e14, #ffc107);
}

.stats-card.videos i,
.stats-card.videos h3 {
    color: #fd7e14;
}

.stats-card.assessments {
    border-color: #dc3545;
}

.stats-card.assessments::before {
    background: linear-gradient(90deg, #dc3545, #e83e8c);
}

.stats-card.assessments i,
.stats-card.assessments h3 {
    color: #dc3545;
}

/* Ensure accordion functionality works properly */
.accordion-button:not(.collapsed) {
    background-color: #e7f1ff;
    color: #0c63e4;
}

.accordion-button:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.accordion-button:hover {
    background-color: #e7f1ff;
}

.accordion-collapse {
    transition: all 0.3s ease;
    overflow: hidden;
}

.accordion-collapse:not(.show) {
    display: none;
}

.accordion-collapse.show {
    display: block;
}

/* Ensure proper spacing and alignment */
.accordion-item {
    border: 1px solid rgba(0, 0, 0, 0.125);
    margin-bottom: 0.5rem;
}

.accordion-button {
    padding: 1rem 1.25rem;
    font-weight: 500;
    cursor: pointer;
    user-select: none;
    transition: all 0.2s ease;
    position: relative;
    z-index: 1;
}

.accordion-button:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
}

.accordion-button:active {
    transform: translateY(0);
}

.accordion-body {
    padding: 1rem 1.25rem;
}

/* Debug styles to ensure visibility */
.accordion-button.collapsed {
    background-color: #fff;
    color: #212529;
}

.accordion-button.collapsed:hover {
    background-color: #e9ecef;
}

/* Ensure the entire button area is clickable */
.accordion-button * {
    pointer-events: none;
}

.accordion-button {
    pointer-events: auto;
}
</style>

<?php

$course_id = (int)($_GET['id'] ?? 0);

// Verify teacher owns this course
$stmt = $db->prepare("
    SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
    FROM courses c
    JOIN academic_periods ap ON c.academic_period_id = ap.id
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ? AND c.teacher_id = ?
");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php?error=Course not found or access denied.');
    exit;
}





$message = '';
$message_type = '';

// Handle module actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'create_module':
                $module_title = sanitizeInput($_POST['module_title'] ?? '');
                $module_description = sanitizeInput($_POST['module_description'] ?? '');
                $module_order = (int)($_POST['module_order'] ?? 1);
                $is_locked = isset($_POST['is_locked']) ? 1 : 0;
                $unlock_score = (int)($_POST['unlock_score'] ?? 70);
                
                if (empty($module_title)) {
                    $message = 'Module title is required.';
                    $message_type = 'danger';
                } else {
                    // Get current modules JSON
                    $current_modules = $course['modules'] ? json_decode($course['modules'], true) : [];
                    if (!is_array($current_modules)) {
                        $current_modules = [];
                    }
                    
                    // Validate module order uniqueness
                    if (!validateModuleOrder($current_modules, $module_order)) {
                        $message = 'Module order ' . $module_order . ' is already taken. Please choose a different order number.';
                        $message_type = 'danger';
                    } else {
                        // Create new module
                    $new_module = [
                        'id' => uniqid('mod_'),
                        'module_title' => $module_title,
                        'module_description' => $module_description,
                        'module_order' => $module_order,
                        'is_locked' => $is_locked,
                        'unlock_score' => $unlock_score,
                        'videos' => [],
                        'assessments' => [],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $current_modules[] = $new_module;
                    
                    // Update course with new modules JSON
                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                    $stmt->execute([json_encode($current_modules), $course_id]);
                    
                    // Refresh course data to show updated modules
                    $stmt = $db->prepare("
                        SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
                        FROM courses c
                        JOIN academic_periods ap ON c.academic_period_id = ap.id
                        JOIN users u ON c.teacher_id = u.id
                        WHERE c.id = ? AND c.teacher_id = ?
                    ");
                    $stmt->execute([$course_id, $_SESSION['user_id']]);
                    $course = $stmt->fetch();
                    
                    $message = 'Module created successfully.';
                    $message_type = 'success';
                    }
                }
                break;
                
            case 'update_module':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                $module_title = sanitizeInput($_POST['module_title'] ?? '');
                $module_description = sanitizeInput($_POST['module_description'] ?? '');
                $module_order = (int)($_POST['module_order'] ?? 1);
                $is_locked = isset($_POST['is_locked']) ? 1 : 0;
                $unlock_score = (int)($_POST['unlock_score'] ?? 70);
                
                if (empty($module_title)) {
                    $message = 'Module title is required.';
                    $message_type = 'danger';
                } else {
                    // Get current modules JSON
                    $current_modules = $course['modules'] ? json_decode($course['modules'], true) : [];
                    if (!is_array($current_modules)) {
                        $current_modules = [];
                    }
                    
                    // Validate module order uniqueness
                    if (!validateModuleOrder($current_modules, $module_order, $module_id)) {
                        $message = 'Module order ' . $module_order . ' is already taken. Please choose a different order number.';
                        $message_type = 'danger';
                    } else {
                        // Find and update the module
                    foreach ($current_modules as &$module) {
                        if ($module['id'] === $module_id) {
                            $module['module_title'] = $module_title;
                            $module['module_description'] = $module_description;
                            $module['module_order'] = $module_order;
                            $module['is_locked'] = $is_locked;
                            $module['unlock_score'] = $unlock_score;
                            $module['updated_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    
                    // Update course with updated modules JSON
                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                    $stmt->execute([json_encode($current_modules), $course_id]);
                    
                    // Refresh course data to show updated modules
                    $stmt = $db->prepare("
                        SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
                        FROM courses c
                        JOIN academic_periods ap ON c.academic_period_id = ap.id
                        JOIN users u ON c.teacher_id = u.id
                        WHERE c.id = ? AND c.teacher_id = ?
                    ");
                    $stmt->execute([$course_id, $_SESSION['user_id']]);
                    $course = $stmt->fetch();
                    
                    $message = 'Module updated successfully.';
                    $message_type = 'success';
                    }
                }
                break;
                
            case 'delete_module':
                $module_id = sanitizeInput($_POST['module_id'] ?? '');
                
                // Get current modules JSON
                $current_modules = $course['modules'] ? json_decode($course['modules'], true) : [];
                if (!is_array($current_modules)) {
                    $current_modules = [];
                }
                
                // Remove the module
                $current_modules = array_filter($current_modules, function($module) use ($module_id) {
                    return $module['id'] !== $module_id;
                });
                
                // Update course with updated modules JSON
                $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                $stmt->execute([json_encode(array_values($current_modules)), $course_id]);
                
                // Refresh course data to show updated modules
                $stmt = $db->prepare("
                    SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
                    FROM courses c
                    JOIN academic_periods ap ON c.academic_period_id = ap.id
                    JOIN users u ON c.teacher_id = u.id
                    WHERE c.id = ? AND c.teacher_id = ?
                ");
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                $course = $stmt->fetch();
                
                $message = 'Module deleted successfully.';
                $message_type = 'success';
                break;
        }
    }
}

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year_level']}{$section['section_name']}";
}

// Get course modules from JSON field
$modules = [];
if ($course['modules']) {
    $modules_data = json_decode($course['modules'], true);
    if (is_array($modules_data)) {
        foreach ($modules_data as $module) {
            // Add placeholder counts for now - these would need to be calculated from the JSON data
            $module['video_count'] = isset($module['videos']) ? count($module['videos']) : 0;
            $module['assessment_count'] = isset($module['assessments']) ? count($module['assessments']) : 0;
            $modules[] = $module;
        }
    }
}

// Get students in sections for this course
$stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.is_irregular, u.identifier
                      FROM sections s 
                      JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
                      WHERE JSON_SEARCH((SELECT sections FROM courses WHERE id = ?), 'one', s.id) IS NOT NULL 
                      ORDER BY u.last_name, u.first_name");
$stmt->execute([$course_id]);
$students = $stmt->fetchAll();

// Get course statistics (fix student count to use assigned sections)
$stats_sql = "
    SELECT 
        (SELECT COUNT(DISTINCT u.id)
         FROM sections s
         JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
         WHERE JSON_SEARCH((SELECT sections FROM courses WHERE id = ?), 'one', s.id) IS NOT NULL) as enrolled_students,
        (SELECT JSON_LENGTH(modules) FROM courses WHERE id = ?) as total_modules,
        (SELECT 
            COALESCE((
                SELECT SUM(JSON_LENGTH(JSON_EXTRACT(modules, '$[*].videos')))
                FROM courses 
                WHERE id = ?
            ), 0)
        ) as total_videos,
        (SELECT 
            COALESCE((
                SELECT SUM(JSON_LENGTH(JSON_EXTRACT(modules, '$[*].assessments')))
                FROM courses 
                WHERE id = ?
            ), 0)
        ) as total_assessments
";
$stmt = $db->prepare($stats_sql);
$stmt->execute([$course_id, $course_id, $course_id, $course_id]);
$stats = $stmt->fetch();
?>

<div class="container-fluid">
    <a href="courses.php" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Back to My Courses
    </a>
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1"><?php echo htmlspecialchars($course['course_name']); ?></h1>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($course['course_code']); ?> • 
                        <?php echo htmlspecialchars($course['academic_year']); ?> • 
                        By <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="course_edit.php?id=<?php echo $course_id; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Edit Course
                    </a>
                    <a href="course_students.php?id=<?php echo $course_id; ?>" class="btn btn-outline-info">
                        <i class="bi bi-people me-1"></i>Students
                    </a>
                    <a href="course_analytics.php?id=<?php echo $course_id; ?>" class="btn btn-outline-success">
                        <i class="bi bi-graph-up me-1"></i>Analytics
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Course Description -->
    <?php if ($course['description']): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Course Description</h6>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Course Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card students text-center">
                <i class="bi bi-people"></i>
                <h3><?php echo $stats['enrolled_students']; ?></h3>
                <p class="mb-0">Enrolled Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card modules text-center">
                <i class="bi bi-collection"></i>
                <h3><?php echo $stats['total_modules']; ?></h3>
                <p class="mb-0">Modules</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card videos text-center">
                <i class="bi bi-play-circle"></i>
                <h3><?php echo $stats['total_videos']; ?></h3>
                <p class="mb-0">Videos</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card assessments text-center">
                <i class="bi bi-clipboard-check"></i>
                <h3><?php echo $stats['total_assessments']; ?></h3>
                <p class="mb-0">Assessments</p>
            </div>
        </div>
    </div>

    <!-- Leaderboard Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="card-title mb-2">
                                <i class="bi bi-trophy text-warning me-2"></i>
                                Course Leaderboard
                            </h5>
                            <p class="card-text text-muted mb-0">
                                View student performance rankings and filter by sections to track progress across your course.
                            </p>
                        </div>
                        <div class="col-md-4">
                            <a href="leaderboard.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary btn-lg">
                                <i class="bi bi-bar-chart me-2"></i>
                                View Leaderboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modules Section -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Course Modules</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModuleModal">
                        <i class="bi bi-plus-circle me-1"></i>Add Module
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($modules)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-collection fs-1 text-muted mb-3"></i>
                            <h6>No Modules Yet</h6>
                            <p class="text-muted">Create your first module to start building your course content.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModuleModal">
                                <i class="bi bi-plus-circle me-1"></i>Create First Module
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="accordion" id="modulesAccordion">
                            <?php foreach ($modules as $index => $module): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="module<?php echo $module['id']; ?>">
                                        <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" onclick="toggleModule('<?php echo $module['id']; ?>')" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $module['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($module['module_title']); ?></strong>
                                                    <span class="badge bg-secondary ms-2">Module <?php echo $module['module_order']; ?></span>
                                                    <?php if ($module['is_locked']): ?>
                                                        <span class="badge bg-warning ms-1">Locked (<?php echo $module['unlock_score']; ?>% required)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <span class="badge bg-info me-1"><?php echo $module['video_count']; ?> videos</span>
                                                    <span class="badge bg-success me-1"><?php echo $module['assessment_count']; ?> assessments</span>
                                                    <?php if (isset($module['file']) && !empty($module['file'])): ?>
                                                        <span class="badge bg-primary" title="Module has attached file: <?php echo htmlspecialchars($module['file']['original_name']); ?>">
                                                            <i class="fas fa-paperclip me-1"></i>File
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $module['id']; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="module<?php echo $module['id']; ?>">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($module['module_description'])); ?></p>
                                                </div>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="editModule(<?php echo htmlspecialchars(json_encode($module)); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <a href="module.php?id=<?php echo $module['id']; ?>" class="btn btn-outline-info">
                                                        <i class="bi bi-gear"></i>
                                                    </a>
                                                    <button class="btn btn-outline-danger delete-confirm" onclick="deleteModule('<?php echo $module['id']; ?>', '<?php echo htmlspecialchars($module['module_title']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Module Actions -->
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <a href="module_videos.php?module_id=<?php echo $module['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                                        <i class="bi bi-play-circle me-1"></i>Manage Videos
                                                    </a>
                                                </div>
                                                <div class="col-md-6">
                                                    <a href="module_assessments.php?module_id=<?php echo $module['id']; ?>" class="btn btn-outline-success btn-sm w-100">
                                                        <i class="bi bi-clipboard-check me-1"></i>Manage Assessments
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="row mt-2">
                                                <div class="col-md-6">
                                                    <a href="videos.php?course_id=<?php echo $course_id; ?>&module_id=<?php echo $module['id']; ?>" class="btn btn-outline-info btn-sm w-100">
                                                        <i class="bi bi-list me-1"></i>View All Videos
                                                    </a>
                                                </div>
                                                <div class="col-md-6">
                                                    <a href="assessments.php?course_id=<?php echo $course_id; ?>&module_id=<?php echo $module['id']; ?>" class="btn btn-outline-warning btn-sm w-100">
                                                        <i class="bi bi-list me-1"></i>View All Assessments
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Module Modal -->
<div class="modal fade" id="createModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_module">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="create_module_title" class="form-label">Module Title</label>
                            <input type="text" class="form-control" id="create_module_title" name="module_title" required>
                        </div>
                        <div class="col-md-4">
                            <label for="create_module_order" class="form-label">Order</label>
                            <input type="number" class="form-control" id="create_module_order" name="module_order" 
                                   value="<?php echo getNextAvailableModuleOrder($modules); ?>" min="1" required>
                            <div class="form-text">Next available order number</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_module_description" class="form-label">Description</label>
                        <textarea class="form-control" id="create_module_description" name="module_description" rows="3"></textarea>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="create_is_locked" name="is_locked">
                                <label class="form-check-label" for="create_is_locked">
                                    Lock this module
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="create_unlock_score" class="form-label">Unlock Score (%)</label>
                            <input type="number" class="form-control" id="create_unlock_score" name="unlock_score" 
                                   value="70" min="0" max="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Module</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Module Modal -->
<div class="modal fade" id="editModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_module">
                    <input type="hidden" name="module_id" id="edit_module_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="edit_module_title" class="form-label">Module Title</label>
                            <input type="text" class="form-control" id="edit_module_title" name="module_title" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_module_order" class="form-label">Order</label>
                            <input type="number" class="form-control" id="edit_module_order" name="module_order" min="1" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_module_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_module_description" name="module_description" rows="3"></textarea>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_locked" name="is_locked">
                                <label class="form-check-label" for="edit_is_locked">
                                    Lock this module
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_unlock_score" class="form-label">Unlock Score (%)</label>
                            <input type="number" class="form-control" id="edit_unlock_score" name="unlock_score" 
                                   min="0" max="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Module</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Module Form -->
<form id="deleteModuleForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_module">
    <input type="hidden" name="module_id" id="delete_module_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<script>
// Simple toggle function for modules
function toggleModule(moduleId) {
    const button = document.querySelector(`button[onclick="toggleModule('${moduleId}')"]`);
    const targetElement = document.getElementById(`collapse${moduleId}`);
    
    if (button && targetElement) {
        const isCollapsed = button.classList.contains('collapsed');
        
        // Toggle the collapsed class
        button.classList.toggle('collapsed');
        
        // Update aria-expanded attribute
        button.setAttribute('aria-expanded', isCollapsed ? 'true' : 'false');
        
        // Simple toggle
        if (isCollapsed) {
            // Opening
            targetElement.classList.add('show');
            targetElement.style.display = 'block';
        } else {
            // Closing
            targetElement.classList.remove('show');
            targetElement.style.display = 'none';
        }
    }
}

function editModule(module) {
    document.getElementById('edit_module_id').value = module.id;
    document.getElementById('edit_module_title').value = module.module_title;
    document.getElementById('edit_module_description').value = module.module_description;
    document.getElementById('edit_module_order').value = module.module_order;
    document.getElementById('edit_is_locked').checked = module.is_locked == 1;
    document.getElementById('edit_unlock_score').value = module.unlock_score;
    
    new bootstrap.Modal(document.getElementById('editModuleModal')).show();
}

function deleteModule(moduleId, moduleTitle) {
    if (confirm(`Are you sure you want to delete "${moduleTitle}"? This will also delete all videos and assessments in this module.`)) {
        document.getElementById('delete_module_id').value = moduleId;
        document.getElementById('deleteModuleForm').submit();
    }
}

// Form validation functions for module orders
function validateModuleOrderInput(input, isEdit = false) {
    const orderValue = parseInt(input.value);
    const currentModules = <?php echo json_encode($modules ?? []); ?>;
    
    // Check if order is already taken
    for (let module of currentModules) {
        if (isEdit && module.id === document.getElementById('edit_module_id').value) {
            continue; // Skip the module being edited
        }
        if (module.module_order == orderValue) {
            input.setCustomValidity('This order number is already taken. Please choose a different number.');
            return false;
        }
    }
    
    input.setCustomValidity('');
    return true;
}

// Add event listeners for order validation
document.addEventListener('DOMContentLoaded', function() {
    const createOrderInput = document.getElementById('create_module_order');
    const editOrderInput = document.getElementById('edit_module_order');
    
    if (createOrderInput) {
        createOrderInput.addEventListener('input', function() {
            validateModuleOrderInput(this, false);
        });
    }
    
    if (editOrderInput) {
        editOrderInput.addEventListener('input', function() {
            validateModuleOrderInput(this, true);
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 