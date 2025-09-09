<?php
$page_title = 'Debug Tutorial';
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <h1>Debug Tutorial System</h1>
            
            <div class="alert alert-info">
                <h5>Debug Information</h5>
                <p><strong>User Role:</strong> <?php echo $_SESSION['role'] ?? 'guest'; ?></p>
                <p><strong>Current Page:</strong> <?php echo $current_page ?? 'debug_tutorial.php'; ?></p>
                <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
                <p><strong>Tutorial Mode:</strong> Manual trigger only (no auto-show)</p>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Test Elements</h5>
                        </div>
                        <div class="card-body">
                            <h3>This is a test heading</h3>
                            <p>This is a test paragraph with some content.</p>
                            <button class="btn btn-primary">Test Button 1</button>
                            <button class="btn btn-success">Test Button 2</button>
                            <button class="btn btn-warning">Test Button 3</button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Debug Controls</h5>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-primary mb-2" onclick="startTutorial()">
                                <i class="bi bi-play-circle"></i> Start Tutorial
                            </button>
                            <br>
                            <button class="btn btn-warning mb-2" onclick="resetTutorial()">
                                <i class="bi bi-arrow-clockwise"></i> Reset Tutorial
                            </button>
                            <br>
                            <button class="btn btn-info mb-2" onclick="checkTutorialStatus()">
                                <i class="bi bi-info-circle"></i> Check Status
                            </button>
                            <br>
                            <button class="btn btn-secondary mb-2" onclick="location.reload()">
                                <i class="bi bi-arrow-repeat"></i> Reload Page
                            </button>
                            <br>
                            <button class="btn btn-success" onclick="testModal()">
                                <i class="bi bi-window"></i> Test Modal
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Console Output</h5>
                        </div>
                        <div class="card-body">
                            <p>Open your browser's developer console (F12) to see debug messages.</p>
                            <p>Look for messages starting with "Tutorial System Debug:"</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function checkTutorialStatus() {
    const userRole = '<?php echo $_SESSION["role"] ?? "guest"; ?>';
    const currentPage = '<?php echo $current_page ?? "debug_tutorial.php"; ?>';
    
    alert(`Tutorial Status:\nRole: ${userRole}\nPage: ${currentPage}\nMode: Manual trigger only\nStatus: Always available`);
}

// Tutorial trigger button is automatically added on page load

// Test modal directly
window.testModal = function() {
    if (tutorialSystem) {
        const tutorialData = tutorialSystem.getTutorialData();
        if (tutorialData) {
            tutorialSystem.showTutorialModal(tutorialData);
        }
    }
};
</script>

<?php require_once 'includes/footer.php'; ?>
