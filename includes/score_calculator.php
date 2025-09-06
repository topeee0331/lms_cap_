<?php
/**
 * Score Calculator Helper Functions
 * 
 * This file contains functions to calculate correct assessment scores
 * by recalculating from student_answers instead of using the incorrect
 * stored scores in assessment_attempts.score
 */

/**
 * Calculate correct score for an assessment attempt
 * 
 * @param PDO $pdo Database connection
 * @param int $attempt_id Assessment attempt ID
 * @return int Score percentage (0-100)
 */
function calculateCorrectScore($pdo, $attempt_id) {
    // Get all questions for the assessment
    $stmt = $pdo->prepare("
        SELECT q.id AS question_id, q.question_text, q.question_type, q.question_order, q.options
        FROM assessment_attempts aa
        JOIN assessments a ON aa.assessment_id = a.id
        JOIN questions q ON a.id = q.assessment_id
        WHERE aa.id = ?
        ORDER BY q.question_order
    ");
    $stmt->execute([$attempt_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($questions)) {
        return 0;
    }
    
    $correct_answers = 0;
    $total_questions = count($questions);
    
    foreach ($questions as $question) {
        // Parse options from JSON
        $options = [];
        if (!empty($question['options'])) {
            $options_data = json_decode($question['options'], true);
            if ($options_data && is_array($options_data)) {
                $options = $options_data;
            }
        }
        
        // Get student's answer from the assessment_attempts.answers JSON field
        $stmt = $pdo->prepare("SELECT answers FROM assessment_attempts WHERE id = ?");
        $stmt->execute([$attempt_id]);
        $answers_data = $stmt->fetchColumn();
        $answers = ($answers_data && $answers_data !== '') ? json_decode($answers_data, true) ?: [] : [];
        
        // Find the answer for this question
        $answer = null;
        foreach ($answers as $answer_data) {
            if ($answer_data['question_id'] == $question['question_id']) {
                $answer = $answer_data;
                break;
            }
        }
        
        if ($question['question_type'] === 'identification') {
            // For identification questions, compare student answer with correct answer text
            $student_answer = $answer['student_answer'] ?? '';
            $correct_text = null;
            foreach ($options as $option) {
                if ($option['is_correct']) {
                    $correct_text = $option['text'] ?? '';
                    break;
                }
            }
            if (!empty($student_answer) && !empty($correct_text) && 
                strtoupper(trim($student_answer)) === strtoupper(trim($correct_text))) {
                $correct_answers++;
            }
        } else {
            // For multiple choice and true/false questions
            $student_answer = $answer['student_answer'] ?? null; // This contains the option index
            $correct_option_index = null;
            foreach ($options as $idx => $option) {
                if ($option['is_correct']) {
                    $correct_option_index = $idx;
                    break;
                }
            }
            if ($student_answer == $correct_option_index) {
                $correct_answers++;
            }
        }
    }
    
    return $total_questions > 0 ? round(($correct_answers / $total_questions) * 100) : 0;
}

/**
 * Calculate average score for a student across all assessments
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id Student user ID
 * @return float Average score percentage
 */
function calculateAverageScore($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT aa.id as attempt_id
        FROM assessment_attempts aa
        WHERE aa.student_id = ? AND aa.status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($attempts)) {
        return 0;
    }
    
    $total_score = 0;
    foreach ($attempts as $attempt_id) {
        $total_score += calculateCorrectScore($pdo, $attempt_id);
    }
    
    return round($total_score / count($attempts), 1);
}

/**
 * Calculate average score for a student in a specific course
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id Student user ID
 * @param int $course_id Course ID
 * @return float Average score percentage
 */
function calculateCourseAverageScore($pdo, $user_id, $course_id) {
    $stmt = $pdo->prepare("
        SELECT aa.id as attempt_id
        FROM assessment_attempts aa
        JOIN assessments a ON aa.assessment_id = a.id
        WHERE aa.student_id = ? AND aa.status = 'completed' AND a.course_id = ?
    ");
    $stmt->execute([$user_id, $course_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($attempts)) {
        return 0;
    }
    
    $total_score = 0;
    foreach ($attempts as $attempt_id) {
        $total_score += calculateCorrectScore($pdo, $attempt_id);
    }
    
    return round($total_score / count($attempts), 1);
}

/**
 * Calculate best score for a student in a specific assessment
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id Student user ID
 * @param int $assessment_id Assessment ID
 * @return int Best score percentage
 */
function calculateBestScore($pdo, $user_id, $assessment_id) {
    $stmt = $pdo->prepare("
        SELECT id FROM assessment_attempts 
        WHERE student_id = ? AND assessment_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id, $assessment_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($attempts)) {
        return 0;
    }
    
    $best_score = 0;
    foreach ($attempts as $attempt_id) {
        $score = calculateCorrectScore($pdo, $attempt_id);
        if ($score > $best_score) {
            $best_score = $score;
        }
    }
    
    return $best_score;
}

/**
 * Calculate average score for a student in a specific assessment
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id Student user ID
 * @param int $assessment_id Assessment ID
 * @return float Average score percentage
 */
function calculateAssessmentAverageScore($pdo, $user_id, $assessment_id) {
    $stmt = $pdo->prepare("
        SELECT id FROM assessment_attempts 
        WHERE student_id = ? AND assessment_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id, $assessment_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($attempts)) {
        return 0;
    }
    
    $total_score = 0;
    foreach ($attempts as $attempt_id) {
        $total_score += calculateCorrectScore($pdo, $attempt_id);
    }
    
    return round($total_score / count($attempts), 1);
}

/**
 * Count high scores (>= 70%) for a student
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id Student user ID
 * @param int $threshold Minimum score threshold (default 70)
 * @return int Number of high scores
 */
function countHighScores($pdo, $user_id, $threshold = 70) {
    $stmt = $pdo->prepare("
        SELECT aa.id as attempt_id
        FROM assessment_attempts aa
        WHERE aa.student_id = ? AND aa.status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($attempts)) {
        return 0;
    }
    
    $high_scores = 0;
    foreach ($attempts as $attempt_id) {
        $score = calculateCorrectScore($pdo, $attempt_id);
        if ($score >= $threshold) {
            $high_scores++;
        }
    }
    
    return $high_scores;
}
?>
