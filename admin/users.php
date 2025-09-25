<?php
$page_title = 'Manage Users';
require_once '../config/config.php';
require_once '../includes/student_id_generator.php';
requireRole('admin');
require_once '../includes/header.php';
?>

<style>
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* Enhanced Users Page Styling - Inspired by Admin Dashboard */
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
    min-width: 1200px; /* Ensure minimum width for proper display */
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

// Handle user actions
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
                $role = sanitizeInput($_POST['role'] ?? '');
                $is_irregular = isset($_POST['is_irregular']) ? intval($_POST['is_irregular']) : 0;
                
                if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($role)) {
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
                        
                        // Generate unique user ID based on role
                        $userId = null;
                        if ($role === 'admin') {
                            $userId = generateAdminId($db);
                        } elseif ($role === 'teacher') {
                            $userId = generateTeacherId($db);
                        } elseif ($role === 'student') {
                            $userId = generateStudentId($db);
                        }
                        
                        $status = ($role === 'teacher') ? (sanitizeInput($_POST['status'] ?? 'active')) : null;
                        if ($role === 'teacher') {
                            $stmt = $db->prepare('INSERT INTO users (username, email, password, first_name, last_name, role, identifier, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                            $stmt->execute([$username, $email, $hashed, $first_name, $last_name, $role, $userId, $status]);
                        } else {
                            $stmt = $db->prepare('INSERT INTO users (username, email, password, first_name, last_name, role, identifier, is_irregular) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                            $stmt->execute([$username, $email, $hashed, $first_name, $last_name, $role, $userId, $is_irregular]);
                        }
                        $message = 'User created successfully with ID: ' . $userId;
                        $message_type = 'success';
                    }
                }
                break;
                
            case 'update':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $first_name = sanitizeInput($_POST['first_name'] ?? '');
                $last_name = sanitizeInput($_POST['last_name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = sanitizeInput($_POST['role'] ?? '');
                $password = $_POST['password'] ?? '';
                $is_irregular = isset($_POST['is_irregular']) ? intval($_POST['is_irregular']) : 0;
                
                // Get current user role to check if it's a student
                $current_user_stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
                $current_user_stmt->execute([$user_id]);
                $current_user = $current_user_stmt->fetch();
                
                // Get current logged-in user's role for permission checking
                $current_admin_role = $_SESSION['role'] ?? '';
                
                if (empty($first_name) || empty($last_name) || empty($email) || empty($role)) {
                    $message = 'All fields are required.';
                    $message_type = 'danger';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = 'Invalid email address.';
                    $message_type = 'danger';
                } elseif ($current_user && $current_user['role'] === 'student' && in_array($role, ['teacher', 'admin'])) {
                    $message = 'Students cannot be promoted to teacher or admin roles.';
                    $message_type = 'danger';
                } elseif ($current_user && $current_user['role'] === 'teacher' && $role === 'student') {
                    $message = 'Teachers cannot be demoted to student roles.';
                    $message_type = 'danger';
                } elseif ($current_user && $current_user['role'] === 'teacher' && $role === 'admin' && $current_admin_role !== 'admin') {
                    $message = 'Only administrators can promote teachers to admin roles.';
                    $message_type = 'danger';
                } elseif ($current_admin_role === 'teacher' && $role === 'student') {
                    $message = 'Teachers cannot promote users to student roles.';
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
                            $status = ($role === 'teacher') ? (sanitizeInput($_POST['status'] ?? 'active')) : null;
                            if ($role === 'teacher') {
                                $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, password = ?, status = ? WHERE id = ?');
                                $stmt->execute([$first_name, $last_name, $email, $role, $hashed, $status, $user_id]);
                            } else {
                                $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, password = ?, is_irregular = ? WHERE id = ?');
                                $stmt->execute([$first_name, $last_name, $email, $role, $hashed, $is_irregular, $user_id]);
                            }
                        } else {
                            $status = ($role === 'teacher') ? (sanitizeInput($_POST['status'] ?? 'active')) : null;
                            if ($role === 'teacher') {
                                $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, status = ? WHERE id = ?');
                                $stmt->execute([$first_name, $last_name, $email, $role, $status, $user_id]);
                            } else {
                                $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, is_irregular = ? WHERE id = ?');
                                $stmt->execute([$first_name, $last_name, $email, $role, $is_irregular, $user_id]);
                            }
                        }
                        $message = 'User updated successfully.';
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
                    $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
                    $stmt->execute([$user_id]);
                    $message = 'User deleted successfully.';
                    $message_type = 'success';
                }
                break;
        }
    }
}

