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

// Get teacher ID from POST data
$teacher_id = $_POST['teacher_id'] ?? null;

if (!$teacher_id) {
    echo json_encode(['success' => false, 'error' => 'Teacher ID is required']);
    exit;
}

try {
    // Fetch teacher details
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.username,
            u.email,
            u.identifier,
            u.status,
            u.profile_picture,
            u.created_at
        FROM users u 
        WHERE u.id = ? AND u.role = 'teacher'
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        echo json_encode(['success' => false, 'error' => 'Teacher not found']);
        exit;
    }
    
    // Generate profile picture URL
    $teacher['profile_picture_url'] = getProfilePictureUrl($teacher['profile_picture'] ?? null, 'large');
    
    echo json_encode([
        'success' => true,
        'teacher' => $teacher
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
