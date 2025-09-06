<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$course_id = (int)($_GET['course_id'] ?? 0);
$export_type = $_GET['type'] ?? 'overview'; // overview, section, assessments
$section_id = (int)($_GET['section_id'] ?? 0);

if (!$course_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid course ID']);
    exit;
}

// Verify teacher owns this course
$stmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();
if (!$course) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Include PhpSpreadsheet library
if (!file_exists('../vendor/autoload.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'PhpSpreadsheet library not found. Please run: composer install']);
    exit;
}
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

function createExcelFile($data, $filename) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('LMS System')
        ->setLastModifiedBy('LMS System')
        ->setTitle($filename)
        ->setSubject('Course Analytics Export')
        ->setDescription('Course analytics data exported from LMS system');
    
    return $spreadsheet;
}

function applyHeaderStyle($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '0D6EFD'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ]);
}

function applyDataStyle($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'font' => [
            'size' => 10,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC'],
            ],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
    ]);
}

function exportOverview($course_id, $course) {
    global $db;
    
    // Get course data
    $section_stmt = $db->prepare("SELECT s.id, s.section_name as name, s.year_level as year FROM course_sections cs INNER JOIN sections s ON cs.section_id = s.id WHERE cs.course_id = ?");
    $section_stmt->execute([$course_id]);
    $sections = $section_stmt->fetchAll();
    
    // Validate data
    if (empty($sections)) {
        $sections = []; // Ensure we have an empty array if no sections
    }
    
    $spreadsheet = createExcelFile([], $course['course_name'] . ' - Overview');
    
    // Create Summary Sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Course Overview');
    
    // Course Information
    $sheet->setCellValue('A1', 'Course Analytics Overview');
    $sheet->mergeCells('A1:D1');
    $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Course Name:');
    $sheet->setCellValue('B3', $course['course_name']);
    $sheet->setCellValue('A4', 'Course Code:');
    $sheet->setCellValue('B4', $course['course_code']);
    $sheet->setCellValue('A5', 'Export Date:');
    $sheet->setCellValue('B5', date('Y-m-d H:i:s'));
    
    // Summary Statistics
    $sheet->setCellValue('A7', 'Summary Statistics');
    $sheet->mergeCells('A7:D7');
    $sheet->getStyle('A7')->getFont()->setSize(14)->setBold(true);
    
    $headers = ['Metric', 'Value', 'Description'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '9', $header);
        $col++;
    }
    applyHeaderStyle($sheet, 'A9:C9');
    
    // Calculate statistics
    $total_students = 0;
    $total_modules = 0;
    $total_assessments = 0;
    
    foreach ($sections as $section) {
        // Get student count for section
        $stmt = $db->prepare("SELECT JSON_LENGTH(COALESCE(students, '[]')) as student_count FROM sections WHERE id = ?");
        $stmt->execute([$section['id']]);
        $total_students += $stmt->fetchColumn();
    }
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM course_modules WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $total_modules = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM assessments a JOIN course_modules cm ON a.module_id = cm.id WHERE cm.course_id = ?");
    $stmt->execute([$course_id]);
    $total_assessments = $stmt->fetchColumn();
    
    $data = [
        ['Total Students', (int)$total_students, 'Number of enrolled students'],
        ['Total Modules', (int)$total_modules, 'Number of course modules'],
        ['Total Assessments', (int)$total_assessments, 'Number of assessments'],
    ];
    
    $row = 10;
    foreach ($data as $rowData) {
        $col = 'A';
        foreach ($rowData as $cellData) {
            $sheet->setCellValue($col . $row, $cellData);
            $col++;
        }
        $row++;
    }
    applyDataStyle($sheet, 'A10:C' . ($row - 1));
    
    // Section Information
    $sheet->setCellValue('A' . ($row + 2), 'Section Information');
    $sheet->mergeCells('A' . ($row + 2) . ':D' . ($row + 2));
    $sheet->getStyle('A' . ($row + 2))->getFont()->setSize(14)->setBold(true);
    
    $sectionHeaders = ['Section', 'Year', 'Student Count'];
    $col = 'A';
    foreach ($sectionHeaders as $header) {
        $sheet->setCellValue($col . ($row + 4), $header);
        $col++;
    }
    applyHeaderStyle($sheet, 'A' . ($row + 4) . ':C' . ($row + 4));
    
    $sectionRow = $row + 5;
    foreach ($sections as $section) {
        // Get student count for section
        $stmt = $db->prepare("SELECT JSON_LENGTH(COALESCE(students, '[]')) as student_count FROM sections WHERE id = ?");
        $stmt->execute([$section['id']]);
        $studentCount = $stmt->fetchColumn();
        
        $sheet->setCellValue('A' . $sectionRow, 'BSIT-' . $section['year'] . $section['name']);
        $sheet->setCellValue('B' . $sectionRow, $section['year']);
        $sheet->setCellValue('C' . $sectionRow, $studentCount);
        $sectionRow++;
    }
    applyDataStyle($sheet, 'A' . ($row + 5) . ':C' . ($sectionRow - 1));
    
    // Auto-size columns
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    return $spreadsheet;
}

