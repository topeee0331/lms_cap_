<?php
$page_title = 'Teacher Dashboard';
require_once '../includes/header.php';
require_once '../config/pusher.php';
require_once '../includes/pusher_notifications.php';
requireRole('teacher');

// 1. Fetch all active academic periods for the dropdown
$ay_stmt = $db->prepare('SELECT id, academic_year, semester_name, is_active FROM academic_periods ORDER BY academic_year DESC, semester_name');
$ay_stmt->execute();
$all_years = $ay_stmt->fetchAll();

// 2. Handle academic year selection (GET or SESSION)
if (isset($_GET['academic_period_id'])) {
    $_SESSION['teacher_dashboard_academic_period_id'] = (int)$_GET['academic_period_id'];
}
// Find the first active academic year
$active_year = null;
foreach ($all_years as $year) {
    if ($year['is_active']) {
        $active_year = $year['id'];
        break;
    }
}
$selected_period_id = $_SESSION['teacher_dashboard_academic_period_id'] ?? $active_year ?? ($all_years[0]['id'] ?? null);

// 3. Filter all queries by selected academic year and teacher
// Get teacher statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_courses,
        (
            SELECT COUNT(DISTINCT e.student_id)
            FROM course_enrollments e
            JOIN courses c2 ON e.course_id = c2.id
            WHERE c2.teacher_id = ? AND c2.academic_period_id = ? AND e.status = 'active'
        ) as total_students,
        COUNT(DISTINCT a.id) as total_assessments,
        AVG(aa.score) as average_score,
        COUNT(DISTINCT aa.assessment_id) as total_assessments_taken,
        COUNT(DISTINCT e.student_id) as total_enrollments
    FROM courses c
    LEFT JOIN assessments a ON a.course_id = c.id
    LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.status = 'completed'
    LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
    WHERE c.teacher_id = ? AND c.academic_period_id = ?
");
$stmt->execute([$_SESSION['user_id'], $selected_period_id, $_SESSION['user_id'], $selected_period_id]);
$stats = $stmt->fetch();

// Calculate trend data (comparing with previous week)
$current_date = date('Y-m-d');
$week_ago = date('Y-m-d', strtotime('-1 week'));

// Get current week stats
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT e.student_id) as current_enrollments
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE c.teacher_id = ? AND c.academic_period_id = ? AND e.status = 'active'
    AND e.enrolled_at >= ?
");
$stmt->execute([$_SESSION['user_id'], $selected_period_id, $week_ago]);
$current_week = $stmt->fetch();

// Get previous week stats
$two_weeks_ago = date('Y-m-d', strtotime('-2 weeks'));
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT e.student_id) as previous_enrollments
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE c.teacher_id = ? AND c.academic_period_id = ? AND e.status = 'active'
    AND e.enrolled_at >= ? AND e.enrolled_at < ?
");
$stmt->execute([$_SESSION['user_id'], $selected_period_id, $two_weeks_ago, $week_ago]);
$previous_week = $stmt->fetch();

$enrollment_trend = ($current_week['current_enrollments'] ?? 0) - ($previous_week['previous_enrollments'] ?? 0);

// Calculate other trends
$course_trend = 0; // For now, keep as 0 since course creation is less frequent
$assessment_trend = 0; // For now, keep as 0 since assessment completion tracking needs more complex logic

// Get recent students (who have activity in this teacher's courses for this academic period)
$stmt = $db->prepare("
    SELECT DISTINCT u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.created_at, u.profile_picture
    FROM users u
    JOIN course_enrollments e ON u.id = e.student_id
    JOIN courses c ON e.course_id = c.id
    WHERE c.teacher_id = ? AND c.academic_period_id = ?
    ORDER BY u.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $selected_period_id]);
$recent_students = $stmt->fetchAll();

// Get recent courses for this teacher
$stmt = $db->prepare("
    SELECT c.*, COUNT(e.student_id) as student_count
    FROM courses c
    LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
    WHERE c.teacher_id = ? AND c.academic_period_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $selected_period_id]);
$recent_courses = $stmt->fetchAll();

// Get recent announcements (for this teacher's courses in this academic period)
$stmt = $db->prepare("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    WHERE a.author_id = ? AND (a.is_global = 1 OR (a.target_audience IS NOT NULL AND JSON_SEARCH(a.target_audience, 'one', ?) IS NOT NULL))
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
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
    padding: 1.5rem 1.2rem;
    text-align: center;
    transition: box-shadow 0.2s, transform 0.2s;
    height: 320px;
    position: relative;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
}

