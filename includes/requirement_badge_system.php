<?php
require_once __DIR__ . '/../config/database.php';

class RequirementBadgeSystem {
    private $db;
    
    public function __construct($database = null) {
        if ($database) {
            $this->db = $database;
        } else {
            global $db;
            $this->db = $db;
        }
    }
    
    /**
     * Check and award requirement-based badges for a student
     * @param int $student_id The student's user ID
     */
    public function checkRequirementBadges($student_id) {
        try {
            // Get all requirement-based badges that the student hasn't earned yet
            $stmt = $this->db->prepare("
                SELECT b.id, b.badge_name, b.badge_description, b.badge_icon, b.badge_type, 
                       b.points_value, b.requirements, b.min_requirement_value, b.category
                FROM badges b
                WHERE b.context = 'requirement' 
                AND b.is_active = 1 
                AND b.id NOT IN (
                    SELECT sb.badge_id 
                    FROM student_badges sb 
                    WHERE sb.student_id = ?
                )
                ORDER BY b.category ASC, b.min_requirement_value ASC
            ");
            $stmt->execute([$student_id]);
            $requirement_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $awarded_badges = [];
            
            foreach ($requirement_badges as $badge) {
                if ($this->checkBadgeRequirements($student_id, $badge)) {
                    $this->awardBadgeToStudent($student_id, $badge);
                    $awarded_badges[] = $badge;
                }
            }
            
            return $awarded_badges;
            
        } catch (Exception $e) {
            error_log("Error checking requirement badges: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if a student meets the requirements for a specific badge
     * @param int $student_id The student's user ID
     * @param array $badge Badge data with requirements
     * @return bool True if requirements are met
     */
    private function checkBadgeRequirements($student_id, $badge) {
        try {
            $requirements = json_decode($badge['requirements'], true);
            if (!$requirements) {
                return false;
            }
            
            // Check different types of requirements
            foreach ($requirements as $requirement_type => $requirement_value) {
                switch ($requirement_type) {
                    case 'courses_required':
                        if (!$this->checkCourseRequirement($student_id, $requirement_value)) {
                            return false;
                        }
                        break;
                        
                    case 'modules_required':
                        if (!$this->checkModuleRequirement($student_id, $requirement_value)) {
                            return false;
                        }
                        break;
                        
                    case 'assessments_required':
                        if (!$this->checkAssessmentRequirement($student_id, $requirement_value)) {
                            return false;
                        }
                        break;
                        
                    case 'min_score':
                        if (!$this->checkScoreRequirement($student_id, $requirement_value)) {
                            return false;
                        }
                        break;
                        
                    case 'discussions_required':
                        if (!$this->checkDiscussionRequirement($student_id, $requirement_value)) {
                            return false;
                        }
                        break;
                        
                    case 'streak_days':
                        if (!$this->checkStreakRequirement($student_id, $requirement_value)) {
                            return false;
                        }
                        break;
                        
                    case 'total_points':
                        if (!$this->checkPointsRequirement($student_id, $requirement_value)) {
                            return false;
                        }
                        break;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error checking badge requirements: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if student has completed required number of courses
     */
    private function checkCourseRequirement($student_id, $required_courses) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as completed_courses
            FROM course_enrollments 
            WHERE student_id = ? AND is_completed = 1
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return $result['completed_courses'] >= $required_courses;
    }
    
    /**
     * Check if student has completed required number of modules
     */
    private function checkModuleRequirement($student_id, $required_modules) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as completed_modules
            FROM course_enrollments ce
            JOIN courses c ON ce.course_id = c.id
            WHERE ce.student_id = ? AND ce.is_completed = 1
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        
        // Count modules in completed courses
        $total_modules = 0;
        $stmt = $this->db->prepare("
            SELECT modules FROM courses c
            JOIN course_enrollments ce ON c.id = ce.course_id
            WHERE ce.student_id = ? AND ce.is_completed = 1
        ");
        $stmt->execute([$student_id]);
        $courses = $stmt->fetchAll();
        
        foreach ($courses as $course) {
            $modules = json_decode($course['modules'], true);
            if ($modules) {
                $total_modules += count($modules);
            }
        }
        
        return $total_modules >= $required_modules;
    }
    
    /**
     * Check if student has completed required number of assessments
     */
    private function checkAssessmentRequirement($student_id, $required_assessments) {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT aa.assessment_id) as completed_assessments
            FROM assessment_attempts aa
            JOIN assessments a ON aa.assessment_id = a.id
            WHERE aa.student_id = ? AND aa.score >= a.passing_rate
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return $result['completed_assessments'] >= $required_assessments;
    }
    
    /**
     * Check if student has achieved minimum score requirement
     */
    private function checkScoreRequirement($student_id, $min_score) {
        $stmt = $this->db->prepare("
            SELECT AVG(aa.score) as average_score
            FROM assessment_attempts aa
            WHERE aa.student_id = ?
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return $result['average_score'] >= $min_score;
    }
    
    /**
     * Check if student has participated in required number of discussions
     */
    private function checkDiscussionRequirement($student_id, $required_discussions) {
        // This would need to be implemented based on your discussion system
        // For now, return true as placeholder
        return true;
    }
    
    /**
     * Check if student has maintained required streak
     */
    private function checkStreakRequirement($student_id, $required_days) {
        // This would need to be implemented based on your activity tracking
        // For now, return true as placeholder
        return true;
    }
    
    /**
     * Check if student has earned required total points
     */
    private function checkPointsRequirement($student_id, $required_points) {
        $stmt = $this->db->prepare("
            SELECT COALESCE(total_points, 0) as current_points
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return $result['current_points'] >= $required_points;
    }
    
    /**
     * Award a badge to a student
     */
    private function awardBadgeToStudent($student_id, $badge) {
        try {
            // Insert into student_badges table
            $stmt = $this->db->prepare("
                INSERT INTO student_badges (student_id, badge_id, awarded_at, awarded_for) 
                VALUES (?, ?, NOW(), 'requirement_met')
            ");
            $stmt->execute([$student_id, $badge['id']]);
            
            // Update student's total points
            $stmt = $this->db->prepare("
                UPDATE users 
                SET total_points = COALESCE(total_points, 0) + ? 
                WHERE id = ?
            ");
            $stmt->execute([$badge['points_value'], $student_id]);
            
            // Log the badge award
            error_log("Requirement badge awarded: Student {$student_id} earned badge '{$badge['badge_name']}' (+{$badge['points_value']} points)");
            
        } catch (Exception $e) {
            error_log("Error awarding requirement badge: " . $e->getMessage());
        }
    }
}
?>
