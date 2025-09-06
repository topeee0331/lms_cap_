<?php
/**
 * Year Level Validation Helper
 */

function validateStudentYearLevel($student_id, $target_section_id, $pdo) {
    // Get student's current year level
    $stmt = $pdo->prepare("SELECT year_level, first_name, last_name FROM users WHERE id = ? AND role = 'student'");
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
    
    $student_year = $student["year_level"];
    $section_year = $section["year_level"];
    
    // Validation rules
    if ($student_year > $section_year) {
        return [
            "valid" => false, 
            "message" => "Cannot assign {$student["first_name"]} {$student["last_name"]} (Year {$student_year}) to {$section["section_name"]} (Year {$section_year}). Student is in a higher year level.",
            "student_year" => $student_year,
            "section_year" => $section_year
        ];
    }
    
    if ($student_year < $section_year - 1) {
        return [
            "valid" => false, 
            "message" => "Cannot assign {$student["first_name"]} {$student["last_name"]} (Year {$student_year}) to {$section["section_name"]} (Year {$section_year}). Student is too far behind.",
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