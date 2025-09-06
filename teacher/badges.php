<?php
$page_title = 'Badge Management';
require_once '../includes/header.php';
requireRole('teacher');

$message = '';
$message_type = '';

// Check if created_by column exists
$stmt = $db->prepare("SHOW COLUMNS FROM badges LIKE 'created_by'");
$stmt->execute();
$has_created_by = $stmt->fetch();

// Handle badge creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['badge_name'] ?? '');
    $desc = trim($_POST['badge_description'] ?? '');
    $type = trim($_POST['badge_type'] ?? '');
    $criteria = trim($_POST['criteria'] ?? '');
    $points_value = (int)($_POST['points_value'] ?? 0);
    $icon = null;
    
    if (isset($_FILES['badge_icon']) && $_FILES['badge_icon']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['badge_icon']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg'])) {
            $icon = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
            $upload_dir = dirname(__DIR__) . '/uploads/badges/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            move_uploaded_file($_FILES['badge_icon']['tmp_name'], $upload_dir . $icon);
        }
    }
    
    if (empty($name) || empty($desc) || empty($type) || empty($criteria)) {
        $message = 'All fields are required.';
        $message_type = 'danger';
    } else {
        // Validate JSON criteria
        $decoded_criteria = json_decode($criteria, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $message = 'Invalid JSON format for criteria.';
            $message_type = 'danger';
        } else {
                                if ($has_created_by) {
                        $stmt = $db->prepare('INSERT INTO badges (badge_name, badge_description, badge_icon, badge_type, criteria, points_value, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$name, $desc, $icon, $type, $criteria, $points_value, $_SESSION['user_id']]);
                    } else {
                        $stmt = $db->prepare('INSERT INTO badges (badge_name, badge_description, badge_icon, badge_type, criteria, points_value) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$name, $desc, $icon, $type, $criteria, $points_value]);
                    }
            $message = 'Badge created successfully!';
            $message_type = 'success';
        }
    }
}

// Handle badge edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['badge_id'];
    $name = trim($_POST['badge_name'] ?? '');
    $desc = trim($_POST['badge_description'] ?? '');
    $type = trim($_POST['badge_type'] ?? '');
    $criteria = trim($_POST['criteria'] ?? '');
    $points_value = (int)($_POST['points_value'] ?? 0);
    $icon = null;
    
    // Verify teacher owns this badge
    $stmt = $db->prepare('SELECT id FROM badges WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        $message = 'You can only edit badges you created.';
        $message_type = 'danger';
    } else {
        if (isset($_FILES['badge_icon']) && $_FILES['badge_icon']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['badge_icon']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg'])) {
                $icon = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
                $upload_dir = dirname(__DIR__) . '/uploads/badges/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                move_uploaded_file($_FILES['badge_icon']['tmp_name'], $upload_dir . $icon);
            }
        }
        
        if (empty($name) || empty($desc) || empty($type) || empty($criteria)) {
            $message = 'All fields are required.';
            $message_type = 'danger';
        } else {
            // Validate JSON criteria
            $decoded_criteria = json_decode($criteria, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $message = 'Invalid JSON format for criteria.';
                $message_type = 'danger';
            } else {
                if ($icon) {
                    $stmt = $db->prepare('UPDATE badges SET badge_name=?, badge_description=?, badge_icon=?, badge_type=?, criteria=?, points_value=? WHERE id=? AND created_by=?');
                    $stmt->execute([$name, $desc, $icon, $type, $criteria, $points_value, $id, $_SESSION['user_id']]);
                } else {
                    $stmt = $db->prepare('UPDATE badges SET badge_name=?, badge_description=?, badge_type=?, criteria=?, points_value=? WHERE id=? AND created_by=?');
                    $stmt->execute([$name, $desc, $type, $criteria, $points_value, $id, $_SESSION['user_id']]);
                }
                $message = 'Badge updated successfully!';
                $message_type = 'success';
            }
        }
    }
}

// Handle badge delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['badge_id'];
    
    // Verify teacher owns this badge
    $stmt = $db->prepare('SELECT id FROM badges WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        $message = 'You can only delete badges you created.';
        $message_type = 'danger';
    } else {
        $stmt = $db->prepare('DELETE FROM badges WHERE id=? AND created_by=?');
        $stmt->execute([$id, $_SESSION['user_id']]);
        $message = 'Badge deleted successfully.';
        $message_type = 'success';
    }
}

