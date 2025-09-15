<?php
require_once '../config/config.php';
requireRole('teacher');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Debug logging
error_log("Toggle module status input: " . json_encode($input));
error_log("Session user ID: " . $_SESSION['user_id']);

// Validate CSRF token
if (!isset($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate required fields
if (!isset($input['module_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$module_id = $input['module_id']; // Keep as string since it's uniqid
$action = $input['action'];

if (!in_array($action, ['lock', 'unlock'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    // First, try to get course ID from referrer if available
    $course_id_from_referrer = null;
    if (isset($_SERVER['HTTP_REFERER'])) {
        if (preg_match('/course\.php\?id=(\d+)/', $_SERVER['HTTP_REFERER'], $matches)) {
            $course_id_from_referrer = $matches[1];
            error_log("Found course ID from referrer: " . $course_id_from_referrer);
        }
    }
    
    // Find the course that contains this module
    if ($course_id_from_referrer) {
        // Try the specific course first
        $stmt = $db->prepare("
            SELECT id, modules, teacher_id 
            FROM courses 
            WHERE id = ? AND teacher_id = ? AND modules IS NOT NULL
        ");
        $stmt->execute([$course_id_from_referrer, $_SESSION['user_id']]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Fallback to all courses
        $stmt = $db->prepare("
            SELECT id, modules, teacher_id 
            FROM courses 
            WHERE teacher_id = ? AND modules IS NOT NULL
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $module_found = false;
    $course_id = null;
    $updated_modules = null;
    
    // Search through all courses to find the module
    error_log("Searching for module ID: " . $module_id . " in " . count($courses) . " courses");
    
    foreach ($courses as $course) {
        error_log("Checking course ID: " . $course['id']);
        $modules = json_decode($course['modules'], true);
        if (is_array($modules)) {
            error_log("Course " . $course['id'] . " has " . count($modules) . " modules");
            foreach ($modules as $index => $module) {
                error_log("Module " . $index . " ID: " . (isset($module['id']) ? $module['id'] : 'NO ID') . " (type: " . gettype($module['id'] ?? null) . ")");
                error_log("Module " . $index . " data: " . json_encode($module));
                
                // Try multiple ID field names
                $module_identifier = null;
                if (isset($module['id'])) {
                    $module_identifier = $module['id'];
                } elseif (isset($module['module_id'])) {
                    $module_identifier = $module['module_id'];
                }
                
                if ($module_identifier && (string)$module_identifier === (string)$module_id) {
                    error_log("Found matching module!");
                    // Found the module, update its status
                    $is_locked = ($action === 'lock') ? 1 : 0;
                    $unlock_score = ($action === 'lock') ? ($module['unlock_score'] ?? 70) : null;
                    
                    $modules[$index]['is_locked'] = $is_locked;
                    $modules[$index]['unlock_score'] = $unlock_score;
                    
                    $course_id = $course['id'];
                    $updated_modules = $modules;
                    $module_found = true;
                    break 2; // Break out of both loops
                }
            }
        } else {
            error_log("Course " . $course['id'] . " modules is not an array: " . $course['modules']);
        }
    }
    
    if (!$module_found) {
        echo json_encode(['success' => false, 'message' => 'Module not found or access denied']);
        exit;
    }
    
    // Update the course with the modified modules JSON
    $stmt = $db->prepare("
        UPDATE courses 
        SET modules = ? 
        WHERE id = ?
    ");
    
    $result = $stmt->execute([json_encode($updated_modules), $course_id]);
    
    if (!$result) {
        throw new Exception("Failed to update module status");
    }
    
    $status_text = $is_locked ? 'locked' : 'unlocked';
    echo json_encode([
        'success' => true, 
        'message' => "Module successfully {$status_text}!"
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in toggle_module_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in toggle_module_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
