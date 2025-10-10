<?php
require_once '../config/database.php';
require_once '../includes/header.php';
?>
<!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap');
    
    .course-card {
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
    }
    .course-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .course-image {
        height: 200px;
        object-fit: cover;
    }
    
    /* Creative Course Code Styling */
    .course-code-text {
        font-family: 'Poppins', 'Arial', sans-serif;
        font-size: 2.8rem;
        font-weight: 800;
        color: rgba(255, 255, 255, 0.9);
        text-align: center;
        mix-blend-mode: overlay;
        position: relative;
        z-index: 2;
    }
    
    /* Clickable card styles */
    .clickable-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .clickable-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }
    
    /* Badge modal specific styles */
    .badge-item {
        transition: all 0.3s ease;
    }
    
    .badge-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .selected-badge-item {
        transition: all 0.3s ease;
    }
    
    .selected-badge-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    /* Ensure proper padding in modal content */
    .modal-xl .modal-body {
        padding: 0;
    }
    
    .modal-xl .container-fluid {
        padding: 1.5rem;
    }
    
    /* Badge selection states */
    .badge-item.selected {
        background-color: rgba(25, 135, 84, 0.1) !important;
        border-color: #198754 !important;
    }
    
    .badge-item.selected .form-check-input:checked {
        background-color: #198754;
        border-color: #198754;
    }
    
    /* Badge filtering styles */
    .badge-item {
        transition: all 0.3s ease;
    }
    
    .badge-item[style*="display: none"] {
        opacity: 0;
        transform: scale(0.95);
        pointer-events: none;
    }
    
    .badge-item[style*="display: block"] {
        opacity: 1;
        transform: scale(1);
        pointer-events: auto;
    }
    
    .no-results-message {
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Filter status indicator */
    .filter-active {
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25) !important;
    }
    
    /* Filter controls styling */
    .form-select:focus, .form-control:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    
    /* Quick filter buttons */
    .btn-group .btn {
        border-radius: 0.375rem;
        margin-right: 2px;
    }
    
    .btn-group .btn:last-child {
        margin-right: 0;
    }
    
    /* Smooth transitions for all interactive elements */
    .form-check-input {
        transition: all 0.3s ease;
    }
    
    .btn {
        transition: all 0.3s ease;
    }
    
    /* Improved scrollbar styling */
    .modal-xl .card-body::-webkit-scrollbar {
        width: 6px;
    }
    
    .modal-xl .card-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    .modal-xl .card-body::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }
    
    .modal-xl .card-body::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    
    /* Horizontal badge layout */
    .selected-badge-item {
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    
    .selected-badge-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    /* Responsive horizontal layout */
    @media (max-width: 768px) {
        .selected-badge-item {
            min-width: 180px !important;
            max-width: 200px !important;
        }
    }
    
    /* Compact badge display */
    .selected-badge-item h6 {
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .selected-badge-item .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
</style>
<?php

// Assume teacher is logged in and their ID is in $_SESSION['user_id']
$teacher_id = $_SESSION['user_id'];

// 1. Fetch all academic periods for the dropdown
$ay_stmt = $db->prepare('SELECT id, academic_year, semester_name, is_active FROM academic_periods ORDER BY academic_year DESC, semester_name');
$ay_stmt->execute();
$all_years = $ay_stmt->fetchAll();

// 2. Handle academic period selection (GET or SESSION)
if (isset($_GET['academic_period_id'])) {
    $_SESSION['teacher_courses_academic_period_id'] = (int)$_GET['academic_period_id'];
}
// Find the first active academic year
$active_year = null;
foreach ($all_years as $year) {
    if ($year['is_active']) {
        $active_year = $year['id'];
        break;
    }
}
$selected_year_id = $_SESSION['teacher_courses_academic_period_id'] ?? $active_year ?? ($all_years[0]['id'] ?? null);

// Check if selected academic period is active
$year_stmt = $db->prepare('SELECT is_active FROM academic_periods WHERE id = ?');
$year_stmt->execute([$selected_year_id]);
$year_row = $year_stmt->fetch();
$is_acad_year_active = $year_row ? (bool)$year_row['is_active'] : true;

// 3. Fetch courses for selected academic period with student count only
$course_sql = "SELECT c.*, ap.academic_year, ap.semester_name,
               COALESCE(student_counts.total_students, 0) as student_count
               FROM courses c 
               LEFT JOIN academic_periods ap ON c.academic_period_id = ap.id
               
               -- Student count subquery (from sections assigned to course)
               LEFT JOIN (
                   SELECT 
                       c.id as course_id,
                       COUNT(DISTINCT u.id) as total_students
                   FROM courses c
                   LEFT JOIN sections s ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
                   LEFT JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL AND u.role = 'student'
                   WHERE c.teacher_id = ? AND c.academic_period_id = ?
                   GROUP BY c.id
               ) student_counts ON student_counts.course_id = c.id
               
               WHERE c.teacher_id = ? AND c.academic_period_id = ? AND c.is_archived = 0
               ORDER BY c.course_name";
$course_stmt = $db->prepare($course_sql);
$course_stmt->execute([$teacher_id, $selected_year_id, $teacher_id, $selected_year_id]);
$courses = $course_stmt->fetchAll();

// Calculate module, video, and assessment counts in PHP for better accuracy
foreach ($courses as &$course) {
    // Module count
    $modules = json_decode($course['modules'] ?? '[]', true);
    $course['module_count'] = is_array($modules) ? count($modules) : 0;
    
    // Video and assessment counts
    $total_videos = 0;
    $total_assessments = 0;
    
    
    if (is_array($modules)) {
        foreach ($modules as $module) {
            if (isset($module['videos']) && is_array($module['videos'])) {
                $total_videos += count($module['videos']);
            }
            if (isset($module['assessments']) && is_array($module['assessments'])) {
                $total_assessments += count($module['assessments']);
            }
        }
    }
    
    $course['video_count'] = $total_videos;
    $course['assessment_count'] = $total_assessments;
}

// After fetching courses, get teacher info for each course
foreach ($courses as &$course) {
    $stmt = $db->prepare('SELECT first_name, last_name, username FROM users WHERE id = ?');
    $stmt->execute([$course['teacher_id']]);
    $creator = $stmt->fetch();
    $course['creator_name'] = $creator ? $creator['first_name'] . ' ' . $creator['last_name'] : '';
    $course['creator_username'] = $creator['username'] ?? '';
}
unset($course);

// Fetch all sections for mapping (for both display and creation), but only active ones
$section_sql = "SELECT id, section_name, year_level FROM sections WHERE is_active = 1 ORDER BY year_level, section_name";
$section_res = $db->query($section_sql);
$sections_raw = $section_res ? $section_res->fetchAll() : [];
$sections = [];
foreach ($sections_raw as $section) {
    $sections[$section['id']] = formatSectionName($section);
}

// Fetch active academic periods for form
$ay_stmt = $db->prepare('SELECT id, academic_year, semester_name FROM academic_periods WHERE is_active = 1 ORDER BY academic_year DESC, semester_name');
$ay_stmt->execute();
$academic_years = $ay_stmt->fetchAll();

// Fetch all distinct year levels from sections for the assign year level UI
$year_stmt = $db->query('SELECT DISTINCT year_level FROM sections WHERE is_active = 1 ORDER BY year_level');
$year_levels = $year_stmt ? $year_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year_level']}{$section['section_name']}";
}

// Helper: get sections for a course (uses JSON sections field in courses table)
function get_course_sections($db, $course_id) {
    try {
        // First get the sections JSON from the course
        $stmt = $db->prepare("SELECT sections FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if (!$course || !$course['sections']) {
            return [];
        }
        
        // Parse the JSON sections array
        $section_ids = json_decode($course['sections'], true);
        if (!$section_ids || !is_array($section_ids)) {
            return [];
        }
        
        // If no sections, return empty array
        if (empty($section_ids)) {
            return [];
        }
        
        // Fetch section details for the IDs
        $placeholders = str_repeat('?,', count($section_ids) - 1) . '?';
        $sql = "SELECT id, section_name, year_level FROM sections 
                WHERE id IN ($placeholders) AND is_active = 1 
                ORDER BY year_level, section_name";
        $stmt = $db->prepare($sql);
        $stmt->execute($section_ids);
        $section_data = $stmt->fetchAll();
        
        $names = [];
        foreach ($section_data as $section) {
            $names[] = [
                'id' => $section['id'],
                'name' => formatSectionName($section)
            ];
        }
        return $names;
    } catch (Exception $e) {
        // Log error and return empty array to prevent page crash
        error_log("Error in get_course_sections: " . $e->getMessage());
        return [];
    }
}

// Define course themes with IT icons
$course_themes = [
    ['bg' => 'bg-primary', 'icon' => 'fas fa-code'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-database'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-network-wired'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-server'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-shield-alt'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-cloud'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-microchip'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-laptop-code'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-mobile-alt'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-wifi'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-keyboard'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-bug'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-terminal'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-cogs'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-rocket'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-robot'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-brain'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-chart-line'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-fire'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-lightbulb']
];

// Helper: get students for a section
function get_section_students($db, $section_id) {
    try {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.is_irregular, u.identifier, u.created_at 
                FROM users u 
                WHERE u.role = 'student' 
                AND JSON_SEARCH((SELECT students FROM sections WHERE id = ?), 'one', u.id) IS NOT NULL
                ORDER BY u.last_name, u.first_name";
        $stmt = $db->prepare($sql);
        $stmt->execute([$section_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        // Log error and return empty array to prevent page crash
        error_log("Error in get_section_students: " . $e->getMessage());
        return [];
    }
}

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $course_name = trim($_POST['course_name']);
    $course_code = trim($_POST['course_code']);
    $description = trim($_POST['description']);
    $academic_period_id = isset($_POST['academic_period_id']) ? intval($_POST['academic_period_id']) : null;
    $selected_year_level = $_POST['year_level'] ?? null;
    
    if ($course_name && $course_code && $academic_period_id && $selected_year_level) {
        $stmt = $db->prepare('INSERT INTO courses (course_name, course_code, description, teacher_id, academic_period_id, year_level) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$course_name, $course_code, $description, $teacher_id, $academic_period_id, $selected_year_level]);
        $course_id = $db->lastInsertId();
        // Assign selected year levels to this course
        // The following line was removed as per the edit hint
        // $db->prepare("UPDATE sections SET course_id = ? WHERE year = ?")->execute([$course_id, $year_level]);
        echo "<script>alert('Course created successfully!'); window.location.href='courses.php?academic_period_id=" . $academic_period_id . "';</script>";
        exit;
    } else {
        echo '<div class="alert alert-danger">All fields are required.</div>';
    }
}
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">My Courses</h1>
                <div>
                    <button class="btn" style="background: var(--main-green); color: var(--white); font-weight: 700; border: none;" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                        <i class="bi bi-plus-circle me-2"></i>Create Course
                    </button>
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
                                <?= htmlspecialchars($year['academic_year']) ?> - <?= htmlspecialchars($year['semester_name']) ?><?= !$year['is_active'] ? ' (Inactive)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn btn-primary btn-sm">Go</button></noscript>
            </form>
        </div>
    </div>

    <?php if (!$is_acad_year_active): ?>
        <div class="alert alert-warning mb-4">
            <strong>Inactive Academic Year:</strong> This academic year is inactive. You can only view and review content. All editing, adding, and uploading is disabled.
        </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <?php if (!empty($courses)): ?>
        <?php
        $total_students = array_sum(array_column($courses, 'student_count'));
        $total_modules = array_sum(array_column($courses, 'module_count'));
        $total_videos = array_sum(array_column($courses, 'video_count'));
        $total_assessments = array_sum(array_column($courses, 'assessment_count'));
        
        ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo count($courses); ?></h4>
                                <p class="mb-0">Total Courses</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-book fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white clickable-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#studentsAttemptsModal">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $total_students; ?></h4>
                                <p class="mb-0">Total Students <i class="bi bi-arrow-right-circle ms-1"></i></p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people fs-1"></i>
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
                                <h4 class="mb-0"><?php echo $total_modules; ?></h4>
                                <p class="mb-0">Total Modules</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-collection fs-1"></i>
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
                                <h4 class="mb-0"><?php echo $total_videos; ?></h4>
                                <p class="mb-0">Total Videos</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-play-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Courses Organized by Year Level -->
    <?php if (empty($courses)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-book fs-1 text-muted mb-3"></i>
                        <h5>No Courses Found</h5>
                        <p class="text-muted">No courses found for the selected academic year. Create your first course to get started.</p>
                        <button class="btn" style="background: var(--main-green); color: var(--white); font-weight: 700; border: none;" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                            <i class="bi bi-plus-circle me-2"></i>Create Your First Course
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php
        // Group courses by year level
        $courses_by_year = [];
        foreach ($courses as $course) {
            $year_level = $course['year_level'] ?? 'Unknown';
            if (!isset($courses_by_year[$year_level])) {
                $courses_by_year[$year_level] = [];
            }
            $courses_by_year[$year_level][] = $course;
        }
        
        // Sort year levels
        ksort($courses_by_year);
        ?>
        
        <?php foreach ($courses_by_year as $year_level => $year_courses): ?>
            <div class="mb-5">
                <!-- Year Level Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, var(--main-green), #2d5a27);">
                            <div class="card-body py-4">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="bi bi-mortarboard-fill text-white" style="font-size: 2.5rem;"></i>
                                        </div>
                                        <div>
                                            <h2 class="text-white mb-1 fw-bold"><?php echo htmlspecialchars($year_level); ?> Year Level</h2>
                                            <p class="text-white-50 mb-0">
                                                <?php echo count($year_courses); ?> Course<?php echo count($year_courses) !== 1 ? 's' : ''; ?> 
                                                • 
                                                <?php 
                                                $year_total_students = array_sum(array_column($year_courses, 'student_count'));
                                                echo $year_total_students; 
                                                ?> Total Students
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-white-50 small">
                                            <?php 
                                            $year_total_modules = array_sum(array_column($year_courses, 'module_count'));
                                            $year_total_videos = array_sum(array_column($year_courses, 'video_count'));
                                            $year_total_assessments = array_sum(array_column($year_courses, 'assessment_count'));
                                            ?>
                                            <div class="d-flex gap-4">
                                                <div>
                                                    <i class="bi bi-collection me-1"></i>
                                                    <?php echo $year_total_modules; ?> Modules
                                                </div>
                                                <div>
                                                    <i class="bi bi-play-circle me-1"></i>
                                                    <?php echo $year_total_videos; ?> Videos
                                                </div>
                                                <div>
                                                    <i class="bi bi-clipboard-check me-1"></i>
                                                    <?php echo $year_total_assessments; ?> Assessments
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Courses Grid for this Year Level -->
                <div class="row">
                    <?php foreach ($year_courses as $course): ?>
                        <?php
                            $theme = $course_themes[$course['id'] % count($course_themes)];
                        ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card course-card h-100">
                                <div class="card-img-top course-image d-flex align-items-center justify-content-center <?php echo $theme['bg']; ?>" style="height: 200px; position: relative; overflow: hidden;">
                                    <i class="<?php echo $theme['icon']; ?>" style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0.4; pointer-events: none; font-size: 10rem; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6);"></i>
                                    <h2 class="course-code-text">
                                        <?php echo htmlspecialchars($course['course_code']); ?>
                                    </h2>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?>
                                    </p>
                                    
                                    <!-- Academic Year and Semester Info -->
                                    <div class="mb-2">
                                        <?php if (isset($course['academic_year'])): ?>
                                            <span class="badge" style="background: var(--highlight-yellow); color: var(--main-green); font-weight: 700;"><?php echo htmlspecialchars($course['academic_year']); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($course['semester_name'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($course['semester_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Sections as Subjects -->
                                    <div class="mb-2">
                                        <strong>Sections:</strong>
                                        <?php
                                        // Minimalist section badge grid with 'View' text button and smaller font
                                        $course_sections = get_course_sections($db, $course['id']);
                                        if ($course_sections):
                                            $max_sections = 9;
                                            $display_sections = array_slice($course_sections, 0, $max_sections);
                                        ?>
                                            <div class="row g-2 mb-2">
                                                <?php foreach ($display_sections as $i => $sec):
                                                    $students = get_section_students($db, $sec['id']);
                                                    $student_count = count($students);
                                                ?>
                                                    <div class="col-12 col-sm-6 col-md-4">
                                                        <span class="d-flex align-items-center justify-content-between w-100 mb-1 px-2 py-1" style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 1.2em; font-size: 0.92rem; min-height: 2em;">
                                                            <span style="font-weight: 500; color: var(--main-green); letter-spacing: 0.5px;"><?= htmlspecialchars($sec['name']) ?></span>
                                                            <span class="badge bg-light text-dark ms-2" style="font-size:0.82em; border-radius:1em; min-width:1.6em; border:1px solid #e5e7eb; color:var(--main-green);"> <?= $student_count ?> </span>
                                                            <button type="button" class="btn btn-link p-0 ms-2" style="color:var(--main-green); border:none; background:none; font-size:0.95em; line-height:1; text-decoration:underline; font-weight:500;" title="View students" onclick="viewStudents(<?= $course['id'] ?>, <?= $sec['id'] ?>, '<?= htmlspecialchars($sec['name']) ?>', '<?= htmlspecialchars($course['course_name']) ?>')">
                                                                View
                                                            </button>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($course_sections) > $max_sections): ?>
                                                <div><small class="text-muted">Showing first 9 sections. <a href="#" onclick="alert('Show all feature coming soon!')">Show all</a></small></div>
                                            <?php endif; ?>
                                        <?php
                                        else:
                                            echo '<span class="text-muted">None</span>';
                                        endif;
                                        ?>
                                    </div>
                                    <!-- Course Statistics -->
                                    <div class="row text-center mb-3">
                                        <div class="col-3">
                                            <div class="border-end">
                                                <h6 class="mb-0 text-primary"><?php echo $course['student_count'] ?? 0; ?></h6>
                                                <small class="text-muted">Students</small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="border-end">
                                                <h6 class="mb-0 text-success"><?php echo $course['module_count'] ?? 0; ?></h6>
                                                <small class="text-muted">Modules</small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="border-end">
                                                <h6 class="mb-0 text-info"><?php echo $course['video_count'] ?? 0; ?></h6>
                                                <small class="text-muted">Videos</small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <h6 class="mb-0 text-warning"><?php echo $course['assessment_count'] ?? 0; ?></h6>
                                            <small class="text-muted">Assessments</small>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <?php if (isset($course['created_at'])): ?>
                                            <small class="text-muted">Created <?php echo date('M j, Y', strtotime($course['created_at'])); ?></small>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            Created by: <?php echo htmlspecialchars($course['creator_name'] . ' (' . $course['creator_username'] . ')'); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-grid gap-2">
                                        <a href="course.php?id=<?php echo $course['id']; ?>" class="btn" style="background: var(--main-green); color: var(--white); font-weight: 700; border: none;">
                                            <i class="bi bi-gear me-1"></i>Manage Course
                                        </a>
                                        <button class="btn btn-outline-primary" onclick="manageCourseBadges(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>')">
                                            <i class="bi bi-award me-1"></i>Manage Badges
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Students Attempts Modal -->
<div class="modal fade" id="studentsAttemptsModal" tabindex="-1" aria-labelledby="studentsAttemptsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentsAttemptsModalLabel">
                    <i class="bi bi-people me-2"></i>
                    Students Assessment Attempts
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="studentsAttemptsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading students and their attempts...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Single Dynamic Students Modal -->
<div class="modal fade" id="studentsModal" tabindex="-1" aria-labelledby="studentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentsModalLabel">
                    <span id="modalTitle">Students</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="studentsModalContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading students...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Course Modal -->
<div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createCourseModalLabel">Create New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="course_name" class="form-label">Course Name</label>
                            <input type="text" class="form-control" id="course_name" name="course_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="course_code" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="course_code" name="course_code" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="academic_period_id" class="form-label">Academic Period</label>
                            <select class="form-select" id="academic_period_id" name="academic_period_id" required>
                                <option value="">Select Academic Year</option>
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?= $year['id'] ?>" <?= $selected_year_id == $year['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['academic_year']) ?> - <?= htmlspecialchars($year['semester_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                    <div class="mb-3">
                        <label for="year_level" class="form-label">Year Level</label>
                        <select class="form-select" id="year_level" name="year_level" required>
                            <option value="">Select Year Level</option>
                            <?php foreach ($year_levels as $year): ?>
                                <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?> Year</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_course" class="btn" style="background: var(--main-green); color: var(--white); font-weight: 700; border: none;">Create Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Course Badge Management Modal -->
<div class="modal fade" id="courseBadgeModal" tabindex="-1" aria-labelledby="courseBadgeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="courseBadgeModalLabel">
                    <i class="bi bi-award me-2"></i>Manage Course Badges
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="courseBadgeContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading badges...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <div class="d-flex justify-content-between w-100">
                    <div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Students will automatically earn these badges when they complete the course
                        </small>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-success" onclick="saveCourseBadges()">
                            <i class="bi bi-check-circle me-1"></i>Save Badge Assignments
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to load students with their assessment attempts
function loadStudentsWithAttempts() {
    const modalContent = document.getElementById('studentsAttemptsContent');
    
    // Show loading state
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading students and their attempts...</p>
        </div>
    `;

    // Fetch students with attempts data
    fetch('get_students_attempts.php?teacher_id=<?php echo $teacher_id; ?>&academic_period_id=<?php echo $selected_year_id; ?>')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.students.length === 0) {
                modalContent.innerHTML = '<p class="text-center text-muted">No students found for the selected academic period.</p>';
            } else {
                let html = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-people-fill text-primary me-2"></i>
                                <span class="fw-bold">${data.students.length} Total Students</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-graph-up text-success me-2"></i>
                                <span class="fw-bold">${data.total_attempts} Total Attempts</span>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Student Name</th>
                                    <th>Username</th>
                                    <th>Course</th>
                                    <th>Assessment</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Attempt Date</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.students.forEach(student => {
                    if (student.attempts && student.attempts.length > 0) {
                        student.attempts.forEach(attempt => {
                            html += `
                                <tr>
                                    <td>
                                        <strong>${student.last_name}, ${student.first_name}</strong>
                                        ${student.is_irregular ? '<span class="badge bg-danger ms-2">Irregular</span>' : '<span class="badge bg-success ms-2">Regular</span>'}
                                    </td>
                                    <td>@${student.username}</td>
                                    <td>${attempt.course_name}</td>
                                    <td>${attempt.assessment_title}</td>
                                    <td>
                                        <span class="fw-bold ${attempt.has_passed ? 'text-success' : 'text-danger'}">
                                            ${attempt.score}%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-${attempt.has_passed ? 'success' : 'danger'}">
                                            ${attempt.has_passed ? 'Passed' : 'Failed'}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            ${new Date(attempt.attempted_at).toLocaleDateString()} ${new Date(attempt.attempted_at).toLocaleTimeString()}
                                        </small>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        // Show student even if no attempts
                        html += `
                            <tr>
                                <td>
                                    <strong>${student.last_name}, ${student.first_name}</strong>
                                    ${student.is_irregular ? '<span class="badge bg-danger ms-2">Irregular</span>' : '<span class="badge bg-success ms-2">Regular</span>'}
                                </td>
                                <td>@${student.username}</td>
                                <td colspan="5" class="text-center text-muted">No attempts yet</td>
                            </tr>
                        `;
                    }
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                modalContent.innerHTML = html;
            }
        })
        .catch(error => {
            modalContent.innerHTML = '<div class="alert alert-danger">Error loading students and attempts. Please try again.</div>';
            console.error('Error fetching students with attempts:', error);
        });
}

// Add event listener for the modal
document.addEventListener('DOMContentLoaded', function() {
    const studentsAttemptsModal = document.getElementById('studentsAttemptsModal');
    if (studentsAttemptsModal) {
        studentsAttemptsModal.addEventListener('show.bs.modal', function() {
            loadStudentsWithAttempts();
        });
    }
});

// Function to load and display students for a specific section
function viewStudents(courseId, sectionId, sectionName, courseName) {
    const modalTitle = document.getElementById('modalTitle');
    const modalContent = document.getElementById('studentsModalContent');
    
    // Show loading state
    modalTitle.textContent = `Students in ${sectionName} (Course: ${courseName})`;
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading students...</p>
        </div>
    `;

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('studentsModal'));
    modal.show();

    // Fetch student data
    fetch(`get_section_students.php?course_id=${courseId}&section_id=${sectionId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(students => {
            if (students.length === 0) {
                modalContent.innerHTML = '<p class="text-center text-muted">No students assigned to this section.</p>';
            } else {
                const ul = document.createElement('ul');
                ul.classList.add('list-group');
                students.forEach(stu => {
                    const li = document.createElement('li');
                    li.classList.add('list-group-item');
                    li.innerHTML = `
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                            <div>
                                <strong>${stu.last_name}, ${stu.first_name}</strong> 
                                <span class="text-muted">(${stu.username})</span> 
                                ${stu.is_irregular ? '<span class="badge bg-danger ms-2">Irregular</span>' : '<span class="badge bg-success ms-2">Regular</span>'}
                            </div>
                            <div class="small text-muted">${stu.email}</div>
                        </div>
                        <div class="mt-1 small text-secondary">
                            Student ID: <span class="fw-semibold">${stu.identifier || '-'}</span> | 
                            Registered: <span>${stu.created_at ? new Date(stu.created_at).toLocaleDateString() : '-'}</span>
                        </div>
                    `;
                    ul.appendChild(li);
                });
                modalContent.innerHTML = '';
                modalContent.appendChild(ul);
            }
        })
        .catch(error => {
            modalContent.innerHTML = '<div class="alert alert-danger">Error loading students. Please try again.</div>';
            console.error('Error fetching students:', error);
        });
}

// Global variables for badge management
let currentCourseId = null;
let currentCourseName = '';

// Function to manage course badges
function manageCourseBadges(courseId, courseName) {
    currentCourseId = courseId;
    currentCourseName = courseName;
    
    // Update modal title
    document.getElementById('courseBadgeModalLabel').innerHTML = 
        `<i class="bi bi-award me-2"></i>Manage Badges for "${courseName}"`;
    
    // Show loading state
    const modalContent = document.getElementById('courseBadgeContent');
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading badges...</p>
        </div>
    `;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('courseBadgeModal'));
    modal.show();
    
    // Load badges
    loadCourseBadges(courseId);
}

// Function to load course badges
function loadCourseBadges(courseId) {
    fetch(`get_course_badges.php?course_id=${courseId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayCourseBadges(data);
            } else {
                throw new Error(data.message || 'Failed to load badges');
            }
        })
        .catch(error => {
            document.getElementById('courseBadgeContent').innerHTML = 
                '<div class="alert alert-danger">Error loading badges: ' + error.message + '</div>';
            console.error('Error loading course badges:', error);
        });
}

// Function to display course badges
function displayCourseBadges(data) {
    const modalContent = document.getElementById('courseBadgeContent');
    
    let html = `
        <div class="container-fluid p-4">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Course Badge Management</h4>
                            <p class="text-muted mb-0">Assign badges that students will earn upon course completion</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary fs-6">${data.available_badges.length} Available</span>
                            <span class="badge bg-success fs-6 ms-2">${data.assigned_badges.length} Selected</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 bg-light">
                        <div class="card-body py-3">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="badgeSearch" placeholder="Search badges by name or description..." onkeyup="filterBadges()">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex gap-2">
                                        <select class="form-select" id="categoryFilter" onchange="filterBadges()">
                                            <option value="">All Categories</option>
                                            <option value="achievement">Achievement</option>
                                            <option value="milestone">Milestone</option>
                                            <option value="skill">Skill</option>
                                            <option value="participation">Participation</option>
                                            <option value="special">Special</option>
                                        </select>
                                        <select class="form-select" id="contextFilter" onchange="filterBadges()">
                                            <option value="">All Contexts</option>
                                            <option value="course">Course</option>
                                            <option value="global">Global</option>
                                            <option value="requirement">Requirement</option>
                                        </select>
                                        <div class="btn-group">
                                            <button class="btn btn-outline-primary btn-sm" onclick="quickFilter('milestone')" title="Show milestone badges">
                                                <i class="bi bi-flag"></i>
                                            </button>
                                            <button class="btn btn-outline-success btn-sm" onclick="quickFilter('skill')" title="Show skill badges">
                                                <i class="bi bi-lightning"></i>
                                            </button>
                                            <button class="btn btn-outline-info btn-sm" onclick="quickFilter('achievement')" title="Show achievement badges">
                                                <i class="bi bi-trophy"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="clearFilters()" title="Clear all filters">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-award me-2"></i>Available Badges</h6>
                        </div>
                        <div class="card-body p-0">
                            <div style="max-height: 500px; overflow-y: auto;">
                                <div id="availableBadges" class="p-3">
    `;
    
    // Available badges
    data.available_badges.forEach(badge => {
        const isSelected = data.assigned_badges.some(assigned => assigned.badge_id === badge.id);
        const typeColor = {
            'course_completion': 'primary',
            'high_score': 'warning',
            'participation': 'info',
            'streak': 'success',
            'special': 'danger'
        }[badge.badge_type] || 'secondary';
        
        html += `
            <div class="badge-item mb-3 p-3 border rounded-3 ${isSelected ? 'bg-success bg-opacity-10 border-success' : 'bg-white border-light'} shadow-sm" 
                 data-badge-id="${badge.id}" data-badge-type="${badge.badge_type}" 
                 data-badge-category="${badge.category}" data-badge-context="${badge.context}">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="badge_${badge.id}" 
                           ${isSelected ? 'checked' : ''} onchange="toggleBadge(${badge.id}, this.checked)">
                    <label class="form-check-label w-100" for="badge_${badge.id}">
                        <div class="d-flex align-items-center">
                            <div class="badge-icon-display me-3 flex-shrink-0" 
                                 style="width:50px;height:50px;background:linear-gradient(135deg, #7DCB80 0%, #2E5E4E 100%);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <i class="${badge.badge_icon}" style="font-size:20px;color:white;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1 fw-bold">${badge.badge_name}</h6>
                                        <p class="text-muted small mb-2">${badge.badge_description}</p>
                                    </div>
                                    <div class="text-end">
                                        <div class="d-flex flex-wrap gap-1 justify-content-end mb-1">
                                            <span class="badge bg-${typeColor}">${badge.badge_type.replace('_', ' ')}</span>
                                            <span class="badge bg-info">${badge.category}</span>
                                            <span class="badge bg-secondary">${badge.context}</span>
                                            ${badge.is_restricted ? '<span class="badge bg-warning">Restricted</span>' : ''}
                                        </div>
                                        <div class="small text-success fw-bold">${badge.points_value} pts</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        `;
    });
    
    html += `
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Selected Badges Sidebar -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-check-circle me-2"></i>Selected Badges</h6>
                        </div>
                        <div class="card-body p-0">
                            <div style="max-height: 500px; overflow-y: auto; padding: 1rem;">
                                <div id="selectedBadges" class="d-flex flex-wrap gap-2">
    `;
    
    // Selected badges
    if (data.assigned_badges.length > 0) {
        data.assigned_badges.forEach(badge => {
            const typeColor = {
                'course_completion': 'primary',
                'high_score': 'warning',
                'participation': 'info',
                'streak': 'success',
                'special': 'danger'
            }[badge.badge_type] || 'secondary';
            
            html += `
                <div class="selected-badge-item d-flex align-items-center p-2 border rounded-3 bg-success bg-opacity-10 border-success shadow-sm" data-badge-id="${badge.badge_id}" style="min-width: 200px; max-width: 250px;">
                    <div class="badge-icon-display me-2 flex-shrink-0" 
                         style="width:35px;height:35px;background:linear-gradient(135deg, #7DCB80 0%, #2E5E4E 100%);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <i class="${badge.badge_icon}" style="font-size:16px;color:white;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold small">${badge.badge_name}</h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-${typeColor} small">${badge.badge_type.replace('_', ' ')}</span>
                            <span class="text-success fw-bold small">${badge.points_value}pts</span>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger flex-shrink-0 ms-2" onclick="removeBadge(${badge.badge_id})" title="Remove badge" style="width: 24px; height: 24px; padding: 0;">
                        <i class="bi bi-x" style="font-size: 12px;"></i>
                    </button>
                </div>
            `;
        });
    } else {
        html += `
            <div class="text-center py-4">
                <i class="bi bi-award text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3 mb-2">No badges selected</p>
                <small class="text-muted">Select badges from the left panel to assign them to this course</small>
            </div>
        `;
    }
    
    html += `
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modalContent.innerHTML = html;
    
    // Initialize selected badges display for pre-assigned badges
    setTimeout(() => {
        // Clear any existing selected badges first
        const selectedBadgesContainer = document.getElementById('selectedBadges');
        selectedBadgesContainer.innerHTML = '';
        
        // Add pre-assigned badges to selected panel
        data.assigned_badges.forEach(badge => {
            addToSelectedBadges(badge.id);
        });
        
        // If no badges are assigned, show empty state
        if (data.assigned_badges.length === 0) {
            selectedBadgesContainer.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-award text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3 mb-2">No badges selected</p>
                    <small class="text-muted">Select badges from the left panel to assign them to this course</small>
                </div>
            `;
        }
    }, 100);
}

