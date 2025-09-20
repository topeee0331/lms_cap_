<?php
require_once '../config/config.php';
requireRole('admin');
require_once '../includes/header.php';
?>

<style>
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* Enhanced Badges Page Styling - Inspired by Admin Dashboard */
:root {
    --main-green: #2E5E4E;
    --accent-green: #7DCB80;
    --highlight-yellow: #FFE066;
    --off-white: #F7FAF7;
    --white: #FFFFFF;
    --text-dark: #2c3e50;
    --text-muted: #6c757d;
    --border-light: #e9ecef;
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 8px rgba(0,0,0,0.12);
    --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
    --border-radius: 8px;
    --border-radius-lg: 12px;
    --border-radius-xl: 20px;
    --transition: all 0.3s ease;
}

/* Page Background */
.page-container {
    background: var(--off-white);
    min-height: 100vh;
}

/* Enhanced Welcome Section */
.welcome-section {
    background: var(--main-green);
    border-radius: var(--border-radius-xl);
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}

.welcome-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
    pointer-events: none;
}

.welcome-title {
    color: white;
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    position: relative;
    z-index: 1;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    font-family: 'Inter', sans-serif;
}

.welcome-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 1.1rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
}

/* Decorative Elements */
.welcome-decoration {
    position: absolute;
    top: 25px;
    right: 20px;
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.welcome-decoration i {
    font-size: 1.5rem;
    color: rgba(255,255,255,0.8);
}

.floating-shapes {
    position: absolute;
    top: 20px;
    right: 100px;
    width: 80px;
    height: 80px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
    z-index: 0;
}

.welcome-section .accent-line {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--accent-green);
    border-radius: 0 0 var(--border-radius-xl) var(--border-radius-xl);
}

/* Badges Container */
.badges-container {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.badges-container .card-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 2px solid var(--accent-green);
    padding: 1.25rem 1.5rem;
}

.badges-container .card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 1.1rem;
}

/* Scrollable Table Container */
.scrollable-table {
    max-height: 600px;
    overflow-y: auto;
    border: 1px solid var(--border-light);
    border-radius: var(--border-radius);
}

.scrollable-table::-webkit-scrollbar {
    width: 8px;
}

.scrollable-table::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.scrollable-table::-webkit-scrollbar-thumb {
    background: var(--main-green);
    border-radius: 4px;
}

.scrollable-table::-webkit-scrollbar-thumb:hover {
    background: var(--accent-green);
}

.scrollable-table {
    scrollbar-width: thin;
    scrollbar-color: var(--main-green) #f1f1f1;
}

/* Ensure table header stays visible */
.scrollable-table .table thead th {
    position: sticky;
    top: 0;
    background-color: #f8f9fa;
    z-index: 10;
    border-bottom: 2px solid var(--border-light);
}

/* Back Button */
.back-btn {
    background: var(--main-green);
    border: none;
    color: white;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.back-btn:hover {
    background: var(--accent-green);
    color: var(--main-green);
    transform: translateY(-1px);
}

/* Action Buttons */
.btn-sm {
    border-radius: var(--border-radius);
    font-weight: 500;
    transition: var(--transition);
    border: none;
}

.btn-sm:hover {
    transform: translateY(-1px);
}

/* Solid Action Button Styles */
.btn-primary {
    background: #0d6efd;
    color: white;
    border: none;
}

.btn-primary:hover {
    background: #0b5ed7;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
    border: none;
}

.btn-danger:hover {
    background: #bb2d3b;
    color: white;
}

.btn-success {
    background: #198754;
    color: white;
    border: none;
}

.btn-success:hover {
    background: #146c43;
    color: white;
}

/* Create Badge Button */
.create-badge-btn {
    background: var(--main-green);
    border: none;
    color: white;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
}

.create-badge-btn:hover {
    background: var(--accent-green);
    color: var(--main-green);
    transform: translateY(-1px);
}

/* Modal Styling */
.modal-content {
    border-radius: var(--border-radius-lg);
    border: none;
    box-shadow: var(--shadow-lg);
}

.modal-header {
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
}

/* Badge Icon Styling */
.badge-icon-display {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: var(--border-radius);
    background: #f8f9fa;
    border: 2px solid var(--border-light);
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
    border-radius: var(--border-radius);
    background: #f8f9fa;
    border: 2px solid var(--border-light);
    margin: 0 auto;
}

.badge-icon-preview i {
    margin: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .welcome-title {
        font-size: 2rem;
    }
    
    .badges-container .card-header {
        padding: 1rem;
    }
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
<div class="page-container">
    <div class="container py-4">
        <!-- Enhanced Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Badge Management</h1>
                    <p class="welcome-subtitle">Create and manage achievement badges for students</p>
                    <a href="dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="welcome-decoration">
                        <i class="bi bi-award"></i>
                    </div>
                    <div class="floating-shapes"></div>
                </div>
            </div>
            <div class="accent-line"></div>
        </div>

        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2 class="h4 mb-0 text-dark">
                    <i class="bi bi-award me-2"></i>Badge Management
                </h2>
                <a href="#" class="btn create-badge-btn" data-bs-toggle="modal" data-bs-target="#createBadgeModal">
                    <i class="bi bi-plus-circle me-2"></i>New Badge
                </a>
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
                <div class="card badges-container">
                    <div class="card-header">
                        <h5>
                            <i class="bi bi-award me-2"></i>All Badges
                            <span class="badge bg-primary ms-2"><?php echo count($badges); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($badges)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-award fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">No badges found</h5>
                                <p class="text-muted">Create your first badge to get started.</p>
                                <a href="#" class="btn create-badge-btn" data-bs-toggle="modal" data-bs-target="#createBadgeModal">
                                    <i class="bi bi-plus-circle me-2"></i>Create Badge
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="scrollable-table">
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
                        <?php endif; ?>
                    </div>
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