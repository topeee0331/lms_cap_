<?php
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');

if (empty($assessment_id)) {
    echo json_encode(['success' => false, 'message' => 'Assessment ID is required']);
    exit;
}

try {
    // Count questions for the assessment
    $stmt = $db->prepare("SELECT COUNT(*) FROM questions WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'count' => (int)$count
    ]);
} catch (Exception $e) {
    error_log("Error getting question count: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get question count'
    ]);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
