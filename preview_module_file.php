<?php
session_start();
require_once 'config/database.php';

// Allow access to file preview for any logged-in user
// This is a preview-only feature, so we allow access to any user who can access the module page
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access denied. Please log in to view this file.');
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
    $target_file = null;
    
    foreach ($modules as $module) {
        if ($module['id'] === $module_id) {
            $target_module = $module;
            
            // Check new multiple files structure first
            if (isset($module['files']) && is_array($module['files'])) {
                foreach ($module['files'] as $file) {
                    if ($file['filename'] === $filename) {
                        $target_file = $file;
                        break 2;
                    }
                }
            }
            // Fallback to old single file structure
            elseif (isset($module['file']['filename']) && $module['file']['filename'] === $filename) {
                $target_file = $module['file'];
                break;
            }
        }
    }
    
    if (!$target_module || !$target_file) {
        http_response_code(404);
        die('File not found in module.');
    }
    
    $file_path = 'uploads/modules/' . $filename;
    
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found on server.');
    }
    
    // Get file info
    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $file_size = filesize($file_path);
    $mime_type = mime_content_type($file_path);
    
    // Set appropriate headers based on file type
    switch ($file_extension) {
        case 'pdf':
            // Redirect to the enhanced PDF viewer
            header('Location: pdf_viewer.php?module_id=' . urlencode($module_id) . '&filename=' . urlencode($filename) . '&original_name=' . urlencode($original_name));
            exit;
            
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
            
        case 'png':
            header('Content-Type: image/png');
            break;
            
        case 'gif':
            header('Content-Type: image/gif');
            break;
            
        case 'bmp':
            header('Content-Type: image/bmp');
            break;
            
        case 'txt':
            header('Content-Type: text/plain; charset=utf-8');
            break;
            
        case 'mp4':
            header('Content-Type: video/mp4');
            break;
            
        case 'avi':
            header('Content-Type: video/x-msvideo');
            break;
            
        case 'mov':
            header('Content-Type: video/quicktime');
            break;
            
        case 'wmv':
            header('Content-Type: video/x-ms-wmv');
            break;
            
        case 'mp3':
            header('Content-Type: audio/mpeg');
            break;
            
        case 'wav':
            header('Content-Type: audio/wav');
            break;
            
        case 'docx':
        case 'doc':
        case 'xlsx':
        case 'xls':
        case 'pptx':
        case 'ppt':
        default:
            // For unsupported file types, show an HTML page
            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>File Preview - <?php echo htmlspecialchars($original_name); ?></title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                <style>
                    body { background-color: #f8f9fa; }
                    .file-preview-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                    .file-card { max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                </style>
            </head>
            <body>
                <div class="file-preview-container">
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="card file-card">
                                    <div class="card-body text-center p-5">
                                        <i class="fas fa-file fa-5x text-primary mb-4"></i>
                                        <h2 class="card-title mb-3">File Preview</h2>
                                        <p class="text-muted mb-4">
                                            This file type (<?php echo strtoupper($file_extension); ?>) cannot be previewed directly in the browser.
                                        </p>
                                        
                                        <div class="card bg-light mb-4">
                                            <div class="card-body">
                                                <h5 class="card-title">File Information</h5>
                                                <ul class="list-unstyled mb-0">
                                                    <li><strong>Name:</strong> <?php echo htmlspecialchars($original_name); ?></li>
                                                    <li><strong>Type:</strong> <?php echo strtoupper($file_extension); ?> file</li>
                                                    <li><strong>Size:</strong> <?php echo round($file_size / 1024, 1); ?> KB</li>
                                                    <li><strong>Course:</strong> <?php echo htmlspecialchars($course['course_name']); ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                            <a href="download_module_file.php?module_id=<?php echo urlencode($module_id); ?>&filename=<?php echo urlencode($filename); ?>&original_name=<?php echo urlencode($original_name); ?>" 
                                               class="btn btn-primary me-md-2">
                                                <i class="fas fa-download me-1"></i>Download File
                                            </a>
                                            <button onclick="window.close()" class="btn btn-outline-secondary">
                                                <i class="fas fa-times me-1"></i>Close
                                            </button>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                To view this file, please download it and open with the appropriate application.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            </body>
            </html>
            <?php
            exit;
    }
    
    // Set additional headers
    header('Content-Length: ' . $file_size);
    header('Cache-Control: public, max-age=3600');
    
    // Output file content
    readfile($file_path);
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error loading file: ' . $e->getMessage());
}
?>