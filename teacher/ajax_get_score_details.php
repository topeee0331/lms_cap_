<?php
header('Content-Type: application/json');
require_once '../config/config.php';
requireRole('teacher');

// Get parameters
$student_id = (int)($_GET['student_id'] ?? 0);
$course_id = (int)($_GET['course_id'] ?? 0);
$academic_period_id = (int)($_GET['academic_period_id'] ?? 0);

// Validate parameters
if (!$student_id || !$academic_period_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Get student basic info
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.identifier
        FROM users u
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit;
    }
    
    // If course_id is provided, get course-specific data
    if ($course_id > 0) {
        // Verify teacher has access to this course
        $stmt = $db->prepare("
            SELECT c.id, c.course_name, c.course_code
            FROM courses c
            WHERE c.id = ? AND c.teacher_id = ? AND c.academic_period_id = ?
        ");
        $stmt->execute([$course_id, $_SESSION['user_id'], $academic_period_id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            echo json_encode(['success' => false, 'error' => 'Course not found or access denied']);
            exit;
        }
        
        // Get assessment statistics for this specific course
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT a.id) as total_assessments,
                COUNT(DISTINCT aa.assessment_id) as attempted_assessments,
                COUNT(DISTINCT CASE WHEN aa.score >= a.passing_rate THEN aa.assessment_id END) as passed_assessments,
                ROUND(AVG(aa.score), 2) as average_score,
                MAX(aa.score) as best_score,
                MIN(aa.score) as worst_score,
                COUNT(aa.id) as total_attempts,
                SUM(aa.score) as total_score,
                ROUND(AVG(aa.time_taken), 0) as avg_time_taken
            FROM assessments a
            LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id = ?
            WHERE a.course_id = ?
        ");
        $stmt->execute([$student_id, $course_id]);
        $stats = $stmt->fetch();
        
        // Get recent assessment attempts for this course
        $stmt = $db->prepare("
            SELECT aa.*, a.assessment_title, a.passing_rate, a.difficulty, a.time_limit
            FROM assessment_attempts aa
            JOIN assessments a ON aa.assessment_id = a.id
            WHERE aa.student_id = ? AND a.course_id = ?
            ORDER BY aa.completed_at DESC
            LIMIT 20
        ");
        $stmt->execute([$student_id, $course_id]);
        $attempts = $stmt->fetchAll();
        
    } else {
        // Get overall statistics across all teacher's courses
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT a.id) as total_assessments,
                COUNT(DISTINCT aa.assessment_id) as attempted_assessments,
                COUNT(DISTINCT CASE WHEN aa.score >= a.passing_rate THEN aa.assessment_id END) as passed_assessments,
                ROUND(AVG(aa.score), 2) as average_score,
                MAX(aa.score) as best_score,
                MIN(aa.score) as worst_score,
                COUNT(aa.id) as total_attempts,
                SUM(aa.score) as total_score,
                ROUND(AVG(aa.time_taken), 0) as avg_time_taken
            FROM assessments a
            LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id = ?
            JOIN courses c ON a.course_id = c.id
            WHERE c.teacher_id = ? AND c.academic_period_id = ?
        ");
        $stmt->execute([$student_id, $_SESSION['user_id'], $academic_period_id]);
        $stats = $stmt->fetch();
        
        // Get recent assessment attempts across all courses
        $stmt = $db->prepare("
            SELECT aa.*, a.assessment_title, a.passing_rate, a.difficulty, a.time_limit, c.course_name, c.course_code
            FROM assessment_attempts aa
            JOIN assessments a ON aa.assessment_id = a.id
            JOIN courses c ON a.course_id = c.id
            WHERE aa.student_id = ? AND c.teacher_id = ? AND c.academic_period_id = ?
            ORDER BY aa.completed_at DESC
            LIMIT 20
        ");
        $stmt->execute([$student_id, $_SESSION['user_id'], $academic_period_id]);
        $attempts = $stmt->fetchAll();
    }
    
    // Process attempts to add additional calculated fields
    foreach ($attempts as &$attempt) {
        // Calculate accuracy if answers are available
        if ($attempt['answers']) {
            $answers = json_decode($attempt['answers'], true) ?: [];
            $total_questions = count($answers);
            $correct_answers = count(array_filter($answers, function($answer) {
                return $answer['is_correct'] ?? false;
            }));
            $attempt['accuracy'] = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100, 1) : 0;
            $attempt['total_questions'] = $total_questions;
            $attempt['correct_answers'] = $correct_answers;
        } else {
            $attempt['accuracy'] = 0;
            $attempt['total_questions'] = 0;
            $attempt['correct_answers'] = 0;
        }
        
        // Format completion date
        $attempt['formatted_date'] = $attempt['completed_at'] ? date('M j, Y g:i A', strtotime($attempt['completed_at'])) : 'Unknown';
        
        // Format time taken
        $attempt['formatted_time'] = $attempt['time_taken'] ? formatTime($attempt['time_taken']) : 'N/A';
        
        // Determine score status
        $attempt['status'] = $attempt['score'] >= $attempt['passing_rate'] ? 'passed' : 'failed';
        $attempt['status_class'] = $attempt['status'] === 'passed' ? 'success' : 'danger';
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'student' => $student,
        'course_id' => $course_id,
        'academic_period_id' => $academic_period_id,
        'course' => $course ?? null,
        'stats' => $stats ?: [
            'total_assessments' => 0,
            'attempted_assessments' => 0,
            'passed_assessments' => 0,
            'average_score' => 0,
            'best_score' => 0,
            'worst_score' => 0,
            'total_attempts' => 0,
            'total_score' => 0,
            'avg_time_taken' => 0
        ],
        'attempts' => $attempts ?: []
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in ajax_get_score_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while loading score details']);
}

// Helper function to format time
function formatTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    } else {
        return sprintf('%d:%02d', $minutes, $secs);
    }
}
?>
