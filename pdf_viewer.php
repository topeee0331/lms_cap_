<?php
session_start();
require_once 'config/database.php';

// Allow access to file preview for any logged-in user
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
    $file_size = filesize($file_path);
    $file_url = 'uploads/modules/' . $filename;
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error loading file: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Viewer - <?php echo htmlspecialchars($original_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        body { margin: 0; padding: 0; background: #f5f5f5; font-family: Arial, sans-serif; }
        .pdf-container { 
            width: 100vw; 
            height: 100vh; 
            display: flex; 
            flex-direction: column; 
        }
        .pdf-header {
            background: #fff;
            padding: 10px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .pdf-viewer {
            flex: 1;
            background: #fff;
            position: relative;
            overflow: auto;
        }
        .pdf-canvas {
            display: block;
            margin: 0 auto;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .pdf-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-info {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
        }
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #666;
        }
        .error {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #dc3545;
            text-align: center;
            padding: 20px;
        }
        .btn-group .btn {
            margin: 0 2px;
        }
        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="pdf-container">
        <div class="pdf-header">
            <div>
                <h5 class="mb-0">
                    <i class="fas fa-file-pdf text-danger me-2"></i>
                    <?php echo htmlspecialchars($original_name); ?>
                </h5>
                <small class="text-muted">
                    Size: <?php echo round($file_size / 1024, 1); ?> KB • 
                    Course: <?php echo htmlspecialchars($course['course_name']); ?>
                </small>
            </div>
            <div class="pdf-controls">
                <div class="zoom-controls">
                    <button class="btn btn-outline-secondary btn-sm" onclick="zoomOut()" title="Zoom Out">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <span class="page-info" id="zoomLevel">100%</span>
                    <button class="btn btn-outline-secondary btn-sm" onclick="zoomIn()" title="Zoom In">
                        <i class="fas fa-search-plus"></i>
                    </button>
                </div>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary btn-sm" onclick="previousPage()" id="prevBtn" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="page-info" id="pageInfo">Page 1 of 1</span>
                    <button class="btn btn-outline-secondary btn-sm" onclick="nextPage()" id="nextBtn" disabled>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="btn-group">
                    <a href="download_module_file.php?module_id=<?php echo urlencode($module_id); ?>&filename=<?php echo urlencode($filename); ?>&original_name=<?php echo urlencode($original_name); ?>" 
                       class="btn btn-primary btn-sm" target="_blank">
                        <i class="fas fa-download me-1"></i>Download
                    </a>
                    <button onclick="window.close()" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
        <div class="pdf-viewer" id="pdfViewer">
            <div class="loading" id="loading">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading PDF...</p>
            </div>
        </div>
    </div>

    <script>
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.0;
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Configure PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({scale: scale});
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };

                const renderTask = page.render(renderContext);
                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });

            document.getElementById('pageInfo').textContent = `Page ${num} of ${pdfDoc.numPages}`;
            document.getElementById('prevBtn').disabled = (num <= 1);
            document.getElementById('nextBtn').disabled = (num >= pdfDoc.numPages);
        }

        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        function onPrevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }

        function onNextPage() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }

        function zoomIn() {
            scale += 0.25;
            document.getElementById('zoomLevel').textContent = Math.round(scale * 100) + '%';
            queueRenderPage(pageNum);
        }

        function zoomOut() {
            if (scale <= 0.25) return;
            scale -= 0.25;
            document.getElementById('zoomLevel').textContent = Math.round(scale * 100) + '%';
            queueRenderPage(pageNum);
        }

        function previousPage() {
            onPrevPage();
        }

        function nextPage() {
            onNextPage();
        }

        // Load PDF
        const loadingTask = pdfjsLib.getDocument('<?php echo $file_url; ?>');
        loadingTask.promise.then(function(pdf) {
            pdfDoc = pdf;
            document.getElementById('loading').style.display = 'none';
            
            // Add canvas to viewer
            canvas.className = 'pdf-canvas';
            document.getElementById('pdfViewer').appendChild(canvas);
            
            renderPage(pageNum);
        }, function(error) {
            console.error('Error loading PDF:', error);
            document.getElementById('loading').innerHTML = `
                <div class="error">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                    <h4>Error Loading PDF</h4>
                    <p>Unable to load the PDF file. This might be due to:</p>
                    <ul class="text-start">
                        <li>File corruption</li>
                        <li>Unsupported PDF format</li>
                        <li>Network issues</li>
                    </ul>
                    <div class="mt-3">
                        <a href="download_module_file.php?module_id=<?php echo urlencode($module_id); ?>&filename=<?php echo urlencode($filename); ?>&original_name=<?php echo urlencode($original_name); ?>" 
                           class="btn btn-primary me-2" target="_blank">
                            <i class="fas fa-download me-1"></i>Download PDF
                        </a>
                        <button onclick="window.close()" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                    </div>
                </div>
            `;
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    previousPage();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    nextPage();
                    break;
                case '+':
                case '=':
                    e.preventDefault();
                    zoomIn();
                    break;
                case '-':
                    e.preventDefault();
                    zoomOut();
                    break;
            }
        });
    </script>
</body>
</html>
