<?php
$page_title = 'Export Student Statistics';
require_once '../config/config.php';
requireRole('teacher');

// Get filters from URL parameters
$academic_period_id = (int)($_GET['academic_period_id'] ?? 0);
$course_filter = (int)($_GET['course'] ?? 0);
$section_filter = (int)($_GET['section'] ?? 0);
$search_filter = sanitizeInput($_GET['search'] ?? '');
$sort_by = sanitizeInput($_GET['sort'] ?? 'name');
$show_enrolled_only = isset($_GET['enrolled_only']) && $_GET['enrolled_only'] === '1';
$detailed_export = isset($_GET['detailed']) && $_GET['detailed'] === '1';

// Validate academic period
if (!$academic_period_id) {
    die('Invalid academic period selected.');
}

// Verify teacher has access to this academic period
$stmt = $db->prepare('SELECT id FROM academic_periods WHERE id = ?');
$stmt->execute([$academic_period_id]);
if (!$stmt->fetch()) {
    die('Academic period not found.');
}

// Get teacher's courses for selected academic period
if ($section_filter > 0) {
    $stmt = $db->prepare('
        SELECT DISTINCT c.id, c.course_name, c.course_code 
        FROM courses c 
        WHERE c.teacher_id = ? 
        AND c.academic_period_id = ? 
        AND JSON_SEARCH(c.sections, "one", ?) IS NOT NULL
        ORDER BY c.course_name
    ');
    $stmt->execute([$_SESSION['user_id'], $academic_period_id, $section_filter]);
} else {
    $stmt = $db->prepare('SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? AND academic_period_id = ? ORDER BY course_name');
    $stmt->execute([$_SESSION['user_id'], $academic_period_id]);
}
$courses = $stmt->fetchAll();

// Get sections that are assigned to teacher's courses for selected academic period
if ($course_filter > 0) {
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.section_name, s.year_level
        FROM sections s
        JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        WHERE s.is_active = 1 
        AND c.teacher_id = ? 
        AND c.academic_period_id = ?
        AND c.id = ?
        ORDER BY s.year_level, s.section_name
    ");
    $stmt->execute([$_SESSION['user_id'], $academic_period_id, $course_filter]);
} else {
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.section_name, s.year_level
        FROM sections s
        JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        WHERE s.is_active = 1 
        AND c.teacher_id = ? 
        AND c.academic_period_id = ?
        ORDER BY s.year_level, s.section_name
    ");
    $stmt->execute([$_SESSION['user_id'], $academic_period_id]);
}
$sections = $stmt->fetchAll();

// Build where conditions for filtering
$where_conditions = [];
$params = [];

if ($course_filter > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

if ($section_filter > 0) {
    $where_conditions[] = "s.id = ?";
    $params[] = $section_filter;
}

if (!empty($search_filter)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.identifier LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_term = "%{$search_filter}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : '';
$having_clause = $show_enrolled_only ? "HAVING COUNT(DISTINCT e.id) > 0" : "";

// Get sort clause
function getSortClause($sort_by) {
    switch ($sort_by) {
        case 'name':
            return 'u.last_name ASC, u.first_name ASC';
        case 'course':
            return 'c.course_name ASC';
        case 'enrolled':
            return 'e.enrolled_at DESC';
        case 'progress':
            return 'e.progress_percentage DESC';
        case 'score':
            return 'assessment_stats.avg_score DESC';
        case 'activity':
            return 'e.last_accessed DESC';
        default:
            return 'u.last_name ASC, u.first_name ASC';
    }
}

// Get students data with the same query as students.php
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
    $stmt->execute([$course_filter, $_SESSION['user_id'], $academic_period_id, $course_filter]);
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

// Get academic period info for filename
$stmt = $db->prepare('SELECT academic_year, semester_name FROM academic_periods WHERE id = ?');
$stmt->execute([$academic_period_id]);
$academic_period = $stmt->fetch();

// Generate filename based on filters
$filename_parts = ['Student_Statistics'];
$filename_parts[] = $academic_period['academic_year'] . '_' . $academic_period['semester_name'];

if ($course_filter > 0) {
    $course_name = $courses[0]['course_name'] ?? 'Unknown';
    $filename_parts[] = str_replace(' ', '_', $course_name);
}

if ($section_filter > 0) {
    $section_name = $sections[0]['section_name'] ?? 'Unknown';
    $year_level = $sections[0]['year_level'] ?? '';
    $filename_parts[] = 'BSIT_' . $year_level . $section_name;
}

if ($show_enrolled_only) {
    $filename_parts[] = 'Enrolled_Only';
}

$filename = implode('_', $filename_parts) . '_' . date('Y-m-d_H-i-s') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV headers
$headers = [
    'Student ID',
    'First Name',
    'Last Name',
    'Email',
    'NEUST Student ID',
    'Sections',
    'Year Levels',
    'Total Courses',
    'Enrolled Courses',
    'Enrollment Status',
    'Average Progress (%)',
    'Last Activity',
    'Total Assessments',
    'Completed Assessments',
    'Assessment Completion Rate (%)',
    'Average Score (%)',
    'Best Score (%)',
    'Total Attempts',
    'Account Created',
    'Assessment Details'
];

fputcsv($output, $headers);

// Write student data
foreach ($students as $student) {
    $assessment_completion_rate = 0;
    if ($student['total_assessments'] > 0) {
        $assessment_completion_rate = ($student['completed_assessments'] / $student['total_assessments']) * 100;
    }
    
    $last_activity = 'Never';
    if ($student['last_activity']) {
        $last_activity = date('Y-m-d H:i:s', strtotime($student['last_activity']));
    }
    
    // Get detailed assessment data for this student
    $assessment_details = getStudentAssessmentDetails($student['student_id'], $course_filter, $academic_period_id, $_SESSION['user_id'], $db);
    
    $row = [
        $student['student_id'],
        $student['first_name'],
        $student['last_name'],
        $student['email'],
        $student['neust_student_id'] ?: 'N/A',
        $student['section_names'] ?: 'Not Assigned',
        $student['section_years'] ?: 'N/A',
        $student['total_courses'],
        $student['enrolled_courses'],
        $student['student_status'],
        number_format($student['avg_progress'] ?? 0, 2),
        $last_activity,
        $student['total_assessments'],
        $student['completed_assessments'],
        number_format($assessment_completion_rate, 2),
        number_format($student['avg_score'] ?? 0, 2),
        number_format($student['best_score'] ?? 0, 2),
        $student['total_attempts'],
        date('Y-m-d H:i:s', strtotime($student['user_created'])),
        $assessment_details
    ];
    
    fputcsv($output, $row);
}

// Add summary statistics at the end
fputcsv($output, []); // Empty row
fputcsv($output, ['SUMMARY STATISTICS']);
fputcsv($output, ['Metric', 'Value']);

$total_students = count($students);
$enrolled_students = array_filter($students, function($s) { return $s['enrolled_courses'] > 0; });
$avg_progress = $total_students > 0 ? array_sum(array_column($students, 'avg_progress')) / $total_students : 0;
$avg_score = $total_students > 0 ? array_sum(array_column($students, 'avg_score')) / $total_students : 0;

// Calculate additional statistics
$total_assessments = array_sum(array_column($students, 'total_assessments'));
$completed_assessments = array_sum(array_column($students, 'completed_assessments'));
$total_attempts = array_sum(array_column($students, 'total_attempts'));

// Calculate progress distribution
$excellent_progress = count(array_filter($students, function($s) { return ($s['avg_progress'] ?? 0) >= 80; }));
$good_progress = count(array_filter($students, function($s) { $p = $s['avg_progress'] ?? 0; return $p >= 60 && $p < 80; }));
$fair_progress = count(array_filter($students, function($s) { $p = $s['avg_progress'] ?? 0; return $p >= 40 && $p < 60; }));
$poor_progress = count(array_filter($students, function($s) { return ($s['avg_progress'] ?? 0) < 40; }));

// Calculate score distribution
$excellent_scores = count(array_filter($students, function($s) { return ($s['avg_score'] ?? 0) >= 80; }));
$good_scores = count(array_filter($students, function($s) { $s = $s['avg_score'] ?? 0; return $s >= 70 && $s < 80; }));
$fair_scores = count(array_filter($students, function($s) { $s = $s['avg_score'] ?? 0; return $s >= 50 && $s < 70; }));
$poor_scores = count(array_filter($students, function($s) { return ($s['avg_score'] ?? 0) < 50; }));

fputcsv($output, ['Total Students', $total_students]);
fputcsv($output, ['Enrolled Students', count($enrolled_students)]);
fputcsv($output, ['Not Enrolled Students', $total_students - count($enrolled_students)]);
fputcsv($output, ['Enrollment Rate (%)', $total_students > 0 ? number_format((count($enrolled_students) / $total_students) * 100, 2) : '0.00']);
fputcsv($output, ['Average Progress (%)', number_format($avg_progress, 2)]);
fputcsv($output, ['Average Score (%)', number_format($avg_score, 2)]);
fputcsv($output, ['Total Assessments', $total_assessments]);
fputcsv($output, ['Completed Assessments', $completed_assessments]);
fputcsv($output, ['Assessment Completion Rate (%)', $total_assessments > 0 ? number_format(($completed_assessments / $total_assessments) * 100, 2) : '0.00']);
fputcsv($output, ['Total Attempts', $total_attempts]);
fputcsv($output, ['Average Attempts per Student', $total_students > 0 ? number_format($total_attempts / $total_students, 2) : '0.00']);

fputcsv($output, []);
fputcsv($output, ['PROGRESS DISTRIBUTION']);
fputcsv($output, ['Excellent (80%+)', $excellent_progress]);
fputcsv($output, ['Good (60-79%)', $good_progress]);
fputcsv($output, ['Fair (40-59%)', $fair_progress]);
fputcsv($output, ['Poor (<40%)', $poor_progress]);

fputcsv($output, []);
fputcsv($output, ['SCORE DISTRIBUTION']);
fputcsv($output, ['Excellent (80%+)', $excellent_scores]);
fputcsv($output, ['Good (70-79%)', $good_scores]);
fputcsv($output, ['Fair (50-69%)', $fair_scores]);
fputcsv($output, ['Poor (<50%)', $poor_scores]);

// Add course-specific statistics if filtering by course
if ($course_filter > 0 && !empty($courses)) {
    $course = $courses[0];
    fputcsv($output, []);
    fputcsv($output, ['COURSE-SPECIFIC STATISTICS']);
    fputcsv($output, ['Course Name', $course['course_name']]);
    fputcsv($output, ['Course Code', $course['course_code']]);
    
    // Get course-specific assessment data
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT a.id) as total_assessments_in_course,
            COUNT(DISTINCT aa.id) as total_attempts_in_course,
            AVG(aa.score) as avg_score_in_course,
            MAX(aa.score) as best_score_in_course,
            MIN(aa.score) as worst_score_in_course
        FROM assessments a
        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id
        WHERE a.course_id = ?
    ");
    $stmt->execute([$course_filter]);
    $course_stats = $stmt->fetch();
    
    fputcsv($output, ['Total Assessments in Course', $course_stats['total_assessments_in_course']]);
    fputcsv($output, ['Total Attempts in Course', $course_stats['total_attempts_in_course']]);
    fputcsv($output, ['Average Score in Course (%)', number_format($course_stats['avg_score_in_course'] ?? 0, 2)]);
    fputcsv($output, ['Best Score in Course (%)', number_format($course_stats['best_score_in_course'] ?? 0, 2)]);
    fputcsv($output, ['Worst Score in Course (%)', number_format($course_stats['worst_score_in_course'] ?? 0, 2)]);
}

// Add section-specific statistics if filtering by section
if ($section_filter > 0 && !empty($sections)) {
    $section = $sections[0];
    fputcsv($output, []);
    fputcsv($output, ['SECTION-SPECIFIC STATISTICS']);
    fputcsv($output, ['Section Name', 'BSIT-' . $section['year_level'] . $section['section_name']]);
    fputcsv($output, ['Year Level', $section['year_level']]);
    
    // Count students by enrollment status in this section
    $enrolled_in_section = count(array_filter($students, function($s) { 
        return $s['enrolled_courses'] > 0; 
    }));
    fputcsv($output, ['Students in Section', $total_students]);
    fputcsv($output, ['Enrolled Students in Section', $enrolled_in_section]);
    fputcsv($output, ['Section Enrollment Rate (%)', $total_students > 0 ? number_format(($enrolled_in_section / $total_students) * 100, 2) : '0.00']);
}

// Add detailed assessment breakdown section only if detailed export is requested
if ($detailed_export) {
    fputcsv($output, []);
    fputcsv($output, ['STUDENT ASSESSMENT SCORES']);
    fputcsv($output, []); // Empty row for spacing
    
    // Get all assessments for the filtered students
    $assessments = getAllAssessmentsWithScores($students, $course_filter, $academic_period_id, $_SESSION['user_id'], $db);
    
    // Simple format: Student Name, Course Name, Assessment Title, Student Score
    fputcsv($output, ['Student Name', 'Course Name', 'Assessment Title', 'Student Score']);
    fputcsv($output, []); // Empty row for spacing
    
    foreach ($assessments as $assessment) {
        foreach ($assessment['student_scores'] as $score) {
            fputcsv($output, [
                $score['student_name'],
                $assessment['course_code'] . ' - ' . $assessment['course_name'],
                $assessment['assessment_title'],
                $score['score']
            ]);
        }
    }
}

// Add filter information
fputcsv($output, []);
fputcsv($output, ['FILTER INFORMATION']);
fputcsv($output, ['Academic Period', $academic_period['academic_year'] . ' - ' . $academic_period['semester_name']]);

if ($course_filter > 0) {
    $course_name = $courses[0]['course_name'] ?? 'Unknown';
    fputcsv($output, ['Course Filter', $course_name]);
}

if ($section_filter > 0) {
    $section_name = $sections[0]['section_name'] ?? 'Unknown';
    $year_level = $sections[0]['year_level'] ?? '';
    fputcsv($output, ['Section Filter', 'BSIT-' . $year_level . $section_name]);
}

if (!empty($search_filter)) {
    fputcsv($output, ['Search Filter', $search_filter]);
}

if ($show_enrolled_only) {
    fputcsv($output, ['View Filter', 'Enrolled Only']);
}

fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($output, ['Exported By', ($_SESSION['first_name'] ?? 'Unknown') . ' ' . ($_SESSION['last_name'] ?? 'User')]);

fclose($output);
exit;

// Function to get all assessments with student scores grouped by assessment
function getAllAssessmentsWithScores($students, $course_filter, $academic_period_id, $teacher_id, $db) {
    try {
        $student_ids = array_column($students, 'student_id');
        if (empty($student_ids)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
        
        if ($course_filter > 0) {
            // Get assessments for specific course
            $stmt = $db->prepare("
                SELECT 
                    a.id as assessment_id,
                    a.assessment_title,
                    a.course_id,
                    c.course_name,
                    c.course_code,
                    a.passing_rate,
                    a.difficulty,
                    a.time_limit,
                    aa.student_id,
                    u.first_name,
                    u.last_name,
                    aa.score,
                    aa.completed_at,
                    aa.time_taken,
                    CASE 
                        WHEN aa.score >= a.passing_rate THEN 'PASSED'
                        ELSE 'FAILED'
                    END as status
                FROM assessments a
                JOIN courses c ON a.course_id = c.id
                LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id IN ($placeholders)
                LEFT JOIN users u ON aa.student_id = u.id
                WHERE a.course_id = ?
                AND c.teacher_id = ?
                AND c.academic_period_id = ?
                ORDER BY a.assessment_title, aa.completed_at DESC
            ");
            $stmt->execute(array_merge($student_ids, [$course_filter, $teacher_id, $academic_period_id]));
        } else {
            // Get assessments for all teacher's courses
            $stmt = $db->prepare("
                SELECT 
                    a.id as assessment_id,
                    a.assessment_title,
                    a.course_id,
                    c.course_name,
                    c.course_code,
                    a.passing_rate,
                    a.difficulty,
                    a.time_limit,
                    aa.student_id,
                    u.first_name,
                    u.last_name,
                    aa.score,
                    aa.completed_at,
                    aa.time_taken,
                    CASE 
                        WHEN aa.score >= a.passing_rate THEN 'PASSED'
                        ELSE 'FAILED'
                    END as status
                FROM assessments a
                JOIN courses c ON a.course_id = c.id
                LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id IN ($placeholders)
                LEFT JOIN users u ON aa.student_id = u.id
                WHERE c.teacher_id = ?
                AND c.academic_period_id = ?
                ORDER BY a.assessment_title, aa.completed_at DESC
            ");
            $stmt->execute(array_merge($student_ids, [$teacher_id, $academic_period_id]));
        }
        
        $results = $stmt->fetchAll();
        
        // Group by assessment
        $assessments = [];
        foreach ($results as $row) {
            $assessment_id = $row['assessment_id'];
            
            if (!isset($assessments[$assessment_id])) {
                $assessments[$assessment_id] = [
                    'assessment_title' => $row['assessment_title'],
                    'course_name' => $row['course_name'],
                    'course_code' => $row['course_code'],
                    'passing_rate' => $row['passing_rate'],
                    'difficulty' => $row['difficulty'],
                    'time_limit' => $row['time_limit'],
                    'student_scores' => [],
                    'total_attempts' => 0,
                    'passed_count' => 0,
                    'failed_count' => 0,
                    'scores' => []
                ];
            }
            
            // Add student score if exists
            if ($row['student_id']) {
                $time_taken = $row['time_taken'] ? formatTime($row['time_taken']) : 'N/A';
                $completed_date = $row['completed_at'] ? date('Y-m-d H:i:s', strtotime($row['completed_at'])) : 'N/A';
                
                $assessments[$assessment_id]['student_scores'][] = [
                    'student_id' => $row['student_id'],
                    'student_name' => $row['first_name'] . ' ' . $row['last_name'],
                    'score' => $row['score'],
                    'status' => $row['status'],
                    'completed_date' => $completed_date,
                    'time_taken' => $time_taken
                ];
                
                $assessments[$assessment_id]['total_attempts']++;
                $assessments[$assessment_id]['scores'][] = $row['score'];
                
                if ($row['status'] === 'PASSED') {
                    $assessments[$assessment_id]['passed_count']++;
                } else {
                    $assessments[$assessment_id]['failed_count']++;
                }
            }
        }
        
        // Calculate statistics for each assessment
        foreach ($assessments as &$assessment) {
            if (!empty($assessment['scores'])) {
                $assessment['average_score'] = round(array_sum($assessment['scores']) / count($assessment['scores']), 2);
                $assessment['highest_score'] = max($assessment['scores']);
                $assessment['lowest_score'] = min($assessment['scores']);
            } else {
                $assessment['average_score'] = 0;
                $assessment['highest_score'] = 0;
                $assessment['lowest_score'] = 0;
            }
        }
        
        return array_values($assessments);
        
    } catch (Exception $e) {
        error_log("Error getting assessments with scores: " . $e->getMessage());
        return [];
    }
}

// Function to get detailed assessment data in structured format
function getDetailedAssessmentData($student_id, $course_filter, $academic_period_id, $teacher_id, $db) {
    try {
        if ($course_filter > 0) {
            // Get assessments for specific course
            $stmt = $db->prepare("
                SELECT 
                    a.assessment_title,
                    a.course_id,
                    c.course_name,
                    c.course_code,
                    aa.score,
                    aa.completed_at,
                    aa.time_taken,
                    a.passing_rate,
                    a.difficulty,
                    a.time_limit,
                    CASE 
                        WHEN aa.score >= a.passing_rate THEN 'PASSED'
                        ELSE 'FAILED'
                    END as status
                FROM assessment_attempts aa
                JOIN assessments a ON aa.assessment_id = a.id
                JOIN courses c ON a.course_id = c.id
                WHERE aa.student_id = ? 
                AND a.course_id = ?
                AND c.teacher_id = ?
                AND c.academic_period_id = ?
                ORDER BY aa.completed_at DESC
            ");
            $stmt->execute([$student_id, $course_filter, $teacher_id, $academic_period_id]);
        } else {
            // Get assessments for all teacher's courses
            $stmt = $db->prepare("
                SELECT 
                    a.assessment_title,
                    a.course_id,
                    c.course_name,
                    c.course_code,
                    aa.score,
                    aa.completed_at,
                    aa.time_taken,
                    a.passing_rate,
                    a.difficulty,
                    a.time_limit,
                    CASE 
                        WHEN aa.score >= a.passing_rate THEN 'PASSED'
                        ELSE 'FAILED'
                    END as status
                FROM assessment_attempts aa
                JOIN assessments a ON aa.assessment_id = a.id
                JOIN courses c ON a.course_id = c.id
                WHERE aa.student_id = ? 
                AND c.teacher_id = ?
                AND c.academic_period_id = ?
                ORDER BY aa.completed_at DESC
            ");
            $stmt->execute([$student_id, $teacher_id, $academic_period_id]);
        }
        
        $assessments = $stmt->fetchAll();
        
        // Format the data for CSV export
        $formatted_assessments = [];
        foreach ($assessments as $assessment) {
            $time_taken = $assessment['time_taken'] ? formatTime($assessment['time_taken']) : 'N/A';
            $completed_date = $assessment['completed_at'] ? date('Y-m-d H:i:s', strtotime($assessment['completed_at'])) : 'N/A';
            
            $formatted_assessments[] = [
                'assessment_title' => $assessment['assessment_title'],
                'course_name' => $assessment['course_name'],
                'course_code' => $assessment['course_code'],
                'score' => $assessment['score'],
                'status' => $assessment['status'],
                'completed_date' => $completed_date,
                'time_taken' => $time_taken,
                'difficulty' => $assessment['difficulty'],
                'passing_rate' => $assessment['passing_rate']
            ];
        }
        
        return $formatted_assessments;
        
    } catch (Exception $e) {
        error_log("Error getting detailed assessment data for student $student_id: " . $e->getMessage());
        return [];
    }
}

// Function to get detailed assessment data for a student (for summary column)
function getStudentAssessmentDetails($student_id, $course_filter, $academic_period_id, $teacher_id, $db) {
    try {
        if ($course_filter > 0) {
            // Get assessments for specific course
            $stmt = $db->prepare("
                SELECT 
                    a.assessment_title,
                    a.course_id,
                    c.course_name,
                    c.course_code,
                    aa.score,
                    aa.completed_at,
                    aa.time_taken,
                    a.passing_rate,
                    a.difficulty,
                    a.time_limit,
                    CASE 
                        WHEN aa.score >= a.passing_rate THEN 'PASSED'
                        ELSE 'FAILED'
                    END as status
                FROM assessment_attempts aa
                JOIN assessments a ON aa.assessment_id = a.id
                JOIN courses c ON a.course_id = c.id
                WHERE aa.student_id = ? 
                AND a.course_id = ?
                AND c.teacher_id = ?
                AND c.academic_period_id = ?
                ORDER BY aa.completed_at DESC
            ");
            $stmt->execute([$student_id, $course_filter, $teacher_id, $academic_period_id]);
        } else {
            // Get assessments for all teacher's courses
            $stmt = $db->prepare("
                SELECT 
                    a.assessment_title,
                    a.course_id,
                    c.course_name,
                    c.course_code,
                    aa.score,
                    aa.completed_at,
                    aa.time_taken,
                    a.passing_rate,
                    a.difficulty,
                    a.time_limit,
                    CASE 
                        WHEN aa.score >= a.passing_rate THEN 'PASSED'
                        ELSE 'FAILED'
                    END as status
                FROM assessment_attempts aa
                JOIN assessments a ON aa.assessment_id = a.id
                JOIN courses c ON a.course_id = c.id
                WHERE aa.student_id = ? 
                AND c.teacher_id = ?
                AND c.academic_period_id = ?
                ORDER BY aa.completed_at DESC
            ");
            $stmt->execute([$student_id, $teacher_id, $academic_period_id]);
        }
        
        $assessments = $stmt->fetchAll();
        
        if (empty($assessments)) {
            return 'No assessment attempts found';
        }
        
        $details = [];
        foreach ($assessments as $assessment) {
            $time_taken = $assessment['time_taken'] ? formatTime($assessment['time_taken']) : 'N/A';
            $completed_date = $assessment['completed_at'] ? date('Y-m-d H:i:s', strtotime($assessment['completed_at'])) : 'N/A';
            
            $details[] = sprintf(
                "%s (%s) - Score: %s%% - Status: %s - Date: %s - Time: %s - Difficulty: %s - Passing: %s%%",
                $assessment['assessment_title'],
                $assessment['course_code'],
                $assessment['score'],
                $assessment['status'],
                $completed_date,
                $time_taken,
                strtoupper($assessment['difficulty']),
                $assessment['passing_rate']
            );
        }
        
        return implode(' | ', $details);
        
    } catch (Exception $e) {
        error_log("Error getting assessment details for student $student_id: " . $e->getMessage());
        return 'Error loading assessment details';
    }
}

// Helper function to format time
function formatTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    } else {
        return sprintf('%d:%02d', $minutes, $secs);
    }
}
?>
