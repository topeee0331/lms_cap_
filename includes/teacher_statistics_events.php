<?php
/**
 * Teacher Statistics Events Handler
 * Sends real-time updates via Pusher when student statistics change
 */

require_once __DIR__ . '/../config/pusher.php';

class TeacherStatisticsEvents {
    
    /**
     * Send statistics update to all teachers
     */
    public static function sendStatisticsUpdate($teacherId, $academicPeriodId, $statsData) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'teacher_statistics_update',
            'teacher_id' => $teacherId,
            'academic_period_id' => $academicPeriodId,
            'stats' => $statsData,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to specific teacher
        return PusherConfig::sendNotification($teacherId, $data);
    }
    
    /**
     * Send statistics update to all teachers (broadcast)
     */
    public static function broadcastStatisticsUpdate($academicPeriodId, $statsData) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'teacher_statistics_broadcast',
            'academic_period_id' => $academicPeriodId,
            'stats' => $statsData,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to all teachers
        return PusherConfig::sendToRole('teacher', $data);
    }
    
    /**
     * Send student progress update
     */
    public static function sendStudentProgressUpdate($teacherId, $studentId, $progressData) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'student_progress_update',
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'progress' => $progressData,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to specific teacher
        return PusherConfig::sendNotification($teacherId, $data);
    }
    
    /**
     * Send assessment completion notification
     */
    public static function sendAssessmentCompletion($teacherId, $studentId, $assessmentData) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'assessment_completion',
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'assessment' => $assessmentData,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to specific teacher
        return PusherConfig::sendNotification($teacherId, $data);
    }
    
    /**
     * Send enrollment update
     */
    public static function sendEnrollmentUpdate($teacherId, $studentId, $enrollmentData) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'enrollment_update',
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'enrollment' => $enrollmentData,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to specific teacher
        return PusherConfig::sendNotification($teacherId, $data);
    }
    
    /**
     * Trigger statistics refresh for a teacher
     */
    public static function triggerStatisticsRefresh($teacherId, $academicPeriodId = null) {
        if (!PusherConfig::isAvailable()) {
            return false;
        }
        
        $data = [
            'type' => 'statistics_refresh_requested',
            'teacher_id' => $teacherId,
            'academic_period_id' => $academicPeriodId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to specific teacher
        return PusherConfig::sendNotification($teacherId, $data);
    }
}
?>
