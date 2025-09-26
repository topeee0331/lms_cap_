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
                
            case 'delete':
                $user_id = (int)($_POST['user_id'] ?? 0);
                if ($user_id === $_SESSION['user_id']) {
                    $message = 'You cannot delete your own account.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('UPDATE users SET status = ? WHERE id = ? AND role = ?');
                    $stmt->execute(['inactive', $user_id, 'teacher']);
                    $message = 'Teacher archived successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'unarchive':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $stmt = $db->prepare('UPDATE users SET status = ? WHERE id = ? AND role = ?');
                $stmt->execute(['active', $user_id, 'teacher']);
                $message = 'Teacher unarchived successfully.';
                    $message_type = 'success';
                break;
        }
    }
}

// Fetch all teachers with status
$stmt = $db->prepare("SELECT * FROM users WHERE role = 'teacher' ORDER BY last_name, first_name");
$stmt->execute();
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
            <div class="d-flex justify-content-end mb-3">
                <button class="btn add-teacher-btn" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="bi bi-plus-circle me-2"></i>Add Teacher
                </button>
            </div>
            <div class="card table-container">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5>
                            <i class="bi bi-person-badge me-2"></i>Teachers List
                        </h5>
                        <span class="badge bg-primary fs-6"><?= $total_teachers ?> teachers</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="scrollable-table">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Teacher ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Date Added</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($teacher['identifier'])): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($teacher['identifier']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($teacher['username']) ?></td>
                                    <td><?= htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']) ?></td>
                                    <td><?= htmlspecialchars($teacher['email']) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($teacher['created_at'])) ?></td>
                                    <td>
                                        <?php if (isset($teacher['status']) && $teacher['status'] === 'inactive'): ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#" class="btn btn-outline-secondary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editTeacherModal<?= $teacher['id'] ?>"><i class="bi bi-pencil"></i> Edit</a>
                                        <?php if (isset($teacher['status']) && $teacher['status'] === 'inactive'): ?>
                                            <form method="post" action="teachers.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to unarchive this teacher?');">
                                                <input type="hidden" name="action" value="unarchive">
                                                <input type="hidden" name="user_id" value="<?= $teacher['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-arrow-clockwise"></i> Unarchive</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="teachers.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to archive this teacher?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $teacher['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-archive"></i> Archive</button>
                                            </form>
                                        <?php endif; ?>
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
<?php require_once '../includes/footer.php'; ?> 