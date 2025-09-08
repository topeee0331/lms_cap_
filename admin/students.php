<?php
$page_title = 'Manage Students';
require_once '../config/config.php';
require_once '../includes/student_id_generator.php';
requireRole('admin');
require_once '../includes/header.php';

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
<div class="container py-4" style="margin-top: 80px;">
    <div class="row mb-4">
        <div class="col-12">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <div class="row mb-3">
                <div class="col-md-3 mb-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="mb-1">Total Students</h6>
                            <h2 class="fw-bold mb-0"><?= $total_students ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="mb-1">Active Students</h6>
                            <h2 class="fw-bold mb-0 text-success"><?= $active_students ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="mb-1">Archived Students</h6>
                            <h2 class="fw-bold mb-0 text-secondary"><?= $inactive_students ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="mb-1">Regular Students</h6>
                            <h2 class="fw-bold mb-0 text-primary"><?= $total_regular ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3 mb-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="mb-1">Irregular Students</h6>
                            <h2 class="fw-bold mb-0 text-danger"><?= $total_irregular ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="bi bi-plus-circle me-2"></i>Add Student</button>
            </div>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Students List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
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