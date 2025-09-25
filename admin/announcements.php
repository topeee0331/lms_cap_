<?php
$page_title = 'Announcements';
require_once '../includes/header.php';
requireRole('admin');
?>

<style>
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* Enhanced Announcements Page Styling - Inspired by Admin Dashboard */
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

/* Page Background */
.page-container {
    background: var(--off-white);
    min-height: 100vh;
}

/* Enhanced Welcome Section */
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
    z-index: 3;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    font-family: 'Inter', sans-serif;
}

.welcome-subtitle {
    color: white !important;
    font-size: 1.1rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 3;
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

/* Minimal floating icon */
.minimal-floating-icon {
    position: absolute;
    top: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    animation: float 3s ease-in-out infinite;
    backdrop-filter: blur(10px);
}

.minimal-floating-icon i {
    font-size: 1.2rem;
    color: rgba(255,255,255,0.9);
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}

/* Additional floating elements */
.floating-element {
    position: absolute;
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 0;
    backdrop-filter: blur(5px);
    opacity: 0.5;
    margin: 5px;
}

.floating-element i {
    font-size: 1rem;
    color: rgba(255,255,255,0.8);
}

/* Individual floating element positions and animations */
.floating-element-1 {
    top: 10%;
    left: 8%;
    animation: floatSlow 4s ease-in-out infinite;
}

.floating-element-2 {
    top: 20%;
    right: 12%;
    animation: floatReverse 3.5s ease-in-out infinite;
}

.floating-element-3 {
    top: 65%;
    left: 3%;
    animation: floatUp 5s ease-in-out infinite;
}

.floating-element-4 {
    top: 75%;
    right: 15%;
    animation: floatDown 4.5s ease-in-out infinite;
}

.floating-element-5 {
    top: 35%;
    left: 15%;
    animation: floatSide 6s ease-in-out infinite;
}

.floating-element-6 {
    top: 45%;
    right: 3%;
    animation: floatRotate 5.5s ease-in-out infinite;
}

/* Center floating elements */
.floating-element-7 {
    top: 30%;
    left: 50%;
    transform: translateX(-50%);
    animation: floatCenter 4s ease-in-out infinite;
}

.floating-element-8 {
    top: 40%;
    left: 40%;
    animation: floatPulse 3.8s ease-in-out infinite;
}

.floating-element-9 {
    top: 50%;
    left: 60%;
    animation: floatBounce 4.2s ease-in-out infinite;
}

.floating-element-10 {
    top: 60%;
    left: 50%;
    transform: translateX(-50%);
    animation: floatWave 5.8s ease-in-out infinite;
}

/* Different animation keyframes */
@keyframes floatSlow {
    0%, 100% {
        transform: translateY(0px) translateX(0px);
    }
    50% {
        transform: translateY(-15px) translateX(10px);
    }
}

@keyframes floatReverse {
    0%, 100% {
        transform: translateY(0px) translateX(0px);
    }
    50% {
        transform: translateY(15px) translateX(-10px);
    }
}

@keyframes floatUp {
    0%, 100% {
        transform: translateY(0px) scale(1);
    }
    50% {
        transform: translateY(-20px) scale(1.1);
    }
}

@keyframes floatDown {
    0%, 100% {
        transform: translateY(0px) scale(1);
    }
    50% {
        transform: translateY(20px) scale(0.9);
    }
}

@keyframes floatSide {
    0%, 100% {
        transform: translateX(0px) rotate(0deg);
    }
    50% {
        transform: translateX(15px) rotate(180deg);
    }
}

@keyframes floatRotate {
    0%, 100% {
        transform: rotate(0deg) translateY(0px);
    }
    50% {
        transform: rotate(180deg) translateY(-10px);
    }
}

/* Center element animations */
@keyframes floatCenter {
    0%, 100% {
        transform: translateX(-50%) translateY(0px) scale(1);
    }
    50% {
        transform: translateX(-50%) translateY(-15px) scale(1.1);
    }
}

@keyframes floatPulse {
    0%, 100% {
        transform: scale(1) translateY(0px);
        opacity: 0.5;
    }
    50% {
        transform: scale(1.2) translateY(-8px);
        opacity: 0.8;
    }
}

@keyframes floatBounce {
    0%, 100% {
        transform: translateY(0px) rotate(0deg);
    }
    25% {
        transform: translateY(-12px) rotate(90deg);
    }
    50% {
        transform: translateY(-20px) rotate(180deg);
    }
    75% {
        transform: translateY(-12px) rotate(270deg);
    }
}

@keyframes floatWave {
    0%, 100% {
        transform: translateX(-50%) translateY(0px) rotate(0deg);
    }
    25% {
        transform: translateX(-45%) translateY(-10px) rotate(90deg);
    }
    50% {
        transform: translateX(-55%) translateY(-15px) rotate(180deg);
    }
    75% {
        transform: translateX(-45%) translateY(-10px) rotate(270deg);
    }
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

/* Search and Filter Section */
.search-filter-card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.search-filter-card .card-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 2px solid var(--accent-green);
    padding: 1.25rem 1.5rem;
}

.search-filter-card .card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 1.1rem;
}

