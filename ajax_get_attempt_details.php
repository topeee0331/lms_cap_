<?php
require_once 'config/config.php';
require_once 'config/database.php';
requireRole('student');

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

// Debug logging
error_log("Attempt ID received: " . $attempt_id);
error_log("User ID: " . ($_SESSION['user_id'] ?? 'not set'));

if (!$attempt_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid attempt ID.'
    ]);
    exit();
}

// Get attempt details
$stmt = $db->prepare("
    SELECT 
        aa.*,
        a.assessment_title,
        a.description as assessment_description,
        a.difficulty,
        a.time_limit,
        a.num_questions,
        a.passing_rate,
        c.course_name,
        c.course_code,
        'N/A' as module_title
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE aa.id = ? AND aa.student_id = ?
");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    echo json_encode([
        'success' => false,
        'message' => 'Assessment attempt not found or access denied.'
    ]);
    exit();
}

// Get student's answers for this attempt from JSON data
$answers = $attempt['answers'] ? json_decode($attempt['answers'], true) : [];

// Process questions for display
$questions = [];
if (!empty($answers)) {
    foreach ($answers as $index => $answer) {
        $questions[] = [
            'question_number' => $index + 1,
            'question_text' => $answer['question_text'] ?? 'Question not available',
            'question_type' => $answer['question_type'] ?? 'unknown',
            'points' => $answer['points'] ?? 0,
            'student_answer' => $answer['student_answer'] ?? '',
            'correct_answer' => $answer['correct_answer'] ?? '',
            'points_earned' => $answer['points_earned'] ?? 0,
            'is_correct' => $answer['is_correct'] ?? false,
            'explanation' => $answer['explanation'] ?? ''
        ];
    }
}

// Calculate attempt statistics
$total_questions = count($questions);
$correct_answers = array_sum(array_column($questions, 'is_correct'));
$total_points = array_sum(array_column($questions, 'points'));
$earned_points = array_sum(array_column($questions, 'points_earned'));

// Calculate time taken if both start and completion times exist
$time_taken = null;
if ($attempt['started_at'] && $attempt['completed_at']) {
    $start_time = strtotime($attempt['started_at']);
    $end_time = strtotime($attempt['completed_at']);
    if ($start_time && $end_time) {
        $time_taken_seconds = $end_time - $start_time;
        $hours = floor($time_taken_seconds / 3600);
        $minutes = floor(($time_taken_seconds % 3600) / 60);
        $seconds = $time_taken_seconds % 60;
        
        if ($hours > 0) {
            $time_taken = sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            $time_taken = sprintf('%dm %ds', $minutes, $seconds);
        } else {
            $time_taken = sprintf('%ds', $seconds);
        }
    }
}

// Prepare response data
$response = [
    'success' => true,
    'attempt' => [
        'id' => $attempt['id'],
        'assessment_title' => $attempt['assessment_title'],
        'course_name' => $attempt['course_name'],
        'course_code' => $attempt['course_code'],
        'difficulty' => $attempt['difficulty'],
        'time_limit' => $attempt['time_limit'],
        'num_questions' => $attempt['num_questions'],
        'passing_rate' => $attempt['passing_rate'],
        'status' => $attempt['status'],
        'score' => $attempt['score'],
        'started_at' => $attempt['started_at'],
        'completed_at' => $attempt['completed_at'],
        'time_taken' => $time_taken,
        'total_questions' => $total_questions,
        'correct_answers' => $correct_answers,
        'total_points' => $total_points,
        'earned_points' => $earned_points,
        'has_passed' => $attempt['has_passed'] ?? false
    ],
    'questions' => $questions
];

echo json_encode($response);
?>
