<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/year_level_validation.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get section ID from request
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

if (!$section_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
    exit;
}

try {
    // Get current students in the section
    $stmt = $db->prepare("SELECT students FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch();
    
    if (!$section) {
        echo json_encode(['success' => false, 'message' => 'Section not found']);
        exit;
    }
    
    $current_students = [];
    if ($section['students']) {
        $current_students = json_decode($section['students'], true) ?: [];
    }
    
    // Get target section's year level
    $stmt = $db->prepare("SELECT year_level, section_name FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $target_section = $stmt->fetch();
    
    if (!$target_section) {
        echo json_encode(['success' => false, 'message' => 'Target section not found']);
        exit;
    }
    
    $target_year = $target_section['year_level'];
    $section_name = $target_section['section_name'];
    
    // Get all students that are not already in this section
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, is_irregular, identifier
        FROM users 
        WHERE role = 'student' 
        AND status = 'active'
        ORDER BY last_name, first_name
    ");
    $stmt->execute();
    $all_students = $stmt->fetchAll();
    
    // Filter out students already in the section and validate year levels
    $available_students = [];
    $invalid_students = [];
    
    foreach ($all_students as $student) {
        if (!in_array($student['id'], $current_students)) {
            // Validate year level assignment
            $validation = validateStudentYearLevel($student['id'], $section_id, $db);
            
            $student_data = [
                'id' => $student['id'],
                'name' => $student['last_name'] . ', ' . $student['first_name'] . ' (' . ($student['identifier'] ?: 'No ID') . ')',
                'is_irregular' => $student['is_irregular'],
                'year_level' => $target_year,
                'year_level_text' => getYearLevelOptions($target_year)
            ];
            
            if ($validation['valid']) {
                $available_students[] = $student_data;
            } else {
                $student_data['validation_error'] = $validation['message'];
                $invalid_students[] = $student_data;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'students' => $available_students,
        'invalid_students' => $invalid_students,
        'total_available' => count($available_students),
        'total_invalid' => count($invalid_students),
        'current_in_section' => count($current_students),
        'target_section' => [
            'year_level' => $target_year,
            'section_name' => $section_name
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching available students: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching students: ' . $e->getMessage()
    ]);
}
?>