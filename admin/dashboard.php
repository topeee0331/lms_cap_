<?php
$page_title = 'Admin Dashboard';
require_once '../config/config.php';
requireRole('admin');
require_once '../includes/header.php';

// 1. Fetch all active academic periods for the dropdown
$ay_stmt = $db->prepare('SELECT id, CONCAT(academic_year, " - ", semester_name) as period_name, is_active FROM academic_periods ORDER BY academic_year DESC, semester_name');
$ay_stmt->execute();
$all_periods = $ay_stmt->fetchAll();

// 2. Handle academic period selection (GET or SESSION)
if (isset($_GET['academic_period_id'])) {
    $_SESSION['dashboard_academic_period_id'] = (int)$_GET['academic_period_id'];
}
// Find the first active academic period
$active_period = null;
foreach ($all_periods as $period) {
    if ($period['is_active']) {
        $active_period = $period['id'];
        break;
    }
}
$selected_period_id = $_SESSION['dashboard_academic_period_id'] ?? $active_period ?? ($all_periods[0]['id'] ?? null);

// 3. Filter all queries by selected academic year
// Get system statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT CASE WHEN u.role = 'student' THEN u.id END) as total_students,
        COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN u.id END) as total_teachers,
        COUNT(DISTINCT c.id) as total_courses,
        COUNT(DISTINCT e.student_id) as total_enrollments,
        COUNT(DISTINCT aa.assessment_id) as total_assessments_taken,
        AVG(aa.score) as average_score
    FROM users u
    LEFT JOIN courses c ON c.academic_period_id = ?
    LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
    LEFT JOIN assessment_attempts aa ON aa.status = 'completed'
");
$stmt->execute([$selected_period_id]);
$stats = $stmt->fetch();

// Calculate trend data (comparing with previous week)
$current_date = date('Y-m-d');
$week_ago = date('Y-m-d', strtotime('-1 week'));

// Get current week enrollments
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT e.student_id) as current_enrollments
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE c.academic_period_id = ? AND e.status = 'active'
    AND e.enrolled_at >= ?
");
$stmt->execute([$selected_period_id, $week_ago]);
$current_week = $stmt->fetch();

// Get previous week enrollments
$two_weeks_ago = date('Y-m-d', strtotime('-2 weeks'));
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT e.student_id) as previous_enrollments
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE c.academic_period_id = ? AND e.status = 'active'
    AND e.enrolled_at >= ? AND e.enrolled_at < ?
");
$stmt->execute([$selected_period_id, $two_weeks_ago, $week_ago]);
$previous_week = $stmt->fetch();

$enrollment_trend = ($current_week['current_enrollments'] ?? 0) - ($previous_week['previous_enrollments'] ?? 0);

// Calculate other trends
$user_trend = 0; // For now, keep as 0 since user registration tracking needs more complex logic
$course_trend = 0; // For now, keep as 0 since course creation tracking needs more complex logic
$assessment_trend = 0; // For now, keep as 0 since assessment completion tracking needs more complex logic

