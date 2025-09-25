<?php
$page_title = 'Student Badges';
require_once '../includes/header.php';
requireRole('teacher');

// Get teacher ID
$teacher_id = $_SESSION['user_id'];

// Check if badges table exists
$stmt = $db->prepare("SHOW TABLES LIKE 'badges'");
$stmt->execute();
$badges_table_exists = $stmt->fetch();

if (!$badges_table_exists) {
    echo '<div class="container-fluid py-4">
        <div class="alert alert-warning">
            <h4>Badges System Not Available</h4>
            <p>The badges system is not yet set up in your database. Please contact your administrator to set up the badges functionality.</p>
            <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
        </div>
    </div>';
    require_once '../includes/footer.php';
    exit;
}

// Get filter parameters
$course_filter = (int)($_GET['course_id'] ?? 0);
$student_filter = (int)($_GET['student_id'] ?? 0);
$badge_filter = (int)($_GET['badge_id'] ?? 0);

// Get view type with persistence
$view_type = $_GET['view'] ?? $_SESSION['student_badges_view_type'] ?? 'detailed';

// Save view type to session for persistence
if (isset($_GET['view'])) {
    $_SESSION['student_badges_view_type'] = $_GET['view'];
}

// Build WHERE clause for teacher's courses
$where_conditions = ["c.teacher_id = ?"];
$params = [$_SESSION['user_id']];

