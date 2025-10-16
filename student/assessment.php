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
    
    return trim($text);
}

/**
 * Check if two normalized texts are a fuzzy match (allowing for minor variations)
 */
function fuzzyMatch($text1, $text2) {
    if (empty($text1) || empty($text2)) return false;
    
    // Exact match after normalization
    if ($text1 === $text2) return true;
    
    // Check if one contains the other (for partial matches)
    if (strpos($text1, $text2) !== false || strpos($text2, $text1) !== false) {
        return true;
    }
    
    // Calculate similarity using Levenshtein distance
    $distance = levenshtein($text1, $text2);
    $maxLength = max(strlen($text1), strlen($text2));
    
    // If strings are similar enough (80% similarity), consider it a match
    if ($maxLength > 0) {
        $similarity = 1 - ($distance / $maxLength);
        return $similarity >= 0.8;
    }
    
    return false;
}

/**
 * Convert numeric option index to letter (0=A, 1=B, 2=C, 3=D, etc.)
 */
function getOptionLetter($index) {
    return chr(65 + $index); // 65 is ASCII for 'A'
}

// Initialize database connection
$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$assessment_id = isset($_GET['id']) ? $_GET['id'] : '';

if (!$assessment_id) {
    header('Location: assessments.php');
    exit();
}

