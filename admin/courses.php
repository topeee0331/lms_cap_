<?php
$page_title = 'Manage Courses';
require_once '../includes/header.php';
requireRole('admin');
?>

<style>
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* Enhanced Courses Page Styling - Inspired by Admin Dashboard */
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

/* Statistics Cards Styling - Inspired by Dashboard */
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

/* Table Container with Scrollable Table */
.table-container {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.table-container .card-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 2px solid var(--accent-green);
    padding: 1.25rem 1.5rem;
}

.table-container .card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 1.1rem;
}

/* Scrollable Table */
.scrollable-table {
    overflow-x: auto;
    max-height: 600px;
    overflow-y: auto;
}

.scrollable-table::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.scrollable-table::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.scrollable-table::-webkit-scrollbar-thumb {
    background: var(--main-green);
    border-radius: 4px;
}

.scrollable-table::-webkit-scrollbar-thumb:hover {
    background: var(--accent-green);
}

.scrollable-table {
    scrollbar-width: thin;
    scrollbar-color: var(--main-green) #f1f1f1;
}

/* Table Styling */
.table {
    margin-bottom: 0;
    min-width: 1400px; /* Ensure minimum width for proper display */
}

.table thead th {
    background: #f8f9fa;
    border-bottom: 2px solid var(--accent-green);
    font-weight: 600;
    color: var(--text-dark);
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.table tbody td {
    vertical-align: middle;
    white-space: nowrap;
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
.btn-outline-info {
    background: #0dcaf0;
    color: white;
    border: none;
}

.btn-outline-info:hover {
    background: #0aa2c0;
    color: white;
}

.btn-outline-primary {
    background: #0d6efd;
    color: white;
    border: none;
}

.btn-outline-primary:hover {
    background: #0b5ed7;
    color: white;
}

.btn-outline-warning {
    background: #ffc107;
    color: #000;
    border: none;
}

.btn-outline-warning:hover {
    background: #ffca2c;
    color: #000;
}

.btn-outline-success {
    background: #198754;
    color: white;
    border: none;
}

.btn-outline-success:hover {
    background: #146c43;
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

/* Create Course Button */
.create-course-btn {
    background: var(--main-green);
    border: none;
    color: white;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
}

.create-course-btn:hover {
    background: var(--accent-green);
    color: var(--main-green);
    transform: translateY(-1px);
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

/* Loading States */
#loadingIndicator {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(2px);
}

.spinner-border {
    width: 3rem;
    height: 3rem;
}

/* Search Input Enhancements */
#search {
    transition: all 0.3s ease;
}

#search:focus {
    box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25);
    border-color: var(--main-green);
}

/* Filter Button States */
#filterBtn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Status Badge Colors */
.badge.bg-success {
    background-color: #198754 !important;
}

.badge.bg-warning {
    background-color: #ffc107 !important;
    color: #000 !important;
}

.badge.bg-secondary {
    background-color: #6c757d !important;
}

.badge.bg-danger {
    background-color: #dc3545 !important;
}

/* Filter Form Enhancements */
.search-filter-card .form-control:focus,
.search-filter-card .form-select:focus {
    border-color: var(--main-green);
    box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25);
}

/* Loading State for Form */
.form-loading {
    opacity: 0.6;
    pointer-events: none;
}

.form-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid var(--main-green);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Real-time Search Indicator */
.searching::after {
    content: '';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid var(--main-green);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Filtering State for Dropdowns */
.filtering {
    background-color: rgba(46, 94, 78, 0.1) !important;
    border-color: var(--main-green) !important;
    box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25) !important;
}

/* Success Alert Animation removed as requested */

/* Statistics Card Animation */
.stats-card h3 {
    transition: all 0.3s ease;
}

.stats-card h3.updated {
    animation: pulse 0.5s ease-in-out;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Course Count Badge Animation */
.badge.bg-primary {
    transition: all 0.3s ease;
}

/* Form Loading State */
.form-loading {
    position: relative;
    overflow: hidden;
}

.form-loading::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(46, 94, 78, 0.1), transparent);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

@keyframes spin {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .welcome-title {
        font-size: 2rem;
    }
    
    .stats-card .card-body {
        padding: 1rem;
    }
    
    .search-filter-card .card-header,
    .table-container .card-header {
        padding: 1rem;
    }
}
</style>

<?php

$message = '';
$message_type = '';

