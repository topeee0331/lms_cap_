<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get student ID from POST data
$student_id = $_POST['student_id'] ?? null;

if (!$student_id) {
    echo json_encode(['success' => false, 'error' => 'Student ID is required']);
    exit;
}

try {
    // Fetch student details
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.username,
            u.email,
            u.identifier,
            u.year_level,
            u.is_irregular,
            u.status,
            u.profile_picture,
            u.created_at
        FROM users u 
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit;
    }
    
    // Get student's sections
    $stmt = $db->prepare("
        SELECT s.id, s.year_level, s.section_name 
        FROM sections s 
        WHERE s.students IS NOT NULL 
        AND JSON_SEARCH(s.students, 'one', ?) IS NOT NULL
        ORDER BY s.year_level, s.section_name
    ");
    $stmt->execute([$student_id]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $student['sections'] = $sections;
    
    // Generate profile picture URL
    $student['profile_picture_url'] = getProfilePictureUrl($student['profile_picture'] ?? null, 'large');
    
    echo json_encode([
        'success' => true,
        'student' => $student
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
