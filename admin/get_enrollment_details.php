<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

$enrollment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$enrollment_id) {
    echo '<div class="alert alert-danger">Invalid enrollment ID</div>';
    exit;
}

try {
    // Get enrollment details
    $stmt = $db->prepare("
        SELECT 
            e.*,
            u.first_name, u.last_name, u.email, u.username, u.identifier,
            c.course_name, c.course_code, c.description as course_description,
            t.first_name as teacher_first_name, t.last_name as teacher_last_name,
            ap.academic_year, ap.semester_name,
            s.section_name, s.year_level
        FROM course_enrollments e
        JOIN users u ON e.student_id = u.id
        JOIN courses c ON e.course_id = c.id
        JOIN users t ON c.teacher_id = t.id
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        WHERE e.id = ?
    ");
    $stmt->execute([$enrollment_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$enrollment) {
        echo '<div class="alert alert-danger">Enrollment not found</div>';
        exit;
    }
    
    // Get student progress
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT mp.id) as completed_modules,
            JSON_LENGTH(COALESCE(c.modules, '[]')) as total_modules,
            AVG(aa.score) as average_score
        FROM courses c
        LEFT JOIN module_progress mp ON mp.course_id = c.id AND mp.student_id = ? AND mp.is_completed = 1
        LEFT JOIN assessment_attempts aa ON aa.course_id = c.id AND aa.student_id = ?
        WHERE c.id = ?
    ");
    $stmt->execute([$enrollment['student_id'], $enrollment['student_id'], $enrollment['course_id']]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $progress_percentage = $progress['total_modules'] > 0 ? 
        round(($progress['completed_modules'] / $progress['total_modules']) * 100, 1) : 0;
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading enrollment details: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="fw-bold">Student Information</h6>
        <table class="table table-sm">
            <tr><td><strong>Name:</strong></td><td><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></td></tr>
            <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($enrollment['email']); ?></td></tr>
            <tr><td><strong>Student ID:</strong></td><td><?php echo htmlspecialchars($enrollment['identifier']); ?></td></tr>
            <tr><td><strong>Username:</strong></td><td><?php echo htmlspecialchars($enrollment['username']); ?></td></tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="fw-bold">Course Information</h6>
        <table class="table table-sm">
            <tr><td><strong>Course:</strong></td><td><?php echo htmlspecialchars($enrollment['course_name']); ?></td></tr>
            <tr><td><strong>Code:</strong></td><td><?php echo htmlspecialchars($enrollment['course_code']); ?></td></tr>
            <tr><td><strong>Teacher:</strong></td><td><?php echo htmlspecialchars($enrollment['teacher_first_name'] . ' ' . $enrollment['teacher_last_name']); ?></td></tr>
            <tr><td><strong>Academic Period:</strong></td><td><?php echo htmlspecialchars($enrollment['academic_year'] . ' - ' . $enrollment['semester_name']); ?></td></tr>
        </table>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <h6 class="fw-bold">Enrollment Details</h6>
        <table class="table table-sm">
            <tr><td><strong>Status:</strong></td><td>
                <span class="badge bg-<?php echo $enrollment['status'] === 'active' ? 'success' : ($enrollment['status'] === 'completed' ? 'primary' : 'warning'); ?>">
                    <?php echo ucfirst(htmlspecialchars($enrollment['status'])); ?>
                </span>
            </td></tr>
            <tr><td><strong>Enrolled Date:</strong></td><td><?php echo date('M j, Y', strtotime($enrollment['enrolled_at'])); ?></td></tr>
            <tr><td><strong>Section:</strong></td><td>
                <?php if ($enrollment['section_name']): ?>
                    <span class="badge bg-info"><?php echo htmlspecialchars($enrollment['section_name'] . ' (Year ' . $enrollment['year_level'] . ')'); ?></span>
                <?php else: ?>
                    <span class="text-muted">Not assigned</span>
                <?php endif; ?>
            </td></tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="fw-bold">Progress Information</h6>
        <table class="table table-sm">
            <tr><td><strong>Modules Completed:</strong></td><td><?php echo $progress['completed_modules']; ?> / <?php echo $progress['total_modules']; ?></td></tr>
            <tr><td><strong>Progress:</strong></td><td>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $progress_percentage; ?>%">
                        <?php echo $progress_percentage; ?>%
                    </div>
                </div>
            </td></tr>
            <tr><td><strong>Average Score:</strong></td><td>
                <?php echo $progress['average_score'] ? round($progress['average_score'], 1) . '%' : 'N/A'; ?>
            </td></tr>
        </table>
    </div>
</div>

<?php if ($enrollment['course_description']): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6 class="fw-bold">Course Description</h6>
        <p class="text-muted"><?php echo nl2br(htmlspecialchars($enrollment['course_description'])); ?></p>
    </div>
</div>
<?php endif; ?>
