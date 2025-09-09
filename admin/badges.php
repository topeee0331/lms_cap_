<?php
require_once '../config/config.php';
requireRole('admin');
require_once '../includes/header.php';
?>

<style>
/* Scrollable Table Container */
.table-scrollable {
    max-height: 600px;
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

// Handle create, edit, delete actions
$message = '';
$message_type = '';

// Define available badge icons for consistency (using same icons as student system)
$available_badge_icons = [
    'bi-trophy' => 'Trophy',
    'bi-award' => 'Award',
    'bi-star' => 'Star',
    'bi-star-fill' => 'Star Fill',
    'bi-gem' => 'Gem',
    'bi-crown' => 'Crown',
    'bi-shield-check' => 'Shield Check',
    'bi-shield-star' => 'Shield Star',
    'bi-patch-check' => 'Patch Check',
    'bi-patch-check-fill' => 'Patch Check Fill',
    'bi-emoji-smile' => 'Smile',
    'bi-emoji-heart-eyes' => 'Heart Eyes',
    'bi-emoji-laughing' => 'Laughing',
    'bi-emoji-wink' => 'Wink',
    'bi-fire' => 'Fire',
    'bi-lightning' => 'Lightning',
    'bi-rocket' => 'Rocket',
    'bi-target' => 'Target',
    'bi-flag' => 'Flag',
    'bi-flag-fill' => 'Flag Fill',
    'bi-heart' => 'Heart',
    'bi-heart-fill' => 'Heart Fill',
    'bi-hand-thumbs-up' => 'Thumbs Up',
    'bi-hand-thumbs-up-fill' => 'Thumbs Up Fill',
    'bi-mortarboard' => 'Graduation Cap',
    'bi-mortarboard-fill' => 'Graduation Cap Fill',
    'bi-book' => 'Book',
    'bi-book-fill' => 'Book Fill',
    'bi-code-slash' => 'Code',
    'bi-database' => 'Database',
    'bi-laptop' => 'Laptop',
    'bi-cpu' => 'CPU',
    'bi-brain' => 'Brain',
    'bi-lightbulb' => 'Lightbulb',
    'bi-lightbulb-fill' => 'Lightbulb Fill',
    'bi-puzzle' => 'Puzzle',
    'bi-puzzle-fill' => 'Puzzle Fill',
    'bi-person-badge' => 'Person Badge',
    'bi-person-check' => 'Person Check',
    'bi-people' => 'People',
    'bi-people-fill' => 'People Fill',
    'bi-person-circle' => 'Person Circle',
    'bi-person-circle-fill' => 'Person Circle Fill'
];

// Handle badge creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['badge_name'] ?? '');
    $desc = trim($_POST['badge_description'] ?? '');
    $type = trim($_POST['badge_type'] ?? '');
    $criteria = trim($_POST['criteria'] ?? '');
    $icon = trim($_POST['badge_icon_select'] ?? '');
    
    // Handle file upload if provided
    if (isset($_FILES['badge_icon_file']) && $_FILES['badge_icon_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['badge_icon_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','gif','svg'])) {
            $icon = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
            $upload_dir = dirname(__DIR__) . '/uploads/badges/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            move_uploaded_file($_FILES['badge_icon_file']['tmp_name'], $upload_dir . $icon);
        } else {
            $message = 'Invalid file format. Please upload PNG, JPG, JPEG, GIF, or SVG files only.';
            $message_type = 'danger';
        }
    }
    
    if (empty($message)) {
    $stmt = $db->prepare('INSERT INTO badges (badge_name, badge_description, badge_icon, badge_type, criteria) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $desc, $icon, $type, $criteria]);
    $message = 'Badge created successfully!';
    $message_type = 'success';
    }
}
// Handle badge edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['badge_id'];
    $name = trim($_POST['badge_name'] ?? '');
    $desc = trim($_POST['badge_description'] ?? '');
    $type = trim($_POST['badge_type'] ?? '');
    $criteria = trim($_POST['criteria'] ?? '');
    $icon = trim($_POST['badge_icon_select'] ?? '');
    
    // Handle file upload if provided
    if (isset($_FILES['badge_icon_file']) && $_FILES['badge_icon_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['badge_icon_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','gif','svg'])) {
            $icon = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
            $upload_dir = dirname(__DIR__) . '/uploads/badges/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            move_uploaded_file($_FILES['badge_icon_file']['tmp_name'], $upload_dir . $icon);
        } else {
            $message = 'Invalid file format. Please upload PNG, JPG, JPEG, GIF, or SVG files only.';
            $message_type = 'danger';
        }
    }
    
    if (empty($message)) {
        $stmt = $db->prepare('UPDATE badges SET badge_name=?, badge_description=?, badge_icon=?, badge_type=?, criteria=? WHERE id=?');
        $stmt->execute([$name, $desc, $icon, $type, $criteria, $id]);
    $message = 'Badge updated successfully!';
    $message_type = 'success';
    }
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
                    <div class="table-scrollable">
                        <table class="table table-hover align-middle mb-0">
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
                                    <td>
                                        <?php if (strpos($badge['badge_icon'], 'bi-') === 0): ?>
                                            <div class="badge-icon-display">
                                                <i class="bi <?php echo htmlspecialchars($badge['badge_icon']); ?>" style="font-size: 2rem; color: #007bff;"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="badge-icon-display">
                                                <img src="../uploads/badges/<?php echo htmlspecialchars($badge['badge_icon'] ?: 'default.png'); ?>" 
                                                     alt="Badge Icon" 
                                                     class="badge-image-icon"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                <i class="bi bi-image" style="font-size: 2rem; color: #6c757d; display:none;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
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
                                                        <label class="form-label">Icon</label>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Select Icon</label>
                                                                <select class="form-select" name="badge_icon_select" id="editIconSelect<?php echo $badge['id']; ?>">
                                                                    <option value="">Choose an icon...</option>
                                                                    <?php foreach ($available_badge_icons as $icon_class => $icon_name): ?>
                                                                        <option value="<?php echo $icon_class; ?>" <?= $badge['badge_icon'] === $icon_class ? 'selected' : '' ?>><?php echo $icon_name; ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Or Upload File</label>
                                                                <input type="file" class="form-control" name="badge_icon_file" accept="image/png,image/jpeg,image/gif,image/svg">
                                                            </div>
                                                        </div>
                                                        <div class="mt-2">
                                                            <label class="form-label small">Current Icon Preview:</label>
                                                            <div class="badge-icon-preview" id="editIconPreview<?php echo $badge['id']; ?>">
                                                                <?php if (strpos($badge['badge_icon'], 'bi-') === 0): ?>
                                                                    <i class="bi <?php echo htmlspecialchars($badge['badge_icon']); ?>" style="font-size: 2rem; color: #007bff;"></i>
                                                                <?php else: ?>
                                                                    <img src="../uploads/badges/<?php echo htmlspecialchars($badge['badge_icon'] ?: 'default.png'); ?>" 
                                                                         alt="Current Icon" 
                                                                         class="badge-image-icon"
                                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                                    <i class="bi bi-image" style="font-size: 2rem; color: #6c757d; display:none;"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="form-text">Choose an icon from the dropdown or upload a custom image file.</div>
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
                            <label class="form-label">Icon</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label small">Select Icon</label>
                                    <select class="form-select" name="badge_icon_select" id="createIconSelect">
                                        <option value="">Choose an icon...</option>
                                        <?php foreach ($available_badge_icons as $icon_class => $icon_name): ?>
                                            <option value="<?php echo $icon_class; ?>"><?php echo $icon_name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Or Upload File</label>
                                    <input type="file" class="form-control" name="badge_icon_file" accept="image/png,image/jpeg,image/gif,image/svg">
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label small">Icon Preview:</label>
                                <div class="badge-icon-preview" id="createIconPreview">
                                    <i class="bi bi-image" style="font-size: 2rem; color: #6c757d;">Select an icon to preview</i>
                                </div>
                            </div>
                            <div class="form-text">Choose an icon from the dropdown or upload a custom image file.</div>
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

<style>
.badge-icon-display {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 8px;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
}

.badge-image-icon {
    max-width: 40px;
    max-height: 40px;
    object-fit: contain;
    border-radius: 4px;
}

.badge-icon-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    margin: 0 auto;
}