if ($course_filter > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get teacher's courses for filter
$stmt = $db->prepare('SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name');
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

// Get all badges
$stmt = $db->prepare('SELECT id, badge_name FROM badges ORDER BY badge_name');
$stmt->execute();
$all_badges = $stmt->fetchAll();

// Get students enrolled in teacher's courses
$stmt = $db->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
    FROM users u
    JOIN course_enrollments e ON u.id = e.student_id
    JOIN courses c ON e.course_id = c.id
    $where_clause
    ORDER BY u.first_name, u.last_name
");
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get student badge data with real-time information
try {
    $student_badge_query = "
        SELECT DISTINCT
            u.id as student_id,
            u.first_name,
            u.last_name,
            u.email,
            u.profile_picture,
            b.id as badge_id,
            b.badge_name,
            b.badge_description,
            b.badge_icon,
            b.badge_type,
            b.points_value,
            b.awarded_to,
            JSON_UNQUOTE(JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', u.id, NULL, '$[*].student_id'), '].awarded_at'))) as awarded_at,
            GROUP_CONCAT(DISTINCT c.course_name ORDER BY c.course_name SEPARATOR ', ') as course_names,
            GROUP_CONCAT(DISTINCT c.course_code ORDER BY c.course_code SEPARATOR ', ') as course_codes
        FROM courses c
        LEFT JOIN sections s ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        LEFT JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL AND u.role = 'student'
        CROSS JOIN badges b
        WHERE c.teacher_id = ?
        AND c.is_archived = 0
        AND JSON_SEARCH(b.awarded_to, 'one', u.id, NULL, '$[*].student_id') IS NOT NULL
        AND b.is_active = 1
        GROUP BY u.id, b.id
    ";

    // Build parameters array
    $badge_params = [$teacher_id];

    if ($student_filter > 0) {
        $student_badge_query .= " AND u.id = ?";
        $badge_params[] = $student_filter;
    }

    if ($badge_filter > 0) {
        $student_badge_query .= " AND b.id = ?";
        $badge_params[] = $badge_filter;
    }

    $student_badge_query .= " ORDER BY u.first_name, u.last_name, b.badge_name";

    $stmt = $db->prepare($student_badge_query);
    $stmt->execute($badge_params);
    $student_badges = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Student badges query error: " . $e->getMessage());
    $student_badges = [];
}

// Group badges by student with enhanced data
$badges_by_student = [];
foreach ($student_badges as $badge) {
    $student_id = $badge['student_id'];
    if (!isset($badges_by_student[$student_id])) {
        $badges_by_student[$student_id] = [
            'student' => [
                'id' => $badge['student_id'],
                'first_name' => $badge['first_name'],
                'last_name' => $badge['last_name'],
                'email' => $badge['email'],
                'profile_picture' => $badge['profile_picture']
            ],
            'badges' => [],
            'courses' => []
        ];
    }
    $badges_by_student[$student_id]['badges'][] = $badge;
    
    // Track courses for this student (using the new grouped course fields)
    if (!isset($badges_by_student[$student_id]['courses'])) {
        $badges_by_student[$student_id]['courses'] = [
            'course_names' => $badge['course_names'],
            'course_codes' => $badge['course_codes']
        ];
    }
}

// Get badge statistics - comprehensive and real-time accurate
try {
    // Debug: Check if session user_id is set
    if (!isset($_SESSION['user_id'])) {
        error_log("ERROR: Session user_id not set!");
        throw new Exception("User not logged in");
    }
    
    error_log("DEBUG: Using teacher_id: " . $teacher_id);
    
    // 1. Count total students from sections assigned to teacher's courses (REAL-TIME) - Same logic as courses.php
    $total_students_query = "
        SELECT COUNT(DISTINCT u.id) as total_students
        FROM courses c
        LEFT JOIN sections s ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        LEFT JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL AND u.role = 'student'
        WHERE c.teacher_id = ?
        AND c.is_archived = 0
    ";
    
    $stmt = $db->prepare($total_students_query);
    $stmt->execute([$teacher_id]);
    $total_students = $stmt->fetch()['total_students'];
    error_log("DEBUG: Total students (real-time): " . $total_students);
    
    // 2. Count students who have actually earned badges (REAL-TIME) - Same logic as courses.php
    $students_with_badges_query = "
        SELECT COUNT(DISTINCT u.id) as students_with_badges
        FROM courses c
        LEFT JOIN sections s ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        LEFT JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL AND u.role = 'student'
        JOIN badges b ON b.is_active = 1
        WHERE c.teacher_id = ?
        AND c.is_archived = 0
        AND JSON_SEARCH(b.awarded_to, 'one', u.id, NULL, '$[*].student_id') IS NOT NULL
    ";
    
    $stmt = $db->prepare($students_with_badges_query);
    $stmt->execute([$teacher_id]);
    $students_with_badges = $stmt->fetch()['students_with_badges'];
    error_log("DEBUG: Students with badges (real-time): " . $students_with_badges);
    
    // 3. Count total individual badge awards given to teacher's students (REAL-TIME) - Accurate count
    $total_badges_awarded_query = "
        SELECT COUNT(*) as total_badges_awarded
        FROM badges b
        WHERE b.is_active = 1
        AND JSON_LENGTH(b.awarded_to) > 0
        AND EXISTS (
            SELECT 1 FROM courses c
            LEFT JOIN sections s ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
            LEFT JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL AND u.role = 'student'
            WHERE c.teacher_id = ?
            AND c.is_archived = 0
            AND JSON_SEARCH(b.awarded_to, 'one', u.id, NULL, '$[*].student_id') IS NOT NULL
        )
    ";
    
    $stmt = $db->prepare($total_badges_awarded_query);
    $stmt->execute([$teacher_id]);
    $total_badges_awarded = $stmt->fetch()['total_badges_awarded'];
    error_log("DEBUG: Total badges awarded (real-time): " . $total_badges_awarded);
    
    // 4. Calculate average badges per student who has earned badges
    $avg_badges_per_student = $students_with_badges > 0 ? ($total_badges_awarded / $students_with_badges) : 0;
    
    // 5. Get additional real-time statistics
    $additional_stats_query = "
        SELECT 
            COUNT(DISTINCT c.id) as total_courses,
            COUNT(DISTINCT b.id) as total_available_badges,
            COUNT(DISTINCT CASE WHEN JSON_LENGTH(b.awarded_to) > 0 THEN b.id END) as badges_with_awards
        FROM courses c
        LEFT JOIN course_enrollments e ON c.id = e.course_id
        LEFT JOIN users u ON e.student_id = u.id
        LEFT JOIN badges b ON b.is_active = 1
        WHERE c.teacher_id = ?
    ";
    
    $stmt = $db->prepare($additional_stats_query);
    $stmt->execute([$teacher_id]);
    $additional_stats = $stmt->fetch();
    
    // Compile comprehensive statistics
    $stats = [
        'total_students' => (int)$total_students,
        'students_with_badges' => (int)$students_with_badges,
        'total_badges_awarded' => (int)$total_badges_awarded,
        'avg_badges_per_student' => round($avg_badges_per_student, 1),
        'total_courses' => (int)$additional_stats['total_courses'],
        'total_available_badges' => (int)$additional_stats['total_available_badges'],
        'badges_with_awards' => (int)$additional_stats['badges_with_awards'],
        'last_updated' => date('Y-m-d H:i:s'),
        'teacher_id' => $teacher_id
    ];
    
    // Debug information (remove in production)
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log("=== REAL-TIME STATISTICS DEBUG ===");
        error_log("Teacher ID: " . $teacher_id);
        error_log("Total Students: " . $total_students);
        error_log("Students with Badges: " . $students_with_badges);
        error_log("Total Badges Awarded: " . $total_badges_awarded);
        error_log("Avg Badges per Student: " . $avg_badges_per_student);
        error_log("Total Courses: " . $additional_stats['total_courses']);
        error_log("Total Available Badges: " . $additional_stats['total_available_badges']);
        error_log("Badges with Awards: " . $additional_stats['badges_with_awards']);
        error_log("Last Updated: " . $stats['last_updated']);
        error_log("Student Badges Array Count: " . count($student_badges));
        error_log("Badges by Student Count: " . count($badges_by_student));
    }
    
    // Handle AJAX request for real-time statistics
    if (isset($_GET['ajax']) && $_GET['ajax'] == 'stats') {
        header('Content-Type: application/json');
        echo json_encode($stats);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Real-time statistics query error: " . $e->getMessage());
    $stats = [
        'total_students' => 0,
        'students_with_badges' => 0,
        'total_badges_awarded' => 0,
        'avg_badges_per_student' => 0,
        'total_courses' => 0,
        'total_available_badges' => 0,
        'badges_with_awards' => 0,
        'last_updated' => date('Y-m-d H:i:s'),
        'teacher_id' => $_SESSION['user_id'] ?? 0
    ];
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">Student Badges</h1>
                    <p class="text-muted mb-0">View and track student badge achievements in real-time</p>
                </div>
                <div class="d-flex gap-2">
                    <!-- View Type Toggle -->
                    <div class="btn-group view-toggle" role="group">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'detailed'])); ?>" 
                           class="btn <?php echo $view_type === 'detailed' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-list-ul me-1"></i>Detailed
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'grid'])); ?>" 
                           class="btn <?php echo $view_type === 'grid' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-grid-3x3-gap me-1"></i>Grid
                        </a>
                    </div>
                    <a href="badges.php" class="btn btn-outline-secondary">
                        <i class="bi bi-gear me-1"></i>Manage Badges
                    </a>
                    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                        <a href="?<?php echo http_build_query(array_diff_key($_GET, ['debug' => ''])); ?>" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-eye-slash me-1"></i>Hide Debug
                        </a>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['debug' => '1'])); ?>" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-bug me-1"></i>Debug
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="total-students"><?= $stats['total_students'] ?? 0 ?></h4>
                            <small>Total Students</small>
                            <div class="mt-1">
                                <small class="opacity-75">
                                    <i class="bi bi-clock me-1"></i>Real-time
                                </small>
                            </div>
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
                            <h4 class="mb-0" id="students-with-badges"><?= $stats['students_with_badges'] ?? 0 ?></h4>
                            <small>Students with Badges</small>
                            <div class="mt-1">
                                <small class="opacity-75">
                                    <?php if ($stats['total_students'] > 0): ?>
                                        <?= round(($stats['students_with_badges'] / $stats['total_students']) * 100, 1) ?>% of total
                                    <?php else: ?>
                                        <i class="bi bi-clock me-1"></i>Real-time
                                    <?php endif; ?>
                                </small>
                            </div>
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
                            <h4 class="mb-0" id="total-badges-awarded"><?= $stats['total_badges_awarded'] ?? 0 ?></h4>
                            <small>Total Badges Awarded</small>
                            <div class="mt-1">
                                <small class="opacity-75">
                                    <i class="bi bi-clock me-1"></i>Real-time
                                </small>
                            </div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-trophy fs-1"></i>
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
                            <h4 class="mb-0" id="avg-badges-per-student"><?= number_format($stats['avg_badges_per_student'] ?? 0, 1) ?></h4>
                            <small>Avg Badges/Student</small>
                            <div class="mt-1">
                                <small class="opacity-75">
                                    <i class="bi bi-clock me-1"></i>Real-time
                                </small>
                            </div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Real-time Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h5 class="card-title text-primary">
                        <i class="bi bi-book me-2"></i>Total Courses
                    </h5>
                    <h3 class="mb-0" id="total-courses"><?= $stats['total_courses'] ?? 0 ?></h3>
                    <small class="text-muted">Active courses</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h5 class="card-title text-success">
                        <i class="bi bi-award me-2"></i>Available Badges
                    </h5>
                    <h3 class="mb-0" id="total-available-badges"><?= $stats['total_available_badges'] ?? 0 ?></h3>
                    <small class="text-muted">Badge types</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h5 class="card-title text-warning">
                        <i class="bi bi-star me-2"></i>Badges with Awards
                    </h5>
                    <h3 class="mb-0" id="badges-with-awards"><?= $stats['badges_with_awards'] ?? 0 ?></h3>
                    <small class="text-muted">Awarded badges</small>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
    <!-- Debug Information -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="bi bi-bug me-2"></i>Debug Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Statistics:</h6>
                            <ul class="list-unstyled">
                                <li><strong>Total Students:</strong> <?= $stats['total_students'] ?></li>
                                <li><strong>Students with Badges:</strong> <?= $stats['students_with_badges'] ?></li>
                                <li><strong>Total Badges Awarded:</strong> <?= $stats['total_badges_awarded'] ?></li>
                                <li><strong>Avg Badges per Student:</strong> <?= $stats['avg_badges_per_student'] ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Data Counts:</h6>
                            <ul class="list-unstyled">
                                <li><strong>Student Badges Array:</strong> <?= count($student_badges) ?> records</li>
                                <li><strong>Badges by Student:</strong> <?= count($badges_by_student) ?> students</li>
                                <li><strong>Current View:</strong> <?= $view_type ?></li>
                                <li><strong>Teacher ID:</strong> <?= $_SESSION['user_id'] ?? 'NOT SET' ?></li>
                                <li><strong>Where Clause:</strong> <?= $where_clause ?></li>
                                <li><strong>Params:</strong> <?= implode(', ', $params) ?></li>
                            </ul>
                            
                            <h6 class="mt-3">Query Verification:</h6>
                            <ul class="list-unstyled">
                                <li><strong>Students Query:</strong> <?= $total_students ?> students found</li>
                                <li><strong>Badges Awarded Query:</strong> <?= $total_badges_awarded ?> awards found</li>
                                <li><strong>Students with Badges Query:</strong> <?= $students_with_badges ?> students found</li>
                                <li><strong>Calculation:</strong> <?= $students_with_badges ?> students ÷ <?= $total_badges_awarded ?> awards = <?= round($avg_badges_per_student, 1) ?> avg</li>
                                <li><strong>Last Updated:</strong> <?= $stats['last_updated'] ?></li>
                                <li><strong>Teacher ID:</strong> <?= $stats['teacher_id'] ?></li>
                            </ul>
                            
                            <h6 class="mt-3">Real-time Data:</h6>
                            <ul class="list-unstyled">
                                <li><strong>Total Courses:</strong> <?= $stats['total_courses'] ?></li>
                                <li><strong>Available Badges:</strong> <?= $stats['total_available_badges'] ?></li>
                                <li><strong>Badges with Awards:</strong> <?= $stats['badges_with_awards'] ?></li>
                                <li><strong>Student Badge Records:</strong> <?= count($student_badges) ?></li>
                                <li><strong>Grouped Students:</strong> <?= count($badges_by_student) ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php if (!empty($student_badges)): ?>
                    <div class="mt-3">
                        <h6>Sample Badge Data:</h6>
                        <pre class="bg-light p-2 rounded" style="max-height: 200px; overflow-y: auto;">
