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

$username = sanitizeInput($_POST['username'] ?? '');

if (empty($username)) {
    echo json_encode(['available' => false, 'error' => 'Username is required']);
    exit;
}

if (strlen($username) < 3) {
    echo json_encode(['available' => false, 'error' => 'Username must be at least 3 characters']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['available' => false, 'error' => 'Username can only contain letters, numbers, and underscores']);
    exit;
}

try {
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $exists = $stmt->fetch();
    
    echo json_encode(['available' => !$exists]);
} catch (Exception $e) {
    error_log('Username check error: ' . $e->getMessage());
    echo json_encode(['available' => false, 'error' => 'Database error occurred']);
}
?>
