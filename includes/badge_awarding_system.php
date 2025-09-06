<?php
/**
 * Badge Awarding System
 * Automatically awards badges based on student achievements
 */

class BadgeAwardingSystem {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Award badges for course completion
     */
    public function checkCourseCompletionBadges($student_id) {
        $badges_awarded = [];
        
        // Get student's completed courses
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as completed_courses, AVG(final_grade) as average_grade
            FROM course_enrollments 
            WHERE student_id = ? AND is_completed = 1
        ");
        $stmt->execute([$student_id]);
        $stats = $stmt->fetch();
        
        if (!$stats) return $badges_awarded;
        
        // Check course completion badges
        $course_badges = [
            ['name' => 'First Steps', 'courses_required' => 1, 'min_grade' => 0],
            ['name' => 'Course Master', 'courses_required' => 5, 'min_grade' => 0],
            ['name' => 'Academic Excellence', 'courses_required' => 10, 'min_grade' => 85]
        ];
        
        foreach ($course_badges as $badge) {
            if ($stats['completed_courses'] >= $badge['courses_required'] && 
                $stats['average_grade'] >= $badge['min_grade']) {
                $badges_awarded[] = $this->awardBadge($student_id, $badge['name']);
            }
        }
        
        return $badges_awarded;
    }
    
    /**
     * Award badges for assessment performance
     */
    public function checkAssessmentBadges($student_id, $assessment_id, $score) {
        $badges_awarded = [];
        
        // Check high score badges
        if ($score >= 100) {
            $badges_awarded[] = $this->awardBadge($student_id, 'Perfect Score');
        } elseif ($score >= 90) {
            $badges_awarded[] = $this->awardBadge($student_id, 'High Achiever');
        }
        
        // Check consecutive high scores
        $badges_awarded = array_merge($badges_awarded, $this->checkConsecutiveHighScores($student_id));
        
        // Check assessment completion count
        $badges_awarded = array_merge($badges_awarded, $this->checkAssessmentCount($student_id));
        
        return $badges_awarded;
    }
    
    /**
     * Award badges for video completion
     */
    public function checkVideoBadges($student_id, $course_id) {
        $badges_awarded = [];
        
        // Get video completion count
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as video_count
            FROM course_enrollments ce
            JOIN courses c ON ce.course_id = c.id
            WHERE ce.student_id = ? AND ce.course_id = ?
        ");
        $stmt->execute([$student_id, $course_id]);
        $result = $stmt->fetch();
        
        if (!$result) return $badges_awarded;
        
        // Check video completion badges
        $video_badges = [
            ['name' => 'Video Learner', 'videos_required' => 10],
            ['name' => 'Knowledge Seeker', 'videos_required' => 50]
        ];
        
        foreach ($video_badges as $badge) {
            if ($result['video_count'] >= $badge['videos_required']) {
                $badges_awarded[] = $this->awardBadge($student_id, $badge['name']);
            }
        }
        
        return $badges_awarded;
    }
    
    /**
     * Award badges for login streaks
     */
    public function checkStreakBadges($student_id) {
        $badges_awarded = [];
        
        // Get current login streak (simplified - you'd need to implement proper streak tracking)
        $streak_days = $this->getLoginStreak($student_id);
        
        $streak_badges = [
            ['name' => 'Daily Learner', 'days_required' => 7],
            ['name' => 'Dedicated Student', 'days_required' => 30]
        ];
        
        foreach ($streak_badges as $badge) {
            if ($streak_days >= $badge['days_required']) {
                $badges_awarded[] = $this->awardBadge($student_id, $badge['name']);
            }
        }
        
        return $badges_awarded;
    }
    
    /**
     * Award badges for special achievements
     */
    public function checkSpecialBadges($student_id, $achievement_type, $data = []) {
        $badges_awarded = [];
        
        switch ($achievement_type) {
            case 'early_completion':
                $badges_awarded[] = $this->awardBadge($student_id, 'Early Bird');
                break;
                
            case 'fast_completion':
                if (isset($data['time_ratio']) && $data['time_ratio'] <= 0.5) {
                    $badges_awarded[] = $this->awardBadge($student_id, 'Speed Demon');
                }
                break;
                
            case 'first_attempt_success':
                $badges_awarded = array_merge($badges_awarded, $this->checkFirstAttemptSuccess($student_id));
                break;
                
            case 'module_completion':
                $badges_awarded[] = $this->awardBadge($student_id, 'Module Master');
                break;
        }
        
        return $badges_awarded;
    }
    
