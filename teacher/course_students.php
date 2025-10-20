<?php
$page_title = 'Course Students';
require_once '../config/config.php';
requireRole('teacher');


// Get course ID from URL parameter
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    header('Location: courses.php');
    exit();
}

// Get course information
$stmt = $db->prepare('SELECT * FROM courses WHERE id = ? AND teacher_id = ?');
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit();
}

// Get academic period information
$stmt = $db->prepare('SELECT * FROM academic_periods WHERE id = ?');
$stmt->execute([$course['academic_period_id']]);
$academic_period = $stmt->fetch();

// Get students enrolled in this course with enhanced data
$stmt = $db->prepare('
    SELECT 
        u.id as student_id,
        u.first_name,
        u.last_name,
        u.email,
        u.identifier,
        u.year_level,
        u.is_irregular,
        u.profile_picture,
        ce.status,
        ce.enrolled_at,
        ce.progress_percentage,
        ce.last_accessed,
        ce.final_grade,
        -- Assessment statistics
        (SELECT COUNT(DISTINCT aa.assessment_id) 
         FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE aa.student_id = u.id AND a.course_id = ?) as total_assessments,
        (SELECT COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.assessment_id END) 
         FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE aa.student_id = u.id AND a.course_id = ?) as completed_assessments,
        (SELECT ROUND(AVG(aa.score), 2) 
         FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE aa.student_id = u.id AND a.course_id = ?) as avg_score,
        (SELECT MAX(aa.score) 
         FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE aa.student_id = u.id AND a.course_id = ?) as best_score,
        (SELECT COUNT(*) 
         FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE aa.student_id = u.id AND a.course_id = ?) as total_attempts
    FROM course_enrollments ce
    JOIN users u ON ce.student_id = u.id
    WHERE ce.course_id = ? AND u.role = "student"
    ORDER BY u.last_name, u.first_name
');
$stmt->execute([$course_id, $course_id, $course_id, $course_id, $course_id, $course_id]);
$students = $stmt->fetchAll();

$message = '';
$message_type = '';

