<?php
/**
 * Badge Date Helper
 * Provides functions to properly extract and format badge award dates
 */

class BadgeDateHelper {
    
    /**
     * Extract the awarded_at date for a specific student from a badge's awarded_to JSON
     */
    public static function getBadgeAwardDate($awarded_to_json, $student_id) {
        if (empty($awarded_to_json)) {
            return null;
        }
        
        $awarded_to = json_decode($awarded_to_json, true);
        
        if (!$awarded_to || !is_array($awarded_to)) {
            return null;
        }
        
        foreach ($awarded_to as $award) {
            if (isset($award['student_id']) && $award['student_id'] == $student_id) {
                return $award['awarded_at'] ?? null;
            }
        }
        
        return null;
    }
    
    /**
     * Format a badge award date for display
     */
    public static function formatBadgeDate($date_string) {
        if (empty($date_string) || $date_string === 'null') {
            return 'Recently earned';
        }
        
        try {
            $date = new DateTime($date_string);
            return $date->format('M j, Y');
        } catch (Exception $e) {
            return 'Recently earned';
        }
    }
    
    /**
     * Get all badges for a student with properly formatted dates
     */
    public static function getStudentBadgesWithDates($pdo, $student_id, $limit = 5) {
        $stmt = $pdo->prepare("
            SELECT b.*, b.awarded_to
            FROM badges b
            WHERE JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL
            ORDER BY b.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        $badges = $stmt->fetchAll();
        
        // Process each badge to add proper date
        foreach ($badges as &$badge) {
            $badge['earned_at'] = self::getBadgeAwardDate($badge['awarded_to'], $student_id);
            $badge['formatted_date'] = self::formatBadgeDate($badge['earned_at']);
        }
        
        // Sort by actual award date if available
        usort($badges, function($a, $b) {
            $date_a = $a['earned_at'] ? strtotime($a['earned_at']) : 0;
            $date_b = $b['earned_at'] ? strtotime($b['earned_at']) : 0;
            return $date_b - $date_a; // Most recent first
        });
        
        return $badges;
    }
    
    /**
     * Fix missing award dates for existing badges
     */
    public static function fixMissingBadgeDates($pdo) {
        $fixed_count = 0;
        
        $stmt = $pdo->prepare("SELECT id, badge_name, awarded_to FROM badges WHERE awarded_to IS NOT NULL AND awarded_to != ''");
        $stmt->execute();
        $badges = $stmt->fetchAll();
        
        foreach ($badges as $badge) {
            $awarded_to = json_decode($badge['awarded_to'], true);
            $needs_update = false;
            
            if ($awarded_to && is_array($awarded_to)) {
                foreach ($awarded_to as &$award) {
                    if (isset($award['student_id']) && (!isset($award['awarded_at']) || empty($award['awarded_at']))) {
                        // Set a default date (badge creation date)
                        $award['awarded_at'] = date('Y-m-d H:i:s');
                        $needs_update = true;
                        $fixed_count++;
                    }
                }
                
                if ($needs_update) {
                    $stmt = $pdo->prepare("UPDATE badges SET awarded_to = ? WHERE id = ?");
                    $stmt->execute([json_encode($awarded_to), $badge['id']]);
                }
            }
        }
        
        return $fixed_count;
    }
}
?>
