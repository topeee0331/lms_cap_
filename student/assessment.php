<?php
session_start();
require_once '../config/database.php';
require_once '../includes/semester_security.php';
require_once '../includes/assessment_pass_tracker.php';

/**
 * Normalize text for comparison by removing extra spaces, converting to lowercase,
 * removing punctuation, and handling common variations
 */
function normalizeText($text) {
    if (empty($text)) return '';
    
    // Convert to lowercase
    $text = strtolower(trim($text));
    
    // Remove extra whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Remove common punctuation that might vary
    $text = preg_replace('/[.,;:!?\'"()-]/', '', $text);
    
    // Handle common abbreviations and variations
    $replacements = [
        '&' => 'and',
        '+' => 'plus',
        '=' => 'equals',
        '%' => 'percent',
        '#' => 'number',
        '@' => 'at',
        'vs' => 'versus',
        'vs.' => 'versus',
        'etc' => 'etcetera',
        'etc.' => 'etcetera',
        'dr' => 'doctor',
        'dr.' => 'doctor',
        'mr' => 'mister',
        'mr.' => 'mister',
        'ms' => 'miss',
        'ms.' => 'miss',
        'mrs' => 'missus',
        'mrs.' => 'missus',
        'prof' => 'professor',
        'prof.' => 'professor',
    ];
    
    foreach ($replacements as $search => $replace) {
        $text = str_replace($search, $replace, $text);
    }
    
    return $text;
}

// Get user ID
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../login.php');
    exit();
}

// Get assessment ID
$assessment_id = $_GET['id'] ?? null;
if (!$assessment_id) {
    header('Location: assessments.php');
    exit();
}

// Get current question number
$current_question = (int)($_GET['q'] ?? 0);

