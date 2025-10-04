<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in - if not, return empty announcements
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode([
        'success' => true,
        'count' => 0,
        'announcements' => []
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    if ($role === 'teacher') {
        // Get teacher's announcements (both general and course-specific) - only unread ones
        $stmt = $db->prepare("
            SELECT 
                a.id, a.title, a.content, a.created_at, a.is_global, a.target_audience,
                CONCAT(u.first_name, ' ', u.last_name) as author_name,
                u.first_name, u.last_name,
                CASE 
                    WHEN a.is_global = 1 THEN 'General Announcement'
                    WHEN a.target_audience IS NOT NULL THEN (
                        SELECT c.course_name 
                        FROM courses c 
                        WHERE c.id = JSON_UNQUOTE(JSON_EXTRACT(a.target_audience, '$.courses[0]'))
                    )
                    ELSE 'Unknown'
                END as context,
                CASE 
                    WHEN a.is_global = 1 THEN 'General'
                    ELSE 'Course'
                END as announcement_type
            FROM announcements a
            LEFT JOIN users u ON a.author_id = u.id
            WHERE (a.author_id = ? 
               OR a.is_global = 1
               OR (a.target_audience IS NOT NULL AND JSON_SEARCH(a.target_audience, 'one', ?) IS NOT NULL))
               AND (a.read_by IS NULL OR JSON_SEARCH(a.read_by, 'one', ?) IS NULL)
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($role === 'student') {
        // Get student's announcements (both general and course-specific) - only unread ones
        $stmt = $db->prepare("
            SELECT 
                a.id, a.title, a.content, a.created_at, a.is_global, a.target_audience,
                CONCAT(u.first_name, ' ', u.last_name) as author_name,
                u.first_name, u.last_name,
                CASE 
                    WHEN a.is_global = 1 THEN 'General Announcement'
                    WHEN a.target_audience IS NOT NULL THEN (
                        SELECT c.course_name 
                        FROM courses c 
                        WHERE c.id = JSON_UNQUOTE(JSON_EXTRACT(a.target_audience, '$.courses[0]'))
                    )
                    ELSE 'Unknown'
                END as context,
                CASE 
                    WHEN a.is_global = 1 THEN 'General'
                    ELSE 'Course'
                END as announcement_type
            FROM announcements a
            LEFT JOIN users u ON a.author_id = u.id
            WHERE (a.is_global = 1
               OR (a.target_audience IS NOT NULL AND JSON_SEARCH(a.target_audience, 'one', ?) IS NOT NULL))
               AND (a.read_by IS NULL OR JSON_SEARCH(a.read_by, 'one', ?) IS NULL)
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id, $user_id]);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Admin or other roles - get all announcements (only unread ones)
        $stmt = $db->prepare("
            SELECT 
                a.id, a.title, a.content, a.created_at, a.is_global, a.target_audience,
                CONCAT(u.first_name, ' ', u.last_name) as author_name,
                u.first_name, u.last_name,
                CASE 
                    WHEN a.is_global = 1 THEN 'General Announcement'
                    WHEN a.target_audience IS NOT NULL THEN (
                        SELECT c.course_name 
                        FROM courses c 
                        WHERE c.id = JSON_UNQUOTE(JSON_EXTRACT(a.target_audience, '$.courses[0]'))
                    )
                    ELSE 'Unknown'
                END as context,
                CASE 
                    WHEN a.is_global = 1 THEN 'General'
                    ELSE 'Course'
                END as announcement_type
            FROM announcements a
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.read_by IS NULL OR JSON_SEARCH(a.read_by, 'one', ?) IS NULL
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Format announcements for display
    $formatted_announcements = [];
    foreach ($announcements as $ann) {
        $formatted_announcements[] = [
            'id' => $ann['id'],
            'title' => $ann['title'],
            'content' => $ann['content'],
            'preview' => substr($ann['content'], 0, 100) . (strlen($ann['content']) > 100 ? '...' : ''),
            'created_at' => date('M j, Y g:i A', strtotime($ann['created_at'])),
            'context' => $ann['context'],
            'announcement_type' => $ann['announcement_type'],
            'author_name' => $ann['author_name'] ?? 'Unknown User',
            'first_name' => $ann['first_name'] ?? '',
            'last_name' => $ann['last_name'] ?? ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($formatted_announcements),
        'announcements' => $formatted_announcements
    ]);
    
} catch (Exception $e) {
    error_log("Error in navbar_announcements.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
