<?php
$page_title = 'Manage Teachers';
require_once '../includes/header.php';
require_once '../includes/student_id_generator.php';
requireRole('admin');
?>

<style>
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* Enhanced Teachers Page Styling - Inspired by Admin Dashboard */
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
    background: #0d6efd !important;
    border-left: 4px solid #0a58ca !important;
    color: white !important;
}

.stats-success {
    background: #198754 !important;
    border-left: 4px solid #146c43 !important;
    color: white !important;
}

.stats-info {
    background: #0dcaf0 !important;
    border-left: 4px solid #0aa2c0 !important;
    color: white !important;
}

.stats-warning {
    background: #ffc107 !important;
    border-left: 4px solid #ffca2c !important;
    color: #000 !important;
}

.stats-secondary {
    background: #6c757d !important;
    border-left: 4px solid #5c636a !important;
    color: white !important;
}

.stats-danger {
    background: #dc3545 !important;
    border-left: 4px solid #b02a37 !important;
    color: white !important;
}

.stats-danger-alt {
    background: #e91e63 !important;
    border-left: 4px solid #d81b60 !important;
    color: white !important;
}

.stats-purple {
    background: #9c27b0 !important;
    border-left: 4px solid #7b1fa2 !important;
    color: white !important;
}

/* Table Container with Scrollable Table */
.table-container {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

/* Search and Filter Section */
.search-filter-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
}

