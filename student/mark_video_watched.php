<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit('Not authorized');
}

$user_id = $_SESSION['user_id'];
$video_id = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;

if (!$video_id) {
    http_response_code(400);
    exit('Invalid video ID');
}

// Check if already viewed
$stmt = $pdo->prepare("SELECT * FROM video_views WHERE student_id = ? AND video_id = ?");
$stmt->execute([$user_id, $video_id]);

if ($stmt->rowCount() == 0) {
    $stmt = $pdo->prepare("INSERT INTO video_views (student_id, video_id, watched_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $video_id]);
    
    // Send Pusher notifications
    require_once '../config/pusher.php';
    require_once '../includes/pusher_notifications.php';
    
    // Get video and course details from JSON modules structure
    $stmt = $pdo->prepare("
        SELECT c.course_name, c.teacher_id, c.modules, u.first_name, u.last_name
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.modules LIKE ?
    ");
    $stmt->execute(['%"id":' . $video_id . '%']);
    $courseData = $stmt->fetch();
    
    $videoDetails = null;
    if ($courseData) {
        $modules = json_decode($courseData['modules'], true);
        if ($modules) {
            foreach ($modules as $module) {
                if (isset($module['videos'])) {
                    foreach ($module['videos'] as $video) {
                        if (isset($video['id']) && $video['id'] == $video_id) {
                            $videoDetails = [
                                'video_title' => $video['title'] ?? 'Unknown Video',
                                'course_name' => $courseData['course_name'],
                                'teacher_id' => $courseData['teacher_id'],
                                'first_name' => $courseData['first_name'],
                                'last_name' => $courseData['last_name']
                            ];
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    if ($videoDetails) {
        // Send notification to student
        PusherNotifications::sendVideoCompleted(
            $user_id,
            $videoDetails['video_title'],
            $videoDetails['course_name']
        );
        
        // Send notification to teacher
        PusherNotifications::sendVideoProgressToTeacher(
            $videoDetails['teacher_id'],
            $_SESSION['first_name'] . ' ' . $_SESSION['last_name'],
            $videoDetails['video_title'],
            $videoDetails['course_name']
        );
    }
}

echo 'ok'; 