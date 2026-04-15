<?php
/**
 * Mark Messages as Read
 * Call this when user opens the chat page
 */

session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id > 0) {
    // Mark all messages as read for this user (using is_read column)
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'marked_read' => $affected_rows,
                'message' => "$affected_rows message(s) marked as read"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to mark messages as read'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Database prepare failed'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'User not logged in'
    ]);
}

$conn->close();
?>