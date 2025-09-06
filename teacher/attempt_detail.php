<?php
$page_title = 'Attempt Detail';
require_once '../includes/header.php';
requireRole('teacher');

$attempt_id = (int)($_GET['id'] ?? 0);

// Verify teacher owns this attempt
$stmt = $db->prepare("
    SELECT aa.*, u.first_name, u.last_name, u.email, u.profile_picture,
           a.assessment_title, a.time_limit, a.difficulty,
           c.course_name, c.course_code, c.modules
    FROM assessment_attempts aa
    JOIN users u ON aa.student_id = u.id
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE aa.id = ? AND c.teacher_id = ?
");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

// Get module title from JSON (since assessments are now directly linked to courses)
$module_title = 'Module Assessment';
if ($attempt && $attempt['modules']) {
    $modules_data = json_decode($attempt['modules'], true);
    if ($modules_data) {
        // Get the first module as default, or use assessment_order to determine module
        $first_module = reset($modules_data);
        $module_title = $first_module['title'] ?? 'Module Assessment';
    }
}
$attempt['module_title'] = $module_title;

if (!$attempt) {
    redirectWithMessage('assessments.php', 'Attempt not found or access denied.', 'danger');
}

// Get questions and answers with proper correctness calculation and correct answers
$stmt = $db->prepare("
    SELECT 
        aq.id as question_id,
        aq.question_text,
        aq.question_type,
        aq.points,
        aq.question_order,
        aqa.student_answer,
        aqa.selected_option_id,
        aqa.essay_answer,
        aqa.answered_at,
        COALESCE(aqa.is_correct, 0) as is_correct,
        COALESCE(aqa.points_earned, 0) as points_earned,
        CASE 
            WHEN aq.question_type = 'multiple_choice' THEN (
                SELECT qo.option_text FROM question_options qo 
                WHERE qo.question_id = aq.id AND qo.is_correct = 1 
                LIMIT 1
            )
            WHEN aq.question_type = 'true_false' THEN (
                SELECT qo.option_text FROM question_options qo 
                WHERE qo.question_id = aq.id AND qo.is_correct = 1 
                LIMIT 1
            )
            WHEN aq.question_type = 'identification' THEN (
                SELECT qo.option_text FROM question_options qo 
                WHERE qo.question_id = aq.id AND qo.is_correct = 1 
                LIMIT 1
            )
            ELSE 'Manual grading required'
        END as correct_answer
    FROM assessment_questions aq
    LEFT JOIN assessment_question_answers aqa ON aq.id = aqa.question_id AND aqa.attempt_id = ?
    WHERE aq.assessment_id = ?
    ORDER BY aq.question_order
");
$stmt->execute([$attempt_id, $attempt['assessment_id']]);
$questions = $stmt->fetchAll();

// Get options for multiple choice questions
$question_ids = array_column($questions, 'question_id');
$options = [];
if (!empty($question_ids)) {
    $placeholders = str_repeat('?,', count($question_ids) - 1) . '?';
    $stmt = $db->prepare("
        SELECT qo.*, aq.id as question_id
        FROM question_options qo
        JOIN assessment_questions aq ON qo.question_id = aq.id
        WHERE aq.id IN ($placeholders)
        ORDER BY qo.option_order
    ");
    $stmt->execute($question_ids);
    $options_data = $stmt->fetchAll();
    
    foreach ($options_data as $option) {
        $options[$option['question_id']][] = $option;
    }
}


?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Attempt Detail</h1>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($attempt['assessment_title']); ?> • 
                        <?php echo htmlspecialchars($attempt['course_name']); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="assessment_results.php?id=<?php echo $attempt['assessment_id']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Results
                    </a>
                    <a href="assessments.php" class="btn btn-outline-primary">
                        <i class="bi bi-list me-1"></i>All Assessments
                    </a>
                </div>
            </div>
        </div>
    </div>

    

    <!-- Student and Attempt Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?php echo getProfilePictureUrl($attempt['profile_picture'] ?? null, 'large'); ?>" 
                             class="rounded-circle me-3" width="64" height="64" alt="Profile">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?></h5>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($attempt['email']); ?></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Assessment:</strong> <?php echo htmlspecialchars($attempt['assessment_title']); ?><br>
                            <strong>Module:</strong> <?php echo htmlspecialchars($attempt['module_title']); ?><br>
                            <strong>Course:</strong> <?php echo htmlspecialchars($attempt['course_name']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Started:</strong> <?php echo date('M j, Y g:i A', strtotime($attempt['started_at'])); ?><br>
                            <strong>Completed:</strong> <?php echo date('M j, Y g:i A', strtotime($attempt['completed_at'])); ?><br>
                            <strong>Duration:</strong> 
                            <?php 
                            $duration = (strtotime($attempt['completed_at']) - strtotime($attempt['started_at'])) / 60;
                            echo number_format($duration, 1) . ' minutes';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-2"><?php echo $attempt['score']; ?>%</h3>
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar bg-<?php echo $attempt['score'] >= 90 ? 'success' : ($attempt['score'] >= 70 ? 'info' : ($attempt['score'] >= 50 ? 'warning' : 'danger')); ?>" 
                             style="width: <?php echo $attempt['score']; ?>%">
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-6">
                            <strong>Status</strong><br>
                            <span class="badge bg-success">Completed</span>
                        </div>
                        <div class="col-6">
                            <strong>Difficulty</strong><br>
                            <span class="badge bg-<?php echo $attempt['difficulty'] === 'easy' ? 'success' : ($attempt['difficulty'] === 'medium' ? 'warning' : 'danger'); ?>">
                                <?php echo ucfirst($attempt['difficulty']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Questions and Answers -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Questions and Answers</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($questions)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-question-circle fs-1 text-muted mb-3"></i>
                            <h6>No Questions Found</h6>
                            <p class="text-muted">This assessment doesn't have any questions.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Question <?php echo $question['question_order']; ?></h6>
                                    <div>
                                        <span class="badge bg-secondary me-2"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                        <span class="badge bg-primary"><?php echo $question['points']; ?> points</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>Question:</strong>
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                    </div>
                                    
                                                                         <?php if ($question['question_type'] === 'multiple_choice' && isset($options[$question['question_id']])): ?>
                                         <div class="mb-3">
                                             <strong>Options:</strong>
                                             <div class="ms-3">
                                                 <?php foreach ($options[$question['question_id']] as $option): ?>
                                                     <div class="form-check">
                                                         <input class="form-check-input" type="radio" disabled 
                                                                <?php echo $question['selected_option_id'] == $option['id'] ? 'checked' : ''; ?>>
                                                         <label class="form-check-label <?php echo $option['is_correct'] ? 'fw-bold text-success' : ''; ?>">
                                                             <?php echo htmlspecialchars($option['option_text']); ?>
                                                             <?php if ($option['is_correct']): ?>
                                                                 <i class="bi bi-check-circle-fill text-success ms-1"></i>
                                                             <?php endif; ?>
                                                         </label>
                                                     </div>
                                                 <?php endforeach; ?>
                                             </div>
                                         </div>
                                         <div class="row mb-3">
                                             <div class="col-md-6">
                                                 <strong>Student Answer:</strong>
                                                 <div class="ms-3">
                                                     <?php 
                                                     $student_option = null;
                                                     foreach ($options[$question['question_id']] as $option) {
                                                         if ($option['id'] == $question['selected_option_id']) {
                                                             $student_option = $option;
                                                             break;
                                                         }
                                                     }
                                                     ?>
                                                     <?php if ($student_option): ?>
                                                         <span class="badge bg-<?php echo $student_option['is_correct'] ? 'success' : 'danger'; ?>">
                                                             <?php echo htmlspecialchars($student_option['option_text']); ?>
                                                         </span>
                                                     <?php else: ?>
                                                         <span class="badge bg-secondary">No answer</span>
                                                     <?php endif; ?>
                                                 </div>
                                             </div>
                                             <div class="col-md-6">
                                                 <strong>Correct Answer:</strong>
                                                 <div class="ms-3">
                                                     <span class="badge bg-success">
                                                         <?php echo htmlspecialchars($question['correct_answer']); ?>
                                                     </span>
                                                 </div>
                                             </div>
                                         </div>
                                     <?php elseif ($question['question_type'] === 'true_false'): ?>
                                         <div class="row mb-3">
                                             <div class="col-md-6">
                                                 <strong>Student Answer:</strong>
                                                 <div class="ms-3">
                                                     <?php 
                                                     // Get the student's selected option text
                                                     $student_option_text = 'No answer';
                                                     if ($question['selected_option_id']) {
                                                         $stmt = $db->prepare("SELECT option_text FROM question_options WHERE id = ?");
                                                         $stmt->execute([$question['selected_option_id']]);
                                                         $student_option = $stmt->fetch();
                                                         $student_option_text = $student_option ? $student_option['option_text'] : 'No answer';
                                                     }
                                                     ?>
                                                     <span class="badge bg-<?php echo $question['is_correct'] ? 'success' : 'danger'; ?>">
                                                         <?php echo htmlspecialchars($student_option_text); ?>
                                                     </span>
                                                 </div>
                                             </div>
                                             <div class="col-md-6">
                                                 <strong>Correct Answer:</strong>
                                                 <div class="ms-3">
                                                     <span class="badge bg-success">
                                                         <?php echo htmlspecialchars($question['correct_answer']); ?>
                                                     </span>
                                                 </div>
                                             </div>
                                         </div>
                                     <?php elseif ($question['question_type'] === 'essay'): ?>
                                         <div class="mb-3">
                                             <strong>Student Answer:</strong>
                                             <div class="ms-3">
                                                 <div class="alert alert-info">
                                                     <?php echo nl2br(htmlspecialchars($question['essay_answer'] ?? $question['student_answer'] ?? 'No answer provided')); ?>
                                                 </div>
                                             </div>
                                         </div>
                                         <div class="mb-3">
                                             <strong>Correct Answer:</strong>
                                             <div class="ms-3">
                                                 <span class="text-muted"><?php echo htmlspecialchars($question['correct_answer']); ?></span>
                                             </div>
                                         </div>
                                     <?php else: ?>
                                         <div class="mb-3">
                                             <strong>Student Answer:</strong>
                                             <div class="ms-3">
                                                 <div class="alert alert-info">
                                                     <?php echo nl2br(htmlspecialchars($question['student_answer'] ?? 'No answer provided')); ?>
                                                 </div>
                                             </div>
                                         </div>
                                         <div class="mb-3">
                                             <strong>Correct Answer:</strong>
                                             <div class="ms-3">
                                                 <span class="text-muted"><?php echo htmlspecialchars($question['correct_answer']); ?></span>
                                             </div>
                                         </div>
                                     <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Result:</strong>
                                            <div class="ms-3">
                                                <?php if ($question['is_correct']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Correct
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-x-circle me-1"></i>Incorrect
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Points Earned:</strong>
                                            <div class="ms-3">
                                                <span class="badge bg-<?php echo $question['points_earned'] == $question['points'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $question['points_earned']; ?> / <?php echo $question['points']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 