<?php
require_once '../includes/header.php';
requireRole('admin');

// Handle create, edit, delete actions
$message = '';
$message_type = '';

// Handle badge creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['badge_name'] ?? '');
    $desc = trim($_POST['badge_description'] ?? '');
    $type = trim($_POST['badge_type'] ?? '');
    $criteria = trim($_POST['criteria'] ?? '');
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
    $stmt = $db->prepare('INSERT INTO badges (badge_name, badge_description, badge_icon, badge_type, criteria) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $desc, $icon, $type, $criteria]);
    $message = 'Badge created successfully!';
    $message_type = 'success';
}
// Handle badge edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['badge_id'];
    $name = trim($_POST['badge_name'] ?? '');
    $desc = trim($_POST['badge_description'] ?? '');
    $type = trim($_POST['badge_type'] ?? '');
    $criteria = trim($_POST['criteria'] ?? '');
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
    if ($icon) {
        $stmt = $db->prepare('UPDATE badges SET badge_name=?, badge_description=?, badge_icon=?, badge_type=?, criteria=? WHERE id=?');
        $stmt->execute([$name, $desc, $icon, $type, $criteria, $id]);
    } else {
        $stmt = $db->prepare('UPDATE badges SET badge_name=?, badge_description=?, badge_type=?, criteria=? WHERE id=?');
        $stmt->execute([$name, $desc, $type, $criteria, $id]);
    }
    $message = 'Badge updated successfully!';
    $message_type = 'success';
}
// Handle badge delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['badge_id'];
    $stmt = $db->prepare('DELETE FROM badges WHERE id=?');
    $stmt->execute([$id]);
    $message = 'Badge deleted.';
    $message_type = 'success';
}
// Fetch all badges
$stmt = $db->prepare('SELECT * FROM badges ORDER BY id');
$stmt->execute();
$badges = $stmt->fetchAll();
?>
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Badge Management</h1>
            <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createBadgeModal"><i class="bi bi-plus-circle me-1"></i>New Badge</a>
        </div>
    </div>
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
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
                                    <th>Criteria</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($badges as $badge): ?>
                                <tr>
                                    <td><img src="../uploads/badges/<?php echo htmlspecialchars($badge['badge_icon'] ?: 'default.png'); ?>" alt="icon" style="height:40px;"></td>
                                    <td><?php echo htmlspecialchars($badge['badge_name']); ?></td>
                                    <td><?php echo htmlspecialchars($badge['badge_description']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$badge['badge_type']))); ?></span></td>
                                    <td><code><?php echo htmlspecialchars($badge['criteria']); ?></code></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editBadgeModal<?php echo $badge['id']; ?>"><i class="bi bi-pencil"></i></a>
                                        <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteBadgeModal<?php echo $badge['id']; ?>"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <!-- Edit Badge Modal -->
                                <div class="modal fade" id="editBadgeModal<?php echo $badge['id']; ?>" tabindex="-1" aria-labelledby="editBadgeLabel<?php echo $badge['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editBadgeLabel<?php echo $badge['id']; ?>">Edit Badge</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Badge Name</label>
                                                        <input type="text" class="form-control" name="badge_name" value="<?php echo htmlspecialchars($badge['badge_name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea class="form-control" name="badge_description" required><?php echo htmlspecialchars($badge['badge_description']); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Type</label>
                                                        <select class="form-select" name="badge_type" required>
                                                            <option value="course_completion" <?= $badge['badge_type']==='course_completion'?'selected':'' ?>>Course Completion</option>
                                                            <option value="high_score" <?= $badge['badge_type']==='high_score'?'selected':'' ?>>High Score</option>
                                                            <option value="participation" <?= $badge['badge_type']==='participation'?'selected':'' ?>>Participation</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Criteria (JSON)</label>
                                                        <input type="text" class="form-control" name="criteria" value="<?php echo htmlspecialchars($badge['criteria']); ?>" required>
                                                        <div class="form-text">e.g. {"assessments_taken": 3}</div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Icon (optional)</label>
                                                        <input type="file" class="form-control" name="badge_icon" accept="image/png,image/jpeg">
                                                        <div class="form-text">Leave blank to keep current icon.</div>
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
                                <div class="modal fade" id="deleteBadgeModal<?php echo $badge['id']; ?>" tabindex="-1" aria-labelledby="deleteBadgeLabel<?php echo $badge['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteBadgeLabel<?php echo $badge['id']; ?>">Delete Badge</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete this badge?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Create Badge Modal -->
    <div class="modal fade" id="createBadgeModal" tabindex="-1" aria-labelledby="createBadgeLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createBadgeLabel">Create New Badge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Badge Name</label>
                            <input type="text" class="form-control" name="badge_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="badge_description" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="badge_type" required>
                                <option value="course_completion">Course Completion</option>
                                <option value="high_score">High Score</option>
                                <option value="participation">Participation</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Criteria (JSON)</label>
                            <input type="text" class="form-control" name="criteria" required>
                            <div class="form-text">e.g. {"assessments_taken": 3}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icon (optional)</label>
                            <input type="file" class="form-control" name="badge_icon" accept="image/png,image/jpeg">
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
</div>
<?php require_once '../includes/footer.php'; ?> 