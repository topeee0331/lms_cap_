<?php
require_once '../config/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_order':
                $assessment_id = sanitizeInput($_POST['assessment_id'] ?? '');
                $new_order = (int)($_POST['new_order'] ?? 1);
                
                if (empty($assessment_id) || $new_order < 1) {
                    $message = 'Invalid assessment ID or order.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare("UPDATE assessments SET assessment_order = ? WHERE id = ?");
                    $stmt->execute([$new_order, $assessment_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = 'Assessment order updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Assessment not found or no changes made.';
                        $message_type = 'warning';
                    }
                }
                break;
                
            case 'bulk_update':
                $updates = $_POST['orders'] ?? [];
                $success_count = 0;
                
                foreach ($updates as $assessment_id => $order) {
                    $assessment_id = sanitizeInput($assessment_id);
                    $order = (int)$order;
                    
                    if (!empty($assessment_id) && $order > 0) {
                        $stmt = $db->prepare("UPDATE assessments SET assessment_order = ? WHERE id = ?");
                        $stmt->execute([$order, $assessment_id]);
                        if ($stmt->rowCount() > 0) {
                            $success_count++;
                        }
                    }
                }
                
                $message = "Updated $success_count assessment(s) successfully.";
                $message_type = 'success';
                break;
        }
    }
}

// Get all assessments with their current orders
$stmt = $db->query("
    SELECT a.id, a.assessment_title, a.assessment_order, c.course_name, u.first_name, u.last_name
    FROM assessments a
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    ORDER BY c.course_name, a.assessment_order, a.assessment_title
");
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group assessments by course
$assessments_by_course = [];
foreach ($assessments as $assessment) {
    $course_name = $assessment['course_name'];
    if (!isset($assessments_by_course[$course_name])) {
        $assessments_by_course[$course_name] = [
            'teacher' => $assessment['first_name'] . ' ' . $assessment['last_name'],
            'assessments' => []
        ];
    }
    $assessments_by_course[$course_name]['assessments'][] = $assessment;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assessment Orders - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .course-section {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .course-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
        }
        .assessment-item {
            border-bottom: 1px solid #f8f9fa;
            padding: 1rem;
            transition: background-color 0.2s;
        }
        .assessment-item:hover {
            background-color: #f8f9fa;
        }
        .assessment-item:last-child {
            border-bottom: none;
        }
        .order-input {
            width: 80px;
        }
        .order-badge {
            font-size: 0.9rem;
            padding: 0.5rem 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard.php">
                                <i class="bi bi-house-door"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="courses.php">
                                <i class="bi bi-book"></i> Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="assessments.php">
                                <i class="bi bi-clipboard-check"></i> Assessments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="manage_assessment_orders.php">
                                <i class="bi bi-sort-numeric-down"></i> Assessment Orders
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-sort-numeric-down text-primary"></i>
                        Manage Assessment Orders
                    </h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-list-ol"></i>
                                    Assessment Order Management
                                </h5>
                                <small class="text-muted">Set the order in which students will take assessments (1 = first assessment)</small>
                            </div>
                            <div class="card-body">
                                <form method="post" id="bulkUpdateForm">
                                    <input type="hidden" name="action" value="bulk_update">
                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                    
                                    <?php foreach ($assessments_by_course as $course_name => $course_data): ?>
                                        <div class="course-section">
                                            <div class="course-header">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-book"></i>
                                                    <?php echo htmlspecialchars($course_name); ?>
                                                </h6>
                                                <small class="opacity-75">
                                                    <i class="bi bi-person"></i>
                                                    Teacher: <?php echo htmlspecialchars($course_data['teacher']); ?>
                                                </small>
                                            </div>
                                            
                                            <?php foreach ($course_data['assessments'] as $assessment): ?>
                                                <div class="assessment-item">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-6">
                                                            <div class="d-flex align-items-center">
                                                                <span class="badge bg-primary order-badge me-3">
                                                                    Order: <?php echo $assessment['assessment_order']; ?>
                                                                </span>
                                                                <div>
                                                                    <h6 class="mb-1"><?php echo htmlspecialchars($assessment['assessment_title']); ?></h6>
                                                                    <small class="text-muted">ID: <?php echo htmlspecialchars($assessment['id']); ?></small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="input-group">
                                                                <span class="input-group-text">
                                                                    <i class="bi bi-sort-numeric-down"></i>
                                                                </span>
                                                                <input type="number" 
                                                                       class="form-control order-input" 
                                                                       name="orders[<?php echo htmlspecialchars($assessment['id']); ?>]"
                                                                       value="<?php echo $assessment['assessment_order']; ?>"
                                                                       min="1" max="100" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button type="button" 
                                                                    class="btn btn-outline-primary btn-sm"
                                                                    onclick="updateSingleOrder('<?php echo htmlspecialchars($assessment['id']); ?>', this.previousElementSibling.querySelector('input').value)">
                                                                <i class="bi bi-check"></i> Update
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="text-center mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-save"></i>
                                            Save All Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Single Update Form (Hidden) -->
    <form id="singleUpdateForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="update_order">
        <input type="hidden" name="assessment_id" id="single_assessment_id">
        <input type="hidden" name="new_order" id="single_new_order">
        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateSingleOrder(assessmentId, newOrder) {
            if (confirm('Update assessment order to ' + newOrder + '?')) {
                document.getElementById('single_assessment_id').value = assessmentId;
                document.getElementById('single_new_order').value = newOrder;
                document.getElementById('singleUpdateForm').submit();
            }
        }
        
        // Auto-save on Enter key
        document.querySelectorAll('.order-input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const assessmentId = this.name.match(/\[(.*?)\]/)[1];
                    updateSingleOrder(assessmentId, this.value);
                }
            });
        });
    </script>
</body>
</html>