try {
    if (!isset($pdo) || !$pdo) {
        $database = new Database();
        $pdo = $database->getConnection();
    }
    
    // Get assessment details
    $stmt = $pdo->prepare("
        SELECT a.*, c.course_name, c.teacher_id, c.academic_period_id, c.sections
        FROM assessments a
        JOIN courses c ON a.course_id = c.id
        WHERE a.id = ? AND a.is_active = 1
    ");
    $stmt->execute([$assessment_id]);
    $assessment = $stmt->fetch();
    
    if (!$assessment) {
        $_SESSION['error'] = 'Assessment not found.';
        header('Location: assessments.php');
        exit();
    }
    
    // Check if student is enrolled in the course
    $stmt = $pdo->prepare("
        SELECT ce.*, c.course_name, c.teacher_id, c.academic_period_id
        FROM course_enrollments ce
        JOIN courses c ON ce.course_id = c.id
        WHERE ce.student_id = ? AND ce.course_id = ? AND ce.status = 'enrolled'
    ");
    $stmt->execute([$user_id, $assessment['course_id']]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        $_SESSION['error'] = 'You are not enrolled in this course.';
        header('Location: assessments.php');
        exit();
    }
    
    // Check if assessment is accessible
    $assessment['is_accessible'] = true;
    $assessment['lock_reason'] = '';
    
    // Check if there's a previous assessment that needs to be completed first
    if ($assessment['prerequisite_assessment_id']) {
        $stmt = $pdo->prepare("
            SELECT aa.score, aa.completed_at
            FROM assessment_attempts aa
            WHERE aa.student_id = ? AND aa.assessment_id = ? AND aa.score >= 70
            ORDER BY aa.completed_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id, $assessment['prerequisite_assessment_id']]);
        $prereq_attempt = $stmt->fetch();
        
        if (!$prereq_attempt) {
            $assessment['is_accessible'] = false;
            $assessment['lock_reason'] = 'Previous assessment not completed';
        }
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['submit_assessment'])) {
            // Process assessment submission
            $answers = $_POST['answers'] ?? [];
            $time_taken = (int)($_POST['time_taken'] ?? 0);
            $time_expired = (int)($_POST['time_expired'] ?? 0);
            $auto_submit = (int)($_POST['auto_submit'] ?? 0);
            
            // Calculate score
            $total_questions = count($answers);
            $correct_answers = 0;
            
            foreach ($answers as $question_id => $student_answer) {
                $stmt = $pdo->prepare("SELECT correct_answer, question_type FROM questions WHERE id = ?");
                $stmt->execute([$question_id]);
                $question = $stmt->fetch();
                
                if ($question) {
                    $correct_answer = $question['correct_answer'];
                    $question_type = strtolower(trim($question['question_type']));
                    
                    if ($question_type === 'identification') {
                        // For identification questions, normalize both answers
                        $normalized_student = normalizeText($student_answer);
                        $normalized_correct = normalizeText($correct_answer);
                        
                        if ($normalized_student === $normalized_correct) {
                            $correct_answers++;
                        }
                    } else {
                        // For multiple choice and true/false
                        if (is_array($student_answer)) {
                            $student_answer = implode(',', $student_answer);
                        }
                        
                        if (trim($student_answer) === trim($correct_answer)) {
                            $correct_answers++;
                        }
                    }
                }
            }
            
            $score = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100, 2) : 0;
            
            // Insert attempt record
            $stmt = $pdo->prepare("
                INSERT INTO assessment_attempts (student_id, assessment_id, score, time_taken, started_at, completed_at, time_expired, auto_submit)
                VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?)
            ");
            $stmt->execute([$user_id, $assessment_id, $score, $time_taken, $time_expired, $auto_submit]);
            $attempt_id = $pdo->lastInsertId();
            
            // Insert individual answers
            foreach ($answers as $question_id => $student_answer) {
                if (is_array($student_answer)) {
                    $student_answer = implode(',', $student_answer);
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO assessment_answers (attempt_id, question_id, student_answer, is_correct)
                    VALUES (?, ?, ?, ?)
                ");
                
                // Check if answer is correct
                $stmt_check = $pdo->prepare("SELECT correct_answer, question_type FROM questions WHERE id = ?");
                $stmt_check->execute([$question_id]);
                $question = $stmt_check->fetch();
                
                $is_correct = 0;
                if ($question) {
                    $correct_answer = $question['correct_answer'];
                    $question_type = strtolower(trim($question['question_type']));
                    
                    if ($question_type === 'identification') {
                        $normalized_student = normalizeText($student_answer);
                        $normalized_correct = normalizeText($correct_answer);
                        $is_correct = ($normalized_student === $normalized_correct) ? 1 : 0;
                    } else {
                        $is_correct = (trim($student_answer) === trim($correct_answer)) ? 1 : 0;
                    }
                }
                
                $stmt->execute([$attempt_id, $question_id, $student_answer, $is_correct]);
            }
            
            // Clear session data
            if (isset($_SESSION['assessment_in_progress'])) {
                $inProgressKey = "assessment_{$assessment_id}_student_{$user_id}";
                unset($_SESSION['assessment_in_progress'][$inProgressKey]);
                if (empty($_SESSION['assessment_in_progress'])) {
                    unset($_SESSION['assessment_in_progress']);
                }
            }
            
            // Set success message
            if ($time_expired) {
                $_SESSION['warning'] = "Time expired! Assessment submitted automatically. Your score: $score%";
            } else {
                $_SESSION['success'] = "Assessment submitted successfully! Your score: $score%";
            }
            
            // Check if this is a retake and get previous best score
            $is_retake_submission = isset($is_retake) ? $is_retake : false;
            $previous_best_score = null;
            $previous_attempt_id = null;
            
            if ($is_retake_submission) {
                // Get the best previous attempt
                $stmt = $pdo->prepare("
                    SELECT id, score 
                    FROM assessment_attempts 
                    WHERE student_id = ? AND assessment_id = ? AND id != ?
                    ORDER BY score DESC, completed_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $assessment_id, $attempt_id]);
                $previous_attempt = $stmt->fetch();
                
                if ($previous_attempt) {
                    $previous_best_score = $previous_attempt['score'];
                    $previous_attempt_id = $previous_attempt['id'];
                }
            }
            
            // Ensure proper redirect with absolute path
            if ($is_retake_submission && $previous_best_score !== null) {
                // Redirect to score comparison page for retakes
                $redirect_url = 'assessment_result.php?attempt_id=' . $attempt_id . '&retake=1&previous_attempt_id=' . $previous_attempt_id;
            } else {
                // Normal redirect for first attempts
                $redirect_url = 'assessment_result.php?attempt_id=' . $attempt_id;
            }
            
            // Debug: Log redirect attempt
            error_log("Redirecting to: " . $redirect_url . " for attempt: " . $attempt_id);
            
            // Clear randomized question order so a new attempt (if allowed) gets a fresh shuffle
            if (isset($_SESSION['random_question_order'])) {
                $submitOrderKey = "assessment_{$assessment_id}_student_{$user_id}";
                unset($_SESSION['random_question_order'][$submitOrderKey]);
                if (empty($_SESSION['random_question_order'])) {
                    unset($_SESSION['random_question_order']);
                }
            }
            
            header('Location: ' . $redirect_url);
            exit();
        }
    }
    
    // Check if assessment is accessible
    if (!$assessment['is_accessible']) {
        $_SESSION['error'] = $assessment['lock_reason'];
        header('Location: assessments.php');
        exit();
    }
    
    // Get questions for this assessment
    $stmt = $pdo->prepare("
        SELECT * FROM questions 
        WHERE assessment_id = ? AND is_active = 1 
        ORDER BY question_order, id
    ");
    $stmt->execute([$assessment_id]);
    $questions = $stmt->fetchAll();
    
    if (empty($questions)) {
        $_SESSION['error'] = 'No questions available for this assessment.';
        header('Location: assessments.php');
        exit();
    }
    
    $total_questions = count($questions);
    
    // Check if current question is valid
    if ($current_question < 0 || $current_question >= $total_questions) {
        $_SESSION['error'] = 'Invalid question number.';
        header('Location: assessments.php');
        exit();
    }
    
    // Get current question data
    $current_question_data = $questions[$current_question];
    
    // Create order key for session tracking
    $orderKey = "assessment_{$assessment_id}_student_{$user_id}";
    
    // Track if an assessment page is currently in progress to avoid mid-attempt reshuffles
    if (!isset($_SESSION['assessment_in_progress'])) {
        $_SESSION['assessment_in_progress'] = [];
    }
    
    // If caller requested a reset (e.g., explicit retake), clear previous order ONLY if not currently in-progress
    $is_retake = isset($_GET['reset']) && $_GET['reset'] === '1';
    if ($is_retake) {
        // Check if student has already passed this assessment
        if (hasStudentPassedAssessment($pdo, $user_id, $assessment_id)) {
            $_SESSION['error'] = 'You have already passed this assessment.';
            header('Location: assessments.php');
            exit();
        }
        
        $inProgress = $_SESSION['assessment_in_progress'][$orderKey] ?? false;
        if (!$inProgress && isset($_SESSION['random_question_order'][$orderKey])) {
            unset($_SESSION['random_question_order'][$orderKey]);
            if (empty($_SESSION['random_question_order'])) {
                unset($_SESSION['random_question_order']);
            }
        }
    }
    
    // Check if there's a saved question order for this assessment
    if (!isset($_SESSION['random_question_order'][$orderKey])) {
        // Create a shuffled copy of questions
        $shuffledQuestions = $questions;
        shuffle($shuffledQuestions);
        
        // Save the shuffled order
        $_SESSION['random_question_order'][$orderKey] = $shuffledQuestions;
    } else {
        // Use the saved shuffled order
        $shuffledQuestions = $_SESSION['random_question_order'][$orderKey];
    }
    
    // Update questions with shuffled order
    $questions = $shuffledQuestions;
    
    // Mark this assessment as in-progress for this user (prevents accidental reshuffle on refresh)
    $_SESSION['assessment_in_progress'][$orderKey] = true;
    
    // Get previous attempts
    $stmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE student_id = ? AND assessment_id = ? ORDER BY started_at DESC");
    $stmt->execute([$user_id, $assessment_id]);
    $previous_attempts = $stmt->fetchAll();
    
    // Helper function to get option letter
    function getOptionLetter($index) {
        return chr(65 + $index); // A, B, C, D, etc.
    }
    ?>
    
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Assessment - <?php echo htmlspecialchars($assessment['title']); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            .assessment-container {
                background: rgba(255, 255, 255, 0.95);
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                backdrop-filter: blur(10px);
                margin: 20px auto;
                max-width: 900px;
                overflow: hidden;
            }
            
            .assessment-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            
            .assessment-title {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 10px;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            }
            
            .assessment-subtitle {
                font-size: 1.2rem;
                opacity: 0.9;
                margin-bottom: 0;
            }
            
            .timer-container {
                background: rgba(255, 255, 255, 0.2);
                border-radius: 15px;
                padding: 20px;
                margin: 20px 0;
                text-align: center;
            }
            
            .timer-display {
                font-size: 3rem;
                font-weight: 700;
                color: #fff;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
                margin-bottom: 10px;
            }
            
            .timer-progress {
                background: rgba(255, 255, 255, 0.3);
                border-radius: 10px;
                height: 8px;
                overflow: hidden;
            }
            
            .timer-progress-fill {
                background: linear-gradient(90deg, #4CAF50, #8BC34A);
                height: 100%;
                transition: width 1s ease;
                border-radius: 10px;
            }
            
            .question-card {
                background: white;
                border-radius: 15px;
                padding: 30px;
                margin: 20px 0;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                border: 1px solid rgba(102, 126, 234, 0.1);
            }
            
            .question-header {
                display: flex;
                align-items: center;
                margin-bottom: 25px;
                padding-bottom: 20px;
                border-bottom: 2px solid #f8f9fa;
            }
            
            .question-number-badge {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 1.2rem;
                margin-right: 20px;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            }
            
            .question-info {
                flex: 1;
            }
            
            .question-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: #333;
                margin-bottom: 5px;
            }
            
            .question-subtitle {
                color: #666;
                font-size: 1rem;
            }
            
            .question-text {
                font-size: 1.3rem;
                line-height: 1.6;
                color: #333;
                margin-bottom: 30px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 10px;
                border-left: 4px solid #667eea;
            }
            
            .options-container {
                margin-bottom: 30px;
            }
            
            .option-item {
                background: white;
                border: 2px solid #e9ecef;
                border-radius: 15px;
                padding: 20px;
                margin-bottom: 15px;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                position: relative;
                overflow: hidden;
            }
            
            .option-item:hover {
                border-color: #667eea;
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
            }
            
            .option-item.selected {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-color: #667eea;
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            }
            
            .option-letter {
                background: rgba(102, 126, 234, 0.1);
                color: #667eea;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 1.1rem;
                margin-right: 20px;
                transition: all 0.3s ease;
            }
            
            .option-item.selected .option-letter {
                background: rgba(255, 255, 255, 0.2);
                color: white;
            }
            
            .option-text {
                flex: 1;
                font-size: 1.1rem;
                font-weight: 500;
            }
            
            .checkbox-icon {
                width: 20px;
                height: 20px;
                border: 2px solid #667eea;
                border-radius: 4px;
                margin-right: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
            }
            
            .option-item.selected .checkbox-icon {
                background: #667eea;
                color: white;
            }
            
            .identification-input {
                width: 100%;
                padding: 20px;
                border: 2px solid #e9ecef;
                border-radius: 15px;
                font-size: 1.2rem;
                transition: all 0.3s ease;
                background: #f8f9fa;
            }
            
            .identification-input:focus {
                outline: none;
                border-color: #667eea;
                background: white;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            .navigation-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 30px;
                background: #f8f9fa;
                border-top: 1px solid #e9ecef;
            }
            
            .nav-button {
                padding: 15px 30px;
                border: none;
                border-radius: 25px;
                font-weight: 600;
                font-size: 1.1rem;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 10px;
            }
            
            .nav-button-secondary {
                background: #6c757d;
                color: white;
            }
            
            .nav-button-secondary:hover {
                background: #5a6268;
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
            }
            
            .nav-button-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .nav-button-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            }
            
            .nav-button-success {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                color: white;
            }
            
            .nav-button-success:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
            }
            
            .progress-container {
                background: rgba(255, 255, 255, 0.9);
                border-radius: 15px;
                padding: 20px;
                margin: 20px 0;
            }
            
            .progress-bar-container {
                background: #e9ecef;
                border-radius: 10px;
                height: 10px;
                overflow: hidden;
                margin-bottom: 15px;
            }
            
            .progress-bar-fill {
                background: linear-gradient(90deg, #667eea, #764ba2);
                height: 100%;
                transition: width 0.5s ease;
                border-radius: 10px;
            }
            
            .progress-text {
                text-align: center;
                color: #666;
                font-weight: 600;
            }
            
            .achievement-badge {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                color: white;
                padding: 30px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                z-index: 1000;
                display: none;
                text-align: center;
                animation: achievementPop 0.5s ease;
            }
            
            @keyframes achievementPop {
                0% { transform: translate(-50%, -50%) scale(0.5); opacity: 0; }
                50% { transform: translate(-50%, -50%) scale(1.1); opacity: 1; }
                100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            }
            
            .save-feedback {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 10px 20px;
                border-radius: 25px;
                font-size: 0.9rem;
                z-index: 1000;
                display: none;
                animation: slideIn 0.3s ease;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            .timer-warning {
                background: #ffc107;
                color: #856404;
                border: 1px solid #ffeaa7;
            }
            
            .timer-danger {
                background: #dc3545;
                color: white;
                border: 1px solid #dc3545;
            }
        </style>
    </head>
    <body>
        <div class="assessment-container">
            <div class="assessment-header">
                <h1 class="assessment-title"><?php echo htmlspecialchars($assessment['title']); ?></h1>
                <p class="assessment-subtitle"><?php echo htmlspecialchars($assessment['course_name']); ?></p>
                
                <div class="timer-container" id="timer">
                    <div class="timer-display" id="time-display">30:00</div>
                    <div class="timer-progress">
                        <div class="timer-progress-fill" id="timer-progress-bar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
            
            <div class="progress-container">
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: <?php echo round((($current_question + 1) / $total_questions) * 100, 2); ?>%"></div>
                </div>
                <div class="progress-text">Question <?php echo $current_question + 1; ?> of <?php echo $total_questions; ?></div>
            </div>
            
            <form method="POST" action="assessment.php?id=<?php echo $assessment_id; ?>&q=<?php echo $current_question; ?>" id="assessment-form" autocomplete="off">
                <input type="hidden" name="time_taken" id="time-taken" value="0">
                <input type="hidden" name="time_expired" id="time-expired" value="0">
                <input type="hidden" name="auto_submit" id="auto-submit" value="0">
                
                <div class="question-card">
                    <div class="question-header">
                        <div class="question-number-badge"><?php echo $current_question + 1; ?></div>
                        <div class="question-info">
                            <div class="question-title">Question <?php echo $current_question + 1; ?> of <?php echo $total_questions; ?></div>
                            <div class="question-subtitle">
                                <?php 
                                $question_type = strtolower(trim($current_question_data['question_type']));
                                if ($question_type === 'identification') {
                                    echo 'Type your answer below';
                                } elseif ($question_type === 'true_false') {
                                    echo 'Select True or False';
                                } else {
                                    echo 'Select all correct answers';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="question-text">
                        <?php echo htmlspecialchars($current_question_data['question_text']); ?>
                    </div>
                    
                    <div class="options-container">
                        <?php if (strtolower(trim($current_question_data['question_type'])) === 'identification'): ?>
                            <input type="text" 
                                   class="identification-input" 
                                   name="answers[<?php echo $current_question_data['id']; ?>]" 
                                   placeholder="Type your answer here..." 
                                   autocomplete="off"
                                   autocapitalize="off"
                                   autocorrect="off"
                                   spellcheck="false"
                                   id="identification-answer">
                        <?php elseif (strtolower(trim($current_question_data['question_type'])) === 'true_false'): ?>
                            <?php 
                            $options_array = [];
                            if ($current_question_data['options']) {
                                $json_options = json_decode($current_question_data['options'], true);
                                if ($json_options && is_array($json_options)) {
                                    foreach ($json_options as $idx => $option) {
                                        $options_array[$idx] = $option['text'] ?? '';
                                    }
                                }
                            }
                            
                            foreach ($options_array as $key => $option): 
                            ?>
                            <div class="option-item" onclick="selectOptionWithAnimation(this, '<?php echo $current_question_data['id']; ?>', '<?php echo $option; ?>')">
                                <input type="radio" 
                                       name="answers[<?php echo $current_question_data['id']; ?>]" 
                                       value="<?php echo $option; ?>" 
                                       autocomplete="off"
                                       style="display: none;">
                                <div class="option-letter"><?php echo getOptionLetter($key); ?></div>
                                <div class="option-text"><?php echo htmlspecialchars($option); ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php elseif (strtolower(trim($current_question_data['question_type'])) === 'multiple_choice'): ?>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Select all correct answers. You can choose multiple options.
                                </small>
                            </div>
                            <?php 
                            $options_array = [];
                            if ($current_question_data['options']) {
                                $json_options = json_decode($current_question_data['options'], true);
                                if ($json_options && is_array($json_options)) {
                                    foreach ($json_options as $idx => $option) {
                                        $options_array[$idx] = $option['text'] ?? '';
                                    }
                                }
                            }
                            
                            foreach ($options_array as $key => $option): 
                            ?>
                            <div class="option-item checkbox-option" onclick="selectMultipleOptionWithAnimation(this, '<?php echo $current_question_data['id']; ?>', '<?php echo $key; ?>')">
                                <input type="checkbox" 
                                       name="answers[<?php echo $current_question_data['id']; ?>][]" 
                                       value="<?php echo $key; ?>" 
                                       autocomplete="off"
                                       style="display: none;">
                                <div class="checkbox-icon"></div>
                                <div class="option-letter"><?php echo getOptionLetter($key); ?></div>
                                <div class="option-text"><?php echo htmlspecialchars($option); ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="navigation-container">
                    <div>
                        <?php if ($current_question > 0): ?>
                            <a href="#" 
                               class="nav-button nav-button-secondary"
                               onclick="goToPrevious(); return false;">
                                <i class="fas fa-arrow-left"></i> Previous
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <?php if ($current_question < $total_questions - 1): ?>
                            <button type="button" class="nav-button nav-button-primary" onclick="saveAndNext()">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        <?php else: ?>
                            <button type="submit" name="submit_assessment" class="nav-button nav-button-success" onclick="return validateAndSubmitAssessment()">
                                <i class="fas fa-paper-plane"></i> Submit Assessment
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Achievement Badge -->
        <div class="achievement-badge" id="achievement-badge">
            <i class="fas fa-trophy fa-3x mb-3"></i>
            <div id="achievement-text"></div>
        </div>
        
        <!-- Save Feedback -->
        <div class="save-feedback" id="save-feedback">
            <i class="fas fa-check-circle me-2"></i>Answer saved
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Timer functionality
            let timeLimit = <?php echo max(($assessment['time_limit'] ?? 30) * 60, 300); ?>; // Convert to seconds, minimum 5 minutes
            let timeLeft = timeLimit;
            let timerInterval;
            let startTime = null; // Will be set when assessment actually begins
            
            // Timer persistence key
            const timerKey = 'assessment_timer_' + '<?php echo (string)$assessment_id; ?>';
            
            // Save timer state to localStorage
            function saveTimerState() {
                if (startTime !== null) {
                    const timerState = {
                        startTime: startTime,
                        timeLeft: timeLeft,
                        timeLimit: timeLimit
                    };
                    localStorage.setItem(timerKey, JSON.stringify(timerState));
                    console.log('Timer state saved:', timerState);
                }
            }
            
            // Restore timer state from localStorage
            function restoreTimerState() {
                const savedState = localStorage.getItem(timerKey);
                if (savedState) {
                    try {
                        const timerState = JSON.parse(savedState);
                        startTime = timerState.startTime;
                        timeLeft = timerState.timeLeft;
                        timeLimit = timerState.timeLimit;
                        console.log('Timer state restored:', timerState);
                        return true;
                    } catch (e) {
                        console.log('Failed to parse saved timer state:', e);
                        localStorage.removeItem(timerKey);
                    }
                }
                return false;
            }
            
            // Clear timer state (when assessment is submitted or completed)
            function clearTimerState() {
                localStorage.removeItem(timerKey);
                console.log('Timer state cleared');
            }
            
            function updateTimer() {
                if (startTime === null) {
                    console.log('Timer not started yet, startTime is null');
                    return; // Don't tick until started
                }
                
                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                timeLeft = Math.max(0, timeLimit - elapsed);
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    // Clear timer state since time expired
                    clearTimerState();
                    // Update time taken before submitting
                    const timeTaken = Math.floor((Date.now() - startTime) / 1000);
                    document.getElementById('time-taken').value = timeTaken;
                    document.getElementById('time-expired').value = '1';
                    document.getElementById('auto-submit').value = '1';
                    
                    // Show final alert and submit form automatically
                    showNotification('⏰ Time is up! Your assessment will be submitted automatically.', 'danger');
                    console.log('Time expired, submitting form automatically');
                    
                    // Force form submission immediately
                    const form = document.getElementById('assessment-form');
                    if (form) {
                        console.log('Submitting form automatically...');
                        form.submit();
                    }
                    return; // Stop the timer
                }
                
                timeLeft--;
                
                // Save timer state every 5 seconds
                if (timeLeft % 5 === 0) {
                    saveTimerState();
                }
            }
            
            function beginAssessment() {
                console.log('beginAssessment() called');
                
                // Ensure minimum time limit
                if (timeLimit < 60) {
                    console.log('Time limit too short, setting minimum time');
                    timeLimit = 300; // 5 minutes minimum
                }
                
                // Check if timer state exists in localStorage
                if (!restoreTimerState()) {
                    // No saved state, start fresh
                    startTime = Date.now();
                    timeLeft = timeLimit;
                    console.log('Timer started fresh, timeLimit:', timeLimit, 'seconds');
                } else {
                    console.log('Timer restored from localStorage, timeLeft:', timeLeft, 'seconds');
                }
                
                // Start or restart the timer
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
                timerInterval = setInterval(updateTimer, 1000);
                updateTimer();
                console.log('Timer interval set and updateTimer called');
                
                // Save initial timer state
                saveTimerState();
            }
            
            function showNotification(message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                notification.style.cssText = 'top: 80px; right: 20px; z-index: 1050; min-width: 300px; max-width: 400px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);';
                notification.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'danger' ? 'times-circle' : 'info-circle'} me-2"></i>
                    ${message}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                // Add to page
                document.body.appendChild(notification);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 5000);
            }
            
            function showSaveFeedback() {
                const feedback = document.getElementById('save-feedback');
                feedback.style.display = 'block';
                setTimeout(() => {
                    feedback.style.display = 'none';
                }, 2000);
            }
            
            // Enhanced option selection with animations
            function selectOptionWithAnimation(element, questionId, optionValue) {
                // Add selection animation
                element.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 150);
                
                // Call original function
                selectOption(element, questionId, optionValue);
                
                // Show progress celebration
                celebrateProgress();
            }
            
            // Enhanced multiple option selection
            function selectMultipleOptionWithAnimation(element, questionId, optionValue) {
                // Add selection animation
                element.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 150);
                
                // Call original function
                selectMultipleOption(element, questionId, optionValue);
                
                // Show progress celebration
                celebrateProgress();
            }
            
            function updateProgress() {
                const totalQuestions = <?php echo count($questions); ?>;
                const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;
                const progress = (answeredQuestions / totalQuestions) * 100;
                const progressBar = document.getElementById('progress-bar');
                if (progressBar) {
                    progressBar.style.width = progress + '%';
                }
            }
            
            // Function to clear all form inputs to prevent cached values
            function clearAllFormInputs() {
                console.log('🧹 Clearing all form inputs...');
                
                // Clear text inputs
                const textInputs = document.querySelectorAll('input[type="text"]');
                textInputs.forEach(input => {
                    input.value = '';
                    input.removeAttribute('value');
                });
                
                // Clear radio buttons
                const radioButtons = document.querySelectorAll('input[type="radio"]');
                radioButtons.forEach(radio => {
                    radio.checked = false;
                    radio.removeAttribute('checked');
                });
                
                // Clear checkboxes
                const checkboxes = document.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                    checkbox.removeAttribute('checked');
                });
                
                // Clear any selected options in dropdowns
                const selects = document.querySelectorAll('select');
                selects.forEach(select => {
                    select.selectedIndex = -1;
                });
                
                // Remove any visual selection states from custom elements
                const optionItems = document.querySelectorAll('.option-item');
                optionItems.forEach(item => {
                    item.classList.remove('selected', 'active');
                });
                
                console.log('✅ All form inputs cleared');
            }
            
            // Start assessment immediately when page loads
            document.addEventListener('DOMContentLoaded', function() {
                console.log('🚀 DOM loaded, starting assessment and loading answers...');
                
                // Check if this is truly a new attempt before clearing anything
                const existingTimerState = localStorage.getItem('assessment_timer_' + '<?php echo (string)$assessment_id; ?>');
                const questionIds = <?php echo json_encode(array_column($questions, 'id')); ?>;
                const hasExistingAnswers = questionIds.some(questionId => {
                    return localStorage.getItem('assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId) !== null;
                });
                
                <?php if (!$is_retake && !isset($_GET['continue']) && $current_question == 0): ?>
                // This is potentially a new attempt - only clear if no existing state
                if (!existingTimerState && !hasExistingAnswers) {
                    console.log('🆕 Truly new attempt - clearing all localStorage');
                    // Clear localStorage for truly new attempts
                    questionIds.forEach(questionId => {
                        localStorage.removeItem('assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId);
                    });
                    localStorage.removeItem('assessment_timer_' + '<?php echo (string)$assessment_id; ?>');
                } else {
                    console.log('🔄 Continuing existing attempt - preserving localStorage');
                }
                <?php else: ?>
                console.log('🔄 Navigation within attempt - preserving localStorage');
                <?php endif; ?>
                
                // Clear all form inputs to prevent cached values
                clearAllFormInputs();
                
                // Additional clearing for retakes
                <?php if ($is_retake): ?>
                console.log('Retake detected - additional localStorage clearing...');
                <?php endif; ?>
                
                const timer = document.getElementById('timer');
                console.log('Timer element found:', timer);
                
                // Start assessment immediately (no pre-countdown)
                beginAssessment();
                
                // Prevent back navigation during assessment
                preventBackNavigation();
                
                // Load saved answers for navigation within the same attempt
                // Only skip loading for truly new attempts
                if (!existingTimerState && !hasExistingAnswers && <?php echo (!$is_retake && !isset($_GET['continue']) && $current_question == 0) ? 'true' : 'false'; ?>) {
                    console.log('🆕 Truly new attempt - skipping answer loading to prevent cached values');
                } else {
                    // Load saved answers for current question with a longer delay to ensure DOM is ready
                    setTimeout(() => {
                        console.log('🔄 Attempting to load answers after delay...');
                        loadCurrentQuestionAnswer();
                    }, 500);
                    
                    // Also try when window is fully loaded as backup
                    window.addEventListener('load', function() {
                        console.log('🔄 Window fully loaded, attempting to load answers...');
                        setTimeout(() => {
                            loadCurrentQuestionAnswer();
                        }, 100);
                    });
                    
                    // Additional attempt to load answers after a longer delay for complex DOM
                    setTimeout(() => {
                        console.log('🔄 Final attempt to load answers...');
                        loadCurrentQuestionAnswer();
                    }, 1000);
                }
            });
            
            // Function to prevent back navigation
            function preventBackNavigation() {
                // Add a history entry to prevent back navigation
                if (window.history && window.history.pushState) {
                    window.history.pushState(null, null, window.location.href);
                    
                    // Listen for back button attempts
                    window.addEventListener('popstate', function(event) {
                        // Show warning and prevent navigation
                        if (confirm('You are in the middle of an assessment. Are you sure you want to leave? Your progress will be lost.')) {
                            window.location.href = 'assessments.php';
                        } else {
                            // Push state again to prevent back navigation
                            window.history.pushState(null, null, window.location.href);
                        }
                    });
                }
                
                // Disable back button keyboard shortcut
                document.addEventListener('keydown', function(event) {
                    if (event.altKey && event.keyCode === 37) { // Alt + Left Arrow
                        event.preventDefault();
                        if (confirm('You are in the middle of an assessment. Are you sure you want to leave? Your progress will be lost.')) {
                            window.location.href = 'assessments.php';
                        }
                    }
                });
            }
            
            // Update time taken when form is submitted
            const assessmentForm = document.getElementById('assessment-form');
            if (assessmentForm) {
                assessmentForm.addEventListener('submit', function(e) {
                    console.log('Form submission started');
                    const baselineStart = startTime ?? Date.now();
                    const timeTaken = Math.floor((Date.now() - baselineStart) / 1000);
                    const timeTakenElement = document.getElementById('time-taken');
                    if (timeTakenElement) {
                        timeTakenElement.value = timeTaken;
                    }
                    
                    // Clear timer state since assessment is being submitted
                    clearTimerState();
                    
                    // Remove the beforeunload warning
                    window.removeEventListener('beforeunload', window.beforeUnloadHandler);
                    
                    console.log('Time taken:', timeTaken, 'seconds');
                });
            }
            
            // Save answers when page becomes hidden (tab switch, minimize, etc.)
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    const questionId = '<?php echo $current_question_data['id'] ?? ''; ?>';
                    if (questionId) {
                        saveCurrentAnswer(questionId);
                        console.log('Saved answer due to page visibility change');
                    }
                }
            });
            
            // New functions for one-question-at-a-time interface
            function selectOption(element, questionId, optionValue) {
                console.log('🎯 selectOption called for question:', questionId, 'value:', optionValue);
                
                // Remove selected class from all options for this question
                const allOptions = document.querySelectorAll(`input[name="answers[${questionId}]"]`);
                allOptions.forEach(option => {
                    const optionItem = option.closest('.option-item');
                    if (optionItem) {
                        optionItem.classList.remove('selected');
                    }
                });
                
                // Add selected class to clicked option
                element.classList.add('selected');
                
                // Set the radio button value
                const radioButton = element.querySelector('input[type="radio"]');
                if (radioButton) {
                    radioButton.checked = true;
                    console.log('✅ Radio button checked:', radioButton.value);
                }
                
                // Save answer immediately when option is selected
                saveCurrentAnswer(questionId);
                
                // Show visual feedback
                console.log('🎉 Option selected and saved:', optionValue);
            }
            
            // Function for multiple choice checkboxes
            function selectMultipleOption(element, questionId, optionValue) {
                console.log('🔧 selectMultipleOption called:', {element, questionId, optionValue});
                
                // Toggle the selected class
                element.classList.toggle('selected');
                
                // Toggle the checkbox
                const checkbox = element.querySelector('input[type="checkbox"]');
                console.log('🔧 Checkbox found:', checkbox);
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    console.log('🔧 Checkbox checked:', checkbox.checked);
                }
                
                // Update the checkbox icon
                const icon = element.querySelector('.checkbox-icon');
                if (icon) {
                    if (checkbox.checked) {
                        icon.innerHTML = '✓';
                    } else {
                        icon.innerHTML = '';
                    }
                    console.log('🔧 Icon updated to:', icon.innerHTML);
                }
                
                // Save answer immediately when checkbox is toggled
                saveCurrentAnswer(questionId);
                
                // Show visual feedback
                console.log('🎉 Multiple choice option toggled and saved:', optionValue);
            }
            
            // Function to save current answer immediately
            function saveCurrentAnswer(questionId) {
                let answer = '';
                
                console.log('💾 saveCurrentAnswer called for question:', questionId);
                
                // For identification questions (text input)
                const textInput = document.querySelector(`input[name="answers[${questionId}]"][type="text"]`);
                if (textInput) {
                    answer = textInput.value.trim();
                    console.log('💾 Text input found, answer:', answer);
                } else {
                    // Check if this is a multiple choice question with checkboxes
                    const checkboxes = document.querySelectorAll(`input[name="answers[${questionId}][]"]:checked`);
                    console.log('💾 Checkboxes found:', checkboxes.length);
                    if (checkboxes.length > 0) {
                        // Multiple choice with checkboxes - collect all selected values
                        const selectedValues = Array.from(checkboxes).map(cb => cb.value);
                        answer = selectedValues.join(',');
                        console.log('💾 Multiple choice answer:', answer, 'from values:', selectedValues);
                    } else {
                        // For true/false questions (radio button)
                        const radioButton = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
                        if (radioButton) {
                            answer = radioButton.value;
                            console.log('💾 Radio button answer:', answer);
                        } else {
                            console.log('💾 No input found for question:', questionId);
                        }
                    }
                }
                
                try {
                    // Save answer to localStorage
                    const storageKey = 'assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId;
                    localStorage.setItem(storageKey, answer);
                    console.log('✅ Successfully saved answer for question ' + questionId + ': "' + answer + '"');
                    
                    // Verify the save worked
                    const savedValue = localStorage.getItem(storageKey);
                    console.log('🔍 Verification - saved value:', savedValue);
                    
                    // Show visual feedback that answer was saved
                    showSaveFeedback();
                    
                } catch (error) {
                    console.error('❌ Error saving answer:', error);
                }
            }
            
            // Function to load saved answer for current question
            function loadCurrentQuestionAnswer() {
                console.log('🚀 Loading saved answers...');
                const questionId = '<?php echo $current_question_data['id'] ?? ''; ?>';
                console.log('🔍 Current question ID:', questionId);
                
                if (questionId) {
                    const storageKey = 'assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId;
                    const savedAnswer = localStorage.getItem(storageKey);
                    console.log('📥 Loading saved answer for question ' + questionId + ':', savedAnswer);
                    console.log('🔑 Storage key used:', storageKey);
                    
                    // Check if DOM elements are ready
                    const textInput = document.querySelector(`input[name="answers[${questionId}]"][type="text"]`);
                    const radioButtons = document.querySelectorAll(`input[name="answers[${questionId}]"]`);
                    const checkboxes = document.querySelectorAll(`input[name="answers[${questionId}][]"]`);
                    
                    console.log('🔍 DOM elements found:', {
                        textInput: !!textInput,
                        radioButtons: radioButtons.length,
                        checkboxes: checkboxes.length
                    });
                    
                    if (!textInput && radioButtons.length === 0 && checkboxes.length === 0) {
                        console.log('⚠️ DOM elements not ready yet, retrying in 200ms...');
                        setTimeout(() => {
                            loadCurrentQuestionAnswer();
                        }, 200);
                        return;
                    }
                    
                    if (savedAnswer !== null && savedAnswer !== '') {
                        // For identification questions (text input)
                        if (textInput) {
                            textInput.value = savedAnswer;
                            console.log('✅ Restored text input:', savedAnswer);
                            
                            // Add real-time saving for text input
                            textInput.addEventListener('input', function() {
                                console.log('📝 Text input changed, saving immediately...');
                                saveCurrentAnswer(questionId);
                            });
                            
                            // Also save on blur (when user clicks away)
                            textInput.addEventListener('blur', function() {
                                console.log('📝 Text input blurred, saving...');
                                saveCurrentAnswer(questionId);
                            });
                            
                            // Save on keyup for additional safety
                            textInput.addEventListener('keyup', function() {
                                console.log('📝 Text input keyup, saving...');
                                saveCurrentAnswer(questionId);
                            });
                        } else {
                            // Check if this is a multiple choice question with checkboxes
                            if (savedAnswer.includes(',')) {
                                // Multiple answers (comma-separated)
                                const selectedValues = savedAnswer.split(',');
                                console.log('🔄 Restoring multiple choice answers:', selectedValues);
                                
                                // First, uncheck all checkboxes for this question
                                const allCheckboxes = document.querySelectorAll(`input[name="answers[${questionId}][]"]`);
                                allCheckboxes.forEach(cb => {
                                    cb.checked = false;
                                    const optionItem = cb.closest('.option-item');
                                    if (optionItem) {
                                        optionItem.classList.remove('selected');
                                        const icon = optionItem.querySelector('.checkbox-icon');
                                        if (icon) {
                                            icon.innerHTML = '';
                                        }
                                    }
                                });
                                
                                // Then check the saved ones
                                selectedValues.forEach(value => {
                                    const checkbox = document.querySelector(`input[name="answers[${questionId}][]"][value="${value}"]`);
                                    if (checkbox) {
                                        checkbox.checked = true;
                                        const optionItem = checkbox.closest('.option-item');
                                        if (optionItem) {
                                            optionItem.classList.add('selected');
                                            const icon = optionItem.querySelector('.checkbox-icon');
                                            if (icon) {
                                                icon.innerHTML = '✓';
                                            }
                                        }
                                        console.log('✅ Restored checkbox:', value);
                                    } else {
                                        console.log('❌ Checkbox not found for value:', value);
                                    }
                                });
                            } else {
                                // Single answer - could be radio button OR single checkbox
                                console.log('🔄 Restoring single answer:', savedAnswer);
                                
                                // Try radio buttons first
                                const allRadios = document.querySelectorAll(`input[name="answers[${questionId}]"]`);
                                if (allRadios.length > 0) {
                                    console.log('📻 Found radio buttons, trying radio approach...');
                                    // First, uncheck all radio buttons for this question
                                    allRadios.forEach(radio => {
                                        radio.checked = false;
                                        const optionItem = radio.closest('.option-item');
                                        if (optionItem) {
                                            optionItem.classList.remove('selected');
                                        }
                                    });
                                    
                                    // Then check the saved one
                                    const radioButton = document.querySelector(`input[name="answers[${questionId}]"][value="${savedAnswer}"]`);
                                    if (radioButton) {
                                        radioButton.checked = true;
                                        const optionItem = radioButton.closest('.option-item');
                                        if (optionItem) {
                                            optionItem.classList.add('selected');
                                        }
                                        console.log('✅ Restored radio button:', savedAnswer);
                                    } else {
                                        console.log('❌ Radio button not found for value:', savedAnswer);
                                    }
                                } else {
                                    // Try checkboxes (single selection)
                                    console.log('☑️ No radio buttons found, trying checkbox approach...');
                                    const allCheckboxes = document.querySelectorAll(`input[name="answers[${questionId}][]"]`);
                                    if (allCheckboxes.length > 0) {
                                        console.log('📋 Found checkboxes, trying checkbox approach...');
                                        // First, uncheck all checkboxes for this question
                                        allCheckboxes.forEach(cb => {
                                            cb.checked = false;
                                            const optionItem = cb.closest('.option-item');
                                            if (optionItem) {
                                                optionItem.classList.remove('selected');
                                                const icon = optionItem.querySelector('.checkbox-icon');
                                                if (icon) {
                                                    icon.innerHTML = '';
                                                }
                                            }
                                        });
                                        
                                        // Then check the saved one
                                        const checkbox = document.querySelector(`input[name="answers[${questionId}][]"][value="${savedAnswer}"]`);
                                        if (checkbox) {
                                            checkbox.checked = true;
                                            const optionItem = checkbox.closest('.option-item');
                                            if (optionItem) {
                                                optionItem.classList.add('selected');
                                                const icon = optionItem.querySelector('.checkbox-icon');
                                                if (icon) {
                                                    icon.innerHTML = '✓';
                                                }
                                            }
                                            console.log('✅ Restored checkbox:', savedAnswer);
                                        } else {
                                            console.log('❌ Checkbox not found for value:', savedAnswer);
                                        }
                                    } else {
                                        console.log('❌ No input elements found for question:', questionId);
                                    }
                                }
                            }
                        }
                    } else {
                        console.log('ℹ️ No saved answer found for question:', questionId);
                    }
                    
                    // Always add real-time saving for text input
                    if (textInput) {
                        // Save immediately when typing
                        textInput.addEventListener('input', function() {
                            console.log('📝 Text input changed, saving immediately...');
                            saveCurrentAnswer(questionId);
                        });
                        
                        // Also save on blur (when user clicks away)
                        textInput.addEventListener('blur', function() {
                            console.log('📝 Text input blurred, saving...');
                            saveCurrentAnswer(questionId);
                        });
                        
                        // Save on keyup for additional safety
                        textInput.addEventListener('keyup', function() {
                            console.log('📝 Text input keyup, saving...');
                            saveCurrentAnswer(questionId);
                        });
                    }
                } else {
                    console.log('❌ No question ID found');
                }
            }
            
            // Save current answer before navigation
            function saveCurrentAnswerBeforeNavigation() {
                const questionId = '<?php echo $current_question_data['id'] ?? ''; ?>';
                let answer = '';
                
                if (questionId) {
                    // For identification questions (text input)
                    const textInput = document.querySelector(`input[name="answers[${questionId}]"][type="text"]`);
                    if (textInput) {
                        answer = textInput.value.trim();
                    } else {
                        // Check if this is a multiple choice question with checkboxes
                        const checkboxes = document.querySelectorAll(`input[name="answers[${questionId}][]"]:checked`);
                        if (checkboxes.length > 0) {
                            // Multiple choice with checkboxes - collect all selected values
                            const selectedValues = Array.from(checkboxes).map(cb => cb.value);
                            answer = selectedValues.join(',');
                        } else {
                            // For true/false questions (radio button)
                            const radioButton = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
                            if (radioButton) {
                                answer = radioButton.value;
                            }
                        }
                    }
                    
                    // Always save answer to localStorage (even if empty, to track that question was visited)
                    localStorage.setItem('assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId, answer);
                    console.log('Saved answer for question ' + questionId + ': "' + answer + '"');
                }
            }
            
            // Save answer and navigate to next question
            function saveAndNext() {
                // Get current question ID
                const questionId = '<?php echo $current_question_data['id'] ?? ''; ?>';
                let answer = '';
                
                // Get answer based on question type
                if (questionId) {
                    // For identification questions (text input)
                    const textInput = document.querySelector(`input[name="answers[${questionId}]"][type="text"]`);
                    if (textInput) {
                        answer = textInput.value.trim();
                    } else {
                        // Check if this is a multiple choice question with checkboxes
                        const checkboxes = document.querySelectorAll(`input[name="answers[${questionId}][]"]:checked`);
                        if (checkboxes.length > 0) {
                            // Multiple choice with checkboxes - collect all selected values
                            const selectedValues = Array.from(checkboxes).map(cb => cb.value);
                            answer = selectedValues.join(',');
                        } else {
                            // For true/false questions (radio button)
                            const radioButton = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
                            if (radioButton) {
                                answer = radioButton.value;
                            }
                        }
                    }
                    
                    // Check if answer is provided before allowing navigation
                    if (!answer || answer.trim() === '') {
                        showNotification('⚠️ Please select an answer before proceeding to the next question.', 'warning');
                        return false;
                    }
                    
                    // Always save answer to localStorage (even if empty, to track that question was visited)
                    localStorage.setItem('assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId, answer);
                    console.log('Saved answer for question ' + questionId + ': "' + answer + '"');
                }
                
                // Save timer state before navigating
                saveTimerState();
                
                // Set navigation flag to prevent beforeunload warning
                isNavigatingBetweenQuestions = true;
                
                // Navigate to next question
                const currentQ = <?php echo $current_question; ?>;
                const nextQ = currentQ + 1;
                window.location.href = 'assessment.php?id=<?php echo (string)$assessment_id; ?>&q=' + nextQ;
            }
            
            // Navigate to previous question
            function goToPrevious() {
                console.log('🔄 goToPrevious() called');
                
                // Save current answer before navigating
                const questionId = '<?php echo $current_question_data['id'] ?? ''; ?>';
                console.log('💾 Saving answer for question:', questionId);
                
                if (questionId) {
                    saveCurrentAnswer(questionId);
                }
                
                // Save timer state before navigating
                saveTimerState();
                
                // Set navigation flag to prevent beforeunload warning
                isNavigatingBetweenQuestions = true;
                
                // Navigate to previous question
                const currentQ = <?php echo $current_question; ?>;
                const prevQ = currentQ - 1;
                console.log('🔄 Navigating to previous question:', prevQ);
                window.location.href = 'assessment.php?id=<?php echo (string)$assessment_id; ?>&q=' + prevQ;
            }
            
            // Function to validate and submit assessment
            function validateAndSubmitAssessment() {
                // Get current question ID
                const questionId = '<?php echo $current_question_data['id'] ?? ''; ?>';
                let answer = '';
                
                // Get answer based on question type
                if (questionId) {
                    // For identification questions (text input)
                    const textInput = document.querySelector(`input[name="answers[${questionId}]"][type="text"]`);
                    if (textInput) {
                        answer = textInput.value.trim();
                    } else {
                        // Check if this is a multiple choice question with checkboxes
                        const checkboxes = document.querySelectorAll(`input[name="answers[${questionId}][]"]:checked`);
                        if (checkboxes.length > 0) {
                            // Multiple choice with checkboxes - collect all selected values
                            const selectedValues = Array.from(checkboxes).map(cb => cb.value);
                            answer = selectedValues.join(',');
                        } else {
                            // For true/false questions (radio button)
                            const radioButton = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
                            if (radioButton) {
                                answer = radioButton.value;
                            }
                        }
                    }
                    
                    // Check if answer is provided
                    if (!answer || answer.trim() === '') {
                        showNotification('⚠️ Please select an answer before submitting the assessment.', 'warning');
                        return false;
                    }
                    
                    // Always save answer to localStorage (even if empty, to track that question was visited)
                    localStorage.setItem('assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId, answer);
                    console.log('Saved answer for question ' + questionId + ': "' + answer + '"');
                }
                
                // If all validations pass, show confirmation dialog
                return confirm('Are you sure you want to submit this assessment?');
            }
            
            // Auto-save answers periodically
            setInterval(function() {
                const questionId = '<?php echo $current_question_data['id'] ?? ''; ?>';
                
                if (questionId) {
                    // Use the existing saveCurrentAnswer function for consistency
                    saveCurrentAnswer(questionId);
                    console.log('🔄 Periodic auto-save completed for question ' + questionId);
                }
            }, 15000); // Auto-save every 15 seconds
        </script>
    </body>
    </html>
    <?php
} catch (PDOException $e) {
    error_log('Assessment loading error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unable to load the assessment right now. Please try again later.';
    header('Location: assessments.php');
    exit();
} catch (Exception $e) {
    error_log('Assessment unexpected error: ' . $e->getMessage());
    $_SESSION['error'] = 'An unexpected error occurred. Please try again later.';
    header('Location: assessments.php');
    exit();
}
?>