/* Announcements Container */
.announcements-container {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.announcements-container .card-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 2px solid var(--accent-green);
    padding: 1.25rem 1.5rem;
}

.announcements-container .card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 1.1rem;
}

/* Scrollable Announcements */
.scrollable-announcements {
    max-height: 600px;
    overflow-y: auto;
    padding: 1rem;
}

.scrollable-announcements::-webkit-scrollbar {
    width: 8px;
}

.scrollable-announcements::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.scrollable-announcements::-webkit-scrollbar-thumb {
    background: var(--main-green);
    border-radius: 4px;
}

.scrollable-announcements::-webkit-scrollbar-thumb:hover {
    background: var(--accent-green);
}

.scrollable-announcements {
    scrollbar-width: thin;
    scrollbar-color: var(--main-green) #f1f1f1;
}

/* Back Button */
.back-btn {
    background: var(--main-green);
    border: none;
    color: white;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    position: relative;
    z-index: 3;
}

.back-btn:hover {
    background: var(--accent-green);
    color: var(--main-green);
    transform: translateY(-1px);
}

/* Action Buttons */
.btn-sm {
    border-radius: var(--border-radius);
    font-weight: 500;
    transition: var(--transition);
    border: none;
}

.btn-sm:hover {
    transform: translateY(-1px);
}

/* Solid Action Button Styles */
.btn-outline-primary {
    background: #0d6efd;
    color: white;
    border: none;
}

.btn-outline-primary:hover {
    background: #0b5ed7;
    color: white;
}

.btn-outline-danger {
    background: #dc3545;
    color: white;
    border: none;
}

.btn-outline-danger:hover {
    background: #bb2d3b;
    color: white;
}

/* Create Announcement Button */
.create-announcement-btn {
    background: var(--main-green);
    border: none;
    color: white;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
}

.create-announcement-btn:hover {
    background: var(--accent-green);
    color: var(--main-green);
    transform: translateY(-1px);
}

/* Announcement Cards */
.announcement-card {
    border: 1px solid var(--border-light);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    margin-bottom: 1rem;
}

.announcement-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.announcement-card .card-body {
    padding: 1.25rem;
}

/* Modal Styling */
.modal-content {
    border-radius: var(--border-radius-lg);
    border: none;
    box-shadow: var(--shadow-lg);
}

.modal-header {
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .welcome-title {
        font-size: 2rem;
    }
    
    .search-filter-card .card-header,
    .announcements-container .card-header {
        padding: 1rem;
    }
}
</style>

<?php

$message = '';
$message_type = '';

