<?php
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['available' => false, 'error' => 'Method not allowed']);
    exit;
}

$email = sanitizeInput($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['available' => false, 'error' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['available' => false, 'error' => 'Invalid email format']);
    exit;
}

try {
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $exists = $stmt->fetch();
    
    echo json_encode(['available' => !$exists]);
} catch (Exception $e) {
    error_log('Email check error: ' . $e->getMessage());
    echo json_encode(['available' => false, 'error' => 'Database error occurred']);
}
?>
