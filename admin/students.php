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
.btn-outline-info {
    background: #0dcaf0;
    color: white;
    border: none;
}

.btn-outline-info:hover {
    background: #0aa2c0;
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

.btn-outline-danger {
    background: #dc3545;
    color: white;
    border: none;
}

.btn-outline-danger:hover {
    background: #bb2d3b;
    color: white;
}

/* Add Student Button */
.add-student-btn {
    background: var(--main-green);
    border: none;
    color: white;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
}

.add-student-btn:hover {
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
                
            case 'delete':
                $user_id = (int)($_POST['user_id'] ?? 0);
                if ($user_id === $_SESSION['user_id']) {
                    $message = 'You cannot delete your own account.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('UPDATE users SET status = ? WHERE id = ? AND role = ?');
                    $stmt->execute(['inactive', $user_id, 'student']);
                    $message = 'Student archived successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'unarchive':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $stmt = $db->prepare('UPDATE users SET status = ? WHERE id = ? AND role = ?');
                $stmt->execute(['active', $user_id, 'student']);
                $message = 'Student unarchived successfully.';
                $message_type = 'success';
                break;
        }
    }
}

// Fetch all students
$stmt = $db->prepare("SELECT * FROM users WHERE role = 'student' ORDER BY last_name, first_name");
$stmt->execute();
$students = $stmt->fetchAll();

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
                <div class="col-md-3 mb-3">
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
                <div class="col-md-3 mb-3">
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
                <div class="col-md-3 mb-3">
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
                <div class="col-md-3 mb-3">
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
            </div>
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
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
            </div>
            <div class="d-flex justify-content-end mb-3">
                <button class="btn add-student-btn" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="bi bi-plus-circle me-2"></i>Add Student
                </button>
            </div>
            <div class="card table-container">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5>
                            <i class="bi bi-mortarboard me-2"></i>Students List
                        </h5>
                        <span class="badge bg-primary fs-6"><?= $total_students ?> students</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="scrollable-table">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Status</th>
                                    <th>Email</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($student['identifier'])): ?>
                                            <span class="badge bg-primary"><?= htmlspecialchars($student['identifier']) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">No ID</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($student['username']) ?></td>
                                    <td><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></td>
                                    <td>
                                        <?php if ($student['is_irregular']): ?>
                                            <span class="badge bg-danger">Irregular</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Regular</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($student['email']) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($student['created_at'])) ?></td>
                                    <td>
                                        <a href="#" class="btn btn-outline-info btn-sm me-1" data-bs-toggle="modal" data-bs-target="#viewStudentModal<?= $student['id'] ?>"><i class="bi bi-eye"></i> View</a>
                                        <a href="#" class="btn btn-outline-secondary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editStudentModal<?= $student['id'] ?>"><i class="bi bi-pencil"></i> Edit</a>
                                        <?php if (isset($student['status']) && $student['status'] === 'inactive'): ?>
                                            <form method="post" action="students.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to unarchive this student?');">
                                                <input type="hidden" name="action" value="unarchive">
                                                <input type="hidden" name="user_id" value="<?= $student['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-arrow-clockwise"></i> Unarchive</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="students.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to archive this student?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $student['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-archive"></i> Archive</button>
                                            </form>
                                        <?php endif; ?>
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
<?php require_once '../includes/footer.php'; ?> 