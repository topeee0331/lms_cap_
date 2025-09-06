<?php
$page_title = 'Announcements';
require_once '../includes/header.php';
requireRole('admin');

$message = '';
$message_type = '';

// Handle announcement actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'create':
                $title = sanitizeInput($_POST['title'] ?? '');
                $content = sanitizeInput($_POST['content'] ?? '');
                $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
                $is_global = empty($course_id) ? 1 : 0;
                $target_audience = $course_id ? json_encode(['courses' => [$course_id]]) : null;
                
                if (empty($title) || empty($content)) {
                    $message = 'Title and content are required.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('INSERT INTO announcements (title, content, author_id, is_global, target_audience) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$title, $content, $_SESSION['user_id'], $is_global, $target_audience]);
                    
                    // Send Pusher notification
                    require_once '../config/pusher.php';
                    require_once '../includes/pusher_notifications.php';
                    
                    // Get author name and course name
                    $authorName = $_SESSION['name'] ?? 'Unknown User';
                    $courseName = null;
                    if ($course_id) {
                        $courseStmt = $db->prepare('SELECT course_name FROM courses WHERE id = ?');
                        $courseStmt->execute([$course_id]);
                        $courseName = $courseStmt->fetchColumn();
                    }
                    
                    $announcementData = [
                        'title' => $title,
                        'content' => $content,
                        'course_id' => $course_id,
                        'course_name' => $courseName,
                        'author_name' => $authorName
                    ];
                    
                    PusherNotifications::sendNewAnnouncement($announcementData);
                    
                    $message = 'Announcement created successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'update':
                $announcement_id = (int)($_POST['announcement_id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $content = sanitizeInput($_POST['content'] ?? '');
                $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
                $is_global = empty($course_id) ? 1 : 0;
                $target_audience = $course_id ? json_encode(['courses' => [$course_id]]) : null;
                
                if (empty($title) || empty($content)) {
                    $message = 'Title and content are required.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('UPDATE announcements SET title = ?, content = ?, is_global = ?, target_audience = ? WHERE id = ?');
                    $stmt->execute([$title, $content, $is_global, $target_audience, $announcement_id]);
                    $message = 'Announcement updated successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'delete':
                $announcement_id = (int)($_POST['announcement_id'] ?? 0);
                $stmt = $db->prepare('DELETE FROM announcements WHERE id = ?');
                $stmt->execute([$announcement_id]);
                $message = 'Announcement deleted successfully.';
                $message_type = 'success';
                break;
        }
    }
}

// Get announcements with search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$course_filter = (int)($_GET['course'] ?? 0);

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(a.title LIKE ? OR a.content LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if ($course_filter > 0) {
    $where_conditions[] = "JSON_SEARCH(a.target_audience, 'one', ?) IS NOT NULL";
    $params[] = $course_filter;
} elseif ($course_filter === 0) {
    $where_conditions[] = "a.is_global = 1";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $db->prepare("
    SELECT a.*, u.first_name, u.last_name, 
           CONCAT(u.first_name, ' ', u.last_name) as author_name,
           CASE 
               WHEN a.is_global = 1 THEN 'General Announcement'
               ELSE 'Course Specific'
           END as announcement_type
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    $where_clause
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Get courses for filter and form
$stmt = $db->prepare('SELECT id, course_name, course_code FROM courses WHERE is_archived = 0 ORDER BY course_name');
$stmt->execute();
$courses = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Announcements</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="bi bi-plus-circle me-2"></i>Create Announcement
                </button>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-8">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search announcements by title or content">
                        </div>
                        <div class="col-md-3">
                            <label for="course" class="form-label">Filter by Course</label>
                            <select class="form-select" id="course" name="course">
                                <option value="">All Announcements</option>
                                <option value="0" <?php echo $course_filter === 0 ? 'selected' : ''; ?>>General Announcements</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcements List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Announcements (<?php echo count($announcements); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($announcements)): ?>
                        <p class="text-muted">No announcements found.</p>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger delete-confirm" 
                                                    onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">
                                                By <?php echo htmlspecialchars($announcement['author_name'] ?? 'Unknown User'); ?>
                                            </small>
                                            <br>
                                            <span class="badge bg-<?php echo $announcement['announcement_type'] === 'General Announcement' ? 'secondary' : 'info'; ?>">
                                                <?php echo htmlspecialchars($announcement['announcement_type']); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted"><?php echo formatDate($announcement['created_at']); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="create_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="create_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_content" class="form-label">Content</label>
                        <textarea class="form-control" id="create_content" name="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_course_id" class="form-label">Course (Optional)</label>
                        <select class="form-select" id="create_course_id" name="course_id">
                            <option value="">General Announcement</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty for a general announcement visible to all users.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="announcement_id" id="edit_announcement_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_content" class="form-label">Content</label>
                        <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_course_id" class="form-label">Course (Optional)</label>
                        <select class="form-select" id="edit_course_id" name="course_id">
                            <option value="">General Announcement</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty for a general announcement visible to all users.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Announcement Form -->
<form id="deleteAnnouncementForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="announcement_id" id="delete_announcement_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<script>
function editAnnouncement(announcement) {
    document.getElementById('edit_announcement_id').value = announcement.id;
    document.getElementById('edit_title').value = announcement.title;
    document.getElementById('edit_content').value = announcement.content;
    document.getElementById('edit_course_id').value = announcement.course_id || '';
    
    new bootstrap.Modal(document.getElementById('editAnnouncementModal')).show();
}

function deleteAnnouncement(announcementId, announcementTitle) {
    if (confirm(`Are you sure you want to delete "${announcementTitle}"? This action cannot be undone.`)) {
        document.getElementById('delete_announcement_id').value = announcementId;
        document.getElementById('deleteAnnouncementForm').submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?> 