<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$assessment_id = $_GET['id'] ?? null;
if (!$assessment_id) {
    header('Location: assessments.php');
    exit();
}

// Get assessment details
$assessment_query = "SELECT a.*, c.course_name, c.course_code 
                     FROM assessments a 
                     LEFT JOIN courses c ON a.course_id = c.id 
                     WHERE a.id = ?";
$assessment_stmt = $pdo->prepare($assessment_query);
$assessment_stmt->execute([$assessment_id]);
$assessment = $assessment_stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    header('Location: assessments.php');
    exit();
}

// Get assessment statistics
$stats_query = "SELECT 
                    COUNT(DISTINCT aa.student_id) as total_students,
                    COUNT(aa.id) as total_attempts,
                    AVG(aa.score) as average_score,
                    MAX(aa.score) as highest_score,
                    MIN(aa.score) as lowest_score,
                    SUM(CASE WHEN aa.has_passed = 1 THEN 1 ELSE 0 END) as passed_attempts,
                    AVG(aa.time_taken) as average_time_taken
                FROM assessment_attempts aa 
                WHERE aa.assessment_id = ? AND aa.status = 'completed'";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$assessment_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get question-level statistics
$question_stats_query = "SELECT 
                            aa.answers,
                            aa.score,
                            aa.student_id,
                            u.first_name,
                            u.last_name,
                            u.username
                         FROM assessment_attempts aa 
                         LEFT JOIN users u ON aa.student_id = u.id 
                         WHERE aa.assessment_id = ? AND aa.status = 'completed' AND aa.answers IS NOT NULL";
$question_stats_stmt = $pdo->prepare($question_stats_query);
$question_stats_stmt->execute([$assessment_id]);
$attempts = $question_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get questions from questions table
$questions_query = "SELECT id, question_text, question_type, points, options 
                    FROM questions 
                    WHERE assessment_id = ? 
                    ORDER BY question_order ASC";
$questions_stmt = $pdo->prepare($questions_query);
$questions_stmt->execute([$assessment_id]);
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate question-level statistics
$question_analysis = [];
if (!empty($questions) && !empty($attempts)) {
    foreach ($questions as $question) {
        $question_id = $question['id'];
        $question_analysis[$question_id] = [
            'question_text' => $question['question_text'],
            'question_type' => $question['question_type'],
            'points' => $question['points'],
            'options' => json_decode($question['options'], true) ?: [],
            'total_attempts' => 0,
            'correct_attempts' => 0,
            'accuracy_rate' => 0,
            'average_time' => 0,
            'common_wrong_answers' => [],
            'difficulty_level' => 'medium'
        ];
    }

    // Analyze each attempt
    foreach ($attempts as $attempt) {
        $answers = json_decode($attempt['answers'], true);
        if ($answers) {
            foreach ($answers as $answer) {
                $q_id = $answer['question_id'] ?? null;
                if ($q_id && isset($question_analysis[$q_id])) {
                    $question_analysis[$q_id]['total_attempts']++;
                    if ($answer['is_correct']) {
                        $question_analysis[$q_id]['correct_attempts']++;
                    } else {
                        // Track wrong answers with better formatting
                        $wrong_answer = $answer['student_answer'];
                        if (empty($wrong_answer)) {
                            $wrong_answer = '[No Answer]';
                        }
                        
                        // For multiple choice, show the actual option text
                        if ($question_analysis[$q_id]['question_type'] === 'multiple_choice' && is_numeric($wrong_answer)) {
                            $option_index = (int)$wrong_answer - 1;
                            if (isset($question_analysis[$q_id]['options'][$option_index])) {
                                $wrong_answer = $question_analysis[$q_id]['options'][$option_index]['text'] . ' (Option ' . $wrong_answer . ')';
                            }
                        }
                        
                        if (!isset($question_analysis[$q_id]['common_wrong_answers'][$wrong_answer])) {
                            $question_analysis[$q_id]['common_wrong_answers'][$wrong_answer] = 0;
                        }
                        $question_analysis[$q_id]['common_wrong_answers'][$wrong_answer]++;
                    }
                }
            }
        }
    }

    // Calculate final statistics
    foreach ($question_analysis as $q_id => &$analysis) {
        if ($analysis['total_attempts'] > 0) {
            $analysis['accuracy_rate'] = ($analysis['correct_attempts'] / $analysis['total_attempts']) * 100;
            
            // Determine difficulty level based on accuracy
            if ($analysis['accuracy_rate'] >= 80) {
                $analysis['difficulty_level'] = 'easy';
            } elseif ($analysis['accuracy_rate'] >= 60) {
                $analysis['difficulty_level'] = 'medium';
            } else {
                $analysis['difficulty_level'] = 'hard';
            }
        }
        
        // Sort common wrong answers by frequency
        arsort($analysis['common_wrong_answers']);
        $analysis['common_wrong_answers'] = array_slice($analysis['common_wrong_answers'], 0, 3, true);
    }
}

