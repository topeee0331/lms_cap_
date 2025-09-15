<?php
$page_title = 'Manage Academic Periods';
require_once '../includes/header.php';
requireRole('admin');
?>

<style>
/* Statistics Cards Styling */
.stats-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.stats-icon {
    width: 60px;
    height: 60px;
    transition: all 0.3s ease;
}

.stats-card:hover .stats-icon {
    transform: scale(1.1);
}

.stats-primary {
    background: #0d6efd;
    border-left: 4px solid #0a58ca;
    color: white;
}

.stats-success {
    background: #198754;
    border-left: 4px solid #146c43;
    color: white;
}

.stats-info {
    background: #0dcaf0;
    border-left: 4px solid #0aa2c0;
    color: white;
}

.stats-warning {
    background: #ffc107;
    border-left: 4px solid #ffca2c;
    color: #000;
}

.stats-secondary {
    background: #6c757d;
    border-left: 4px solid #5c636a;
    color: white;
}

.stats-danger {
    background: #dc3545;
    border-left: 4px solid #b02a37;
    color: white;
}

.stats-danger-alt {
    background: #e91e63;
    border-left: 4px solid #d81b60;
    color: white;
}

.stats-purple {
    background: #9c27b0;
    border-left: 4px solid #7b1fa2;
    color: white;
}

/* Scrollable Table Container */
.table-scrollable {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}

.table-scrollable::-webkit-scrollbar {
    width: 8px;
}

.table-scrollable::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-scrollable::-webkit-scrollbar-thumb {
    background: #2E5E4E;
    border-radius: 4px;
}

.table-scrollable::-webkit-scrollbar-thumb:hover {
    background: #7DCB80;
}

/* Firefox scrollbar styling */
.table-scrollable {
    scrollbar-width: thin;
    scrollbar-color: #2E5E4E #f1f1f1;
}

/* Ensure table header stays visible */
.table-scrollable .table thead th {
    position: sticky;
    top: 0;
    background-color: #f8f9fa;
    z-index: 10;
    border-bottom: 2px solid #dee2e6;
}
</style>

<?php

// Handle add academic period
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_period'])) {
    $academic_year = trim($_POST['academic_year']);
    $semester_name = trim($_POST['semester_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($academic_year !== '' && $semester_name !== '') {
        if ($is_active) {
            // Set all other academic periods to inactive
            $db->exec("UPDATE academic_periods SET is_active = 0");
        }
        
        $stmt = $db->prepare("INSERT INTO academic_periods (academic_year, semester_name, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$academic_year, $semester_name, $start_date, $end_date, $is_active]);
        echo "<script>window.location.href='academic_periods.php';</script>";
        exit;
    } else {
        echo '<div class="alert alert-danger">Academic year and semester name are required.</div>';
    }
}

// Handle delete academic period
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_period_id'])) {
    $del_id = intval($_POST['delete_period_id']);
    
    // Check if this period is being used by courses
    $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE academic_period_id = ?");
    $stmt->execute([$del_id]);
    $course_count = $stmt->fetchColumn();
    
    if ($course_count > 0) {
        echo '<div class="alert alert-danger">Cannot delete academic period. It is being used by ' . $course_count . ' course(s).</div>';
    } else {
        $stmt = $db->prepare("DELETE FROM academic_periods WHERE id = ?");
    $stmt->execute([$del_id]);
        echo "<script>window.location.href='academic_periods.php';</script>";
    exit;
    }
}

// Handle edit academic period (load data)
$edit_period = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_period_id'])) {
    $edit_id = intval($_POST['edit_period_id']);
    $stmt = $db->prepare("SELECT * FROM academic_periods WHERE id = ?");
    $stmt->execute([$edit_id]);
    if ($stmt->rowCount() > 0) {
        $edit_period = $stmt->fetch();
    }
}

