<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Use the global $pdo variable from database.php
global $pdo;

try {
    // Check if database connection is available
    if (!isset($pdo) || $pdo === null) {
        throw new Exception('Database connection not available');
    }
    
    $question_id = $_GET['question_id'] ?? null;
    $assessment_id = $_GET['assessment_id'] ?? null;
    
    if (!$question_id || !$assessment_id) {
        throw new Exception('Missing required parameters: question_id=' . $question_id . ', assessment_id=' . $assessment_id);
    }
    
    // Log the request for debugging
    error_log("Question attempts request: question_id=$question_id, assessment_id=$assessment_id");
    
    // Get question attempts for the specific question
    $attempts_query = "
        SELECT 
            aa.id,
            aa.student_id,
            aa.completed_at as attempted_at,
            aa.answers,
            aa.score,
            aa.max_score,
            aa.has_passed,
            aa.time_taken,
            u.first_name,
            u.last_name,
            u.username
        FROM assessment_attempts aa
        INNER JOIN users u ON aa.student_id = u.id
        WHERE aa.assessment_id = ? 
        AND aa.status = 'completed'
        AND aa.answers IS NOT NULL
        ORDER BY aa.student_id, aa.completed_at DESC
    ";
    
    $attempts_stmt = $pdo->prepare($attempts_query);
    $attempts_stmt->execute([$assessment_id]);
    $all_attempts = $attempts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the correct answer for this question from the questions table
    $question_query = "SELECT id, question_text, question_type, options, points FROM questions WHERE id = ? AND assessment_id = ?";
    $question_stmt = $pdo->prepare($question_query);
    $question_stmt->execute([$question_id, $assessment_id]);
    $target_question = $question_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_question) {
        // Get all questions for this assessment to show what's available
        $all_questions_query = "SELECT id, question_text FROM questions WHERE assessment_id = ? ORDER BY question_order";
        $all_questions_stmt = $pdo->prepare($all_questions_query);
        $all_questions_stmt->execute([$assessment_id]);
        $all_questions = $all_questions_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $available_ids = array_column($all_questions, 'id');
        throw new Exception("Question not found. Looking for: $question_id, Available: " . implode(', ', $available_ids));
    }
    
    // Extract correct answer and all options
    $options = json_decode($target_question['options'], true);
    $correct_answer = null;
    $all_options = [];
    
    if (is_array($options)) {
        foreach ($options as $option) {
            $all_options[] = [
                'text' => $option['text'],
                'is_correct' => $option['is_correct'] ?? false,
                'order' => $option['order'] ?? 0
            ];
            
            if (isset($option['is_correct']) && $option['is_correct']) {
                $correct_answer = $option['text'];
            }
        }
    }
    
    error_log("Found question: " . $target_question['question_text']);
    error_log("Correct answer: " . ($correct_answer ?? 'not found'));
    error_log("All options: " . json_encode($all_options));
    
    // Process attempts to extract answers for this specific question
    $question_attempts = [];
    
    error_log("Found " . count($all_attempts) . " total attempts for assessment $assessment_id");
    
    foreach ($all_attempts as $attempt) {
        $answers = json_decode($attempt['answers'], true);
        
        if ($answers && is_array($answers)) {
            error_log("Processing attempt " . $attempt['id'] . " with " . count($answers) . " answers");
            
            foreach ($answers as $answer) {
                // Convert both to strings for comparison since question_id might be stored differently
                $answer_question_id = (string)$answer['question_id'];
                $target_question_id = (string)$question_id;
                
                error_log("Comparing answer question_id: '$answer_question_id' with target: '$target_question_id'");
                
                if ($answer_question_id === $target_question_id) {
                    error_log("Found matching question! Student answer: " . ($answer['student_answer'] ?? 'empty'));
                    
                    $question_attempts[] = [
                        'id' => $attempt['id'],
                        'student_id' => $attempt['student_id'],
                        'first_name' => $attempt['first_name'],
                        'last_name' => $attempt['last_name'],
                        'username' => $attempt['username'],
                        'student_answer' => $answer['student_answer'] ?? '',
                        'correct_answer' => $correct_answer,
                        'attempted_at' => $attempt['attempted_at'],
                        'attempt_score' => $attempt['score'],
                        'max_score' => $attempt['max_score'],
                        'has_passed' => $attempt['has_passed'],
                        'time_taken' => $attempt['time_taken']
                    ];
                    break; // Found the answer for this question, move to next attempt
                }
            }
        }
    }
    
    error_log("Found " . count($question_attempts) . " attempts for question $question_id");
    
    echo json_encode([
        'success' => true,
        'question_id' => $question_id,
        'question_text' => $target_question['question_text'],
        'question_type' => $target_question['question_type'],
        'points' => $target_question['points'] ?? 1,
        'correct_answer' => $correct_answer,
        'options' => $all_options,
        'attempts' => $question_attempts,
        'debug' => [
            'total_attempts' => count($all_attempts),
            'question_attempts_found' => count($question_attempts)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Question attempts error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
