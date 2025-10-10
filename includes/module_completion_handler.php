<?php
/**
 * Module Completion Handler
 * Handles module completion logic and badge awarding
 */

require_once 'module_badge_system.php';

class ModuleCompletionHandler {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Award badges to a student for completing a specific module
     * @param int $student_id The student's user ID
     * @param string $module_id The module ID that was completed
     */
    public function awardModuleBadges($student_id, $module_id) {
        try {
            $moduleBadgeSystem = new ModuleBadgeSystem($this->db);
            $moduleBadgeSystem->awardModuleBadges($student_id, $module_id);
            
            // Get the module name for logging
            $stmt = $this->db->prepare("
                SELECT module_title FROM courses 
                WHERE JSON_CONTAINS(modules, JSON_OBJECT('id', ?))
            ");
            $stmt->execute([$module_id]);
            $result = $stmt->fetch();
            $module_name = $result ? $result['module_title'] : 'Unknown Module';
            
            error_log("Module badges processed for student $student_id completing module $module_id ($module_name)");
            
        } catch (Exception $e) {
            error_log("Error in module completion handler: " . $e->getMessage());
        }
    }
}
?>
