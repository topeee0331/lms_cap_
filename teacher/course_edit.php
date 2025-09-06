<?php
include '../config/database.php';
include '../includes/header.php';

// Get course ID
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

// Fetch all sections for the same year as the course (standalone sections)
$section_sql = "SELECT id, section_name as name, year_level as year FROM sections WHERE year_level = ? ORDER BY year_level, section_name";
$section_stmt = $db->prepare($section_sql);
$section_stmt->execute([$course['academic_year_id']]);
$sections = $section_stmt->fetchAll();

// Assigned sections logic: no longer by course_id, so leave empty or implement new logic if needed
$assigned_section_ids = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['course_name']);
    $code = trim($_POST['course_code']);
    $desc = trim($_POST['description']);
    $selected_sections = isset($_POST['sections']) ? $_POST['sections'] : [];

    if ($name !== '' && $code !== '') {
        // Update course details
        $update_stmt = $db->prepare("UPDATE courses SET course_name = ?, course_code = ?, description = ? WHERE id = ?");
        $update_stmt->execute([$name, $code, $desc, $course_id]);
        // Remove all previous section assignments for this course
        // $db->prepare("UPDATE sections SET course_id = NULL WHERE course_id = ?")->execute([$course_id]); // This line is removed
        // Assign selected sections to this course
        foreach ($selected_sections as $sid) {
            $sid = intval($sid);
            // $db->prepare("UPDATE sections SET course_id = ? WHERE id = ?")->execute([$course_id, $sid]); // This line is removed
        }
        echo "<script>alert('Course updated successfully!'); window.location.href='courses.php';</script>";
        exit;
    } else {
        echo '<div class="alert alert-danger">All fields are required.</div>';
    }
}
?>

<style>
.course-edit-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: none;
    overflow: hidden;
}

.course-header {
    background: #2E5E4E;
    color: white;
    padding: 2rem;
    text-align: center;
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

.form-section {
    padding: 2rem;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.form-control {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #2E5E4E;
    box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25);
}

.btn-primary {
    background: #2E5E4E;
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(46, 94, 78, 0.2);
}

.btn-primary:hover {
    background: #1e4a3e;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(46, 94, 78, 0.3);
}

.btn-secondary {
    background: #6c757d;
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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

.form-group {
    margin-bottom: 1.5rem;
}

.required-field::after {
    content: " *";
    color: #dc3545;
    font-weight: bold;
}

.course-info {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid #2E5E4E;
}

.course-info h6 {
    color: #2E5E4E;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.course-info p {
    margin: 0;
    color: #6c757d;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
    }
}
</style>
<div class="container" style="margin-bottom: 340px;">
    <!-- Navigation Back Button -->
    <div class="row mb-4">
        <div class="col-12">
            <a href="courses.php" class="btn back-btn">
                <i class="bi bi-arrow-left me-2"></i>Back to Courses
            </a>
        </div>
    </div>

    <!-- Course Edit Card -->
    <div class="course-edit-container">
        <!-- Course Header -->
        <div class="course-header">
            <h1><i class="bi bi-pencil-square me-3"></i>Edit Course</h1>
            <div class="course-code">
                <i class="bi bi-book me-2"></i><?= htmlspecialchars($course['course_name']) ?>
            </div>
        </div>

        <!-- Course Information -->
        <div class="form-section">
            <div class="course-info">
                <h6><i class="bi bi-info-circle me-2"></i>Course Details</h6>
                <p><strong>Course Code:</strong> <?= htmlspecialchars($course['course_code']) ?></p>
                <p><strong>Academic Year:</strong> <?= $course['academic_year_id'] ?? 'N/A' ?></p>
                <p><strong>Created:</strong> <?= $course['created_at'] ? date('M j, Y', strtotime($course['created_at'])) : 'N/A' ?></p>
            </div>

            <form method="post" action="course_edit.php?id=<?= $course_id ?>">
                <div class="form-group">
                    <label for="course_name" class="form-label required-field">
                        <i class="bi bi-book me-2"></i>Course Name
                    </label>
                    <input type="text" class="form-control" id="course_name" name="course_name" 
                           required value="<?= htmlspecialchars($course['course_name']) ?>"
                           placeholder="Enter course name">
                </div>

                <div class="form-group">
                    <label for="course_code" class="form-label required-field">
                        <i class="bi bi-tag me-2"></i>Course Code
                    </label>
                    <input type="text" class="form-control" id="course_code" name="course_code" 
                           required value="<?= htmlspecialchars($course['course_code']) ?>"
                           placeholder="Enter course code">
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">
                        <i class="bi bi-text-paragraph me-2"></i>Description
                    </label>
                    <textarea class="form-control" id="description" name="description" rows="4"
                              placeholder="Enter course description"><?= htmlspecialchars($course['description']) ?></textarea>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Save Changes
                    </button>
                    <a href="courses.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if (!empty($assigned_section_ids)): ?>
    <hr>
    <h4>Sections Assigned to this Subject</h4>
    <div class="mb-3">
        <?php foreach ($sections as $section): ?>
            <?php if (in_array($section['id'], $assigned_section_ids)): ?>
                <span class="badge bg-info text-dark me-1 mb-1">
                    <?= htmlspecialchars(formatSectionName($section)) ?>
                    <button type="button" class="btn btn-sm btn-link p-0 ms-1" data-bs-toggle="modal" data-bs-target="#studentsModal<?= $course_id ?>_<?= $section['id'] ?>">View Students</button>
                </span>
                <!-- Modal for students in this section -->
                <div class="modal fade" id="studentsModal<?= $course_id ?>_<?= $section['id'] ?>" tabindex="-1" aria-labelledby="studentsModalLabel<?= $course_id ?>_<?= $section['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="studentsModalLabel<?= $course_id ?>_<?= $section['id'] ?>">
                                    Students in <?= htmlspecialchars(formatSectionName($section)) ?> (<?= htmlspecialchars($course['course_name']) ?>)
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php
                                // Reuse the get_section_students function
                                $students = get_section_students($db, $section['id']);
                                if ($students) {
                                    echo '<ul class="list-group">';
                                    foreach ($students as $stu) {
                                        $status_badge = ($stu['is_irregular'] ?? 0)
                                            ? "<span class='badge bg-danger ms-2'>Irregular</span>"
                                            : "<span class='badge bg-success ms-2'>Regular</span>";
                                        echo '<li class="list-group-item d-flex flex-column flex-md-row align-items-md-center justify-content-between">';
                                        echo '<div><strong>' . htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name']) . '</strong> '; 
                                        echo '<span class="text-muted">(' . htmlspecialchars($stu['username']) . ')</span> ';
                                        echo $status_badge;
                                        echo '</div>';
                                        echo '<div class="small text-muted">' . htmlspecialchars($stu['email']) . '</div>';
                                        echo '</li>';
                                    }
                                    echo '</ul>';
                                } else {
                                    echo '<p>No students assigned.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?> 