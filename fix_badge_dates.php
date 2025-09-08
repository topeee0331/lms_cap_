<?php
/**
 * Fix Badge Dates Script
 * This script will fix any badges that don't have proper awarded_at timestamps
 */

require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "<h2>Fixing Badge Dates</h2>";

try {
    // Get all badges with awarded_to data
    $stmt = $pdo->prepare("SELECT id, badge_name, awarded_to FROM badges WHERE awarded_to IS NOT NULL AND awarded_to != ''");
    $stmt->execute();
    $badges = $stmt->fetchAll();
    
    $fixed_count = 0;
    
    foreach ($badges as $badge) {
        $awarded_to = json_decode($badge['awarded_to'], true);
        $needs_update = false;
        
        if ($awarded_to && is_array($awarded_to)) {
            foreach ($awarded_to as &$award) {
                if (isset($award['student_id']) && (!isset($award['awarded_at']) || empty($award['awarded_at']))) {
                    // Set a default date (badge creation date or current date)
                    $award['awarded_at'] = date('Y-m-d H:i:s');
                    $needs_update = true;
                    $fixed_count++;
                }
            }
            
            if ($needs_update) {
                // Update the badge with fixed dates
                $stmt = $pdo->prepare("UPDATE badges SET awarded_to = ? WHERE id = ?");
                $stmt->execute([json_encode($awarded_to), $badge['id']]);
                echo "<p>✅ Fixed badge: {$badge['badge_name']}</p>";
            }
        }
    }
    
    echo "<h3>Summary</h3>";
    echo "<p>Fixed {$fixed_count} badge entries</p>";
    
    // Test the dashboard query
    echo "<h3>Testing Dashboard Query</h3>";
    
    // Get a sample student ID
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'student' LIMIT 1");
    $stmt->execute();
    $student = $stmt->fetch();
    
    if ($student) {
        $user_id = $student['id'];
        
        $stmt = $pdo->prepare("
            SELECT b.*, 
                   JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', ?), '].awarded_at')) as earned_at
            FROM badges b
            WHERE JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id, $user_id]);
        $test_badges = $stmt->fetchAll();
        
        echo "<p>Found " . count($test_badges) . " badges for student ID: {$user_id}</p>";
        
        foreach ($test_badges as $badge) {
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px;'>";
            echo "<strong>{$badge['badge_name']}</strong><br>";
            echo "Earned at: " . ($badge['earned_at'] ?: 'NULL') . "<br>";
            echo "Raw JSON: " . htmlspecialchars($badge['awarded_to']);
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Badge Dates</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <p><a href="student/dashboard.php">← Back to Dashboard</a></p>
</body>
</html>
