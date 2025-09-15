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
// Check if academic_year_id exists, otherwise use a default value
$academic_year_id = $course['academic_year_id'] ?? 1; // Default to 1 if not set
$section_sql = "SELECT id, section_name as name, year_level as year FROM sections WHERE year_level = ? ORDER BY year_level, section_name";
$section_stmt = $db->prepare($section_sql);
$section_stmt->execute([$academic_year_id]);
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

/* Modern Course Edit Styles - Matching Design System */

/* Course Edit Header */
.course-edit-header {
    background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    position: relative;
}

.course-edit-header h1 {
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.course-edit-header .opacity-90 {
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

/* Course Edit Container */
.course-edit-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: none;
    overflow: hidden;
    margin-bottom: 2rem;
}

/* Form Section */
.form-section {
    padding: 2rem;
}

/* Form Labels */
.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

/* Form Controls */
.form-control {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #1e3a2e;
    box-shadow: 0 0 0 0.2rem rgba(30, 58, 46, 0.25);
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(30, 58, 46, 0.2);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0f2a1f 0%, #1e3a2e 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(30, 58, 46, 0.3);
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

/* Form Groups */
.form-group {
    margin-bottom: 1.5rem;
}

.required-field::after {
    content: " *";
    color: #dc3545;
    font-weight: bold;
}

/* Course Edit Cards */
.course-edit-card {
    border-radius: 15px;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.course-edit-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.course-edit-card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    padding: 1rem 1.25rem;
    font-weight: 600;
    color: #495057;
}

.course-edit-card .card-body {
    padding: 1.5rem;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
}

/* Responsive Design */
@media (max-width: 768px) {
    .course-edit-header {
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
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
    }
    
    .course-edit-card {
        margin-bottom: 1rem;
    }
    
    .course-edit-card-header {
        padding: 0.75rem 1rem;
    }
    
    .course-edit-card .card-body {
        padding: 1rem;
    }
}

@media (max-width: 576px) {
    .course-edit-header {
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
<!-- Modern Course Edit Header with Back Button -->
<div class="course-edit-header">
    <div class="container">
        <!-- Back Button Row -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-start">
                    <a href="courses.php" class="btn btn-outline-light btn-back-icon" title="Back to Courses">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Header Content -->
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-3">
                    <i class="bi bi-pencil-square me-3"></i>Edit Course
                </h1>
                <p class="mb-0 opacity-90">
                    <strong><?= htmlspecialchars($course['course_name']) ?></strong> • 
                    <?= htmlspecialchars($course['course_code']) ?> • 
                    Academic Year <?= $academic_year_id ?? 'N/A' ?>
                </p>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="course-stats">
                        <div class="course-stat-item">
                            <span class="course-stat-number"><?= $academic_year_id ?? 'N/A' ?></span>
                            <span class="course-stat-label">Academic Year</span>
                        </div>
                        <div class="course-stat-item">
                            <span class="course-stat-number"><?= $course['created_at'] ? date('M j', strtotime($course['created_at'])) : 'N/A' ?></span>
                            <span class="course-stat-label">Created</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-bottom: 340px;">
    <!-- Course Edit Cards -->
    <div class="row">
        <!-- Course Name Card -->
        <div class="col-md-6 mb-4">
            <div class="card course-edit-card border-0 shadow-sm h-100">
                <div class="card-header course-edit-card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-book me-2"></i>Course Name
                    </h6>
                </div>
                <div class="card-body">
                    <form method="post" action="course_edit.php?id=<?= $course_id ?>">
                        <div class="form-group">
                            <input type="text" class="form-control" id="course_name" name="course_name" 
                                   required value="<?= htmlspecialchars($course['course_name']) ?>"
                                   placeholder="Enter course name">
                        </div>
                </div>
            </div>
        </div>

        <!-- Course Code Card -->
        <div class="col-md-6 mb-4">
            <div class="card course-edit-card border-0 shadow-sm h-100">
                <div class="card-header course-edit-card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-tag me-2"></i>Course Code
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <input type="text" class="form-control" id="course_code" name="course_code" 
                               required value="<?= htmlspecialchars($course['course_code']) ?>"
                               placeholder="Enter course code">
                    </div>
                </div>
            </div>
        </div>

        <!-- Description Card -->
        <div class="col-12 mb-4">
            <div class="card course-edit-card border-0 shadow-sm">
                <div class="card-header course-edit-card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-text-paragraph me-2"></i>Course Description
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <textarea class="form-control" id="description" name="description" rows="4"
                                  placeholder="Enter course description"><?= htmlspecialchars($course['description']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons Card -->
        <div class="col-12">
            <div class="card course-edit-card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Save Changes
                        </button>
                        <a href="courses.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
                    </form>
    </div>
</div>
<?php include '../includes/footer.php'; ?> 