// Get users with search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$role_filter = sanitizeInput($_GET['role'] ?? '');
$section_filter = sanitizeInput($_GET['section'] ?? '');
$year_filter = sanitizeInput($_GET['year'] ?? '');

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Update users query to fetch is_irregular, status, and identifier
$stmt = $db->prepare("
    SELECT id, username, email, first_name, last_name, role, profile_picture, created_at, is_irregular, status, identifier 
    FROM users 
    $where_clause 
    ORDER BY created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Apply section and year filters after fetching users
if (!empty($section_filter) || !empty($year_filter)) {
    $filtered_users = [];
    foreach ($users as $user) {
        if ($user['role'] === 'student' || $user['role'] === 'teacher') {
            $user_sections = get_user_sections_with_year($db, $user['id'], $user['role']);
            
            $include_user = true;
            
            // Filter by section
            if (!empty($section_filter)) {
                $section_found = false;
                foreach ($user_sections as $section) {
                    if ($section['id'] == $section_filter) {
                        $section_found = true;
                        break;
                    }
                }
                if (!$section_found) {
                    $include_user = false;
                }
            }
            
            // Filter by year
            if (!empty($year_filter) && $include_user) {
                $year_found = false;
                foreach ($user_sections as $section) {
                    if ($section['year_level'] == $year_filter) {
                        $year_found = true;
                        break;
                    }
                }
                if (!$year_found) {
                    $include_user = false;
                }
            }
            
            if ($include_user) {
                $filtered_users[] = $user;
            }
        } else {
            // Include admins if no section/year filter is applied
            if (empty($section_filter) && empty($year_filter)) {
                $filtered_users[] = $user;
            }
        }
    }
    $users = $filtered_users;
}

// Get statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN role = 'admin' THEN 1 END) as total_admins,
        COUNT(CASE WHEN role = 'teacher' THEN 1 END) as total_teachers,
        COUNT(CASE WHEN role = 'student' THEN 1 END) as total_students,
        COUNT(CASE WHEN role = 'student' AND is_irregular = 1 THEN 1 END) as irregular_students,
        COUNT(CASE WHEN role = 'teacher' AND status = 'inactive' THEN 1 END) as inactive_teachers
    FROM users
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year_level']}{$section['section_name']}";
}

// Fetch all sections for mapping with year information
$section_sql = "SELECT id, section_name, year_level FROM sections ORDER BY year_level, section_name";
$section_res = $db->query($section_sql);
$sections_raw = $section_res ? $section_res->fetchAll() : [];
$sections = [];
foreach ($sections_raw as $section) {
    $sections[$section['id']] = formatSectionName($section);
}

