<?php
/**
 * Mark All Notifications as Read
 * Marks all notifications for current user as read
 */

session_start();
require 'db.php';

header('Content-Type: application/json');

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Mark all notifications as read
$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE user_id = ? AND is_read = 0
");

$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'marked_count' => $affected
    ]);
} else {
    $stmt->close();
    $conn->close();
    
    echo json_encode(['success' => false, 'error' => 'Failed to mark all as read']);
}
?>