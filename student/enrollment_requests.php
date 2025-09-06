<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Check if user is a student
if ($_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Mark enrollment notifications as viewed when page loads
try {
    $studentId = $_SESSION['user_id'];
    
    // Simple approach like teacher system: no complex database operations needed
    // The badge count will be handled by the AJAX endpoint
    
} catch (Exception $e) {
    error_log("Error in enrollment requests page: " . $e->getMessage());
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];
$page_title = 'Enrollment Requests';
require_once '../includes/header.php';
require_once '../config/pusher.php';
require_once '../includes/pusher_notifications.php';

// Get student's enrollment requests
$stmt = $pdo->prepare("
    SELECT er.*, c.course_name, c.course_code, u.first_name, u.last_name as teacher_last_name, er.approved_at, er.rejection_reason
    FROM enrollment_requests er
    JOIN courses c ON er.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    WHERE er.student_id = ?
    ORDER BY er.requested_at DESC
");
$stmt->execute([$student_id]);
$enrollment_requests = $stmt->fetchAll();

// Debug: Let's see what we're getting
if (empty($enrollment_requests)) {
    // No requests found
} else {
    // Debug output (remove this after testing)
    foreach ($enrollment_requests as $req) {
        if ($req['status'] === 'rejected') {
            error_log("Rejected request ID: " . $req['id'] . ", Reason: " . ($req['rejection_reason'] ?? 'NULL'));
        }
    }
}

// Count requests by status
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

foreach ($enrollment_requests as $request) {
    switch ($request['status']) {
        case 'pending':
            $pending_count++;
            break;
        case 'approved':
            $approved_count++;
            break;
        case 'rejected':
            $rejected_count++;
            break;
    }
}
?>

<style>
.status-badge {
    font-size: 0.8rem;
    font-weight: 600;
}
.status-pending {
    background-color: #ffc107;
    color: #212529;
}
.status-approved {
    background-color: #198754;
    color: white;
}
.status-rejected {
    background-color: #dc3545;
    color: white;
}
.request-card {
    border-left: 4px solid #dee2e6;
    transition: all 0.3s ease;
}
.request-card.pending {
    border-left-color: #ffc107;
}
.request-card.approved {
    border-left-color: #198754;
}
.request-card.rejected {
    border-left-color: #dc3545;
}
.request-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Prevent modal from auto-closing and ensure text doesn't fade */
.rejection-modal {
    backdrop-filter: blur(5px);
}

.rejection-modal .modal-content {
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.rejection-modal .modal-header {
    border-bottom: 2px solid #dc3545;
}

.rejection-modal .modal-body {
    padding: 1.5rem;
}

/* .rejection-modal .rejection-reason-text {
    font-size: 1rem;
    line-height: 1.6;
    color: #212529;
    background-color: transparent;
    border: none;
    border-radius: 0;
    padding: 0;
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
    opacity: 1 !important;
    transition: none !important;
    text-align: left !important;
    text-align-last: left !important;
    direction: ltr;
    unicode-bidi: normal;
    float: left !important;
    clear: both !important;
    width: 100% !important;
    display: block !important;
    position: relative !important;
    left: 0 !important;
    right: auto !important;
    margin-left: 0 !important;
    margin-right: auto !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
} */

.rejection-modal .rejection-reason-text strong {
    text-align: left !important;
    text-align-last: left !important;
    direction: ltr !important;
    float: left !important;
    clear: both !important;
    width: 100% !important;
    display: block !important;
    position: relative !important;
    left: 0 !important;
    right: auto !important;
    margin-left: 0 !important;
    margin-right: auto !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.rejection-modal .rejection-reason-text em {
    text-align: left !important;
    text-align-last: left !important;
    direction: ltr !important;
    float: left !important;
    clear: both !important;
    width: 100% !important;
    display: block !important;
    position: relative !important;
    left: 0 !important;
    right: auto !important;
    margin-left: 0 !important;
    margin-right: auto !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

/* Target the actual reason text content */
.rejection-modal .rejection-reason-text:not(:has(strong)) {
    text-align: left !important;
    text-align-last: left !important;
    direction: ltr !important;
    float: left !important;
    clear: both !important;
    width: 100% !important;
    display: block !important;
    position: relative !important;
    left: 0 !important;
    right: auto !important;
    margin-left: 0 !important;
    margin-right: auto !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.rejection-modal .rejection-reason-text * {
    text-align: left !important;
    text-align-last: left !important;
    direction: ltr !important;
}

.rejection-modal .modal-body {
    padding: 1.5rem;
    text-align: left !important;
}

.rejection-modal .modal-content {
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    text-align: left !important;
}

.rejection-modal .rejection-reason-text:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

/* Ensure modal stays open */
.rejection-modal[data-bs-backdrop="static"] {
    pointer-events: auto;
}

.rejection-modal .modal-dialog {
    pointer-events: auto;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">My Enrollment Requests</h1>
        </div>
    </div>

    <!-- Status Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-warning"><?php echo $pending_count; ?></h5>
                    <p class="card-text">Pending Requests</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-success"><?php echo $approved_count; ?></h5>
                    <p class="card-text">Approved Requests</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-danger"><?php echo $rejected_count; ?></h5>
                    <p class="card-text">Rejected Requests</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrollment Requests List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Enrollment Request History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($enrollment_requests)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <h5 class="mt-3 text-muted">No Enrollment Requests</h5>
                            <p class="text-muted">You haven't made any enrollment requests yet.</p>
                            <a href="courses.php" class="btn btn-primary">Browse Courses</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($enrollment_requests as $request): ?>
                            <div class="card mb-3 request-card <?php echo $request['status']; ?>" data-request-id="<?php echo $request['id']; ?>">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($request['course_name']); ?></h6>
                                            <p class="card-text text-muted mb-1">
                                                <small>
                                                    <i class="bi bi-code"></i> <?php echo htmlspecialchars($request['course_code']); ?> • 
                                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['teacher_last_name']); ?>
                                                </small>
                                            </p>
                                            <p class="card-text text-muted mb-0">
                                                <small>
                                                    <i class="bi bi-calendar"></i> Requested: <?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?>
                                                </small>
                                            </p>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($request['status']) {
                                                case 'pending':
                                                    $status_class = 'status-pending';
                                                    $status_text = 'Pending';
                                                    break;
                                                case 'approved':
                                                    $status_class = 'status-approved';
                                                    $status_text = 'Approved';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'status-rejected';
                                                    $status_text = 'Rejected';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <?php if ($request['status'] === 'rejected'): ?>
                                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectionModal<?php echo $request['id']; ?>">
                                                    <i class="bi bi-info-circle"></i> View Reason
                                                </button>
                                            <?php elseif ($request['status'] === 'approved'): ?>
                                                <span class="text-success">
                                                    <i class="bi bi-check-circle"></i> Enrolled
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="bi bi-clock"></i> Under Review
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($request['status'] === 'rejected'): ?>
                                <!-- Rejection Reason Modal -->
                                <div class="modal fade rejection-modal" id="rejectionModal<?php echo $request['id']; ?>" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">
                                                    <i class="bi bi-exclamation-triangle"></i> Rejection Reason
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <h6 class="mb-3"><?php echo htmlspecialchars($request['course_name']); ?></h6>
                                                <p class="text-muted mb-3">
                                                    <small>Rejected on <?php echo date('M j, Y g:i A', strtotime($request['approved_at'])); ?></small>
                                                </p>
                                                <div class="rejection-reason-text">
                                                    <strong>Reason for Rejection:</strong><br><br>
                                                    <?php if (!empty($request['rejection_reason'])): ?>
                                                        <?php echo htmlspecialchars($request['rejection_reason']); ?>
                                                    <?php else: ?>
                                                        <em>No specific reason provided.</em>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-muted small mt-3">
                                                    <i class="bi bi-info-circle"></i> You can request enrollment again if you believe the issue has been resolved.
                                                </p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <a href="courses.php" class="btn btn-primary">Browse Courses</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
<!-- Include Pusher for real-time updates -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script src="../assets/js/pusher-client.js"></script>
    
<script>
    // Initialize Pusher client for real-time updates
    if (typeof window.pusherClient !== 'undefined') {
        // Use existing Pusher client if available
        window.pusherClient.initializeStudentDashboard(<?php echo $student_id; ?>);
        
        // Initialize enrollment badge immediately
        setTimeout(() => {
            window.pusherClient.updateStudentNavbarEnrollmentBadge();
        }, 1000);
        
        // Set up periodic badge refresh every 15 seconds
        setInterval(() => {
            if (typeof window.pusherClient !== 'undefined') {
                window.pusherClient.updateStudentNavbarEnrollmentBadge();
            }
        }, 15000);
    } else if (typeof pusherClient !== 'undefined') {
        // Use local Pusher client if available
        pusherClient.initializeStudentDashboard(<?php echo $student_id; ?>);
        
        // Initialize enrollment badge immediately
        setTimeout(() => {
            pusherClient.updateStudentNavbarEnrollmentBadge();
        }, 1000);
        
        // Set up periodic badge refresh every 15 seconds
        setInterval(() => {
            if (typeof window.pusherClient !== 'undefined') {
                window.pusherClient.updateStudentNavbarEnrollmentBadge();
            }
        }, 15000);
    } else {
        console.warn('⚠️ PusherClient not available, enrollment badge updates may not work');
    }
    
    // Show success message when page loads (indicating red dot was cleared)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('viewed') === '1') {
        // Create a temporary success message
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success alert-dismissible fade show';
        successDiv.innerHTML = `
            <i class="fas fa-check-circle"></i>
            <strong>Notifications cleared!</strong> The red dot badge has been removed. It will reappear when there are new enrollment status changes.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at the top of the page
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(successDiv, container.firstChild);
        }
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (successDiv.parentNode) {
                successDiv.remove();
            }
        }, 5000);
    }
    
    // Add click tracking for enrollment request cards
    const requestCards = document.querySelectorAll('.request-card');
    requestCards.forEach(card => {
        card.addEventListener('click', function() {
            // Track that user interacted with this request
            console.log('User clicked on enrollment request:', this.dataset.requestId);
        });
    });
        
        // Automatically mark notifications as viewed when page loads
        // This ensures the red badge disappears when student visits the page
        if (typeof window.currentUserId !== 'undefined') {
            $.post('<?php echo SITE_URL; ?>/student/mark_notifications_viewed.php', {
                student_id: window.currentUserId
            }, function(response) {
                console.log('Notifications automatically marked as viewed:', response);
                
                // After marking as viewed, refresh the badge count to ensure it stays hidden
                setTimeout(function() {
                    $.get('<?php echo SITE_URL; ?>/ajax_get_student_enrollment_requests.php', function(data) {
                        try {
                            var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
                            if (parsed.success) {
                                var totalCount = parsed.pending_count || 0;
                                console.log('🔄 Badge count after marking as viewed:', totalCount);
                                
                                // If no notifications, ensure badges are hidden (like teacher system)
                                if (totalCount === 0) {
                                    // Hide badge count but keep bell icon visible
                                    const notificationBell = document.getElementById('navbarAnnounceDropdown');
                                    if (notificationBell) {
                                        const badge = notificationBell.querySelector('.badge');
                                        if (badge) {
                                            badge.style.display = 'none';
                                            console.log('✅ Hidden notification bell badge count (bell icon preserved)');
                                        }
                                    }
                                    
                                    const enrollmentLink = document.getElementById('student-enrollment-requests-link');
                                    if (enrollmentLink) {
                                        const existingBadge = enrollmentLink.querySelector('.position-relative');
                                        if (existingBadge) {
                                            existingBadge.remove();
                                            console.log('✅ Removed enrollment requests badge');
                                        }
                                    }
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing badge count:', e);
                        }
                    });
                }, 1000); // Delay to ensure database is updated
                
            }).fail(function(xhr, status, error) {
                console.warn('Error auto-marking notifications as viewed:', error);
            });
        }
        
        // Enhanced badge removal system - ensure badges stay hidden
        function ensureBadgesHidden() {
            console.log('🔒 Ensuring all notification badges are hidden...');
            
            // Use the new real-time update function to hide badges while preserving bell icon
            if (typeof updateBadgeCountRealtime === 'function') {
                updateBadgeCountRealtime(0);
                console.log('✅ Used real-time update to hide badges (bell icon preserved)');
            } else {
                // Fallback to manual hiding - ONLY hide badge count, NEVER remove bell icon
                const notificationBell = document.getElementById('navbarAnnounceDropdown');
                if (notificationBell) {
                    // Ensure bell icon is always visible
                    notificationBell.style.display = 'block';
                    notificationBell.style.visibility = 'visible';
                    
                    const badge = notificationBell.querySelector('.badge');
                    if (badge) {
                        // Only hide the badge count, keep bell icon
                        badge.style.display = 'none';
                        console.log('✅ Hidden notification bell badge count (bell icon preserved)');
                    }
                }
                
                // Remove enrollment requests badge completely (this is safe to remove)
                const enrollmentLink = document.getElementById('student-enrollment-requests-link');
                if (enrollmentLink) {
                    const existingBadge = enrollmentLink.querySelector('.position-relative');
                    if (existingBadge) {
                        existingBadge.remove();
                        console.log('✅ Removed enrollment requests badge');
                    }
                }
                
                // Only remove red dots that are NOT part of the bell icon
                const redDots = document.querySelectorAll('.position-relative .position-absolute.bg-danger');
                redDots.forEach(dot => {
                    const container = dot.closest('.position-relative');
                    if (container && !container.closest('#navbarAnnounceDropdown')) {
                        // Only remove if it's NOT part of the bell icon
                        container.remove();
                        console.log('✅ Removed red dot badge (bell icon preserved)');
                    }
                });
            }
        }
        
        // Call badge removal immediately and then periodically
        ensureBadgesHidden();
        
        // Set up periodic badge removal to prevent reappearing
        setInterval(ensureBadgesHidden, 5000); // Check every 5 seconds
        
        // Also call the global function from header.php if available
        if (typeof hideAllStudentNotificationBadges === 'function') {
            hideAllStudentNotificationBadges();
            console.log('✅ Called global badge hiding function');
        }
        
        // Function to protect bell icon from being removed
        function protectBellIcon() {
            const notificationBell = document.getElementById('navbarAnnounceDropdown');
            if (notificationBell) {
                // Ensure bell icon is always visible and never removed
                notificationBell.style.display = 'block';
                notificationBell.style.visibility = 'visible';
                notificationBell.style.opacity = '1';
                
                // If bell icon was accidentally removed, restore it
                if (!notificationBell.innerHTML.includes('bi-bell')) {
                    console.log('⚠️ Bell icon content was removed, restoring...');
                    notificationBell.innerHTML = '<i class="bi bi-bell"></i> Notifications';
                }
                
                console.log('🔒 Bell icon protected and preserved');
            }
        }
        
        // Call bell icon protection immediately and then periodically
        protectBellIcon();
        setInterval(protectBellIcon, 3000); // Check every 3 seconds to ensure bell icon stays
</script>

<?php require_once '../includes/footer.php'; ?> 