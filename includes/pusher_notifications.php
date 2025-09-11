<?php
/**
 * Pusher Notification Integration
 * Integrates Pusher with existing notification system
 */

require_once __DIR__ . '/../config/pusher.php';

class PusherNotifications {
    
    // ===== ENROLLMENT NOTIFICATIONS =====
    
    public static function sendNewEnrollmentRequest($teacherId, $courseName, $studentId) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - new enrollment request notification skipped"); 
            return false; 
        }
        
        try {
            $db = new PDO("mysql:host=localhost;dbname=lms_neust_normalized;charset=utf8mb4", "root", "", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
            $stmt = $db->prepare("SELECT first_name, last_name, identifier FROM users WHERE id = ?");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch();
            $studentName = $student ? ($student['first_name'] . ' ' . $student['last_name']) : 'A student';
            $neustStudentId = $student ? $student['identifier'] : null;
        } catch (Exception $e) {
            error_log("Database error in PusherNotifications: " . $e->getMessage());
            $studentName = 'A student';
            $neustStudentId = null;
        }
        
        $data = [
            'type' => 'new_enrollment_request',
            'course_name' => $courseName,
            'student_name' => $studentName,
            'student_id' => $studentId,
            'neust_student_id' => $neustStudentId ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($teacherId, $data);
    }
    
    public static function sendEnrollmentUpdate($userId, $courseName, $status, $reason = null) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - enrollment update notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'enrollment_update',
            'course_name' => $courseName,
            'status' => $status,
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($userId, $data);
    }
    
    // ===== ASSESSMENT NOTIFICATIONS =====
    
    public static function sendAssessmentCompleted($studentId, $assessmentTitle, $score, $courseName) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - assessment completion notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'assessment_completed',
            'assessment_title' => $assessmentTitle,
            'score' => $score,
            'course_name' => $courseName,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($studentId, $data);
    }
    
    public static function sendAssessmentResultToTeacher($teacherId, $studentName, $assessmentTitle, $score, $courseName) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - assessment result notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'student_assessment_result',
            'student_name' => $studentName,
            'assessment_title' => $assessmentTitle,
            'score' => $score,
            'course_name' => $courseName,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($teacherId, $data);
    }
    
    // ===== ANNOUNCEMENT NOTIFICATIONS =====
    
    public static function sendNewAnnouncement($announcementData) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - announcement notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'new_announcement',
            'title' => $announcementData['title'],
            'content' => $announcementData['content'],
            'course_name' => $announcementData['course_name'] ?? null,
            'author_name' => $announcementData['author_name'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to all users if no specific course, otherwise send to course students
        if (empty($announcementData['course_id'])) {
            return PusherConfig::sendToAll($data);
        } else {
            return PusherConfig::sendToRole('student', $data);
        }
    }
    
    // ===== BADGE NOTIFICATIONS =====
    
    public static function sendBadgeEarned($userId, $badgeName, $badgeDescription = null) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - badge notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'badge_earned',
            'badge_name' => $badgeName,
            'badge_description' => $badgeDescription,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($userId, $data);
    }
    
    // ===== VIDEO PROGRESS NOTIFICATIONS =====
    
    public static function sendVideoCompleted($studentId, $videoTitle, $courseName) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - video completion notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'video_completed',
            'video_title' => $videoTitle,
            'course_name' => $courseName,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($studentId, $data);
    }
    
    public static function sendVideoProgressToTeacher($teacherId, $studentName, $videoTitle, $courseName) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - video progress notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'student_video_progress',
            'student_name' => $studentName,
            'video_title' => $videoTitle,
            'course_name' => $courseName,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($teacherId, $data);
    }
    
    // ===== DASHBOARD STATISTICS UPDATES =====
    
    public static function sendDashboardStatsUpdate($userId, $role, $stats) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - dashboard stats update skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'dashboard_stats_update',
            'role' => $role,
            'stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($userId, $data);
    }
    
    // ===== LEADERBOARD UPDATES =====
    
    public static function sendLeaderboardUpdate($courseId = null, $sectionId = null) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - leaderboard update skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'leaderboard_update',
            'course_id' => $courseId,
            'section_id' => $sectionId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendToRole('student', $data);
    }
    
    // ===== COURSE UPDATES =====
    
    public static function sendCourseUpdate($courseId, $updateType, $courseName) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - course update notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'course_update',
            'course_id' => $courseId,
            'course_name' => $courseName,
            'update_type' => $updateType, // 'new_module', 'new_assessment', 'new_video', etc.
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendToRole('student', $data);
    }
    
    // ===== MODULE UPDATES =====
    
    public static function sendModuleUpdate($data) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - module update notification skipped"); 
            return false; 
        }
        
        $notificationData = [
            'type' => 'module_update',
            'module_id' => $data['module_id'],
            'module_title' => $data['module_title'],
            'module_description' => $data['module_description'],
            'course_id' => $data['course_id'],
            'course_name' => $data['course_name'],
            'module_order' => $data['module_order'],
            'is_locked' => $data['is_locked'],
            'teacher_id' => $data['teacher_id'],
            'timestamp' => date('Y-m-d H:i:s'),
            'update_type' => $data['update_type'] ?? 'edit'
        ];
        
        // Send to the teacher who made the change
        PusherConfig::sendNotification($data['teacher_id'], $notificationData);
        
        // Also send to all students enrolled in the course
        try {
            $db = new PDO("mysql:host=localhost;dbname=lms_neust_normalized;charset=utf8mb4", "root", "", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
            // Get all students enrolled in this course
            $stmt = $db->prepare("
                SELECT DISTINCT u.id 
                FROM users u 
                INNER JOIN course_enrollments e ON u.id = e.student_id 
                WHERE e.course_id = ? AND u.role = 'student'
            ");
            $stmt->execute([$data['course_id']]);
            $students = $stmt->fetchAll();
            
            // Send notification to each student
            foreach ($students as $student) {
                PusherConfig::sendNotification($student['id'], $notificationData);
            }
            
        } catch (Exception $e) {
            error_log("Database error in sendModuleUpdate: " . $e->getMessage());
        }
        
        return true;
    }
    
    // ===== MODULE LOCK UPDATES =====
    
    public static function sendModuleLockUpdate($data) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - module lock update notification skipped"); 
            return false; 
        }
        
        $notificationData = [
            'type' => 'module_lock_update',
            'module_id' => $data['module_id'],
            'module_title' => $data['module_title'],
            'course_id' => $data['course_id'],
            'course_name' => $data['course_name'],
            'is_locked' => $data['is_locked'],
            'teacher_id' => $data['teacher_id'],
            'timestamp' => date('Y-m-d H:i:s'),
            'update_type' => $data['update_type'] ?? 'lock_change'
        ];
        
        // Send to the teacher who made the change
        PusherConfig::sendNotification($data['teacher_id'], $notificationData);
        
        // Also send to all students enrolled in the course
        try {
            $db = new PDO("mysql:host=localhost;dbname=lms_neust_normalized;charset=utf8mb4", "root", "", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
            // Get all students enrolled in this course
            $stmt = $db->prepare("
                SELECT DISTINCT u.id 
                FROM users u 
                INNER JOIN course_enrollments e ON u.id = e.student_id 
                WHERE e.course_id = ? AND u.role = 'student'
            ");
            $stmt->execute([$data['course_id']]);
            $students = $stmt->fetchAll();
            
            // Send notification to each student
            foreach ($students as $student) {
                PusherConfig::sendNotification($student['id'], $notificationData);
            }
            
        } catch (Exception $e) {
            error_log("Database error in sendModuleLockUpdate: " . $e->getMessage());
        }
        
        return true;
    }
    
    // ===== MODULE PROGRESS UPDATES =====
    
    public static function sendModuleCompleted($studentId, $moduleTitle, $courseName) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - module completion notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'module_completed',
            'module_title' => $moduleTitle,
            'course_name' => $courseName,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($studentId, $data);
    }
    
    public static function sendModuleProgressToTeacher($teacherId, $studentName, $moduleTitle, $courseName) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - module progress notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'student_module_progress',
            'student_name' => $studentName,
            'module_title' => $moduleTitle,
            'course_name' => $courseName,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($teacherId, $data);
    }
    
    // ===== SYSTEM NOTIFICATIONS =====
    
    public static function sendSystemNotification($userId, $title, $message, $notificationType = 'info') {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - system notification skipped"); 
            return false; 
        }
        
        $data = [
            'type' => 'system_notification',
            'title' => $title,
            'message' => $message,
            'notification_type' => $notificationType, // 'info', 'success', 'warning', 'error'
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return PusherConfig::sendNotification($userId, $data);
    }
    
    // ===== ADMIN NOTIFICATIONS =====
    
    public static function sendAdminNotification($adminId, $title, $message, $data = []) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - admin notification skipped"); 
            return false; 
        }
        
        $notificationData = array_merge([
            'type' => 'admin_notification',
            'title' => $title,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], $data);
        
        return PusherConfig::sendNotification($adminId, $notificationData);
    }
    
    // ===== REAL-TIME DATA UPDATES =====
    
    public static function sendDataUpdate($channel, $event, $data) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - data update skipped"); 
            return false; 
        }
        
        try {
            $pusher = PusherConfig::getInstance();
            if ($pusher === null) {
                return false;
            }
            
            $result = $pusher->trigger($channel, $event, $data);
            return $result;
        } catch (Exception $e) {
            error_log("Pusher error in sendDataUpdate: " . $e->getMessage());
            return false;
        }
    }
    
    // ===== BULK NOTIFICATIONS =====
    
    public static function sendBulkNotification($userIds, $data) {
        if (!PusherConfig::isAvailable()) { 
            error_log("Pusher not available - bulk notification skipped"); 
            return false; 
        }
        
        $successCount = 0;
        foreach ($userIds as $userId) {
            if (PusherConfig::sendNotification($userId, $data)) {
                $successCount++;
            }
        }
        
        return $successCount;
    }
}

