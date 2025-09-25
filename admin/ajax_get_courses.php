<?php
// Start session and include necessary files
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

try {
    // Get filter parameters
    $search = sanitizeInput($_GET['search'] ?? '');
    $academic_year_filter = (int)($_GET['academic_year'] ?? 0);
    $status_filter = sanitizeInput($_GET['status'] ?? '');
    $year_level_filter = sanitizeInput($_GET['year_level'] ?? '');
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(c.course_name LIKE ? OR c.course_code LIKE ? OR c.description LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if ($academic_year_filter > 0) {
        $where_conditions[] = "c.academic_period_id = ?";
        $params[] = $academic_year_filter;
    }
    
if ($status_filter === 'archived') {
    $where_conditions[] = "(c.is_archived = 1 OR c.status = 'archived')";
} elseif ($status_filter === 'active') {
    $where_conditions[] = "(c.is_archived = 0 AND (c.status = 'active' OR c.status IS NULL))";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "c.status = 'inactive'";
} elseif ($status_filter === 'draft') {
    $where_conditions[] = "c.status = 'draft'";
}
    
    if (!empty($year_level_filter)) {
        $where_conditions[] = "c.year_level = ?";
        $params[] = $year_level_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get courses with search and filter
    $stmt = $db->prepare("
        SELECT c.*, u.first_name, u.last_name, u.username, ap.academic_year, ap.semester_name,
               COUNT(e.student_id) as student_count,
               JSON_LENGTH(c.modules) as module_count
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
        $where_clause
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
    
    // Get statistics with improved status logic
    $stats_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_courses,
            COUNT(CASE WHEN (is_archived = 0 AND (status = 'active' OR status IS NULL)) THEN 1 END) as active_courses,
            COUNT(CASE WHEN (is_archived = 1 OR status = 'archived') THEN 1 END) as archived_courses,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_courses,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_courses,
            COUNT(DISTINCT teacher_id) as unique_teachers,
            COUNT(DISTINCT academic_period_id) as periods_with_courses,
            SUM(CASE WHEN (is_archived = 0 AND (status = 'active' OR status IS NULL)) THEN 1 ELSE 0 END) as current_courses
        FROM courses
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();
    
    // Get total students and modules
    $total_stats_stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT e.student_id) as total_students,
            SUM(JSON_LENGTH(c.modules)) as total_modules
        FROM courses c
        LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
        WHERE c.is_archived = 0
    ");
    $total_stats_stmt->execute();
    $total_stats = $total_stats_stmt->fetch();
    
    // Generate HTML for courses table
    $courses_html = '';
    
    if (empty($courses)) {
        $courses_html = '
            <div class="text-center py-5">
                <i class="bi bi-book fs-1 text-muted mb-3"></i>
                <h5 class="text-muted">No courses found</h5>
                <p class="text-muted">Try adjusting your search criteria.</p>
            </div>
        ';
    } else {
        $courses_html = '<div class="scrollable-table">
            <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="border-0">
                        <i class="bi bi-book me-2"></i>Course
                    </th>
                    <th class="border-0">
                        <i class="bi bi-person-workspace me-2"></i>Teacher
                    </th>
                    <th class="border-0">
                        <i class="bi bi-calendar-event me-2"></i>Academic Year
                    </th>
                    <th class="border-0">
                        <i class="bi bi-mortarboard me-2"></i>Year Level
                    </th>
                    <th class="border-0">
                        <i class="bi bi-people me-2"></i>Students
                    </th>
                    <th class="border-0">
                        <i class="bi bi-collection me-2"></i>Modules
                    </th>
                    <th class="border-0">
                        <i class="bi bi-toggle-on me-2"></i>Status
                    </th>
                    <th class="border-0">
                        <i class="bi bi-calendar me-2"></i>Created
                    </th>
                    <th class="border-0 text-center">
                        <i class="bi bi-gear me-2"></i>Actions
                    </th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($courses as $course) {
            $year_level = $course['year_level'] ?? 'N/A';
            $year_colors = [
                '1' => 'success',
                '2' => 'info', 
                '3' => 'warning',
                '4' => 'danger'
            ];
            $badge_color = $year_colors[$year_level] ?? 'secondary';
            
            $courses_html .= '
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-' . ($course['is_archived'] ? 'secondary' : 'primary') . ' rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="bi bi-book text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex align-items-center mb-1">
                                    <h6 class="mb-0 fw-semibold me-2">' . htmlspecialchars($course['course_name']) . '</h6>
                                    <span class="badge bg-' . $badge_color . ' small">
                                        <i class="bi bi-mortarboard me-1"></i>' . htmlspecialchars($year_level) . ' Year
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-code me-1"></i>' . htmlspecialchars($course['course_code']) . '
                                </small>';
            
            if ($course['description']) {
                $courses_html .= '<br><small class="text-muted">' . htmlspecialchars(substr($course['description'], 0, 50)) . '...</small>';
            }
            
            $courses_html .= '
                                <small class="text-muted">
                                    Created by: ' . htmlspecialchars($course['first_name'] . ' ' . $course['last_name'] . ' (' . $course['username'] . ')') . '
                                </small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="' . getProfilePictureUrl($course['profile_picture'] ?? null, 'medium') . '" 
                                 class="rounded-circle me-2" alt="Teacher" style="width: 32px; height: 32px; object-fit: cover;">
                            <div>
                                <div class="fw-semibold">' . htmlspecialchars($course['first_name'] . ' ' . $course['last_name']) . '</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold">' . htmlspecialchars($course['academic_year']) . '</div>
                        <small class="text-muted">
                            <i class="bi bi-calendar2-week me-1"></i>' . htmlspecialchars($course['semester_name']) . '
                        </small>
                    </td>
                    <td>
                        <div class="d-flex flex-column align-items-center">
                            <span class="badge bg-' . $badge_color . ' fs-6 px-3 py-2">
                                <i class="bi bi-mortarboard me-1"></i>' . htmlspecialchars($year_level) . ' Year
                            </span>
                            <small class="text-muted mt-1">';
            
            switch($year_level) {
                case '1':
                    $courses_html .= 'Freshman Level';
                    break;
                case '2':
                    $courses_html .= 'Sophomore Level';
                    break;
                case '3':
                    $courses_html .= 'Junior Level';
                    break;
                case '4':
                    $courses_html .= 'Senior Level';
                    break;
                default:
                    $courses_html .= 'Undefined Level';
            }
            
            $courses_html .= '
                            </small>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-info">
                            <i class="bi bi-people me-1"></i>' . $course['student_count'] . ' students
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-secondary">
                            <i class="bi bi-collection me-1"></i>' . $course['module_count'] . ' modules
                        </span>
                    </td>
                    <td>';
            
            if ($course['is_archived']) {
                $courses_html .= '
                        <span class="badge bg-warning">
                            <i class="bi bi-archive me-1"></i>Archived
                        </span>';
            } else {
                $courses_html .= '
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle me-1"></i>Active
                        </span>';
            }
            
            $courses_html .= '
                    </td>
                    <td>
                        <small class="text-muted">
                            <i class="bi bi-calendar me-1"></i>' . formatDate($course['created_at']) . '
                        </small>
                    </td>
                    <td>
                        <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-info" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#viewCourseModal' . $course['id'] . '"
                                    title="View Course">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editCourseModal' . $course['id'] . '"
                                    title="Edit Course">
                                <i class="bi bi-pencil"></i>
                            </button>';
            
            if (!$course['is_archived']) {
                $courses_html .= '
                            <form method="post" action="courses.php" style="display:inline;" 
                                  onsubmit="return confirm(\'Archive this course?\');">
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="course_id" value="' . $course['id'] . '">
                                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') . '">
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Archive Course">
                                    <i class="bi bi-archive"></i>
                                </button>
                            </form>';
            } else {
                $courses_html .= '
                            <form method="post" action="courses.php" style="display:inline;" 
                                  onsubmit="return confirm(\'Unarchive this course?\');">
                                <input type="hidden" name="action" value="unarchive">
                                <input type="hidden" name="course_id" value="' . $course['id'] . '">
                                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') . '">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Unarchive Course">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </form>';
            }
            
            $courses_html .= '
                            <form method="post" action="courses.php" style="display:inline;" 
                                  onsubmit="return confirm(\'Are you sure you want to delete this course?\');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="course_id" value="' . $course['id'] . '">
                                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') . '">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Course">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>';
        }
        
        $courses_html .= '
            </tbody>
        </table>
    </div>';
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'courses_html' => $courses_html,
        'total_courses' => count($courses),
        'stats' => $stats,
        'total_stats' => $total_stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