// Handle update academic period
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_period']) && isset($_POST['period_id'])) {
    $update_id = intval($_POST['period_id']);
    $academic_year = trim($_POST['academic_year']);
    $semester_name = trim($_POST['semester_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($academic_year !== '' && $semester_name !== '') {
        if ($is_active) {
            // Set all other academic periods to inactive
            $db->exec("UPDATE academic_periods SET is_active = 0 WHERE id != $update_id");
        }
        
        $stmt = $db->prepare("UPDATE academic_periods SET academic_year = ?, semester_name = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$academic_year, $semester_name, $start_date, $end_date, $is_active, $update_id]);
        echo "<script>window.location.href='academic_periods.php';</script>";
        exit;
    } else {
        echo '<div class="alert alert-danger">Academic year and semester name are required.</div>';
    }
}

// Fetch all academic periods
$periods = [];
$period_sql = "SELECT * FROM academic_periods ORDER BY academic_year DESC, semester_name";
$res = $db->query($period_sql);
if ($res && $res->rowCount() > 0) {
    $periods = $res->fetchAll();
}

// Get statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_periods,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_periods,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_periods,
        COUNT(DISTINCT c.id) as total_courses
    FROM academic_periods ap
    LEFT JOIN courses c ON ap.id = c.academic_period_id
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch();
?>

<div class="container-fluid py-4">
    <!-- Navigation Back to Dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <a href="../admin/dashboard.php" class="btn btn-outline-primary mb-3">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stats-card stats-primary border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-calendar-event fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['total_periods'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Total Periods</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stats-card stats-success border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-check-circle fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['active_periods'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Active Periods</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stats-card stats-danger border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-danger text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-x-circle fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['inactive_periods'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Inactive Periods</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stats-card stats-info border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-book fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-white"><?= $stats['total_courses'] ?></h3>
                    <p class="text-white mb-0 small fw-medium">Total Courses</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Header and Add Button -->
    <div class="row mb-4">
        <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Manage Academic Periods</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPeriodModal">
                    <i class="bi bi-plus-circle me-2"></i>Add Academic Period
                        </button>
            </div>
        </div>
    </div>

    <!-- Academic Periods Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Academic Periods</h5>
                    </div>
        <div class="card-body">
            <?php if (empty($periods)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No academic periods found.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-scrollable">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Courses</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($periods as $period): ?>
                                <?php
                                // Get course count for this period
                                $course_count_stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE academic_period_id = ?");
                                $course_count_stmt->execute([$period['id']]);
                                $course_count = $course_count_stmt->fetchColumn();
                                ?>
                                    <tr>
                                            <td>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($period['academic_year']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($period['semester_name']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                        <small class="text-muted">
                                            <?= $period['start_date'] ? date('M j, Y', strtotime($period['start_date'])) : 'Not set' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $period['end_date'] ? date('M j, Y', strtotime($period['end_date'])) : 'Not set' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($period['is_active']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                <i class="bi bi-x-circle me-1"></i>Inactive
                                                    </span>
                                            <?php endif; ?>
                                        </td>
                                            <td>
                                        <span class="badge bg-info"><?= $course_count ?> courses</span>
                                            </td>
                                            <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                    data-bs-target="#editPeriodModal<?= $period['id'] ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                            <?php if ($course_count == 0): ?>
                                                <form method="post" action="academic_periods.php" 
                                                      style="display:inline;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this academic period?');">
                                                    <input type="hidden" name="delete_period_id" value="<?= $period['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                            </form>
                                            <?php endif; ?>
                                                                        </div>
                                            </td>
                                        </tr>
                                        
                                <!-- Edit Period Modal -->
                                <div class="modal fade" id="editPeriodModal<?= $period['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                            <form method="post" action="academic_periods.php">
                                                        <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">
                                                        <i class="bi bi-pencil-square me-2"></i>Edit Academic Period
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                    <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="academic_year_edit<?= $period['id'] ?>" class="form-label">Academic Year</label>
                                                                <input type="text" class="form-control" id="academic_year_edit<?= $period['id'] ?>" 
                                                                       name="academic_year" value="<?= htmlspecialchars($period['academic_year']) ?>" required>
                                                                </div>
                                                            </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="semester_name_edit<?= $period['id'] ?>" class="form-label">Semester Name</label>
                                                                <input type="text" class="form-control" id="semester_name_edit<?= $period['id'] ?>" 
                                                                       name="semester_name" value="<?= htmlspecialchars($period['semester_name']) ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                                <div class="mb-3">
                                                                <label for="start_date_edit<?= $period['id'] ?>" class="form-label">Start Date</label>
                                                                <input type="date" class="form-control" id="start_date_edit<?= $period['id'] ?>" 
                                                                       name="start_date" value="<?= $period['start_date'] ?>">
                                                            </div>
                                                            </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="end_date_edit<?= $period['id'] ?>" class="form-label">End Date</label>
                                                                <input type="date" class="form-control" id="end_date_edit<?= $period['id'] ?>" 
                                                                       name="end_date" value="<?= $period['end_date'] ?>">
                                                    </div>
                                                </div>
                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="is_active_edit<?= $period['id'] ?>" 
                                                                   name="is_active" <?= $period['is_active'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="is_active_edit<?= $period['id'] ?>">
                                                                Active Period
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_period" class="btn btn-primary">Update Period</button>
                                                        </div>
                                                    </form>
                                                    </div>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Period Modal -->
<div class="modal fade" id="addPeriodModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
            <form method="post" action="academic_periods.php">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Add New Academic Period
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="academic_year_add" class="form-label">Academic Year</label>
                                <input type="text" class="form-control" id="academic_year_add" 
                                       name="academic_year" placeholder="e.g., 2024-2025" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="semester_name_add" class="form-label">Semester Name</label>
                                <input type="text" class="form-control" id="semester_name_add" 
                                       name="semester_name" placeholder="e.g., First Semester" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date_add" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date_add" name="start_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                        <div class="mb-3">
                                <label for="end_date_add" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date_add" name="end_date">
                            </div>
                        </div>
                        </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active_add" name="is_active">
                            <label class="form-check-label" for="is_active_add">
                                Active Period
                            </label>
                        </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_period" class="btn btn-success">Add Period</button>
                    </div>
                </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 