<?php
$page_title = 'Student Badges';
require_once '../includes/header.php';
requireRole('teacher');

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

// Get student badge data
try {
    $student_badge_query = "
        SELECT 
            u.id as student_id,
            u.first_name,
            u.last_name,
            u.email,
            b.id as badge_id,
            b.badge_name,
            b.badge_description,
            b.badge_icon,
            b.badge_type,
            b.points_value,
            JSON_UNQUOTE(JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', u.id, NULL, '$[*].student_id'), '].awarded_at'))) as awarded_at
        FROM users u
        JOIN course_enrollments e ON u.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        CROSS JOIN badges b
        $where_clause
        AND JSON_SEARCH(b.awarded_to, 'one', u.id, NULL, '$[*].student_id') IS NOT NULL
    ";

    if ($student_filter > 0) {
        $student_badge_query .= " AND u.id = ?";
        $params[] = $student_filter;
    }

    if ($badge_filter > 0) {
        $student_badge_query .= " AND b.id = ?";
        $params[] = $badge_filter;
    }

    $student_badge_query .= " ORDER BY u.first_name, u.last_name, b.badge_name";

    $stmt = $db->prepare($student_badge_query);
    $stmt->execute($params);
    $student_badges = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Student badges query error: " . $e->getMessage());
    $student_badges = [];
}

// Group badges by student
$badges_by_student = [];
foreach ($student_badges as $badge) {
    $student_id = $badge['student_id'];
    if (!isset($badges_by_student[$student_id])) {
        $badges_by_student[$student_id] = [
            'student' => [
                'id' => $badge['student_id'],
                'first_name' => $badge['first_name'],
                'last_name' => $badge['last_name'],
                'email' => $badge['email']
            ],
            'badges' => []
        ];
    }
    $badges_by_student[$student_id]['badges'][] = $badge;
}

// Get badge statistics
try {
    $stats_query = "
        SELECT 
            COUNT(DISTINCT u.id) as total_students,
            COUNT(DISTINCT b.id) as total_badges_awarded,
            AVG(student_badge_count.badge_count) as avg_badges_per_student
        FROM users u
        JOIN course_enrollments e ON u.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN (
            SELECT 
                u2.id as student_id,
                COUNT(DISTINCT b2.id) as badge_count
            FROM users u2
            JOIN course_enrollments e2 ON u2.id = e2.student_id
            JOIN courses c2 ON e2.course_id = c2.id
            CROSS JOIN badges b2
            WHERE c2.teacher_id = ?
            AND JSON_SEARCH(b2.awarded_to, 'one', u2.id, NULL, '$[*].student_id') IS NOT NULL
            GROUP BY u2.id
        ) student_badge_count ON u.id = student_badge_count.student_id
        $where_clause
    ";

    $stmt = $db->prepare($stats_query);
    $stmt->execute(array_merge([$_SESSION['user_id']], $params));
    $stats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Badge statistics query error: " . $e->getMessage());
    $stats = [
        'total_students' => 0,
        'total_badges_awarded' => 0,
        'avg_badges_per_student' => 0
    ];
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">Student Badges</h1>
                    <p class="text-muted mb-0">View and track student badge achievements</p>
                </div>
                <div class="btn-group">
                    <a href="badges.php" class="btn btn-outline-primary">
                        <i class="bi bi-gear me-1"></i>Manage Badges
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['total_students'] ?? 0 ?></h4>
                            <small>Total Students</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $stats['total_badges_awarded'] ?? 0 ?></h4>
                            <small>Badges Awarded</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-trophy fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($stats['avg_badges_per_student'] ?? 0, 1) ?></h4>
                            <small>Avg Badges/Student</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                <div class="card-header">
                    <h5 class="mb-0">Student Badge Achievements (<?= count($badges_by_student) ?> students)</h5>
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
                        <?php foreach ($badges_by_student as $student_data): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($student_data['student']['first_name'] . ' ' . $student_data['student']['last_name']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($student_data['student']['email']); ?></small>
                                        </div>
                                        <div>
                                            <span class="badge bg-primary"><?= count($student_data['badges']) ?> badges</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <?php foreach ($student_data['badges'] as $badge): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-body text-center">
                                                        <img src="../uploads/badges/<?php echo htmlspecialchars($badge['badge_icon'] ?: 'default.png'); ?>" 
                                                             alt="badge" style="height:60px;" class="mb-3">
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
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>