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

// Get pending enrollment requests for this teacher
$stmt = $db->prepare("
    SELECT er.*, c.course_name, c.course_code, u.first_name, u.last_name, u.username, u.identifier as neust_student_id,
           er.requested_at, er.status, er.rejection_reason,
           CASE WHEN JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL THEN 1 ELSE 0 END as is_section_assigned,
           u.is_irregular,
           s.section_name, s.year_level as academic_year
    FROM enrollment_requests er
    JOIN courses c ON er.course_id = c.id
    JOIN users u ON er.student_id = u.id
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE c.teacher_id = ? AND er.status = 'pending' AND c.academic_period_id = ?
    ORDER BY er.requested_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $selected_period_id]);
$pending_enrollment_requests = $stmt->fetchAll();

// Get count of pending enrollment requests
$stmt = $db->prepare("
    SELECT COUNT(*) as pending_count
    FROM enrollment_requests er
    JOIN courses c ON er.course_id = c.id
    WHERE c.teacher_id = ? AND er.status = 'pending' AND c.academic_period_id = ?
");
$stmt->execute([$_SESSION['user_id'], $selected_period_id]);
$enrollment_requests_count = $stmt->fetch()['pending_count'];



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
<!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* System Color Variables - Matching Courses Page */
:root {
    --main-green: #2E5E4E;      /* Deep, modern green */
    --accent-green: #7DCB80;    /* Light, fresh green */
    --highlight-yellow: #FFE066;/* Softer yellow for highlights */
    --off-white: #F7FAF7;       /* Clean, soft background */
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
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Global Styles */
body {
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--off-white);
    min-height: 100vh;
}

.container-fluid {
    margin-top: 0 !important;
    padding-top: 0 !important;
    background: transparent;
}

/* Enhanced Welcome Section with Animations */
.welcome-section {
    background: var(--main-green);
    border-radius: var(--border-radius-xl);
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    opacity: 0;
    transform: translateY(-30px);
    animation: slideInDown 0.8s ease-out forwards;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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
    font-weight: 700;
    margin-bottom: 0.5rem;
    position: relative;
    z-index: 2;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    font-family: 'Inter', sans-serif;
}

.welcome-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 1.1rem;
    margin-bottom: 0;
    position: relative;
    z-index: 2;
    font-family: 'Inter', sans-serif;
}

.welcome-actions {
    position: relative;
    z-index: 1;
}

.teacher-stats-display {
    display: flex;
    gap: 2rem;
    justify-content: center;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1rem 1.5rem;
}

.stat-item {
    text-align: center;
    color: white;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: #7DCB80;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-label {
    display: block;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.9);
    margin-top: 0.25rem;
}

.academic-year-selector {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    color: white;
}

.academic-year-icon {
    color: #7DCB80;
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
    border-color: #7DCB80;
    box-shadow: 0 0 0 0.2rem rgba(125, 203, 128, 0.25);
    color: white;
}

