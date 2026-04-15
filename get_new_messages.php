<?php
/**
 * Get new messages (for polling)
 * More stable than your current fetch_messages.php
 */

session_start();
require 'db.php';

header('Content-Type: application/json');

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$last_message_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Validate
if ($other_user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

// Get new messages since last_message_id
$stmt = $conn->prepare("
    SELECT m.*, u.name as sender_name, u.profile_picture as sender_picture
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE ((m.sender_id = ? AND m.receiver_id = ?)
       OR (m.sender_id = ? AND m.receiver_id = ?))
      AND m.id > ?
    ORDER BY m.timestamp ASC
");

$stmt->bind_param("iiiii", $logged_in_user_id, $other_user_id, $other_user_id, $logged_in_user_id, $last_message_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    // Set default profile picture
    if (empty($row['sender_picture']) || !file_exists($row['sender_picture'])) {
        $row['sender_picture'] = 'assets/images/profile-picture.png';
    }
    
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'receiver_id' => $row['receiver_id'],
        'message' => $row['message'],
        'timestamp' => $row['timestamp'],
        'is_read' => $row['is_read'],
        'sender_name' => $row['sender_name'],
        'sender_picture' => $row['sender_picture']
    ];
}

$stmt->close();

// Mark received messages as read
if (!empty($messages)) {
    $markRead = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $markRead->bind_param("ii", $other_user_id, $logged_in_user_id);
    $markRead->execute();
    $markRead->close();
}

// Get online status
$statusStmt = $conn->prepare("SELECT last_seen FROM users WHERE id = ?");
$statusStmt->bind_param("i", $other_user_id);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();
$statusData = $statusResult->fetch_assoc();
$statusStmt->close();

$is_online = $statusData && $statusData['last_seen'] && (strtotime($statusData['last_seen']) > time() - 300);

// Response
echo json_encode([
    'success' => true,
    'messages' => $messages,
    'is_online' => $is_online,
    'count' => count($messages)
]);

$conn->close();
?>