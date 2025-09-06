<?php
/**
 * Fix Duplicate Section Assignments
 * This script removes duplicate student assignments from sections
 */

require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "Starting duplicate section assignment cleanup...\n";

try {
    // Get all students who are in multiple sections
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, 
               GROUP_CONCAT(s.id) as section_ids,
               GROUP_CONCAT(s.section_name) as section_names,
               GROUP_CONCAT(s.academic_period_id) as academic_periods
        FROM users u
        JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        WHERE u.role = 'student'
        GROUP BY u.id
        HAVING COUNT(DISTINCT s.id) > 1
    ");
    
    $duplicates = $stmt->fetchAll();
    
    echo "Found " . count($duplicates) . " students with duplicate section assignments:\n";
    
    foreach ($duplicates as $student) {
        echo "Student: {$student['first_name']} {$student['last_name']} (ID: {$student['id']})\n";
        echo "  Currently in sections: {$student['section_names']}\n";
        echo "  Academic periods: {$student['academic_periods']}\n";
        
        // Get the most recent academic period for this student
        $section_ids = explode(',', $student['section_ids']);
        $academic_periods = explode(',', $student['academic_periods']);
        
        // Find the section with the most recent academic period
        $most_recent_section = null;
        $most_recent_period = 0;
        
        for ($i = 0; $i < count($section_ids); $i++) {
            $period_id = intval($academic_periods[$i]);
            if ($period_id > $most_recent_period) {
                $most_recent_period = $period_id;
                $most_recent_section = $section_ids[$i];
            }
        }
        
        echo "  Keeping section ID: {$most_recent_section} (Academic Period: {$most_recent_period})\n";
        
        // Remove student from all sections first
        $stmt = $pdo->prepare("UPDATE sections SET students = JSON_REMOVE(students, JSON_UNQUOTE(JSON_SEARCH(students, 'one', ?))) WHERE JSON_SEARCH(students, 'one', ?) IS NOT NULL");
        $stmt->execute([$student['id'], $student['id']]);
        
        // Add student back to the most recent section only
        $stmt = $pdo->prepare("SELECT students FROM sections WHERE id = ?");
        $stmt->execute([$most_recent_section]);
        $current_students = json_decode($stmt->fetchColumn(), true) ?? [];
        
        if (!in_array($student['id'], $current_students)) {
            $current_students[] = $student['id'];
            $stmt = $pdo->prepare("UPDATE sections SET students = ? WHERE id = ?");
            $stmt->execute([json_encode($current_students), $most_recent_section]);
        }
        
        echo "  Fixed!\n\n";
    }
    
    echo "Duplicate section assignment cleanup completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