// Function to toggle badge selection
function toggleBadge(badgeId, isSelected) {
    const badgeItem = document.querySelector(`[data-badge-id="${badgeId}"]`);
    const checkbox = document.getElementById(`badge_${badgeId}`);
    
    if (isSelected) {
        // Update visual state for selected badge
        badgeItem.classList.remove('bg-white', 'border-light');
        badgeItem.classList.add('bg-success', 'bg-opacity-10', 'border-success');
        addToSelectedBadges(badgeId);
    } else {
        // Update visual state for deselected badge
        badgeItem.classList.remove('bg-success', 'bg-opacity-10', 'border-success');
        badgeItem.classList.add('bg-white', 'border-light');
        removeFromSelectedBadges(badgeId);
    }
}

// Function to add badge to selected list
function addToSelectedBadges(badgeId) {
    // Check if badge is already in selected list
    const existingBadge = document.querySelector(`#selectedBadges .selected-badge-item[data-badge-id="${badgeId}"]`);
    if (existingBadge) return;
    
    // Find the badge data
    const badgeItem = document.querySelector(`[data-badge-id="${badgeId}"]`);
    if (!badgeItem) return;
    
    const badgeName = badgeItem.querySelector('h6').textContent;
    const badgeDesc = badgeItem.querySelector('p').textContent;
    const badgeIcon = badgeItem.querySelector('i').className;
    const badgeType = badgeItem.getAttribute('data-badge-type');
    const pointsValue = badgeItem.querySelector('.text-success').textContent;
    
    const typeColor = {
        'course_completion': 'primary',
        'high_score': 'warning',
        'participation': 'info',
        'streak': 'success',
        'special': 'danger'
    }[badgeType] || 'secondary';
    
    // Add to selected badges display
    const selectedBadgesContainer = document.getElementById('selectedBadges');
    const emptyState = selectedBadgesContainer.querySelector('.text-center');
    if (emptyState) {
        emptyState.remove();
    }
    
    const selectedBadgeHtml = `
        <div class="selected-badge-item d-flex align-items-center p-2 border rounded-3 bg-success bg-opacity-10 border-success shadow-sm" data-badge-id="${badgeId}" style="min-width: 200px; max-width: 250px;">
            <div class="badge-icon-display me-2 flex-shrink-0" 
                 style="width:35px;height:35px;background:linear-gradient(135deg, #7DCB80 0%, #2E5E4E 100%);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <i class="${badgeIcon}" style="font-size:16px;color:white;"></i>
            </div>
            <div class="flex-grow-1">
                <h6 class="mb-0 fw-bold small">${badgeName}</h6>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="badge bg-${typeColor} small">${badgeType.replace('_', ' ')}</span>
                    <span class="text-success fw-bold small">${pointsValue}</span>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-danger flex-shrink-0 ms-2" onclick="removeBadge(${badgeId})" title="Remove badge" style="width: 24px; height: 24px; padding: 0;">
                <i class="bi bi-x" style="font-size: 12px;"></i>
            </button>
        </div>
    `;
    
    selectedBadgesContainer.insertAdjacentHTML('beforeend', selectedBadgeHtml);
}

