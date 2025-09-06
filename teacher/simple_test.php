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
    // Create a simple spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Add simple data
    $sheet->setCellValue('A1', 'Test');
    $sheet->setCellValue('B1', 'Data');
    $sheet->setCellValue('A2', 'Hello');
    $sheet->setCellValue('B2', 'World');
    
    // Create the Excel file
    $writer = new Xlsx($spreadsheet);
    
    // Create exports directory if it doesn't exist
    $exportsDir = '../exports/';
    if (!is_dir($exportsDir)) {
        mkdir($exportsDir, 0755, true);
    }
    
    // Save to file
    $filename = 'simple_test_' . date('Y-m-d_H-i-s') . '.xlsx';
    $filePath = $exportsDir . $filename;
    $writer->save($filePath);
    
    // Verify file exists
    if (!file_exists($filePath)) {
        throw new Exception('File was not created');
    }
    
    // Set headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    
    // Output file
    readfile($filePath);
    
    // Clean up
    unlink($filePath);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