<?php print_r(array_slice($student_badges, 0, 2)); ?>
                        </pre>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="course_id" class="form-label">Filter by Course</label>
                            <select class="form-select" id="course_id" name="course_id" onchange="this.form.submit()">
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
                            <label for="student_id" class="form-label">Filter by Student</label>
                            <select class="form-select" id="student_id" name="student_id" onchange="this.form.submit()">
                                <option value="">All Students</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" 
                                            <?php echo $student_filter == $student['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="badge_id" class="form-label">Filter by Badge</label>
                            <select class="form-select" id="badge_id" name="badge_id" onchange="this.form.submit()">
                                <option value="">All Badges</option>
                                <?php foreach ($all_badges as $badge): ?>
                                    <option value="<?php echo $badge['id']; ?>" 
                                            <?php echo $badge_filter == $badge['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($badge['badge_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <a href="student_badges.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Badges List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Student Badge Achievements (<?= count($badges_by_student) ?> students)</h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-info">
                            <i class="bi bi-clock me-1"></i>Real-time Data
                        </span>
                        <span class="badge bg-success" id="view-saved-indicator" style="display: none;">
                            <i class="bi bi-check-circle me-1"></i>View Saved
                        </span>
                        <small class="text-muted">Last updated: <?= date('M j, Y g:i A') ?></small>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($badges_by_student)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-trophy text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">No Badge Achievements Found</h5>
                            <p class="text-muted">
                                <?php if ($course_filter || $student_filter || $badge_filter): ?>
                                    Try adjusting your filter criteria.
                                <?php else: ?>
                                    Students haven't earned any badges yet.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php if ($view_type === 'grid'): ?>
                            <!-- Grid View -->
                            <div class="row g-4">
                                <?php foreach ($badges_by_student as $student_data): ?>
                                    <div class="col-lg-6 col-xl-4">
                                        <div class="card h-100 shadow-sm border-0">
                                            <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <?php if (!empty($student_data['student']['profile_picture'])): ?>
                                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($student_data['student']['profile_picture']); ?>" 
                                                                 alt="Profile" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                                <i class="bi bi-person-fill text-primary" style="font-size: 1.5rem;"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-white">
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($student_data['student']['first_name'] . ' ' . $student_data['student']['last_name']); ?></h6>
                                                        <small class="opacity-75"><?php echo htmlspecialchars($student_data['student']['email']); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span class="badge bg-primary fs-6"><?= count($student_data['badges']) ?> Badges</span>
                                                    <span class="badge bg-success"><?= array_sum(array_column($student_data['badges'], 'points_value')) ?> Points</span>
                                                </div>
                                                
                                                <!-- Course Info -->
                                                <div class="mb-3">
                                                    <small class="text-muted">Courses:</small>
                                                    <div class="mt-1">
                                                        <?php 
                                                        $course_codes = explode(', ', $student_data['courses']['course_codes']);
                                                        $displayed_courses = array_slice($course_codes, 0, 2);
                                                        foreach ($displayed_courses as $course_code): ?>
                                                            <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(trim($course_code)); ?></span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($course_codes) > 2): ?>
                                                            <span class="badge bg-secondary">+<?= count($course_codes) - 2 ?> more</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Badge Icons Grid -->
                                                <div class="row g-2">
                                                    <?php foreach (array_slice($student_data['badges'], 0, 6) as $badge): ?>
                                                        <div class="col-4">
                                                            <div class="text-center">
                                                                <?php
                                                                $badge_icon = $badge['badge_icon'] ?: 'default.png';
                                                                $icon_path = "../uploads/badges/" . htmlspecialchars($badge_icon);
                                                                $icon_exists = !empty($badge['badge_icon']) && file_exists(__DIR__ . "/../uploads/badges/" . $badge['badge_icon']);
                                                                ?>
                                                                <?php if ($icon_exists): ?>
                                                                    <img src="<?php echo $icon_path; ?>" 
                                                                         alt="<?php echo htmlspecialchars($badge['badge_name']); ?>" 
                                                                         class="img-fluid rounded-circle" 
                                                                         style="width: 40px; height: 40px; object-fit: cover;"
                                                                         title="<?php echo htmlspecialchars($badge['badge_name']); ?>">
                                                                <?php else: ?>
                                                                    <div class="d-inline-flex align-items-center justify-content-center bg-gradient text-white rounded-circle" 
                                                                         style="width: 40px; height: 40px; background: linear-gradient(135deg, #7DCB80, #2E5E4E);">
                                                                        <i class="fas fa-trophy" style="font-size: 16px;"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (count($student_data['badges']) > 6): ?>
                                                        <div class="col-4">
                                                            <div class="text-center">
                                                                <div class="d-inline-flex align-items-center justify-content-center bg-light text-muted rounded-circle" 
                                                                     style="width: 40px; height: 40px;">
                                                                    <small>+<?= count($student_data['badges']) - 6 ?></small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-light">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i>
                                                    Latest: <?php 
                                                        $latest_badge = end($student_data['badges']);
                                                        echo $latest_badge['awarded_at'] ? date('M j, Y', strtotime($latest_badge['awarded_at'])) : 'N/A';
                                                    ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Detailed View -->
                        <?php foreach ($badges_by_student as $student_data): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($student_data['student']['profile_picture'])): ?>
                                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($student_data['student']['profile_picture']); ?>" 
                                                         alt="Profile" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                                        <i class="bi bi-person-fill text-white" style="font-size: 1.5rem;"></i>
                                                    </div>
                                                <?php endif; ?>
                                        <div>
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($student_data['student']['first_name'] . ' ' . $student_data['student']['last_name']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($student_data['student']['email']); ?></small>
                                                    <div class="mt-1">
                                                        <?php 
                                                        $course_codes = explode(', ', $student_data['courses']['course_codes']);
                                                        $displayed_courses = array_slice($course_codes, 0, 3);
                                                        foreach ($displayed_courses as $course_code): ?>
                                                            <span class="badge bg-info me-1"><?php echo htmlspecialchars(trim($course_code)); ?></span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($course_codes) > 3): ?>
                                                            <span class="badge bg-secondary">+<?= count($course_codes) - 3 ?> more</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-primary fs-6"><?= count($student_data['badges']) ?> badges</span>
                                                <div class="mt-1">
                                                    <span class="badge bg-success"><?= array_sum(array_column($student_data['badges'], 'points_value')) ?> total points</span>
                                                </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <?php foreach ($student_data['badges'] as $badge): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-body text-center">
                                                            <?php
                                                            $badge_icon = $badge['badge_icon'] ?: 'default.png';
                                                            $icon_path = "../uploads/badges/" . htmlspecialchars($badge_icon);
                                                            $icon_exists = !empty($badge['badge_icon']) && file_exists(__DIR__ . "/../uploads/badges/" . $badge['badge_icon']);
                                                            ?>
                                                            <?php if ($icon_exists): ?>
                                                                <img src="<?php echo $icon_path; ?>" 
                                                                     alt="<?php echo htmlspecialchars($badge['badge_name']); ?>" 
                                                                     style="height:60px;" class="mb-3">
                                                            <?php else: ?>
                                                                <div class="d-inline-flex align-items-center justify-content-center bg-gradient text-white rounded-circle mb-3" 
                                                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #7DCB80, #2E5E4E);">
                                                                    <i class="fas fa-trophy" style="font-size: 24px;"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        <h6 class="card-title"><?php echo htmlspecialchars($badge['badge_name']); ?></h6>
                                                        <p class="card-text small text-muted">
                                                            <?php echo htmlspecialchars($badge['badge_description']); ?>
                                                        </p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="badge bg-<?php 
                                                                echo $badge['badge_type'] === 'course_completion' ? 'primary' : 
                                                                    ($badge['badge_type'] === 'high_score' ? 'warning' : 
                                                                    ($badge['badge_type'] === 'participation' ? 'info' : 'secondary')); 
                                                            ?>">
                                                                <?php echo ucfirst(str_replace('_',' ',$badge['badge_type'])); ?>
                                                            </span>
                                                            <span class="badge bg-success"><?= $badge['points_value'] ?> pts</span>
                                                        </div>
                                                        <?php if ($badge['awarded_at']): ?>
                                                            <small class="text-muted d-block mt-2">
                                                                <i class="bi bi-calendar me-1"></i>
                                                                Awarded: <?php echo date('M j, Y', strtotime($badge['awarded_at'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced styling for student badges page */
.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.badge-icon {
    transition: transform 0.3s ease;
}

.badge-icon:hover {
    transform: scale(1.1);
}

.view-toggle .btn {
    transition: all 0.3s ease;
    position: relative;
}

.view-toggle .btn:hover {
    transform: translateY(-1px);
}

.view-toggle .btn.active {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-1px);
}

.view-toggle .btn.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 3px;
    background: currentColor;
    border-radius: 2px;
}

/* Grid view specific styling */
.grid-view .card {
    border-radius: 15px;
    overflow: hidden;
}

.grid-view .card-header {
    border-radius: 0;
    border: none;
}

/* Detailed view specific styling */
.detailed-view .card {
    border-radius: 10px;
}

.detailed-view .card-header {
    border-radius: 10px 10px 0 0;
}

/* Real-time indicator */
.real-time-indicator {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

/* Badge grid in grid view */
.badge-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}

/* Real-time update animations */
.updated {
    animation: updatePulse 0.6s ease-in-out;
}

@keyframes updatePulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); background-color: rgba(40, 167, 69, 0.1); }
    100% { transform: scale(1); }
}

