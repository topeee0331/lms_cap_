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
    
    // Get sections for mapping
    $section_sql = "SELECT id, section_name, year_level FROM sections ORDER BY year_level, section_name";
    $section_res = $db->query($section_sql);
    $sections_raw = $section_res ? $section_res->fetchAll() : [];
    $sections = [];
    foreach ($sections_raw as $section) {
        $sections[$section['id']] = "BSIT-{$section['year_level']}{$section['section_name']}";
    }
    
    // Generate users table HTML
    $users_html = '';
    
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
        }
        
        $users_html .= '
                    </tbody>
                </table>
            </div>';
    }
    
    echo json_encode([
        'success' => true,
        'users_html' => $users_html,
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
?>
