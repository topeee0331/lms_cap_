<?php
/**
 * Badge Awarding System
 * Automatically awards badges to students based on their achievements
 */

class BadgeSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check and award badges for a student
     */
    public function checkAndAwardBadges($student_id) {
        $awarded_badges = [];
        
        // Get all available badges
        $stmt = $this->pdo->prepare("SELECT * FROM badges ORDER BY id");
        $stmt->execute();
        $badges = $stmt->fetchAll();
        
        // Get student's current badges from the awarded_to JSON field
        $stmt = $this->pdo->prepare("SELECT id, awarded_to FROM badges WHERE awarded_to IS NOT NULL AND awarded_to != ''");
        $stmt->execute();
        $badges_with_awards = $stmt->fetchAll();
        
        $earned_badges = [];
        foreach ($badges_with_awards as $badge) {
            $awarded_to = json_decode($badge['awarded_to'], true);
            if ($awarded_to && is_array($awarded_to)) {
                foreach ($awarded_to as $award) {
                    if (isset($award['student_id']) && $award['student_id'] == $student_id) {
                        $earned_badges[] = $badge['id'];
                        break;
                    }
                }
            }
        }
        
        foreach ($badges as $badge) {
            // Skip if already earned
            if (in_array($badge['id'], $earned_badges)) {
                continue;
            }
            
            // Check if student meets criteria for this badge
            if ($this->checkBadgeCriteria($student_id, $badge)) {
                $this->awardBadge($student_id, $badge['id']);
                $awarded_badges[] = $badge;
            }
        }
        
        return $awarded_badges;
    }
    
    /**
     * Check if student meets criteria for a specific badge
     */
    private function checkBadgeCriteria($student_id, $badge) {
        switch ($badge['badge_type']) {
            case 'course_completion':
                return $this->checkCourseCompletionCriteria($student_id, $badge);
            case 'high_score':
                return $this->checkHighScoreCriteria($student_id, $badge);
            case 'participation':
                return $this->checkParticipationCriteria($student_id, $badge);
            default:
                return false;
        }
    }
    
    /**
     * Check course completion criteria
     */
    private function checkCourseCompletionCriteria($student_id, $badge) {
        $criteria = json_decode($badge['criteria'], true);
        
        if (!$criteria) {
            // Default criteria based on badge name
            if (strpos(strtolower($badge['badge_name']), 'first course') !== false) {
                return $this->getCompletedCoursesCount($student_id) >= 1;
            } elseif (strpos(strtolower($badge['badge_name']), 'course master') !== false) {
                return $this->getCompletedCoursesCount($student_id) >= 5;
            } elseif (strpos(strtolower($badge['badge_name']), 'module') !== false) {
                return $this->getCompletedModulesCount($student_id) >= 10;
            }
        } else {
            // Use JSON criteria
            if (isset($criteria['courses_completed'])) {
                return $this->getCompletedCoursesCount($student_id) >= $criteria['courses_completed'];
            }
            if (isset($criteria['modules_completed'])) {
                return $this->getCompletedModulesCount($student_id) >= $criteria['modules_completed'];
            }
        }
        
        return false;
    }
    
    /**
     * Check high score criteria
     */
    private function checkHighScoreCriteria($student_id, $badge) {
        $criteria = json_decode($badge['criteria'], true);
        
        if (!$criteria) {
            // Default criteria based on badge name
            if (strpos(strtolower($badge['badge_name']), 'perfect score') !== false) {
                return $this->hasPerfectScore($student_id);
            } elseif (strpos(strtolower($badge['badge_name']), 'high achiever') !== false) {
                return $this->getAverageScore($student_id) >= 90;
            } elseif (strpos(strtolower($badge['badge_name']), 'excellent') !== false) {
                return $this->getAverageScore($student_id) >= 85;
            }
        } else {
            // Use JSON criteria
            if (isset($criteria['average_score'])) {
                return $this->getAverageScore($student_id) >= $criteria['average_score'];
            }
            if (isset($criteria['perfect_scores'])) {
                return $this->getPerfectScoresCount($student_id) >= $criteria['perfect_scores'];
            }
        }
        
        return false;
    }
    
    /**
     * Check participation criteria
     */
    private function checkParticipationCriteria($student_id, $badge) {
        $criteria = json_decode($badge['criteria'], true);
        
        if (!$criteria) {
            // Default criteria based on badge name
            if (strpos(strtolower($badge['badge_name']), 'video watcher') !== false) {
                return $this->getWatchedVideosCount($student_id) >= 20;
            } elseif (strpos(strtolower($badge['badge_name']), 'assessment taker') !== false) {
                return $this->getAssessmentAttemptsCount($student_id) >= 3;
            } elseif (strpos(strtolower($badge['badge_name']), 'assessment master') !== false) {
                return $this->getAssessmentAttemptsCount($student_id) >= 10;
            } elseif (strpos(strtolower($badge['badge_name']), 'first assessment') !== false) {
                return $this->getAssessmentAttemptsCount($student_id) >= 1;
            } elseif (strpos(strtolower($badge['badge_name']), 'consistent learner') !== false) {
                return $this->getConsecutiveDaysCount($student_id) >= 7;
            }
        } else {
            // Use JSON criteria
            if (isset($criteria['videos_watched'])) {
                return $this->getWatchedVideosCount($student_id) >= $criteria['videos_watched'];
            }
            if (isset($criteria['assessments_taken'])) {
                return $this->getAssessmentAttemptsCount($student_id) >= $criteria['assessments_taken'];
            }
            if (isset($criteria['consecutive_days'])) {
                return $this->getConsecutiveDaysCount($student_id) >= $criteria['consecutive_days'];
            }
        }
        
        return false;
    }
    
    /**
     * Award a badge to a student
     */
    private function awardBadge($student_id, $badge_id) {
        try {
            // Get current awarded_to data
            $stmt = $this->pdo->prepare("SELECT awarded_to FROM badges WHERE id = ?");
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
                $stmt = $this->pdo->prepare("UPDATE badges SET awarded_to = ? WHERE id = ?");
                $stmt->execute([json_encode($awards), $badge_id]);
                
                return true;
            }
            
            return false; // Already awarded
        } catch (PDOException $e) {
            error_log("Error awarding badge: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get count of completed courses for a student
     */
    private function getCompletedCoursesCount($student_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT c.id) as completed_courses
            FROM courses c
            JOIN course_enrollments ce ON c.id = ce.course_id
            LEFT JOIN module_progress mp ON mp.course_id = c.id AND mp.student_id = ?
            WHERE ce.student_id = ?
            GROUP BY c.id
            HAVING JSON_LENGTH(c.modules) = COUNT(CASE WHEN mp.is_completed = 1 THEN 1 END)
        ");
        $stmt->execute([$student_id, $student_id]);
        $result = $stmt->fetchAll();
        return count($result);
    }
    
    /**
     * Get count of completed modules for a student
     */
    private function getCompletedModulesCount($student_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as completed_modules
            FROM module_progress 
            WHERE student_id = ? AND is_completed = 1
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return $result['completed_modules'] ?? 0;
    }
    
    /**
     * Get average score for a student
     */
    private function getAverageScore($student_id) {
        $stmt = $this->pdo->prepare("
            SELECT AVG(score) as average_score
            FROM assessment_attempts 
            WHERE student_id = ? AND status = 'completed'
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return $result['average_score'] ?? 0;
    }
    
    /**
     * Check if student has a perfect score
     */
    private function hasPerfectScore($student_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as perfect_scores
            FROM assessment_attempts 
            WHERE student_id = ? AND score = 100 AND status = 'completed'
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return ($result['perfect_scores'] ?? 0) > 0;
    }
    
    /**
     * Get count of perfect scores
     */
    private function getPerfectScoresCount($student_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as perfect_scores
            FROM assessment_attempts 
            WHERE student_id = ? AND score = 100 AND status = 'completed'
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return $result['perfect_scores'] ?? 0;
    }
    
    /**
     * Get count of watched videos
     */
    private function getWatchedVideosCount($student_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as watched_videos
            FROM video_views 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return $result['watched_videos'] ?? 0;
    }
    
    /**
     * Get count of assessment attempts
     */
    private function getAssessmentAttemptsCount($student_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts
            FROM assessment_attempts 
            WHERE student_id = ? AND status = 'completed'
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch();
        return $result['attempts'] ?? 0;
    }
    
    /**
     * Get consecutive days of activity
     */
    private function getConsecutiveDaysCount($student_id) {
        // This is a simplified version - you might want to implement a more sophisticated algorithm
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT DATE(activity_date)) as active_days
            FROM (
                SELECT watched_at as activity_date FROM video_views WHERE student_id = ?
                UNION
                SELECT started_at as activity_date FROM assessment_attempts WHERE student_id = ?
            ) activities
            WHERE activity_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$student_id, $student_id]);
        $result = $stmt->fetch();
        return $result['active_days'] ?? 0;
    }
    
    /**
     * Create default badges if they don't exist
     */
    public function createDefaultBadges() {
        $default_badges = [
            [
                'badge_name' => 'First Assessment',
                'badge_description' => 'Completed your first assessment',
                'badge_icon' => 'first_assessment.png',
                'badge_type' => 'participation',
                'criteria' => '{"assessments_taken": 1}'
            ],
            [
                'badge_name' => 'Assessment Taker',
                'badge_description' => 'Completed 3 assessments',
                'badge_icon' => 'assessment_taker.png',
                'badge_type' => 'participation',
                'criteria' => '{"assessments_taken": 3}'
            ],
            [
                'badge_name' => 'Assessment Master',
                'badge_description' => 'Completed 10 assessments',
                'badge_icon' => 'assessment_master.png',
                'badge_type' => 'participation',
                'criteria' => '{"assessments_taken": 10}'
            ],
            [
                'badge_name' => 'First Course Complete',
                'badge_description' => 'Completed your first course successfully',
                'badge_icon' => 'first_course.png',
                'badge_type' => 'course_completion',
                'criteria' => '{"courses_completed": 1}'
            ],
            [
                'badge_name' => 'Course Master',
                'badge_description' => 'Completed 5 courses',
                'badge_icon' => 'course_master.png',
                'badge_type' => 'course_completion',
                'criteria' => '{"courses_completed": 5}'
            ],
            [
                'badge_name' => 'Module Explorer',
                'badge_description' => 'Completed 10 modules',
                'badge_icon' => 'module_explorer.png',
                'badge_type' => 'course_completion',
                'criteria' => '{"modules_completed": 10}'
            ],
            [
                'badge_name' => 'Perfect Score',
                'badge_description' => 'Achieved a perfect score on an assessment',
                'badge_icon' => 'perfect_score.png',
                'badge_type' => 'high_score',
                'criteria' => '{"perfect_scores": 1}'
            ],
            [
                'badge_name' => 'High Achiever',
                'badge_description' => 'Maintained an average score of 90% or higher',
                'badge_icon' => 'high_achiever.png',
                'badge_type' => 'high_score',
                'criteria' => '{"average_score": 90}'
            ],
            [
                'badge_name' => 'Video Watcher',
                'badge_description' => 'Watched 20 video lessons',
                'badge_icon' => 'video_watcher.png',
                'badge_type' => 'participation',
                'criteria' => '{"videos_watched": 20}'
            ],
            [
                'badge_name' => 'Consistent Learner',
                'badge_description' => 'Maintained consistent learning for 7 consecutive days',
                'badge_icon' => 'consistent_learner.png',
                'badge_type' => 'participation',
                'criteria' => '{"consecutive_days": 7}'
            ]
        ];
        
        foreach ($default_badges as $badge) {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO badges (badge_name, badge_description, badge_icon, badge_type, criteria)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $badge['badge_name'],
                $badge['badge_description'],
                $badge['badge_icon'],
                $badge['badge_type'],
                $badge['criteria']
            ]);
        }
    }
}
?> 