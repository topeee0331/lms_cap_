<?php
/**
 * Admin interface to unlock locked accounts
 * Allows administrators to manually unlock users or IP addresses
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Account Unlock Management';
require_once '../includes/header.php';
requireRole('admin');

$message = '';
$message_type = '';

// Handle unlock actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target = $_POST['target'] ?? '';
    $type = $_POST['type'] ?? '';
    
    try {
        if ($action === 'unlock' && !empty($target)) {
            if ($type === 'email') {
                // Unlock specific email
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ? AND success = 0");
                $stmt->execute([$target]);
                $deleted = $stmt->rowCount();
                
                $message = "Successfully unlocked email: {$target}. Removed {$deleted} failed attempts.";
                $message_type = 'success';
                
            } elseif ($type === 'ip') {
                // Unlock specific IP
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND success = 0");
                $stmt->execute([$target]);
                $deleted = $stmt->rowCount();
                
                $message = "Successfully unlocked IP: {$target}. Removed {$deleted} failed attempts.";
                $message_type = 'success';
                
            } elseif ($type === 'all') {
                // Unlock all locked accounts
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE success = 0");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                
                $message = "Successfully unlocked all accounts. Removed {$deleted} failed attempts.";
                $message_type = 'success';
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get current locked accounts
$locked_emails = [];
$locked_ips = [];

try {
    // Get emails with 3+ failed attempts in the last hour
    $stmt = $pdo->prepare("
        SELECT 
            email,
            COUNT(*) as failed_count,
            MAX(attempt_time) as last_attempt,
            TIMESTAMPDIFF(SECOND, MAX(attempt_time), NOW()) as seconds_ago
        FROM login_attempts 
        WHERE success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY email 
        HAVING failed_count >= ?
        ORDER BY failed_count DESC, last_attempt DESC
    ");
    $stmt->execute([MAX_LOGIN_ATTEMPTS]);
    $locked_emails = $stmt->fetchAll();
    
    // Get IPs with 3+ failed attempts in the last 15 minutes
    $stmt = $pdo->prepare("
        SELECT 
            ip_address,
            COUNT(*) as failed_count,
            MAX(attempt_time) as last_attempt,
            TIMESTAMPDIFF(SECOND, MAX(attempt_time), NOW()) as seconds_ago
        FROM login_attempts 
        WHERE success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        GROUP BY ip_address 
        HAVING failed_count >= ?
        ORDER BY failed_count DESC, last_attempt DESC
    ");
    $stmt->execute([MAX_LOGIN_ATTEMPTS]);
    $locked_ips = $stmt->fetchAll();
    
} catch (Exception $e) {
    $message = "Error fetching locked accounts: " . $e->getMessage();
    $message_type = 'danger';
}

// Helper function to format time
function formatTimeAgo($seconds) {
    if ($seconds < 60) {
        return "{$seconds} seconds ago";
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return "{$minutes} minutes ago";
    } else {
        $hours = floor($seconds / 3600);
        return "{$hours} hours ago";
    }
}

// Helper function to get remaining lockout time
function getRemainingLockout($seconds_ago) {
    $lockout_duration = LOGIN_LOCKOUT_DURATION;
    $remaining = $lockout_duration - $seconds_ago;
    
    if ($remaining <= 0) {
        return "Expired";
    }
    
    $minutes = floor($remaining / 60);
    $seconds = $remaining % 60;
    return "{$minutes}m {$seconds}s remaining";
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2 mb-1 text-dark fw-bold">
                        <i class="bi bi-shield-lock-fill text-primary me-3"></i>
                        Account Unlock Management
                    </h1>
                    <p class="text-muted mb-0">Manage locked accounts and IP addresses</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-arrow-left me-2"></i>Back to Admin
                </a>
            </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle mb-3" style="width: 60px; height: 60px;">
                                    <i class="bi bi-envelope-fill text-primary" style="font-size: 1.5rem;"></i>
                                </div>
                                <h3 class="fw-bold text-dark mb-1"><?php echo count($locked_emails); ?></h3>
                                <p class="text-muted mb-0 fw-medium">Locked Emails</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 rounded-circle mb-3" style="width: 60px; height: 60px;">
                                    <i class="bi bi-network-wired text-warning" style="font-size: 1.5rem;"></i>
                                </div>
                                <h3 class="fw-bold text-dark mb-1"><?php echo count($locked_ips); ?></h3>
                                <p class="text-muted mb-0 fw-medium">Locked IPs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 rounded-circle mb-3" style="width: 60px; height: 60px;">
                                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 1.5rem;"></i>
                                </div>
                                <h3 class="fw-bold text-dark mb-1"><?php echo MAX_LOGIN_ATTEMPTS; ?></h3>
                                <p class="text-muted mb-0 fw-medium">Max Attempts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <div class="d-inline-flex align-items-center justify-content-center bg-info bg-opacity-10 rounded-circle mb-3" style="width: 60px; height: 60px;">
                                    <i class="bi bi-clock-fill text-info" style="font-size: 1.5rem;"></i>
                                </div>
                                <h3 class="fw-bold text-dark mb-1"><?php echo floor(LOGIN_LOCKOUT_DURATION / 60); ?>m</h3>
                                <p class="text-muted mb-0 fw-medium">Lockout Duration</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Locked Emails -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-primary bg-opacity-10 border-0 py-3">
                        <h5 class="mb-0 text-primary fw-bold">
                            <i class="bi bi-envelope-fill me-2"></i>Locked Email Accounts
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($locked_emails)): ?>
                            <p class="text-muted text-center py-3">No email accounts are currently locked.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 fw-semibold text-dark">Email</th>
                                            <th class="border-0 fw-semibold text-dark">Failed Attempts</th>
                                            <th class="border-0 fw-semibold text-dark">Last Attempt</th>
                                            <th class="border-0 fw-semibold text-dark">Status</th>
                                            <th class="border-0 fw-semibold text-dark">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($locked_emails as $email): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($email['email']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger"><?php echo $email['failed_count']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo formatTimeAgo($email['seconds_ago']); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $remaining = getRemainingLockout($email['seconds_ago']);
                                                    if ($remaining === 'Expired') {
                                                        echo '<span class="badge bg-success">Auto-unlocked</span>';
                                                    } else {
                                                        echo '<span class="badge bg-warning">' . $remaining . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="unlock">
                                                        <input type="hidden" name="type" value="email">
                                                        <input type="hidden" name="target" value="<?php echo htmlspecialchars($email['email']); ?>">
                                                                                                                 <button type="submit" class="btn btn-success btn-sm px-3 py-2" 
                                                                 onclick="return confirm('Unlock this email account?')">
                                                             <i class="bi bi-unlock-fill me-2"></i>Unlock
                                                         </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Locked IPs -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-warning bg-opacity-10 border-0 py-3">
                        <h5 class="mb-0 text-warning fw-bold">
                            <i class="bi bi-network-wired me-2"></i>Locked IP Addresses
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($locked_ips)): ?>
                            <p class="text-muted text-center py-3">No IP addresses are currently locked.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 fw-semibold text-dark">IP Address</th>
                                            <th class="border-0 fw-semibold text-dark">Failed Attempts</th>
                                            <th class="border-0 fw-semibold text-dark">Last Attempt</th>
                                            <th class="border-0 fw-semibold text-dark">Status</th>
                                            <th class="border-0 fw-semibold text-dark">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($locked_ips as $ip): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($ip['ip_address']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger"><?php echo $ip['failed_count']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo formatTimeAgo($ip['seconds_ago']); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $remaining = getRemainingLockout($ip['seconds_ago']);
                                                    if ($remaining === 'Expired') {
                                                        echo '<span class="badge bg-success">Auto-unlocked</span>';
                                                    } else {
                                                        echo '<span class="badge bg-warning">' . $remaining . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="unlock">
                                                        <input type="hidden" name="type" value="ip">
                                                        <input type="hidden" name="target" value="<?php echo htmlspecialchars($ip['ip_address']); ?>">
                                                                                                                 <button type="submit" class="btn btn-success btn-sm px-3 py-2" 
                                                                 onclick="return confirm('Unlock this IP address?')">
                                                             <i class="bi bi-unlock-fill me-2"></i>Unlock
                                                         </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success bg-opacity-10 border-0 py-3">
                        <h5 class="mb-0 text-success fw-bold">
                            <i class="bi bi-tools me-2"></i>Bulk Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Unlock All Accounts</h6>
                                <p class="text-muted small">This will unlock all currently locked email accounts and IP addresses.</p>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="unlock">
                                    <input type="hidden" name="type" value="all">
                                                                         <button type="submit" class="btn btn-warning btn-lg px-4 py-3" 
                                             onclick="return confirm('Are you sure you want to unlock ALL locked accounts? This action cannot be undone.')">
                                         <i class="bi bi-unlock-fill me-2"></i>Unlock All Accounts
                                     </button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <h6>System Information</h6>
                                <ul class="list-unstyled small">
                                    <li><strong>Max Login Attempts:</strong> <?php echo MAX_LOGIN_ATTEMPTS; ?></li>
                                    <li><strong>Lockout Duration:</strong> <?php echo floor(LOGIN_LOCKOUT_DURATION / 60); ?> minutes</li>
                                    <li><strong>Attempt Window:</strong> <?php echo floor(LOGIN_ATTEMPT_WINDOW / 3600); ?> hours</li>
                                    <li><strong>Admin Bypass:</strong> Enabled</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-info bg-opacity-10 border-0 py-3">
                        <h5 class="mb-0 text-info fw-bold">
                            <i class="bi bi-clock-history me-2"></i>Recent Login Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $stmt = $pdo->query("
                                SELECT 
                                    ip_address, 
                                    email, 
                                    success, 
                                    attempt_time, 
                                    user_agent
                                FROM login_attempts 
                                ORDER BY attempt_time DESC 
                                LIMIT 20
                            ");
                            $recent_attempts = $stmt->fetchAll();
                        ?>
                                                         <div class="table-responsive">
                                 <table class="table table-sm align-middle">
                                     <thead class="table-light">
                                         <tr>
                                             <th class="border-0 fw-semibold text-dark">Time</th>
                                             <th class="border-0 fw-semibold text-dark">IP</th>
                                             <th class="border-0 fw-semibold text-dark">Email</th>
                                             <th class="border-0 fw-semibold text-dark">Status</th>
                                             <th class="border-0 fw-semibold text-dark">User Agent</th>
                                         </tr>
                                     </thead>
                                    <tbody>
                                        <?php foreach ($recent_attempts as $attempt): ?>
                                            <tr>
                                                <td class="small"><?php echo date('M j, H:i:s', strtotime($attempt['attempt_time'])); ?></td>
                                                <td class="small"><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                                <td class="small"><?php echo htmlspecialchars($attempt['email']); ?></td>
                                                <td>
                                                    <?php if ($attempt['success']): ?>
                                                        <span class="badge bg-success">Success</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Failed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($attempt['user_agent']); ?>">
                                                    <?php echo htmlspecialchars(substr($attempt['user_agent'], 0, 50)); ?>...
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php
                        } catch (Exception $e) {
                            echo "<p class='text-muted'>Unable to load recent activity.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    // Auto-refresh every 30 seconds to show updated status
    setTimeout(function() {
        location.reload();
    }, 30000);
    
    // Show confirmation for bulk unlock
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>

