<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Settings;

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
    
    // Check if it's a PPTX file
    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if ($file_extension !== 'pptx') {
        http_response_code(400);
        die('This preview is only for PPTX files.');
    }
    
    // Load the PPTX file
    $presentation = IOFactory::load($file_path);
    
    // Set headers for HTML output
    header('Content-Type: text/html; charset=utf-8');
    
    // Return full HTML page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PPTX Preview - <?php echo htmlspecialchars($original_name); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            .slide-container {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                margin: 10px 0;
                padding: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .slide-content {
                background: white;
                border-radius: 4px;
                padding: 20px;
                min-height: 400px;
                position: relative;
            }
            .slide-number {
                position: absolute;
                top: 10px;
                right: 15px;
                background: #007bff;
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 12px;
                font-weight: bold;
            }
            .slide-title {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 20px;
                color: #333;
                border-bottom: 2px solid #007bff;
                padding-bottom: 10px;
            }
            .slide-text {
                font-size: 16px;
                line-height: 1.6;
                color: #555;
            }
            .slide-text h1, .slide-text h2, .slide-text h3 {
                color: #007bff;
                margin-top: 20px;
                margin-bottom: 15px;
            }
            .slide-text ul, .slide-text ol {
                margin-left: 20px;
            }
            .slide-text li {
                margin-bottom: 8px;
            }
            .slide-text p {
                margin-bottom: 15px;
            }
            .slide-text strong {
                color: #333;
                font-weight: 600;
            }
            .slide-text em {
                color: #666;
                font-style: italic;
            }
            .navigation {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1000;
            }
            .nav-btn {
                background: #007bff;
                color: white;
                border: none;
                padding: 10px 15px;
                margin: 0 5px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 16px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }
            .nav-btn:hover {
                background: #0056b3;
            }
            .nav-btn:disabled {
                background: #6c757d;
                cursor: not-allowed;
            }
            .slide-counter {
                background: rgba(0,0,0,0.7);
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 12px;
                margin: 0 10px;
            }
            .no-content {
                text-align: center;
                color: #6c757d;
                font-style: italic;
                padding: 40px;
            }
            .download-btn {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
            }
        </style>
    </head>
    <body>
        <div class="download-btn">
            <a href="../preview_module_file.php?module_id=<?php echo urlencode($module_id); ?>&filename=<?php echo urlencode($filename); ?>&original_name=<?php echo urlencode($original_name); ?>" 
               class="btn btn-success" target="_blank">
                <i class="fas fa-download me-1"></i>Download Original
            </a>
        </div>

        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4">
                        <i class="fas fa-file-powerpoint text-primary me-2"></i>
                        <?php echo htmlspecialchars($original_name); ?>
                    </h2>
                    
                    <div id="slides-container">
                        <?php
                        $slideCount = 0;
                        foreach ($presentation->getAllSlides() as $slide) {
                            $slideCount++;
                            echo '<div class="slide-container" id="slide-' . $slideCount . '">';
                            echo '<div class="slide-content">';
                            echo '<div class="slide-number">Slide ' . $slideCount . '</div>';
                            
                            // Get slide content
                            $content = '';
                            
                            // Process shapes in the slide
                            foreach ($slide->getShapeCollection() as $shape) {
                                if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                                    $content .= '<div class="slide-text">';
                                    foreach ($shape->getParagraphs() as $paragraph) {
                                        $text = '';
                                        foreach ($paragraph->getRichTextElements() as $element) {
                                            if ($element instanceof \PhpOffice\PhpPresentation\Shape\RichText\Run) {
                                                $text .= $element->getText();
                                            } else {
                                                $text .= $element->getText();
                                            }
                                        }
                                        
                                        // Check if this looks like a title (first paragraph or larger text)
                                        if (empty($content) || strpos($content, '<h1') === false) {
                                            $content .= '<h1>' . htmlspecialchars($text) . '</h1>';
                                        } else {
                                            $content .= '<p>' . htmlspecialchars($text) . '</p>';
                                        }
                                    }
                                    $content .= '</div>';
                                }
                            }
                            
                            if (empty(trim(strip_tags($content)))) {
                                $content = '<div class="no-content">This slide appears to be empty or contains only images/graphics that cannot be displayed in text format.</div>';
                            }
                            
                            echo $content;
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="navigation">
            <button class="nav-btn" id="prevBtn" onclick="previousSlide()" disabled>
                <i class="fas fa-chevron-left"></i>
            </button>
            <span class="slide-counter" id="slideCounter">1 / <?php echo $slideCount; ?></span>
            <button class="nav-btn" id="nextBtn" onclick="nextSlide()" <?php echo $slideCount <= 1 ? 'disabled' : ''; ?>>
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            let currentSlide = 1;
            const totalSlides = <?php echo $slideCount; ?>;
            
            function showSlide(slideNumber) {
                // Hide all slides
                for (let i = 1; i <= totalSlides; i++) {
                    const slide = document.getElementById('slide-' + i);
                    if (slide) {
                        slide.style.display = 'none';
                    }
                }
                
                // Show current slide
                const currentSlideElement = document.getElementById('slide-' + slideNumber);
                if (currentSlideElement) {
                    currentSlideElement.style.display = 'block';
                }
                
                // Update navigation buttons
                const prevBtn = document.getElementById('prevBtn');
                const nextBtn = document.getElementById('nextBtn');
                const slideCounter = document.getElementById('slideCounter');
                
                prevBtn.disabled = slideNumber <= 1;
                nextBtn.disabled = slideNumber >= totalSlides;
                slideCounter.textContent = slideNumber + ' / ' + totalSlides;
            }
            
            function nextSlide() {
                if (currentSlide < totalSlides) {
                    currentSlide++;
                    showSlide(currentSlide);
                }
            }
            
            function previousSlide() {
                if (currentSlide > 1) {
                    currentSlide--;
                    showSlide(currentSlide);
                }
            }
            
            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowRight' || e.key === ' ') {
                    e.preventDefault();
                    nextSlide();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    previousSlide();
                }
            });
            
            // Initialize - show first slide
            showSlide(1);
        </script>
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
        <title>Error - PPTX Preview</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error Loading PPTX File
                        </h4>
                        <p>There was an error loading the PowerPoint presentation. This could be due to:</p>
                        <ul>
                            <li>File corruption</li>
                            <li>Unsupported PPTX format</li>
                            <li>File size limitations</li>
                        </ul>
                        <hr>
                        <p class="mb-0">
                            <a href="../preview_module_file.php?module_id=<?php echo urlencode($module_id); ?>&filename=<?php echo urlencode($filename); ?>&original_name=<?php echo urlencode($original_name); ?>" 
                               class="btn btn-primary" target="_blank">
                                <i class="fas fa-download me-1"></i>Download Original File
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
