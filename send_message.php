<?php
/**
 * Send Message - Improved Version
 * Better validation and error handling
 * ✅ NOW HANDLES PRE-FILLED IMAGES FROM ITEM DETAILS
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
$image_path = null;

// ✅ CHECK IF THIS IS A PRE-FILLED IMAGE REQUEST (FormData)
if (isset($_POST['prefilled_image']) && !empty($_POST['prefilled_image'])) {
    // This is from item_details.php - handle FormData
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $prefilled_image = trim($_POST['prefilled_image']);
    
    // Validate receiver
    if ($receiver_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid receiver']);
        exit;
    }
    
    // Validate message
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit;
    }
    
    // ✅ COPY THE ITEM IMAGE TO CHAT UPLOADS FOLDER
    if (file_exists($prefilled_image)) {
        // Create chat uploads directory if it doesn't exist
        $upload_dir = 'uploads/chat/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($prefilled_image, PATHINFO_EXTENSION);
        $new_filename = 'item_' . time() . '_' . uniqid() . '.' . $file_extension;
        $new_path = $upload_dir . $new_filename;
        
        // Copy the image (don't move, so original stays)
        if (copy($prefilled_image, $new_path)) {
            $image_path = $new_path;
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to copy image']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Item image not found']);
        exit;
    }
    
} else {
    // Regular JSON request (text-only message)
    $data = json_decode(file_get_contents('php://input'), true);
    
    $receiver_id = isset($data['receiver_id']) ? (int)$data['receiver_id'] : 0;
    $message = isset($data['message']) ? trim($data['message']) : '';
    
    // Validation
    if ($receiver_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid receiver']);
        exit;
    }
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit;
    }
}

// Check message length
if (strlen($message) > 5000) {
    echo json_encode(['success' => false, 'error' => 'Message too long (max 5000 characters)']);
    exit;
}

// Check if receiver exists
$checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
$checkUser->bind_param("i", $receiver_id);
$checkUser->execute();
$userResult = $checkUser->get_result();

if ($userResult->num_rows === 0) {
    $checkUser->close();
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}
$checkUser->close();

// ✅ INSERT MESSAGE WITH IMAGE PATH (if exists)
if ($image_path) {
    // Message with image
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, image_path, status, is_read, timestamp) VALUES (?, ?, ?, ?, 'sent', 0, NOW())");
    $stmt->bind_param("iiss", $sender_id, $receiver_id, $message, $image_path);
} else {
    // Text-only message
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, status, is_read, timestamp) VALUES (?, ?, ?, 'sent', 0, NOW())");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
}

if ($stmt->execute()) {
    $message_id = $conn->insert_id;
    
    // Create notification for receiver
    if (file_exists('notification_functions.php')) {
        require_once 'notification_functions.php';
        notifyNewMessage($receiver_id, $sender_id);
    }
    
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
    
    $stmt->close();
    
    // Return success
    echo json_encode([
        'success' => true,
        'message_id' => $message_id,
        'message' => htmlspecialchars($message),
        'image_path' => $image_path, // ✅ Include image path in response
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'sender_picture' => $sender_picture,
        'sender_name' => $senderData['name']
    ]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>