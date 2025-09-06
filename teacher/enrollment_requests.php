<?php
$page_title = 'Enrollment Requests';
require_once '../includes/header.php';
require_once '../config/pusher.php';
require_once '../includes/pusher_notifications.php';
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];

// Note: Academic year selection removed - using simplified system

// Handle approval/rejection of enrollment requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = (int)($_POST['request_id'] ?? 0);
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'approve':
                // Get the enrollment request details
                $stmt = $db->prepare("
                    SELECT er.*, c.course_name, u.first_name, u.last_name 
                    FROM enrollment_requests er
                    JOIN courses c ON er.course_id = c.id
                    JOIN users u ON er.student_id = u.id
                    WHERE er.id = ? AND c.teacher_id = ?
                ");
                $stmt->execute([$request_id, $teacher_id]);
                $request = $stmt->fetch();
                
                if ($request) {
                    // Update request status to approved
                    $stmt = $db->prepare("UPDATE enrollment_requests SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?");
                    $stmt->execute([$teacher_id, $request_id]);
                    
                    // Create enrollment record
                    $stmt = $db->prepare("INSERT INTO course_enrollments (student_id, course_id, enrolled_at, status) VALUES (?, ?, NOW(), 'active')");
                    $stmt->execute([$request['student_id'], $request['course_id']]);
                    
                    // Create notification for the student
                    $notification_title = "Enrollment Request Approved";
                    $notification_message = "Your enrollment request for course '{$request['course_name']}' has been approved! You can now access the course.";
                    
                    $stmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, related_id) 
                        VALUES (?, ?, ?, 'enrollment_approved', ?)
                    ");
                    $stmt->execute([$request['student_id'], $notification_title, $notification_message, $request_id]);
                    
                    // Create student notification record for red dot tracking
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO student_notifications (student_id, type, course_id, enrollment_request_id) 
                            VALUES (?, 'enrollment_approved', ?, ?)
                        ");
                        $stmt->execute([$request['student_id'], $request['course_id'], $request_id]);
                    } catch (Exception $e) {
                        error_log("Error creating student notification: " . $e->getMessage());
                    }
                    
                    // Send real-time notification via Pusher
                    require_once __DIR__ . '/../includes/pusher_notifications.php';
                    PusherNotifications::sendEnrollmentUpdate(
                        $request['student_id'], 
                        $request['course_name'], 
                        'approved'
                    );
                    
                    $message = "Enrollment request approved for " . $request['first_name'] . " " . $request['last_name'];
                    $message_type = 'success';
                } else {
                    $message = "Invalid enrollment request.";
                    $message_type = 'danger';
                }
                break;
                
            case 'reject':
                $rejection_reason = sanitizeInput($_POST['rejection_reason'] ?? '');
                
                // Get the enrollment request details
                $stmt = $db->prepare("
                    SELECT er.*, c.course_name, u.first_name, u.last_name 
                    FROM enrollment_requests er
                    JOIN courses c ON er.course_id = c.id
                    JOIN users u ON er.student_id = u.id
                    WHERE er.id = ? AND c.teacher_id = ?
                ");
                $stmt->execute([$request_id, $teacher_id]);
                $request = $stmt->fetch();
                
                if ($request) {
                    // Update request status to rejected
                    $stmt = $db->prepare("UPDATE enrollment_requests SET status = 'rejected', approved_at = NOW(), approved_by = ?, rejection_reason = ? WHERE id = ?");
                    $stmt->execute([$teacher_id, $rejection_reason, $request_id]);
                    
                    // Create notification for the student
                    $notification_title = "Enrollment Request Rejected";
                    $notification_message = "Your enrollment request for course '{$request['course_name']}' has been rejected.";
                    if (!empty($rejection_reason)) {
                        $notification_message .= " Reason: " . $rejection_reason;
                    }
                    $notification_message .= " You can request enrollment again if needed.";
                    
                    $stmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, related_id) 
                        VALUES (?, ?, ?, 'enrollment_rejected', ?)
                    ");
                    $stmt->execute([$request['student_id'], $notification_title, $notification_message, $request_id]);
                    
                    // Create student notification record for red dot tracking
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO student_notifications (student_id, type, course_id, enrollment_request_id) 
                            VALUES (?, 'enrollment_rejected', ?, ?)
                        ");
                        $stmt->execute([$request['student_id'], $request['course_id'], $request_id]);
                    } catch (Exception $e) {
                        error_log("Error creating student notification: " . $e->getMessage());
                    }
                    
                    // Send real-time notification via Pusher
                    require_once __DIR__ . '/../includes/pusher_notifications.php';
                    PusherNotifications::sendEnrollmentUpdate(
                        $request['student_id'], 
                        $request['course_name'], 
                        'rejected',
                        $rejection_reason
                    );
                    
                    $message = "Enrollment request rejected for " . $request['first_name'] . " " . $request['last_name'];
                    $message_type = 'success';
                } else {
                    $message = "Invalid enrollment request.";
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Get all enrollment requests for courses taught by this teacher
$stmt = $db->prepare("
    SELECT er.*, c.course_name, c.course_code, u.first_name, u.last_name, u.username,
           er.requested_at, er.status, er.rejection_reason,
           CASE WHEN JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL THEN 1 ELSE 0 END as is_section_assigned,
           u.is_irregular,
           s.section_name, s.year_level as academic_year
    FROM enrollment_requests er
    JOIN courses c ON er.course_id = c.id
    JOIN users u ON er.student_id = u.id
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE c.teacher_id = ? AND er.status = 'pending'
    ORDER BY er.requested_at DESC
");
$stmt->execute([$teacher_id]);
$pending_requests = $stmt->fetchAll();

// Get recently processed requests (approved/rejected)
$stmt = $db->prepare("
    SELECT er.*, c.course_name, c.course_code, u.first_name, u.last_name, u.username,
           er.requested_at, er.status, er.rejection_reason, er.approved_at,
           s.section_name, s.year_level as academic_year
    FROM enrollment_requests er
    JOIN courses c ON er.course_id = c.id
    JOIN users u ON er.student_id = u.id
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE c.teacher_id = ? AND er.status IN ('approved', 'rejected')
    ORDER BY er.approved_at DESC
    LIMIT 20
");
$stmt->execute([$teacher_id]);
$processed_requests = $stmt->fetchAll();
?>

<div class="container-fluid">
    <!-- Academic year selection removed for simplified system -->
    
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Enrollment Requests</h1>
                <div>
                    <!-- <button type="button" class="btn btn-info me-2" onclick="testNewEnrollmentRequest()">
                        <i class="bi bi-plus-circle me-1"></i>Test New Request
                    </button> -->
                    <!-- <button type="button" class="btn btn-primary me-2" onclick="testDynamicUpdate()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Test Dynamic Update
                    </button> -->
                <a href="courses.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Courses
                </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Irregular Students - Section Assigned Courses -->
    <?php 
    $irregular_section_requests = array_filter($pending_requests, function($req) {
        return $req['is_irregular'] && $req['is_section_assigned'];
    });
    ?>
    <?php if (!empty($irregular_section_requests)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Irregular Students - Section Assigned Courses (<?php echo count($irregular_section_requests); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> These irregular students are requesting enrollment in courses already assigned to their section.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Year & Section</th>
                                        <th>Course</th>
                                        <th>Requested</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($irregular_section_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($request['username'] ?? ''); ?> (Irregular)</small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>
                                                        <?php if ($request['section_name']): ?>
                                                            BSIT-<?php echo htmlspecialchars(($request['academic_year'] ?? '') . ($request['section_name'] ?? '')); ?>
                                                        <?php else: ?>
                                                            BSIT-<?php echo htmlspecialchars($request['academic_year'] ?? ''); ?> (No Section)
                                                        <?php endif; ?>
                                                    </strong>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($request['course_name'] ?? ''); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($request['course_code'] ?? ''); ?></small>
                                                    <span class="badge bg-success ms-2">Section Assigned</span>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-success" onclick="approveRequest(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?>')">
                                                        <i class="bi bi-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-danger" onclick="rejectRequest(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?>')">
                                                        <i class="bi bi-x"></i> Reject
                                                    </button>
                                                </div>
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
    <?php endif; ?>

    <!-- Other Pending Requests -->
    <?php 
    $other_requests = array_filter($pending_requests, function($req) {
        return !($req['is_irregular'] && $req['is_section_assigned']);
    });
    ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock text-warning me-2"></i>
                        Other Pending Requests (<span id="other-requests-count"><?php echo count($other_requests); ?></span>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($other_requests)): ?>
                        <div class="text-center py-4" id="no-other-requests-message">
                            <i class="bi bi-check-circle fs-1 text-success mb-3"></i>
                            <h6>No Other Pending Requests</h6>
                            <p class="text-muted">All other enrollment requests have been processed.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="other-requests-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Year & Section</th>
                                        <th>Course</th>
                                        <th>Requested</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="other-requests-tbody">
                                    <?php foreach ($other_requests as $request): ?>
                                        <tr id="request-row-<?php echo $request['id']; ?>">
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($request['username'] ?? ''); ?>
                                                        <?php if ($request['is_irregular']): ?>
                                                            (Irregular)
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>
                                                        <?php if ($request['section_name']): ?>
                                                            BSIT-<?php echo htmlspecialchars(($request['academic_year'] ?? '') . ($request['section_name'] ?? '')); ?>
                                                        <?php else: ?>
                                                            BSIT-<?php echo htmlspecialchars($request['academic_year'] ?? ''); ?> (No Section)
                                                        <?php endif; ?>
                                                    </strong>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($request['course_name'] ?? ''); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($request['course_code'] ?? ''); ?></small>
                                                    <?php if ($request['is_section_assigned']): ?>
                                                        <span class="badge bg-success ms-2">Section Assigned</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning ms-2">Other Section</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-success" onclick="approveRequest(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?>')">
                                                        <i class="bi bi-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-danger" onclick="rejectRequest(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?>')">
                                                        <i class="bi bi-x"></i> Reject
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

    <!-- Recently Processed Requests -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history text-info me-2"></i>
                        Recently Processed Requests
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($processed_requests)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                            <h6>No Processed Requests</h6>
                            <p class="text-muted">No enrollment requests have been processed yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="processedRequestsTable">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Year & Section</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                        <th>Processed</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($processed_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($request['username'] ?? ''); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>
                                                        <?php if ($request['section_name']): ?>
                                                            BSIT-<?php echo htmlspecialchars(($request['academic_year'] ?? '') . ($request['section_name'] ?? '')); ?>
                                                        <?php else: ?>
                                                            BSIT-<?php echo htmlspecialchars($request['academic_year'] ?? ''); ?> (No Section)
                                                        <?php endif; ?>
                                                    </strong>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($request['course_name'] ?? ''); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($request['course_code'] ?? ''); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($request['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Approved
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-x-circle me-1"></i>Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($request['approved_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($request['status'] === 'rejected' && $request['rejection_reason']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($request['rejection_reason'] ?? ''); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
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

<!-- Approve Request Form -->
<form id="approveRequestForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="request_id" id="approve_request_id">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
</form>

<!-- Reject Request Modal -->
<div class="modal fade" id="rejectRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Enrollment Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="request_id" id="reject_request_id">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <p>Are you sure you want to reject the enrollment request for <strong id="reject_student_name"></strong>?</p>
                    
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Rejection Reason (Optional)</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" placeholder="Provide a reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Real-time enrollment request updates with comprehensive debugging
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Teacher enrollment requests page loaded');
    console.log('👤 Current user ID:', window.currentUserId);
    console.log('👤 Current user role:', window.currentUserRole);
    console.log('🔌 Pusher client available:', typeof window.pusherClient !== 'undefined');
    
    // Automatically mark notifications as viewed when page loads (like teacher bell system)
    markTeacherNotificationsAsViewed();
    
    // Listen for real-time updates via Pusher
    if (typeof window.pusherClient !== 'undefined') {
        console.log('📡 Subscribing to enrollment requests...');
        
        // Subscribe to new enrollment requests for this teacher
        if (typeof window.pusherClient.subscribeToEnrollmentRequests === 'function') {
            window.pusherClient.subscribeToEnrollmentRequests();
        }
        
        // Handle new enrollment requests
        window.pusherClient.onNewEnrollmentRequest = function(data) {
            console.log('🎯 New enrollment request received:', data);
            
            // Show notification toast if available
            if (typeof window.pusherClient.showToast === 'function') {
                window.pusherClient.showToast(
                    `New enrollment request from ${data.student_name} for course "${data.course_name}"`,
                    'info',
                    { icon: 'fas fa-user-plus', duration: 6000 }
                );
            }
            
            // IMMEDIATELY show the red dot in navbar when a new request comes in
            console.log('🔄 Showing red dot immediately for new enrollment request...');
            if (typeof showNavbarRedDot === 'function') {
                showNavbarRedDot();
            }
            
            // Then update the navbar badge with the actual count
            console.log('🔄 Updating navbar badge with actual count...');
            if (typeof updateNavbarEnrollmentBadge === 'function') {
                updateNavbarEnrollmentBadge();
            }
            
            // Update enrollment request count if exists
            const badge = document.querySelector('.enrollment-request-badge');
            if (badge) {
                const currentCount = parseInt(badge.textContent || '0');
                badge.textContent = currentCount + 1;
                console.log('📊 Updated enrollment request badge:', currentCount + 1);
            }
            
            // Add new request to the "Other Pending Requests" section immediately
            addNewEnrollmentRequestToTable(data);
            
            // Update the UI dynamically instead of reloading the page
            console.log('🔄 Updating enrollment requests UI dynamically...');
            setTimeout(() => {
                if (typeof updateEnrollmentRequestsUI === 'function') {
                    updateEnrollmentRequestsUI();
                }
            }, 1000); // Small delay to ensure the notification is processed
        };
        
        console.log('✅ Enrollment requests real-time handlers configured');
    } else {
        console.warn('⚠️ Pusher client not available - real-time updates disabled');
    }
    
    console.log('🎉 Teacher enrollment requests page initialization complete');
});

// Function to mark teacher notifications as viewed (like student system)
function markTeacherNotificationsAsViewed() {
    if (typeof window.currentUserId === 'undefined') {
        console.warn('⚠️ Current user ID not available');
        return;
    }
    
    console.log('👁️ Marking teacher notifications as viewed...');
    
    // Mark notifications as viewed (simplified system)
    $.post('<?php echo SITE_URL; ?>/teacher/mark_notifications_viewed.php', {
        teacher_id: window.currentUserId
    }, function(response) {
        console.log('✅ Teacher notifications marked as viewed:', response);
        
        // Update badge count immediately (like teacher bell system)
        if (typeof updateNavbarEnrollmentBadge === 'function') {
            updateNavbarEnrollmentBadge();
        }
        
    }).fail(function(xhr, status, error) {
        console.warn('❌ Error marking teacher notifications as viewed:', error);
    });
}

// Function to dynamically add new enrollment request to the table
function addNewEnrollmentRequestToTable(data) {
    console.log('➕ Adding new enrollment request to table:', data);
    
    // Remove "no requests" message if it exists
    const noRequestsMessage = document.getElementById('no-other-requests-message');
    if (noRequestsMessage) {
        noRequestsMessage.remove();
    }
    
    // Check if table exists, if not create it
    let tbody = document.getElementById('other-requests-tbody');
    if (!tbody) {
        createOtherRequestsTable();
        tbody = document.getElementById('other-requests-tbody');
    }
    
    // Show the table if it was hidden
    const tableContainer = document.getElementById('other-requests-table');
    if (tableContainer && tableContainer.style.display === 'none') {
        tableContainer.style.display = 'table';
    }
    
    // Create new row HTML
    const newRowHtml = `
        <tr id="request-row-${data.id}" class="new-request-highlight">
            <td>
                <div>
                    <strong>${data.student_name}</strong>
                    <br>
                    <small class="text-muted">${data.student_username || 'Student'}</small>
                    ${data.is_irregular ? ' (Irregular)' : ''}
                </div>
            </td>
            <td>
                <div>
                    <strong>
                        ${data.section_name ? `BSIT-${data.academic_year}${data.section_name}` : `BSIT-${data.academic_year} (No Section)`}
                    </strong>
                </div>
            </td>
            <td>
                <div>
                    <strong>${data.course_name}</strong>
                    <br>
                    <small class="text-muted">${data.course_code || ''}</small>
                    ${data.is_section_assigned ? '<span class="badge bg-success ms-2">Section Assigned</span>' : '<span class="badge bg-warning ms-2">Other Section</span>'}
                </div>
            </td>
            <td>
                <small class="text-muted">${new Date().toLocaleString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: true 
                })}</small>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-success" onclick="approveRequest(${data.id}, '${data.student_name}')">
                        <i class="bi bi-check"></i> Approve
                    </button>
                    <button class="btn btn-danger" onclick="rejectRequest(${data.id}, '${data.student_name}')">
                        <i class="bi bi-x"></i> Reject
                    </button>
                </div>
            </td>
        </tr>
    `;
    
    // Add new row to the beginning of the table body
    if (tbody) {
        tbody.insertAdjacentHTML('afterbegin', newRowHtml);
        
        // Add highlight animation
        const newRow = document.getElementById(`request-row-${data.id}`);
        if (newRow) {
            newRow.classList.add('table-warning');
            setTimeout(() => {
                newRow.classList.remove('table-warning', 'new-request-highlight');
            }, 3000);
        }
        
        // Update the count
        updateOtherRequestsCount();
        
        console.log('✅ New enrollment request added to table successfully');
    } else {
        console.error('❌ Could not find table body to add new request');
    }
}

// Function to create the other requests table when it doesn't exist
function createOtherRequestsTable() {
    console.log('🏗️ Creating other requests table...');
    
    const cardBody = document.querySelector('.card-body');
    if (!cardBody) {
        console.error('❌ Could not find card body to create table');
        return;
    }
    
    // Remove any existing no-requests message
    const noRequestsMessage = document.getElementById('no-other-requests-message');
    if (noRequestsMessage) {
        noRequestsMessage.remove();
    }
    
    // Create table HTML
    const tableHtml = `
        <div class="table-responsive">
            <table class="table table-hover" id="other-requests-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Year & Section</th>
                        <th>Course</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="other-requests-tbody">
                </tbody>
            </table>
        </div>
    `;
    
    // Insert table into card body
    cardBody.insertAdjacentHTML('beforeend', tableHtml);
    
    console.log('✅ Other requests table created successfully');
}

// Function to update the count of other pending requests
function updateOtherRequestsCount() {
    const tbody = document.getElementById('other-requests-tbody');
    const countSpan = document.getElementById('other-requests-count');
    
    if (tbody && countSpan) {
        const rowCount = tbody.querySelectorAll('tr').length;
        const currentCount = parseInt(countSpan.textContent || '0');
        
        // Only animate if count actually changed
        if (rowCount !== currentCount) {
            countSpan.textContent = rowCount;
            
            // Add animation class
            countSpan.classList.add('updated');
            
            // Remove animation class after animation completes
            setTimeout(() => {
                countSpan.classList.remove('updated');
            }, 500);
            
            console.log('📊 Updated other requests count to:', rowCount);
        }
    }
}

// Function to remove a request row when it's approved/rejected
function removeRequestRow(requestId) {
    const row = document.getElementById(`request-row-${requestId}`);
    if (row) {
        row.style.transition = 'all 0.5s ease';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-100%)';
        
        setTimeout(() => {
            row.remove();
            updateOtherRequestsCount();
            
            // Check if table is empty and show "no requests" message
            const tbody = document.getElementById('other-requests-tbody');
            if (tbody && tbody.querySelectorAll('tr').length === 0) {
                showNoOtherRequestsMessage();
            }
        }, 500);
        
        console.log('✅ Request row removed:', requestId);
    }
}

// Function to show "no requests" message when table is empty
function showNoOtherRequestsMessage() {
    const tableContainer = document.getElementById('other-requests-table');
    const cardBody = tableContainer?.closest('.card-body');
    
    if (cardBody && !document.getElementById('no-other-requests-message')) {
        const noRequestsHtml = `
            <div class="text-center py-4" id="no-other-requests-message">
                <i class="bi bi-check-circle fs-1 text-success mb-3"></i>
                <h6>No Other Pending Requests</h6>
                <p class="text-muted">All other enrollment requests have been processed.</p>
            </div>
        `;
        cardBody.insertAdjacentHTML('beforeend', noRequestsHtml);
        
        // Hide the table
        if (tableContainer) {
            tableContainer.style.display = 'none';
        }
    }
}

function approveRequest(requestId, studentName) {
    if (confirm(`Are you sure you want to approve the enrollment request for ${studentName}?`)) {
        // Remove the row from the table immediately for better UX
        removeRequestRow(requestId);
        
        document.getElementById('approve_request_id').value = requestId;
        document.getElementById('approveRequestForm').submit();
    }
}

function rejectRequest(requestId, studentName) {
    // Remove the row from the table immediately for better UX
    removeRequestRow(requestId);
    
    document.getElementById('reject_request_id').value = requestId;
    document.getElementById('reject_student_name').textContent = studentName;
    new bootstrap.Modal(document.getElementById('rejectRequestModal')).show();
}

function testDynamicUpdate() {
    console.log('🧪 Testing dynamic update manually...');
    if (typeof updateEnrollmentRequestsUI === 'function') {
        updateEnrollmentRequestsUI().then(() => {
            console.log('✅ Manual dynamic update completed');
            alert('Dynamic update completed! Check console for details.');
        }).catch(error => {
            console.error('❌ Manual dynamic update failed:', error);
            alert('Dynamic update failed: ' + error.message);
        });
    } else {
        console.error('❌ updateEnrollmentRequestsUI function not available');
        alert('updateEnrollmentRequestsUI function not available. Check console for details.');
    }
}

// Test function to simulate a new enrollment request
function testNewEnrollmentRequest() {
    console.log('🧪 Testing new enrollment request simulation...');
    
    const testData = {
        id: Date.now(), // Use timestamp as unique ID
        student_name: 'Test Student',
        student_username: 'teststudent',
        course_name: 'Test Course',
        course_code: 'TEST101',
        academic_year: '2024',
        section_name: 'A',
        is_irregular: false,
        is_section_assigned: false
    };
    
    // Simulate the Pusher event
    if (typeof window.pusherClient !== 'undefined' && window.pusherClient.onNewEnrollmentRequest) {
        window.pusherClient.onNewEnrollmentRequest(testData);
        console.log('✅ Test enrollment request processed via Pusher simulation');
    } else {
        // Direct call if Pusher is not available
        addNewEnrollmentRequestToTable(testData);
        console.log('✅ Test enrollment request added directly to table');
    }
    
    alert('Test enrollment request added! Check the table for the new row.');
}
</script>

<?php require_once '../includes/footer.php'; ?>

<style>
/* New request highlight animation */
.new-request-highlight {
    animation: newRequestPulse 2s ease-in-out;
}

@keyframes newRequestPulse {
    0% {
        background-color: rgba(255, 193, 7, 0.3);
        transform: scale(1);
    }
    50% {
        background-color: rgba(255, 193, 7, 0.6);
        transform: scale(1.02);
    }
    100% {
        background-color: transparent;
        transform: scale(1);
    }
}

/* Smooth row removal animation */
#other-requests-tbody tr {
    transition: all 0.5s ease;
}

/* Table row hover effects */
#other-requests-tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transform: translateX(5px);
}

/* Badge animations */
.badge {
    transition: all 0.3s ease;
}

.badge:hover {
    transform: scale(1.1);
}

/* Count update animation */
#other-requests-count {
    transition: all 0.3s ease;
    display: inline-block;
}

#other-requests-count.updated {
    animation: countUpdate 0.5s ease-in-out;
}

@keyframes countUpdate {
    0% {
        transform: scale(1);
        color: inherit;
    }
    50% {
        transform: scale(1.2);
        color: #28a745;
    }
    100% {
        transform: scale(1);
        color: inherit;
    }
}

/* Loading state for new requests */
.request-loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Success/error states */
.request-success {
    background-color: rgba(40, 167, 69, 0.1) !important;
}

.request-error {
    background-color: rgba(220, 53, 69, 0.1) !important;
}
</style> 