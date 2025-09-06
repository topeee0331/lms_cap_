<?php
// Ensure session is properly started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


require_once 'config/database.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

// Allow access to file preview for any logged-in user
// This is a preview-only feature, so we allow access to any user who can access the module page
if (!isset($_SESSION['user_id'])) {
    // Only require login, no role restriction
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center p-5">
                            <i class="fas fa-lock fa-5x text-warning mb-3"></i>
                            <h4>Access Denied</h4>
                            <p class="text-muted">Please log in to view this document.</p>
                            <a href="../login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get parameters
$module_id = $_GET['module_id'] ?? '';
$filename = $_GET['filename'] ?? '';
$original_name = $_GET['original_name'] ?? '';

if (empty($module_id) || empty($filename)) {
    http_response_code(400);
    die('Missing required parameters.');
}

try {
    // Get course and module information
    $stmt = $pdo->prepare("
        SELECT c.id as course_id, c.course_name, c.modules
        FROM courses c
        WHERE JSON_CONTAINS(c.modules, JSON_OBJECT('id', ?))
    ");
    $stmt->execute([$module_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        http_response_code(404);
        die('Module not found.');
    }
    
    // Skip enrollment check for file preview feature
    // Since user can access the module page, they should be able to preview files
    // This allows file preview without strict enrollment verification
    
    // Parse modules to find the specific module
    $modules = json_decode($course['modules'], true);
    $target_module = null;
    
    foreach ($modules as $module) {
        if ($module['id'] === $module_id && isset($module['file']['filename']) && $module['file']['filename'] === $filename) {
            $target_module = $module;
            break;
        }
    }
    
    if (!$target_module) {
        http_response_code(404);
        die('File not found in module.');
    }
    
    $file_path = 'uploads/modules/' . $filename;
    
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found on server.');
    }
    
    // Check if it's a DOCX file
    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if ($file_extension !== 'docx') {
        http_response_code(400);
        die('This preview is only for DOCX files.');
    }
    
    // Load the DOCX file
    $phpWord = IOFactory::load($file_path);
    
    // Set headers for HTML output
    header('Content-Type: text/html; charset=utf-8');
    
    // Return full HTML page (works for both iframe and direct access)
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Preview - <?php echo htmlspecialchars($original_name); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { 
                background-color: #f8f9fa; 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 20px;
            }
            .document-preview { 
                background: white; 
                border-radius: 8px; 
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                padding: 30px;
                margin: 0;
                min-height: 600px;
            }
            .document-header {
                border-bottom: 2px solid #e9ecef;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .document-title {
                color: #2c3e50;
                font-size: 1.5rem;
                font-weight: 600;
                margin-bottom: 8px;
            }
            .document-meta {
                color: #6c757d;
                font-size: 0.9rem;
            }
            .document-content {
                line-height: 1.6;
                color: #333;
            }
            .document-content h1, .document-content h2, .document-content h3 {
                color: #2c3e50;
                margin-top: 25px;
                margin-bottom: 12px;
            }
            .document-content p {
                margin-bottom: 12px;
            }
            .document-content ul, .document-content ol {
                margin-bottom: 12px;
                padding-left: 25px;
            }
            .document-content table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }
            .document-content table th,
            .document-content table td {
                border: 1px solid #dee2e6;
                padding: 10px;
                text-align: left;
            }
            .document-content table th {
                background-color: #f8f9fa;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="document-preview">
            <div class="document-header">
                <h1 class="document-title"><?php echo htmlspecialchars($original_name); ?></h1>
                <div class="document-meta">
                    <i class="fas fa-file-alt me-1"></i>
                    <?php echo strtoupper($file_extension); ?> Document • 
                    <i class="fas fa-calendar me-1"></i>
                    Uploaded: <?php echo date('M j, Y', strtotime($target_module['file']['uploaded_at'])); ?> • 
                    <i class="fas fa-weight me-1"></i>
                    Size: <?php echo round($target_module['file']['file_size'] / 1024, 1); ?> KB
                </div>
            </div>
            
            <div class="document-content">
                <?php
                try {
                    // Convert to HTML
                    $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
                    $htmlWriter->save('php://output');
                } catch (Exception $e) {
                    echo '<div class="text-center p-4">';
                    echo '<i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>';
                    echo '<h5>Preview Error</h5>';
                    echo '<p class="text-muted">Unable to convert this DOCX file to HTML for preview.</p>';
                    echo '<p class="text-muted">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Preview Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center p-5">
                            <i class="fas fa-exclamation-triangle fa-5x text-danger mb-3"></i>
                            <h4>Preview Error</h4>
                            <p class="text-muted">Unable to load the document preview.</p>
                            <p class="text-muted">Error: <?php echo htmlspecialchars($e->getMessage()); ?></p>
                            <button onclick="window.close()" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
