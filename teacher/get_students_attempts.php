<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $teacher_id = $_GET['teacher_id'] ?? null;
    $academic_period_id = $_GET['academic_period_id'] ?? null;
    
    if (!$teacher_id || !$academic_period_id) {
        throw new Exception('Missing required parameters');
    }
    
    // Get all students enrolled in courses taught by this teacher for the selected academic period
    $students_query = "
        SELECT DISTINCT 
            u.id,
            u.first_name,
            u.last_name,
            u.username,
            u.email,
            u.is_irregular,
            u.identifier,
            u.created_at
        FROM users u
        INNER JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        INNER JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        WHERE u.role = 'student' 
        AND c.teacher_id = ? 
        AND c.academic_period_id = ?
        ORDER BY u.last_name, u.first_name
    ";
    
    $students_stmt = $pdo->prepare($students_query);
    $students_stmt->execute([$teacher_id, $academic_period_id]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assessment attempts for each student
    $attempts_query = "
        SELECT 
            aa.id,
            aa.student_id,
            aa.assessment_id,
            aa.score,
            aa.has_passed,
            aa.time_taken,
            aa.completed_at as attempted_at,
            aa.status,
            a.assessment_title,
            c.course_name
        FROM assessment_attempts aa
        INNER JOIN assessments a ON aa.assessment_id = a.id
        INNER JOIN courses c ON a.course_id = c.id
        WHERE aa.student_id = ? 
        AND c.teacher_id = ? 
        AND c.academic_period_id = ?
        AND aa.status = 'completed'
        ORDER BY aa.completed_at DESC
    ";
    
    $attempts_stmt = $pdo->prepare($attempts_query);
    
    $total_attempts = 0;
    
    foreach ($students as &$student) {
        $attempts_stmt->execute([$student['id'], $teacher_id, $academic_period_id]);
        $attempts = $attempts_stmt->fetchAll(PDO::FETCH_ASSOC);
        $student['attempts'] = $attempts;
        $total_attempts += count($attempts);
    }
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total_attempts' => $total_attempts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
