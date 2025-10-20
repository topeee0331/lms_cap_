<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

// Require admin access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    $year_level = isset($_GET['year_level']) ? (int) $_GET['year_level'] : 0;
    if ($year_level <= 0) {
        echo json_encode(['success' => true, 'courses' => []]);
        exit;
    }

    // Fetch courses that match the given year level and are not archived
    $stmt = $db->prepare("SELECT id, course_name, course_code, description FROM courses WHERE is_archived = 0 AND (year_level = ? OR year_level IS NULL) ORDER BY course_name");
    $stmt->execute([$year_level]);
    $courses = $stmt->fetchAll();

    echo json_encode(['success' => true, 'courses' => $courses]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

try {
    $year_level = sanitizeInput($_GET['year_level'] ?? '');
    
    if (empty($year_level)) {
        echo json_encode(['success' => false, 'error' => 'Year level is required']);
        exit;
    }
    
    // Fetch courses for the specific year level
    $stmt = $db->prepare("
        SELECT id, course_name, course_code, description, year_level
        FROM courses 
        WHERE year_level = ? AND is_archived = 0 
        ORDER BY course_name
    ");
    $stmt->execute([$year_level]);
    $courses = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'total_courses' => count($courses),
        'year_level' => $year_level
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
