<?php
$page_title = 'Course Students';
require_once '../config/config.php';
requireRole('teacher');
require_once '../includes/header.php';

$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$course_id) {
    echo '<div class="alert alert-danger">Invalid course ID.</div>';
    include '../includes/footer.php';
    exit;
}

// Fetch course details
$course_stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();

if (!$course) {
    echo '<div class="alert alert-danger">Course not found.</div>';
    include '../includes/footer.php';
    exit;
}

// Get all students enrolled in this course
$students_stmt = $db->prepare("
    SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.is_irregular, u.created_at,
           s.section_name, s.year_level
    FROM users u
    JOIN course_enrollments ce ON u.id = ce.student_id
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE ce.course_id = ? AND ce.status = 'active' AND u.role = 'student'
    ORDER BY s.year_level, s.section_name, u.last_name, u.first_name
");
$students_stmt->execute([$course_id]);
$students = $students_stmt->fetchAll();

// Get sections for this course
$sections_stmt = $db->prepare("
    SELECT s.id, s.section_name, s.year_level, 
           JSON_LENGTH(COALESCE(s.students, '[]')) as student_count
    FROM sections s
    WHERE JSON_SEARCH((SELECT sections FROM courses WHERE id = ?), 'one', s.id) IS NOT NULL
    ORDER BY s.year_level, s.section_name
");
$sections_stmt->execute([$course_id]);
$sections = $sections_stmt->fetchAll();
?>

<style>
/* Modern Course Students Styles - Matching Course Edit Design */

/* Course Students Header */
.course-students-header {
    background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    position: relative;
}

.course-students-header h1 {
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.course-students-header .opacity-90 {
    opacity: 0.9;
}

/* Back Button Icon Only - Left Corner */
.btn-back-icon {
    width: 45px !important;
    height: 45px !important;
    border-radius: 50% !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 1.2rem !important;
    position: absolute !important;
    top: 20px !important;
    left: 20px !important;
    z-index: 10 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
    color: white !important;
    background: rgba(255, 255, 255, 0.1) !important;
    transition: all 0.3s ease !important;
}

.btn-back-icon:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    color: white !important;
}

/* Course Statistics */
.course-stats {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.course-stat-item {
    text-align: center;
}

.course-stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.course-stat-label {
    display: block;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.8);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}

/* Students Table Card */
.students-table-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: none;
    overflow: hidden;
    margin-bottom: 2rem;
}

.students-table-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    padding: 1rem 1.25rem;
    font-weight: 600;
    color: #495057;
}

.students-table-card .table {
    margin-bottom: 0;
}

.students-table-card .table thead th {
    background: #1e3a2e;
    color: white;
    border: none;
    padding: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.9rem;
}

.students-table-card .table tbody td {
    padding: 1rem;
    border: none;
    border-bottom: 1px solid #f8f9fa;
    vertical-align: middle;
}

.students-table-card .table tbody tr:hover {
    background: #f8f9fa;
    transform: scale(1.01);
    transition: all 0.3s ease;
}

/* Status Badges */
.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.regular {
    background: #28a745;
    color: white;
}

.status-badge.irregular {
    background: #dc3545;
    color: white;
}

/* Section Badge */
.section-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h5 {
    color: #495057;
    margin-bottom: 1rem;
}

.empty-state p {
    margin: 0;
    font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .course-students-header {
        padding: 1.5rem 0;
    }
    
    .course-stats {
        gap: 1rem;
    }
    
    .course-stat-number {
        font-size: 1.25rem;
    }
    
    .course-stat-label {
        font-size: 0.75rem;
    }
    
    .btn-back-icon {
        top: 15px;
        left: 15px;
        width: 40px !important;
        height: 40px !important;
        font-size: 1rem !important;
    }
}

@media (max-width: 576px) {
    .course-students-header {
        padding: 1rem 0;
    }
    
    .course-stats {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-back-icon {
        top: 10px;
        left: 10px;
        width: 35px !important;
        height: 35px !important;
        font-size: 0.9rem !important;
    }
}
</style>

<!-- Modern Course Students Header with Back Button -->
<div class="course-students-header">
    <div class="container">
        <!-- Back Button Row -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-start">
                    <a href="course.php?id=<?= $course_id ?>" class="btn btn-outline-light btn-back-icon" title="Back to Course">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Header Content -->
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-3">
                    <i class="bi bi-people me-3"></i>Course Students
                </h1>
                <p class="mb-0 opacity-90">
                    <strong><?= htmlspecialchars($course['course_name']) ?></strong> • 
                    <?= htmlspecialchars($course['course_code']) ?> • 
                    <?= count($students) ?> Students
                </p>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="course-stats">
                        <div class="course-stat-item">
                            <span class="course-stat-number"><?= count($sections) ?></span>
                            <span class="course-stat-label">Sections</span>
                        </div>
                        <div class="course-stat-item">
                            <span class="course-stat-number"><?= count($students) ?></span>
                            <span class="course-stat-label">Students</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-bottom: 340px;">
    <!-- Students Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card students-table-card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-table me-2"></i>Enrolled Students
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (count($students) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-person me-2"></i>Student Name</th>
                                        <th><i class="bi bi-at me-2"></i>Username</th>
                                        <th><i class="bi bi-envelope me-2"></i>Email</th>
                                        <th><i class="bi bi-collection me-2"></i>Section</th>
                                        <th><i class="bi bi-patch-check me-2"></i>Status</th>
                                        <th><i class="bi bi-calendar-plus me-2"></i>Date Enrolled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                        <i class="bi bi-person-fill"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($student['username']) ?></code></td>
                                            <td><?= htmlspecialchars($student['email']) ?></td>
                                            <td>
                                                <?php if ($student['section_name']): ?>
                                                    <span class="section-badge">
                                                        BSIT-<?= $student['year_level'] ?><?= $student['section_name'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">No Section</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($student['is_irregular']): ?>
                                                    <span class="status-badge irregular">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>Irregular
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge regular">
                                                        <i class="bi bi-check-circle me-1"></i>Regular
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <i class="bi bi-calendar3 me-2"></i>
                                                <?= $student['created_at'] ? date('M j, Y', strtotime($student['created_at'])) : '-' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            <h5>No Students Found</h5>
                            <p>No students are currently enrolled in this course.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
