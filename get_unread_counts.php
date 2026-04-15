<?php
/**
 * Get Unread Counts (AJAX endpoint)
 * Returns current unread message and notification counts
 * Called periodically to update badges in real-time
 */

session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id > 0) {
    $unread_messages = 0;
    $unread_notifications = 0;
    
    // Count unread messages (using is_read column)
    $msgStmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    if ($msgStmt) {
        $msgStmt->bind_param("i", $user_id);
        $msgStmt->execute();
        $msgResult = $msgStmt->get_result();
        if ($msgRow = $msgResult->fetch_assoc()) {
            $unread_messages = (int)$msgRow['count'];
        }
        $msgStmt->close();
    }
    
    // Count unread notifications
    $notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    if ($notifStmt) {
        $notifStmt->bind_param("i", $user_id);
        $notifStmt->execute();
        $notifResult = $notifStmt->get_result();
        if ($notifRow = $notifResult->fetch_assoc()) {
            $unread_notifications = (int)$notifRow['count'];
        }
        $notifStmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $unread_messages,
        'notifications' => $unread_notifications,
        'timestamp' => time()
    ]);
} else {
    echo json_encode([
        'success' => false,
        'messages' => 0,
        'notifications' => 0,
        'error' => 'User not logged in'
    ]);
}

$conn->close();
?>