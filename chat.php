<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include_once "db.php";

$loggedInUserId = $_SESSION['user_id'];

// ✅ HANDLE DELETE CHAT ACTION (removes conversation + user from list)
if (isset($_POST['delete_chat']) && isset($_POST['other_user_id'])) {
    $otherUserId = (int)$_POST['other_user_id'];
    
    // Delete all messages between the two users
    $deleteStmt = $conn->prepare("DELETE FROM messages 
                                  WHERE (sender_id = ? AND receiver_id = ?) 
                                     OR (sender_id = ? AND receiver_id = ?)");
    $deleteStmt->bind_param("iiii", $loggedInUserId, $otherUserId, $otherUserId, $loggedInUserId);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Redirect back to chat home (no user selected)
    header("Location: chat.php");
    exit;
}

// ✅ MARK ALL MESSAGES AS READ WHEN CHAT PAGE OPENS
$markReadStmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
if ($markReadStmt) {
    $markReadStmt->bind_param("i", $loggedInUserId);
    $markReadStmt->execute();
    $markReadStmt->close();
}

// Update last_seen
$updateSeenStmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
if ($updateSeenStmt) {
    $updateSeenStmt->bind_param("i", $loggedInUserId);
    $updateSeenStmt->execute();
    $updateSeenStmt->close();
}

// Get logged-in user info
$userStmt = $conn->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$userStmt->bind_param("i", $loggedInUserId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$loggedInUser = $userResult->fetch_assoc();
$userStmt->close();

// Set profile picture for logged-in user (fetch from database, not session)
$myProfilePic = !empty($loggedInUser['profile_picture']) ? $loggedInUser['profile_picture'] : 'assets/images/profile-picture.png';

// ✅ Get selected user ID from URL (support both 'user_id' and 'user' parameters)
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_GET['user']) ? (int)$_GET['user'] : null);

// ✅ Get pre-filled message and image from URL (from item_details.php)
$prefilledMessage = isset($_GET['message']) ? urldecode($_GET['message']) : '';
$prefilledImage = isset($_GET['image']) ? urldecode($_GET['image']) : '';

