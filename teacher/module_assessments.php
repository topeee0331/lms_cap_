<?php
// Handle AJAX requests first, before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || !in_array($_POST['action'] ?? '', ['create_assessment', 'update_assessment', 'delete_assessment', 'toggle_status']))) {
    // Set content type for JSON responses
    header('Content-Type: application/json');
    
    // Include necessary files for AJAX processing
require_once '../config/config.php';
    
    // Start session for CSRF validation
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    error_log("POST request received - processing AJAX action");
    error_log("POST data: " . print_r($_POST, true));
    error_log("GET data: " . print_r($_GET, true));
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    // Debug: Log CSRF token validation
    error_log("AJAX Request - Action: $action, CSRF Token: $csrf_token, Expected: " . ($_SESSION[CSRF_TOKEN_NAME] ?? 'not set'));
    
    // CSRF validation
    if (!validateCSRFToken($csrf_token)) {
        error_log("CSRF token validation failed for action: $action");
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token.'
        ]);
        exit;
    }
    
    // Get module_id from GET parameters
    $module_id = sanitizeInput($_GET['module_id'] ?? '');

// Verify teacher owns this module
$stmt = $db->prepare("
        SELECT c.*, c.modules
        FROM courses c
        WHERE c.teacher_id = ? AND JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL
    ");
    $stmt->execute([$_SESSION['user_id'], $module_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        echo json_encode([
            'success' => false,
            'message' => 'Module not found or access denied.'
        ]);
        exit;
    }
    
    // Decode modules JSON
    $modules_data = json_decode($course['modules'] ?? '[]', true) ?: [];
    
    // Process AJAX actions
    switch ($action) {
        case 'get_questions':
            $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
            
            // Debug logging
            error_log("Getting questions for assessment_id: " . $assessment_id);
            
            // Verify that this assessment exists in the system
            $assessment_exists = false;
            foreach ($modules_data as $module) {
                if (isset($module['assessments']) && is_array($module['assessments'])) {
                    foreach ($module['assessments'] as $assessment) {
                        if ($assessment['id'] === $assessment_id) {
                            $assessment_exists = true;
                            break 2;
                        }
                    }
                }
            }
            
            if (!$assessment_exists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Assessment not found or access denied.'
                ]);
                exit;
            }
            
            // Get questions from the questions table
            $stmt = $db->prepare("
                SELECT id, question_text, question_type, question_order, points, options, created_at
                FROM questions 
                WHERE assessment_id = ? 
                ORDER BY question_order ASC
            ");
            $stmt->execute([$assessment_id]);
            $questions = $stmt->fetchAll();
            
            // Decode options JSON for each question
            foreach ($questions as &$question) {
                $question['options'] = json_decode($question['options'] ?? '[]', true) ?: [];
            }
            
            // Return JSON response
            echo json_encode([
                'success' => true,
                'questions' => $questions
            ]);
            exit;
            
        case 'get_question':
            $question_id = sanitizeInput($_POST['question_id'] ?? '');
            
            // Debug logging
            error_log("Getting question with ID: " . $question_id);
            
            // Get question from the questions table
            $stmt = $db->prepare("
                SELECT id, question_text, question_type, question_order, points, options, created_at, assessment_id
                FROM questions 
                WHERE id = ?
            ");
            $stmt->execute([$question_id]);
            $question = $stmt->fetch();
            
            if ($question) {
                // Verify that this question belongs to an assessment that exists in the system
                $assessment_exists = false;
                foreach ($modules_data as $module) {
                    if (isset($module['assessments']) && is_array($module['assessments'])) {
                        foreach ($module['assessments'] as $assessment) {
                            if ($assessment['id'] === $question['assessment_id']) {
                                $assessment_exists = true;
                                break 2;
                            }
                        }
                    }
                }
                
                if (!$assessment_exists) {
                    $question = null; // Question doesn't belong to a valid assessment
                } else {
                    // Decode options JSON
                    $question['options'] = json_decode($question['options'] ?? '[]', true) ?: [];
                    error_log("Question found: " . print_r($question, true));
                }
            } else {
                error_log("Question not found for ID: " . $question_id);
            }
            
            // Return JSON response
            if ($question) {
                $response = [
                    'success' => true,
                    'question' => $question
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Question not found'
                ];
            }
            
            // Ensure clean output
            ob_clean();
            
            $json_response = json_encode($response);
            if ($json_response === false) {
                error_log("JSON encode error: " . json_last_error_msg());
                $json_response = json_encode([
                    'success' => false,
                    'message' => 'Error encoding response: ' . json_last_error_msg()
                ]);
            }
            
            error_log("Sending response: " . $json_response);
            echo $json_response;
            exit;
            
        case 'create_question':
            $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
            $question_text = sanitizeInput($_POST['question_text'] ?? '');
            $question_type = sanitizeInput($_POST['question_type'] ?? 'multiple_choice');
            $question_type = strtolower(trim($question_type));
            $points = (int)($_POST['points'] ?? 1);
            
            if (empty($question_text)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Question text is required.'
                ]);
                exit;
            }
            
            // Get next question order
            $stmt = $db->prepare("
                SELECT COALESCE(MAX(question_order), 0) + 1 as next_order 
                FROM questions 
                WHERE assessment_id = ?
            ");
            $stmt->execute([$assessment_id]);
            $next_order = $stmt->fetchColumn();
            
            // Prepare options array
            $options = [];
            
            if ($question_type === 'multiple_choice') {
                $options_input = $_POST['options'] ?? [];
                $correct_option = (int)($_POST['correct_option'] ?? 0);
                
                foreach ($options_input as $index => $option_text) {
                    if (!empty($option_text)) {
                        $options[] = [
                            'text' => $option_text,
                            'is_correct' => ($index == $correct_option),
                            'order' => $index + 1
                        ];
                    }
                }
            } elseif ($question_type === 'true_false') {
                $correct_tf = $_POST['correct_tf'] ?? 'true';
                $options = [
                    [
                        'text' => 'True',
                        'is_correct' => ($correct_tf === 'true'),
                        'order' => 1
                    ],
                    [
                        'text' => 'False',
                        'is_correct' => ($correct_tf === 'false'),
                        'order' => 2
                    ]
                ];
            } elseif ($question_type === 'identification') {
                $correct_answer = strtoupper(trim($_POST['correct_answer'] ?? ''));
                if (!empty($correct_answer)) {
                    $options = [
                        [
                            'text' => $correct_answer,
                            'is_correct' => true,
                            'order' => 1
                        ]
                    ];
                }
            }
            
            // Insert question into database
            $stmt = $db->prepare("
                INSERT INTO questions 
                (assessment_id, question_text, question_type, question_order, points, options) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $assessment_id,
                $question_text,
                $question_type,
                $next_order,
                $points,
                json_encode($options)
            ])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Question added successfully.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to add question.'
                ]);
            }
            exit;
            
        case 'update_question':
            $question_id = sanitizeInput($_POST['question_id'] ?? '');
            $question_text = sanitizeInput($_POST['question_text'] ?? '');
            $question_type = sanitizeInput($_POST['question_type'] ?? 'multiple_choice');
            $question_type = strtolower(trim($question_type));
            $points = (int)($_POST['points'] ?? 1);
            
            if (empty($question_text)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Question text is required.'
                ]);
                exit;
            }
            
            // Prepare options array
            $options = [];
            
            if ($question_type === 'multiple_choice') {
                $options_input = $_POST['options'] ?? $_POST['edit_options'] ?? [];
                $correct_option = (int)($_POST['correct_option'] ?? $_POST['edit_correct_option'] ?? 0);
                
                foreach ($options_input as $index => $option_text) {
                    if (!empty($option_text)) {
                        $options[] = [
                            'text' => $option_text,
                            'is_correct' => ($index == $correct_option),
                            'order' => $index + 1
                        ];
                    }
                }
            } elseif ($question_type === 'true_false') {
                $correct_tf = $_POST['correct_tf'] ?? $_POST['edit_correct_tf'] ?? 'true';
                $options = [
                    [
                        'text' => 'True',
                        'is_correct' => ($correct_tf === 'true'),
                        'order' => 1
                    ],
                    [
                        'text' => 'False',
                        'is_correct' => ($correct_tf === 'false'),
                        'order' => 2
                    ]
                ];
            } elseif ($question_type === 'identification') {
                $correct_answer = strtoupper(trim($_POST['correct_answer'] ?? $_POST['edit_correct_answer'] ?? ''));
                if (!empty($correct_answer)) {
                    $options = [
                        [
                            'text' => $correct_answer,
                            'is_correct' => true,
                            'order' => 1
                        ]
                    ];
                }
            }
            
            // Update question in database
            $stmt = $db->prepare("
                UPDATE questions 
                SET question_text = ?, question_type = ?, points = ?, options = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                $question_text,
                $question_type,
                $points,
                json_encode($options),
                $question_id
            ])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Question updated successfully.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Question not found.'
                ]);
            }
            exit;
            
        case 'delete_question':
            $question_id = sanitizeInput($_POST['question_id'] ?? '');
            
            // Delete question from database
            $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
            
            if ($stmt->execute([$question_id])) {
                // Reorder remaining questions
                $stmt = $db->prepare("
                    UPDATE questions 
                    SET question_order = (
                        SELECT new_order FROM (
                            SELECT id, ROW_NUMBER() OVER (ORDER BY question_order) as new_order
                            FROM questions 
                            WHERE assessment_id = (
                                SELECT assessment_id FROM questions WHERE id = ?
                            )
                        ) as reordered 
                        WHERE questions.id = reordered.id
                    )
                    WHERE assessment_id = (
                        SELECT assessment_id FROM questions WHERE id = ?
                    )
                ");
                $stmt->execute([$question_id, $question_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Question deleted successfully.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Question not found.'
                ]);
            }
            exit;
            
        case 'create_bulk_questions':
            $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
            $questions_data = $_POST['questions'] ?? [];
            
            if (empty($assessment_id)) {
                echo json_encode(['success' => false, 'message' => 'Assessment ID is required.']);
                exit;
            }
            
            $created_count = 0;
            $errors = [];
            
            // Get next order number
            $stmt = $db->prepare("SELECT COALESCE(MAX(question_order), 0) + 1 as next_order FROM questions WHERE assessment_id = ?");
            $stmt->execute([$assessment_id]);
            $next_order = $stmt->fetchColumn();
            
            foreach ($questions_data as $index => $question_data) {
                $question_text = sanitizeInput($question_data['question_text'] ?? '');
                $question_type = sanitizeInput($question_data['question_type'] ?? 'multiple_choice');
                $points = (int)($question_data['points'] ?? 1);
                
                // Skip empty questions
                if (empty($question_text)) {
                    continue;
                }
                
                // Prepare options array
                $options = [];
                
                if ($question_type === 'multiple_choice') {
                    $options_input = $question_data['options'] ?? [];
                    $correct_option = (int)($question_data['correct_option'] ?? 0);
                    
                    foreach ($options_input as $i => $option_text) {
                        if (!empty(trim($option_text))) {
                            $options[] = [
                                'text' => trim($option_text),
                                'is_correct' => ($i === $correct_option),
                                'order' => $i + 1
                            ];
                        }
                    }
                } elseif ($question_type === 'true_false') {
                    $correct_tf = $question_data['correct_tf'] ?? 'true';
                    $options = [
                        [
                            'text' => 'True',
                            'is_correct' => ($correct_tf === 'true'),
                            'order' => 1
                        ],
                        [
                            'text' => 'False',
                            'is_correct' => ($correct_tf === 'false'),
                            'order' => 2
                        ]
                    ];
                } elseif ($question_type === 'identification') {
                    $correct_answer = strtoupper(trim($question_data['correct_answer'] ?? ''));
                    if (!empty($correct_answer)) {
                        $options = [
                            [
                                'text' => $correct_answer,
                                'is_correct' => true,
                                'order' => 1
                            ]
                        ];
                    }
                }
                
                // Insert question into database
                $stmt = $db->prepare("
                    INSERT INTO questions 
                    (assessment_id, question_text, question_type, question_order, points, options) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([
                    $assessment_id,
                    $question_text,
                    $question_type,
                    $next_order,
                    $points,
                    json_encode($options)
                ])) {
                    $created_count++;
                    $next_order++;
                } else {
                    $errors[] = "Failed to create question " . ($index + 1);
                }
            }
            
            if ($created_count > 0) {
                $message = "Successfully created {$created_count} question(s).";
                if (!empty($errors)) {
                    $message .= " Errors: " . implode(', ', $errors);
                }
                echo json_encode(['success' => true, 'message' => $message, 'created_count' => $created_count]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No valid questions were created.']);
            }
            exit;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action.'
            ]);
            exit;
    }
}

