<?php
/**
 * Course Completion Handler
 * Handles course completion logic and badge awarding
 */

require_once 'course_badge_system.php';
require_once 'badge_system.php';

class CourseCompletionHandler {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Check if a student has completed a course and award badges
     */
    public function checkCourseCompletion($student_id, $course_id) {
        try {
            // Get course enrollment details
            $stmt = $this->db->prepare("
                SELECT ce.*, c.modules, c.course_name
                FROM course_enrollments ce
                JOIN courses c ON ce.course_id = c.id
                WHERE ce.student_id = ? AND ce.course_id = ? AND ce.is_completed = 0
            ");
            $stmt->execute([$student_id, $course_id]);
            $enrollment = $stmt->fetch();
            
            if (!$enrollment) {
                return false; // Course already completed or not found
            }
            
            // Parse course modules
            $modules_data = json_decode($enrollment['modules'] ?? '[]', true) ?: [];
            $module_progress = json_decode($enrollment['module_progress'] ?? '{}', true) ?: [];
            
            if (empty($modules_data)) {
                return false; // No modules to complete
            }
            
            // Check if all modules are completed
            $all_modules_completed = true;
            foreach ($modules_data as $module) {
                $module_id = $module['id'];
                if (!isset($module_progress[$module_id]) || 
                    !isset($module_progress[$module_id]['is_completed']) || 
                    $module_progress[$module_id]['is_completed'] != 1) {
                    $all_modules_completed = false;
                    break;
                }
            }
            
            if ($all_modules_completed) {
                // Mark course as completed
                $this->markCourseCompleted($student_id, $course_id);
                
                // Award course-specific badges
                $this->awardCourseBadges($student_id, $course_id);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error checking course completion: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark course as completed
     */
    private function markCourseCompleted($student_id, $course_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE course_enrollments 
                SET is_completed = 1, 
                    status = 'completed', 
                    completion_date = NOW(),
                    final_grade = progress_percentage
                WHERE student_id = ? AND course_id = ?
            ");
            $stmt->execute([$student_id, $course_id]);
            
            // Log completion
            error_log("Course completed: Student $student_id completed Course $course_id");
            
        } catch (Exception $e) {
            error_log("Error marking course completed: " . $e->getMessage());
        }
    }
    
    /**
     * Award course-specific badges
     */
    private function awardCourseBadges($student_id, $course_id) {
        try {
            $courseBadgeSystem = new CourseBadgeSystem($this->db);
            $awarded_badges = $courseBadgeSystem->awardCourseBadges($student_id, $course_id);
            
            if (!empty($awarded_badges)) {
                // Send notifications for awarded badges
                require_once 'pusher_notifications.php';
                
                foreach ($awarded_badges as $badge) {
                    PusherNotifications::sendBadgeEarned(
                        $student_id,
                        $badge['badge_name'],
                        $badge['badge_description']
                    );
                }
                
                // Store badges in session for display
                if (!isset($_SESSION['badges_earned'])) {
                    $_SESSION['badges_earned'] = [];
                }
                $_SESSION['badges_earned'] = array_merge(
                    $_SESSION['badges_earned'] ?? [],
                    array_column($awarded_badges, 'badge_name')
                );
                
                error_log("Awarded " . count($awarded_badges) . " course badges to student $student_id");
            }
            
        } catch (Exception $e) {
            error_log("Error awarding course badges: " . $e->getMessage());
        }
    }
    
    /**
     * Check and update course completion for a student
     * This should be called after module completion
     */
    public function updateCourseCompletion($student_id, $course_id) {
        return $this->checkCourseCompletion($student_id, $course_id);
    }
}
?>
