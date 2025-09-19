<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('admin');

$attempt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$attempt_id) {
    echo '<div class="alert alert-danger">Invalid attempt ID.</div>';
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
        c.course_name,
        c.course_code,
        u.first_name,
        u.last_name,
        u.email,
        u.username,
        'N/A' as module_title
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON aa.student_id = u.id
    WHERE aa.id = ?
");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
    echo '<div class="alert alert-danger">Assessment attempt not found.</div>';
    exit();
}

// Get student's answers for this attempt
$stmt = $db->prepare("
    SELECT 
        aqa.*,
        aq.question_text,
        aq.question_type,
        aq.points
    FROM assessment_question_answers aqa
    JOIN assessment_questions aq ON aqa.question_id = aq.id
    WHERE aqa.attempt_id = ?
    ORDER BY aq.question_order
");
$stmt->execute([$attempt_id]);
$answers = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="fw-bold text-primary">Assessment Information</h6>
        <dl class="row">
            <dt class="col-sm-4">Assessment:</dt>
            <dd class="col-sm-8"><?php echo htmlspecialchars($attempt['assessment_title']); ?></dd>
            
            <dt class="col-sm-4">Course:</dt>
            <dd class="col-sm-8"><?php echo htmlspecialchars($attempt['course_name']); ?> (<?php echo htmlspecialchars($attempt['course_code']); ?>)</dd>
            
            <dt class="col-sm-4">Module:</dt>
            <dd class="col-sm-8"><?php echo htmlspecialchars($attempt['module_title']); ?></dd>
            
            <dt class="col-sm-4">Difficulty:</dt>
            <dd class="col-sm-8">
                <span class="badge bg-<?php echo $attempt['difficulty'] === 'easy' ? 'success' : ($attempt['difficulty'] === 'medium' ? 'warning' : 'danger'); ?>">
                    <?php echo ucfirst($attempt['difficulty']); ?>
                </span>
            </dd>
            
            <dt class="col-sm-4">Time Limit:</dt>
            <dd class="col-sm-8"><?php echo $attempt['time_limit'] ? $attempt['time_limit'] . ' minutes' : 'No limit'; ?></dd>
            
            <dt class="col-sm-4">Questions:</dt>
            <dd class="col-sm-8"><?php echo $attempt['num_questions']; ?></dd>
        </dl>
    </div>
    
    <div class="col-md-6">
        <h6 class="fw-bold text-primary">Student Information</h6>
        <dl class="row">
            <dt class="col-sm-4">Name:</dt>
            <dd class="col-sm-8"><?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?></dd>
            
            <dt class="col-sm-4">Email:</dt>
            <dd class="col-sm-8"><?php echo htmlspecialchars($attempt['email']); ?></dd>
            
            <dt class="col-sm-4">Username:</dt>
            <dd class="col-sm-8"><?php echo htmlspecialchars($attempt['username']); ?></dd>
        </dl>
        
        <h6 class="fw-bold text-primary mt-3">Attempt Details</h6>
        <dl class="row">
            <dt class="col-sm-4">Status:</dt>
            <dd class="col-sm-8">
                <?php if ($attempt['status'] === 'completed'): ?>
                    <span class="badge bg-success">Completed</span>
                <?php else: ?>
                    <span class="badge bg-warning">In Progress</span>
                <?php endif; ?>
            </dd>
            
            <dt class="col-sm-4">Score:</dt>
            <dd class="col-sm-8">
                <?php if ($attempt['status'] === 'completed'): ?>
                    <span class="fw-bold <?php echo $attempt['score'] >= 70 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $attempt['score']; ?>%
                    </span>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </dd>
            
            <dt class="col-sm-4">Started:</dt>
            <dd class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($attempt['started_at'])); ?></dd>
            
            <?php if ($attempt['completed_at']): ?>
                <dt class="col-sm-4">Completed:</dt>
                <dd class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($attempt['completed_at'])); ?></dd>
                
                <?php 
                // Calculate time taken if both start and completion times exist
                $start_time = strtotime($attempt['started_at']);
                $end_time = strtotime($attempt['completed_at']);
                if ($start_time && $end_time) {
                    $time_taken_seconds = $end_time - $start_time;
                    $hours = floor($time_taken_seconds / 3600);
                    $minutes = floor(($time_taken_seconds % 3600) / 60);
                    $seconds = $time_taken_seconds % 60;
                    
                    if ($time_taken_seconds > 0) {
                        echo '<dt class="col-sm-4">Time Taken:</dt>';
                        echo '<dd class="col-sm-8">';
                        if ($hours > 0) {
                            echo sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
                        } elseif ($minutes > 0) {
                            echo sprintf('%dm %ds', $minutes, $seconds);
                        } else {
                            echo sprintf('%ds', $seconds);
                        }
                        echo '</dd>';
                    }
                }
                ?>
            <?php endif; ?>
        </dl>
    </div>
</div>

<?php if (!empty($answers)): ?>
    <hr>
    <h6 class="fw-bold text-primary">Student Answers</h6>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Question</th>
                    <th>Student's Answer</th>
                    <th>Points Earned</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($answers as $index => $answer): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($answer['question_text']); ?></div>
                            <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $answer['question_type'])); ?> • <?php echo $answer['points']; ?> points</small>
                        </td>
                        <td>
                            <?php if ($answer['student_answer']): ?>
                                <span class="text-primary"><?php echo htmlspecialchars($answer['student_answer']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">No answer</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fw-bold"><?php echo $answer['points_earned']; ?> / <?php echo $answer['points']; ?></span>
                        </td>
                        <td>
                            <?php if ($answer['is_correct']): ?>
                                <span class="badge bg-success">✓ Correct</span>
                            <?php else: ?>
                                <span class="badge bg-danger">✗ Wrong</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <hr>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No detailed answers available for this attempt.
    </div>
<?php endif; ?>

<?php if ($attempt['assessment_description']): ?>
    <hr>
    <h6 class="fw-bold text-primary">Assessment Description</h6>
    <p class="text-muted"><?php echo nl2br(htmlspecialchars($attempt['assessment_description'])); ?></p>
<?php endif; ?> 