// Fetch assessment, module, and course details, including academic period status
$stmt = $pdo->prepare("
    SELECT a.*, c.id as course_id, c.academic_period_id, 
           ap.is_active as academic_period_active
    FROM assessments a 
    JOIN courses c ON a.course_id = c.id 
    JOIN academic_periods ap ON c.academic_period_id = ap.id
    WHERE a.id = ?
");
$stmt->execute([$assessment_id]);
$assessment_info = $stmt->fetch(PDO::FETCH_ASSOC);
$is_acad_year_active = $assessment_info ? (bool)$assessment_info['academic_period_active'] : true;
$is_semester_active = $is_acad_year_active; // Same as academic period in new structure

// Check if semester is inactive - if so, only allow viewing, not taking
$is_view_only = !$is_acad_year_active || !$is_semester_active;

// Get assessment details and check enrollment
$stmt = $pdo->prepare("
    SELECT a.*, c.course_name, u.first_name, u.last_name,
           a.is_locked, a.lock_type, a.prerequisite_assessment_id, 
           a.prerequisite_score, a.prerequisite_video_count, a.unlock_date, a.lock_message
    FROM assessments a
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    JOIN course_enrollments e ON c.id = e.course_id
    WHERE a.id = ? AND e.student_id = ? AND e.status IN ('active', 'completed')
");
$stmt->execute([$assessment_id, $user_id]);
$assessment = $stmt->fetch();

// Extract module title from JSON modules field
$module_title = 'Module Assessment';
if ($assessment && $assessment['course_id']) {
    $stmt = $pdo->prepare("SELECT modules FROM courses WHERE id = ?");
    $stmt->execute([$assessment['course_id']]);
    $course_data = $stmt->fetch();
    if ($course_data && $course_data['modules']) {
        $modules_data = json_decode($course_data['modules'], true);
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
}
$assessment['module_title'] = $module_title;

if (!$assessment) {
    $_SESSION['error'] = "Assessment not found or you are not enrolled in this course.";
    header('Location: assessments.php');
    exit();
}

// Check assessment accessibility based on locking conditions
$assessment['is_accessible'] = true;
$assessment['lock_reason'] = '';
$assessment['lock_details'] = '';

// Check if assessment is locked
if ($assessment['is_locked']) {
    $assessment['is_accessible'] = false;
    
    switch ($assessment['lock_type']) {
        case 'prerequisite_score':
            if ($assessment['prerequisite_assessment_id']) {
                // Check if student has taken the prerequisite assessment
                $prereq_stmt = $pdo->prepare("
                    SELECT aa.score, a.passing_rate 
                    FROM assessment_attempts aa 
                    JOIN assessments a ON aa.assessment_id = a.id 
                    WHERE aa.student_id = ? AND aa.assessment_id = ? AND aa.status = 'completed' 
                    ORDER BY aa.score DESC LIMIT 1
                ");
                $prereq_stmt->execute([$user_id, $assessment['prerequisite_assessment_id']]);
                $prereq_result = $prereq_stmt->fetch();
                
                if (!$prereq_result) {
                    $assessment['lock_reason'] = 'Prerequisite assessment not completed';
                    $assessment['lock_details'] = 'You must complete the prerequisite assessment first.';
                } else {
                    $score_percentage = ($prereq_result['score'] / $prereq_result['passing_rate']) * 100;
                    if ($score_percentage < $assessment['prerequisite_score']) {
                        $assessment['lock_reason'] = 'Prerequisite score not met';
                        $assessment['lock_details'] = "You need at least {$assessment['prerequisite_score']}% on the prerequisite assessment. Your best score: " . round($score_percentage, 1) . "%";
                    } else {
                        $assessment['is_accessible'] = true;
                    }
                }
            }
            break;
            
        case 'prerequisite_videos':
            if ($assessment['prerequisite_video_count']) {
                // Count videos watched by student in this course using the new structure
                $stmt = $pdo->prepare("
                    SELECT video_progress 
                    FROM course_enrollments 
                    WHERE student_id = ? AND course_id = ?
                ");
                $stmt->execute([$user_id, $assessment['course_id']]);
                $enrollment = $stmt->fetch();
                
                $watched_count = 0;
                if ($enrollment && $enrollment['video_progress']) {
                    $video_progress = json_decode($enrollment['video_progress'], true);
                    if ($video_progress) {
                        $watched_count = count(array_filter($video_progress, function($progress) {
                            return isset($progress['is_watched']) && $progress['is_watched'] == 1;
                        }));
                    }
                }
                
                if ($watched_count < $assessment['prerequisite_video_count']) {
                    $assessment['lock_reason'] = 'Video requirements not met';
                    $assessment['lock_details'] = "You need to watch {$assessment['prerequisite_video_count']} videos. You have watched {$watched_count} videos.";
                } else {
                    $assessment['is_accessible'] = true;
                }
            }
            break;
            
        case 'date_based':
            if ($assessment['unlock_date']) {
                $current_time = new DateTime();
                $unlock_time = new DateTime($assessment['unlock_date']);
                
                if ($current_time < $unlock_time) {
                    $assessment['lock_reason'] = 'Assessment not yet available';
                    $assessment['lock_details'] = 'This assessment will be available on ' . $unlock_time->format('M j, Y \a\t g:i A');
                } else {
                    $assessment['is_accessible'] = true;
                }
            }
            break;
            
        default: // manual lock
            $assessment['lock_reason'] = 'Assessment locked by teacher';
            $assessment['lock_details'] = $assessment['lock_message'] ?: 'This assessment is currently locked by your teacher.';
            break;
    }
}

// Check order-based unlocking logic
$assessment_order = $assessment['assessment_order'] ?? 1;
if ($assessment_order > 1) {
    // For assessments with order > 1, check if the previous order assessment is completed
    $previous_order = $assessment_order - 1;
    
    // Get all assessments for this course to find the previous one
    $stmt = $pdo->prepare("
        SELECT a.id, a.assessment_title, a.assessment_order, a.passing_rate,
               COALESCE(MAX(aa.score), 0) as best_score
        FROM assessments a
        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id = ?
        WHERE a.course_id = ?
        GROUP BY a.id
        ORDER BY a.assessment_order ASC
    ");
    $stmt->execute([$user_id, $assessment['course_id']]);
    $all_assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $previous_assessment_completed = false;
    foreach ($all_assessments as $prev_assessment) {
        if ($prev_assessment['assessment_order'] == $previous_order) {
            $prev_best_score = $prev_assessment['best_score'];
            $prev_passing_rate = $prev_assessment['passing_rate'];
            $previous_assessment_completed = ($prev_best_score >= $prev_passing_rate);
            break;
        }
    }
    
    if (!$previous_assessment_completed) {
        $assessment['is_accessible'] = false;
        $assessment['lock_reason'] = 'Previous assessment not completed';
        $assessment['lock_details'] = "You must complete Assessment $previous_order first before taking this assessment.";
    }
}

// Check academic period status
$academic_status = checkAssessmentAcademicStatus($pdo, $assessment_id);
if (!$academic_status['is_active']) {
    $assessment['is_accessible'] = false;
    $assessment['lock_reason'] = 'Academic period inactive';
    $assessment['lock_details'] = getInactiveStatusMessage($academic_status);
}

// Check if assessment is active
if ($assessment['status'] !== 'active') {
    $assessment['is_accessible'] = false;
    $assessment['lock_reason'] = 'Assessment deactivated';
    $assessment['lock_details'] = 'This assessment has been deactivated by your teacher.';
}

// Redirect if assessment is not accessible
if (!$assessment['is_accessible']) {
    $_SESSION['error'] = "Assessment Access Denied: {$assessment['lock_reason']}. {$assessment['lock_details']}";
    header('Location: assessments.php');
    exit();
}

// Check attempt limit
$stmt = $pdo->prepare("
    SELECT a.attempt_limit, COUNT(aa.id) as current_attempts
    FROM assessments a
    LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id = ? AND aa.status = 'completed'
    WHERE a.id = ?
    GROUP BY a.id, a.attempt_limit
");
$stmt->execute([$user_id, $assessment_id]);
$attempt_info = $stmt->fetch();

if ($attempt_info && $attempt_info['attempt_limit'] > 0 && $attempt_info['current_attempts'] >= $attempt_info['attempt_limit']) {
    $_SESSION['error'] = "You have reached the maximum number of attempts ({$attempt_info['attempt_limit']}) for this assessment.";
    header('Location: assessments.php');
    exit();
}

// Check if assessment was recently completed to prevent back navigation
if (isset($_SESSION['assessment_completed']) && $_SESSION['assessment_completed'] && 
    isset($_SESSION['completed_assessment_id']) && $_SESSION['completed_assessment_id'] == $assessment_id) {
    // Clear the session flags
    unset($_SESSION['assessment_completed']);
    unset($_SESSION['completed_assessment_id']);
    
    // Redirect to assessments page with a message
    $_SESSION['error'] = "Assessment has been completed. You cannot return to change your answers.";
    header('Location: assessments.php');
    exit();
}

// Handle assessment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_assessment']) || isset($_POST['auto_submit']))) {
    // SECURITY CHECK: Prevent submission for inactive semesters
    if ($is_view_only) {
        $_SESSION['error'] = "Assessment submission is not allowed for inactive academic periods. This assessment is view-only for review purposes.";
        header('Location: assessments.php');
        exit();
    }
    
    // Debug: Log submission attempt
    error_log("Assessment submission started for user: " . $user_id . ", assessment: " . $assessment_id);
    error_log("POST data: " . print_r($_POST, true));
    error_log("Session data: " . print_r($_SESSION, true));
    
    $answers = $_POST['answers'] ?? [];
    $time_taken = $_POST['time_taken'] ?? 0;
    $is_auto_submit = isset($_POST['auto_submit']) && $_POST['auto_submit'] == '1';
    
    // Debug: Log received answers
    error_log("Received answers: " . print_r($answers, true));
    
    // Get questions from the questions table - NO ORDERING at database level
    $stmt = $pdo->prepare("
        SELECT id, question_text, question_type, question_order, points, options
        FROM questions 
        WHERE assessment_id = ?
    ");
    $stmt->execute([$assessment_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Reorder questions to match the randomized order used during the attempt (if available)
    $submitOrderKey = "assessment_{$assessment_id}_student_{$user_id}";
    if (isset($_SESSION['random_question_order'][$submitOrderKey])) {
        $questionById = [];
        foreach ($questions as $questionRow) {
            $questionById[$questionRow['id']] = $questionRow;
        }
        $orderedQuestions = [];
        foreach ($_SESSION['random_question_order'][$submitOrderKey] as $qid) {
            if (isset($questionById[$qid])) {
                $orderedQuestions[] = $questionById[$qid];
            }
        }
        // Fallback: if mismatch, keep original
        if (!empty($orderedQuestions)) {
            $questions = $orderedQuestions;
        }
    }
    
    $correct_answers = 0;
    $total_questions = count($questions);
    $answers_data = [];
    
    
    foreach ($questions as $index => $question) {
        // Use index as question_id if id is not available
        $question_id = $question['id'] ?? 'q_' . $index;
        $student_answer = $answers[$question_id] ?? '';
        $is_correct = false;
        
        
        // Check if answer is correct based on question type
        if ($question['question_type'] === 'identification') {
            // For identification questions, we need to get the correct answer from the options
            $options = json_decode($question['options'], true);
            $correct_answers_list = [];
            
            if ($options && is_array($options)) {
                foreach ($options as $option) {
                    if (isset($option['is_correct']) && $option['is_correct']) {
                        $correct_answers_list[] = $option['text'] ?? '';
                    }
                }
            }
            
            // Normalize student answer
            $normalized_student_answer = normalizeText($student_answer);
            
            // Debug: Log identification validation
            error_log("Question {$question_id} - Identification validation:");
            error_log("  Student answer: '{$student_answer}'");
            error_log("  Normalized student answer: '{$normalized_student_answer}'");
            error_log("  Correct answers: " . print_r($correct_answers_list, true));
            
            // Check against all correct answers
            foreach ($correct_answers_list as $correct_answer) {
                $normalized_correct_answer = normalizeText($correct_answer);
                error_log("  Checking against: '{$correct_answer}' → '{$normalized_correct_answer}'");
                
                // Exact match
                if ($normalized_student_answer === $normalized_correct_answer) {
                    error_log("  ✓ Exact match found!");
                    $correct_answers++;
                    $is_correct = true;
                    break;
                }
                
                // Fuzzy match for minor variations
                if (fuzzyMatch($normalized_student_answer, $normalized_correct_answer)) {
                    error_log("  ✓ Fuzzy match found!");
                    $correct_answers++;
                    $is_correct = true;
                    break;
                }
            }
            
            if (!$is_correct) {
                error_log("  ✗ No match found");
            }
        } elseif ($question['question_type'] === 'true_false') {
            // For true/false questions, compare with the correct option text
            $options = json_decode($question['options'], true);
            $correct_answer = '';
            
            if ($options && is_array($options)) {
                foreach ($options as $option) {
                    if (isset($option['is_correct']) && $option['is_correct']) {
                        $correct_answer = $option['text'] ?? '';
                        break;
                    }
                }
            }
            
            // Normalize both answers for comparison
            $normalized_student_answer = normalizeText($student_answer);
            $normalized_correct_answer = normalizeText($correct_answer);
            
            // Debug: Log true/false validation
            error_log("Question {$question_id} - True/False validation:");
            error_log("  Student answer: '{$student_answer}' → '{$normalized_student_answer}'");
            error_log("  Correct answer: '{$correct_answer}' → '{$normalized_correct_answer}'");
            
            // Compare normalized answers
            if ($normalized_student_answer === $normalized_correct_answer) {
                error_log("  ✓ Match found!");
                $correct_answers++;
                $is_correct = true;
            } else {
                error_log("  ✗ No match found");
            }
        } else {
            // For multiple choice questions
            $options = json_decode($question['options'], true);
            $correct_option_orders = [];
            
            if ($options && is_array($options)) {
                foreach ($options as $option) {
                    if (isset($option['is_correct']) && $option['is_correct']) {
                        $correct_option_orders[] = (int)$option['order'];
                    }
                }
            }
            
            // Debug: Log the options and correct answers
            error_log("Question {$question_id} - Options: " . print_r($options, true));
            error_log("Question {$question_id} - Correct option orders: " . print_r($correct_option_orders, true));
            
            // Check if student answer matches any of the correct answers
            if (!empty($student_answer)) {
                // Handle both single and multiple answers
                $student_answers = strpos($student_answer, ',') !== false ? 
                    explode(',', $student_answer) : [$student_answer];
                
                // Convert to integers for comparison
                $student_answers = array_map('intval', $student_answers);
                
                // Debug: Log the comparison
                error_log("Question {$question_id} - Student answers (received): " . print_r($student_answers, true));
                error_log("Question {$question_id} - Correct answers: " . print_r($correct_option_orders, true));
                
                // Check if all student answers are correct and all correct answers are selected
                sort($student_answers);
                sort($correct_option_orders);
                
                error_log("Question {$question_id} - Sorted student answers: " . print_r($student_answers, true));
                error_log("Question {$question_id} - Sorted correct answers: " . print_r($correct_option_orders, true));
                error_log("Question {$question_id} - Arrays equal: " . ($student_answers === $correct_option_orders ? 'YES' : 'NO'));
                
                if ($student_answers === $correct_option_orders) {
                    $correct_answers++;
                    $is_correct = true;
                }
            }
        }
        
        // Store answer data for JSON storage
        $answers_data[] = [
            'question_id' => $question_id,
            'question_text' => $question['question_text'],
            'question_type' => $question['question_type'],
            'student_answer' => $student_answer,
            'is_correct' => $is_correct,
            'points' => $question['points']
        ];
    }
    
    $score = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100) : 0;
    
    // Debug: Log score calculation for troubleshooting 80% bug
    error_log("Assessment Submission Debug - User ID: " . $user_id . ", Assessment ID: " . $assessment_id . ", Total Questions: " . $total_questions . ", Correct Answers: " . $correct_answers . ", Calculated Score: " . $score . "%, Passing Rate: " . ($assessment['passing_rate'] ?? 70) . "%");
    
    // Ensure score is not artificially limited by passing rate
    // This fixes the bug where scores might be capped at the passing rate (e.g., 80%)
    if ($score > 100) {
        $score = 100; // Cap at 100% maximum only
        error_log("Score capped at 100% for assessment attempt");
    }
    
    // Create assessment attempt with answers stored as JSON
    $stmt = $pdo->prepare("
        INSERT INTO assessment_attempts 
        (student_id, assessment_id, status, started_at, completed_at, score, max_score, time_taken, answers, has_passed) 
        VALUES (?, ?, 'completed', NOW(), NOW(), ?, ?, ?, ?, ?)
    ");
    $has_passed = ($score >= ($assessment['passing_rate'] ?? 70));
    $stmt->execute([
        $user_id, 
        $assessment_id, 
        $score, 
        $total_questions, 
        $time_taken, 
        json_encode($answers_data),
        $has_passed ? 1 : 0
    ]);
    $attempt_id = $pdo->lastInsertId();
    
    // Update assessment pass status
    $passing_rate = $assessment['passing_rate'] ?? 70.0;
    $is_passed = updateAssessmentPassStatus($pdo, $user_id, $assessment_id, $score, $passing_rate);
    
    // Check if time expired or auto-submitted
    $time_expired = isset($_POST['time_expired']) && $_POST['time_expired'] == '1';
    
    if ($time_expired || $is_auto_submit) {
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
    
    // Set session flag to prevent back navigation
    $_SESSION['assessment_completed'] = true;
    $_SESSION['completed_assessment_id'] = $assessment_id;
    
    // Check and award badges
    require_once '../includes/badge_system.php';
    $badgeSystem = new BadgeSystem($pdo);
    $awarded_badges = $badgeSystem->checkAndAwardBadges($user_id);
    
    if (!empty($awarded_badges)) {
        $badge_names = array_column($awarded_badges, 'badge_name');
        $_SESSION['badges_earned'] = $badge_names;
    }
    
    // Send Pusher notifications
    require_once '../config/pusher.php';
    require_once '../includes/pusher_notifications.php';
    
    // Get assessment and course details for notifications
    $stmt = $pdo->prepare("
        SELECT a.assessment_title, c.course_name, c.teacher_id, u.first_name, u.last_name
        FROM assessments a
        JOIN courses c ON a.course_id = c.id
        JOIN users u ON c.teacher_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$assessment_id]);
    $assessmentDetails = $stmt->fetch();
    
    if ($assessmentDetails) {
        // Send notification to student
        PusherNotifications::sendAssessmentCompleted(
            $user_id,
            $assessmentDetails['assessment_title'],
            $score,
            $assessmentDetails['course_name']
        );
        
        // Send notification to teacher
        PusherNotifications::sendAssessmentResultToTeacher(
            $assessmentDetails['teacher_id'],
            $_SESSION['first_name'] . ' ' . $_SESSION['last_name'],
            $assessmentDetails['assessment_title'],
            $score,
            $assessmentDetails['course_name']
        );
        
        // Send badge notifications if any were earned
        foreach ($awarded_badges as $badge) {
            PusherNotifications::sendBadgeEarned(
                $user_id,
                $badge['badge_name'],
                $badge['description'] ?? null
            );
        }
        
        // Send leaderboard update
        PusherNotifications::sendLeaderboardUpdate();
        
        // Send teacher statistics update
        require_once '../includes/teacher_statistics_events.php';
        TeacherStatisticsEvents::sendAssessmentCompletion(
            $assessmentDetails['teacher_id'],
            $user_id,
            [
                'student_name' => $_SESSION['first_name'] . ' ' . $_SESSION['last_name'],
                'assessment_title' => $assessmentDetails['assessment_title'],
                'score' => $score,
                'course_name' => $assessmentDetails['course_name']
            ]
        );
        
        // Trigger statistics refresh for the teacher
        TeacherStatisticsEvents::triggerStatisticsRefresh($assessmentDetails['teacher_id']);
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

    // Clear in-progress flag
    if (isset($_SESSION['assessment_in_progress'])) {
        $inProgressKey = "assessment_{$assessment_id}_student_{$user_id}";
        unset($_SESSION['assessment_in_progress'][$inProgressKey]);
        if (empty($_SESSION['assessment_in_progress'])) {
            unset($_SESSION['assessment_in_progress']);
        }
    }
    
    // Set a flag to indicate assessment was just completed (for redirect detection)
    $_SESSION['assessment_just_completed'] = true;
    $_SESSION['completed_assessment_id'] = $assessment_id;

    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Ensure no output has been sent
    if (!headers_sent()) {
        header('Location: ' . $redirect_url);
        exit();
    } else {
        // Fallback if headers already sent
        echo "<script>window.location.href = '$redirect_url';</script>";
        echo "<p>If you are not redirected automatically, <a href='$redirect_url'>click here</a>.</p>";
        exit();
    }
}

// Get assessment details
$stmt = $pdo->prepare("
    SELECT assessment_title, course_id
    FROM assessments 
    WHERE id = ?
");
$stmt->execute([$assessment_id]);
$assessment_data = $stmt->fetch();

// If no assessment found, show error
if (!$assessment_data) {
    $_SESSION['error'] = "Assessment not found or you don't have permission to access it.";
    header('Location: assessments.php');
    exit();
}

// Get questions from the questions table - NO ORDERING at database level
$stmt = $pdo->prepare("
    SELECT id, question_text, question_type, question_order, points, options
    FROM questions 
    WHERE assessment_id = ?
");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log what we got from the database (can be removed later)
error_log("Assessment ID: " . $assessment_id);
error_log("Assessment: " . $assessment_data['assessment_title']);
error_log("Questions count: " . count($questions));

// IMMEDIATELY randomize questions after loading from database
// This ensures no teacher order is ever used
if (!empty($questions)) {
    $questionIds = array_column($questions, 'id');
    $seed = $user_id . $assessment_id . time() . rand(1000, 9999);
    mt_srand(crc32($seed));
    shuffle($questionIds);
    
    // Reorder questions according to the randomized order
    $questionById = [];
    foreach ($questions as $questionRow) {
        $questionById[$questionRow['id']] = $questionRow;
    }
    $shuffledQuestions = [];
    foreach ($questionIds as $qid) {
        if (isset($questionById[$qid])) {
            $shuffledQuestions[] = $questionById[$qid];
        }
    }
    $questions = $shuffledQuestions;
    
    error_log("Questions randomized immediately after database load: " . implode(',', $questionIds));
}

// Check if we have any questions
if (empty($questions)) {
    $_SESSION['error'] = "No questions found for this assessment. Please contact your instructor.";
    header('Location: assessments.php');
    exit();
}

// Get current question index from URL parameter
$current_question = isset($_GET['q']) ? (int)$_GET['q'] : 0;
$total_questions = count($questions);

// Ensure current question is within bounds
if ($current_question < 0) $current_question = 0;
if ($current_question >= $total_questions) $current_question = $total_questions - 1;

$current_question_data = $questions[$current_question] ?? null;
if ($current_question_data) {
    // Ensure question has an ID for form processing
    $current_question_data['id'] = $current_question_data['id'] ?? 'q_' . $current_question;
}

// Randomize question order per student and assessment, persist in session for stability across refreshes
$orderKey = "assessment_{$assessment_id}_student_{$user_id}";

// Track if an assessment page is currently in progress to avoid mid-attempt reshuffles
if (!isset($_SESSION['assessment_in_progress'])) {
    $_SESSION['assessment_in_progress'] = [];
}

// Check if this is a new attempt by looking for a 'new_attempt' parameter or if no in-progress session exists
$is_new_attempt = isset($_GET['new_attempt']) && $_GET['new_attempt'] === '1';
$is_retake = (isset($_GET['reset']) && $_GET['reset'] === '1') || (isset($_GET['new_attempt']) && $_GET['new_attempt'] === '1');
$currently_in_progress = $_SESSION['assessment_in_progress'][$orderKey] ?? false;

// Check if assessment was just completed (indicates a new attempt should start)
$assessment_just_completed = isset($_SESSION['assessment_just_completed']) && 
                           $_SESSION['assessment_just_completed'] && 
                           isset($_SESSION['completed_assessment_id']) && 
                           $_SESSION['completed_assessment_id'] == $assessment_id;

// If caller requested a reset (e.g., explicit retake), clear previous order
if ($is_retake) {
    // Check if student has already passed this assessment
    if (hasStudentPassedAssessment($pdo, $user_id, $assessment_id)) {
        $_SESSION['error'] = "You have already passed this assessment. Retakes are not allowed for passed assessments.";
        header('Location: assessments.php');
        exit();
    }
    
    // ALWAYS clear previous order for retakes to ensure fresh randomization
    if (isset($_SESSION['random_question_order'][$orderKey])) {
        unset($_SESSION['random_question_order'][$orderKey]);
        if (empty($_SESSION['random_question_order'])) {
            unset($_SESSION['random_question_order']);
        }
    }
    
    // Also clear in-progress flag for retakes
    if (isset($_SESSION['assessment_in_progress'][$orderKey])) {
        unset($_SESSION['assessment_in_progress'][$orderKey]);
        if (empty($_SESSION['assessment_in_progress'])) {
            unset($_SESSION['assessment_in_progress']);
        }
    }
}

// Initialize random question order session if not exists
if (!isset($_SESSION['random_question_order'])) {
    $_SESSION['random_question_order'] = [];
}

// ALWAYS randomize questions for students - no teacher order should be used
// The only exception is if student is currently in the middle of an attempt (to prevent mid-attempt reshuffling)
$should_randomize = true; // Always randomize by default

// Only skip randomization if student is currently in progress of the same attempt
if ($currently_in_progress && isset($_SESSION['random_question_order'][$orderKey])) {
    $should_randomize = false;
}

// But ALWAYS randomize for new attempts, retakes, or if no order exists
if ($is_new_attempt || $is_retake || $assessment_just_completed || 
    !isset($_SESSION['random_question_order'][$orderKey])) {
    $should_randomize = true;
}

if ($should_randomize) {
    // Use the already randomized questions from above
    $questionIds = array_column($questions, 'id');
    $_SESSION['random_question_order'][$orderKey] = $questionIds;
    
    // Log the randomization for debugging
    $randomization_reason = '';
    if ($is_new_attempt) $randomization_reason .= 'new_attempt ';
    if ($is_retake) $randomization_reason .= 'retake ';
    if ($assessment_just_completed) $randomization_reason .= 'just_completed ';
    if (!isset($_SESSION['random_question_order'][$orderKey])) $randomization_reason .= 'no_existing_order ';
    if (!$currently_in_progress) $randomization_reason .= 'not_in_progress ';
    
    error_log("RANDOMIZATION TRIGGERED for assessment {$assessment_id}, student {$user_id}. Reason: {$randomization_reason}. New order: " . implode(',', $questionIds));
} else {
    error_log("NO RANDOMIZATION for assessment {$assessment_id}, student {$user_id}. Current order: " . implode(',', $_SESSION['random_question_order'][$orderKey] ?? []));
}

// Clear completion flags after using them to prevent affecting subsequent loads
if ($assessment_just_completed) {
    unset($_SESSION['assessment_just_completed']);
    unset($_SESSION['completed_assessment_id']);
}

// Questions are already randomized above, no need to reorder

// Mark this assessment as in-progress for this user (prevents accidental reshuffle on refresh)
$_SESSION['assessment_in_progress'][$orderKey] = true;

// Get previous attempts
$stmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE student_id = ? AND assessment_id = ? ORDER BY started_at DESC");
$stmt->execute([$user_id, $assessment_id]);
$previous_attempts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($assessment['assessment_title']); ?> - Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Modern Gamified Assessment Design */
        :root {
            --primary-color: #2E5E4E;
            --primary-dark: #1e3d33;
            --secondary-color: #7DCB80;
            --success-color: #7DCB80;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-bg: #1f2937;
            --card-bg: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        body {
            background: linear-gradient(135deg, #2E5E4E 0%, #7DCB80 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            padding-top: 60px; /* Account for fixed navbar */
        }

        .assessment-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: var(--shadow-xl);
            margin: 20px;
            overflow: hidden;
        }

        .assessment-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
             color: white;
            padding: 24px 32px;
            position: relative;
            overflow: hidden;
        }

        .assessment-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .assessment-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .assessment-meta {
            display: flex;
            gap: 24px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .meta-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Progress Section */
        .progress-section {
            background: var(--card-bg);
            padding: 24px 32px;
            border-bottom: 1px solid var(--border-color);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .progress-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
        }

        .progress-stats {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .progress-bar-container {
            background: #f3f4f6;
             border-radius: 12px;
            height: 12px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar-fill {
            background: #7DCB80;
            height: 100%;
            border-radius: 12px;
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.2);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Question Card */
        .question-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 32px;
            margin: 24px 32px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .question-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .question-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .question-number-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: var(--shadow-md);
            position: relative;
        }

        .question-number-badge::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            z-index: -1;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
        }

        .question-info {
            flex: 1;
        }

        .question-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .question-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .question-text {
            font-size: 1.25rem;
            line-height: 1.6;
            color: var(--text-primary);
            margin-bottom: 32px;
            font-weight: 500;
        }

        /* Answer Options */
        .options-container {
            margin-bottom: 32px;
        }

        .option-item {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }

        .option-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transition: width 0.3s ease;
            z-index: 0;
        }

        .option-item:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .option-item:hover::before {
            width: 4px;
        }

        .option-item.selected {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .option-item.selected::before {
            width: 0;
        }

        .option-letter {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-secondary);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .option-item.selected .option-letter {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .option-text {
            flex: 1;
            font-size: 1.1rem;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        /* Checkbox Options */
        .checkbox-option {
            position: relative;
        }

        .checkbox-icon {
            width: 24px;
            height: 24px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            background: var(--card-bg);
            position: relative;
            z-index: 1;
        }

        .checkbox-option.selected .checkbox-icon {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }


        /* Text Input */
         .identification-input {
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 24px;
             font-size: 1.2rem;
             transition: all 0.3s ease;
            background: var(--card-bg);
            box-shadow: var(--shadow-sm);
            width: 100%;
            font-weight: 500;
         }
         
         .identification-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(46, 94, 78, 0.1);
             transform: translateY(-2px);
            outline: none;
         }

        /* Timer */
        .timer {
            position: fixed;
            top: 80px; /* Below the navbar */
            right: 24px;
            z-index: 1020; /* Below navbar but above content */
            background: #28a745;
            color: white;
            padding: 20px 24px;
            border-radius: 20px;
            font-weight: 700;
            box-shadow: var(--shadow-xl);
            min-width: 220px;
            text-align: center;
            font-size: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .timer.warning {
            background: #ffc107;
            animation: timerWarning 1s ease-in-out infinite;
        }
        
        .timer.danger {
            background: #dc3545;
            animation: timerDanger 0.5s ease-in-out infinite;
        }
        
        .timer-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
        }
        
        .timer-icon {
            font-size: 1.1rem;
            margin-right: 8px;
            animation: timerIconSpin 2s linear infinite;
        }
        
        .timer-label {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 600;
        }
        
        .timer-text {
            font-size: 1.75rem;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            display: block;
            margin: 8px 0;
        }
        
        .timer-total {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-bottom: 8px;
        }
        
        @keyframes timerPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes timerWarning {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        @keyframes timerDanger {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }
        
        @keyframes timerIconSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .timer-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 0 0 20px 20px;
            overflow: hidden;
        }
        
        .timer-progress-fill {
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            width: 100%;
            transition: width 1s linear;
            border-radius: 0 0 20px 20px;
        }

        /* Navigation */
        .navigation-section {
            background: var(--card-bg);
            padding: 24px 32px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-button {
            padding: 12px 24px;
             border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
             transition: all 0.3s ease;
            border: none;
            cursor: pointer;
             display: flex;
             align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .nav-button-primary {
            background: #2E5E4E;
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-button-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
             color: white;
        }

        .nav-button-secondary {
            background: var(--card-bg);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .nav-button-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
             transform: translateY(-2px);
        }

        .nav-button-danger {
            background: #ef4444;
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-button-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .nav-button-success {
            background: #7DCB80;
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-button-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        /* Achievement Badges */
        .achievement-badge {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #7DCB80;
            color: white;
            padding: 20px 32px;
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            z-index: 2000;
            display: none;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            animation: achievementPop 0.5s ease-out;
        }

        @keyframes achievementPop {
            0% { transform: translate(-50%, -50%) scale(0.5); opacity: 0; }
            50% { transform: translate(-50%, -50%) scale(1.1); opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .assessment-container {
                margin: 10px;
                border-radius: 16px;
            }

            .assessment-header {
                padding: 20px 24px;
            }

            .assessment-title {
                font-size: 1.5rem;
            }

            .assessment-meta {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .question-card {
                margin: 16px 20px;
                padding: 24px;
            }

            .navigation-section {
                padding: 20px 24px;
                flex-direction: column;
                gap: 16px;
            }

            .timer {
                top: 80px;
                right: 16px;
                min-width: 180px;
                padding: 16px 20px;
                font-size: 0.9rem;
            }

            .timer-text {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .question-card {
                margin: 12px 16px;
                padding: 20px;
            }

            .option-item {
                padding: 16px 20px;
            }

            .option-text {
                font-size: 1rem;
            }
        }

        /* Additional Gamification Styles */
        .option-item:active {
            transform: scale(0.98);
        }

        .question-number-badge {
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from { box-shadow: 0 0 10px rgba(46, 94, 78, 0.5); }
            to { box-shadow: 0 0 20px rgba(46, 94, 78, 0.8); }
        }

        .progress-bar-fill {
            position: relative;
            overflow: hidden;
        }

        .progress-bar-fill::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.3);
            animation: progressShine 2s infinite;
        }

        @keyframes progressShine {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Success animations */
        .option-item.selected {
            animation: successPulse 0.6s ease-out;
        }

        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Loading states */
        .nav-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .nav-button:disabled:hover {
            transform: none !important;
        }

        /* Save feedback animations */
        @keyframes slideInRight {
            from { 
                transform: translateX(100%); 
                opacity: 0; 
            }
            to { 
                transform: translateX(0); 
                opacity: 1; 
            }
        }

        @keyframes slideOutRight {
            from { 
                transform: translateX(0); 
                opacity: 1; 
            }
            to { 
                transform: translateX(100%); 
                opacity: 0; 
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <!-- Timer (if academic year is active) -->
            <?php if ($is_acad_year_active): ?>
                <div id="timer-container">
                    <div id="timer" class="timer">
                        <div class="timer-header">
                            <i class="fas fa-clock timer-icon"></i>
                            <span class="timer-label">Time Remaining</span>
                        </div>
                        <span id="time-display" class="timer-text">00:00</span>
                        <div class="timer-total">of <?php echo $assessment['time_limit']; ?> minutes</div>
                        <div class="timer-progress">
                            <div id="timer-progress-bar" class="timer-progress-fill"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

    <!-- Main Assessment Container -->
    <div class="assessment-container">
        <!-- Assessment Header -->
        <div class="assessment-header">
            <h1 class="assessment-title">
                <i class="fas fa-trophy me-2"></i>
                                    <?php echo htmlspecialchars($assessment['assessment_title']); ?>
            </h1>
            <div class="assessment-meta">
                <div class="meta-item">
                    <i class="fas fa-book meta-icon"></i>
                    <span><?php echo htmlspecialchars($assessment['course_name']); ?></span>
                            </div>
                <div class="meta-item">
                    <i class="fas fa-folder meta-icon"></i>
                    <span><?php echo htmlspecialchars($assessment['module_title']); ?></span>
                        </div>
                <div class="meta-item">
                    <i class="fas fa-clock meta-icon"></i>
                    <span><?php echo $assessment['time_limit']; ?> minutes</span>
                    </div>
                    </div>
                </div>

                <?php if ($is_view_only): ?>
            <div class="progress-section">
                <div class="alert alert-warning">
                    <strong><i class="fas fa-exclamation-triangle me-2"></i>Inactive Academic Period:</strong> 
                        <?php if (!$is_acad_year_active): ?>
                            This academic year is inactive. 
                        <?php endif; ?>
                        <?php if (!$is_semester_active): ?>
                            This semester is inactive. 
                        <?php endif; ?>
                        You cannot take or submit this assessment. Only review is allowed.
                    </div>
            </div>
                <?php else: ?>
                    <?php if (empty($questions)): ?>
                <div class="progress-section">
                        <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>No Questions Available</h5>
                            <p>This assessment doesn't have any questions yet. Please contact your teacher to add questions to this assessment.</p>
                        <a href="assessments.php" class="nav-button nav-button-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Assessments
                            </a>
                    </div>
                        </div>
                    <?php elseif ($current_question_data): ?>
                <!-- Progress Section -->
                <div class="progress-section">
                    <div class="progress-header">
                        <div class="progress-label">
                            <i class="fas fa-chart-line me-2"></i>Assessment Progress
                            </div>
                        <div class="progress-stats">
                            <div class="stat-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Question <?php echo $current_question + 1; ?> of <?php echo $total_questions; ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-percentage"></i>
                                <span><?php echo round((($current_question + 1) / $total_questions) * 100, 1); ?>% Complete</span>
                            </div>
                        </div>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo round((($current_question + 1) / $total_questions) * 100, 2); ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Question Form -->
                        <form method="POST" action="assessment.php?id=<?php echo $assessment_id; ?>&q=<?php echo $current_question; ?>" id="assessment-form">
                            <input type="hidden" name="time_taken" id="time-taken" value="0">
                            <input type="hidden" name="time_expired" id="time-expired" value="0">
                            <input type="hidden" name="auto_submit" id="auto-submit" value="0">
                            
                            <div class="question-card">
                        <!-- Question Header -->
                        <div class="question-header">
                            <div class="question-number-badge">
                                <?php echo $current_question + 1; ?>
                            </div>
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
                                <?php if (isset($_SESSION['random_question_order'][$orderKey])): ?>
                                <div class="debug-info" style="font-size: 0.8em; color: #666; margin-top: 5px;">
                                    <small>Debug: Question Order: <?php echo implode(', ', $_SESSION['random_question_order'][$orderKey]); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                                </div>
                                
                                <!-- Question Text -->
                                <div class="question-text">
                                    <?php echo htmlspecialchars($current_question_data['question_text']); ?>
                                </div>
                                <!-- Answer Input -->
                        <div class="options-container">
                                <?php if (strtolower(trim($current_question_data['question_type'])) === 'identification'): ?>
                                        <input type="text" 
                                       class="identification-input" 
                                               name="answers[<?php echo $current_question_data['id']; ?>]" 
                                               placeholder="Type your answer here..." 
                                               autocomplete="off"
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
                                                       style="display: none;">
                                        <div class="checkbox-icon"></div>
                                        <div class="option-letter"><?php echo getOptionLetter($key); ?></div>
                                        <div class="option-text"><?php echo htmlspecialchars($option); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Default to multiple choice with checkboxes -->
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
                                                       style="display: none;">
                                        <div class="checkbox-icon"></div>
                                        <div class="option-letter"><?php echo getOptionLetter($key); ?></div>
                                        <div class="option-text"><?php echo htmlspecialchars($option); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                <?php endif; ?>
                        </div>
                            </div>
                            
                    <!-- Navigation Section -->
                    <div class="navigation-section">
                                    <div>
                                        <?php if ($current_question > 0): ?>
                                            <a href="#" 
                                   class="nav-button nav-button-secondary"
                                   onclick="goToPrevious(); return false;">
                                                <i class="fas fa-arrow-left"></i> Previous
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                        <div style="display: flex; gap: 12px;">
                            <a href="assessments.php" class="nav-button nav-button-danger">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                        
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
                    <?php endif; ?>
                <?php endif; ?>
        </div>

    <!-- Achievement Badge -->
    <div id="achievement-badge" class="achievement-badge">
        <i class="fas fa-trophy"></i>
        <span id="achievement-text">Achievement Unlocked!</span>
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
            
            // Prevent immediate auto-submission if time limit is too short
            if (timeLimit < 60) { // Less than 1 minute
                console.log('Time limit too short, setting minimum time');
                timeLimit = 300; // 5 minutes minimum
                timeLeft = timeLimit;
            }
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const timeDisplay = document.getElementById('time-display');
            if (timeDisplay) {
                timeDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                console.log('Timer updated:', timeDisplay.textContent, 'timeLeft:', timeLeft);
            } else {
                console.log('Timer display element not found!');
            }
            
            
            // Update progress bar
            const progressPercentage = (timeLeft / timeLimit) * 100;
            document.getElementById('timer-progress-bar').style.width = progressPercentage + '%';
            
            // Add visual warnings based on time remaining
            const timerElement = document.getElementById('timer');
            const timeLimitMinutes = <?php echo max(($assessment['time_limit'] ?? 30), 5); ?>;
            const warningThreshold = Math.floor(timeLimitMinutes * 0.25); // 25% of time left
            const dangerThreshold = Math.floor(timeLimitMinutes * 0.1); // 10% of time left
            const criticalThreshold = Math.floor(timeLimitMinutes * 0.05); // 5% of time left
            
            // Remove all warning classes first
            timerElement.classList.remove('warning', 'danger');
            
            if (timeLeft <= criticalThreshold * 60) {
                // Less than 5% time left - critical mode
                timerElement.classList.add('danger');
                // Show critical warning message
                if (timeLeft === criticalThreshold * 60) {
                    showNotification('⚠️ CRITICAL: Less than 5% time remaining! Submit your assessment NOW!', 'danger');
                }
            } else if (timeLeft <= dangerThreshold * 60) {
                // Less than 10% time left - danger mode
                timerElement.classList.add('danger');
                // Show warning message
                if (timeLeft === dangerThreshold * 60) {
                    showNotification('⚠️ WARNING: Less than 10% time remaining! Assessment will auto-submit when time expires.', 'warning');
                }
            } else if (timeLeft <= warningThreshold * 60) {
                // Less than 25% time left - warning mode
                timerElement.classList.add('warning');
                // Show warning message
                if (timeLeft === warningThreshold * 60) {
                    showNotification('⏰ Reminder: Less than 25% time remaining! Please complete your assessment soon.', 'info');
                }
            }
            
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
                } else {
                    // Fallback if form not found
                    console.log('Form not found, redirecting to assessments');
                    window.location.href = 'assessments.php';
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

        // Achievement system
        function showAchievement(message) {
            const badge = document.getElementById('achievement-badge');
            const text = document.getElementById('achievement-text');
            
            text.textContent = message;
            badge.style.display = 'flex';
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                badge.style.display = 'none';
            }, 3000);
        }

        // Show save feedback
        function showSaveFeedback() {
            // Create a small save indicator
            const saveIndicator = document.createElement('div');
            saveIndicator.innerHTML = '💾 Saved';
            saveIndicator.style.cssText = `
                position: fixed;
                top: 100px;
                right: 24px;
                background: var(--success-color);
                color: white;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 0.9rem;
                font-weight: 600;
                z-index: 1001;
                box-shadow: var(--shadow-md);
                animation: slideInRight 0.3s ease-out;
            `;
            
            document.body.appendChild(saveIndicator);
            
            // Auto-remove after 2 seconds
            setTimeout(() => {
                saveIndicator.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => {
                    if (saveIndicator.parentNode) {
                        saveIndicator.remove();
                    }
                }, 300);
            }, 2000);
        }

        // Progress celebration
        function celebrateProgress() {
            const currentProgress = <?php echo $current_question + 1; ?>;
            const totalQuestions = <?php echo $total_questions; ?>;
            const progressPercentage = Math.round((currentProgress / totalQuestions) * 100);
            
            // Show achievement for milestones
            if (progressPercentage === 25) {
                showAchievement('🎯 Quarter Complete! Keep it up!');
            } else if (progressPercentage === 50) {
                showAchievement('🔥 Halfway There! You\'re on fire!');
            } else if (progressPercentage === 75) {
                showAchievement('⚡ Almost Done! Final stretch!');
            } else if (progressPercentage === 100) {
                showAchievement('🏆 Assessment Complete! Excellent work!');
            }
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

        // Start assessment immediately when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM loaded, starting assessment and loading answers...');
            
            // Clear localStorage if this is a retake
            <?php if ($is_retake): ?>
            console.log('Retake detected - clearing localStorage...');
            const questionIds = <?php echo json_encode(array_column($questions, 'id')); ?>;
            questionIds.forEach(questionId => {
                localStorage.removeItem('assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId);
            });
            // Also clear any timer state
            localStorage.removeItem('assessment_timer_' + '<?php echo (string)$assessment_id; ?>');
            <?php endif; ?>
            
            const timer = document.getElementById('timer');
            console.log('Timer element found:', timer);
            
            // Start assessment immediately (no pre-countdown)
            beginAssessment();
            
            // Prevent back navigation during assessment
            preventBackNavigation();
            
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
            
            // Collect all answers from localStorage
            const allAnswers = {};
            const questionIds = <?php echo json_encode(array_column($questions, 'id')); ?>;
            
            questionIds.forEach(questionId => {
                const savedAnswer = localStorage.getItem('assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId);
                // Always include the answer, even if it's empty (to track unanswered questions)
                let finalAnswer = savedAnswer || '';
                
                // Convert 0-based answers to 1-based for multiple choice questions
                if (finalAnswer && finalAnswer.includes(',')) {
                    // This is a multiple choice question with multiple answers
                    const answers = finalAnswer.split(',').map(val => parseInt(val) + 1).join(',');
                    finalAnswer = answers;
                    console.log(`Converted multiple choice answer for question ${questionId}: "${savedAnswer}" → "${finalAnswer}"`);
                } else if (finalAnswer && !isNaN(finalAnswer)) {
                    // This is a single answer (could be multiple choice or other)
                    finalAnswer = (parseInt(finalAnswer) + 1).toString();
                    console.log(`Converted single answer for question ${questionId}: "${savedAnswer}" → "${finalAnswer}"`);
                }
                
                allAnswers[questionId] = finalAnswer;
                console.log(`Retrieved from localStorage for question ${questionId}: "${savedAnswer}" → Final: "${finalAnswer}"`);
            });
            
            // Add hidden inputs for all saved answers
            Object.keys(allAnswers).forEach(questionId => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = `answers[${questionId}]`;
                hiddenInput.value = allAnswers[questionId];
                this.appendChild(hiddenInput);
                console.log(`Adding answer for question ${questionId}: "${allAnswers[questionId]}"`);
            });
            
            // Debug: Check what's actually in the form before submission
            console.log('Form data before submission:');
            const formData = new FormData(this);
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            // Debug: Check if there are any actual checkbox inputs that might conflict
            const actualCheckboxes = document.querySelectorAll('input[type="checkbox"]');
            console.log('Actual checkboxes found:', actualCheckboxes.length);
            actualCheckboxes.forEach((cb, index) => {
                console.log(`Actual checkbox ${index}: name="${cb.name}", value="${cb.value}", checked=${cb.checked}`);
            });
            
            
            console.log('Time taken:', timeTaken, 'seconds');
            const timeExpiredElement = document.getElementById('time-expired');
            const autoSubmitElement = document.getElementById('auto-submit');
            console.log('Time expired:', timeExpiredElement ? timeExpiredElement.value : 'N/A');
            console.log('Auto submit:', autoSubmitElement ? autoSubmitElement.value : 'N/A');
            
            // Clear the timer to prevent double submission
            if (timerInterval) {
                clearInterval(timerInterval);
                console.log('Timer cleared');
            }
            
            // Clear timer state since assessment is being submitted
            clearTimerState();
            
            // Remove the beforeunload warning
            window.removeEventListener('beforeunload', window.beforeUnloadHandler);
            
            // Clear browser history to prevent back navigation after submission
            if (window.history && window.history.pushState) {
                // Replace current history entry to prevent back navigation
                window.history.replaceState(null, null, window.location.href);
            }
            
            console.log('Form submission completed');
        });
        }

        // Warn user before leaving page (only when not navigating between questions)
        let isNavigatingBetweenQuestions = false;
        
        function beforeUnloadHandler(e) {
            // Save current answer before leaving page
            const questionId = '<?php echo $current_question_data['id'] ?? ''; ?>';
            if (questionId) {
                saveCurrentAnswer(questionId);
            }
            
            // Don't show warning if we're navigating between questions
            if (isNavigatingBetweenQuestions) {
                return;
            }
            
            e.preventDefault();
            e.returnValue = '';
        }
        window.beforeUnloadHandler = beforeUnloadHandler;
        window.addEventListener('beforeunload', beforeUnloadHandler);
        
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
                if (checkbox && checkbox.checked) {
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
            
            // Debug: Check all checkboxes for this question
            const allCheckboxes = document.querySelectorAll(`input[name="answers[${questionId}][]"]`);
            console.log('🔍 All checkboxes for question ' + questionId + ':', allCheckboxes.length);
            allCheckboxes.forEach((cb, index) => {
                console.log(`Checkbox ${index}: value="${cb.value}", checked=${cb.checked}`);
            });
        }

        // Validate and submit assessment
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
                
                // Check if answer is provided before allowing submission
                if (!answer || answer.trim() === '') {
                    showNotification('⚠️ Please select an answer before submitting the assessment.', 'warning');
                    return false;
                }
            }
            
            // Check if all questions have been answered
            const totalQuestions = <?php echo count($questions); ?>;
            const questionIds = <?php echo json_encode(array_column($questions, 'id')); ?>;
            let unansweredQuestions = [];
            
            questionIds.forEach(qId => {
                const savedAnswer = localStorage.getItem('assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + qId);
                if (!savedAnswer || savedAnswer.trim() === '') {
                    unansweredQuestions.push(qId);
                }
            });
            
            if (unansweredQuestions.length > 0) {
                showNotification(`⚠️ Please answer all questions before submitting. You have ${unansweredQuestions.length} unanswered question(s).`, 'warning');
                return false;
            }
            
            // If all validations pass, show confirmation dialog
            return confirm('Are you sure you want to submit this assessment?');
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
                
                // Save answer to localStorage (even if empty, to track that question was visited)
                localStorage.setItem('assessment_' + '<?php echo (string)$assessment_id; ?>' + '_q_' + questionId, answer);
                console.log('Saved answer for question ' + questionId + ': "' + answer + '"');
            }
            
            // Save timer state before navigating
            saveTimerState();
            
            // Set navigation flag to prevent beforeunload warning
            isNavigatingBetweenQuestions = true;
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
                }
            } else {
                console.log('❌ No question ID found');
            }
        }

        // Debug function to check localStorage
        function debugLocalStorage() {
            console.log('🔍 Debugging localStorage...');
            const questionIds = <?php echo json_encode(array_column($questions, 'id')); ?>;
            const assessmentId = '<?php echo (string)$assessment_id; ?>';
            
            questionIds.forEach(questionId => {
                const key = 'assessment_' + assessmentId + '_q_' + questionId;
                const value = localStorage.getItem(key);
                console.log(`Key: ${key} | Value: "${value}"`);
            });
        }
        
        // Make debug functions available globally
        window.debugLocalStorage = debugLocalStorage;
        window.loadCurrentQuestionAnswer = loadCurrentQuestionAnswer;
        
        // Manual trigger for testing
        window.testAnswerLoading = function() {
            console.log('🧪 Manual test of answer loading...');
            loadCurrentQuestionAnswer();
        };

        // Auto-save answers periodically
        setInterval(function() {
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
                console.log('Auto-saved answer for question ' + questionId + ': "' + answer + '"');
                
            }
        }, 5000); // Auto-save every 5 seconds
    </script>
</body>
</html> 