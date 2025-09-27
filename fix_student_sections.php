<?php
// Script to fix individual student section assignments

require_once 'config/database.php';

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if (!$student_id) {
    echo "<div style='color: red;'>Invalid student ID</div>";
    exit;
}

echo "<h2>Fix Student Section Assignment</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .error { background-color: #ffebee; border-left: 4px solid #f44336; padding: 10px; margin: 10px 0; }
    .warning { background-color: #fff3e0; border-left: 4px solid #ff9800; padding: 10px; margin: 10px 0; }
    .success { background-color: #e8f5e8; border-left: 4px solid #4caf50; padding: 10px; margin: 10px 0; }
    .info { background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 10px; margin: 10px 0; }
    .section-option { border: 1px solid #ddd; padding: 10px; margin: 5px 0; border-radius: 4px; }
    .section-option:hover { background-color: #f5f5f5; }
    .btn { padding: 8px 16px; margin: 5px; text-decoration: none; border-radius: 4px; display: inline-block; }
    .btn-primary { background-color: #2196f3; color: white; }
    .btn-danger { background-color: #f44336; color: white; }
    .btn-success { background-color: #4caf50; color: white; }
</style>";

try {
    // Get student details
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, is_irregular, identifier
        FROM users 
        WHERE id = ? AND role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        echo "<div class='error'>Student not found</div>";
        exit;
    }

    echo "<h3>Student: {$student['last_name']}, {$student['first_name']} (ID: {$student['identifier']})</h3>";
    echo "<p><strong>Status:</strong> " . ($student['is_irregular'] ? 'Irregular' : 'Regular') . "</p>";

    // Get all sections this student is assigned to
    $stmt = $db->prepare("
        SELECT s.id, s.section_name, s.year_level, s.students, ap.academic_year, ap.semester_name
        FROM sections s 
        LEFT JOIN academic_periods ap ON s.academic_period_id = ap.id 
        WHERE s.students IS NOT NULL AND s.students != '[]' AND s.students != ''
        ORDER BY s.academic_period_id DESC, s.year_level, s.section_name
    ");
    $stmt->execute();
    $all_sections = $stmt->fetchAll();

    $assigned_sections = [];
    foreach ($all_sections as $section) {
        $students = json_decode($section['students'], true) ?: [];
        if (in_array($student_id, $students)) {
            $assigned_sections[] = $section;
        }
    }

    if (empty($assigned_sections)) {
        echo "<div class='warning'>Student is not assigned to any sections</div>";
        exit;
    }

    echo "<h4>Currently Assigned Sections (" . count($assigned_sections) . "):</h4>";

    if (!$student['is_irregular'] && count($assigned_sections) > 1) {
        echo "<div class='error'>";
        echo "<strong>VIOLATION:</strong> Regular student assigned to multiple sections!<br>";
        echo "Please select which section to keep the student in:";
        echo "</div>";

        if (isset($_POST['keep_section'])) {
            $keep_section_id = intval($_POST['keep_section']);
            $remove_sections = array_filter($assigned_sections, function($section) use ($keep_section_id) {
                return $section['id'] != $keep_section_id;
            });

            $db->beginTransaction();
            try {
                // Remove student from other sections
                foreach ($remove_sections as $section) {
                    $current_students = json_decode($section['students'], true) ?: [];
                    $key = array_search($student_id, $current_students);
                    
                    if ($key !== false) {
                        unset($current_students[$key]);
                        $students_json = json_encode(array_values($current_students));
                        
                        $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
                        $stmt->execute([$students_json, $section['id']]);
                    }
                }

                $db->commit();
                echo "<div class='success'>";
                echo "✅ Student successfully kept in selected section and removed from others.<br>";
                echo "<a href='check_student_sections.php'>Back to Analysis</a>";
                echo "</div>";
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
            }
        }

        echo "<form method='post'>";
        foreach ($assigned_sections as $section) {
            echo "<div class='section-option'>";
            echo "<label>";
            echo "<input type='radio' name='keep_section' value='{$section['id']}' required> ";
            echo "<strong>{$section['section_name']}</strong> (Year {$section['year_level']})<br>";
            echo "Academic Period: {$section['academic_year']} {$section['semester_name']}<br>";
            echo "Section ID: {$section['id']}";
            echo "</label>";
            echo "</div>";
        }
        echo "<br><button type='submit' class='btn btn-primary'>Keep in Selected Section</button>";
        echo "</form>";

    } elseif ($student['is_irregular'] && count($assigned_sections) > 1) {
        echo "<div class='success'>";
        echo "<strong>VALID:</strong> Irregular student can be in multiple sections.";
        echo "</div>";

        foreach ($assigned_sections as $section) {
            echo "<div class='section-option'>";
            echo "<strong>{$section['section_name']}</strong> (Year {$section['year_level']})<br>";
            echo "Academic Period: {$section['academic_year']} {$section['semester_name']}<br>";
            echo "Section ID: {$section['id']}";
            echo "</div>";
        }

    } else {
        echo "<div class='success'>";
        echo "<strong>VALID:</strong> Student is properly assigned to one section.";
        echo "</div>";

        $section = $assigned_sections[0];
        echo "<div class='section-option'>";
        echo "<strong>{$section['section_name']}</strong> (Year {$section['year_level']})<br>";
        echo "Academic Period: {$section['academic_year']} {$section['semester_name']}<br>";
        echo "Section ID: {$section['id']}";
        echo "</div>";
    }

    echo "<br><a href='check_student_sections.php' class='btn btn-primary'>Back to Analysis</a>";

} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}
?>