// Helper: get sections for a user with year information
function get_user_sections_with_year($db, $user_id, $role) {
    if ($role === 'student') {
        $sql = "SELECT id, section_name, year_level FROM sections 
                WHERE JSON_SEARCH(students, 'one', ?) IS NOT NULL 
                ORDER BY year_level, section_name";
    } elseif ($role === 'teacher') {
        $sql = "SELECT id, section_name, year_level FROM sections 
                WHERE JSON_SEARCH(teachers, 'one', ?) IS NOT NULL 
                ORDER BY year_level, section_name";
    } else {
        return [];
    }
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Helper: get sections for a user (for display)
function get_user_sections($db, $user_id, $role, $sections) {
    if ($role === 'student') {
        $sql = "SELECT id FROM sections WHERE JSON_SEARCH(students, 'one', ?) IS NOT NULL";
    } elseif ($role === 'teacher') {
        $sql = "SELECT id FROM sections WHERE JSON_SEARCH(teachers, 'one', ?) IS NOT NULL";
    } else {
        return [];
    }
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $names = [];
    foreach ($ids as $sid) {
        if (isset($sections[$sid])) {
            $names[] = $sections[$sid];
        }
    }
    return $names;
}
?>

<div class="page-container">
    <div class="container-fluid py-4">
        <!-- Enhanced Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Manage Users</h1>
                    <p class="welcome-subtitle">Create, edit, and manage user accounts across the system</p>
                    <a href="dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="welcome-decoration">
                        <i class="bi bi-people"></i>
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
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['total_users'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-success border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-shield-fill-check fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['total_admins'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Administrators</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-info border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-person-badge-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['total_teachers'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Teachers</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-warning border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-mortarboard-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['total_students'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Students</p>
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
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['irregular_students'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Irregular Students</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card stats-secondary border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-person-x-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['inactive_teachers'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Inactive Teachers</p>
                </div>
            </div>
        </div>
    </div>

        <!-- Search and Filter Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card search-filter-card">
                    <div class="card-body">
                        <form method="GET" action="users.php" class="row g-3 align-items-end" id="filterForm">
                        <div class="col-md-3">
                            <label for="search" class="form-label fw-semibold">
                                <i class="bi bi-search me-2"></i>Search Users
                            </label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by name, email, or username..." 
                                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                                <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                    <small class="text-muted" id="searchCounter" style="display: none;">
                                        <span id="charCount">0</span> characters
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="role" class="form-label fw-semibold">
                                <i class="bi bi-funnel me-2"></i>Filter by Role
                            </label>
                            <select class="form-select" id="role" name="role">
                                <option value="">All Roles</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                <option value="teacher" <?= $role_filter === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                                <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Student</option>
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
                            <a href="users.php" class="btn btn-outline-secondary w-100" id="clearBtn">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset
                            </a>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

        <!-- Users Table -->
        <div class="row">
            <div class="col-12">
                <div class="card table-container">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>
                                <i class="bi bi-people me-2"></i>Users Management
                            </h5>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary fs-6"><?= count($users) ?> users found</span>
                                <div id="loadingIndicator" style="display: none;">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0" id="usersTableContainer">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show m-3" role="alert">
                                <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($users)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">No users found</h5>
                                <p class="text-muted">Try adjusting your search criteria or add a new user.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                    <i class="bi bi-plus-circle me-2"></i>Add New User
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="scrollable-table">
                                <table class="table table-hover mb-0">
                            <thead class="table-light">
                                    <tr>
                                        <th class="border-0">
                                            <i class="bi bi-person me-2"></i>User Info
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-card-text me-2"></i>User ID
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-shield me-2"></i>Role & Status
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
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <img src="<?= getProfilePictureUrl($user['profile_picture'] ?? null, 'medium') ?>" 
                                                             class="rounded-circle me-3" 
                                                             width="40" height="40" 
                                                             alt="Profile">
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h6>
                                                        <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($user['identifier'])): ?>
                                                    <?php
                                                    $badgeColor = 'bg-primary'; // default blue
                                                    if ($user['role'] === 'admin') {
                                                        $badgeColor = 'bg-danger'; // red for admin
                                                    } elseif ($user['role'] === 'teacher') {
                                                        $badgeColor = 'bg-primary'; // blue for teacher
                                                    } elseif ($user['role'] === 'student') {
                                                        $badgeColor = 'bg-success'; // green for student
                                                    }
                                                    ?>
                                                    <span class="badge <?= $badgeColor ?>"><?= htmlspecialchars($user['identifier']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted small">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'success' : ($user['role'] === 'teacher' ? 'info' : 'warning') ?> mb-1">
                                                        <i class="bi bi-<?= $user['role'] === 'admin' ? 'shield-check' : ($user['role'] === 'teacher' ? 'person-badge' : 'mortarboard') ?> me-1"></i>
                                                        <?= ucfirst($user['role']) ?>
                                                    </span>
                                                    <?php if ($user['role'] === 'student'): ?>
                                                        <span class="badge bg-<?= (isset($user['is_irregular']) && $user['is_irregular']) ? 'danger' : 'success' ?>">
                                                            <?= (isset($user['is_irregular']) && $user['is_irregular']) ? 'Irregular' : 'Regular' ?>
                                                        </span>
                                                    <?php elseif ($user['role'] === 'teacher'): ?>
                                                        <span class="badge bg-<?= (isset($user['status']) && $user['status'] === 'inactive') ? 'secondary' : 'success' ?>">
                                                            <?= (isset($user['status']) && $user['status'] === 'inactive') ? 'Inactive' : 'Active' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <td>
                                            <?php
                                            if ($user['role'] === 'student' || $user['role'] === 'teacher') {
                                                $user_sections = get_user_sections($db, $user['id'], $user['role'], $sections);
                                                    if ($user_sections) {
                                                        echo '<span class="badge bg-light text-dark">' . htmlspecialchars(implode(', ', $user_sections)) . '</span>';
                                                    } else {
                                                        echo '<span class="text-muted small">No sections</span>';
                                                    }
                                            } else {
                                                    echo '<span class="text-muted small">N/A</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-envelope text-muted me-2"></i>
                                                    <span class="small"><?= htmlspecialchars($user['email']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-calendar3 text-muted me-2"></i>
                                                    <span class="small"><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                                                </div>
                                        </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewUserModal<?= $user['id'] ?>"
                                                            title="View User Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editUserModal<?= $user['id'] ?>"
                                                            title="Edit User">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                        <form method="post" action="users.php" style="display:inline;" 
                                                              onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                            </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete your own account">
                                                            <i class="bi bi-shield-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                        </td>
                                    </tr>
                                        
                                        <!-- Edit User Modal -->
                                    <div class="modal fade" id="editUserModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="editUserLabel<?= $user['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="post" action="users.php">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title" id="editUserLabel<?= $user['id'] ?>">
                                                                <i class="bi bi-pencil-square me-2"></i>Edit User
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label for="user_id_display<?= $user['id'] ?>" class="form-label fw-semibold">
                                                                    <i class="bi bi-card-text me-2"></i>Identifier
                                                                </label>
                                                                <input type="text" class="form-control" id="user_id_display<?= $user['id'] ?>" 
                                                                       value="<?= htmlspecialchars($user['identifier'] ?? 'Not assigned') ?>" readonly>
                                                                <div class="form-text">Identifier cannot be modified</div>
                                                            </div>
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                        <div class="mb-3">
                                                                        <label for="first_name<?= $user['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-person me-2"></i>First Name
                                                                        </label>
                                                                        <input type="text" class="form-control" id="first_name<?= $user['id'] ?>" 
                                                                               name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>">
                                                                    </div>
                                                        </div>
                                                                <div class="col-md-6">
                                                        <div class="mb-3">
                                                                        <label for="last_name<?= $user['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-person me-2"></i>Last Name
                                                                        </label>
                                                                        <input type="text" class="form-control" id="last_name<?= $user['id'] ?>" 
                                                                               name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>">
                                                                    </div>
                                                                </div>
                                                        </div>
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                        <div class="mb-3">
                                                                        <label for="email<?= $user['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-envelope me-2"></i>Email
                                                                        </label>
                                                                        <input type="email" class="form-control" id="email<?= $user['id'] ?>" 
                                                                               name="email" required value="<?= htmlspecialchars($user['email']) ?>">
                                                                    </div>
                                                        </div>
                                                                <div class="col-md-6">
                                                        <div class="mb-3">
                                                                        <label for="role<?= $user['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-shield me-2"></i>Role
                                                                            <?php 
                                                                            $current_admin_role = $_SESSION['role'] ?? '';
                                                                            $role_restricted = false;
                                                                            $restriction_message = '';
                                                                            
                                                                            if ($user['role'] === 'student') {
                                                                                $role_restricted = true;
                                                                                $restriction_message = 'Students cannot be promoted to teacher or admin roles.';
                                                                            } elseif ($current_admin_role === 'teacher') {
                                                                                $role_restricted = true;
                                                                                $restriction_message = 'Teachers cannot change user roles. Only administrators can manage roles.';
                                                                            }
                                                                            
                                                                            if ($role_restricted): ?>
                                                                                <span class="badge bg-warning ms-2">Cannot be changed</span>
                                                                            <?php endif; ?>
                                                                        </label>
                                                            <select class="form-select" id="role<?= $user['id'] ?>" name="role" required <?= $role_restricted ? 'disabled' : '' ?>>
                                                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                                                <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                                                                <?php if ($current_admin_role === 'admin' && $user['role'] === 'student'): ?>
                                                                    <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                                                <?php endif; ?>
                                                            </select>
                                                            <?php if ($role_restricted): ?>
                                                                <input type="hidden" name="role" value="<?= $user['role'] ?>">
                                                                <div class="form-text text-warning">
                                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                                    <?= $restriction_message ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                                </div>
                                                            </div>
                                                            
                                                        <?php if ($user['role'] === 'student'): ?>
                                                        <div class="mb-3">
                                                                <label for="is_irregular<?= $user['id'] ?>" class="form-label fw-semibold">
                                                                    <i class="bi bi-exclamation-triangle me-2"></i>Student Status
                                                                </label>
                                                            <select class="form-select" id="is_irregular<?= $user['id'] ?>" name="is_irregular">
                                                                <option value="0" <?= (isset($user['is_irregular']) && !$user['is_irregular']) ? 'selected' : '' ?>>Regular</option>
                                                                <option value="1" <?= (isset($user['is_irregular']) && $user['is_irregular']) ? 'selected' : '' ?>>Irregular</option>
                                                            </select>
                                                        </div>
                                                        <?php endif; ?>
                                                            
                                                        <?php if ($user['role'] === 'teacher'): ?>
                                                        <div class="mb-3">
                                                                <label for="status<?= $user['id'] ?>" class="form-label fw-semibold">
                                                                    <i class="bi bi-toggle-on me-2"></i>Status
                                                                </label>
                                                            <select class="form-select" id="status<?= $user['id'] ?>" name="status">
                                                                <option value="active" <?= (isset($user['status']) && $user['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                                                                <option value="inactive" <?= (isset($user['status']) && $user['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                                            </select>
                                                        </div>
                                                        <?php endif; ?>
                                                            
                                                        <div class="mb-3">
                                                                <label for="password<?= $user['id'] ?>" class="form-label fw-semibold">
                                                                    <i class="bi bi-key me-2"></i>Password
                                                                </label>
                                                                <input type="password" class="form-control" id="password<?= $user['id'] ?>" 
                                                                       name="password" placeholder="Leave blank to keep current password">
                                                                <div class="form-text">Minimum 6 characters required if changing password.</div>
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
                                        
                                        <!-- View User Modal -->
                                    <div class="modal fade" id="viewUserModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="viewUserLabel<?= $user['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                        <div class="modal-header bg-info text-white">
                                                            <h5 class="modal-title" id="viewUserLabel<?= $user['id'] ?>">
                                                                <i class="bi bi-eye me-2"></i>View User Details
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-card-text me-2"></i>Identifier
                                                                        </label>
                                                                        <p class="form-control-plaintext bg-light p-2 rounded"><?= htmlspecialchars($user['identifier'] ?? 'Not assigned') ?></p>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-person-badge me-2"></i>Role
                                                                        </label>
                                                                        <p class="form-control-plaintext bg-light p-2 rounded">
                                                                            <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'primary' : 'success') ?>">
                                                                                <?= ucfirst($user['role']) ?>
                                                                            </span>
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
                                                                        <p class="form-control-plaintext bg-light p-2 rounded"><?= htmlspecialchars($user['first_name']) ?></p>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-person me-2"></i>Last Name
                                                                        </label>
                                                                        <p class="form-control-plaintext bg-light p-2 rounded"><?= htmlspecialchars($user['last_name']) ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-person-circle me-2"></i>Username
                                                                        </label>
                                                                        <p class="form-control-plaintext bg-light p-2 rounded"><?= htmlspecialchars($user['username']) ?></p>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-envelope me-2"></i>Email
                                                                        </label>
                                                                        <p class="form-control-plaintext bg-light p-2 rounded"><?= htmlspecialchars($user['email']) ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if ($user['role'] === 'teacher'): ?>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-check-circle me-2"></i>Status
                                                                        </label>
                                                                        <p class="form-control-plaintext bg-light p-2 rounded">
                                                                            <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                                                                                <?= ucfirst($user['status'] ?? 'active') ?>
                                                                            </span>
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($user['role'] === 'student'): ?>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-book me-2"></i>Student Type
                                                                        </label>
                                                                        <p class="form-control-plaintext bg-light p-2 rounded">
                                                                            <span class="badge bg-<?= $user['is_irregular'] ? 'warning' : 'info' ?>">
                                                                                <?= $user['is_irregular'] ? 'Irregular' : 'Regular' ?>
                                                                            </span>
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-calendar3 me-2"></i>Created Date
                                                                        </label>
                                                                        <p class="form-control-plaintext bg-light p-2 rounded"><?= date('M j, Y g:i A', strtotime($user['created_at'])) ?></p>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-clock me-2"></i>Last Updated
                                                                        </label>
                                                                        <p class="form-control-plaintext bg-light p-2 rounded"><?= date('M j, Y g:i A', strtotime($user['updated_at'] ?? $user['created_at'])) ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>
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

<!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
                <form method="post" action="users.php">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="createUserLabel">
                        <i class="bi bi-person-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                        <div class="mb-3">
                                <label for="first_name_add" class="form-label fw-semibold">
                                    <i class="bi bi-person me-2"></i>First Name
                                </label>
                            <input type="text" class="form-control" id="first_name_add" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                        <div class="mb-3">
                                <label for="last_name_add" class="form-label fw-semibold">
                                    <i class="bi bi-person me-2"></i>Last Name
                                </label>
                            <input type="text" class="form-control" id="last_name_add" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username_add" class="form-label fw-semibold">
                                    <i class="bi bi-at me-2"></i>Username
                                </label>
                                <input type="text" class="form-control" id="username_add" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                    <div class="mb-3">
                                <label for="email_add" class="form-label fw-semibold">
                                    <i class="bi bi-envelope me-2"></i>Email
                                </label>
                            <input type="email" class="form-control" id="email_add" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                    <div class="mb-3">
                                <label for="role_add" class="form-label fw-semibold">
                                    <i class="bi bi-shield me-2"></i>Role
                                </label>
                            <select class="form-select" id="role_add" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Administrator</option>
                            <option value="teacher">Teacher</option>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                <option value="student">Student</option>
                            <?php endif; ?>
                        </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_add" class="form-label fw-semibold">
                                    <i class="bi bi-key me-2"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password_add" name="password" required>
                                <div class="form-text">Minimum 6 characters required.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="isIrregularAddGroup" style="display:none;">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-exclamation-triangle me-2"></i>Student Status
                        </label>
                        <select class="form-select" name="is_irregular" id="is_irregular_add">
                            <option value="0">Regular</option>
                            <option value="1">Irregular</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="teacherStatusAddGroup" style="display:none;">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-toggle-on me-2"></i>Teacher Status
                        </label>
                        <select class="form-select" name="status" id="status_add">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSubjects(id) {
    var el = document.getElementById(id);
    if (el.style.display === 'none') {
        el.style.display = '';
    } else {
        el.style.display = 'none';
    }
}

document.getElementById('role_add').addEventListener('change', function() {
    var group = document.getElementById('isIrregularAddGroup');
    var teacherGroup = document.getElementById('teacherStatusAddGroup');
    if (this.value === 'student') {
        group.style.display = '';
        teacherGroup.style.display = 'none';
    } else if (this.value === 'teacher') {
        group.style.display = 'none';
        teacherGroup.style.display = '';
    } else {
        group.style.display = 'none';
        teacherGroup.style.display = 'none';
    }
});

window.addEventListener('DOMContentLoaded', function() {
    var group = document.getElementById('isIrregularAddGroup');
    var teacherGroup = document.getElementById('teacherStatusAddGroup');
    if (document.getElementById('role_add').value === 'student') {
        group.style.display = '';
        teacherGroup.style.display = 'none';
    } else if (document.getElementById('role_add').value === 'teacher') {
        group.style.display = 'none';
        teacherGroup.style.display = '';
    } else {
        group.style.display = 'none';
        teacherGroup.style.display = 'none';
    }
});

// Real-time filtering functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const roleSelect = document.getElementById('role');
    const sectionSelect = document.getElementById('section');
    const yearSelect = document.getElementById('year');
    const filterBtn = document.getElementById('filterBtn');
    const clearBtn = document.getElementById('clearBtn');
    const usersTableContainer = document.getElementById('usersTableContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const userCountBadge = document.querySelector('.badge.bg-primary.fs-6');
    
    let searchTimeout;
    
    // Function to perform AJAX search
    function performSearch() {
        const search = searchInput.value.trim();
        const role = roleSelect.value;
        const section = sectionSelect.value;
        const year = yearSelect.value;
        
        // Show loading indicator and disable form
        loadingIndicator.style.display = 'block';
        document.getElementById('filterForm').classList.add('form-loading');
        usersTableContainer.querySelector('.scrollable-table, .text-center')?.style.setProperty('display', 'none');
        
        // Build query parameters
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (role) params.append('role', role);
        if (section) params.append('section', section);
        if (year) params.append('year', year);
        
        // Make AJAX request
        fetch(`ajax_get_users.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update users table with animation
                    usersTableContainer.innerHTML = data.users_html;
                    
                    // Update user count badge with animation
                    if (userCountBadge) {
                        userCountBadge.style.transform = 'scale(1.1)';
                        userCountBadge.textContent = `${data.total_users} users found`;
                        setTimeout(() => {
                            userCountBadge.style.transform = 'scale(1)';
                        }, 200);
                    }
                    
                    // Update statistics cards if available
                    updateStatistics(data.stats);
                    
                    // Update URL without page reload
                    const newUrl = new URL(window.location);
                    newUrl.search = params.toString();
                    window.history.pushState({}, '', newUrl);
                    
                    // Success feedback removed as requested
                } else {
                    console.error('Error:', data.error);
                    showError('Failed to load users. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Failed to load users. Please try again.');
            })
            .finally(() => {
                loadingIndicator.style.display = 'none';
                document.getElementById('filterForm').classList.remove('form-loading');
            });
    }
    
    // Function to update statistics cards with animations
    function updateStatistics(stats) {
        // Update total users
        const totalUsersElement = document.querySelector('.stats-primary h3');
        if (totalUsersElement) {
            animateNumberChange(totalUsersElement, stats.total_users);
        }
        
        // Update admins
        const adminsElement = document.querySelector('.stats-success h3');
        if (adminsElement) {
            animateNumberChange(adminsElement, stats.total_admins);
        }
        
        // Update teachers
        const teachersElement = document.querySelector('.stats-info h3');
        if (teachersElement) {
            animateNumberChange(teachersElement, stats.total_teachers);
        }
        
        // Update students
        const studentsElement = document.querySelector('.stats-warning h3');
        if (studentsElement) {
            animateNumberChange(studentsElement, stats.total_students);
        }
        
        // Update irregular students
        const irregularElement = document.querySelector('.stats-danger h3');
        if (irregularElement) {
            animateNumberChange(irregularElement, stats.irregular_students);
        }
        
        // Update inactive teachers
        const inactiveElement = document.querySelector('.stats-secondary h3');
        if (inactiveElement) {
            animateNumberChange(inactiveElement, stats.inactive_teachers);
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
    
    // Function to show error message
    function showError(message) {
        usersTableContainer.innerHTML = `
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
        
        // Show immediate visual feedback
        searchInput.classList.add('searching');
        
        // Faster debouncing for better responsiveness
        searchTimeout = setTimeout(() => {
            searchInput.classList.remove('searching');
            performSearch();
        }, 200); // Reduced to 200ms for faster response
    });
    
    // Immediate filtering for dropdowns with visual feedback
    roleSelect.addEventListener('change', function() {
        this.classList.add('filtering');
        performSearch();
        setTimeout(() => this.classList.remove('filtering'), 1000);
    });
    
    sectionSelect.addEventListener('change', function() {
        this.classList.add('filtering');
        performSearch();
        setTimeout(() => this.classList.remove('filtering'), 1000);
    });
    
    yearSelect.addEventListener('change', function() {
        this.classList.add('filtering');
        performSearch();
        setTimeout(() => this.classList.remove('filtering'), 1000);
    });
    
    // Filter button (for manual trigger)
    filterBtn.addEventListener('click', performSearch);
    
    // Clear button
    clearBtn.addEventListener('click', function(e) {
        e.preventDefault();
        searchInput.value = '';
        roleSelect.value = '';
        sectionSelect.value = '';
        yearSelect.value = '';
        performSearch();
    });
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function() {
        const urlParams = new URLSearchParams(window.location.search);
        searchInput.value = urlParams.get('search') || '';
        roleSelect.value = urlParams.get('role') || '';
        sectionSelect.value = urlParams.get('section') || '';
        yearSelect.value = urlParams.get('year') || '';
        performSearch();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?> 