// Sort questions by difficulty (hardest first)
uasort($question_analysis, function($a, $b) {
    return $a['accuracy_rate'] <=> $b['accuracy_rate'];
});

$page_title = "Assessment Statistics - " . htmlspecialchars($assessment['assessment_title']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --main-green: #2E5E4E;
            --accent-green: #7DCB80;
            --highlight-yellow: #FFE066;
            --off-white: #F7FAF7;
            --white: #FFFFFF;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-light: #e9ecef;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 8px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --border-radius-xl: 20px;
            --transition: all 0.3s ease;
            --light-green: #4a7c59;
            --bg-light: #f8f9fa;
            --border-color: #dee2e6;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body {
            background-color: var(--off-white);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Page Container */
        .page-container {
            background: var(--off-white);
            min-height: 100vh;
        }

        .main-header {
            background: var(--main-green);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: var(--border-radius-xl);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .main-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }

        .main-header .container {
            position: relative;
            z-index: 1;
        }

        .stats-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            margin-bottom: 1rem;
        }

        .stats-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stats-icon.success { background: var(--success-color); color: white; }
        .stats-icon.warning { background: var(--warning-color); color: white; }
        .stats-icon.danger { background: var(--danger-color); color: white; }
        .stats-icon.info { background: var(--info-color); color: white; }

        .question-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .question-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .question-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--main-green);
        }

        .question-card.easy::before { background: var(--success-color); }
        .question-card.medium::before { background: var(--warning-color); }
        .question-card.hard::before { background: var(--danger-color); }

        .difficulty-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .difficulty-easy { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }
        .difficulty-medium { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); }
        .difficulty-hard { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); }

        .accuracy-bar {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }

        .accuracy-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.8s ease;
        }

        .accuracy-high { background: var(--success-color); }
        .accuracy-medium { background: var(--warning-color); }
        .accuracy-low { background: var(--danger-color); }

        .wrong-answer-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            margin: 0.25rem;
            font-size: 0.85rem;
        }

        .back-btn {
            background: var(--main-green);
            border: none;
            color: white;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .back-btn:hover {
            background: var(--accent-green);
            color: var(--main-green);
            transform: translateY(-1px);
        }

        .no-data-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .no-data-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .question-number {
            background: var(--main-green);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        /* Question Options Styling */
        .question-options {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid #e9ecef;
        }

        .option-item {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            background: white;
            border: 1px solid #dee2e6;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .option-item:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
        }

        .correct-option {
            background: rgba(40, 167, 69, 0.1) !important;
            border-color: var(--success-color) !important;
            font-weight: 600;
        }

        .option-number {
            background: var(--main-green);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .correct-option .option-number {
            background: var(--success-color);
        }

        .wrong-answer-item {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #c53030;
            border-radius: 6px;
            padding: 0.4rem 0.6rem;
            margin: 0.25rem;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .no-data-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .no-data-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Enhanced Question Statistics */
        .question-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-item.success .stat-value { color: var(--success-color); }
        .stat-item.warning .stat-value { color: var(--warning-color); }
        .stat-item.danger .stat-value { color: var(--danger-color); }
        .stat-item.info .stat-value { color: var(--info-color); }

        /* Performance Indicators */
        .performance-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }

        .performance-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .performance-icon.excellent { background: var(--success-color); color: white; }
        .performance-icon.good { background: #17a2b8; color: white; }
        .performance-icon.average { background: var(--warning-color); color: white; }
        .performance-icon.poor { background: var(--danger-color); color: white; }

        /* Enhanced Question Text */
        .question-text {
            font-size: 1.1rem;
            line-height: 1.6;
            color: var(--text-dark);
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--main-green);
        }

        /* Enhanced Options Display */
        .options-container {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .option-item {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .option-item:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .option-item.correct-option {
            background: rgba(40, 167, 69, 0.1);
            border-color: var(--success-color);
            font-weight: 600;
        }

        .option-item.correct-option::after {
            content: '✓';
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--success-color);
            font-weight: bold;
        }

        /* Wrong Answers Enhancement */
        .wrong-answers-container {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .wrong-answer-item {
            background: white;
            border: 1px solid #fed7d7;
            color: #c53030;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            margin: 0.25rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            position: relative;
        }

        .wrong-answer-item::before {
            content: '✗';
            margin-right: 0.5rem;
            color: #e53e3e;
        }

        /* Clickable Stats Card */
        .stats-card.clickable {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .stats-card.clickable:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.2);
        }

        .stats-card.clickable .stats-icon {
            transition: all 0.3s ease;
        }

        .stats-card.clickable:hover .stats-icon {
            transform: scale(1.1);
        }

        /* Clickable Question Stats Item */
        .stat-item.clickable {
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .stat-item.clickable:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-radius: 8px;
        }

        .stat-item.clickable::after {
            content: '→';
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.2rem;
            color: var(--main-green);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-item.clickable:hover::after {
            opacity: 1;
        }

        /* Avatar circles for student names */
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .avatar-text {
            text-transform: uppercase;
        }

        /* Enhanced table styling */
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }

        .table-success {
            background-color: rgba(25, 135, 84, 0.1) !important;
        }

        .table-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        /* Progress bar enhancements */
        .progress {
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 0.6s ease;
        }

        /* Card hover effects */
        .card:hover {
            transform: translateY(-2px);
            transition: transform 0.3s ease;
        }

        /* Badge enhancements */
        .badge.fs-6 {
            font-size: 0.9rem !important;
            padding: 0.5rem 0.75rem;
        }

        /* Question options styling */
        .option-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 16px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .option-card:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .option-card.correct-option {
            border-color: #28a745;
            background: #d4edda;
        }

        .option-letter {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .correct-option .option-letter {
            background: #28a745;
        }

        .option-text {
            flex: 1;
            font-weight: 500;
        }

        /* Enhanced Modal Styling - Admin Bell Modal Design */
        .modal-content {
            border-radius: var(--border-radius-lg);
            border: none;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            background: var(--main-green);
            color: white;
            border: none;
            padding: 1.5rem;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
            position: relative;
            overflow: hidden;
        }

        .modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }

        .modal-header .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
            margin: 0;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .modal-header .btn-close {
            filter: invert(1);
            position: relative;
            z-index: 1;
        }

        .modal-body {
            padding: 2rem;
            background: #fafbfc;
            flex: 1;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }

        .modal-footer {
            background: #f8f9fa;
            border-top: 2px solid #e9ecef;
            padding: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            min-height: 80px;
            align-items: center;
            flex-shrink: 0;
            position: sticky;
            bottom: 0;
        }

        .modal-footer .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .student-attempt-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .student-attempt-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .attempt-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .attempt-score {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .attempt-score.passed {
            color: var(--success-color);
        }

        .attempt-score.failed {
            color: var(--danger-color);
        }

        .student-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .student-username {
            color: #6c757d;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .main-header {
                padding: 1.5rem 0;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .question-card {
                margin-bottom: 1rem;
            }
            
            .question-options .row {
                margin: 0;
            }
            
            .question-options .col-md-6 {
                padding: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="page-container">
        <div class="container-fluid py-4">
            <div class="main-header">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-3">
                                <a href="assessments.php" class="back-btn me-3">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Back to Assessments
                                </a>
                            </div>
                            <h1 class="mb-2" style="font-size: 2.5rem; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <i class="bi bi-graph-up me-3"></i>
                                Assessment Statistics
                            </h1>
                            <h3 class="mb-0 text-light">
                                <?php echo htmlspecialchars($assessment['assessment_title']); ?>
                            </h3>
                            <p class="mb-0 text-light opacity-75">
                                <i class="bi bi-book me-2"></i>
                                <?php echo htmlspecialchars($assessment['course_name']); ?> 
                                <span class="mx-2">•</span>
                                <i class="bi bi-calendar me-2"></i>
                                Created <?php echo date('M j, Y', strtotime($assessment['created_at'])); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="assessment-meta">
                                <div class="badge bg-light text-dark fs-6 mb-2">
                                    <i class="bi bi-speedometer2 me-1"></i>
                                    <?php echo ucfirst($assessment['difficulty']); ?> Difficulty
                                </div>
                                <div class="badge bg-light text-dark fs-6 mb-2">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo $assessment['time_limit'] ? $assessment['time_limit'] . ' min' : 'No limit'; ?>
                                </div>
                                <div class="badge bg-light text-dark fs-6">
                                    <i class="bi bi-trophy me-1"></i>
                                    <?php echo $assessment['passing_rate']; ?>% Passing Rate
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="container">
        <?php if (empty($attempts)): ?>
            <div class="no-data-state">
                <i class="bi bi-graph-down"></i>
                <h4>No Data Available</h4>
                <p>This assessment hasn't been attempted by any students yet.</p>
                <a href="assessments.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>
                    Back to Assessments
                </a>
            </div>
        <?php else: ?>
            <!-- Overall Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon success mx-auto">
                                <i class="bi bi-people"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['total_students']; ?></h3>
                            <p class="text-muted mb-0">Total Students</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card clickable h-100" data-bs-toggle="modal" data-bs-target="#attemptsModal">
                        <div class="card-body text-center">
                            <div class="stats-icon info mx-auto">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['total_attempts']; ?></h3>
                            <p class="text-muted mb-0">Total Attempts <i class="bi bi-arrow-right-circle ms-1"></i></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon <?php echo $stats['average_score'] >= 70 ? 'success' : ($stats['average_score'] >= 50 ? 'warning' : 'danger'); ?> mx-auto">
                                <i class="bi bi-trophy"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['average_score'], 1); ?>%</h3>
                            <p class="text-muted mb-0">Average Score</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon warning mx-auto">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['total_attempts'] > 0 ? number_format(($stats['passed_attempts'] / $stats['total_attempts']) * 100, 1) : 0; ?>%</h3>
                            <p class="text-muted mb-0">Pass Rate</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Question Analysis -->
            <div class="row">
                <div class="col-12">
                    <div class="card stats-card">
                        <div class="card-header bg-white border-0 pb-0">
                            <h4 class="mb-0">
                                <i class="bi bi-list-check me-2"></i>
                                Question Analysis
                            </h4>
                            <p class="text-muted mb-0">Detailed breakdown of each question's performance</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($question_analysis)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-question-circle fs-1 text-muted"></i>
                                    <p class="text-muted mt-2">No question data available for analysis.</p>
                                </div>
                            <?php else: ?>
                                <?php $question_num = 1; ?>
                                <?php foreach ($question_analysis as $q_id => $analysis): ?>
                                    <div class="question-card <?php echo $analysis['difficulty_level']; ?>">
                                        <div class="card-body">
                                            <div class="row align-items-start">
                                                <div class="col-md-1 text-center">
                                                    <div class="question-number"><?php echo $question_num++; ?></div>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="question-text">
                                                        <?php echo htmlspecialchars($analysis['question_text']); ?>
                                                    </div>
                                                    
                                                    <!-- Question Options for Multiple Choice -->
                                                    <?php if ($analysis['question_type'] === 'multiple_choice' && !empty($analysis['options'])): ?>
                                                        <div class="options-container">
                                                            <h6 class="mb-3">
                                                                <i class="bi bi-list-ul me-2"></i>
                                                                Answer Options
                                                            </h6>
                                                            <div class="row">
                                                                <?php foreach ($analysis['options'] as $index => $option): ?>
                                                                    <div class="col-md-6 mb-2">
                                                                        <div class="option-item <?php echo $option['is_correct'] ? 'correct-option' : ''; ?>">
                                                                            <span class="option-number"><?php echo $index + 1; ?></span>
                                                                            <?php echo htmlspecialchars($option['text']); ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="d-flex align-items-center gap-3 mb-3">
                                                        <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $analysis['question_type'])); ?></span>
                                                        <span class="badge bg-info"><?php echo $analysis['points']; ?> points</span>
                                                        <span class="difficulty-badge difficulty-<?php echo $analysis['difficulty_level']; ?>">
                                                            <?php echo ucfirst($analysis['difficulty_level']); ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Accuracy Bar -->
                                                    <div class="mb-3">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <small class="text-muted">Accuracy Rate</small>
                                                            <small class="fw-bold"><?php echo number_format($analysis['accuracy_rate'], 1); ?>%</small>
                                                        </div>
                                                        <div class="accuracy-bar">
                                                            <div class="accuracy-fill accuracy-<?php echo $analysis['accuracy_rate'] >= 70 ? 'high' : ($analysis['accuracy_rate'] >= 40 ? 'medium' : 'low'); ?>" 
                                                                 style="width: <?php echo $analysis['accuracy_rate']; ?>%"></div>
                                                        </div>
                                                    </div>

                                                    <!-- Enhanced Statistics Grid -->
                                                    <div class="question-stats-grid">
                                                        <div class="stat-item success clickable" data-bs-toggle="modal" data-bs-target="#questionAttemptsModal" data-question-id="<?php echo $question['id']; ?>">
                                                            <div class="stat-value"><?php echo $analysis['total_attempts']; ?></div>
                                                            <div class="stat-label">Total Attempts</div>
                                                        </div>
                                                        <div class="stat-item success">
                                                            <div class="stat-value"><?php echo $analysis['correct_attempts']; ?></div>
                                                            <div class="stat-label">Correct</div>
                                                        </div>
                                                        <div class="stat-item danger">
                                                            <div class="stat-value"><?php echo $analysis['total_attempts'] - $analysis['correct_attempts']; ?></div>
                                                            <div class="stat-label">Incorrect</div>
                                                        </div>
                                                        <div class="stat-item info">
                                                            <div class="stat-value"><?php echo number_format($analysis['accuracy_rate'], 1); ?>%</div>
                                                            <div class="stat-label">Accuracy</div>
                                                        </div>
                                                        <div class="stat-item warning">
                                                            <div class="stat-value"><?php echo $analysis['points']; ?></div>
                                                            <div class="stat-label">Points</div>
                                                        </div>
                                                        <div class="stat-item <?php echo $analysis['difficulty_level'] === 'easy' ? 'success' : ($analysis['difficulty_level'] === 'medium' ? 'warning' : 'danger'); ?>">
                                                            <div class="stat-value"><?php echo ucfirst($analysis['difficulty_level']); ?></div>
                                                            <div class="stat-label">Difficulty</div>
                                                        </div>
                                                    </div>

                                                    <!-- Performance Indicator -->
                                                    <div class="performance-indicator">
                                                        <div class="performance-icon <?php echo $analysis['accuracy_rate'] >= 80 ? 'excellent' : ($analysis['accuracy_rate'] >= 60 ? 'good' : ($analysis['accuracy_rate'] >= 40 ? 'average' : 'poor')); ?>">
                                                            <?php echo $analysis['accuracy_rate'] >= 80 ? '★' : ($analysis['accuracy_rate'] >= 60 ? '●' : ($analysis['accuracy_rate'] >= 40 ? '▲' : '▼')); ?>
                                                        </div>
                                                        <span class="fw-bold">
                                                            <?php 
                                                            if ($analysis['accuracy_rate'] >= 80) echo 'Excellent Performance';
                                                            elseif ($analysis['accuracy_rate'] >= 60) echo 'Good Performance';
                                                            elseif ($analysis['accuracy_rate'] >= 40) echo 'Average Performance';
                                                            else echo 'Needs Improvement';
                                                            ?>
                                                        </span>
                                                    </div>

                                                    <!-- Common Wrong Answers -->
                                                    <?php if (!empty($analysis['common_wrong_answers'])): ?>
                                                        <div class="wrong-answers-container">
                                                            <h6 class="mb-3">
                                                                <i class="bi bi-exclamation-triangle me-2"></i>
                                                                Common Wrong Answers
                                                            </h6>
                                                            <div class="d-flex flex-wrap">
                                                                <?php foreach ($analysis['common_wrong_answers'] as $answer => $count): ?>
                                                                    <div class="wrong-answer-item">
                                                                        "<?php echo htmlspecialchars($answer); ?>" (<?php echo $count; ?>x)
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="card h-100" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                                                        <div class="card-body text-center">
                                                            <div class="mb-3">
                                                                <div class="fs-2 fw-bold text-<?php echo $analysis['accuracy_rate'] >= 70 ? 'success' : ($analysis['accuracy_rate'] >= 40 ? 'warning' : 'danger'); ?>">
                                                                    <?php echo number_format($analysis['accuracy_rate'], 1); ?>%
                                                                </div>
                                                                <small class="text-muted">Overall Accuracy</small>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <div class="fs-5 fw-bold text-primary">
                                                                    <?php echo $analysis['total_attempts']; ?>
                                                                </div>
                                                                <small class="text-muted">Total Responses</small>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <div class="fs-5 fw-bold text-success">
                                                                    <?php echo $analysis['correct_attempts']; ?>
                                                                </div>
                                                                <small class="text-muted">Correct Answers</small>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <div class="fs-5 fw-bold text-danger">
                                                                    <?php echo $analysis['total_attempts'] - $analysis['correct_attempts']; ?>
                                                                </div>
                                                                <small class="text-muted">Incorrect Answers</small>
                                                            </div>
                                                            
                                                            <div class="mt-3">
                                                                <span class="badge bg-<?php echo $analysis['difficulty_level'] === 'easy' ? 'success' : ($analysis['difficulty_level'] === 'medium' ? 'warning' : 'danger'); ?> fs-6">
                                                                    <?php echo ucfirst($analysis['difficulty_level']); ?> Difficulty
                                                                </span>
                                                            </div>
                                                        </div>
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
        <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Attempts Modal -->
    <div class="modal fade" id="attemptsModal" tabindex="-1" aria-labelledby="attemptsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attemptsModalLabel">
                        <i class="bi bi-graph-up me-2"></i>
                        Student Attempts - <?php echo htmlspecialchars($assessment['assessment_title']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                    // Get detailed attempt information
                    $attempts_query = "SELECT 
                                        aa.id,
                                        aa.student_id,
                                        aa.score,
                                        aa.has_passed,
                                        aa.time_taken,
                                        aa.completed_at as attempted_at,
                                        aa.status,
                                        u.first_name,
                                        u.last_name,
                                        u.username
                                     FROM assessment_attempts aa 
                                     LEFT JOIN users u ON aa.student_id = u.id 
                                     WHERE aa.assessment_id = ? AND aa.status = 'completed'
                                     ORDER BY aa.completed_at DESC";
                    $attempts_stmt = $pdo->prepare($attempts_query);
                    $attempts_stmt->execute([$assessment_id]);
                    $detailed_attempts = $attempts_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (empty($detailed_attempts)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No attempts found for this assessment.</p>
                        </div>
                    <?php else: ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-people-fill text-primary me-2"></i>
                                    <span class="fw-bold"><?php echo count($detailed_attempts); ?> Total Attempts</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <span class="fw-bold"><?php echo count(array_filter($detailed_attempts, function($attempt) { return $attempt['has_passed'] == 1; })); ?> Passed</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="attempts-list">
                            <?php foreach ($detailed_attempts as $attempt): ?>
                                <div class="student-attempt-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <div class="student-name">
                                                <?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?>
                                            </div>
                                            <div class="student-username">
                                                @<?php echo htmlspecialchars($attempt['username']); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="attempt-score <?php echo $attempt['has_passed'] ? 'passed' : 'failed'; ?>">
                                                <?php echo number_format($attempt['score'], 1); ?>%
                                            </div>
                                            <div class="attempt-date">
                                                <?php echo date('M j, Y g:i A', strtotime($attempt['attempted_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <span class="badge bg-<?php echo $attempt['has_passed'] ? 'success' : 'danger'; ?>">
                                                <?php echo $attempt['has_passed'] ? 'Passed' : 'Failed'; ?>
                                            </span>
                                            <?php if ($attempt['time_taken']): ?>
                                                <div class="attempt-date mt-1">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?php echo gmdate("H:i:s", $attempt['time_taken']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Question Attempts Modal -->
    <div class="modal fade" id="questionAttemptsModal" tabindex="-1" aria-labelledby="questionAttemptsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="questionAttemptsModalLabel">
                        <i class="bi bi-question-circle me-2"></i>
                        Question Attempts Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="questionAttemptsContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading question attempts...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load question attempts when modal opens
        document.addEventListener('DOMContentLoaded', function() {
            const questionAttemptsModal = document.getElementById('questionAttemptsModal');
            if (questionAttemptsModal) {
                questionAttemptsModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const questionId = button.getAttribute('data-question-id');
                    loadQuestionAttempts(questionId);
                });
            }
        });

        // Helper function to get time ago
        function getTimeAgo(date) {
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
            if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
            return `${Math.floor(diffInSeconds / 2592000)}mo ago`;
        }

        function loadQuestionAttempts(questionId) {
            const modalContent = document.getElementById('questionAttemptsContent');
            
            // Show loading state
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading question attempts...</p>
                </div>
            `;

            // Fetch question attempts data
            fetch(`get_question_attempts.php?question_id=${questionId}&assessment_id=<?php echo $assessment_id; ?>`)
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`Server error (${response.status}): ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Question attempts data:', data);
                    
                    if (!data.attempts || data.attempts.length === 0) {
                        modalContent.innerHTML = `
                            <p class="text-center text-muted">No attempts found for this question.</p>
                            <div class="alert alert-info">
                                <strong>Debug Info:</strong><br>
                                Total attempts: ${data.debug?.total_attempts || 0}<br>
                                Question attempts found: ${data.debug?.question_attempts_found || 0}<br>
                                Question ID: ${data.question_id}
                            </div>
                        `;
                    } else {
                        // Calculate statistics
                        const totalAttempts = data.attempts.length;
                        const correctAttempts = data.attempts.filter(attempt => attempt.student_answer === attempt.correct_answer).length;
                        const incorrectAttempts = totalAttempts - correctAttempts;
                        const accuracyRate = totalAttempts > 0 ? ((correctAttempts / totalAttempts) * 100).toFixed(1) : 0;
                        
                        // Group attempts by student for summary
                        const attemptsByStudent = {};
                        data.attempts.forEach(attempt => {
                            if (!attemptsByStudent[attempt.student_id]) {
                                attemptsByStudent[attempt.student_id] = [];
                            }
                            attemptsByStudent[attempt.student_id].push(attempt);
                        });
                        
                        const uniqueStudents = Object.keys(attemptsByStudent).length;
                        const avgAttemptsPerStudent = uniqueStudents > 0 ? (totalAttempts / uniqueStudents).toFixed(1) : 0;
                        
                        let html = `
                            <!-- Question Header -->
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h5 class="mb-1">
                                                <i class="bi bi-question-circle me-2"></i>
                                                Question Analysis
                                            </h5>
                                            <p class="mb-0 opacity-75">Detailed breakdown of student responses</p>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="badge bg-light text-dark fs-6">ID: ${data.question_id}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="text-muted mb-2">Question:</h6>
                                            <p class="mb-3 fw-normal fs-5">${data.question_text || 'Question text not available'}</p>
                                            
                                            <!-- Question Options -->
                                            ${data.options && data.options.length > 0 ? `
                                                <h6 class="text-muted mb-2">Answer Options:</h6>
                                                <div class="row">
                                                    ${data.options.map((option, index) => `
                                                        <div class="col-md-6 mb-2">
                                                            <div class="option-card ${option.is_correct ? 'correct-option' : ''}">
                                                                <div class="d-flex align-items-center">
                                                                    <span class="option-letter me-3">${String.fromCharCode(65 + index)}</span>
                                                                    <span class="option-text">${option.text}</span>
                                                                    ${option.is_correct ? '<i class="bi bi-check-circle-fill text-success ms-auto"></i>' : ''}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    `).join('')}
                                                </div>
                                            ` : ''}
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-end">
                                                <span class="badge bg-secondary me-1 mb-2">${data.question_type || 'Unknown Type'}</span>
                                                <span class="badge bg-warning mb-2">${data.points || 1} Points</span>
                                                <div class="mt-3">
                                                    <h6 class="text-success mb-1">Correct Answer:</h6>
                                                    <span class="badge bg-success fs-6 p-2">${data.correct_answer || 'Not specified'}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics Overview -->
                            <div class="row mb-4">
                                <div class="col-md-2">
                                    <div class="card text-center border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="text-primary mb-2">
                                                <i class="bi bi-people-fill fs-1"></i>
                                            </div>
                                            <h4 class="mb-1">${uniqueStudents}</h4>
                                            <p class="text-muted mb-0">Students</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card text-center border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="text-info mb-2">
                                                <i class="bi bi-arrow-repeat fs-1"></i>
                                            </div>
                                            <h4 class="mb-1">${totalAttempts}</h4>
                                            <p class="text-muted mb-0">Total Attempts</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card text-center border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="text-warning mb-2">
                                                <i class="bi bi-graph-up fs-1"></i>
                                            </div>
                                            <h4 class="mb-1">${avgAttemptsPerStudent}</h4>
                                            <p class="text-muted mb-0">Avg per Student</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card text-center border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="text-success mb-2">
                                                <i class="bi bi-check-circle-fill fs-1"></i>
                                            </div>
                                            <h4 class="mb-1">${correctAttempts}</h4>
                                            <p class="text-muted mb-0">Correct</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card text-center border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="text-danger mb-2">
                                                <i class="bi bi-x-circle-fill fs-1"></i>
                                            </div>
                                            <h4 class="mb-1">${incorrectAttempts}</h4>
                                            <p class="text-muted mb-0">Incorrect</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card text-center border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="text-info mb-2">
                                                <i class="bi bi-percent fs-1"></i>
                                            </div>
                                            <h4 class="mb-1">${accuracyRate}%</h4>
                                            <p class="text-muted mb-0">Accuracy</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Answer Distribution -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white">
                                            <h6 class="mb-0">
                                                <i class="bi bi-list-ul me-2"></i>
                                                Answer Distribution
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="progress mb-2" style="height: 25px;">
                                                <div class="progress-bar bg-success" style="width: ${accuracyRate}%">
                                                    ${accuracyRate}% Correct
                                                </div>
                                            </div>
                                            <div class="row text-center">
                                                <div class="col-md-4">
                                                    <h5 class="text-success mb-0">${correctAttempts}</h5>
                                                    <small class="text-muted">Correct Answers</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <h5 class="text-danger mb-0">${incorrectAttempts}</h5>
                                                    <small class="text-muted">Incorrect Answers</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <h5 class="text-info mb-0">${accuracyRate}%</h5>
                                                    <small class="text-muted">Accuracy Rate</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Student Attempts Table -->
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="mb-0">
                                                <i class="bi bi-list-check me-2"></i>
                                                Student Attempts (${totalAttempts} total)
                                            </h6>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                <input type="text" class="form-control" id="studentSearch" placeholder="Search students...">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="border-0">Student & Attempt</th>
                                                    <th class="border-0">Answer Given</th>
                                                    <th class="border-0">Result</th>
                                                    <th class="border-0">Attempt Details</th>
                                                    <th class="border-0">Score</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                        `;
                        
                        // Display attempts grouped by student
                        Object.keys(attemptsByStudent).forEach(studentId => {
                            const studentAttempts = attemptsByStudent[studentId];
                            const student = studentAttempts[0]; // Get student info from first attempt
                            
                            studentAttempts.forEach((attempt, attemptIndex) => {
                                const isCorrect = attempt.student_answer === attempt.correct_answer;
                                const attemptDate = new Date(attempt.attempted_at);
                                const timeAgo = getTimeAgo(attemptDate);
                                const attemptNumber = studentAttempts.length - attemptIndex; // Most recent first
                                
                                html += `
                                    <tr class="${isCorrect ? 'table-success' : 'table-danger'}">
                                        <td class="border-0">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-3">
                                                    <span class="avatar-text">${student.first_name.charAt(0)}${student.last_name.charAt(0)}</span>
                                                </div>
                                                <div>
                                                    <div class="fw-bold">${student.last_name}, ${student.first_name}</div>
                                                    <small class="text-muted">@${student.username}</small>
                                                    <div class="mt-1">
                                                        <span class="badge bg-info">Attempt #${attemptNumber}</span>
                                                        ${attempt.has_passed ? '<span class="badge bg-success ms-1">Passed</span>' : '<span class="badge bg-danger ms-1">Failed</span>'}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="border-0">
                                            <span class="badge bg-${isCorrect ? 'success' : 'danger'} fs-6 p-2">
                                                ${attempt.student_answer || 'No answer'}
                                            </span>
                                        </td>
                                        <td class="border-0">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-${isCorrect ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'} me-2"></i>
                                                <span class="fw-bold ${isCorrect ? 'text-success' : 'text-danger'}">
                                                    ${isCorrect ? 'Correct' : 'Incorrect'}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="border-0">
                                            <div>
                                                <div class="fw-bold">${attemptDate.toLocaleDateString()}</div>
                                                <small class="text-muted">${attemptDate.toLocaleTimeString()}</small>
                                                <br><small class="text-info">${timeAgo}</small>
                                                ${attempt.time_taken ? `<br><small class="text-warning"><i class="bi bi-clock"></i> ${Math.floor(attempt.time_taken / 60)}m ${attempt.time_taken % 60}s</small>` : ''}
                                            </div>
                                        </td>
                                        <td class="border-0">
                                            <div class="text-center">
                                                <div class="fw-bold text-${isCorrect ? 'success' : 'danger'}">${isCorrect ? 'Correct' : 'Incorrect'}</div>
                                                <small class="text-muted">Score: ${attempt.attempt_score}/${attempt.max_score}</small>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            });
                        });
                        
                        html += `
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        modalContent.innerHTML = html;
                        
                        // Add search functionality
                        const searchInput = document.getElementById('studentSearch');
                        if (searchInput) {
                            searchInput.addEventListener('input', function() {
                                const searchTerm = this.value.toLowerCase();
                                const tableRows = document.querySelectorAll('#questionAttemptsContent tbody tr');
                                
                                tableRows.forEach(row => {
                                    const studentName = row.querySelector('td:first-child').textContent.toLowerCase();
                                    const username = row.querySelector('td:first-child small').textContent.toLowerCase();
                                    
                                    if (studentName.includes(searchTerm) || username.includes(searchTerm)) {
                                        row.style.display = '';
                                    } else {
                                        row.style.display = 'none';
                                    }
                                });
                            });
                        }
                    }
                })
        .catch(error => {
            console.error('Error fetching question attempts:', error);
            
            // Try to parse the error message for more details
            let errorMessage = error.message;
            if (error.message.includes('Question not found')) {
                errorMessage = error.message.replace('Server error (500): ', '');
            }
            
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <h6>Error loading question attempts</h6>
                    <p><strong>Error:</strong> ${errorMessage}</p>
                    <p>This usually means the question ID format doesn't match between the assessment questions and the student answers.</p>
                    <p>Please check the browser console for more details.</p>
                </div>
            `;
        });
        }

        // Animate accuracy bars on page load
        document.addEventListener('DOMContentLoaded', function() {
            const accuracyBars = document.querySelectorAll('.accuracy-fill');
            accuracyBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html>
