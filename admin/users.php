<?php
$page_title = 'Manage Users';
require_once '../includes/header.php';
require_once '../includes/student_id_generator.php';
requireRole('admin');

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
                
                if (empty($first_name) || empty($last_name) || empty($email) || empty($role)) {
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

<div class="container-fluid py-4">
    <!-- Navigation Back to Dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <a href="../admin/dashboard.php" class="btn btn-outline-primary mb-3">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-people-fill fs-1 text-primary"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['total_users'] ?></h3>
                    <p class="text-muted mb-0 small">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-shield-fill-check fs-1 text-success"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['total_admins'] ?></h3>
                    <p class="text-muted mb-0 small">Administrators</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-person-badge-fill fs-1 text-info"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['total_teachers'] ?></h3>
                    <p class="text-muted mb-0 small">Teachers</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-mortarboard-fill fs-1 text-warning"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['total_students'] ?></h3>
                    <p class="text-muted mb-0 small">Students</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-exclamation-triangle-fill fs-1 text-danger"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['irregular_students'] ?></h3>
                    <p class="text-muted mb-0 small">Irregular Students</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="bi bi-person-x-fill fs-1 text-secondary"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $stats['inactive_teachers'] ?></h3>
                    <p class="text-muted mb-0 small">Inactive Teachers</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="users.php" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="search" class="form-label fw-semibold">
                                <i class="bi bi-search me-2"></i>Search Users
                            </label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by name, email, or username..." 
                                   value="<?= htmlspecialchars($search) ?>">
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
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-2"></i>Search
                            </button>
                        </div>
                        <div class="col-md-1">
                            <a href="users.php" class="btn btn-outline-secondary w-100">
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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-semibold">
                            <i class="bi bi-people me-2"></i>Users Management
                        </h5>
                        <span class="badge bg-primary fs-6"><?= count($users) ?> users found</span>
                    </div>
                </div>
                <div class="card-body p-0">
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
                        <div class="table-responsive">
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
                                                                        </label>
                                                            <select class="form-select" id="role<?= $user['id'] ?>" name="role" required>
                                                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                                                <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                                                                <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                                            </select>
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
                            <option value="student">Student</option>
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
</script>

<?php require_once '../includes/footer.php'; ?> 