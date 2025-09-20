<?php
/**
 * Admin interface to unlock locked accounts
 * Allows administrators to manually unlock users or IP addresses
 */

require_once '../config/config.php';
require_once '../config/database.php';
?>

<style>
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* Enhanced Unlock Accounts Page Styling - Inspired by Admin Dashboard */
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
    background: rgba(255,255,255,0.05);
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
    background: rgba(255,255,255,0.1);
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

/* Statistics Cards */
.stats-card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    transition: var(--transition);
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
}

/* Card Headers */
.card-header {
    background: #f8f9fa;
    border-bottom: 2px solid var(--accent-green);
    padding: 1.25rem 1.5rem;
}

.card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 1.1rem;
}

/* Scrollable Table Container */
.scrollable-table {
    max-height: 500px;
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
.btn-success {
    background: #198754;
    color: white;
    border: none;
}

.btn-success:hover {
    background: #146c43;
    color: white;
}

.btn-warning {
    background: #ffc107;
    color: #000;
    border: none;
}

.btn-warning:hover {
    background: #ffca2c;
    color: #000;
}

/* Responsive Design */
@media (max-width: 768px) {
    .welcome-title {
        font-size: 2rem;
    }
    
    .card-header {
        padding: 1rem;
    }
}
</style>

<?php

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

<div class="page-container">
    <div class="container-fluid py-4">
        <!-- Enhanced Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Account Unlock Management</h1>
                    <p class="welcome-subtitle">Manage locked accounts and IP addresses for security</p>
                    <a href="dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="welcome-decoration">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div class="floating-shapes"></div>
                </div>
            </div>
            <div class="accent-line"></div>
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
                <div class="card stats-card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="stats-icon bg-primary text-white mx-auto">
                            <i class="bi bi-envelope-fill"></i>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark"><?php echo count($locked_emails); ?></h3>
                        <p class="text-muted mb-0 small fw-medium">Locked Emails</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="stats-icon bg-warning text-white mx-auto">
                            <i class="bi bi-network-wired"></i>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark"><?php echo count($locked_ips); ?></h3>
                        <p class="text-muted mb-0 small fw-medium">Locked IPs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="stats-icon bg-danger text-white mx-auto">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark"><?php echo MAX_LOGIN_ATTEMPTS; ?></h3>
                        <p class="text-muted mb-0 small fw-medium">Max Attempts</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="stats-icon bg-info text-white mx-auto">
                            <i class="bi bi-clock-fill"></i>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark"><?php echo floor(LOGIN_LOCKOUT_DURATION / 60); ?>m</h3>
                        <p class="text-muted mb-0 small fw-medium">Lockout Duration</p>
                    </div>
                </div>
            </div>
        </div>
                
        <!-- Locked Emails -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header">
                <h5>
                    <i class="bi bi-envelope-fill me-2"></i>Locked Email Accounts
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($locked_emails)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-envelope-check fs-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No email accounts are currently locked</h5>
                        <p class="text-muted">All email accounts are accessible.</p>
                    </div>
                <?php else: ?>
                    <div class="scrollable-table">
                                <table class="table table-hover align-middle mb-0">
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
            <div class="card-header">
                <h5>
                    <i class="bi bi-network-wired me-2"></i>Locked IP Addresses
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($locked_ips)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-shield-check fs-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No IP addresses are currently locked</h5>
                        <p class="text-muted">All IP addresses are accessible.</p>
                    </div>
                <?php else: ?>
                    <div class="scrollable-table">
                                <table class="table table-hover align-middle mb-0">
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
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header">
                <h5>
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
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5>
                    <i class="bi bi-clock-history me-2"></i>Recent Login Activity
                </h5>
            </div>
            <div class="card-body p-0">
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
                <div class="scrollable-table">
                                 <table class="table table-sm align-middle mb-0">
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
                    echo "<div class='text-center py-5'><p class='text-muted'>Unable to load recent activity.</p></div>";
                }
                ?>
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

