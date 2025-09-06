<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all']) && $_POST['mark_all'] === 'true') {
        // Mark all notifications as read
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        echo 'OK';
    } elseif (isset($_POST['notification_id'])) {
        // Mark specific notification as read
        $notification_id = (int)$_POST['notification_id'];
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        echo 'OK';
    } else {
        echo 'Invalid request';
    }
} else {
    echo 'Invalid method';
}
?> 