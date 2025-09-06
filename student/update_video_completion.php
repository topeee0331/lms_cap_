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
    }
} else {
    $stmt = $pdo->prepare("INSERT INTO video_views (student_id, video_id, completion_percentage, watched_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $video_id, $completion]);
}
echo 'ok'; 