    /**
     * Award a specific badge to a student
     */
    private function awardBadge($student_id, $badge_name) {
        try {
            // Get badge details
            $stmt = $this->db->prepare("SELECT * FROM badges WHERE badge_name = ? AND is_active = 1");
            $stmt->execute([$badge_name]);
            $badge = $stmt->fetch();
            
            if (!$badge) {
                return null; // Badge not found
            }
            
            // Check if student already has this badge
            if ($this->hasBadge($student_id, $badge['id'])) {
                return null; // Already has this badge
            }
            
            // Award the badge
            $this->addBadgeToStudent($student_id, $badge['id']);
            
            // Create notification
            $this->createBadgeNotification($student_id, $badge);
            
            return [
                'badge_id' => $badge['id'],
                'badge_name' => $badge['badge_name'],
                'points_value' => $badge['points_value']
            ];
            
        } catch (Exception $e) {
            error_log("Error awarding badge '$badge_name' to student $student_id: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if student already has a specific badge
     */
    private function hasBadge($student_id, $badge_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM badges 
            WHERE id = ? AND JSON_SEARCH(awarded_to, 'one', ?, NULL, '$[*].student_id') IS NOT NULL
        ");
        $stmt->execute([$badge_id, $student_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Add badge to student's awarded badges
     */
    private function addBadgeToStudent($student_id, $badge_id) {
        // Get current awarded_to JSON
        $stmt = $this->db->prepare("SELECT awarded_to FROM badges WHERE id = ?");
        $stmt->execute([$badge_id]);
        $current_awarded = $stmt->fetchColumn();
        
        $awarded_array = $current_awarded ? json_decode($current_awarded, true) : [];
        
        // Add new award
        $awarded_array[] = [
            'student_id' => $student_id,
            'awarded_at' => date('Y-m-d H:i:s')
        ];
        
        // Update database
        $stmt = $this->db->prepare("UPDATE badges SET awarded_to = ? WHERE id = ?");
        $stmt->execute([json_encode($awarded_array), $badge_id]);
    }
    
    /**
     * Create notification for badge award
     */
    private function createBadgeNotification($student_id, $badge) {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, message, type, priority) 
            VALUES (?, ?, ?, 'badge', 'normal')
        ");
        
        $title = "🎉 New Badge Earned!";
        $message = "Congratulations! You've earned the '{$badge['badge_name']}' badge. {$badge['badge_description']}";
        
        $stmt->execute([$student_id, $title, $message]);
    }
    
    /**
     * Check consecutive high scores
     */
    private function checkConsecutiveHighScores($student_id) {
        $badges_awarded = [];
        
        // Get recent assessment attempts ordered by completion date
        $stmt = $this->db->prepare("
            SELECT score 
            FROM assessment_attempts 
            WHERE student_id = ? AND status = 'completed' AND score IS NOT NULL
            ORDER BY completed_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$student_id]);
        $scores = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($scores) >= 5) {
            $consecutive_high = true;
            foreach (array_slice($scores, 0, 5) as $score) {
                if ($score < 80) {
                    $consecutive_high = false;
                    break;
                }
            }
            
            if ($consecutive_high) {
                $badges_awarded[] = $this->awardBadge($student_id, 'Consistent Performer');
            }
        }
        
        return $badges_awarded;
    }
    
    /**
     * Check assessment completion count
     */
    private function checkAssessmentCount($student_id) {
        $badges_awarded = [];
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as assessment_count
            FROM assessment_attempts 
            WHERE student_id = ? AND status = 'completed'
        ");
        $stmt->execute([$student_id]);
        $count = $stmt->fetchColumn();
        
        if ($count >= 20) {
            $badges_awarded[] = $this->awardBadge($student_id, 'Assessment Warrior');
        }
        
        return $badges_awarded;
    }
    
    /**
     * Check first attempt success rate
     */
    private function checkFirstAttemptSuccess($student_id) {
        $badges_awarded = [];
        
        // Get first attempts per assessment
        $stmt = $this->db->prepare("
            SELECT assessment_id, 
                   MIN(completed_at) as first_attempt,
                   MAX(CASE WHEN completed_at = MIN(completed_at) THEN score END) as first_score
            FROM assessment_attempts 
            WHERE student_id = ? AND status = 'completed'
            GROUP BY assessment_id
            HAVING first_score >= 70
        ");
        $stmt->execute([$student_id]);
        $first_attempts = $stmt->fetchAll();
        
        if (count($first_attempts) >= 10) {
            $badges_awarded[] = $this->awardBadge($student_id, 'Assessment Ace');
        }
        
        return $badges_awarded;
    }
    
    /**
     * Get login streak (simplified implementation)
     */
    private function getLoginStreak($student_id) {
        // This is a simplified implementation
        // In a real system, you'd track daily logins in a separate table
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT DATE(last_accessed)) as streak_days
            FROM course_enrollments 
            WHERE student_id = ? 
            AND last_accessed >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    /**
     * Get all badges for a student
     */
    public function getStudentBadges($student_id) {
        $stmt = $this->db->prepare("
            SELECT b.*, 
                   JSON_UNQUOTE(JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', ?, NULL, '$[*].student_id'), '].awarded_at'))) as awarded_at
            FROM badges b
            WHERE JSON_SEARCH(b.awarded_to, 'one', ?, NULL, '$[*].student_id') IS NOT NULL
            ORDER BY awarded_at DESC
        ");
        $stmt->execute([$student_id, $student_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get badge statistics for a student
     */
    public function getStudentBadgeStats($student_id) {
        $badges = $this->getStudentBadges($student_id);
        
        $stats = [
            'total_badges' => count($badges),
            'total_points' => array_sum(array_column($badges, 'points_value')),
            'badges_by_type' => [],
            'recent_badges' => array_slice($badges, 0, 5)
        ];
        
        // Group by type
        foreach ($badges as $badge) {
            $type = $badge['badge_type'];
            if (!isset($stats['badges_by_type'][$type])) {
                $stats['badges_by_type'][$type] = 0;
            }
            $stats['badges_by_type'][$type]++;
        }
        
        return $stats;
    }
}
