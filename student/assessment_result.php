<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/semester_security.php';
require_once '../includes/assessment_pass_tracker.php';

$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$is_retake = isset($_GET['retake']) && $_GET['retake'] === '1';
$previous_attempt_id = isset($_GET['previous_attempt_id']) ? (int)$_GET['previous_attempt_id'] : 0;

// Handle score choice form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'choose_score') {
    $current_attempt_id = (int)($_POST['current_attempt_id'] ?? 0);
    $previous_attempt_id = (int)($_POST['previous_attempt_id'] ?? 0);
    $chosen_score = $_POST['chosen_score'] ?? '';
    
    if ($current_attempt_id && $previous_attempt_id && in_array($chosen_score, ['current', 'previous'])) {
        // Get both attempts
        $stmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE id = ? AND student_id = ?");
        $stmt->execute([$current_attempt_id, $user_id]);
        $current_attempt = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE id = ? AND student_id = ?");
        $stmt->execute([$previous_attempt_id, $user_id]);
        $previous_attempt = $stmt->fetch();
        
        if ($current_attempt && $previous_attempt) {
            if ($chosen_score === 'previous') {
                // Keep previous score - mark current attempt as not counted
                $stmt = $pdo->prepare("UPDATE assessment_attempts SET status = 'abandoned' WHERE id = ?");
                $stmt->execute([$current_attempt_id]);
                $_SESSION['success'] = "You have chosen to keep your previous score of " . $previous_attempt['score'] . "%";
                header('Location: assessment_result.php?attempt_id=' . $previous_attempt_id);
            } else {
                // Keep current score - mark previous attempt as not counted
                $stmt = $pdo->prepare("UPDATE assessment_attempts SET status = 'abandoned' WHERE id = ?");
                $stmt->execute([$previous_attempt_id]);
                $_SESSION['success'] = "You have chosen to keep your current score of " . $current_attempt['score'] . "%";
                header('Location: assessment_result.php?attempt_id=' . $current_attempt_id);
            }
            exit();
        }
    }
    
    $_SESSION['error'] = "Invalid score choice request.";
    header('Location: assessments.php');
    exit();
}

// Debug: Log attempt access
error_log("Assessment result page accessed - Attempt ID: $attempt_id, User ID: $user_id, Retake: " . ($is_retake ? 'Yes' : 'No'));

if (!$attempt_id) {
    error_log("No attempt_id provided, redirecting to assessments");
    header('Location: assessments.php');
    exit();
}

