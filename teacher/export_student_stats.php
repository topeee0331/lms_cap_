<?php
require_once '../config/config.php';
requireRole('teacher');

// Set error handling
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {

// Get parameters from URL
$academic_period_id = (int)($_GET['academic_period_id'] ?? 0);
$course_filter = (int)($_GET['course'] ?? 0);
$section_filter = (int)($_GET['section'] ?? 0);
$search_filter = trim($_GET['search'] ?? '');
$enrolled_only = (int)($_GET['enrolled_only'] ?? 0);
$sort_by = $_GET['sort'] ?? 'name';
$detailed = (int)($_GET['detailed'] ?? 0);

// Validate academic period
if (!$academic_period_id) {
    // Find the first active academic year
    $stmt = $db->prepare('SELECT id FROM academic_periods WHERE is_active = 1 ORDER BY academic_year DESC, semester_name LIMIT 1');
    $stmt->execute();
    $academic_period_id = $stmt->fetchColumn() ?? 0;
}

if (!$academic_period_id) {
    die('No academic period selected.');
}

// Build where conditions
$where_conditions = [];
$params = [];

// Course filter
if ($course_filter > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

// Section filter
if ($section_filter > 0) {
    $where_conditions[] = "s.id = ?";
    $params[] = $section_filter;
}

// Search filter
if (!empty($search_filter)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.identifier LIKE ?)";
    $search_term = "%{$search_filter}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$where_clause = implode(' AND ', $where_conditions);
$show_enrolled_only = $enrolled_only == 1;
$having_clause = $show_enrolled_only ? "HAVING COUNT(DISTINCT e.id) > 0" : "";

// Function to get sort clause
function getSortClause($sort_by) {
    switch ($sort_by) {
        case 'name':
            return 'u.first_name, u.last_name';
        case 'email':
            return 'u.email';
        case 'section':
            return 's.section_name';
        case 'enrollment':
            return 'enrolled_courses DESC';
        case 'progress':
            return 'avg_progress DESC';
        case 'score':
            return 'avg_score DESC';
        case 'activity':
            return 'last_activity DESC';
        default:
            return 'u.first_name, u.last_name';
    }
}

// Execute the same query as in students.php
if ($course_filter > 0) {
    $stmt = $db->prepare("
        SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at as user_created, u.identifier as neust_student_id,
               GROUP_CONCAT(DISTINCT s.section_name ORDER BY s.section_name SEPARATOR ', ') as section_names,
               GROUP_CONCAT(DISTINCT s.year_level ORDER BY s.year_level SEPARATOR ', ') as section_years,
               1 as total_courses,
               COUNT(DISTINCT e.id) as enrolled_courses,
               MAX(e.enrolled_at) as latest_enrollment,
               AVG(e.progress_percentage) as avg_progress,
               MAX(e.last_accessed) as last_activity,
               CASE 
                   WHEN COUNT(DISTINCT e.id) > 0 THEN 'Regular'
                   ELSE 'Irregular'
               END as student_status,
               
               -- Assessment Statistics for this specific course
               COALESCE(SUM(assessment_stats.total_assessments), 0) as total_assessments,
               COALESCE(SUM(assessment_stats.completed_assessments), 0) as completed_assessments,
               COALESCE(AVG(assessment_stats.avg_score), 0) as avg_score,
               COALESCE(MAX(assessment_stats.best_score), 0) as best_score,
               COALESCE(SUM(assessment_stats.total_attempts), 0) as total_attempts
               
        FROM sections s
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL 
            AND c.id = ? AND c.teacher_id = ? AND c.academic_period_id = ?
        LEFT JOIN course_enrollments e ON e.student_id = u.id AND e.course_id = c.id
        
        -- Assessment Statistics Subquery for this specific course
        LEFT JOIN (
            SELECT 
                aa.student_id,
                COUNT(DISTINCT aa.assessment_id) as total_assessments,
                COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.assessment_id END) as completed_assessments,
                ROUND(AVG(aa.score), 2) as avg_score,
                MAX(aa.score) as best_score,
                COUNT(*) as total_attempts
            FROM assessment_attempts aa
            WHERE aa.assessment_id IN (
                SELECT JSON_UNQUOTE(JSON_EXTRACT(c.modules, CONCAT('$[', numbers.n, ']')))
                FROM courses c
                CROSS JOIN (
                    SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
                ) numbers
                WHERE c.id = ? AND JSON_UNQUOTE(JSON_EXTRACT(c.modules, CONCAT('$[', numbers.n, ']'))) IS NOT NULL
            )
            GROUP BY aa.student_id
        ) assessment_stats ON assessment_stats.student_id = u.id
        
        WHERE s.is_active = 1
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at, u.identifier
        " . $having_clause . "
        ORDER BY " . getSortClause($sort_by) . "
    ");
    $stmt->execute(array_merge([$course_filter, $_SESSION['user_id'], $academic_period_id, $course_filter], $params));
} else {
    $stmt = $db->prepare("
        SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at as user_created, u.identifier as neust_student_id,
               GROUP_CONCAT(DISTINCT s.section_name ORDER BY s.section_name SEPARATOR ', ') as section_names,
               GROUP_CONCAT(DISTINCT s.year_level ORDER BY s.year_level SEPARATOR ', ') as section_years,
               COUNT(DISTINCT c.id) as total_courses,
               COUNT(DISTINCT e.id) as enrolled_courses,
               MAX(e.enrolled_at) as latest_enrollment,
               AVG(e.progress_percentage) as avg_progress,
               MAX(e.last_accessed) as last_activity,
               CASE 
                   WHEN COUNT(DISTINCT e.id) = COUNT(DISTINCT c.id) THEN 'Regular'
                   WHEN COUNT(DISTINCT e.id) > 0 THEN 'Irregular'
                   ELSE 'Irregular'
               END as student_status,
               
               -- Overall Assessment Statistics
               COALESCE(SUM(assessment_stats.total_assessments), 0) as total_assessments,
               COALESCE(SUM(assessment_stats.completed_assessments), 0) as completed_assessments,
               COALESCE(AVG(assessment_stats.avg_score), 0) as avg_score,
               COALESCE(MAX(assessment_stats.best_score), 0) as best_score,
               COALESCE(SUM(assessment_stats.total_attempts), 0) as total_attempts
               
        FROM sections s
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL 
            AND c.teacher_id = ? AND c.academic_period_id = ?
        LEFT JOIN course_enrollments e ON e.student_id = u.id AND e.course_id = c.id
        
        -- Assessment Statistics Subquery
        LEFT JOIN (
            SELECT 
                aa.student_id,
                c.id as course_id,
                COUNT(DISTINCT aa.assessment_id) as total_assessments,
                COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.assessment_id END) as completed_assessments,
                ROUND(AVG(aa.score), 2) as avg_score,
                MAX(aa.score) as best_score,
                COUNT(*) as total_attempts
            FROM assessment_attempts aa
            JOIN courses c ON JSON_SEARCH(c.modules, 'one', aa.assessment_id) IS NOT NULL
            WHERE c.teacher_id = ? AND c.academic_period_id = ?
            GROUP BY aa.student_id, c.id
        ) assessment_stats ON assessment_stats.student_id = u.id AND assessment_stats.course_id = c.id
        
        WHERE s.is_active = 1
        " . ($where_clause ? "AND " . $where_clause : "") . "
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at, u.identifier
        " . $having_clause . "
        ORDER BY " . getSortClause($sort_by) . "
    ");
    $stmt->execute(array_merge([$_SESSION['user_id'], $academic_period_id, $_SESSION['user_id'], $academic_period_id], $params));
}

$students = $stmt->fetchAll();

// Get course information if filtering by course
$course_info = null;
if ($course_filter > 0) {
    $stmt = $db->prepare('SELECT course_name, course_code FROM courses WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$course_filter, $_SESSION['user_id']]);
    $course_info = $stmt->fetch();
}

// Set headers for CSV download
$filename = $detailed ? 'detailed_assessment_data' : 'student_statistics';
if ($course_info) {
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $course_info['course_code']);
}
$filename .= '_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
$headers = [
    'Student ID',
    'First Name', 
    'Last Name',
    'Email',
    'NEUST ID',
    'Sections',
    'Year Levels',
    'Total Courses',
    'Enrolled Courses',
    'Enrollment Status',
    'Latest Enrollment',
    'Average Progress (%)',
    'Last Activity',
    'Total Assessments',
    'Completed Assessments',
    'Average Score (%)',
    'Best Score (%)',
    'Total Attempts'
];

// Add detailed assessment headers if requested
if ($detailed) {
    $headers = array_merge($headers, [
        'Assessment Details'
    ]);
}

fputcsv($output, $headers);

// Add course information row if filtering by course
if ($course_info) {
    fputcsv($output, ['COURSE:', $course_info['course_name'] . ' (' . $course_info['course_code'] . ')']);
    fputcsv($output, []); // Empty row
}

// Add filter information
$filter_info = [];
if ($academic_period_id) {
    $stmt = $db->prepare('SELECT academic_year, semester_name FROM academic_periods WHERE id = ?');
    $stmt->execute([$academic_period_id]);
    $period = $stmt->fetch();
    if ($period) {
        $filter_info[] = 'Academic Period: ' . $period['academic_year'] . ' - ' . $period['semester_name'];
    }
}
if ($section_filter > 0) {
    $stmt = $db->prepare('SELECT section_name FROM sections WHERE id = ?');
    $stmt->execute([$section_filter]);
    $section = $stmt->fetch();
    if ($section) {
        $filter_info[] = 'Section: ' . $section['section_name'];
    }
}
if (!empty($search_filter)) {
    $filter_info[] = 'Search: ' . $search_filter;
}
if ($enrolled_only) {
    $filter_info[] = 'Enrolled Only: Yes';
}

if (!empty($filter_info)) {
    fputcsv($output, ['FILTERS:', implode(', ', $filter_info)]);
    fputcsv($output, []); // Empty row
}

// Export timestamp
fputcsv($output, ['EXPORTED:', date('Y-m-d H:i:s')]);
fputcsv($output, []); // Empty row

// Data rows
foreach ($students as $student) {
    $row = [
        $student['student_id'],
        $student['first_name'],
        $student['last_name'],
        $student['email'],
        $student['neust_student_id'],
        $student['section_names'],
        $student['section_years'],
        $student['total_courses'],
        $student['enrolled_courses'],
        $student['student_status'],
        $student['latest_enrollment'] ? date('Y-m-d H:i:s', strtotime($student['latest_enrollment'])) : '',
        number_format($student['avg_progress'] ?? 0, 2),
        $student['last_activity'] ? date('Y-m-d H:i:s', strtotime($student['last_activity'])) : '',
        $student['total_assessments'],
        $student['completed_assessments'],
        number_format($student['avg_score'] ?? 0, 2),
        number_format($student['best_score'] ?? 0, 2),
        $student['total_attempts']
    ];
    
    // Add detailed assessment information if requested
    if ($detailed) {
        $assessment_details = '';
        if ($student['total_assessments'] > 0) {
            $completion_rate = ($student['total_assessments'] > 0) ? 
                ($student['completed_assessments'] / $student['total_assessments']) * 100 : 0;
            $assessment_details = sprintf(
                'Assessments: %d total, %d completed (%.1f%% completion rate), %.1f%% avg score, %.1f%% best score, %d attempts',
                $student['total_assessments'],
                $student['completed_assessments'],
                $completion_rate,
                $student['avg_score'] ?? 0,
                $student['best_score'] ?? 0,
                $student['total_attempts']
            );
        }
        $row[] = $assessment_details;
    }
    
    fputcsv($output, $row);
}

// Add summary statistics
fputcsv($output, []); // Empty row
fputcsv($output, ['SUMMARY STATISTICS:']);
fputcsv($output, ['Total Students:', count($students)]);

if (count($students) > 0) {
    $active_students = array_filter($students, function($s) { return ($s['enrolled_courses'] ?? 0) > 0; });
    $avg_progress = array_sum(array_column($students, 'avg_progress')) / count($students);
    $avg_score = array_sum(array_column($students, 'avg_score')) / count($students);
    $total_assessments = array_sum(array_column($students, 'total_assessments'));
    $total_completed = array_sum(array_column($students, 'completed_assessments'));
    
    fputcsv($output, ['Active Students:', count($active_students)]);
    fputcsv($output, ['Average Progress:', number_format($avg_progress, 2) . '%']);
    fputcsv($output, ['Average Score:', number_format($avg_score, 2) . '%']);
    fputcsv($output, ['Total Assessments:', $total_assessments]);
    fputcsv($output, ['Completed Assessments:', $total_completed]);
    if ($total_assessments > 0) {
        fputcsv($output, ['Completion Rate:', number_format(($total_completed / $total_assessments) * 100, 2) . '%']);
    }
}

fclose($output);
exit;

} catch (Exception $e) {
    // Log the error
    error_log("Export error: " . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    exit;
}
?>
