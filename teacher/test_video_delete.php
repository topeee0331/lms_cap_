<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('teacher');

// Simple test to debug video deletion
echo "<h2>Video Deletion Debug Test</h2>";

// Get all videos for the current teacher
$stmt = $db->prepare('SELECT c.id, c.course_name, c.modules FROM courses c WHERE c.teacher_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$courses_data = $stmt->fetchAll();

echo "<h3>Current Videos:</h3>";
$video_count = 0;
foreach ($courses_data as $course) {
    $modules_data = json_decode($course['modules'], true);
    if (is_array($modules_data)) {
        foreach ($modules_data as $module) {
            if (isset($module['videos']) && is_array($module['videos'])) {
                foreach ($module['videos'] as $video) {
                    $video_count++;
                    echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
                    echo "<strong>Video ID:</strong> " . htmlspecialchars($video['id'] ?? 'NO ID') . "<br>";
                    echo "<strong>Title:</strong> " . htmlspecialchars($video['video_title'] ?? 'NO TITLE') . "<br>";
                    echo "<strong>Course:</strong> " . htmlspecialchars($course['course_name']) . "<br>";
                    echo "<strong>Module:</strong> " . htmlspecialchars($module['module_title'] ?? 'NO MODULE TITLE') . "<br>";
                    echo "<strong>File:</strong> " . htmlspecialchars($video['video_file'] ?? 'NO FILE') . "<br>";
                    echo "</div>";
                }
            }
        }
    }
}

echo "<p><strong>Total videos found: $video_count</strong></p>";

// Test deletion if video ID is provided
if (isset($_GET['test_delete']) && !empty($_GET['test_delete'])) {
    $test_video_id = $_GET['test_delete'];
    echo "<h3>Testing deletion of video ID: $test_video_id</h3>";
    
    $video_deleted = false;
    foreach ($courses_data as $course) {
        $modules_data = json_decode($course['modules'], true);
        if (is_array($modules_data)) {
            foreach ($modules_data as &$module) {
                if (isset($module['videos']) && is_array($module['videos'])) {
                    foreach ($module['videos'] as $index => $video) {
                        if (isset($video['id']) && $video['id'] === $test_video_id) {
                            echo "<p>Found video to delete: " . htmlspecialchars($video['video_title']) . "</p>";
                            
                            // Remove video from array
                            array_splice($module['videos'], $index, 1);
                            $video_deleted = true;
                            
                            // Update course with updated modules JSON
                            $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                            $stmt->execute([json_encode($modules_data), $course['id']]);
                            
                            echo "<p style='color: green;'><strong>Video deleted successfully!</strong></p>";
                            break 3;
                        }
                    }
                }
            }
        }
    }
    
    if (!$video_deleted) {
        echo "<p style='color: red;'><strong>Video not found!</strong></p>";
    }
    
    echo "<p><a href='test_video_delete.php'>Refresh to see updated list</a></p>";
}

echo "<p><a href='videos.php'>Back to Videos Page</a></p>";
?>