// Get attempt details and verify ownership
$stmt = $pdo->prepare("
    SELECT aa.*, a.assessment_title, a.description as assessment_description, 
           a.passing_rate, c.course_name as course_title, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    WHERE aa.id = ? AND aa.student_id = ?
");
$stmt->execute([$attempt_id, $user_id]);
$attempt = $stmt->fetch();

// Get previous attempt details if this is a retake
$previous_attempt = null;
if ($is_retake && $previous_attempt_id) {
    $stmt = $pdo->prepare("
        SELECT aa.*, a.assessment_title, a.description as assessment_description, 
               a.passing_rate, c.course_name as course_title, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM assessment_attempts aa
        JOIN assessments a ON aa.assessment_id = a.id
        JOIN courses c ON a.course_id = c.id
        JOIN users u ON c.teacher_id = u.id
        WHERE aa.id = ? AND aa.student_id = ?
    ");
    $stmt->execute([$previous_attempt_id, $user_id]);
    $previous_attempt = $stmt->fetch();
}

// Extract module title from JSON modules field
$module_title = 'Module Assessment';
if ($attempt && $attempt['assessment_id']) {
    $stmt = $pdo->prepare("SELECT modules FROM courses c JOIN assessments a ON c.id = a.course_id WHERE a.id = ?");
    $stmt->execute([$attempt['assessment_id']]);
    $course_data = $stmt->fetch();
    if ($course_data && $course_data['modules']) {
        $modules_data = json_decode($course_data['modules'], true);
        if ($modules_data) {
            // Find the module that contains this assessment
            foreach ($modules_data as $module) {
                if (isset($module['assessments']) && is_array($module['assessments'])) {
                    foreach ($module['assessments'] as $module_assessment) {
                        if ($module_assessment['id'] === $attempt['assessment_id']) {
                            $module_title = $module['module_title'] ?? 'Module Assessment';
                            break 2;
                        }
                    }
                }
            }
        }
    }
}
$attempt['module_title'] = $module_title;

if (!$attempt) {
    error_log("Assessment attempt not found - Attempt ID: $attempt_id, User ID: $user_id");
    $_SESSION['error'] = "Assessment result not found.";
    header('Location: assessments.php');
    exit();
}

error_log("Assessment attempt found - Attempt ID: $attempt_id, Score: " . $attempt['score']);

// Get student answers from the assessment_attempts.answers JSON field
$answers_data = json_decode($attempt['answers'], true) ?: [];

// Fetch all questions for this assessment from the questions table
$stmt = $pdo->prepare("
    SELECT id, question_text, question_type, question_order, points, options
    FROM questions 
    WHERE assessment_id = ?
    ORDER BY question_order ASC
");
$stmt->execute([$attempt['assessment_id']]);
$questions_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format questions for display
$questions = [];
foreach ($questions_db as $question) {
            $questions[] = [
        'question_id' => $question['id'],
        'question_text' => $question['question_text'],
        'question_type' => $question['question_type'],
        'question_order' => $question['question_order'],
        'points' => $question['points'],
        'options' => json_decode($question['options'], true) ?: []
    ];
}

// For each question, get student's answer
foreach ($questions as &$question) {
    // Options are already decoded as array from the previous step
    $options_array = $question['options'];

    // Find the correct answer order(s)
    $question['correct_answer'] = null;
    $question['correct_answers'] = [];
    foreach ($question['options'] as $option) {
        if ($option['is_correct']) {
            $question['correct_answers'][] = (int)$option['order'];
            if ($question['correct_answer'] === null) {
                $question['correct_answer'] = (int)$option['order'];
            }
        }
    }

    // Get student's answer from the JSON data
    $question['student_answer'] = null;
    $question['student_id_answer'] = null;
    $question['is_correct'] = false;
    $question['is_answered'] = false;
    $question['is_unanswered'] = false;
    
    // Find the answer for this question in the JSON data
    foreach ($answers_data as $answer_data) {
        if ($answer_data['question_id'] == $question['question_id']) {
            // For multiple choice, student_answer contains the option index (0, 1, 2, 3)
            // For identification and true/false, student_answer contains the text answer
            if ($question['question_type'] === 'multiple_choice') {
                $question['student_answer'] = $answer_data['student_answer'] ?? null;
                $question['is_answered'] = $question['student_answer'] !== null && $question['student_answer'] !== '';
            } else {
                $question['student_id_answer'] = $answer_data['student_answer'] ?? null;
                $question['is_answered'] = !empty($question['student_id_answer']);
            }
            
            // Determine if the question is truly answered (not just visited)
            if ($question['is_answered']) {
                // Re-validate the answer to ensure accuracy
                if ($question['question_type'] === 'identification') {
                    $correct_text = null;
                    foreach ($question['options'] as $option) {
                        if ($option['is_correct']) {
                            $correct_text = $option['text'] ?? '';
                            break;
                        }
                    }
                    $question['is_correct'] = strtoupper(trim($question['student_id_answer'] ?? '')) === strtoupper(trim($correct_text ?? ''));
                } elseif ($question['question_type'] === 'true_false') {
                    $correct_text = null;
                    foreach ($question['options'] as $option) {
                        if ($option['is_correct']) {
                            $correct_text = $option['text'] ?? '';
                            break;
                        }
                    }
                    $question['is_correct'] = strtoupper(trim($question['student_id_answer'] ?? '')) === strtoupper(trim($correct_text ?? ''));
                } else {
                    // Multiple choice - handle both single and multiple answers
                    if (!empty($question['student_answer'])) {
                        $student_answers = strpos($question['student_answer'], ',') !== false ? 
                            explode(',', $question['student_answer']) : [$question['student_answer']];
                        $student_answers = array_map('intval', $student_answers);
                        sort($student_answers);
                        sort($question['correct_answers']);
                        $question['is_correct'] = $student_answers === $question['correct_answers'];
                    } else {
                        $question['is_correct'] = false;
                    }
                }
            } else {
                // Question was visited but not answered (empty answer)
                $question['is_unanswered'] = true;
                $question['is_correct'] = false;
            }
            break;
        }
    }
    
    // If no answer entry found at all, question was never visited
    if (!$question['is_answered'] && !$question['is_unanswered']) {
        $question['is_unanswered'] = true;
        $question['is_correct'] = false;
    }
}
unset($question);

// Calculate statistics using the corrected validation logic
$total_questions = count($questions);
$correct_answers = 0;
$incorrect_answers = 0;
$unanswered = 0;

foreach ($questions as $question) {
    if ($question['is_unanswered']) {
        $unanswered++;
    } elseif ($question['is_correct']) {
        $correct_answers++;
    } else {
        $incorrect_answers++;
    }
}

// Calculate accuracy percentage - ensure it's not capped by passing rate
$accuracy = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100) : 0;

// Ensure accuracy is not artificially limited by passing rate
// This fixes the bug where scores might be capped at the passing rate (e.g., 80%)
if ($accuracy > 100) {
    $accuracy = 100; // Cap at 100% maximum
}

// Determine pass/fail status based on passing rate
$passing_rate = $attempt['passing_rate'] ?? 70.0; // Default to 70% if not set
$is_passed = $accuracy >= $passing_rate;

// Debug: Log score calculation for troubleshooting
error_log("Assessment Result Debug - Attempt ID: " . $attempt_id . ", Calculated Accuracy: " . $accuracy . "%, Stored Score: " . $attempt['score'] . "%, Passing Rate: " . $passing_rate . "%");
$pass_fail_status = $is_passed ? 'PASSED' : 'FAILED';
$pass_fail_class = $is_passed ? 'success' : 'danger';

// Calculate time taken
// Allotted time from assessment (in minutes)
if (isset($attempt['time_limit'])) {
    $allotted_time = (int)$attempt['time_limit'];
} else {
    // Fallback: fetch from assessments table if not present in $attempt
    $stmt = $pdo->prepare("SELECT time_limit FROM assessments WHERE id = ?");
    $stmt->execute([$attempt['assessment_id']]);
    $allotted_time = (int)($stmt->fetchColumn() ?: 0);
}
// Time taken in seconds
$time_taken = 0;
if ($attempt['completed_at'] && $attempt['started_at']) {
    $start_time = strtotime($attempt['started_at']);
    $end_time = strtotime($attempt['completed_at']);
    $time_taken = $end_time - $start_time;
}
$minutes = floor($time_taken / 60);
$seconds = $time_taken % 60;

// Fetch assessment, module, and course details, including academic period status
$stmt = $pdo->prepare("SELECT a.*, c.id as course_id, c.academic_period_id, ap.is_active as academic_period_active FROM assessments a JOIN courses c ON a.course_id = c.id JOIN academic_periods ap ON c.academic_period_id = ap.id WHERE a.id = ?");
$stmt->execute([$attempt['assessment_id']]);
$assessment_info = $stmt->fetch(PDO::FETCH_ASSOC);
$is_acad_year_active = $assessment_info ? (bool)$assessment_info['academic_period_active'] : true;

// Get assessment academic status using helper function
$assessment_status = checkAssessmentAcademicStatus($pdo, $attempt['assessment_id']);
$is_view_only = !$assessment_status['is_active'];


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Result - <?php echo htmlspecialchars($attempt['assessment_title'] ?? ''); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        /* Root Variables */
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --success-color: #10b981;
            --success-light: #d1fae5;
            --danger-color: #ef4444;
            --danger-light: #fee2e2;
            --warning-color: #f59e0b;
            --warning-light: #fef3c7;
            --info-color: #3b82f6;
            --info-light: #dbeafe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Global Styles */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            color: var(--gray-800);
        }

        .container-fluid {
            background: transparent;
        }

        /* Header Styling */
        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .breadcrumb-item a {
            color: var(--gray-600);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb-item a:hover {
            color: var(--primary-color);
        }

        .breadcrumb-item.active {
            color: var(--gray-800);
            font-weight: 500;
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--gray-800);
            font-weight: 600;
        }

        .text-muted {
            color: var(--gray-600) !important;
        }

        /* Card Styling */
        .result-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            transition: var(--transition);
            overflow: hidden;
        }

        .result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 1.25rem 1.5rem;
        }

        .card-header.bg-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);
        }

        .card-header.bg-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
        }

        .card-header.bg-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
        }

        /* Score Circle */
        .score-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: conic-gradient(
                var(--success-color) 0deg, 
                var(--success-color) <?php echo min(100, max(0, $accuracy)) * 3.6; ?>deg, 
                var(--gray-200) <?php echo min(100, max(0, $accuracy)) * 3.6; ?>deg, 
                var(--gray-200) 360deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .score-circle:hover {
            transform: scale(1.05);
        }

        .score-circle::before {
            content: '';
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: white;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .score-text {
            position: absolute;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--success-color);
            z-index: 2;
        }

        /* Badge Styling */
        .badge {
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%) !important;
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%) !important;
        }

        .badge.bg-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%) !important;
        }

        .badge.bg-info {
            background: linear-gradient(135deg, var(--info-color) 0%, #2563eb 100%) !important;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%) !important;
        }

        /* Question Results */
        .question-result {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .question-result:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .question-result.correct {
            border-left: 4px solid var(--success-color);
            background: linear-gradient(135deg, var(--success-light) 0%, white 100%);
        }

        .question-result.incorrect {
            border-left: 4px solid var(--danger-color);
            background: linear-gradient(135deg, var(--danger-light) 0%, white 100%);
        }

        .question-result.unanswered {
            border-left: 4px solid var(--gray-400);
            background: linear-gradient(135deg, var(--gray-100) 0%, white 100%);
        }

        /* Options Styling */
        .option {
            padding: 1rem;
            margin: 0.5rem 0;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            background: white;
            transition: var(--transition);
            position: relative;
        }

        .option:hover {
            border-color: var(--gray-300);
            transform: translateX(4px);
        }

        .option-selected {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, var(--info-light) 0%, white 100%);
            box-shadow: var(--shadow-sm);
        }

        .option-correct {
            border-color: var(--success-color);
            background: linear-gradient(135deg, var(--success-light) 0%, white 100%);
            box-shadow: var(--shadow-sm);
        }

        .option-selected.option-correct {
            border-color: var(--success-color);
            background: linear-gradient(135deg, var(--success-light) 0%, white 100%);
            box-shadow: var(--shadow-md);
        }

        /* Button Styling */
        .btn {
            font-weight: 600;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            box-shadow: var(--shadow-md);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-2px);
        }

        /* Alert Styling */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            color: var(--success-color);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            color: var(--danger-color);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
            color: var(--warning-color);
            border-color: rgba(245, 158, 11, 0.2);
        }

        /* Performance Summary */
        .performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .performance-item:last-child {
            border-bottom: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .score-circle {
                width: 150px;
                height: 150px;
            }
            
            .score-circle::before {
                width: 120px;
                height: 120px;
            }
            
            .score-text {
                font-size: 2rem;
            }
            
            .result-card {
                margin-bottom: 1rem;
            }
        }

        /* Animation for score reveal */
        @keyframes scoreReveal {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .score-circle {
            animation: scoreReveal 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Loading animation */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .loading {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/badge_notification.php'; ?>
    <?php displayBadgeNotifications(); ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Removed Sidebar -->
            <main class="col-12 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="assessments.php">Assessments</a></li>
                                <li class="breadcrumb-item"><a href="assessment.php?id=<?php echo $attempt['assessment_id']; ?>"><?php echo htmlspecialchars($attempt['assessment_title'] ?? ''); ?></a></li>
                                <li class="breadcrumb-item active">Result</li>
                            </ol>
                        </nav>
                        <h1 class="h2">Assessment Result</h1>
                        <p class="text-muted">
                            <?php echo htmlspecialchars($attempt['course_title'] ?? ''); ?> - 
                            <?php echo htmlspecialchars($attempt['module_title'] ?? ''); ?>
                        </p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="assessments.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Assessments
                        </a>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $_SESSION['warning']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['warning']); ?>
                <?php endif; ?>

                <?php if ($is_view_only): ?>
                    <div class="alert alert-warning mb-4">
                        <?php echo getInactiveStatusMessage($assessment_status); ?>
                    </div>
                <?php endif; ?>



                <!-- Result Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card result-card text-center">
                            <div class="card-body">
                                <div class="position-relative">
                                    <div class="score-circle"></div>
                                    <div class="score-text"><?php echo $accuracy; ?>%</div>
                                </div>
                                <!-- Debug: Show both calculated and stored scores -->
                                <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Calculated: <?php echo $accuracy; ?>% | 
                                            Stored: <?php echo $attempt['score']; ?>% | 
                                            Passing: <?php echo $passing_rate; ?>%
                                        </small>
                                    </div>
                                <?php endif; ?>
                                <h5 class="card-title mt-3">Your Score</h5>
                                <p class="card-text"><?php echo $correct_answers; ?> out of <?php echo $total_questions; ?> correct</p>
                                <div class="mt-3">
                                    <span class="badge bg-<?php echo $is_passed ? 'success' : 'danger'; ?> fs-6">
                                        <?php echo $is_passed ? 'PASSED' : 'FAILED'; ?>
                                    </span>
                                    <br>
                                    <small class="text-muted mt-2 d-block">
                                        Passing Rate: <?php echo $passing_rate; ?>%
                                        <?php if (!$is_passed): ?>
                                            <br>
                                            <?php 
                                            $required_correct = ceil(($passing_rate / 100) * $total_questions);
                                            $more_needed = $required_correct - $correct_answers;
                                            ?>
                                            Need <?php echo $more_needed; ?> more correct answer<?php echo $more_needed == 1 ? '' : 's'; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <?php if ($is_retake && $previous_attempt): ?>
                        <!-- Score Comparison for Retakes -->
                        <div class="card result-card mb-4">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0 text-dark">
                                    <i class="fas fa-balance-scale me-2"></i>Score Comparison - Choose Your Best Score
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="text-center p-3 border rounded <?php echo $previous_attempt['score'] > $accuracy ? 'bg-success bg-opacity-10' : ''; ?>">
                                            <h6 class="text-muted">Previous Best Score</h6>
                                            <div class="display-6 fw-bold text-primary"><?php echo $previous_attempt['score']; ?>%</div>
                                            <small class="text-muted">
                                                <?php echo $previous_attempt['correct_answers'] ?? 'N/A'; ?> out of <?php echo $previous_attempt['max_score'] ?? 'N/A'; ?> correct
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                Completed: <?php echo date('M j, Y g:i A', strtotime($previous_attempt['completed_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center p-3 border rounded <?php echo $accuracy > $previous_attempt['score'] ? 'bg-success bg-opacity-10' : ''; ?>">
                                            <h6 class="text-muted">Current Attempt Score</h6>
                                            <div class="display-6 fw-bold text-success"><?php echo $accuracy; ?>%</div>
                                            <small class="text-muted">
                                                <?php echo $correct_answers; ?> out of <?php echo $total_questions; ?> correct
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                Completed: <?php echo date('M j, Y g:i A', strtotime($attempt['completed_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 text-center">
                                    <form method="POST" action="assessment_result.php" class="d-inline">
                                        <input type="hidden" name="action" value="choose_score">
                                        <input type="hidden" name="current_attempt_id" value="<?php echo $attempt_id; ?>">
                                        <input type="hidden" name="previous_attempt_id" value="<?php echo $previous_attempt_id; ?>">
                                        
                                        <div class="btn-group" role="group">
                                            <button type="submit" name="chosen_score" value="previous" class="btn btn-outline-primary">
                                                <i class="fas fa-arrow-left me-2"></i>Keep Previous Score (<?php echo $previous_attempt['score']; ?>%)
                                            </button>
                                            <button type="submit" name="chosen_score" value="current" class="btn btn-success">
                                                <i class="fas fa-arrow-right me-2"></i>Keep Current Score (<?php echo $accuracy; ?>%)
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="mt-3 text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Choose which score you want to keep as your final score for this assessment.
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card result-card">
                            <div class="card-header">
                                <h5 class="mb-0">Performance Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="performance-item">
                                            <span><i class="fas fa-check-circle text-success me-2"></i>Correct Answers:</span>
                                            <span class="badge bg-success"><?php echo $correct_answers; ?></span>
                                        </div>
                                        <div class="performance-item">
                                            <span><i class="fas fa-times-circle text-danger me-2"></i>Incorrect Answers:</span>
                                            <span class="badge bg-danger"><?php echo $incorrect_answers; ?></span>
                                        </div>
                                        <div class="performance-item">
                                            <span><i class="fas fa-question-circle text-warning me-2"></i>Unanswered:</span>
                                            <span class="badge bg-warning"><?php echo $unanswered; ?></span>
                                        </div>
                                        <div class="performance-item">
                                            <span><i class="fas fa-percentage text-info me-2"></i>Passing Rate:</span>
                                            <span class="badge bg-info"><?php echo $passing_rate; ?>%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row mb-2">
                                            <div class="col-6 text-start">
                                                <span class="fw-semibold">Allotted Time:</span>
                                            </div>
                                            <div class="col-6 text-end">
                                                <span><?php echo $allotted_time; ?> minute<?php echo $allotted_time == 1 ? '' : 's'; ?></span>
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-6 text-start">
                                                <span class="fw-semibold">Time Taken:</span>
                                            </div>
                                            <div class="col-6 text-end">
                                                <span><?php echo $minutes; ?>m <?php echo $seconds; ?>s</span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Attempt Date:</span>
                                            <span><?php echo date('M j, Y g:i A', strtotime($attempt['started_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Question Review -->
                <div class="card result-card">
                    <div class="card-header">
                        <h5 class="mb-0">Question Review</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($questions as $index => $question): ?>
                            <?php 
                            // Use the corrected validation logic from above
                            $is_correct = $question['is_correct'];
                            $is_unanswered = $question['is_unanswered'];
                            $result_class = $is_correct ? 'correct' : ($is_unanswered ? 'unanswered' : 'incorrect');
                            ?>
                            <div class="question-result <?php echo $result_class; ?> p-3">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="mb-0">Question <?php echo $index + 1; ?></h6>
                                    <div>
                                        <?php if ($is_unanswered): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-minus"></i> Unanswered
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <p class="mb-3"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                
                                <?php if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false'): ?>
                                    <div class="options">
                                        <?php 
                                        $optionLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                                        foreach ($question['options'] as $idx => $option): 
                                            $letter = $optionLetters[$idx] ?? chr(65 + $idx);
                                            $order = (int)$option['order'];
                                            
                                            // Check if this option is selected (handle both single and multiple answers)
                                            $is_selected = false;
                                            if (!empty($question['student_answer'])) {
                                                $student_answers = strpos($question['student_answer'], ',') !== false ? 
                                                    explode(',', $question['student_answer']) : [$question['student_answer']];
                                                $student_answers = array_map('intval', $student_answers);
                                                $is_selected = in_array($order, $student_answers);
                                            }
                                            
                                            $is_correct = $option['is_correct'];
                                        ?>
                                            <div class="option <?php echo $is_selected ? 'option-selected' : ''; ?> <?php echo $is_correct ? 'option-correct' : ''; ?>">
                                                <span class="option-label" style="font-weight:bold; margin-right:8px;">
                                                    <?php echo $letter; ?>.
                                                </span>
                                                <?php echo htmlspecialchars($option['text'] ?? ''); ?>
                                                <?php if ($is_selected): ?>
                                                    <span class="badge bg-primary ms-2">Your Answer</span>
                                                <?php endif; ?>
                                                <?php if ($is_correct): ?>
                                                    <span class="badge bg-success ms-2">Correct Answer</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($question['question_type'] === 'true_false'): ?>
                                    <div class="options">
                                        <?php 
                                        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                                        foreach ($question['options'] as $idx => $option): 
                                            $letter = $letters[$idx] ?? ($idx + 1);
                                            $is_selected = ($option['text'] == $question['student_id_answer']);
                                            $is_correct = $option['is_correct'];
                                        ?>
                                            <div class="option <?php echo $is_selected ? 'option-selected' : ''; ?> <?php echo $is_correct ? 'option-correct' : ''; ?>">
                                                <span class="option-label" style="font-weight:bold; margin-right:8px;">
                                                    <?php echo $letter; ?>.
                                                </span>
                                                <?php echo htmlspecialchars($option['text'] ?? ''); ?>
                                                <?php if ($is_selected): ?>
                                                    <span class="badge bg-primary ms-2">Your Answer</span>
                                                <?php endif; ?>
                                                <?php if ($is_correct): ?>
                                                    <span class="badge bg-success ms-2">Correct Answer</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($question['question_type'] === 'identification'): ?>
                                    <div class="border rounded p-3 bg-light">
                                        <strong>Your Answer:</strong><br>
                                        <?php echo htmlspecialchars($question['student_id_answer'] ?? 'No answer provided'); ?>
                                    </div>
                                <?php endif; ?>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
                    <?php 
                    // Check if student has ever passed this assessment
                    $has_ever_passed = hasStudentPassedAssessment($pdo, $user_id, $attempt['assessment_id']);
                    ?>
                    <?php if (!$is_view_only && !$has_ever_passed): ?>
                        <a href="assessment.php?id=<?php echo $attempt['assessment_id']; ?>&reset=1" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Retake Assessment
                        </a>
                    <?php elseif ($has_ever_passed): ?>
                        <button class="btn btn-success disabled" disabled>
                            <i class="fas fa-check-circle"></i> Assessment Passed - No Retakes Allowed
                        </button>
                    <?php else: ?>
                        <button class="btn btn-primary disabled" disabled>
                            <i class="fas fa-redo"></i> Retake Assessment (Disabled)
                        </button>
                    <?php endif; ?>
                    <a href="assessments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list"></i> View All Assessments
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html> 