// Get recent users (who have activity in this academic period)
$stmt = $db->prepare("
    SELECT DISTINCT u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.created_at, u.profile_picture
    FROM users u
    LEFT JOIN course_enrollments e ON u.id = e.student_id
    LEFT JOIN courses c ON e.course_id = c.id
    WHERE c.academic_period_id = ? OR u.role = 'admin'
    ORDER BY u.created_at DESC 
    LIMIT 5
");
$stmt->execute([$selected_period_id]);
$recent_users = $stmt->fetchAll();

// Get recent courses
$stmt = $db->prepare("
    SELECT c.*, u.first_name, u.last_name, COUNT(e.student_id) as student_count
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
    WHERE c.academic_period_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->execute([$selected_period_id]);
$recent_courses = $stmt->fetchAll();

// Get recent announcements (for courses in this academic period)
$stmt = $db->prepare("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    WHERE a.is_global = 1 OR a.target_audience IS NOT NULL
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_announcements = $stmt->fetchAll();

function getRandomIconClass($userId) {
    $icons = [
        'bi-person', 'bi-person-circle', 'bi-person-badge', 'bi-person-fill', 'bi-emoji-smile', 'bi-people', 'bi-person-lines-fill', 'bi-person-video', 'bi-person-check', 'bi-person-gear'
    ];
    return $icons[$userId % count($icons)];
}
function getRandomBgClass($userId) {
    $colors = [
        'bg-success', 'bg-primary', 'bg-warning', 'bg-danger', 'bg-info', 'bg-secondary', 'bg-dark', 'bg-green', 'bg-teal', 'bg-orange'
    ];
    return $colors[$userId % count($colors)];
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
.stats-card {
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 2px 16px rgba(46,94,78,0.08);
    border: 1.5px solid #e0e0e0;
    padding: 2.2rem 1.2rem 1.5rem 1.2rem;
    text-align: center;
    transition: box-shadow 0.2s, transform 0.2s;
    min-height: 260px;
    position: relative;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
}
.stats-card:hover {
    box-shadow: 0 8px 32px rgba(46,94,78,0.13), 0 2px 8px rgba(24,119,242,0.10);
    transform: translateY(-2px) scale(1.03);
    z-index: 2;
}
.stats-card .stats-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}
.stats-card .stats-users { color: #2E5E4E; }
.stats-card .stats-courses { color: #7DCB80; }
.stats-card .stats-assess { color: #FFE066; }
.stats-card .stats-enroll { color: #1877f2; }
.stats-card .stats-trend {
    font-size: 1.1rem;
    margin-left: 0.3rem;
    vertical-align: middle;
}
.stats-card .stats-trend.up { color: #388e3c; }
.stats-card .stats-trend.down { color: #e57373; }
.stats-card .stats-tooltip {
    margin-left: 0.3rem;
    color: #888;
    cursor: pointer;
}
.stats-card .progress {
    height: 0.7rem;
    border-radius: 0.5rem;
    margin-top: 0.5rem;
    margin-bottom: 0.2rem;
}
.stats-card .view-details-btn {
    margin-top: 0.7rem;
    border-radius: 2rem;
    font-size: 0.98rem;
    font-weight: 600;
    padding: 0.4rem 1.2rem;
}
@media (max-width: 991.98px) {
    .stats-card { min-height: 180px; padding: 1.2rem 0.7rem; }
}
.quickaction-btn {
    border-color: var(--main-green) !important;
    color: var(--main-green) !important;
    font-weight: 600;
    background: #fff;
    transition: background 0.18s, color 0.18s, border 0.18s;
}
.quickaction-btn:hover, .quickaction-btn:focus {
    background: var(--main-green) !important;
    color: #fff !important;
    border-color: var(--main-green) !important;
}
.quickaction-btn i {
    color: var(--accent-green) !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Admin Dashboard</h1>
        </div>
    </div>

    <!-- 4. Add the academic year dropdown to the dashboard UI (above the stats cards): -->
    <div class="row mb-3">
      <div class="col-12">
        <form method="get" class="d-flex align-items-center">
          <label for="academic_period_id" class="me-2 fw-bold">Academic Period:</label>
          <select name="academic_period_id" id="academic_period_id" class="form-select w-auto me-2" onchange="this.form.submit()">
            <?php foreach ($all_periods as $period): ?>
              <option value="<?= $period['id'] ?>" <?= $selected_period_id == $period['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($period['period_name']) ?><?= !$period['is_active'] ? ' (Inactive)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <noscript><button type="submit" class="btn btn-primary btn-sm">Go</button></noscript>
        </form>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="users.php" class="text-decoration-none" tabindex="0" aria-label="View all users">
            <div class="stats-card hoverable" tabindex="0">
                <div class="d-flex justify-content-center align-items-center mb-2">
                    <i class="bi bi-people stats-icon stats-users"></i>
                    <span data-bs-toggle="tooltip" title="All registered users including students, teachers, and admins.">
                        <i class="bi bi-info-circle stats-tooltip"></i>
                    </span>
                    <span class="stats-trend <?php echo $user_trend >= 0 ? 'up' : 'down'; ?>" title="<?php echo $user_trend >= 0 ? 'Up' : 'Down'; ?> <?php echo abs($user_trend); ?> this week">
                        <i class="bi bi-arrow-<?php echo $user_trend >= 0 ? 'up' : 'down'; ?>"></i><?php echo $user_trend >= 0 ? '+' : ''; ?><?php echo $user_trend; ?>
                    </span>
                </div>
                <h3 class="display-6 fw-bold mb-1" aria-label="Total Users"><?php echo $stats['total_users']; ?></h3>
                <div class="text-muted mb-1">Total Users</div>
                <small class="text-secondary"><?php echo $stats['total_students']; ?> Students, <?php echo $stats['total_teachers']; ?> Teachers</small>
                <div class="mt-2">
                    <button class="btn btn-outline-success btn-sm view-details-btn" tabindex="-1">View Details</button>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="courses.php" class="text-decoration-none" tabindex="0" aria-label="View all courses">
            <div class="stats-card hoverable" tabindex="0">
                <div class="d-flex justify-content-center align-items-center mb-2">
                    <i class="bi bi-book stats-icon stats-courses"></i>
                    <span data-bs-toggle="tooltip" title="All courses created by teachers.">
                        <i class="bi bi-info-circle stats-tooltip"></i>
                    </span>
                    <span class="stats-trend <?php echo $course_trend >= 0 ? 'up' : 'down'; ?>" title="<?php echo $course_trend >= 0 ? 'Up' : 'Down'; ?> <?php echo abs($course_trend); ?> this week">
                        <i class="bi bi-arrow-<?php echo $course_trend >= 0 ? 'up' : 'down'; ?>"></i><?php echo $course_trend >= 0 ? '+' : ''; ?><?php echo $course_trend; ?>
                    </span>
                </div>
                <h3 class="display-6 fw-bold mb-1" aria-label="Total Courses"><?php echo $stats['total_courses']; ?></h3>
                <div class="text-muted mb-1">Total Courses</div>
                <small class="text-secondary"><?php echo $stats['total_enrollments']; ?> Enrollments</small>
                <div class="mt-2">
                    <button class="btn btn-outline-success btn-sm view-details-btn" tabindex="-1">View Details</button>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="assessments.php" class="text-decoration-none" tabindex="0" aria-label="View all assessments">
            <div class="stats-card hoverable" tabindex="0">
                <div class="d-flex justify-content-center align-items-center mb-2">
                    <i class="bi bi-clipboard-check stats-icon stats-assess"></i>
                    <span data-bs-toggle="tooltip" title="Total number of assessments taken by students.">
                        <i class="bi bi-info-circle stats-tooltip"></i>
                    </span>
                    <span class="stats-trend <?php echo $assessment_trend >= 0 ? 'up' : 'down'; ?>" title="<?php echo $assessment_trend >= 0 ? 'Up' : 'Down'; ?> <?php echo abs($assessment_trend); ?> this week">
                        <i class="bi bi-arrow-<?php echo $assessment_trend >= 0 ? 'up' : 'down'; ?>"></i><?php echo $assessment_trend >= 0 ? '+' : ''; ?><?php echo $assessment_trend; ?>
                    </span>
                </div>
                <h3 class="display-6 fw-bold mb-1" aria-label="Assessments Taken"><?php echo $stats['total_assessments_taken']; ?></h3>
                <div class="text-muted mb-1">Assessments Taken</div>
                <small class="text-secondary">Avg Score: <?php echo number_format($stats['average_score'] ?? 0, 1); ?>%</small>
                <div class="progress mt-2" aria-label="Average Score Progress">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, max(0, round($stats['average_score'] ?? 0))); ?>%;" aria-valuenow="<?php echo min(100, max(0, round($stats['average_score'] ?? 0))); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="mt-2">
                    <button class="btn btn-outline-success btn-sm view-details-btn" tabindex="-1">View Details</button>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="enrollments.php" class="text-decoration-none" tabindex="0" aria-label="View all enrollments">
            <div class="stats-card hoverable" tabindex="0">
                <div class="d-flex justify-content-center align-items-center mb-2">
                    <i class="bi bi-graph-up stats-icon stats-enroll"></i>
                    <span data-bs-toggle="tooltip" title="Number of active enrollments.">
                        <i class="bi bi-info-circle stats-tooltip"></i>
                    </span>
                    <span class="stats-trend <?php echo $enrollment_trend >= 0 ? 'up' : 'down'; ?>" title="<?php echo $enrollment_trend >= 0 ? 'Up' : 'Down'; ?> <?php echo abs($enrollment_trend); ?> this week">
                        <i class="bi bi-arrow-<?php echo $enrollment_trend >= 0 ? 'up' : 'down'; ?>"></i><?php echo $enrollment_trend >= 0 ? '+' : ''; ?><?php echo $enrollment_trend; ?>
                    </span>
                </div>
                <h3 class="display-6 fw-bold mb-1" aria-label="Active Enrollments"><?php echo $stats['total_enrollments']; ?></h3>
                <div class="text-muted mb-1">Active Enrollments</div>
                <small class="text-secondary">Active Students</small>
                <div class="mt-2">
                    <button class="btn btn-outline-success btn-sm view-details-btn" tabindex="-1">View Details</button>
                </div>
            </div>
            </a>
        </div>
    </div>
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-center">
        <div class="card border-0 shadow-sm h-100" style="min-width: 320px; max-width: 350px;">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-award fs-1 text-warning"></i>
                </div>
                <h3 class="fw-bold mb-1">Badge Management</h3>
                <p class="text-muted mb-0 small">Create, edit, and delete badges</p>
                <a href="badges.php" class="btn btn-primary mt-2"><i class="bi bi-gear me-1"></i>Manage Badges</a>
            </div>
        </div>
    </div>
</div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="users.php" class="btn btn-outline-success w-100 quickaction-btn">
                                <i class="bi bi-people me-2"></i>Manage Users
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="courses.php" class="btn btn-outline-success w-100 quickaction-btn">
                                <i class="bi bi-book me-2"></i>Manage Courses
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="sections.php" class="btn btn-outline-success w-100 quickaction-btn">
                                <i class="bi bi-journal-text me-2"></i>Manage Sections
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="academic_periods.php" class="btn btn-outline-success w-100 quickaction-btn">
                                <i class="bi bi-calendar me-2"></i>Academic Periods
                            </a>
                        </div>
                        <div class="col-12 mb-3">
                            <a href="announcements.php" class="btn btn-outline-success w-100 quickaction-btn" style="font-size:1.15rem; padding:1.1rem 0;">
                                <i class="bi bi-megaphone me-2"></i>Announcements
                            </a>
                        </div>
                        <div class="col-12 mb-3">
                            <a href="unlock_accounts.php" class="btn btn-outline-warning w-100 quickaction-btn" style="font-size:1.15rem; padding:1.1rem 0;">
                                <i class="bi bi-shield-lock me-2"></i>Security Management
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <!-- Recent Users -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Recent Users</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recent_users as $user): ?>
                        <li class="list-group-item d-flex align-items-center">
                            <img src="<?php echo getProfilePictureUrl($user['profile_picture'] ?? null, 'medium'); ?>" class="rounded-circle me-3" alt="Profile" style="width: 48px; height: 48px; object-fit: cover;">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                            </div>
                            <span class="badge ms-auto <?php echo $user['role'] === 'admin' ? 'bg-danger' : ($user['role'] === 'teacher' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Recent Courses -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Courses</h5>
                    <a href="courses.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_courses)): ?>
                        <p class="text-muted">No courses found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_courses as $course): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($course['course_code']); ?> • 
                                        By <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary"><?php echo $course['student_count']; ?> students</span>
                                    <br>
                                    <small class="text-muted"><?php echo formatDate($course['created_at']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Announcements -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Announcements</h5>
                    <a href="announcements.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_announcements)): ?>
                        <p class="text-muted">No announcements found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_announcements as $announcement): ?>
                            <div class="border-bottom pb-3 mb-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars(substr($announcement['content'], 0, 150)) . '...'; ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        By <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                    </small>
                                    <small class="text-muted"><?php echo formatDate($announcement['created_at']); ?></small>
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