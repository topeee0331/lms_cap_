<?php
// Script to check for logical errors in student section assignments
// Regular students should only be in 1 section, irregular students can be in multiple

require_once 'config/database.php';

echo "<h2>Student Section Assignment Analysis</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .error { background-color: #ffebee; border-left: 4px solid #f44336; padding: 10px; margin: 10px 0; }
    .warning { background-color: #fff3e0; border-left: 4px solid #ff9800; padding: 10px; margin: 10px 0; }
    .success { background-color: #e8f5e8; border-left: 4px solid #4caf50; padding: 10px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .regular-error { background-color: #ffcdd2; }
    .irregular-ok { background-color: #c8e6c9; }
</style>";

try {
    // Get all sections with their students
    $stmt = $db->prepare("
        SELECT s.id, s.section_name, s.year_level, s.students, ap.academic_year, ap.semester_name
        FROM sections s 
        LEFT JOIN academic_periods ap ON s.academic_period_id = ap.id 
        WHERE s.students IS NOT NULL AND s.students != '[]' AND s.students != ''
        ORDER BY s.academic_period_id DESC, s.year_level, s.section_name
    ");
    $stmt->execute();
    $sections = $stmt->fetchAll();

    // Create a map of student assignments
    $student_assignments = [];
    $section_info = [];

    foreach ($sections as $section) {
        $section_info[$section['id']] = [
            'name' => $section['section_name'],
            'year' => $section['year_level'],
            'academic_year' => $section['academic_year'],
            'semester' => $section['semester_name']
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
        echo "<div class='warning'>No student assignments found in any sections.</div>";
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

    // Analyze assignments
    $regular_violations = [];
    $irregular_multi_section = [];
    $valid_assignments = [];

    foreach ($student_assignments as $student_id => $section_ids) {
        if (!isset($student_lookup[$student_id])) {
            continue; // Skip if student not found
        }

        $student = $student_lookup[$student_id];
        $is_irregular = $student['is_irregular'];
        $section_count = count($section_ids);

        if (!$is_irregular && $section_count > 1) {
            // Regular student in multiple sections - VIOLATION
            $regular_violations[] = [
                'student' => $student,
                'sections' => $section_ids,
                'count' => $section_count
            ];
        } elseif ($is_irregular && $section_count > 1) {
            // Irregular student in multiple sections - ALLOWED
            $irregular_multi_section[] = [
                'student' => $student,
                'sections' => $section_ids,
                'count' => $section_count
            ];
        } else {
            // Valid assignment (regular in 1 section or irregular in 1+ sections)
            $valid_assignments[] = [
                'student' => $student,
                'sections' => $section_ids,
                'count' => $section_count
            ];
        }
    }

    // Display results
    echo "<h3>Analysis Summary</h3>";
    echo "<div class='success'>";
    echo "<strong>Valid Assignments:</strong> " . count($valid_assignments) . " students<br>";
    echo "<strong>Regular Students in Multiple Sections (VIOLATIONS):</strong> " . count($regular_violations) . " students<br>";
    echo "<strong>Irregular Students in Multiple Sections (ALLOWED):</strong> " . count($irregular_multi_section) . " students<br>";
    echo "</div>";

    // Show violations
    if (!empty($regular_violations)) {
        echo "<h3 style='color: #f44336;'>🚨 REGULAR STUDENTS IN MULTIPLE SECTIONS (VIOLATIONS)</h3>";
        echo "<table>";
        echo "<tr><th>Student Name</th><th>Student ID</th><th>Status</th><th>Section Count</th><th>Assigned Sections</th><th>Action</th></tr>";
        
        foreach ($regular_violations as $violation) {
            $student = $violation['student'];
            $sections = $violation['sections'];
            $section_names = [];
            
            foreach ($sections as $section_id) {
                if (isset($section_info[$section_id])) {
                    $info = $section_info[$section_id];
                    $section_names[] = "{$info['name']} (Year {$info['year']}, {$info['academic_year']} {$info['semester']})";
                }
            }
            
            echo "<tr class='regular-error'>";
            echo "<td>{$student['last_name']}, {$student['first_name']}</td>";
            echo "<td>{$student['identifier']}</td>";
            echo "<td><span style='color: #f44336; font-weight: bold;'>Regular</span></td>";
            echo "<td>{$violation['count']}</td>";
            echo "<td>" . implode('<br>', $section_names) . "</td>";
            echo "<td><a href='fix_student_sections.php?student_id={$student['id']}' style='color: #f44336;'>Fix Assignment</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Show irregular students in multiple sections (for reference)
    if (!empty($irregular_multi_section)) {
        echo "<h3 style='color: #4caf50;'>✅ IRREGULAR STUDENTS IN MULTIPLE SECTIONS (ALLOWED)</h3>";
        echo "<table>";
        echo "<tr><th>Student Name</th><th>Student ID</th><th>Status</th><th>Section Count</th><th>Assigned Sections</th></tr>";
        
        foreach ($irregular_multi_section as $assignment) {
            $student = $assignment['student'];
            $sections = $assignment['sections'];
            $section_names = [];
            
            foreach ($sections as $section_id) {
                if (isset($section_info[$section_id])) {
                    $info = $section_info[$section_id];
                    $section_names[] = "{$info['name']} (Year {$info['year']}, {$info['academic_year']} {$info['semester']})";
                }
            }
            
            echo "<tr class='irregular-ok'>";
            echo "<td>{$student['last_name']}, {$student['first_name']}</td>";
            echo "<td>{$student['identifier']}</td>";
            echo "<td><span style='color: #4caf50; font-weight: bold;'>Irregular</span></td>";
            echo "<td>{$assignment['count']}</td>";
            echo "<td>" . implode('<br>', $section_names) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Show valid assignments summary
    if (!empty($valid_assignments)) {
        echo "<h3 style='color: #4caf50;'>✅ VALID ASSIGNMENTS</h3>";
        echo "<p>Total valid assignments: " . count($valid_assignments) . " students</p>";
        
        // Count by type
        $regular_single = 0;
        $irregular_single = 0;
        $irregular_multiple = 0;
        
        foreach ($valid_assignments as $assignment) {
            $student = $assignment['student'];
            $count = $assignment['count'];
            
            if (!$student['is_irregular'] && $count == 1) {
                $regular_single++;
            } elseif ($student['is_irregular'] && $count == 1) {
                $irregular_single++;
            } elseif ($student['is_irregular'] && $count > 1) {
                $irregular_multiple++;
            }
        }
        
        echo "<ul>";
        echo "<li>Regular students in 1 section: {$regular_single}</li>";
        echo "<li>Irregular students in 1 section: {$irregular_single}</li>";
        echo "<li>Irregular students in multiple sections: {$irregular_multiple}</li>";
        echo "</ul>";
    }

    // Generate fix script if there are violations
    if (!empty($regular_violations)) {
        echo "<h3>🔧 Fix Script</h3>";
        echo "<div class='warning'>";
        echo "<strong>Warning:</strong> The following students need to be fixed. You can either:<br>";
        echo "1. Remove them from all but one section<br>";
        echo "2. Mark them as irregular students if they should be in multiple sections<br>";
        echo "3. Use the 'Fix Assignment' links above to handle each case individually<br>";
        echo "</div>";
        
        echo "<p><a href='fix_all_violations.php' style='background-color: #f44336; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Fix All Violations Automatically</a></p>";
    }

} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}
?>
