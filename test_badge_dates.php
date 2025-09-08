<?php
/**
 * Test Badge Dates
 * This page tests the badge date functionality
 */

require_once 'config/database.php';
require_once 'includes/badge_date_helper.php';

$db = new Database();
$pdo = $db->getConnection();

// Fix any missing badge dates first
$fixed_count = BadgeDateHelper::fixMissingBadgeDates($pdo);

echo "<h2>Badge Date Test</h2>";
echo "<p>Fixed {$fixed_count} badge entries with missing dates.</p>";

// Get a sample student
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'student' LIMIT 1");
$stmt->execute();
$student = $stmt->fetch();

if ($student) {
    echo "<h3>Testing for Student: {$student['first_name']} {$student['last_name']} (ID: {$student['id']})</h3>";
    
    // Get badges using the helper
    $badges = BadgeDateHelper::getStudentBadgesWithDates($pdo, $student['id'], 10);
    
    echo "<h4>Badges Found: " . count($badges) . "</h4>";
    
    if (empty($badges)) {
        echo "<p>No badges found for this student.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Badge Name</th><th>Raw Date</th><th>Formatted Date</th><th>JSON Data</th></tr>";
        
        foreach ($badges as $badge) {
            echo "<tr>";
            echo "<td>{$badge['badge_name']}</td>";
            echo "<td>" . ($badge['earned_at'] ?: 'NULL') . "</td>";
            echo "<td>{$badge['formatted_date']}</td>";
            echo "<td style='max-width: 300px; word-wrap: break-word;'>" . htmlspecialchars($badge['awarded_to']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
} else {
    echo "<p>No students found in the database.</p>";
}

// Test the raw query
echo "<h3>Raw Query Test</h3>";
$stmt = $pdo->prepare("
    SELECT b.badge_name, 
           JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', ?), '].awarded_at')) as earned_at
    FROM badges b
    WHERE JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL
    LIMIT 5
");
$stmt->execute([$student['id'], $student['id']]);
$raw_badges = $stmt->fetchAll();

echo "<p>Raw query returned " . count($raw_badges) . " badges:</p>";
foreach ($raw_badges as $badge) {
    echo "<p><strong>{$badge['badge_name']}</strong>: " . ($badge['earned_at'] ?: 'NULL') . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Badge Dates</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { margin: 20px 0; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <p><a href="student/dashboard.php">← Back to Dashboard</a></p>
    <p><a href="fix_badge_dates.php">Run Badge Date Fix Script</a></p>
</body>
</html>
