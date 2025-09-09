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

/* Recent Activity Scrolling Improvements */
.recent-activity-card {
    height: 400px;
    display: flex;
    flex-direction: column;
}

.recent-activity-card .card-body {
    flex: 1;
    overflow: hidden;
    padding: 0;
}

.recent-activity-list {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: #c1c1c1 #f1f1f1;
}

/* Custom scrollbar for webkit browsers */
.recent-activity-list::-webkit-scrollbar {
    width: 6px;
}

.recent-activity-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.recent-activity-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
    transition: background 0.3s ease;
}

.recent-activity-list::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Recent Students specific styling */
.recent-students-list {
    max-height: 320px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: #4caf50 #e8f5e8;
}

.recent-students-list::-webkit-scrollbar {
    width: 6px;
}

.recent-students-list::-webkit-scrollbar-track {
    background: #e8f5e8;
    border-radius: 3px;
}

.recent-students-list::-webkit-scrollbar-thumb {
    background: #4caf50;
    border-radius: 3px;
    transition: background 0.3s ease;
}

.recent-students-list::-webkit-scrollbar-thumb:hover {
    background: #388e3c;
}

/* Recent Courses specific styling */
.recent-courses-list {
    max-height: 320px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: #2196f3 #e3f2fd;
}

.recent-courses-list::-webkit-scrollbar {
    width: 6px;
}

.recent-courses-list::-webkit-scrollbar-track {
    background: #e3f2fd;
    border-radius: 3px;
}

.recent-courses-list::-webkit-scrollbar-thumb {
    background: #2196f3;
    border-radius: 3px;
    transition: background 0.3s ease;
}

.recent-courses-list::-webkit-scrollbar-thumb:hover {
    background: #1976d2;
}

/* Smooth hover effects for list items */
.recent-activity-list .list-group-item {
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.recent-activity-list .list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: #4caf50;
    transform: translateX(2px);
}

/* Course item hover effects */
.course-item {
    transition: all 0.3s ease;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    border: 1px solid transparent;
}

.course-item:hover {
    background-color: #f8f9fa;
    border-color: #e0e0e0;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Mobile responsiveness */
@media (max-width: 991.98px) {
    .recent-activity-card {
        height: 300px;
    }
    
    .recent-students-list,
    .recent-courses-list {
        max-height: 220px;
    }
}

@media (max-width: 575.98px) {
    .recent-activity-card {
        height: 250px;
    }
    
    .recent-students-list,
    .recent-courses-list {
        max-height: 170px;
    }
}

/* Recent Announcements Scrolling Improvements */
.recent-announcements-card {
    height: 450px;
    display: flex;
    flex-direction: column;
}

.recent-announcements-card .card-body {
    flex: 1;
    overflow: hidden;
    padding: 0;
}

.recent-announcements-list {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: #6f42c1 #f3e5f5;
    padding: 20px;
}

/* Custom scrollbar for announcements */
.recent-announcements-list::-webkit-scrollbar {
    width: 6px;
}

.recent-announcements-list::-webkit-scrollbar-track {
    background: #f3e5f5;
    border-radius: 3px;
}

.recent-announcements-list::-webkit-scrollbar-thumb {
    background: #6f42c1;
    border-radius: 3px;
    transition: background 0.3s ease;
}

.recent-announcements-list::-webkit-scrollbar-thumb:hover {
    background: #5a2d91;
}

/* Announcement item styling */
.announcement-item {
    transition: all 0.3s ease;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    border: 1px solid transparent;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.announcement-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #6f42c1, #e83e8c);
    transition: width 0.3s ease;
}

.announcement-item:hover {
    background: #f8f9fa;
    border-color: #e0e0e0;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(111, 66, 193, 0.15);
}

.announcement-item:hover::before {
    width: 6px;
}

.announcement-item:last-child {
    margin-bottom: 0;
}

.announcement-title {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    font-size: 1.1rem;
    line-height: 1.4;
}

.announcement-content {
    color: #6c757d;
    margin-bottom: 12px;
    line-height: 1.5;
    font-size: 0.95rem;
}

.announcement-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    color: #6c757d;
}

