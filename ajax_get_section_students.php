<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

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
    // Get students in the section
    $stmt = $db->prepare("SELECT students FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch();
    
    if (!$section) {
        echo json_encode(['success' => false, 'message' => 'Section not found']);
        exit;
    }
    
    $student_ids = [];
    if ($section['students']) {
        $student_ids = json_decode($section['students'], true) ?: [];
    }
    
    if (empty($student_ids)) {
        echo json_encode([
            'success' => true,
            'students' => [],
            'total' => 0
        ]);
        exit;
    }
    
    // Get student details
    $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, is_irregular, identifier
        FROM users 
        WHERE id IN ($placeholders) AND role = 'student'
        ORDER BY last_name, first_name
    ");
    $stmt->execute($student_ids);
    $students = $stmt->fetchAll();
    
    // Check for missing students
    $existing_student_ids = array_column($students, 'id');
    $missing_student_ids = array_diff($student_ids, $existing_student_ids);
    
    // Format student data
    $formatted_students = [];
    foreach ($students as $student) {
        $formatted_students[] = [
            'id' => $student['id'],
            'section_id' => $section_id,
            'name' => $student['last_name'] . ', ' . $student['first_name'],
            'identifier' => $student['identifier'],
            'is_irregular' => $student['is_irregular']
        ];
    }
    
    // Add debug information
    $debug_info = [
        'total_in_json' => count($student_ids),
        'found_students' => count($formatted_students),
        'missing_student_ids' => array_values($missing_student_ids)
    ];
    
    echo json_encode([
        'success' => true,
        'students' => $formatted_students,
        'total' => count($formatted_students),
        'debug' => $debug_info
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching section students: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching students: ' . $e->getMessage()
    ]);
}
?>
