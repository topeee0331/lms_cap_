<?php
$page_title = 'Home';
require_once 'includes/header.php';
?>
<style>
:root {
    --main-green: #2E5E4E;      /* Deep, modern green */
    --accent-green: #7DCB80;    /* Light, fresh green */
    --highlight-yellow: #FFE066;/* Softer yellow for highlights */
    --off-white: #F7FAF7;       /* Clean, soft background */
    --white: #FFFFFF;
}
body {
    background: linear-gradient(120deg, var(--off-white) 0%, var(--accent-green) 100%);
    min-height: 100vh;
    position: relative;
}
/* Subtle pattern overlay */
.home-bg-pattern {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    width: 100vw;
    height: 100vh;
    z-index: 0;
    pointer-events: none;
    opacity: 0.13;
    background: url('data:image/svg+xml;utf8,<svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="30" cy="30" r="1.5" fill="%237DCB80"/><circle cx="10" cy="50" r="1" fill="%23FDD744"/><circle cx="50" cy="10" r="1" fill="%232E5E4E"/></svg>');
    background-repeat: repeat;
}
/* Abstract SVG shapes overlay */
.home-bg-svg {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    width: 100vw;
    height: 100vh;
    z-index: 1;
    pointer-events: none;
}
.main-content-home {
    position: relative;
    z-index: 2;
}
.hero-section {
    background: linear-gradient(120deg, var(--main-green) 60%, var(--accent-green) 100%);
    color: var(--white);
    padding: 4.5rem 0 3.5rem 0;
    border-radius: 0 0 2.5rem 2.5rem;
    box-shadow: 0 8px 32px rgba(46,94,78,0.10);
    margin-bottom: 2.5rem;
    position: relative;
    overflow: hidden;
}
.hero-section h1 {
    color: var(--highlight-yellow);
    font-weight: 900;
    letter-spacing: 1px;
}
.hero-section .lead {
    color: #eafbe7;
    font-size: 1.25rem;
}
.hero-section .btn-light {
    background: var(--highlight-yellow);
    color: var(--main-green);
    font-weight: 700;
    border: none;
    box-shadow: 0 2px 8px rgba(253,215,68,0.10);
    transition: background 0.2s;
}
.hero-section .btn-light:hover {
    background: #fff6b0;
    color: var(--main-green);
}
.hero-section .btn-outline-light {
    border: 2px solid var(--highlight-yellow);
    color: var(--highlight-yellow);
    font-weight: 700;
    background: transparent;
    transition: background 0.2s, color 0.2s;
}
.hero-section .btn-outline-light:hover {
    background: var(--highlight-yellow);
    color: var(--main-green);
}
.hero-section i.bi-mortarboard-fill {
    color: var(--highlight-yellow);
    text-shadow: 0 2px 8px rgba(253,215,68,0.18);
}
.stats-card {
    background: var(--white);
    border-radius: 1.2rem;
    box-shadow: 0 2px 16px rgba(46,94,78,0.08);
    padding: 2rem 1rem 1.5rem 1rem;
    margin-bottom: 1.5rem;
    border: 2px solid var(--accent-green);
    transition: box-shadow 0.2s, border 0.2s;
}
.stats-card i {
    color: var(--main-green);
}
.stats-card h3 {
    color: var(--main-green);
    font-weight: 800;
}
.stats-card p {
    color: #6c757d;
}
.card, .card-body {
    background: var(--white) !important;
    border-radius: 1.1rem !important;
}
.card-title {
    color: var(--main-green);
    font-weight: 700;
}
.btn-primary, .btn-primary:active, .btn-primary:focus {
    background: var(--main-green) !important;
    border: none !important;
    color: var(--white) !important;
    font-weight: 700;
}
.btn-primary:hover {
    background: var(--accent-green) !important;
    color: var(--main-green) !important;
}
.btn-outline-primary, .btn-outline-primary:active, .btn-outline-primary:focus {
    border: 2px solid var(--main-green) !important;
    color: var(--main-green) !important;
    background: transparent !important;
    font-weight: 700;
}
.btn-outline-primary:hover {
    background: var(--main-green) !important;
    color: var(--white) !important;
}
.bg-primary {
    background: var(--main-green) !important;
    color: var(--white) !important;
}
.text-primary {
    color: var(--main-green) !important;
}
.badge.bg-primary {
    background: var(--accent-green) !important;
    color: var(--main-green) !important;
    font-weight: 700;
}
/* Features Section */
#features .card {
    border: 2px solid var(--highlight-yellow);
    box-shadow: 0 2px 12px rgba(253,215,68,0.08);
    transition: box-shadow 0.2s, border 0.2s;
}
#features .card:hover {
    box-shadow: 0 8px 32px rgba(125,203,128,0.13);
    border: 2px solid var(--accent-green);
}
#features .card-title {
    color: var(--main-green);
}
#features .card-body i {
    color: var(--accent-green) !important;
}
/* Announcements Section */
section.py-5.bg-light {
    background: var(--off-white) !important;
}
section.py-5.bg-light .card {
    border: 2px solid var(--main-green);
    box-shadow: 0 2px 12px rgba(46,94,78,0.08);
}
section.py-5.bg-light .card-title {
    color: var(--main-green);
}
</style>
<?php
// Get recent announcements
$stmt = $db->prepare("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a 
    JOIN users u ON a.author_id = u.id 
    WHERE a.is_global = 1 
    ORDER BY a.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$announcements = $stmt->fetchAll();

// Get statistics for logged-in users
if (isLoggedIn()) {
    if ($_SESSION['role'] === 'student') {
        // Student statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT e.course_id) as enrolled_courses,
                COUNT(DISTINCT aa.assessment_id) as completed_assessments,
                AVG(aa.score) as average_score,
                (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL) as total_badges
            FROM course_enrollments e
            LEFT JOIN assessment_attempts aa ON e.student_id = aa.student_id AND aa.status = 'completed'
            WHERE e.student_id = ? AND e.status = 'active'
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $stats = $stmt->fetch();
        
        // Get recent courses
        $stmt = $db->prepare("
            SELECT c.*, u.first_name, u.last_name, u.profile_picture
            FROM course_enrollments e
            JOIN courses c ON e.course_id = c.id
            JOIN users u ON c.teacher_id = u.id
            WHERE e.student_id = ? AND e.status = 'active'
            ORDER BY e.enrolled_at DESC
            LIMIT 6
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $recent_courses = $stmt->fetchAll();
        
    } elseif ($_SESSION['role'] === 'teacher') {
        // Teacher statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_courses,
                COUNT(DISTINCT e.student_id) as total_students,
                COUNT(DISTINCT a.id) as total_assessments,
                AVG(aa.score) as average_student_score
            FROM courses c
            LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
            LEFT JOIN assessments a ON c.id = a.course_id
            LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.status = 'completed'
            WHERE c.teacher_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $stats = $stmt->fetch();
        
        // Get teacher's courses
        $stmt = $db->prepare("
            SELECT c.*, COUNT(e.student_id) as student_count
            FROM courses c
            LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
            WHERE c.teacher_id = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT 6
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $teacher_courses = $stmt->fetchAll();
        
    } elseif ($_SESSION['role'] === 'admin') {
        // Admin statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT c.id) as total_courses,
                COUNT(DISTINCT e.student_id) as total_students,
                COUNT(DISTINCT aa.assessment_id) as total_assessments_taken
            FROM users u
            LEFT JOIN courses c ON u.role = 'teacher'
            LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
            LEFT JOIN assessment_attempts aa ON e.student_id = aa.student_id AND aa.status = 'completed'
        ");
        $stmt->execute();
        $stats = $stmt->fetch();
    }
}
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">
                    Welcome to NEUST-MGT BSIT LMS
                </h1>
                <p class="lead mb-4">
                    Empowering students and teachers with a comprehensive learning management system designed for the BSIT Department at Nueva Ecija University of Science and Technology.
                </p>
                <?php if (!isLoggedIn()): ?>
                    <div class="d-flex gap-3">
                        <a href="login.php" class="btn btn-light btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-info-circle me-2"></i>Learn More
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-6 text-center">
                <i class="bi bi-mortarboard-fill" style="font-size: 8rem; opacity: 0.8;"></i>
            </div>
        </div>
    </div>
</section>

<?php if (isLoggedIn()): ?>
    <!-- Dashboard Content -->
    <div class="container mt-5">
        <?php if ($_SESSION['role'] === 'student'): ?>
            <!-- Student Dashboard -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="student-courses">
                        <i class="bi bi-book fs-1 mb-3"></i>
                        <h3><?php echo $stats['enrolled_courses'] ?? 0; ?></h3>
                        <p class="mb-0">Enrolled Courses</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="student-assessments">
                        <i class="bi bi-clipboard-check fs-1 mb-3"></i>
                        <h3><?php echo $stats['completed_assessments'] ?? 0; ?></h3>
                        <p class="mb-0">Completed Assessments</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="student-score">
                        <i class="bi bi-graph-up fs-1 mb-3"></i>
                        <h3><?php echo number_format($stats['average_score'] ?? 0, 1); ?>%</h3>
                        <p class="mb-0">Average Score</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="student-badges">
                        <i class="bi bi-award fs-1 mb-3"></i>
                        <h3><?php echo $stats['total_badges'] ?? 0; ?></h3>
                        <p class="mb-0">Badges Earned</p>
                    </div>
                </div>
            </div>

            <!-- Recent Courses -->
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">My Courses</h3>
                    <div class="row">
                        <?php foreach ($recent_courses as $course): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                        <p class="card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                                        <div class="d-flex align-items-center mb-3">
                                                                            <img src="<?php echo getProfilePictureUrl($course['profile_picture'] ?? null, 'small'); ?>" 
                                     class="profile-picture me-2" alt="Teacher">
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                            </small>
                                        </div>
                                        <a href="student/course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-arrow-right me-1"></i>Continue Learning
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($_SESSION['role'] === 'teacher'): ?>
            <!-- Teacher Dashboard -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="teacher-courses">
                        <i class="bi bi-book fs-1 mb-3"></i>
                        <h3><?php echo $stats['total_courses'] ?? 0; ?></h3>
                        <p class="mb-0">Total Courses</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="teacher-students">
                        <i class="bi bi-people fs-1 mb-3"></i>
                        <h3><?php echo $stats['total_students'] ?? 0; ?></h3>
                        <p class="mb-0">Total Students</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="teacher-assessments">
                        <i class="bi bi-clipboard-check fs-1 mb-3"></i>
                        <h3><?php echo $stats['total_assessments'] ?? 0; ?></h3>
                        <p class="mb-0">Assessments</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="teacher-score">
                        <i class="bi bi-graph-up fs-1 mb-3"></i>
                        <h3><?php echo number_format($stats['average_student_score'] ?? 0, 1); ?>%</h3>
                        <p class="mb-0">Avg Student Score</p>
                    </div>
                </div>
            </div>

            <!-- Teacher's Courses -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3>My Courses</h3>
                        <a href="teacher/courses.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Create Course
                        </a>
                    </div>
                    <div class="row">
                        <?php foreach ($teacher_courses as $course): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                        <p class="card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-primary"><?php echo $course['student_count']; ?> students</span>
                                            <a href="teacher/course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-gear me-1"></i>Manage
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($_SESSION['role'] === 'admin'): ?>
            <!-- Admin Dashboard -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="admin-users">
                        <i class="bi bi-people fs-1 mb-3"></i>
                        <h3><?php echo $stats['total_users'] ?? 0; ?></h3>
                        <p class="mb-0">Total Users</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="admin-courses">
                        <i class="bi bi-book fs-1 mb-3"></i>
                        <h3><?php echo $stats['total_courses'] ?? 0; ?></h3>
                        <p class="mb-0">Total Courses</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="admin-students">
                        <i class="bi bi-mortarboard fs-1 mb-3"></i>
                        <h3><?php echo $stats['total_students'] ?? 0; ?></h3>
                        <p class="mb-0">Total Students</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center" data-tutorial="admin-assessments">
                        <i class="bi bi-clipboard-check fs-1 mb-3"></i>
                        <h3><?php echo $stats['total_assessments_taken'] ?? 0; ?></h3>
                        <p class="mb-0">Assessments Taken</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">Quick Actions</h3>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="admin/users.php" class="card text-decoration-none">
                                <div class="card-body text-center">
                                    <i class="bi bi-people fs-1 text-primary mb-3"></i>
                                    <h5>Manage Users</h5>
                                    <p class="text-muted">Add, edit, or remove users</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="admin/courses.php" class="card text-decoration-none">
                                <div class="card-body text-center">
                                    <i class="bi bi-book fs-1 text-primary mb-3"></i>
                                    <h5>Manage Courses</h5>
                                    <p class="text-muted">View and manage all courses</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="admin/academic_periods.php" class="card text-decoration-none">
                                <div class="card-body text-center">
                                    <i class="bi bi-calendar fs-1 text-primary mb-3"></i>
                                    <h5>Academic Years</h5>
                                    <p class="text-muted">Manage academic periods</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="admin/analytics.php" class="card text-decoration-none">
                                <div class="card-body text-center">
                                    <i class="bi bi-graph-up fs-1 text-primary mb-3"></i>
                                    <h5>Analytics</h5>
                                    <p class="text-muted">View system statistics</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- Features Section for Non-Logged In Users -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="display-5 fw-bold text-neust-blue">Key Features</h2>
                    <p class="lead">Discover what makes our LMS the perfect solution for NEUST-MGT BSIT Department</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <i class="bi bi-play-circle fs-1 text-neust-blue mb-3"></i>
                            <h5 class="card-title">Video Management</h5>
                            <p class="card-text">Upload and stream educational videos with clear titles and descriptions.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <i class="bi bi-clipboard-check fs-1 text-neust-blue mb-3"></i>
                            <h5 class="card-title">Smart Assessments</h5>
                            <p class="card-text">Non-bypassable questions with time limits.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <i class="bi bi-graph-up fs-1 text-neust-blue mb-3"></i>
                            <h5 class="card-title">Progress Tracking</h5>
                            <p class="card-text">Interactive graphs and analytics to monitor student progress.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <i class="bi bi-award fs-1 text-neust-blue mb-3"></i>
                            <h5 class="card-title">Gamification</h5>
                            <p class="card-text">Earn badges for course completion and high assessment scores.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <i class="bi bi-trophy fs-1 text-neust-blue mb-3"></i>
                            <h5 class="card-title">Leaderboard</h5>
                            <p class="card-text">Public ranking system to motivate students and foster competition.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <i class="bi bi-shield-check fs-1 text-neust-blue mb-3"></i>
                            <h5 class="card-title">Secure Platform</h5>
                            <p class="card-text">Advanced security with password hashing and CSRF protection.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Recent Announcements -->
<?php if (!empty($announcements)): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <h3 class="mb-4">Recent Announcements</h3>
            <div class="row">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                <p class="card-text"><?php echo htmlspecialchars(substr($announcement['content'], 0, 150)) . '...'; ?></p>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small class="text-muted">
                                        By <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                    </small>
                                    <small class="text-muted">
                                        <?php echo formatDate($announcement['created_at']); ?>
                                    </small>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button class="btn btn-sm btn-outline-primary view-ann-details-home" 
                                            data-ann-id="<?php echo $announcement['id']; ?>"
                                            data-ann-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                            data-ann-content="<?php echo htmlspecialchars($announcement['content']); ?>"
                                            data-ann-author="<?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>"
                                            data-ann-context="<?php echo isset($announcement['course_name']) ? 'Course: ' . htmlspecialchars($announcement['course_name']) : 'General Announcement'; ?>">
                                        <i class="bi bi-eye"></i> View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<div class="home-bg-pattern"></div>
<div class="home-bg-svg">
    <svg width="100vw" height="100vh" viewBox="0 0 1440 600" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:100vw;height:100vh;">
        <ellipse cx="200" cy="100" rx="300" ry="120" fill="#7DCB80" fill-opacity="0.18"/>
        <ellipse cx="1240" cy="500" rx="320" ry="140" fill="#2E5E4E" fill-opacity="0.13"/>
        <ellipse cx="900" cy="100" rx="180" ry="80" fill="#FFE066" fill-opacity="0.10"/>
    </svg>
</div>
<div class="main-content-home">
<?php require_once 'includes/footer.php'; ?>
</div> 