// Handle announcement actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'create':
                $title = sanitizeInput($_POST['title'] ?? '');
                $content = sanitizeInput($_POST['content'] ?? '');
                $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
                $is_global = empty($course_id) ? 1 : 0;
                $target_audience = $course_id ? json_encode(['courses' => [$course_id]]) : null;
                
                if (empty($title) || empty($content)) {
                    $message = 'Title and content are required.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('INSERT INTO announcements (title, content, author_id, is_global, target_audience) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$title, $content, $_SESSION['user_id'], $is_global, $target_audience]);
                    
                    // Send Pusher notification
                    require_once '../config/pusher.php';
                    require_once '../includes/pusher_notifications.php';
                    
                    // Get author name and course name
                    $authorName = $_SESSION['name'] ?? 'Unknown User';
                    $courseName = null;
                    if ($course_id) {
                        $courseStmt = $db->prepare('SELECT course_name FROM courses WHERE id = ?');
                        $courseStmt->execute([$course_id]);
                        $courseName = $courseStmt->fetchColumn();
                    }
                    
                    $announcementData = [
                        'title' => $title,
                        'content' => $content,
                        'course_id' => $course_id,
                        'course_name' => $courseName,
                        'author_name' => $authorName
                    ];
                    
                    PusherNotifications::sendNewAnnouncement($announcementData);
                    
                    $message = 'Announcement created successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'update':
                $announcement_id = (int)($_POST['announcement_id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $content = sanitizeInput($_POST['content'] ?? '');
                $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
                $is_global = empty($course_id) ? 1 : 0;
                $target_audience = $course_id ? json_encode(['courses' => [$course_id]]) : null;
                
                if (empty($title) || empty($content)) {
                    $message = 'Title and content are required.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('UPDATE announcements SET title = ?, content = ?, is_global = ?, target_audience = ? WHERE id = ?');
                    $stmt->execute([$title, $content, $is_global, $target_audience, $announcement_id]);
                    $message = 'Announcement updated successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'delete':
                $announcement_id = (int)($_POST['announcement_id'] ?? 0);
                $stmt = $db->prepare('DELETE FROM announcements WHERE id = ?');
                $stmt->execute([$announcement_id]);
                $message = 'Announcement deleted successfully.';
                $message_type = 'success';
                break;
        }
    }
}

