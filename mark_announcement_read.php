<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_POST['id'])) {
    http_response_code(403);
    exit('ERR');
}
$user_id = (int)$_SESSION['user_id'];
$ann_id = (int)$_POST['id'];
// First, get the current read_by array for this announcement
$stmt = $db->prepare('SELECT read_by FROM announcements WHERE id = ?');
$stmt->execute([$ann_id]);
$announcement = $stmt->fetch();

if ($announcement) {
    // Parse the existing read_by JSON or create empty array
    $read_by = $announcement['read_by'] ? json_decode($announcement['read_by'], true) : [];
    
    // Add current user if not already in the array
    if (!in_array($user_id, $read_by)) {
        $read_by[] = $user_id;
        
        // Update the announcement with the new read_by array
        $update_stmt = $db->prepare('UPDATE announcements SET read_by = ? WHERE id = ?');
        if ($update_stmt->execute([json_encode($read_by), $ann_id])) {
            echo 'OK';
        } else {
            http_response_code(500);
            echo 'ERR';
        }
    } else {
        // User already read this announcement
        echo 'OK';
    }
} else {
    http_response_code(404);
    echo 'ERR';
} 