/* Ensure all cards have equal height */
.col-xl-3.col-md-6 {
    display: flex;
    flex-direction: column;
}

.col-xl-3.col-md-6 > a {
    display: flex;
    flex: 1;
}

.col-xl-3.col-md-6 > a > .stats-card {
    flex: 1;
    width: 100%;
}

/* Card-specific background colors */
.stats-card.students-card {
    background: #e3f2fd;
    border-color: #2196f3;
    color: #1565c0;
}

.stats-card.students-card .text-muted {
    color: #1976d2 !important;
}

.stats-card.students-card .text-secondary {
    color: #0d47a1 !important;
}

.stats-card.courses-card {
    background: #e8f5e8;
    border-color: #4caf50;
    color: #2e7d32;
}

.stats-card.courses-card .text-muted {
    color: #388e3c !important;
}

.stats-card.courses-card .text-secondary {
    color: #1b5e20 !important;
}

.stats-card.assessments-card {
    background: #fff3e0;
    border-color: #ff9800;
    color: #e65100;
}

.stats-card.assessments-card .text-muted {
    color: #f57c00 !important;
}

.stats-card.assessments-card .text-secondary {
    color: #bf360c !important;
}

.stats-card.enrollments-card {
    background: #f3e5f5;
    border-color:rgb(39, 48, 176);
    color:rgb(44, 31, 162);
}

.stats-card.enrollments-card .text-muted {
    color:rgb(63, 36, 170) !important;
}

