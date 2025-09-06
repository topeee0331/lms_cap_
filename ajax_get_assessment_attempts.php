<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/score_calculator.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if assessment_id is provided
if (!isset($_GET['assessment_id']) || empty($_GET['assessment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid assessment ID']);
    exit();
}

$student_id = $_SESSION['user_id'];
$assessment_id = $_GET['assessment_id'];

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Verify the student has access to this assessment (is enrolled in the course)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as has_access
        FROM assessments a
        JOIN course_enrollments e ON a.course_id = e.course_id
        WHERE a.id = ? AND e.student_id = ? AND e.status = 'active'
    ");
    $stmt->execute([$assessment_id, $student_id]);
    $access_check = $stmt->fetch();

    if (!$access_check || $access_check['has_access'] == 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied to this assessment']);
        exit();
    }

    // Get all attempts for this assessment by this student
    $stmt = $pdo->prepare("
        SELECT 
            aa.id,
            aa.started_at,
            aa.completed_at,
            aa.has_passed,
            aa.has_ever_passed,
            aa.status,
            aa.score as stored_score,
            aa.max_score
        FROM assessment_attempts aa
        WHERE aa.assessment_id = ? AND aa.student_id = ? AND aa.status = 'completed'
        ORDER BY COALESCE(aa.completed_at, aa.started_at) DESC
    ");
    $stmt->execute([$assessment_id, $student_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process attempts and use stored scores when available
    $attempts_with_scores = [];
    foreach ($attempts as $attempt) {
        // Use stored score if available, otherwise calculate it
        if (isset($attempt['stored_score']) && $attempt['stored_score'] !== null) {
            $attempt['score'] = $attempt['stored_score'];
            $attempt['score_source'] = 'stored';
        } else {
            // Calculate score if not stored
            $calculated_score = calculateCorrectScore($pdo, $attempt['id']);
            $attempt['score'] = $calculated_score;
            $attempt['score_source'] = 'calculated';
        }
        
        // Remove the stored_score field to avoid confusion
        unset($attempt['stored_score']);
        $attempts_with_scores[] = $attempt;
    }

    echo json_encode([
        'success' => true,
        'attempts' => $attempts_with_scores,
        'total_attempts' => count($attempts_with_scores)
    ]);

} catch (Exception $e) {
    error_log("Error in ajax_get_assessment_attempts.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