// Function to remove badge from selected list
function removeFromSelectedBadges(badgeId) {
    const selectedBadgeItem = document.querySelector(`#selectedBadges .selected-badge-item[data-badge-id="${badgeId}"]`);
    if (selectedBadgeItem) {
        selectedBadgeItem.remove();
    }
    
    // Check if no badges are selected and show empty state
    const selectedBadgesContainer = document.getElementById('selectedBadges');
    const remainingBadges = selectedBadgesContainer.querySelectorAll('.selected-badge-item');
    if (remainingBadges.length === 0) {
        selectedBadgesContainer.innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-award text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3 mb-2">No badges selected</p>
                <small class="text-muted">Select badges from the left panel to assign them to this course</small>
            </div>
        `;
    }
}

// Function to remove badge
function removeBadge(badgeId) {
    const checkbox = document.getElementById(`badge_${badgeId}`);
    if (checkbox) {
        checkbox.checked = false;
        toggleBadge(badgeId, false);
    }
    
    // Remove from selected badges display
    removeFromSelectedBadges(badgeId);
}

// Function to filter badges
function filterBadges() {
    const searchTerm = document.getElementById('badgeSearch').value.toLowerCase().trim();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const contextFilter = document.getElementById('contextFilter').value;
    const badgeItems = document.querySelectorAll('.badge-item');
    
    let visibleCount = 0;
    let hasActiveFilters = false;
    
    // Check if any filters are active
    if (searchTerm !== '' || categoryFilter !== '' || contextFilter !== '') {
        hasActiveFilters = true;
    }
    
    badgeItems.forEach(item => {
        const badgeName = item.querySelector('h6').textContent.toLowerCase();
        const badgeDesc = item.querySelector('p').textContent.toLowerCase();
        const badgeCategory = item.getAttribute('data-badge-category');
        const badgeContext = item.getAttribute('data-badge-context');
        
        // Apply strict filtering - all conditions must match
        let matchesSearch = true;
        let matchesCategory = true;
        let matchesContext = true;
        
        // Search filter (name or description)
        if (searchTerm !== '') {
            matchesSearch = badgeName.includes(searchTerm) || badgeDesc.includes(searchTerm);
        }
        
        // Category filter (exact match)
        if (categoryFilter !== '') {
            matchesCategory = badgeCategory === categoryFilter;
        }
        
        // Context filter (exact match)
        if (contextFilter !== '') {
            matchesContext = badgeContext === contextFilter;
        }
        
        // Only show if ALL active filters match
        if (matchesSearch && matchesCategory && matchesContext) {
            item.style.display = 'block';
            item.style.opacity = '1';
            item.style.transform = 'scale(1)';
            visibleCount++;
        } else {
            item.style.display = 'none';
            item.style.opacity = '0';
            item.style.transform = 'scale(0.95)';
        }
    });
    
    // Update the header with filtered count
    const headerElement = document.querySelector('#availableBadges').closest('.card').querySelector('.card-header h6');
    if (headerElement) {
        const originalText = headerElement.textContent.replace(/\(\d+\)/, '');
        if (hasActiveFilters) {
            headerElement.textContent = `${originalText} (${visibleCount} of ${badgeItems.length})`;
        } else {
            headerElement.textContent = originalText;
        }
    }
    
    // Show "No badges found" message if no results
    const availableBadgesContainer = document.getElementById('availableBadges');
    const existingNoResults = availableBadgesContainer.querySelector('.no-results-message');
    
    if (visibleCount === 0 && hasActiveFilters) {
        if (!existingNoResults) {
            const noResultsHtml = `
                <div class="no-results-message text-center py-4">
                    <i class="bi bi-search fs-1 text-muted"></i>
                    <h6 class="text-muted mt-2">No badges found</h6>
                    <p class="text-muted small">Try adjusting your search or filters</p>
                    <button class="btn btn-outline-primary btn-sm mt-2" onclick="clearFilters()">
                        <i class="bi bi-x-circle me-1"></i>Clear Filters
                    </button>
                </div>
            `;
            availableBadgesContainer.insertAdjacentHTML('beforeend', noResultsHtml);
        }
    } else {
        if (existingNoResults) {
            existingNoResults.remove();
        }
    }
    
    // Update filter status
    updateFilterStatus();
}

// Function to update filter status display
function updateFilterStatus() {
    const searchTerm = document.getElementById('badgeSearch').value;
    const categoryFilter = document.getElementById('categoryFilter').value;
    const contextFilter = document.getElementById('contextFilter').value;
    
    const activeFilters = [];
    
    if (searchTerm) {
        activeFilters.push(`Search: "${searchTerm}"`);
    }
    if (categoryFilter) {
        activeFilters.push(`Category: ${categoryFilter.charAt(0).toUpperCase() + categoryFilter.slice(1)}`);
    }
    if (contextFilter) {
        activeFilters.push(`Context: ${contextFilter.charAt(0).toUpperCase() + contextFilter.slice(1)}`);
    }
    
    // You can add a filter status display here if needed
    console.log('Active filters:', activeFilters.join(' • '));
}

// Function for quick filtering by category
function quickFilter(category) {
    document.getElementById('badgeSearch').value = '';
    document.getElementById('categoryFilter').value = category;
    document.getElementById('contextFilter').value = '';
    filterBadges();
}

// Function to clear all filters
function clearFilters() {
    document.getElementById('badgeSearch').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('contextFilter').value = '';
    filterBadges();
}

// Function to save course badges
function saveCourseBadges() {
    const selectedBadges = [];
    const checkboxes = document.querySelectorAll('#availableBadges input[type="checkbox"]:checked');
    
    checkboxes.forEach(checkbox => {
        const badgeId = checkbox.id.replace('badge_', '');
        selectedBadges.push(badgeId);
    });
    
    // Send data to server
    fetch('save_course_badges.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            course_id: currentCourseId,
            badge_ids: selectedBadges
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Badges saved successfully!');
            bootstrap.Modal.getInstance(document.getElementById('courseBadgeModal')).hide();
        } else {
            alert('Error saving badges: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error saving badges. Please try again.');
        console.error('Error saving course badges:', error);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>     