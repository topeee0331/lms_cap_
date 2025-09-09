<?php
/**
 * Video Player Component
 * Displays videos with support for YouTube, Vimeo, and direct video files
 */

function renderVideoPlayer($video, $options = []) {
    $defaults = [
        'width' => '100%',
        'height' => '400px',
        'autoplay' => false,
        'controls' => true,
        'show_title' => true,
        'show_description' => true,
        'class' => 'video-player'
    ];
    
    $options = array_merge($defaults, $options);
    $video_url = $video['video_url'] ?? '';
    $video_title = $video['video_title'] ?? '';
    $video_description = $video['video_description'] ?? '';
    
    if (empty($video_url)) {
        return '<div class="alert alert-warning">No video URL provided.</div>';
    }
    
    $player_id = 'video_player_' . uniqid();
    $embed_url = '';
    $is_youtube = false;
    $is_vimeo = false;
    
    // Detect video platform and create embed URL
    if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
        $is_youtube = true;
        $video_id = '';
        
        if (strpos($video_url, 'youtu.be') !== false) {
            $video_id = substr(parse_url($video_url, PHP_URL_PATH), 1);
        } else {
            parse_str(parse_url($video_url, PHP_URL_QUERY), $query);
            $video_id = $query['v'] ?? '';
        }
        
        if ($video_id) {
            $embed_url = "https://www.youtube.com/embed/{$video_id}";
            if ($options['autoplay']) {
                $embed_url .= '?autoplay=1';
            }
        }
    } elseif (strpos($video_url, 'vimeo.com') !== false) {
        $is_vimeo = true;
        $video_id = substr(parse_url($video_url, PHP_URL_PATH), 1);
        
        if ($video_id) {
            $embed_url = "https://player.vimeo.com/video/{$video_id}";
            if ($options['autoplay']) {
                $embed_url .= '?autoplay=1';
            }
        }
    }
    
    ob_start();
    ?>
    <div class="<?php echo htmlspecialchars($options['class']); ?>" style="width: <?php echo $options['width']; ?>;">
        <?php if ($options['show_title'] && !empty($video_title)): ?>
            <h5 class="video-title mb-3"><?php echo htmlspecialchars($video_title); ?></h5>
        <?php endif; ?>
        
        <div class="video-container" style="position: relative; width: 100%; height: <?php echo $options['height']; ?>; background: #000; border-radius: 8px; overflow: hidden;">
            <?php if ($is_youtube || $is_vimeo): ?>
                <iframe 
                    id="<?php echo $player_id; ?>"
                    src="<?php echo $embed_url; ?>"
                    width="100%" 
                    height="100%" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen
                    style="border-radius: 8px;">
                </iframe>
            <?php else: ?>
                <video 
                    id="<?php echo $player_id; ?>"
                    width="100%" 
                    height="100%" 
                    controls="<?php echo $options['controls'] ? 'true' : 'false'; ?>"
                    <?php echo $options['autoplay'] ? 'autoplay' : ''; ?>
                    style="border-radius: 8px;">
                    <source src="<?php echo htmlspecialchars($video_url); ?>" type="video/mp4">
                    <source src="<?php echo htmlspecialchars($video_url); ?>" type="video/webm">
                    <source src="<?php echo htmlspecialchars($video_url); ?>" type="video/ogg">
                    Your browser does not support the video tag.
                </video>
            <?php endif; ?>
        </div>
        
        <?php if ($options['show_description'] && !empty($video_description)): ?>
            <div class="video-description mt-3">
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($video_description)); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($video['file']) && !empty($video['file'])): ?>
            <div class="video-file-info mt-2">
                <small class="text-muted">
                    <i class="bi bi-download me-1"></i>
                    <a href="<?php echo htmlspecialchars($video['file']['file_path']); ?>" download class="text-decoration-none">
                        Download: <?php echo htmlspecialchars($video['file']['original_name']); ?>
                    </a>
                    (<?php echo formatFileSize($video['file']['file_size'] ?? 0); ?>)
                </small>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function renderVideoThumbnail($video, $options = []) {
    $defaults = [
        'width' => '100%',
        'height' => '200px',
        'class' => 'video-thumbnail',
        'show_play_button' => true,
        'clickable' => true
    ];
    
    $options = array_merge($defaults, $options);
    $video_url = $video['video_url'] ?? '';
    $video_title = $video['video_title'] ?? '';
    
    if (empty($video_url)) {
        return '<div class="alert alert-warning">No video URL provided.</div>';
    }
    
    $thumbnail_id = 'video_thumb_' . uniqid();
    $is_youtube = strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false;
    $is_vimeo = strpos($video_url, 'vimeo.com') !== false;
    
    ob_start();
    ?>
    <div class="<?php echo htmlspecialchars($options['class']); ?>" style="width: <?php echo $options['width']; ?>; height: <?php echo $options['height']; ?>; position: relative; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; overflow: hidden; cursor: <?php echo $options['clickable'] ? 'pointer' : 'default'; ?>;">
        <?php if ($is_youtube): ?>
            <?php
            $video_id = '';
            if (strpos($video_url, 'youtu.be') !== false) {
                $video_id = substr(parse_url($video_url, PHP_URL_PATH), 1);
            } else {
                parse_str(parse_url($video_url, PHP_URL_QUERY), $query);
                $video_id = $query['v'] ?? '';
            }
            ?>
            <?php if ($video_id): ?>
                <img src="https://img.youtube.com/vi/<?php echo $video_id; ?>/maxresdefault.jpg" 
                     alt="<?php echo htmlspecialchars($video_title); ?>"
                     style="width: 100%; height: 100%; object-fit: cover;">
            <?php endif; ?>
        <?php elseif ($is_vimeo): ?>
            <div style="width: 100%; height: 100%; background: #1ab7ea; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-play-circle-fill" style="font-size: 3rem; color: white;"></i>
            </div>
        <?php else: ?>
            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-play-circle-fill" style="font-size: 3rem; color: white;"></i>
            </div>
        <?php endif; ?>
        
        <?php if ($options['show_play_button']): ?>
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.7); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-play-fill" style="font-size: 1.5rem; color: white; margin-left: 3px;"></i>
            </div>
        <?php endif; ?>
        
        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.8)); padding: 1rem; color: white;">
            <h6 class="mb-0" style="font-size: 0.9rem;"><?php echo htmlspecialchars($video_title); ?></h6>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
