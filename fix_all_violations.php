<?php
// Script to automatically fix regular students assigned to multiple sections
// This will keep them in the first section and remove them from others

require_once 'config/database.php';

echo "<h2>Fixing Student Section Assignment Violations</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .error { background-color: #ffebee; border-left: 4px solid #f44336; padding: 10px; margin: 10px 0; }
    .warning { background-color: #fff3e0; border-left: 4px solid #ff9800; padding: 10px; margin: 10px 0; }
    .success { background-color: #e8f5e8; border-left: 4px solid #4caf50; padding: 10px; margin: 10px 0; }
    .info { background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 10px; margin: 10px 0; }
</style>";

try {
    $db->beginTransaction();
    
    // Get all sections with their students
    $stmt = $db->prepare("
        SELECT s.id, s.section_name, s.year_level, s.students
        FROM sections s 
        WHERE s.students IS NOT NULL AND s.students != '[]' AND s.students != ''
        ORDER BY s.id
    ");
    $stmt->execute();
    $sections = $stmt->fetchAll();

    // Create a map of student assignments
    $student_assignments = [];
    $section_info = [];

    foreach ($sections as $section) {
        $section_info[$section['id']] = [
            'name' => $section['section_name'],
            'year' => $section['year_level']
        ];

        $students = json_decode($section['students'], true) ?: [];
        foreach ($students as $student_id) {
            if (!isset($student_assignments[$student_id])) {
                $student_assignments[$student_id] = [];
            }
            $student_assignments[$student_id][] = $section['id'];
        }
    }

    // Get student details
    $student_ids = array_keys($student_assignments);
    if (empty($student_ids)) {
        echo "<div class='warning'>No student assignments found.</div>";
        exit;
    }

    $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, is_irregular, identifier
        FROM users 
        WHERE id IN ($placeholders) AND role = 'student'
        ORDER BY last_name, first_name
    ");
    $stmt->execute($student_ids);
    $students = $stmt->fetchAll();

    // Create student lookup
    $student_lookup = [];
    foreach ($students as $student) {
        $student_lookup[$student['id']] = $student;
    }

    // Find violations
    $violations = [];
    foreach ($student_assignments as $student_id => $section_ids) {
        if (!isset($student_lookup[$student_id])) {
            continue;
        }

        $student = $student_lookup[$student_id];
        $is_irregular = $student['is_irregular'];
        $section_count = count($section_ids);

        if (!$is_irregular && $section_count > 1) {
            $violations[] = [
                'student' => $student,
                'sections' => $section_ids
            ];
        }
    }

    if (empty($violations)) {
        echo "<div class='success'>No violations found! All regular students are properly assigned to only one section.</div>";
        $db->rollBack();
        exit;
    }

    echo "<div class='info'>Found " . count($violations) . " violations. Starting fix process...</div>";

    $fixed_count = 0;
    $errors = [];

    foreach ($violations as $violation) {
        $student = $violation['student'];
        $sections = $violation['sections'];
        
        // Keep student in the first section (lowest ID)
        $keep_section = min($sections);
        $remove_sections = array_diff($sections, [$keep_section]);
        
        echo "<div class='info'>";
        echo "Fixing: {$student['last_name']}, {$student['first_name']} (ID: {$student['identifier']})<br>";
        echo "Keeping in section: " . $section_info[$keep_section]['name'] . " (ID: {$keep_section})<br>";
        echo "Removing from sections: ";
        foreach ($remove_sections as $section_id) {
            echo $section_info[$section_id]['name'] . " (ID: {$section_id}) ";
        }
        echo "</div>";

        // Remove student from other sections
        foreach ($remove_sections as $section_id) {
            try {
                // Get current students in this section
                $stmt = $db->prepare("SELECT students FROM sections WHERE id = ?");
                $stmt->execute([$section_id]);
                $section = $stmt->fetch();
                
                if ($section && $section['students']) {
                    $current_students = json_decode($section['students'], true) ?: [];
                    $key = array_search($student['id'], $current_students);
                    
                    if ($key !== false) {
                        unset($current_students[$key]);
                        $students_json = json_encode(array_values($current_students));
                        
                        $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
                        $stmt->execute([$students_json, $section_id]);
                        
                        echo "<div class='success'>✓ Removed from section: " . $section_info[$section_id]['name'] . "</div>";
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Error removing {$student['last_name']}, {$student['first_name']} from section {$section_id}: " . $e->getMessage();
                echo "<div class='error'>✗ Error removing from section: " . $section_info[$section_id]['name'] . " - " . $e->getMessage() . "</div>";
            }
        }
        
        $fixed_count++;
    }

    if (empty($errors)) {
        $db->commit();
        echo "<div class='success'>";
        echo "<h3>✅ Fix Complete!</h3>";
        echo "Successfully fixed {$fixed_count} violations.<br>";
        echo "All regular students are now assigned to only one section each.<br>";
        echo "<a href='check_student_sections.php'>Verify the fix</a>";
        echo "</div>";
    } else {
        $db->rollBack();
        echo "<div class='error'>";
        echo "<h3>❌ Fix Failed!</h3>";
        echo "Errors occurred during the fix process. Changes have been rolled back.<br>";
        echo "<strong>Errors:</strong><br>";
        foreach ($errors as $error) {
            echo "• " . $error . "<br>";
        }
        echo "</div>";
    }

} catch (Exception $e) {
    $db->rollBack();
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}
?>
