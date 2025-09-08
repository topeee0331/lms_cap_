<?php
/**
 * Year Level Validation Helper
 */

function validateStudentYearLevel($student_id, $target_section_id, $pdo) {
    // Get student's basic info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        return ["valid" => false, "message" => "Student not found"];
    }
    
    // Get target section's year level
    $stmt = $pdo->prepare("SELECT year_level, section_name FROM sections WHERE id = ?");
    $stmt->execute([$target_section_id]);
    $section = $stmt->fetch();
    
    if (!$section) {
        return ["valid" => false, "message" => "Section not found"];
    }
    
    // Get student's current year level from their existing section assignments
    $stmt = $pdo->prepare("
        SELECT s.year_level, s.section_name
        FROM sections s 
        WHERE JSON_SEARCH(s.students, 'one', ?) IS NOT NULL 
        ORDER BY s.year_level DESC 
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $current_section = $stmt->fetch();
    
    $student_year = $current_section ? $current_section["year_level"] : 1; // Default to 1st year if no current assignment
    $section_year = $section["year_level"];
    
    // Validation rules
    if ($current_section && $student_year != $section_year) {
        return [
            "valid" => false, 
            "message" => "Cannot assign {$student["first_name"]} {$student["last_name"]} (Year {$student_year}) to {$section["section_name"]} (Year {$section_year}). Student is already assigned to a different year level section ({$current_section["section_name"]}).",
            "student_year" => $student_year,
            "section_year" => $section_year
        ];
    }
    
    return [
        "valid" => true, 
        "message" => "Assignment is valid",
        "student_year" => $student_year,
        "section_year" => $section_year
    ];
}

function getYearLevelOptions($current_year = null) {
    $options = [
        1 => "1st Year",
        2 => "2nd Year", 
        3 => "3rd Year",
        4 => "4th Year"
    ];
    
    if ($current_year) {
        return $options[$current_year] ?? "Unknown";
    }
    
    return $options;
}
?>