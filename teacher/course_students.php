<?php
include '../config/database.php';
include '../includes/header.php';

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

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year']}{$section['name']}";
}

// Fetch all sections assigned to this course via JSON sections field
$section_sql = "SELECT s.id, s.section_name as name, s.year_level as year FROM sections s WHERE JSON_SEARCH((SELECT sections FROM courses WHERE id = ?), 'one', s.id) IS NOT NULL ORDER BY s.year_level, s.section_name";
$section_stmt = $db->prepare($section_sql);
$section_stmt->execute([$course_id]);
$sections = $section_stmt->fetchAll();

// Determine selected section
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : (count($sections) > 0 ? $sections[0]['id'] : 0);

// Fetch students in the selected section
$students = [];
if ($selected_section_id) {
    $stu_sql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email 
                 FROM users u 
                 WHERE u.role = 'student' 
                 AND JSON_SEARCH((SELECT students FROM sections WHERE id = ?), 'one', u.id) IS NOT NULL
                 ORDER BY u.last_name, u.first_name";
    $stu_stmt = $db->prepare($stu_sql);
    $stu_stmt->execute([$selected_section_id]);
    $students = $stu_stmt->fetchAll();
}

// Get total student count
$total_students = 0;
foreach ($sections as $section) {
    $stu_sql = "SELECT JSON_LENGTH(COALESCE(students, '[]')) as student_count FROM sections WHERE id = ?";
    $stu_stmt = $db->prepare($stu_sql);
    $stu_stmt->execute([$section['id']]);
    $total_students += $stu_stmt->fetchColumn();
}
?>

<style>
.course-header {
    background: #2E5E4E;
    color: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.course-header h1 {
    margin: 0;
    font-weight: 700;
    font-size: 2.5rem;
}

.course-header .course-code {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-top: 0.5rem;
}

.stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: none;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.stats-card .stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2E5E4E;
    margin-bottom: 0.5rem;
}

.stats-card .stat-label {
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.section-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 2rem;
    overflow: hidden;
}

.section-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
}

.section-header h4 {
    margin: 0;
    color: #495057;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.section-header .section-icon {
    background: #2E5E4E;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content-center;
    margin-right: 1rem;
    font-size: 1.2rem;
}

.enhanced-table {
    border: none;
    border-radius: 0;
}

.enhanced-table thead th {
    background: #2E5E4E;
    color: white;
    border: none;
    padding: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.9rem;
}

.enhanced-table tbody td {
    padding: 1rem;
    border: none;
    border-bottom: 1px solid #f8f9fa;
    vertical-align: middle;
}

.enhanced-table tbody tr:hover {
    background: #f8f9fa;
    transform: scale(1.01);
    transition: all 0.3s ease;
}

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

.back-btn {
    background: #6c757d;
    border: none;
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.back-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    color: white;
    background: #5a6268;
}

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
</style>

<div class="container mt-2" style="margin-bottom: 340px;">
    <!-- Back Button -->
    <div class="row mb-4">
        <div class="col-12">
            <a href="course.php?id=<?= $course_id ?>" class="btn back-btn">
                <i class="bi bi-arrow-left me-2"></i> Back to Manage Course
            </a>
        </div>
    </div>

    <!-- Course Header -->
    <div class="course-header">
        <h1><i class="bi bi-book me-3"></i><?= htmlspecialchars($course['course_name']) ?></h1>
        <div class="course-code">
            <i class="bi bi-tag me-2"></i><?= htmlspecialchars($course['course_code']) ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card text-center">
                <div class="stat-number"><?= count($sections) ?></div>
                <div class="stat-label">Total Sections</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card text-center">
                <div class="stat-number"><?= $total_students ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card text-center">
                <div class="stat-number"><?= $course['created_at'] ? date('Y', strtotime($course['created_at'])) : 'N/A' ?></div>
                <div class="stat-label">Course Year</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card text-center">
                <div class="stat-number"><?= $course['is_archived'] ? 'Archived' : 'Active' ?></div>
                <div class="stat-label">Status</div>
            </div>
        </div>
    </div>

    <?php if (count($sections) > 0): ?>
        <?php foreach ($sections as $section): ?>
            <div class="section-card">
                <div class="section-header">
                    <h4>
                        <div class="section-icon">
                            <i class="bi bi-collection"></i>
                        </div>
                        Section: <?= htmlspecialchars(formatSectionName($section)) ?>
                    </h4>
                </div>
                <div class="card-body p-0">
                    <?php
                    $stu_sql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.is_irregular, u.created_at 
                                 FROM users u 
                                 WHERE u.role = 'student' 
                                 AND JSON_SEARCH((SELECT students FROM sections WHERE id = ?), 'one', u.id) IS NOT NULL
                                 ORDER BY u.last_name, u.first_name";
                    $stu_stmt = $db->prepare($stu_sql);
                    $stu_stmt->execute([$section['id']]);
                    $students = $stu_stmt->fetchAll();
                    ?>
                    <?php if (count($students) > 0): ?>
                        <div class="table-responsive">
                            <table class="table enhanced-table mb-0">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-person me-2"></i>Student Name</th>
                                        <th><i class="bi bi-at me-2"></i>Username</th>
                                        <th><i class="bi bi-envelope me-2"></i>Email</th>
                                        <th><i class="bi bi-patch-check me-2"></i>Status</th>
                                        <th><i class="bi bi-calendar-plus me-2"></i>Date Added</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $stu): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                        <i class="bi bi-person-fill"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name']) ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($stu['username']) ?></code></td>
                                            <td><?= htmlspecialchars($stu['email']) ?></td>
                                            <td>
                                                <?php if (($stu['is_irregular'] ?? 0)): ?>
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
                                                <?= $stu['created_at'] ? date('M j, Y', strtotime($stu['created_at'])) : '-' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            <h5>No Students Assigned</h5>
                            <p>No students are currently assigned to this section.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="section-card">
            <div class="empty-state">
                <i class="bi bi-collection"></i>
                <h5>No Sections Assigned</h5>
                <p>No sections are currently assigned to this course.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?> 