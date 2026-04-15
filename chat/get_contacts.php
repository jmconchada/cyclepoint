<?php
/**
 * Get Contacts List (Real-Time)
 * Returns users you've messaged with
 */

session_start();
require 'db.php';

header('Content-Type: application/json');

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$loggedInUserId = $_SESSION['user_id'];

// Fetch users you've messaged with
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.profile_picture, u.last_seen,
           (SELECT COUNT(*) FROM messages m 
            WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count,
           (SELECT message FROM messages 
            WHERE (sender_id = u.id AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = u.id)
            ORDER BY timestamp DESC LIMIT 1) as last_message,
           (SELECT timestamp FROM messages 
            WHERE (sender_id = u.id AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = u.id)
            ORDER BY timestamp DESC LIMIT 1) as last_timestamp
    FROM users u
    WHERE u.id != ?
      AND EXISTS (
          SELECT 1 FROM messages m 
          WHERE (m.sender_id = u.id AND m.receiver_id = ?)
             OR (m.sender_id = ? AND m.receiver_id = u.id)
      )
    ORDER BY last_timestamp DESC
");

$stmt->bind_param("iiiiiiii", 
    $loggedInUserId, $loggedInUserId, $loggedInUserId, 
    $loggedInUserId, $loggedInUserId, $loggedInUserId,
    $loggedInUserId, $loggedInUserId
);

$stmt->execute();
$result = $stmt->get_result();

$contacts = [];
while ($row = $result->fetch_assoc()) {
    // Check if online (active in last 5 minutes)
    $row['is_online'] = $row['last_seen'] && (strtotime($row['last_seen']) > time() - 300);
    
    // Set profile picture
    if (empty($row['profile_picture']) || !file_exists($row['profile_picture'])) {
        $row['profile_picture'] = 'assets/images/profile-picture.png';
    }
    
    $contacts[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'contacts' => $contacts
]);
?>