.search-filter-card .form-control,
.search-filter-card .form-select {
    border: 1px solid var(--border-light);
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.search-filter-card .form-control:focus,
.search-filter-card .form-select:focus {
    border-color: var(--accent-green);
    box-shadow: 0 0 0 0.2rem rgba(125, 203, 128, 0.25);
}

.search-filter-card .form-label {
    color: var(--text-dark);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

/* Loading States */
.form-loading .form-control,
.form-loading .form-select {
    opacity: 0.7;
    pointer-events: none;
}

.searching {
    background-color: rgba(125, 203, 128, 0.1) !important;
    border-color: var(--accent-green) !important;
}

.filtering {
    background-color: rgba(46, 94, 78, 0.1) !important;
    border-color: var(--main-green) !important;
}

/* Loading Indicator */
.loading-indicator {
    display: flex;
    align-items: center;
    color: var(--text-muted);
    font-size: 0.875rem;
}

/* Animation for number changes */
.updated {
    animation: numberChange 0.3s ease-in-out;
}

@keyframes numberChange {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); color: var(--accent-green); }
    100% { transform: scale(1); }
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
    min-width: 1000px; /* Ensure minimum width for proper display */
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
.btn-outline-secondary {
    background: #6c757d;
    color: white;
    border: none;
}

.btn-outline-secondary:hover {
    background: #5c636a;
    color: white;
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

/* Add Teacher Button */
.add-teacher-btn {
    background: var(--main-green);
    border: none;
    color: white;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
}

.add-teacher-btn:hover {
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

/* Responsive Design */
@media (max-width: 768px) {
    .welcome-title {
        font-size: 2rem;
    }
    
    .stats-card .card-body {
        padding: 1rem;
    }
    
    .table-container .card-header {
        padding: 1rem;
    }
}
</style>

<?php

$message = '';
$message_type = '';

// Handle teacher actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'create':
                $first_name = sanitizeInput($_POST['first_name'] ?? '');
                $last_name = sanitizeInput($_POST['last_name'] ?? '');
                $username = sanitizeInput($_POST['username'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $status = sanitizeInput($_POST['status'] ?? 'active');
                
                if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
                    $message = 'All fields are required.';
                    $message_type = 'danger';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = 'Invalid email address.';
                    $message_type = 'danger';
                } elseif (strlen($password) < 6) {
                    $message = 'Password must be at least 6 characters.';
                    $message_type = 'danger';
                } else {
                    // Check for duplicate email/username
                    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
                    $stmt->execute([$email, $username]);
                    if ($stmt->fetch()) {
                        $message = 'Email or username already exists.';
                        $message_type = 'danger';
                    } else {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Generate unique teacher ID
                        $teacherId = generateTeacherId($db);
                        
                        $stmt = $db->prepare('INSERT INTO users (username, email, password, first_name, last_name, role, identifier, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$username, $email, $hashed, $first_name, $last_name, 'teacher', $teacherId, $status]);
                        $message = 'Teacher created successfully with ID: ' . $teacherId;
                        $message_type = 'success';
                    }
                }
                break;
                
            case 'update':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $first_name = sanitizeInput($_POST['first_name'] ?? '');
                $last_name = sanitizeInput($_POST['last_name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $status = sanitizeInput($_POST['status'] ?? 'active');
                
                if (empty($first_name) || empty($last_name) || empty($email)) {
                    $message = 'All fields are required.';
                    $message_type = 'danger';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = 'Invalid email address.';
                    $message_type = 'danger';
                } else {
                    // Check for duplicate email (excluding current user)
                    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $message = 'Email already exists.';
                        $message_type = 'danger';
                    } else {
                        if (!empty($password)) {
                            if (strlen($password) < 6) {
                                $message = 'Password must be at least 6 characters.';
                                $message_type = 'danger';
                                break;
                            }
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, password = ?, status = ? WHERE id = ?');
                            $stmt->execute([$first_name, $last_name, $email, $hashed, $status, $user_id]);
                        } else {
                            $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, status = ? WHERE id = ?');
                            $stmt->execute([$first_name, $last_name, $email, $status, $user_id]);
                        }
                        $message = 'Teacher updated successfully.';
                        $message_type = 'success';
                    }
                }
                break;
                
            case 'archive':
                $user_id = (int)($_POST['user_id'] ?? 0);
                if ($user_id === $_SESSION['user_id']) {
                    $message = 'You cannot archive your own account.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('UPDATE users SET is_archived = 1 WHERE id = ? AND role = ?');
                    $stmt->execute([$user_id, 'teacher']);
                    $message = 'Teacher archived successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'recover':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $stmt = $db->prepare('UPDATE users SET is_archived = 0 WHERE id = ? AND role = ?');
                $stmt->execute([$user_id, 'teacher']);
                $message = 'Teacher recovered successfully.';
                $message_type = 'success';
                break;
        }
    }
}

// Get teachers with search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$show_archived = sanitizeInput($_GET['show_archived'] ?? '0');

$where_conditions = ["role = 'teacher'"];
$params = [];

// Add archive filter - show only archived teachers when requested, otherwise show only active teachers
if ($show_archived === '1') {
    $where_conditions[] = "is_archived = 1";
} else {
    $where_conditions[] = "(is_archived = 0 OR is_archived IS NULL)";
}

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ? OR identifier LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "(status IS NULL OR status = 'active')";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "status = 'inactive'";
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
$stmt = $db->prepare("SELECT * FROM users $where_clause ORDER BY last_name, first_name");
$stmt->execute($params);
$teachers = $stmt->fetchAll();

// Assign IDs to existing teachers who don't have them
foreach ($teachers as $teacher) {
    if (empty($teacher['identifier'])) {
        $newId = assignUserId($db, $teacher['id'], 'teacher');
        if ($newId) {
            // Update the teacher array with the new ID
            $teacher['identifier'] = $newId;
        }
    }
}

$total_teachers = count($teachers);
$active_teachers = 0;
$inactive_teachers = 0;
foreach ($teachers as $teacher) {
    if (isset($teacher['status']) && $teacher['status'] === 'inactive') {
        $inactive_teachers++;
    } else {
        $active_teachers++;
    }
}
?>

<div class="page-container">
    <div class="container-fluid py-4">
        <!-- Enhanced Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Manage Teachers</h1>
                    <p class="welcome-subtitle">Create, edit, and manage teacher accounts and their status</p>
                    <a href="dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="welcome-decoration">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div class="floating-shapes"></div>
                </div>
            </div>
            <div class="accent-line"></div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card stats-card stats-primary border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <div class="d-flex align-items-center justify-content-center mb-3">
                                <div class="stats-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-people-fill fs-4"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-white"><?= $total_teachers ?></h3>
                            <p class="text-white mb-0 small fw-medium">Total Teachers</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card stats-success border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <div class="d-flex align-items-center justify-content-center mb-3">
                                <div class="stats-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-person-check-fill fs-4"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-white"><?= $active_teachers ?></h3>
                            <p class="text-white mb-0 small fw-medium">Active Teachers</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card stats-secondary border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <div class="d-flex align-items-center justify-content-center mb-3">
                                <div class="stats-icon bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-archive-fill fs-4"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-white"><?= $inactive_teachers ?></h3>
                            <p class="text-white mb-0 small fw-medium">Archived Teachers</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="card search-filter-card mb-4">
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label fw-semibold">
                                <i class="bi bi-search me-2"></i>Search Teachers
                            </label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by name, email, username, or ID..." 
                                       autocomplete="off">
                                <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                    <small class="text-muted" id="searchCounter" style="display: none;">
                                        <span id="charCount">0</span> chars
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label fw-semibold">
                                <i class="bi bi-shield me-2"></i>Status
                            </label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Archived</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="sort" class="form-label fw-semibold">
                                <i class="bi bi-sort me-2"></i>Sort By
                            </label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="name">Name</option>
                                <option value="email">Email</option>
                                <option value="date">Date Added</option>
                                <option value="status">Status</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="order" class="form-label fw-semibold">
                                <i class="bi bi-arrow-up-down me-2"></i>Order
                            </label>
                            <select class="form-select" id="order" name="order">
                                <option value="asc">Ascending</option>
                                <option value="desc">Descending</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-primary" id="filterBtn">
                                    <i class="bi bi-funnel me-2"></i>Search
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clearBtn">
                                    <i class="bi bi-x-circle me-2"></i>Clear
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <button class="btn add-teacher-btn" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="bi bi-plus-circle me-2"></i>Add Teacher
                    </button>
                </div>
                <div class="d-flex align-items-center">
                    <div class="loading-indicator me-3" id="loadingIndicator" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span class="ms-2 text-muted">Loading teachers...</span>
                    </div>
                </div>
            </div>
            <?php if ($show_archived === '1'): ?>
                <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                    <i class="bi bi-archive-fill me-2"></i>
                    <div>
                        <strong>Viewing Archived Teachers</strong> - These teachers have been archived and can be recovered. 
                        Click the green recover button (🔄) to restore them to active status.
                    </div>
                </div>
            <?php endif; ?>
            <div class="card table-container">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5>
                            <i class="bi bi-person-badge me-2"></i><?= $show_archived === '1' ? 'Archived Teachers List' : 'Teachers List' ?>
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($show_archived === '1'): ?>
                                <a href="teachers.php" class="btn btn-outline-success btn-sm" title="Back to Active Teachers">
                                    <i class="bi bi-arrow-left me-1"></i>Back to Active
                                </a>
                            <?php else: ?>
                                <a href="teachers.php?show_archived=1" class="btn btn-outline-warning btn-sm" title="View Archived Teachers">
                                    <i class="bi bi-archive me-1"></i>View Archived
                                </a>
                            <?php endif; ?>
                            <span class="badge bg-primary fs-6" id="teacherCountBadge"><?= $total_teachers ?> teachers</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="scrollable-table">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">
                                        <i class="bi bi-person me-2"></i>Teacher Info
                                    </th>
                                    <th class="border-0">
                                        <i class="bi bi-card-text me-2"></i>Teacher ID
                                    </th>
                                    <th class="border-0">
                                        <i class="bi bi-shield me-2"></i>Status
                                    </th>
                                    <th class="border-0">
                                        <i class="bi bi-envelope me-2"></i>Contact
                                    </th>
                                    <th class="border-0">
                                        <i class="bi bi-calendar me-2"></i>Joined
                                    </th>
                                    <th class="border-0 text-center">
                                        <i class="bi bi-gear me-2"></i>Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="teachersTableContainer">
                                <?php foreach ($teachers as $teacher): ?>
                                <tr class="<?= (isset($teacher['is_archived']) && $teacher['is_archived']) ? 'table-warning opacity-75' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <img src="<?= getProfilePictureUrl($teacher['profile_picture'] ?? null, 'medium') ?>" 
                                                     class="rounded-circle me-3" 
                                                     width="40" height="40" 
                                                     alt="Profile">
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></h6>
                                                <small class="text-muted">@<?= htmlspecialchars($teacher['username']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($teacher['identifier'])): ?>
                                            <span class="badge bg-success"><?= htmlspecialchars($teacher['identifier']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= (isset($teacher['status']) && $teacher['status'] === 'inactive') ? 'secondary' : 'success' ?>">
                                            <i class="bi bi-<?= (isset($teacher['status']) && $teacher['status'] === 'inactive') ? 'archive' : 'check-circle' ?> me-1"></i>
                                            <?= (isset($teacher['status']) && $teacher['status'] === 'inactive') ? 'Archived' : 'Active' ?>
                                        </span>
                                        <?php if (isset($teacher['is_archived']) && $teacher['is_archived']): ?>
                                            <span class="badge bg-warning">
                                                <i class="bi bi-archive me-1"></i>Archived
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-envelope text-muted me-2"></i>
                                            <span class="small"><?= htmlspecialchars($teacher['email']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar3 text-muted me-2"></i>
                                            <span class="small"><?= date('M j, Y', strtotime($teacher['created_at'])) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-1">
                                            <button class="btn btn-sm btn-info text-white" 
                                                    onclick="viewTeacher(<?= $teacher['id'] ?>)"
                                                    title="View Teacher Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="editTeacher(<?= $teacher['id'] ?>)"
                                                    title="Edit Teacher">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if (isset($teacher['is_archived']) && $teacher['is_archived']): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="recoverTeacher(<?= $teacher['id'] ?>)"
                                                        title="Recover Teacher">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="archiveTeacher(<?= $teacher['id'] ?>)"
                                                        title="Archive Teacher">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Edit Teacher Modal (simplified) -->
                                <div class="modal fade" id="editTeacherModal<?= $teacher['id'] ?>" tabindex="-1" aria-labelledby="editTeacherLabel<?= $teacher['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" action="teachers.php">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editTeacherLabel<?= $teacher['id'] ?>">Edit Teacher</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="user_id" value="<?= $teacher['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                    <div class="mb-3">
                                                        <label for="teacher_id<?= $teacher['id'] ?>" class="form-label">Teacher ID</label>
                                                        <input type="text" class="form-control" id="teacher_id<?= $teacher['id'] ?>" value="<?= htmlspecialchars($teacher['identifier'] ?? 'Not assigned') ?>" readonly>
                                                        <div class="form-text">Teacher ID cannot be modified</div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="first_name<?= $teacher['id'] ?>" class="form-label">First Name</label>
                                                        <input type="text" class="form-control" id="first_name<?= $teacher['id'] ?>" name="first_name" required value="<?= htmlspecialchars($teacher['first_name']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="last_name<?= $teacher['id'] ?>" class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" id="last_name<?= $teacher['id'] ?>" name="last_name" required value="<?= htmlspecialchars($teacher['last_name']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="email<?= $teacher['id'] ?>" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="email<?= $teacher['id'] ?>" name="email" required value="<?= htmlspecialchars($teacher['email']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="password<?= $teacher['id'] ?>" class="form-label">Password (leave blank to keep current)</label>
                                                        <input type="password" class="form-control" id="password<?= $teacher['id'] ?>" name="password">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="status<?= $teacher['id'] ?>" class="form-label">Status</label>
                                                        <select class="form-select" id="status<?= $teacher['id'] ?>" name="status">
                                                            <option value="active" <?= (isset($teacher['status']) && $teacher['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                                                            <option value="inactive" <?= (isset($teacher['status']) && $teacher['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="teachers.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTeacherLabel">Add Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                    <div class="mb-3">
                        <label for="first_name_add" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name_add" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name_add" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name_add" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="username_add" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username_add" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="email_add" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email_add" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_add" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password_add" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="status_add" class="form-label">Status</label>
                        <select class="form-select" id="status_add" name="status">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Real-time filtering functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing teacher search functionality...');
    const searchInput = document.getElementById('search');
    const statusSelect = document.getElementById('status');
    const sortSelect = document.getElementById('sort');
    const orderSelect = document.getElementById('order');
    const filterBtn = document.getElementById('filterBtn');
    const clearBtn = document.getElementById('clearBtn');
    const teachersTableContainer = document.getElementById('teachersTableContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const teacherCountBadge = document.getElementById('teacherCountBadge');
    
    // Debug: Check if elements are found
    console.log('Search input found:', !!searchInput);
    console.log('Status select found:', !!statusSelect);
    console.log('Sort select found:', !!sortSelect);
    console.log('Order select found:', !!orderSelect);
    
    // Debug: Check input properties
    if (searchInput) {
        console.log('Search input disabled:', searchInput.disabled);
        console.log('Search input readonly:', searchInput.readOnly);
        console.log('Search input value:', searchInput.value);
        
        // Test if input is focusable
        searchInput.addEventListener('focus', function() {
            console.log('Search input focused!');
        });
        
        searchInput.addEventListener('click', function() {
            console.log('Search input clicked!');
        });
    }
    
    let searchTimeout;
    
    // Function to perform AJAX search
    function performSearch() {
        const search = searchInput ? searchInput.value.trim() : '';
        const status = statusSelect ? statusSelect.value : '';
        const sort = sortSelect ? sortSelect.value : 'name';
        const order = orderSelect ? orderSelect.value : 'asc';
        
        // Show loading indicator
        if (loadingIndicator) {
            loadingIndicator.style.display = 'flex';
        }
        
        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.set('search', search);
        url.searchParams.set('status', status);
        url.searchParams.set('sort', sort);
        url.searchParams.set('order', order);
        window.history.pushState({}, '', url);
        
        // Make AJAX request
        fetch(`ajax_get_teachers.php?${url.searchParams.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update table content
                    if (teachersTableContainer) {
                        teachersTableContainer.innerHTML = data.teachers_html;
                    }
                    
                    // Update statistics
                    updateStats(data.stats);
                    
                    // Update teacher count badge
                    if (teacherCountBadge) {
                        teacherCountBadge.textContent = `${data.total_teachers} teachers`;
                    }
                } else {
                    console.error('Error fetching teachers:', data.error);
                    showError('Failed to load teachers. Please try again.');
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                showError('Network error. Please check your connection.');
            })
            .finally(() => {
                // Hide loading indicator
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
            });
    }
    
    // Function to update statistics with animation
    function updateStats(stats) {
        const statCards = document.querySelectorAll('.stats-card h3');
        if (statCards.length >= 3) {
            animateNumberChange(statCards[0], stats.total_teachers);
            animateNumberChange(statCards[1], stats.active_teachers);
            animateNumberChange(statCards[2], stats.inactive_teachers);
        }
    }
    
    // Function to animate number changes
    function animateNumberChange(element, newValue) {
        if (element) {
            element.classList.add('updated');
            element.textContent = newValue;
            setTimeout(() => element.classList.remove('updated'), 300);
        }
    }
    
    // Function to show error message
    function showError(message) {
        if (teachersTableContainer) {
            teachersTableContainer.innerHTML = `
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
    }
    
    // Real-time search with faster debouncing and character counter
    if (searchInput) {
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
            
            // Show immediate visual feedback
            searchInput.classList.add('searching');
            
            // Faster debouncing for better responsiveness
            searchTimeout = setTimeout(() => {
                searchInput.classList.remove('searching');
                performSearch();
            }, 200); // Reduced to 200ms for faster response
        });
    } else {
        console.error('Search input element not found!');
    }
    
    // Immediate filtering for dropdowns with visual feedback
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            this.classList.add('filtering');
            performSearch();
            setTimeout(() => this.classList.remove('filtering'), 1000);
        });
    }
    
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            this.classList.add('filtering');
            performSearch();
            setTimeout(() => this.classList.remove('filtering'), 1000);
        });
    }
    
    if (orderSelect) {
        orderSelect.addEventListener('change', function() {
            this.classList.add('filtering');
            performSearch();
            setTimeout(() => this.classList.remove('filtering'), 1000);
        });
    }
    
    // Filter button (for manual trigger)
    if (filterBtn) {
        filterBtn.addEventListener('click', performSearch);
    }
    
    // Clear button
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (searchInput) searchInput.value = '';
            if (statusSelect) statusSelect.value = '';
            if (sortSelect) sortSelect.value = 'name';
            if (orderSelect) orderSelect.value = 'asc';
            performSearch();
        });
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (searchInput) searchInput.value = urlParams.get('search') || '';
        if (statusSelect) statusSelect.value = urlParams.get('status') || '';
        if (sortSelect) sortSelect.value = urlParams.get('sort') || 'name';
        if (orderSelect) orderSelect.value = urlParams.get('order') || 'asc';
        performSearch();
    });
    
    // Clean up any leftover dynamic modals on page load
    cleanupDynamicModals();
});