.academic-year-select option {
    background: #2E5E4E;
    color: white;
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

.welcome-section .accent-line {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: #7DCB80;
    border-radius: 0 0 20px 20px;
}

/* Statistics Cards Styling with Animations */
.stats-card {
    transition: var(--transition);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
    opacity: 0;
    transform: translateY(20px);
    animation: fadeInUp 0.6s ease-out forwards;
}

.stats-card:nth-child(1) { animation-delay: 0.1s; }
.stats-card:nth-child(2) { animation-delay: 0.2s; }
.stats-card:nth-child(3) { animation-delay: 0.3s; }
.stats-card:nth-child(4) { animation-delay: 0.4s; }

.stats-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15) !important;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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
/* Quick Actions with Animations */
.quick-actions-card {
    opacity: 0;
    transform: translateX(-30px);
    animation: slideInLeft 0.6s ease-out 0.2s forwards;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.quickaction-btn {
    border-color: var(--main-green) !important;
    color: var(--main-green) !important;
    font-weight: 600;
    background: #fff;
    transition: var(--transition);
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
    background: linear-gradient(90deg, transparent, rgba(125, 203, 128, 0.2), transparent);
    transition: left 0.5s;
}

.quickaction-btn:hover::before {
    left: 100%;
}

.quickaction-btn:hover, .quickaction-btn:focus {
    background: var(--main-green) !important;
    color: #fff !important;
    border-color: var(--main-green) !important;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.quickaction-btn i {
    color: var(--accent-green) !important;
    transition: var(--transition);
}

.quickaction-btn:hover i {
    transform: scale(1.1);
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

/* Red dot indicator styling */
.red-dot {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 12px;
    height: 12px;
    background-color: #dc3545;
    border-radius: 50%;
    border: 2px solid white;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.7;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Activity Cards with Animations */
.activity-card {
    opacity: 0;
    transform: translateY(30px);
    animation: slideInUp 0.6s ease-out forwards;
}

.activity-card:nth-child(1) { animation-delay: 0.3s; }
.activity-card:nth-child(2) { animation-delay: 0.4s; }
.activity-card:nth-child(3) { animation-delay: 0.5s; }
.activity-card:nth-child(4) { animation-delay: 0.6s; }

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.activity-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.enrollment-requests-card {
    position: relative;
    opacity: 0;
    transform: translateY(30px);
    animation: slideInUp 0.6s ease-out 0.7s forwards;
}

.enrollment-requests-header {
    position: relative;
}

/* Card hover effects */
.card {
    transition: var(--transition);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

/* List items animation */
.list-group-item {
    transition: var(--transition);
}

.list-group-item:hover {
    background-color: var(--off-white);
    transform: translateX(5px);
}

/* Badge animations */
.badge {
    transition: var(--transition);
}

.badge:hover {
    transform: scale(1.1);
}

/* Pulse animation for red dot */
@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

.red-dot {
    animation: pulse 2s infinite;
}

/* Floating animation for decorative elements */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.floating-shapes {
    animation: float 3s ease-in-out infinite;
}

/* Pulse animation for welcome decoration */
.welcome-decoration i {
    animation: pulse 2s infinite;
}

/* Ripple effect for buttons */
.btn {
    position: relative;
    overflow: hidden;
}

.ripple {
    position: absolute;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.6);
    transform: scale(0);
    animation: ripple 600ms linear;
    pointer-events: none;
}

@keyframes ripple {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .welcome-title {
        font-size: 2rem;
    }
    
    .welcome-subtitle {
        font-size: 1rem;
    }
    
    .teacher-stats-display {
        flex-direction: column;
        gap: 1rem;
        width: 100%;
        margin-top: 1rem;
    }
    
    .academic-year-selector {
        margin-top: 1rem;
        width: 100%;
    }
    
    .quick-actions-card .row {
        justify-content: center;
    }
    
    .col-md-2 {
        flex: 0 0 auto;
        width: 48%;
        margin-bottom: 0.5rem;
    }
    
    .floating-shapes {
        display: none;
    }
    
    .welcome-decoration {
        display: none;
    }
}

@media (max-width: 576px) {
    .welcome-title {
        font-size: 1.5rem;
    }
    
    .welcome-subtitle {
        font-size: 0.9rem;
    }
    
    .stats-card .card-body {
        padding: 1rem;
    }
    
    .quickaction-btn {
        font-size: 0.9rem;
        padding: 0.5rem 0.75rem;
    }
    
    .col-md-2 {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .activity-card .card-body {
        padding: 1rem;
    }
    
    .scrollable-container {
        max-height: 300px;
    }
}

@media (max-width: 480px) {
    .container-fluid {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    
    .welcome-title {
        font-size: 1.25rem;
    }
    
    .welcome-section {
        padding: 1.5rem;
    }
    
    .stats-card .card-body {
        padding: 0.75rem;
    }
    
    .quickaction-btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.6rem;
    }
    
    .btn {
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
    }
}

</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add scroll-triggered animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    }, observerOptions);
    
    // Observe all animated elements
    const animatedElements = document.querySelectorAll('.stats-card, .activity-card, .quick-actions-card, .enrollment-requests-card, .welcome-section');
    animatedElements.forEach(el => {
        observer.observe(el);
    });
    
    // Add ripple effect to buttons
    function createRipple(event) {
        const button = event.currentTarget;
        const circle = document.createElement('span');
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
        circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
        circle.classList.add('ripple');
        
        const ripple = button.getElementsByClassName('ripple')[0];
        if (ripple) {
            ripple.remove();
        }
        
        button.appendChild(circle);
    }
    
    // Add ripple effect to all buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', createRipple);
    });
});
</script>

