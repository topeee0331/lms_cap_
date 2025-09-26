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
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* Enhanced Admin Dashboard Styling - Inspired by Teacher Dashboard */
:root {
    --main-green: #2E5E4E;
    --accent-green: #7DCB80;
    --highlight-yellow: #FFE066;
    --off-white: #F7FAF7;
    --white: #FFFFFF;
    --text-dark: #2c3e50;
    --text-muted: #6c757d;
    --border-light: #e9ecef;
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 8px rgba(0,0,0,0.12);
    --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
    --border-radius: 8px;
    --border-radius-lg: 12px;
    --border-radius-xl: 20px;
    --transition: all 0.3s ease;
}

/* Dashboard Background */
.dashboard-container {
    background: var(--off-white);
    min-height: 100vh;
}

.dashboard-content {
    background: transparent;
}

/* Enhanced Welcome Section - Inspired by Teacher Dashboard */
.welcome-section {
    background: var(--main-green);
    border-radius: var(--border-radius-xl);
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}

.welcome-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
    pointer-events: none;
}

.welcome-title {
    color: white;
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    position: relative;
    z-index: 1;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    font-family: 'Inter', sans-serif;
}

.welcome-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 1.1rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
}

.welcome-actions {
    position: relative;
    z-index: 1;
}

/* Integrated Stats Display */
.admin-stats-display {
    display: flex;
    gap: 2rem;
    justify-content: center;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(10px);
    border-radius: var(--border-radius-xl);
    padding: 1rem 1.5rem;
    margin-top: 1rem;
}

.stat-item {
    text-align: center;
    color: white;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: var(--accent-green);
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-label {
    display: block;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.9);
    margin-top: 0.25rem;
}

/* Academic Period Selector */
.academic-year-selector {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    color: white;
    display: inline-block;
    margin-top: 1rem;
}

.academic-year-icon {
    color: var(--accent-green);
    margin-right: 0.5rem;
    font-size: 1.1rem;
}

.academic-year-label {
    color: rgba(255,255,255,0.9);
    margin-right: 0.75rem;
    font-weight: 600;
    margin-bottom: 0;
}

.academic-year-select {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    border-radius: 15px;
    padding: 0.5rem 1rem;
    font-weight: 600;
}

.academic-year-select:focus {
    background: rgba(255,255,255,0.3);
    border-color: var(--accent-green);
    box-shadow: 0 0 0 0.2rem rgba(125, 203, 128, 0.25);
    color: white;
}

.academic-year-select option {
    background: var(--main-green);
    color: white;
}

/* Decorative Elements */
.welcome-decoration {
    position: absolute;
    top: 25px;
    right: 20px;
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.welcome-decoration i {
    font-size: 1.5rem;
    color: rgba(255,255,255,0.8);
}

.floating-shapes {
    position: absolute;
    top: 20px;
    right: 100px;
    width: 80px;
    height: 80px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
    z-index: 0;
}

.welcome-section .accent-line {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--accent-green);
    border-radius: 0 0 var(--border-radius-xl) var(--border-radius-xl);
}

/* Academic Period Selector */
.period-selector {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    margin-bottom: 2rem;
}

.period-selector label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.period-selector .form-select {
    border: 2px solid var(--border-light);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-weight: 500;
    transition: var(--transition);
}

.period-selector .form-select:focus {
    border-color: var(--main-green);
    box-shadow: 0 0 0 0.2rem rgba(46,94,78,0.15);
}

/* Statistics Cards Styling - Inspired by Teacher Dashboard */
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

.stats-card .card-body {
    padding: 1.5rem;
    text-align: center;
}

.stats-card h3 {
    font-family: 'Inter', sans-serif;
    font-weight: 800;
    font-size: 2.2rem;
    margin-bottom: 0.5rem;
    color: white;
}

.stats-card p {
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.stats-card small {
    color: rgba(255,255,255,0.8);
    font-size: 0.85rem;
}


/* Quick Actions Section - Inspired by Teacher Dashboard */
.quick-actions-card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.quick-actions-card .card-header {
    background: var(--main-green);
    color: var(--white);
    border: none;
    padding: 1.25rem 1.5rem;
}

.quick-actions-card .card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    margin: 0;
    font-size: 1.1rem;
}