// Handle student actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'kick_student':
                $student_id = (int)($_POST['student_id'] ?? 0);
                
                if (!$student_id) {
                    $message = 'Student ID is required.';
                    $message_type = 'danger';
                } else {
                    try {
                        // Start transaction for data integrity
                        $db->beginTransaction();
                        
                        // Remove student from course enrollments
                        $stmt = $db->prepare('DELETE FROM course_enrollments WHERE student_id = ? AND course_id = ?');
                        $stmt->execute([$student_id, $course_id]);
                        
                        // Remove assessment attempts for this student in this course
                        $stmt = $db->prepare('
                            DELETE aa FROM assessment_attempts aa 
                            JOIN assessments a ON aa.assessment_id = a.id
                            WHERE aa.student_id = ? AND a.course_id = ?
                        ');
                        $stmt->execute([$student_id, $course_id]);
                        
                        // Remove video views for this student in this course (if table exists)
                        try {
                            $stmt = $db->prepare('
                                DELETE vv FROM video_views vv 
                                JOIN videos v ON vv.video_id = v.id
                                WHERE vv.student_id = ? AND v.course_id = ?
                            ');
                            $stmt->execute([$student_id, $course_id]);
                        } catch (Exception $e) {
                            // Table might not exist, continue
                        }
                        
                        // Remove any enrollment requests for this course
                        $stmt = $db->prepare('DELETE FROM enrollment_requests WHERE student_id = ? AND course_id = ?');
                        $stmt->execute([$student_id, $course_id]);
                        
                        $db->commit();
                        
                        // Refresh the page to show updated list
                        header('Location: course_students.php?id=' . $course_id);
                        exit();
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $message = 'Error removing student: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                }
                break;
        }
    }
}

// Include header after all POST processing to prevent output before redirects
require_once '../includes/header.php';
?>

<!-- Compact Course Students Management Header -->
<div class="students-management-header-compact">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="bi bi-people me-2"></i>Course Students
                </h2>
                <p class="mb-0 opacity-90">
                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                    <?php if ($academic_period): ?>
                        - <?php echo htmlspecialchars($academic_period['academic_year'] . ' ' . $academic_period['semester_name']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-end">
                    <a href="courses.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left"></i> Back to Courses
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Compact Statistics Cards -->
    <div class="row mb-3 students-stats-compact">
        <div class="col-md-3">
            <div class="card stats-card-compact stats-primary">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="stats-content">
                            <h4 class="stats-number-compact mb-0"><?php echo count($students); ?></h4>
                            <small class="stats-label-compact">Total Students</small>
                        </div>
                        <div class="stats-icon-compact">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card-compact stats-success">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="stats-content">
                            <h4 class="stats-number-compact mb-0">
                                <?php 
                                $active_students = array_filter($students, function($s) { 
                                    return $s['status'] === 'active'; 
                                });
                                echo count($active_students);
                                ?>
                            </h4>
                            <small class="stats-label-compact">Active</small>
                        </div>
                        <div class="stats-icon-compact">
                            <i class="bi bi-person-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card-compact stats-warning">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="stats-content">
                            <h4 class="stats-number-compact mb-0">
                                <?php 
                                $completed_students = array_filter($students, function($s) { 
                                    return $s['status'] === 'completed'; 
                                });
                                echo count($completed_students);
                                ?>
                            </h4>
                            <small class="stats-label-compact">Completed</small>
                        </div>
                        <div class="stats-icon-compact">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card-compact stats-info">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="stats-content">
                            <h4 class="stats-number-compact mb-0">
                                <?php 
                                $score_values = array_filter(array_column($students, 'avg_score'), function($val) {
                                    return $val !== null && $val !== '' && $val > 0;
                                });
                                $avg_score = count($score_values) > 0 ? 
                                    array_sum($score_values) / count($score_values) : 0;
                                echo number_format($avg_score, 1);
                                ?>%
                            </h4>
                            <small class="stats-label-compact">Avg Score</small>
                        </div>
                        <div class="stats-icon-compact">
                            <i class="bi bi-award"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students List -->
    <div class="row">
        <div class="col-12">
            <div class="card students-card border-0 shadow-sm">
                <div class="card-header students-table-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>Enrolled Students
                                <span class="badge bg-primary ms-2"><?php echo count($students); ?></span>
                            </h5>
                            <small class="text-muted">
                                <i class="bi bi-arrow-down-up me-1"></i>Scroll to view all students
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()" title="Refresh Data">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($students)): ?>
                        <div class="empty-state text-center py-5">
                            <div class="empty-state-content">
                                <i class="bi bi-people display-1 text-muted mb-4"></i>
                                <h4 class="text-muted mb-3">No Students Enrolled</h4>
                                <p class="text-muted mb-4">No students are currently enrolled in this course. Students will appear here once they enroll.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Scrollable Students Table Container -->
                        <div class="students-table-scrollable-container-tall" id="studentsTableContainer">
                            <!-- Scroll Indicators -->
                            <div class="scroll-indicator scroll-indicator-top" id="scrollTopIndicator">
                                <i class="bi bi-chevron-up"></i>
                            </div>
                            <div class="scroll-indicator scroll-indicator-bottom" id="scrollBottomIndicator">
                                <i class="bi bi-chevron-down"></i>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover students-table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="30%">
                                                <i class="bi bi-person me-1"></i>Student
                                            </th>
                                            <th width="10%">Student ID</th>
                                            <th width="10%">Year Level</th>
                                            <th width="10%">Assessments</th>
                                            <th width="10%">
                                                <i class="bi bi-award me-1"></i>Avg %
                                            </th>
                                            <th width="12%">
                                                <i class="bi bi-clock me-1"></i>Last Activity
                                            </th>
                                            <th width="10%">Status</th>
                                            <th width="8%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr data-student-id="<?php echo $student['student_id']; ?>" class="student-row">
                                                <td>
                                                    <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'medium'); ?>" class="profile-picture me-2" alt="Student" style="width: 48px; height: 48px; object-fit: cover;">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                        <?php if ($student['is_irregular']): ?>
                                                            <br><span class="badge bg-warning text-dark mt-1">Irregular</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($student['identifier'])): ?>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($student['identifier']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">No ID</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        Year <?php echo $student['year_level']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $completed_assessments = (int)($student['completed_assessments'] ?? 0);
                                                    $total_assessments = (int)($student['total_assessments'] ?? 0);
                                                    $assessment_color = $completed_assessments == $total_assessments && $total_assessments > 0 ? 'bg-success' : 
                                                                       ($completed_assessments > 0 ? 'bg-warning' : 'bg-secondary');
                                                    ?>
                                                    <span class="badge <?php echo $assessment_color; ?> assessment-badge">
                                                        <i class="bi bi-file-text me-1"></i><?php echo $completed_assessments; ?>/<?php echo $total_assessments; ?>
                                                    </span>
                                                    <?php if ($student['total_attempts'] > 0): ?>
                                                        <small class="text-muted d-block">
                                                            <?php echo $student['total_attempts']; ?> attempts
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $avg_score = $student['avg_score'] ?? 0;
                                                    $avg_score = max(0, min(100, $avg_score)); // Ensure between 0-100
                                                    $best_score = $student['best_score'] ?? 0;
                                                    $best_score = max(0, min(100, $best_score)); // Ensure between 0-100
                                                    $score_color = $avg_score >= 80 ? 'bg-success' : 
                                                                  ($avg_score >= 70 ? 'bg-warning' : 
                                                                  ($avg_score >= 50 ? 'bg-info' : 'bg-danger'));
                                                    ?>
                                                    <span class="badge <?php echo $score_color; ?> score-badge">
                                                        <i class="bi bi-info-circle me-1"></i><?php echo number_format($avg_score, 1); ?>%
                                                    </span>
                                                    <?php if ($best_score > 0): ?>
                                                        <small class="text-muted d-block">
                                                            Best: <?php echo number_format($best_score, 1); ?>%
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $last_activity = $student['last_accessed'] ?? null;
                                                    if ($last_activity && $last_activity !== '0000-00-00 00:00:00') {
                                                        $time_ago = time() - strtotime($last_activity);
                                                        $days_ago = floor($time_ago / (24 * 60 * 60));
                                                        
                                                        if ($days_ago == 0) {
                                                            $activity_text = 'Today';
                                                            $activity_color = 'text-success';
                                                        } elseif ($days_ago == 1) {
                                                            $activity_text = 'Yesterday';
                                                            $activity_color = 'text-warning';
                                                        } elseif ($days_ago < 7) {
                                                            $activity_text = $days_ago . ' days ago';
                                                            $activity_color = 'text-info';
                                                        } else {
                                                            $activity_text = date('M d', strtotime($last_activity));
                                                            $activity_color = 'text-muted';
                                                        }
                                                    } else {
                                                        $activity_text = 'Never';
                                                        $activity_color = 'text-danger';
                                                    }
                                                    ?>
                                                    <small class="<?php echo $activity_color; ?>">
                                                        <?php echo $activity_text; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    switch ($student['status']) {
                                                        case 'active':
                                                            $status_class = 'bg-success';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-primary';
                                                            break;
                                                        case 'dropped':
                                                            $status_class = 'bg-danger';
                                                            break;
                                                        case 'pending':
                                                            $status_class = 'bg-warning';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($student['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="confirmKickStudent(<?php echo $student['student_id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')"
                                                                title="Remove from course">
                                                            <i class="bi bi-person-x"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kick Student Confirmation Modal -->
<div class="modal fade" id="kickStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Remove Student from Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove <strong id="studentName"></strong> from this course?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action will:
                    <ul class="mb-0 mt-2">
                        <li>Remove the student from the course</li>
                        <li>Delete all their progress data</li>
                        <li>Remove assessment attempts</li>
                        <li>Remove video views</li>
                    </ul>
                    <strong>This action cannot be undone!</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="kick_student">
                    <input type="hidden" name="student_id" id="kickStudentId">
                    <button type="submit" class="btn btn-danger">Remove Student</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Compact Students Management Header */
.students-management-header-compact {
    background: var(--main-green);
    color: white;
    padding: 20px 0;
    margin-bottom: 15px;
}

.students-management-header-compact .h2 {
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 1.75rem;
}

.students-management-header-compact .opacity-90 {
    opacity: 0.9;
}


/* Compact Statistics Cards */
.students-stats-compact .card {
    transition: all 0.3s ease;
    border-radius: 8px;
    overflow: hidden;
    border: none;
    box-shadow: 0 1px 6px rgba(0,0,0,0.08);
}

.students-stats-compact .card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.12);
}

.students-stats-compact .card .card-body {
    padding: 12px 16px;
}

.stats-card-compact {
    min-height: auto;
}

.stats-number-compact {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0;
}

.stats-label-compact {
    font-size: 0.75rem;
    opacity: 0.9;
    font-weight: 500;
}

.stats-icon-compact i {
    font-size: 1.5rem;
    opacity: 0.8;
}

.stats-card-compact.stats-primary {
    background: var(--main-green);
    color: white;
}

.stats-card-compact.stats-success {
    background: #28a745;
    color: white;
}

.stats-card-compact.stats-warning {
    background: #ffc107;
    color: #212529;
}

.stats-card-compact.stats-info {
    background: #17a2b8;
    color: white;
}


/* Students Card */
.students-card {
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
}

.students-card .card-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 16px 20px;
}

.students-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #495057;
}

