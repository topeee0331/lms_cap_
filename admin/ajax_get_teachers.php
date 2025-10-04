<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$order = $_GET['order'] ?? 'asc';

try {
    // Build the base query
    $where_conditions = ["u.role = 'teacher'"];
    $params = [];
    
    // Add search condition
    if (!empty($search)) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.identifier LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    }
    
    // Add status condition
    if (!empty($status)) {
        if ($status === 'active') {
            $where_conditions[] = "(u.status IS NULL OR u.status != 'inactive')";
        } else {
            $where_conditions[] = "u.status = ?";
            $params[] = $status;
        }
    }
    
    // Build sort clause
    $sort_column = match($sort) {
        'email' => 'u.email',
        'date' => 'u.created_at',
        'status' => 'u.status',
        default => 'u.first_name'
    };
    
    $sort_order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    
    // Execute the query
    $sql = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.username,
            u.email,
            u.identifier,
            u.status,
            u.profile_picture,
            u.created_at
        FROM users u 
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY {$sort_column} {$sort_order}
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
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
    
    // Generate teachers table HTML
    $teachers_html = '';
    if (empty($teachers)) {
        $teachers_html = '
            <div class="text-center py-5">
                <i class="bi bi-person-badge fs-1 text-muted mb-3"></i>
                <h5 class="text-muted">No teachers found</h5>
                <p class="text-muted">Try adjusting your search criteria or add a new teacher.</p>
            </div>
        ';
    } else {
        $teachers_html = '
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
                <tbody>';
        
        foreach ($teachers as $teacher) {
            $teachers_html .= '
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <img src="' . getProfilePictureUrl($teacher['profile_picture'] ?? null, 'medium') . '" 
                                     class="rounded-circle me-3" 
                                     width="40" height="40" 
                                     alt="Profile">
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-semibold">' . htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) . '</h6>
                                <small class="text-muted">@' . htmlspecialchars($teacher['username']) . '</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        ' . (!empty($teacher['identifier']) ? 
                            '<span class="badge bg-success">' . htmlspecialchars($teacher['identifier']) . '</span>' : 
                            '<span class="text-muted small">Not assigned</span>') . '
                    </td>
                    <td>
                        <span class="badge bg-' . ((isset($teacher['status']) && $teacher['status'] === 'inactive') ? 'secondary' : 'success') . '">
                            <i class="bi bi-' . ((isset($teacher['status']) && $teacher['status'] === 'inactive') ? 'archive' : 'check-circle') . ' me-1"></i>
                            ' . ((isset($teacher['status']) && $teacher['status'] === 'inactive') ? 'Archived' : 'Active') . '
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-envelope text-muted me-2"></i>
                            <span class="small">' . htmlspecialchars($teacher['email']) . '</span>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-calendar3 text-muted me-2"></i>
                            <span class="small">' . date('M j, Y', strtotime($teacher['created_at'])) . '</span>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-info text-white" 
                                    onclick="viewTeacher(' . $teacher['id'] . ')"
                                    title="View Teacher Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary" 
                                    onclick="editTeacher(' . $teacher['id'] . ')"
                                    title="Edit Teacher">
                                <i class="bi bi-pencil"></i>
                            </button>
                            ' . (isset($teacher['status']) && $teacher['status'] === 'inactive' ? 
                                '<button class="btn btn-sm btn-success" 
                                        onclick="unarchiveTeacher(' . $teacher['id'] . ')"
                                        title="Unarchive Teacher">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>' : 
                                '<button class="btn btn-sm btn-danger" 
                                        onclick="archiveTeacher(' . $teacher['id'] . ')"
                                        title="Archive Teacher">
                                    <i class="bi bi-archive"></i>
                                </button>') . '
                        </div>
                    </td>
                </tr>';
        }
        
        $teachers_html .= '
                </tbody>
            </table>';
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'teachers_html' => $teachers_html,
        'total_teachers' => $total_teachers,
        'stats' => [
            'total_teachers' => $total_teachers,
            'active_teachers' => $active_teachers,
            'inactive_teachers' => $inactive_teachers
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