.quick-actions-card .card-body {
    padding: 2rem;
}

.quickaction-btn {
    border-color: var(--main-green) !important;
    color: var(--main-green) !important;
    font-weight: 600;
    background: #fff;
    transition: background 0.18s, color 0.18s, border 0.18s;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Inter', sans-serif;
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius);
}

.quickaction-btn:hover, .quickaction-btn:focus {
    background: var(--main-green) !important;
    color: #fff !important;
    border-color: var(--main-green) !important;
    transform: translateY(-1px);
}

.quickaction-btn i {
    color: var(--accent-green) !important;
    margin-right: 0.5rem;
}

.quickaction-btn:hover i {
    color: #fff !important;
}

/* Activity Cards - Inspired by Teacher Dashboard */
.recent-activity-card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
    height: 100%;
}

.recent-activity-card .card-header {
    background: #f8f9fa;
    border-bottom: 2px solid var(--accent-green);
    padding: 1.25rem 1.5rem;
}

.recent-activity-card .card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 1rem;
}

.recent-activity-card .btn-sm {
    background: var(--main-green);
    border: none;
    border-radius: var(--border-radius);
    padding: 0.5rem 1rem;
    font-weight: 500;
    color: white;
    transition: var(--transition);
}

.recent-activity-card .btn-sm:hover {
    background: var(--accent-green);
    color: var(--main-green);
    transform: translateY(-1px);
}

/* Recent Announcements Card */
.recent-announcements-card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.recent-announcements-card .card-header {
    background: #f8f9fa;
    border-bottom: 2px solid var(--accent-green);
    padding: 1.25rem 1.5rem;
}

.recent-announcements-card .card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 1rem;
}

.recent-announcements-card .btn-sm {
    background: var(--main-green);
    border: none;
    border-radius: var(--border-radius);
    padding: 0.5rem 1rem;
    font-weight: 500;
    color: white;
    transition: var(--transition);
}

.recent-announcements-card .btn-sm:hover {
    background: var(--accent-green);
    color: var(--main-green);
    transform: translateY(-1px);
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
    background: var(--main-green);
    border-radius: 4px;
}

.scrollable-container::-webkit-scrollbar-thumb:hover {
    background: var(--accent-green);
}

.scrollable-container {
    scrollbar-width: thin;
    scrollbar-color: var(--main-green) #f1f1f1;
}

/* List Items */
.list-group-item {
    border: none;
    border-bottom: 1px solid var(--border-light);
    padding: 1.25rem 2rem;
    transition: var(--transition);
}

.list-group-item:hover {
    background: #f8f9fa;
}

.list-group-item:last-child {
    border-bottom: none;
}

/* Profile Images */
.rounded-circle {
    border: 3px solid var(--accent-green);
    transition: var(--transition);
}

.list-group-item:hover .rounded-circle {
    border-color: var(--main-green);
    transform: scale(1.05);
}

