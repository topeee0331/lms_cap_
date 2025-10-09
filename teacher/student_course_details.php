<?php
$page_title = 'Student Course Details';
require_once '../config/config.php';
requireRole('teacher');

$message = '';
$message_type = '';

// Get parameters
$student_id = (int)($_GET['student_id'] ?? 0);
$course_id = (int)($_GET['course_id'] ?? 0);
$academic_period_id = (int)($_GET['academic_period_id'] ?? 0);
$select_course = isset($_GET['select_course']) && $_GET['select_course'] == '1';

// Handle redirects BEFORE including header.php to avoid "headers already sent" error
if (!$student_id || !$academic_period_id) {
    $_SESSION['error'] = 'Invalid parameters. Student ID and Academic Period ID are required.';
    header('Location: students.php');
    exit();
}

try {
    // Get student basic info
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.identifier,
               s.section_name, s.year_level
        FROM users u
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', ?) IS NOT NULL
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id, $student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        $_SESSION['error'] = 'Student not found.';
        header('Location: students.php');
        exit();
    }
    
    // If no course_id provided, show course selection
    if (!$course_id || $select_course) {
        // Get all courses for this student with the teacher
        $stmt = $db->prepare("
            SELECT c.id, c.course_name, c.course_code, c.description,
                   e.enrolled_at, e.status, e.progress_percentage, e.last_accessed,
                   e.module_progress, e.video_progress
            FROM courses c
            INNER JOIN course_enrollments e ON e.course_id = c.id AND e.student_id = ?
            WHERE c.teacher_id = ? AND c.academic_period_id = ?
            ORDER BY c.course_name
        ");
        $stmt->execute([$student_id, $_SESSION['user_id'], $academic_period_id]);
        $available_courses = $stmt->fetchAll();
        
        if (empty($available_courses)) {
            $_SESSION['error'] = 'No courses found for this student.';
            header('Location: students.php');
            exit();
        }
        
        // If only one course, redirect to it
        if (count($available_courses) == 1) {
            $course_id = $available_courses[0]['id'];
            header("Location: student_course_details.php?student_id=$student_id&course_id=$course_id&academic_period_id=$academic_period_id");
            exit();
        }
        
        // Show course selection page
        $show_course_selection = true;
        $course = null;
        $overall_progress = 0;
        $assessment_stats = null;
        $recent_attempts = [];
        $module_progress = [];
        $video_progress = [];
        $completed_modules = 0;
        $total_modules = 0;
        $watched_videos = 0;
        $total_videos = 0;
    } else {
        // Get course details and verify teacher access
        $stmt = $db->prepare("
            SELECT c.id, c.course_name, c.course_code, c.description,
                   e.enrolled_at, e.status, e.progress_percentage, e.last_accessed,
                   e.module_progress, e.video_progress
            FROM courses c
            INNER JOIN course_enrollments e ON e.course_id = c.id AND e.student_id = ?
            WHERE c.id = ? AND c.teacher_id = ? AND c.academic_period_id = ?
        ");
        $stmt->execute([$student_id, $course_id, $_SESSION['user_id'], $academic_period_id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            $_SESSION['error'] = 'Course not found or you do not have access to this course.';
            header('Location: students.php');
            exit();
        }
        
        $show_course_selection = false;
    }
    
    // Only get detailed data if a course is selected
    if (!$show_course_selection) {
        // Get assessment statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT a.id) as total_assessments,
                COUNT(DISTINCT aa.assessment_id) as attempted_assessments,
                COUNT(DISTINCT CASE WHEN aa.score >= a.passing_rate THEN aa.assessment_id END) as passed_assessments,
                ROUND(AVG(aa.score), 2) as avg_score,
                MAX(aa.score) as best_score,
                MIN(aa.score) as worst_score,
                COUNT(aa.id) as total_attempts,
                ROUND(AVG(aa.time_taken), 0) as avg_time_taken
            FROM assessments a
            LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id = ?
            WHERE a.course_id = ?
        ");
        $stmt->execute([$student_id, $course_id]);
        $assessment_stats = $stmt->fetch();
        
        // Get recent assessment attempts with detailed answers
        $stmt = $db->prepare("
            SELECT aa.*, a.assessment_title, a.passing_rate, a.difficulty, a.time_limit
            FROM assessment_attempts aa
            JOIN assessments a ON aa.assessment_id = a.id
            WHERE aa.student_id = ? AND a.course_id = ?
            ORDER BY aa.completed_at DESC
            LIMIT 10
        ");
        $stmt->execute([$student_id, $course_id]);
        $recent_attempts = $stmt->fetchAll();
        
        // Process each attempt to decode answers and calculate detailed stats
        foreach ($recent_attempts as &$attempt) {
            if ($attempt['answers']) {
                $attempt['decoded_answers'] = json_decode($attempt['answers'], true) ?: [];
                $attempt['total_questions'] = count($attempt['decoded_answers']);
                $attempt['correct_answers'] = count(array_filter($attempt['decoded_answers'], function($answer) {
                    return $answer['is_correct'] ?? false;
                }));
                $attempt['incorrect_answers'] = $attempt['total_questions'] - $attempt['correct_answers'];
                $attempt['accuracy'] = $attempt['total_questions'] > 0 ? round(($attempt['correct_answers'] / $attempt['total_questions']) * 100, 1) : 0;
            } else {
                $attempt['decoded_answers'] = [];
                $attempt['total_questions'] = 0;
                $attempt['correct_answers'] = 0;
                $attempt['incorrect_answers'] = 0;
                $attempt['accuracy'] = 0;
            }
        }
        
        // Get module progress
        $module_progress = [];
        if ($course['module_progress']) {
            $module_progress = json_decode($course['module_progress'], true) ?: [];
        }
        
        // Get video progress
        $video_progress = [];
        if ($course['video_progress']) {
            $video_progress = json_decode($course['video_progress'], true) ?: [];
        }
        
        // Calculate overall progress
        $total_modules = count($module_progress);
        $completed_modules = count(array_filter($module_progress, function($module) {
            return isset($module['is_completed']) && $module['is_completed'] == 1;
        }));
        
        $total_videos = count($video_progress);
        $watched_videos = count(array_filter($video_progress, function($video) {
            return isset($video['is_watched']) && $video['is_watched'] == 1;
        }));
        
        // Use database progress_percentage as primary, fallback to calculated progress
        $overall_progress = $course['progress_percentage'] ?? 0;
        if ($overall_progress == 0 && $total_modules > 0) {
            $overall_progress = round(($completed_modules / $total_modules) * 100);
        }
    }
    
} catch (Exception $e) {
    error_log("Error in student_course_details.php: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while loading student course details.';
    header('Location: students.php');
    exit();
}

// Include header.php AFTER all redirects are handled
require_once '../includes/header.php';

// Debug information (remove in production)
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Header Section -->
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-title-section">
                        <div class="d-flex align-items-center mb-2">
                            <div class="page-icon me-3">
                                <i class="bi bi-person-check"></i>
                            </div>
                            <div>
                                <h1 class="page-title mb-0">Student Course Progress</h1>
                                <p class="page-subtitle mb-0">Real-time progress tracking and detailed analytics</p>
                            </div>
                        </div>
                        <div class="page-stats">
                            <span class="stat-item">
                                <i class="bi bi-person text-primary me-1"></i>
                                <span class="fw-semibold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                            </span>
                            <span class="stat-item">
                                <i class="bi bi-book text-success me-1"></i>
                                <span class="fw-semibold"><?php echo $show_course_selection ? 'Select Course' : htmlspecialchars($course['course_name']); ?></span>
                            </span>
                            <span class="stat-item">
                                <i class="bi bi-graph-up text-info me-1"></i>
                                <span class="fw-semibold"><?php echo $show_course_selection ? 'N/A' : ($overall_progress ?? 0); ?>% Complete</span>
                            </span>
                        </div>
                    </div>
                    <div class="page-actions">
                        <a href="students.php" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-arrow-left me-2"></i>Back to Students
                        </a>
                        <button class="btn btn-primary btn-lg" onclick="refreshData()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Student Info Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card student-info-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'large'); ?>" 
                                         class="profile-picture-large" alt="Student">
                                </div>
                                <div class="col-md-6">
                                    <h4 class="mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($student['email']); ?></p>
                                    <?php if ($student['identifier']): ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($student['identifier']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($student['section_name']): ?>
                                        <span class="badge bg-info ms-2"><?php echo htmlspecialchars($student['section_name'] . ' - Year ' . $student['year_level']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="enrollment-info">
                                        <?php if (!$show_course_selection && $course): ?>
                                            <small class="text-muted">Enrolled</small>
                                            <div class="fw-bold"><?php echo $course['enrolled_at'] ? date('M j, Y', strtotime($course['enrolled_at'])) : 'Not enrolled'; ?></div>
                                            <small class="text-muted">Last Active</small>
                                            <div class="fw-bold"><?php echo $course['last_accessed'] ? date('M j, Y g:i A', strtotime($course['last_accessed'])) : 'Never'; ?></div>
                                        <?php else: ?>
                                            <small class="text-muted">Select a course to view progress</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($show_course_selection): ?>
            <!-- Course Selection -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card course-selection-main-card">
                        <div class="card-header course-selection-header">
                            <h5 class="mb-0">
                                <i class="bi bi-book me-2"></i>Select Course to View Progress
                                <span class="badge bg-light text-dark ms-2"><?php echo count($available_courses); ?> Available</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($available_courses as $course_option): ?>
                                    <?php
                                    // Calculate actual progress by fetching real data from database
                                    $actual_progress = 0;
                                    $progress_details = [];
                                    
                                    try {
                                        // Get course modules from the courses table
                                        $stmt_modules = $db->prepare("SELECT modules FROM courses WHERE id = ?");
                                        $stmt_modules->execute([$course_option['id']]);
                                        $course_data = $stmt_modules->fetch();
                                        
                                        if ($course_data && $course_data['modules']) {
                                            $modules_data = json_decode($course_data['modules'], true);
                                            if (is_array($modules_data)) {
                                                $total_modules = count($modules_data);
                                                $completed_modules = 0;
                                                $total_videos = 0;
                                                $watched_videos = 0;
                                                $total_assessments = 0;
                                                $completed_assessments = 0;
                                                
                                                foreach ($modules_data as $module) {
                                                    // Count videos in this module
                                                    if (isset($module['videos']) && is_array($module['videos'])) {
                                                        $total_videos += count($module['videos']);
                                                    }
                                                    
                                                    // Count assessments in this module
                                                    if (isset($module['assessments']) && is_array($module['assessments'])) {
                                                        $total_assessments += count($module['assessments']);
                                                    }
                                                    
                                                    // Check if module is completed
                                                    $module_id = $module['id'] ?? '';
                                                    if ($module_id) {
                                                        // Check module completion in student_progress table
                                                        $stmt_progress = $db->prepare("
                                                            SELECT is_completed, progress_percentage 
                                                            FROM student_progress 
                                                            WHERE student_id = ? AND course_id = ? AND module_id = ?
                                                        ");
                                                        $stmt_progress->execute([$student_id, $course_option['id'], $module_id]);
                                                        $module_progress = $stmt_progress->fetch();
                                                        
                                                        if ($module_progress && $module_progress['is_completed'] == 1) {
                                                            $completed_modules++;
                                                        }
                                                    }
                                                }
                                                
                                                // Get video watch progress
                                                $stmt_videos = $db->prepare("
                                                    SELECT COUNT(*) as watched_count
                                                    FROM video_views 
                                                    WHERE student_id = ? AND course_id = ? AND watch_percentage >= 80
                                                ");
                                                $stmt_videos->execute([$student_id, $course_option['id']]);
                                                $video_result = $stmt_videos->fetch();
                                                $watched_videos = $video_result['watched_count'] ?? 0;
                                                
                                                // Get assessment completion
                                                $stmt_assessments = $db->prepare("
                                                    SELECT COUNT(DISTINCT assessment_id) as completed_count
                                                    FROM assessment_attempts 
                                                    WHERE student_id = ? AND assessment_id IN (
                                                        SELECT a.id FROM assessments a 
                                                        WHERE a.course_id = ?
                                                    ) AND status = 'completed'
                                                ");
                                                $stmt_assessments->execute([$student_id, $course_option['id']]);
                                                $assessment_result = $stmt_assessments->fetch();
                                                $completed_assessments = $assessment_result['completed_count'] ?? 0;
                                                
                                                // Calculate overall progress (weighted average)
                                                $module_progress_percent = $total_modules > 0 ? ($completed_modules / $total_modules) * 100 : 0;
                                                $video_progress_percent = $total_videos > 0 ? ($watched_videos / $total_videos) * 100 : 0;
                                                $assessment_progress_percent = $total_assessments > 0 ? ($completed_assessments / $total_assessments) * 100 : 0;
                                                
                                                // Weighted calculation: 40% modules, 40% videos, 20% assessments
                                                $actual_progress = round(
                                                    ($module_progress_percent * 0.4) + 
                                                    ($video_progress_percent * 0.4) + 
                                                    ($assessment_progress_percent * 0.2)
                                                );
                                                
                                                $progress_details = [
                                                    'modules' => ['completed' => $completed_modules, 'total' => $total_modules, 'percent' => $module_progress_percent],
                                                    'videos' => ['watched' => $watched_videos, 'total' => $total_videos, 'percent' => $video_progress_percent],
                                                    'assessments' => ['completed' => $completed_assessments, 'total' => $total_assessments, 'percent' => $assessment_progress_percent]
                                                ];
                                            }
                                        }
                                        
                                        // Fallback to stored progress if calculation fails
                                        if ($actual_progress == 0) {
                                            $actual_progress = $course_option['progress_percentage'] ?? 0;
                                        }
                                        
                                    } catch (Exception $e) {
                                        // Fallback to stored progress on error
                                        $actual_progress = $course_option['progress_percentage'] ?? 0;
                                        error_log("Progress calculation error: " . $e->getMessage());
                                    }
                                    
                                    // Determine progress status
                                    $progress_status = 'not-started';
                                    $progress_icon = 'bi-circle';
                                    if ($actual_progress > 0 && $actual_progress < 25) {
                                        $progress_status = 'just-started';
                                        $progress_icon = 'bi-play-circle';
                                    } elseif ($actual_progress >= 25 && $actual_progress < 50) {
                                        $progress_status = 'in-progress';
                                        $progress_icon = 'bi-clock';
                                    } elseif ($actual_progress >= 50 && $actual_progress < 75) {
                                        $progress_status = 'halfway';
                                        $progress_icon = 'bi-arrow-right-circle';
                                    } elseif ($actual_progress >= 75 && $actual_progress < 100) {
                                        $progress_status = 'almost-done';
                                        $progress_icon = 'bi-check-circle';
                                    } elseif ($actual_progress >= 100) {
                                        $progress_status = 'completed';
                                        $progress_icon = 'bi-check-circle-fill';
                                    }
                                    ?>
                                    <div class="col-lg-6 col-xl-4 mb-4">
                                        <div class="course-selection-card" onclick="selectCourse(<?php echo $course_option['id']; ?>)">
                                            <div class="card h-100 course-card">
                                                <div class="card-header course-card-header">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="course-title-section">
                                                            <h6 class="course-title mb-1"><?php echo htmlspecialchars($course_option['course_name']); ?></h6>
                                                            <p class="course-code mb-0"><?php echo htmlspecialchars($course_option['course_code']); ?></p>
                                                        </div>
                                                        <div class="course-status">
                                                            <i class="bi <?php echo $progress_icon; ?> status-icon <?php echo $progress_status; ?>"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <?php if ($course_option['description']): ?>
                                                        <p class="course-description"><?php echo htmlspecialchars(substr($course_option['description'], 0, 120)) . '...'; ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Progress Section -->
                                                    <div class="progress-section">
                                                        <div class="progress-header">
                                                            <span class="progress-label">Course Progress</span>
                                                            <span class="progress-percentage"><?php echo $actual_progress; ?>%</span>
                                                        </div>
                                                        <div class="progress progress-custom">
                                                            <div class="progress-bar progress-bar-custom <?php echo $progress_status; ?>" 
                                                                 style="width: <?php echo $actual_progress; ?>%"
                                                                 role="progressbar" 
                                                                 aria-valuenow="<?php echo $actual_progress; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                        <div class="progress-status-text">
                                                            <?php
                                                            switch($progress_status) {
                                                                case 'not-started': echo 'Not Started'; break;
                                                                case 'just-started': echo 'Just Started'; break;
                                                                case 'in-progress': echo 'In Progress'; break;
                                                                case 'halfway': echo 'Halfway Done'; break;
                                                                case 'almost-done': echo 'Almost Complete'; break;
                                                                case 'completed': echo 'Completed'; break;
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Course Meta Information -->
                                                    <div class="course-meta">
                                                        <div class="meta-item">
                                                            <i class="bi bi-calendar-check text-primary"></i>
                                                            <span>Enrolled: <?php echo $course_option['enrolled_at'] ? date('M j, Y', strtotime($course_option['enrolled_at'])) : 'Not enrolled'; ?></span>
                                                        </div>
                                                        <?php if ($course_option['last_accessed']): ?>
                                                            <div class="meta-item">
                                                                <i class="bi bi-clock text-info"></i>
                                                                <span>Last Active: <?php echo date('M j, Y', strtotime($course_option['last_accessed'])); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="meta-item">
                                                            <i class="bi bi-graph-up text-success"></i>
                                                            <span>Status: <?php echo ucfirst($course_option['status'] ?? 'Active'); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-footer course-card-footer">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="view-details-text">Click to view detailed progress</span>
                                                        <i class="bi bi-arrow-right-circle view-details-icon"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>

            <!-- Progress Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card progress-card">
                        <div class="card-body text-center">
                            <div class="progress-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <h3 class="progress-value" id="overall-progress-value"><?php echo $overall_progress ?? 0; ?>%</h3>
                            <p class="progress-label">Overall Progress</p>
                            
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card progress-card">
                        <div class="card-body text-center">
                            <div class="progress-icon">
                                <i class="bi bi-collection"></i>
                            </div>
                            <h3 class="progress-value"><span id="modules-completed"><?php echo $completed_modules ?? 0; ?></span>/<span id="modules-total"><?php echo $total_modules ?? 0; ?></span></h3>
                            <p class="progress-label">Modules Completed</p>
                            
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card progress-card">
                        <div class="card-body text-center">
                            <div class="progress-icon">
                                <i class="bi bi-play-circle"></i>
                            </div>
                            <h3 class="progress-value"><span id="videos-watched"><?php echo $watched_videos ?? 0; ?></span>/<span id="videos-total"><?php echo $total_videos ?? 0; ?></span></h3>
                            <p class="progress-label">Videos Watched</p>
                            
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card progress-card">
                        <div class="card-body text-center">
                            <div class="progress-icon">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <h3 class="progress-value"><span id="assessments-passed"><?php echo $assessment_stats['passed_assessments'] ?? 0; ?></span>/<span id="assessments-total"><?php echo $assessment_stats['total_assessments'] ?? 0; ?></span></h3>
                            <p class="progress-label">Assessments Passed</p>
                            
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assessment Statistics -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-clipboard-data me-2"></i>Real-time Assessment Statistics
                                <span class="badge bg-success ms-2">Live Data</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $assessment_stats['avg_score'] ?? 0; ?>%</div>
                                        <div class="stat-label">Average Score</div>
                                        <div class="stat-trend">
                                            <?php 
                                            $avg_score = $assessment_stats['avg_score'] ?? 0;
                                            if ($avg_score >= 80) echo '<i class="bi bi-arrow-up text-success"></i> Excellent';
                                            elseif ($avg_score >= 70) echo '<i class="bi bi-arrow-up text-warning"></i> Good';
                                            elseif ($avg_score >= 60) echo '<i class="bi bi-arrow-down text-warning"></i> Needs Improvement';
                                            else echo '<i class="bi bi-arrow-down text-danger"></i> Requires Attention';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $assessment_stats['best_score'] ?? 0; ?>%</div>
                                        <div class="stat-label">Best Score</div>
                                        <div class="stat-trend">
                                            <i class="bi bi-trophy text-warning"></i> Personal Best
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $assessment_stats['passed_assessments'] ?? 0; ?>/<?php echo $assessment_stats['total_assessments'] ?? 0; ?></div>
                                        <div class="stat-label">Passed/Total</div>
                                        <div class="stat-trend">
                                            <?php 
                                            $pass_rate = $assessment_stats['total_assessments'] > 0 ? round((($assessment_stats['passed_assessments'] ?? 0) / $assessment_stats['total_assessments']) * 100) : 0;
                                            echo $pass_rate . '% Pass Rate';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $assessment_stats['total_attempts'] ?? 0; ?></div>
                                        <div class="stat-label">Total Attempts</div>
                                        <div class="stat-trend">
                                            <i class="bi bi-arrow-repeat text-info"></i> Attempts Made
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo ($assessment_stats['avg_time_taken'] ?? 0) ? gmdate('H:i:s', $assessment_stats['avg_time_taken']) : '0:00:00'; ?></div>
                                        <div class="stat-label">Avg. Time</div>
                                        <div class="stat-trend">
                                            <i class="bi bi-clock text-primary"></i> Per Assessment
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $assessment_stats['worst_score'] ?? 0; ?>%</div>
                                        <div class="stat-label">Lowest Score</div>
                                        <div class="stat-trend">
                                            <i class="bi bi-exclamation-triangle text-danger"></i> Needs Review
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Assessment Attempts -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>Detailed Assessment Attempts
                                <span class="badge bg-info ms-2">Question-by-Question Analysis</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_attempts) || !is_array($recent_attempts)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                                    <p class="text-muted mt-2">No assessment attempts yet</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                    <?php foreach ($recent_attempts as $index => $attempt): ?>
                                    <div class="assessment-attempt-card mb-4">
                                        <div class="attempt-header">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($attempt['assessment_title']); ?></h6>
                                                    <div class="attempt-meta">
                                                        <span class="badge bg-<?php echo $attempt['difficulty'] == 'easy' ? 'success' : ($attempt['difficulty'] == 'medium' ? 'warning' : 'danger'); ?>">
                                                            <?php echo ucfirst($attempt['difficulty']); ?>
                                                        </span>
                                                        <span class="text-muted ms-2">Time Limit: <?php echo $attempt['time_limit']; ?> minutes</span>
                                                        <span class="text-muted ms-2">Passing Rate: <?php echo $attempt['passing_rate']; ?>%</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 text-end">
                                                    <div class="attempt-score">
                                                        <span class="score-badge <?php echo $attempt['score'] >= $attempt['passing_rate'] ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo $attempt['score']; ?>%
                                                        </span>
                                                        <div class="score-details">
                                                            <small class="text-muted">
                                                                <?php echo $attempt['correct_answers']; ?>/<?php echo $attempt['total_questions']; ?> correct
                                                                (<?php echo $attempt['accuracy']; ?>% accuracy)
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="attempt-details">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="detail-item">
                                                        <i class="bi bi-check-circle text-success"></i>
                                                        <span>Correct: <?php echo $attempt['correct_answers']; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="detail-item">
                                                        <i class="bi bi-x-circle text-danger"></i>
                                                        <span>Incorrect: <?php echo $attempt['incorrect_answers']; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="detail-item">
                                                        <i class="bi bi-clock text-primary"></i>
                                                        <span>Time: <?php echo $attempt['time_taken'] ? gmdate('H:i:s', $attempt['time_taken']) : 'N/A'; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="detail-item">
                                                        <i class="bi bi-calendar text-info"></i>
                                                        <span><?php echo date('M j, Y g:i A', strtotime($attempt['completed_at'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($attempt['decoded_answers'])): ?>
                                        <div class="attempt-questions mt-3">
                                            <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#questions-<?php echo $index; ?>" aria-expanded="false">
                                                <i class="bi bi-eye me-1"></i>View Question Details
                                            </button>
                                            <div class="collapse mt-3" id="questions-<?php echo $index; ?>">
                                                <div class="questions-list">
                                                    <?php foreach ($attempt['decoded_answers'] as $qIndex => $question): ?>
                                                        <div class="question-item <?php echo $question['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                                            <div class="question-header">
                                                                <div class="question-number">Q<?php echo $qIndex + 1; ?></div>
                                                                <div class="question-status">
                                                                    <?php if ($question['is_correct']): ?>
                                                                        <i class="bi bi-check-circle-fill text-success"></i>
                                                                    <?php else: ?>
                                                                        <i class="bi bi-x-circle-fill text-danger"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="question-points">
                                                                    <span class="badge bg-<?php echo $question['is_correct'] ? 'success' : 'danger'; ?>">
                                                                        <?php echo $question['points']; ?> pts
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="question-content">
                                                                <div class="question-text">
                                                                    <strong><?php echo htmlspecialchars($question['question_text']); ?></strong>
                                                                </div>
                                                                <div class="question-type">
                                                                    <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                                                </div>
                                                                <div class="student-answer">
                                                                    <strong>Student Answer:</strong> 
                                                                    <span class="answer-text <?php echo $question['is_correct'] ? 'text-success' : 'text-danger'; ?>">
                                                                        <?php echo !empty($question['student_answer']) ? htmlspecialchars($question['student_answer']) : '<em>No answer provided</em>'; ?>
                                                                    </span>
                                                                </div>
                                                                <?php if (!$question['is_correct']): ?>
                                                                    <div class="answer-analysis">
                                                                        <small class="text-muted">
                                                                            <i class="bi bi-info-circle me-1"></i>
                                                                            This answer was marked incorrect. Review the question and consider the correct approach.
                                                                        </small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            
            <?php endif; ?>
            
            <?php if ($debug_mode): ?>
            <!-- Debug Information -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Debug Information</h5>
                        </div>
                        <div class="card-body">
                            <h6>Student Data:</h6>
                            <pre><?php print_r($student); ?></pre>
                            
                            <h6>Course Data:</h6>
                            <pre><?php print_r($course); ?></pre>
                            
                            <h6>Assessment Stats:</h6>
                            <pre><?php print_r($assessment_stats); ?></pre>
                            
                            <h6>Module Progress:</h6>
                            <pre><?php print_r($module_progress); ?></pre>
                            
                            <h6>Video Progress:</h6>
                            <pre><?php print_r($video_progress); ?></pre>
                            
                            <h6>Available Courses:</h6>
                            <pre><?php print_r($available_courses ?? []); ?></pre>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Page Header Styles */
.page-header {
    background: #2E5E4E;
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(46, 94, 78, 0.3);
}

.page-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    backdrop-filter: blur(10px);
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.page-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.page-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    opacity: 0.9;
}

.page-actions .btn {
    border-radius: 12px;
    padding: 12px 24px;
    font-weight: 600;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    transition: all 0.3s ease;
}

.page-actions .btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

/* Student Info Card */
.student-info-card {
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}

.profile-picture-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #2E5E4E;
}

.enrollment-info {
    text-align: right;
}

/* Progress Cards */
.progress-card {
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.progress-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.progress-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #2E5E4E, #7DCB80);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 24px;
    color: white;
}

.progress-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2E5E4E;
    margin-bottom: 0.5rem;
}

.progress-label {
    color: #6c757d;
    font-weight: 600;
    margin-bottom: 1rem;
}

/* Statistics */
.stat-item {
    text-align: center;
    padding: 1rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #2E5E4E;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #6c757d;
    font-weight: 600;
}

/* Module Progress */
.module-progress-item {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

/* Course Selection */
.course-selection-main-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(46, 94, 78, 0.1);
}

.course-selection-header {
    background: #2E5E4E;
    color: white;
    border: none;
    border-radius: 16px 16px 0 0;
    padding: 1.5rem;
}

.course-selection-card {
    cursor: pointer;
    transition: all 0.3s ease;
    height: 100%;
}

.course-selection-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(46, 94, 78, 0.2);
}

.course-card {
    border: 2px solid #e9ecef;
    border-radius: 16px;
    transition: all 0.3s ease;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.course-selection-card:hover .course-card {
    border-color: #2E5E4E;
    box-shadow: 0 8px 25px rgba(46, 94, 78, 0.15);
}

.course-card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    padding: 1.25rem;
}

.course-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2E5E4E;
    margin: 0;
}

.course-code {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
}

.course-status {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f8f9fa;
}

.status-icon {
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.status-icon.not-started {
    color: #6c757d;
}

.status-icon.just-started {
    color: #17a2b8;
}

.status-icon.in-progress {
    color: #ffc107;
}

.status-icon.halfway {
    color: #fd7e14;
}

.status-icon.almost-done {
    color: #28a745;
}

.status-icon.completed {
    color: #2E5E4E;
}

.course-description {
    font-size: 0.9rem;
    color: #6c757d;
    line-height: 1.5;
    margin-bottom: 1.5rem;
}

/* Progress Section */
.progress-section {
    margin-bottom: 1.5rem;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.progress-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #495057;
}

.progress-percentage {
    font-size: 0.9rem;
    font-weight: 700;
    color: #2E5E4E;
}

.progress-custom {
    height: 8px;
    border-radius: 4px;
    background-color: #e9ecef;
    overflow: hidden;
}

.progress-bar-custom {
    border-radius: 4px;
    transition: width 0.6s ease;
    position: relative;
}

.progress-bar-custom.not-started {
    background: #6c757d;
}

.progress-bar-custom.just-started {
    background: linear-gradient(90deg, #17a2b8 0%, #20c997 100%);
}

.progress-bar-custom.in-progress {
    background: linear-gradient(90deg, #ffc107 0%, #fd7e14 100%);
}

.progress-bar-custom.halfway {
    background: linear-gradient(90deg, #fd7e14 0%, #e83e8c 100%);
}

.progress-bar-custom.almost-done {
    background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
}

.progress-bar-custom.completed {
    background: linear-gradient(90deg, #2E5E4E 0%, #28a745 100%);
}

.progress-status-text {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6c757d;
    text-align: center;
    margin-top: 0.5rem;
}

/* Course Meta */
.course-meta {
    margin-bottom: 1rem;
}

.meta-item {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    color: #6c757d;
}

.meta-item i {
    margin-right: 0.5rem;
    width: 16px;
    text-align: center;
}

.course-card-footer {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    padding: 1rem 1.25rem;
    margin-top: auto;
}

.view-details-text {
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: 500;
}

.view-details-icon {
    color: #2E5E4E;
    font-size: 1.1rem;
    transition: transform 0.3s ease;
}

.course-selection-card:hover .view-details-icon {
    transform: translateX(4px);
}

/* Assessment Attempt Cards */
.assessment-attempt-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.assessment-attempt-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.attempt-header {
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}

.attempt-meta {
    margin-top: 0.5rem;
}

.score-badge {
    font-size: 1.5rem;
    font-weight: 700;
    padding: 0.5rem 1rem;
    border-radius: 8px;
}

.score-details {
    margin-top: 0.5rem;
}

.attempt-details {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.questions-list {
    background: white;
    border-radius: 8px;
    padding: 1rem;
}

.question-item {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.question-item.correct {
    border-left: 4px solid #28a745;
    background: #f8fff9;
}

.question-item.incorrect {
    border-left: 4px solid #dc3545;
    background: #fff8f8;
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.question-number {
    font-weight: 700;
    color: #2E5E4E;
    font-size: 1.1rem;
}

.question-status {
    font-size: 1.2rem;
}

.question-content {
    margin-top: 1rem;
}

.question-text {
    margin-bottom: 1rem;
    line-height: 1.6;
}

.question-type {
    margin-bottom: 1rem;
}

.student-answer {
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.answer-text {
    font-weight: 600;
}

.answer-analysis {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #fff3cd;
    border-radius: 4px;
    border-left: 3px solid #ffc107;
}

/* Module Progress Cards */
.module-progress-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    background: white;
    transition: all 0.3s ease;
    height: 100%;
}

.module-progress-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.module-header {
    margin-bottom: 1rem;
}

.module-meta {
    margin-top: 0.5rem;
}

.module-progress-bar {
    margin-bottom: 1rem;
}

.progress-text {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
}

.progress-percentage {
    font-weight: 600;
    color: #2E5E4E;
}

.progress-time {
    font-size: 0.9rem;
    color: #6c757d;
}

.module-details {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.detail-stat {
    text-align: center;
}

.detail-stat .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2E5E4E;
    margin-bottom: 0.25rem;
}

.detail-stat .stat-label {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.module-activity {
    text-align: center;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

/* Stat Trend Indicators */
.stat-trend {
    font-size: 0.8rem;
    margin-top: 0.25rem;
    font-weight: 500;
}

.stat-trend i {
    margin-right: 0.25rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .page-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .progress-value {
        font-size: 2rem;
    }
}

/* Custom Scrollbar Styling */
.table-responsive::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #2E5E4E;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #1e3a2e;
}

/* Smooth scrolling */
.table-responsive {
    scroll-behavior: smooth;
}

/* Scroll indicators */
.table-responsive::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 20px;
    height: 100%;
    background: linear-gradient(to left, rgba(46, 94, 78, 0.1), transparent);
    pointer-events: none;
    z-index: 1;
}

.table-responsive::after {
    content: '';
    position: absolute;
    bottom: 0;
    right: 0;
    width: 20px;
    height: 20px;
    background: linear-gradient(to top, rgba(46, 94, 78, 0.1), transparent);
    pointer-events: none;
    z-index: 1;
}
</style>

<script>
// Course selection function
function selectCourse(courseId) {
    const studentId = <?php echo $student_id; ?>;
    const academicPeriodId = <?php echo $academic_period_id; ?>;
    
    // Redirect to the course details page
    window.location.href = `student_course_details.php?student_id=${studentId}&course_id=${courseId}&academic_period_id=${academicPeriodId}`;
}

// Real-time data refresh
let refreshInterval;

async function refreshData() {
    // Show loading state
    const refreshBtn = document.querySelector('button[onclick="refreshData()"]');
    if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Refreshing...';
    }
    try {
        await fetchAndUpdateProgress();
    } catch (e) {}
    if (refreshBtn) {
        refreshBtn.disabled = false;
        refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Refresh';
    }
}

async function fetchAndUpdateProgress() {
    const studentId = <?php echo $student_id; ?>;
    const courseId = <?php echo $course_id ?: 0; ?>;
    if (!courseId) return;
    const url = `/lms_cap/ajax_get_student_progress.php?student_id=${studentId}&course_id=${courseId}`;
    const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!resp.ok) return;
    const json = await resp.json();
    if (!json.success) return;
    updateProgressUI(json);
}

function updateProgressUI(json) {
    const progress = json.progress || {};
    const modules = json.modules || [];
    // Overall card
    const overallVal = document.getElementById('overall-progress-value');
    const overallBar = document.getElementById('overall-progress-bar');
    if (overallVal) overallVal.textContent = (progress.course_progress ?? 0) + '%';
    if (overallBar) overallBar.style.width = (progress.course_progress ?? 0) + '%';
    // Modules card
    const mCompleted = document.getElementById('modules-completed');
    const mTotal = document.getElementById('modules-total');
    const mBar = document.getElementById('modules-progress-bar');
    if (mCompleted) mCompleted.textContent = progress.completed_modules ?? 0;
    if (mTotal) mTotal.textContent = progress.total_modules ?? 0;
    if (mBar) {
        const pct = (progress.total_modules ?? 0) > 0 ? Math.round(((progress.completed_modules ?? 0) / (progress.total_modules || 1)) * 100) : 0;
        mBar.style.width = pct + '%';
    }
    // Videos card
    const vWatched = document.getElementById('videos-watched');
    const vTotal = document.getElementById('videos-total');
    const vBar = document.getElementById('videos-progress-bar');
    if (vWatched) vWatched.textContent = progress.total_videos_watched ?? 0;
    if (vTotal) vTotal.textContent = progress.total_videos ?? 0;
    if (vBar) {
        const vpct = (progress.total_videos ?? 0) > 0 ? Math.round(((progress.total_videos_watched ?? 0) / (progress.total_videos || 1)) * 100) : 0;
        vBar.style.width = vpct + '%';
    }
    // Per-module cards
    if (Array.isArray(modules)) {
        modules.forEach(function(mod) {
            const card = document.querySelector(`.module-progress-card[data-module-id="${mod.id}"]`);
            if (!card) return;
            const statusEl = card.querySelector('[data-module-status]');
            const progBar = card.querySelector('[data-module-progress]');
            const progText = card.querySelector('[data-module-progress-text]');
            const vidsWatched = card.querySelector('[data-module-videos-watched]');
            if (statusEl) {
                if (mod.is_completed) {
                    statusEl.className = 'badge bg-success';
                    statusEl.innerHTML = '<i class="bi bi-check-circle me-1"></i>Completed';
                } else {
                    statusEl.className = 'badge bg-warning';
                    statusEl.innerHTML = '<i class="bi bi-clock me-1"></i>In Progress';
                }
            }
            if (progBar) progBar.style.width = (mod.progress_percentage ?? 0) + '%';
            if (progText) progText.textContent = (mod.progress_percentage ?? 0) + '% Complete';
            if (vidsWatched && mod.watched_videos !== undefined) vidsWatched.textContent = mod.watched_videos;
        });
    }
}

// Auto-refresh every 30 seconds (only if not in course selection mode)
<?php if (!$show_course_selection): ?>
function startAutoRefresh() {
    refreshInterval = setInterval(() => {
        if (!document.hidden) {
            fetchAndUpdateProgress();
        }
    }, 10000);
}

document.addEventListener('DOMContentLoaded', function() {
    fetchAndUpdateProgress();
    startAutoRefresh();
});

document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        clearInterval(refreshInterval);
    } else {
        startAutoRefresh();
        fetchAndUpdateProgress();
    }
});

window.addEventListener('beforeunload', function() {
    clearInterval(refreshInterval);
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
