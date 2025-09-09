/**
 * Video Watch Time Tracker
 * Tracks student watch time and sends progress to server
 */

class VideoTracker {
    constructor(videoId, moduleId, minWatchTime) {
        this.videoId = videoId;
        this.moduleId = moduleId;
        this.minWatchTime = minWatchTime * 60; // Convert minutes to seconds
        this.watchDuration = 0;
        this.isTracking = false;
        this.startTime = null;
        this.lastProgressTime = 0;
        this.progressInterval = null;
        this.videoElement = null;
        this.isCompleted = false;
        
        this.init();
    }
    
    init() {
        // Find video element
        this.videoElement = document.querySelector('video, iframe');
        if (!this.videoElement) {
            console.warn('Video element not found');
            return;
        }
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Start progress tracking
        this.startProgressTracking();
    }
    
    setupEventListeners() {
        if (this.videoElement.tagName === 'VIDEO') {
            // For HTML5 video elements
            this.videoElement.addEventListener('play', () => this.startTracking());
            this.videoElement.addEventListener('pause', () => this.pauseTracking());
            this.videoElement.addEventListener('ended', () => this.completeVideo());
            this.videoElement.addEventListener('timeupdate', () => this.updateProgress());
        } else if (this.videoElement.tagName === 'IFRAME') {
            // For YouTube/Vimeo iframes - use postMessage API
            this.setupIframeTracking();
        }
    }
    
    setupIframeTracking() {
        // Listen for messages from iframe
        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin) return;
            
            const data = event.data;
            if (data.type === 'video_progress') {
                this.handleIframeProgress(data);
            }
        });
        
        // Send tracking request to iframe
        this.videoElement.contentWindow.postMessage({
            type: 'start_tracking',
            minWatchTime: this.minWatchTime
        }, '*');
    }
    
    handleIframeProgress(data) {
        if (data.currentTime && data.duration) {
            this.watchDuration = Math.floor(data.currentTime);
            this.updateProgress();
            
            if (data.currentTime >= data.duration * 0.9) { // 90% watched
                this.completeVideo();
            }
        }
    }
    
    startTracking() {
        if (!this.isTracking) {
            this.isTracking = true;
            this.startTime = Date.now();
            console.log('Started tracking video watch time');
        }
    }
    
    pauseTracking() {
        if (this.isTracking) {
            this.isTracking = false;
            this.watchDuration += Math.floor((Date.now() - this.startTime) / 1000);
            console.log('Paused tracking. Total watch time:', this.watchDuration, 'seconds');
        }
    }
    
    updateProgress() {
        if (this.videoElement && this.videoElement.tagName === 'VIDEO') {
            this.watchDuration = Math.floor(this.videoElement.currentTime);
        }
        
        // Send progress every 30 seconds
        const now = Date.now();
        if (now - this.lastProgressTime > 30000) { // 30 seconds
            this.sendProgress();
            this.lastProgressTime = now;
        }
    }
    
    completeVideo() {
        if (!this.isCompleted) {
            this.isCompleted = true;
            this.watchDuration = this.minWatchTime; // Ensure minimum watch time is met
            this.sendProgress(true);
            console.log('Video completed!');
        }
    }
    
    startProgressTracking() {
        // Send progress every 30 seconds
        this.progressInterval = setInterval(() => {
            if (this.isTracking && this.watchDuration > 0) {
                this.sendProgress();
            }
        }, 30000);
    }
    
    sendProgress(isCompleted = false) {
        if (this.watchDuration < this.minWatchTime && !isCompleted) {
            return; // Don't send if minimum watch time not met
        }
        
        const completionPercentage = this.videoElement && this.videoElement.duration 
            ? Math.min(100, Math.floor((this.watchDuration / this.videoElement.duration) * 100))
            : 100;
        
        const data = {
            video_id: this.videoId,
            module_id: this.moduleId,
            watch_duration: this.watchDuration,
            completion_percentage: completionPercentage,
            is_completed: isCompleted
        };
        
        fetch('mark_video_watched_with_time.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.text())
        .then(result => {
            if (result === 'ok') {
                console.log('Progress saved successfully');
                if (isCompleted) {
                    this.showCompletionMessage();
                }
            } else {
                console.error('Failed to save progress:', result);
            }
        })
        .catch(error => {
            console.error('Error saving progress:', error);
        });
    }
    
    showCompletionMessage() {
        // Create a success notification
        const notification = document.createElement('div');
        notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>
            Video completed! Your progress has been saved.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
    
    destroy() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
        }
        this.isTracking = false;
    }
}

// Auto-initialize if video data is available
document.addEventListener('DOMContentLoaded', function() {
    const videoData = window.videoData;
    if (videoData && videoData.videoId && videoData.moduleId) {
        window.videoTracker = new VideoTracker(
            videoData.videoId,
            videoData.moduleId,
            videoData.minWatchTime || 5
        );
    }
});

// Export for manual initialization
window.VideoTracker = VideoTracker;
