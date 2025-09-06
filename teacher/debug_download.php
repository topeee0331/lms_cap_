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

try {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Create a simple spreadsheet
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Add simple data
    $sheet->setCellValue('A1', 'Test');
    $sheet->setCellValue('B1', 'Data');
    $sheet->setCellValue('A2', 'Hello');
    $sheet->setCellValue('B2', 'World');
    
    // Create the Excel file
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    // Create exports directory if it doesn't exist
    $exportsDir = '../exports/';
    if (!is_dir($exportsDir)) {
        mkdir($exportsDir, 0755, true);
    }
    
    // Save to file
    $filename = 'debug_download_' . date('Y-m-d_H-i-s') . '.xlsx';
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
    header('Cache-Control: max-age=0');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output file
    readfile($filePath);
    
    // Clean up
    unlink($filePath);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