/* Badges */
.badge {
    font-weight: 600;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-header h1 {
        font-size: 1.8rem;
    }
    
    .stats-card .card-body {
        padding: 1.25rem 1rem;
    }
    
    .stats-card h3 {
        font-size: 1.75rem;
    }
    
    .quick-actions-card .card-body {
        padding: 1.25rem;
    }
    
    .activity-card .card-header {
        padding: 1rem 1.25rem;
    }
    
    .period-selector {
        padding: 1rem;
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

<div class="dashboard-container">
    <div class="dashboard-content">
        <div class="container-fluid">
            <!-- Enhanced Welcome Section - Inspired by Teacher Dashboard -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="welcome-title">Admin Dashboard</h1>
                        <p class="welcome-subtitle">Manage your learning management system with comprehensive analytics and controls</p>
                        
                        <!-- Academic Year Selector -->
                        <div class="academic-year-selector">
                            <form method="get" class="d-flex align-items-center">
                                <i class="fas fa-calendar-alt academic-year-icon"></i>
                                <label for="academic_period_id" class="academic-year-label">Academic Period:</label>
                                <select name="academic_period_id" id="academic_period_id" class="academic-year-select" onchange="this.form.submit()">
                                    <?php foreach ($all_periods as $period): ?>
                                        <option value="<?= $period['id'] ?>" <?= $selected_period_id == $period['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($period['period_name']) ?><?= !$period['is_active'] ? ' (Inactive)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="admin-stats-display">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></span>
                                <span class="stat-label">Total Users</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $stats['total_courses'] ?? 0; ?></span>
                                <span class="stat-label">Courses</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $stats['total_enrollments'] ?? 0; ?></span>
                                <span class="stat-label">Enrollments</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="welcome-decoration">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="floating-shapes"></div>
                <div class="accent-line"></div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <a href="users.php" class="text-decoration-none" tabindex="0" aria-label="View all users">
                        <div class="card stats-card stats-primary border-0 shadow-sm h-100">
                            <div class="card-body text-center p-3">
                                <div class="d-flex align-items-center justify-content-center mb-3">
                                    <div class="stats-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-people fs-4"></i>
                                    </div>
                                </div>
                                <h3 class="fw-bold mb-1 text-white"><?php echo $stats['total_users']; ?></h3>
                                <p class="text-white mb-0 small fw-medium">Total Users</p>
                                <small class="text-white-50"><?php echo $stats['total_students']; ?> Students, <?php echo $stats['total_teachers']; ?> Teachers</small>
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
                    <a href="enrollments.php" class="text-decoration-none" tabindex="0" aria-label="View all enrollments">
                        <div class="card stats-card stats-warning border-0 shadow-sm h-100">
                            <div class="card-body text-center p-3">
                                <div class="d-flex align-items-center justify-content-center mb-3">
                                    <div class="stats-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-graph-up fs-4"></i>
                                    </div>
                                </div>
                                <h3 class="fw-bold mb-1 text-white"><?php echo $stats['total_enrollments']; ?></h3>
                                <p class="text-white mb-0 small fw-medium">Active Enrollments</p>
                                <small class="text-white-50">Active Students</small>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card quick-actions-card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3" style="align-items: center; justify-content: center;">
                                <div class="col-md-2 mb-3">
                                    <a href="users.php" class="btn btn-outline-success w-100 quickaction-btn">
                                        <i class="bi bi-people me-2"></i>Manage Users
                                    </a>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <a href="courses.php" class="btn btn-outline-success w-100 quickaction-btn">
                                        <i class="bi bi-book me-2"></i>Manage Courses
                                    </a>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <a href="sections.php" class="btn btn-outline-success w-100 quickaction-btn">
                                        <i class="bi bi-journal-text me-2"></i>Manage Sections
                                    </a>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <a href="academic_periods.php" class="btn btn-outline-success w-100 quickaction-btn">
                                        <i class="bi bi-calendar me-2"></i>Academic Periods
                                    </a>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <a href="badges.php" class="btn btn-outline-success w-100 quickaction-btn">
                                        <i class="bi bi-award me-2"></i>Badge Management
                                    </a>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <a href="announcements.php" class="btn btn-outline-success w-100 quickaction-btn">
                                        <i class="bi bi-megaphone me-2"></i>Announcements
                                    </a>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <a href="unlock_accounts.php" class="btn btn-outline-warning w-100 quickaction-btn">
                                        <i class="bi bi-shield-lock me-2"></i>Security
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
                    <div class="card recent-activity-card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Recent Users</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="scrollable-container">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_users as $user): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <img src="<?php echo getProfilePictureUrl($user['profile_picture'] ?? null, 'medium'); ?>" class="rounded-circle me-3" alt="Profile" style="width: 48px; height: 48px; object-fit: cover;">
                                        <div class="flex-grow-1">
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
                                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($course['course_name']); ?></h6>
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
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($announcement['title']); ?></h6>
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
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 