<?php
$page_title = 'Manage Students';
require_once '../config/config.php';
require_once '../includes/student_id_generator.php';
requireRole('admin');
require_once '../includes/header.php';
?>

<style>
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* Enhanced Students Page Styling - Inspired by Admin Dashboard */
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

.search-filter-card .card-body {
    padding: 1.5rem;
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
    min-width: 1200px; /* Ensure minimum width for proper display with section column */
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

.btn-outline-danger {
    background: #dc3545;
    color: white;
    border: none;
}

.btn-outline-danger:hover {
    background: #bb2d3b;
    color: white;
}

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

/* Modal Styling */
.modal-content {
    border-radius: var(--border-radius-lg);
    border: none;
    box-shadow: var(--shadow-lg);
}

.modal-header {
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
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

/* User Count Badge Animation */
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
    
    .search-filter-card .card-body {
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

// Handle student actions
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
                $is_irregular = isset($_POST['is_irregular']) ? intval($_POST['is_irregular']) : 0;
                
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
                        
                        // Generate unique student ID
                        $studentId = generateStudentId($db);
                        
                        $stmt = $db->prepare('INSERT INTO users (username, email, password, first_name, last_name, role, identifier, is_irregular, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$username, $email, $hashed, $first_name, $last_name, 'student', $studentId, $is_irregular, 'active']);
                        $message = 'Student created successfully with ID: ' . $studentId;
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
                $is_irregular = isset($_POST['is_irregular']) ? intval($_POST['is_irregular']) : 0;
                
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
                            $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, password = ?, is_irregular = ? WHERE id = ?');
                            $stmt->execute([$first_name, $last_name, $email, $hashed, $is_irregular, $user_id]);
                        } else {
                            $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, is_irregular = ? WHERE id = ?');
                            $stmt->execute([$first_name, $last_name, $email, $is_irregular, $user_id]);
                        }
                        $message = 'Student updated successfully.';
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
                    $stmt->execute([$user_id, 'student']);
                    $message = 'Student archived successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'recover':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $stmt = $db->prepare('UPDATE users SET is_archived = 0 WHERE id = ? AND role = ?');
                $stmt->execute([$user_id, 'student']);
                $message = 'Student recovered successfully.';
                $message_type = 'success';
                break;
        }
    }
}

// Get students with search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$section_filter = sanitizeInput($_GET['section'] ?? '');
$year_filter = sanitizeInput($_GET['year'] ?? '');
$show_archived = sanitizeInput($_GET['show_archived'] ?? '0');

$where_conditions = ["role = 'student'"];
$params = [];

// Add archive filter - show only archived students when requested, otherwise show only active students
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