// Handle course actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'archive':
                $course_id = (int)($_POST['course_id'] ?? 0);
                $stmt = $db->prepare('UPDATE courses SET is_archived = 1 WHERE id = ?');
                $stmt->execute([$course_id]);
                $message = 'Course archived successfully.';
                $message_type = 'success';
                break;
                
            case 'unarchive':
                $course_id = (int)($_POST['course_id'] ?? 0);
                $stmt = $db->prepare('UPDATE courses SET is_archived = 0 WHERE id = ?');
                $stmt->execute([$course_id]);
                $message = 'Course unarchived successfully.';
                $message_type = 'success';
                break;
                
            case 'delete':
                $course_id = (int)($_POST['course_id'] ?? 0);
                
                // Check if course has enrollments
                $stmt = $db->prepare('SELECT COUNT(*) FROM course_enrollments WHERE course_id = ?');
                $stmt->execute([$course_id]);
                $enrollment_count = $stmt->fetchColumn();
                
                if ($enrollment_count > 0) {
                    $message = 'Cannot delete course with existing enrollments.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('DELETE FROM courses WHERE id = ?');
                    $stmt->execute([$course_id]);
                    $message = 'Course deleted successfully.';
                    $message_type = 'success';
                }
                break;
            case 'create':
                $course_name = sanitizeInput($_POST['course_name'] ?? '');
                $course_code = sanitizeInput($_POST['course_code'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $teacher_id = (int)($_POST['teacher_id'] ?? 0);
                $academic_period_id = (int)($_POST['academic_period_id'] ?? 0);
                $year_level = sanitizeInput($_POST['year_level'] ?? '');
                if (empty($course_name) || empty($course_code) || !$teacher_id || !$academic_period_id || empty($year_level)) {
                    $message = 'All fields are required.';
                    $message_type = 'danger';
                } else {
                    // Check for duplicate course code
                    $stmt = $db->prepare('SELECT id FROM courses WHERE course_code = ? LIMIT 1');
                    $stmt->execute([$course_code]);
                    if ($stmt->fetch()) {
                        $message = 'Course code already exists.';
                        $message_type = 'danger';
                    } else {
                        $stmt = $db->prepare('INSERT INTO courses (course_name, course_code, description, teacher_id, academic_period_id, year_level, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                        $stmt->execute([$course_name, $course_code, $description, $teacher_id, $academic_period_id, $year_level]);
                        $message = 'Course created successfully.';
                        $message_type = 'success';
                    }
                }
                break;
            case 'update':
                $course_id = (int)($_POST['course_id'] ?? 0);
                $course_name = sanitizeInput($_POST['course_name'] ?? '');
                $course_code = sanitizeInput($_POST['course_code'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $teacher_id = (int)($_POST['teacher_id'] ?? 0);
                $academic_period_id = (int)($_POST['academic_period_id'] ?? 0);
                $year_level = sanitizeInput($_POST['year_level'] ?? '');
                if (empty($course_name) || empty($course_code) || !$teacher_id || !$academic_period_id || empty($year_level)) {
                    $message = 'All fields are required.';
                    $message_type = 'danger';
                } else {
                    // Check for duplicate course code (excluding current course)
                    $stmt = $db->prepare('SELECT id FROM courses WHERE course_code = ? AND id != ? LIMIT 1');
                    $stmt->execute([$course_code, $course_id]);
                    if ($stmt->fetch()) {
                        $message = 'Course code already exists.';
                        $message_type = 'danger';
                    } else {
                        $stmt = $db->prepare('UPDATE courses SET course_name = ?, course_code = ?, description = ?, teacher_id = ?, academic_period_id = ?, year_level = ? WHERE id = ?');
                        $stmt->execute([$course_name, $course_code, $description, $teacher_id, $academic_period_id, $year_level, $course_id]);
                        $message = 'Course updated successfully.';
                        $message_type = 'success';
                    }
                }
                break;
        }
    }
}

// Get courses with search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$academic_year_filter = (int)($_GET['academic_year'] ?? 0);
$status_filter = sanitizeInput($_GET['status'] ?? '');
$year_level_filter = sanitizeInput($_GET['year_level'] ?? '');

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.course_name LIKE ? OR c.course_code LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($academic_year_filter > 0) {
    $where_conditions[] = "c.academic_period_id = ?";
    $params[] = $academic_year_filter;
}

if ($status_filter === 'archived') {
    $where_conditions[] = "(c.is_archived = 1 OR c.status = 'archived')";
} elseif ($status_filter === 'active') {
    $where_conditions[] = "(c.is_archived = 0 AND (c.status = 'active' OR c.status IS NULL))";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "c.status = 'inactive'";
} elseif ($status_filter === 'draft') {
    $where_conditions[] = "c.status = 'draft'";
}

if (!empty($year_level_filter)) {
    $where_conditions[] = "c.year_level = ?";
    $params[] = $year_level_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Update the courses query to join academic_periods instead of separate academic_years and semesters
$stmt = $db->prepare("
    SELECT c.*, u.first_name, u.last_name, u.username, ap.academic_year, ap.semester_name,
           COUNT(e.student_id) as student_count,
           JSON_LENGTH(c.modules) as module_count
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    JOIN academic_periods ap ON c.academic_period_id = ap.id
    LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
    $where_clause
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get academic years for filter (only those with courses)
$stmt = $db->prepare('
    SELECT DISTINCT ap.id, CONCAT(ap.academic_year, " - ", ap.semester_name) as period_name 
    FROM academic_periods ap
    INNER JOIN courses c ON ap.id = c.academic_period_id
    ORDER BY ap.academic_year DESC, ap.semester_name
');
$stmt->execute();
$academic_periods = $stmt->fetchAll();

// 1. Fetch all semesters for use in the modal:
$period_stmt = $db->prepare('SELECT * FROM academic_periods ORDER BY academic_year DESC, semester_name');
$period_stmt->execute();
$all_periods = $period_stmt->fetchAll();

// Fetch only active academic years for the modal:
$stmt = $db->prepare('SELECT id, CONCAT(academic_year, " - ", semester_name) as period_name FROM academic_periods WHERE is_active = 1 ORDER BY academic_year DESC, semester_name');
$stmt->execute();
$active_academic_periods = $stmt->fetchAll();
// Fetch only active semesters for the modal:
$active_period_stmt = $db->prepare('SELECT * FROM academic_periods WHERE is_active = 1 ORDER BY academic_year DESC, semester_name');
$active_period_stmt->execute();
$active_periods = $active_period_stmt->fetchAll();

// Fetch distinct year levels for the year level dropdown from courses table
$year_level_stmt = $db->query('SELECT DISTINCT year_level FROM courses WHERE year_level IS NOT NULL AND year_level != "" ORDER BY CAST(year_level AS UNSIGNED)');
$year_levels = $year_level_stmt ? $year_level_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Get statistics with improved status logic
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_courses,
        COUNT(CASE WHEN (is_archived = 0 AND (status = 'active' OR status IS NULL)) THEN 1 END) as active_courses,
        COUNT(CASE WHEN (is_archived = 1 OR status = 'archived') THEN 1 END) as archived_courses,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_courses,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_courses,
        COUNT(DISTINCT teacher_id) as unique_teachers,
        COUNT(DISTINCT academic_period_id) as periods_with_courses,
        SUM(CASE WHEN (is_archived = 0 AND (status = 'active' OR status IS NULL)) THEN 1 ELSE 0 END) as current_courses
    FROM courses
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get total students and modules
$total_stats_stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT e.student_id) as total_students,
        SUM(JSON_LENGTH(c.modules)) as total_modules
    FROM courses c
    LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
    WHERE c.is_archived = 0
");
$total_stats_stmt->execute();
$total_stats = $total_stats_stmt->fetch();
?>

<div class="page-container">
    <div class="container-fluid py-4">
        <!-- Enhanced Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Manage Courses</h1>
                    <p class="welcome-subtitle">Create, edit, and manage all courses and their assignments</p>
                    <a href="dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="welcome-decoration">
                        <i class="bi bi-book"></i>
                    </div>
                    <div class="floating-shapes"></div>
                </div>
            </div>
            <div class="accent-line"></div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-primary border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-book-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['total_courses'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Total Courses</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-success border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-play-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['active_courses'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Active Courses</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-info border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $total_stats['total_students'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-warning border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-collection-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $total_stats['total_modules'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Total Modules</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-danger border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-danger text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-person-workspace fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['unique_teachers'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Active Teachers</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-secondary border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-archive-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['archived_courses'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Archived Courses</p>
                </div>
            </div>
        </div>
    </div>

        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="h4 mb-0 text-dark">
                        <i class="bi bi-book me-2"></i>Course Management
                    </h2>
                    <button class="btn create-course-btn" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                        <i class="bi bi-plus-circle me-2"></i>Create Course
                    </button>
                </div>
            </div>
        </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

        <!-- Search and Filter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card search-filter-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>
                                <i class="bi bi-search me-2"></i>Search & Filter
                            </h5>
                            <div id="activeFilters" class="d-flex flex-wrap gap-1" style="display: none !important;">
                                <!-- Active filters will be displayed here -->
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                    <form method="get" class="row g-3" id="filterForm">
                        <div class="col-md-3">
                            <label for="search" class="form-label fw-semibold">
                                <i class="bi bi-search me-2"></i>Search
                            </label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by course name, code, or description"
                                       autocomplete="off">
                                <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                    <small class="text-muted" id="searchCounter" style="display: none;">
                                        <span id="charCount">0</span> characters
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="academic_year" class="form-label fw-semibold">
                                <i class="bi bi-calendar-event me-2"></i>Academic Year
                            </label>
                            <select class="form-select" id="academic_year" name="academic_year">
                                <option value="">All Years</option>
                                <?php foreach ($academic_periods as $period): ?>
                                    <option value="<?php echo $period['id']; ?>" 
                                            <?php echo $academic_year_filter == $period['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($period['period_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="year_level" class="form-label fw-semibold">
                                <i class="bi bi-mortarboard me-2"></i>Year Level
                            </label>
                            <select class="form-select" id="year_level" name="year_level">
                                <option value="">All Years</option>
                                <?php foreach ($year_levels as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year); ?>" 
                                            <?php echo $year_level_filter === $year ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year); ?> Year
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label fw-semibold">
                                <i class="bi bi-toggle-on me-2"></i>Status
                            </label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2 d-md-flex">
                                <button type="button" class="btn btn-primary" id="filterBtn">
                                    <i class="bi bi-funnel me-2"></i>Filter
                                </button>
                                <a href="courses.php" class="btn btn-outline-secondary" id="clearBtn">
                                    <i class="bi bi-x-circle me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

        <!-- Courses Table -->
        <div class="row">
            <div class="col-12">
                <div class="card table-container">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>
                                <i class="bi bi-list-ul me-2"></i>All Courses
                            </h5>
                            <span class="badge bg-primary fs-6"><?php echo count($courses); ?> courses</span>
                        </div>
                    </div>
                    <div class="card-body p-0" id="coursesTableContainer">
                        <!-- Loading indicator -->
                        <div id="loadingIndicator" class="text-center py-5" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading courses...</p>
                        </div>
                        
                        <?php if (empty($courses)): ?>
                            <div class="text-center py-5" id="noCoursesMessage">
                                <i class="bi bi-book fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">No courses found</h5>
                                <p class="text-muted">Start by creating your first course.</p>
                                <button class="btn create-course-btn" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                                    <i class="bi bi-plus-circle me-2"></i>Create Course
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="scrollable-table" id="coursesTable">
                                <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="border-0">
                                            <i class="bi bi-book me-2"></i>Course
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-person-workspace me-2"></i>Teacher
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-calendar-event me-2"></i>Academic Year
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-mortarboard me-2"></i>Year Level
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-people me-2"></i>Students
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-collection me-2"></i>Modules
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-toggle-on me-2"></i>Status
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-calendar me-2"></i>Created
                                        </th>
                                        <th class="border-0 text-center">
                                            <i class="bi bi-gear me-2"></i>Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <div class="bg-<?= $course['is_archived'] ? 'secondary' : 'primary' ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                            <i class="bi bi-book text-white"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <h6 class="mb-0 fw-semibold me-2"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                                            <?php 
                                                            $year_level = $course['year_level'] ?? 'N/A';
                                                            $year_colors = [
                                                                '1' => 'success',
                                                                '1st Year' => 'success',
                                                                '2' => 'info', 
                                                                '2nd Year' => 'info',
                                                                '3' => 'warning',
                                                                '3rd Year' => 'warning',
                                                                '4' => 'danger',
                                                                '4th Year' => 'danger'
                                                            ];
                                                            $badge_color = $year_colors[$year_level] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?= $badge_color ?> small">
                                                                <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($year_level) ?> Year
                                                            </span>
                                                        </div>
                                                        <small class="text-muted">
                                                            <i class="bi bi-code me-1"></i><?php echo htmlspecialchars($course['course_code']); ?>
                                                        </small>
                                                        <?php if ($course['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($course['description'], 0, 50)) . '...'; ?></small>
                                                        <?php endif; ?>
                                                        <small class="text-muted">
                                                            Created by: <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name'] . ' (' . $course['username'] . ')'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo getProfilePictureUrl($course['profile_picture'] ?? null, 'medium'); ?>" 
                                                         class="rounded-circle me-2" alt="Teacher" style="width: 32px; height: 32px; object-fit: cover;">
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($course['academic_year']) ?></div>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar2-week me-1"></i><?= htmlspecialchars($course['semester_name']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php 
                                                $year_level = $course['year_level'] ?? 'N/A';
                                                $year_colors = [
                                                    '1' => 'success',
                                                    '1st Year' => 'success',
                                                    '2' => 'info', 
                                                    '2nd Year' => 'info',
                                                    '3' => 'warning',
                                                    '3rd Year' => 'warning',
                                                    '4' => 'danger',
                                                    '4th Year' => 'danger'
                                                ];
                                                $badge_color = $year_colors[$year_level] ?? 'secondary';
                                                ?>
                                                <div class="d-flex flex-column align-items-center">
                                                    <span class="badge bg-<?= $badge_color ?> fs-6 px-3 py-2">
                                                        <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($year_level) ?> Year
                                                    </span>
                                                    <small class="text-muted mt-1">
                                                        <?php
                                                        switch($year_level) {
                                                            case '1':
                                                            case '1st Year':
                                                                echo 'Freshman Level';
                                                                break;
                                                            case '2':
                                                            case '2nd Year':
                                                                echo 'Sophomore Level';
                                                                break;
                                                            case '3':
                                                            case '3rd Year':
                                                                echo 'Junior Level';
                                                                break;
                                                            case '4':
                                                            case '4th Year':
                                                                echo 'Senior Level';
                                                                break;
                                                            default:
                                                                echo 'Undefined Level';
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <i class="bi bi-people me-1"></i><?php echo $course['student_count']; ?> students
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-collection me-1"></i><?php echo $course['module_count']; ?> modules
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($course['is_archived']): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-archive me-1"></i>Archived
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Active
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i><?php echo formatDate($course['created_at']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewCourseModal<?= $course['id'] ?>"
                                                            title="View Course">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editCourseModal<?= $course['id'] ?>"
                                                            title="Edit Course">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if (!$course['is_archived']): ?>
                                                        <form method="post" action="courses.php" style="display:inline;" 
                                                              onsubmit="return confirm('Archive this course?');">
                                                            <input type="hidden" name="action" value="archive">
                                                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Archive Course">
                                                                <i class="bi bi-archive"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" action="courses.php" style="display:inline;" 
                                                              onsubmit="return confirm('Unarchive this course?');">
                                                            <input type="hidden" name="action" value="unarchive">
                                                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Unarchive Course">
                                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" action="courses.php" style="display:inline;" 
                                                          onsubmit="return confirm('Are you sure you want to delete this course?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Course">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Archive Course Form -->
<form id="archiveCourseForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="archive">
    <input type="hidden" name="course_id" id="archive_course_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Unarchive Course Form -->
<form id="unarchiveCourseForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="unarchive">
    <input type="hidden" name="course_id" id="unarchive_course_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Delete Course Form -->
<form id="deleteCourseForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="course_id" id="delete_course_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Create Course Modal -->
<div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="courses.php">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="createCourseModalLabel">
            <i class="bi bi-plus-circle me-2"></i>Create New Course
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
          <div class="mb-3">
            <label for="course_name" class="form-label fw-semibold">
              <i class="bi bi-book me-2"></i>Course Name
            </label>
            <input type="text" class="form-control" id="course_name" name="course_name" required>
          </div>
          <div class="mb-3">
            <label for="course_code" class="form-label fw-semibold">
              <i class="bi bi-code me-2"></i>Course Code
            </label>
            <input type="text" class="form-control" id="course_code" name="course_code" required>
          </div>
          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">
              <i class="bi bi-text-paragraph me-2"></i>Description
            </label>
            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label for="teacher_id" class="form-label fw-semibold">
              <i class="bi bi-person-workspace me-2"></i>Teacher
            </label>
            <select class="form-select" id="teacher_id" name="teacher_id" required>
              <option value="">Select Teacher</option>
              <?php
              $teacher_stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'teacher' AND (status = 'active' OR status IS NULL) ORDER BY last_name, first_name");
              $teacher_stmt->execute();
              $teachers = $teacher_stmt->fetchAll();
              foreach ($teachers as $teacher): ?>
                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="academic_period_id" class="form-label fw-semibold">
              <i class="bi bi-calendar-event me-2"></i>Academic Period
            </label>
            <select class="form-select" id="academic_period_id" name="academic_period_id" required>
              <option value="">Select Period</option>
              <?php foreach ($active_academic_periods as $period): ?>
                <option value="<?= $period['id']; ?>"><?= htmlspecialchars($period['period_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="year_level" class="form-label fw-semibold">
              <i class="bi bi-mortarboard me-2"></i>Year Level
            </label>
            <select class="form-select" id="year_level" name="year_level" required>
              <option value="">Select Year Level</option>
              <?php foreach ($year_levels as $year): ?>
                <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?> Year</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-2"></i>Cancel
          </button>
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle me-2"></i>Create Course
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($courses as $course): ?>
<div class="modal fade" id="viewCourseModal<?= $course['id'] ?>" tabindex="-1" aria-labelledby="viewCourseLabel<?= $course['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="viewCourseLabel<?= $course['id'] ?>">
          <i class="bi bi-eye me-2"></i>Course Details: <?= htmlspecialchars($course['course_name']) ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <dl class="row">
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-code me-2"></i>Course Code
          </dt>
          <dd class="col-sm-9"><?= htmlspecialchars($course['course_code']) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-text-paragraph me-2"></i>Description
          </dt>
          <dd class="col-sm-9"><?= nl2br(htmlspecialchars($course['description'])) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-person-workspace me-2"></i>Teacher
          </dt>
          <dd class="col-sm-9"><?= htmlspecialchars($course['last_name'] . ', ' . $course['first_name']) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-calendar-event me-2"></i>Academic Year
          </dt>
          <dd class="col-sm-9"><?= htmlspecialchars($course['academic_year']) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-calendar2-week me-2"></i>Semester
          </dt>
          <dd class="col-sm-9"><?= htmlspecialchars($course['semester_name']) ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-people me-2"></i>Students
          </dt>
          <dd class="col-sm-9"><?= (int)$course['student_count'] ?></dd>
          <dt class="col-sm-3 fw-semibold">
            <i class="bi bi-collection me-2"></i>Modules
          </dt>
          <dd class="col-sm-9"><?= (int)$course['module_count'] ?></dd>
        </dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-2"></i>Close
        </button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php foreach ($courses as $course): ?>
<div class="modal fade" id="editCourseModal<?= $course['id'] ?>" tabindex="-1" aria-labelledby="editCourseLabel<?= $course['id'] ?>" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="courses.php">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="editCourseLabel<?= $course['id'] ?>">
            <i class="bi bi-pencil-square me-2"></i>Edit Course: <?= htmlspecialchars($course['course_name']) ?>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
          <div class="mb-3">
            <label for="course_name_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-book me-2"></i>Course Name
            </label>
            <input type="text" class="form-control" id="course_name_edit<?= $course['id'] ?>" name="course_name" required value="<?= htmlspecialchars($course['course_name']) ?>">
          </div>
          <div class="mb-3">
            <label for="course_code_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-code me-2"></i>Course Code
            </label>
            <input type="text" class="form-control" id="course_code_edit<?= $course['id'] ?>" name="course_code" required value="<?= htmlspecialchars($course['course_code']) ?>">
          </div>
          <div class="mb-3">
            <label for="description_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-text-paragraph me-2"></i>Description
            </label>
            <textarea class="form-control" id="description_edit<?= $course['id'] ?>" name="description" rows="2"><?= htmlspecialchars($course['description']) ?></textarea>
          </div>
          <div class="mb-3">
            <label for="teacher_id_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-person-workspace me-2"></i>Teacher
            </label>
            <select class="form-select" id="teacher_id_edit<?= $course['id'] ?>" name="teacher_id" required>
              <option value="">Select Teacher</option>
              <?php
              $active_teachers_stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'teacher' AND (status = 'active' OR status IS NULL) ORDER BY last_name, first_name");
              $active_teachers_stmt->execute();
              $active_teachers = $active_teachers_stmt->fetchAll();
              foreach ($active_teachers as $teacher): ?>
                <option value="<?= $teacher['id'] ?>" <?= $course['teacher_id'] == $teacher['id'] ? 'selected' : '' ?>><?= htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="academic_period_id_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-calendar-event me-2"></i>Academic Period
            </label>
            <select class="form-select" id="academic_period_id_edit<?= $course['id'] ?>" name="academic_period_id" required>
              <option value="">Select Period</option>
              <?php foreach ($active_academic_periods as $period): ?>
                <option value="<?= $period['id'] ?>" <?= $course['academic_period_id'] == $period['id'] ? 'selected' : '' ?>><?= htmlspecialchars($period['period_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="year_level_edit<?= $course['id'] ?>" class="form-label fw-semibold">
              <i class="bi bi-mortarboard me-2"></i>Year Level
            </label>
            <select class="form-select" id="year_level_edit<?= $course['id'] ?>" name="year_level" required>
              <option value="">Select Year Level</option>
              <?php foreach ($year_levels as $year): ?>
                <option value="<?= htmlspecialchars($year) ?>" <?= $course['year_level'] == $year ? 'selected' : '' ?>><?= htmlspecialchars($year) ?> Year</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-2"></i>Cancel
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-2"></i>Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
function archiveCourse(courseId) {
    if (confirm('Are you sure you want to archive this course? It will be read-only.')) {
        document.getElementById('archive_course_id').value = courseId;
        document.getElementById('archiveCourseForm').submit();
    }
}

function unarchiveCourse(courseId) {
    if (confirm('Are you sure you want to unarchive this course?')) {
        document.getElementById('unarchive_course_id').value = courseId;
        document.getElementById('unarchiveCourseForm').submit();
    }
}

function deleteCourse(courseId, courseName) {
    if (confirm(`Are you sure you want to delete "${courseName}"? This action cannot be undone.`)) {
        document.getElementById('delete_course_id').value = courseId;
        document.getElementById('deleteCourseForm').submit();
    }
}

// Real-time filtering functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const academicYearSelect = document.getElementById('academic_year');
    const yearLevelSelect = document.getElementById('year_level');
    const statusSelect = document.getElementById('status');
    const filterBtn = document.getElementById('filterBtn');
    const clearBtn = document.getElementById('clearBtn');
    const coursesTableContainer = document.getElementById('coursesTableContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const courseCountBadge = document.querySelector('.badge.bg-primary.fs-6');
    
    let searchTimeout;
    
    // Function to perform AJAX search
    function performSearch() {
        const search = searchInput.value.trim();
        const academicYear = academicYearSelect.value;
        const yearLevel = yearLevelSelect.value;
        const status = statusSelect.value;
        
        // Show loading indicator and disable form
        loadingIndicator.style.display = 'block';
        document.getElementById('filterForm').classList.add('form-loading');
        coursesTableContainer.querySelector('.scrollable-table, #noCoursesMessage')?.style.setProperty('display', 'none');
        
        // Build query parameters
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (academicYear) params.append('academic_year', academicYear);
        if (yearLevel) params.append('year_level', yearLevel);
        if (status) params.append('status', status);
        
        // Make AJAX request
        fetch(`ajax_get_courses.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update courses table with animation
                    coursesTableContainer.innerHTML = data.courses_html;
                    
                    // Update course count badge with animation
                    if (courseCountBadge) {
                        courseCountBadge.style.transform = 'scale(1.1)';
                        courseCountBadge.textContent = `${data.total_courses} courses`;
                        setTimeout(() => {
                            courseCountBadge.style.transform = 'scale(1)';
                        }, 200);
                    }
                    
                    // Update statistics cards if available
                    updateStatistics(data.stats, data.total_stats);
                    
                    // Update URL without page reload
                    const newUrl = new URL(window.location);
                    newUrl.search = params.toString();
                    window.history.pushState({}, '', newUrl);
                    
                    // Success feedback removed as requested
                } else {
                    console.error('Error:', data.error);
                    showError('Failed to load courses. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Failed to load courses. Please try again.');
            })
            .finally(() => {
                loadingIndicator.style.display = 'none';
                document.getElementById('filterForm').classList.remove('form-loading');
            });
    }
    
    // Function to update statistics cards with animations
    function updateStatistics(stats, totalStats) {
        // Update total courses
        const totalCoursesElement = document.querySelector('.stats-primary h3');
        if (totalCoursesElement) {
            animateNumberChange(totalCoursesElement, stats.total_courses);
        }
        
        // Update active courses
        const activeCoursesElement = document.querySelector('.stats-success h3');
        if (activeCoursesElement) {
            animateNumberChange(activeCoursesElement, stats.active_courses);
        }
        
        // Update total students
        const totalStudentsElement = document.querySelector('.stats-info h3');
        if (totalStudentsElement) {
            animateNumberChange(totalStudentsElement, totalStats.total_students);
        }
        
        // Update total modules
        const totalModulesElement = document.querySelector('.stats-warning h3');
        if (totalModulesElement) {
            animateNumberChange(totalModulesElement, totalStats.total_modules);
        }
        
        // Update unique teachers
        const uniqueTeachersElement = document.querySelector('.stats-danger h3');
        if (uniqueTeachersElement) {
            animateNumberChange(uniqueTeachersElement, stats.unique_teachers);
        }
        
        // Update archived courses
        const archivedCoursesElement = document.querySelector('.stats-secondary h3');
        if (archivedCoursesElement) {
            animateNumberChange(archivedCoursesElement, stats.archived_courses);
        }
    }
    
    // Function to animate number changes
    function animateNumberChange(element, newValue) {
        const currentValue = parseInt(element.textContent) || 0;
        const targetValue = parseInt(newValue) || 0;
        
        if (currentValue !== targetValue) {
            element.classList.add('updated');
            
            // Animate the number change
            let current = currentValue;
            const increment = (targetValue - currentValue) / 20;
            const timer = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= targetValue) || (increment < 0 && current <= targetValue)) {
                    current = targetValue;
                    clearInterval(timer);
                    element.classList.remove('updated');
                }
                element.textContent = Math.round(current);
            }, 50);
        }
    }
    
    // Success feedback function removed as requested
    
    // Function to update active filters display
    function updateActiveFilters() {
        const activeFiltersContainer = document.getElementById('activeFilters');
        const search = searchInput.value.trim();
        const academicYear = academicYearSelect.value;
        const yearLevel = yearLevelSelect.value;
        const status = statusSelect.value;
        
        let activeFilters = [];
        
        if (search) {
            activeFilters.push(`<span class="badge bg-primary">Search: "${search}"</span>`);
        }
        if (academicYear) {
            const selectedOption = academicYearSelect.options[academicYearSelect.selectedIndex];
            activeFilters.push(`<span class="badge bg-info">Academic Year: ${selectedOption.text}</span>`);
        }
        if (yearLevel) {
            activeFilters.push(`<span class="badge bg-warning">Year Level: ${yearLevel}</span>`);
        }
        if (status) {
            const statusText = status.charAt(0).toUpperCase() + status.slice(1);
            activeFilters.push(`<span class="badge bg-secondary">Status: ${statusText}</span>`);
        }
        
        if (activeFilters.length > 0) {
            activeFiltersContainer.innerHTML = activeFilters.join(' ');
            activeFiltersContainer.style.display = 'flex';
        } else {
            activeFiltersContainer.style.display = 'none';
        }
    }
    
    // Function to show error message
    function showError(message) {
        coursesTableContainer.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-exclamation-triangle fs-1 text-danger mb-3"></i>
                <h5 class="text-danger">Error</h5>
                <p class="text-muted">${message}</p>
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Retry
                </button>
            </div>
        `;
    }
    
    // Real-time search with faster debouncing and character counter
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        // Update character counter
        const charCount = this.value.length;
        const charCountElement = document.getElementById('charCount');
        const searchCounter = document.getElementById('searchCounter');
        
        if (charCount > 0) {
            charCountElement.textContent = charCount;
            searchCounter.style.display = 'block';
        } else {
            searchCounter.style.display = 'none';
        }
        
        // Update active filters display
        updateActiveFilters();
        
        // Show immediate visual feedback
        searchInput.classList.add('searching');
        
        // Faster debouncing for better responsiveness
        searchTimeout = setTimeout(() => {
            searchInput.classList.remove('searching');
            performSearch();
        }, 200); // Reduced to 200ms for faster response
    });
    
    // Immediate filtering for dropdowns with visual feedback
    academicYearSelect.addEventListener('change', function() {
        this.classList.add('filtering');
        updateActiveFilters();
        performSearch();
        setTimeout(() => this.classList.remove('filtering'), 1000);
    });
    
    yearLevelSelect.addEventListener('change', function() {
        this.classList.add('filtering');
        updateActiveFilters();
        performSearch();
        setTimeout(() => this.classList.remove('filtering'), 1000);
    });
    
    statusSelect.addEventListener('change', function() {
        this.classList.add('filtering');
        updateActiveFilters();
        performSearch();
        setTimeout(() => this.classList.remove('filtering'), 1000);
    });
    
    // Filter button (for manual trigger)
    filterBtn.addEventListener('click', performSearch);
    
    // Clear button
    clearBtn.addEventListener('click', function(e) {
        e.preventDefault();
        searchInput.value = '';
        academicYearSelect.value = '';
        yearLevelSelect.value = '';
        statusSelect.value = '';
        updateActiveFilters();
        performSearch();
    });
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function() {
        const urlParams = new URLSearchParams(window.location.search);
        searchInput.value = urlParams.get('search') || '';
        academicYearSelect.value = urlParams.get('academic_year') || '';
        yearLevelSelect.value = urlParams.get('year_level') || '';
        statusSelect.value = urlParams.get('status') || '';
        updateActiveFilters();
        performSearch();
    });
    
    // Initialize active filters on page load
    updateActiveFilters();
});

// Academic period change event handler (no longer needed since we have a single select)
</script>

<?php require_once '../includes/footer.php'; ?> 