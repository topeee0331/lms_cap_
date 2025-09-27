<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    header('Location: courses.php');
    exit();
}

// Check if student is enrolled in this course
$stmt = $pdo->prepare("
    SELECT * FROM course_enrollments 
    WHERE student_id = ? AND course_id = ? AND status = 'active'
");
$stmt->execute([$user_id, $course_id]);

if ($stmt->rowCount() == 0) {
    $_SESSION['error'] = "You are not enrolled in this course.";
    header('Location: courses.php');
    exit();
}

// Get course details
$stmt = $pdo->prepare("
    SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ? AND c.is_archived = 0
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit();
}

// Define course themes with IT icons
$course_themes = [
    ['bg' => 'bg-primary', 'icon' => 'fas fa-code'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-database'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-network-wired'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-server'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-shield-alt'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-cloud'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-microchip'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-laptop-code'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-mobile-alt'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-wifi'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-keyboard'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-bug'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-terminal'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-cogs'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-rocket'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-robot'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-brain'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-chart-line'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-fire'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-lightbulb']
];

// Get modules from JSON data in courses table
$modules = [];
if (!empty($course['modules'])) {
    $modules_data = json_decode($course['modules'] ?? '[]', true) ?: [];
    
    // Get student's progress data
    $stmt = $pdo->prepare("
        SELECT module_progress, video_progress 
        FROM course_enrollments 
        WHERE student_id = ? AND course_id = ?
    ");
    $stmt->execute([$user_id, $course_id]);
    $enrollment = $stmt->fetch();
    
    $module_progress = [];
    $video_progress = [];
    
    if ($enrollment) {
        $module_progress = json_decode($enrollment['module_progress'] ?? '{}', true) ?: [];
        $video_progress = json_decode($enrollment['video_progress'] ?? '{}', true) ?: [];
    }
    
    // Process modules and add progress information
    foreach ($modules_data as $module) {
        $module_id = $module['id'];
        $video_count = isset($module['videos']) ? count($module['videos']) : 0;
        $assessment_count = isset($module['assessments']) ? count($module['assessments']) : 0;
        
        // Count files from both new multiple files structure and legacy single file structure
        $file_count = 0;
        if (isset($module['files']) && is_array($module['files'])) {
            $file_count = count($module['files']);
        } elseif (isset($module['file']) && !empty($module['file'])) {
            $file_count = 1; // Legacy single file
        }
        
        // Check if module is completed
        $is_completed = isset($module_progress[$module_id]) && $module_progress[$module_id]['is_completed'] == 1;
        $completed_at = isset($module_progress[$module_id]) ? $module_progress[$module_id]['completed_at'] : null;
        
        // Check if student meets requirements for module completion
        $can_complete = true;
        $requirement_message = '';
        $missing_requirements = [];
        
        if (isset($module['assessments']) && !empty($module['assessments'])) {
            foreach ($module['assessments'] as $assessment) {
                // Extract assessment ID - handle both string and object formats
                $assessment_id = is_array($assessment) ? $assessment['id'] : $assessment;
                
                // Get assessment details
                $stmt = $pdo->prepare("
                    SELECT a.assessment_title, a.passing_rate
                    FROM assessments a 
                    WHERE a.id = ? AND a.course_id = ?
                ");
                $stmt->execute([$assessment_id, $course_id]);
                $assessment = $stmt->fetch();
                
                if ($assessment) {
                    // Get student's best score for this assessment
                    $stmt = $pdo->prepare("
                        SELECT MAX(score) as best_score, MAX(has_passed) as has_passed
                        FROM assessment_attempts 
                        WHERE student_id = ? AND assessment_id = ? AND status = 'completed'
                    ");
                    $stmt->execute([$user_id, $assessment_id]);
                    $attempt = $stmt->fetch();
                    
                    $required_score = $assessment['required_score'] ?? $assessment['passing_rate'];
                    $student_score = $attempt['best_score'] ?? 0;
                    $has_passed = $attempt['has_passed'] ?? 0;
                    
                    // Check if student meets the requirement
                    if ($student_score < $required_score || !$has_passed) {
                        $can_complete = false;
                        $missing_requirements[] = [
                            'title' => $assessment['assessment_title'],
                            'required' => $required_score,
                            'current' => $student_score,
                            'has_attempted' => $student_score > 0
                        ];
                    }
                }
            }
        }
        
        $modules[] = [
            'id' => $module_id,
            'module_title' => $module['module_title'],
            'module_description' => $module['module_description'],
            'module_order' => $module['module_order'],
            'is_locked' => $module['is_locked'] ?? 0,
            'unlock_score' => $module['unlock_score'] ?? 70,
            'video_count' => $video_count,
            'assessment_count' => $assessment_count,
            'file_count' => $file_count,
            'is_completed' => $is_completed,
            'completed_at' => $completed_at,
            'videos' => $module['videos'] ?? [],
            'assessments' => $module['assessments'] ?? [],
            'files' => $module['files'] ?? [],
            'file' => $module['file'] ?? null,
            'can_complete' => $can_complete,
            'missing_requirements' => $missing_requirements
        ];
    }
    
    // Sort modules by order
    usort($modules, function($a, $b) {
        return $a['module_order'] - $b['module_order'];
    });
}

// Get course progress
$total_modules = count($modules);
$completed_modules = 0;
foreach ($modules as $module) {
    if ($module['is_completed']) {
        $completed_modules++;
    }
}
$course_progress = $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0;

// Handle module completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_module'])) {
    $module_id = $_POST['module_id'];
    
    // Check if module exists in the course's modules JSON
    $modules_data = json_decode($course['modules'] ?? '[]', true) ?: [];
    $target_module = null;
    foreach ($modules_data as $module) {
        if ($module['id'] === $module_id) {
            $target_module = $module;
            break;
        }
    }
    
    if ($target_module) {
        // Check if student meets the requirements for module completion
        $can_complete = true;
        $requirement_message = '';
        
        // Check if module has assessments and passing requirements
        if (isset($target_module['assessments']) && !empty($target_module['assessments'])) {
            foreach ($target_module['assessments'] as $assessment) {
                // Extract assessment ID - handle both string and object formats
                $assessment_id = is_array($assessment) ? $assessment['id'] : $assessment;
                
                // Get assessment details including passing rate
                $stmt = $pdo->prepare("
                    SELECT a.assessment_title, a.passing_rate
                    FROM assessments a 
                    WHERE a.id = ? AND a.course_id = ?
                ");
                $stmt->execute([$assessment_id, $course_id]);
                $assessment = $stmt->fetch();
                
                if ($assessment) {
                    // Get student's best score for this assessment
                    $stmt = $pdo->prepare("
                        SELECT MAX(score) as best_score, MAX(has_passed) as has_passed
                        FROM assessment_attempts 
                        WHERE student_id = ? AND assessment_id = ? AND status = 'completed'
                    ");
                    $stmt->execute([$user_id, $assessment_id]);
                    $attempt = $stmt->fetch();
                    
                    $required_score = $assessment['required_score'] ?? $assessment['passing_rate'];
                    $student_score = $attempt['best_score'] ?? 0;
                    $has_passed = $attempt['has_passed'] ?? 0;
                    
                    // Check if student meets the requirement
                    if ($student_score < $required_score || !$has_passed) {
                        $can_complete = false;
                        $requirement_message = "You must achieve at least {$required_score}% on '{$assessment['assessment_title']}' to complete this module. Your best score: {$student_score}%";
                        break;
                    }
                }
            }
        }
        
        if ($can_complete) {
            // Get current progress data
            $stmt = $pdo->prepare("
                SELECT module_progress 
                FROM course_enrollments 
                WHERE student_id = ? AND course_id = ?
            ");
            $stmt->execute([$user_id, $course_id]);
            $enrollment = $stmt->fetch();
            
            $module_progress = json_decode($enrollment['module_progress'] ?? '{}', true) ?: [];
            
            // Update module progress
            $module_progress[$module_id] = [
                'is_completed' => 1,
                'completed_at' => date('Y-m-d H:i:s')
            ];
            
            // Calculate progress percentage
            $total_modules = count($modules_data);
            $completed_count = count($module_progress);
            $progress_percentage = $total_modules > 0 ? round(($completed_count / $total_modules) * 100, 2) : 0;
            
            // Update enrollment with new progress
            $stmt = $pdo->prepare("
                UPDATE course_enrollments 
                SET module_progress = ?, progress_percentage = ?
                WHERE student_id = ? AND course_id = ?
            ");
            $stmt->execute([
                json_encode($module_progress),
                $progress_percentage,
                $user_id,
                $course_id
            ]);
            
            $_SESSION['success'] = "Module marked as completed!";
        } else {
            $_SESSION['error'] = $requirement_message;
        }
    }
    
    header('Location: course.php?id=' . $course_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_name'] ?? ''); ?> - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap');
        
        /* Button Hover Effects */
        .btn:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-primary:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-success:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-secondary:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-danger:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-warning:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .module-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .module-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .module-card.completed {
            border-left: 4px solid #28a745;
        }
        .module-card.locked {
            opacity: 0.6;
        }
        .progress-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(#28a745 <?php echo $course_progress; ?>%, #e9ecef 0);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .progress-text {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        /* Course Header Styling */
        .course-header {
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .course-header-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .course-header-bg i {
            font-size: 18rem;
            color: rgba(255, 255, 255, 0.9);
            opacity: 0.75;
        }
        
        .course-header-content {
            position: relative;
            z-index: 2;
        }
        
        .course-code-text {
            font-family: 'Poppins', 'Arial', sans-serif;
            font-size: 3.5rem;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            mix-blend-mode: overlay;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="col-12 px-md-4">
                <!-- Course Header with IT Icon Background -->
                <?php 
                $theme = $course_themes[$course_id % count($course_themes)];
                ?>
                <div class="course-header <?php echo $theme['bg']; ?>">
                    <div class="course-header-bg">
                        <i class="<?php echo $theme['icon']; ?>"></i>
                    </div>
                    <div class="course-header-content text-center">
                        <h1 class="course-code-text">
                            <?php echo htmlspecialchars($course['course_code'] ?? 'N/A'); ?>
                        </h1>
                        <h2 class="h1 mb-3"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></h2>
                        <p class="lead mb-0">by <?php echo htmlspecialchars($course['teacher_name'] ?? ''); ?></p>
                    </div>
                </div>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></h1>
                        <p class="text-muted">by <?php echo htmlspecialchars($course['teacher_name'] ?? ''); ?></p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="courses.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Courses
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

                <!-- Course Overview -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Course Description</h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($course['description'] ?? '')); ?></p>
                                
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <small class="text-muted">
                                            <i class="fas fa-layer-group"></i> <?php echo $total_modules; ?> modules
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> Created <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> Last updated <?php echo date('M j, Y', strtotime($course['updated_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="position-relative">
                                    <div class="progress-circle"></div>
                                    <div class="progress-text"><?php echo $course_progress; ?>%</div>
                                </div>
                                <h5 class="card-title mt-3">Course Progress</h5>
                                <p class="card-text"><?php echo $completed_modules; ?> of <?php echo $total_modules; ?> modules completed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Statistics -->
                <?php
                // Calculate total counts across all modules
                $total_videos = 0;
                $total_assessments = 0;
                $total_files = 0;
                
                foreach ($modules as $module) {
                    $total_videos += $module['video_count'];
                    $total_assessments += $module['assessment_count'];
                    $total_files += $module['file_count'];
                }
                ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-video fa-2x text-primary mb-2"></i>
                                <h4 class="card-title"><?php echo $total_videos; ?></h4>
                                <p class="card-text text-muted">Total Videos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-question-circle fa-2x text-warning mb-2"></i>
                                <h4 class="card-title"><?php echo $total_assessments; ?></h4>
                                <p class="card-text text-muted">Total Assessments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-file fa-2x text-info mb-2"></i>
                                <h4 class="card-title"><?php echo $total_files; ?></h4>
                                <p class="card-text text-muted">Total Files</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-layer-group fa-2x text-success mb-2"></i>
                                <h4 class="card-title"><?php echo $total_modules; ?></h4>
                                <p class="card-text text-muted">Total Modules</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modules -->
                <div class="mb-4">
                    <h3>Course Modules</h3>
                    <?php if (empty($modules)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No modules have been created for this course yet.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($modules as $index => $module): ?>
                                <?php 
                                $is_locked = $module['is_locked'] && $index > 0 && !$modules[$index - 1]['is_completed'];
                                ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card module-card <?php echo $module['is_completed'] ? 'completed' : ''; ?> <?php echo $is_locked ? 'locked' : ''; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title"><?php echo htmlspecialchars($module['module_title'] ?? ''); ?></h5>
                                                <?php if ($module['is_completed']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Completed
                                                    </span>
                                                <?php elseif ($is_locked): ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-lock"></i> Locked
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="card-text"><?php echo htmlspecialchars(substr($module['module_description'] ?? '', 0, 100)); ?>...</p>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-4">
                                                    <small class="text-muted">
                                                        <i class="fas fa-video"></i><br>
                                                        <?php echo $module['video_count']; ?> videos
                                                    </small>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">
                                                        <i class="fas fa-question-circle"></i><br>
                                                        <?php echo $module['assessment_count']; ?> assessments
                                                    </small>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">
                                                        <i class="fas fa-paperclip"></i><br>
                                                        <?php echo $module['file_count']; ?> file<?php echo $module['file_count'] != 1 ? 's' : ''; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <?php if ($is_locked): ?>
                                                <button class="btn btn-secondary w-100" disabled>
                                                    Complete previous module first
                                                </button>
                                            <?php elseif ($module['is_completed']): ?>
                                                <div class="d-grid gap-2">
                                                    <a href="module.php?id=<?php echo $module['id']; ?>" class="btn btn-outline-primary">
                                                        Review Module
                                                    </a>
                                                    <small class="text-muted">
                                                        Completed on <?php echo date('M j, Y', strtotime($module['completed_at'])); ?>
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-grid gap-2">
                                                    <a href="module.php?id=<?php echo $module['id']; ?>" class="btn btn-primary">
                                                        Start Module
                                                    </a>
                                                    
                                                    <?php if ($module['can_complete']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                                            <button type="submit" name="complete_module" class="btn btn-outline-success btn-sm">
                                                                Mark as Complete
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary btn-sm" disabled title="Requirements not met">
                                                            <i class="fas fa-lock me-1"></i>Requirements Not Met
                                                        </button>
                                                        <?php if (!empty($module['missing_requirements'])): ?>
                                                            <div class="mt-2">
                                                                <small class="text-muted">Missing requirements:</small>
                                                                <?php foreach ($module['missing_requirements'] as $req): ?>
                                                                    <div class="small text-danger">
                                                                        <i class="fas fa-times-circle me-1"></i>
                                                                        <?php echo htmlspecialchars($req['title']); ?>: 
                                                                        <?php echo $req['current']; ?>% / <?php echo $req['required']; ?>% required
                                                                        <?php if (!$req['has_attempted']): ?>
                                                                            <span class="text-muted">(Not attempted)</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 