<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if PDO connection is available
if (!isset($pdo)) {
    http_response_code(500);
    exit('Database connection error');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit('Not authorized');
}

$user_id = $_SESSION['user_id'];
$video_id = isset($_POST['video_id']) ? $_POST['video_id'] : '';
$module_id = isset($_POST['module_id']) ? $_POST['module_id'] : '';
$watch_duration = isset($_POST['watch_duration']) ? (int)$_POST['watch_duration'] : 0;
$completion_percentage = isset($_POST['completion_percentage']) ? (float)$_POST['completion_percentage'] : 0;

if (!$video_id || !$module_id) {
    http_response_code(400);
    exit('Invalid video ID or module ID');
}

// Find the course and module
try {
    $stmt = $pdo->prepare("
        SELECT c.*, c.modules
        FROM courses c
        WHERE JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL
    ");
    $stmt->execute([$module_id]);
    $course = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    exit('Database error');
}

if (!$course) {
    http_response_code(404);
    exit('Course not found');
}

// Check if student is enrolled
$stmt = $pdo->prepare("
    SELECT * FROM course_enrollments 
    WHERE student_id = ? AND course_id = ? AND status = 'active'
");
$stmt->execute([$user_id, $course['id']]);

if ($stmt->rowCount() == 0) {
    http_response_code(403);
    exit('Not enrolled in this course');
}

// Parse modules and find the specific module and video
$modules_data = json_decode($course['modules'] ?? '[]', true) ?: [];
$module = null;
$video = null;

foreach ($modules_data as $mod) {
    if ($mod['id'] === $module_id) {
        $module = $mod;
        if (isset($mod['videos'])) {
            foreach ($mod['videos'] as $vid) {
                if ($vid['id'] === $video_id) {
                    $video = $vid;
                    break 2;
                }
            }
        }
        break;
    }
}

if (!$module || !$video) {
    http_response_code(404);
    exit('Video not found');
}

// Get minimum watch time requirement (convert from minutes to seconds)
$min_watch_time_minutes = $video['min_watch_time'] ?? 5; // Default 5 minutes
$min_watch_time = $min_watch_time_minutes * 60; // Convert minutes to seconds

// Check if watch duration meets requirement
if ($watch_duration < $min_watch_time) {
    http_response_code(400);
    exit('Insufficient watch time');
}

// Get current video progress
$stmt = $pdo->prepare("
    SELECT video_progress 
    FROM course_enrollments 
    WHERE student_id = ? AND course_id = ?
");
$stmt->execute([$user_id, $course['id']]);
$enrollment = $stmt->fetch();

$video_progress = [];
if ($enrollment && $enrollment['video_progress']) {
    $video_progress = json_decode($enrollment['video_progress'], true);
}

// Update video progress
$video_progress[$video_id] = [
    'is_watched' => 1,
    'watched_at' => date('Y-m-d H:i:s'),
    'watch_duration' => $watch_duration,
    'completion_percentage' => $completion_percentage
];

// Update enrollment
$stmt = $pdo->prepare("
    UPDATE course_enrollments 
    SET video_progress = ?
    WHERE student_id = ? AND course_id = ?
");
$stmt->execute([
    json_encode($video_progress),
    $user_id,
    $course['id']
]);

// Record in video_views table
// Note: video_id needs to be converted to integer for the database
// We'll use a hash of the video_id string to create a consistent integer
$video_id_int = crc32($video_id);

$stmt = $pdo->prepare("
    INSERT INTO video_views (student_id, video_id, watch_duration, completion_percentage, viewed_at) 
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE 
    watch_duration = GREATEST(watch_duration, VALUES(watch_duration)),
    completion_percentage = GREATEST(completion_percentage, VALUES(completion_percentage)),
    viewed_at = NOW()
");
$stmt->execute([$user_id, $video_id_int, $watch_duration, $completion_percentage]);

// Send Pusher notifications (with error handling)
try {
    require_once __DIR__ . '/../config/pusher.php';
    require_once __DIR__ . '/../includes/pusher_notifications.php';

    // Get video and course details for notifications
    $videoDetails = [
        'video_title' => $video['video_title'] ?? 'Unknown Video',
        'course_name' => $course['course_name'],
        'teacher_id' => $course['teacher_id'],
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? ''
    ];

    // Send notification to student
    PusherNotifications::sendVideoCompleted(
        $user_id,
        $videoDetails['video_title'],
        $videoDetails['course_name']
    );

    // Send notification to teacher
    PusherNotifications::sendVideoProgressToTeacher(
        $videoDetails['teacher_id'],
        $videoDetails['first_name'] . ' ' . $videoDetails['last_name'],
        $videoDetails['video_title'],
        $videoDetails['course_name']
    );
} catch (Exception $e) {
    // Log the error but don't fail the video marking
    error_log("Pusher notification error: " . $e->getMessage());
}

echo 'ok';
?>
