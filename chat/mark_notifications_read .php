<?php
/**
 * Mark Notifications as Read - FIXED VERSION
 * Call this when user opens the notifications page
 */

session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;

// Log for debugging
error_log("mark_notifications_read.php called for user: $user_id");

if ($user_id > 0) {
    // First, check how many unread notifications exist
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $beforeCount = $checkResult->fetch_assoc()['count'];
    $checkStmt->close();
    
    error_log("Unread notifications before: $beforeCount");
    
    // Mark all notifications as read for this user
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            error_log("Notifications marked as read: $affected_rows");
            
            // Verify the update worked
            $verifyStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $verifyStmt->bind_param("i", $user_id);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            $afterCount = $verifyResult->fetch_assoc()['count'];
            $verifyStmt->close();
            
            error_log("Unread notifications after: $afterCount");
            
            echo json_encode([
                'success' => true,
                'marked_read' => $affected_rows,
                'before_count' => $beforeCount,
                'after_count' => $afterCount,
                'message' => "$affected_rows notification(s) marked as read"
            ]);
        } else {
            error_log("Failed to execute update: " . $stmt->error);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to mark notifications as read: ' . $stmt->error
            ]);
        }
    } else {
        error_log("Failed to prepare statement: " . $conn->error);
        echo json_encode([
            'success' => false,
            'error' => 'Database prepare failed: ' . $conn->error
        ]);
    }
} else {
    error_log("User not logged in");
    echo json_encode([
        'success' => false,
        'error' => 'User not logged in'
    ]);
}

$conn->close();
?>