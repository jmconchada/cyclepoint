<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'] ?? 0;

// ✅ MARK ALL NOTIFICATIONS AS READ WHEN PAGE OPENS
if ($user_id > 0) {
    $markReadStmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    if ($markReadStmt) {
        $markReadStmt->bind_param("i", $user_id);
        $markReadStmt->execute();
        $affected = $markReadStmt->affected_rows;
        $markReadStmt->close();
        error_log("Marked $affected notifications as read for user $user_id");
    }
}

// Fetch user-specific + admin notifications
$sql = "SELECT * FROM notifications 
        WHERE user_id = $user_id OR user_id IS NULL
        ORDER BY created_at DESC";
$result = $conn->query($sql);
$total_count = $result ? $result->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - CyclePoint</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/notifications.css">
</head>
<body>

<!-- HEADER -->
<header class="notif-topbar">
  <div class="notif-topbar-inner">
    <a href="index.php" class="notif-back-btn">
      <i class="fas fa-arrow-left"></i> Back
    </a>
    <h1 class="notif-topbar-title">Notifications</h1>
    <div class="notif-topbar-count">
      <i class="fas fa-bell"></i> <?= $total_count ?>
    </div>
  </div>
</header>

<!-- PAGE BODY -->
<div class="notif-page-body">

  <div class="notif-page-title">
    <h2>All Notifications</h2>
    <span class="total-badge"><?= $total_count ?> total</span>
  </div>

  <div class="notifications-container">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): 
                // Determine icon based on type
                $icon = 'fa-bell';
                $color = 'default';
                
                switch(strtolower($row['type'])) {
                    case 'admin':
                    case 'announcement':
                    case 'admin_announcement':
                        $icon = 'fa-bullhorn';
                        $color = 'admin';
                        break;
                    case 'message':
                    case 'new_message':
                        $icon = 'fa-envelope';
                        $color = 'message';
                        break;
                    case 'item_view':
                        $icon = 'fa-eye';
                        $color = 'view';
                        break;
                    case 'item_interest':
                        $icon = 'fa-heart';
                        $color = 'interest';
                        break;
                    case 'item_approved':
                        $icon = 'fa-check-circle';
                        $color = 'success';
                        break;
                    case 'trade_complete':
                        $icon = 'fa-handshake';
                        $color = 'trade_complete';
                        break;
                    case 'profile_view':
                        $icon = 'fa-user';
                        $color = 'profile';
                        break;
                }
                
                // Time ago function
                $time_diff = time() - strtotime($row['created_at']);
                if ($time_diff < 60) $time_ago = 'Just now';
                elseif ($time_diff < 3600) $time_ago = floor($time_diff / 60) . 'm ago';
                elseif ($time_diff < 86400) $time_ago = floor($time_diff / 3600) . 'h ago';
                elseif ($time_diff < 604800) $time_ago = floor($time_diff / 86400) . 'd ago';
                else $time_ago = date('M d, Y', strtotime($row['created_at']));
            ?>
                <div class="notification-card <?= $color ?>" onclick="openNotificationModal(<?= $row['id'] ?>)">
                    <div class="notification-icon">
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-type"><?= htmlspecialchars(ucfirst($row['type'])) ?></div>
                        <div class="notification-message"><?= htmlspecialchars(substr($row['message'], 0, 100)) ?><?= strlen($row['message']) > 100 ? '...' : '' ?></div>
                        <div class="notification-time">
                            <i class="fas fa-clock"></i>
                            <?= $time_ago ?>
                        </div>
                    </div>
                    <div class="notification-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
                
                <!-- Hidden data for modal -->
                <div id="notification-data-<?= $row['id'] ?>" style="display: none;">
                    <span class="notif-type"><?= htmlspecialchars($row['type']) ?></span>
                    <span class="notif-message"><?= htmlspecialchars($row['message']) ?></span>
                    <span class="notif-date"><?= date('F j, Y \a\t g:i A', strtotime($row['created_at'])) ?></span>
                    <span class="notif-icon"><?= $icon ?></span>
                    <span class="notif-color"><?= $color ?></span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h3>No Notifications Yet</h3>
                <p>You're all caught up! Check back later for updates.</p>
            </div>
        <?php endif; ?>
  </div>
</div>

<!-- Notification Modal -->
<div id="notificationModal" class="modal">
    <div class="modal-overlay" onclick="closeNotificationModal()"></div>
    <div class="modal-content">
        <div class="modal-header" id="modalHeader">
            <div class="modal-icon" id="modalIcon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="modal-title-section">
                <h2 id="modalType">Notification</h2>
                <div class="modal-date" id="modalDate">
                    <i class="fas fa-clock"></i>
                    <span></span>
                </div>
            </div>
            <button class="modal-close" onclick="closeNotificationModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-message" id="modalMessage">
                <!-- Message will be inserted here -->
            </div>
        </div>

    </div>
</div>

<script>
// Pass user login status to JavaScript
const isLoggedIn = <?php echo $user_id > 0 ? 'true' : 'false'; ?>;

// Open notification modal
function openNotificationModal(notificationId) {
    const modal = document.getElementById('notificationModal');
    const data = document.getElementById('notification-data-' + notificationId);
    
    if (!data) return;
    
    // Get data
    const type = data.querySelector('.notif-type').textContent;
    const message = data.querySelector('.notif-message').textContent;
    const date = data.querySelector('.notif-date').textContent;
    const icon = data.querySelector('.notif-icon').textContent;
    const color = data.querySelector('.notif-color').textContent;
    
    // Update modal
    document.getElementById('modalType').textContent = type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ');
    document.getElementById('modalMessage').textContent = message;
    document.getElementById('modalDate').querySelector('span').textContent = date;
    document.getElementById('modalIcon').innerHTML = '<i class="fas ' + icon + '"></i>';
    
    // Update header color
    const modalHeader = document.getElementById('modalHeader');
    modalHeader.className = 'modal-header ' + color;
    
    // Show modal
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

// Close notification modal
function closeNotificationModal() {
    const modal = document.getElementById('notificationModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }, 300);
}

// Close modal on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeNotificationModal();
    }
});
</script>

<script src="assets/js/notification-badges.js"></script>
</body>
</html>