// ✅ ONLY SHOW USERS YOU'VE MESSAGED WITH (NOT ALL REGISTERED USERS)
$usersStmt = $conn->prepare("
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

$usersStmt->bind_param("iiiiiiii", 
    $loggedInUserId, $loggedInUserId, $loggedInUserId, 
    $loggedInUserId, $loggedInUserId, $loggedInUserId,
    $loggedInUserId, $loggedInUserId
);
$usersStmt->execute();
$usersResult = $usersStmt->get_result();

$users = [];
while ($row = $usersResult->fetch_assoc()) {
    // Check if online (active in last 5 minutes)
    $row['is_online'] = $row['last_seen'] && (strtotime($row['last_seen']) > time() - 300);
    
    // Set profile picture (fetch from database)
    $row['profile_picture'] = !empty($row['profile_picture']) ? $row['profile_picture'] : 'assets/images/profile-picture.png';
    
    $users[] = $row;
}
$usersStmt->close();

// Get selected user info and messages if user is selected
$selectedUser = null;
$messages = [];

if ($selectedUserId) {
    // Get selected user info
    $selectedUserStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $selectedUserStmt->bind_param("i", $selectedUserId);
    $selectedUserStmt->execute();
    $selectedUserResult = $selectedUserStmt->get_result();
    $selectedUser = $selectedUserResult->fetch_assoc();
    $selectedUserStmt->close();
    
    if ($selectedUser) {
        // Set profile picture (fetch from database)
        $selectedUser['profile_picture'] = !empty($selectedUser['profile_picture']) ? $selectedUser['profile_picture'] : 'assets/images/profile-picture.png';
        
        $selectedUser['is_online'] = $selectedUser['last_seen'] && (strtotime($selectedUser['last_seen']) > time() - 300);
        
        // Get messages
        $messagesStmt = $conn->prepare("
            SELECT m.*, u.name as sender_name, u.profile_picture as sender_picture
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.timestamp ASC
        ");
        $messagesStmt->bind_param("iiii", $loggedInUserId, $selectedUserId, $selectedUserId, $loggedInUserId);
        $messagesStmt->execute();
        $messagesResult = $messagesStmt->get_result();
        
        while ($msg = $messagesResult->fetch_assoc()) {
            // Set profile picture (fetch from database)
            $msg['sender_picture'] = !empty($msg['sender_picture']) ? $msg['sender_picture'] : 'assets/images/profile-picture.png';
            $messages[] = $msg;
        }
        $messagesStmt->close();
        
        // Mark messages as read
        $markReadStmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $markReadStmt->bind_param("ii", $selectedUserId, $loggedInUserId);
        $markReadStmt->execute();
        $markReadStmt->close();
    }
}

// ── Fetch listings for trade modal (MY items + PARTNER items, active only) ──
$myListings      = [];
$partnerListings = [];

if ($selectedUserId) {
    // My active listings
    $myListStmt = $conn->prepare("
        SELECT l.id, l.title,
               (SELECT path FROM listing_images WHERE listing_id = l.id LIMIT 1) as image
        FROM listings l
        WHERE l.user_id = ? AND l.status = 'active'
        ORDER BY l.created_at DESC
    ");
    $myListStmt->bind_param("i", $loggedInUserId);
    $myListStmt->execute();
    $myListRes = $myListStmt->get_result();
    while ($r = $myListRes->fetch_assoc()) { $myListings[] = $r; }
    $myListStmt->close();

    // Partner's active listings
    $ptListStmt = $conn->prepare("
        SELECT l.id, l.title,
               (SELECT path FROM listing_images WHERE listing_id = l.id LIMIT 1) as image
        FROM listings l
        WHERE l.user_id = ? AND l.status = 'active'
        ORDER BY l.created_at DESC
    ");
    $ptListStmt->bind_param("i", $selectedUserId);
    $ptListStmt->execute();
    $ptListRes = $ptListStmt->get_result();
    while ($r = $ptListRes->fetch_assoc()) { $partnerListings[] = $r; }
    $ptListStmt->close();
}

// Helper function for time display
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'd';
    return date('M j', $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - CyclePoint</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/chat.css">
    
    <script>
        const isLoggedIn = true;
        window.loggedInUserId = <?php echo $loggedInUserId; ?>;
        window.selectedUserId = <?php echo $selectedUserId ?? 'null'; ?>;
        window.myProfilePic = '<?php echo addslashes($myProfilePic); ?>';
        
        // ✅ Pre-filled message data from item_details.php
        window.prefilledMessage = <?php echo json_encode($prefilledMessage); ?>;
        window.prefilledImage = <?php echo json_encode($prefilledImage); ?>;
    </script>
</head>
<body>

<div class="chat-container">

    <!-- LEFT PANEL: User List -->
    <div class="user-list">
        
        <!-- Header -->
        <div class="user-list-header">
            <h3><i class="fas fa-comments"></i> Messages</h3>
            <a href="index.php" class="home-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <!-- Search -->
        <div class="user-search">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchUserInput" placeholder="Search conversations..." />
        </div>

        <!-- Users Container -->
        <div id="usersContainer">
            <?php if (empty($users)): ?>
                <div class="no-users">
                    <i class="fas fa-inbox"></i>
                    <p>No conversations yet</p>
                    <small>Start chatting by visiting item listings and clicking "Chat With Owner"</small>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <a href="chat.php?user_id=<?= $user['id'] ?>" 
                       class="user-item <?= ($selectedUserId == $user['id']) ? 'active' : '' ?> <?= ($user['unread_count'] > 0) ? 'unread' : '' ?>"
                       data-user-id="<?= $user['id'] ?>">
                        
                        <!-- Avatar -->
                        <div class="user-avatar">
                            <img src="<?= htmlspecialchars($user['profile_picture']) ?>" 
                                 alt="<?= htmlspecialchars($user['name']) ?>">
                            <?php if ($user['is_online']): ?>
                                <span class="online-dot"></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Info -->
                        <div class="user-info">
                            <div class="user-header">
                                <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                                <?php if (!empty($user['last_timestamp'])): ?>
                                    <span class="user-time"><?= timeAgo($user['last_timestamp']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="user-preview">
                                <span class="last-msg <?= ($user['unread_count'] > 0) ? 'unread-text' : '' ?>">
                                    <?php if (!empty($user['last_message'])): ?>
                                        <?= htmlspecialchars(substr($user['last_message'], 0, 35)) ?>
                                        <?= strlen($user['last_message']) > 35 ? '...' : '' ?>
                                    <?php else: ?>
                                        <em>No messages yet</em>
                                    <?php endif; ?>
                                </span>
                                
                                <?php if ($user['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?= $user['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- RIGHT PANEL: Chat Area -->
    <div class="chat-panel">
        
        <?php if ($selectedUser): ?>
            
            <!-- Chat Header -->
            <div class="chat-header">
                <button class="mobile-back-btn" id="mobileBackBtn" title="Back to conversations">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="chat-user">
                    <div class="chat-avatar">
                        <img src="<?= htmlspecialchars($selectedUser['profile_picture']) ?>" 
                             alt="<?= htmlspecialchars($selectedUser['name']) ?>">
                        <?php if ($selectedUser['is_online']): ?>
                            <span class="online-dot"></span>
                        <?php endif; ?>
                    </div>
                    <div class="chat-info">
                        <div class="chat-name"><?= htmlspecialchars($selectedUser['name']) ?></div>
                        <div class="chat-status" id="chatStatus">
                            <?= $selectedUser['is_online'] ? 'Online' : 'Offline' ?>
                        </div>
                    </div>
                </div>
                
                <div class="chat-actions">
                    <button class="action-btn" id="menuBtn" title="Options">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div class="chat-menu" id="chatMenu">
                        <div class="menu-item" onclick="viewProfile(<?= $selectedUser['id'] ?>)">
                            <i class="fas fa-user"></i> View Profile
                        </div>
                        <div class="menu-item menu-item-trade" onclick="openTradeModal()">
                            <i class="fas fa-handshake"></i> Mark as Traded
                        </div>
                        <div class="menu-item menu-item-danger" onclick="deleteChat(<?= $selectedUser['id'] ?>)">
                            <i class="fas fa-trash"></i> Delete Chat
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="chat-messages" id="chatMessages">
                <?php if (empty($messages)): ?>
                    <div class="no-messages">
                        <i class="fas fa-comment-dots"></i>
                        <p>No messages yet</p>
                        <small>Say hi to start the conversation!</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php $isSent = ($msg['sender_id'] == $loggedInUserId); ?>
                        
                        <div class="message <?= $isSent ? 'sent' : 'received' ?>" data-id="<?= $msg['id'] ?>">
                            
                            <?php if (!$isSent): ?>
                                <img src="<?= htmlspecialchars($msg['sender_picture']) ?>" 
                                     class="msg-avatar" 
                                     alt="<?= htmlspecialchars($msg['sender_name']) ?>">
                            <?php endif; ?>
                            
                            <div class="msg-bubble">
                                
                                <?php if (!empty($msg['image_path']) && file_exists($msg['image_path'])): ?>
                                    <div class="msg-image-wrapper">
                                        <img src="<?= htmlspecialchars($msg['image_path']) ?>" 
                                             class="msg-image" 
                                             alt="Image"
                                             onclick="openImageModal('<?= htmlspecialchars($msg['image_path']) ?>')">
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($msg['message']) && $msg['message'] !== '[Image]'): ?>
                                    <div class="msg-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                <?php endif; ?>
                                
                                <div class="msg-time">
                                    <?= date('g:i A', strtotime($msg['timestamp'])) ?>
                                    <?php if ($isSent): ?>
                                        <?php if ($msg['is_read']): ?>
                                            <i class="fas fa-check-double read-check"></i>
                                        <?php else: ?>
                                            <i class="fas fa-check sent-check"></i>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    <?php endforeach; ?>
                    
                    <!-- Typing Indicator (hidden by default) -->
                    <div class="message received typing-indicator" id="typingIndicator" style="display:none;">
                        <img src="<?= htmlspecialchars($selectedUser['profile_picture']) ?>" 
                             class="msg-avatar" 
                             alt="<?= htmlspecialchars($selectedUser['name']) ?>">
                        <div class="msg-bubble">
                            <div class="typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Input Area -->
            <div class="chat-input-wrapper">
                <!-- ✅ Item Preview Area (shows image from item_details) -->
                <div class="item-preview-area" id="itemPreviewArea" style="<?= empty($prefilledImage) ? 'display:none;' : '' ?>">
                    <div class="item-preview">
                        <img src="<?= htmlspecialchars($prefilledImage) ?>" alt="Item Preview" class="item-preview-img" id="itemPreviewImg">
                        <button type="button" class="remove-preview" onclick="removeItemPreview()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- File Preview Area (hidden by default) -->
                <div class="file-preview-area" id="filePreviewArea" style="display:none;">
                    <div class="file-preview-list" id="filePreviewList">
                        <!-- File previews will appear here -->
                    </div>
                </div>
                
                <!-- Main Input Area -->
                <div class="chat-input">
                    <!-- Emoji Button -->
                    <button type="button" class="input-btn" id="emojiBtn" title="Emoji">
                        <i class="fas fa-smile"></i>
                    </button>
                    
                    <!-- File Button -->
                    <button type="button" class="input-btn" id="fileBtn" title="Send Image">
                        <i class="fas fa-image"></i>
                    </button>
                    <input type="file" id="fileInput" accept="image/*" multiple style="display:none;">
                    
                    <!-- Message Input -->
                    <input type="text" 
                           id="messageInput" 
                           placeholder="Type a message..." 
                           autocomplete="off"
                           value="<?= htmlspecialchars($prefilledMessage) ?>">
                    
                    <!-- Send Button -->
                    <button type="button" id="sendBtn" class="send-btn" title="Send">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>

        <?php else: ?>
            
            <!-- No User Selected -->
            <div class="no-chat">
                <div class="welcome-icon-wrapper">
                    <i class="fas fa-comments"></i>
                </div>
                <h2>Welcome to CyclePoint Messages</h2>
                <p>Select a conversation from the left to start chatting</p>
                <div class="welcome-features">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Message item owners directly</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Trade with confidence</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Safe & secure conversations</span>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>

    </div>

</div>

<!-- Hidden form for delete chat -->
<form id="deleteChatForm" method="POST" style="display:none;">
    <input type="hidden" name="delete_chat" value="1">
    <input type="hidden" name="other_user_id" id="deleteChatUserId" value="">
</form>

<!-- Emoji Picker -->
<div class="emoji-picker" id="emojiPicker" style="display:none;">
    <div class="emoji-header">
        <span>Emoji</span>
        <button class="emoji-close" onclick="document.getElementById('emojiPicker').style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="emoji-grid" id="emojiGrid">
        <!-- Emojis will be populated by JavaScript -->
    </div>
</div>

<!-- ===== MARK AS TRADED MODAL ===== -->
<?php if ($selectedUserId && $selectedUser): ?>
<div id="tradeModal" style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,0.65);backdrop-filter:blur(5px);">
  <div style="background:white;border-radius:20px;max-width:560px;width:100%;max-height:90vh;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.3);display:flex;flex-direction:column;">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#1a3c2e,#40916c);color:white;padding:22px 26px;display:flex;align-items:center;gap:14px;flex-shrink:0;">
      <div style="width:48px;height:48px;background:rgba(255,255,255,.18);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">🤝</div>
      <div>
        <h3 style="margin:0 0 3px;font-size:18px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;">Mark as Traded</h3>
        <p style="margin:0;font-size:13px;opacity:.8;font-family:'Plus Jakarta Sans',sans-serif;">Each user selects the item they offered</p>
      </div>
      <button onclick="closeTradeModal()" style="margin-left:auto;background:rgba(255,255,255,.18);border:1.5px solid rgba(255,255,255,.3);color:white;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .2s;">✕</button>
    </div>

    <!-- Scrollable body -->
    <div style="overflow-y:auto;padding:22px 26px;flex:1;">

      <p style="font-size:13.5px;color:#527060;margin:0 0 20px;background:#edf7f0;border:1px solid #cce8d4;border-radius:10px;padding:11px 14px;display:flex;align-items:flex-start;gap:8px;">
        <i class="fas fa-info-circle" style="color:#40916c;margin-top:2px;flex-shrink:0;"></i>
        Both users must select <strong>&nbsp;one item each&nbsp;</strong> — the item they are offering in this trade. Confirm is only available when both sides are selected.
      </p>

      <!-- YOUR items -->
      <div style="margin-bottom:22px;">
        <div style="font-size:12px;font-weight:800;color:#527060;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;display:flex;align-items:center;gap:7px;">
          <i class="fas fa-user" style="color:#40916c;"></i> Your Item (what you're offering)
        </div>
        <?php if (empty($myListings)): ?>
          <div style="text-align:center;padding:20px;color:#94b0a0;font-size:13.5px;background:#f5f8f6;border-radius:10px;border:1px dashed #cce8d4;">
            <i class="fas fa-box-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
            You have no active listings.
          </div>
        <?php else: ?>
          <div id="myItemList" style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($myListings as $tl): ?>
            <label class="trade-item-row" data-side="mine" style="display:flex;align-items:center;gap:12px;padding:12px 14px;border:2px solid #daeae0;border-radius:12px;cursor:pointer;transition:all .18s;background:white;">
              <input type="radio" name="my_trade_item" value="<?= $tl['id'] ?>" style="width:18px;height:18px;accent-color:#40916c;flex-shrink:0;" onchange="onTradeSelect(this)">
              <?php if (!empty($tl['image'])): ?>
                <img src="<?= htmlspecialchars($tl['image']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0;border:1px solid #daeae0;">
              <?php else: ?>
                <div style="width:44px;height:44px;background:#edf7f0;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#94b0a0;font-size:18px;"><i class="fas fa-image"></i></div>
              <?php endif; ?>
              <span style="font-weight:600;font-size:14px;color:#253d2f;"><?= htmlspecialchars($tl['title']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- PARTNER items -->
      <div>
        <div style="font-size:12px;font-weight:800;color:#527060;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;display:flex;align-items:center;gap:7px;">
          <i class="fas fa-user-friends" style="color:#40916c;"></i> <?= htmlspecialchars($selectedUser['name']) ?>'s Item (what they're offering)
        </div>
        <?php if (empty($partnerListings)): ?>
          <div style="text-align:center;padding:20px;color:#94b0a0;font-size:13.5px;background:#f5f8f6;border-radius:10px;border:1px dashed #cce8d4;">
            <i class="fas fa-box-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
            <?= htmlspecialchars($selectedUser['name']) ?> has no active listings.
          </div>
        <?php else: ?>
          <div id="partnerItemList" style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($partnerListings as $tl): ?>
            <label class="trade-item-row" data-side="partner" style="display:flex;align-items:center;gap:12px;padding:12px 14px;border:2px solid #daeae0;border-radius:12px;cursor:pointer;transition:all .18s;background:white;">
              <input type="radio" name="partner_trade_item" value="<?= $tl['id'] ?>" style="width:18px;height:18px;accent-color:#40916c;flex-shrink:0;" onchange="onTradeSelect(this)">
              <?php if (!empty($tl['image'])): ?>
                <img src="<?= htmlspecialchars($tl['image']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0;border:1px solid #daeae0;">
              <?php else: ?>
                <div style="width:44px;height:44px;background:#edf7f0;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#94b0a0;font-size:18px;"><i class="fas fa-image"></i></div>
              <?php endif; ?>
              <span style="font-weight:600;font-size:14px;color:#253d2f;"><?= htmlspecialchars($tl['title']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div><!-- end body -->

    <!-- Footer -->
    <div style="padding:16px 26px;border-top:1px solid #daeae0;display:flex;gap:10px;flex-shrink:0;background:#f5f8f6;border-radius:0 0 20px 20px;">
      <button onclick="closeTradeModal()" style="flex:1;padding:12px;border:1.5px solid #daeae0;background:white;color:#527060;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;transition:all .18s;">
        Cancel
      </button>
      <button onclick="submitTrade()" id="tradeSubmitBtn" disabled
        style="flex:2;padding:12px;background:#cce8d4;color:#527060;border:none;border-radius:10px;font-weight:700;font-size:14px;cursor:not-allowed;font-family:inherit;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fas fa-handshake"></i> Select both items first
      </button>
    </div>

  </div>
</div>
<?php endif; ?>

<style>
.trade-item-row:hover { border-color:#a8d4b8 !important; background:#f5fdf8 !important; }
.trade-item-row.selected { border-color:#40916c !important; background:#edf7f0 !important; }
</style>

<script src="assets/js/chat.js"></script>
<script src="assets/js/notification-badges.js"></script>

<script>
// ── Pre-filled message / item preview ──
window.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('messageInput');
    const itemPreviewArea = document.getElementById('itemPreviewArea');
    const itemPreviewImg  = document.getElementById('itemPreviewImg');

    if (window.prefilledImage && itemPreviewArea && itemPreviewImg) {
        itemPreviewImg.src = window.prefilledImage;
        itemPreviewArea.style.display = 'block';
    }
    if (window.prefilledMessage && messageInput) {
        messageInput.focus();
        messageInput.selectionStart = messageInput.selectionEnd = messageInput.value.length;
    }
});

function removeItemPreview() {
    const a = document.getElementById('itemPreviewArea');
    if (a) a.style.display = 'none';
    window.prefilledImage = '';
}

function viewProfile(userId) {
    window.location.href = 'view_profile.php?user_id=' + userId;
}

function deleteChat(userId) {
    if (confirm('DELETE ENTIRE CONVERSATION?\n\nThis cannot be undone. Are you sure?')) {
        document.getElementById('deleteChatUserId').value = userId;
        document.getElementById('deleteChatForm').submit();
    }
}

// ── Trade Modal ──────────────────────────────────────────────
const _partnerId = <?= $selectedUserId ?? 'null' ?>;

function openTradeModal() {
    const modal = document.getElementById('tradeModal');
    if (!modal) return;
    // reset selections
    document.querySelectorAll('.trade-item-row').forEach(r => r.classList.remove('selected'));
    document.querySelectorAll('input[name="my_trade_item"], input[name="partner_trade_item"]').forEach(r => r.checked = false);
    _updateConfirmBtn();
    modal.style.display = 'flex';
    // close dropdown menu
    const m = document.getElementById('chatMenu');
    if (m) m.style.display = 'none';
}

function closeTradeModal() {
    const modal = document.getElementById('tradeModal');
    if (modal) modal.style.display = 'none';
}

// Called whenever any radio changes
function onTradeSelect(radio) {
    // Style the label
    const side = radio.closest('.trade-item-row').dataset.side;
    document.querySelectorAll(`.trade-item-row[data-side="${side}"]`).forEach(r => r.classList.remove('selected'));
    radio.closest('.trade-item-row').classList.add('selected');
    _updateConfirmBtn();
}

function _updateConfirmBtn() {
    const btn = document.getElementById('tradeSubmitBtn');
    if (!btn) return;
    const myPicked      = !!document.querySelector('input[name="my_trade_item"]:checked');
    const partnerPicked = !!document.querySelector('input[name="partner_trade_item"]:checked');
    const both = myPicked && partnerPicked;
    btn.disabled = !both;
    if (both) {
        btn.style.background = 'linear-gradient(135deg,#1a3c2e,#40916c)';
        btn.style.color = 'white';
        btn.style.cursor = 'pointer';
        btn.innerHTML = '<i class="fas fa-handshake"></i> Confirm Trade';
    } else {
        btn.style.background = '#cce8d4';
        btn.style.color = '#527060';
        btn.style.cursor = 'not-allowed';
        const myDone = myPicked ? '✓' : '○';
        const ptDone = partnerPicked ? '✓' : '○';
        btn.innerHTML = `<i class="fas fa-lock"></i> ${myDone} Your item &nbsp;|&nbsp; ${ptDone} Their item`;
    }
}

function submitTrade() {
    const myItem      = document.querySelector('input[name="my_trade_item"]:checked');
    const partnerItem = document.querySelector('input[name="partner_trade_item"]:checked');
    if (!myItem || !partnerItem) {
        alert('Please select one item from each side.');
        return;
    }

    const btn = document.getElementById('tradeSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('mark_traded.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
            my_listing_id:      parseInt(myItem.value),
            partner_listing_id: parseInt(partnerItem.value),
            partner_id:         _partnerId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeTradeModal();
            alert('✅ ' + data.message);
            // Remove traded items from labels
            document.querySelector(`input[name="my_trade_item"][value="${myItem.value}"]`)?.closest('label')?.remove();
            document.querySelector(`input[name="partner_trade_item"][value="${partnerItem.value}"]`)?.closest('label')?.remove();
        } else {
            alert('❌ ' + (data.error || 'Something went wrong.'));
            btn.disabled = false;
            _updateConfirmBtn();
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
        _updateConfirmBtn();
    });
}

// Close modal on backdrop click
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('tradeModal');
    if (modal) {
        modal.addEventListener('click', e => { if (e.target === modal) closeTradeModal(); });
    }
});
</script>
</body>
</html>

<?php $conn->close(); ?>