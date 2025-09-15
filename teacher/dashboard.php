<?php
$page_title = 'Teacher Dashboard';
require_once '../includes/header.php';
require_once '../config/pusher.php';
require_once '../includes/pusher_notifications.php';
requireRole('teacher');

// 1. Fetch all academic periods for the dropdown (active first)
$ay_stmt = $db->prepare('SELECT id, academic_year, semester_name, is_active FROM academic_periods ORDER BY is_active DESC, academic_year DESC, semester_name');
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
/* Statistics Cards Styling */
.stats-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.stats-icon {
    width: 60px;
    height: 60px;
    transition: all 0.3s ease;
}

.stats-card:hover .stats-icon {
    transform: scale(1.1);
}

.stats-primary {
    background: #0d6efd;
    border-left: 4px solid #0a58ca;
    color: white;
}

.stats-success {
    background: #198754;
    border-left: 4px solid #146c43;
    color: white;
}

.stats-info {
    background: #0dcaf0;
    border-left: 4px solid #0aa2c0;
    color: white;
}

.stats-warning {
    background: #ffc107;
    border-left: 4px solid #ffca2c;
    color: #000;
}

.stats-secondary {
    background: #6c757d;
    border-left: 4px solid #5c636a;
    color: white;
}

.stats-danger {
    background: #dc3545;
    border-left: 4px solid #b02a37;
    color: white;
}

.stats-danger-alt {
    background: #e91e63;
    border-left: 4px solid #d81b60;
    color: white;
}

.stats-purple {
    background: #9c27b0;
    border-left: 4px solid #7b1fa2;
    color: white;
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

/* Scrollable Container Styles */
.scrollable-container {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 10px;
}

.scrollable-container::-webkit-scrollbar {
    width: 8px;
}

.scrollable-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.scrollable-container::-webkit-scrollbar-thumb {
    background: #2E5E4E;
    border-radius: 4px;
}

.scrollable-container::-webkit-scrollbar-thumb:hover {
    background: #7DCB80;
}

/* Firefox scrollbar styling */
.scrollable-container {
    scrollbar-width: thin;
    scrollbar-color: #2E5E4E #f1f1f1;
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
            <div class="card stats-card stats-primary border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-people fs-4"></i>
                </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?php echo $stats['total_students']; ?></h3>
                    <p class="text-white mb-0 small fw-medium">Total Students</p>
                    <small class="text-white-50">Enrolled in your courses</small>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="courses.php" class="text-decoration-none" tabindex="0" aria-label="View all courses">
            <div class="card stats-card stats-success border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-book fs-4"></i>
                </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?php echo $stats['total_courses']; ?></h3>
                    <p class="text-white mb-0 small fw-medium">Total Courses</p>
                    <small class="text-white-50"><?php echo $stats['total_enrollments']; ?> Enrollments</small>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="assessments.php" class="text-decoration-none" tabindex="0" aria-label="View all assessments">
            <div class="card stats-card stats-info border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-clipboard-check fs-4"></i>
                </div>
                </div>
                    <h3 class="fw-bold mb-1 text-white"><?php echo $stats['total_assessments_taken']; ?></h3>
                    <p class="text-white mb-0 small fw-medium">Assessments Taken</p>
                    <small class="text-white-50">Avg Score: <?php echo number_format($stats['average_score'] ?? 0, 1); ?>%</small>
                </div>
            </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="enrollment_requests.php" class="text-decoration-none" tabindex="0" aria-label="View all enrollments">
            <div class="card stats-card stats-warning border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-graph-up fs-4"></i>
                </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?php echo $stats['total_enrollments']; ?></h3>
                    <p class="text-white mb-0 small fw-medium">Active Enrollments</p>
                    <small class="text-white-50">In your courses</small>
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
                <div class="card-body p-0">
                    <div class="scrollable-container">
                    <ul class="list-group list-group-flush">
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
        </div>

        <!-- Recent Courses -->
        <div class="col-lg-6 mb-4">
            <div class="card recent-activity-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Courses</h5>
                    <a href="courses.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                        <?php if (empty($recent_courses)): ?>
                        <p class="text-muted">No courses found.</p>
                        <?php else: ?>
                        <div class="scrollable-container">
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
                        </div>
                        <?php endif; ?>
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
                        <p class="text-muted">No announcements found.</p>
                    <?php else: ?>
                        <div class="scrollable-container">
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
        const recentStudentsList = document.querySelector('.scrollable-container');
        const recentCoursesList = document.querySelectorAll('.scrollable-container')[1];
        
        // Add smooth scrolling behavior
        if (recentStudentsList) {
            recentStudentsList.style.scrollBehavior = 'smooth';
        }
        
        if (recentCoursesList) {
            recentCoursesList.style.scrollBehavior = 'smooth';
        }
    }
    
    // Initialize enhanced scrolling
    enhanceScrolling();
});
</script>

<?php require_once '../includes/footer.php'; ?> 