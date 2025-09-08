<?php
/**
 * Leaderboard Events Handler
 * Sends real-time updates via Pusher when leaderboard data changes
 */

require_once __DIR__ . '/../config/pusher.php';

class LeaderboardEvents {
    
    /**
     * Send leaderboard update to all students
     */
    public static function sendLeaderboardUpdate($leaderboardData, $currentUserRank = null, $totalStudents = null) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'leaderboard_update',
            'leaderboard' => $leaderboardData,
            'current_user_rank' => $currentUserRank,
            'total_students' => $totalStudents,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to all students
        return PusherConfig::sendToRole('student', $data);
    }
    
    /**
     * Send score update to specific student
     */
    public static function sendScoreUpdate($studentId, $newScore, $averageScore = null, $badgeCount = null) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'score_update',
            'student_id' => $studentId,
            'new_score' => $newScore,
            'average_score' => $averageScore,
            'badge_count' => $badgeCount,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to specific user
        return PusherConfig::sendNotification($studentId, $data);
    }
    
    /**
     * Send badge awarded notification
     */
    public static function sendBadgeAwarded($studentId, $badgeName, $badgeCount) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'badge_awarded',
            'student_id' => $studentId,
            'badge_name' => $badgeName,
            'badge_count' => $badgeCount,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to specific user
        return PusherConfig::sendNotification($studentId, $data);
    }
    
    /**
     * Send general leaderboard update to all students
     */
    public static function notifyLeaderboardChange($changeType, $details = []) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'leaderboard_update',
            'change_type' => $changeType,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to all students
        return PusherConfig::sendToRole('student', $data);
    }
    
    /**
     * Calculate and send updated leaderboard after score change
     */
    public static function updateLeaderboardAfterScoreChange($studentId, $newScore) {
        try {
            require_once __DIR__ . '/../config/database.php';
            
            $db = new Database();
            $pdo = $db->getConnection();
            
            // Get updated leaderboard data
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, u.first_name, u.last_name, u.email, u.profile_picture,
                    s.section_name, s.year_level as section_year,
                    (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) as badge_count,
                    (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as completed_modules,
                    (SELECT COUNT(*) FROM course_enrollments e WHERE e.student_id = u.id AND e.status = 'active') as watched_videos,
                    (SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id) as average_score,
                    (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.score >= 70) as high_scores,
                    (
                        (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') * 10 +
                        COALESCE((SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id), 0) * 0.5 +
                        (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) * 5
                    ) as calculated_score
                FROM users u
                LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
                WHERE u.role = 'student'
                ORDER BY calculated_score DESC
            ");
            $stmt->execute();
            $leaderboard = $stmt->fetchAll();
            
            // Find student's new rank
            $studentRank = null;
            foreach ($leaderboard as $index => $student) {
                if ($student['id'] == $studentId) {
                    $studentRank = $index + 1;
                    break;
                }
            }
            
            // Send updates
            self::sendLeaderboardUpdate($leaderboard, $studentRank, count($leaderboard));
            self::sendScoreUpdate($studentId, $newScore);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error updating leaderboard after score change: " . $e->getMessage());
            return false;
        }
    }
}
?>
