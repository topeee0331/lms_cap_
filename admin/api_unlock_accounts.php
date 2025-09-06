<?php
/**
 * API endpoint for unlocking accounts
 * Accepts POST requests with JSON data
 * Requires admin authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized: Admin access required'
    ]);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON input'
    ]);
    exit();
}

$action = $input['action'] ?? '';
$target = $input['target'] ?? '';
$type = $input['type'] ?? '';

// Validate required fields
if (empty($action) || empty($type)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: action and type'
    ]);
    exit();
}

try {
    $result = [];
    
    if ($action === 'unlock') {
        if ($type === 'email' && !empty($target)) {
            // Unlock specific email
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ? AND success = 0");
            $stmt->execute([$target]);
            $deleted = $stmt->rowCount();
            
            $result = [
                'success' => true,
                'message' => "Successfully unlocked email: {$target}",
                'deleted_attempts' => $deleted,
                'target' => $target,
                'type' => 'email'
            ];
            
        } elseif ($type === 'ip' && !empty($target)) {
            // Unlock specific IP
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND success = 0");
            $stmt->execute([$target]);
            $deleted = $stmt->rowCount();
            
            $result = [
                'success' => true,
                'message' => "Successfully unlocked IP: {$target}",
                'deleted_attempts' => $deleted,
                'target' => $target,
                'type' => 'ip'
            ];
            
        } elseif ($type === 'all') {
            // Unlock all locked accounts
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE success = 0");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            $result = [
                'success' => true,
                'message' => "Successfully unlocked all accounts",
                'deleted_attempts' => $deleted,
                'type' => 'all'
            ];
            
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid type or missing target'
            ]);
            exit();
        }
        
    } elseif ($action === 'status') {
        // Get current lock status
        if ($type === 'email' && !empty($target)) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as failed_count,
                       MAX(attempt_time) as last_attempt,
                       TIMESTAMPDIFF(SECOND, MAX(attempt_time), NOW()) as seconds_ago
                FROM login_attempts 
                WHERE email = ? AND success = 0 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$target]);
            $status = $stmt->fetch();
            
            $is_locked = $status['failed_count'] >= MAX_LOGIN_ATTEMPTS;
            $remaining_lockout = 0;
            
            if ($is_locked) {
                $remaining_lockout = max(0, LOGIN_LOCKOUT_DURATION - $status['seconds_ago']);
            }
            
            $result = [
                'success' => true,
                'target' => $target,
                'type' => 'email',
                'is_locked' => $is_locked,
                'failed_attempts' => $status['failed_count'],
                'last_attempt' => $status['last_attempt'],
                'remaining_lockout_seconds' => $remaining_lockout,
                'max_attempts' => MAX_LOGIN_ATTEMPTS
            ];
            
        } elseif ($type === 'ip' && !empty($target)) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as failed_count,
                       MAX(attempt_time) as last_attempt,
                       TIMESTAMPDIFF(SECOND, MAX(attempt_time), NOW()) as seconds_ago
                FROM login_attempts 
                WHERE ip_address = ? AND success = 0 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$target]);
            $status = $stmt->fetch();
            
            $is_locked = $status['failed_count'] >= MAX_LOGIN_ATTEMPTS;
            $remaining_lockout = 0;
            
            if ($is_locked) {
                $remaining_lockout = max(0, LOGIN_LOCKOUT_DURATION - $status['seconds_ago']);
            }
            
            $result = [
                'success' => true,
                'target' => $target,
                'type' => 'ip',
                'is_locked' => $is_locked,
                'failed_attempts' => $status['failed_count'],
                'last_attempt' => $status['last_attempt'],
                'remaining_lockout_seconds' => $remaining_lockout,
                'max_attempts' => MAX_LOGIN_ATTEMPTS
            ];
            
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid type or missing target for status check'
            ]);
            exit();
        }
        
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action. Use "unlock" or "status"'
        ]);
        exit();
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
