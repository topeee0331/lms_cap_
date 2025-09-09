<?php
require_once 'config/database.php';

echo "Testing teacher video statistics with crc32 conversion...\n";

// Test with a sample video ID
$video_id = 'vid_test123';
$video_id_int = crc32($video_id);

echo "Original video_id: $video_id\n";
echo "Converted to int: $video_id_int\n\n";

// Test the query that was failing
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_views,
            COUNT(DISTINCT student_id) as unique_viewers,
            AVG(completion_percentage) as avg_completion,
            SUM(watch_duration) as total_watch_time
        FROM video_views 
        WHERE video_id = ?
    ");
    $stmt->execute([$video_id_int]);
    $stats = $stmt->fetch();
    
    echo "Query successful!\n";
    print_r($stats);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check what's in the table
echo "\nChecking video_views table contents:\n";
$stmt = $pdo->query("SELECT * FROM video_views WHERE video_id = $video_id_int ORDER BY viewed_at DESC LIMIT 3");
while($row = $stmt->fetch()) {
    print_r($row);
}
?>