.stats-card.enrollments-card .text-secondary {
    color:rgb(44, 20, 140) !important;
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

.stats-card .view-details-btn {
    margin-top: 0.7rem;
    border-radius: 2rem;
    font-size: 0.98rem;
    font-weight: 600;
    padding: 0.4rem 1.2rem;
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
    margin-bottom: 0.5rem;
}
.stats-card .view-details-btn {
    margin-top: auto;
    border-radius: 2rem;
    font-size: 0.98rem;
    font-weight: 600;
    padding: 0.5rem 1rem;
}

/* Ensure button container is at the bottom */
.stats-card .mt-2 {
    margin-top: auto;
    padding-top: 1rem;
}

/* Specific styling for assessments card to handle progress bar */
.stats-card.assessments-card .text-secondary {
    margin-bottom: 0.1rem;
}

.stats-card.assessments-card .progress {
    margin-bottom: 0rem;
}
@media (max-width: 991.98px) {
    .stats-card { min-height: 180px; padding: 1.2rem 0.7rem; }
}
.quickaction-btn {
    border-color: var(--main-green) !important;
    color: var(--main-green) !important;
    font-weight: 600;
    background: #fff;
    transition: all 0.3s ease;
    border-radius: 15px;
    border-width: 2px;
    position: relative;
    overflow: hidden;
}

.quickaction-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.quickaction-btn:hover::before {
    left: 100%;
}

.quickaction-btn:hover, .quickaction-btn:focus {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: var(--main-green) !important;
}

.quickaction-btn i {
    transition: transform 0.3s ease;
}

.quickaction-btn:hover i {
    transform: scale(1.1);
}

/* Color-specific hover effects */
.btn-outline-primary:hover {
    background: #0d6efd !important;
    color: white !important;
}

.btn-outline-success:hover {
    background: #198754 !important;
    color: white !important;
}

.btn-outline-info:hover {
    background: #0dcaf0 !important;
    color: white !important;
}

.btn-outline-warning:hover {
    background: #ffc107 !important;
    color: #000 !important;
}

.btn-outline-danger:hover {
    background: #dc3545 !important;
    color: white !important;
}

.btn-outline-secondary:hover {
    background: #6c757d !important;
    color: white !important;
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
            <h1 class="h3 mb-4">
                Teacher Dashboard
            </h1>
        </div>
    </div>

    <!-- 4. Add the academic year dropdown to the dashboard UI (above the stats cards): -->
    <div class="row mb-3">
      <div class="col-12">
        <form method="get" class="d-flex align-items-center">
                          <label for="academic_period_id" class="me-2 fw-bold">Academic Period:</label>
                <select name="academic_period_id" id="academic_period_id" class="form-select w-auto me-2" onchange="this.form.submit()">
            <?php foreach ($all_years as $year): ?>
              <option value="<?= $year['id'] ?>" <?= $selected_period_id == $year['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($year['academic_year']) ?> - <?= htmlspecialchars($year['semester_name']) ?><?= !$year['is_active'] ? ' (Inactive)' : '' ?>
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
            <a href="students.php" class="text-decoration-none" tabindex="0" aria-label="View all students">
            <div class="stats-card students-card hoverable" tabindex="0">
                <div class="d-flex justify-content-center align-items-center mb-2">
                    <i class="bi bi-people stats-icon stats-users"></i>
                    <span data-bs-toggle="tooltip" title="All students enrolled in your courses.">
                        <i class="bi bi-info-circle stats-tooltip"></i>
                    </span>
                    <span class="stats-trend <?php echo $enrollment_trend >= 0 ? 'up' : 'down'; ?>" title="<?php echo $enrollment_trend >= 0 ? 'Up' : 'Down'; ?> <?php echo abs($enrollment_trend); ?> this week">
                        <i class="bi bi-arrow-<?php echo $enrollment_trend >= 0 ? 'up' : 'down'; ?>"></i><?php echo $enrollment_trend >= 0 ? '+' : ''; ?><?php echo $enrollment_trend; ?>
                    </span>
                </div>
                <h3 class="display-6 fw-bold mb-1" aria-label="Total Students"><?php echo $stats['total_students']; ?></h3>
                <div class="text-muted mb-1">Total Students</div>
                <small class="text-secondary">Enrolled in your courses</small>
                <div class="mt-2" style="height: 0.7rem;"></div>
                <div class="mt-2">
                    <button class="btn btn-outline-success btn-sm view-details-btn" tabindex="-1">View Details</button>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="courses.php" class="text-decoration-none" tabindex="0" aria-label="View all courses">
            <div class="stats-card courses-card hoverable" tabindex="0">
                <div class="d-flex justify-content-center align-items-center mb-2">
                    <i class="bi bi-book stats-icon stats-courses"></i>
                    <span data-bs-toggle="tooltip" title="All courses you have created.">
                        <i class="bi bi-info-circle stats-tooltip"></i>
                    </span>
                    <span class="stats-trend <?php echo $course_trend >= 0 ? 'up' : 'down'; ?>" title="<?php echo $course_trend >= 0 ? 'Up' : 'Down'; ?> <?php echo abs($course_trend); ?> this week">
                        <i class="bi bi-arrow-<?php echo $course_trend >= 0 ? 'up' : 'down'; ?>"></i><?php echo $course_trend >= 0 ? '+' : ''; ?><?php echo $course_trend; ?>
                    </span>
                </div>
                <h3 class="display-6 fw-bold mb-1" aria-label="Total Courses"><?php echo $stats['total_courses']; ?></h3>
                <div class="text-muted mb-1">Total Courses</div>
                <small class="text-secondary"><?php echo $stats['total_enrollments']; ?> Enrollments</small>
                <div class="mt-2" style="height: 0.7rem;"></div>
                <div class="mt-2">
                    <button class="btn btn-outline-success btn-sm view-details-btn" tabindex="-1">View Details</button>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="assessments.php" class="text-decoration-none" tabindex="0" aria-label="View all assessments">
            <div class="stats-card assessments-card hoverable" tabindex="0">
                <div class="d-flex justify-content-center align-items-center mb-2">
                    <i class="bi bi-clipboard-check stats-icon stats-assess"></i>
                    <span data-bs-toggle="tooltip" title="Total number of assessments taken by your students.">
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
            <a href="enrollment_requests.php" class="text-decoration-none" tabindex="0" aria-label="View all enrollments">
            <div class="stats-card enrollments-card hoverable" tabindex="0">
                <div class="d-flex justify-content-center align-items-center mb-2">
                    <i class="bi bi-graph-up stats-icon stats-enroll"></i>
                    <span data-bs-toggle="tooltip" title="Number of active enrollments in your courses.">
                        <i class="bi bi-info-circle stats-tooltip"></i>
                    </span>
                    <span class="stats-trend <?php echo $enrollment_trend >= 0 ? 'up' : 'down'; ?>" title="<?php echo $enrollment_trend >= 0 ? 'Up' : 'Down'; ?> <?php echo abs($enrollment_trend); ?> this week">
                        <i class="bi bi-arrow-<?php echo $enrollment_trend >= 0 ? 'up' : 'down'; ?>"></i><?php echo $enrollment_trend >= 0 ? '+' : ''; ?><?php echo $enrollment_trend; ?>
                    </span>
                </div>
                <h3 class="display-6 fw-bold mb-1" aria-label="Active Enrollments"><?php echo $stats['total_enrollments']; ?></h3>
                <div class="text-muted mb-1">Active Enrollments</div>
                <small class="text-secondary">In your courses</small>
                <div class="mt-2" style="height: 0.7rem;"></div>
                <div class="mt-2">
                    <button class="btn btn-outline-success btn-sm view-details-btn" tabindex="-1">View Details</button>
                </div>
            </div>
            </a>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3" style="align-items: center; justify-content: center;">
                        <div class="col-md-2 mb-3">
                            <a href="courses.php" class="btn btn-outline-success w-100 quickaction-btn">
                                <i class="bi bi-book me-2"></i>Manage Courses
                            </a>
                        </div>
                        <div class="col-md-2 mb-3">
                            <a href="modules.php" class="btn btn-outline-success w-100 quickaction-btn">
                                <i class="bi bi-folder me-2"></i>Manage Modules
                            </a>
                        </div>
                        <div class="col-md-2 mb-3">
                            <a href="assessments.php" class="btn btn-outline-success w-100 quickaction-btn">
                                <i class="bi bi-clipboard-check me-2"></i>Manage Assessments
                            </a>
                        </div>
                        <div class="col-md-2 mb-3">
                            <a href="students.php" class="btn btn-outline-success w-100 quickaction-btn">
                                <i class="bi bi-people me-2"></i>My Students
                            </a>
                        </div>
                        <div class="col-md-2 mb-3">
                            <a href="announcements.php" class="btn btn-outline-success w-100 quickaction-btn">
                                <i class="bi bi-megaphone me-2"></i>Announcements
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <!-- Recent Students -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Recent Students</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recent_students as $student): ?>
                        <li class="list-group-item d-flex align-items-center">
                            <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'medium'); ?>" class="rounded-circle me-3" alt="Profile" style="width: 48px; height: 48px; object-fit: cover;">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                            </div>
                            <span class="badge ms-auto bg-success">
                                Student
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
                                        <?php echo htmlspecialchars($course['course_code']); ?>
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

<script>
// Real-time dashboard updates with comprehensive debugging
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Teacher dashboard loaded');
    console.log('👤 Current user ID:', window.currentUserId);
    console.log('👤 Current user role:', window.currentUserRole);
    console.log('🔌 Pusher client available:', typeof window.pusherClient !== 'undefined');
    
    // Listen for real-time updates via Pusher
    if (typeof window.pusherClient !== 'undefined') {
        console.log('📡 Subscribing to dashboard updates...');
        
        // Subscribe to dashboard updates for this teacher
        window.pusherClient.subscribeToDashboardUpdates();
        

        
        // Handle dashboard stats updates
        window.pusherClient.onDashboardUpdate = function(data) {
            console.log('📊 Dashboard stats updated:', data);
            
            // Update stats in real-time if updateDashboardStats function exists
            if (typeof updateDashboardStats === 'function') {
                updateDashboardStats(data.stats);
            }
        };
        
        console.log('✅ Dashboard real-time handlers configured');
    } else {
        console.warn('⚠️ Pusher client not available - real-time updates disabled');
    }
    
    // Function to update dashboard stats (can be called by Pusher)
    window.updateDashboardStats = function(stats) {
        console.log('📊 Updating dashboard stats:', stats);
        
        // Update total courses
        const totalCoursesElement = document.querySelector('#totalCourses');
        if (totalCoursesElement && stats.total_courses !== undefined) {
            totalCoursesElement.textContent = stats.total_courses;
        }
        
        // Update total students
        const totalStudentsElement = document.querySelector('#totalStudents');
        if (totalStudentsElement && stats.total_students !== undefined) {
            totalStudentsElement.textContent = stats.total_students;
        }
        
        // Update total assessments
        const totalAssessmentsElement = document.querySelector('#totalAssessments');
        if (totalAssessmentsElement && stats.total_assessments !== undefined) {
            totalAssessmentsElement.textContent = stats.total_assessments;
        }
        
        // Update average score
        const averageScoreElement = document.querySelector('#averageScore');
        if (averageScoreElement && stats.average_score !== undefined) {
            averageScoreElement.textContent = stats.average_score + '%';
        }
    };
    
    console.log('🎉 Teacher dashboard initialization complete');
});
</script>

<?php require_once '../includes/footer.php'; ?> 