function exportSectionData($course_id, $section_id, $course) {
    global $db;
    
    // Get section info
    $stmt = $db->prepare("SELECT s.* FROM sections s JOIN course_sections cs ON s.id = cs.section_id WHERE cs.course_id = ? AND s.id = ?");
    $stmt->execute([$course_id, $section_id]);
    $section = $stmt->fetch();
    
    if (!$section) {
        throw new Exception('Section not found');
    }
    
    // Get students and assessments
    // Get students in section
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
    
    $spreadsheet = createExcelFile([], $course['course_name'] . ' - ' . 'BSIT-' . $section['year'] . $section['name']);
    
    // Create Student Scores Sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Student Scores');
    
    // Header
    $sheet->setCellValue('A1', 'Student Assessment Scores');
    $sheet->mergeCells('A1:' . chr(65 + count($assessments) + 2) . '1');
    $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Course: ' . $course['course_name']);
    $sheet->setCellValue('A4', 'Section: BSIT-' . $section['year'] . $section['name']);
    $sheet->setCellValue('A5', 'Export Date: ' . date('Y-m-d H:i:s'));
    
    // Assessment headers
    $col = 'B';
    $headers = ['Student Name', 'Student ID'];
    foreach ($assessments as $assessment) {
        $headers[] = $assessment['assessment_title'];
    }
    $headers[] = 'Average Score';
    $headers[] = 'Status';
    
    $headerRow = 7;
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $headerRow, $header);
        $col++;
    }
    applyHeaderStyle($sheet, 'A' . $headerRow . ':' . chr(65 + count($headers) - 1) . $headerRow);
    
    // Student data
    $dataRow = $headerRow + 1;
    foreach ($students as $student) {
        $col = 'A';
        $sheet->setCellValue($col . $dataRow, $student['last_name'] . ', ' . $student['first_name']);
        $col++;
        $sheet->setCellValue($col . $dataRow, $student['identifier']);
        $col++;
        
        $scores = [];
        foreach ($assessments as $assessment) {
            $stmt = $db->prepare("SELECT MAX(score) as score FROM assessment_attempts WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$student['id'], $assessment['id']]);
            $score = $stmt->fetchColumn();
            
            if ($score !== null) {
                $scores[] = $score;
                $sheet->setCellValue($col . $dataRow, $score);
            } else {
                $sheet->setCellValue($col . $dataRow, 'N/A');
            }
            $col++;
        }
        
        // Calculate average
        if (!empty($scores)) {
            $average = round(array_sum($scores) / count($scores), 1);
            $sheet->setCellValue($col . $dataRow, $average);
            $col++;
            
            // Status
            $status = $average >= 70 ? 'Passing' : 'Needs Improvement';
            $sheet->setCellValue($col . $dataRow, $status);
        } else {
            $sheet->setCellValue($col . $dataRow, 'N/A');
            $col++;
            $sheet->setCellValue($col . $dataRow, 'No Data');
        }
        
        $dataRow++;
    }
    
    applyDataStyle($sheet, 'A' . ($headerRow + 1) . ':' . chr(65 + count($headers) - 1) . ($dataRow - 1));
    
    // Auto-size columns
    foreach (range('A', chr(65 + count($headers) - 1)) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    return $spreadsheet;
}

function exportAssessments($course_id, $course) {
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
    
    $spreadsheet = createExcelFile([], $course['course_name'] . ' - Assessments');
    
    // Create Assessment Performance Sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Assessment Performance');
    
    // Header
    $sheet->setCellValue('A1', 'Assessment Performance Report');
    $sheet->mergeCells('A1:H1');
    $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Course: ' . $course['course_name']);
    $sheet->setCellValue('A4', 'Export Date: ' . date('Y-m-d H:i:s'));
    
    // Headers
    $headers = ['Module', 'Assessment', 'Passing Rate', 'Avg Score', 'Total Attempts', 'Min Score', 'Max Score', 'Pass Rate', 'Status'];
    $col = 'A';
    $headerRow = 6;
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $headerRow, $header);
        $col++;
    }
    applyHeaderStyle($sheet, 'A' . $headerRow . ':I' . $headerRow);
    
    // Data
    $dataRow = $headerRow + 1;
    foreach ($assessments as $assessment) {
        $col = 'A';
        $sheet->setCellValue($col . $dataRow, $assessment['module_title']);
        $col++;
        $sheet->setCellValue($col . $dataRow, $assessment['assessment_title']);
        $col++;
        $sheet->setCellValue($col . $dataRow, $assessment['passing_rate'] . '%');
        $col++;
        $sheet->setCellValue($col . $dataRow, round($assessment['avg_score'] ?? 0, 1) . '%');
        $col++;
        $sheet->setCellValue($col . $dataRow, $assessment['total_attempts']);
        $col++;
        $sheet->setCellValue($col . $dataRow, $assessment['min_score'] ?? 'N/A');
        $col++;
        $sheet->setCellValue($col . $dataRow, $assessment['max_score'] ?? 'N/A');
        $col++;
        
        $passRate = $assessment['total_attempts'] > 0 ? round(($assessment['passed_attempts'] / $assessment['total_attempts']) * 100, 1) : 0;
        $sheet->setCellValue($col . $dataRow, $passRate . '%');
        $col++;
        
        $status = ($assessment['avg_score'] ?? 0) >= $assessment['passing_rate'] ? 'Passing' : 'Below Threshold';
        $sheet->setCellValue($col . $dataRow, $status);
        
        $dataRow++;
    }
    
    applyDataStyle($sheet, 'A' . ($headerRow + 1) . ':I' . ($dataRow - 1));
    
    // Auto-size columns
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    return $spreadsheet;
}

