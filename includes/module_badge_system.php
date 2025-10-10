<?php
require_once __DIR__ . '/../config/database.php';

class ModuleBadgeSystem {
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
     * Award badges to a student for completing a specific module
     * @param int $student_id The student's user ID
     * @param string $module_id The module ID that was completed
     */
    public function awardModuleBadges($student_id, $module_id) {
        try {
            // Get badges assigned to this module
            $stmt = $this->db->prepare("
                SELECT b.id, b.badge_name, b.badge_description, b.badge_icon, b.badge_type, b.points_value
                FROM badges b
                INNER JOIN module_badges mb ON b.id = mb.badge_id
                WHERE mb.module_id = ? AND b.is_active = 1
            ");
            $stmt->execute([$module_id]);
            $module_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($module_badges)) {
                return; // No badges assigned to this module
            }
            
            // Check which badges the student already has
            $stmt = $this->db->prepare("
                SELECT badge_id FROM student_badges 
                WHERE student_id = ? AND badge_id IN (" . str_repeat('?,', count($module_badges) - 1) . "?)
            ");
            $badge_ids = array_column($module_badges, 'id');
            $stmt->execute(array_merge([$student_id], $badge_ids));
            $existing_badges = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Award new badges
            foreach ($module_badges as $badge) {
                if (!in_array($badge['id'], $existing_badges)) {
                    $this->awardBadgeToStudent($student_id, $badge);
                }
            }
            
        } catch (Exception $e) {
            error_log("Error awarding module badges: " . $e->getMessage());
        }
    }
    
    /**
     * Award a specific badge to a student
     * @param int $student_id The student's user ID
     * @param array $badge Badge data
     */
    private function awardBadgeToStudent($student_id, $badge) {
        try {
            // Insert into student_badges table
            $stmt = $this->db->prepare("
                INSERT INTO student_badges (student_id, badge_id, awarded_at, awarded_for) 
                VALUES (?, ?, NOW(), 'module_completion')
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
            error_log("Badge awarded: Student {$student_id} earned badge '{$badge['badge_name']}' (+{$badge['points_value']} points)");
            
        } catch (Exception $e) {
            error_log("Error awarding badge to student: " . $e->getMessage());
        }
    }
}
?>