// Fetch students with filters
$stmt = $db->prepare("
    SELECT id, username, email, first_name, last_name, profile_picture, created_at, is_irregular, status, identifier 
    FROM users 
    $where_clause 
    ORDER BY last_name, first_name
");
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get section information for each student
foreach ($students as &$student) {
    $stmt = $db->prepare("
        SELECT s.id as section_id, 
               s.section_name, 
               s.year_level,
               s.description as section_description
        FROM sections s
        WHERE JSON_SEARCH(s.students, 'one', CAST(? AS CHAR)) IS NOT NULL
        ORDER BY s.year_level, s.section_name
    ");
    $stmt->execute([$student['id']]);
    $student['sections'] = $stmt->fetchAll();
}

// Apply section and year filters after fetching students
if (!empty($section_filter) || !empty($year_filter)) {
    $filtered_students = [];
    foreach ($students as $student) {
        $include_student = true;
        
        // Filter by section
        if (!empty($section_filter)) {
            $section_found = false;
            foreach ($student['sections'] as $section) {
                if ($section['section_id'] == $section_filter) {
                    $section_found = true;
                    break;
                }
            }
            if (!$section_found) {
                $include_student = false;
            }
        }
        
        // Filter by year
        if (!empty($year_filter) && $include_student) {
            $year_found = false;
            foreach ($student['sections'] as $section) {
                if ($section['year_level'] == $year_filter) {
                    $year_found = true;
                    break;
                }
            }
            if (!$year_found) {
                $include_student = false;
            }
        }
        
        if ($include_student) {
            $filtered_students[] = $student;
        }
    }
    $students = $filtered_students;
}

// Get all sections for filter dropdown
$section_sql = "SELECT id, section_name, year_level FROM sections ORDER BY year_level, section_name";
$section_res = $db->query($section_sql);
$sections_raw = $section_res ? $section_res->fetchAll() : [];

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year_level']}{$section['section_name']}";
}

$sections = [];
foreach ($sections_raw as $section) {
    $sections[$section['id']] = formatSectionName($section);
}

// Assign IDs to existing students who don't have them
foreach ($students as $student) {
    if (empty($student['identifier'])) {
        $newId = assignUserId($db, $student['id'], 'student');
        if ($newId) {
            // Update the student array with the new ID
            $student['identifier'] = $newId;
        }
    }
}

$total_students = count($students);
$total_regular = 0;
$total_irregular = 0;
$active_students = 0;
$inactive_students = 0;
$students_with_sections = 0;
$students_without_sections = 0;
$section_counts = [];

foreach ($students as $stu) {
    if ($stu['is_irregular']) {
        $total_irregular++;
    } else {
        $total_regular++;
    }
    if (isset($stu['status']) && $stu['status'] === 'inactive') {
        $inactive_students++;
    } else {
        $active_students++;
    }
    
    // Count students with/without sections
    if (!empty($stu['sections']) && count($stu['sections']) > 0) {
        $students_with_sections++;
        foreach ($stu['sections'] as $section) {
            $section_key = $section['year_level'] . ' - ' . $section['section_name'];
            if (!isset($section_counts[$section_key])) {
                $section_counts[$section_key] = 0;
            }
            $section_counts[$section_key]++;
        }
    } else {
        $students_without_sections++;
    }
}
?>
<div class="page-container">
    <div class="container-fluid py-4">
        <!-- Enhanced Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Manage Students</h1>
                    <p class="welcome-subtitle">Create, edit, and manage student accounts and their academic status</p>
                    <a href="dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="welcome-decoration">
                        <i class="bi bi-mortarboard"></i>
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
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-primary border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $total_students ?></h3>
                    <p class="text-white mb-0 small fw-medium">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-success border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-person-check-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $active_students ?></h3>
                    <p class="text-white mb-0 small fw-medium">Active Students</p>
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
                    <h3 class="fw-bold mb-1 text-white"><?= $inactive_students ?></h3>
                    <p class="text-white mb-0 small fw-medium">Archived Students</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-info border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-person-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $total_regular ?></h3>
                    <p class="text-white mb-0 small fw-medium">Regular Students</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-danger border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-danger text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $total_irregular ?></h3>
                    <p class="text-white mb-0 small fw-medium">Irregular Students</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-purple border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-purple text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-collection-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $students_with_sections ?></h3>
                    <p class="text-white mb-0 small fw-medium">With Sections</p>
                </div>
            </div>
        </div>
    </div>

        <!-- Search and Filter Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card search-filter-card">
                    <div class="card-body">
                        <form method="GET" action="students.php" class="row g-3 align-items-end" id="filterForm">
                        <div class="col-md-3">
                            <label for="search" class="form-label fw-semibold">
                                <i class="bi bi-search me-2"></i>Search Students
                            </label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by name, email, username, or ID..." 
                                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                                <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                    <small class="text-muted" id="searchCounter" style="display: none;">
                                        <span id="charCount">0</span> characters
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label fw-semibold">
                                <i class="bi bi-funnel me-2"></i>Filter by Status
                            </label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="section" class="form-label fw-semibold">
                                <i class="bi bi-collection me-2"></i>Filter by Section
                            </label>
                            <select class="form-select" id="section" name="section">
                                <option value="">All Sections</option>
                                <?php foreach ($sections_raw as $section): ?>
                                    <option value="<?= $section['id'] ?>" <?= $section_filter == $section['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(formatSectionName($section)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="year" class="form-label fw-semibold">
                                <i class="bi bi-calendar me-2"></i>Filter by Year
                            </label>
                            <select class="form-select" id="year" name="year">
                                <option value="">All Years</option>
                                <option value="1" <?= $year_filter === '1' ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= $year_filter === '2' ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= $year_filter === '3' ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= $year_filter === '4' ? 'selected' : '' ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-primary w-100" id="filterBtn">
                                <i class="bi bi-search me-2"></i>Search
                            </button>
                        </div>
                        <div class="col-md-1">
                            <a href="students.php" class="btn btn-outline-secondary w-100" id="clearBtn">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset
                            </a>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
        <!-- Students Table -->
        <div class="row">
            <div class="col-12">
                <?php if ($show_archived === '1'): ?>
                    <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                        <i class="bi bi-archive-fill me-2"></i>
                        <div>
                            <strong>Viewing Archived Students</strong> - These students have been archived and can be recovered. 
                            Click the green recover button (🔄) to restore them to active status.
                        </div>
                    </div>
                <?php endif; ?>
                <div class="card table-container">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>
                                <i class="bi bi-mortarboard me-2"></i><?= $show_archived === '1' ? 'Archived Students Management' : 'Students Management' ?>
                            </h5>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($show_archived === '1'): ?>
                                    <a href="students.php" class="btn btn-outline-success btn-sm" title="Back to Active Students">
                                        <i class="bi bi-arrow-left me-1"></i>Back to Active
                                    </a>
                                <?php else: ?>
                                    <a href="students.php?show_archived=1" class="btn btn-outline-warning btn-sm" title="View Archived Students">
                                        <i class="bi bi-archive me-1"></i>View Archived
                                    </a>
                                <?php endif; ?>
                                <span class="badge bg-primary fs-6"><?= count($students) ?> students found</span>
                                <div id="loadingIndicator" style="display: none;">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0" id="studentsTableContainer">
                        <?php if (empty($students)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-mortarboard fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">No students found</h5>
                                <p class="text-muted">Try adjusting your search criteria or add a new student.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                    <i class="bi bi-plus-circle me-2"></i>Add New Student
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="scrollable-table">
                                <table class="table table-hover mb-0">
                            <thead class="table-light">
                                    <tr>
                                        <th class="border-0">
                                            <i class="bi bi-person me-2"></i>Student Info
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-card-text me-2"></i>Student ID
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-shield me-2"></i>Status & Type
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-geo-alt me-2"></i>Section
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
                            <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr class="<?= (isset($student['is_archived']) && $student['is_archived']) ? 'table-warning opacity-75' : '' ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <img src="<?= getProfilePictureUrl($student['profile_picture'] ?? null, 'medium') ?>" 
                                                         class="rounded-circle me-3" 
                                                         width="40" height="40" 
                                                         alt="Profile">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h6>
                                                    <small class="text-muted">@<?= htmlspecialchars($student['username']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($student['identifier'])): ?>
                                                <span class="badge bg-success"><?= htmlspecialchars($student['identifier']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="badge bg-<?= (isset($student['status']) && $student['status'] === 'inactive') ? 'secondary' : 'success' ?> mb-1">
                                                    <i class="bi bi-<?= (isset($student['status']) && $student['status'] === 'inactive') ? 'archive' : 'check-circle' ?> me-1"></i>
                                                    <?= (isset($student['status']) && $student['status'] === 'inactive') ? 'Archived' : 'Active' ?>
                                                </span>
                                                <?php if (isset($student['is_archived']) && $student['is_archived']): ?>
                                                    <span class="badge bg-warning mb-1">
                                                        <i class="bi bi-archive me-1"></i>Archived
                                                    </span>
                                                <?php endif; ?>
                                                <span class="badge bg-<?= (isset($student['is_irregular']) && $student['is_irregular']) ? 'danger' : 'success' ?>">
                                                    <?= (isset($student['is_irregular']) && $student['is_irregular']) ? 'Irregular' : 'Regular' ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            if (!empty($student['sections']) && count($student['sections']) > 0) {
                                                $section_names = [];
                                                foreach ($student['sections'] as $section) {
                                                    $section_names[] = "Year {$section['year_level']} - " . htmlspecialchars($section['section_name']);
                                                }
                                                echo '<span class="badge bg-light text-dark">' . htmlspecialchars(implode(', ', $section_names)) . '</span>';
                                            } else {
                                                echo '<span class="text-muted small">No sections</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-envelope text-muted me-2"></i>
                                                <span class="small"><?= htmlspecialchars($student['email']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-calendar3 text-muted me-2"></i>
                                                <span class="small"><?= date('M j, Y', strtotime($student['created_at'])) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <button class="btn btn-sm btn-outline-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewStudentModal<?= $student['id'] ?>"
                                                        title="View Student Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editStudentModal<?= $student['id'] ?>"
                                                        title="Edit Student">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if (isset($student['is_archived']) && $student['is_archived']): ?>
                                                    <!-- Recover button for archived students -->
                                                    <form method="post" action="students.php" style="display:inline;" 
                                                          onsubmit="return confirm('Are you sure you want to recover this student?');">
                                                        <input type="hidden" name="action" value="recover">
                                                        <input type="hidden" name="user_id" value="<?= $student['id'] ?>">
                                                        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Recover Student">
                                                            <i class="bi bi-arrow-clockwise"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <!-- Archive button for active students -->
                                                    <form method="post" action="students.php" style="display:inline;" 
                                                          onsubmit="return confirm('Are you sure you want to archive this student? They will be hidden but can be recovered later.');">
                                                        <input type="hidden" name="action" value="archive">
                                                        <input type="hidden" name="user_id" value="<?= $student['id'] ?>">
                                                        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning" title="Archive Student">
                                                            <i class="bi bi-archive"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <!-- Edit Student Modal (simplified) -->
                                <div class="modal fade" id="editStudentModal<?= $student['id'] ?>" tabindex="-1" aria-labelledby="editStudentLabel<?= $student['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" action="students.php">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editStudentLabel<?= $student['id'] ?>">Edit Student</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="user_id" value="<?= $student['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                    <div class="mb-3">
                                                        <label for="identifier<?= $student['id'] ?>" class="form-label">Student ID</label>
                                                        <input type="text" class="form-control" id="identifier<?= $student['id'] ?>" value="<?= htmlspecialchars($student['identifier'] ?? 'Not Assigned') ?>" readonly style="background-color: #f8f9fa;">
                                                        <small class="form-text text-muted">Student ID cannot be edited</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="first_name<?= $student['id'] ?>" class="form-label">First Name</label>
                                                        <input type="text" class="form-control" id="first_name<?= $student['id'] ?>" name="first_name" required value="<?= htmlspecialchars($student['first_name']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="last_name<?= $student['id'] ?>" class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" id="last_name<?= $student['id'] ?>" name="last_name" required value="<?= htmlspecialchars($student['last_name']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="email<?= $student['id'] ?>" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="email<?= $student['id'] ?>" name="email" required value="<?= htmlspecialchars($student['email']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="is_irregular<?= $student['id'] ?>" class="form-label">Student Status</label>
                                                        <select class="form-select" id="is_irregular<?= $student['id'] ?>" name="is_irregular">
                                                            <option value="0" <?= (isset($student['is_irregular']) && !$student['is_irregular']) ? 'selected' : '' ?>>Regular</option>
                                                            <option value="1" <?= (isset($student['is_irregular']) && $student['is_irregular']) ? 'selected' : '' ?>>Irregular</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="password<?= $student['id'] ?>" class="form-label">Password (leave blank to keep current)</label>
                                                        <input type="password" class="form-control" id="password<?= $student['id'] ?>" name="password">
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
                                
                                <!-- View Student Modal -->
                                <div class="modal fade" id="viewStudentModal<?= $student['id'] ?>" tabindex="-1" aria-labelledby="viewStudentLabel<?= $student['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-info text-white">
                                                <h5 class="modal-title" id="viewStudentLabel<?= $student['id'] ?>">
                                                    <i class="bi bi-eye me-2"></i>View Student Details
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-card-text me-2"></i>Student ID
                                                            </label>
                                                            <p class="form-control-plaintext bg-light p-2 rounded">
                                                                <?php if (!empty($student['identifier'])): ?>
                                                                    <span class="badge bg-primary fs-6"><?= htmlspecialchars($student['identifier']) ?></span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning fs-6">No ID Assigned</span>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-person-badge me-2"></i>Student Type
                                                            </label>
                                                            <p class="form-control-plaintext bg-light p-2 rounded">
                                                                <?php if ($student['is_irregular']): ?>
                                                                    <span class="badge bg-danger fs-6"><i class="bi bi-exclamation-triangle me-1"></i>Irregular</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Regular</span>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-person me-2"></i>First Name
                                                            </label>
                                                            <p class="form-control-plaintext bg-light p-2 rounded"><?= htmlspecialchars($student['first_name']) ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-person me-2"></i>Last Name
                                                            </label>
                                                            <p class="form-control-plaintext bg-light p-2 rounded"><?= htmlspecialchars($student['last_name']) ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-mortarboard me-2"></i>Section Assignment<?= (!empty($student['sections']) && count($student['sections']) > 1) ? 's' : '' ?>
                                                            </label>
                                                            <div class="form-control-plaintext bg-light p-2 rounded">
                                                                <?php if (!empty($student['sections']) && count($student['sections']) > 0): ?>
                                                                    <?php foreach ($student['sections'] as $index => $section): ?>
                                                                        <div class="d-flex align-items-center mb-2">
                                                                            <span class="badge bg-primary fs-6 me-2">
                                                                                <i class="bi bi-mortarboard me-1"></i>
                                                                                Year <?= $section['year_level'] ?> - <?= htmlspecialchars($section['section_name']) ?>
                                                                            </span>
                                                                        </div>
                                                                        <?php if (!empty($section['section_description'])): ?>
                                                                            <small class="text-muted d-block mb-2"><?= htmlspecialchars($section['section_description']) ?></small>
                                                                        <?php endif; ?>
                                                                        <?php if ($index < count($student['sections']) - 1): ?>
                                                                            <hr class="my-2">
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning fs-6">
                                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                                        No Section Assigned
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-person-circle me-2"></i>Username
                                                            </label>
                                                            <p class="form-control-plaintext bg-light p-2 rounded"><?= htmlspecialchars($student['username']) ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-envelope me-2"></i>Email
                                                            </label>
                                                            <p class="form-control-plaintext bg-light p-2 rounded"><?= htmlspecialchars($student['email']) ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-calendar3 me-2"></i>Created Date
                                                            </label>
                                                            <p class="form-control-plaintext bg-light p-2 rounded"><?= date('M j, Y g:i A', strtotime($student['created_at'])) ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-clock me-2"></i>Last Updated
                                                            </label>
                                                            <p class="form-control-plaintext bg-light p-2 rounded"><?= date('M j, Y g:i A', strtotime($student['updated_at'] ?? $student['created_at'])) ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <?php if (isset($student['status'])): ?>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">
                                                                <i class="bi bi-check-circle me-2"></i>Account Status
                                                            </label>
                                                            <p class="form-control-plaintext bg-light p-2 rounded">
                                                                <?php if ($student['status'] === 'inactive'): ?>
                                                                    <span class="badge bg-warning fs-6"><i class="bi bi-pause-circle me-1"></i>Inactive (Archived)</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Active</span>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
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
    </div>
</div>
<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="students.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStudentLabel">Add Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
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
                        <label for="is_irregular_add" class="form-label">Student Status</label>
                        <select class="form-select" id="is_irregular_add" name="is_irregular">
                            <option value="0" selected>Regular</option>
                            <option value="1">Irregular</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Real-time filtering functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing search functionality...');
    const searchInput = document.getElementById('search');
    const statusSelect = document.getElementById('status');
    const sectionSelect = document.getElementById('section');
    const yearSelect = document.getElementById('year');
    const filterBtn = document.getElementById('filterBtn');
    const clearBtn = document.getElementById('clearBtn');
    const studentsTableContainer = document.getElementById('studentsTableContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const studentCountBadge = document.querySelector('.badge.bg-primary.fs-6');
    
    // Debug: Check if elements are found
    console.log('Search input found:', !!searchInput);
    console.log('Status select found:', !!statusSelect);
    console.log('Section select found:', !!sectionSelect);
    console.log('Year select found:', !!yearSelect);
    
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
        const search = searchInput.value.trim();
        const status = statusSelect.value;
        const section = sectionSelect.value;
        const year = yearSelect.value;
        
        // Show loading indicator and disable form
        loadingIndicator.style.display = 'block';
        document.getElementById('filterForm').classList.add('form-loading');
        studentsTableContainer.querySelector('.scrollable-table, .text-center')?.style.setProperty('display', 'none');
        
        // Build query parameters
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (status) params.append('status', status);
        if (section) params.append('section', section);
        if (year) params.append('year', year);
        
        // Make AJAX request
        fetch(`ajax_get_students.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update students table with animation
                    // Clean up any existing dynamic modals before updating the table
                    cleanupDynamicModals();
                    studentsTableContainer.innerHTML = data.students_html;
                    
                    // Update student count badge with animation
                    if (studentCountBadge) {
                        studentCountBadge.style.transform = 'scale(1.1)';
                        studentCountBadge.textContent = `${data.total_students} students found`;
                        setTimeout(() => {
                            studentCountBadge.style.transform = 'scale(1)';
                        }, 200);
                    }
                    
                    // Update statistics cards if available
                    updateStatistics(data.stats);
                    
                    // Update URL without page reload
                    const newUrl = new URL(window.location);
                    newUrl.search = params.toString();
                    window.history.pushState({}, '', newUrl);
                } else {
                    console.error('Error:', data.error);
                    showError('Failed to load students. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Failed to load students. Please try again.');
            })
            .finally(() => {
                loadingIndicator.style.display = 'none';
                document.getElementById('filterForm').classList.remove('form-loading');
            });
    }
    
    // Function to update statistics cards with animations
    function updateStatistics(stats) {
        // Update total students
        const totalStudentsElement = document.querySelector('.stats-primary h3');
        if (totalStudentsElement) {
            animateNumberChange(totalStudentsElement, stats.total_students);
        }
        
        // Update active students
        const activeStudentsElement = document.querySelector('.stats-success h3');
        if (activeStudentsElement) {
            animateNumberChange(activeStudentsElement, stats.active_students);
        }
        
        // Update archived students
        const archivedStudentsElement = document.querySelector('.stats-secondary h3');
        if (archivedStudentsElement) {
            animateNumberChange(archivedStudentsElement, stats.inactive_students);
        }
        
        // Update regular students
        const regularStudentsElement = document.querySelector('.stats-info h3');
        if (regularStudentsElement) {
            animateNumberChange(regularStudentsElement, stats.total_regular);
        }
        
        // Update irregular students
        const irregularStudentsElement = document.querySelector('.stats-danger h3');
        if (irregularStudentsElement) {
            animateNumberChange(irregularStudentsElement, stats.total_irregular);
        }
        
        // Update students with sections
        const withSectionsElement = document.querySelector('.stats-purple h3');
        if (withSectionsElement) {
            animateNumberChange(withSectionsElement, stats.students_with_sections);
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
    
    // Function to show error message
    function showError(message) {
        studentsTableContainer.innerHTML = `
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
    
    if (sectionSelect) {
        sectionSelect.addEventListener('change', function() {
            this.classList.add('filtering');
            performSearch();
            setTimeout(() => this.classList.remove('filtering'), 1000);
        });
    }
    
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
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
            if (sectionSelect) sectionSelect.value = '';
            if (yearSelect) yearSelect.value = '';
            performSearch();
        });
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (searchInput) searchInput.value = urlParams.get('search') || '';
        if (statusSelect) statusSelect.value = urlParams.get('status') || '';
        if (sectionSelect) sectionSelect.value = urlParams.get('section') || '';
        if (yearSelect) yearSelect.value = urlParams.get('year') || '';
        performSearch();
    });
    
    // Clean up any leftover dynamic modals on page load
    cleanupDynamicModals();
});

// Function to clean up dynamic modals
function cleanupDynamicModals() {
    // Remove all dynamically created modals
    const dynamicModals = document.querySelectorAll('[id^="viewStudentModal"], [id^="editStudentModal"]');
    dynamicModals.forEach(modal => {
        // Hide the modal first if it's open
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
        modal.remove();
    });
}

// Function to get CSRF token from existing form
function getCSRFToken() {
    const csrfInput = document.querySelector('input[name="<?php echo CSRF_TOKEN_NAME; ?>"]');
    return csrfInput ? csrfInput.value : '';
}

// Global functions for action buttons (accessible from AJAX-loaded content)
function viewStudent(studentId) {
    // Find the view modal for this student
    let modal = document.getElementById('viewStudentModal' + studentId);
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    } else {
        // Create modal dynamically if it doesn't exist
        createViewModal(studentId);
    }
}

function editStudent(studentId) {
    // Find the edit modal for this student
    let modal = document.getElementById('editStudentModal' + studentId);
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    } else {
        // Create modal dynamically if it doesn't exist
        createEditModal(studentId);
    }
}

function archiveStudent(studentId) {
    if (confirm('Are you sure you want to archive this student?')) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'students.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = studentId;
        form.appendChild(userIdInput);
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '<?php echo CSRF_TOKEN_NAME; ?>';
        csrfInput.value = '<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? ''); ?>';
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function unarchiveStudent(studentId) {
    if (confirm('Are you sure you want to unarchive this student?')) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'students.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'unarchive';
        form.appendChild(actionInput);
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = studentId;
        form.appendChild(userIdInput);
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '<?php echo CSRF_TOKEN_NAME; ?>';
        csrfInput.value = '<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? ''); ?>';
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Function to create view modal dynamically
function createViewModal(studentId) {
    console.log('Creating view modal for student ID:', studentId);
    // Fetch student data via AJAX
    fetch('ajax_get_student_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'student_id=' + studentId
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('View modal response:', data);
        if (data.success) {
            const student = data.student;
            const modalHtml = `
                <div class="modal fade" id="viewStudentModal${studentId}" tabindex="-1" aria-labelledby="viewStudentLabel${studentId}" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="viewStudentLabel${studentId}">Student Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <img src="${student.profile_picture_url || 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0OCIgaGVpZ2h0PSI0OCIgdmlld0JveD0iMCAwIDQ4IDQ4Ij48Y2lyY2xlIGN4PSIyNCIgY3k9IjI0IiByPSIyNCIgZmlsbD0iIzZjNzU3ZCIvPjxjaXJjbGUgY3g9IjI0IiBjeT0iMTkiIHI9IjciIGZpbGw9IiNmZmYiLz48cGF0aCBkPSJNOSAzOWMwLTguODM3IDcuMTYzLTE2IDE2LTE2czE2IDcuMTYzIDE2IDE2IiBmaWxsPSIjZmZmIi8+PC9zdmc+'}" 
                                             class="rounded-circle mb-3" width="120" height="120" alt="Profile">
                                    </div>
                                    <div class="col-md-8">
                                        <h4>${student.first_name} ${student.last_name}</h4>
                                        <p class="text-muted">@${student.username}</p>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <strong>Student ID:</strong><br>
                                                <span class="badge bg-success">${student.identifier || 'Not Assigned'}</span>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong>Status:</strong><br>
                                                <span class="badge bg-${student.status === 'inactive' ? 'secondary' : 'success'}">
                                                    ${student.status === 'inactive' ? 'Archived' : 'Active'}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-sm-6">
                                                <strong>Type:</strong><br>
                                                <span class="badge bg-${student.is_irregular ? 'danger' : 'success'}">
                                                    ${student.is_irregular ? 'Irregular' : 'Regular'}
                                                </span>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong>Year Level:</strong><br>
                                                <span class="badge bg-info">Year ${student.year_level}</span>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <strong>Email:</strong><br>
                                                <a href="mailto:${student.email}">${student.email}</a>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <strong>Section:</strong><br>
                                                ${student.sections && student.sections.length > 0 ? 
                                                    student.sections.map(s => `<span class="badge bg-light text-dark">Year ${s.year_level} - ${s.section_name}</span>`).join(' ') : 
                                                    '<span class="text-muted">No sections assigned</span>'
                                                }
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <strong>Joined:</strong><br>
                                                ${new Date(student.created_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary" onclick="editStudent(${studentId})">Edit Student</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('viewStudentModal' + studentId);
            if (existingModal) {
                // Hide the modal first if it's open
                const bsModal = bootstrap.Modal.getInstance(existingModal);
                if (bsModal) {
                    bsModal.hide();
                }
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show the modal
            const modal = document.getElementById('viewStudentModal' + studentId);
            const bsModal = new bootstrap.Modal(modal);
            
            // Add event listener to clean up when modal is hidden
            modal.addEventListener('hidden.bs.modal', function() {
                modal.remove();
            });
            
            bsModal.show();
        } else {
            console.error('Failed to fetch student details:', data.error);
            alert('Unable to load student details. Please refresh the page.');
        }
    })
    .catch(error => {
        console.error('Error fetching student details:', error);
        alert('Unable to load student details. Please refresh the page.');
    });
}

// Function to create edit modal dynamically
function createEditModal(studentId) {
    console.log('Creating edit modal for student ID:', studentId);
    // Fetch student data via AJAX
    fetch('ajax_get_student_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'student_id=' + studentId
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Edit modal response:', data);
        if (data.success) {
            const student = data.student;
            const modalHtml = `
                <div class="modal fade" id="editStudentModal${studentId}" tabindex="-1" aria-labelledby="editStudentLabel${studentId}" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post" action="students.php">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editStudentLabel${studentId}">Edit Student</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="user_id" value="${studentId}">
                                    <input type="hidden" name="csrf_token" value="${getCSRFToken()}">
                                    <div class="mb-3">
                                        <label for="identifier${studentId}" class="form-label">Student ID</label>
                                        <input type="text" class="form-control" id="identifier${studentId}" value="${student.identifier || 'Not Assigned'}" readonly style="background-color: #f8f9fa;">
                                        <small class="form-text text-muted">Student ID cannot be edited</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="first_name${studentId}" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name${studentId}" name="first_name" required value="${student.first_name}">
                                    </div>
                                    <div class="mb-3">
                                        <label for="last_name${studentId}" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name${studentId}" name="last_name" required value="${student.last_name}">
                                    </div>
                                    <div class="mb-3">
                                        <label for="email${studentId}" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email${studentId}" name="email" required value="${student.email}">
                                    </div>
                                    <div class="mb-3">
                                        <label for="year_level${studentId}" class="form-label">Year Level</label>
                                        <select class="form-select" id="year_level${studentId}" name="year_level" required>
                                            <option value="1" ${student.year_level == 1 ? 'selected' : ''}>1st Year</option>
                                            <option value="2" ${student.year_level == 2 ? 'selected' : ''}>2nd Year</option>
                                            <option value="3" ${student.year_level == 3 ? 'selected' : ''}>3rd Year</option>
                                            <option value="4" ${student.year_level == 4 ? 'selected' : ''}>4th Year</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_irregular${studentId}" name="is_irregular" value="1" ${student.is_irregular ? 'checked' : ''}>
                                            <label class="form-check-label" for="is_irregular${studentId}">
                                                Irregular Student
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password${studentId}" class="form-label">Password (leave blank to keep current)</label>
                                        <input type="password" class="form-control" id="password${studentId}" name="password">
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
            
            // Remove existing modal if any
            const existingModal = document.getElementById('editStudentModal' + studentId);
            if (existingModal) {
                // Hide the modal first if it's open
                const bsModal = bootstrap.Modal.getInstance(existingModal);
                if (bsModal) {
                    bsModal.hide();
                }
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show the modal
            const modal = document.getElementById('editStudentModal' + studentId);
            const bsModal = new bootstrap.Modal(modal);
            
            // Add event listener to clean up when modal is hidden
            modal.addEventListener('hidden.bs.modal', function() {
                modal.remove();
            });
            
            bsModal.show();
        } else {
            console.error('Failed to fetch student details:', data.error);
            alert('Unable to load student details. Please refresh the page.');
        }
    })
    .catch(error => {
        console.error('Error fetching student details:', error);
        alert('Unable to load student details. Please refresh the page.');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?> 