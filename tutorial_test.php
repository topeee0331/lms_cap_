<?php
$page_title = 'Tutorial Test';
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Tutorial System Test Page</h1>
            
            <div class="alert alert-info">
                <h5><i class="bi bi-info-circle"></i> Tutorial System Features</h5>
                <ul class="mb-0">
                    <li><strong>Auto-start:</strong> Tutorials automatically start on first visit to each page</li>
                    <li><strong>Role-based:</strong> Different tutorials for Students, Teachers, and Admins</li>
                    <li><strong>Persistent:</strong> Once completed, tutorials won't show again (stored in localStorage)</li>
                    <li><strong>Manual trigger:</strong> Floating tutorial button appears if not completed</li>
                    <li><strong>Responsive:</strong> Works on desktop and mobile devices</li>
                </ul>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-gear"></i> Test Controls</h5>
                        </div>
                        <div class="card-body">
                            <p>Use these buttons to test the tutorial system:</p>
                            <button class="btn btn-primary mb-2" onclick="startTutorial()">
                                <i class="bi bi-play-circle"></i> Start Tutorial
                            </button>
                            <br>
                            <button class="btn btn-warning mb-2" onclick="resetTutorial()">
                                <i class="bi bi-arrow-clockwise"></i> Reset Tutorial
                            </button>
                            <br>
                            <button class="btn btn-info" onclick="location.reload()">
                                <i class="bi bi-arrow-repeat"></i> Reload Page
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-question-circle"></i> How to Test</h5>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Click "Reset Tutorial" to clear completion status</li>
                                <li>Click "Reload Page" to see the tutorial auto-start</li>
                                <li>Or click "Start Tutorial" to manually start it</li>
                                <li>Complete the tutorial to see it disappear</li>
                                <li>Reload the page - tutorial won't show again</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-list-check"></i> Current Status</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>User Role:</strong> <span class="badge bg-primary"><?php echo $_SESSION['role'] ?? 'guest'; ?></span></p>
                            <p><strong>Current Page:</strong> <code><?php echo $current_page ?? 'tutorial_test.php'; ?></code></p>
                            <p><strong>Tutorial Status:</strong> <span id="tutorial-status" class="badge bg-secondary">Loading...</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update tutorial status display
document.addEventListener('DOMContentLoaded', function() {
    const userRole = '<?php echo $_SESSION["role"] ?? "guest"; ?>';
    const currentPage = '<?php echo $current_page ?? "tutorial_test.php"; ?>';
    const isCompleted = localStorage.getItem('tutorial_completed_' + userRole + '_' + currentPage);
    
    const statusElement = document.getElementById('tutorial-status');
    if (isCompleted) {
        statusElement.textContent = 'Completed';
        statusElement.className = 'badge bg-success';
    } else {
        statusElement.textContent = 'Not Completed';
        statusElement.className = 'badge bg-warning';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