.announcement-author {
    font-weight: 500;
    color: #6f42c1;
}

.announcement-date {
    color: #adb5bd;
}

/* Empty state styling */
.announcements-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.announcements-empty-state i {
    font-size: 3rem;
    color: #dee2e6;
    margin-bottom: 16px;
}

.announcements-empty-state h6 {
    color: #495057;
    margin-bottom: 8px;
}

/* Mobile responsiveness for announcements */
@media (max-width: 991.98px) {
    .recent-announcements-card {
        height: 350px;
    }
    
    .recent-announcements-list {
        padding: 16px;
    }
    
    .announcement-item {
        padding: 12px;
        margin-bottom: 12px;
    }
}

@media (max-width: 575.98px) {
    .recent-announcements-card {
        height: 300px;
    }
    
    .recent-announcements-list {
        padding: 12px;
    }
    
    .announcement-item {
        padding: 10px;
        margin-bottom: 10px;
    }
    
    .announcement-title {
        font-size: 1rem;
    }
    
    .announcement-content {
        font-size: 0.9rem;
    }
    
    .announcement-meta {
        font-size: 0.8rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
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
            <div class="card recent-activity-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Recent Students</h5>
                    <a href="students.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush recent-students-list">
                        <?php if (empty($recent_students)): ?>
                            <li class="list-group-item text-center text-muted py-4">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>
                                No students found
                            </li>
                        <?php else: ?>
                            <?php foreach ($recent_students as $student): ?>
                            <li class="list-group-item d-flex align-items-center">
                                <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'medium'); ?>" class="rounded-circle me-3" alt="Profile" style="width: 48px; height: 48px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                </div>
                                <span class="badge bg-success">
                                    Student
                                </span>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Recent Courses -->
        <div class="col-lg-6 mb-4">
            <div class="card recent-activity-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Courses</h5>
                    <a href="courses.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="recent-courses-list">
                        <?php if (empty($recent_courses)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-book fs-1 d-block mb-2"></i>
                                No courses found
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_courses as $course): ?>
                                <div class="course-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                            <small class="text-muted d-block">
                                                <?php echo htmlspecialchars($course['course_code']); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?php echo formatDate($course['created_at']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end ms-3">
                                            <span class="badge bg-primary mb-1"><?php echo $course['student_count']; ?> students</span>
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

    <!-- Recent Announcements -->
    <div class="row">
        <div class="col-12">
            <div class="card recent-announcements-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Announcements</h5>
                    <a href="announcements.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_announcements)): ?>
                        <div class="announcements-empty-state">
                            <i class="bi bi-megaphone"></i>
                            <h6>No Announcements</h6>
                            <p>No recent announcements found.</p>
                        </div>
                    <?php else: ?>
                        <div class="recent-announcements-list">
                            <?php foreach ($recent_announcements as $announcement): ?>
                                <div class="announcement-item">
                                    <h6 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <p class="announcement-content"><?php echo htmlspecialchars(substr($announcement['content'], 0, 150)) . '...'; ?></p>
                                    <div class="announcement-meta">
                                        <span class="announcement-author">
                                            <i class="bi bi-person-circle me-1"></i>
                                            By <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                        </span>
                                        <span class="announcement-date">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?php echo formatDate($announcement['created_at']); ?>
                                        </span>
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
    
    // Enhanced scrolling behavior for Recent Activity sections
    function enhanceScrolling() {
        const recentStudentsList = document.querySelector('.recent-students-list');
        const recentCoursesList = document.querySelector('.recent-courses-list');
        
        // Add smooth scrolling behavior
        if (recentStudentsList) {
            recentStudentsList.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const studentsCard = recentStudentsList.closest('.card');
            if (studentsCard) {
                addScrollIndicators(recentStudentsList, studentsCard, 'students');
            }
        }
        
        if (recentCoursesList) {
            recentCoursesList.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const coursesCard = recentCoursesList.closest('.card');
            if (coursesCard) {
                addScrollIndicators(recentCoursesList, coursesCard, 'courses');
            }
        }
    }
    
    // Add scroll indicators to show when content is scrollable
    function addScrollIndicators(scrollContainer, cardContainer, type) {
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = `scroll-indicator scroll-indicator-${type}`;
        scrollIndicator.innerHTML = `
            <div class="scroll-indicator-content">
                <i class="bi bi-chevron-up scroll-indicator-top"></i>
                <i class="bi bi-chevron-down scroll-indicator-bottom"></i>
            </div>
        `;
        
        // Add CSS for scroll indicators
        const style = document.createElement('style');
        style.textContent = `
            .scroll-indicator {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                z-index: 10;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .scroll-indicator-content {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .scroll-indicator i {
                background: rgba(0,0,0,0.6);
                color: white;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
            }
            
            .scroll-indicator-students i {
                background: rgba(76, 175, 80, 0.8);
            }
            
            .scroll-indicator-courses i {
                background: rgba(33, 150, 243, 0.8);
            }
            
            .scroll-indicator.show {
                opacity: 1;
            }
            
            .scroll-indicator-top.hide,
            .scroll-indicator-bottom.hide {
                opacity: 0.3;
            }
        `;
        document.head.appendChild(style);
        
        cardContainer.style.position = 'relative';
        cardContainer.appendChild(scrollIndicator);
        
        // Update scroll indicators based on scroll position
        function updateScrollIndicators() {
            const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
            const isAtTop = scrollContainer.scrollTop === 0;
            const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
            
            if (isScrollable) {
                scrollIndicator.classList.add('show');
                scrollIndicator.querySelector('.scroll-indicator-top').classList.toggle('hide', isAtTop);
                scrollIndicator.querySelector('.scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
            } else {
                scrollIndicator.classList.remove('show');
            }
        }
        
        // Initial check
        updateScrollIndicators();
        
        // Update on scroll
        scrollContainer.addEventListener('scroll', updateScrollIndicators);
        
        // Update on resize
        window.addEventListener('resize', updateScrollIndicators);
    }
    
    // Initialize enhanced scrolling
    enhanceScrolling();
    
    // Enhanced scrolling behavior for Recent Announcements
    function enhanceAnnouncementsScrolling() {
        const announcementsList = document.querySelector('.recent-announcements-list');
        
        if (announcementsList) {
            // Add smooth scrolling behavior
            announcementsList.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const announcementsCard = announcementsList.closest('.card');
            if (announcementsCard) {
                addAnnouncementsScrollIndicators(announcementsList, announcementsCard);
            }
        }
    }
    
    // Add scroll indicators to announcements
    function addAnnouncementsScrollIndicators(scrollContainer, cardContainer) {
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = 'scroll-indicator scroll-indicator-announcements';
        scrollIndicator.innerHTML = `
            <div class="scroll-indicator-content">
                <i class="bi bi-chevron-up scroll-indicator-top"></i>
                <i class="bi bi-chevron-down scroll-indicator-bottom"></i>
            </div>
        `;
        
        // Add CSS for announcements scroll indicators
        const style = document.createElement('style');
        style.textContent = `
            .scroll-indicator-announcements i {
                background: rgba(111, 66, 193, 0.8);
            }
        `;
        document.head.appendChild(style);
        
        cardContainer.style.position = 'relative';
        cardContainer.appendChild(scrollIndicator);
        
        // Update scroll indicators based on scroll position
        function updateAnnouncementsScrollIndicators() {
            const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
            const isAtTop = scrollContainer.scrollTop === 0;
            const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
            
            if (isScrollable) {
                scrollIndicator.classList.add('show');
                scrollIndicator.querySelector('.scroll-indicator-top').classList.toggle('hide', isAtTop);
                scrollIndicator.querySelector('.scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
            } else {
                scrollIndicator.classList.remove('show');
            }
        }
        
        // Initial check
        updateAnnouncementsScrollIndicators();
        
        // Update on scroll
        scrollContainer.addEventListener('scroll', updateAnnouncementsScrollIndicators);
        
        // Update on resize
        window.addEventListener('resize', updateAnnouncementsScrollIndicators);
    }
    
    // Initialize enhanced announcements scrolling
    enhanceAnnouncementsScrolling();
});
</script>

<?php require_once '../includes/footer.php'; ?> 