<div class="container-fluid">
    <div class="row">
        <!-- Removed Sidebar -->
        <!-- Main content -->
        <main class="col-12 px-md-4">
            <!-- Enhanced Welcome Section -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
                        <p class="welcome-subtitle">Ready to manage your courses and students?</p>
                        
                        <!-- Academic Year Selector -->
                        <div class="academic-year-selector d-inline-block mt-3">
                            <form method="get" class="d-flex align-items-center">
                                <i class="fas fa-calendar-alt academic-year-icon"></i>
                                <label for="academic_period_id" class="academic-year-label">Academic Year:</label>
                                <select name="academic_period_id" id="academic_period_id" class="academic-year-select" onchange="this.form.submit()">
                                    <?php foreach ($all_years as $year): ?>
                                        <option value="<?= $year['id'] ?>" <?= $selected_period_id == $year['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['academic_year'] . ' - ' . $year['semester_name']) ?><?= !$year['is_active'] ? ' (Inactive)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="welcome-actions">
                            <div class="teacher-stats-display">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $stats['total_courses'] ?? 0; ?></span>
                                    <span class="stat-label">Courses</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $stats['total_students'] ?? 0; ?></span>
                                    <span class="stat-label">Students</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="welcome-decoration">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="floating-shapes"></div>
                <div class="accent-line"></div>
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
            <div class="card bg-white quick-actions-card">
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
            <div class="card bg-white activity-card">
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
            <div class="card bg-white activity-card">
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
            <div class="card bg-white activity-card">
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

    <!-- Enrollment Requests -->
    <div class="row">
        <div class="col-12">
            <div class="card bg-white enrollment-requests-card">
                <div class="card-header d-flex justify-content-between align-items-center enrollment-requests-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>Enrollment Requests
                        <?php if ($enrollment_requests_count > 0): ?>
                            <span class="red-dot"></span>
                        <?php endif; ?>
                    </h5>
                    <a href="enrollment_requests.php" class="btn btn-sm btn-primary">
                        View All
                        <?php if ($enrollment_requests_count > 0): ?>
                            <span class="badge bg-danger ms-1"><?php echo $enrollment_requests_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_enrollment_requests)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                            <p class="mb-0">No pending enrollment requests</p>
                        </div>
                    <?php else: ?>
                        <div class="scrollable-container">
                            <?php foreach ($pending_enrollment_requests as $request): ?>
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                            <?php if ($request['is_irregular']): ?>
                                                <span class="badge bg-warning text-dark ms-2">Irregular</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="text-muted mb-1">
                                            <i class="bi bi-book me-1"></i>
                                            <?php echo htmlspecialchars($request['course_name']); ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($request['course_code']); ?>)</small>
                                        </p>
                                        <small class="text-muted">
                                            <i class="bi bi-person me-1"></i>
                                            <?php echo htmlspecialchars($request['username']); ?>
                                            <?php if ($request['neust_student_id']): ?>
                                                - ID: <?php echo htmlspecialchars($request['neust_student_id']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo formatDate($request['requested_at']); ?>
                                        </small>
                                        <?php if ($request['section_name']): ?>
                                            <small class="text-info d-block">
                                                <i class="bi bi-people me-1"></i>
                                                <?php echo htmlspecialchars($request['section_name']); ?>
                                            </small>
                                        <?php endif; ?>
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
        </main>
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
    
    // Function to check enrollment requests and update red dot
    function checkEnrollmentRequests() {
        console.log('🔍 Checking enrollment requests...');
        
        // Make AJAX request to get enrollment requests count
        fetch('<?php echo SITE_URL; ?>/ajax_get_enrollment_requests.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const count = data.pending_count || 0;
                    console.log('📊 Enrollment requests count:', count);
                    
                    // Update red dot visibility
                    const redDot = document.querySelector('.enrollment-requests-card .red-dot');
                    const badge = document.querySelector('.enrollment-requests-card .badge');
                    
                    if (count > 0) {
                        // Show red dot
                        if (redDot) {
                            redDot.style.display = 'block';
                        } else {
                            // Create red dot if it doesn't exist
                            const header = document.querySelector('.enrollment-requests-header h5');
                            if (header) {
                                const newRedDot = document.createElement('span');
                                newRedDot.className = 'red-dot';
                                header.appendChild(newRedDot);
                            }
                        }
                        
                        // Update or create badge
                        if (badge) {
                            badge.textContent = count;
                        } else {
                            const viewAllBtn = document.querySelector('.enrollment-requests-card .btn-primary');
                            if (viewAllBtn) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'badge bg-danger ms-1';
                                newBadge.textContent = count;
                                viewAllBtn.appendChild(newBadge);
                            }
                        }
                    } else {
                        // Hide red dot and badge
                        if (redDot) {
                            redDot.style.display = 'none';
                        }
                        if (badge) {
                            badge.remove();
                        }
                    }
                }
            })
            .catch(error => {
                console.error('❌ Error checking enrollment requests:', error);
            });
    }
    
    // Check enrollment requests on page load
    checkEnrollmentRequests();
    
    // Check enrollment requests every 30 seconds
    setInterval(checkEnrollmentRequests, 30000);
    
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