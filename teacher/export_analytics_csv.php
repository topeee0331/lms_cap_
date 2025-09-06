<?php
// Start output buffering immediately
ob_start();

session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$course_id = (int)($_GET['course_id'] ?? 0);
$export_type = $_GET['type'] ?? 'overview';
$section_id = (int)($_GET['section_id'] ?? 0);

if (!$course_id) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid course ID']);
    exit;
}

// Verify teacher owns this course
$stmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();
if (!$course) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

function exportOverviewCSV($course_id, $course) {
    global $db;
    
    // Get course data
    $section_stmt = $db->prepare("SELECT s.id, s.section_name as name, s.year_level as year FROM course_sections cs INNER JOIN sections s ON cs.section_id = s.id WHERE cs.course_id = ?");
    $section_stmt->execute([$course_id]);
    $sections = $section_stmt->fetchAll();
    
    if (empty($sections)) {
        $sections = [];
    }
    
    // Get all students and their assessments
    $stmt = $db->prepare("SELECT a.id, a.assessment_title FROM assessments a JOIN course_modules cm ON a.module_id = cm.id WHERE cm.course_id = ? ORDER BY a.assessment_title");
    $stmt->execute([$course_id]);
    $assessments = $stmt->fetchAll();
    
    // Get all students from all sections
    $all_students = [];
    foreach ($sections as $section) {
        $stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.identifier, s.section_name, s.year_level FROM sections s JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL WHERE s.id = ? ORDER BY u.last_name, u.first_name");
        $stmt->execute([$section['id']]);
        $section_students = $stmt->fetchAll();
        $all_students = array_merge($all_students, $section_students);
    }
    
    $csv_data = [];
    
    // Course Information
    $csv_data[] = ['Course Analytics Overview'];
    $csv_data[] = [];
    $csv_data[] = ['Course Name:', $course['course_name']];
    $csv_data[] = ['Course Code:', $course['course_code']];
    $csv_data[] = ['Export Date:', date('Y-m-d H:i:s')];
    $csv_data[] = [];
    
    // Student Assessment Table Header
    $headers = ['Students name'];
    foreach ($assessments as $assessment) {
        $headers[] = $assessment['assessment_title'];
    }
    $headers[] = 'Total Score';
    $csv_data[] = $headers;
    
    // Student data with scores
    foreach ($all_students as $student) {
        $row = [$student['last_name'] . ', ' . $student['first_name']];
        
        $scores = [];
        foreach ($assessments as $assessment) {
            $stmt = $db->prepare("SELECT MAX(score) as score FROM assessment_attempts WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$student['id'], $assessment['id']]);
            $score = $stmt->fetchColumn();
            
            if ($score !== null) {
                $scores[] = $score;
                $row[] = $score;
            } else {
                $row[] = 'N/A';
            }
        }
        
        // Calculate total score
        $valid_scores = array_filter($scores, function($score) { return $score !== null && $score !== 'N/A'; });
        if (!empty($valid_scores)) {
            $total_score = array_sum($valid_scores);
            $row[] = $total_score;
        } else {
            $row[] = 'N/A';
        }
        
        $csv_data[] = $row;
    }
    
    $csv_data[] = [];
    $csv_data[] = [];
    
    // Summary Statistics
    $csv_data[] = ['Summary Statistics'];
    $csv_data[] = ['Metric', 'Value', 'Description'];
    
    $total_students = count($all_students);
    // Get module count from JSON
    $stmt = $db->prepare("SELECT JSON_LENGTH(COALESCE(modules, '[]')) as module_count FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $total_modules = $stmt->fetchColumn();
    
    // Get assessment count directly from assessments table
    $stmt = $db->prepare("SELECT COUNT(*) FROM assessments WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $total_assessments = $stmt->fetchColumn();
    
    $csv_data[] = ['Total Students', $total_students, 'Number of enrolled students'];
    $csv_data[] = ['Total Modules', $total_modules, 'Number of course modules'];
    $csv_data[] = ['Total Assessments', $total_assessments, 'Number of assessments'];
    $csv_data[] = [];
    
    // Section Information
    $csv_data[] = ['Section Information'];
    $csv_data[] = ['Section', 'Year', 'Student Count'];
    
    foreach ($sections as $section) {
        $stmt = $db->prepare("SELECT JSON_LENGTH(COALESCE(students, '[]')) as student_count FROM sections WHERE id = ?");
        $stmt->execute([$section['id']]);
        $studentCount = $stmt->fetchColumn();
        $csv_data[] = ['BSIT-' . $section['year_level'] . $section['section_name'], $section['year_level'], $studentCount];
    }
    
    return $csv_data;
}

function exportSectionDataCSV($course_id, $section_id, $course) {
    global $db;
    
    // Get section info
    // Get course sections from JSON
    $stmt = $db->prepare("SELECT sections FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course_sections = $stmt->fetchColumn();
    
    // Check if section exists in course sections
    $stmt = $db->prepare("SELECT s.* FROM sections s WHERE s.id = ? AND JSON_SEARCH(?, 'one', s.id) IS NOT NULL");
    $stmt->execute([$section_id, $course_sections]);
    $section = $stmt->fetch();
    
    if (!$section) {
        throw new Exception('Section not found');
    }
    
    // Get students and assessments
    $stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.identifier 
                      FROM sections s 
                      JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
                      WHERE s.id = ? 
                      ORDER BY u.last_name, u.first_name");
    $stmt->execute([$section_id]);
    $students = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT a.id, a.assessment_title, a.passing_rate FROM assessments a JOIN course_modules cm ON a.module_id = cm.id WHERE cm.course_id = ? ORDER BY a.assessment_title");
    $stmt->execute([$course_id]);
    $assessments = $stmt->fetchAll();
    
    $csv_data = [];
    
    // Header
    $csv_data[] = ['Student Assessment Scores'];
    $csv_data[] = [];
    $csv_data[] = ['Course:', $course['course_name']];
    $csv_data[] = ['Section:', 'BSIT-' . $section['year'] . $section['name']];
    $csv_data[] = ['Export Date:', date('Y-m-d H:i:s')];
    $csv_data[] = [];
    
    // Assessment headers
    $headers = ['Student Name', 'Student ID'];
    foreach ($assessments as $assessment) {
        $headers[] = $assessment['assessment_title'];
    }
    $headers[] = 'Total Score';
    $headers[] = 'Average Score';
    $headers[] = 'Status';
    $csv_data[] = $headers;
    
    // Student data
    foreach ($students as $student) {
        $row = [$student['last_name'] . ', ' . $student['first_name'], $student['identifier']];
        
        $scores = [];
        foreach ($assessments as $assessment) {
            $stmt = $db->prepare("SELECT MAX(score) as score FROM assessment_attempts WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$student['id'], $assessment['id']]);
            $score = $stmt->fetchColumn();
            
            if ($score !== null) {
                $scores[] = $score;
                $row[] = $score;
            } else {
                $row[] = 'N/A';
            }
        }
        
        // Calculate total score
        $valid_scores = array_filter($scores, function($score) { return $score !== null && $score !== 'N/A'; });
        if (!empty($valid_scores)) {
            $total_score = array_sum($valid_scores);
            $row[] = $total_score;
        } else {
            $row[] = 'N/A';
        }
        
        // Calculate average
        if (!empty($scores)) {
            $average = round(array_sum($scores) / count($scores), 1);
            $row[] = $average;
            
            // Status
            $status = $average >= 70 ? 'Passing' : 'Needs Improvement';
            $row[] = $status;
        } else {
            $row[] = 'N/A';
            $row[] = 'No Data';
        }
        
        $csv_data[] = $row;
    }
    
    return $csv_data;
}

function exportAssessmentsCSV($course_id, $course) {
    global $db;
    
    // Get assessment data
    $stmt = $db->prepare("
        SELECT 
            a.assessment_title,
            a.passing_rate,
            cm.module_title,
            AVG(aa.score) as avg_score,
            COUNT(aa.id) as total_attempts,
            MIN(aa.score) as min_score,
            MAX(aa.score) as max_score,
            COUNT(CASE WHEN aa.score >= a.passing_rate THEN 1 END) as passed_attempts
        FROM assessments a 
        JOIN course_modules cm ON a.module_id = cm.id 
        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id
        WHERE cm.course_id = ?
        GROUP BY a.id, a.assessment_title, a.passing_rate, cm.module_title
        ORDER BY cm.module_title, a.assessment_title
    ");
    $stmt->execute([$course_id]);
    $assessments = $stmt->fetchAll();
    
    $csv_data = [];
    
    // Header
    $csv_data[] = ['Assessment Performance Report'];
    $csv_data[] = [];
    $csv_data[] = ['Course:', $course['course_name']];
    $csv_data[] = ['Export Date:', date('Y-m-d H:i:s')];
    $csv_data[] = [];
    
    // Headers
    $headers = ['Module', 'Assessment', 'Passing Rate', 'Avg Score', 'Total Attempts', 'Min Score', 'Max Score', 'Pass Rate', 'Status'];
    $csv_data[] = $headers;
    
    // Data
    foreach ($assessments as $assessment) {
        $passRate = $assessment['total_attempts'] > 0 ? round(($assessment['passed_attempts'] / $assessment['total_attempts']) * 100, 1) : 0;
        $status = ($assessment['avg_score'] ?? 0) >= $assessment['passing_rate'] ? 'Passing' : 'Below Threshold';
        
        $csv_data[] = [
            $assessment['module_title'],
            $assessment['assessment_title'],
            $assessment['passing_rate'] . '%',
            round($assessment['avg_score'] ?? 0, 1) . '%',
            $assessment['total_attempts'],
            $assessment['min_score'] ?? 'N/A',
            $assessment['max_score'] ?? 'N/A',
            $passRate . '%',
            $status
        ];
    }
    
    return $csv_data;
}

function arrayToCSV($data) {
    $output = fopen('php://temp', 'r+');
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

try {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $csv_data = null;
    
    switch ($export_type) {
        case 'overview':
            $csv_data = exportOverviewCSV($course_id, $course);
            $filename = $course['course_name'] . '_Overview_' . date('Y-m-d') . '.csv';
            break;
            
        case 'section':
            if (!$section_id) {
                throw new Exception('Section ID required for section export');
            }
            $csv_data = exportSectionDataCSV($course_id, $section_id, $course);
            $filename = $course['course_name'] . '_Section_' . date('Y-m-d') . '.csv';
            break;
            
        case 'assessments':
            $csv_data = exportAssessmentsCSV($course_id, $course);
            $filename = $course['course_name'] . '_Assessments_' . date('Y-m-d') . '.csv';
            break;
            
        default:
            throw new Exception('Invalid export type');
    }
    
    // Convert to CSV
    $csv_content = arrayToCSV($csv_data);
    
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($csv_content));
    header('Cache-Control: max-age=0');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output CSV
    echo $csv_content;
    
} catch (Exception $e) {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
