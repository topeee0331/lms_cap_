<?php
/**
 * Main Configuration File
 * Learning Management System for NEUST-MGT BSIT Department
 */

// Load Composer autoloader for Pusher
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Define the base URL of the application
define('BASE_URL', '/lms_cap');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application constants
define('SITE_NAME', 'NEUST-MGT BSIT LMS');
define('SITE_URL', 'http://localhost/lms_cap');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/lms_cap/uploads/');
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024); // 100MB
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_VIDEO_TYPES', ['video/mp4']);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png']);

// NEUST Branding Colors
define('NEUST_BLUE', '#003087');
define('NEUST_WHITE', '#FFFFFF');

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour

// File upload paths
define('VIDEO_UPLOAD_PATH', UPLOAD_PATH . 'videos/');
define('PROFILE_UPLOAD_PATH', UPLOAD_PATH . 'profiles/');
define('BADGE_UPLOAD_PATH', UPLOAD_PATH . 'badges/');

// Create upload directories if they don't exist (silently)
try {
    $upload_dirs = [VIDEO_UPLOAD_PATH, PROFILE_UPLOAD_PATH, BADGE_UPLOAD_PATH];
    foreach ($upload_dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }
} catch (Exception $e) {
    // Silently log the error without outputting anything
    error_log("Failed to create upload directories: " . $e->getMessage());
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Authentication check
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role && $_SESSION['role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/unauthorized.php');
        exit();
    }
}

// Input sanitization
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// File upload validation
function validateFileUpload($file, $allowed_types, $max_size) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return false;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime_type, $allowed_types)) {
        return false;
    }
    
    return true;
}

// Generate unique filename
function generateUniqueFilename($original_name, $extension) {
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    return $timestamp . '_' . $random . '.' . $extension;
}

// Format date
function formatDate($date) {
    return date('F j, Y g:i A', strtotime($date));
}

// Calculate percentage
function calculatePercentage($score, $max_score) {
    if ($max_score == 0) return 0;
    return round(($score / $max_score) * 100, 2);
}

// Get user role display name
function getRoleDisplayName($role) {
    switch ($role) {
        case 'admin':
            return 'Administrator';
        case 'teacher':
            return 'Teacher';
        case 'student':
            return 'Student';
        default:
            return ucfirst($role);
    }
}

// Check if user has permission
function hasPermission($permission) {
    if (!isLoggedIn()) return false;
    
    $user_role = $_SESSION['role'];
    
    switch ($permission) {
        case 'manage_users':
            return $user_role === 'admin';
        case 'manage_courses':
            return in_array($user_role, ['admin', 'teacher']);
        case 'manage_assessments':
            return in_array($user_role, ['admin', 'teacher']);
        case 'take_assessments':
            return $user_role === 'student';
        case 'view_analytics':
            return in_array($user_role, ['admin', 'teacher']);
        default:
            return false;
    }
}

// Log activity
function logActivity($user_id, $action, $details = '') {
    global $db;
    
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
}

// Redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header('Location: ' . $url);
    exit();
}

// Display message
function displayMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'info';
        $message = $_SESSION['message'];
        unset($_SESSION['message'], $_SESSION['message_type']);
        
        return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                    {$message}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
    return '';
}

// Profile picture helper function
function getProfilePictureUrl($profile_picture, $size = 'medium') {
    // Check if profile picture exists and is not empty
    if (!empty($profile_picture) && file_exists(PROFILE_UPLOAD_PATH . $profile_picture)) {
        return SITE_URL . '/uploads/profiles/' . $profile_picture;
    }
    
    // Return appropriate placeholder based on size
    switch ($size) {
        case 'small':
            // 36x36px placeholder
            return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36"><circle cx="18" cy="18" r="18" fill="#6c757d"/><circle cx="18" cy="14" r="5" fill="#fff"/><path d="M7 29c0-6.627 5.373-12 12-12s12 5.373 12 12" fill="#fff"/></svg>');
        case 'medium':
            // 48x48px placeholder
            return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><circle cx="24" cy="24" r="24" fill="#6c757d"/><circle cx="24" cy="19" r="7" fill="#fff"/><path d="M9 39c0-8.837 7.163-16 16-16s16 7.163 16 16" fill="#fff"/></svg>');
        case 'large':
            // 80x80px placeholder
            return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><circle cx="40" cy="40" r="40" fill="#6c757d"/><circle cx="40" cy="32" r="12" fill="#fff"/><path d="M16 64c0-13.255 10.745-24 24-24s24 10.745 24 24" fill="#fff"/></svg>');
        case 'xlarge':
            // 120x120px placeholder
            return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><circle cx="60" cy="60" r="60" fill="#6c757d"/><circle cx="60" cy="48" r="18" fill="#fff"/><path d="M24 96c0-19.882 15.118-36 36-36s36 16.118 36 36" fill="#fff"/></svg>');
        default:
            // Default medium size
            return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><circle cx="24" cy="24" r="24" fill="#6c757d"/><circle cx="24" cy="19" r="7" fill="#fff"/><path d="M9 39c0-8.837 7.163-16 16-16s16 7.163 16 16" fill="#fff"/></svg>');
    }
}