// Get announcements with search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$course_filter = (int)($_GET['course'] ?? 0);

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(a.title LIKE ? OR a.content LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if ($course_filter > 0) {
    $where_conditions[] = "JSON_SEARCH(a.target_audience, 'one', ?) IS NOT NULL";
    $params[] = $course_filter;
} elseif ($course_filter === 0) {
    $where_conditions[] = "a.is_global = 1";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $db->prepare("
    SELECT a.*, u.first_name, u.last_name, 
           CONCAT(u.first_name, ' ', u.last_name) as author_name,
           CASE 
               WHEN a.is_global = 1 THEN 'General Announcement'
               ELSE 'Course Specific'
           END as announcement_type
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    $where_clause
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Get courses for filter and form
$stmt = $db->prepare('SELECT id, course_name, course_code FROM courses WHERE is_archived = 0 ORDER BY course_name');
$stmt->execute();
$courses = $stmt->fetchAll();
?>

<div class="page-container">
    <div class="container-fluid py-4">
        <!-- Enhanced Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Manage Announcements</h1>
                    <p class="welcome-subtitle">Create, edit, and manage announcements for students and courses</p>
                    <a href="dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="welcome-decoration">
                        <i class="bi bi-megaphone"></i>
                    </div>
                    <div class="floating-shapes"></div>
                    <!-- Minimal floating icon -->
                    <div class="minimal-floating-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <!-- Additional floating elements -->
                    <div class="floating-element floating-element-1">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="floating-element floating-element-2">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="floating-element floating-element-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="floating-element floating-element-4">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="floating-element floating-element-5">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="floating-element floating-element-6">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <!-- Center floating elements -->
                    <div class="floating-element floating-element-7">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="floating-element floating-element-8">
                        <i class="fas fa-puzzle-piece"></i>
                    </div>
                    <div class="floating-element floating-element-9">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="floating-element floating-element-10">
                        <i class="fas fa-brain"></i>
                    </div>
                </div>
            </div>
            <div class="accent-line"></div>
        </div>

        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="h4 mb-0 text-dark">
                        <i class="bi bi-megaphone me-2"></i>Announcements Management
                    </h2>
                    <button class="btn create-announcement-btn" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                        <i class="bi bi-plus-circle me-2"></i>Create Announcement
                    </button>
                </div>
            </div>
        </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

        <!-- Search and Filter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card search-filter-card">
                    <div class="card-header">
                        <h5>
                            <i class="bi bi-search me-2"></i>Search & Filter Announcements
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                        <div class="col-md-8">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search announcements by title or content">
                        </div>
                        <div class="col-md-3">
                            <label for="course" class="form-label">Filter by Course</label>
                            <select class="form-select" id="course" name="course">
                                <option value="">All Announcements</option>
                                <option value="0" <?php echo $course_filter === 0 ? 'selected' : ''; ?>>General Announcements</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

        <!-- Announcements List -->
        <div class="row">
            <div class="col-12">
                <div class="card announcements-container">
                    <div class="card-header">
                        <h5>
                            <i class="bi bi-megaphone me-2"></i>Announcements
                            <span class="badge bg-primary ms-2"><?php echo count($announcements); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($announcements)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-megaphone fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">No announcements found</h5>
                                <p class="text-muted">Create your first announcement to get started.</p>
                                <button class="btn create-announcement-btn" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                    <i class="bi bi-plus-circle me-2"></i>Create Announcement
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="scrollable-announcements">
                                <?php foreach ($announcements as $announcement): ?>
                                    <div class="card announcement-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0 fw-semibold"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger delete-confirm" 
                                                            onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <p class="card-text text-muted"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small class="text-muted">
                                                        <i class="bi bi-person me-1"></i>By <?php echo htmlspecialchars($announcement['author_name'] ?? 'Unknown User'); ?>
                                                    </small>
                                                    <br>
                                                    <span class="badge bg-<?php echo $announcement['announcement_type'] === 'General Announcement' ? 'secondary' : 'info'; ?>">
                                                        <i class="bi bi-<?php echo $announcement['announcement_type'] === 'General Announcement' ? 'globe' : 'book'; ?> me-1"></i>
                                                        <?php echo htmlspecialchars($announcement['announcement_type']); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i><?php echo formatDate($announcement['created_at']); ?>
                                                </small>
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
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="create_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="create_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_content" class="form-label">Content</label>
                        <textarea class="form-control" id="create_content" name="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_course_id" class="form-label">Course (Optional)</label>
                        <select class="form-select" id="create_course_id" name="course_id">
                            <option value="">General Announcement</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty for a general announcement visible to all users.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="announcement_id" id="edit_announcement_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_content" class="form-label">Content</label>
                        <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_course_id" class="form-label">Course (Optional)</label>
                        <select class="form-select" id="edit_course_id" name="course_id">
                            <option value="">General Announcement</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty for a general announcement visible to all users.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Announcement Form -->
<form id="deleteAnnouncementForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="announcement_id" id="delete_announcement_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<script>
function editAnnouncement(announcement) {
    document.getElementById('edit_announcement_id').value = announcement.id;
    document.getElementById('edit_title').value = announcement.title;
    document.getElementById('edit_content').value = announcement.content;
    document.getElementById('edit_course_id').value = announcement.course_id || '';
    
    new bootstrap.Modal(document.getElementById('editAnnouncementModal')).show();
}

function deleteAnnouncement(announcementId, announcementTitle) {
    if (confirm(`Are you sure you want to delete "${announcementTitle}"? This action cannot be undone.`)) {
        document.getElementById('delete_announcement_id').value = announcementId;
        document.getElementById('deleteAnnouncementForm').submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?> 