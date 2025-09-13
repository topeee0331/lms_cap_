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
    WHERE a.id = ? AND e.student_id = ? AND e.status = 'active'
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
    
    // Get questions from the questions table
    $stmt = $pdo->prepare("
        SELECT id, question_text, question_type, question_order, points, options
        FROM questions 
        WHERE assessment_id = ?
        ORDER BY question_order ASC
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
    $is_retake_submission = $is_retake;
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

// Get questions from the questions table
$stmt = $pdo->prepare("
    SELECT id, question_text, question_type, question_order, points, options
    FROM questions 
    WHERE assessment_id = ?
    ORDER BY question_order ASC
");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log what we got from the database (can be removed later)
error_log("Assessment ID: " . $assessment_id);
error_log("Assessment: " . $assessment_data['assessment_title']);
error_log("Questions count: " . count($questions));

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

// If caller requested a reset (e.g., explicit retake), clear previous order ONLY if not currently in-progress
$is_retake = isset($_GET['reset']) && $_GET['reset'] === '1';
if ($is_retake) {
    // Check if student has already passed this assessment
    if (hasStudentPassedAssessment($pdo, $user_id, $assessment_id)) {
        $_SESSION['error'] = "You have already passed this assessment. Retakes are not allowed for passed assessments.";
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
// Initialize random question order session if not exists
if (!isset($_SESSION['random_question_order'])) {
    $_SESSION['random_question_order'] = [];
}

// Create randomized question order if not exists
if (!isset($_SESSION['random_question_order'][$orderKey])) {
    $questionIds = array_column($questions, 'id');
    // Shuffle to create a per-student randomized order for this assessment
    shuffle($questionIds);
    $_SESSION['random_question_order'][$orderKey] = $questionIds;
}

// Reorder questions according to the randomized order stored in session
$questionById = [];
foreach ($questions as $questionRow) {
    $questionById[$questionRow['id']] = $questionRow;
}
$shuffledQuestions = [];
foreach ($_SESSION['random_question_order'][$orderKey] as $qid) {
    if (isset($questionById[$qid])) {
        $shuffledQuestions[] = $questionById[$qid];
    }
}
$questions = $shuffledQuestions;

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
         .question-card {
             margin-bottom: 2rem;
             border: 1px solid #dee2e6;
             border-radius: 16px;
             padding: 40px;
             min-height: 400px;
             background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
             box-shadow: 0 8px 32px rgba(0,0,0,0.1);
         }
        
         .question-number {
             background: linear-gradient(135deg, #007bff, #0056b3);
             color: white;
             border-radius: 12px;
             padding: 20px;
             margin-bottom: 25px;
             text-align: center;
             box-shadow: 0 4px 15px rgba(0,123,255,0.3);
         }
        
        .question-text {
            font-size: 1.2rem;
            line-height: 1.6;
            margin-bottom: 25px;
            color: #333;
        }
        
        .progress-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .navigation-buttons {
            background: #f8f9fa;
            padding: 20px;
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 10px 10px;
        }
        
         .identification-input {
             border: 2px solid #e9ecef;
             border-radius: 12px;
             padding: 20px;
             font-size: 1.2rem;
             transition: all 0.3s ease;
             background: #ffffff;
             box-shadow: 0 2px 8px rgba(0,0,0,0.05);
         }
         
         .identification-input:focus {
             border-color: #007bff;
             box-shadow: 0 0 0 0.3rem rgba(0, 123, 255, 0.25);
             transform: translateY(-2px);
         }
        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 16px 20px;
            border-radius: 15px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            min-width: 180px;
            text-align: center;
            font-size: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            animation: timerPulse 2s ease-in-out infinite;
        }
        
        .timer.warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            animation: timerWarning 1s ease-in-out infinite;
        }
        
        .timer.danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            animation: timerDanger 0.5s ease-in-out infinite;
        }
        
        .timer-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
        }
        
        .timer-icon {
            font-size: 1rem;
            margin-right: 8px;
            animation: timerIconSpin 2s linear infinite;
        }
        
        .timer-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .timer-text {
            font-size: 1.5rem;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            display: block;
            margin: 4px 0;
        }
        
        .timer-total {
            font-size: 0.8rem;
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
            background: rgba(255, 255, 255, 0.3);
            border-radius: 0 0 15px 15px;
            overflow: hidden;
        }
        
        .timer-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffffff, #f8f9fa);
            width: 100%;
            transition: width 1s linear;
            border-radius: 0 0 15px 15px;
        }
         .option-item {
             border: 2px solid #e9ecef;
             border-radius: 12px;
             padding: 15px 20px;
             margin-bottom: 12px;
             cursor: pointer;
             transition: all 0.3s ease;
             display: flex;
             align-items: center;
             background: #ffffff;
             box-shadow: 0 2px 4px rgba(0,0,0,0.05);
         }
         .option-item:hover {
             background-color: #f8f9fa;
             border-color: #007bff;
             transform: translateY(-2px);
             box-shadow: 0 4px 12px rgba(0,123,255,0.15);
         }
         .option-item.selected {
             background: linear-gradient(135deg, #007bff, #0056b3);
             color: white;
             border-color: #007bff;
             transform: translateY(-2px);
             box-shadow: 0 6px 20px rgba(0,123,255,0.3);
         }
        
        .checkbox-option {
            position: relative;
        }
        
        .checkbox-icon {
            font-size: 1.4rem;
            margin-right: 15px;
            color: #6c757d;
            transition: all 0.3s ease;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            background: #ffffff;
        }
        
        .checkbox-option.selected .checkbox-icon {
            color: #ffffff;
            background: #007bff;
            border-color: #007bff;
        }
        
        .checkbox-option.selected .checkbox-icon::before {
            content: "✓";
            font-weight: bold;
        }
        .progress-bar {
            height: 5px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php if ($is_acad_year_active): ?>
                <!-- Timer and related JS only visible if academic year is active -->
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

            <!-- Main content -->
            <main class="col-12 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="assessments.php">Assessments</a></li>
                                <li class="breadcrumb-item active"><?php echo htmlspecialchars($assessment['assessment_title']); ?></li>
                            </ol>
                        </nav>
                        <h1 class="h2"><?php echo htmlspecialchars($assessment['assessment_title']); ?></h1>
                        <p class="text-muted">
                            <?php echo htmlspecialchars($assessment['course_name']); ?> - 
                            <?php echo htmlspecialchars($assessment['module_title']); ?>
                        </p>
                    </div>
                </div>

                <?php if ($is_view_only): ?>
                    <div class="alert alert-warning mb-4">
                        <strong>Inactive Academic Period:</strong> 
                        <?php if (!$is_acad_year_active): ?>
                            This academic year is inactive. 
                        <?php endif; ?>
                        <?php if (!$is_semester_active): ?>
                            This semester is inactive. 
                        <?php endif; ?>
                        You cannot take or submit this assessment. Only review is allowed.
                    </div>
                    <!-- Show assessment info in view-only mode, hide form/buttons -->
                <?php else: ?>
                    <?php if (empty($questions)): ?>
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-triangle"></i> No Questions Available</h5>
                            <p>This assessment doesn't have any questions yet. Please contact your teacher to add questions to this assessment.</p>
                            <a href="assessments.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Assessments
                            </a>
                        </div>
                    <?php elseif ($current_question_data): ?>
                        <!-- Progress Bar -->
                        <div class="progress-container mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Progress</span>
                                <span class="text-muted">Question <?php echo $current_question + 1; ?> of <?php echo $total_questions; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo (($current_question + 1) / $total_questions) * 100; ?>%"></div>
                            </div>
                        </div>

                        
                        <!-- Question Form -->
                        <form method="POST" action="assessment.php?id=<?php echo $assessment_id; ?>&q=<?php echo $current_question; ?>" id="assessment-form">
                            <input type="hidden" name="time_taken" id="time-taken" value="0">
                            <input type="hidden" name="time_expired" id="time-expired" value="0">
                            <input type="hidden" name="auto_submit" id="auto-submit" value="0">
                            
                            <div class="question-card">
                                <!-- Question Number -->
                                <div class="question-number">
                                    <h4>Question <?php echo $current_question + 1; ?> of <?php echo $total_questions; ?></h4>
                                </div>
                                
                                <!-- Question Text -->
                                <div class="question-text">
                                    <?php echo htmlspecialchars($current_question_data['question_text']); ?>
                                </div>
                                
                                <!-- Answer Input -->
                                <?php if (strtolower(trim($current_question_data['question_type'])) === 'identification'): ?>
                                    <div class="mb-4">
                                        <input type="text" 
                                               class="form-control identification-input" 
                                               name="answers[<?php echo $current_question_data['id']; ?>]" 
                                               placeholder="Type your answer here..." 
                                               autocomplete="off"
                                               id="identification-answer">
                                    </div>
                                <?php elseif (strtolower(trim($current_question_data['question_type'])) === 'true_false'): ?>
                                    <div class="options">
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
                                             <div class="option-item" onclick="selectOption(this, '<?php echo $current_question_data['id']; ?>', '<?php echo $option; ?>')">
                                                 <input type="radio" 
                                                        name="answers[<?php echo $current_question_data['id']; ?>]" 
                                                        value="<?php echo $option; ?>" 
                                                        style="display: none;">
                                                 <strong><?php echo getOptionLetter($key); ?>.</strong> <?php echo htmlspecialchars($option); ?>
                                             </div>
                                         <?php endforeach; ?>
                                    </div>
                                <?php elseif (strtolower(trim($current_question_data['question_type'])) === 'multiple_choice'): ?>
                                    <div class="options">
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-info-circle me-1"></i>
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
                                            <div class="option-item checkbox-option" onclick="selectMultipleOption(this, '<?php echo $current_question_data['id']; ?>', '<?php echo $key; ?>')">
                                                <input type="checkbox" 
                                                       name="answers[<?php echo $current_question_data['id']; ?>][]" 
                                                       value="<?php echo $key; ?>" 
                                                       style="display: none;">
                                                 <span class="checkbox-icon"></span>
                                                 <strong><?php echo getOptionLetter($key); ?>.</strong> <?php echo htmlspecialchars($option); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Default to multiple choice with checkboxes -->
                                    <div class="options">
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-info-circle me-1"></i>
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
                                            <div class="option-item checkbox-option" onclick="selectMultipleOption(this, '<?php echo $current_question_data['id']; ?>', '<?php echo $key; ?>')">
                                                <input type="checkbox" 
                                                       name="answers[<?php echo $current_question_data['id']; ?>][]" 
                                                       value="<?php echo $key; ?>" 
                                                       style="display: none;">
                                                 <span class="checkbox-icon"></span>
                                                 <strong><?php echo getOptionLetter($key); ?>.</strong> <?php echo htmlspecialchars($option); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Navigation Buttons -->
                            <div class="navigation-buttons">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if ($current_question > 0): ?>
                                            <a href="assessment.php?id=<?php echo $assessment_id; ?>&q=<?php echo $current_question - 1; ?>" 
                                               class="btn btn-outline-secondary"
                                               onclick="saveTimerState(); isNavigatingBetweenQuestions = true;">
                                                <i class="fas fa-arrow-left"></i> Previous
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div>
                                        <a href="assessments.php" class="btn btn-outline-danger me-2">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                        
                                        <?php if ($current_question < $total_questions - 1): ?>
                                            <button type="button" class="btn btn-primary" onclick="saveAndNext()">
                                                Next <i class="fas fa-arrow-right"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="submit_assessment" class="btn btn-success" onclick="return validateAndSubmitAssessment()">
                                                <i class="fas fa-paper-plane"></i> Submit Assessment
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Timer functionality
        let timeLimit = <?php echo $assessment['time_limit']; ?> * 60; // Convert to seconds
        let timeLeft = timeLimit;
        let timerInterval;
        let startTime = null; // Will be set when assessment actually begins
        
        // Timer persistence key
        const timerKey = 'assessment_timer_' + '<?php echo $assessment_id; ?>';
        
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
            const timeLimitMinutes = <?php echo $assessment['time_limit']; ?>;
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
            notification.style.cssText = 'top: 80px; right: 20px; z-index: 1050; min-width: 300px; max-width: 400px;';
            notification.innerHTML = `
                ${message}
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

        function selectOption(element, questionId, optionValue) {
            // Remove selected class from other options in this question
            const questionCard = element.closest('.question-card');
            questionCard.querySelectorAll('.option-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Check the radio button
            const radio = element.querySelector('input[type="radio"]');
            radio.checked = true;
            
            updateProgress();
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
            console.log('DOM loaded, starting assessment immediately...');
            
            // Clear localStorage if this is a retake
            <?php if ($is_retake): ?>
            console.log('Retake detected - clearing localStorage...');
            const questionIds = <?php echo json_encode(array_column($questions, 'id')); ?>;
            questionIds.forEach(questionId => {
                localStorage.removeItem('assessment_' + '<?php echo $assessment_id; ?>' + '_q_' + questionId);
            });
            // Also clear any timer state
            localStorage.removeItem('assessment_timer_' + '<?php echo $assessment_id; ?>');
            <?php endif; ?>
            
            const timer = document.getElementById('timer');
            console.log('Timer element found:', timer);
            
            // Start assessment immediately (no pre-countdown)
            beginAssessment();
            
            // Prevent back navigation during assessment
            preventBackNavigation();
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
                const savedAnswer = localStorage.getItem('assessment_' + '<?php echo $assessment_id; ?>' + '_q_' + questionId);
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
            // Don't show warning if we're navigating between questions
            if (isNavigatingBetweenQuestions) {
                return;
            }
            
            e.preventDefault();
            e.returnValue = '';
        }
        window.beforeUnloadHandler = beforeUnloadHandler;
        window.addEventListener('beforeunload', beforeUnloadHandler);

        // New functions for one-question-at-a-time interface
        function selectOption(element, questionId, optionValue) {
            // Remove selected class from all options for this question
            const allOptions = document.querySelectorAll(`input[name="answers[${questionId}]"]`);
            allOptions.forEach(option => {
                option.closest('.option-item').classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Set the radio button value
            const radioButton = element.querySelector('input[type="radio"]');
            radioButton.checked = true;
            
            // Save answer immediately when option is selected
            saveCurrentAnswer(questionId);
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
            
            // Save answer to localStorage
            localStorage.setItem('assessment_' + '<?php echo $assessment_id; ?>' + '_q_' + questionId, answer);
            console.log('Immediately saved answer for question ' + questionId + ': "' + answer + '"');
            console.log('Question type detected:', document.querySelector(`input[name="answers[${questionId}][]"]`) ? 'multiple_choice' : 'other');
            
            // Debug: Check all checkboxes for this question
            const allCheckboxes = document.querySelectorAll(`input[name="answers[${questionId}][]"]`);
            console.log('All checkboxes for question ' + questionId + ':', allCheckboxes.length);
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
                const savedAnswer = localStorage.getItem('assessment_' + '<?php echo $assessment_id; ?>' + '_q_' + qId);
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
                localStorage.setItem('assessment_' + '<?php echo $assessment_id; ?>' + '_q_' + questionId, answer);
                console.log('Saved answer for question ' + questionId + ': "' + answer + '"');
            }
            
            // Save timer state before navigating
            saveTimerState();
            
            // Set navigation flag to prevent beforeunload warning
            isNavigatingBetweenQuestions = true;
            
            // Navigate to next question
            const currentQ = <?php echo $current_question; ?>;
            const nextQ = currentQ + 1;
            window.location.href = 'assessment.php?id=<?php echo $assessment_id; ?>&q=' + nextQ;
        }

        // Load saved answers when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const questionId = '<?php echo $current_question_data['id'] ?? ''; ?>';
            if (questionId) {
                const savedAnswer = localStorage.getItem('assessment_' + '<?php echo $assessment_id; ?>' + '_q_' + questionId);
                if (savedAnswer !== null) {
                    // For identification questions (text input)
                    const textInput = document.querySelector(`input[name="answers[${questionId}]"][type="text"]`);
                    if (textInput) {
                        textInput.value = savedAnswer;
                        
                        // Add real-time saving for text input
                        textInput.addEventListener('input', function() {
                            saveCurrentAnswer(questionId);
                        });
                    }
                    
                    // Check if this is a multiple choice question with checkboxes
                    if (savedAnswer.includes(',')) {
                        // Multiple answers (comma-separated)
                        const selectedValues = savedAnswer.split(',');
                        selectedValues.forEach(value => {
                            const checkbox = document.querySelector(`input[name="answers[${questionId}][]"][value="${value}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                                const optionItem = checkbox.closest('.option-item');
                                optionItem.classList.add('selected');
                                 const icon = optionItem.querySelector('.checkbox-icon');
                                 if (icon) {
                                     icon.innerHTML = '✓';
                                 }
                            }
                        });
                    } else {
                        // Single answer (radio button for true/false)
                        const radioButton = document.querySelector(`input[name="answers[${questionId}]"][value="${savedAnswer}"]`);
                        if (radioButton) {
                            radioButton.checked = true;
                            radioButton.closest('.option-item').classList.add('selected');
                        }
                    }
                } else {
                    // Add real-time saving for text input even if no saved answer
                    const textInput = document.querySelector(`input[name="answers[${questionId}]"][type="text"]`);
                    if (textInput) {
                        textInput.addEventListener('input', function() {
                            saveCurrentAnswer(questionId);
                        });
                    }
                }
            }
        });

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
                localStorage.setItem('assessment_' + '<?php echo $assessment_id; ?>' + '_q_' + questionId, answer);
                console.log('Auto-saved answer for question ' + questionId + ': "' + answer + '"');
            }
        }, 5000); // Auto-save every 5 seconds
    </script>
</body>
</html> 
</html> 