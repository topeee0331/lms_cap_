<?php
/**
 * Example: How to award course-specific badges when a student completes a course
 * This should be called when a student completes a course
 */

require_once 'config/database.php';
require_once 'includes/course_badge_system.php';

// Example function to handle course completion
function handleCourseCompletion($student_id, $course_id) {
    global $db;
    
    try {
        // Initialize the course badge system
        $courseBadgeSystem = new CourseBadgeSystem($db);
        
        // Award course-specific badges
        $awarded_badges = $courseBadgeSystem->awardCourseBadges($student_id, $course_id);
        
        if (!empty($awarded_badges)) {
            echo "Student $student_id earned " . count($awarded_badges) . " badges for completing course $course_id:\n";
            foreach ($awarded_badges as $badge) {
                echo "- {$badge['badge_name']} ({$badge['points_value']} points)\n";
            }
        } else {
            echo "No course-specific badges to award for this course.\n";
        }
        
        return $awarded_badges;
        
    } catch (Exception $e) {
        error_log("Error awarding course badges: " . $e->getMessage());
        return [];
    }
}

// Example usage:
// $awarded_badges = handleCourseCompletion(4, 1); // Student ID 4, Course ID 1
?>
