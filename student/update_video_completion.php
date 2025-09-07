<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit('Not authorized');
}

$user_id = $_SESSION['user_id'];
$video_id = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$completion = isset($_POST['completion_percentage']) ? (int)$_POST['completion_percentage'] : 0;
if (!$video_id || $completion < 0 || $completion > 100) {
    http_response_code(400);
    exit('Invalid input');
}

// Check if already viewed
$stmt = $pdo->prepare("SELECT * FROM video_views WHERE student_id = ? AND video_id = ?");
$stmt->execute([$user_id, $video_id]);
if ($row = $stmt->fetch()) {
    // Only update if new completion is higher
    $current = (int)($row['completion_percentage'] ?? 0);
    if ($completion > $current) {
        $stmt = $pdo->prepare("UPDATE video_views SET completion_percentage = ?, watched_at = NOW() WHERE student_id = ? AND video_id = ?");
        $stmt->execute([$completion, $user_id, $video_id]);
        
        // Send notification to teacher if completion is significant (e.g., > 80%)
        if ($completion >= 80) {
            require_once '../config/pusher.php';
            require_once '../includes/pusher_notifications.php';
            
            // Get video and course details
            $stmt = $pdo->prepare("
                SELECT c.course_name, c.teacher_id, c.modules, u.first_name, u.last_name
                FROM courses c
                JOIN users u ON c.teacher_id = u.id
                WHERE c.modules LIKE ?
            ");
            $stmt->execute(['%"id":' . $video_id . '%']);
            $courseData = $stmt->fetch();
            
            if ($courseData) {
                $modules = json_decode($courseData['modules'], true);
                if ($modules) {
                    foreach ($modules as $module) {
                        if (isset($module['videos'])) {
                            foreach ($module['videos'] as $video) {
                                if (isset($video['id']) && $video['id'] == $video_id) {
                                    PusherNotifications::sendVideoProgressToTeacher(
                                        $courseData['teacher_id'],
                                        $_SESSION['first_name'] . ' ' . $_SESSION['last_name'],
                                        $video['title'] ?? 'Unknown Video',
                                        $courseData['course_name']
                                    );
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
} else {
    $stmt = $pdo->prepare("INSERT INTO video_views (student_id, video_id, completion_percentage, watched_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $video_id, $completion]);
}
echo 'ok'; 