<?php
$page_title = 'Edit Assessment';
require_once '../includes/header.php';
requireRole('teacher');

$assessment_id = sanitizeInput($_GET['id'] ?? '');

// Verify teacher owns this assessment
$stmt = $db->prepare("
    SELECT a.*, c.course_name, c.course_code, c.modules
    FROM assessments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ? AND c.teacher_id = ?
");
$stmt->execute([$assessment_id, $_SESSION['user_id']]);
$assessment = $stmt->fetch();

// Get module title from the course's modules JSON
$module_title = 'Module Assessment';
if ($assessment && $assessment['modules']) {
    $modules_data = json_decode($assessment['modules'], true);
    if ($modules_data) {
        // Find the module that contains this assessment
        foreach ($modules_data as $module) {
            if (isset($module['assessments']) && is_array($module['assessments'])) {
                foreach ($module['assessments'] as $module_assessment) {
                    if ($module_assessment['id'] === $assessment_id) {
                        $module_title = $module['module_title'] ?? 'Module Assessment';
                        break 2;
                    }
                }
            }
        }
    }
}
$assessment['module_title'] = $module_title;

if (!$assessment) {
    redirectWithMessage('assessments.php', 'Assessment not found or access denied.', 'danger');
}

$message = '';
$message_type = '';

// Handle question actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST: ' . print_r($_POST, true));
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'create_question':
                // Check if we've reached the maximum number of questions
                $stmt = $db->prepare('SELECT COUNT(*) as current_count FROM questions WHERE assessment_id = ?');
                $stmt->execute([$assessment_id]);
                $current_count = $stmt->fetch()['current_count'];
                
                if ($current_count >= $assessment['num_questions']) {
                    $message = "Cannot add more questions. Maximum of {$assessment['num_questions']} questions allowed for this assessment.";
                    $message_type = 'danger';
                } else {
                    $question_text = sanitizeInput($_POST['question_text'] ?? '');
                    $question_type = sanitizeInput($_POST['question_type'] ?? 'multiple_choice');
                    $question_type = strtolower(trim($question_type));
                    if (empty($question_type)) { $question_type = 'identification'; }
                    // Force identification if correct_answer is present and type is missing or invalid
                    if ((empty($question_type) || !in_array($question_type, ['multiple_choice', 'true_false', 'identification'])) && !empty($_POST['correct_answer'])) {
                        $question_type = 'identification';
                    }
                    $question_order = (int)($_POST['question_order'] ?? 1);
                    $points = (int)($_POST['points'] ?? 1);
                    
                    if (empty($question_text)) {
                        $message = 'Question text is required.';
                        $message_type = 'danger';
                    } elseif ($question_type === 'multiple_choice' && empty(trim($_POST['correct_option'] ?? ''))) {
                        $message = 'Correct option is required for Multiple Choice questions.';
                        $message_type = 'danger';
                    } elseif ($question_type === 'true_false' && empty(trim($_POST['correct_tf'] ?? ''))) {
                        $message = 'Correct answer is required for True/False questions.';
                        $message_type = 'danger';
                    } else {
                    // Prepare options JSON
                    $options_json = '[]';
                    if ($question_type === 'multiple_choice') {
                        $options = $_POST['options'] ?? [];
                        $correct_option = (int)($_POST['correct_option'] ?? 0);
                        $options_array = [];
                        
                        foreach ($options as $index => $option_text) {
                            if (!empty($option_text)) {
                                $options_array[] = [
                                    'text' => $option_text,
                                    'is_correct' => ($index == $correct_option) ? 1 : 0,
                                    'order' => $index + 1
                                ];
                            }
                        }
                        $options_json = json_encode($options_array);
                    } elseif ($question_type === 'true_false') {
                        $correct_tf = $_POST['correct_tf'] ?? 'true';
                        $options_array = [
                            [
                                'text' => 'True',
                                'is_correct' => $correct_tf === 'true' ? 1 : 0,
                                'order' => 1
                            ],
                            [
                                'text' => 'False',
                                'is_correct' => $correct_tf === 'false' ? 1 : 0,
                                'order' => 2
                            ]
                        ];
                        $options_json = json_encode($options_array);
                    } elseif ($question_type === 'identification') {
                        $correct_answer = $_POST['correct_answer'] ?? '';
                        $options_array = [
                            [
                                'text' => $correct_answer,
                                'is_correct' => 1,
                                'order' => 1
                            ]
                        ];
                        $options_json = json_encode($options_array);
                    }
                    
                    $stmt = $db->prepare('INSERT INTO questions (assessment_id, question_text, question_type, question_order, points, options) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$assessment_id, $question_text, $question_type, $question_order, $points, $options_json]);
                    $question_id = $db->lastInsertId();
                    
                    $message = 'Question added successfully.';
                    $message_type = 'success';
                }
                }
                break;
                
            case 'update_settings':
                $assessment_title = sanitizeInput($_POST['assessment_title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $time_limit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
                $difficulty = sanitizeInput($_POST['difficulty'] ?? 'medium');
                $num_questions = (int)($_POST['num_questions'] ?? 10);
                $passing_rate = (float)($_POST['passing_rate'] ?? 70.0);
                $attempt_limit = (int)($_POST['attempt_limit'] ?? 3);
                
                if (empty($assessment_title)) {
                    $message = 'Assessment title is required.';
                    $message_type = 'danger';
                } elseif ($passing_rate < 0 || $passing_rate > 100) {
                    $message = 'Passing rate must be between 0 and 100.';
                    $message_type = 'danger';
                } elseif ($num_questions < 1) {
                    $message = 'Number of questions must be at least 1.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('UPDATE assessments SET assessment_title = ?, description = ?, time_limit = ?, difficulty = ?, num_questions = ?, passing_rate = ?, attempt_limit = ? WHERE id = ?');
                    $stmt->execute([$assessment_title, $description, $time_limit, $difficulty, $num_questions, $passing_rate, $attempt_limit, $assessment_id]);
                    
                    // Refresh assessment data
                    $stmt = $db->prepare("SELECT a.*, c.course_name, c.course_code, c.modules FROM assessments a JOIN courses c ON a.course_id = c.id WHERE a.id = ? AND c.teacher_id = ?");
                    $stmt->execute([$assessment_id, $_SESSION['user_id']]);
                    $assessment = $stmt->fetch();
                    
                    // Get module title from the course's modules JSON
                    $module_title = 'Module Assessment';
                    if ($assessment && $assessment['modules']) {
                        $modules_data = json_decode($assessment['modules'], true);
                        if ($modules_data) {
                            // Find the module that contains this assessment
                            foreach ($modules_data as $module) {
                                if (isset($module['assessments']) && is_array($module['assessments'])) {
                                    foreach ($module['assessments'] as $module_assessment) {
                                        if ($module_assessment['id'] === $assessment_id) {
                                            $module_title = $module['module_title'] ?? 'Module Assessment';
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $assessment['module_title'] = $module_title;
                    
                    $message = 'Assessment settings updated successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'update_question':
                $question_id = (int)($_POST['question_id'] ?? 0);
                $question_text = sanitizeInput($_POST['question_text'] ?? '');
                $question_type = sanitizeInput($_POST['question_type'] ?? 'multiple_choice');
                $question_type = strtolower(trim($question_type));
                if (empty($question_type)) { $question_type = 'identification'; }
                $question_order = (int)($_POST['question_order'] ?? 1);
                $points = (int)($_POST['points'] ?? 1);
                
                if (empty($question_text)) {
                    $message = 'Question text is required.';
                    $message_type = 'danger';
                } else {
                    // Prepare options JSON
                    $options_json = '[]';
                    if ($question_type === 'multiple_choice') {
                        $options = $_POST['options'] ?? [];
                        $correct_option = (int)($_POST['correct_option'] ?? 0);
                        $options_array = [];
                        
                        foreach ($options as $index => $option_text) {
                            if (!empty($option_text)) {
                                $options_array[] = [
                                    'text' => $option_text,
                                    'is_correct' => ($index == $correct_option) ? 1 : 0,
                                    'order' => $index + 1
                                ];
                            }
                        }
                        $options_json = json_encode($options_array);
                    } elseif ($question_type === 'true_false') {
                        $correct_tf = $_POST['correct_tf'] ?? 'true';
                        $options_array = [
                            [
                                'text' => 'True',
                                'is_correct' => $correct_tf === 'true' ? 1 : 0,
                                'order' => 1
                            ],
                            [
                                'text' => 'False',
                                'is_correct' => $correct_tf === 'false' ? 1 : 0,
                                'order' => 2
                            ]
                        ];
                        $options_json = json_encode($options_array);
                    } elseif ($question_type === 'identification' && isset($_POST['correct_answer'])) {
                        $correct_answer = strtoupper(trim($_POST['correct_answer']));
                        $options_array = [
                            [
                                'text' => $correct_answer,
                                'is_correct' => 1,
                                'order' => 1
                            ]
                        ];
                        $options_json = json_encode($options_array);
                    }
                    
                    $stmt = $db->prepare('UPDATE questions SET question_text = ?, question_type = ?, question_order = ?, points = ?, options = ? WHERE id = ? AND assessment_id = ?');
                    $stmt->execute([$question_text, $question_type, $question_order, $points, $options_json, $question_id, $assessment_id]);
                    
                    $message = 'Question updated successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'delete_question':
                $question_id = (int)($_POST['question_id'] ?? 0);
                
                // Get the question order before deleting
                $stmt = $db->prepare('SELECT question_order FROM questions WHERE id = ? AND assessment_id = ?');
                $stmt->execute([$question_id, $assessment_id]);
                $question = $stmt->fetch();
                
                if ($question) {
                    $deleted_order = $question['question_order'];
                    
                    // Delete the question
                    $stmt = $db->prepare('DELETE FROM questions WHERE id = ? AND assessment_id = ?');
                    $stmt->execute([$question_id, $assessment_id]);
                    
                    // Reorder remaining questions to maintain sequence
                    $stmt = $db->prepare('UPDATE questions SET question_order = question_order - 1 WHERE assessment_id = ? AND question_order > ?');
                    $stmt->execute([$assessment_id, $deleted_order]);
                }
                
                $message = 'Question deleted successfully.';
                $message_type = 'success';
                break;
        }
    }
}

// --- BATCH ADD QUESTIONS BACKEND ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_add_questions'])) {
    $batch_questions = $_POST['batch_questions'] ?? [];
    $batch_errors = [];
    $batch_success = 0;
    
    // Check if we've reached the maximum number of questions
    $stmt = $db->prepare('SELECT COUNT(*) as current_count FROM questions WHERE assessment_id = ?');
    $stmt->execute([$assessment_id]);
    $current_count = $stmt->fetch()['current_count'];
    
    $remaining_slots = $assessment['num_questions'] - $current_count;
    if ($remaining_slots <= 0) {
        $message = "Cannot add more questions. Maximum of {$assessment['num_questions']} questions allowed for this assessment.";
        $message_type = 'danger';
    } else {
        // Limit the number of questions that can be added in batch
        $questions_to_add = min(count($batch_questions), $remaining_slots);
        $batch_questions = array_slice($batch_questions, 0, $questions_to_add);
        
        if ($questions_to_add < count($_POST['batch_questions'])) {
            $batch_errors[] = "Only {$questions_to_add} question(s) can be added. Maximum of {$assessment['num_questions']} questions allowed.";
        }
        
        // Get the current highest question order to ensure proper sequencing
        $stmt = $db->prepare('SELECT MAX(question_order) as max_order FROM questions WHERE assessment_id = ?');
        $stmt->execute([$assessment_id]);
        $result = $stmt->fetch();
        $current_max_order = $result['max_order'] ?? 0;
    
    foreach ($batch_questions as $idx => $q) {
        $question_text = sanitizeInput($q['question_text'] ?? '');
        $question_type = sanitizeInput($q['question_type'] ?? 'multiple_choice');
        error_log('BATCH ADD: Received question_type: ' . var_export($question_type, true));
        $question_type = strtolower(trim($question_type));
        // Force identification if correct_answer is present and type is missing or invalid
        if ((empty($question_type) || !in_array($question_type, ['multiple_choice', 'true_false', 'identification'])) && !empty($q['correct_answer'])) {
            $question_type = 'identification';
        }
        error_log('BATCH ADD: Normalized question_type: ' . var_export($question_type, true));
        $question_order = (int)($q['question_order'] ?? ($current_max_order + $idx + 1));
        $points = (int)($q['points'] ?? 1);
        
        if (empty($question_text)) {
            $batch_errors[] = "Question #" . ($idx+1) . ": Question text is required.";
            continue;
        }
        
        // Prepare options JSON
        $options_json = '[]';
        if ($question_type === 'multiple_choice') {
            $options = $q['options'] ?? [];
            $correct_option = (int)($q['correct_option'] ?? 0);
            $options_array = [];
            $has_correct = false;
            
            foreach ($options as $index => $option_text) {
                if (!empty($option_text)) {
                    $is_correct = ($index == $correct_option) ? 1 : 0;
                    if ($is_correct) $has_correct = true;
                    $options_array[] = [
                        'text' => $option_text,
                        'is_correct' => $is_correct,
                        'order' => $index + 1
                    ];
                }
            }
            if (!$has_correct) {
                $batch_errors[] = "Question #" . ($idx+1) . ": No correct option selected.";
            }
            $options_json = json_encode($options_array);
        } elseif ($question_type === 'true_false') {
            $correct_tf = $q['correct_tf'] ?? 'true';
            $options_array = [
                [
                    'text' => 'True',
                    'is_correct' => $correct_tf === 'true' ? 1 : 0,
                    'order' => 1
                ],
                [
                    'text' => 'False',
                    'is_correct' => $correct_tf === 'false' ? 1 : 0,
                    'order' => 2
                ]
            ];
            $options_json = json_encode($options_array);
        } elseif ($question_type === 'identification') {
            $correct_answer = $q['correct_answer'] ?? '';
            if (empty($correct_answer)) {
                $batch_errors[] = "Question #" . ($idx+1) . ": Correct answer is required for Identification questions.";
            } else {
                $options_array = [
                    [
                        'text' => strtoupper($correct_answer),
                        'is_correct' => 1,
                        'order' => 1
                    ]
                ];
                $options_json = json_encode($options_array);
            }
        }
        
        $stmt = $db->prepare('INSERT INTO questions (assessment_id, question_text, question_type, question_order, points, options) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$assessment_id, $question_text, $question_type, $question_order, $points, $options_json]);
        $question_id = $db->lastInsertId();
        $batch_success++;
    }
    
    if ($batch_success > 0) {
        $message = "$batch_success question(s) added successfully.";
        $message_type = 'success';
    }
    if (!empty($batch_errors)) {
        $message .= '<br>' . implode('<br>', $batch_errors);
        $message_type = 'danger';
    }
    }
}

// Get assessment questions
$stmt = $db->prepare("
    SELECT q.*, 
           CASE 
               WHEN q.options IS NOT NULL AND q.options != '[]' 
               THEN JSON_LENGTH(q.options) 
               ELSE 0 
           END as option_count
    FROM questions q
    WHERE q.assessment_id = ?
    ORDER BY q.question_order
");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll();

// Get total points
$total_points = array_sum(array_column($questions, 'points'));

// --- PAGINATION, SEARCH, FILTER LOGIC ---
$search_query = strtolower(trim($_GET['q'] ?? ''));
$type_filter = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;



$filtered_questions = array_filter($questions, function($q) use ($search_query, $type_filter) {        
    $match = true;
    
    // Apply search filter
    if ($search_query) {
        $match = stripos($q['question_text'], $search_query) !== false;
    }
    
    // Apply type filter
    if ($type_filter) {
        $qt = strtolower(trim($q['question_type']));
        if ($type_filter === 'identification') {
            if (!($qt === 'identification' || empty($qt))) {
                $match = false;
            }
        } else if ($qt !== $type_filter) {
            $match = false;
        }
    }
    
    return $match;
});
$total_filtered = count($filtered_questions);
$total_pages = max(1, ceil($total_filtered / $per_page));
$filtered_questions = array_values($filtered_questions);
$start = ($page - 1) * $per_page;
$visible_questions = array_slice($filtered_questions, $start, $per_page);
?>
<style>
/* Clean layout without background */
body {
    padding-top: calc(var(--navbar-height) + 0.1rem) !important;
    background: #f8f9fa;
}

/* Gear icon for question cards */
.question-card-compact .card::before {
    content: '⚙️';
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 1.5rem;
    opacity: 0.3;
    color: #6c757d;
    z-index: 1;
    pointer-events: none;
}

/* Course gear icon in the empty space */
.course-gear-overlay {
    position: fixed;
    top: calc(var(--navbar-height) + 0.5rem);
    left: 50%;
    transform: translateX(-50%);
    font-size: 4rem;
    opacity: 0.15;
    color: var(--main-green);
    z-index: 0;
    pointer-events: none;
    text-align: center;
    height: 3rem;
    overflow: hidden;
}

.course-gear-overlay::before {
    content: '⚙️';
    display: block;
    margin-bottom: 0.25rem;
}

.course-gear-overlay::after {
    content: 'BC1';
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--main-green);
    opacity: 0.4;
}

main {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

.container-fluid {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

/* Remove all top spacing from the main content area */
.container-fluid > *:first-child {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* Ensure the assessment header starts immediately after navbar */
h4:first-of-type {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* Force minimal spacing for all elements */
.alert {
    margin-top: 0.25rem !important;
    margin-bottom: 0.5rem !important;
}

h4 {
    margin-top: 0 !important;
    margin-bottom: 0.25rem !important;
}

.row {
    margin-top: 0 !important;
}

.d-flex {
    margin-top: 0 !important;
}

/* Remove any extra spacing from the questions grid */
.row.g-1 {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* Force minimal spacing for all elements */
.alert {
    margin-top: 0.25rem !important;
    margin-bottom: 0.25rem !important;
}

h4 {
    margin-top: 0 !important;
    margin-bottom: 0.25rem !important;
}

.row {
    margin-top: 0 !important;
}

.d-flex {
    margin-top: 0 !important;
}

/* Ensure questions start immediately after controls */
.d-flex.flex-wrap.align-items-center.gap-2.mb-1 {
    margin-bottom: 0.25rem !important;
}

/* Remove any container spacing */
.container-fluid, .row, .col-12 {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* Clean content container */
.content-container {
    background: white;
    border-radius: 8px;
    margin: 0.25rem;
    padding: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.question-card-compact .card-header, .question-card-compact .card-body {
    padding: 0.35rem 0.5rem;
}
.question-card-compact .card-header {
    font-size: 0.89rem;
}
.question-card-compact .card-body {
    font-size: 0.92rem;
}
.question-card-compact .list-group-item {
    padding: 0.25rem 0.5rem;
    font-size: 0.91em;
}
.question-card-compact .fw-bold {
    font-weight: 500 !important;
}
@media (min-width: 576px) {
    .question-card-compact .card { min-height: 140px; }
}
@media (min-width: 1200px) {
    .question-card-compact.col-xl-3 { flex: 0 0 20%; max-width: 20%; }
}
</style>
<!-- Course gear overlay in empty space -->
<div class="course-gear-overlay"></div>

<div class="content-container">
    <!-- Assessment Header -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <h4 class="mb-0">Edit Assessment: <?php echo htmlspecialchars($assessment['assessment_title']); ?></h4>
<div class="row mb-1">
    <div class="col-md-6">
        <p class="text-muted mb-0">
            <strong>Course:</strong> <?php echo htmlspecialchars($assessment['course_name']); ?> (<?php echo htmlspecialchars($assessment['course_code']); ?>)
        </p>
        <p class="text-muted mb-0">
            <strong>Module:</strong> <?php echo htmlspecialchars($assessment['module_title']); ?>
        </p>
    </div>
    <div class="col-md-6">
        <p class="text-muted mb-0">
            <strong>Total Points:</strong> <?php echo $total_points; ?> points
        </p>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <form class="d-flex flex-wrap gap-2 me-auto" method="get" style="min-width:0;">
        <input type="hidden" name="id" value="<?php echo $assessment_id; ?>">
        <input type="text" class="form-control form-control-sm" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search questions..." style="max-width:200px;">
        <select class="form-select form-select-sm" name="type" style="max-width:150px;" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="multiple_choice" <?php if($type_filter==='multiple_choice')echo'selected';?>>Multiple Choice</option>
            <option value="true_false" <?php if($type_filter==='true_false')echo'selected';?>>True/False</option>
            <option value="identification" <?php if($type_filter==='identification')echo'selected';?>>Identification</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        <?php if ($search_query || $type_filter): ?>
            <a href="?id=<?php echo $assessment_id; ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Clear</a>
        <?php endif; ?>
    </form>
    <div class="d-flex gap-2 align-items-center">
        <label for="jumpToQ" class="form-label mb-0 me-1">Jump to:</label>
        <select id="jumpToQ" class="form-select form-select-sm" style="width:auto;" onchange="if(this.value)document.getElementById('qcard'+this.value).scrollIntoView({behavior:'smooth',block:'center'});">
            <option value="">Select</option>
            <?php foreach ($filtered_questions as $q): ?>
                <option value="<?php echo $q['id']; ?>">#<?php echo $q['question_order']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="ms-auto d-flex gap-2">
        <?php 
        // Calculate current question count and maximum allowed
        $current_question_count = count($questions);
        $max_questions = $assessment['num_questions'];
        $can_add_questions = $current_question_count < $max_questions;
        ?>
        
        <!-- Question count indicator -->
        <span class="badge <?php echo $current_question_count >= $max_questions ? 'bg-warning' : 'bg-info'; ?> align-self-center">
            <?php echo $current_question_count; ?> / <?php echo $max_questions; ?> questions
            <?php if ($current_question_count >= $max_questions): ?>
                (Maximum reached)
            <?php endif; ?>
        </span>
        
        <!-- Assessment Settings Button -->
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#assessmentSettingsModal">
            <i class="bi bi-gear me-1"></i>Settings
        </button>
        
        <?php if ($search_query || $type_filter): ?>
            <span class="badge bg-info align-self-center">
                <?php echo count($visible_questions); ?> of <?php echo count($questions); ?> questions
                <?php if ($type_filter): ?>
                    (<?php echo ucfirst(str_replace('_', ' ', $type_filter)); ?>)
                <?php endif; ?>
            </span>
        <?php endif; ?>
        
        <?php if ($can_add_questions): ?>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#batchAddQuestionsModal">
                <i class="bi bi-list-check me-1"></i>Add Question
            </button>
        <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled title="Maximum number of questions reached" onclick="showMaxQuestionsAlert()">
                <i class="bi bi-list-check me-1"></i>Add Question
            </button>
        <?php endif; ?>
        
        <a href="assessments.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="row g-1 mt-0">
    <?php if (empty($visible_questions)): ?>
        <div class="col-12 text-center py-4">
            <i class="bi bi-question-circle fs-1 text-muted mb-3"></i>
            <h6>No Questions Found</h6>
            <p class="text-muted">Try adjusting your search or filter.</p>
        </div>
                            <?php else: ?>
        <?php foreach ($visible_questions as $index => $question): ?>
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 question-card-compact col-xxl-2">
                <div class="card shadow-sm h-100" id="qcard<?php echo $question['id']; ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center bg-light">
                    <div>
                        <span class="badge bg-secondary me-2">#<?php echo $question['question_order']; ?></span>
                        <span class="badge bg-info text-dark me-2">
                            <?php
                                $type = strtolower(trim($question['question_type']));
                                if ($type === 'multiple_choice') {
                                    echo 'Multiple choice';
                                } elseif ($type === 'true_false') {
                                    echo 'True false';
                                } elseif ($type === 'identification' || empty($type)) {
                                    echo 'Identification';
                                } else {
                                    echo 'Identification'; // treat all unrecognized as identification
                                }
                            ?>
                        </span>
                        <span class="badge bg-success"><?php echo $question['points']; ?> pts</span>
                        <span class="ms-2 text-muted"><i class="bi bi-gear"></i></span>
                    </div>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" title="Edit" onclick="editQuestion(<?php echo htmlspecialchars(json_encode($question)); ?>)"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-outline-danger delete-confirm" title="Delete" onclick="deleteQuestion(<?php echo $question['id']; ?>, <?php echo $question['question_order']; ?>)"><i class="bi bi-trash"></i></button>
                        </div>
                </div>
                <div class="card-body">
                        <div class="mb-2 fw-bold" style="min-height:1.5em;">
                            <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                        </div>
                        <?php 
                        // Parse options from JSON
                        $options = [];
                        if (!empty($question['options'])) {
                            $options = json_decode($question['options'], true) ?: [];
                        }
                        
                        if ($type === 'identification' || empty($type)): ?>
                            <div class="mb-2 text-muted"><small>Correct Answer: <strong><?php
                                $correct_answer = '';
                                foreach ($options as $option) {
                                    if ($option['is_correct'] == 1) {
                                        $correct_answer = $option['text'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($correct_answer);
                            ?></strong></small></div>
                        <?php endif; ?>
                                                    <?php if ($question['question_type'] === 'multiple_choice'): ?>
                            <ul class="list-group list-group-flush mb-2">
                                                            <?php foreach ($options as $option): ?>
                                    <li class="list-group-item d-flex align-items-center <?php echo $option['is_correct'] ? 'list-group-item-success fw-bold' : ''; ?>">
                                        <span class="me-2"><?php echo htmlspecialchars($option['text']); ?></span>
                                                                        <?php if ($option['is_correct']): ?>
                                            <i class="bi bi-check-circle-fill text-success ms-auto" title="Correct Answer"></i>
                                                                        <?php endif; ?>
                                    </li>
                                                            <?php endforeach; ?>
                            </ul>
                                                    <?php elseif ($question['question_type'] === 'true_false'): ?>
                            <ul class="list-group list-group-flush mb-2">
                                <?php foreach ($options as $option): ?>
                                    <li class="list-group-item d-flex align-items-center <?php echo $option['is_correct'] ? 'list-group-item-success fw-bold' : ''; ?>">
                                        <span class="me-2"><?php echo htmlspecialchars($option['text']); ?></span>
                                        <?php if ($option['is_correct']): ?>
                                            <i class="bi bi-check-circle-fill text-success ms-auto" title="Correct Answer"></i>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif ($question['question_type'] === 'identification'): ?>
                            <ul class="list-group list-group-flush mb-2">
                                <?php foreach ($options as $option): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <span class="me-2"><?php echo htmlspecialchars($option['text']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                                                </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>
<?php if ($total_pages > 1): ?>
<nav class="mt-4 d-flex justify-content-center">
    <ul class="pagination pagination-sm">
        <li class="page-item <?php if($page<=1)echo'disabled';?>"><a class="page-link" href="?id=<?php echo $assessment_id; ?>&q=<?php echo urlencode($search_query); ?>&type=<?php echo urlencode($type_filter); ?>&page=<?php echo max(1,$page-1); ?>">&laquo; Prev</a></li>
        <?php for($p=1;$p<=$total_pages;$p++): ?>
            <li class="page-item <?php if($p==$page)echo'active';?>"><a class="page-link" href="?id=<?php echo $assessment_id; ?>&q=<?php echo urlencode($search_query); ?>&type=<?php echo urlencode($type_filter); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?php if($page>=$total_pages)echo'disabled';?>"><a class="page-link" href="?id=<?php echo $assessment_id; ?>&q=<?php echo urlencode($search_query); ?>&type=<?php echo urlencode($type_filter); ?>&page=<?php echo min($total_pages,$page+1); ?>">Next &raquo;</a></li>
    </ul>
</nav>
<?php endif; ?>

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_question">
                    <input type="hidden" name="question_id" id="edit_question_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="edit_question_text" class="form-label">Question Text</label>
                            <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_question_type" class="form-label">Question Type</label>
                            <select class="form-select" id="edit_question_type" name="question_type" required onchange="toggleEditOptions()">
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True/False</option>
                                <option value="identification">Identification</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_question_order" class="form-label">Question Order</label>
                            <input type="number" class="form-control" id="edit_question_order" name="question_order" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_points" class="form-label">Points</label>
                            <input type="number" class="form-control" id="edit_points" name="points" min="1" required>
                        </div>
                    </div>
                    <!-- Identification Correct Answer Field -->
                    <div id="editIdentificationAnswer" style="display:none;">
                        <hr>
                        <label for="edit_correct_answer" class="form-label">Correct Answer</label>
                        <input type="text" class="form-control" id="edit_correct_answer" name="correct_answer">
                    </div>
                    
                    <!-- Edit Multiple Choice Options -->
                    <div id="editMultipleChoiceOptions">
                        <hr>
                        <h6>Options</h6>
                        <div id="editOptionsContainer">
                            <!-- Options will be loaded dynamically -->
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addEditOption()">
                            <i class="bi bi-plus-circle me-1"></i>Add Option
                        </button>
                    </div>
                    
                    <!-- Edit True/False Options -->
                    <div id="editTrueFalseOptions" style="display:none;">
                        <hr>
                        <h6>Correct Answer</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="correct_tf" id="edit_tf_true" value="true">
                            <label class="form-check-label" for="edit_tf_true">True</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="correct_tf" id="edit_tf_false" value="false">
                            <label class="form-check-label" for="edit_tf_false">False</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Question Form -->
<form id="deleteQuestionForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_question">
    <input type="hidden" name="question_id" id="delete_question_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Batch Add Questions Modal -->
<div class="modal fade" id="batchAddQuestionsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <?php if (!$can_add_questions): ?>
            <div class="alert alert-warning m-3" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Maximum questions reached!</strong> This assessment already has <?php echo $current_question_count; ?> questions out of the maximum <?php echo $max_questions; ?> allowed. You cannot add more questions.
            </div>
            <?php endif; ?>
            <form method="post" id="batchAddQuestionsForm">
                <input type="hidden" name="batch_add_questions" value="1">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <?php if ($can_add_questions): ?>
                        <div class="alert alert-info mb-3" role="alert">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Question Limit:</strong> You can add up to <?php echo $max_questions - $current_question_count; ?> more question(s). 
                            Current: <?php echo $current_question_count; ?> / <?php echo $max_questions; ?> questions.
                        </div>
                    <?php endif; ?>
                    <div id="batchQuestionsContainer"></div>
                    <?php if ($can_add_questions): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm my-2" onclick="addBatchQuestion()">
                            <i class="bi bi-plus-circle me-1"></i>Add Question
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-sm my-2" disabled title="Maximum questions reached">
                            <i class="bi bi-plus-circle me-1"></i>Add Question
                        </button>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if ($can_add_questions): ?>
                        <button type="submit" class="btn btn-primary">Add All Questions</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-secondary" disabled>Add All Questions</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let optionCount = 2; // Changed to 2 for true/false

function toggleOptions() {
    const questionType = document.getElementById('create_question_type').value;
    const optionsDiv = document.getElementById('multipleChoiceOptions');
    const tfDiv = document.getElementById('trueFalseOptions');
    
    // Get all option inputs in the multiple choice section
    const optionInputs = optionsDiv.querySelectorAll('input[name="options[]"]');
    
    if (questionType === 'multiple_choice') {
        optionsDiv.style.display = 'block';
        tfDiv.style.display = 'none';
        // Make option inputs required for multiple choice
        optionInputs.forEach((input, index) => {
            if (index < 2) { // First two options are always required
                input.required = true;
            } else {
                input.required = false;
            }
        });
    } else if (questionType === 'true_false') {
        optionsDiv.style.display = 'none';
        tfDiv.style.display = 'block';
        // Remove required from option inputs for true/false
        optionInputs.forEach(input => {
            input.required = false;
        });
    } else {
        optionsDiv.style.display = 'none';
        tfDiv.style.display = 'none';
    }
}

function toggleEditOptions() {
    const questionType = document.getElementById('edit_question_type').value;
    const optionsDiv = document.getElementById('editMultipleChoiceOptions');
    const idDiv = document.getElementById('editIdentificationAnswer');
    const tfDiv = document.getElementById('editTrueFalseOptions');
    
    if (questionType === 'multiple_choice') {
        optionsDiv.style.display = 'block';
        idDiv.style.display = 'none';
        tfDiv.style.display = 'none';
    } else if (questionType === 'true_false') {
        optionsDiv.style.display = 'none';
        idDiv.style.display = 'none';
        tfDiv.style.display = 'block';
    } else if (questionType === 'identification') {
        optionsDiv.style.display = 'none';
        idDiv.style.display = 'block';
        tfDiv.style.display = 'none';
    } else {
        optionsDiv.style.display = 'none';
        idDiv.style.display = 'none';
        tfDiv.style.display = 'none';
    }
}

function addOption() {
    const container = document.getElementById('optionsContainer');
    const newOption = document.createElement('div');
    newOption.className = 'row g-2 mb-2';
    newOption.innerHTML = `
        <div class="col-md-10">
            <input type="text" class="form-control" name="options[]" placeholder="Option ${optionCount + 1}">
        </div>
        <div class="col-md-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="correct_option" value="${optionCount}">
                <label class="form-check-label">Correct</label>
            </div>
        </div>
    `;
    container.appendChild(newOption);
    optionCount++;
}

function addEditOption() {
    const container = document.getElementById('editOptionsContainer');
    const newOption = document.createElement('div');
    newOption.className = 'row g-2 mb-2';
    newOption.innerHTML = `
        <div class="col-md-10">
            <input type="text" class="form-control" name="options[]" placeholder="New Option">
        </div>
        <div class="col-md-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="correct_option" value="0">
                <label class="form-check-label">Correct</label>
            </div>
        </div>
    `;
    container.appendChild(newOption);
}

function editQuestion(question) {
    document.getElementById('edit_question_id').value = question.id;
    document.getElementById('edit_question_text').value = question.question_text;
    document.getElementById('edit_question_type').value = question.question_type;
    document.getElementById('edit_question_order').value = question.question_order;
    document.getElementById('edit_points').value = question.points;
    
    // Parse options from JSON
    let options = [];
    if (question.options) {
        try {
            options = JSON.parse(question.options);
        } catch (e) {
            options = [];
        }
    }
    
    // Fetch correct answer for identification
    if (question.question_type === 'identification') {
        if (options.length > 0) {
            const correctOption = options.find(option => option.is_correct == 1);
            document.getElementById('edit_correct_answer').value = correctOption ? correctOption.text : '';
        } else {
            document.getElementById('edit_correct_answer').value = '';
        }
    } else {
        document.getElementById('edit_correct_answer').value = '';
    }
    
    // Load correct answer for true/false
    if (question.question_type === 'true_false') {
        const correctOption = options.find(option => option.is_correct == 1);
        if (correctOption) {
            const isTrue = correctOption.text.toLowerCase() === 'true';
            document.getElementById('edit_tf_true').checked = isTrue;
            document.getElementById('edit_tf_false').checked = !isTrue;
        } else {
            // Default to True if no correct option found
            document.getElementById('edit_tf_true').checked = true;
            document.getElementById('edit_tf_false').checked = false;
        }
    } else {
        // Reset true/false radio buttons
        document.getElementById('edit_tf_true').checked = false;
        document.getElementById('edit_tf_false').checked = false;
    }
    
    toggleEditOptions();
    
    // Load options if multiple choice
    if (question.question_type === 'multiple_choice') {
        loadQuestionOptions(question);
    }
    
    new bootstrap.Modal(document.getElementById('editQuestionModal')).show();
}

function loadQuestionOptions(question) {
    // Parse options from JSON
    let options = [];
    if (question.options) {
        try {
            options = JSON.parse(question.options);
        } catch (e) {
            options = [];
        }
    }
    
    const container = document.getElementById('editOptionsContainer');
    container.innerHTML = '';
    
    options.forEach((option, index) => {
        const optionDiv = document.createElement('div');
        optionDiv.className = 'row g-2 mb-2';
        optionDiv.innerHTML = `
            <div class="col-md-10">
                <input type="text" class="form-control" name="options[]" value="${option.text}" required>
            </div>
            <div class="col-md-2">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="correct_option" value="${index}" ${option.is_correct ? 'checked' : ''}>
                    <label class="form-check-label">Correct</label>
                </div>
            </div>
        `;
        container.appendChild(optionDiv);
    });
}

function deleteQuestion(questionId, questionOrder) {
    if (confirm(`Are you sure you want to delete Question ${questionOrder}? This action cannot be undone.`)) {
        document.getElementById('delete_question_id').value = questionId;
        document.getElementById('deleteQuestionForm').submit();
    }
}

function showMaxQuestionsAlert() {
    alert('Maximum number of questions reached! You cannot add more questions to this assessment.');
}

function addBatchQuestion() {
    const container = document.getElementById('batchQuestionsContainer');
    const idx = container.children.length;
    // Get the current total number of questions from PHP
    const currentQuestionCount = <?php echo count($questions); ?>;
    const maxQuestions = <?php echo $assessment['num_questions']; ?>;
    
    // Check if adding this question would exceed the maximum
    if (currentQuestionCount + idx >= maxQuestions) {
        alert('Cannot add more questions. Maximum of ' + maxQuestions + ' questions allowed for this assessment.');
        return;
    }
    
    const qDiv = document.createElement('div');
    qDiv.className = 'card mb-3';
    qDiv.innerHTML = `
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Question Text</label>
                    <textarea class="form-control" name="batch_questions[${idx}][question_text]" rows="2" required></textarea>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="batch_questions[${idx}][question_type]" onchange="toggleBatchOptions(this, ${idx})" required>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="identification">Identification</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Order</label>
                    <input type="number" class="form-control" name="batch_questions[${idx}][question_order]" value="${currentQuestionCount + idx + 1}" min="1" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Points</label>
                    <input type="number" class="form-control" name="batch_questions[${idx}][points]" value="1" min="1" required>
                </div>
                <div class="col-md-2" id="batchOptions${idx}">
                    <!-- Options will be inserted here -->
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeBatchQuestion(this)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(qDiv);
    // Default to multiple choice options with 2 choices
    setTimeout(() => toggleBatchOptions(qDiv.querySelector('select'), idx, true), 10);
}
function toggleBatchOptions(sel, idx, forceTwo) {
    const val = sel.value;
    console.log('toggleBatchOptions: idx=' + idx + ', value=' + val); // Debug
    const optDiv = document.getElementById('batchOptions'+idx);
    if (val === 'multiple_choice') {
        let html = '';
        for (let i = 0; i < (forceTwo ? 2 : 1); i++) {
            html += `<div class='mb-1'><input type='text' class='form-control mb-1' name='batch_questions[${idx}][options][]' placeholder='Option ${i+1}' required></div>`;
        }
        html += `<div id='batchCorrectOptions${idx}'>`;
        for (let i = 0; i < (forceTwo ? 2 : 1); i++) {
            html += `<div class='form-check'><input class='form-check-input' type='radio' name='batch_questions[${idx}][correct_option]' value='${i}' ${i===0?'checked':''}> <label class='form-check-label'>Correct</label></div>`;
        }
        html += `</div>`;
        html += `<button type='button' class='btn btn-outline-secondary btn-sm mt-1' onclick='addBatchOption(${idx})'><i class="bi bi-plus-circle me-1"></i>Add Option</button>`;
        optDiv.innerHTML = html;
    } else if (val === 'true_false') {
        optDiv.innerHTML = `
            <div class='form-check'><input class='form-check-input' type='radio' name='batch_questions[${idx}][correct_tf]' value='true' checked> <label class='form-check-label'>True</label></div>
            <div class='form-check'><input class='form-check-input' type='radio' name='batch_questions[${idx}][correct_tf]' value='false'> <label class='form-check-label'>False</label></div>
        `;
    } else if (val === 'identification') {
        optDiv.innerHTML = `
            <div class='form-group'>
                <label for='batchCorrectAnswer${idx}' class='form-label'>Correct Answer</label>
                <input type='text' class='form-control' id='batchCorrectAnswer${idx}' name='batch_questions[${idx}][correct_answer]' required>
            </div>
        `;
    } else {
        optDiv.innerHTML = '';
    }
}
function addBatchOption(idx) {
    const optDiv = document.getElementById('batchOptions'+idx);
    const inputs = optDiv.querySelectorAll("input[name^='batch_questions["+idx+"][options]']");
    const correctDiv = optDiv.querySelector('#batchCorrectOptions'+idx);
    const newIdx = inputs.length;
    // Add new option input
    const newOpt = document.createElement('div');
    newOpt.className = 'mb-1';
    newOpt.innerHTML = `<input type='text' class='form-control mb-1' name='batch_questions[${idx}][options][]' placeholder='Option ${newIdx+1}' required>`;
    correctDiv.parentNode.insertBefore(newOpt, correctDiv);
    // Add new correct radio
    const newRadio = document.createElement('div');
    newRadio.className = 'form-check';
    newRadio.innerHTML = `<input class='form-check-input' type='radio' name='batch_questions[${idx}][correct_option]' value='${newIdx}'> <label class='form-check-label'>Correct</label>`;
    correctDiv.appendChild(newRadio);
}

function removeBatchQuestion(button) {
    const card = button.closest('.card');
    card.remove();
    // Update question orders for remaining questions
    updateBatchQuestionOrders();
}

function updateBatchQuestionOrders() {
    const container = document.getElementById('batchQuestionsContainer');
    const currentQuestionCount = <?php echo count($questions); ?>;
    const cards = container.querySelectorAll('.card');
    
    cards.forEach((card, index) => {
        const orderInput = card.querySelector('input[name*="[question_order]"]');
        if (orderInput) {
            orderInput.value = currentQuestionCount + index + 1;
        }
    });
}

// Ensure correct options are shown when modal opens
if (document.getElementById('createQuestionModal')) {
    document.getElementById('createQuestionModal').addEventListener('shown.bs.modal', function () {
        toggleOptions();
        // Update the question order to the next available number
        const currentQuestionCount = <?php echo count($questions); ?>;
        document.getElementById('create_question_order').value = currentQuestionCount + 1;
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleOptions();
});

// Also call toggleOptions when the question type changes
document.addEventListener('DOMContentLoaded', function() {
    const questionTypeSelect = document.getElementById('create_question_type');
    if (questionTypeSelect) {
        questionTypeSelect.addEventListener('change', toggleOptions);
    }
});
</script>

<!-- Assessment Settings Modal -->
<div class="modal fade" id="assessmentSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assessment Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_assessment_title" class="form-label">Assessment Title</label>
                            <input type="text" class="form-control" id="edit_assessment_title" name="assessment_title" 
                                   value="<?php echo htmlspecialchars($assessment['assessment_title']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_difficulty" class="form-label">Difficulty</label>
                            <select class="form-select" id="edit_difficulty" name="difficulty" required>
                                <option value="easy" <?php echo $assessment['difficulty'] === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo $assessment['difficulty'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo $assessment['difficulty'] === 'hard' ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_time_limit" class="form-label">Time Limit (minutes)</label>
                            <input type="number" class="form-control" id="edit_time_limit" name="time_limit" 
                                   value="<?php echo $assessment['time_limit'] ?? ''; ?>" min="1">
                            <small class="form-text text-muted">Leave empty for no time limit</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_passing_rate" class="form-label">Passing Rate (%)</label>
                            <input type="number" class="form-control" id="edit_passing_rate" name="passing_rate" 
                                   value="<?php echo $assessment['passing_rate'] ?? 70; ?>" min="0" max="100" step="0.1" required>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_attempt_limit" class="form-label">Attempt Limit</label>
                            <input type="number" class="form-control" id="edit_attempt_limit" name="attempt_limit" 
                                   value="<?php echo $assessment['attempt_limit'] ?? 3; ?>" min="0" max="10" required>
                            <small class="form-text text-muted">Maximum attempts allowed (0 = unlimited)</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_num_questions" class="form-label">Number of Questions</label>
                            <input type="number" class="form-control" id="edit_num_questions" name="num_questions" 
                                   value="<?php echo $assessment['num_questions'] ?? 10; ?>" min="1" max="100" required>
                            <small class="form-text text-muted">Maximum questions allowed for this assessment</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"><?php echo htmlspecialchars($assessment['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 