// Regular page load - include header and continue with normal page processing
$page_title = 'Module Assessments';
require_once '../includes/header.php';
requireRole('teacher');

$module_id = sanitizeInput($_GET['module_id'] ?? '');

// Verify teacher owns this module by finding it in the course's modules JSON
$stmt = $db->prepare("
    SELECT c.*, c.modules
    FROM courses c
    WHERE c.teacher_id = ? AND JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL
");
$stmt->execute([$_SESSION['user_id'], $module_id]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php?error=Module not found or access denied.');
    exit;
}

// Extract module data from JSON
$modules_data = json_decode($course['modules'], true);
$module = null;
foreach ($modules_data as $mod) {
    if ($mod['id'] === $module_id) {
        $module = $mod;
        break;
    }
}

if (!$module) {
    header('Location: courses.php?error=Module not found or access denied.');
    exit;
}

$message = '';
$message_type = '';

// Get assessments for this module from database
$assessments = [];

// Get all assessments for this course first
$stmt = $db->prepare("
    SELECT id, assessment_title, description, time_limit, difficulty, status, 
           num_questions, passing_rate, attempt_limit, is_locked, lock_type,
           prerequisite_assessment_id, prerequisite_score, prerequisite_video_count,
           unlock_date, lock_message, created_at
    FROM assessments 
    WHERE course_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$course['id']]);
$all_assessments = $stmt->fetchAll();

// Filter assessments that belong to this specific module
$assessments = []; // Reset to ensure sequential array keys
foreach ($all_assessments as $assessment) {
    // Check if this assessment exists in the current module's JSON
    if (isset($module['assessments']) && is_array($module['assessments'])) {
        foreach ($module['assessments'] as $json_assessment) {
            if ($json_assessment['id'] === $assessment['id']) {
                // Map database 'status' field to 'is_active' for backward compatibility
                $assessment['is_active'] = ($assessment['status'] === 'active');
                $assessments[] = $assessment; // Use [] to ensure sequential array keys
                break;
            }
        }
    }
}