.badge-icon-preview i {
    margin: 0;
}

.icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
    gap: 10px;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 10px;
    background: #fff;
}

.icon-option {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    background: #fff;
}

.icon-option:hover {
    border-color: #007bff;
    background: #f8f9fa;
}

.icon-option.selected {
    border-color: #007bff;
    background: #e7f3ff;
}

.icon-option i {
    font-size: 1.5rem;
    color: #6c757d;
}

.icon-option.selected i {
    color: #007bff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle create modal icon selection
    const createIconSelect = document.getElementById('createIconSelect');
    const createIconPreview = document.getElementById('createIconPreview');
    
    if (createIconSelect && createIconPreview) {
        createIconSelect.addEventListener('change', function() {
            const selectedIcon = this.value;
            if (selectedIcon) {
                createIconPreview.innerHTML = `<i class="bi ${selectedIcon}" style="font-size: 2rem; color: #007bff;"></i>`;
            } else {
                createIconPreview.innerHTML = '<i class="bi bi-image" style="font-size: 2rem; color: #6c757d;">Select an icon to preview</i>';
            }
        });
    }
    
    // Handle edit modals icon selection
    document.querySelectorAll('[id^="editIconSelect"]').forEach(select => {
        const modalId = select.id.replace('editIconSelect', '');
        const preview = document.getElementById('editIconPreview' + modalId);
        
        if (preview) {
            select.addEventListener('change', function() {
                const selectedIcon = this.value;
                if (selectedIcon) {
                    preview.innerHTML = `<i class="bi ${selectedIcon}" style="font-size: 2rem; color: #007bff;"></i>`;
                } else {
                    // Keep current icon display
                    const currentIcon = preview.querySelector('i, img');
                    if (currentIcon) {
                        preview.innerHTML = currentIcon.outerHTML;
                    }
                }
            });
        }
    });
    
    // Handle file upload preview
    document.querySelectorAll('input[type="file"][name="badge_icon_file"]').forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = this.closest('.mb-3').querySelector('.badge-icon-preview');
                    if (preview) {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="badge-image-icon" style="max-width: 50px; max-height: 50px;">`;
                    }
                }.bind(this);
                reader.readAsDataURL(file);
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?> 