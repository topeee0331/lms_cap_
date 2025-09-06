<?php
/**
 * Badge Notification System
 * Displays notifications when students earn new badges
 */

function displayBadgeNotifications() {
    if (isset($_SESSION['badges_earned']) && !empty($_SESSION['badges_earned'])) {
        $badges = $_SESSION['badges_earned'];
        unset($_SESSION['badges_earned']); // Clear after displaying
        
        echo '<div class="badge-notifications">';
        foreach ($badges as $badge_name) {
            echo '
            <div class="alert alert-success alert-dismissible fade show badge-alert" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-trophy fa-2x me-3 text-warning"></i>
                    <div>
                        <h6 class="alert-heading mb-1">🎉 New Badge Earned!</h6>
                        <p class="mb-0">Congratulations! You earned the <strong>' . htmlspecialchars($badge_name) . '</strong> badge.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
        echo '</div>';
        
        echo '
        <style>
        .badge-notifications {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        .badge-alert {
            animation: slideInRight 0.5s ease-out;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            /* Remove any hover or active effect */
            transition: none;
            cursor: default;
        }
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        </style>';
    }
}
?> 