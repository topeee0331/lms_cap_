<?php
require_once '../config/database.php';

// Check if user is logged in and is a teacher
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get academic year ID from request
$academic_year_id = isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : 0;

if ($academic_year_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid academic year ID']);
    exit();
}

try {
    // Fetch semesters for the selected academic year
    $stmt = $db->prepare('SELECT id, name FROM semesters WHERE academic_year_id = ? ORDER BY name');
    $stmt->execute([$academic_year_id]);
    $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($semesters);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?> 