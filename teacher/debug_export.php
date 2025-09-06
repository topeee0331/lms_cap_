<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

echo "<h2>Excel Export Diagnostic</h2>";

// Test 1: Check PhpSpreadsheet installation
echo "<h3>1. PhpSpreadsheet Library Check</h3>";
if (file_exists('../vendor/autoload.php')) {
    echo "✅ PhpSpreadsheet library found<br>";
    require '../vendor/autoload.php';
    
    try {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        echo "✅ PhpSpreadsheet classes loaded successfully<br>";
    } catch (Exception $e) {
        echo "❌ Error loading PhpSpreadsheet: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ PhpSpreadsheet library not found. Please run: composer install<br>";
    exit;
}

// Test 2: Check directory permissions
echo "<h3>2. Directory Permissions Check</h3>";
$exportsDir = '../exports/';
if (!is_dir($exportsDir)) {
    if (mkdir($exportsDir, 0755, true)) {
        echo "✅ Created exports directory<br>";
    } else {
        echo "❌ Failed to create exports directory<br>";
    }
} else {
    echo "✅ Exports directory exists<br>";
}

if (is_writable($exportsDir)) {
    echo "✅ Exports directory is writable<br>";
} else {
    echo "❌ Exports directory is not writable<br>";
}

// Test 3: Check PHP configuration
echo "<h3>3. PHP Configuration Check</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
echo "Output Buffering: " . (ob_get_level() ? "Enabled (Level: " . ob_get_level() . ")" : "Disabled") . "<br>";

// Test 4: Create a simple Excel file
echo "<h3>4. Excel File Creation Test</h3>";
try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Add simple data
    $sheet->setCellValue('A1', 'Test');
    $sheet->setCellValue('B1', 'Data');
    $sheet->setCellValue('A2', 'Hello');
    $sheet->setCellValue('B2', 'World');
    
    // Create the Excel file
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    // Save to file
    $filename = 'debug_test_' . date('Y-m-d_H-i-s') . '.xlsx';
    $filePath = $exportsDir . $filename;
    $writer->save($filePath);
    
    if (file_exists($filePath)) {
        $fileSize = filesize($filePath);
        echo "✅ Excel file created successfully<br>";
        echo "File path: " . $filePath . "<br>";
        echo "File size: " . $fileSize . " bytes<br>";
        
        // Test file content
        $fileContent = file_get_contents($filePath);
        if (strpos($fileContent, 'PK') === 0) {
            echo "✅ File appears to be a valid ZIP/Excel file (starts with PK)<br>";
        } else {
            echo "❌ File does not appear to be a valid Excel file<br>";
            echo "File starts with: " . substr($fileContent, 0, 20) . "<br>";
        }
        
        // Clean up
        unlink($filePath);
        echo "✅ Test file cleaned up<br>";
        
    } else {
        echo "❌ Excel file was not created<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error creating Excel file: " . $e->getMessage() . "<br>";
}

// Test 5: Check for any output before headers
echo "<h3>5. Output Buffer Check</h3>";
$outputBeforeHeaders = ob_get_contents();
if (!empty($outputBeforeHeaders)) {
    echo "❌ Found output before headers: " . htmlspecialchars(substr($outputBeforeHeaders, 0, 100)) . "<br>";
} else {
    echo "✅ No output detected before headers<br>";
}

// Test 6: Test actual download
echo "<h3>6. Download Test</h3>";
echo "<a href='debug_download.php' target='_blank'>Click here to test actual download</a><br>";

echo "<h3>7. Recommendations</h3>";
echo "<ul>";
echo "<li>If PhpSpreadsheet is not found, run: <code>composer install</code></li>";
echo "<li>If directory is not writable, check permissions</li>";
echo "<li>If file is corrupted, check for output before headers</li>";
echo "<li>If download fails, check browser console for errors</li>";
echo "</ul>";
?>
