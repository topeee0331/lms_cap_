<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('teacher');

header('Content-Type: application/json');

$question_id = (int)($_GET['question_id'] ?? 0);

if (!$question_id) {
    echo json_encode([]);
    exit;
}

// Verify teacher owns this question
$stmt = $db->prepare("
    SELECT qo.* FROM question_options qo
    JOIN assessment_questions aq ON qo.question_id = aq.id
    JOIN assessments a ON aq.assessment_id = a.id
    JOIN course_modules cm ON a.module_id = cm.id
    JOIN courses c ON cm.course_id = c.id
    WHERE qo.question_id = ? AND c.teacher_id = ?
");
$stmt->execute([$question_id, $_SESSION['user_id']]);
$options = $stmt->fetchAll();

echo json_encode($options);
?> 