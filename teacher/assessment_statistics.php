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
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --main-green: #2c5530;
            --light-green: #4a7c59;
            --accent-green: #6b8e6b;
            --bg-light: #f8f9fa;
            --text-dark: #2c3e50;
            --border-color: #dee2e6;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-header {
            background: linear-gradient(135deg, var(--main-green) 0%, var(--light-green) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
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

        .stats-icon.success { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }
        .stats-icon.warning { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); }
        .stats-icon.danger { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); }
        .stats-icon.info { background: rgba(23, 162, 184, 0.1); color: var(--info-color); }

        .question-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .question-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

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

        .accuracy-high { background: linear-gradient(90deg, var(--success-color), #20c997); }
        .accuracy-medium { background: linear-gradient(90deg, var(--warning-color), #fd7e14); }
        .accuracy-low { background: linear-gradient(90deg, var(--danger-color), #e83e8c); }

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
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: var(--light-green);
            color: white;
            transform: translateX(-3px);
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

    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-3">
                        <a href="assessments.php" class="back-btn me-3">
                            <i class="bi bi-arrow-left"></i>
                            Back to Assessments
                        </a>
                    </div>
                    <h1 class="mb-2">
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
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon info mx-auto">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['total_attempts']; ?></h3>
                            <p class="text-muted mb-0">Total Attempts</p>
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
                                    <div class="question-card">
                                        <div class="card-body">
                                            <div class="row align-items-start">
                                                <div class="col-md-1 text-center">
                                                    <div class="question-number"><?php echo $question_num++; ?></div>
                                                </div>
                                                <div class="col-md-8">
                                                    <h6 class="mb-2"><?php echo htmlspecialchars($analysis['question_text']); ?></h6>
                                                    
                                                    <!-- Question Options for Multiple Choice -->
                                                    <?php if ($analysis['question_type'] === 'multiple_choice' && !empty($analysis['options'])): ?>
                                                        <div class="question-options mb-3">
                                                            <small class="text-muted d-block mb-2">Options:</small>
                                                            <div class="row">
                                                                <?php foreach ($analysis['options'] as $index => $option): ?>
                                                                    <div class="col-md-6 mb-1">
                                                                        <div class="option-item <?php echo $option['is_correct'] ? 'correct-option' : ''; ?>">
                                                                            <span class="option-number"><?php echo $index + 1; ?>.</span>
                                                                            <?php echo htmlspecialchars($option['text']); ?>
                                                                            <?php if ($option['is_correct']): ?>
                                                                                <i class="bi bi-check-circle-fill text-success ms-2"></i>
                                                                            <?php endif; ?>
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

                                                    <!-- Statistics -->
                                                    <div class="row text-center">
                                                        <div class="col-4">
                                                            <div class="fw-bold text-primary"><?php echo $analysis['total_attempts']; ?></div>
                                                            <small class="text-muted">Attempts</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="fw-bold text-success"><?php echo $analysis['correct_attempts']; ?></div>
                                                            <small class="text-muted">Correct</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="fw-bold text-danger"><?php echo $analysis['total_attempts'] - $analysis['correct_attempts']; ?></div>
                                                            <small class="text-muted">Incorrect</small>
                                                        </div>
                                                    </div>

                                                    <!-- Common Wrong Answers -->
                                                    <?php if (!empty($analysis['common_wrong_answers'])): ?>
                                                        <div class="mt-3">
                                                            <small class="text-muted d-block mb-2">Common Wrong Answers:</small>
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
                                                <div class="col-md-3 text-end">
                                                    <div class="text-center">
                                                        <div class="fs-4 fw-bold text-<?php echo $analysis['accuracy_rate'] >= 70 ? 'success' : ($analysis['accuracy_rate'] >= 40 ? 'warning' : 'danger'); ?>">
                                                            <?php echo number_format($analysis['accuracy_rate'], 1); ?>%
                                                        </div>
                                                        <small class="text-muted">Accuracy</small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
