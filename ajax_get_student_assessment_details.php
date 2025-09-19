<?php
require_once 'config/config.php';
require_once 'config/database.php';
requireRole('admin');

header('Content-Type: application/json');

$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$section_id && !$student_id) {
    echo json_encode(['success' => false, 'message' => 'Section ID or Student ID required']);
    exit;
}

try {
    $assessment_details = [];
    
    if ($section_id) {
        // Get all students in the section and their assessment details
        $section_students_query = "
            SELECT 
                u.id as student_id,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                u.username,
                u.email
            FROM users u
            JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
            WHERE s.id = ? AND u.role = 'student' AND u.status = 'active'
        ";
        $stmt = $db->prepare($section_students_query);
        $stmt->execute([$section_id]);
        $students = $stmt->fetchAll();
        
        foreach ($students as $student) {
            // Get recent assessment attempts for this student
            $attempts_query = "
                SELECT 
                    aa.id as attempt_id,
                    aa.score,
                    aa.status,
                    aa.created_at as started_at,
                    aa.completed_at,
                    a.assessment_title,
                    a.difficulty,
                    c.course_name,
                    c.course_code
                FROM assessment_attempts aa
                JOIN assessments a ON aa.assessment_id = a.id
                JOIN courses c ON a.course_id = c.id
                WHERE aa.student_id = ?
                ORDER BY aa.created_at DESC
                LIMIT 5
            ";
            $stmt = $db->prepare($attempts_query);
            $stmt->execute([$student['student_id']]);
            $attempts = $stmt->fetchAll();
            
            // Get detailed answers for each attempt
            foreach ($attempts as &$attempt) {
                $answers_query = "
                    SELECT 
                        aqa.*,
                        aq.question_text,
                        aq.question_type,
                        aq.points,
                        aq.options,
                        aq.correct_answer
                    FROM assessment_question_answers aqa
                    JOIN assessment_questions aq ON aqa.question_id = aq.id
                    WHERE aqa.attempt_id = ?
                    ORDER BY aq.question_order
                ";
                $stmt = $db->prepare($answers_query);
                $stmt->execute([$attempt['attempt_id']]);
                $answers = $stmt->fetchAll();
                
                // Process answers to format them better
                foreach ($answers as &$answer) {
                    $answer['formatted_answer'] = formatStudentAnswer($answer);
                    $answer['formatted_correct_answer'] = formatCorrectAnswer($answer);
                }
                
                $attempt['answers'] = $answers;
            }
            
            $student['recent_attempts'] = $attempts;
            $assessment_details[] = $student;
        }
    } else {
        // Get specific student's assessment details
        $student_query = "
            SELECT 
                u.id as student_id,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                u.username,
                u.email
            FROM users u
            WHERE u.id = ? AND u.role = 'student' AND u.status = 'active'
        ";
        $stmt = $db->prepare($student_query);
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if ($student) {
            // Get all assessment attempts for this student
            $attempts_query = "
                SELECT 
                    aa.id as attempt_id,
                    aa.score,
                    aa.status,
                    aa.created_at as started_at,
                    aa.completed_at,
                    a.assessment_title,
                    a.difficulty,
                    c.course_name,
                    c.course_code
                FROM assessment_attempts aa
                JOIN assessments a ON aa.assessment_id = a.id
                JOIN courses c ON a.course_id = c.id
                WHERE aa.student_id = ?
                ORDER BY aa.created_at DESC
            ";
            $stmt = $db->prepare($attempts_query);
            $stmt->execute([$student_id]);
            $attempts = $stmt->fetchAll();
            
            // Get detailed answers for each attempt
            foreach ($attempts as &$attempt) {
                $answers_query = "
                    SELECT 
                        aqa.*,
                        aq.question_text,
                        aq.question_type,
                        aq.points,
                        aq.options,
                        aq.correct_answer
                    FROM assessment_question_answers aqa
                    JOIN assessment_questions aq ON aqa.question_id = aq.id
                    WHERE aqa.attempt_id = ?
                    ORDER BY aq.question_order
                ";
                $stmt = $db->prepare($answers_query);
                $stmt->execute([$attempt['attempt_id']]);
                $answers = $stmt->fetchAll();
                
                // Process answers to format them better
                foreach ($answers as &$answer) {
                    $answer['formatted_answer'] = formatStudentAnswer($answer);
                    $answer['formatted_correct_answer'] = formatCorrectAnswer($answer);
                }
                
                $attempt['answers'] = $answers;
            }
            
            $student['attempts'] = $attempts;
            $assessment_details = $student;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $assessment_details
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching assessment details: ' . $e->getMessage()
    ]);
}

function formatStudentAnswer($answer) {
    $question_type = $answer['question_type'];
    $student_answer = $answer['student_answer'];
    $options = json_decode($answer['options'], true);
    
    switch ($question_type) {
        case 'multiple_choice':
            if ($student_answer !== null && $student_answer !== '') {
                $answer_indices = explode(',', $student_answer);
                $formatted_answers = [];
                foreach ($answer_indices as $index) {
                    $index = (int)$index;
                    if (isset($options[$index])) {
                        $formatted_answers[] = $options[$index];
                    }
                }
                return implode(', ', $formatted_answers);
            }
            return 'No answer provided';
            
        case 'true_false':
            return $student_answer ? ucfirst($student_answer) : 'No answer provided';
            
        case 'identification':
        case 'essay':
            return $student_answer ? $student_answer : 'No answer provided';
            
        default:
            return $student_answer ? $student_answer : 'No answer provided';
    }
}

function formatCorrectAnswer($answer) {
    $question_type = $answer['question_type'];
    $correct_answer = $answer['correct_answer'];
    $options = json_decode($answer['options'], true);
    
    switch ($question_type) {
        case 'multiple_choice':
            if ($correct_answer) {
                $answer_indices = explode(',', $correct_answer);
                $formatted_answers = [];
                foreach ($answer_indices as $index) {
                    $index = (int)$index;
                    if (isset($options[$index])) {
                        $formatted_answers[] = $options[$index];
                    }
                }
                return implode(', ', $formatted_answers);
            }
            return 'No correct answer defined';
            
        case 'true_false':
            return $correct_answer ? ucfirst($correct_answer) : 'No correct answer defined';
            
        case 'identification':
        case 'essay':
            return $correct_answer ? $correct_answer : 'No correct answer defined';
            
        default:
            return $correct_answer ? $correct_answer : 'No correct answer defined';
    }
}
?>
