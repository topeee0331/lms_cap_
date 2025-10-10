<?php
/**
 * Course Badge Awarding System
 * Awards course-specific badges when students complete courses
 */

class CourseBadgeSystem {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Award course-specific badges when a student completes a course
     */
    public function awardCourseBadges($student_id, $course_id) {
        $awarded_badges = [];
        
        try {
            // Get badges assigned to this course
            $stmt = $this->db->prepare('
                SELECT cb.badge_id, b.badge_name, b.badge_description, b.badge_icon, b.points_value
                FROM course_badges cb
                JOIN badges b ON cb.badge_id = b.id
                WHERE cb.course_id = ? AND cb.is_active = 1 AND b.is_active = 1
            ');
            $stmt->execute([$course_id]);
            $course_badges = $stmt->fetchAll();
            
            if (empty($course_badges)) {
                return $awarded_badges; // No badges assigned to this course
            }
            
            // Check if student already has these badges
            foreach ($course_badges as $badge) {
                if (!$this->hasBadge($student_id, $badge['badge_id'])) {
                    // Award the badge
                    if ($this->awardBadge($student_id, $badge['badge_id'])) {
                        $awarded_badges[] = $badge;
                        
                        // Create notification
                        $this->createBadgeNotification($student_id, $badge);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error awarding course badges: " . $e->getMessage());
        }
        
        return $awarded_badges;
    }
    
    /**
     * Check if student has a specific badge
     */
    private function hasBadge($student_id, $badge_id) {
        try {
            $stmt = $this->db->prepare('
                SELECT COUNT(*) 
                FROM badges 
                WHERE id = ? AND JSON_SEARCH(awarded_to, "one", ?, NULL, "$[*].student_id") IS NOT NULL
            ');
            $stmt->execute([$badge_id, $student_id]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error checking badge: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Award a badge to a student
     */
    private function awardBadge($student_id, $badge_id) {
        try {
            // Get current awarded_to data
            $stmt = $this->db->prepare("SELECT awarded_to FROM badges WHERE id = ?");
            $stmt->execute([$badge_id]);
            $current_awarded_to = $stmt->fetchColumn();
            
            // Parse existing awards or create new array
            $awards = [];
            if ($current_awarded_to && $current_awarded_to !== '') {
                $awards = json_decode($current_awarded_to, true) ?: [];
            }
            
            // Check if student already has this badge
            $already_awarded = false;
            foreach ($awards as $award) {
                if (isset($award['student_id']) && $award['student_id'] == $student_id) {
                    $already_awarded = true;
                    break;
                }
            }
            
            if (!$already_awarded) {
                // Add new award
                $awards[] = [
                    'student_id' => $student_id,
                    'awarded_at' => date('Y-m-d H:i:s')
                ];
                
                // Update the badge with new awards
                $stmt = $this->db->prepare("UPDATE badges SET awarded_to = ? WHERE id = ?");
                $stmt->execute([json_encode($awards), $badge_id]);
                
                return true;
            }
            
            return false; // Already awarded
        } catch (Exception $e) {
            error_log("Error awarding badge: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a notification for badge award
     */
    private function createBadgeNotification($student_id, $badge) {
        try {
            // Check if notifications table exists
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'notifications'");
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Create notification
                $stmt = $this->db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                    VALUES (?, ?, ?, 'badge', 0, NOW())
                ");
                $title = "Badge Earned: {$badge['badge_name']}";
                $message = "Congratulations! You've earned the '{$badge['badge_name']}' badge for completing a course. You received {$badge['points_value']} points!";
                $stmt->execute([$student_id, $title, $message]);
            }
        } catch (Exception $e) {
            error_log("Error creating badge notification: " . $e->getMessage());
        }
    }
    
    /**
     * Get course badges for a specific course
     */
    public function getCourseBadges($course_id) {
        try {
            $stmt = $this->db->prepare('
                SELECT cb.badge_id, b.badge_name, b.badge_description, b.badge_icon, b.badge_type, b.points_value
                FROM course_badges cb
                JOIN badges b ON cb.badge_id = b.id
                WHERE cb.course_id = ? AND cb.is_active = 1 AND b.is_active = 1
                ORDER BY b.badge_name
            ');
            $stmt->execute([$course_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting course badges: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get student's course-specific badges
     */
    public function getStudentCourseBadges($student_id, $course_id) {
        try {
            $stmt = $this->db->prepare('
                SELECT b.id, b.badge_name, b.badge_description, b.badge_icon, b.points_value,
                       JSON_EXTRACT(b.awarded_to, "$[*].student_id") as awarded_students
                FROM course_badges cb
                JOIN badges b ON cb.badge_id = b.id
                WHERE cb.course_id = ? AND cb.is_active = 1 AND b.is_active = 1
                ORDER BY b.badge_name
            ');
            $stmt->execute([$course_id]);
            $badges = $stmt->fetchAll();
            
            $earned_badges = [];
            foreach ($badges as $badge) {
                $awarded_students = json_decode($badge['awarded_students'], true);
                if (in_array($student_id, $awarded_students)) {
                    $earned_badges[] = $badge;
                }
            }
            
            return $earned_badges;
        } catch (Exception $e) {
            error_log("Error getting student course badges: " . $e->getMessage());
            return [];
        }
    }
}
?>
