<?php
$page_title = 'Teacher Announcements';
require_once '../includes/header.php';
require_once '../config/pusher.php';
require_once '../includes/pusher_notifications.php';
requireRole('teacher');

// Add custom CSS for enhanced scrolling and visual improvements
echo '<style>
    .container-fluid {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    .row:first-child {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    .mb-4 {
        margin-bottom: 1rem !important;
    }
    .mb-3 {
        margin-bottom: 0.75rem !important;
    }
    .card:last-child {
        margin-bottom: 2rem !important;
    }
    .text-center.py-5 {
        padding-bottom: 3rem !important;
        margin-bottom: 2rem !important;
    }
    
    /* Enhanced Announcements Table Scrolling */
    .announcements-table-container {
        max-height: 600px;
        overflow-y: auto;
        overflow-x: hidden;
        scroll-behavior: smooth;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        position: relative;
    }
    
    /* Custom scrollbar for announcements table */
    .announcements-table-container::-webkit-scrollbar {
        width: 8px;
    }
    
    .announcements-table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .announcements-table-container::-webkit-scrollbar-thumb {
        background: #6f42c1;
        border-radius: 4px;
        transition: background 0.3s ease;
    }
    
    .announcements-table-container::-webkit-scrollbar-thumb:hover {
        background: #5a2d91;
    }
    
    /* Firefox scrollbar styling */
    .announcements-table-container {
        scrollbar-width: thin;
        scrollbar-color: #6f42c1 #f1f1f1;
    }
    
    /* Enhanced table styling */
    .announcements-table-container .table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .announcements-table-container .table thead th {
        position: sticky;
        top: 0;
        background: #f8f9fa;
        z-index: 10;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        padding: 16px 12px;
    }
    
    .announcements-table-container .table tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .announcements-table-container .table tbody tr:hover {
        background-color: rgba(111, 66, 193, 0.05);
        transform: translateX(3px);
        box-shadow: 0 2px 8px rgba(111, 66, 193, 0.1);
    }
    
    .announcements-table-container .table tbody td {
        padding: 16px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
    }
    
    /* Enhanced button styling */
    .announcements-table-container .btn-group .btn {
        padding: 6px 12px;
        font-size: 0.875rem;
        border-radius: 6px;
        transition: all 0.3s ease;
        margin: 0 2px;
    }
    
    .announcements-table-container .btn-group .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    /* Badge enhancements */
    .announcements-table-container .badge {
        font-size: 0.75rem;
        padding: 6px 10px;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .announcements-table-container .badge:hover {
        transform: scale(1.05);
    }
    
    /* Scroll indicators for announcements table */
    .announcements-scroll-indicator {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 15;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .announcements-scroll-indicator.show {
        opacity: 1;
    }
    
    .announcements-scroll-indicator-content {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .announcements-scroll-indicator i {
        background: rgba(111, 66, 193, 0.8);
        color: white;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        box-shadow: 0 2px 8px rgba(111, 66, 193, 0.3);
    }
    
    .announcements-scroll-indicator-top.hide,
    .announcements-scroll-indicator-bottom.hide {
        opacity: 0.3;
    }
    
    /* Card enhancements */
    .announcements-card {
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .announcements-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 2px solid #dee2e6;
        padding: 16px 20px;
    }
    
    .announcements-card .card-header h5 {
        margin: 0;
        font-weight: 600;
        color: #495057;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 991.98px) {
        .announcements-table-container {
            max-height: 450px;
        }
        
        .announcements-table-container .table thead th,
        .announcements-table-container .table tbody td {
            padding: 12px 8px;
            font-size: 0.9rem;
        }
    }
    
    @media (max-width: 575.98px) {
        .announcements-table-container {
            max-height: 350px;
        }
        
        .announcements-table-container .table thead th,
        .announcements-table-container .table tbody td {
            padding: 8px 4px;
            font-size: 0.85rem;
        }
        
        .announcements-table-container .btn-group .btn {
            padding: 4px 8px;
            font-size: 0.75rem;
        }
    }
    
    /* Loading and animation states */
    .announcements-table-loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    .announcement-row-enter {
        animation: announcementRowEnter 0.5s ease-out;
    }
    
    @keyframes announcementRowEnter {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .announcement-row-exit {
        animation: announcementRowExit 0.5s ease-in;
    }
    
    @keyframes announcementRowExit {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(-100%);
        }
    }
</style>';

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
                
                if (empty($title) || empty($content)) {
                    $message = 'Title and content are required.';
                    $message_type = 'danger';
                } else {
                    // Verify the course belongs to this teacher
                    if ($course_id) {
                        $courseCheck = $db->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ? AND is_archived = 0');
                        $courseCheck->execute([$course_id, $_SESSION['user_id']]);
                        if (!$courseCheck->fetch()) {
                            $message = 'Invalid course selected.';
                            $message_type = 'danger';
                            break;
                        }
                    }
                    
                    $is_global = empty($course_id) ? 1 : 0;
                    $target_audience = $course_id ? json_encode(['courses' => [$course_id]]) : null;
                    
                    $stmt = $db->prepare('INSERT INTO announcements (title, content, author_id, is_global, target_audience) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$title, $content, $_SESSION['user_id'], $is_global, $target_audience]);
                    
                    // Send Pusher notification
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
                
                if (empty($title) || empty($content)) {
                    $message = 'Title and content are required.';
                    $message_type = 'danger';
                } else {
                    // Verify the announcement belongs to this teacher
                    $announcementCheck = $db->prepare('SELECT id FROM announcements WHERE id = ? AND author_id = ?');
                    $announcementCheck->execute([$announcement_id, $_SESSION['user_id']]);
                    if (!$announcementCheck->fetch()) {
                        $message = 'You can only edit your own announcements.';
                        $message_type = 'danger';
                        break;
                    }
                    
                    // Verify the course belongs to this teacher if course_id is provided
                    if ($course_id) {
                        $courseCheck = $db->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ? AND is_archived = 0');
                        $courseCheck->execute([$course_id, $_SESSION['user_id']]);
                        if (!$courseCheck->fetch()) {
                            $message = 'Invalid course selected.';
                            $message_type = 'danger';
                            break;
                        }
                    }
                    
                    $is_global = empty($course_id) ? 1 : 0;
                    $target_audience = $course_id ? json_encode(['courses' => [$course_id]]) : null;
                    
                    $stmt = $db->prepare('UPDATE announcements SET title = ?, content = ?, is_global = ?, target_audience = ? WHERE id = ? AND author_id = ?');
                    $stmt->execute([$title, $content, $is_global, $target_audience, $announcement_id, $_SESSION['user_id']]);
                    
                    $message = 'Announcement updated successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'delete':
                $announcement_id = (int)($_POST['announcement_id'] ?? 0);
                
                // Verify the announcement belongs to this teacher
                $announcementCheck = $db->prepare('SELECT id FROM announcements WHERE id = ? AND author_id = ?');
                $announcementCheck->execute([$announcement_id, $_SESSION['user_id']]);
                if (!$announcementCheck->fetch()) {
                    $message = 'You can only delete your own announcements.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('DELETE FROM announcements WHERE id = ? AND author_id = ?');
                    $stmt->execute([$announcement_id, $_SESSION['user_id']]);
                    
                    $message = 'Announcement deleted successfully.';
                    $message_type = 'success';
                }
                break;
        }
    }
}

// Get filter parameters
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : '';
$search_query = sanitizeInput($_GET['search'] ?? '');

// Build WHERE clause for teacher's announcements
$where_clause = 'WHERE a.author_id = ?';
$params = [$_SESSION['user_id']];

if ($course_filter) {
    $where_clause .= ' AND JSON_SEARCH(a.target_audience, "one", ?) IS NOT NULL';
    $params[] = $course_filter;
}

if ($search_query) {
    $where_clause .= ' AND (a.title LIKE ? OR a.content LIKE ?)';
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
}

// Get teacher's announcements
$stmt = $db->prepare("
    SELECT a.*, 
           CASE 
               WHEN a.is_global = 1 THEN 'General Announcement'
               ELSE 'Course Specific'
           END as announcement_type,
           CASE 
               WHEN a.is_global = 1 THEN NULL
               WHEN a.target_audience IS NOT NULL THEN (
                   SELECT c.course_name 
                   FROM courses c 
                   WHERE c.id = JSON_UNQUOTE(JSON_EXTRACT(a.target_audience, '$.courses[0]'))
               )
               ELSE NULL
           END as course_name,
           CASE 
               WHEN a.is_global = 1 THEN NULL
               WHEN a.target_audience IS NOT NULL THEN (
                   SELECT c.course_code 
                   FROM courses c 
                   WHERE c.id = JSON_UNQUOTE(JSON_EXTRACT(a.target_audience, '$.courses[0]'))
               )
               ELSE NULL
           END as course_code
    FROM announcements a
    $where_clause
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Get teacher's courses for filter and form
$stmt = $db->prepare('SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? AND is_archived = 0 ORDER BY course_name');
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h1 class="h3">My Announcements</h1>
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
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search_query); ?>" 
                                   placeholder="Search announcements...">
                        </div>
                        <div class="col-md-4">
                            <label for="course_id" class="form-label">Filter by Course</label>
                            <select class="form-select" id="course_id" name="course_id">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search me-1"></i>Search
                            </button>
                            <a href="announcements.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcements List -->
    <div class="row announcements-container" style="margin-bottom: 280px;">
        <div class="col-12">
            <div class="card announcements-card">
                <div class="card-header">
                    <h5 class="mb-0">Announcements (<?php echo count($announcements); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-megaphone text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">No announcements found</h5>
                            <p class="text-muted">
                                <?php if ($search_query || $course_filter): ?>
                                    Try adjusting your search criteria or 
                                    <a href="announcements.php">view all announcements</a>.
                                <?php else: ?>
                                    Create your first announcement to get started.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive announcements-table-container">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Course</th>
                                        <th>Content Preview</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($announcement['course_name']): ?>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($announcement['course_name'] . ' (' . $announcement['course_code'] . ')'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">General</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars(substr($announcement['content'], 0, 100)); ?>
                                                <?php if (strlen($announcement['content']) > 100): ?>...<?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo formatDate($announcement['created_at']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)"
                                                            title="View Announcement">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" 
                                                            onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)"
                                                            title="Edit Announcement">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')"
                                                            title="Delete Announcement">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
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
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="announcement_id" id="edit_announcement_id">
                    
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

<!-- View Announcement Modal -->
<div class="modal fade" id="viewAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="view_announcement_title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Course:</strong> 
                    <span id="view_announcement_course"></span>
                </div>
                <div class="mb-3">
                    <strong>Content:</strong>
                    <div id="view_announcement_content" class="mt-2 p-3 bg-light rounded"></div>
                </div>
                <div class="mb-3">
                    <strong>Created:</strong> 
                    <span id="view_announcement_created"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAnnouncementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the announcement "<span id="delete_announcement_title"></span>"?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="announcement_id" id="delete_announcement_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function viewAnnouncement(announcement) {
    document.getElementById('view_announcement_title').textContent = announcement.title;
    document.getElementById('view_announcement_course').textContent = announcement.course_name ? 
        announcement.course_name + ' (' + announcement.course_code + ')' : 'General Announcement';
    document.getElementById('view_announcement_content').textContent = announcement.content;
    document.getElementById('view_announcement_created').textContent = new Date(announcement.created_at).toLocaleString();
    
    new bootstrap.Modal(document.getElementById('viewAnnouncementModal')).show();
}

function editAnnouncement(announcement) {
    document.getElementById('edit_announcement_id').value = announcement.id;
    document.getElementById('edit_title').value = announcement.title;
    document.getElementById('edit_content').value = announcement.content;
    
    // Extract course ID from target_audience JSON if it exists
    let courseId = '';
    if (announcement.target_audience) {
        try {
            const targetAudience = JSON.parse(announcement.target_audience);
            if (targetAudience.courses && targetAudience.courses.length > 0) {
                courseId = targetAudience.courses[0];
            }
        } catch (e) {
            console.error('Error parsing target_audience:', e);
        }
    }
    document.getElementById('edit_course_id').value = courseId;
    
    new bootstrap.Modal(document.getElementById('editAnnouncementModal')).show();
 }

function deleteAnnouncement(id, title) {
    document.getElementById('delete_announcement_id').value = id;
    document.getElementById('delete_announcement_title').textContent = title;
    
    new bootstrap.Modal(document.getElementById('deleteAnnouncementModal')).show();
}

// Auto-resize textareas
document.addEventListener('DOMContentLoaded', function() {
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });
    
    // Enhanced scrolling behavior for announcements table
    function enhanceAnnouncementsTableScrolling() {
        const tableContainer = document.querySelector('.announcements-table-container');
        
        if (tableContainer) {
            // Add smooth scrolling behavior
            tableContainer.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const cardContainer = tableContainer.closest('.card');
            if (cardContainer) {
                addAnnouncementsTableScrollIndicators(tableContainer, cardContainer);
            }
        }
    }
    
    // Add scroll indicators to announcements table
    function addAnnouncementsTableScrollIndicators(scrollContainer, cardContainer) {
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = 'announcements-scroll-indicator';
        scrollIndicator.innerHTML = `
            <div class="announcements-scroll-indicator-content">
                <i class="bi bi-chevron-up announcements-scroll-indicator-top"></i>
                <i class="bi bi-chevron-down announcements-scroll-indicator-bottom"></i>
            </div>
        `;
        
        cardContainer.style.position = 'relative';
        cardContainer.appendChild(scrollIndicator);
        
        // Update scroll indicators based on scroll position
        function updateAnnouncementsScrollIndicators() {
            const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
            const isAtTop = scrollContainer.scrollTop === 0;
            const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
            
            if (isScrollable) {
                scrollIndicator.classList.add('show');
                scrollIndicator.querySelector('.announcements-scroll-indicator-top').classList.toggle('hide', isAtTop);
                scrollIndicator.querySelector('.announcements-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
            } else {
                scrollIndicator.classList.remove('show');
            }
        }
        
        // Initial check
        updateAnnouncementsScrollIndicators();
        
        // Update on scroll
        scrollContainer.addEventListener('scroll', updateAnnouncementsScrollIndicators);
        
        // Update on resize
        window.addEventListener('resize', updateAnnouncementsScrollIndicators);
    }
    
    // Initialize enhanced announcements table scrolling
    enhanceAnnouncementsTableScrolling();
});
</script>