/* Table Styling */
.students-table-scrollable-container-tall {
    max-height: 75vh;
    overflow-y: auto;
    overflow-x: hidden;
    position: relative;
    min-height: 500px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

/* Custom Scrollbar Styling */
.students-table-scrollable-container-tall::-webkit-scrollbar {
    width: 8px;
}

.students-table-scrollable-container-tall::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.students-table-scrollable-container-tall::-webkit-scrollbar-thumb {
    background: var(--main-green);
    border-radius: 4px;
}

.students-table-scrollable-container-tall::-webkit-scrollbar-thumb:hover {
    background: #2d5a3d;
}

/* Firefox Scrollbar */
.students-table-scrollable-container-tall {
    scrollbar-width: thin;
    scrollbar-color: var(--main-green) #f1f1f1;
}

/* Scroll Indicators */
.scroll-indicator {
    position: absolute;
    right: 15px;
    z-index: 15;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.scroll-indicator-top {
    top: 15px;
}

.scroll-indicator-bottom {
    bottom: 15px;
}

.scroll-indicator.show {
    opacity: 1;
}

.students-table {
    margin-bottom: 0;
}

.students-table thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #343a40;
    color: white;
    border: none;
    padding: 15px 12px;
    font-weight: 600;
    font-size: 0.875rem;
}

.students-table tbody td {
    padding: 15px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f3f4;
    font-size: 0.875rem;
}

.students-table tbody tr:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

/* Profile Picture */
.profile-picture {
    border-radius: 50%;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.students-table tbody tr:hover .profile-picture {
    transform: scale(1.1);
    border-color: var(--main-green);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

/* Badge Styling */
.badge {
    font-size: 0.75rem;
    padding: 6px 10px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.badge:hover {
    transform: scale(1.05);
}

/* Progress Text */
.progress-text {
    transition: all 0.3s ease;
}

.students-table tbody tr:hover .progress-text {
    transform: scale(1.05);
}

/* Student Name Styling */
.students-table tbody td .fw-bold {
    transition: all 0.3s ease;
}

.students-table tbody tr:hover .fw-bold {
    color: var(--main-green);
    transform: translateX(2px);
}

/* Empty State */
.empty-state {
    padding: 60px 20px;
}

.empty-state-content {
    max-width: 400px;
    margin: 0 auto;
}

/* Mobile Responsiveness */
@media (max-width: 991.98px) {
    .students-table-scrollable-container-tall {
        max-height: 60vh;
        min-height: 400px;
    }
    
    .students-table thead th,
    .students-table tbody td {
        padding: 12px 8px;
        font-size: 0.9rem;
    }
    
    .student-stats {
        flex-direction: column;
        gap: 10px;
    }
}

@media (max-width: 575.98px) {
    .students-table-scrollable-container-tall {
        max-height: 50vh;
        min-height: 300px;
    }
    
    .students-table thead th,
    .students-table tbody td {
        padding: 8px 4px;
        font-size: 0.85rem;
    }
    
    .profile-picture {
        width: 36px !important;
        height: 36px !important;
    }
    
    .students-management-header-compact {
        padding: 15px 0;
    }
    
    .students-management-header-compact .h2 {
        font-size: 1.5rem;
    }
}
</style>

<script>
function confirmKickStudent(studentId, studentName) {
    document.getElementById('studentName').textContent = studentName;
    document.getElementById('kickStudentId').value = studentId;
    new bootstrap.Modal(document.getElementById('kickStudentModal')).show();
}

// Enhanced scroll functionality
document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.getElementById('studentsTableContainer');
    const scrollTopIndicator = document.getElementById('scrollTopIndicator');
    const scrollBottomIndicator = document.getElementById('scrollBottomIndicator');
    
    if (tableContainer && scrollTopIndicator && scrollBottomIndicator) {
        // Update scroll indicators based on scroll position
        function updateScrollIndicators() {
            const scrollTop = tableContainer.scrollTop;
            const scrollHeight = tableContainer.scrollHeight;
            const clientHeight = tableContainer.clientHeight;
            
            // Show/hide top indicator
            if (scrollTop > 50) {
                scrollTopIndicator.classList.add('show');
            } else {
                scrollTopIndicator.classList.remove('show');
            }
            
            // Show/hide bottom indicator
            if (scrollTop < scrollHeight - clientHeight - 50) {
                scrollBottomIndicator.classList.add('show');
            } else {
                scrollBottomIndicator.classList.remove('show');
            }
        }
        
        // Add scroll event listener
        tableContainer.addEventListener('scroll', updateScrollIndicators);
        
        // Initial check
        updateScrollIndicators();
        
        // Add smooth scroll functionality to indicators
        scrollTopIndicator.addEventListener('click', function() {
            tableContainer.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        scrollBottomIndicator.addEventListener('click', function() {
            tableContainer.scrollTo({
                top: tableContainer.scrollHeight,
                behavior: 'smooth'
            });
        });
        
        // Make indicators clickable
        scrollTopIndicator.style.pointerEvents = 'auto';
        scrollBottomIndicator.style.pointerEvents = 'auto';
        scrollTopIndicator.style.cursor = 'pointer';
        scrollBottomIndicator.style.cursor = 'pointer';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
