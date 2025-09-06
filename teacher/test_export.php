<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
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

try {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Create a simple test spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Add some test data
    $sheet->setCellValue('A1', 'Test Excel Export');
    $sheet->setCellValue('A2', 'Course: Test Course');
    $sheet->setCellValue('A3', 'Date: ' . date('Y-m-d H:i:s'));
    
    $sheet->setCellValue('A5', 'Student');
    $sheet->setCellValue('B5', 'Score');
    $sheet->setCellValue('C5', 'Status');
    
    $sheet->setCellValue('A6', 'John Doe');
    $sheet->setCellValue('B6', '85');
    $sheet->setCellValue('C6', 'Passing');
    
    $sheet->setCellValue('A7', 'Jane Smith');
    $sheet->setCellValue('B7', '92');
    $sheet->setCellValue('C7', 'Excellent');
    
    // Auto-size columns
    foreach (range('A', 'C') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Create the Excel file
    $writer = new Xlsx($spreadsheet);
    
    // Set headers for file download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Test_Export_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Pragma: public');
    
    // Create temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'excel_test_');
    $writer->save($tempFile);
    
    // Get file size and set content length
    $fileSize = filesize($tempFile);
    header('Content-Length: ' . $fileSize);
    
    // Output the file
    readfile($tempFile);
    
    // Clean up temporary file
    unlink($tempFile);
    
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