.card.updated {
    box-shadow: 0 0 20px rgba(40, 167, 69, 0.3);
    border-color: #28a745;
}

/* Manual refresh button styling */
.text-center .btn {
    transition: all 0.3s ease;
}

.text-center .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Statistics card hover effects */
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .badge-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .card-header .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .card-header .text-end {
        text-align: left !important;
        margin-top: 0.5rem;
    }
    
    .text-center .btn {
        margin-bottom: 0.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View type persistence using localStorage
    const STORAGE_KEY = 'student_badges_view_type';
    const currentView = '<?= $view_type ?>';
    
    // Save current view to localStorage
    localStorage.setItem(STORAGE_KEY, currentView);
    
    // Add smooth transitions
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.style.transition = 'all 0.3s ease';
    });
    
    // Add hover effects for badge icons
    const badgeIcons = document.querySelectorAll('.badge-icon, img[alt*="badge"]');
    badgeIcons.forEach(icon => {
        icon.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
        });
        icon.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Enhanced view switching with persistence
    const viewButtons = document.querySelectorAll('.view-toggle .btn');
    viewButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetView = this.getAttribute('href').split('view=')[1];
            
            // Add loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Switching...';
            this.disabled = true;
            
            // Save to localStorage immediately
            localStorage.setItem(STORAGE_KEY, targetView);
            
            // Show "View Saved" indicator
            const savedIndicator = document.getElementById('view-saved-indicator');
            if (savedIndicator) {
                savedIndicator.style.display = 'inline-block';
                setTimeout(() => {
                    savedIndicator.style.display = 'none';
                }, 2000);
            }
            
            // Add fade out effect
            const content = document.querySelector('.card-body');
            if (content) {
                content.style.opacity = '0.5';
                content.style.transition = 'opacity 0.3s ease';
            }
            
            // Navigate after a brief delay for smooth transition
            setTimeout(() => {
                window.location.href = this.href;
            }, 300);
        });
    });
    
    // Pusher will handle real-time updates - no auto-refresh needed
    
    // Restore view type from localStorage on page load (fallback)
    const savedView = localStorage.getItem(STORAGE_KEY);
    if (savedView && savedView !== currentView) {
        // If there's a saved view different from current, update the URL
        const url = new URL(window.location);
        url.searchParams.set('view', savedView);
        if (url.toString() !== window.location.href) {
            window.location.href = url.toString();
        }
    }
    
    // Add visual feedback for current view
    const currentViewButton = document.querySelector(`.view-toggle .btn[href*="view=${currentView}"]`);
    if (currentViewButton) {
        currentViewButton.classList.add('active');
    }
    
    // Add keyboard shortcuts for view switching
    document.addEventListener('keydown', function(e) {
        // Ctrl + 1 for detailed view, Ctrl + 2 for grid view
        if (e.ctrlKey) {
            if (e.key === '1') {
                e.preventDefault();
                const detailedBtn = document.querySelector('.view-toggle .btn[href*="view=detailed"]');
                if (detailedBtn) detailedBtn.click();
            } else if (e.key === '2') {
                e.preventDefault();
                const gridBtn = document.querySelector('.view-toggle .btn[href*="view=grid"]');
                if (gridBtn) gridBtn.click();
            }
        }
    });
    
    // Add tooltip for keyboard shortcuts
    viewButtons.forEach(button => {
        const viewType = button.getAttribute('href').split('view=')[1];
        const shortcut = viewType === 'detailed' ? 'Ctrl+1' : 'Ctrl+2';
        button.setAttribute('title', `Switch to ${viewType} view (${shortcut})`);
    });
    
    // Pusher will handle real-time updates for statistics
    // Manual refresh functionality (optional)
    function refreshStatistics() {
        location.reload();
    }
    
    // Add manual refresh button
    const refreshButton = document.createElement('div');
    refreshButton.className = 'text-center mb-3';
    refreshButton.innerHTML = `
        <button id="manual-refresh" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh Data
        </button>
        <small class="text-muted ms-2">
            <i class="bi bi-clock me-1"></i>Last updated: <?= $stats['last_updated'] ?>
        </small>
    `;
    
    // Insert refresh button after the statistics cards
    const statsCards = document.querySelector('.row.mb-4');
    if (statsCards) {
        statsCards.insertAdjacentElement('afterend', refreshButton);
    }
    
    // Add event listener for manual refresh
    document.getElementById('manual-refresh')?.addEventListener('click', function() {
        this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Refreshing...';
        this.disabled = true;
        
        setTimeout(() => {
            refreshStatistics();
        }, 500);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