// Login throttling constants
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_LOCKOUT_DURATION', 900); // 15 minutes in seconds
define('LOGIN_ATTEMPT_WINDOW', 3600); // 1 hour window for counting attempts

// Login throttling functions
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isLoginThrottled($email, $ip_address = null) {
    global $pdo;
    
    if ($ip_address === null) {
        $ip_address = getClientIP();
    }
    
    // Check if admin role (bypass throttling)
    $stmt = $pdo->prepare('SELECT role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && $user['role'] === 'admin') {
        return false; // Admin bypasses throttling
    }
    
    // Count recent failed attempts
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as attempt_count 
        FROM login_attempts 
        WHERE (ip_address = ? OR email = ?) 
        AND success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ');
    $stmt->execute([$ip_address, $email, LOGIN_ATTEMPT_WINDOW]);
    $result = $stmt->fetch();
    
    return $result['attempt_count'] >= MAX_LOGIN_ATTEMPTS;
}

function isIPLocked($ip_address = null) {
    global $pdo;
    
    if ($ip_address === null) {
        $ip_address = getClientIP();
    }
    
    // Check if IP is locked due to too many failed attempts
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as attempt_count, 
               MAX(attempt_time) as last_attempt 
        FROM login_attempts 
        WHERE ip_address = ? 
        AND success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ');
    $stmt->execute([$ip_address, LOGIN_LOCKOUT_DURATION]);
    $result = $stmt->fetch();
    
    if ($result['attempt_count'] >= MAX_LOGIN_ATTEMPTS) {
        $last_attempt = strtotime($result['last_attempt']);
        $lockout_until = $last_attempt + LOGIN_LOCKOUT_DURATION;
        
        if (time() < $lockout_until) {
            return true; // Still locked
        }
    }
    
    return false;
}

function recordLoginAttempt($email, $success, $ip_address = null, $user_agent = null) {
    global $pdo;
    
    if ($ip_address === null) {
        $ip_address = getClientIP();
    }
    
    if ($user_agent === null) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO login_attempts (ip_address, email, success, user_agent) 
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$ip_address, $email, $success ? 1 : 0, $user_agent]);
}

function getRemainingLockoutTime($ip_address = null) {
    global $pdo;
    
    if ($ip_address === null) {
        $ip_address = getClientIP();
    }
    
    $stmt = $pdo->prepare('
        SELECT MAX(attempt_time) as last_attempt 
        FROM login_attempts 
        WHERE ip_address = ? 
        AND success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ');
    $stmt->execute([$ip_address, LOGIN_LOCKOUT_DURATION]);
    $result = $stmt->fetch();
    
    if ($result['last_attempt']) {
        $last_attempt = strtotime($result['last_attempt']);
        $lockout_until = $last_attempt + LOGIN_LOCKOUT_DURATION;
        $remaining = $lockout_until - time();
        
        return max(0, $remaining);
    }
    
    return 0;
}

function clearLoginAttempts($email, $ip_address = null) {
    global $pdo;
    
    if ($ip_address === null) {
        $ip_address = getClientIP();
    }
    
    // Clear successful login attempts for this email/IP combination
    $stmt = $pdo->prepare('
        DELETE FROM login_attempts 
        WHERE (email = ? OR ip_address = ?) 
        AND success = 0
    ');
    $stmt->execute([$email, $ip_address]);
}

// CAPTCHA functions
function generateSimpleCaptcha() {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $operator = ['+', '-', '×'][rand(0, 2)];
    
    switch ($operator) {
        case '+':
            $answer = $num1 + $num2;
            break;
        case '-':
            $answer = $num1 - $num2;
            break;
        case '×':
            $answer = $num1 * $num2;
            break;
    }
    
    // Store CAPTCHA data with timestamp for better security
    $_SESSION['captcha_data'] = [
        'answer' => $answer,
        'question' => "$num1 $operator $num2",
        'timestamp' => time(),
        'attempts' => 0
    ];
    
    return $_SESSION['captcha_data']['question'];
}

function validateCaptcha($user_answer) {
    // Check if CAPTCHA data exists and is not expired
    if (!isset($_SESSION['captcha_data']) || 
        !isset($_SESSION['captcha_data']['answer']) ||
        !isset($_SESSION['captcha_data']['timestamp'])) {
        return false;
    }
    
    // CAPTCHA expires after 10 minutes
    $captcha_expiry = 600; // 10 minutes
    if (time() - $_SESSION['captcha_data']['timestamp'] > $captcha_expiry) {
        unset($_SESSION['captcha_data']);
        return false;
    }
    
    // Increment attempts counter
    $_SESSION['captcha_data']['attempts']++;
    
    // Get the correct answer
    $correct_answer = $_SESSION['captcha_data']['answer'];
    
    // Validate the user's answer
    $is_correct = (int)$user_answer === $correct_answer;
    
    // If correct or too many attempts, clear the CAPTCHA data
    if ($is_correct || $_SESSION['captcha_data']['attempts'] >= 3) {
        unset($_SESSION['captcha_data']);
    }
    
    return $is_correct;
}

function clearCaptcha() {
    unset($_SESSION['captcha_data']);
}

function getCurrentCaptcha() {
    return $_SESSION['captcha_data']['question'] ?? null;
}

// Include database connection
require_once 'database.php';
