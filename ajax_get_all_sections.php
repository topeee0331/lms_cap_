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

try {
    // Get all sections
    $stmt = $db->prepare("
        SELECT s.id, s.section_name, s.year_level, s.description, s.is_active,
               ap.academic_year, ap.semester_name
        FROM sections s
        JOIN academic_periods ap ON s.academic_period_id = ap.id
        ORDER BY ap.academic_year DESC, ap.semester_name, s.year_level, s.section_name
    ");
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sections' => $sections
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching sections: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching sections: ' . $e->getMessage()
    ]);
}
?>