// Handle assessment actions (non-AJAX form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create_assessment', 'update_assessment', 'delete_assessment', 'toggle_status'])) {
    error_log("POST request received - processing form submission");
    error_log("POST data: " . print_r($_POST, true));
    error_log("GET data: " . print_r($_GET, true));
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    // Debug: Log CSRF token validation
    error_log("Form Request - Action: $action, CSRF Token: $csrf_token, Expected: " . ($_SESSION[CSRF_TOKEN_NAME] ?? 'not set'));
    
    // CSRF validation
    if (!validateCSRFToken($csrf_token)) {
        error_log("CSRF token validation failed for action: $action");
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'create_assessment':
                $assessment_title = sanitizeInput($_POST['assessment_title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $time_limit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
                $difficulty = sanitizeInput($_POST['difficulty'] ?? 'medium');
                $num_questions = (int)($_POST['num_questions'] ?? 10);
                $passing_rate = (float)($_POST['passing_rate'] ?? 70.0);
                $attempt_limit = (int)($_POST['attempt_limit'] ?? 3);
                $assessment_order = (int)($_POST['assessment_order'] ?? 1);
                
                if (empty($assessment_title)) {
                    $message = 'Assessment title is required.';
                    $message_type = 'danger';
                } elseif ($passing_rate < 0 || $passing_rate > 100) {
                    $message = 'Passing rate must be between 0 and 100.';
                    $message_type = 'danger';
                } else {
                    // Create new assessment object
                    $new_assessment = [
                        'id' => uniqid('assess_'),
                        'assessment_title' => $assessment_title,
                        'description' => $description,
                        'time_limit' => $time_limit,
                        'difficulty' => $difficulty,
                        'num_questions' => $num_questions,
                        'passing_rate' => $passing_rate,
                        'attempt_limit' => $attempt_limit,
                        'assessment_order' => $assessment_order,
                        'is_active' => true,
                        'status' => 'active',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Save assessment to database table
                    $stmt = $db->prepare("
                        INSERT INTO assessments (
                            id, course_id, assessment_title, description, time_limit, difficulty, 
                            status, num_questions, passing_rate, attempt_limit, assessment_order, is_locked, lock_type,
                            questions, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $new_assessment['id'],
                        $course['id'],
                        $assessment_title,
                        $description,
                        $time_limit,
                        $difficulty,
                        'active',
                        $num_questions,
                        $passing_rate,
                        $attempt_limit,
                        $assessment_order,
                        0,
                        'manual',
                        '[]',
                        $new_assessment['created_at']
                    ]);
                    
                    // Add assessment to module's assessments array
                    if (!isset($module['assessments'])) {
                        $module['assessments'] = [];
                    }
                    $module['assessments'][] = $new_assessment;
                    
                    // Update the module in the modules array
                    foreach ($modules_data as &$mod) {
                        if ($mod['id'] === $module_id) {
                            $mod = $module;
                            break;
                        }
                    }
                    
                    // Update course with new modules JSON
                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                    $stmt->execute([json_encode($modules_data), $course['id']]);
                    
                    // Refresh the assessments array to show the newly added assessment immediately
                    $assessments[] = $new_assessment;
                    
                    $message = 'Assessment created successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'update_assessment':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                $assessment_title = sanitizeInput($_POST['edit_assessment_title'] ?? '');
                $description = sanitizeInput($_POST['edit_description'] ?? '');
                $time_limit = !empty($_POST['edit_time_limit']) ? (int)$_POST['edit_time_limit'] : null;
                $difficulty = sanitizeInput($_POST['edit_difficulty'] ?? 'medium');
                $num_questions = (int)($_POST['edit_num_questions'] ?? 10);
                $passing_rate = (float)($_POST['edit_passing_rate'] ?? 70.0);
                $attempt_limit = (int)($_POST['edit_attempt_limit'] ?? 3);
                $assessment_order = (int)($_POST['edit_assessment_order'] ?? 1);
                
                // Debug: Log the assessment order being updated
                error_log("Updating assessment order for ID: $assessment_id, New order: $assessment_order");
                
                if (empty($assessment_title)) {
                    $message = 'Assessment title is required.';
                    $message_type = 'danger';
                } elseif ($passing_rate < 0 || $passing_rate > 100) {
                    $message = 'Passing rate must be between 0 and 100.';
                    $message_type = 'danger';
                } else {
                    // Update assessment in database table
                    $stmt = $db->prepare("
                        UPDATE assessments SET 
                            assessment_title = ?, description = ?, time_limit = ?, difficulty = ?, 
                            num_questions = ?, passing_rate = ?, attempt_limit = ?, assessment_order = ?
                        WHERE id = ? AND course_id = ?
                    ");
                    $result = $stmt->execute([
                        $assessment_title, $description, $time_limit, $difficulty,
                        $num_questions, $passing_rate, $attempt_limit, $assessment_order,
                        $assessment_id, $course['id']
                    ]);
                    
                    // Debug: Log the database update result
                    error_log("Database update result: " . ($result ? 'SUCCESS' : 'FAILED') . ", Rows affected: " . $stmt->rowCount());
                    
                    // Find and update the assessment in the module
                    if (isset($module['assessments']) && is_array($module['assessments'])) {
                        foreach ($module['assessments'] as &$assessment) {
                            if ($assessment['id'] === $assessment_id) {
                                $assessment['assessment_title'] = $assessment_title;
                                $assessment['description'] = $description;
                                $assessment['time_limit'] = $time_limit;
                                $assessment['difficulty'] = $difficulty;
                                $assessment['num_questions'] = $num_questions;
                                $assessment['passing_rate'] = $passing_rate;
                                $assessment['attempt_limit'] = $attempt_limit;
                                $assessment['assessment_order'] = $assessment_order;
                                $assessment['updated_at'] = date('Y-m-d H:i:s');
                                break;
                            }
                        }
                        
                        // Update the module in the modules array
                        foreach ($modules_data as &$mod) {
                            if ($mod['id'] === $module_id) {
                                $mod = $module;
                                break;
                            }
                        }
                        
                        // Update course with updated modules JSON
                        $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                        $stmt->execute([json_encode($modules_data), $course['id']]);
                        
                        // Refresh the assessments array
                        foreach ($assessments as &$assess) {
                            if ($assess['id'] === $assessment_id) {
                                $assess['assessment_title'] = $assessment_title;
                                $assess['description'] = $description;
                                $assess['time_limit'] = $time_limit;
                                $assess['difficulty'] = $difficulty;
                                $assess['num_questions'] = $num_questions;
                                $assess['passing_rate'] = $passing_rate;
                                $assess['attempt_limit'] = $attempt_limit;
                                $assess['assessment_order'] = $assessment_order;
                                break;
                            }
                        }
                        
                        $message = "Assessment updated successfully. Order set to: $assessment_order";
                        $message_type = 'success';
                    }
                }
                break;
                
                        case 'toggle_status':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                $is_active = (int)($_POST['is_active'] ?? 0);
                $status = $is_active ? 'active' : 'inactive';
                
                // Update assessment status in database
                $stmt = $db->prepare("UPDATE assessments SET status = ? WHERE id = ? AND course_id = ?");
                $stmt->execute([$status, $assessment_id, $course['id']]);
                
                // Find and update the assessment in the module
                if (isset($module['assessments']) && is_array($module['assessments'])) {
                    foreach ($module['assessments'] as &$assessment) {
                        if ($assessment['id'] === $assessment_id) {
                            $assessment['is_active'] = (bool)$is_active;
                            break;
                        }
                    }
                    
                    // Update the module in the modules array
                    foreach ($modules_data as &$mod) {
                        if ($mod['id'] === $module_id) {
                            $mod = $module;
                            break;
                        }
                    }
                    
                    // Update course with updated modules JSON
                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                    $stmt->execute([json_encode($modules_data), $course['id']]);
                    
                    // Refresh the assessments array
                    foreach ($assessments as &$assess) {
                        if ($assess['id'] === $assessment_id) {
                            $assess['is_active'] = (bool)$is_active;
                            $assess['status'] = $status;
                            break;
                        }
                    }
                    
                    $message = 'Assessment status updated successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'delete_assessment':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                
                // Delete assessment from database table
                $stmt = $db->prepare('DELETE FROM assessments WHERE id = ? AND course_id = ?');
                $stmt->execute([$assessment_id, $course['id']]);
                
                // Also delete related questions
                $stmt = $db->prepare('DELETE FROM questions WHERE assessment_id = ?');
                $stmt->execute([$assessment_id]);
                
                // Find and remove the assessment from the module
                if (isset($module['assessments']) && is_array($module['assessments'])) {
                    $module['assessments'] = array_filter($module['assessments'], function($assessment) use ($assessment_id) {
                        return $assessment['id'] !== $assessment_id;
                    });
                    
                    // Update the module in the modules array
                    foreach ($modules_data as &$mod) {
                        if ($mod['id'] === $module_id) {
                            $mod = $module;
                            break;
                        }
                    }
                    
                    // Update course with updated modules JSON
                    $stmt = $db->prepare('UPDATE courses SET modules = ? WHERE id = ?');
                    $stmt->execute([json_encode($modules_data), $course['id']]);
                    
                    // Refresh the assessments array to remove the deleted assessment immediately
                    $assessments = array_filter($assessments, function($assessment) use ($assessment_id) {
                        return $assessment['id'] !== $assessment_id;
                    });
                    
                    $message = 'Assessment deleted successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'create_question':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                $question_text = sanitizeInput($_POST['question_text'] ?? '');
                $question_type = sanitizeInput($_POST['question_type'] ?? 'multiple_choice');
                $question_type = strtolower(trim($question_type));
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
                    // Get next question order
                    $stmt = $db->prepare("
                        SELECT COALESCE(MAX(question_order), 0) + 1 as next_order 
                        FROM questions 
                        WHERE assessment_id = ?
                    ");
                $stmt->execute([$assessment_id]);
                    $next_order = $stmt->fetchColumn();
                    
                    // Prepare options array
                    $options = [];
                    
                    if ($question_type === 'multiple_choice') {
                        $options_input = $_POST['options'] ?? [];
                        $correct_option = (int)($_POST['correct_option'] ?? 0);
                        
                        foreach ($options_input as $index => $option_text) {
                            if (!empty($option_text)) {
                                $options[] = [
                                    'text' => $option_text,
                                    'is_correct' => ($index == $correct_option),
                                    'order' => $index + 1
                                ];
                            }
                        }
                    } elseif ($question_type === 'true_false') {
                        $correct_tf = $_POST['correct_tf'] ?? 'true';
                        $options = [
                            [
                                'text' => 'True',
                                'is_correct' => ($correct_tf === 'true'),
                                'order' => 1
                            ],
                            [
                                'text' => 'False',
                                'is_correct' => ($correct_tf === 'false'),
                                'order' => 2
                            ]
                        ];
                    } elseif ($question_type === 'identification') {
                        $correct_answer = strtoupper(trim($_POST['correct_answer'] ?? ''));
                        if (!empty($correct_answer)) {
                            $options = [
                                [
                                    'text' => $correct_answer,
                                    'is_correct' => true,
                                    'order' => 1
                                ]
                            ];
                        }
                    }
                    
                    // Insert question into database
                    $stmt = $db->prepare("
                        INSERT INTO questions 
                        (assessment_id, question_text, question_type, question_order, points, options) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([
                        $assessment_id,
                        $question_text,
                        $question_type,
                        $next_order,
                        $points,
                        json_encode($options)
                    ])) {
                        $message = 'Question added successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Assessment not found.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'update_question':
                $question_id = sanitizeInput($_POST['question_id'] ?? '');
                $question_text = sanitizeInput($_POST['question_text'] ?? '');
                $question_type = sanitizeInput($_POST['question_type'] ?? 'multiple_choice');
                $question_type = strtolower(trim($question_type));
                $points = (int)($_POST['points'] ?? 1);
                
                if (empty($question_text)) {
                    $message = 'Question text is required.';
                    $message_type = 'danger';
                } else {
                    // Prepare options array
                    $options = [];
                    
                    if ($question_type === 'multiple_choice') {
                        $options_input = $_POST['options'] ?? [];
                        $correct_option = (int)($_POST['correct_option'] ?? 0);
                        
                        foreach ($options_input as $index => $option_text) {
                            if (!empty($option_text)) {
                                $options[] = [
                                    'text' => $option_text,
                                    'is_correct' => ($index == $correct_option),
                                    'order' => $index + 1
                                ];
                            }
                        }
                    } elseif ($question_type === 'true_false') {
                        $correct_tf = $_POST['correct_tf'] ?? 'true';
                        $options = [
                            [
                                'text' => 'True',
                                'is_correct' => ($correct_tf === 'true'),
                                'order' => 1
                            ],
                            [
                                'text' => 'False',
                                'is_correct' => ($correct_tf === 'false'),
                                'order' => 2
                            ]
                        ];
                    } elseif ($question_type === 'identification') {
                        $correct_answer = strtoupper(trim($_POST['correct_answer'] ?? ''));
                        if (!empty($correct_answer)) {
                            $options = [
                                [
                                    'text' => $correct_answer,
                                    'is_correct' => true,
                                    'order' => 1
                                ]
                            ];
                        }
                    }
                    
                    // Update question in database
                    $stmt = $db->prepare("
                        UPDATE questions 
                        SET question_text = ?, question_type = ?, points = ?, options = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([
                        $question_text,
                        $question_type,
                        $points,
                        json_encode($options),
                        $question_id
                    ])) {
                        $message = 'Question updated successfully.';
                    $message_type = 'success';
                    } else {
                        $message = 'Question not found.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'delete_question':
                $question_id = sanitizeInput($_POST['question_id'] ?? '');
                
                // Delete question from database
                $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
                
                if ($stmt->execute([$question_id])) {
                    // Reorder remaining questions
                    $stmt = $db->prepare("
                        UPDATE questions 
                        SET question_order = (
                            SELECT new_order FROM (
                                SELECT id, ROW_NUMBER() OVER (ORDER BY question_order) as new_order
                                FROM questions 
                                WHERE assessment_id = (
                                    SELECT assessment_id FROM questions WHERE id = ?
                                )
                            ) as reordered 
                            WHERE questions.id = reordered.id
                        )
                        WHERE assessment_id = (
                            SELECT assessment_id FROM questions WHERE id = ?
                        )
                    ");
                    $stmt->execute([$question_id, $question_id]);
                    
                    $message = 'Question deleted successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Question not found.';
                    $message_type = 'danger';
                }
                break;
                
            case 'get_questions':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                
                // Debug logging
                error_log("Getting questions for assessment_id (form handler): " . $assessment_id);
                
                // Verify that this assessment exists in the system
                $assessment_exists = false;
                foreach ($modules_data as $module) {
                    if (isset($module['assessments']) && is_array($module['assessments'])) {
                        foreach ($module['assessments'] as $assessment) {
                            if ($assessment['id'] === $assessment_id) {
                                $assessment_exists = true;
                                break 2;
                            }
                        }
                    }
                }
                
                if (!$assessment_exists) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Assessment not found or access denied.'
                    ]);
                    exit;
                }
                
                // Get questions from the questions table
                $stmt = $db->prepare("
                    SELECT id, question_text, question_type, question_order, points, options, created_at
                    FROM questions 
                    WHERE assessment_id = ? 
                    ORDER BY question_order ASC
                ");
                $stmt->execute([$assessment_id]);
                $questions = $stmt->fetchAll();
                
                // Decode options JSON for each question
                foreach ($questions as &$question) {
                    $question['options'] = json_decode($question['options'] ?? '[]', true) ?: [];
                }
                
                // Return JSON response
                echo json_encode([
                    'success' => true,
                    'questions' => $questions
                ]);
                exit;
                
            case 'get_question':
                $question_id = sanitizeInput($_POST['question_id'] ?? '');
                
                // Get question from the questions table
                $stmt = $db->prepare("
                    SELECT id, question_text, question_type, question_order, points, options, created_at, assessment_id
                    FROM questions 
                    WHERE id = ?
                ");
                $stmt->execute([$question_id]);
                $question = $stmt->fetch();
                
                if ($question) {
                    // Verify that this question belongs to an assessment that exists in the system
                    $assessment_exists = false;
                    foreach ($modules_data as $module) {
                        if (isset($module['assessments']) && is_array($module['assessments'])) {
                            foreach ($module['assessments'] as $assessment) {
                                if ($assessment['id'] === $question['assessment_id']) {
                                    $assessment_exists = true;
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    if (!$assessment_exists) {
                        $question = null; // Question doesn't belong to a valid assessment
                    } else {
                        // Decode options JSON
                        $question['options'] = json_decode($question['options'] ?? '[]', true) ?: [];
                    }
                }
                
                // Return JSON response
                if ($question) {
                    $response = [
                        'success' => true,
                        'question' => $question
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Question not found'
                    ];
                }
                
                // Ensure clean output
                ob_clean();
                
                $json_response = json_encode($response);
                if ($json_response === false) {
                    error_log("JSON encode error: " . json_last_error_msg());
                    $json_response = json_encode([
                        'success' => false,
                        'message' => 'Error encoding response: ' . json_last_error_msg()
                    ]);
                }
                
                // This is a form submission, not AJAX, so we don't echo JSON
                // The page will reload and show the message
                
            default:
                $message = 'Invalid action.';
                $message_type = 'danger';
                break;
        }
    }
}

// Note: Questions are now stored directly in the assessments JSON structure
// No separate helper function needed as questions are accessed directly from the JSON
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1 text-primary">
                        <i class="bi bi-clipboard-check me-2"></i>Assessments for <?php echo htmlspecialchars($module['module_title']); ?>
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="bi bi-book me-1"></i><?php echo htmlspecialchars($course['course_name']); ?> • 
                        <i class="bi bi-hash me-1"></i><?php echo htmlspecialchars($course['course_code']); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Course
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
                        <i class="bi bi-plus-circle me-2"></i>Create Assessment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Module Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Module Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="card-title text-primary"><?php echo htmlspecialchars($module['module_title']); ?></h6>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($module['module_description'])); ?></p>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <span class="badge bg-secondary fs-6">
                                    <i class="bi bi-sort-numeric-up me-1"></i>Module <?php echo $module['module_order']; ?>
                                </span>
                                <?php if ($module['is_locked']): ?>
                                    <span class="badge bg-warning ms-2 fs-6">
                                        <i class="bi bi-lock me-1"></i>Locked (<?php echo $module['unlock_score']; ?>% required)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assessments Grid -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark">
                            <i class="bi bi-list-check me-2"></i>Assessments (<?php echo count($assessments); ?>)
                        </h5>
                        <div class="text-end">
                            <?php
                            $active_count = 0;
                            foreach ($assessments as $assessment) {
                                if ($assessment['is_active']) {
                                    $active_count++;
                                }
                            }
                            ?>
                            <span class="badge bg-success fs-6">
                                <i class="bi bi-check-circle me-1"></i><?php echo $active_count; ?> active
                            </span>
                            <span class="badge bg-secondary fs-6 ms-2">
                                <i class="bi bi-pause-circle me-1"></i><?php echo count($assessments) - $active_count; ?> inactive
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($assessments)): ?>
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-clipboard-check display-1 text-muted"></i>
                            </div>
                            <h4 class="text-muted mb-3">No Assessments Found</h4>
                            <p class="text-muted mb-4">Create your first assessment for this module to start evaluating student progress and understanding.</p>
                            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
                                <i class="bi bi-plus-circle me-2"></i>Create First Assessment
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                                    <?php foreach ($assessments as $assessment): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100 border-0 shadow-sm hover-shadow">
                                        <div class="card-header bg-<?php echo $assessment['is_active'] ? 'success' : 'secondary'; ?> text-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 text-white fw-bold"><?php echo htmlspecialchars($assessment['assessment_title']); ?></h6>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" 
                                                           onchange="toggleAssessmentStatus('<?php echo $assessment['id']; ?>', this.checked)"
                                                           <?php echo $assessment['is_active'] ? 'checked' : ''; ?>
                                                           title="<?php echo $assessment['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body d-flex flex-column">
                                            <?php if (!empty($assessment['description'])): ?>
                                                <p class="card-text small text-muted mb-3"><?php echo htmlspecialchars(substr($assessment['description'], 0, 120)) . (strlen($assessment['description']) > 120 ? '...' : ''); ?></p>
                                                    <?php endif; ?>
                                            
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-clock text-info me-2"></i>
                                                        <small class="text-muted">
                                                            <?php echo $assessment['time_limit'] ? $assessment['time_limit'] . ' min' : 'No limit'; ?>
                                                        </small>
                                                </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-question-circle text-warning me-2"></i>
                                                        <small class="text-muted">
                                                            <?php echo $assessment['num_questions']; ?> questions
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-trophy text-success me-2"></i>
                                                        <small class="text-muted">
                                                            <?php echo $assessment['passing_rate']; ?>% to pass
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-arrow-repeat text-primary me-2"></i>
                                                        <small class="text-muted">
                                                            <?php echo $assessment['attempt_limit']; ?> attempts
                                                        </small>
                                                    </div>
                                                </div>
                                                </div>
                                            
                                            <div class="mb-3">
                                                <span class="badge bg-<?php echo $assessment['difficulty'] === 'easy' ? 'success' : ($assessment['difficulty'] === 'medium' ? 'warning' : 'danger'); ?> fs-6">
                                                    <i class="bi bi-<?php echo $assessment['difficulty'] === 'easy' ? 'emoji-smile' : ($assessment['difficulty'] === 'medium' ? 'emoji-neutral' : 'emoji-frown'); ?> me-1"></i>
                                                    <?php echo ucfirst($assessment['difficulty']); ?>
                                                </span>
                                                <span class="badge bg-<?php echo $assessment['is_active'] ? 'success' : 'secondary'; ?> fs-6 ms-2">
                                                    <i class="bi bi-<?php echo $assessment['is_active'] ? 'check-circle' : 'pause-circle'; ?> me-1"></i>
                                                    <?php echo $assessment['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                            </div>
                                            
                                            <div class="mt-auto">
                                                <div class="btn-group btn-group-sm w-100">
                                                    <button class="btn btn-outline-primary" onclick="editAssessment('<?php echo htmlspecialchars(json_encode($assessment)); ?>')" title="Edit Assessment">
                                                        <i class="bi bi-pencil me-1"></i>Edit
                                                    </button>
                                                    <button class="btn btn-outline-success" onclick="manageQuestions('<?php echo $assessment['id']; ?>', '<?php echo htmlspecialchars($assessment['assessment_title']); ?>')" title="Manage Questions">
                                                        <i class="bi bi-question-circle me-1"></i>Questions
                                                        </button>
                                                    <button class="btn btn-outline-info" onclick="viewAssessmentStats('<?php echo $assessment['id']; ?>')" title="View Statistics">
                                                        <i class="bi bi-graph-up me-1"></i>Stats
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteAssessment('<?php echo $assessment['id']; ?>', '<?php echo htmlspecialchars($assessment['assessment_title']); ?>')" title="Delete Assessment">
                                                        <i class="bi bi-trash me-1"></i>Delete
                                                        </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                                </div>
                                    <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Assessment Modal -->
<div class="modal fade" id="createAssessmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Create New Assessment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="module_assessments.php?module_id=<?php echo $module_id; ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_assessment">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="assessment_title" class="form-label fw-bold">
                            <i class="bi bi-pencil-square me-1 text-primary"></i>Assessment Title
                        </label>
                        <input type="text" class="form-control form-control-lg" id="assessment_title" name="assessment_title" 
                               placeholder="Enter assessment title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">
                            <i class="bi bi-card-text me-1 text-primary"></i>Description
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Enter assessment description (optional)"></textarea>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="time_limit" class="form-label fw-bold">
                                <i class="bi bi-clock me-1 text-info"></i>Time Limit (minutes)
                            </label>
                            <input type="number" class="form-control" id="time_limit" name="time_limit" 
                                   min="5" max="300" step="5" placeholder="Leave empty for no limit">
                            <div class="form-text text-muted">Leave empty for no time limit</div>
                    </div>
                        <div class="col-md-6">
                            <label for="difficulty" class="form-label fw-bold">
                                <i class="bi bi-bar-chart me-1 text-warning"></i>Difficulty Level
                            </label>
                            <select class="form-select" id="difficulty" name="difficulty" required>
                                <option value="easy">Easy</option>
                                <option value="medium" selected>Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="num_questions" class="form-label fw-bold">
                                <i class="bi bi-question-circle me-1 text-success"></i>Number of Questions
                            </label>
                            <select class="form-select" id="num_questions" name="num_questions" required>
                                <option value="10" selected>10 Questions</option>
                                <option value="30">30 Questions</option>
                                <option value="50">50 Questions</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="passing_rate" class="form-label fw-bold">
                                <i class="bi bi-trophy me-1 text-primary"></i>Passing Rate (%)
                            </label>
                            <input type="number" class="form-control" id="passing_rate" name="passing_rate" 
                                   value="70" min="0" max="100" step="5" required>
                        </div>
                        <div class="col-md-6">
                            <label for="attempt_limit" class="form-label fw-bold">
                                <i class="bi bi-arrow-repeat me-1 text-info"></i>Attempt Limit
                            </label>
                            <input type="number" class="form-control" id="attempt_limit" name="attempt_limit" 
                                   value="3" min="1" max="10" required>
                        </div>
                        <div class="col-md-6">
                            <label for="assessment_order" class="form-label fw-bold">
                                <i class="bi bi-sort-numeric-down me-1 text-primary"></i>Assessment Order
                            </label>
                            <input type="number" class="form-control" id="assessment_order" name="assessment_order" 
                                   placeholder="Enter order number" min="1" max="100" required>
                            <div class="form-text text-muted">Manual order assignment - enter the order number (1 = first assessment)</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Create Assessment
                    </button>
                </div>
            </form>
                        </div>
                        </div>
                    </div>

<!-- Edit Assessment Modal -->
<div class="modal fade" id="editAssessmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Edit Assessment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="module_assessments.php?module_id=<?php echo $module_id; ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_assessment">
                    <input type="hidden" name="assessment_id" id="edit_assessment_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="edit_assessment_title" class="form-label fw-bold">
                            <i class="bi bi-pencil-square me-1 text-primary"></i>Assessment Title
                        </label>
                        <input type="text" class="form-control form-control-lg" id="edit_assessment_title" name="edit_assessment_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label fw-bold">
                            <i class="bi bi-card-text me-1 text-primary"></i>Description
                        </label>
                        <textarea class="form-control" id="edit_description" name="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_time_limit" class="form-label fw-bold">
                                <i class="bi bi-clock me-1 text-info"></i>Time Limit (minutes)
                            </label>
                            <input type="number" class="form-control" id="edit_time_limit" name="edit_time_limit" 
                                   min="5" max="300" step="5">
                            <div class="form-text text-muted">Leave empty for no time limit</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_difficulty" class="form-label fw-bold">
                                <i class="bi bi-bar-chart me-1 text-warning"></i>Difficulty Level
                            </label>
                            <select class="form-select" id="edit_difficulty" name="edit_difficulty" required>
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_num_questions" class="form-label fw-bold">
                                <i class="bi bi-question-circle me-1 text-success"></i>Number of Questions
                            </label>
                            <select class="form-select" id="edit_num_questions" name="edit_num_questions" required>
                                <option value="10">10 Questions</option>
                                <option value="30">30 Questions</option>
                                <option value="50">50 Questions</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_passing_rate" class="form-label fw-bold">
                                <i class="bi bi-trophy me-1 text-primary"></i>Passing Rate (%)
                            </label>
                            <input type="number" class="form-control" id="edit_passing_rate" name="edit_passing_rate" 
                                   min="0" max="100" step="5" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_attempt_limit" class="form-label fw-bold">
                                <i class="bi bi-arrow-repeat me-1 text-info"></i>Attempt Limit
                            </label>
                            <input type="number" class="form-control" id="edit_attempt_limit" name="edit_attempt_limit" 
                                   min="1" max="10" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_assessment_order" class="form-label fw-bold">
                                <i class="bi bi-sort-numeric-down me-1 text-primary"></i>Assessment Order
                            </label>
                            <input type="number" class="form-control" id="edit_assessment_order" name="edit_assessment_order" 
                                   min="1" max="100" required>
                            <div class="form-text text-muted">Manual order assignment - enter the order number (1 = first assessment)</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-circle me-1"></i>Update Assessment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Assessment Form -->
<form id="deleteAssessmentForm" method="post" action="module_assessments.php?module_id=<?php echo $module_id; ?>" style="display: none;">
    <input type="hidden" name="action" value="delete_assessment">
    <input type="hidden" name="assessment_id" id="delete_assessment_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Toggle Status Form -->
<form id="toggleStatusForm" method="post" action="module_assessments.php?module_id=<?php echo $module_id; ?>" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="assessment_id" id="toggle_assessment_id">
    <input type="hidden" name="is_active" id="toggle_is_active">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Assessment Stats Modal -->
<div class="modal fade" id="assessmentStatsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-graph-up me-2"></i>Assessment Statistics
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="text-muted mb-3">Assessment Details</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Title:</strong> <span id="stats_assessment_title"></span></p>
                        <p><strong>Difficulty:</strong> <span id="stats_difficulty"></span></p>
                        <p><strong>Time Limit:</strong> <span id="stats_time_limit"></span></p>
                        <p><strong>Questions:</strong> <span id="stats_questions"></span></p>
                        <p><strong>Passing Rate:</strong> <span id="stats_passing_rate"></span></p>
                        <p><strong>Attempt Limit:</strong> <span id="stats_attempt_limit"></span></p>
                        <p><strong>Status:</strong> <span id="stats_status"></span></p>
                        <p><strong>Created:</strong> <span id="stats_created"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Description:</strong></p>
                        <p id="stats_description" class="text-muted small"></p>
                    </div>
                </div>

                <h6 class="text-muted mb-3 mt-4">Performance Metrics</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <p><strong>Total Attempts:</strong> <span id="total_attempts">0</span></p>
                        <p><strong>Average Score:</strong> <span id="avg_score">0%</span></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Pass Rate:</strong> <span id="pass_rate">0%</span></p>
                        <p><strong>Completion Rate:</strong> <span id="completion_rate">0%</span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
                <button type="button" class="btn btn-info" onclick="exportAssessmentStats()">
                    <i class="bi bi-download me-1"></i>Export Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Question Management Modal -->
<div class="modal fade" id="questionManagementModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-question-circle me-2"></i>Manage Questions - <span id="question_modal_title"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Assessment Questions</h6>
                    <button type="button" class="btn btn-success btn-sm" onclick="showAddQuestionForm()">
                        <i class="bi bi-plus-circle me-1"></i>Add Question
                    </button>
                </div>
                
                <!-- Questions List -->
                <div id="questionsList" class="mb-4">
                    <!-- Questions will be loaded here -->
                </div>
                
                <!-- Bulk Question Creation Form -->
                <div id="questionForm" style="display: none;">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="bi bi-plus-circle me-2"></i>Create Assessment Questions
                                </h6>
                                <span class="badge bg-light text-primary fs-6" id="questionCounter">0 / <span id="maxQuestions">10</span> Questions</span>
                            </div>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-light" id="questionProgress" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <form id="bulkQuestionForm">
                                <input type="hidden" id="assessment_id" name="assessment_id">
                                <input type="hidden" name="action" value="create_bulk_questions">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="p-4">
                                    <div class="row g-4" id="questionSlots">
                                        <!-- Question slots will be generated here -->
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="bg-light p-3 border-top">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Fill out the questions you want to include. Empty questions will be skipped.
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-outline-secondary me-2" onclick="hideQuestionForm()">
                                                <i class="bi bi-x-circle me-1"></i>Cancel
                                            </button>
                                            <button type="button" class="btn btn-outline-primary me-2" onclick="clearAllQuestions()">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Clear All
                                            </button>
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-check-circle me-1"></i>Save Questions
                                            </button>
                                        </div>
                                    </div>
                                </div>
</form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.hover-shadow:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    transition: all 0.3s ease;
}

.card {
    transition: all 0.3s ease;
}

.badge {
    font-size: 0.75rem;
}

.form-control:focus, .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.btn-group .btn {
    border-radius: 0.375rem !important;
}

.btn-group .btn:first-child {
    border-top-left-radius: 0.375rem !important;
    border-bottom-left-radius: 0.375rem !important;
}

.btn-group .btn:last-child {
    border-top-right-radius: 0.375rem !important;
    border-bottom-right-radius: 0.375rem !important;
}

/* Enhanced Stats Modal Styling */
#assessmentStatsModal .modal-content {
    border: none;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    border-radius: 15px;
}

#assessmentStatsModal .modal-header {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    border-radius: 15px 15px 0 0;
    border: none;
}

#assessmentStatsModal .modal-body {
    padding: 2rem;
}

#assessmentStatsModal .modal-footer {
    border-top: 1px solid #e9ecef;
    padding: 1rem 2rem;
}

#assessmentStatsModal h6 {
    color: #495057;
    font-weight: 600;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

#assessmentStatsModal p {
    margin-bottom: 0.75rem;
    line-height: 1.6;
}

#assessmentStatsModal strong {
    color: #495057;
    font-weight: 600;
}

#assessmentStatsModal .text-muted {
    color: #6c757d !important;
}

#assessmentStatsModal .btn-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

#assessmentStatsModal .btn-info:hover {
    background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Assessment card enhancements */
.assessment-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.assessment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.assessment-card .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}

.btn-action {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

/* Status and difficulty badges */
.assessment-status {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.assessment-status.active {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.assessment-status.inactive {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.difficulty-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 15px;
    font-size: 0.875rem;
    font-weight: 500;
}

.difficulty-badge.easy {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.difficulty-badge.medium {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.difficulty-badge.hard {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Performance metrics styling */
#total_attempts, #avg_score, #pass_rate, #completion_rate {
    font-weight: 600;
    color: #495057;
}

#stats_questions, #stats_passing_rate, #stats_time_limit, #stats_attempt_limit {
    font-weight: 700;
    color: #495057;
}

#stats_assessment_title {
    color: #17a2b8;
    font-weight: 600;
}

#stats_description {
    font-style: italic;
    line-height: 1.5;
}
</style>

<script>
function toggleAssessmentStatus(assessmentId, isActive) {
        document.getElementById('toggle_assessment_id').value = assessmentId;
    document.getElementById('toggle_is_active').value = isActive ? 1 : 0;
        document.getElementById('toggleStatusForm').submit();
    }

function editAssessment(assessment) {
    try {
        // Parse the assessment data
        const assessmentData = typeof assessment === 'string' ? JSON.parse(assessment) : assessment;
        
        // Populate the edit modal fields
        document.getElementById('edit_assessment_id').value = assessmentData.id;
        document.getElementById('edit_assessment_title').value = assessmentData.assessment_title || '';
        document.getElementById('edit_description').value = assessmentData.description || '';
        document.getElementById('edit_time_limit').value = assessmentData.time_limit || '';
        document.getElementById('edit_difficulty').value = assessmentData.difficulty || 'medium';
        // Set the number of questions dropdown
        const numQuestionsSelect = document.getElementById('edit_num_questions');
        const numQuestions = assessmentData.num_questions || 10;
        // Find and select the matching option
        for (let option of numQuestionsSelect.options) {
            if (parseInt(option.value) === numQuestions) {
                option.selected = true;
                break;
            }
        }
        document.getElementById('edit_passing_rate').value = assessmentData.passing_rate || 70;
        document.getElementById('edit_attempt_limit').value = assessmentData.attempt_limit || 3;
        document.getElementById('edit_assessment_order').value = assessmentData.assessment_order || 1;
        
        // Show the edit modal
        const editModal = new bootstrap.Modal(document.getElementById('editAssessmentModal'));
        editModal.show();
    } catch (error) {
        console.error('Error in editAssessment:', error);
        alert('Error opening edit modal. Please check console for details.');
    }
}

function viewAssessmentStats(assessmentId) {
    // Find the assessment data
    const assessment = <?php echo json_encode($assessments); ?>.find(a => a.id === assessmentId);
    if (!assessment) {
        alert('Assessment not found!');
        return;
    }
    
    // Show the stats modal
    const statsModal = new bootstrap.Modal(document.getElementById('assessmentStatsModal'));
    
    // Populate the stats modal with assessment data
    document.getElementById('stats_assessment_title').textContent = assessment.assessment_title;
    document.getElementById('stats_difficulty').textContent = assessment.difficulty.charAt(0).toUpperCase() + assessment.difficulty.slice(1);
    document.getElementById('stats_time_limit').textContent = assessment.time_limit ? assessment.time_limit + ' minutes' : 'No limit';
    document.getElementById('stats_questions').textContent = assessment.num_questions;
    document.getElementById('stats_passing_rate').textContent = assessment.passing_rate + '%';
    document.getElementById('stats_attempt_limit').textContent = assessment.attempt_limit;
    document.getElementById('stats_status').textContent = assessment.is_active ? 'Active' : 'Inactive';
    document.getElementById('stats_created').textContent = new Date(assessment.created_at).toLocaleDateString();
    document.getElementById('stats_description').textContent = assessment.description || 'No description provided';
    
    // For now, show placeholder stats (you can implement real data collection later)
    document.getElementById('total_attempts').textContent = '0';
    document.getElementById('avg_score').textContent = '0%';
    document.getElementById('pass_rate').textContent = '0%';
    document.getElementById('completion_rate').textContent = '0%';
    
    // Show the modal
    statsModal.show();
}

function deleteAssessment(assessmentId, assessmentTitle) {
    if (confirm(`Are you sure you want to delete "${assessmentTitle}"?\n\nThis action cannot be undone and will remove all assessment data.`)) {
        document.getElementById('delete_assessment_id').value = assessmentId;
        document.getElementById('deleteAssessmentForm').submit();
    }
}

function exportAssessmentStats() {
    const assessmentTitle = document.getElementById('stats_assessment_title').textContent;
    const difficulty = document.getElementById('stats_difficulty').textContent;
    const timeLimit = document.getElementById('stats_time_limit').textContent;
    const numQuestions = document.getElementById('stats_questions').textContent;
    const passingRate = document.getElementById('stats_passing_rate').textContent;
    const attemptLimit = document.getElementById('stats_attempt_limit').textContent;
    const status = document.getElementById('stats_status').textContent;
    const createdAt = document.getElementById('stats_created').textContent;
    const description = document.getElementById('stats_description').textContent;

    const statsData = {
        'Assessment Title': assessmentTitle,
        'Difficulty': difficulty,
        'Time Limit': timeLimit,
        'Number of Questions': numQuestions,
        'Passing Rate': passingRate,
        'Attempt Limit': attemptLimit,
        'Status': status,
        'Created At': createdAt,
        'Description': description
    };

    const csvContent = Object.entries(statsData).map(([key, value]) => `${key}: ${value}`).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${assessmentTitle.replace(/[^a-zA-Z0-9]/g, '_')}_stats.txt`; // Safely handle filename
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Auto-hide success messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s ease-out';
            successAlert.style.opacity = '0';
            setTimeout(() => {
                if (successAlert.parentNode) {
                    successAlert.remove();
                }
            }, 500);
        }, 5000);
    }
    
    // Add hover effects to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('hover-shadow');
        });
        card.addEventListener('mouseleave', function() {
            this.classList.remove('hover-shadow');
        });
    });
});

// Question Management Functions
let currentAssessmentId = null;
let currentAssessmentNumQuestions = 10;

function manageQuestions(assessmentId, assessmentTitle) {
    currentAssessmentId = assessmentId;
    document.getElementById('question_modal_title').textContent = assessmentTitle;
    document.getElementById('assessment_id').value = assessmentId;
    
    // Get the assessment data to determine num_questions
    const assessmentsData = <?php echo json_encode($assessments); ?>;
    console.log('🔍 All assessments:', assessmentsData);
    console.log('🔍 Looking for assessment ID:', assessmentId);
    
    // Convert to array if it's an object (PHP json_encode with non-sequential keys)
    const assessments = Array.isArray(assessmentsData) ? assessmentsData : Object.values(assessmentsData);
    console.log('🔍 Converted to array:', assessments);
    
    const assessment = assessments.find(a => a.id === assessmentId);
    if (assessment) {
        currentAssessmentNumQuestions = assessment.num_questions || 10;
        console.log('✅ Found assessment:', assessment);
        console.log('✅ Set currentAssessmentNumQuestions to:', currentAssessmentNumQuestions);
    } else {
        currentAssessmentNumQuestions = 10; // Default fallback
        console.log('❌ Assessment not found, using default:', currentAssessmentNumQuestions);
    }
    
    // Load questions for this assessment
    loadQuestions(assessmentId);
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('questionManagementModal'));
    modal.show();
}

function loadQuestions(assessmentId) {
    // Make AJAX request to load questions
    const formData = new FormData();
    formData.append('action', 'get_questions');
    formData.append('assessment_id', assessmentId);
    formData.append('<?php echo CSRF_TOKEN_NAME; ?>', '<?php echo generateCSRFToken(); ?>');
    
    console.log('🔍 Loading questions for assessment:', assessmentId);
    console.log('📤 FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    fetch('module_assessments.php?module_id=<?php echo $module_id; ?>&v=' + Date.now(), {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        // Debug: Log the response before parsing
        console.log('Response status:', response.status);
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        });
    })
    .then(data => {
        if (data.success) {
            displayQuestions(data.questions);
        } else {
            console.error('Error loading questions:', data.message);
            if (data.debug) {
                console.log('Debug info:', data.debug);
            }
            document.getElementById('questionsList').innerHTML = '<div class="alert alert-warning">No questions found for this assessment.</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('questionsList').innerHTML = '<div class="alert alert-danger">Error loading questions: ' + error.message + '</div>';
    });
}

function displayQuestions(questions) {
    const container = document.getElementById('questionsList');
    
    if (questions.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No questions added yet. Click "Add Question" to get started.</div>';
        return;
    }
    
    let html = '<div class="row g-3">';
    questions.forEach((question, index) => {
        html += `
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="card-title">Question ${question.question_order}</h6>
                                <p class="card-text">${question.question_text}</p>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-primary">${question.question_type.replace('_', ' ').toUpperCase()}</span>
                                    <span class="badge bg-secondary">${question.points} point${question.points > 1 ? 's' : ''}</span>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="editQuestion(${question.id})" title="Edit Question">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteQuestion(${question.id})" title="Delete Question">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function showAddQuestionForm() {
    document.getElementById('questionForm').style.display = 'block';
    
    // Update the max questions display
    document.getElementById('maxQuestions').textContent = currentAssessmentNumQuestions;
    
    // Check for existing questions first
    checkExistingQuestions();
}

function hideQuestionForm() {
    document.getElementById('questionForm').style.display = 'none';
}

// Check for existing questions and display them
function checkExistingQuestions() {
    if (!currentAssessmentId) {
        showNotification('Please select an assessment first.', 'error');
        hideQuestionForm();
        return;
    }
    
    // Show loading state
    const container = document.getElementById('questionSlots');
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-hourglass-split me-2"></i>Loading existing questions...</div>';
    
    // Fetch existing questions
    const formData = new FormData();
    formData.append('action', 'get_questions');
    formData.append('assessment_id', currentAssessmentId);
    formData.append('<?php echo CSRF_TOKEN_NAME; ?>', '<?php echo generateCSRFToken(); ?>');
    
    fetch('module_assessments.php?module_id=<?php echo $module_id; ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        try {
            const response = JSON.parse(data);
            if (response.success) {
                const existingQuestions = response.questions || [];
                displayQuestionSlotsWithExisting(existingQuestions);
            } else {
                // No existing questions, show empty slots
                displayQuestionSlotsWithExisting([]);
            }
        } catch (e) {
            console.error('Error parsing response:', e);
            // Show empty slots on error
            displayQuestionSlotsWithExisting([]);
        }
    })
    .catch(error => {
        console.error('Error fetching questions:', error);
        // Show empty slots on error
        displayQuestionSlotsWithExisting([]);
    });
}

// Display question slots with existing questions
function displayQuestionSlotsWithExisting(existingQuestions) {
    const container = document.getElementById('questionSlots');
    let html = '';
    
    // Show existing questions info
    if (existingQuestions.length > 0) {
        html += `
            <div class="col-12 mb-4">
                <div class="alert alert-info">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle me-2"></i>
                        <div>
                            <strong>Existing Questions Found:</strong> This assessment already has ${existingQuestions.length} question(s).
                            <br><small>You can add more questions below. Existing questions will remain unchanged.</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Generate question slots based on assessment's num_questions
    console.log('🔧 Generating question slots. currentAssessmentNumQuestions:', currentAssessmentNumQuestions);
    for (let i = 1; i <= currentAssessmentNumQuestions; i++) {
        const existingQuestion = existingQuestions.find(q => q.question_order === i);
        const isExisting = !!existingQuestion;
        
        html += `
            <div class="col-md-6">
                <div class="card question-slot ${isExisting ? 'border-warning' : ''}" data-question="${i}">
                    <div class="card-header ${isExisting ? 'bg-warning text-dark' : 'bg-light'}">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-question-circle me-2 ${isExisting ? 'text-warning' : 'text-primary'}"></i>
                                Question ${i}
                                ${isExisting ? '<span class="badge bg-warning text-dark ms-2">Existing</span>' : ''}
                            </h6>
                            ${!isExisting ? `
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enable_${i}" onchange="toggleQuestionSlot(${i})">
                                    <label class="form-check-label" for="enable_${i}">Enable</label>
                                </div>
                            ` : `
                                <button class="btn btn-sm btn-outline-warning" onclick="viewExistingQuestion(${i}, '${existingQuestion.id}')">
                                    <i class="bi bi-eye me-1"></i>View
                                </button>
                            `}
                        </div>
                    </div>
                    ${!isExisting ? `
                        <div class="card-body question-content" id="content_${i}" style="display: none;">
                            <!-- Question Text -->
                            <div class="mb-3">
                                <label class="form-label">Question Text</label>
                                <textarea class="form-control" name="questions[${i}][question_text]" rows="2" 
                                          placeholder="Enter your question here..."></textarea>
                            </div>
                            
                            <!-- Question Type and Points -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">Type</label>
                                    <select class="form-select" name="questions[${i}][question_type]" onchange="toggleQuestionTypeOptions(${i})">
                                        <option value="multiple_choice">📝 Multiple Choice</option>
                                        <option value="true_false">✅ True/False</option>
                                        <option value="identification">🔍 Identification</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Points</label>
                                    <input type="number" class="form-control" name="questions[${i}][points]" value="1" min="1" max="100">
                                </div>
                            </div>
                            
                            <!-- Multiple Choice Options -->
                            <div id="mc_options_${i}" class="question-type-options">
                                <label class="form-label">Options</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-text">
                                                <input type="radio" name="questions[${i}][correct_option]" value="0">
                                            </div>
                                            <input type="text" class="form-control" name="questions[${i}][options][]" placeholder="Option 1">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-text">
                                                <input type="radio" name="questions[${i}][correct_option]" value="1">
                                            </div>
                                            <input type="text" class="form-control" name="questions[${i}][options][]" placeholder="Option 2">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-text">
                                                <input type="radio" name="questions[${i}][correct_option]" value="2">
                                            </div>
                                            <input type="text" class="form-control" name="questions[${i}][options][]" placeholder="Option 3">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-text">
                                                <input type="radio" name="questions[${i}][correct_option]" value="3">
                                            </div>
                                            <input type="text" class="form-control" name="questions[${i}][options][]" placeholder="Option 4">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- True/False Options -->
                            <div id="tf_options_${i}" class="question-type-options" style="display: none;">
                                <label class="form-label">Correct Answer</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="questions[${i}][correct_tf]" value="true" checked>
                                            <label class="form-check-label text-success fw-bold">✅ True</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="questions[${i}][correct_tf]" value="false">
                                            <label class="form-check-label text-danger fw-bold">❌ False</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Identification Options -->
                            <div id="id_options_${i}" class="question-type-options" style="display: none;">
                                <label class="form-label">Correct Answer</label>
                                <input type="text" class="form-control" name="questions[${i}][correct_answer]" 
                                       placeholder="Enter the correct answer...">
                            </div>
                        </div>
                    ` : `
                        <div class="card-body">
                            <div class="text-muted">
                                <small>
                                    <strong>Type:</strong> ${existingQuestion.question_type.replace('_', ' ').toUpperCase()}<br>
                                    <strong>Points:</strong> ${existingQuestion.points}<br>
                                    <strong>Created:</strong> ${new Date(existingQuestion.created_at).toLocaleDateString()}
                                </small>
                            </div>
                        </div>
                    `}
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
    updateQuestionCounter();
}

// Toggle question slot visibility
function toggleQuestionSlot(questionNum) {
    const checkbox = document.getElementById(`enable_${questionNum}`);
    const content = document.getElementById(`content_${questionNum}`);
    
    if (checkbox.checked) {
        content.style.display = 'block';
    } else {
        content.style.display = 'none';
        // Clear the form data when disabled
        const form = content.closest('.question-slot');
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            if (input.type === 'checkbox') return; // Don't clear the enable checkbox
            if (input.type === 'radio') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
    }
    
    updateQuestionCounter();
}

// Toggle question type options
function toggleQuestionTypeOptions(questionNum) {
    const questionType = document.querySelector(`select[name="questions[${questionNum}][question_type]"]`).value;
    
    // Hide all options
    document.getElementById(`mc_options_${questionNum}`).style.display = 'none';
    document.getElementById(`tf_options_${questionNum}`).style.display = 'none';
    document.getElementById(`id_options_${questionNum}`).style.display = 'none';
    
    // Show relevant options
    if (questionType === 'multiple_choice') {
        document.getElementById(`mc_options_${questionNum}`).style.display = 'block';
    } else if (questionType === 'true_false') {
        document.getElementById(`tf_options_${questionNum}`).style.display = 'block';
    } else if (questionType === 'identification') {
        document.getElementById(`id_options_${questionNum}`).style.display = 'block';
    }
}

// View existing question details
function viewExistingQuestion(questionNum, questionId) {
    // Make AJAX request to get question details
    const formData = new FormData();
    formData.append('action', 'get_question');
    formData.append('question_id', questionId);
    formData.append('<?php echo CSRF_TOKEN_NAME; ?>', '<?php echo generateCSRFToken(); ?>');
    
    fetch('module_assessments.php?module_id=<?php echo $module_id; ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('View question response status:', response.status);
        return response.text();
    })
    .then(data => {
        console.log('View question raw response:', data);
        try {
            const response = JSON.parse(data);
            if (response.success) {
                const question = response.question;
                showQuestionDetailsModal(question);
            } else {
                showNotification('Error loading question details.', 'error');
            }
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response data:', data);
            showNotification('Error parsing question data.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error loading question details.', 'error');
    });
}

// Show question details in a modal
function showQuestionDetailsModal(question) {
    // Options are already parsed as an array from the backend
    const options = question.options || [];
    let optionsHtml = '';
    
    if (question.question_type === 'multiple_choice') {
        options.forEach((option, index) => {
            const isCorrect = option.is_correct ? 'text-success fw-bold' : '';
            optionsHtml += `
                <div class="form-check">
                    <input class="form-check-input" type="radio" disabled ${option.is_correct ? 'checked' : ''}>
                    <label class="form-check-label ${isCorrect}">
                        ${option.text} ${option.is_correct ? '(Correct)' : ''}
                    </label>
                </div>
            `;
        });
    } else if (question.question_type === 'true_false') {
        options.forEach(option => {
            const isCorrect = option.is_correct ? 'text-success fw-bold' : '';
            optionsHtml += `
                <div class="form-check">
                    <input class="form-check-input" type="radio" disabled ${option.is_correct ? 'checked' : ''}>
                    <label class="form-check-label ${isCorrect}">
                        ${option.text} ${option.is_correct ? '(Correct)' : ''}
                    </label>
                </div>
            `;
        });
    } else if (question.question_type === 'identification') {
        options.forEach(option => {
            optionsHtml += `
                <div class="alert alert-info">
                    <strong>Correct Answer:</strong> ${option.text}
                </div>
            `;
        });
    }
    
    const modalHtml = `
        <div class="modal fade" id="questionDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-eye me-2"></i>Question Details
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Question Text:</label>
                            <div class="p-3 bg-light rounded">${question.question_text}</div>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Type:</label>
                                <div class="p-2 bg-light rounded">${question.question_type.replace('_', ' ').toUpperCase()}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Points:</label>
                                <div class="p-2 bg-light rounded">${question.points}</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Options/Answers:</label>
                            <div class="p-3 bg-light rounded">
                                ${optionsHtml}
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Created:</label>
                                <div class="p-2 bg-light rounded">${new Date(question.created_at).toLocaleString()}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Order:</label>
                                <div class="p-2 bg-light rounded">${question.question_order}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('questionDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('questionDetailsModal'));
    modal.show();
}

// Update question counter and progress
function updateQuestionCounter() {
    const enabledQuestions = document.querySelectorAll('.question-content[style*="block"]').length;
    const existingQuestions = document.querySelectorAll('.question-slot.border-warning').length;
    const totalQuestions = existingQuestions + enabledQuestions;
    
    const counter = document.getElementById('questionCounter');
    const progress = document.getElementById('questionProgress');
    
    if (existingQuestions > 0) {
        counter.textContent = `${totalQuestions} / ${currentAssessmentNumQuestions} Questions (${existingQuestions} existing, ${enabledQuestions} new)`;
    } else {
        counter.textContent = `${enabledQuestions} / ${currentAssessmentNumQuestions} Questions`;
    }
    
    progress.style.width = `${(totalQuestions / currentAssessmentNumQuestions) * 100}%`;
}

// Clear all questions
function clearAllQuestions() {
    if (confirm('Are you sure you want to clear all questions? This action cannot be undone.')) {
        const checkboxes = document.querySelectorAll('.form-check-input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
            toggleQuestionSlot(checkbox.id.replace('enable_', ''));
        });
        updateQuestionCounter();
    }
}

function editQuestion(questionId) {
    // Make AJAX request to get question details
    const formData = new FormData();
    formData.append('action', 'get_question');
    formData.append('question_id', questionId);
    formData.append('<?php echo CSRF_TOKEN_NAME; ?>', '<?php echo generateCSRFToken(); ?>');
    
    fetch('module_assessments.php?module_id=<?php echo $module_id; ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Edit question response status:', response.status);
        return response.text();
    })
    .then(data => {
        console.log('Edit question raw response:', data);
        try {
            const response = JSON.parse(data);
            if (response.success) {
                const question = response.question;
                showEditQuestionModal(question);
            } else {
                showNotification('Error loading question: ' + response.message, 'error');
            }
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response data:', data);
            showNotification('Error loading question details: ' + e.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error loading question details: ' + error.message, 'error');
    });
}

// Show edit question modal
function showEditQuestionModal(question) {
    // Options are already parsed as an array from the backend
    const options = question.options || [];
    let optionsHtml = '';
    
    if (question.question_type === 'multiple_choice') {
        options.forEach((option, index) => {
            const isCorrect = option.is_correct ? 'checked' : '';
            optionsHtml += `
                <div class="input-group mb-2">
                    <div class="input-group-text">
                        <input class="form-check-input mt-0" type="radio" name="edit_correct_option" value="${index}" ${isCorrect}>
                    </div>
                    <input type="text" class="form-control" name="edit_options[]" value="${option.text}" placeholder="Option ${index + 1}">
                </div>
            `;
        });
    } else if (question.question_type === 'true_false') {
        const trueChecked = options[0] && options[0].is_correct ? 'checked' : '';
        const falseChecked = options[1] && options[1].is_correct ? 'checked' : '';
        optionsHtml = `
            <div class="form-check">
                <input class="form-check-input" type="radio" name="edit_correct_tf" value="true" id="edit_tf_true" ${trueChecked}>
                <label class="form-check-label" for="edit_tf_true">True</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="edit_correct_tf" value="false" id="edit_tf_false" ${falseChecked}>
                <label class="form-check-label" for="edit_tf_false">False</label>
            </div>
        `;
    } else if (question.question_type === 'identification') {
        const correctAnswer = options[0] ? options[0].text : '';
        optionsHtml = `
            <input type="text" class="form-control" name="edit_correct_answer" value="${correctAnswer}" placeholder="Enter the correct answer">
        `;
    }
    
    const modalHtml = `
        <div class="modal fade" id="editQuestionModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil me-2"></i>Edit Question
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="editQuestionForm">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="update_question">
                            <input type="hidden" name="question_id" value="${question.id}">
                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="edit_question_text" class="form-label">Question Text</label>
                                <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required>${question.question_text}</textarea>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label for="edit_question_type" class="form-label">Question Type</label>
                                    <select class="form-select" id="edit_question_type" name="question_type" onchange="toggleEditQuestionOptions()">
                                        <option value="multiple_choice" ${question.question_type === 'multiple_choice' ? 'selected' : ''}>📝 Multiple Choice</option>
                                        <option value="true_false" ${question.question_type === 'true_false' ? 'selected' : ''}>✅ True/False</option>
                                        <option value="identification" ${question.question_type === 'identification' ? 'selected' : ''}>🔍 Identification</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_points" class="form-label">Points</label>
                                    <input type="number" class="form-control" id="edit_points" name="points" value="${question.points}" min="1" max="100" required>
                                </div>
                            </div>
                            
                            <div id="editQuestionOptions">
                                <label class="form-label">Options/Answer</label>
                                ${optionsHtml}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-check-circle me-1"></i>Update Question
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('editQuestionModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editQuestionModal'));
    modal.show();
    
    // Handle form submission
    document.getElementById('editQuestionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Updating...';
        submitBtn.disabled = true;
        
        fetch('module_assessments.php?module_id=<?php echo $module_id; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            try {
                const response = JSON.parse(data);
                if (response.success) {
                    showNotification('Question updated successfully!', 'success');
                    loadQuestions(currentAssessmentId);
                    modal.hide();
                } else {
                    showNotification('Error: ' + response.message, 'error');
                }
            } catch (e) {
                showNotification('Error: Invalid response from server.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error updating question.', 'error');
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
}

// Toggle edit question options
function toggleEditQuestionOptions() {
    const questionType = document.getElementById('edit_question_type').value;
    const optionsContainer = document.getElementById('editQuestionOptions');
    
    let optionsHtml = '';
    
    if (questionType === 'multiple_choice') {
        optionsHtml = `
            <label class="form-label">Options</label>
            <div class="input-group mb-2">
                <div class="input-group-text">
                    <input class="form-check-input mt-0" type="radio" name="edit_correct_option" value="0">
                </div>
                <input type="text" class="form-control" name="edit_options[]" placeholder="Option 1">
            </div>
            <div class="input-group mb-2">
                <div class="input-group-text">
                    <input class="form-check-input mt-0" type="radio" name="edit_correct_option" value="1">
                </div>
                <input type="text" class="form-control" name="edit_options[]" placeholder="Option 2">
            </div>
            <div class="input-group mb-2">
                <div class="input-group-text">
                    <input class="form-check-input mt-0" type="radio" name="edit_correct_option" value="2">
                </div>
                <input type="text" class="form-control" name="edit_options[]" placeholder="Option 3">
            </div>
            <div class="input-group mb-2">
                <div class="input-group-text">
                    <input class="form-check-input mt-0" type="radio" name="edit_correct_option" value="3">
                </div>
                <input type="text" class="form-control" name="edit_options[]" placeholder="Option 4">
            </div>
        `;
    } else if (questionType === 'true_false') {
        optionsHtml = `
            <label class="form-label">Correct Answer</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="edit_correct_tf" value="true" id="edit_tf_true" checked>
                <label class="form-check-label" for="edit_tf_true">True</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="edit_correct_tf" value="false" id="edit_tf_false">
                <label class="form-check-label" for="edit_tf_false">False</label>
            </div>
        `;
    } else if (questionType === 'identification') {
        optionsHtml = `
            <label class="form-label">Correct Answer</label>
            <input type="text" class="form-control" name="edit_correct_answer" placeholder="Enter the correct answer">
        `;
    }
    
    optionsContainer.innerHTML = optionsHtml;
}

function deleteQuestion(questionId) {
    if (confirm('Are you sure you want to delete this question? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_question');
        formData.append('question_id', questionId);
        formData.append('<?php echo CSRF_TOKEN_NAME; ?>', '<?php echo generateCSRFToken(); ?>');
        
        fetch('module_assessments.php?module_id=<?php echo $module_id; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Reload questions
            loadQuestions(currentAssessmentId);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting question.');
        });
    }
}

function toggleQuestionOptions() {
    const questionType = document.getElementById('question_type').value;
    
    // Hide all option sections
    document.getElementById('multipleChoiceOptions').style.display = 'none';
    document.getElementById('trueFalseOptions').style.display = 'none';
    document.getElementById('identificationOptions').style.display = 'none';
    
    // Show relevant section
    if (questionType === 'multiple_choice') {
        document.getElementById('multipleChoiceOptions').style.display = 'block';
    } else if (questionType === 'true_false') {
        document.getElementById('trueFalseOptions').style.display = 'block';
    } else if (questionType === 'identification') {
        document.getElementById('identificationOptions').style.display = 'block';
    }
}

// Handle bulk question form submission
document.getElementById('bulkQuestionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Collect enabled questions data
    const questionsData = {};
    const enabledQuestions = document.querySelectorAll('.question-content[style*="block"]');
    const existingQuestions = document.querySelectorAll('.question-slot.border-warning');
    
    if (enabledQuestions.length === 0 && existingQuestions.length === 0) {
        showNotification('Please enable at least one question before saving.', 'error');
        return;
    }
    
    // Check if there are any new questions to add
    if (enabledQuestions.length === 0) {
        showNotification('No new questions to add. All questions are already existing.', 'info');
        return;
    }
    
    let hasValidQuestions = false;
    
    enabledQuestions.forEach(content => {
        const questionNum = content.id.replace('content_', '');
        const questionText = content.querySelector(`textarea[name="questions[${questionNum}][question_text]"]`).value.trim();
        
        if (questionText) {
            hasValidQuestions = true;
            const questionType = content.querySelector(`select[name="questions[${questionNum}][question_type]"]`).value;
            const points = content.querySelector(`input[name="questions[${questionNum}][points]"]`).value;
            
            questionsData[questionNum] = {
                question_text: questionText,
                question_type: questionType,
                points: points
            };
            
            // Add type-specific data
            if (questionType === 'multiple_choice') {
                const options = [];
                const optionInputs = content.querySelectorAll(`input[name="questions[${questionNum}][options][]"]`);
                const correctOption = content.querySelector(`input[name="questions[${questionNum}][correct_option]"]:checked`);
                
                optionInputs.forEach((input, index) => {
                    if (input.value.trim()) {
                        options.push(input.value.trim());
                    }
                });
                
                questionsData[questionNum].options = options;
                questionsData[questionNum].correct_option = correctOption ? parseInt(correctOption.value) : 0;
                
            } else if (questionType === 'true_false') {
                const correctTf = content.querySelector(`input[name="questions[${questionNum}][correct_tf]"]:checked`);
                questionsData[questionNum].correct_tf = correctTf ? correctTf.value : 'true';
                
            } else if (questionType === 'identification') {
                const correctAnswer = content.querySelector(`input[name="questions[${questionNum}][correct_answer]"]`).value.trim();
                questionsData[questionNum].correct_answer = correctAnswer;
            }
        }
    });
    
    if (!hasValidQuestions) {
        showNotification('Please fill in at least one question before saving.', 'error');
        return;
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('assessment_id', document.getElementById('assessment_id').value);
    formData.append('action', 'create_bulk_questions');
    formData.append('<?php echo CSRF_TOKEN_NAME; ?>', '<?php echo generateCSRFToken(); ?>');
    
    // Add questions data
    Object.keys(questionsData).forEach(key => {
        Object.keys(questionsData[key]).forEach(subKey => {
            if (Array.isArray(questionsData[key][subKey])) {
                questionsData[key][subKey].forEach((item, index) => {
                    formData.append(`questions[${key}][${subKey}][]`, item);
                });
            } else {
                formData.append(`questions[${key}][${subKey}]`, questionsData[key][subKey]);
            }
        });
    });
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving...';
    submitBtn.disabled = true;
    
    fetch('module_assessments.php?module_id=<?php echo $module_id; ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        try {
            const response = JSON.parse(data);
            if (response.success) {
                const existingCount = document.querySelectorAll('.question-slot.border-warning').length;
                const totalCount = existingCount + response.created_count;
                showNotification(`Successfully created ${response.created_count} new question(s)! Total: ${totalCount} questions.`, 'success');
                loadQuestions(currentAssessmentId);
                hideQuestionForm();
            } else {
                showNotification('Error: ' + response.message, 'error');
            }
        } catch (e) {
            showNotification('Error: Invalid response from server.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving questions.', 'error');
    })
    .finally(() => {
        // Restore button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification-toast alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    
    const icon = type === 'error' ? 'bi-exclamation-triangle' : type === 'success' ? 'bi-check-circle' : 'bi-info-circle';
    const iconColor = type === 'error' ? 'text-danger' : type === 'success' ? 'text-success' : 'text-info';
    
    notification.innerHTML = `
        <i class="bi ${icon} ${iconColor} me-2"></i>
        <strong>${type === 'error' ? 'Error' : type === 'success' ? 'Success' : 'Info'}:</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Assessment order is now purely manual - no auto-population

</script>

<?php require_once '../includes/footer.php'; ?> 