if ($has_created_by) {
    // Fetch teacher's badges and system badges
    $stmt = $db->prepare('
        SELECT b.*, u.first_name, u.last_name,
               CASE WHEN b.created_by = ? THEN 1 ELSE 0 END as is_teacher_badge
        FROM badges b
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.created_by = ? OR b.created_by IS NULL
        ORDER BY b.created_at DESC
    ');
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $badges = $stmt->fetchAll();
} else {
    // Fallback for older badges table structure
    $stmt = $db->prepare('
        SELECT b.*, NULL as first_name, NULL as last_name, 0 as is_teacher_badge
        FROM badges b
        ORDER BY b.created_at DESC
    ');
    $stmt->execute();
    $badges = $stmt->fetchAll();
}

// Get badge statistics
$stmt = $db->prepare('
    SELECT 
        COUNT(*) as total_badges,
        COUNT(CASE WHEN created_by = ? THEN 1 END) as teacher_badges,
        COUNT(CASE WHEN created_by IS NULL THEN 1 END) as system_badges
    FROM badges
');
$stmt->execute([$_SESSION['user_id']]);
$badge_stats = $stmt->fetch();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">Badge Management</h1>
                    <p class="text-muted mb-0">Create and manage badges for your students</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createBadgeModal">
                        <i class="bi bi-plus-circle me-1"></i>Create Badge
                    </button>
                    <a href="student_badges.php" class="btn btn-outline-info">
                        <i class="bi bi-eye me-1"></i>View Student Badges
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Badge Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $badge_stats['total_badges'] ?></h4>
                            <small>Total Badges</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-trophy fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $badge_stats['teacher_badges'] ?></h4>
                            <small>Your Badges</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-person-badge fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $badge_stats['system_badges'] ?></h4>
                            <small>System Badges</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-gear fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Badges List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">All Badges</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Icon</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Points</th>
                                    <th>Creator</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($badges as $badge): ?>
                                <tr>
                                    <td>
                                        <img src="../uploads/badges/<?php echo htmlspecialchars($badge['badge_icon'] ?: 'default.png'); ?>" 
                                             alt="icon" style="height:40px;" class="rounded">
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($badge['badge_name']); ?></div>
                                        <?php if ($badge['is_teacher_badge']): ?>
                                            <span class="badge bg-success">Your Badge</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">System Badge</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($badge['badge_description']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $badge['badge_type'] === 'course_completion' ? 'primary' : 
                                                ($badge['badge_type'] === 'high_score' ? 'warning' : 
                                                ($badge['badge_type'] === 'participation' ? 'info' : 'secondary')); 
                                        ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$badge['badge_type']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?= $badge['points_value'] ?> pts</span>
                                    </td>
                                    <td>
                                        <?php if ($badge['first_name']): ?>
                                            <?php echo htmlspecialchars($badge['first_name'] . ' ' . $badge['last_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($badge['is_teacher_badge']): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBadgeModal<?php echo $badge['id']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteBadgeModal<?php echo $badge['id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Read Only</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Badge Modal -->
<div class="modal fade" id="createBadgeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Badge</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Badge Name</label>
                            <input type="text" class="form-control" name="badge_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Badge Type</label>
                            <select class="form-select" name="badge_type" required>
                                <option value="participation">Participation</option>
                                <option value="course_completion">Course Completion</option>
                                <option value="high_score">High Score</option>
                                <option value="streak">Learning Streak</option>
                                <option value="special">Special Achievement</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="badge_description" rows="3" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Points Value</label>
                            <input type="number" class="form-control" name="points_value" value="10" min="0" max="1000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Icon (optional)</label>
                            <input type="file" class="form-control" name="badge_icon" accept="image/png,image/jpeg">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Criteria (JSON)</label>
                        <textarea class="form-control" name="criteria" rows="4" required placeholder='{"assessments_taken": 3}'></textarea>
                        <div class="form-text">
                            <strong>Examples:</strong><br>
                            • <code>{"assessments_taken": 3}</code> - Complete 3 assessments<br>
                            • <code>{"courses_completed": 1}</code> - Complete 1 course<br>
                            • <code>{"average_score": 90}</code> - Maintain 90% average score<br>
                            • <code>{"videos_watched": 20}</code> - Watch 20 videos
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Badge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($badges as $badge): ?>
    <?php if ($badge['is_teacher_badge']): ?>
    <!-- Edit Badge Modal -->
    <div class="modal fade" id="editBadgeModal<?php echo $badge['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Badge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Badge Name</label>
                                <input type="text" class="form-control" name="badge_name" value="<?php echo htmlspecialchars($badge['badge_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Badge Type</label>
                                <select class="form-select" name="badge_type" required>
                                    <option value="participation" <?= $badge['badge_type']==='participation'?'selected':'' ?>>Participation</option>
                                    <option value="course_completion" <?= $badge['badge_type']==='course_completion'?'selected':'' ?>>Course Completion</option>
                                    <option value="high_score" <?= $badge['badge_type']==='high_score'?'selected':'' ?>>High Score</option>
                                    <option value="streak" <?= $badge['badge_type']==='streak'?'selected':'' ?>>Learning Streak</option>
                                    <option value="special" <?= $badge['badge_type']==='special'?'selected':'' ?>>Special Achievement</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="badge_description" rows="3" required><?php echo htmlspecialchars($badge['badge_description']); ?></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Points Value</label>
                                <input type="number" class="form-control" name="points_value" value="<?= $badge['points_value'] ?>" min="0" max="1000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Icon (optional)</label>
                                <input type="file" class="form-control" name="badge_icon" accept="image/png,image/jpeg">
                                <div class="form-text">Leave blank to keep current icon.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Criteria (JSON)</label>
                            <textarea class="form-control" name="criteria" rows="4" required><?php echo htmlspecialchars($badge['criteria']); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Badge Modal -->
    <div class="modal fade" id="deleteBadgeModal<?php echo $badge['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Badge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the badge "<strong><?php echo htmlspecialchars($badge['badge_name']); ?></strong>"?</p>
                        <p class="text-danger"><small>This action cannot be undone.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Badge</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php require_once '../includes/footer.php'; ?>
