<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/student_id_generator.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Get search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$section_filter = sanitizeInput($_GET['section'] ?? '');
$year_filter = sanitizeInput($_GET['year'] ?? '');

$where_conditions = ["role = 'student'"];
$params = [];

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

// Calculate statistics
$total_students = count($students);
$total_regular = 0;
$total_irregular = 0;
$active_students = 0;
$inactive_students = 0;
$students_with_sections = 0;

foreach ($students as $student) {
    if ($student['is_irregular']) {
        $total_irregular++;
    } else {
        $total_regular++;
    }
    if (isset($student['status']) && $student['status'] === 'inactive') {
        $inactive_students++;
    } else {
        $active_students++;
    }
    if (!empty($student['sections']) && count($student['sections']) > 0) {
        $students_with_sections++;
    }
}

// Generate students table HTML
$students_html = '';
if (empty($students)) {
    $students_html = '
        <div class="text-center py-5">
            <i class="bi bi-mortarboard fs-1 text-muted mb-3"></i>
            <h5 class="text-muted">No students found</h5>
            <p class="text-muted">Try adjusting your search criteria or add a new student.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="bi bi-plus-circle me-2"></i>Add New Student
            </button>
        </div>';
} else {
    $students_html = '<div class="scrollable-table">
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
            <tbody>';
    
    foreach ($students as $student) {
        $students_html .= '
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <img src="' . getProfilePictureUrl($student['profile_picture'] ?? null, 'medium') . '" 
                                 class="rounded-circle me-3" 
                                 width="40" height="40" 
                                 alt="Profile">
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-semibold">' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . '</h6>
                            <small class="text-muted">@' . htmlspecialchars($student['username']) . '</small>
                        </div>
                    </div>
                </td>
                <td>
                    ' . (!empty($student['identifier']) ? 
                        '<span class="badge bg-success">' . htmlspecialchars($student['identifier']) . '</span>' : 
                        '<span class="text-muted small">Not assigned</span>') . '
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="badge bg-' . ((isset($student['status']) && $student['status'] === 'inactive') ? 'secondary' : 'success') . ' mb-1">
                            <i class="bi bi-' . ((isset($student['status']) && $student['status'] === 'inactive') ? 'archive' : 'check-circle') . ' me-1"></i>
                            ' . ((isset($student['status']) && $student['status'] === 'inactive') ? 'Archived' : 'Active') . '
                        </span>
                        <span class="badge bg-' . ((isset($student['is_irregular']) && $student['is_irregular']) ? 'danger' : 'success') . '">
                            ' . ((isset($student['is_irregular']) && $student['is_irregular']) ? 'Irregular' : 'Regular') . '
                        </span>
                    </div>
                </td>
                <td>
                    ' . (!empty($student['sections']) && count($student['sections']) > 0 ? 
                        '<span class="badge bg-light text-dark">' . htmlspecialchars(implode(', ', array_map(function($s) { return "Year {$s['year_level']} - " . htmlspecialchars($s['section_name']); }, $student['sections']))) . '</span>' : 
                        '<span class="text-muted small">No sections</span>') . '
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-envelope text-muted me-2"></i>
                        <span class="small">' . htmlspecialchars($student['email']) . '</span>
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-calendar3 text-muted me-2"></i>
                        <span class="small">' . date('M j, Y', strtotime($student['created_at'])) . '</span>
                    </div>
                </td>
                <td>
                    <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-sm btn-info text-white" 
                                onclick="viewStudent(' . $student['id'] . ')"
                                title="View Student Details">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" 
                                onclick="editStudent(' . $student['id'] . ')"
                                title="Edit Student">
                            <i class="bi bi-pencil"></i>
                        </button>
                        ' . (isset($student['status']) && $student['status'] === 'inactive' ? 
                            '<button class="btn btn-sm btn-success" 
                                    onclick="unarchiveStudent(' . $student['id'] . ')"
                                    title="Unarchive Student">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>' : 
                            '<button class="btn btn-sm btn-danger" 
                                    onclick="archiveStudent(' . $student['id'] . ')"
                                    title="Archive Student">
                                <i class="bi bi-archive"></i>
                            </button>') . '
                    </div>
                </td>
            </tr>';
    }
    
    $students_html .= '
            </tbody>
        </table>
    </div>';
}

// Return JSON response
echo json_encode([
    'success' => true,
    'students_html' => $students_html,
    'total_students' => $total_students,
    'stats' => [
        'total_students' => $total_students,
        'active_students' => $active_students,
        'inactive_students' => $inactive_students,
        'total_regular' => $total_regular,
        'total_irregular' => $total_irregular,
        'students_with_sections' => $students_with_sections
    ]
]);
?>