try {
    // Disable output buffering completely
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Disable any output
    ob_start();
    
    $spreadsheet = null;
    
    switch ($export_type) {
        case 'overview':
            $spreadsheet = exportOverview($course_id, $course);
            $filename = $course['course_name'] . '_Overview_' . date('Y-m-d') . '.xlsx';
            break;
            
        case 'section':
            if (!$section_id) {
                throw new Exception('Section ID required for section export');
            }
            $spreadsheet = exportSectionData($course_id, $section_id, $course);
            $filename = $course['course_name'] . '_Section_' . date('Y-m-d') . '.xlsx';
            break;
            
        case 'assessments':
            $spreadsheet = exportAssessments($course_id, $course);
            $filename = $course['course_name'] . '_Assessments_' . date('Y-m-d') . '.xlsx';
            break;
            
        default:
            throw new Exception('Invalid export type');
    }
    
    // Create the Excel file
    $writer = new Xlsx($spreadsheet);
    
    // Create a unique filename in the exports directory
    $exportsDir = '../exports/';
    if (!is_dir($exportsDir)) {
        mkdir($exportsDir, 0755, true);
    }
    
    $uniqueFilename = uniqid() . '_' . $filename;
    $filePath = $exportsDir . $uniqueFilename;
    
    // Save the file
    $writer->save($filePath);
    
    // Verify file was created successfully
    if (!file_exists($filePath) || filesize($filePath) === 0) {
        throw new Exception('Failed to create Excel file');
    }
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for file download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    
    // Output the file
    readfile($filePath);
    
    // Clean up the file after download
    unlink($filePath);
    
} catch (Exception $e) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