// Global functions for teacher actions
function viewTeacher(teacherId) {
    console.log('Viewing teacher:', teacherId);
    
    // Check if modal already exists
    let modal = document.getElementById(`viewTeacherModal${teacherId}`);
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        return;
    }
    
    // Create modal dynamically
    createViewModal(teacherId);
}

function editTeacher(teacherId) {
    console.log('Editing teacher:', teacherId);
    
    // Check if modal already exists
    let modal = document.getElementById(`editTeacherModal${teacherId}`);
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        return;
    }
    
    // Create modal dynamically
    createEditModal(teacherId);
}

function archiveTeacher(teacherId) {
    if (confirm('Are you sure you want to archive this teacher? They will be hidden but can be recovered later.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'teachers.php';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'archive';
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = teacherId;
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = getCSRFToken();
        
        form.appendChild(actionInput);
        form.appendChild(userIdInput);
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function recoverTeacher(teacherId) {
    if (confirm('Are you sure you want to recover this teacher?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'teachers.php';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'recover';
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = teacherId;
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = getCSRFToken();
        
        form.appendChild(actionInput);
        form.appendChild(userIdInput);
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Function to create view modal dynamically
function createViewModal(teacherId) {
    console.log('Creating view modal for teacher:', teacherId);
    
    fetch('ajax_get_teacher_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `teacher_id=${teacherId}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Teacher details response:', data);
        
        if (data.success) {
            const teacherData = data.teacher;
            const basicInfo = teacherData.basic_info;
            const courses = teacherData.courses || [];
            const sections = teacherData.sections || [];
            const stats = teacherData.statistics || {};
            const recentActivity = teacherData.recent_activity || [];
            
            const modalHtml = `
                <div class="modal fade" id="viewTeacherModal${teacherId}" tabindex="-1" aria-labelledby="viewTeacherLabel${teacherId}" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="viewTeacherLabel${teacherId}">
                                    <i class="bi bi-person-badge me-2"></i>Teacher Details
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-0">
                                <!-- Teacher Profile Section -->
                                <div class="bg-light p-4 border-bottom">
                                    <div class="row align-items-center">
                                        <div class="col-md-3 text-center">
                                            <img src="${basicInfo.profile_picture_url}" 
                                                 class="rounded-circle mb-3 shadow" 
                                             width="120" height="120" 
                                             alt="Profile Picture">
                                            <h4 class="mb-1">${basicInfo.first_name} ${basicInfo.last_name}</h4>
                                            <p class="text-muted mb-0">@${basicInfo.username}</p>
                                            <span class="badge bg-${basicInfo.status === 'inactive' ? 'secondary' : 'success'} fs-6">
                                                <i class="bi bi-${basicInfo.status === 'inactive' ? 'archive' : 'check-circle'} me-1"></i>
                                                ${basicInfo.status === 'inactive' ? 'Archived' : 'Active'}
                                            </span>
                                    </div>
                                        <div class="col-md-9">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <strong><i class="bi bi-card-text me-2"></i>Teacher ID:</strong>
                                                        <div class="mt-1">
                                                            ${basicInfo.identifier ? `<span class="badge bg-success fs-6">${basicInfo.identifier}</span>` : '<span class="text-muted">Not assigned</span>'}
                                            </div>
                                        </div>
                                                    <div class="mb-3">
                                                        <strong><i class="bi bi-envelope me-2"></i>Email:</strong>
                                                        <div class="mt-1">${basicInfo.email}</div>
                                        </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <strong><i class="bi bi-building me-2"></i>Department:</strong>
                                                        <div class="mt-1">${basicInfo.department || 'Not specified'}</div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong><i class="bi bi-calendar me-2"></i>Joined:</strong>
                                                        <div class="mt-1">${new Date(basicInfo.created_at).toLocaleDateString()}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Statistics Cards -->
                                <div class="p-4">
                                    <h5 class="mb-3"><i class="bi bi-graph-up me-2"></i>Statistics</h5>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-3">
                                            <div class="card bg-primary text-white">
                                                <div class="card-body text-center">
                                                    <i class="bi bi-book fs-1 mb-2"></i>
                                                    <h3 class="mb-1">${stats.total_courses || 0}</h3>
                                                    <small>Total Courses</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card bg-success text-white">
                                                <div class="card-body text-center">
                                                    <i class="bi bi-people fs-1 mb-2"></i>
                                                    <h3 class="mb-1">${stats.total_students || 0}</h3>
                                                    <small>Total Students</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card bg-info text-white">
                                                <div class="card-body text-center">
                                                    <i class="bi bi-clipboard-check fs-1 mb-2"></i>
                                                    <h3 class="mb-1">${stats.total_assessments || 0}</h3>
                                                    <small>Assessments</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card bg-warning text-white">
                                                <div class="card-body text-center">
                                                    <i class="bi bi-percent fs-1 mb-2"></i>
                                                    <h3 class="mb-1">${stats.average_score || 0}%</h3>
                                                    <small>Avg Score</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tabs for detailed information -->
                                    <ul class="nav nav-tabs" id="teacherTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button" role="tab">
                                                <i class="bi bi-book me-2"></i>Courses (${courses.length})
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button" role="tab">
                                                <i class="bi bi-people me-2"></i>Sections (${sections.length})
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                                <i class="bi bi-clock-history me-2"></i>Recent Activity
                                            </button>
                                        </li>
                                    </ul>
                                    
                                    <div class="tab-content" id="teacherTabContent">
                                        <!-- Courses Tab -->
                                        <div class="tab-pane fade show active" id="courses" role="tabpanel">
                                            <div class="mt-3">
                                                ${courses.length > 0 ? `
                                                    <div class="table-responsive">
                                                        <table class="table table-hover">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Course Code</th>
                                                                    <th>Course Name</th>
                                                                    <th>Year Level</th>
                                                                    <th>Students</th>
                                                                    <th>Modules</th>
                                                                    <th>Videos</th>
                                                                    <th>Assessments</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                ${courses.map(course => `
                                                                    <tr>
                                                                        <td><span class="badge bg-primary">${course.course_code}</span></td>
                                                                        <td>${course.course_name}</td>
                                                                        <td>${course.year_level ? course.year_level + ' Year' : 'N/A'}</td>
                                                                        <td><span class="badge bg-info">${course.student_count || 0}</span></td>
                                                                        <td><span class="badge bg-secondary">${course.module_count || 0}</span></td>
                                                                        <td><span class="badge bg-warning">${course.video_count || 0}</span></td>
                                                                        <td><span class="badge bg-danger">${course.assessment_count || 0}</span></td>
                                                                        <td>
                                                                            <span class="badge bg-${course.status === 'active' ? 'success' : 'secondary'}">
                                                                                ${course.status}
                                                </span>
                                                                        </td>
                                                                    </tr>
                                                                `).join('')}
                                                            </tbody>
                                                        </table>
                                            </div>
                                                ` : '<div class="text-center py-4 text-muted"><i class="bi bi-book fs-1 mb-3"></i><p>No courses assigned</p></div>'}
                                        </div>
                                        </div>
                                        
                                        <!-- Sections Tab -->
                                        <div class="tab-pane fade" id="sections" role="tabpanel">
                                            <div class="mt-3">
                                                ${sections.length > 0 ? `
                                                    <div class="table-responsive">
                                                        <table class="table table-hover">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Section Name</th>
                                                                    <th>Year Level</th>
                                                                    <th>Academic Year</th>
                                                                    <th>Semester</th>
                                                                    <th>Students</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                ${sections.map(section => `
                                                                    <tr>
                                                                        <td><strong>${section.section_name}</strong></td>
                                                                        <td><span class="badge bg-primary">${section.year_level} Year</span></td>
                                                                        <td>${section.academic_year || 'N/A'}</td>
                                                                        <td>${section.semester_name || 'N/A'}</td>
                                                                        <td><span class="badge bg-info">${section.student_count || 0}</span></td>
                                                                    </tr>
                                                                `).join('')}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                ` : '<div class="text-center py-4 text-muted"><i class="bi bi-people fs-1 mb-3"></i><p>No sections assigned</p></div>'}
                                            </div>
                                        </div>
                                        
                                        <!-- Activity Tab -->
                                        <div class="tab-pane fade" id="activity" role="tabpanel">
                                            <div class="mt-3">
                                                ${recentActivity.length > 0 ? `
                                                    <div class="list-group">
                                                        ${recentActivity.map(activity => `
                                                            <div class="list-group-item">
                                                                <div class="d-flex w-100 justify-content-between">
                                                                    <h6 class="mb-1">
                                                                        <i class="bi bi-${activity.activity_type === 'course_created' ? 'plus-circle' : 'check-circle'} me-2"></i>
                                                                        ${activity.title}
                                                                    </h6>
                                                                    <small>${new Date(activity.activity_date).toLocaleDateString()}</small>
                                                                </div>
                                                            </div>
                                                        `).join('')}
                                                    </div>
                                                ` : '<div class="text-center py-4 text-muted"><i class="bi bi-clock-history fs-1 mb-3"></i><p>No recent activity</p></div>'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-2"></i>Close
                                </button>
                                <button type="button" class="btn btn-primary" onclick="editTeacher(${teacherId})">
                                    <i class="bi bi-pencil me-2"></i>Edit Teacher
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to DOM
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById(`viewTeacherModal${teacherId}`));
            modal.show();
            
            // Add event listener to remove modal when hidden
            document.getElementById(`viewTeacherModal${teacherId}`).addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        } else {
            alert('Unable to load teacher details. Please refresh the page.');
        }
    })
    .catch(error => {
        console.error('Error fetching teacher details:', error);
        alert('Unable to load teacher details. Please refresh the page.');
    });
}

// Function to create edit modal dynamically
function createEditModal(teacherId) {
    console.log('Creating edit modal for teacher:', teacherId);
    
    fetch('ajax_get_teacher_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `teacher_id=${teacherId}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Teacher details response:', data);
        
        if (data.success) {
            const teacher = data.teacher;
            const modalHtml = `
                <div class="modal fade" id="editTeacherModal${teacherId}" tabindex="-1" aria-labelledby="editTeacherLabel${teacherId}" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post" action="teachers.php">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editTeacherLabel${teacherId}">Edit Teacher</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="user_id" value="${teacherId}">
                                    <input type="hidden" name="csrf_token" value="${getCSRFToken()}">
                                    <div class="mb-3">
                                        <label for="teacher_id${teacherId}" class="form-label">Teacher ID</label>
                                        <input type="text" class="form-control" id="teacher_id${teacherId}" value="${teacher.identifier || 'Not assigned'}" readonly>
                                        <div class="form-text">Teacher ID cannot be modified</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="first_name${teacherId}" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name${teacherId}" name="first_name" required value="${teacher.first_name}">
                                    </div>
                                    <div class="mb-3">
                                        <label for="last_name${teacherId}" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name${teacherId}" name="last_name" required value="${teacher.last_name}">
                                    </div>
                                    <div class="mb-3">
                                        <label for="email${teacherId}" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email${teacherId}" name="email" required value="${teacher.email}">
                                    </div>
                                    <div class="mb-3">
                                        <label for="password${teacherId}" class="form-label">Password (leave blank to keep current)</label>
                                        <input type="password" class="form-control" id="password${teacherId}" name="password">
                                    </div>
                                    <div class="mb-3">
                                        <label for="status${teacherId}" class="form-label">Status</label>
                                        <select class="form-select" id="status${teacherId}" name="status">
                                            <option value="active" ${teacher.status === 'active' ? 'selected' : ''}>Active</option>
                                            <option value="inactive" ${teacher.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to DOM
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById(`editTeacherModal${teacherId}`));
            modal.show();
            
            // Add event listener to remove modal when hidden
            document.getElementById(`editTeacherModal${teacherId}`).addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        } else {
            alert('Unable to load teacher details. Please refresh the page.');
        }
    })
    .catch(error => {
        console.error('Error fetching teacher details:', error);
        alert('Unable to load teacher details. Please refresh the page.');
    });
}

// Function to get CSRF token
function getCSRFToken() {
    const form = document.querySelector('form[method="post"]');
    if (form) {
        const csrfInput = form.querySelector('input[name="csrf_token"]');
        return csrfInput ? csrfInput.value : '';
    }
    return '';
}

// Function to clean up dynamic modals
function cleanupDynamicModals() {
    const dynamicModals = document.querySelectorAll('[id^="viewTeacherModal"], [id^="editTeacherModal"]');
    dynamicModals.forEach(modal => modal.remove());
}
</script>

<?php require_once '../includes/footer.php'; ?> 