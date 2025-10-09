<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

try {
    // Get filter parameters
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
    
    // Get users
    $stmt = $db->prepare("
        SELECT id, username, email, first_name, last_name, role, profile_picture, created_at, is_irregular, status, identifier, plain_text_password 
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
    
    // Get sections for mapping
    $section_sql = "SELECT id, section_name, year_level FROM sections ORDER BY year_level, section_name";
    $section_res = $db->query($section_sql);
    $sections_raw = $section_res ? $section_res->fetchAll() : [];
    $sections = [];
    foreach ($sections_raw as $section) {
        $sections[$section['id']] = "BSIT-{$section['year_level']}{$section['section_name']}";
    }
    
    // Generate users table HTML and modals
    $users_html = '';
    $modals_html = '';
    
    if (empty($users)) {
        $users_html = '
            <div class="text-center py-5">
                <i class="bi bi-people fs-1 text-muted mb-3"></i>
                <h5 class="text-muted">No users found</h5>
                <p class="text-muted">Try adjusting your search criteria or add a new user.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New User
                </button>
            </div>
        ';
    } else {
        $users_html = '
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
                    <tbody>';
        
        foreach ($users as $user) {
            $users_html .= '
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <img src="' . getProfilePictureUrl($user['profile_picture'] ?? null, 'medium') . '" 
                                     class="rounded-circle me-3" 
                                     width="40" height="40" 
                                     alt="Profile">
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-semibold">' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</h6>
                                <small class="text-muted">@' . htmlspecialchars($user['username']) . '</small>
                            </div>
                        </div>
                    </td>
                    <td>';
            
            if (!empty($user['identifier'])) {
                $badgeColor = 'bg-primary';
                if ($user['role'] === 'admin') {
                    $badgeColor = 'bg-danger';
                } elseif ($user['role'] === 'teacher') {
                    $badgeColor = 'bg-primary';
                } elseif ($user['role'] === 'student') {
                    $badgeColor = 'bg-success';
                }
                $users_html .= '<span class="badge ' . $badgeColor . '">' . htmlspecialchars($user['identifier']) . '</span>';
            } else {
                $users_html .= '<span class="text-muted small">Not assigned</span>';
            }
            
            $users_html .= '
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            <span class="badge bg-' . ($user['role'] === 'admin' ? 'success' : ($user['role'] === 'teacher' ? 'info' : 'warning')) . ' mb-1">
                                <i class="bi bi-' . ($user['role'] === 'admin' ? 'shield-check' : ($user['role'] === 'teacher' ? 'person-badge' : 'mortarboard')) . ' me-1"></i>
                                ' . ucfirst($user['role']) . '
                            </span>';
            
            if ($user['role'] === 'student') {
                $users_html .= '
                            <span class="badge bg-' . ((isset($user['is_irregular']) && $user['is_irregular']) ? 'danger' : 'success') . '">
                                ' . ((isset($user['is_irregular']) && $user['is_irregular']) ? 'Irregular' : 'Regular') . '
                            </span>';
            } elseif ($user['role'] === 'teacher') {
                $users_html .= '
                            <span class="badge bg-' . ((isset($user['status']) && $user['status'] === 'inactive') ? 'secondary' : 'success') . '">
                                ' . ((isset($user['status']) && $user['status'] === 'inactive') ? 'Inactive' : 'Active') . '
                            </span>';
            }
            
            $users_html .= '
                        </div>
                    </td>
                    <td>';
            
            if ($user['role'] === 'student' || $user['role'] === 'teacher') {
                $user_sections = get_user_sections($db, $user['id'], $user['role'], $sections);
                if ($user_sections) {
                    $users_html .= '<span class="badge bg-light text-dark">' . htmlspecialchars(implode(', ', $user_sections)) . '</span>';
                } else {
                    $users_html .= '<span class="text-muted small">No sections</span>';
                }
            } else {
                $users_html .= '<span class="text-muted small">N/A</span>';
            }
            
            $users_html .= '
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-envelope text-muted me-2"></i>
                            <span class="small">' . htmlspecialchars($user['email']) . '</span>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-calendar3 text-muted me-2"></i>
                            <span class="small">' . date('M j, Y', strtotime($user['created_at'])) . '</span>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-info" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#viewUserModal' . $user['id'] . '"
                                    title="View User Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editUserModal' . $user['id'] . '"
                                    title="Edit User">
                                <i class="bi bi-pencil"></i>
                            </button>';
            
            if ($user['id'] !== $_SESSION['user_id']) {
                $users_html .= '
                            <form method="post" action="users.php" style="display:inline;" 
                                  onsubmit="return confirm(\'Are you sure you want to delete this user? This action cannot be undone.\');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="' . $user['id'] . '">
                                <input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') . '">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>';
            } else {
                $users_html .= '
                            <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete your own account">
                                <i class="bi bi-shield-lock"></i>
                            </button>';
            }
            
            $users_html .= '
                        </div>
                    </td>
                </tr>';
            
            // Generate modals for this user
            $modals_html .= generateUserModals($user, $sections);
        }
        
        $users_html .= '
                    </tbody>
                </table>
            </div>';
    }
    
    echo json_encode([
        'success' => true,
        'users_html' => $users_html,
        'modals_html' => $modals_html,
        'total_users' => count($users),
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year_level']}{$section['section_name']}";
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

// Get courses for a teacher
function get_teacher_courses($db, $teacher_id) {
    $sql = "SELECT id, course_name, course_code, description, status, year_level, credits, created_at 
            FROM courses 
            WHERE teacher_id = ? 
            ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$teacher_id]);
    return $stmt->fetchAll();
}

// Generate courses display for teacher
function generateTeacherCoursesDisplay($teacher_id) {
    global $db;
    
    $courses = get_teacher_courses($db, $teacher_id);
    
    if (empty($courses)) {
        return '<div class="text-center text-muted py-2">
                    <i class="bi bi-book fs-5 mb-1"></i>
                    <p class="mb-0 small">No courses assigned yet</p>
                </div>';
    }
    
    $courses_html = '<div class="row g-2">';
    
    foreach ($courses as $course) {
        $status_badge = '';
        switch ($course['status']) {
            case 'active':
                $status_badge = '<span class="badge bg-success badge-sm">Active</span>';
                break;
            case 'inactive':
                $status_badge = '<span class="badge bg-secondary badge-sm">Inactive</span>';
                break;
            case 'archived':
                $status_badge = '<span class="badge bg-warning badge-sm">Archived</span>';
                break;
            case 'draft':
                $status_badge = '<span class="badge bg-info badge-sm">Draft</span>';
                break;
        }
        
        $courses_html .= '
            <div class="col-md-6">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="card-title mb-0 fw-semibold text-primary small">' . htmlspecialchars($course['course_name']) . '</h6>
                            ' . $status_badge . '
                        </div>
                        <div class="d-flex flex-wrap gap-2 small text-muted">
                            <span><strong>Code:</strong> ' . htmlspecialchars($course['course_code']) . '</span>';
        
        if (!empty($course['year_level'])) {
            $courses_html .= '<span><strong>Year:</strong> ' . htmlspecialchars($course['year_level']) . '</span>';
        }
        
        if (!empty($course['credits'])) {
            $courses_html .= '<span><strong>Credits:</strong> ' . htmlspecialchars($course['credits']) . '</span>';
        }
        
        $courses_html .= '</div>';
        
        if (!empty($course['description'])) {
            $description = strlen($course['description']) > 80 ? substr($course['description'], 0, 80) . '...' : $course['description'];
            $courses_html .= '<p class="card-text small text-muted mb-1 mt-1">
                                ' . htmlspecialchars($description) . '
                              </p>';
        }
        
        $courses_html .= '
                        <div class="mt-1">
                            <small class="text-muted">
                                <i class="bi bi-calendar3 me-1"></i>
                                ' . date('M j, Y', strtotime($course['created_at'])) . '
                            </small>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    $courses_html .= '</div>';
    
    return $courses_html;
}

// Generate modals for a user
function generateUserModals($user, $sections) {
    $modals_html = '';
    
    // Edit User Modal
    $modals_html .= '
        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal' . $user['id'] . '" tabindex="-1" aria-labelledby="editUserLabel' . $user['id'] . '" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="post" action="users.php">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="editUserLabel' . $user['id'] . '">
                                <i class="bi bi-pencil-square me-2"></i>Edit User
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="user_id" value="' . $user['id'] . '">
                            <input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') . '">
                            
                            <div class="mb-3">
                                <label for="user_id_display' . $user['id'] . '" class="form-label fw-semibold">
                                    <i class="bi bi-card-text me-2"></i>Identifier
                                </label>
                                <input type="text" class="form-control" id="user_id_display' . $user['id'] . '" 
                                       value="' . htmlspecialchars($user['identifier'] ?? 'Not assigned') . '" readonly>
                                <div class="form-text">Identifier cannot be modified</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name' . $user['id'] . '" class="form-label fw-semibold">
                                            <i class="bi bi-person me-2"></i>First Name
                                        </label>
                                        <input type="text" class="form-control" id="first_name' . $user['id'] . '" 
                                               name="first_name" required value="' . htmlspecialchars($user['first_name']) . '">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name' . $user['id'] . '" class="form-label fw-semibold">
                                            <i class="bi bi-person me-2"></i>Last Name
                                        </label>
                                        <input type="text" class="form-control" id="last_name' . $user['id'] . '" 
                                               name="last_name" required value="' . htmlspecialchars($user['last_name']) . '">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email' . $user['id'] . '" class="form-label fw-semibold">
                                            <i class="bi bi-envelope me-2"></i>Email
                                        </label>
                                        <input type="email" class="form-control" id="email' . $user['id'] . '" 
                                               name="email" required value="' . htmlspecialchars($user['email']) . '">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="role' . $user['id'] . '" class="form-label fw-semibold">
                                            <i class="bi bi-shield me-2"></i>Role
                                            ' . (($user['role'] === 'student' || ($_SESSION['role'] ?? '') === 'teacher') ? '<span class="badge bg-warning ms-2">Cannot be changed</span>' : '') . '
                                        </label>
                                        <select class="form-select" id="role' . $user['id'] . '" name="role" required ' . (($user['role'] === 'student' || ($_SESSION['role'] ?? '') === 'teacher') ? 'disabled' : '') . '>
                                            <option value="admin" ' . ($user['role'] === 'admin' ? 'selected' : '') . '>Administrator</option>
                                            <option value="teacher" ' . ($user['role'] === 'teacher' ? 'selected' : '') . '>Teacher</option>
                                            ' . (($_SESSION['role'] ?? '') === 'admin' && $user['role'] === 'student' ? '<option value="student" ' . ($user['role'] === 'student' ? 'selected' : '') . '>Student</option>' : '') . '
                                        </select>
                                        ' . (($user['role'] === 'student' || ($_SESSION['role'] ?? '') === 'teacher') ? '<input type="hidden" name="role" value="' . $user['role'] . '">' : '') . '
                                    </div>
                                </div>
                            </div>
                            
                            ' . ($user['role'] === 'student' ? '
                            <div class="mb-3">
                                <label for="is_irregular' . $user['id'] . '" class="form-label fw-semibold">
                                    <i class="bi bi-exclamation-triangle me-2"></i>Student Status
                                </label>
                                <select class="form-select" id="is_irregular' . $user['id'] . '" name="is_irregular">
                                    <option value="0" ' . ((isset($user['is_irregular']) && !$user['is_irregular']) ? 'selected' : '') . '>Regular</option>
                                    <option value="1" ' . ((isset($user['is_irregular']) && $user['is_irregular']) ? 'selected' : '') . '>Irregular</option>
                                </select>
                            </div>
                            ' : '') . '
                            
                            ' . ($user['role'] === 'teacher' ? '
                            <div class="mb-3">
                                <label for="status' . $user['id'] . '" class="form-label fw-semibold">
                                    <i class="bi bi-toggle-on me-2"></i>Status
                                </label>
                                <select class="form-select" id="status' . $user['id'] . '" name="status">
                                    <option value="active" ' . ((isset($user['status']) && $user['status'] === 'active') ? 'selected' : '') . '>Active</option>
                                    <option value="inactive" ' . ((isset($user['status']) && $user['status'] === 'inactive') ? 'selected' : '') . '>Inactive</option>
                                </select>
                            </div>
                            ' : '') . '
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-key me-2"></i>Password Management
                                </label>
                                
                                <!-- Current Password Display -->
                                <div class="mb-2">
                                    <label for="currentPassword' . $user['id'] . '" class="form-label small text-muted">
                                        <i class="bi bi-shield-lock me-1"></i>Current Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="currentPassword' . $user['id'] . '" 
                                               value="' . htmlspecialchars($user['plain_text_password'] ?? '••••••••••••') . '" readonly>
                                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" id="toggleCurrentPassword' . $user['id'] . '">
                                            <i class="bi bi-eye" id="currentEyeIcon' . $user['id'] . '"></i>
                                        </button>
                                    </div>
                                    <div class="form-text small text-muted">
                                        <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                        <strong>Security Warning:</strong> Password is stored in plain text for admin viewing
                                    </div>
                                </div>
                                
                                <!-- New Password Input -->
                                <div>
                                    <label for="password' . $user['id'] . '" class="form-label small text-muted">
                                        <i class="bi bi-key me-1"></i>New Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password' . $user['id'] . '" 
                                               name="password" placeholder="Enter new password (leave blank to keep current)">
                                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" id="togglePassword' . $user['id'] . '">
                                            <i class="bi bi-eye" id="eyeIcon' . $user['id'] . '"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimum 6 characters required if changing password.</div>
                                </div>
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
        <div class="modal fade" id="viewUserModal' . $user['id'] . '" tabindex="-1" aria-labelledby="viewUserLabel' . $user['id'] . '" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="viewUserLabel' . $user['id'] . '">
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
                                    <p class="form-control-plaintext bg-light p-2 rounded">' . htmlspecialchars($user['identifier'] ?? 'Not assigned') . '</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-person-badge me-2"></i>Role
                                    </label>
                                    <p class="form-control-plaintext bg-light p-2 rounded">
                                        <span class="badge bg-' . ($user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'primary' : 'success')) . '">
                                            ' . ucfirst($user['role']) . '
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
                                    <p class="form-control-plaintext bg-light p-2 rounded">' . htmlspecialchars($user['first_name']) . '</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-person me-2"></i>Last Name
                                    </label>
                                    <p class="form-control-plaintext bg-light p-2 rounded">' . htmlspecialchars($user['last_name']) . '</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-person-circle me-2"></i>Username
                                    </label>
                                    <p class="form-control-plaintext bg-light p-2 rounded">' . htmlspecialchars($user['username']) . '</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-envelope me-2"></i>Email
                                    </label>
                                    <p class="form-control-plaintext bg-light p-2 rounded">' . htmlspecialchars($user['email']) . '</p>
                                </div>
                            </div>
                        </div>
                        
                        ' . ($user['role'] === 'teacher' ? '
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-check-circle me-2"></i>Status
                                    </label>
                                    <p class="form-control-plaintext bg-light p-2 rounded">
                                        <span class="badge bg-' . ($user['status'] === 'active' ? 'success' : 'warning') . '">
                                            ' . ucfirst($user['status'] ?? 'active') . '
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-book me-2"></i>Courses Taught
                                    </label>
                                    <div class="bg-light p-3 rounded courses-scrollable">
                                        ' . generateTeacherCoursesDisplay($user['id']) . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        ' : '') . '
                        
                        ' . ($user['role'] === 'student' ? '
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-book me-2"></i>Student Type
                                    </label>
                                    <p class="form-control-plaintext bg-light p-2 rounded">
                                        <span class="badge bg-' . ($user['is_irregular'] ? 'warning' : 'info') . '">
                                            ' . ($user['is_irregular'] ? 'Irregular' : 'Regular') . '
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        ' : '') . '
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-calendar3 me-2"></i>Created Date
                                    </label>
                                    <p class="form-control-plaintext bg-light p-2 rounded">' . date('M j, Y g:i A', strtotime($user['created_at'])) . '</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-clock me-2"></i>Last Updated
                                    </label>
                                    <p class="form-control-plaintext bg-light p-2 rounded">' . date('M j, Y g:i A', strtotime($user['updated_at'] ?? $user['created_at'])) . '</p>
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
    ';
    
    return $modals_html;
}
?>
