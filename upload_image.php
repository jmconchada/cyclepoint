<?php
/**
 * Upload Image to Chat
 * Handles multiple image uploads like Messenger
 */

session_start();
require 'db.php';

header('Content-Type: application/json');

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$sender_id = $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;

// Validate receiver
if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid receiver']);
    exit;
}

// Check if files were uploaded
if (!isset($_FILES['images']) || empty($_FILES['images']['tmp_name'][0])) {
    echo json_encode(['success' => false, 'error' => 'No images uploaded']);
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/chat/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Array to store uploaded image data
$uploaded_images = [];
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

// Process each uploaded file
$file_count = count($_FILES['images']['tmp_name']);

for ($i = 0; $i < $file_count; $i++) {
    // Check if file was uploaded
    if (!isset($_FILES['images']['tmp_name'][$i]) || empty($_FILES['images']['tmp_name'][$i])) {
        continue;
    }
    
    $tmp_name = $_FILES['images']['tmp_name'][$i];
    $original_name = $_FILES['images']['name'][$i];
    $file_size = $_FILES['images']['size'][$i];
    $file_type = $_FILES['images']['type'][$i];
    
    // Validate file type
    if (!in_array($file_type, $allowed_types)) {
        continue; // Skip non-image files
    }
    
    // Validate file size
    if ($file_size > $max_size) {
        continue; // Skip files larger than 5MB
    }
    
    // Generate unique filename
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $new_filename = uniqid('chat_' . $sender_id . '_', true) . '.' . $extension;
    $file_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($tmp_name, $file_path)) {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, image_path, status, is_read, timestamp) VALUES (?, ?, '[Image]', ?, 'sent', 0, NOW())");
        $stmt->bind_param("iis", $sender_id, $receiver_id, $file_path);
        
        if ($stmt->execute()) {
            $message_id = $conn->insert_id;
            
            // Get sender info
            $senderStmt = $conn->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
            $senderStmt->bind_param("i", $sender_id);
            $senderStmt->execute();
            $senderResult = $senderStmt->get_result();
            $senderData = $senderResult->fetch_assoc();
            $senderStmt->close();
            
            // Set default profile picture
            $sender_picture = 'assets/images/profile-picture.png';
            if (!empty($senderData['profile_picture']) && file_exists($senderData['profile_picture'])) {
                $sender_picture = $senderData['profile_picture'];
            }
            
            // Add to uploaded images array
            $uploaded_images[] = [
                'id' => $message_id,
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'message' => '[Image]',
                'image_path' => $file_path,
                'timestamp' => date('Y-m-d H:i:s'),
                'is_read' => 0,
                'sender_picture' => $sender_picture,
                'sender_name' => $senderData['name']
            ];
        }
        
        $stmt->close();
    }
}

// Create notification for receiver
if (!empty($uploaded_images) && file_exists('notification_functions.php')) {
    require_once 'notification_functions.php';
    notifyNewMessage($receiver_id, $sender_id);
}

// Return response
if (!empty($uploaded_images)) {
    echo json_encode([
        'success' => true,
        'images' => $uploaded_images,
        'count' => count($uploaded_images)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'No images were uploaded successfully'
    ]);
}

$conn->close();
?>